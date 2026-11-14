<?php

declare(strict_types=1);

return [
    'progress' => 'Paso :current de :total',
    'steps' => [
        'tutorial' => 'Cómo funciona',
        'simulador' => 'Simulador',
        'metodo' => 'Tu método',
        'moneda' => 'Moneda',
        'tipo-ingreso' => 'Tu ingreso',
        'configuracion' => 'Configuración',
        'recurrencia' => 'Recurrencia',
        'primer-ingreso' => 'Primer ingreso',
        'notificaciones' => 'Recordatorios',
    ],
    'tutorial' => [
        'title' => 'Cómo funciona Cauce',
        'start' => 'Empezar',
        'slides' => [
            [
                'title' => 'Cauce reparte tu dinero con intención',
                'body' => 'Cauce toma tu ingreso y lo divide según un método de presupuestación. Tú decides qué porcentaje va a cada parte y ves el resultado antes de mover un peso.',
            ],
            [
                'title' => 'Un método, muchas formas de vivirlo',
                'body' => 'Puedes probar distintos métodos con tu monto real. Compara cómo se reparte tu dinero y elige el que mejor se adapte a tu situación.',
            ],
            [
                'title' => 'Tú controlas',
                'body' => 'Nada se mueve sin tu confirmación. Puedes ajustar porcentajes, cambiar de método o pausar lo que no te sirva. Cauce solo te da estructura.',
            ],
        ],
    ],
    'simulador' => [
        'heading' => 'Prueba tu monto',
        'subtitle' => 'Escribe cuánto recibes y compara cómo lo repartiría cada método.',
        'amount_label' => '¿Cuánto recibes al mes?',
        'amount_hint' => 'Es solo un ejemplo: puedes cambiarlo cuando quieras.',
        'recommended' => 'Recomendado para ti: :method',
        'recommended_badge' => 'Recomendado',
        'cultural_note' => 'Inspirado en la tradición judía del diezmo; adaptación moderna, no precepto religioso.',
        'quiz_button' => '¿No sabes cuál elegir? Responde 3 preguntas',
        'quiz' => [
            'q1' => '¿Tu ingreso es fijo o variable?',
            'q2' => '¿Tienes deudas que quieres pagar primero?',
            'q3' => '¿Qué te importa más ahora?',
            'choose' => 'Elige una opción',
            'submit' => 'Ver mi recomendación',
            'p1' => [
                'fijo' => 'Fijo',
                'variable' => 'Variable',
                'mixto' => 'Mixto',
            ],
            'p2' => [
                'si' => 'Sí',
                'no' => 'No',
                'no_seguro' => 'No estoy seguro',
            ],
            'p3' => [
                'ahorrar' => 'Ahorrar',
                'invertir' => 'Invertir',
                'libertad' => 'Libertad para gastar',
                'donar' => 'Donar',
            ],
        ],
    ],
    'methods' => [
        '50_30_20' => [
            'name' => 'Regla 50/30/20',
            'desc' => 'Distribuye tu ingreso en necesidades, deseos y ahorro.',
        ],
        'seis_sobres' => [
            'name' => 'Seis sobres',
            'desc' => 'Divide tu ingreso en sobres con propósitos específicos.',
        ],
        'cinco_frascos' => [
            'name' => 'Cinco frascos',
            'desc' => 'Reparte el ingreso en cinco frascos de consumo, inversión, ahorro y donación.',
        ],
        '70_20_10' => [
            'name' => 'Regla 70/20/10',
            'desc' => 'Destina 70% a gastos, 20% a ahorro y 10% a deudas o inversión.',
        ],
        'kakebo' => [
            'name' => 'Kakebo',
            'desc' => 'Método japonés de registro consciente del gasto por categorías.',
        ],
    ],
    'metodo' => [
        'heading' => 'Elige tu método',
        'subtitle' => 'Puedes cambiarlo más adelante. Los porcentajes se aplicarán a tu próximo ingreso.',
        'choose' => 'Elegir este método y continuar',
        'recommended_badge' => 'Recomendado para ti',
    ],
    'moneda' => [
        'heading' => '¿En qué moneda te pagan?',
        'subtitle' => 'La usarás para registrar tus montos y ver tus reportes.',
        'mxn' => 'Peso mexicano',
        'usd' => 'Dólar estadounidense',
        'eur' => 'Euro',
    ],
    'tipo_ingreso' => [
        'heading' => '¿Cómo llega tu ingreso?',
        'subtitle' => 'Esto nos ayuda a ajustar el ritmo de tus recordatorios.',
        'fijo' => 'Fijo',
        'fijo_hint' => 'Mismo monto cada periodo',
        'variable' => 'Variable',
        'variable_hint' => 'Cambia mes a mes',
        'mixto' => 'Mixto',
        'mixto_hint' => 'Una base fija más extras',
    ],
    'configuracion' => [
        'heading' => '¿Cuánto quieres configurar ahora?',
        'subtitle' => 'Puedes completar el resto después desde Ajustes.',
        'rapida' => [
            'title' => 'Configuración rápida',
            'desc' => 'Termina ahora con lo esencial. Ideal si quieres empezar a usar Cauce de inmediato.',
        ],
        'completa' => [
            'title' => 'Configuración completa',
            'desc' => 'Define también la recurrencia de tu ingreso para afinar tus reportes.',
        ],
    ],
    'recurrencia' => [
        'heading' => '¿Cada cuánto recibes tu ingreso?',
        'subtitle' => 'Podrás cambiarlo cuando quieras.',
        'diaria' => 'Diaria',
        'semanal' => 'Semanal',
        'mensual' => 'Mensual',
        'anual' => 'Anual',
    ],
    'primer_ingreso' => [
        'heading' => '¿Registramos tu primer ingreso?',
        'subtitle' => 'Si prefieres, puedes hacerlo después desde el botón +.',
        'yes' => 'Sí, registrar ahora',
        'no' => 'Después',
        'hint' => 'Puedes registrarlo ahora o hacerlo después desde el botón +.',
    ],
    'notificaciones' => [
        'heading' => 'Un empujón cuando lo necesites',
        'subtitle' => 'Los recordatorios te ayudan a mantener tu presupuesto al día. Tú decides cuándo activarlos.',
        'reason_1' => 'Recordatorios suaves para registrar tus movimientos.',
        'reason_2' => 'Avisos cuando se acerque el cierre de tu periodo.',
        'reason_3' => 'Puedes desactivarlos en cualquier momento.',
        'activate' => 'Activar recordatorios',
        'not_now' => 'Ahora no',
        'hint' => 'Cauce nunca comparte tus datos con terceros.',
        'status_active' => 'Recordatorios dentro de Cauce activados.',
        'status_push' => 'Recordatorios activados. También activamos las notificaciones de este dispositivo.',
        'status_denied' => 'Los recordatorios dentro de Cauce quedaron activos. Las notificaciones del navegador están bloqueadas; puedes cambiarlo en Ajustes.',
        'status_unsupported' => 'Los recordatorios dentro de Cauce quedaron activos. Este navegador no admite notificaciones push.',
    ],
    'greetings' => [
        'morning' => 'Buenos días',
        'afternoon' => 'Buenas tardes',
        'evening' => 'Buenas noches',
    ],
    'flash' => [
        'completed' => 'Tu onboarding está completo. ¡Bienvenido a Cauce!',
    ],
    'errors' => [
        'amount' => 'Ingresa un monto válido.',
        'method' => 'Elige un método para continuar.',
        'currency' => 'Elige una moneda válida.',
        'income_type' => 'Elige el tipo de ingreso.',
        'configuration' => 'Elige un tipo de configuración.',
        'recurrence' => 'Elige una recurrencia.',
        'quiz' => 'Responde las 3 preguntas para ver tu recomendación.',
        'generic' => 'Ocurrió un error. Inténtalo de nuevo.',
    ],
];
