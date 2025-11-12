<?php

header('Content-Type: application/json');

$gameId = isset($_GET['game_id']) ? $_GET['game_id'] : null;

if (!$gameId) {
    echo json_encode(['error' => 'Game ID required']);
    exit;
}

// Use centralized data.json
$jsonFile = '/var/www/u1852176/data/www/data/data.json';

if (!file_exists($jsonFile)) {
    echo json_encode(['error' => 'Data file not found']);
    exit;
}

$jsonContent = file_get_contents($jsonFile);
$data = json_decode($jsonContent, true);
$linksData = $data['links'] ?? [];

$links = isset($linksData[$gameId]) ? $linksData[$gameId] : [];

echo json_encode([
    'success' => true,
    'game_id' => $gameId,
    'links' => $links,
    'count' => count($links)
]);