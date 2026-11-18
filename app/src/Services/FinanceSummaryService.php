<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\DonationRepository;
use Cauce\Repositories\InvestmentRepository;
use Cauce\Repositories\SavingsRepository;

/**
 * Resumen de ahorro, inversión, donación y deudas para Inicio.
 */
final class FinanceSummaryService
{
    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function forUser(array $user): array
    {
        $savings = new SavingsRepository();
        $investments = new InvestmentRepository();
        $donations = new DonationRepository();
        $support = new FinanceSupportService();
        $userId = (int) $user['id'];
        $emergency = (new EmergencyFundService())->ensure($user);
        $emergencyId = (int) ($emergency['meta']['id'] ?? 0);
        $metasTotals = $savings->totalsByMeta($userId);
        $emergencySaved = 0;

        if ($emergencyId > 0) {
            $emergencySaved = (int) ($metasTotals[$emergencyId]['aportado'] ?? 0)
                - (int) ($metasTotals[$emergencyId]['retirado'] ?? 0);
        }

        $emergencyTarget = (int) ($emergency['meta']['objetivo_principal_centavos'] ?? $emergency['meta']['objetivo_centavos'] ?? 0);
        $savingsTotal = $savings->sumAllContributions($userId) - $savings->sumAllWithdrawals($userId);
        $investmentTotals = $investments->totalsByInstrument($userId);
        $investmentValue = (int) ((new InvestmentService())->valuation($user)['value_centavos'] ?? array_sum($investmentTotals));
        $period = $support->period($user);
        $donationPeriod = $donations->sumPeriod($userId, $period['inicio'], $period['fin']);
        $donationAssigned = $support->assignedForType($user, 'donacion');
        $debtOriginal = 0;
        $debtBalance = 0;
        $debtActive = 0;

        foreach ((new DebtRepository())->allForUser($userId) as $debt) {
            $original = max(0, (int) ($debt['monto_total_principal_centavos'] ?? $debt['monto_total_centavos']));
            $debtOriginal += $original;

            if ((int) $debt['activa'] === 1) {
                $debtBalance += max(0, (int) ($debt['saldo_actual_principal_centavos'] ?? $debt['saldo_actual_centavos']));
                $debtActive++;
            }
        }

        return [
            'savings' => [
                'total_centavos' => $savingsTotal,
                'emergency_target_centavos' => $emergencyTarget,
                'emergency_saved_centavos' => max(0, $emergencySaved),
                'progress' => $emergencyTarget > 0
                    ? min(100, (int) round(($emergencySaved / $emergencyTarget) * 100))
                    : 0,
                'notify_emergency' => (bool) ($emergency['notify'] ?? false),
            ],
            'investment' => [
                'total_centavos' => $investmentValue,
            ],
            'donation' => [
                'period_centavos' => $donationPeriod,
                'assigned_centavos' => $donationAssigned,
                'progress' => $donationAssigned > 0
                    ? min(100, (int) round(($donationPeriod / $donationAssigned) * 100))
                    : 0,
            ],
            'debt' => [
                'balance_centavos' => $debtBalance,
                'active' => $debtActive,
                'progress' => $debtOriginal > 0
                    ? min(100, (int) round((max(0, $debtOriginal - $debtBalance) / $debtOriginal) * 100))
                    : 0,
            ],
        ];
    }
}
