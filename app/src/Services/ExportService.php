<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Services\Export\PdfWriter;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Exportación completa de la cuenta.
 *
 * El ZIP se construye en un archivo temporal, se transmite y se elimina. Nunca
 * queda almacenado en storage/.
 */
final class ExportService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function stream(array $user): never
    {
        $path = $this->build($user);

        try {
            $filename = 'cauce-export-' . gmdate('Ymd-His') . '.zip';
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, max-age=0');
            header('Pragma: no-cache');
            readfile($path);
        } finally {
            @unlink($path);
        }

        exit;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function build(array $user): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP no está disponible.');
        }

        $path = tempnam(sys_get_temp_dir(), 'cauce-export-');

        if ($path === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            throw new RuntimeException('No se pudo crear el ZIP.');
        }

        try {
            $this->addDataFiles($zip, $user);
            $zip->addFromString('resumen.pdf', $this->summaryPdf($user));
            $this->addPhotos($zip, $user);
            $this->addStatements($zip, $user);
            $zip->addFromString('LEEME.txt', $this->readme($user));
            $zip->close();
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($path);

            throw $exception;
        }

        return $path;
    }

    private function addDataFiles(ZipArchive $zip, array $user): void
    {
        $userId = (int) $user['id'];

        $direct = [
            'ingresos' => 'SELECT * FROM ingresos WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'asignaciones' => 'SELECT * FROM asignaciones WHERE user_id = ? ORDER BY id ASC',
            'gastos' => 'SELECT * FROM gastos WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'movimientos' => 'SELECT * FROM movimientos WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'no_controlado' => 'SELECT * FROM no_controlado WHERE user_id = ? ORDER BY periodo_inicio ASC, id ASC',
            'aportaciones_ahorro' => 'SELECT * FROM aportaciones_ahorro WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'retiros_ahorro' => 'SELECT * FROM retiros_ahorro WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'cuentas_ahorro' => 'SELECT * FROM cuentas_ahorro WHERE user_id = ? ORDER BY created_at ASC, id ASC',
            'transferencias_ahorro' => 'SELECT * FROM transferencias_ahorro WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'aportaciones_inversion' => 'SELECT * FROM aportaciones_inversion WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'retiros_inversion' => 'SELECT * FROM retiros_inversion WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'instrumentos_inversion' => 'SELECT * FROM instrumentos_inversion WHERE user_id = ? ORDER BY id ASC',
            'inversiones_posiciones' => 'SELECT * FROM inversiones_posiciones WHERE user_id = ? ORDER BY created_at ASC, id ASC',
            'inversiones_precios' => 'SELECT * FROM inversiones_precios WHERE simbolo_proveedor IN (SELECT simbolo_proveedor FROM inversiones_posiciones WHERE user_id = ?) ORDER BY fecha ASC, id ASC',
            'donaciones' => 'SELECT * FROM donaciones WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'metas' => 'SELECT * FROM metas_ahorro WHERE user_id = ? ORDER BY created_at ASC, id ASC',
            'deudas' => 'SELECT * FROM deudas WHERE user_id = ? ORDER BY created_at ASC, id ASC',
            'pagos_deuda' => 'SELECT * FROM pagos_deuda WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'deseos' => 'SELECT * FROM lista_deseos WHERE user_id = ? ORDER BY created_at ASC, id ASC',
            'tags' => 'SELECT * FROM tags WHERE user_id = ? ORDER BY id ASC',
            'subcategorias' => 'SELECT * FROM subcategorias WHERE user_id = ? ORDER BY id ASC',
            'recurrentes' => 'SELECT * FROM gastos_recurrentes WHERE user_id = ? ORDER BY id ASC',
            'plantillas' => 'SELECT * FROM plantillas_gasto WHERE user_id = ? ORDER BY id ASC',
            'pausas_inversion' => 'SELECT * FROM pausas_inversion WHERE user_id = ? ORDER BY id ASC',
            'resumenes_mensuales' => 'SELECT * FROM resumenes_mensuales WHERE user_id = ? ORDER BY periodo_inicio ASC, id ASC',
            'patrimonio_snapshots' => 'SELECT * FROM patrimonio_snapshots WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'score_snapshots' => 'SELECT * FROM score_snapshots WHERE user_id = ? ORDER BY fecha ASC, id ASC',
            'rachas' => 'SELECT * FROM rachas WHERE user_id = ?',
            'niveles' => 'SELECT * FROM niveles WHERE user_id = ?',
            'retos' => 'SELECT * FROM retos WHERE user_id = ? ORDER BY periodo_inicio ASC, id ASC',
            'hitos' => 'SELECT * FROM hitos_usuario WHERE user_id = ? ORDER BY fecha_logro ASC, id ASC',
            'usuario_metodos' => 'SELECT * FROM usuario_metodos WHERE user_id = ? ORDER BY vigente_desde ASC, id ASC',
        ];

        foreach ($direct as $name => $query) {
            $statement = $this->db->prepare($query);
            $statement->execute([$userId]);
            $rows = $statement->fetchAll();
            $headers = $rows !== [] ? array_keys($rows[0]) : $this->columns($this->tableFor($name));
            $zip->addFromString('datos/' . $name . '.csv', $this->csv($headers, $rows));
        }

        $gastoTags = $this->db->prepare(
            'SELECT gt.* FROM gasto_tags gt JOIN gastos g ON g.id = gt.gasto_id WHERE g.user_id = ? ORDER BY gt.gasto_id ASC'
        );
        $gastoTags->execute([$userId]);
        $rows = $gastoTags->fetchAll();
        $zip->addFromString('datos/gasto_tags.csv', $this->csv(
            $rows !== [] ? array_keys($rows[0]) : ['gasto_id', 'tag_id'],
            $rows
        ));

        $justifications = $this->db->prepare(
            'SELECT j.* FROM justificaciones j
             JOIN asignaciones a ON a.id = j.asignacion_id
             WHERE a.user_id = ? ORDER BY j.id ASC'
        );
        $justifications->execute([$userId]);
        $rows = $justifications->fetchAll();
        $zip->addFromString('datos/justificaciones.csv', $this->csv(
            $rows !== [] ? array_keys($rows[0]) : ['id', 'asignacion_id', 'motivo_catalogo', 'texto', 'created_at', 'updated_at'],
            $rows
        ));

        $zip->addFromString('datos/configuracion.csv', $this->configurationCsv($user));
    }

    private function addPhotos(ZipArchive $zip, array $user): void
    {
        $statement = $this->db->prepare(
            "SELECT id, foto_path FROM gastos WHERE user_id = ? AND foto_path IS NOT NULL AND foto_path <> ''"
        );
        $statement->execute([(int) $user['id']]);
        $seen = [];

        foreach ($statement->fetchAll() as $row) {
            $relative = (string) $row['foto_path'];
            $absolute = base_path('storage/uploads/gastos/' . ltrim($relative, '/'));

            if (!is_file($absolute) || isset($seen[$absolute])) {
                continue;
            }

            $seen[$absolute] = true;
            $extension = pathinfo($absolute, PATHINFO_EXTENSION) ?: 'jpg';
            $zip->addFile($absolute, 'fotos/gasto-' . (int) $row['id'] . '.' . $extension);
        }
    }

    private function addStatements(ZipArchive $zip, array $user): void
    {
        $statement = $this->db->prepare(
            'SELECT * FROM estados_cuenta WHERE user_id = ? ORDER BY fecha_subida ASC, id ASC'
        );
        $statement->execute([(int) $user['id']]);

        foreach ($statement->fetchAll() as $row) {
            $absolute = base_path('storage/estados_cuenta/' . ltrim((string) $row['ruta_archivo'], '/'));

            if (!is_file($absolute)) {
                continue;
            }

            $name = str_replace(['/', '\\'], '-', (string) $row['nombre_original']);
            $zip->addFile($absolute, 'estados_cuenta/' . (int) $row['id'] . '-' . $name);
        }
    }

    private function summaryPdf(array $user): string
    {
        $userId = (int) $user['id'];
        $pdf = new PdfWriter();
        $pdf->title(__('privacy.export.pdf_title'));
        $pdf->keyValue(__('common.fields.alias'), (string) $user['alias']);
        $pdf->keyValue(__('common.fields.email'), (string) $user['email']);
        $pdf->keyValue(__('privacy.export.generated_at'), gmdate('Y-m-d H:i') . ' UTC');
        $pdf->spacer(8);

        $pdf->heading(__('privacy.export.summary_title'));
        $pdf->keyValue(__('privacy.export.income_total'), format_money($this->sum('ingresos', 'monto_principal_centavos', $userId), (string) $user['moneda_activa']));
        $pdf->keyValue(__('privacy.export.expense_total'), format_money($this->sum('gastos', 'monto_principal_centavos', $userId), (string) $user['moneda_activa']));
        $pdf->keyValue(__('privacy.export.savings_total'), format_money(
            $this->sum('aportaciones_ahorro', 'monto_principal_centavos', $userId) - $this->sum('retiros_ahorro', 'monto_principal_centavos', $userId),
            (string) $user['moneda_activa']
        ));
        $pdf->keyValue(__('privacy.export.investment_total'), format_money(
            $this->sum('aportaciones_inversion', 'monto_principal_centavos', $userId) - $this->sum('retiros_inversion', 'monto_principal_centavos', $userId),
            (string) $user['moneda_activa']
        ));
        $pdf->keyValue(__('privacy.export.donation_total'), format_money($this->sum('donaciones', 'monto_principal_centavos', $userId), (string) $user['moneda_activa']));
        $pdf->keyValue(__('privacy.export.statement_count'), (string) $this->count('estados_cuenta', $userId));
        $pdf->spacer(8);

        $pdf->heading(__('privacy.export.goals_title'));
        $goals = $this->db->prepare(
            'SELECT nombre, objetivo_centavos, moneda, activa FROM metas_ahorro WHERE user_id = ? ORDER BY activa DESC, id ASC'
        );
        $goals->execute([$userId]);
        $rows = [];

        foreach ($goals->fetchAll() as $goal) {
            $rows[] = [
                (string) $goal['nombre'],
                format_money((int) $goal['objetivo_centavos'], (string) $goal['moneda']),
                !empty($goal['activa']) ? __('common.yes') : __('common.no'),
            ];
        }

        if ($rows === []) {
            $pdf->text(__('privacy.export.empty'));
        } else {
            $pdf->table([
                __('privacy.export.goal_name'),
                __('privacy.export.goal_target'),
                __('privacy.export.goal_active'),
            ], $rows, [260, 150, 90]);
        }

        $pdf->spacer(6);
        $pdf->heading(__('privacy.export.wishes_title'));
        $wishes = $this->db->prepare(
            "SELECT nombre, monto_estimado_centavos, moneda, estado FROM lista_deseos WHERE user_id = ? ORDER BY id ASC"
        );
        $wishes->execute([$userId]);
        $rows = [];

        foreach ($wishes->fetchAll() as $wish) {
            $rows[] = [
                (string) $wish['nombre'],
                format_money((int) $wish['monto_estimado_centavos'], (string) $wish['moneda']),
                __((string) $wish['estado'] === 'activo' ? 'wishlist.states.activo' : ((string) $wish['estado'] === 'comprado' ? 'wishlist.states.comprado' : 'wishlist.states.descartado')),
            ];
        }

        if ($rows === []) {
            $pdf->text(__('privacy.export.empty'));
        } else {
            $pdf->table([
                __('privacy.export.wish_name'),
                __('privacy.export.wish_amount'),
                __('common.admin.state'),
            ], $rows, [260, 150, 90]);
        }

        $pdf->spacer(10);
        $pdf->text(__('privacy.export.pdf_footer'), 9);

        return $pdf->output();
    }

    private function configurationCsv(array $user): string
    {
        $safeKeys = [
            'alias', 'email', 'moneda_activa', 'zona_horaria', 'idioma', 'rol', 'estado',
            'tono_notificaciones', 'gamificacion_activa', 'comparacion_social', 'modo_discreto',
            'onboarding_completado', 'periodo_inicio_dia', 'tipo_ingreso', 'recurrencia',
            'tema_modo', 'tema_color_principal', 'tema_enfoque', 'tema_efecto_fondo',
            'estrategia_deuda', 'analytics_opt_out', 'created_at', 'updated_at',
        ];
        $rows = [];

        foreach ($safeKeys as $key) {
            $rows[] = [
                'seccion' => 'user',
                'clave' => $key,
                'valor' => (string) ($user[$key] ?? ''),
            ];
        }

        $notifications = $this->db->prepare(
            'SELECT tipo, activo, hora, frecuencia FROM notificaciones_config WHERE user_id = ? ORDER BY tipo ASC'
        );
        $notifications->execute([(int) $user['id']]);

        foreach ($notifications->fetchAll() as $row) {
            $rows[] = [
                'seccion' => 'notificacion',
                'clave' => (string) $row['tipo'],
                'valor' => json_encode($row, JSON_UNESCAPED_UNICODE) ?: '',
            ];
        }

        $methods = $this->db->prepare(
            'SELECT um.*, m.codigo, m.nombre
             FROM usuario_metodos um JOIN metodos m ON m.id = um.metodo_id
             WHERE um.user_id = ? ORDER BY um.vigente_desde ASC, um.id ASC'
        );
        $methods->execute([(int) $user['id']]);

        foreach ($methods->fetchAll() as $row) {
            $rows[] = [
                'seccion' => 'metodo',
                'clave' => (string) $row['codigo'],
                'valor' => json_encode($row, JSON_UNESCAPED_UNICODE) ?: '',
            ];
        }

        return $this->csv(['seccion', 'clave', 'valor'], $rows);
    }

    private function readme(array $user): string
    {
        $lines = [
            __('privacy.export.readme_title'),
            '',
            __('privacy.export.readme_intro'),
            '',
            __('privacy.export.readme_generated') . ': ' . gmdate('Y-m-d H:i:s') . ' UTC',
            __('common.fields.alias') . ': ' . (string) $user['alias'],
            '',
            __('privacy.export.readme_contents'),
            '- datos/*.csv',
            '- resumen.pdf',
            '- estados_cuenta/*.pdf',
            '- fotos/*',
            '',
            __('privacy.export.readme_no_copy'),
            '',
            __('privacy.export.readme_security'),
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, mixed>> $rows
     */
    private function csv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            $values = [];

            foreach ($headers as $header) {
                $value = $row[$header] ?? '';

                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }

                $value = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);

                if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    // Evita que una hoja de cálculo interprete texto libre como fórmula.
                    $value = "'" . $value;
                }

                $values[] = $value;
            }

            fputcsv($handle, $values, ',', '"', '\\');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }

    private function sum(string $table, string $column, int $userId): int
    {
        $statement = $this->db->prepare("SELECT COALESCE(SUM({$column}), 0) FROM {$table} WHERE user_id = ?");
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    private function count(string $table, int $userId): int
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = ?");
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        $statement = $this->db->query("SELECT * FROM {$table} LIMIT 0");
        $columns = [];

        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);
            $columns[] = (string) ($meta['name'] ?? ('col_' . $i));
        }

        return $columns;
    }

    private function tableFor(string $name): string
    {
        return match ($name) {
            'metas' => 'metas_ahorro',
            'deseos' => 'lista_deseos',
            'recurrentes' => 'gastos_recurrentes',
            'plantillas' => 'plantillas_gasto',
            'resumenes_mensuales' => 'resumenes_mensuales',
            'hitos' => 'hitos_usuario',
            default => $name,
        };
    }
}
