<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Logger;
use Cauce\Repositories\InvestmentRepository;
use Throwable;

/**
 * Actualización de cotizaciones para el portafolio.
 *
 * BMV/EUA usan Yahoo Finance (sin API key). Cripto usa CoinGecko.
 * CETES, fondos y posiciones manuales no se consultan.
 */
final class QuoteService
{
    private InvestmentRepository $repo;

    public function __construct(?InvestmentRepository $repo = null)
    {
        $this->repo = $repo ?? new InvestmentRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array{updated: int, failed: int}
     */
    public function refreshUser(array $user): array
    {
        $positions = $this->repo->positionsForUser((int) $user['id']);

        return $this->refreshPositions($positions);
    }

    /**
     * @return array{updated: int, failed: int}
     */
    public function refreshAll(): array
    {
        $db = Database::connection();
        $stmt = $db->query(
            "SELECT DISTINCT tipo, mercado, simbolo_proveedor, moneda, modo_valor
             FROM inversiones_posiciones
             WHERE activo = 1 AND modo_valor = 'automatico'
               AND simbolo_proveedor IS NOT NULL AND simbolo_proveedor <> ''"
        );

        return $this->refreshPositions($stmt->fetchAll());
    }

    /**
     * @param list<array<string, mixed>> $positions
     * @return array{updated: int, failed: int}
     */
    private function refreshPositions(array $positions): array
    {
        $updated = 0;
        $failed = 0;
        $crypto = [];

        foreach ($positions as $position) {
            $market = (string) ($position['mercado'] ?? '');
            $symbol = trim((string) ($position['simbolo_proveedor'] ?? ''));

            if ($symbol === '' || (string) ($position['modo_valor'] ?? 'manual') !== 'automatico') {
                continue;
            }

            if ($market === 'CRIPTO') {
                $crypto[$symbol] = (string) ($position['moneda'] ?? 'USD');
                continue;
            }

            if ($market !== 'BMV' && $market !== 'EUA') {
                continue;
            }

            try {
                $quote = $this->yahoo($symbol);

                if ($quote === null) {
                    $failed++;
                    continue;
                }

                $this->repo->savePrice(
                    $symbol,
                    $market,
                    $quote['currency'],
                    $quote['price'],
                    gmdate('Y-m-d'),
                    'yahoo'
                );
                $updated++;
            } catch (Throwable $exception) {
                $failed++;
                Logger::warning('No se pudo actualizar una cotización', [
                    'symbol' => $symbol,
                    'market' => $market,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($crypto !== []) {
            try {
                $quotes = $this->coinGecko(array_keys($crypto));

                foreach ($crypto as $symbol => $currency) {
                    $quote = $quotes[$symbol] ?? null;

                    if ($quote === null) {
                        $failed++;
                        continue;
                    }

                    $this->repo->savePrice(
                        $symbol,
                        'CRIPTO',
                        $quote['currency'],
                        $quote['price'],
                        gmdate('Y-m-d'),
                        'coingecko'
                    );
                    $updated++;
                }
            } catch (Throwable $exception) {
                $failed += count($crypto);
                Logger::warning('No se pudieron actualizar criptomonedas', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    /**
     * @return array{price: float, currency: string}|null
     */
    private function yahoo(string $symbol): ?array
    {
        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/'
            . rawurlencode($symbol)
            . '?interval=1d&range=5d';

        $body = $this->httpGet($url);

        if ($body === null) {
            return null;
        }

        $data = json_decode($body, true);
        $result = $data['chart']['result'][0] ?? null;

        if (!is_array($result)) {
            return null;
        }

        $price = $result['meta']['regularMarketPrice'] ?? null;
        $currency = (string) ($result['meta']['currency'] ?? '');

        if (!is_numeric($price)) {
            $closes = $result['indicators']['quote'][0]['close'] ?? [];

            foreach (array_reverse($closes) as $close) {
                if (is_numeric($close)) {
                    $price = $close;
                    break;
                }
            }
        }

        if (!is_numeric($price) || $currency === '') {
            return null;
        }

        return ['price' => (float) $price, 'currency' => strtoupper($currency)];
    }

    /**
     * @param list<string> $ids
     * @return array<string, array{price: float, currency: string}>
     */
    private function coinGecko(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        $url = 'https://api.coingecko.com/api/v3/simple/price?ids='
            . rawurlencode(implode(',', $ids))
            . '&vs_currencies=usd,mxn';

        $body = $this->httpGet($url);

        if ($body === null) {
            return [];
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            return [];
        }

        $result = [];

        foreach ($ids as $id) {
            $row = $data[$id] ?? null;

            if (!is_array($row)) {
                continue;
            }

            if (isset($row['mxn']) && is_numeric($row['mxn'])) {
                $result[$id] = ['price' => (float) $row['mxn'], 'currency' => 'MXN'];
            } elseif (isset($row['usd']) && is_numeric($row['usd'])) {
                $result[$id] = ['price' => (float) $row['usd'], 'currency' => 'USD'];
            }
        }

        return $result;
    }

    private function httpGet(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => "User-Agent: Cauce/1.0 (+https://cause.iguzman.com.mx)\r\nAccept: application/json\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return is_string($body) && $body !== '' ? $body : null;
    }
}
