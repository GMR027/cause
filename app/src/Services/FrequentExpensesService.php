<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\GastoRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Top de gastos más frecuentes de los últimos días (Reportes).
 */
final class FrequentExpensesService
{
    public const DAYS = 90;
    public const LIMIT = 10;

    private GastoRepository $gastos;

    public function __construct(?GastoRepository $gastos = null)
    {
        $this->gastos = $gastos ?? new GastoRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array{days: int, items: list<array<string, mixed>>, max: int}
     */
    public function top(array $user): array
    {
        $timezone = (new PeriodoService())->userTimezone($user);
        $since = (new DateTimeImmutable('today', $timezone))
            ->sub(new DateInterval('P' . self::DAYS . 'D'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $items = self::rank($this->gastos->topFrequent((int) $user['id'], $since, self::LIMIT * 2), self::LIMIT);

        return [
            'days' => self::DAYS,
            'items' => $items,
            'max' => $items === [] ? 0 : max(array_column($items, 'veces')),
        ];
    }

    /**
     * Normaliza filas, ordena por veces y total (desc) y limita.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{label: string, part: string, veces: int, total: int, promedio: int, ultima: string}>
     */
    public static function rank(array $rows, int $limit): array
    {
        $items = [];

        foreach ($rows as $row) {
            $label = trim((string) ($row['subcategoria_nombre'] ?? ''));

            if ($label === '') {
                $label = trim((string) ($row['nota'] ?? ''));
            }

            $veces = (int) ($row['veces'] ?? 0);

            if ($label === '' || $veces <= 0) {
                continue;
            }

            $total = (int) ($row['total'] ?? 0);
            $items[] = [
                'label' => mb_strlen($label) > 60 ? mb_substr($label, 0, 59) . '…' : $label,
                'part' => (string) ($row['parte_metodo'] ?? ''),
                'veces' => $veces,
                'total' => $total,
                'promedio' => intdiv($total, $veces),
                'ultima' => (string) ($row['ultima'] ?? ''),
            ];
        }

        usort($items, static fn (array $a, array $b): int => [$b['veces'], $b['total']] <=> [$a['veces'], $a['total']]);

        return array_slice($items, 0, max(0, $limit));
    }
}
