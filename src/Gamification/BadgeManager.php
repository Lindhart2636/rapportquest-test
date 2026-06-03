<?php

declare(strict_types=1);

namespace ExamQuest\Gamification;

use PDO;

/**
 * Awards badges based on player milestones.
 *
 * Badge types:
 *   first_upload      — Første rapport uploadet
 *   first_quiz        — Første quiz gennemført
 *   first_cloze       — Første Cloze-opgave gennemført
 *   first_boss        — Første Boss Battle gennemført
 *   level_5           — Nået level 5
 *   level_10          — Nået level 10
 *   streak_3          — 3 dages streak
 *   streak_7          — 7 dages streak
 *   streak_30         — 30 dages streak
 *   century           — 100 spørgsmål besvaret (tracked via XP proxy)
 *   perfectionist     — Scorer 100% i en quiz
 *   boss_slayer       — Scorer ≥ 80% i en Boss Battle
 */
class BadgeManager
{
    private const BADGE_DEFINITIONS = [
        'first_upload' => [
            'label'       => 'Første Upload',
            'description' => 'Du uploadede din første rapport',
            'icon'        => '📄',
        ],
        'first_quiz' => [
            'label'       => 'Quizmaster Begynder',
            'description' => 'Du gennemførte din første quiz',
            'icon'        => '🎯',
        ],
        'first_cloze' => [
            'label'       => 'Ordmester',
            'description' => 'Du gennemførte din første Cloze-opgave',
            'icon'        => '✏️',
        ],
        'first_boss' => [
            'label'       => 'Bossudfordrer',
            'description' => 'Du gennemførte dit første Boss Battle',
            'icon'        => '⚔️',
        ],
        'level_5' => [
            'label'       => 'Lærling',
            'description' => 'Du nåede level 5',
            'icon'        => '⭐',
        ],
        'level_10' => [
            'label'       => 'Mester',
            'description' => 'Du nåede level 10',
            'icon'        => '🌟',
        ],
        'streak_3' => [
            'label'       => '3 Dages Streak',
            'description' => 'Du var aktiv 3 dage i træk',
            'icon'        => '🔥',
        ],
        'streak_7' => [
            'label'       => 'Ugekriger',
            'description' => 'Du var aktiv 7 dage i træk',
            'icon'        => '🔥🔥',
        ],
        'streak_30' => [
            'label'       => 'Månedsmester',
            'description' => 'Du var aktiv 30 dage i træk',
            'icon'        => '🏅',
        ],
        'perfectionist' => [
            'label'       => 'Perfektionist',
            'description' => 'Du scorede 100% i en quiz',
            'icon'        => '💯',
        ],
        'boss_slayer' => [
            'label'       => 'Bossdræber',
            'description' => 'Du scorede ≥ 80% i et Boss Battle',
            'icon'        => '🏆',
        ],
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check and award any newly earned badges. Returns list of newly awarded badges.
     */
    public function checkAndAward(string $sessionId, array $context = []): array
    {
        $existing = $this->getEarnedBadgeTypes($sessionId);
        $progress = $this->loadProgress($sessionId);
        $newBadges = [];

        $checks = [
            'first_upload'  => fn() => !empty($context['uploaded']),
            'first_quiz'    => fn() => !empty($context['source']) && $context['source'] === 'quiz',
            'first_cloze'   => fn() => !empty($context['source']) && $context['source'] === 'cloze',
            'first_boss'    => fn() => !empty($context['source']) && $context['source'] === 'boss',
            'level_5'       => fn() => ($progress['level'] ?? 1) >= 5,
            'level_10'      => fn() => ($progress['level'] ?? 1) >= 10,
            'streak_3'      => fn() => ($progress['streak'] ?? 0) >= 3,
            'streak_7'      => fn() => ($progress['streak'] ?? 0) >= 7,
            'streak_30'     => fn() => ($progress['streak'] ?? 0) >= 30,
            'perfectionist' => fn() => !empty($context['perfect_quiz']),
            'boss_slayer'   => fn() => !empty($context['boss_pct']) && $context['boss_pct'] >= 80,
        ];

        foreach ($checks as $type => $condition) {
            if (in_array($type, $existing, true)) {
                continue; // Already earned
            }
            if ($condition()) {
                $this->award($sessionId, $type);
                $newBadges[] = array_merge(
                    self::BADGE_DEFINITIONS[$type],
                    ['type' => $type]
                );
            }
        }

        return $newBadges;
    }

    public function getEarnedBadges(string $sessionId): array
    {
        $types = $this->getEarnedBadgeTypes($sessionId);
        $badges = [];
        foreach ($types as $type) {
            if (isset(self::BADGE_DEFINITIONS[$type])) {
                $badges[] = array_merge(self::BADGE_DEFINITIONS[$type], ['type' => $type]);
            }
        }
        return $badges;
    }

    public static function getAllDefinitions(): array
    {
        return self::BADGE_DEFINITIONS;
    }

    private function award(string $sessionId, string $badgeType): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO badges (session_id, badge_type) VALUES (:sid, :type)'
        );
        $stmt->execute([':sid' => $sessionId, ':type' => $badgeType]);
    }

    private function getEarnedBadgeTypes(string $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT badge_type FROM badges WHERE session_id = :sid'
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    private function loadProgress(string $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT xp, level, streak FROM progress WHERE session_id = :sid'
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetch() ?: ['xp' => 0, 'level' => 1, 'streak' => 0];
    }
}
