<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Flash;
use Cauce\Helpers\Logger;
use Cauce\Helpers\View;
use Cauce\Repositories\DebtRepository;
use Cauce\Repositories\GastoRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\PlantillaRepository;
use Cauce\Repositories\RecurringExpenseRepository;
use Cauce\Repositories\SubcategoriaRepository;
use Cauce\Repositories\TagRepository;
use Cauce\Services\AuthService;
use Cauce\Services\GastoService;
use Cauce\Services\PhotoService;
use Throwable;

/**
 * Registro, detalle, edición, borrado y conciliación de gastos.
 */
final class ExpenseController
{
    private GastoService $service;

    public function __construct()
    {
        $this->service = new GastoService();
    }

    public function create(): void
    {
        $user = $this->user();
        $categories = $this->service->categoriasGasto($user);

        if ($categories === []) {
            Flash::error('expenses.errors.no_method');
            redirect('/onboarding');
        }

        $period = $this->service->periodoActual($user);

        if (!(new IngresoRepository())->existsForPeriod((int) $user['id'], $period['inicio'], $period['fin'])) {
            $this->renderBlocked($user, $period);
            return;
        }

        $this->renderForm($user, null);
    }

    public function store(): void
    {
        $user = $this->user();
        $datos = $this->datosFromPost();

        try {
            $photo = (new PhotoService())->store($_FILES['foto'] ?? [], (int) $user['id']);
            $result = $this->service->crearManual($user, $datos, $photo);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo crear el gasto', [
                'user_id' => $user['id'],
                'error' => $exception->getMessage(),
            ]);

