<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\DonationRepository;
use Cauce\Repositories\GamificationRepository;
use Cauce\Repositories\IngresoRepository;
use Cauce\Repositories\InvestmentRepository;
use Cauce\Repositories\MetodoRepository;
use Cauce\Repositories\SavingsRepository;
use Cauce\Repositories\UsuarioMetodoRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Racha, niveles, retos, hitos y comparaciones de Fase 7.
 *
 * La evaluación es perezosa: no hay cron. El middleware llama a reconcile() y
 * refreshLevelIfDue(); los hooks de ingreso y cierre de período adelantan el
 * trabajo cuando ocurre un evento importante.
 */
final class GamificationService
{
    public const BADGES = [
        'primera_meta',
        'primer_mes_completo',
        'emergencia_50',
        'emergencia_100',
        'racha_30',
    ];

    private GamificationRepository $repo;

    public function __construct(?GamificationRepository $repo = null)
    {
        $this->repo = $repo ?? new GamificationRepository();
    }

    /**
     * @param array<string, mixed> $user
     */
    public function isEnabled(array $user): bool
    {
        return (int) ($user['gamificacion_activa'] ?? 1) === 1;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $ingreso
     */
    public function onIncomeFinalized(array $user, array $ingreso): void
    {
        if (!$this->isEnabled($user)) {
            return;
        }

        $this->reconcile($user);
        $this->ensureChallenge($user);
    }

    /**
     * Reconcilia la racha con los períodos ya cerrados y el período actual.
     *
     * @param array<string, mixed> $user
     */
    public function reconcile(array $user): void
    {
        if (!$this->isEnabled($user)) {
            return;
        }

        $userId = (int) $user['id'];
        $row = $this->repo->ensureStreak($userId);
        $current = $this->support()->period($user);
        $now = gmdate('Y-m-d H:i:s');

        if (($row['ultimo_periodo_inicio'] ?? null) === null || ($row['ultimo_periodo_fin'] ?? null) === null) {
            $this->seedStreak($user, $row);
        }

        $guard = 0;
        $cursor = [
            'inicio' => (string) $row['ultimo_periodo_inicio'],
            'fin' => (string) $row['ultimo_periodo_fin'],
        ];

        while ($guard < 240) {
            $next = $this->nextPeriod($user, $cursor);

            if ($next === null || $next['inicio'] > $current['inicio']) {
                break;
            }

            $isCurrent = $next['inicio'] === $current['inicio'] && $next['fin'] === $current['fin'];
            $completed = $next['fin'] < $now;
            $hasIncome = $this->repo->hasFinalizedIncome($userId, $next['inicio'], $next['fin']);

            if (!$hasIncome && $isCurrent && !$completed) {
                // El período actual sigue abierto: no se penaliza todavía.
                break;
            }

            if (!$hasIncome && !$completed) {
                break;
            }

            $transition = self::streakTransition(
                (string) $row['estado'],
                (int) $row['periodos_consecutivos'],
                $hasIncome,
                $completed,
                (int) $row['mejor_racha']
            );

            $row['estado'] = $transition['estado'];
            $row['periodos_consecutivos'] = $transition['periodos_consecutivos'];
            $row['mejor_racha'] = $transition['mejor_racha'];
            $row['ultimo_periodo_inicio'] = $next['inicio'];
            $row['ultimo_periodo_fin'] = $next['fin'];
            $cursor = $next;
            $guard++;
        }

        $row['mejor_racha'] = max((int) ($row['mejor_racha'] ?? 0), (int) ($row['periodos_consecutivos'] ?? 0));
        $this->repo->saveStreak($userId, $row);
    }

    /**
     * Siembra la racha a partir del historial de ingresos finalizados.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $row
     */
    private function seedStreak(array $user, array &$row): void
    {
        $userId = (int) $user['id'];
        $periods = $this->repo->finalizedPeriods($userId, 240);

        if ($periods === []) {
            $previous = $this->previousPeriod($user, $this->support()->period($user));

            if ($previous === null) {
                $previous = $this->support()->period($user);
            }

            $row['periodos_consecutivos'] = 0;
            $row['mejor_racha'] = 0;
            $row['estado'] = 'activa';
            $row['ultimo_periodo_inicio'] = $previous['inicio'];
            $row['ultimo_periodo_fin'] = $previous['fin'];

            return;
        }

        $set = [];

        foreach ($periods as $period) {
            $set[$period['periodo_inicio'] . '|' . $period['periodo_fin']] = true;
        }

        $latest = [
            'inicio' => (string) $periods[0]['periodo_inicio'],
            'fin' => (string) $periods[0]['periodo_fin'],
        ];
        $count = 0;
        $cursor = $latest;
        $guard = 0;

        while ($guard < 240 && isset($set[$cursor['inicio'] . '|' . $cursor['fin']])) {
            $count++;
            $previous = $this->previousPeriod($user, $cursor);

            if ($previous === null) {
                break;
            }

            $cursor = $previous;
            $guard++;
        }

        $row['periodos_consecutivos'] = $count;
        $row['mejor_racha'] = max((int) ($row['mejor_racha'] ?? 0), $count);
        $row['estado'] = 'activa';
        $row['ultimo_periodo_inicio'] = $latest['inicio'];
        $row['ultimo_periodo_fin'] = $latest['fin'];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{count: int, state: string, best: int}
     */
    public function streakData(array $user): array
    {
        $row = $this->repo->ensureStreak((int) $user['id']);

        return [
            'count' => (int) ($row['periodos_consecutivos'] ?? 0),
            'state' => (string) ($row['estado'] ?? 'activa'),
            'best' => (int) ($row['mejor_racha'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function homeCard(array $user): array
    {
        $enabled = $this->isEnabled($user);

        return [
            'enabled' => $enabled,
            'streak' => $this->streakData($user),
            'challenge' => $enabled ? $this->ensureChallenge($user) : null,
            'past' => $this->pastComparison($user),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function panel(array $user): array
    {
        $level = $this->levelData($user);

        return [
            'enabled' => $this->isEnabled($user),
            'streak' => $this->streakData($user),
            'level' => $level,
            'badges' => $this->badges($user),
            'challenge' => $this->isEnabled($user) ? $this->ensureChallenge($user) : null,
            'past' => $this->pastComparison($user),
            'social' => $this->socialComparison($user, $level),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function levelData(array $user): array
    {
        $row = $this->repo->level((int) $user['id']);

        if ($row === null) {
            $this->refreshLevel($user);
            $row = $this->repo->level((int) $user['id']);
        }

        $detail = self::decodeDetail($row['detalle_json'] ?? null);
        $level = (int) ($row['nivel_actual'] ?? 0);

        return [
            'nivel' => $level,
            'porcentaje' => (float) ($row['porcentaje_cumplimiento'] ?? 0),
            'fecha_calculo' => $row['fecha_calculo'] ?? null,
            'has_data' => $level > 0,
            'detalle' => $detail,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function refreshLevelIfDue(array $user): void
    {
        if (!$this->isEnabled($user)) {
            return;
        }

        $current = $this->support()->period($user);
        $timezone = $this->support()->ago($user);
        $currentDate = (new DateTimeImmutable($current['inicio'], new DateTimeZone('UTC')))
            ->setTimezone($timezone)
            ->format('Y-m-d');
        $row = $this->repo->level((int) $user['id']);

        if ($row === null || (string) ($row['fecha_calculo'] ?? '') < $currentDate) {
            $this->refreshLevel($user);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public function refreshLevel(array $user): void
    {
        $userId = (int) $user['id'];
        $current = $this->support()->period($user);
        $calculationDate = (new DateTimeImmutable('now', $this->support()->ago($user)))->format('Y-m-d');
        $periods = [];
        $cursor = $current;

        for ($i = 0; $i < 3; $i++) {
            $previous = $this->previousPeriod($user, $cursor);

            if ($previous === null) {
                break;
            }

            $periods[] = $previous;
            $cursor = $previous;
        }

        $periodData = [];

        foreach ($periods as $period) {
            $data = $this->periodLevel($user, $period);

            if ($data !== null) {
                $periodData[] = $data;
            }
        }

        if ($periodData === []) {
            $this->repo->saveLevel($userId, 0, 0.0, $calculationDate, [
                'has_data' => false,
                'percent' => 0.0,
                'periods' => [],
                'next' => null,
                'level_key' => null,
            ]);

            return;
        }

        $percent = round(
            array_sum(array_column($periodData, 'percent')) / max(1, count($periodData)),
            2
        );
        $level = self::levelForPercent($percent);
        $next = self::nextLevel($level, $percent);

        $this->repo->saveLevel($userId, $level, $percent, $calculationDate, [
            'has_data' => true,
            'percent' => $percent,
            'level' => $level,
            'level_key' => self::levelName($level),
            'next' => $next,
            'periods' => $periodData,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @param array{inicio: string, fin: string} $period
     * @return array<string, mixed>|null
     */
    private function periodLevel(array $user, array $period): ?array
    {
        return (new MethodComplianceService())->forPeriod($user, $period);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function ensureChallenge(array $user): ?array
    {
        if (!$this->isEnabled($user)) {
            return null;
        }

        $userId = (int) $user['id'];
        $current = $this->support()->period($user);
        $existing = $this->repo->challengeForPeriod($userId, $current['inicio'], $current['fin']);

        if ($existing !== null) {
            return $this->refreshChallenge($user, $existing, $current);
        }

        $categories = $this->support()->categories($user);

        if ($categories === []) {
            return null;
        }

        $candidate = self::challengeCandidate($categories);
        $this->repo->expireChallenges($userId, $current['inicio']);
        $id = $this->repo->createChallenge(
            $userId,
            $current['inicio'],
            $current['fin'],
            $candidate['tipo'],
            $candidate['objetivo']
        );
        $row = $this->repo->findChallenge($id, $userId);

        return $row !== null ? $this->refreshChallenge($user, $row, $current) : null;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $challenge
     * @param array{inicio: string, fin: string} $period
     * @return array<string, mixed>
     */
    private function refreshChallenge(array $user, array $challenge, array $period): array
    {
        if (in_array((string) ($challenge['estado'] ?? ''), ['descartado', 'expirado'], true)) {
            return $challenge;
        }

        $progress = $this->challengeProgress($user, $challenge, $period);
        $state = $progress >= 99.95 ? 'completado' : 'activo';

        if ((float) ($challenge['progreso'] ?? 0) !== $progress || (string) $challenge['estado'] !== $state) {
            $this->repo->updateChallenge((int) $challenge['id'], $progress, $state);
            $challenge['progreso'] = $progress;
            $challenge['estado'] = $state;
        }

        return $challenge;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $challenge
     * @param array{inicio: string, fin: string} $period
     */
    private function challengeProgress(array $user, array $challenge, array $period): float
    {
        $userId = (int) $user['id'];
        $type = (string) ($challenge['tipo'] ?? '');
        $objective = (float) ($challenge['objetivo'] ?? 0);

        if ($type === 'registra_todo') {
            $counts = $this->repo->gastoCounts($userId, $period['inicio'], $period['fin']);

            if ($counts['total'] <= 0) {
                return 100.0;
            }

            return round(max(0, min(100, (($counts['total'] - $counts['pending']) / $counts['total']) * 100)), 2);
        }

        if ($type === 'racha') {
            $row = $this->repo->streak((int) $user['id']);
            $count = (int) ($row['periodos_consecutivos'] ?? 0);

            return round(min(100, ($count / 30) * 100), 2);
        }

        $income = (new IngresoRepository())->sumForPeriod($userId, $period['inicio'], $period['fin']);
        $target = $income * ($objective / 100);

        if ($target <= 0) {
            return 0.0;
        }

        $actual = match ($type) {
            'cumple_ahorro' => (new SavingsRepository())->sumPeriodContributions(
                $userId,
                $period['inicio'],
                $period['fin']
            ),
            'cumple_inversion' => (new InvestmentRepository())->sumContributions(
                $userId,
                null,
                $period['inicio'],
                $period['fin'],
                true
            ),
            'cumple_donacion' => (new DonationRepository())->sumPeriodNormal(
                $userId,
                $period['inicio'],
                $period['fin']
            ),
            default => 0,
        };

        return round(min(100, max(0, ($actual / $target) * 100)), 2);
    }

    /**
     * @param array<string, mixed> $user
     * @param array{inicio: string, fin: string} $period
     */
    public function onPeriodClosed(array $user, array $period): void
    {
        $userId = (int) $user['id'];
        $closed = $this->repo->challengeForPeriod($userId, $period['inicio'], $period['fin']);

        if ($closed !== null && (string) ($closed['estado'] ?? '') === 'activo') {
            $progress = $this->challengeProgress($user, $closed, $period);
            $this->repo->updateChallenge(
                (int) $closed['id'],
                $progress,
                $progress >= 99.95 ? 'completado' : 'expirado'
            );
        }

        $current = $this->support()->period($user);
        $this->repo->expireChallenges($userId, $current['inicio']);
        $this->refreshLevelIfDue($user);
        $this->ensureChallenge($user);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function dismissChallenge(array $user, int $id): void
    {
        $this->repo->dismissChallenge($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{id: int, tipo: string, fecha_logro: string}|null
     */
    public function pendingCelebration(array $user): ?array
    {
        if (!$this->isEnabled($user)) {
            return null;
        }

        $hito = $this->repo->uncelebratedHito((int) $user['id']);

        if ($hito === null) {
            return null;
        }

        return [
            'id' => (int) $hito['id'],
            'tipo' => (string) $hito['tipo'],
            'fecha_logro' => (string) $hito['fecha_logro'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function markCelebrated(array $user, int $id): void
    {
        $this->repo->markCelebrated($id, (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function badges(array $user): array
    {
        $map = $this->repo->hitosMap((int) $user['id']);
        $badges = [];

        foreach (self::BADGES as $type) {
            $hito = $map[$type] ?? null;
            $badges[] = [
                'tipo' => $type,
                'unlocked' => $hito !== null,
                'fecha_logro' => $hito['fecha_logro'] ?? null,
                'celebrado_at' => $hito['celebrado_at'] ?? null,
            ];
        }

        return $badges;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function pastComparison(array $user): array
    {
        $current = $this->support()->period($user);
        $previous = $this->previousPeriod($user, $current);
        $currentNet = $this->netSavingsInvestment($user, $current);
        $previousNet = $previous !== null ? $this->netSavingsInvestment($user, $previous) : 0;
        $comparison = self::compareNet($currentNet, $previousNet);

        return array_merge($comparison, [
            'current_centavos' => $currentNet,
            'previous_centavos' => $previousNet,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function socialComparison(array $user, ?array $level = null): array
    {
        $enabled = (int) ($user['comparacion_social'] ?? 0) === 1 && $this->isEnabled($user);

        if (!$enabled) {
            return ['enabled' => false, 'enough' => false, 'direction' => 'off', 'group_size' => 0];
        }

        $level ??= $this->levelData($user);

        if (empty($level['has_data'])) {
            return ['enabled' => true, 'enough' => false, 'direction' => 'no_data', 'group_size' => 0];
        }

        $methodCode = $this->methodCode($user);

        if ($methodCode === null) {
            return ['enabled' => true, 'enough' => false, 'direction' => 'no_data', 'group_size' => 0];
        }

        $recurrence = (string) ($user['recurrencia'] ?? '');
        $currency = (string) ($user['moneda_activa'] ?? 'MXN');
        $others = $this->repo->socialGroup($methodCode, $recurrence, $currency, (int) $user['id']);
        $groupSize = count($others) + 1;

        if ($groupSize < 10) {
            return [
                'enabled' => true,
                'enough' => false,
                'direction' => 'insufficient',
                'group_size' => $groupSize,
            ];
        }

        $average = array_sum(array_column($others, 'porcentaje_cumplimiento')) / max(1, count($others));
        $difference = (float) $level['porcentaje'] - $average;
        $direction = $difference > 2 ? 'above' : ($difference < -2 ? 'below' : 'in_line');

        return [
            'enabled' => true,
            'enough' => true,
            'direction' => $direction,
            'group_size' => $groupSize,
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function toggleGamification(array $user, bool $active): void
    {
        $userId = (int) $user['id'];
        $this->repo->setGamification($userId, $active);
        $row = $this->repo->ensureStreak($userId);

        if (!$active) {
            $row['congelada_desde'] = gmdate('Y-m-d H:i:s');
            $this->repo->saveStreak($userId, $row);
            $this->repo->markAllCelebrated($userId);

            return;
        }

        $current = $this->support()->period($user);
        $previous = $this->previousPeriod($user, $current);

        if ($previous !== null) {
            $lastFin = $row['ultimo_periodo_fin'] ?? null;

            if ($lastFin === null || (string) $lastFin < $previous['fin']) {
                $row['ultimo_periodo_inicio'] = $previous['inicio'];
                $row['ultimo_periodo_fin'] = $previous['fin'];
            }
        }

        $row['congelada_desde'] = null;
        $this->repo->saveStreak($userId, $row);

        $updated = $user;
        $updated['gamificacion_activa'] = 1;
        $this->reconcile($updated);
        $this->refreshLevelIfDue($updated);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function toggleSocialComparison(array $user, bool $active): void
    {
        $this->repo->setSocialComparison((int) $user['id'], $active);
    }

    /**
     * @param array<string, mixed> $user
     * @param array{inicio: string, fin: string} $period
     */
    private function netSavingsInvestment(array $user, array $period): int
    {
        $userId = (int) $user['id'];
        $savings = new SavingsRepository();
        $investment = new InvestmentRepository();

        $savingsNet = $savings->sumPeriodContributions($userId, $period['inicio'], $period['fin'])
            - $savings->sumPeriodWithdrawals($userId, $period['inicio'], $period['fin']);
        $investmentNet = $investment->sumContributions($userId, null, $period['inicio'], $period['fin'])
            - $investment->sumWithdrawals($userId, null, $period['inicio'], $period['fin']);

        return $savingsNet + $investmentNet;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function methodCode(array $user): ?string
    {
        $active = (new UsuarioMetodoRepository())->activeForUser((int) $user['id']);

        if ($active === null) {
            return null;
        }

        $method = (new MetodoRepository())->findById((int) $active['metodo_id']);

        return $method !== null ? (string) $method['codigo'] : null;
    }

    private function support(): FinanceSupportService
    {
        return new FinanceSupportService();
    }

    /**
     * @param array{inicio: string, fin: string} $period
     * @return array{inicio: string, fin: string}|null
     */
    private function previousPeriod(array $user, array $period): ?array
    {
        $reference = (new DateTimeImmutable($period['inicio'], new DateTimeZone('UTC')))->modify('-1 second');
        $calculated = (new PeriodoService())->calcularPeriodo($user, $reference);
        $previous = [
            'inicio' => $calculated['inicio']->format('Y-m-d H:i:s'),
            'fin' => $calculated['fin']->format('Y-m-d H:i:s'),
        ];

        if ($previous['inicio'] === $period['inicio'] && $previous['fin'] === $period['fin']) {
            return null;
        }

        return $previous;
    }

    /**
     * @param array{inicio: string, fin: string} $period
     * @return array{inicio: string, fin: string}|null
     */
    private function nextPeriod(array $user, array $period): ?array
    {
        $reference = (new DateTimeImmutable($period['fin'], new DateTimeZone('UTC')))->modify('+1 second');
        $calculated = (new PeriodoService())->calcularPeriodo($user, $reference);
        $next = [
            'inicio' => $calculated['inicio']->format('Y-m-d H:i:s'),
            'fin' => $calculated['fin']->format('Y-m-d H:i:s'),
        ];

        if ($next['inicio'] === $period['inicio'] && $next['fin'] === $period['fin']) {
            return null;
        }

        return $next;
    }

    /**
     * @return array{estado: string, periodos_consecutivos: int, mejor_racha: int}
     */
    public static function streakTransition(
        string $state,
        int $count,
        bool $hasIncome,
        bool $completed,
        int $best
    ): array {
        $count = max(0, $count);
        $best = max($best, $count);

        if ($hasIncome) {
            $nextCount = in_array($state, ['activa', 'pausa'], true) ? $count + 1 : 1;

            return [
                'estado' => 'activa',
                'periodos_consecutivos' => $nextCount,
                'mejor_racha' => max($best, $nextCount),
            ];
        }

        if (!$completed) {
            return [
                'estado' => $state,
                'periodos_consecutivos' => $count,
                'mejor_racha' => $best,
            ];
        }

        if ($state === 'activa') {
            return [
                'estado' => $count > 0 ? 'pausa' : 'activa',
                'periodos_consecutivos' => $count,
                'mejor_racha' => $best,
            ];
        }

        return [
            'estado' => 'rota',
            'periodos_consecutivos' => 0,
            'mejor_racha' => $best,
        ];
    }

    public static function levelForPercent(float $percent): int
    {
        if ($percent >= 90) {
            return 4;
        }

        if ($percent >= 70) {
            return 3;
        }

        if ($percent >= 50) {
            return 2;
        }

        return 1;
    }

    public static function levelName(int $level): string
    {
        return $level >= 1 && $level <= 4 ? 'gamification.levels.names.' . $level : '';
    }

    /**
     * @return array{level: int, key: string, missing: float, threshold: float}|null
     */
    public static function nextLevel(int $level, float $percent): ?array
    {
        $thresholds = [2 => 50.0, 3 => 70.0, 4 => 90.0];

        if ($level >= 4) {
            return null;
        }

        $next = max(2, $level + 1);
        $threshold = $thresholds[$next] ?? 90.0;

        return [
            'level' => $next,
            'key' => self::levelName($next),
            'missing' => round(max(0, $threshold - $percent), 2),
            'threshold' => $threshold,
        ];
    }

    public static function compliance(bool $controlled, int $assigned, int $actual): float
    {
        if ($assigned <= 0) {
            return 0.0;
        }

        if ($controlled) {
            if ($actual <= $assigned) {
                return 1.0;
            }

            return max(0.0, min(1.0, $assigned / max(1, $actual)));
        }

        return max(0.0, min(1.0, $actual / $assigned));
    }

    /**
     * @param list<array<string, mixed>> $categories
     * @return array{tipo: string, objetivo: ?float, categoria_tipo: ?string}
     */
    public static function challengeCandidate(array $categories): array
    {
        $sums = ['ahorro' => 0.0, 'inversion' => 0.0, 'donacion' => 0.0];

        foreach ($categories as $category) {
            $type = (string) ($category['tipo'] ?? '');

            if (!isset($sums[$type])) {
                continue;
            }

            $sums[$type] += $category['porcentaje'] !== null ? (float) $category['porcentaje'] : 0.0;
        }

        $priority = ['ahorro' => 3, 'inversion' => 2, 'donacion' => 1];
        $bestType = null;
        $best = 0.0;

        foreach (['ahorro', 'inversion', 'donacion'] as $type) {
            if ($sums[$type] > $best + 0.001) {
                $best = $sums[$type];
                $bestType = $type;
                continue;
            }

            if ($bestType !== null && abs($sums[$type] - $best) < 0.001 && $priority[$type] > $priority[$bestType]) {
                $bestType = $type;
            }
        }

        if ($bestType === null || $best <= 0) {
            return ['tipo' => 'registra_todo', 'objetivo' => null, 'categoria_tipo' => null];
        }

        return [
            'tipo' => match ($bestType) {
                'inversion' => 'cumple_inversion',
                'donacion' => 'cumple_donacion',
                default => 'cumple_ahorro',
            },
            'objetivo' => round($best, 2),
            'categoria_tipo' => $bestType,
        ];
    }

    /**
     * @return array{direction: string, percent: ?float, delta: int, comparable: bool}
     */
    public static function compareNet(int $current, int $previous): array
    {
        $delta = $current - $previous;

        if ($previous === 0) {
            if ($current === 0) {
                return ['direction' => 'same', 'percent' => 0.0, 'delta' => 0, 'comparable' => false];
            }

            return [
                'direction' => $delta > 0 ? 'up' : 'down',
                'percent' => null,
                'delta' => $delta,
                'comparable' => false,
            ];
        }

        $percent = round(($delta / abs($previous)) * 100, 1);
        $direction = abs($percent) < 0.05 ? 'same' : ($percent > 0 ? 'up' : 'down');

        return [
            'direction' => $direction,
            'percent' => $percent,
            'delta' => $delta,
            'comparable' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeDetail(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
