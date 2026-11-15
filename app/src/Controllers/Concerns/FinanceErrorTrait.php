<?php

declare(strict_types=1);

namespace Cauce\Controllers\Concerns;

use Cauce\Helpers\Flash;
use Throwable;

trait FinanceErrorTrait
{
    private function flashFinanceError(Throwable $exception): void
    {
        $message = $exception->getMessage();
        $lower = mb_strtolower($message);

        $key = match (true) {
            $message === 'sin_tasa' => 'currency.errors.no_rate',
            // Los servicios nuevos lanzan directamente la clave de traducción.
            str_starts_with($message, 'investment.errors.'),
            str_starts_with($message, 'savings.errors.'),
            str_starts_with($message, 'sharing.errors.') => $message,
            str_contains($lower, 'período actual') => 'finance.errors.closed',
            str_contains($lower, 'fecha no es válida') => 'finance.errors.date',
            str_contains($lower, 'movimiento no existe'),
            str_contains($lower, 'donación no existe') => 'finance.errors.movement_not_found',
            str_contains($lower, 'ya no se puede') => 'finance.errors.closed',
            str_contains($lower, 'retirar más'),
            str_contains($lower, 'supera el acumulado'),
            str_contains($lower, 'supera el disponible') => 'finance.errors.withdraw_exceeds',
            str_contains($lower, 'meta inactiva') => 'finance.errors.inactive_meta',
            str_contains($lower, 'meta no existe') => 'finance.errors.meta_not_found',
            str_contains($lower, 'fondo de emergencia no existe') => 'finance.errors.emergency_not_found',
            str_contains($lower, 'fondo de emergencia se actualiza') => 'finance.errors.emergency_edit',
            str_contains($lower, 'fondo de emergencia no se puede desactivar') => 'finance.errors.emergency_locked',
            str_contains($lower, 'meta necesita un nombre') => 'savings.errors.name',
            str_contains($lower, 'objetivo debe ser mayor') => 'savings.errors.goal',
            str_contains($lower, 'fecha objetivo') => 'savings.errors.date',
            str_contains($lower, 'tipo de meta inválido'),
            str_contains($lower, 'datos de la meta inválidos') => 'savings.errors.type',
            str_contains($lower, 'bolsa'),
            str_contains($lower, 'meta no pertenece') => 'savings.errors.account',
            str_contains($lower, 'transferencia') => 'savings.errors.transfer',
            str_contains($lower, 'no tiene donación') => 'donation.errors.no_category',
            str_contains($lower, 'pausada') => 'investment.errors.paused',
            str_contains($lower, 'instrumento inválido') => 'investment.errors.instrument',
            str_contains($lower, 'destino válido') => 'investment.errors.rebalance_target',
            str_contains($lower, 'destino debe ser distinto') => 'investment.errors.rebalance_same',
            str_contains($lower, 'moneda no válida') => 'currency.errors.invalid',
            str_contains($lower, 'no tiene categoría de inversión') => 'investment.errors.no_category',
            str_contains($lower, 'no hay un método activo') => 'settings.ingreso.no_method',
            str_contains($lower, 'negativo') => 'finance.errors.negative',
            str_contains($lower, 'monto') => 'finance.errors.amount',
            str_contains($lower, 'motivo') => 'finance.errors.reason',
            str_contains($lower, 'porcentaje') => 'finance.errors.percentage',
            default => 'finance.errors.generic',
        };

        Flash::error($key);
    }
}
