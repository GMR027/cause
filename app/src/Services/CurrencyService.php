<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\CurrencyRepository;
use Cauce\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Monedas activas del usuario y conversión a su moneda principal.
 */
final class CurrencyService
{
    public const SUPPORTED = ['MXN', 'USD', 'EUR'];

    private CurrencyRepository $repo;

    private ExchangeRateService $rates;

    public function __construct(?CurrencyRepository $repo = null, ?ExchangeRateService $rates = null)
    {
        $this->repo = $repo ?? new CurrencyRepository();
        $this->rates = $rates ?? new ExchangeRateService();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function principal(array $user): string
    {
        $code = strtoupper((string) ($user['moneda_activa'] ?? 'MXN'));

        return in_array($code, self::SUPPORTED, true) ? $code : 'MXN';
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function currencies(array $user): array
    {
        $userId = (int) $user['id'];
        $this->repo->ensure($userId, $this->principal($user));

        return $this->repo->allForUser($userId);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<string>
     */
    public function codes(array $user): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['codigo'],
            $this->currencies($user)
        ));
    }

    /**
     * @param array<string, mixed> $user
     * @return list<string>
     */
    public function available(array $user): array
    {
        return array_values(array_diff(self::SUPPORTED, $this->codes($user)));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function add(array $user, string $code): void
    {
        $code = $this->validateCode($code);

        if (in_array($code, $this->codes($user), true)) {
            throw new InvalidArgumentException('Esa moneda ya está activa.');
        }

        $this->repo->add((int) $user['id'], $code);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function remove(array $user, string $code): void
    {
        $code = $this->validateCode($code);
        $principal = $this->principal($user);

        if ($code === $principal) {
            throw new InvalidArgumentException('No puedes quitar la moneda principal.');
        }

        if (!in_array($code, $this->codes($user), true)) {
            throw new InvalidArgumentException('Esa moneda no está activa.');
        }

        $this->repo->remove((int) $user['id'], $code);
    }

    /**
     * @param array<string, mixed> $user
     * @return string Moneda principal anterior.
     */
    public function setPrincipal(array $user, string $code): string
    {
        $code = $this->validateCode($code);

        if (!in_array($code, $this->codes($user), true)) {
            throw new InvalidArgumentException('Agrega la moneda antes de hacerla principal.');
        }

        $old = $this->principal($user);
        $this->repo->setPrincipal((int) $user['id'], $code);
        (new UserRepository())->updateCurrency((int) $user['id'], $code);

        return $old;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{centavos: int, tasa: float, fecha: string, exacta: bool, fuente: string}
     */
    public function toPrincipal(array $user, int $cents, string $from, string $date): array
    {
        return $this->rates->convert(
            $cents,
            $this->validateCode($from),
            $this->principal($user),
            $date
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return array{centavos: int, tasa: float, fecha: string, exacta: bool, fuente: string}
     */
    public function convert(array $user, int $cents, string $from, string $to, string $date): array
    {
        return $this->rates->convert($cents, $from, $to, $date);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function userById(int $userId): ?array
    {
        return (new UserRepository())->findById($userId);
    }

    /**
     * Conversión tolerante para backfills y repositorios.
     */
    public function toPrincipalForUserId(int $userId, int $cents, string $from, string $date): ?int
    {
        $user = $this->userById($userId);

        if ($user === null) {
            return null;
        }

        try {
            return $this->toPrincipal($user, $cents, $from, $date)['centavos'];
        } catch (RuntimeException) {
            return null;
        }
    }

    public function validateCode(string $code): string
    {
        $code = strtoupper(trim($code));

        if (!in_array($code, self::SUPPORTED, true)) {
            throw new InvalidArgumentException('Moneda no válida.');
        }

        return $code;
    }
}
