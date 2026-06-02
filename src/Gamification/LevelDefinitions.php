<?php

declare(strict_types=1);

namespace RapportQuest\Gamification;

/**
 * Human-readable level titles and XP thresholds.
 */
class LevelDefinitions
{
    private const LEVELS = [
        1  => ['title' => 'Nybegynder',     'icon' => '🌱', 'xp' => 0],
        2  => ['title' => 'Studerende',     'icon' => '📖', 'xp' => 100],
        3  => ['title' => 'Analytiker',     'icon' => '🔍', 'xp' => 250],
        4  => ['title' => 'Fortolker',      'icon' => '💡', 'xp' => 500],
        5  => ['title' => 'Lærling',        'icon' => '⭐', 'xp' => 850],
        6  => ['title' => 'Praktiker',      'icon' => '🛠️', 'xp' => 1300],
        7  => ['title' => 'Ekspert',        'icon' => '🎓', 'xp' => 1900],
        8  => ['title' => 'Senior',         'icon' => '🏅', 'xp' => 2700],
        9  => ['title' => 'Mester',         'icon' => '🌟', 'xp' => 3700],
        10 => ['title' => 'Eksamensklar',   'icon' => '🏆', 'xp' => 5000],
    ];

    public static function get(int $level): array
    {
        return self::LEVELS[$level] ?? [
            'title' => 'Legende',
            'icon'  => '👑',
            'xp'    => 5000 + ($level - 10) * 1000,
        ];
    }

    public static function all(): array
    {
        return self::LEVELS;
    }
}