            $this->flashError($exception);
            redirect('/gastos/nuevo');
        }

        Flash::success('expenses.flash.created');

        if (!empty($result['excedido'])) {
            Flash::warning('expenses.flash.excess', [
                ':available' => format_money((int) $result['cubierto_centavos'], (string) ($user['moneda_activa'] ?? 'MXN')),
                ':excess' => format_money((int) $result['exceso_centavos'], (string) ($user['moneda_activa'] ?? 'MXN')),
            ]);
        }

        redirect('/movimientos');
    }

    public function show(): void
    {
        $user = $this->user();
        $gasto = $this->findOwnGasto((int) ($_GET['id'] ?? 0), (int) $user['id']);

        if ($gasto === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/movimientos');
        }

        $tags = (new TagRepository())->forGasto((int) $gasto['id']);
        $recurring = $gasto['gasto_recurrente_id'] !== null
            ? (new RecurringExpenseRepository())->findForUser((int) $gasto['gasto_recurrente_id'], (int) $user['id'])
            : null;

        echo View::render('expenses/show', [
            'title' => __('expenses.show.title'),
            'gasto' => $gasto,
            'tags' => $tags,
            'recurring' => $recurring,
            'editable' => $this->service->puedeEditar($gasto, $user),
        ], 'layouts/app');
    }

    public function edit(): void
    {
        $user = $this->user();
        $gasto = $this->findOwnGasto((int) ($_GET['id'] ?? 0), (int) $user['id']);

        if ($gasto === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/movimientos');
        }

        if (!$this->service->puedeEditar($gasto, $user)) {
            Flash::error('expenses.errors.closed');
            redirect('/gastos/ver?id=' . (int) $gasto['id']);
        }

        $this->renderForm($user, $gasto);
    }

    public function update(): void
    {
        $user = $this->user();
        $gastoId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $datos = $this->datosFromPost();
        $nuevaFoto = null;

        if ($this->findOwnGasto($gastoId, (int) $user['id']) === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/movimientos');
        }

        try {
            $nuevaFoto = (new PhotoService())->store($_FILES['foto'] ?? [], (int) $user['id']);
            $this->service->editar(
                $user,
                $gastoId,
                $datos,
                $nuevaFoto,
                !empty($_POST['eliminar_foto'])
            );
        } catch (Throwable $exception) {
            Logger::warning('No se pudo editar el gasto', [
                'user_id' => $user['id'],
                'gasto_id' => $gastoId,
                'error' => $exception->getMessage(),
            ]);

            $this->flashError($exception);
            redirect('/gastos/editar?id=' . $gastoId);
        }

        Flash::success('expenses.flash.updated');
        redirect('/gastos/ver?id=' . $gastoId);
    }

    public function destroy(): void
    {
        $user = $this->user();
        $gastoId = (int) ($_POST['id'] ?? 0);

        if ($this->findOwnGasto($gastoId, (int) $user['id']) === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/movimientos');
        }

        try {
            $this->service->eliminar($user, $gastoId);
        } catch (Throwable $exception) {
            $this->flashError($exception);
            redirect('/gastos/ver?id=' . $gastoId);
        }

        Flash::success('expenses.flash.deleted');
        redirect('/movimientos');
    }

    public function photo(): void
    {
        $user = $this->user();
        $gasto = $this->findOwnGasto((int) ($_GET['id'] ?? 0), (int) $user['id']);

        if ($gasto === null || empty($gasto['foto_path'])) {
            http_response_code(404);
            exit;
        }

        $path = (new PhotoService())->path((string) $gasto['foto_path']);

        if ($path === null) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    public function conciliar(): void
    {
        $user = $this->user();
        $period = $this->service->periodoActual($user);
        $pending = (new GastoRepository())->pendingForUser((int) $user['id']);

        echo View::render('expenses/conciliar', [
            'title' => __('expenses.conciliar.title'),
            'pending' => $pending,
            'period' => $period,
            'hasIncome' => (new IngresoRepository())->existsForPeriod(
                (int) $user['id'],
                $period['inicio'],
                $period['fin']
            ),
        ], 'layouts/app');
    }

    public function conciliarAll(): void
    {
        $user = $this->user();
        $count = $this->service->conciliarTodos($user);
        Flash::success('expenses.flash.conciliated', [':count' => (string) $count]);
        redirect('/inicio');
    }

    public function conciliarOne(): void
    {
        $user = $this->user();

        try {
            $result = $this->service->conciliar($user, (int) ($_POST['id'] ?? 0));
        } catch (Throwable $exception) {
            $this->flashError($exception);
            redirect('/gastos/conciliar');
        }

        if (!empty($result['excedido'])) {
            Flash::warning('expenses.flash.conciliated_excess', [
                ':excess' => format_money((int) $result['exceso_centavos'], (string) ($user['moneda_activa'] ?? 'MXN')),
            ]);
        } else {
            Flash::success('expenses.flash.conciliated_one');
        }

        redirect('/gastos/conciliar');
    }

    public function confirmRecurring(): void
    {
        $user = $this->user();
        $gasto = $this->findOwnGasto((int) ($_POST['id'] ?? 0), (int) $user['id']);

        if ($gasto === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/inicio');
        }

        if ($gasto['gasto_recurrente_id'] === null) {
            Flash::error('expenses.errors.not_recurring');
            redirect('/inicio');
        }

        (new GastoRepository())->confirm((int) $gasto['id'], (int) $user['id']);
        Flash::success('expenses.flash.confirmed');
        redirect('/inicio');
    }

    public function stopRecurring(): void
    {
        $user = $this->user();
        $gasto = $this->findOwnGasto((int) ($_POST['id'] ?? 0), (int) $user['id']);

        if ($gasto === null || $gasto['gasto_recurrente_id'] === null) {
            Flash::error('expenses.errors.not_found');
            redirect('/inicio');
        }

        (new RecurringExpenseRepository())->deactivate((int) $gasto['gasto_recurrente_id'], (int) $user['id']);

        if ($this->service->puedeEditar($gasto, $user)) {
            $this->service->eliminar($user, (int) $gasto['id']);
        }

        Flash::success('expenses.flash.recurring_stopped');
        redirect('/inicio');
    }

    /**
     * @param array<string, mixed>|null $gasto
     */
    private function renderForm(array $user, ?array $gasto): void
    {
        $service = $this->service;
        $categories = $service->categoriasGasto($user);
        $available = $service->disponiblePorParte($user);

        if ($gasto !== null) {
            $part = (string) $gasto['parte_metodo'];

            if (isset($available[$part])) {
                $available[$part]['gastado'] = max(0, $available[$part]['gastado'] - (int) $gasto['monto_centavos']);
                $available[$part]['disponible'] += (int) $gasto['monto_centavos'];
            }
        }

        $templates = (new PlantillaRepository())->allForUser((int) $user['id'], 8);
        $tagRepo = new TagRepository();
        $tags = $tagRepo->allForUser((int) $user['id']);

        foreach ($templates as &$template) {
            $ids = [];

            if (!empty($template['tags_json'])) {
                $decoded = json_decode((string) $template['tags_json'], true);
                $ids = is_array($decoded) ? array_map('intval', $decoded) : [];
            }

            $template['tag_ids'] = $ids;
            $template['tag_names'] = [];

            foreach ($tags as $tag) {
                if (in_array((int) $tag['id'], $ids, true)) {
                    $template['tag_names'][] = $tag['nombre_normalizado'];
                }
            }
        }
        unset($template);

        echo View::render('expenses/form', [
            'title' => $gasto === null ? __('expenses.form.title_create') : __('expenses.form.title_edit'),
            'mode' => $gasto === null ? 'create' : 'edit',
            'gasto' => $gasto,
            'categories' => $categories,
            'period' => $service->periodoActual($user),
            'available' => $available,
            'subcategories' => (new SubcategoriaRepository())->allForUser((int) $user['id']),
            'subcategoryUsage' => (new GastoRepository())->subcategoryUsage((int) $user['id']),
            'tags' => $tags,
            'templates' => $templates,
            'defaultMedium' => $service->sugerirMedioPago((int) $user['id']),
            'debts' => (new DebtRepository())->allForUser((int) $user['id'], true),
            'selectedTags' => $gasto !== null ? $tagRepo->forGasto((int) $gasto['id']) : [],
            'errors' => [],
            'step' => 1,
            'stepTotal' => 1,
            'backUrl' => $gasto === null ? '/inicio' : '/gastos/ver?id=' . (int) $gasto['id'],
        ], 'layouts/flow');
    }

    /**
     * @param array{inicio: string, fin: string} $period
     */
    private function renderBlocked(array $user, array $period): void
    {
        echo View::render('expenses/blocked', [
            'title' => __('expenses.form.title_create'),
            'period' => $period,
            'step' => 1,
            'stepTotal' => 1,
            'backUrl' => '/inicio',
        ], 'layouts/flow');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFromPost(): array
    {
        $shared = !empty($_POST['compartido']);
        $sharedTotal = $shared ? parse_money_cents((string) input('monto_compartido', '')) : null;

        return [
            'monto_centavos' => parse_money_cents((string) input('monto', '')) ?? 0,
            'moneda' => (string) input('moneda', ''),
            'monto_total_compartido_centavos' => $sharedTotal,
            'parte_metodo' => (string) input('parte_metodo', ''),
            'subcategoria_id' => input('subcategoria_id', ''),
            'fecha' => (string) input('fecha', gmdate('Y-m-d')),
            'nota' => (string) input('nota', ''),
            'medio_pago' => (string) input('medio_pago', ''),
            'fuente_exceso' => (string) input('fuente_exceso', 'no_aplica'),
            'deuda_id' => (int) input('deuda_id', '0'),
            'nueva_deuda_nombre' => (string) input('nueva_deuda_nombre', ''),
            'nueva_deuda_tasa' => input('nueva_deuda_tasa'),
            'nueva_deuda_minimo' => input('nueva_deuda_minimo'),
            'nueva_deuda_fecha_limite' => (string) input('nueva_deuda_fecha_limite', ''),
            'tags' => $_POST['tags'] ?? '',
            'plantilla_id' => input('plantilla_id', ''),
            'recordar_parte' => !empty($_POST['recordar_parte']),
        ];
    }

    private function flashError(Throwable $exception): void
    {
        $message = $exception->getMessage();

        $key = match ($message) {
            'sin_ingreso' => 'expenses.errors.no_income',
            'sin_tasa' => 'currency.errors.no_rate',
            'Moneda no válida.' => 'currency.errors.invalid',
            'fuera_periodo' => 'expenses.errors.out_of_period',
            'periodo_cerrado' => 'expenses.errors.closed',
            'El monto del gasto debe ser mayor que cero.' => 'expenses.errors.amount',
            'El monto del gasto es demasiado alto.' => 'expenses.errors.amount_max',
            'La parte del método no es válida.' => 'expenses.errors.part',
            'El total compartido no puede ser menor que tu parte.' => 'expenses.errors.shared',
            'Escribe el nombre de la nueva deuda.' => 'expenses.errors.debt_name',
            'La deuda seleccionada no existe.' => 'expenses.errors.debt_missing',
            'La fecha no es válida.' => 'expenses.errors.date',
            'El gasto no existe.' => 'expenses.errors.not_found',
            'La foto supera el tamaño máximo de 10 MB.' => 'expenses.errors.photo_size',
            'El archivo no es una imagen.',
            'La foto no es válida.',
            'Formato de imagen no soportado.' => 'expenses.errors.photo_type',
            'No se pudo subir la foto.',
            'No se pudo procesar la imagen.' => 'expenses.errors.photo_upload',
            default => 'expenses.errors.generic',
        };

        Flash::error($key);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOwnGasto(int $id, int $userId): ?array
    {
        return (new GastoRepository())->findForUser($id, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $user = AuthService::user();

        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }
}
