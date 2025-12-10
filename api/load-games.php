<?php
/**
 * API: Load Games
 * 
 * Returns games data as JSON with HTML for lazy loading
 * 
 * REFACTORED: Now uses shared functions and constants from includes/
 * 
 * Location: /var/www/u1852176/data/www/streaming/api/load-games.php
 */

header('Content-Type: application/json');

// ==========================================
// LOAD CONFIGURATION AND SHARED FUNCTIONS
// ==========================================
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$sportOffset = isset($_GET['sport_offset']) ? intval($_GET['sport_offset']) : 0;
$sportsPerLoad = isset($_GET['sports_per_load']) ? intval($_GET['sports_per_load']) : DEFAULT_SPORTS_PER_LOAD;
$sport = isset($_GET['sport']) ? $_GET['sport'] : null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Use centralized data.json from constant
// Uses constant: DATA_JSON_FILE

// Check if file exists
if (!file_exists(DATA_JSON_FILE)) {
    echo json_encode([
        'error' => 'Data file not found',
        'path' => DATA_JSON_FILE,
        'debug' => 'Please ensure data.json exists at the specified path'
    ]);
    exit;
}

// Try to read and parse the file
$jsonContent = @file_get_contents(DATA_JSON_FILE);
if ($jsonContent === false) {
    echo json_encode([
        'error' => 'Could not read data file',
        'path' => DATA_JSON_FILE
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

// ==========================================
// NOTE: The following functions are now in includes/functions.php:
// - getTimeCategory()
// - getCountryFlag()
// - getCountryName()
// - formatGameTime()
// - groupGamesBySport()
// - groupByCountryAndLeague()
// ==========================================

$filteredGames = $gamesData;

// Apply sport filter - exact match only
if ($sport) {
    $sportName = str_replace('-', ' ', $sport);
    $filteredGames = array_filter($filteredGames, function($game) use ($sportName) {
        return strtolower($game['sport']) === strtolower($sportName);
    });
}

// Apply time filter (uses getTimeCategory from shared functions)
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

// Group by sport first (uses groupGamesBySport from shared functions)
$allGroupedBySport = groupGamesBySport($filteredGames);

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

// Sport icons mapping (fallback for when icon files don't exist)
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
    'Rugby' => '🏉',
    'Rugby Union' => '🏉',
    'Rugby League' => '🏉',
    'Rugby Sevens' => '🏉',
    'American Football' => '🏈',
    'MMA' => '🥊',
    'Combat Sport' => '🥊',
    'Snooker' => '🎱',
    'E-sports' => '🎮',
    'Motorsport' => '🏎️',
    'Cycling' => '🚴',
    'Athletics' => '🏃',
    'Swimming' => '🏊',
    'Extreme Sport' => '🪂',
];

// Group games to return by sport
$groupedBySport = groupGamesBySport($gamesToReturn);

$html = '';

foreach ($groupedBySport as $sportName => $sportGames) {
    // Use emoji fallback if no icon file
    $sportIcon = $sportsIcons[$sportName] ?? '⚽';
    $sportId = strtolower(str_replace(' ', '-', $sportName));
    
    // Group by country and league (uses groupByCountryAndLeague from shared functions)
    $byCountryLeague = groupByCountryAndLeague($sportGames);
    
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
        
        // Use shared functions for country flag and name
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
            // Use shared formatGameTime function
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