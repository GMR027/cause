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
                (new SubcategoryService())->reconcileParts((int) $user['id'], $method['categorias']);
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
