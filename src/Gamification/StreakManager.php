<?php

declare(strict_types=1);

namespace ExamQuest\Gamification;

use PDO;

/**
 * Tracks daily activity streaks.
 *
 * Rules:
 *   - Activity today that follows activity yesterday → streak + 1
 *   - Activity today after a gap of more than 1 day  → streak reset to 1
 *   - Multiple activities on same day                → streak unchanged
 */
class StreakManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Record activity for the session. Returns updated streak count.
     */
    public function recordActivity(string $sessionId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT streak, last_activity FROM progress WHERE session_id = :sid'
        );
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch();

        if (!$row) {
            return 1;
        }

        $today        = new \DateTimeImmutable('today');
        $lastActivity = $row['last_activity']
            ? new \DateTimeImmutable($row['last_activity'])
            : null;

        $newStreak = $this->computeStreak((int) $row['streak'], $lastActivity, $today);

        $upd = $this->pdo->prepare(
            'UPDATE progress SET streak = :streak, last_activity = NOW() WHERE session_id = :sid'
        );
        $upd->execute([':streak' => $newStreak, ':sid' => $sessionId]);

        return $newStreak;
    }

    public function getStreak(string $sessionId): int
    {
        $stmt = $this->pdo->prepare('SELECT streak FROM progress WHERE session_id = :sid');
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch();
        return (int) ($row['streak'] ?? 0);
    }

    private function computeStreak(
        int $currentStreak,
        ?\DateTimeImmutable $lastActivity,
        \DateTimeImmutable $today
    ): int {
        if ($lastActivity === null) {
            return 1;
        }

        $lastDay = $lastActivity->setTime(0, 0);
        $diff    = (int) $today->diff($lastDay)->days;

        if ($diff === 0) {
            // Same day — keep streak
            return max(1, $currentStreak);
        }

        if ($diff === 1) {
            // Consecutive day — increment
            return $currentStreak + 1;
        }

        // Gap — reset
        return 1;
    }
}
