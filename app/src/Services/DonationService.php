<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\DonationRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Panel de donación: normal descuenta, extra no.
 */
final class DonationService
{
    /** Cantidad de donaciones que merece festejo. */
    private const COUNT_MILESTONES = [5, 10, 25, 50, 100, 250, 500, 1000];

    private DonationRepository $repo;

    public function __construct(?DonationRepository $repo = null)
    {
        $this->repo = $repo ?? new DonationRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $support = new FinanceSupportService();
        $period = $support->period($user);
        $data = $support->activeData($user);
        $donationNames = $support->categoryNamesByType($user, 'donacion');
        $percent = $this->activePercent($user, $data);
        $assigned = $support->assignedForType($user, 'donacion');
        $normal = $this->repo->sumPeriodNormal((int) $user['id'], $period['inicio'], $period['fin']);
        $recent = $this->repo->recent((int) $user['id'], 25);

        foreach ($recent as &$row) {
            $row['editable'] = $support->isCurrentPeriodDate((string) $row['fecha'], $user);
        }
        unset($row);

        return [
            'period' => $period,
            'period_total_centavos' => $this->repo->sumPeriod((int) $user['id'], $period['inicio'], $period['fin']),
            'total_centavos' => $this->repo->sumTotal((int) $user['id']),
            'by_cause' => $this->repo->byCause((int) $user['id']),
            'recent' => $recent,
            'has_category' => $percent > 0.001,
            'category_names' => $donationNames,
            'percent' => $percent,
            'assigned_centavos' => $assigned,
            'normal_centavos' => $normal,
            'available_centavos' => max(0, $assigned - $normal),
            'over_percentage' => $normal > $assigned,
            'currency' => (string) ($user['moneda_activa'] ?? 'MXN'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function donate(
        array $user,
        int $amount,
        ?string $cause,
        string $date,
        bool $extra,
        ?string $currency = null
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        $support = new FinanceSupportService();
        $utc = $support->toUtc($date, $user);
        $this->assertCurrent($user, $utc);

        if (!$extra && $this->activePercent($user, $support->activeData($user)) <= 0.001) {
            throw new RuntimeException('Tu método no tiene donación activa. Actívala o registra una donación extra.');
        }

        $currencyInput = trim((string) ($currency ?? ''));
        $currency = (new CurrencyService())->validateCode(
            $currencyInput !== '' ? $currencyInput : (string) ($user['moneda_activa'] ?? 'MXN')
        );
        $before = $this->repo->recordStats((int) $user['id']);
        $id = $this->repo->create(
            (int) $user['id'],
            $amount,
            $currency,
            $cause !== null && trim($cause) !== '' ? mb_substr(trim($cause), 0, 120) : null,
            $extra,
            $utc->format('Y-m-d H:i:s')
        );

        $period = $support->period($user);
        $assigned = $support->assignedForType($user, 'donacion');
        $normal = $this->repo->sumPeriodNormal((int) $user['id'], $period['inicio'], $period['fin']);
        $created = $this->repo->find($id, (int) $user['id']);
        $principal = (int) ($created['monto_principal_centavos'] ?? $amount);

        return [
            'assigned_centavos' => $assigned,
            'normal_centavos' => $normal,
            'available_centavos' => max(0, $assigned - $normal),
            'over_percentage' => $normal > $assigned,
            'celebration' => [
                'amount_centavos' => $amount,
                'currency' => $currency,
                'count' => $before['count'] + 1,
                'total_centavos' => $this->repo->sumTotal((int) $user['id']),
                'records' => self::records($before, $principal, $utc->format('Y-m')),
            ],
        ];
    }

    /**
     * Récords que rompe una donación nueva, comparada con el historial previo.
     *
     * @param array{count: int, max: int, months: array<string, int>} $before
     * @return list<string>
     */
    public static function records(array $before, int $principal, string $month): array
    {
        $count = $before['count'] + 1;

        if ($before['count'] === 0) {
            return ['first'];
        }

        $records = [];

        if ($principal > $before['max']) {
            $records[] = 'biggest';
        }

        $monthBefore = $before['months'][$month] ?? 0;
        $others = $before['months'];
        unset($others[$month]);
        $bestOther = $others === [] ? 0 : max($others);

        // Solo al cruzar la marca: las donaciones siguientes del mismo mes no repiten el festejo.
        if ($bestOther > 0 && $monthBefore <= $bestOther && $monthBefore + $principal > $bestOther) {
            $records[] = 'best_month';
        }

        if (in_array($count, self::COUNT_MILESTONES, true)) {
            $records[] = 'milestone';
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function activate(array $user, float $percent): void
    {
        (new MethodService())->activateDonation($user, $percent);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function updateDonation(array $user, int $id, int $amount, ?string $cause, bool $extra, string $date): void
    {
        $row = $this->repo->find($id, (int) $user['id']);
        $support = new FinanceSupportService();

        if ($row === null) {
            throw new RuntimeException('Esa donación no existe.');
        }

        if (!$support->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Esa donación ya no se puede editar.');
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
            $cause !== null && trim($cause) !== '' ? mb_substr(trim($cause), 0, 120) : null,
            $extra,
            $utc->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    public function deleteDonation(array $user, int $id): void
    {
        $row = $this->repo->find($id, (int) $user['id']);
        $support = new FinanceSupportService();

        if ($row === null) {
            throw new RuntimeException('Esa donación no existe.');
        }

        if (!$support->isCurrentPeriodDate((string) $row['fecha'], $user)) {
            throw new RuntimeException('Esa donación ya no se puede borrar.');
        }

        $this->repo->delete($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed>|null $data
     */
    private function activePercent(array $user, ?array $data): float
    {
        if ($data === null) {
            return 0.0;
        }

        $percent = 0.0;

        foreach ($data['method']['categorias'] as $category) {
            if ((string) $category['tipo'] === 'donacion') {
                $percent += (float) ($data['percentages'][(string) $category['nombre']] ?? 0);
            }
        }

        return $percent;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function assertCurrent(array $user, \DateTimeImmutable $date): void
    {
        $period = (new FinanceSupportService())->period($user);

        if ($date < $period['inicio_dt'] || $date > $period['fin_dt']) {
            throw new RuntimeException('Solo puedes registrar movimientos en el período actual.');
        }
    }
}
