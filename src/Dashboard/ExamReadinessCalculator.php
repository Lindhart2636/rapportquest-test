<?php

declare(strict_types=1);

namespace RapportQuest\Dashboard;

use PDO;

/**
 * Calculates the "Eksamen Klar" score (0-100).
 *
 * Weighting (per CLAUDE.md KR-11):
 *   Quiz activity    : 40%
 *   Cloze activity   : 25%
 *   Boss Battle      : 25%
 *   Daily activity   : 10%
 */
class ExamReadinessCalculator
{
    private const WEIGHT_QUIZ     = 0.40;
    private const WEIGHT_CLOZE    = 0.25;
    private const WEIGHT_BOSS     = 0.25;
    private const WEIGHT_ACTIVITY = 0.10;

    // Question count targets considered "complete" for each category
    private const QUIZ_Q_TARGET      = 10;    // 10 quiz questions = full quiz score
    private const CLOZE_Q_TARGET     = 10;    // 10 cloze questions = full cloze score
    private const BOSS_Q_TARGET      = 5;     // 5 boss questions = full boss score
    private const ACTIVITY_DAY_TARGET = 7;    // 7-day streak = full activity score

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{
     *   score: int,
     *   quiz_score: int,
     *   cloze_score: int,
     *   boss_score: int,
     *   activity_score: int,
     *   breakdown: array
     * }
     */
    public function calculate(string $sessionId, int $reportId): array
    {
        $stats = $this->gatherStats($sessionId, $reportId);

        $quizRatio     = min(1.0, $stats['quiz_count']   / self::QUIZ_Q_TARGET);
        $clozeRatio    = min(1.0, $stats['cloze_count']  / self::CLOZE_Q_TARGET);
        $bossRatio     = min(1.0, $stats['boss_count']   / self::BOSS_Q_TARGET);
        $activityRatio = min(1.0, $stats['streak_days']  / self::ACTIVITY_DAY_TARGET);

        $quizScore     = (int) round($quizRatio     * 100);
        $clozeScore    = (int) round($clozeRatio    * 100);
        $bossScore     = (int) round($bossRatio     * 100);
        $activityScore = (int) round($activityRatio * 100);

        $total = (int) round(
            $quizScore     * self::WEIGHT_QUIZ     +
            $clozeScore    * self::WEIGHT_CLOZE    +
            $bossScore     * self::WEIGHT_BOSS     +
            $activityScore * self::WEIGHT_ACTIVITY
        );

        return [
            'score'          => $total,
            'quiz_score'     => $quizScore,
            'cloze_score'    => $clozeScore,
            'boss_score'     => $bossScore,
            'activity_score' => $activityScore,
            'breakdown'      => [
                [
                    'label'    => 'Quiz',
                    'score'    => $quizScore,
                    'weight'   => '40%',
                    'weighted' => (int) round($quizScore * self::WEIGHT_QUIZ),
                    'detail'   => $stats['quiz_count'] . ' / ' . self::QUIZ_Q_TARGET . ' spørgsmål',
                ],
                [
                    'label'    => 'Cloze Mode',
                    'score'    => $clozeScore,
                    'weight'   => '25%',
                    'weighted' => (int) round($clozeScore * self::WEIGHT_CLOZE),
                    'detail'   => $stats['cloze_count'] . ' / ' . self::CLOZE_Q_TARGET . ' opgaver',
                ],
                [
                    'label'    => 'Boss Battle',
                    'score'    => $bossScore,
                    'weight'   => '25%',
                    'weighted' => (int) round($bossScore * self::WEIGHT_BOSS),
                    'detail'   => $stats['boss_count'] . ' / ' . self::BOSS_Q_TARGET . ' spørgsmål',
                ],
                [
                    'label'    => 'Aktivitet',
                    'score'    => $activityScore,
                    'weight'   => '10%',
                    'weighted' => (int) round($activityScore * self::WEIGHT_ACTIVITY),
                    'detail'   => $stats['streak_days'] . ' / ' . self::ACTIVITY_DAY_TARGET . ' dages streak',
                ],
            ],
            'stats' => $stats,
        ];
    }

    private function gatherStats(string $sessionId, int $reportId): array
    {
        // XP per source is tracked via total XP — approximate by activity type ratios
        // Use total XP with category weights as proxy
        $progress = $this->pdo->prepare(
            'SELECT xp, level, streak FROM progress WHERE session_id = :sid'
        );
        $progress->execute([':sid' => $sessionId]);
        $prog = $progress->fetch() ?: ['xp' => 0, 'level' => 1, 'streak' => 0];

        $totalXp    = (int) $prog['xp'];
        $quizCount  = $this->countQuizAnswered($reportId);
        $clozeCount = $this->countClozeAnswered($reportId);
        $bossCount  = $this->countBossAnswered($reportId);

        return [
            'total_xp'    => $totalXp,
            'level'       => (int) $prog['level'],
            'streak_days' => (int) $prog['streak'],
            'quiz_count'  => $quizCount,
            'cloze_count' => $clozeCount,
            'boss_count'  => $bossCount,
        ];
    }

    private function countQuizAnswered(int $reportId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(qq.id) FROM quiz_questions qq
             JOIN quiz_sets qs ON qq.quiz_set_id = qs.id
             WHERE qs.report_id = :id'
        );
        $stmt->execute([':id' => $reportId]);
        return (int) $stmt->fetchColumn();
    }

    private function countClozeAnswered(int $reportId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(cq.id) FROM cloze_questions cq
             JOIN cloze_sets cs ON cq.cloze_set_id = cs.id
             WHERE cs.report_id = :id'
        );
        $stmt->execute([':id' => $reportId]);
        return (int) $stmt->fetchColumn();
    }

    private function countBossAnswered(int $reportId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM boss_battles WHERE report_id = :id'
        );
        $stmt->execute([':id' => $reportId]);
        return (int) $stmt->fetchColumn();
    }
}
