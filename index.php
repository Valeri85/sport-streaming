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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background-color: #ffffff;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
        }

        .logo {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            background-color: <?php echo $primaryColor; ?>;
        }

        .logo a {
            text-decoration: none;
            color: inherit;
        }

        .logo h1 {
            font-size: 24px;
            color: #ffffff;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            font-size: 32px;
        }

        .home-section {
            padding: 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .home-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            padding: 15px 20px;
            transition: background-color 0.2s;
        }

        .home-link:hover {
            background-color: #f8f9fa;
        }

        .home-link.active {
            background-color: <?php echo $primaryColor; ?>22;
            border-left: 3px solid <?php echo $primaryColor; ?>;
            padding-left: 17px;
        }

        .home-section {
            padding: 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .home-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            padding: 15px 20px;
            transition: background-color 0.2s;
        }

        .home-link:hover {
            background-color: #f8f9fa;
        }

        .home-link.active {
            background-color: <?php echo $primaryColor; ?>22;
            border-left: 3px solid <?php echo $primaryColor; ?>;
            padding-left: 17px;
        }

        .favorites-section {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #fffbf0;
        }

        .favorites-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            padding: 10px;
            border-radius: 6px;
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .favorites-link:hover {
            background-color: #fff4d6;
        }

        .favorites-link.active {
            background-color: #ffd700;
            color: #000;
        }

        .favorites-count {
            margin-left: auto;
            background-color: #ff6b35;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .section-title {
            padding: 10px 20px;
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
        }

        .sports-menu {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 20px;
        }

        .sports-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sports-menu::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sports-menu::-webkit-scrollbar-thumb {
            background: #c0c0c0;
            border-radius: 3px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            color: #2c3e50;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .menu-item:hover {
            background-color: #f8f9fa;
        }

        .menu-item.active {
            background-color: <?php echo $primaryColor; ?>22;
            border-left: 3px solid <?php echo $primaryColor; ?>;
            padding-left: 17px;
        }

        .menu-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sport-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .sport-name {
            font-size: 15px;
            font-weight: 500;
        }

        .sport-count {
            font-size: 13px;
            color: #95a5a6;
            font-weight: 500;
        }

        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 20px;
        }

        .header {
            background-color: #ffffff;
            padding: 25px 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .date-tabs {
            display: flex;
            gap: 10px;
        }

        .date-tab {
            padding: 10px 20px;
            background-color: #f8f9fa;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .date-tab.active {
            background-color: <?php echo $secondaryColor; ?>;
            color: white;
        }

        .date-tab:hover {
            background-color: #e0e0e0;
        }

        .date-tab.active:hover {
            opacity: 0.9;
        }

        .content-section {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .sport-category {
            margin-bottom: 30px;
        }

        .sport-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            user-select: none;
        }

        .sport-header:hover {
            background-color: #e9ecef;
        }

        .sport-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }

        .sport-count-badge {
            background-color: <?php echo $primaryColor; ?>;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .accordion-arrow {
            font-size: 16px;
            color: #95a5a6;
            transition: transform 0.3s;
        }

        .accordion-arrow.collapsed {
            transform: rotate(-90deg);
        }

        .competition-group {
            margin-bottom: 15px;
            border-left: 3px solid #e0e0e0;
            padding-left: 15px;
        }

        .competition-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #3498db;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 10px;
            padding: 8px 0;
        }

        .competition-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .league-favorite {
            color: #ddd;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.2s;
        }

        .league-favorite:hover {
            color: #ffd700;
        }

        .league-favorite.favorited {
            color: #ffd700;
        }

        .game-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .game-item:hover {
            background-color: #fafafa;
        }

        .game-time {
            font-size: 14px;
            color: #ff6b35;
            font-weight: 600;
            min-width: 50px;
        }

        .game-teams {
            flex: 1;
            margin-left: 15px;
        }

        .team {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 4px 0;
            font-size: 14px;
        }

        .team-icon {
            width: 16px;
            height: 16px;
            background-color: #ddd;
            border-radius: 50%;
        }

        .favorite-star {
            color: #ddd;
            cursor: pointer;
            font-size: 18px;
            margin-left: 15px;
            transition: color 0.2s;
        }

        .favorite-star:hover {
            color: #ffd700;
        }

        .favorite-star.favorited {
            color: #ffd700;
        }

        .no-games {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }
            .main-content {
                margin-left: 220px;
            }
        }
    </style>
