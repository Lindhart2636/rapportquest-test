<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use RapportQuest\Gamification\XpManager;
use RapportQuest\Gamification\StreakManager;
use RapportQuest\Gamification\BadgeManager;

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body) || !isset($body['xp'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ugyldige data']);
    exit;
}

$xp        = max(0, (int) $body['xp']);
$source    = in_array($body['source'] ?? '', ['quiz', 'cloze', 'boss'], true)
             ? $body['source']
             : 'quiz';
$perfectQuiz = !empty($body['perfect_quiz']);
$bossPct     = (int) ($body['boss_pct'] ?? 0);

$sessionId = session_id();

try {
    $pdo           = getDbConnection();
    $xpManager     = new XpManager($pdo);
    $streakManager = new StreakManager($pdo);
    $badgeManager  = new BadgeManager($pdo);

    // 1. Add XP
    $result = $xpManager->addXp($sessionId, $xp);

    // 2. Update streak
    $streak = $streakManager->recordActivity($sessionId);

    // 3. Check badges
    $context = [
        'source'      => $source,
        'perfect_quiz' => $perfectQuiz,
        'boss_pct'    => $bossPct,
    ];
    $newBadges = $badgeManager->checkAndAward($sessionId, $context);

    // 4. Compute XP bar percentage toward next level
    $nextXp = $xpManager->nextLevelThreshold($result['level']);
    $prevXp = $xpManager->nextLevelThreshold($result['level'] - 1);
    $range  = $nextXp - $prevXp;
    $xpIntoLevel = $result['xp'] - $prevXp;
    $xpPct  = $range > 0
        ? min(100, (int) round($xpIntoLevel / $range * 100))
        : 100;

    echo json_encode([
        'xp'          => $result['xp'],
        'xp_gained'   => $result['xp_gained'],
        'level'       => $result['level'],
        'levelled_up' => $result['levelled_up'],
        'next_level_xp' => $nextXp,
        'xp_pct'      => $xpPct,
        'streak'      => $streak,
        'new_badges'  => $newBadges,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Serverfejl']);
}
