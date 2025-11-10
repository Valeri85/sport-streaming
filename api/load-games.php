<?php

header('Content-Type: application/json');

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 30;
$sport = isset($_GET['sport']) ? $_GET['sport'] : null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

$jsonFile = __DIR__ . '/../data.json';

if (!file_exists($jsonFile)) {
    echo json_encode(['error' => 'Data file not found']);
    exit;
}

$jsonContent = file_get_contents($jsonFile);
$data = json_decode($jsonContent, true);
$gamesData = $data['games'] ?? [];
$linksData = $data['links'] ?? [];

function getTimeCategory($dateString) {
    $gameTime = strtotime($dateString);
    $now = time();
    $diff = $gameTime - $now;
    
    if ($diff <= 1800 && $diff >= 0) {
        return 'soon';
    }
    
    $tomorrow = strtotime('tomorrow 00:00:00');
    $dayAfter = strtotime('tomorrow 23:59:59');
    
    if ($gameTime >= $tomorrow && $gameTime <= $dayAfter) {
        return 'tomorrow';
    }
    
    return 'other';
}

function getCountryFlag($countryFile) {
    $country = str_replace('.png', '', $countryFile);
    $country = str_replace('-', ' ', $country);
    
    $flags = [
        'United states' => '🇺🇸',
        'Russia' => '🇷🇺',
        'Germany' => '🇩🇪',
        'Italy' => '🇮🇹',
        'International' => '🌍',
        'Europe' => '🇪🇺',
        'Worldwide' => '🌐',
        'Colombia' => '🇨🇴',
    ];
    
    return $flags[$country] ?? '🌐';
}

function getCountryName($countryFile) {
    $country = str_replace('.png', '', $countryFile);
    return str_replace('-', ' ', ucwords($country));
}

function formatGameTime($dateString) {
    $timestamp = strtotime($dateString);
    return date('H:i', $timestamp);
}

function getLinkCount($gameId, $linksData) {
    return isset($linksData[$gameId]) ? count($linksData[$gameId]) : 0;
}

$filteredGames = $gamesData;

if ($sport) {
    $filteredGames = array_filter($filteredGames, function($game) use ($sport) {
        return strtolower($game['sport']) === strtolower(str_replace('-', ' ', $sport));
    });
}

if ($tab === 'soon') {
    $filteredGames = array_filter($filteredGames, function($game) {
        return getTimeCategory($game['date']) === 'soon';
    });
} elseif ($tab === 'tomorrow') {
    $filteredGames = array_filter($filteredGames, function($game) {
        return getTimeCategory($game['date']) === 'tomorrow';
    });
}

$filteredGames = array_values($filteredGames);
$totalGames = count($filteredGames);
$gamesToReturn = array_slice($filteredGames, $offset, $limit);

$sportsIcons = [
    'Football' => '⚽',
    'Basketball' => '🏀',
    'Ice Hockey' => '🏒',
    'Baseball' => '⚾',
    'Tennis' => '🎾',
    'Volleyball' => '🏐',
    'Handball' => '🤾',
    'Cricket' => '🏏',
    'Table Tennis' => '🏓',
    'Badminton' => '🏸',
    'Darts' => '🎯',
    'Billiard' => '🎱',
    'Winter Sport' => '⛷️',
    'Netball' => '🏐',
    'Futsal' => '⚽',
    'Bowling' => '🎳',
    'Water Polo' => '🤽',
    'Golf' => '⛳',
    'Racing' => '🏎️',
    'Boxing' => '🥊',
    'Chess' => '♟️'
];

$groupedBySport = [];
foreach ($gamesToReturn as $game) {
    $sportName = $game['sport'];
    if (!isset($groupedBySport[$sportName])) {
        $groupedBySport[$sportName] = [];
    }
    $groupedBySport[$sportName][] = $game;
}

$html = '';

foreach ($groupedBySport as $sportName => $sportGames) {
    $sportIcon = $sportsIcons[$sportName] ?? '⚽';
    $sportId = strtolower(str_replace(' ', '-', $sportName));
    
    $byCountryLeague = [];
    foreach ($sportGames as $game) {
        $key = $game['country'] . '|||' . $game['competition'];
        if (!isset($byCountryLeague[$key])) {
            $byCountryLeague[$key] = [
                'country' => $game['country'],
                'competition' => $game['competition'],
                'games' => []
            ];
        }
        $byCountryLeague[$key]['games'][] = $game;
    }
    
    $html .= '<div class="sport-category" id="' . $sportId . '" data-sport="' . htmlspecialchars($sportName) . '">';
    $html .= '<details open>';
    $html .= '<summary class="sport-header">';
    $html .= '<div class="sport-title">';
    $html .= '<span>' . $sportIcon . '</span>';
    $html .= '<span>' . htmlspecialchars($sportName) . '</span>';
    $html .= '<span class="sport-count-badge">' . count($sportGames) . '</span>';
    $html .= '</div>';
    $html .= '</summary>';
    
    foreach ($byCountryLeague as $key => $group) {
        $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
        $countryFlag = getCountryFlag($group['country']);
        $countryName = getCountryName($group['country']);
        
        $html .= '<div class="competition-group" data-league-id="' . $leagueId . '" ';
        $html .= 'data-country="' . htmlspecialchars($group['country']) . '" ';
        $html .= 'data-competition="' . htmlspecialchars($group['competition']) . '">';
        
        $html .= '<div class="competition-header">';
        $html .= '<div class="competition-name">';
        $html .= '<span>' . $countryFlag . '</span>';
        $html .= '<span>' . htmlspecialchars($countryName) . '</span>';
        $html .= '<span>•</span>';
        $html .= '<span>' . htmlspecialchars($group['competition']) . '</span>';
        $html .= '</div>';
        $html .= '<span class="league-favorite" data-league-id="' . $leagueId . '">☆</span>';
        $html .= '</div>';
        
        foreach ($group['games'] as $game) {
            $linkCount = getLinkCount($game['id'], $linksData);
            
            $html .= '<details class="game-item-details" data-game-id="' . $game['id'] . '" data-league-id="' . $leagueId . '">';
            $html .= '<summary class="game-item-summary">';
            $html .= '<div class="game-time">' . formatGameTime($game['date']) . '</div>';
            $html .= '<div class="game-teams">';
            $html .= '<div class="team">';
            $html .= '<span class="team-icon"></span>';
            $html .= htmlspecialchars($game['match']);
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="game-actions">';
            
            if ($linkCount > 0) {
                $html .= '<span class="link-count-badge">' . $linkCount . '</span>';
            }
            
            $html .= '<span class="favorite-star" data-game-id="' . $game['id'] . '">☆</span>';
            $html .= '</div>';
            $html .= '</summary>';
            $html .= '<div class="game-links-container"></div>';
            $html .= '</details>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</details>';
    $html .= '</div>';
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'total' => $totalGames,
    'loaded' => $offset + count($gamesToReturn),
    'hasMore' => ($offset + count($gamesToReturn)) < $totalGames
]);