</head>
<body>
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

    <script>
        let favoriteGames = JSON.parse(localStorage.getItem('favoriteGames') || '[]');
        let favoriteLeagues = JSON.parse(localStorage.getItem('favoriteLeagues') || '[]');
        const isViewingFavorites = <?php echo $viewFavorites ? 'true' : 'false'; ?>;

        console.log('Favorites loaded:', {
            games: favoriteGames.length,
            leagues: favoriteLeagues.length,
            total: favoriteGames.length + favoriteLeagues.length
        });

        function saveScrollPosition(event) {
            const scrollPos = document.querySelector('.sports-menu').scrollTop;
            localStorage.setItem('menuScrollPosition', scrollPos);
        }

        function restoreScrollPosition() {
            const scrollPos = localStorage.getItem('menuScrollPosition');
            if (scrollPos) {
                const menu = document.querySelector('.sports-menu');
                if (menu) {
                    setTimeout(() => {
                        menu.scrollTop = parseInt(scrollPos);
                    }, 50);
                }
            }
        }

        function navigateToFavorites(event) {
            event.preventDefault();
            const totalFavorites = favoriteGames.length + favoriteLeagues.length;
            const count = totalFavorites > 0 ? totalFavorites : 1;
            window.location.href = '/?favorites=' + count;
        }

        function updateFavoritesCount() {
            const totalFavorites = favoriteGames.length + favoriteLeagues.length;
            const countElement = document.getElementById('favoritesCount');
            
            if (countElement) {
                countElement.textContent = totalFavorites;
                console.log('Count updated to:', totalFavorites);
            }
        }

        function filterFavoritesView() {
            if (!isViewingFavorites) return;

            const container = document.getElementById('favoritesContainer');
            const templateData = document.getElementById('templateData');
            
            if (!templateData) return;
            
            let hasAnyFavorites = false;
            let favoritesHTML = '';
            const allSportsData = {};

            templateData.querySelectorAll('.sport-category').forEach(category => {
                const sportName = category.getAttribute('data-sport');
                const sportIcon = category.querySelector('.sport-title span:first-child')?.textContent || '⚽';
                
                const competitions = category.querySelectorAll('.competition-group');
                competitions.forEach(compGroup => {
                    const leagueId = compGroup.getAttribute('data-league-id');
                    const competition = compGroup.getAttribute('data-competition');
                    
                    const games = compGroup.querySelectorAll('.game-item');
                    games.forEach(game => {
                        const gameId = game.getAttribute('data-game-id');
                        const gameLeagueId = game.getAttribute('data-league-id');
                        
                        if (favoriteGames.includes(gameId) || favoriteLeagues.includes(gameLeagueId)) {
                            if (!allSportsData[sportName]) {
                                allSportsData[sportName] = {
                                    icon: sportIcon,
                                    competitions: {}
                                };
                            }
                            
                            if (!allSportsData[sportName].competitions[competition]) {
                                allSportsData[sportName].competitions[competition] = {
                                    leagueId: leagueId,
                                    games: []
                                };
                            }
                            
                            allSportsData[sportName].competitions[competition].games.push(game.outerHTML);
                        }
                    });
                });
            });

            for (const sportName in allSportsData) {
                const sport = allSportsData[sportName];
                let sportGameCount = 0;
                let competitionsHTML = '';

                for (const compName in sport.competitions) {
                    const comp = sport.competitions[compName];
                    sportGameCount += comp.games.length;
                    
                    const isLeagueFavorited = favoriteLeagues.includes(comp.leagueId);
                    
                    competitionsHTML += `
                        <div class="competition-group" data-league-id="${comp.leagueId}">
                            <div class="competition-header">
                                <div class="competition-name">
                                    <span>📋</span>
                                    <span>${compName}</span>
                                </div>
                                <span class="league-favorite ${isLeagueFavorited ? 'favorited' : ''}" data-league-id="${comp.leagueId}">${isLeagueFavorited ? '★' : '☆'}</span>
                            </div>
                            ${comp.games.join('')}
                        </div>
                    `;
                }

                if (sportGameCount > 0) {
                    hasAnyFavorites = true;
                    favoritesHTML += `
                        <div class="sport-category">
                            <div class="sport-header">
                                <div class="sport-title">
                                    <span>${sport.icon}</span>
                                    <span>${sportName}</span>
                                    <span class="sport-count-badge">${sportGameCount}</span>
                                </div>
                                <span class="accordion-arrow">▼</span>
                            </div>
                            ${competitionsHTML}
                        </div>
                    `;
                }
            }

            if (hasAnyFavorites) {
                container.innerHTML = favoritesHTML;
                
                container.querySelectorAll('.sport-header').forEach(header => {
                    header.addEventListener('click', toggleSportCategory);
                });
                
                container.querySelectorAll('.favorite-star').forEach(star => {
                    const gameId = star.getAttribute('data-game-id');
                    if (favoriteGames.includes(gameId)) {
                        star.textContent = '★';
                        star.classList.add('favorited');
                    }
                    star.addEventListener('click', handleGameFavorite);
                });
                
                container.querySelectorAll('.league-favorite').forEach(star => {
                    star.addEventListener('click', handleLeagueFavorite);
                });
                
                updateFavoritesCount();
            } else {
                container.innerHTML = '<div class="no-games"><p>No favorite games yet. Click ⭐ to add favorites!</p></div>';
                updateFavoritesCount();
            }
        }

        function loadFavorites() {
            document.querySelectorAll('.favorite-star').forEach(star => {
                const gameId = star.getAttribute('data-game-id');
                if (favoriteGames.includes(gameId)) {
                    star.textContent = '★';
                    star.classList.add('favorited');
                }
            });

            document.querySelectorAll('.league-favorite').forEach(star => {
                const leagueId = star.getAttribute('data-league-id');
                if (favoriteLeagues.includes(leagueId)) {
                    star.textContent = '★';
                    star.classList.add('favorited');
                }
            });

            updateFavoritesCount();
        }

        function toggleSportCategory() {
            const category = this.closest('.sport-category');
            const competitions = category.querySelectorAll('.competition-group');
            const arrow = this.querySelector('.accordion-arrow');
            
            competitions.forEach(comp => {
                if (comp.style.display === 'none') {
                    comp.style.display = 'block';
                    arrow.classList.remove('collapsed');
                } else {
                    comp.style.display = 'none';
                    arrow.classList.add('collapsed');
                }
            });
        }

        function handleGameFavorite(e) {
            e.stopPropagation();
            const gameId = this.getAttribute('data-game-id');
            
            if (favoriteGames.includes(gameId)) {
                favoriteGames = favoriteGames.filter(id => id !== gameId);
                this.textContent = '☆';
                this.classList.remove('favorited');
            } else {
                favoriteGames.push(gameId);
                this.textContent = '★';
                this.classList.add('favorited');
            }
            
            localStorage.setItem('favoriteGames', JSON.stringify(favoriteGames));
            updateFavoritesCount();
            
            if (isViewingFavorites) {
                const totalFavorites = favoriteGames.length + favoriteLeagues.length;
                const newCount = totalFavorites > 0 ? totalFavorites : 1;
                window.history.replaceState(null, '', '/?favorites=' + newCount);
                setTimeout(filterFavoritesView, 100);
            }
        }

        function handleLeagueFavorite(e) {
            e.stopPropagation();
            const leagueId = this.getAttribute('data-league-id');
            
            if (favoriteLeagues.includes(leagueId)) {
                favoriteLeagues = favoriteLeagues.filter(id => id !== leagueId);
                this.textContent = '☆';
                this.classList.remove('favorited');
            } else {
                favoriteLeagues.push(leagueId);
                this.textContent = '★';
                this.classList.add('favorited');
            }
            
            localStorage.setItem('favoriteLeagues', JSON.stringify(favoriteLeagues));
            updateFavoritesCount();
            
            if (isViewingFavorites) {
                const totalFavorites = favoriteGames.length + favoriteLeagues.length;
                const newCount = totalFavorites > 0 ? totalFavorites : 1;
                window.history.replaceState(null, '', '/?favorites=' + newCount);
                setTimeout(filterFavoritesView, 100);
            }
        }

        document.querySelectorAll('.sport-header').forEach(header => {
            header.addEventListener('click', toggleSportCategory);
        });

        document.querySelectorAll('.favorite-star').forEach(star => {
            star.addEventListener('click', handleGameFavorite);
        });

        document.querySelectorAll('.league-favorite').forEach(star => {
            star.addEventListener('click', handleLeagueFavorite);
        });

        if (isViewingFavorites) {
            filterFavoritesView();
        } else {
            loadFavorites();
            restoreScrollPosition();
        }

        updateFavoritesCount();
        setTimeout(updateFavoritesCount, 100);
        setTimeout(updateFavoritesCount, 500);
        
        console.log('Page initialization complete');
    </script>
</body>
</html>