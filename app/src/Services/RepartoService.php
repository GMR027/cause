<?php

declare(strict_types=1);

namespace Cauce\Services;

use InvalidArgumentException;

/**
 * Reparto entero de un ingreso según porcentajes.
 *
 * El residuo de redondeo se asigna a la primera categoría con mayor
 * porcentaje; los empates se resuelven por el orden de entrada (que debe
 * coincidir con metodo_categorias.orden).
 */
final class RepartoService
{
    /**
     * @param array<string, int|float> $porcentajes
     * @return array<string, int>
     */
    public function repartirIngreso(int $montoCentavos, array $porcentajes): array
    {
        if ($montoCentavos < 0) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        if ($porcentajes === []) {
            throw new InvalidArgumentException('Debes indicar al menos una categoría.');
        }

        $totalPorcentaje = 0.0;
        $montos = [];
        $maxKey = null;
        $maxPorcentaje = -1.0;

        foreach ($porcentajes as $categoria => $porcentaje) {
            if (!is_numeric($porcentaje) || (float) $porcentaje < 0) {
                throw new InvalidArgumentException('Los porcentajes deben ser numéricos y no negativos.');
            }

            $porcentaje = (float) $porcentaje;
            $totalPorcentaje += $porcentaje;

            $montos[$categoria] = (int) floor($montoCentavos * $porcentaje / 100);

            if ($porcentaje > $maxPorcentaje) {
                $maxPorcentaje = $porcentaje;
                $maxKey = $categoria;
            }
        }

        if (abs($totalPorcentaje - 100.0) > 0.01) {
            throw new InvalidArgumentException('Los porcentajes deben sumar 100.');
        }

        if ($maxKey === null) {
            throw new InvalidArgumentException('No se pudo determinar la categoría principal.');
        }

        $residuo = $montoCentavos - array_sum($montos);

        if ($residuo !== 0) {
            $montos[$maxKey] += $residuo;
        }

        return $montos;
    }
}
