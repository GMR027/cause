<?php

declare(strict_types=1);

namespace Cauce\Services;

/**
 * Recomendación determinista del método de presupuestación.
 *
 * Las reglas suman puntos por respuesta y los empates se resuelven por el
 * orden del catálogo. No oculta el resto de métodos: solo resalta uno.
 */
final class MethodRecommender
{
    /** @var list<string> */
    private const METHODS = ['50_30_20', 'seis_sobres', 'cinco_frascos', '70_20_10'];

    /**
     * @param array{p1?: string, p2?: string, p3?: string} $quiz
     */
    public function recommend(array $quiz): string
    {
        $scores = array_fill_keys(self::METHODS, 0);

        $p1 = (string) ($quiz['p1'] ?? '');
        $p2 = (string) ($quiz['p2'] ?? '');
        $p3 = (string) ($quiz['p3'] ?? '');

        if ($p1 === 'variable') {
            $this->add($scores, 'seis_sobres', 3);
            $this->add($scores, 'cinco_frascos', 2);
            $this->add($scores, '50_30_20', 1);
        } elseif ($p1 === 'mixto') {
            $this->add($scores, 'cinco_frascos', 2);
            $this->add($scores, 'seis_sobres', 2);
            $this->add($scores, '50_30_20', 1);
        } elseif ($p1 === 'fijo') {
            $this->add($scores, '50_30_20', 2);
            $this->add($scores, '70_20_10', 1);
        }

        if ($p2 === 'si') {
            $this->add($scores, '70_20_10', 3);
            $this->add($scores, 'seis_sobres', 1);
            $this->add($scores, '50_30_20', 1);
        } elseif ($p2 === 'no_seguro') {
            $this->add($scores, 'seis_sobres', 1);
            $this->add($scores, 'cinco_frascos', 1);
        } elseif ($p2 === 'no') {
            $this->add($scores, '50_30_20', 1);
            $this->add($scores, 'cinco_frascos', 1);
        }

        if ($p3 === 'ahorrar') {
            $this->add($scores, '50_30_20', 2);
            $this->add($scores, 'seis_sobres', 2);
        } elseif ($p3 === 'invertir') {
            $this->add($scores, 'cinco_frascos', 2);
            $this->add($scores, '70_20_10', 2);
        } elseif ($p3 === 'libertad') {
            $this->add($scores, '50_30_20', 3);
        } elseif ($p3 === 'donar') {
            $this->add($scores, 'cinco_frascos', 3);
        }

        $best = self::METHODS[0];
        $bestScore = -1;

        foreach (self::METHODS as $method) {
            if ($scores[$method] > $bestScore) {
                $best = $method;
                $bestScore = $scores[$method];
            }
        }

        return $best;
    }

    /**
     * @param array<string, int> $scores
     */
    private function add(array &$scores, string $method, int $points): void
    {
        if (array_key_exists($method, $scores)) {
            $scores[$method] += $points;
        }
    }
}
