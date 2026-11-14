<?php

declare(strict_types=1);

return [
    'amount' => 'Monto',
    'date' => 'Fecha',
    'reason' => 'Motivo',
    'reason_text' => 'Detalle (opcional)',
    'available' => 'Disponible',
    'over_percentage' => 'Aportación por encima del %',
    'confirm_delete' => '¿Eliminar este movimiento?',
    'flash' => [
        'over_percentage' => 'El movimiento supera el % asignado del período. Quedó marcado como aportación por encima del %.',
    ],
    'errors' => [
        'closed' => 'Solo puedes editar movimientos del período actual.',
        'withdraw_exceeds' => 'El retiro supera el acumulado disponible.',
        'inactive_meta' => 'No se puede aportar a una meta inactiva.',
        'meta_not_found' => 'La meta no existe o no te pertenece.',
        'movement_not_found' => 'El movimiento no existe o no te pertenece.',
        'emergency_locked' => 'El fondo de emergencia no se puede desactivar.',
        'emergency_not_found' => 'El fondo de emergencia no existe.',
        'emergency_edit' => 'El fondo de emergencia se actualiza desde su propio botón.',
        'date' => 'Elige una fecha válida.',
        'negative' => 'El ajuste dejaría el saldo en negativo.',
        'amount' => 'Ingresa un monto mayor que cero.',
        'reason' => 'Elige un motivo válido.',
        'percentage' => 'El porcentaje no es válido.',
        'generic' => 'No se pudo guardar el movimiento.',
    ],
];
