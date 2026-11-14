<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\SavingsRepository;
use Cauce\Repositories\WishlistRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lista de deseos: intención de compra, impacto y enfriamiento.
 */
final class WishlistService
{
    public const PRIORITIES = [1 => 'alta', 2 => 'media', 3 => 'baja'];

    private WishlistRepository $repo;

    public function __construct(?WishlistRepository $repo = null)
    {
        $this->repo = $repo ?? new WishlistRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user, ?string $state = null): array
    {
        $userId = (int) $user['id'];
        $timezone = $this->timezone($user);
        $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $filter = in_array($state, ['activo', 'comprado', 'descartado'], true) ? $state : null;
        $items = $this->repo->allForUser($userId, $filter);
        $savings = new SavingsRepository();
        $savingsTotal = $savings->sumAllContributions($userId) - $savings->sumAllWithdrawals($userId);

        foreach ($items as &$item) {
            $item['cooling_active'] = self::coolingActive(
                $item['fecha_enfriamiento'] !== null ? (string) $item['fecha_enfriamiento'] : null,
                $today
            );
            $item['impact'] = self::impact($savingsTotal, (int) ($item['monto_estimado_principal_centavos'] ?? $item['monto_estimado_centavos']));
        }
        unset($item);

        return [
            'items' => $items,
            'state' => $filter ?? 'todos',
            'savings_total_centavos' => $savingsTotal,
            'active_count' => $this->repo->activeCount($userId),
            'active_total_centavos' => $this->repo->activeEstimatedTotal($userId),
            'priorities' => self::PRIORITIES,
            'categories' => (new GastoService())->categoriasGasto($user),
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
            'today' => $today,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function create(array $user, array $data): int
    {
        return $this->repo->create($this->normalize($user, $data));
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    public function update(array $user, int $id, array $data): void
    {
        $item = $this->repo->findForUser($id, (int) $user['id']);

        if ($item === null) {
            throw new RuntimeException('El deseo no existe.');
        }

        $normalized = $this->normalize($user, $data);
        $this->repo->update($id, (int) $user['id'], [
            'nombre' => $normalized['nombre'],
            'monto_estimado_centavos' => $normalized['monto_estimado_centavos'],
            'moneda' => $normalized['moneda'],
            'prioridad' => $normalized['prioridad'],
            'fecha_objetivo' => $normalized['fecha_objetivo'],
            'fecha_enfriamiento' => $normalized['fecha_enfriamiento'],
            'nota' => $normalized['nota'],
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function purchase(array $user, int $id, array $data): array
    {
        $item = $this->repo->findForUser($id, (int) $user['id']);

        if ($item === null) {
            throw new RuntimeException('El deseo no existe.');
        }

        if ((string) $item['estado'] !== 'activo') {
            throw new RuntimeException('Ese deseo ya está cerrado.');
        }

        $today = (new DateTimeImmutable('now', $this->timezone($user)))->format('Y-m-d');
        $cooling = self::coolingActive(
            $item['fecha_enfriamiento'] !== null ? (string) $item['fecha_enfriamiento'] : null,
            $today
        );

        if ($cooling && empty($data['skip_cooling'])) {
            throw new RuntimeException('cooling');
        }

        $amount = (int) ($data['monto_centavos'] ?? 0);

        if ($amount <= 0) {
            $amount = (int) $item['monto_estimado_centavos'];
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $gastoId = null;
        $expense = null;

        if (!empty($data['register_expense'])) {
            $category = trim((string) ($data['parte_metodo'] ?? ''));

            if ($category === '') {
                throw new InvalidArgumentException('Elige una categoría para el gasto.');
            }

            $gastoService = new GastoService();
            $period = $gastoService->periodoActual($user);
            $gastos = new GastoRepository();
            $available = max(0,
                $gastos->sumAssignedForPart((int) $user['id'], $category, $period['inicio'], $period['fin'])
                - $gastos->sumSpentForPart((int) $user['id'], $category, $period['inicio'], $period['fin'])
            );
            $itemCurrency = (string) ($item['moneda'] ?? ($user['moneda_activa'] ?? 'MXN'));
            $principalAmount = (new CurrencyService())->toPrincipal(
                $user,
                $amount,
                $itemCurrency,
                (string) ($data['fecha'] ?? $today)
            )['centavos'];
            $payload = [
                'monto_centavos' => $amount,
                'moneda' => (string) ($item['moneda'] ?? ($user['moneda_activa'] ?? 'MXN')),
                'parte_metodo' => $category,
                'fecha' => (string) ($data['fecha'] ?? $today),
                'nota' => mb_substr('Deseo: ' . (string) $item['nombre'], 0, 2000),
            ];

            if ($available <= 0 || $principalAmount > $available) {
                $expense = $gastoService->crearPendienteManual($user, $payload);
            } else {
                $expense = $gastoService->crearManual($user, $payload);
            }

            $gastoId = (int) ($expense['gasto_id'] ?? 0);
        }

        $this->repo->markPurchased(
            $id,
            (int) $user['id'],
            $gastoId !== null && $gastoId > 0 ? $gastoId : null,
            gmdate('Y-m-d H:i:s')
        );

        return [
            'item' => $item,
            'gasto_id' => $gastoId,
            'expense' => $expense,
            'amount_centavos' => $amount,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function discard(array $user, int $id): void
    {
        $item = $this->repo->findForUser($id, (int) $user['id']);

        if ($item === null) {
            throw new RuntimeException('El deseo no existe.');
        }

        if ((string) $item['estado'] !== 'activo') {
            throw new RuntimeException('Ese deseo ya está cerrado.');
        }

        $this->repo->discard($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     * @return array{
     *     user_id: int, nombre: string, monto_estimado_centavos: int, moneda: string,
     *     prioridad: int, fecha_objetivo: ?string, fecha_enfriamiento: ?string, nota: ?string
     * }
     */
    private function normalize(array $user, array $data): array
    {
        $name = trim((string) ($data['nombre'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('El deseo necesita un nombre.');
        }

        $amount = (int) ($data['monto_estimado_centavos'] ?? 0);

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $priority = (int) ($data['prioridad'] ?? 2);

        if (!isset(self::PRIORITIES[$priority])) {
            $priority = 2;
        }

        $rawTargetDate = trim((string) ($data['fecha_objetivo'] ?? ''));
        $targetDate = $this->validDate($rawTargetDate);

        if ($rawTargetDate !== '' && $targetDate === null) {
            throw new InvalidArgumentException('La fecha objetivo no es válida.');
        }

        $coolingDate = $this->coolingDate($user, $data);

        $note = trim((string) ($data['nota'] ?? ''));

        return [
            'user_id' => (int) $user['id'],
            'nombre' => mb_substr($name, 0, 120),
            'monto_estimado_centavos' => $amount,
            'moneda' => (new CurrencyService())->validateCode(
                trim((string) ($data['moneda'] ?? '')) !== ''
                    ? trim((string) $data['moneda'])
                    : (string) ($user['moneda_activa'] ?? 'MXN')
            ),
            'prioridad' => $priority,
            'fecha_objetivo' => $targetDate,
            'fecha_enfriamiento' => $coolingDate,
            'nota' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function coolingDate(array $user, array $data): ?string
    {
        $explicit = $this->validDate((string) ($data['fecha_enfriamiento'] ?? ''));

        if ($explicit !== null) {
            return $explicit;
        }

        $days = (int) ($data['dias_enfriamiento'] ?? 0);

        if (!in_array($days, [7, 15, 30], true)) {
            return null;
        }

        return (new DateTimeImmutable('now', $this->timezone($user)))
            ->modify('+' . $days . ' days')
            ->format('Y-m-d');
    }

    private function validDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value . ' 12:00:00');

            return $date->format('Y-m-d') === $value ? $value : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function timezone(array $user): DateTimeZone
    {
        try {
            return new DateTimeZone((string) ($user['zona_horaria'] ?? config('app.timezone', 'UTC')));
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * @return array{before: int, after: int, amount: int, percent: float, exceeds: bool}
     */
    public static function impact(int $savingsTotal, int $amount): array
    {
        $savingsTotal = max(0, $savingsTotal);
        $amount = max(0, $amount);

        return [
            'before' => $savingsTotal,
            'after' => max(0, $savingsTotal - $amount),
            'amount' => $amount,
            'percent' => $savingsTotal > 0 ? round(($amount / $savingsTotal) * 100, 1) : 0.0,
            'exceeds' => $amount > $savingsTotal,
        ];
    }

    public static function coolingActive(?string $date, string $today): bool
    {
        return $date !== null && trim($date) !== '' && $date >= $today;
    }
}
