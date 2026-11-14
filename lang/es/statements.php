<?php

declare(strict_types=1);

return [
    'badge' => 'Herramientas',
    'title' => 'Estado de cuenta',
    'subtitle' => 'Guarda tus estados de cuenta en PDF para consultarlos cuando quieras. Solo tú puedes verlos.',
    'notice' => [
        'title' => 'Antes de subir archivos',
        'body' => 'Subir estados de cuenta es TU decisión. No estás obligado. La información es TU responsabilidad. La app solo la guarda para tu consulta. Asegúrate de que los archivos no contengan información que no quieras almacenar. Puedes eliminar cualquier archivo cuando quieras.',
        'version' => 'Aviso versión :version.',
    ],
    'consent' => [
        'title' => 'Consentimiento para guardar archivos',
        'hint' => 'Registraremos la fecha, la hora y la versión de este aviso. No analizamos ni extraemos información de tus PDFs.',
        'checkbox' => 'Entiendo y acepto el aviso. Autorizo que la app guarde estos archivos para mi consulta.',
        'submit' => 'Aceptar y habilitar',
    ],
    'auth' => [
        'title' => 'Confirma tu contraseña',
        'hint' => 'Por seguridad, vuelve a escribir tu contraseña para entrar a Estado de cuenta.',
        'submit' => 'Entrar',
    ],
    'upload' => [
        'title' => 'Subir un estado de cuenta',
        'hint' => 'Solo PDF, máximo :max y :files archivos por cuenta. No se extrae información del archivo.',
        'file' => 'Archivo PDF',
        'type' => 'Tipo',
        'submit' => 'Subir archivo',
    ],
    'usage' => [
        'title' => 'Archivos guardados',
        'files' => 'de :max permitidos',
    ],
    'list' => [
        'title' => 'Tus estados de cuenta',
        'empty' => 'Todavía no has subido estados de cuenta.',
        'name' => 'Nombre',
        'size' => 'Tamaño',
        'date' => 'Fecha',
        'type' => 'Tipo',
        'confirm_delete' => '¿Eliminar este archivo? Esta acción no se puede deshacer.',
    ],
    'types' => [
        'banco' => 'Banco',
        'inversion' => 'Inversión',
    ],
    'errors' => [
        'desktop_only' => 'Estado de cuenta solo está disponible en computadora de escritorio.',
        'consent_required' => 'Debes aceptar el aviso antes de subir archivos.',
        'invalid_password' => 'La contraseña no es correcta.',
        'reauth_required' => 'Vuelve a confirmar tu contraseña para continuar.',
        'upload_failed' => 'No se pudo subir el archivo: :message',
        'not_found' => 'El estado de cuenta no existe o no te pertenece.',
    ],
    'flash' => [
        'consent_saved' => 'Aviso aceptado. Ya puedes subir archivos.',
        'uploaded' => 'Estado de cuenta guardado.',
        'deleted' => 'Estado de cuenta eliminado.',
    ],
    'upload_errors' => [
        'no_file' => 'no se recibió ningún archivo.',
        'upload' => 'revisa que pese menos de 10 MB e inténtalo de nuevo.',
        'limit' => 'alcanzaste el límite de 240 estados de cuenta.',
        'extension' => 'el archivo debe tener extensión .pdf.',
        'invalid' => 'el archivo no es válido.',
        'empty' => 'el archivo está vacío.',
        'size' => 'el archivo supera el tamaño máximo de 10 MB.',
        'not_pdf' => 'el archivo no es un PDF válido.',
        'generic' => 'inténtalo de nuevo.',
    ],
];
