<?php

declare(strict_types=1);

return [
    'title' => 'Notificaciones',
    'subtitle' => 'Avisos útiles para que no se te pase nada importante.',
    'badge' => 'Avisos',
    'empty' => 'No tienes notificaciones.',
    'empty_hint' => 'Aquí aparecerán los recordatorios de ingreso, alertas de gasto y resúmenes.',
    'mark_all' => 'Marcar todas como leídas',
    'unread' => ':count sin leer',
    'unread_one' => '1 sin leer',
    'new' => 'Nueva',
    'open' => 'Abrir',
    'view_settings' => 'Configurar notificaciones',
    'types' => [
        'ingreso_dia_menos_1' => 'Recordatorio de ingreso (día anterior)',
        'ingreso_dia_0' => 'Recordatorio de ingreso (día de inicio)',
        'ingreso_rescate' => 'Aviso de ingreso sin registrar',
        'gasto_recurrente' => 'Cargo recurrente',
        'recordatorio_deuda' => 'Recordatorio de deuda',
        'soporte_respuesta' => 'Respuesta de soporte',
        'alerta_categoria' => 'Categoría cerca del límite',
        'resumen_mensual' => 'Resumen mensual',
    ],
    'settings' => [
        'title' => 'Notificaciones',
        'subtitle' => 'Elige qué quieres recibir, cuándo y en qué tono.',
        'config_title' => 'Avisos y horarios',
        'config_hint' => 'Los avisos de ingreso usan la hora que definas. Los gastos recurrentes se avisan al registrarse.',
        'active' => 'Activa',
        'hour' => 'Hora',
        'frequency' => 'Frecuencia',
        'save' => 'Guardar configuración',
        'frequencies' => [
            'una_vez' => 'Una vez',
            'diaria' => 'Diaria',
            'semanal' => 'Semanal',
            'mensual' => 'Mensual',
        ],
        'tone_title' => 'Tono de los avisos',
        'tone_hint' => 'Los avisos críticos siempre usan tono neutral.',
        'tones' => [
            'neutral' => 'Neutral',
            'motivacional' => 'Motivacional',
            'empatico' => 'Empático',
        ],
        'tone_save' => 'Guardar tono',
        'vacation_title' => 'Modo vacaciones',
        'vacation_hint' => 'Pausa los Web Push entre 7 y 30 días. Los avisos dentro de la app siguen disponibles. Al terminar, te avisamos una sola vez.',
        'vacation_active' => 'Push pausado hasta :date.',
        'vacation_none' => 'Sin pausa activa.',
        'pause_7' => '7 días',
        'pause_15' => '15 días',
        'pause_30' => '30 días',
        'pause' => 'Pausar',
        'resume' => 'Reactivar ahora',
        'push_title' => 'Web Push',
        'push_hint' => 'Activa los avisos en este dispositivo. Solo enviamos lo accionable o urgente.',
        'push_enabled' => 'Web Push configurado.',
        'push_disabled' => 'Web Push aún no está configurado en el servidor. Los avisos quedan en la app y en storage/logs/push.log.',
        'push_activate' => 'Activar en este dispositivo',
        'push_subscribed' => ':count dispositivo(s) suscrito(s).',
        'push_ok' => 'Dispositivo suscrito a los avisos.',
        'push_error' => 'No se pudo activar el push.',
        'push_denied' => 'El navegador bloqueó las notificaciones.',
        'push_unsupported' => 'Este navegador no soporta Web Push.',
    ],
    'flash' => [
        'config_saved' => 'Configuración de notificaciones guardada.',
        'tone_saved' => 'Tono actualizado.',
        'vacation_saved' => 'Modo vacaciones activado.',
        'vacation_resumed' => 'Los avisos push se reactivaron.',
    ],
    'errors' => [
        'tone' => 'Elige un tono válido.',
        'vacation_days' => 'Elige una duración de vacaciones válida.',
        'config' => 'Revisa las horas y frecuencias: no son válidas.',
    ],
    'tones' => [
        'motivacional' => '¡Muy bien!',
        'empatico' => 'Sin presión.',
    ],
    'group' => [
        'alerts_80' => [
            'title' => 'Varias categorías al 80 %',
            'body' => ':count categorías están cerca de su límite: :categories.',
        ],
    ],
    'templates' => [
        'ingreso_dia_menos_1' => [
            'title' => 'Tu ingreso llega mañana',
            'body' => 'Mañana empieza tu período. Ten listo el monto para registrarlo a tiempo.',
        ],
        'ingreso_dia_0' => [
            'title' => 'Hoy toca registrar tu ingreso',
            'body' => 'Tu nuevo período ya empezó. Registra el ingreso para repartirlo con tu método.',
        ],
        'ingreso_rescate' => [
            'title' => 'Tu período sigue sin ingreso',
            'body' => 'Pasaron 48 horas desde el inicio. Registra tu ingreso para no perder el hilo.',
        ],
        'gasto_recurrente' => [
            'title' => 'Cargo recurrente: :name',
            'body' => 'Registramos :amount en :part. Revisa que todo esté bien.',
        ],
        'recordatorio_deuda' => [
            'title' => 'Recordatorio de pago: :name',
            'body' => 'Toca pagar :amount de :name. El pago se registra desde Deudas.',
        ],
        'soporte_respuesta' => [
            'title' => 'Respuesta de soporte: :subject',
            'body' => ':preview',
        ],
        'alerta_categoria' => [
            'title' => 'Categoría cerca del límite',
            'body' => 'Llevas :percent % de :category. Ajusta o mueve antes de pasarte.',
        ],
        'resumen_mensual' => [
            'title' => 'Tu resumen del período está listo',
            'body' => 'Revisa :period y anota tu reflexión.',
        ],
        'vacaciones_reactivacion' => [
            'title' => 'Tus avisos push vuelven a estar activos',
            'body' => 'Terminó la pausa. Seguimos cuidando tu método.',
        ],
        'hito_primera_meta' => [
            'title' => '¡Primera meta creada!',
            'body' => 'Ya tienes una meta de ahorro. Un paso a la vez.',
        ],
        'hito_primer_mes_completo' => [
            'title' => 'Primer mes completo',
            'body' => 'Cerraste tu primer período con Cauce. Tu resumen ya está disponible.',
        ],
        'hito_emergencia_50' => [
            'title' => 'Fondo de emergencia al 50 %',
            'body' => 'Llevas la mitad de tu colchón. Sigue así.',
        ],
        'hito_emergencia_100' => [
            'title' => 'Fondo de emergencia completo',
            'body' => 'Completaste tu fondo. Ahora respira con más calma.',
        ],
        'hito_racha_30' => [
            'title' => '30 períodos de constancia',
            'body' => 'Treinta períodos registrando. La constancia es el método.',
        ],
    ],
];
