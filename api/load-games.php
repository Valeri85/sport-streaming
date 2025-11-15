<?php

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sportOffset = isset($_GET['sport_offset']) ? intval($_GET['sport_offset']) : 0;
$sportsPerLoad = isset($_GET['sports_per_load']) ? intval($_GET['sports_per_load']) : 2;
$sport = isset($_GET['sport']) ? $_GET['sport'] : null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Use centralized data.json
$jsonFile = '/var/www/u1852176/data/www/data/data.json';

// Check if file exists
if (!file_exists($jsonFile)) {
    echo json_encode([
        'error' => 'Data file not found',
        'path' => $jsonFile,
        'debug' => 'Please ensure data.json exists at the specified path'
    ]);
    exit;
}

// Try to read and parse the file
$jsonContent = @file_get_contents($jsonFile);
if ($jsonContent === false) {
    echo json_encode([
        'error' => 'Could not read data file',
        'path' => $jsonFile
    ]);
    exit;
}

$data = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'error' => 'Invalid JSON in data file',
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

$gamesData = $data['games'] ?? [];

if (empty($gamesData)) {
    echo json_encode([
        'error' => 'No games found in data file',
        'games_count' => count($gamesData)
    ]);
    exit;
}

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

// NEW: Function to check if a sport should be grouped with another
function shouldGroupSports($gameSport, $filterSlug) {
    $gameSportLower = strtolower($gameSport);
    $filterLower = strtolower($filterSlug);
    
    // Special case: Rugby - group all rugby variations together
    if ($filterLower === 'rugby') {
        return strpos($gameSportLower, 'rugby') !== false;
    }
    
    // Special case: Combat - group all combat variations together
    if ($filterLower === 'combat') {
        return strpos($gameSportLower, 'combat') !== false;
    }
    
    // Special case: Water Sports - group all water sport variations together
    if ($filterLower === 'water-sports') {
        return strpos($gameSportLower, 'water') !== false;
    }
    
    // Special case: Winter Sports - group all winter sport variations together
    if ($filterLower === 'winter-sports') {
        return strpos($gameSportLower, 'winter') !== false;
    }
    
    // Default: exact match
    return $gameSportLower === str_replace('-', ' ', $filterLower);
}

$filteredGames = $gamesData;

// Apply sport filter with grouping
if ($sport) {
    $filteredGames = array_filter($filteredGames, function($game) use ($sport) {
        return shouldGroupSports($game['sport'], $sport);
    });
}

// Apply time filter
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

// Group by sport first
$allGroupedBySport = [];
foreach ($filteredGames as $game) {
    $sportName = $game['sport'];
    if (!isset($allGroupedBySport[$sportName])) {
        $allGroupedBySport[$sportName] = [];
    }
    $allGroupedBySport[$sportName][] = $game;
}

// Get sport names
$sportNames = array_keys($allGroupedBySport);
$totalSports = count($sportNames);

// Get the sports to return based on offset
$sportsToReturn = array_slice($sportNames, $sportOffset, $sportsPerLoad);

// Build games array from selected sports
$gamesToReturn = [];
foreach ($sportsToReturn as $sportName) {
    $gamesToReturn = array_merge($gamesToReturn, $allGroupedBySport[$sportName]);
}

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
    'Chess' => '♟️',
    'Rugby' => '🏉'
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
    
    $html .= '<article class="sport-category" id="' . $sportId . '" data-sport="' . htmlspecialchars($sportName) . '">';
    $html .= '<details open>';
    $html .= '<summary class="sport-header">';
    $html .= '<span class="sport-title">';
    $html .= '<span>' . $sportIcon . '</span>';
    $html .= '<span>' . htmlspecialchars($sportName) . '</span>';
    $html .= '<span class="sport-count-badge">' . count($sportGames) . '</span>';
    $html .= '</span>';
    $html .= '</summary>';
    
    foreach ($byCountryLeague as $key => $group) {
        $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
        $countryFlag = getCountryFlag($group['country']);
        $countryName = getCountryName($group['country']);
        
        $html .= '<section class="competition-group" data-league-id="' . $leagueId . '" ';
        $html .= 'data-country="' . htmlspecialchars($group['country']) . '" ';
        $html .= 'data-competition="' . htmlspecialchars($group['competition']) . '">';
        
        $html .= '<div class="competition-header">';
        $html .= '<span class="competition-name">';
        $html .= '<span>' . $countryFlag . '</span>';
        $html .= '<span>' . htmlspecialchars($countryName) . '</span>';
        $html .= '<span>•</span>';
        $html .= '<span>' . htmlspecialchars($group['competition']) . '</span>';
        $html .= '</span>';
        $html .= '<span class="league-favorite" data-league-id="' . $leagueId . '" role="button" aria-label="Favorite league">☆</span>';
        $html .= '</div>';
        
        foreach ($group['games'] as $game) {
            $html .= '<details class="game-item-details" data-game-id="' . $game['id'] . '" data-league-id="' . $leagueId . '">';
            $html .= '<summary class="game-item-summary">';
            $html .= '<time class="game-time" datetime="' . $game['date'] . '">' . formatGameTime($game['date']) . '</time>';
            $html .= '<span class="game-teams">';
            $html .= '<span class="team">';
            $html .= '<span class="team-icon"></span>';
            $html .= htmlspecialchars($game['match']);
            $html .= '</span>';
            $html .= '</span>';
            $html .= '<span class="game-actions">';
            $html .= '<span class="link-count-badge" data-game-id="' . $game['id'] . '">0</span>';
            $html .= '<span class="favorite-star" data-game-id="' . $game['id'] . '" role="button" aria-label="Favorite game">☆</span>';
            $html .= '</span>';
            $html .= '</summary>';
            $html .= '<div class="game-links-container"></div>';
            $html .= '</details>';
        }
        
        $html .= '</section>';
    }
    
    $html .= '</details>';
    $html .= '</article>';
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'total' => $totalGames,
    'totalSports' => $totalSports,
    'sportsLoaded' => $sportOffset + count($sportsToReturn),
    'hasMore' => ($sportOffset + count($sportsToReturn)) < $totalSports,
    'debug' => [
        'sport_offset' => $sportOffset,
        'sports_per_load' => $sportsPerLoad,
        'sports_returned' => count($sportsToReturn),
        'sport_names_returned' => $sportsToReturn,
        'games_returned' => count($gamesToReturn),
        'total_filtered' => $totalGames,
        'sport_filter' => $sport,
        'tab_filter' => $tab,
        'all_sport_names' => $sportNames
    ]
]);