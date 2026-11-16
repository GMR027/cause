<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Versiones del método de presupuestación.
 *
 * Cualquier cambio de porcentajes crea una nueva fila en usuario_metodos con
 * vigente_desde=NOW(). Solo afecta a ingresos futuros.
 */
final class MethodService
{
    /** Tipos que puede tener una categoría de un método propio. */
    public const CUSTOM_TYPES = ['controlada', 'ahorro', 'inversion', 'donacion', 'compartir'];

    public const CUSTOM_MAX_CATEGORIES = 10;

    /**
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categories
     * @param array<string, int|float> $stored
     * @return array<string, float>
     */
    public function buildPercentages(array $categories, array $stored): array
    {
        $result = [];

        foreach ($categories as $category) {
            $name = (string) $category['nombre'];

            if (array_key_exists($name, $stored) && is_numeric($stored[$name])) {
                $result[$name] = (float) $stored[$name];
            } elseif ($category['porcentaje'] !== null) {
                $result[$name] = (float) $category['porcentaje'];
            }
        }

        return $result;
    }

    /**
     * Activa o ajusta Donación reduciendo la mayor categoría controlada.
     *
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categories
     * @param array<string, int|float> $current
     * @return array<string, float>
     */
    public function donationAdjusted(array $categories, array $current, float $percent): array
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('El porcentaje de donación no es válido.');
        }

        $donationName = null;

        foreach ($categories as $category) {
            if (($category['tipo'] ?? '') === 'donacion') {
                $donationName = (string) $category['nombre'];
                break;
            }
        }

        if ($donationName === null) {
            throw new RuntimeException('Este método no admite donación.');
        }

        $result = $this->normalize($current);
        $currentDonation = $result[$donationName] ?? 0.0;
        $delta = $percent - $currentDonation;

        if ($delta > 0.001) {
            $this->reduce($categories, $result, $delta, ['controlada']);
        } elseif ($delta < -0.001) {
            $this->increase($categories, $result, -$delta, ['controlada', 'ahorro']);
        }

        $result[$donationName] = $percent;

        return $this->validate($result);
    }

    /**
     * Ajusta el % total de inversión y mueve la diferencia a ahorro.
     *
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categories
     * @param array<string, int|float> $current
     * @return array<string, float>
     */
    public function investmentAdjusted(array $categories, array $current, float $percent): array
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('El porcentaje de inversión no es válido.');
        }

        $investmentNames = [];

        foreach ($categories as $category) {
            if (($category['tipo'] ?? '') === 'inversion') {
                $investmentNames[] = (string) $category['nombre'];
            }
        }

        if ($investmentNames === []) {
            throw new RuntimeException('Este método no tiene categoría de inversión.');
        }

        $result = $this->normalize($current);
        $currentInvestment = 0.0;

        foreach ($investmentNames as $name) {
            $currentInvestment += $result[$name] ?? 0.0;
        }

        $delta = $percent - $currentInvestment;

        if ($delta > 0.001) {
            $this->reduce($categories, $result, $delta, ['ahorro', 'controlada']);
        } elseif ($delta < -0.001) {
            $this->increase($categories, $result, -$delta, ['ahorro', 'controlada']);
        }

        foreach ($investmentNames as $index => $name) {
            $result[$name] = $index === 0 ? $percent : 0.0;
        }

        return $this->validate($result);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{active: array<string, mixed>, method: array<string, mixed>, percentages: array<string, float>}|null
     */
    public function activeData(array $user): ?array
    {
        $active = (new UsuarioMetodoRepository())->activeForUser((int) $user['id']);

        if ($active === null) {
            return null;
        }

        $method = (new MetodoRepository())->findById((int) $active['metodo_id']);

        if ($method === null) {
            return null;
        }

        $stored = json_decode((string) ($active['porcentajes_json'] ?? ''), true);
        $stored = is_array($stored) ? $stored : [];

        return [
            'active' => $active,
            'method' => $method,
            'percentages' => $this->buildPercentages($method['categorias'], $stored),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, int|float> $percentages
     */
    public function updatePercentages(array $user, array $percentages): void
    {
        $data = $this->activeData($user);

        if ($data === null) {
            throw new RuntimeException('No hay un método activo.');
        }

        $names = array_column($data['method']['categorias'], 'nombre');
        $clean = [];

        foreach ($percentages as $name => $value) {
            if (!in_array($name, $names, true)) {
                throw new InvalidArgumentException('Categoría desconocida: ' . $name);
            }

            if (!is_numeric($value) || (float) $value < 0) {
                throw new InvalidArgumentException('Porcentaje inválido.');
            }

            $clean[(string) $name] = (float) $value;
        }

        // Conserva en cero las categorías activas para evitar que IngresoService
        // vuelva al porcentaje del catálogo.
        foreach ($data['percentages'] as $name => $value) {
            if (!array_key_exists($name, $clean)) {
                $clean[$name] = 0.0;
            }
        }

        $clean = $this->validate($clean);
        $this->save($user, (int) $data['method']['id'], $clean);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function activateDonation(array $user, float $percent): void
    {
        $data = $this->activeData($user);

        if ($data === null) {
            throw new RuntimeException('No hay un método activo.');
        }

        $adjusted = $this->donationAdjusted(
            $data['method']['categorias'],
            $data['percentages'],
            $percent
        );

        $this->save($user, (int) $data['method']['id'], $adjusted);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function adjustInvestment(array $user, float $percent): void
    {
        $data = $this->activeData($user);

        if ($data === null) {
            throw new RuntimeException('No hay un método activo.');
        }

        $adjusted = $this->investmentAdjusted(
            $data['method']['categorias'],
            $data['percentages'],
            $percent
        );

        $this->save($user, (int) $data['method']['id'], $adjusted);
    }

    /**
     * Métodos que la persona puede elegir en Ajustes → Ingreso, cada uno con
     * los porcentajes que se proponen al elegirlo (los vigentes si es el
     * método activo; los del catálogo o los que definió si es otro).
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function selectable(array $user): array
    {
        $active = $this->activeData($user);
        $activeId = $active !== null ? (int) $active['method']['id'] : null;
        $codes = (array) config('onboarding.selectable_methods', []);
        $methods = (new MetodoRepository())->selectableFor((int) $user['id'], $codes);

        // El método vigente siempre aparece, aunque ya no esté en la lista
        // seleccionable (p. ej. uno archivado o retirado del catálogo).
        if ($active !== null && !in_array($activeId, array_map(static fn (array $m): int => (int) $m['id'], $methods), true)) {
            $methods[] = $active['method'];
        }

        foreach ($methods as &$method) {
            $isActive = (int) $method['id'] === $activeId;
            $method['activo'] = $isActive;
            $method['propio'] = $method['user_id'] !== null;
            $method['porcentajes'] = $isActive
                ? $active['percentages']
                : $this->buildPercentages($method['categorias'], []);
        }
        unset($method);

        return $methods;
    }

    /**
     * Normaliza los porcentajes enviados para las categorías de un método.
     * Las categorías sin valor quedan en 0.
     *
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categories
     * @param array<mixed> $input
     * @return array{percentages: array<string, float>, errors: list<string>}
     */
    public function cleanPercentages(array $categories, array $input): array
    {
        $errors = [];
        $clean = [];
        $spending = 0.0;

        foreach ($categories as $category) {
            $name = (string) $category['nombre'];
            $raw = $input[$name] ?? 0;
            $raw = is_string($raw) ? str_replace(',', '.', trim($raw)) : $raw;
            $raw = $raw === '' || $raw === null ? 0 : $raw;

            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
                $errors[] = 'settings.metodo.errors.percent';
                $raw = 0;
            }

            $value = round((float) $raw, 2);
            $clean[$name] = $value;

            if ((string) $category['tipo'] === 'controlada') {
                $spending += $value;
            }
        }

        if ($clean === []) {
            $errors[] = 'settings.metodo.errors.no_categories';
        } elseif (abs(array_sum($clean) - 100.0) > 0.01) {
            $errors[] = 'settings.metodo.errors.sum';
        }

        if ($clean !== [] && $spending <= 0.001) {
            $errors[] = 'settings.metodo.errors.no_spending';
        }

        return ['percentages' => $clean, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Cambia de método (o reajusta el vigente) con nuevos porcentajes.
     * Solo afecta a los ingresos que se registren desde ahora.
     *
     * @param array<string, mixed> $user
     * @param array<mixed> $input
     * @return list<string> Claves de error; vacío si se guardó.
     */
    public function changeMethod(array $user, int $metodoId, array $input): array
    {
        $method = null;

        foreach ($this->selectable($user) as $candidate) {
            if ((int) $candidate['id'] === $metodoId) {
                $method = $candidate;
                break;
            }
        }

        if ($method === null) {
            return ['settings.metodo.errors.unknown'];
        }

        $result = $this->cleanPercentages($method['categorias'], $input);

        if ($result['errors'] !== []) {
            return $result['errors'];
        }

        $this->save($user, $metodoId, $result['percentages']);

        return [];
    }

    /**
     * Valida el nombre y las filas de un método propio.
     *
     * @param array<mixed> $rows
     * @return array{nombre: string, categorias: list<array{id: ?int, nombre: string, tipo: string, porcentaje: int}>, errors: list<string>}
     */
    public function cleanCustom(string $nombre, array $rows): array
    {
        $errors = [];
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            $errors[] = 'settings.metodo.errors.name';
        }

        $categorias = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $catName = trim(preg_replace('/\s+/u', ' ', (string) (is_scalar($row['nombre'] ?? null) ? $row['nombre'] : '')) ?? '');
            $rawPercent = is_scalar($row['porcentaje'] ?? null) ? trim((string) $row['porcentaje']) : '';

            if ($catName === '' && ($rawPercent === '' || (float) $rawPercent === 0.0)) {
                continue; // fila vacía
            }

            if (mb_strlen($catName) < 2 || mb_strlen($catName) > 60 || str_starts_with($catName, '__')) {
                $errors[] = 'settings.metodo.errors.category_name';
                continue;
            }

            $key = mb_strtolower($catName);

            if (isset($seen[$key])) {
                $errors[] = 'settings.metodo.errors.duplicate';
                continue;
            }

            $seen[$key] = true;
            $tipo = is_scalar($row['tipo'] ?? null) ? (string) $row['tipo'] : '';

            if (!in_array($tipo, self::CUSTOM_TYPES, true)) {
                $errors[] = 'settings.metodo.errors.type';
                continue;
            }

            if ($rawPercent === '' || !ctype_digit($rawPercent) || (int) $rawPercent > 100) {
                $errors[] = 'settings.metodo.errors.percent_int';
                continue;
            }

            $id = is_scalar($row['id'] ?? null) && ctype_digit((string) $row['id']) ? (int) $row['id'] : null;

            $categorias[] = [
                'id' => $id,
                'nombre' => $catName,
                'tipo' => $tipo,
                'porcentaje' => (int) $rawPercent,
            ];
        }

        if ($categorias === []) {
            $errors[] = 'settings.metodo.errors.no_categories';
        } elseif (count($categorias) > self::CUSTOM_MAX_CATEGORIES) {
            $errors[] = 'settings.metodo.errors.too_many';
        } else {
            if (array_sum(array_column($categorias, 'porcentaje')) !== 100) {
                $errors[] = 'settings.metodo.errors.sum';
            }

            $spending = array_filter(
                $categorias,
                static fn (array $c): bool => $c['tipo'] === 'controlada' && $c['porcentaje'] > 0
            );

            if ($spending === []) {
                $errors[] = 'settings.metodo.errors.no_spending';
            }
        }

        return ['nombre' => $nombre, 'categorias' => $categorias, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Crea o edita un método propio y, si se pide (o si ya es el vigente),
     * lo activa con sus porcentajes.
     *
     * @param array<string, mixed> $user
     * @param array<mixed> $rows
     * @return array{id: ?int, errors: list<string>}
     */
    public function saveCustom(array $user, ?int $id, string $nombre, array $rows, bool $activate): array
    {
        $userId = (int) $user['id'];
        $clean = $this->cleanCustom($nombre, $rows);

        if ($clean['errors'] !== []) {
            return ['id' => $id, 'errors' => $clean['errors']];
        }

        $db = Database::connection();
        $repo = new MetodoRepository($db);

        if ($id !== null && $repo->findOwned($id, $userId) === null) {
            return ['id' => $id, 'errors' => ['settings.metodo.errors.unknown']];
        }

        $active = (new UsuarioMetodoRepository($db))->activeForUser($userId);
        $isActive = $id !== null && $active !== null && (int) $active['metodo_id'] === $id;
        $percentages = [];

        foreach ($clean['categorias'] as $categoria) {
            $percentages[$categoria['nombre']] = (float) $categoria['porcentaje'];
        }

        try {
            $db->beginTransaction();

            if ($id === null) {
                $id = $repo->createCustom($userId, $clean['nombre'], $clean['categorias']);
            } else {
                $renames = $repo->updateCustom($id, $userId, $clean['nombre'], $clean['categorias']);

                if ($isActive && $renames !== []) {
                    $this->renameParts($db, $user, $renames);
                }
            }

            if ($activate || $isActive) {
                (new UsuarioMetodoRepository($db))->activate($userId, $id, $percentages);
                $method = $repo->findById($id);

                if ($method !== null) {
                    (new SubcategoryService())->reconcileParts(
                        $userId,
                        $this->effectiveCategories($method['categorias'], $percentages)
                    );
                }
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return ['id' => $id, 'errors' => []];
    }

    /**
     * Archiva un método propio. El vigente no se puede eliminar.
     *
     * @param array<string, mixed> $user
     * @return list<string>
     */
    public function deleteCustom(array $user, int $id): array
    {
        $userId = (int) $user['id'];
        $active = (new UsuarioMetodoRepository())->activeForUser($userId);

        if ($active !== null && (int) $active['metodo_id'] === $id) {
            return ['settings.metodo.errors.delete_active'];
        }

        if (!(new MetodoRepository())->archiveCustom($id, $userId)) {
            return ['settings.metodo.errors.unknown'];
        }

        return [];
    }

    /**
     * Categorías con el porcentaje vigente; las que quedan en 0 se omiten
     * para que las subcategorías no se reasignen a una parte sin dinero.
     *
     * @param list<array<string, mixed>> $categories
     * @param array<string, float> $percentages
     * @return list<array<string, mixed>>
     */
    private function effectiveCategories(array $categories, array $percentages): array
    {
        $result = [];

        foreach ($categories as $category) {
            $value = (float) ($percentages[(string) $category['nombre']] ?? 0);

            if ($value <= 0.001) {
                continue;
            }

            $category['porcentaje'] = $value;
            $result[] = $category;
        }

        return $result;
    }

    /**
     * Al renombrar categorías del método vigente, mueve con ellas lo que
     * apunta al nombre anterior: subcategorías, recurrentes, plantillas,
     * reparto de extras y los gastos e ingresos del período actual.
     *
     * @param array<string, mixed> $user
     * @param array<string, string> $renames
     */
    private function renameParts(\PDO $db, array $user, array $renames): void
    {
        $userId = (int) $user['id'];
        $period = (new PeriodoService())->calcularPeriodo($user);
        $start = $period['inicio'] instanceof \DateTimeInterface
            ? $period['inicio']->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
            : (string) $period['inicio'];

        $statements = [
            'UPDATE subcategorias SET parte_metodo_actual = :new WHERE user_id = :user AND parte_metodo_actual = :old',
            'UPDATE subcategorias SET parte_metodo_default = :new WHERE user_id = :user AND parte_metodo_default = :old',
            'UPDATE gastos_recurrentes SET parte_metodo = :new WHERE user_id = :user AND parte_metodo = :old',
            'UPDATE plantillas_gasto SET parte_metodo = :new WHERE user_id = :user AND parte_metodo = :old',
            'UPDATE gastos SET parte_metodo = :new WHERE user_id = :user AND parte_metodo = :old AND periodo_inicio >= :start',
            'UPDATE asignaciones a JOIN ingresos i ON i.id = a.ingreso_id
                SET a.categoria = :new
              WHERE a.user_id = :user AND a.categoria = :old AND i.periodo_inicio >= :start',
        ];

        foreach ($renames as $old => $new) {
            foreach ($statements as $sql) {
                $params = [':new' => $new, ':user' => $userId, ':old' => $old];

                if (str_contains($sql, ':start')) {
                    $params[':start'] = $start;
                }

                $db->prepare($sql)->execute($params);
            }
        }

        $users = new \Cauce\Repositories\UserRepository($db);
        $extra = json_decode((string) ($user['extra_reparto_json'] ?? ''), true);

        if (is_array($extra) && $extra !== []) {
            $updated = [];

            foreach ($extra as $name => $value) {
                $updated[$renames[(string) $name] ?? (string) $name] = (int) $value;
            }

            $users->updateExtraReparto($userId, $updated);
        }
    }

    /**
     * @param array<string, float> $percentages
     */
    private function save(array $user, int $metodoId, array $percentages): void
    {
        $db = Database::connection();

        try {
            $db->beginTransaction();
            (new UsuarioMetodoRepository($db))->activate((int) $user['id'], $metodoId, $percentages);

            $method = (new MetodoRepository($db))->findById($metodoId);

            if ($method !== null) {
                (new SubcategoryService())->reconcileParts(
                    (int) $user['id'],
                    $this->effectiveCategories($method['categorias'], $percentages)
                );
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categories
     * @param array<string, float> $percentages
     * @param list<string> $types
     */
    private function reduce(array $categories, array &$percentages, float $amount, array $types): void
    {
        $remaining = $amount;

        foreach ($types as $type) {
            $candidates = [];

            foreach ($categories as $category) {
                $name = (string) $category['nombre'];

                if ((string) $category['tipo'] !== $type) {
                    continue;
                }

                if (($percentages[$name] ?? 0.0) > 0.001) {
                    $candidates[] = $name;
                }
            }

            usort($candidates, static fn (string $a, string $b): int => ($percentages[$b] ?? 0) <=> ($percentages[$a] ?? 0));

            foreach ($candidates as $name) {
                $take = min($percentages[$name], $remaining);
                $percentages[$name] -= $take;
                $remaining -= $take;

                if ($remaining <= 0.001) {
                    break 2;
                }
            }
        }

        if ($remaining > 0.001) {
            throw new RuntimeException('No hay suficiente porcentaje para mover.');
        }
    }

    /**
     * @param list<array{nombre: string, porcentaje: ?int, tipo: string, orden: int}> $categories
     * @param array<string, float> $percentages
     * @param list<string> $types
     */
    private function increase(array $categories, array &$percentages, float $amount, array $types): void
    {
        foreach ($types as $type) {
            foreach ($categories as $category) {
                $name = (string) $category['nombre'];

                if ((string) $category['tipo'] === $type) {
                    $percentages[$name] = ($percentages[$name] ?? 0.0) + $amount;

                    return;
                }
            }
        }

        throw new RuntimeException('No hay una categoría disponible para compensar.');
    }

    /**
     * @param array<string, int|float> $percentages
     * @return array<string, float>
     */
    private function normalize(array $percentages): array
    {
        $result = [];

        foreach ($percentages as $name => $value) {
            $result[(string) $name] = round((float) $value, 4);
        }

        return $result;
    }

    /**
     * @param array<string, float> $percentages
     * @return array<string, float>
     */
    private function validate(array $percentages): array
    {
        $sum = array_sum($percentages);

        if (abs($sum - 100.0) > 0.01) {
            throw new RuntimeException('Los porcentajes deben sumar 100.');
        }

        foreach ($percentages as $value) {
            if ($value < 0 || $value > 100) {
                throw new RuntimeException('Porcentaje fuera de rango.');
            }
        }

        return $percentages;
    }
}
