<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\ExchangeRateRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resolución y administración de tasas de cambio globales.
 */
final class ExchangeRateService
{
    private ExchangeRateRepository $repo;

    public function __construct(?ExchangeRateRepository $repo = null)
    {
        $this->repo = $repo ?? new ExchangeRateRepository();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->repo->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Devuelve la tasa y si fue exacta o un respaldo anterior.
     *
     * @return array{tasa: float, fecha: string, exacta: bool, fuente: string}
     */
    public function lookup(string $from, string $to, string $date): array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        $date = $this->normalizeDate($date);

        if ($from === $to) {
            return ['tasa' => 1.0, 'fecha' => $date, 'exacta' => true, 'fuente' => 'misma'];
        }

        $direct = $this->leg($from, $to, $date);

        if ($direct !== null) {
            return $direct;
        }

        // Cruce por una moneda pivote (normalmente MXN).
        foreach (CurrencyService::SUPPORTED as $pivot) {
            if ($pivot === $from || $pivot === $to) {
                continue;
            }

            $first = $this->leg($from, $pivot, $date);
            $second = $this->leg($pivot, $to, $date);

            if ($first === null || $second === null) {
                continue;
            }

            return [
                'tasa' => $first['tasa'] * $second['tasa'],
                'fecha' => min((string) $first['fecha'], (string) $second['fecha']),
                'exacta' => $first['exacta'] && $second['exacta'],
                'fuente' => 'cruce',
            ];
        }

        throw new RuntimeException('sin_tasa');
    }

    /**
     * @return array{tasa: float, fecha: string, exacta: bool, fuente: string}|null
     */
    private function leg(string $from, string $to, string $date): ?array
    {
        $direct = $this->repo->forPairOnDate($from, $to, $date);

        if ($direct !== null) {
            return [
                'tasa' => (float) $direct['tasa'],
                'fecha' => (string) $direct['fecha'],
                'exacta' => true,
                'fuente' => 'directa',
            ];
        }

        $inverse = $this->repo->forPairOnDate($to, $from, $date);

        if ($inverse !== null && (float) $inverse['tasa'] > 0) {
            return [
                'tasa' => 1 / (float) $inverse['tasa'],
                'fecha' => (string) $inverse['fecha'],
                'exacta' => true,
                'fuente' => 'inversa',
            ];
        }

        $directFallback = $this->repo->latestForPair($from, $to, $date);
        $inverseFallback = $this->repo->latestForPair($to, $from, $date);
        $candidates = [];

        if ($directFallback !== null) {
            $candidates[] = [
                'tasa' => (float) $directFallback['tasa'],
                'fecha' => (string) $directFallback['fecha'],
                'exacta' => false,
                'fuente' => 'directa',
            ];
        }

        if ($inverseFallback !== null && (float) $inverseFallback['tasa'] > 0) {
            $candidates[] = [
                'tasa' => 1 / (float) $inverseFallback['tasa'],
                'fecha' => (string) $inverseFallback['fecha'],
                'exacta' => false,
                'fuente' => 'inversa',
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => strcmp($b['fecha'], $a['fecha']));

        return $candidates[0];
    }

    /**
     * @return array{centavos: int, tasa: float, fecha: string, exacta: bool, fuente: string}
     */
    public function convert(int $cents, string $from, string $to, string $date): array
    {
        $rate = $this->lookup($from, $to, $date);

        return array_merge($rate, [
            'centavos' => self::applyRate($cents, $rate['tasa']),
        ]);
    }

    public static function applyRate(int $cents, float $rate): int
    {
        return (int) round($cents * $rate);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?int $id = null): int
    {
        $normalized = $this->normalize($data, $id);

        if ($id === null) {
            return $this->repo->create($normalized);
        }

        if ($this->repo->find($id) === null) {
            throw new RuntimeException('La tasa no existe.');
        }

        $this->repo->update($id, $normalized);

        return $id;
    }

    public function delete(int $id): void
    {
        if ($this->repo->find($id) === null) {
            throw new RuntimeException('La tasa no existe.');
        }

        $this->repo->delete($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{moneda_origen: string, moneda_destino: string, tasa: float, fecha: string}
     */
    private function normalize(array $data, ?int $id): array
    {
        $from = strtoupper(trim((string) ($data['moneda_origen'] ?? '')));
        $to = strtoupper(trim((string) ($data['moneda_destino'] ?? '')));
        $rate = (float) str_replace(',', '.', (string) ($data['tasa'] ?? '0'));
        $date = $this->normalizeDate((string) ($data['fecha'] ?? ''));

        if (!in_array($from, CurrencyService::SUPPORTED, true)
            || !in_array($to, CurrencyService::SUPPORTED, true)
        ) {
            throw new InvalidArgumentException('La moneda no es válida.');
        }

        if ($from === $to) {
            throw new InvalidArgumentException('Las monedas deben ser distintas.');
        }

        if (!is_finite($rate)) {
            throw new InvalidArgumentException('La tasa es demasiado alta.');
        }

        $rounded = round($rate, 8);

        if ($rounded <= 0) {
            throw new InvalidArgumentException('La tasa debe ser mayor que cero.');
        }

        if ($rounded >= 10000000000) {
            throw new InvalidArgumentException('La tasa es demasiado alta.');
        }

        if ($this->repo->exists($from, $to, $date, $id ?? 0)) {
            throw new InvalidArgumentException('Ya existe una tasa para ese par y fecha.');
        }

        return [
            'moneda_origen' => $from,
            'moneda_destino' => $to,
            'tasa' => $rounded,
            'fecha' => $date,
        ];
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException('La fecha de la tasa no es válida.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('La fecha de la tasa no es válida.');
        }

        return $date;
    }
}
