<?php

declare(strict_types=1);

return [
    'home' => [
        'welcome' => 'Bienvenido, :alias',
        'income' => 'Ingresos del mes',
        'expenses' => 'Gastos del mes',
        'available' => 'Disponible',
        'empty_hint' => 'Aún sin movimientos',
        'period_hint' => 'Del período actual',
        'over' => 'Te pasaste este período',
        'over_hint' => 'Registraste más gastos, aportes y donaciones que tu ingreso. Revisa tus movimientos; si aportaste dinero que ya tenías, márcalo como «ya ahorrado».',
        'available_hint' => 'Ingreso menos gastos, ahorro, inversión, donación y compartir',
        'movements_title' => 'Movimientos del período',
        'movements_body' => 'Consulta y filtra ingresos, gastos, aportes, donaciones y todo lo que pasó en este período.',
        'view_movements' => 'Ver movimientos',
        'method' => 'Tu método',
        'method_period_hint' => 'Calculado con tu ingreso del período: :amount.',
        'method_estimated_hint' => 'Calculado con tu ingreso estimado: :amount.',
        'method_status' => [
            'cumplido' => 'Cumplido',
            'excedido' => 'Excedido',
            'pendiente' => ':used de :assigned',
            'aria' => ':name: :state',
        ],
        'no_method' => 'Sin método activo',
        'no_method_hint' => 'Completa el onboarding para definir cómo repartir tu ingreso.',
    ],
    'movimientos' => [
        'subtitle' => 'Aquí verás tus ingresos, gastos y movimientos.',
        'empty_title' => 'Aún no hay movimientos',
        'empty_body' => 'Registra tu primer ingreso o gasto para empezar a ver tu dinero en movimiento.',
    ],
    'reportes' => [
        'subtitle' => 'Gráficas y análisis de tus finanzas.',
        'empty_title' => 'Aún no hay datos',
        'empty_body' => 'Cuando registres movimientos, aquí aparecerán tus reportes.',
    ],
];
