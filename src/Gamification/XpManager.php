<?php

declare(strict_types=1);

namespace ExamQuest\Gamification;

use PDO;

/**
 * Manages XP and level progression for a session.
 *
 * Level thresholds (cumulative XP required):
 *   Level 1:     0 XP
 *   Level 2:   100 XP
 *   Level 3:   250 XP
 *   Level 4:   500 XP
 *   Level 5:   850 XP
 *   Level 6+:  previous + (level * 200)
 */
class XpManager
{
    private const LEVEL_THRESHOLDS = [0, 100, 250, 500, 850, 1300, 1900, 2700, 3700, 5000];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function addXp(string $sessionId, int $xp): array
    {
        $progress = $this->getOrCreate($sessionId);

        $newXp    = $progress['xp'] + $xp;
        $newLevel = $this->calculateLevel($newXp);

        $stmt = $this->pdo->prepare(
            'UPDATE progress SET xp = :xp, level = :level, last_activity = NOW()
             WHERE session_id = :sid'
        );
        $stmt->execute([':xp' => $newXp, ':level' => $newLevel, ':sid' => $sessionId]);

        return [
            'xp'        => $newXp,
            'xp_gained' => $xp,
            'level'     => $newLevel,
            'levelled_up' => $newLevel > $progress['level'],
            'next_level_xp' => $this->nextLevelThreshold($newLevel),
        ];
    }

    public function getProgress(string $sessionId): array
    {
        return $this->getOrCreate($sessionId);
    }

    public function calculateLevel(int $xp): int
    {
        $level = 1;
        foreach (self::LEVEL_THRESHOLDS as $i => $threshold) {
            if ($xp >= $threshold) {
                $level = $i + 1;
            }
        }
        return $level;
    }

    public function nextLevelThreshold(int $currentLevel): int
    {
        return self::LEVEL_THRESHOLDS[$currentLevel] ?? 99999;
    }

    private function getOrCreate(string $sessionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM progress WHERE session_id = :sid');
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch();

        if ($row) {
            return $row;
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO progress (session_id, xp, level, streak, last_activity)
             VALUES (:sid, 0, 1, 0, NOW())'
        );
        $ins->execute([':sid' => $sessionId]);

        return ['session_id' => $sessionId, 'xp' => 0, 'level' => 1, 'streak' => 0];
    }
}
