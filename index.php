<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$domain = $_SERVER['HTTP_HOST'];
$domain = str_replace('www.', '', $domain);

$websitesConfigFile = __DIR__ . '/config/websites.json';
if (!file_exists($websitesConfigFile)) {
    die("Websites configuration file not found");
}

$configContent = file_get_contents($websitesConfigFile);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];

$website = null;
foreach ($websites as $site) {
    if ($site['domain'] === $domain && $site['status'] === 'active') {
        $website = $site;
        break;
    }
}

if (!$website) {
    die("Website not found: " . htmlspecialchars($domain));
}

$siteName = $website['site_name'];
$logo = $website['logo'];
$primaryColor = $website['primary_color'];
$secondaryColor = $website['secondary_color'];
$language = $website['language'];
$sidebarContent = $website['sidebar_content'];

$jsonFile = '/var/www/u1852176/data/www/data/data.json';
$gamesData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    $gamesData = $data['games'] ?? [];
}

// Function to send Slack notification about new sports
function sendNewSportsNotification($newSports, $siteName) {
    $slackConfigFile = '/var/www/u1852176/data/www/streaming/config/slack-config.json';
    if (!file_exists($slackConfigFile)) {
        return false;
    }
    
    $slackConfig = json_decode(file_get_contents($slackConfigFile), true);
    $slackWebhookUrl = $slackConfig['webhook_url'] ?? '';
    
    if (empty($slackWebhookUrl)) {
        return false;
    }
    
    $sportsList = implode("\n", array_map(function($sport) {
        return "• " . $sport;
    }, $newSports));
    
    $message = [
        'text' => "🆕 *New Sport Categories Detected*",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Website:* " . $siteName . "\n*New Sports Found in data.json:*\n" . $sportsList . "\n\n⚠️ Please add these sports to CMS and configure SEO."
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Action Required:*\n1. Go to CMS > Manage Sports\n2. Add the new sport categories\n3. Configure SEO for new sport pages"
                ]
            ]
        ]
    ];
    
    $ch = curl_init($slackWebhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// Check for new sports in data.json
function checkForNewSports($gamesData, $configuredSports, $siteName, $websiteId) {
    $dataSports = [];
    foreach ($gamesData as $game) {
        $sport = $game['sport'] ?? '';
        if ($sport && !in_array($sport, $dataSports)) {
            $dataSports[] = $sport;
        }
    }
    
    $newSports = [];
    foreach ($dataSports as $sport) {
        // Exact match only - no grouping logic
        if (!in_array($sport, $configuredSports)) {
            $newSports[] = $sport;
        }
    }
    
    if (!empty($newSports)) {
        $notifiedFile = __DIR__ . '/config/notified-sports.json';
        $notifiedData = [];
        
        if (file_exists($notifiedFile)) {
            $notifiedData = json_decode(file_get_contents($notifiedFile), true) ?: [];
        }
        
        if (!isset($notifiedData[$websiteId])) {
            $notifiedData[$websiteId] = [];
        }
        
        $sportsToNotify = [];
        foreach ($newSports as $sport) {
            if (!in_array($sport, $notifiedData[$websiteId])) {
                $sportsToNotify[] = $sport;
            }
        }
        
        if (!empty($sportsToNotify)) {
            $notificationSent = sendNewSportsNotification($sportsToNotify, $siteName);
            
            if ($notificationSent !== false) {
                $notifiedData[$websiteId] = array_merge($notifiedData[$websiteId], $sportsToNotify);
                file_put_contents($notifiedFile, json_encode($notifiedData, JSON_PRETTY_PRINT));
            }
        }
    }
}

$configuredSports = $website['sports_categories'] ?? [];
checkForNewSports($gamesData, $configuredSports, $siteName, $website['id']);

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

$pagesSeo = $website['pages_seo'] ?? [];
$seoTitle = $website['seo_title'];
$seoDescription = $website['seo_description'];

if ($viewFavorites) {
    if (isset($pagesSeo['favorites'])) {
        $seoTitle = $pagesSeo['favorites']['title'] ?: $seoTitle;
        $seoDescription = $pagesSeo['favorites']['description'] ?: $seoDescription;
    }
} elseif ($activeSport) {
    if (isset($pagesSeo['sports'][$activeSport])) {
        $seoTitle = $pagesSeo['sports'][$activeSport]['title'] ?: $seoTitle;
        $seoDescription = $pagesSeo['sports'][$activeSport]['description'] ?: $seoDescription;
    }
} else {
    if (isset($pagesSeo['home'])) {
        $seoTitle = $pagesSeo['home']['title'] ?: $seoTitle;
        $seoDescription = $pagesSeo['home']['description'] ?: $seoDescription;
    }
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

// Get custom sport icons from website config
$customSportsIcons = $website['sports_icons'] ?? [];

// Function to get sport icon with RELATIVE path
function getSportIcon($sportName, $customIcons) {
    // Check if custom image icon exists
    if (isset($customIcons[$sportName]) && !empty($customIcons[$sportName])) {
        $iconFile = htmlspecialchars($customIcons[$sportName]);
        $iconPath = '/images/sports/' . $iconFile;
        
        return '<img src="' . $iconPath . '" alt="' . htmlspecialchars($sportName) . '" class="sport-icon-img" onerror="this.parentElement.innerHTML=\'⚽\'">';
    }
    
    // If no custom icon uploaded, show default generic sports icon
    return '⚽';
}

// Function to render logo with RELATIVE path
function renderLogo($logo) {
    // Check if logo contains file extension (is a file)
    if (preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $logo)) {
        $logoFile = htmlspecialchars($logo);
        $logoPath = '/images/logos/' . $logoFile;
        return '<img src="' . $logoPath . '" alt="Logo" class="logo-image" style="width: 48px; height: 48px; object-fit: contain;">';
    } else {
        return $logo;
    }
}

$filteredGames = $gamesData;

if ($viewFavorites) {
    $filteredGames = $gamesData;
} else {
    if ($activeSport) {
        // REMOVED GROUPING LOGIC - Now exact match only
        $activeSportName = str_replace('-', ' ', $activeSport);
        $filteredGames = array_filter($filteredGames, function($game) use ($activeSportName) {
            return strtolower($game['sport']) === strtolower($activeSportName);
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

$sportCategoriesFromConfig = $website['sports_categories'] ?? [];

$sportCounts = [];
foreach ($sportCategoriesFromConfig as $sportName) {
    $sportCounts[$sportName] = 0;
}

// Count games for each configured sport - exact match only
foreach ($gamesData as $game) {
    $sport = $game['sport'];
    
    // Exact match only - no grouping
    if (in_array($sport, $sportCategoriesFromConfig)) {
        $sportCounts[$sport]++;
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <link rel="stylesheet" href="/styles.css">
    <script src="/main.js" defer></script>
    <style>
        .logo {
            background-color: <?php echo $primaryColor; ?>;
        }
        
        .home-link.active,
        .menu-item.active {
            background-color: <?php echo $primaryColor; ?>22;
            border-left: 3px solid <?php echo $primaryColor; ?>;
        }
        
        .date-tab.active {
            background-color: <?php echo $secondaryColor; ?>;
        }
        
        /* Logo image styling */
        .logo-image {
            border-radius: 8px;
        }
    </style>
</head>
<body data-viewing-favorites="<?php echo $viewFavorites ? 'true' : 'false'; ?>" 
      data-primary-color="<?php echo $primaryColor; ?>"
      data-active-sport="<?php echo $activeSport ?: ''; ?>"
      data-active-tab="<?php echo $activeTab; ?>">
    
    <button class="burger-menu" id="burgerMenu" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    
    <div class="overlay" id="overlay"></div>
    
    <div class="content-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <a href="/">
                    <div class="logo-title">
                        <span class="logo-icon"><?php echo renderLogo($logo); ?></span>
                        <span class="logo-text"><?php echo htmlspecialchars($siteName); ?></span>
                    </div>
                </a>
            </div>

            <section class="favorites-section">
                <h2 class="sr-only">Favorites</h2>
                <a href="/favorites" class="favorites-link <?php echo $viewFavorites ? 'active' : ''; ?>" id="favoritesLink">
                    <span>⭐</span>
                    <span>Favorites</span>
                    <span class="favorites-count" id="favoritesCount">0</span>
                </a>
            </section>

            <div class="section-title">Sports</div>

            <nav class="sports-menu">
                <a href="/" class="menu-item <?php echo (!$viewFavorites && !$activeSport) ? 'active' : ''; ?>">
                    <span class="menu-item-left">
                        <span class="sport-icon">🏠</span>
                        <span class="sport-name">Home</span>
                    </span>
                </a>
                
                <?php foreach ($sportCounts as $sportName => $count): 
                    $icon = getSportIcon($sportName, $customSportsIcons);
                    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
                    $isActive = ($activeSport === $sportSlug && !$viewFavorites);
                ?>
                    <a href="/live-<?php echo $sportSlug; ?>" class="menu-item <?php echo $isActive ? 'active' : ''; ?>" onclick="saveScrollPosition(event)">
                        <span class="menu-item-left">
                            <span class="sport-icon"><?php echo $icon; ?></span>
                            <span class="sport-name"><?php echo htmlspecialchars($sportName); ?></span>
                        </span>
                        <span class="sport-count"><?php echo $count; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <h1><?php echo $viewFavorites ? 'My Favorites' : ($activeSport ? ucwords(str_replace('-', ' ', $activeSport)) : 'Live Sports Streaming'); ?></h1>
                </div>
                <div class="header-right">
                    <button id="themeToggle" class="theme-toggle" aria-label="Toggle Dark Mode" title="Toggle Dark Mode">
                        <span class="theme-icon">🌙</span>
                    </button>
                </div>
            </header>

            <?php if (!$viewFavorites): ?>
            <nav class="date-tabs-wrapper" aria-label="Time filter">
                <div class="date-tabs">
                    <a href="<?php echo $activeSport ? '/live-'.$activeSport : '/'; ?>" class="date-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="<?php echo $activeSport ? '/live-'.$activeSport.'?tab=soon' : '/?tab=soon'; ?>" class="date-tab <?php echo $activeTab === 'soon' ? 'active' : ''; ?>">Soon</a>
                    <a href="<?php echo $activeSport ? '/live-'.$activeSport.'?tab=tomorrow' : '/?tab=tomorrow'; ?>" class="date-tab <?php echo $activeTab === 'tomorrow' ? 'active' : ''; ?>">Tomorrow</a>
                </div>
            </nav>
            <?php endif; ?>

            <section class="content-section" id="mainContent">
                <h2 class="sr-only">Live Games</h2>
                <?php if ($viewFavorites): ?>
                    <div id="favoritesContainer">
                        <div class="no-games">
                            <p>Loading favorites...</p>
                        </div>
                    </div>
                    <template id="templateData">
                        <?php
                        $allGroupedBySport = groupGamesBySport($gamesData);
                        foreach ($allGroupedBySport as $sportName => $sportGames):
                            $sportIconDisplay = getSportIcon($sportName, $customSportsIcons);
                        ?>
                            <article class="sport-category" data-sport="<?php echo htmlspecialchars($sportName); ?>">
                                <h2 class="sr-only"><?php echo htmlspecialchars($sportName); ?></h2>
                                <details open>
                                    <summary class="sport-header">
                                        <span class="sport-title">
                                            <span><?php echo $sportIconDisplay; ?></span>
                                            <span><?php echo htmlspecialchars($sportName); ?></span>
                                            <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                                        </span>
                                    </summary>
                                    
                                    <?php
                                    $byCountryLeague = groupByCountryAndLeague($sportGames);
                                    foreach ($byCountryLeague as $key => $group):
                                        $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
                                        $countryFlag = getCountryFlag($group['country']);
                                        $countryName = getCountryName($group['country']);
                                    ?>
                                        <section class="competition-group" data-league-id="<?php echo $leagueId; ?>" 
                                             data-country="<?php echo htmlspecialchars($group['country']); ?>"
                                             data-competition="<?php echo htmlspecialchars($group['competition']); ?>">
                                            <h3 class="sr-only"><?php echo htmlspecialchars($countryName . ' - ' . $group['competition']); ?></h3>
                                            <div class="competition-header">
                                                <span class="competition-name">
                                                    <span><?php echo $countryFlag; ?></span>
                                                    <span><?php echo htmlspecialchars($countryName); ?></span>
                                                    <span>•</span>
                                                    <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                                </span>
                                                <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>" role="button" aria-label="Favorite league">☆</span>
                                            </div>
                                            
                                            <?php foreach ($group['games'] as $game): ?>
                                                <details class="game-item-details" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                                    <summary class="game-item-summary">
                                                        <time class="game-time"><?php echo formatGameTime($game['date']); ?></time>
                                                        <span class="game-teams">
                                                            <span class="team">
                                                                <span class="team-icon"></span>
                                                                <?php echo htmlspecialchars($game['match']); ?>
                                                            </span>
                                                        </span>
                                                        <span class="game-actions">
                                                            <span class="link-count-badge" data-game-id="<?php echo $game['id']; ?>">0</span>
                                                            <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>" role="button" aria-label="Favorite game">☆</span>
                                                        </span>
                                                    </summary>
                                                    <div class="game-links-container"></div>
                                                </details>
                                            <?php endforeach; ?>
                                        </section>
                                    <?php endforeach; ?>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </template>
                <?php elseif (empty($groupedBySport)): ?>
                    <div class="no-games">
                        <p>No games available for this time period</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($groupedBySport as $sportName => $sportGames): 
                        $sportIconDisplay = getSportIcon($sportName, $customSportsIcons);
                        $sportId = strtolower(str_replace(' ', '-', $sportName));
                    ?>
                        <article class="sport-category" id="<?php echo $sportId; ?>" data-sport="<?php echo htmlspecialchars($sportName); ?>">
                            <h2 class="sr-only"><?php echo htmlspecialchars($sportName); ?></h2>
                            <details open>
                                <summary class="sport-header">
                                    <span class="sport-title">
                                        <span><?php echo $sportIconDisplay; ?></span>
                                        <span><?php echo htmlspecialchars($sportName); ?></span>
                                        <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                                    </span>
                                </summary>
                                
                                <?php
                                $byCountryLeague = groupByCountryAndLeague($sportGames);
                                foreach ($byCountryLeague as $key => $group):
                                    $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
                                    $countryFlag = getCountryFlag($group['country']);
                                    $countryName = getCountryName($group['country']);
                                ?>
                                    <section class="competition-group" data-league-id="<?php echo $leagueId; ?>" 
                                         data-country="<?php echo htmlspecialchars($group['country']); ?>"
                                         data-competition="<?php echo htmlspecialchars($group['competition']); ?>">
                                        <h3 class="sr-only"><?php echo htmlspecialchars($countryName . ' - ' . $group['competition']); ?></h3>
                                        <div class="competition-header">
                                            <span class="competition-name">
                                                <span><?php echo $countryFlag; ?></span>
                                                <span><?php echo htmlspecialchars($countryName); ?></span>
                                                <span>•</span>
                                                <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                            </span>
                                            <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>" role="button" aria-label="Favorite league">☆</span>
                                        </div>
                                        
                                        <?php foreach ($group['games'] as $game): ?>
                                            <details class="game-item-details" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                                <summary class="game-item-summary">
                                                    <time class="game-time" datetime="<?php echo $game['date']; ?>"><?php echo formatGameTime($game['date']); ?></time>
                                                    <span class="game-teams">
                                                        <span class="team">
                                                            <span class="team-icon"></span>
                                                            <?php echo htmlspecialchars($game['match']); ?>
                                                        </span>
                                                    </span>
                                                    <span class="game-actions">
                                                        <span class="link-count-badge" data-game-id="<?php echo $game['id']; ?>">0</span>
                                                        <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>" role="button" aria-label="Favorite game">☆</span>
                                                    </span>
                                                </summary>
                                                <div class="game-links-container"></div>
                                            </details>
                                        <?php endforeach; ?>
                                    </section>
                                <?php endforeach; ?>
                            </details>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>

        <aside class="right-sidebar">
            <div class="sidebar-content">
                <?php echo $sidebarContent; ?>
            </div>
        </aside>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h2>Sports</h2>
                <ul>
                    <li><a href="/live-football">⚽ Football</a></li>
                    <li><a href="/live-basketball">🏀 Basketball</a></li>
                    <li><a href="/live-tennis">🎾 Tennis</a></li>
                    <li><a href="/live-ice-hockey">🏒 Ice Hockey</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h2>Quick Links</h2>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/favorites">⭐ Favorites</a></li>
                    <li><a href="/?tab=soon">Soon</a></li>
                    <li><a href="/?tab=tomorrow">Tomorrow</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h2>About</h2>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Contact</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h2><?php echo htmlspecialchars($siteName); ?></h2>
                <p>Watch live sports streaming online free. All major sports events in HD quality.</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>