<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ExamQuest\BossBattle\BossEvaluator;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body) || !isset($body['answer'], $body['keywords'], $body['points'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ugyldige data']);
    exit;
}

$evaluator = new BossEvaluator();
$result    = $evaluator->evaluate(
    (string) $body['answer'],
    (array)  $body['keywords'],
    (int)    $body['points']
);

echo json_encode($result);
