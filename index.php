<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$domain = $_SERVER['HTTP_HOST'];
$domain = str_replace('www.', '', $domain);

$configFile = __DIR__ . '/config/' . $domain . '.php';

if (file_exists($configFile)) {
    $config = include($configFile);
} else {
    die("Configuration not found for domain: " . htmlspecialchars($domain));
}

$siteName = $config['site_name'];
$logo = $config['logo'];
$primaryColor = $config['theme']['primary_color'];
$secondaryColor = $config['theme']['secondary_color'];
$seoTitle = $config['seo']['title'];
$seoDescription = $config['seo']['description'];
$seoKeywords = $config['seo']['keywords'];
$language = $config['language'];

$jsonFile = __DIR__ . '/data.json';
$gamesData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    $gamesData = $data['games'] ?? [];
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$activeSport = isset($_GET['sport']) ? $_GET['sport'] : null;

if (!$activeSport) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = trim($path, '/');
    
    if (preg_match('/^live-(.+)$/', $path, $matches)) {
        $activeSport = $matches[1];
    }
}

$viewFavorites = false;
$favoritesCount = 0;

if (isset($_GET['favorites'])) {
    $viewFavorites = true;
    $favoritesCount = intval($_GET['favorites']);
}

function groupGamesBySport($games) {
    $grouped = [];
    foreach ($games as $game) {
        $sport = $game['sport'];
        if (!isset($grouped[$sport])) {
            $grouped[$sport] = [];
        }
        $grouped[$sport][] = $game;
    }
    return $grouped;
}

function groupByCompetition($games) {
    $grouped = [];
    foreach ($games as $game) {
        $comp = $game['competition'];
        if (!isset($grouped[$comp])) {
            $grouped[$comp] = [];
        }
        $grouped[$comp][] = $game;
    }
    return $grouped;
}

