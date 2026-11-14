<?php

declare(strict_types=1);

/*
 * Configuración de períodos.
 *
 * Semanal: lunes a domingo. Anual: año calendario. El día de inicio solo
 * aplica al período mensual.
 */

return [
    'default_start_day' => 1,
    'week_starts_on' => 1, // 1 = lunes (ISO-8601)
    'default_recurrence' => 'mensual',
];
