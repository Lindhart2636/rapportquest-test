<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use RapportQuest\Gamification\XpManager;

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
$sessionId = session_id();

try {
    $pdo       = getDbConnection();
    $manager   = new XpManager($pdo);
    $result    = $manager->addXp($sessionId, $xp);

    $nextXp    = $manager->nextLevelThreshold($result['level']);
    $xpPct     = $nextXp > 0
        ? min(100, (int) round($result['xp'] / $nextXp * 100))
        : 100;

    echo json_encode([
        'xp'          => $result['xp'],
        'xp_gained'   => $result['xp_gained'],
        'level'       => $result['level'],
        'levelled_up' => $result['levelled_up'],
        'next_level_xp' => $nextXp,
        'xp_pct'      => $xpPct,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Serverfejl']);
}