function formatGameTime($dateString) {
    $timestamp = strtotime($dateString);
    return date('H:i', $timestamp);
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

$filteredGames = $gamesData;

if ($viewFavorites) {
    $filteredGames = $gamesData;
} else {
    if ($activeSport) {
        $filteredGames = array_filter($filteredGames, function($game) use ($activeSport) {
            return strtolower($game['sport']) === strtolower(str_replace('-', ' ', $activeSport));
        });
    }
    
    if ($activeTab === 'soon') {
        $filteredGames = array_filter($filteredGames, function($game) {
            return getTimeCategory($game['date']) === 'soon';
        });
    } elseif ($activeTab === 'tomorrow') {
        $filteredGames = array_filter($filteredGames, function($game) {
            return getTimeCategory($game['date']) === 'tomorrow';
        });
    }
}

$groupedBySport = groupGamesBySport($filteredGames);

$sportCounts = [];
foreach ($gamesData as $game) {
    $sport = $game['sport'];
    if (!isset($sportCounts[$sport])) {
        $sportCounts[$sport] = 0;
    }
    $sportCounts[$sport]++;
}

?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $seoTitle; ?></title>
    <meta name="description" content="<?php echo $seoDescription; ?>">
    <meta name="keywords" content="<?php echo $seoKeywords; ?>">
    <link rel="stylesheet" href="/styles.css">
    <style>
        .logo {
            background-color: <?php echo $primaryColor; ?>;
        }
        
        .home-link.active,
        .menu-item.active {
            background-color: <?php echo $primaryColor; ?>22;
            border-left: 3px solid <?php echo $primaryColor; ?>;
        }
        
        .sport-count-badge {
            background-color: <?php echo $primaryColor; ?>;
        }
        
        .date-tab.active {
            background-color: <?php echo $secondaryColor; ?>;
        }
    </style>
</head>
<body data-viewing-favorites="<?php echo $viewFavorites ? 'true' : 'false'; ?>" data-primary-color="<?php echo $primaryColor; ?>">
    <div class="sidebar">
        <div class="logo">
            <a href="/">
                <h1>
                    <span class="logo-icon"><?php echo $logo; ?></span>
                    <?php echo $siteName; ?>
                </h1>
            </a>
        </div>

        <div class="favorites-section">
            <a href="#" class="favorites-link <?php echo $viewFavorites ? 'active' : ''; ?>" id="favoritesLink" onclick="navigateToFavorites(event)">
                <span>⭐</span>
                <span>Favorites</span>
                <span class="favorites-count" id="favoritesCount">0</span>
            </a>
        </div>

        <div class="section-title">Sports</div>

        <div class="sports-menu">
            <a href="/" class="menu-item <?php echo (!$viewFavorites && !$activeSport) ? 'active' : ''; ?>">
                <div class="menu-item-left">
                    <div class="sport-icon">🏠</div>
                    <span class="sport-name">Home</span>
                </div>
            </a>
            
            <?php
            foreach ($sportCounts as $sportName => $count):
                $icon = $sportsIcons[$sportName] ?? '⚽';
                $sportSlug = strtolower(str_replace(' ', '-', $sportName));
                $isActive = ($activeSport === $sportSlug && !$viewFavorites);
            ?>
                <a href="/live-<?php echo $sportSlug; ?>" class="menu-item <?php echo $isActive ? 'active' : ''; ?>" onclick="saveScrollPosition(event)">
                    <div class="menu-item-left">
                        <div class="sport-icon"><?php echo $icon; ?></div>
                        <span class="sport-name"><?php echo $sportName; ?></span>
                    </div>
                    <span class="sport-count"><?php echo $count; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1><?php echo $viewFavorites ? 'My Favorites' : ($activeSport ? ucwords(str_replace('-', ' ', $activeSport)) : 'Live Sports Streaming'); ?></h1>
            <?php if (!$viewFavorites): ?>
            <div class="date-tabs">
                <a href="<?php echo $activeSport ? '/live-'.$activeSport : '/'; ?>" class="date-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>">All</a>
                <a href="<?php echo $activeSport ? '/live-'.$activeSport.'?tab=soon' : '/?tab=soon'; ?>" class="date-tab <?php echo $activeTab === 'soon' ? 'active' : ''; ?>">Soon</a>
                <a href="<?php echo $activeSport ? '/live-'.$activeSport.'?tab=tomorrow' : '/?tab=tomorrow'; ?>" class="date-tab <?php echo $activeTab === 'tomorrow' ? 'active' : ''; ?>">Tomorrow</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-section">
            <?php if ($viewFavorites): ?>
                <div id="favoritesContainer">
                    <div class="no-games">
                        <p>Loading favorites...</p>
                    </div>
                </div>
                <div id="templateData" style="display: none;">
                    <?php
                    $allGroupedBySport = groupGamesBySport($gamesData);
                    foreach ($allGroupedBySport as $sportName => $sportGames):
                    ?>
                        <div class="sport-category" data-sport="<?php echo $sportName; ?>">
                            <div class="sport-header">
                                <div class="sport-title">
                                    <span><?php echo $sportsIcons[$sportName] ?? '⚽'; ?></span>
                                    <span><?php echo $sportName; ?></span>
                                    <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                                </div>
                                <span class="accordion-arrow">▼</span>
                            </div>
                            
                            <?php
                            $byCompetition = groupByCompetition($sportGames);
                            foreach ($byCompetition as $competition => $compGames):
                                $leagueId = 'league-' . md5($sportName . $competition);
                            ?>
                                <div class="competition-group" data-league-id="<?php echo $leagueId; ?>" data-competition="<?php echo htmlspecialchars($competition); ?>">
                                    <div class="competition-header">
                                        <div class="competition-name">
                                            <span>📋</span>
                                            <span><?php echo htmlspecialchars($competition); ?></span>
                                        </div>
                                        <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>">☆</span>
                                    </div>
                                    
                                    <?php foreach ($compGames as $game): ?>
                                        <div class="game-item" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                            <div class="game-time">
                                                <?php echo formatGameTime($game['date']); ?>
                                            </div>
                                            <div class="game-teams">
                                                <div class="team">
                                                    <span class="team-icon"></span>
                                                    <?php echo htmlspecialchars($game['match']); ?>
                                                </div>
                                            </div>
                                            <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>">☆</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (empty($groupedBySport)): ?>
                <div class="no-games">
                    <p>No games available for this time period</p>
                </div>
            <?php else: ?>
                <?php foreach ($groupedBySport as $sportName => $sportGames): ?>
                    <div class="sport-category" id="<?php echo strtolower(str_replace(' ', '-', $sportName)); ?>" data-sport="<?php echo $sportName; ?>">
                        <div class="sport-header">
                            <div class="sport-title">
                                <span><?php echo $sportsIcons[$sportName] ?? '⚽'; ?></span>
                                <span><?php echo $sportName; ?></span>
                                <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                            </div>
                            <span class="accordion-arrow">▼</span>
                        </div>
                        
                        <?php
                        $byCompetition = groupByCompetition($sportGames);
                        foreach ($byCompetition as $competition => $compGames):
                            $leagueId = 'league-' . md5($sportName . $competition);
                        ?>
                            <div class="competition-group" data-league-id="<?php echo $leagueId; ?>" data-competition="<?php echo htmlspecialchars($competition); ?>">
                                <div class="competition-header">
                                    <div class="competition-name">
                                        <span>📋</span>
                                        <span><?php echo htmlspecialchars($competition); ?></span>
                                    </div>
                                    <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>">☆</span>
                                </div>
                                
                                <?php foreach ($compGames as $game): ?>
                                    <div class="game-item" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                        <div class="game-time">
                                            <?php echo formatGameTime($game['date']); ?>
                                        </div>
                                        <div class="game-teams">
                                            <div class="team">
                                                <span class="team-icon"></span>
                                                <?php echo htmlspecialchars($game['match']); ?>
                                            </div>
                                        </div>
                                        <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>">☆</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="/main.js"></script>
</body>
</html>