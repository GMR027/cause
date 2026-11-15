<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\SharingRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Panel de «Compartir»: lo que la persona comparte con familia, amigos o en
 * regalos contra el % de Compartir de su método (p. ej. Cinco frascos).
 *
 * Igual que Donación: lo normal descuenta del % del período y lo extra no.
 */
final class SharingService
{
    public const TYPE = 'compartir';

    private SharingRepository $repo;

    public function __construct(?SharingRepository $repo = null)
    {
        $this->repo = $repo ?? new SharingRepository();
    }

    /**
     * ¿El método activo del usuario tiene una categoría de Compartir?
     *
     * @param array<string, mixed> $user
     */
    public function applies(array $user): bool
    {
        return $this->activePercent($user) > 0.001;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $userId = (int) $user['id'];
        $assigned = $support->assignedForType($user, self::TYPE);
        $normal = $this->repo->sumPeriodNormal($userId, $period['inicio'], $period['fin']);
        $recent = $this->repo->recent($userId, 25);

        foreach ($recent as &$row) {
            $row['editable'] = $support->isCurrentPeriodDate((string) $row['fecha'], $user);
        }
        unset($row);

        return [
            'period' => $period,
            'has_category' => $this->applies($user),
            'category_names' => $support->categoryNamesByType($user, self::TYPE),
            'percent' => $this->activePercent($user),
            'assigned_centavos' => $assigned,
            'normal_centavos' => $normal,
            'available_centavos' => max(0, $assigned - $normal),
            'over_percentage' => $normal > $assigned,
            'completed' => $assigned > 0 && $normal >= $assigned,
            'period_total_centavos' => $this->repo->sumPeriod($userId, $period['inicio'], $period['fin']),
            'total_centavos' => $this->repo->sumTotal($userId),
            'by_recipient' => $this->repo->byRecipient($userId),
            'recent' => $recent,
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{assigned_centavos: int, normal_centavos: int, over_percentage: bool}
     */
    public function register(
        array $user,
        int $amount,
        ?string $recipient,
        string $date,
        bool $extra,
        ?string $currency = null
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        if (!$extra && !$this->applies($user)) {
            throw new RuntimeException('sharing.errors.no_category');
        }

        $support = new FinanceSupportService();
        $utc = $support->toUtc($date, $user);
        $this->assertCurrent($user, $utc);

        $currencyInput = trim((string) ($currency ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN')
        );

        $this->repo->create(
            (int) $user['id'],
            $amount,
            $currency,
            $this->cleanRecipient($recipient),
            $extra,
            $utc->format('Y-m-d H:i:s')
        );

        return $this->status($user);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function update(array $user, int $id, int $amount, ?string $recipient, bool $extra, string $date): void
    {
        $row = $this->repo->find($id, (int) $user['id']);
        $support = new FinanceSupportService();

        if ($row === null) {
            throw new RuntimeException('sharing.errors.not_found');
        }

        if (!$support->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese registro ya no se puede editar.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $utc = $support->toUtc($date, $user);
        $this->assertCurrent($user, $utc);

        $this->repo->update(
            $id,
            (int) $user['id'],
            $amount,
            $this->cleanRecipient($recipient),
            $extra,
            $utc->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function delete(array $user, int $id): void
    {
        $row = $this->repo->find($id, (int) $user['id']);

        if ($row === null) {
            throw new RuntimeException('sharing.errors.not_found');
        }

        if (!(new FinanceSupportService())->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Ese registro ya no se puede borrar.');
        }

        $this->repo->delete($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{assigned_centavos: int, normal_centavos: int, over_percentage: bool}
     */
    private function status(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $assigned = $support->assignedForType($user, self::TYPE);
        $normal = $this->repo->sumPeriodNormal((int) $user['id'], $period['inicio'], $period['fin']);

        return [
            'assigned_centavos' => $assigned,
            'normal_centavos' => $normal,
            'over_percentage' => $normal > $assigned,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function activePercent(array $user): float
    {
        $data = (new MethodService())->activeData($user);

        if ($data === null) {
            return 0.0;
        }

        $percent = 0.0;

        foreach ($data['method']['categorias'] as $category) {
            if ((string) $category['tipo'] === self::TYPE) {
                $percent += (float) ($data['percentages'][(string) $category['nombre']] ?? 0);
            }
        }

        return $percent;
    }

    private function cleanRecipient(?string $recipient): ?string
    {
        $recipient = trim((string) $recipient);

        return $recipient !== '' ? mb_substr($recipient, 0, 120) : null;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function assertCurrent(array $user, DateTimeImmutable $date): void
    {
        $period = (new FinanceSupportService())->period($user);

        if ($date < $period['inicio_dt'] || $date > $period['fin_dt']) {
            throw new RuntimeException('Solo puedes registrar movimientos en el período actual.');
        }
    }
}
