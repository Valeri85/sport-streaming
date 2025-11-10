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
$linksData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    $gamesData = $data['games'] ?? [];
    $linksData = $data['links'] ?? [];
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
if (strpos($_SERVER['REQUEST_URI'], '/favorites') !== false) {
    $viewFavorites = true;
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

function groupByCountryAndLeague($games) {
    $grouped = [];
    foreach ($games as $game) {
        $country = $game['country'];
        $comp = $game['competition'];
        $key = $country . '|||' . $comp;
        
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'country' => $country,
                'competition' => $comp,
                'games' => []
            ];
        }
        $grouped[$key]['games'][] = $game;
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

function getLinkCount($gameId, $linksData) {
    return isset($linksData[$gameId]) ? count($linksData[$gameId]) : 0;
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
<body data-viewing-favorites="<?php echo $viewFavorites ? 'true' : 'false'; ?>" 
      data-primary-color="<?php echo $primaryColor; ?>"
      data-active-sport="<?php echo $activeSport ?: ''; ?>"
      data-active-tab="<?php echo $activeTab; ?>"
      data-links='<?php echo json_encode($linksData); ?>'>
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
            <a href="/favorites" class="favorites-link <?php echo $viewFavorites ? 'active' : ''; ?>" id="favoritesLink">
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

    <div class="main-wrapper">
        <div class="main-content">
            <div class="header">
                <div class="header-left">
                    <h1><?php echo $viewFavorites ? 'My Favorites' : ($activeSport ? ucwords(str_replace('-', ' ', $activeSport)) : 'Live Sports Streaming'); ?></h1>
                </div>
                <div class="header-right">
                    <button id="themeToggle" class="theme-toggle" title="Toggle Dark Mode">
                        <span class="theme-icon">🌙</span>
                    </button>
                </div>
            </div>

            <?php if (!$viewFavorites): ?>
            <div class="date-tabs-wrapper">
                <div class="date-tabs">
                    <a href="<?php echo $activeSport ? '/live-'.$activeSport : '/'; ?>" class="date-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="<?php echo $activeSport ? '/live-'.$activeSport.'?tab=soon' : '/?tab=soon'; ?>" class="date-tab <?php echo $activeTab === 'soon' ? 'active' : ''; ?>">Soon</a>
                    <a href="<?php echo $activeSport ? '/live-'.$activeSport.'?tab=tomorrow' : '/?tab=tomorrow'; ?>" class="date-tab <?php echo $activeTab === 'tomorrow' ? 'active' : ''; ?>">Tomorrow</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-section" id="mainContent">
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
                                <details open>
                                    <summary class="sport-header">
                                        <div class="sport-title">
                                            <span><?php echo $sportsIcons[$sportName] ?? '⚽'; ?></span>
                                            <span><?php echo $sportName; ?></span>
                                            <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                                        </div>
                                    </summary>
                                    
                                    <?php
                                    $byCountryLeague = groupByCountryAndLeague($sportGames);
                                    foreach ($byCountryLeague as $key => $group):
                                        $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
                                        $countryFlag = getCountryFlag($group['country']);
                                        $countryName = getCountryName($group['country']);
                                    ?>
                                        <div class="competition-group" data-league-id="<?php echo $leagueId; ?>" 
                                             data-country="<?php echo htmlspecialchars($group['country']); ?>"
                                             data-competition="<?php echo htmlspecialchars($group['competition']); ?>">
                                            <div class="competition-header">
                                                <div class="competition-name">
                                                    <span><?php echo $countryFlag; ?></span>
                                                    <span><?php echo htmlspecialchars($countryName); ?></span>
                                                    <span>•</span>
                                                    <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                                </div>
                                                <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>">☆</span>
                                            </div>
                                            
                                            <?php foreach ($group['games'] as $game): 
                                                $linkCount = getLinkCount($game['id'], $linksData);
                                            ?>
                                                <details class="game-item-details" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                                    <summary class="game-item-summary">
                                                        <div class="game-time">
                                                            <?php echo formatGameTime($game['date']); ?>
                                                        </div>
                                                        <div class="game-teams">
                                                            <div class="team">
                                                                <span class="team-icon"></span>
                                                                <?php echo htmlspecialchars($game['match']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="game-actions">
                                                            <?php if ($linkCount > 0): ?>
                                                                <span class="link-count-badge"><?php echo $linkCount; ?></span>
                                                            <?php endif; ?>
                                                            <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>">☆</span>
                                                        </div>
                                                    </summary>
                                                    <div class="game-links-container"></div>
                                                </details>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (empty($groupedBySport)): ?>
                    <div class="no-games">
                        <p>No games available for this time period</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $displayedGames = 0;
                    $maxInitialGames = 30;
                    foreach ($groupedBySport as $sportName => $sportGames): 
                        if ($displayedGames >= $maxInitialGames && !$activeSport) break;
                    ?>
                        <div class="sport-category" id="<?php echo strtolower(str_replace(' ', '-', $sportName)); ?>" data-sport="<?php echo $sportName; ?>">
                            <details open>
                                <summary class="sport-header">
                                    <div class="sport-title">
                                        <span><?php echo $sportsIcons[$sportName] ?? '⚽'; ?></span>
                                        <span><?php echo $sportName; ?></span>
                                        <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                                    </div>
                                </summary>
                                
                                <?php
                                $byCountryLeague = groupByCountryAndLeague($sportGames);
                                foreach ($byCountryLeague as $key => $group):
                                    if ($displayedGames >= $maxInitialGames && !$activeSport) break;
                                    
                                    $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
                                    $countryFlag = getCountryFlag($group['country']);
                                    $countryName = getCountryName($group['country']);
                                ?>
                                    <div class="competition-group" data-league-id="<?php echo $leagueId; ?>" 
                                         data-country="<?php echo htmlspecialchars($group['country']); ?>"
                                         data-competition="<?php echo htmlspecialchars($group['competition']); ?>">
                                        <div class="competition-header">
                                            <div class="competition-name">
                                                <span><?php echo $countryFlag; ?></span>
                                                <span><?php echo htmlspecialchars($countryName); ?></span>
                                                <span>•</span>
                                                <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                            </div>
                                            <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>">☆</span>
                                        </div>
                                        
                                        <?php foreach ($group['games'] as $game): 
                                            if ($displayedGames >= $maxInitialGames && !$activeSport) break;
                                            $displayedGames++;
                                            $linkCount = getLinkCount($game['id'], $linksData);
                                        ?>
                                            <details class="game-item-details" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                                <summary class="game-item-summary">
                                                    <div class="game-time">
                                                        <?php echo formatGameTime($game['date']); ?>
                                                    </div>
                                                    <div class="game-teams">
                                                        <div class="team">
                                                            <span class="team-icon"></span>
                                                            <?php echo htmlspecialchars($game['match']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="game-actions">
                                                        <?php if ($linkCount > 0): ?>
                                                            <span class="link-count-badge"><?php echo $linkCount; ?></span>
                                                        <?php endif; ?>
                                                        <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>">☆</span>
                                                    </div>
                                                </summary>
                                                <div class="game-links-container"></div>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </details>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($displayedGames >= $maxInitialGames && !$activeSport): ?>
                        <div id="loadMoreTrigger" style="height: 1px;"></div>
                        <div id="loadingIndicator" style="display: none; text-align: center; padding: 20px;">
                            <p>Loading more games...</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-sidebar">
            <div class="sidebar-content">
                <h3>About</h3>
                <p>Add your content here...</p>
            </div>
        </div>
    </div>

    <script src="/main.js"></script>
</body>
</html>