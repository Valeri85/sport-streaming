<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==========================================
// DOMAIN DETECTION & WEBSITE LOADING
// ==========================================
$domain = $_SERVER['HTTP_HOST'];
$domain = str_replace('www.', '', strtolower(trim($domain)));

$websitesConfigFile = __DIR__ . '/config/websites.json';
if (!file_exists($websitesConfigFile)) {
    die("Websites configuration file not found");
}

$configContent = file_get_contents($websitesConfigFile);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];

$website = null;
foreach ($websites as $site) {
    $siteDomain = str_replace('www.', '', strtolower(trim($site['domain'])));
    
    if ($siteDomain === $domain && $site['status'] === 'active') {
        $website = $site;
        break;
    }
}

if (!$website) {
    die("Website not found. Looking for: '" . htmlspecialchars($domain) . "'. Available domains: " . 
        implode(', ', array_map(function($s) { 
            return "'" . str_replace('www.', '', strtolower(trim($s['domain']))) . "'";
        }, $websites)));
}

// ==========================================
// LOAD AVAILABLE LANGUAGES
// ==========================================
$langDir = __DIR__ . '/config/lang/';
$availableLanguages = [];

if (is_dir($langDir)) {
    $langFiles = glob($langDir . '*.json');
    foreach ($langFiles as $file) {
        $langCode = basename($file, '.json');
        $langData = json_decode(file_get_contents($file), true);
        
        if (isset($langData['language_info']) && ($langData['language_info']['active'] ?? false)) {
            $availableLanguages[$langCode] = [
                'code' => $langCode,
                'name' => $langData['language_info']['name'] ?? $langCode,
                'flag' => $langData['language_info']['flag'] ?? '🌐'
            ];
        }
    }
}

// ==========================================
// DETERMINE ACTIVE LANGUAGE
// ==========================================
$defaultLanguage = $website['language'] ?? 'en';
$websiteLanguage = $defaultLanguage;

$urlLang = $_GET['url_lang'] ?? null;

if ($urlLang && array_key_exists($urlLang, $availableLanguages)) {
    if ($urlLang === $defaultLanguage) {
        setcookie('user_language', '', time() - 3600, '/');
        
        $redirectPath = '/';
        if (isset($_GET['view']) && $_GET['view'] === 'favorites') {
            $redirectPath = '/favorites';
        } elseif (isset($_GET['sport'])) {
            $redirectPath = '/live-' . $_GET['sport'];
        }
        
        if (isset($_GET['tab'])) {
            $redirectPath .= '?tab=' . $_GET['tab'];
        }
        
        header('Location: ' . $redirectPath, true, 301);
        exit;
    }
    
    $websiteLanguage = $urlLang;
    setcookie('user_language', $websiteLanguage, time() + (30 * 24 * 60 * 60), '/');
}
elseif (!$urlLang) {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    
    $cameFromLanguageUrl = false;
    if (!empty($referer) && strpos($referer, $currentHost) !== false) {
        foreach ($availableLanguages as $code => $langInfo) {
            if ($code !== $defaultLanguage && preg_match('#/' . $code . '(/|$|\?)#', $referer)) {
                $cameFromLanguageUrl = true;
                break;
            }
        }
    }
    
    if ($cameFromLanguageUrl) {
        setcookie('user_language', '', time() - 3600, '/');
        $websiteLanguage = $defaultLanguage;
    }
    elseif (isset($_COOKIE['user_language']) && array_key_exists($_COOKIE['user_language'], $availableLanguages)) {
        $websiteLanguage = $_COOKIE['user_language'];
    }
}

$siteDefaultLanguage = $defaultLanguage;

// ==========================================
// HELPER: Build language URL
// ==========================================
function buildLanguageUrl($langCode, $defaultLang, $activeSport = null, $viewFavorites = false, $activeTab = 'all') {
    if ($langCode === $defaultLang) {
        $path = '/';
        if ($viewFavorites) {
            $path = '/favorites';
        } elseif ($activeSport) {
            $path = '/live-' . $activeSport;
        }
    } else {
        $path = '/' . $langCode;
        if ($viewFavorites) {
            $path .= '/favorites';
        } elseif ($activeSport) {
            $path .= '/live-' . $activeSport;
        }
    }
    
    if ($activeTab !== 'all' && !$viewFavorites) {
        $path .= '?tab=' . $activeTab;
    }
    
    return $path;
}

// ==========================================
// HELPER: Build internal link with language prefix
// ==========================================
function langUrl($path, $websiteLanguage, $defaultLanguage) {
    if ($websiteLanguage !== $defaultLanguage) {
        if ($path === '/') {
            return '/' . $websiteLanguage;
        }
        return '/' . $websiteLanguage . $path;
    }
    return $path;
}

// ==========================================
// LOAD LANGUAGE FILE
// ==========================================
$langFile = __DIR__ . '/config/lang/' . $websiteLanguage . '.json';

if (!file_exists($langFile)) {
    $langFile = __DIR__ . '/config/lang/en.json';
    $websiteLanguage = 'en';
}

$lang = [];
if (file_exists($langFile)) {
    $langContent = file_get_contents($langFile);
    $lang = json_decode($langContent, true) ?? [];
}

// ==========================================
// TRANSLATION HELPER FUNCTIONS
// ==========================================
function t($key, $section = 'ui') {
    global $lang;
    
    if (strpos($key, '.') !== false) {
        $parts = explode('.', $key);
        $section = $parts[0];
        $key = $parts[1];
    }
    
    if (isset($lang[$section][$key])) {
        return $lang[$section][$key];
    }
    
    return ucfirst(str_replace('_', ' ', $key));
}

function tSport($sportName) {
    global $lang;
    
    if (isset($lang['sports'][$sportName])) {
        return $lang['sports'][$sportName];
    }
    
    return $sportName;
}

// ==========================================
// WEBSITE CONFIG VARIABLES
// ==========================================
$siteName = $website['site_name'];
$logo = $website['logo'];
$primaryColor = $website['primary_color'];
$secondaryColor = $website['secondary_color'];
$language = $website['language'];
$sidebarContent = $website['sidebar_content'];

// ==========================================
// LOAD GAMES DATA
// ==========================================
$jsonFile = '/var/www/u1852176/data/www/data/data.json';
$gamesData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    $gamesData = $data['games'] ?? [];
}

// ==========================================
// SLACK NOTIFICATION FOR NEW SPORTS
// ==========================================
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

// ==========================================
// ROUTE DETECTION
// ==========================================
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

$activeSport = $_GET['sport'] ?? null;

if (!$activeSport) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = trim($path, '/');
    
    if ($urlLang && strpos($path, $urlLang) === 0) {
        $path = ltrim(substr($path, strlen($urlLang)), '/');
    }
    
    if (preg_match('/^live-(.+)$/', $path, $matches)) {
        $activeSport = $matches[1];
    }
}

$viewFavorites = false;
if (isset($_GET['view']) && $_GET['view'] === 'favorites') {
    $viewFavorites = true;
} elseif (strpos($_SERVER['REQUEST_URI'], '/favorites') !== false) {
    $viewFavorites = true;
}

// ==========================================
// CANONICAL URL & NOINDEX LOGIC
// ==========================================
$baseCanonicalUrl = $website['canonical_url'] ?? 'https://www.' . $domain;
$baseCanonicalUrl = rtrim($baseCanonicalUrl, '/');

$canonicalUrl = $baseCanonicalUrl;
$shouldNoindex = false;

$langPrefix = ($websiteLanguage !== $siteDefaultLanguage) ? '/' . $websiteLanguage : '';

if ($viewFavorites) {
    $canonicalUrl = $baseCanonicalUrl . $langPrefix . '/favorites';
    $shouldNoindex = true;
    
} elseif ($activeSport) {
    $canonicalUrl = $baseCanonicalUrl . $langPrefix . '/live-' . $activeSport;
    
    if ($activeTab !== 'all') {
        $shouldNoindex = true;
    }
    
} else {
    if ($langPrefix) {
        $canonicalUrl = $baseCanonicalUrl . $langPrefix;
    } else {
        $canonicalUrl = $baseCanonicalUrl . '/';
    }
    
    if ($activeTab !== 'all') {
        $shouldNoindex = true;
    }
}

// ==========================================
// SEO TITLE & DESCRIPTION
// ==========================================
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

// ==========================================
// HELPER FUNCTIONS
// ==========================================
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

// ==========================================
// MASTER SPORT ICON FUNCTION
// Icons are stored in /shared/icons/sports/ and shared across all websites
// ==========================================
function getSportIcon($sportName) {
    // Convert sport name to filename: "Ice Hockey" -> "ice-hockey"
    $filename = strtolower($sportName);
    $filename = str_replace(' ', '-', $filename);
    $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    $filename = trim($filename, '-');
    
    // Check for icon in master folder with different extensions
    $extensions = ['webp', 'svg', 'avif'];
    $basePath = __DIR__ . '/shared/icons/sports/';
    
    foreach ($extensions as $ext) {
        $fullPath = $basePath . $filename . '.' . $ext;
        if (file_exists($fullPath)) {
            $iconPath = '/shared/icons/sports/' . $filename . '.' . $ext;
            return '<img src="' . $iconPath . '" alt="' . htmlspecialchars($sportName) . '" class="sport-icon-img" onerror="this.parentElement.innerHTML=\'⚽\'">';
        }
    }
    
    // If no icon found, show default emoji
    return '⚽';
}

// Function to render logo with RELATIVE path
function renderLogo($logo) {
    if (preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $logo)) {
        $logoFile = htmlspecialchars($logo);
        $logoPath = '/images/logos/' . $logoFile;
        return '<img src="' . $logoPath . '" alt="Logo" class="logo-image" style="width: 48px; height: 48px; object-fit: contain;">';
    } else {
        return $logo;
    }
}

// ==========================================
// FILTER GAMES
// ==========================================
$filteredGames = $gamesData;

if ($viewFavorites) {
    $filteredGames = $gamesData;
} else {
    if ($activeSport) {
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

foreach ($gamesData as $game) {
    $sport = $game['sport'];
    
    if (in_array($sport, $sportCategoriesFromConfig)) {
        $sportCounts[$sport]++;
    }
}

// ==========================================
// PREPARE TRANSLATIONS FOR JAVASCRIPT
// ==========================================
$jsTranslations = [
    'messages' => $lang['messages'] ?? [],
    'ui' => $lang['ui'] ?? [],
    'accessibility' => $lang['accessibility'] ?? []
];

// ==========================================
// PRE-BUILD URLS FOR TEMPLATES
// ==========================================
$homeUrl = langUrl('/', $websiteLanguage, $siteDefaultLanguage);
$favoritesUrl = langUrl('/favorites', $websiteLanguage, $siteDefaultLanguage);

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($websiteLanguage); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    
    <!-- CANONICAL TAG & NOINDEX -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <?php if ($shouldNoindex): ?>
    <meta name="robots" content="noindex, follow">
    <?php endif; ?>
    
    <link rel="stylesheet" href="/styles.css">
    <script>
        window.TRANSLATIONS = <?php echo json_encode($jsTranslations, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/main.js" defer></script>
    <style>
        .date-tab.active {
            background-color: <?php echo $secondaryColor; ?>;
        }
        .logo-image {
            border-radius: 8px;
        }
    </style>
</head>
<body data-viewing-favorites="<?php echo $viewFavorites ? 'true' : 'false'; ?>" 
      data-primary-color="<?php echo $primaryColor; ?>"
      data-active-sport="<?php echo $activeSport ?: ''; ?>"
      data-active-tab="<?php echo $activeTab; ?>">
    
    <!-- HEADER -->
    <header class="header">
        <div class="logo">
            <a href="<?php echo $homeUrl; ?>">
                <div class="logo-title">
                    <span class="logo-icon"><?php echo renderLogo($logo); ?></span>
                    <span class="logo-text"><?php echo htmlspecialchars($siteName); ?></span>
                </div>
            </a>
        </div>
        
        <div class="header-page-title">
            <h1><?php 
                if ($viewFavorites) {
                    echo htmlspecialchars(t('my_favorites'));
                } elseif ($activeSport) {
                    $activeSportName = ucwords(str_replace('-', ' ', $activeSport));
                    echo htmlspecialchars(tSport($activeSportName));
                } else {
                    echo htmlspecialchars(t('live_sports_streaming'));
                }
            ?></h1>
        </div>
        
        <div class="header-right">
            <button id="themeToggle" class="theme-toggle" aria-label="<?php echo htmlspecialchars(t('toggle_dark_mode', 'accessibility')); ?>" title="<?php echo htmlspecialchars(t('toggle_dark_mode', 'accessibility')); ?>">
                <span class="theme-icon">🌙</span>
            </button>
            
            <?php if (count($availableLanguages) > 1): ?>
            <div class="language-switcher" id="languageSwitcher">
                <button class="language-toggle" id="languageToggle" aria-label="<?php echo htmlspecialchars(t('change_language', 'accessibility')); ?>" aria-expanded="false">
                    <span class="current-flag"><?php echo $availableLanguages[$websiteLanguage]['flag'] ?? '🌐'; ?></span>
                    <span class="language-arrow">▼</span>
                </button>
                <div class="language-dropdown" id="languageDropdown">
                    <?php foreach ($availableLanguages as $code => $langInfo): 
                        $isActive = ($code === $websiteLanguage);
                        $langSwitchUrl = buildLanguageUrl($code, $siteDefaultLanguage, $activeSport, $viewFavorites, $activeTab);
                        $onclickAttr = ($code === $siteDefaultLanguage) ? 'onclick="document.cookie=\'user_language=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;\';"' : '';
                    ?>
                        <a href="<?php echo htmlspecialchars($langSwitchUrl); ?>" 
                           class="language-option <?php echo $isActive ? 'active' : ''; ?>"
                           data-lang="<?php echo htmlspecialchars($code); ?>"
                           <?php echo $onclickAttr; ?>>
                            <span class="lang-flag"><?php echo $langInfo['flag']; ?></span>
                            <span class="lang-name"><?php echo htmlspecialchars($langInfo['name']); ?></span>
                            <?php if ($isActive): ?><span class="lang-check">✓</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <button class="burger-menu" 
                    id="burgerMenu" 
                    popovertarget="sidebar"
                    aria-label="<?php echo htmlspecialchars(t('toggle_menu', 'accessibility')); ?>"
                    aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
    
    <!-- LEFT SIDEBAR -->
    <aside class="sidebar" id="sidebar" popover>
        <section class="favorites-section">
            <h2 class="sr-only"><?php echo htmlspecialchars(t('favorites')); ?></h2>
            <a href="<?php echo $favoritesUrl; ?>" class="favorites-link <?php echo $viewFavorites ? 'active' : ''; ?>" id="favoritesLink">
                <span>⭐</span>
                <span><?php echo htmlspecialchars(t('favorites')); ?></span>
                <span class="favorites-count" id="favoritesCount">0</span>
            </a>
        </section>

        <div class="section-title"><?php echo htmlspecialchars(t('sports')); ?></div>

        <nav class="sports-menu">
            <a href="<?php echo $homeUrl; ?>" class="menu-item <?php echo (!$viewFavorites && !$activeSport) ? 'active' : ''; ?>">
                <span class="menu-item-left">
                    <span class="sport-icon">🏠</span>
                    <span class="sport-name"><?php echo htmlspecialchars(t('home')); ?></span>
                </span>
            </a>
            
            <?php foreach ($sportCounts as $sportName => $count): 
                $icon = getSportIcon($sportName);
                $sportSlug = strtolower(str_replace(' ', '-', $sportName));
                $isActive = ($activeSport === $sportSlug && !$viewFavorites);
                $translatedSportName = tSport($sportName);
                $sportUrl = langUrl('/live-' . $sportSlug, $websiteLanguage, $siteDefaultLanguage);
            ?>
                <a href="<?php echo $sportUrl; ?>" class="menu-item <?php echo $isActive ? 'active' : ''; ?>">
                    <span class="menu-item-left">
                        <span class="sport-icon"><?php echo $icon; ?></span>
                        <span class="sport-name"><?php echo htmlspecialchars($translatedSportName); ?></span>
                    </span>
                    <?php if ($count > 0): ?>
                        <span class="sport-count"><?php echo $count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <?php if (!$viewFavorites): ?>
        <nav class="date-tabs-wrapper" aria-label="<?php echo htmlspecialchars(t('filter_by_time', 'accessibility')); ?>">
            <div class="date-tabs">
                <?php 
                    $tabBaseUrl = $activeSport ? langUrl('/live-' . $activeSport, $websiteLanguage, $siteDefaultLanguage) : $homeUrl;
                ?>
                <a href="<?php echo $tabBaseUrl; ?>" class="date-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>"><?php echo htmlspecialchars(t('all')); ?></a>
                <a href="<?php echo $tabBaseUrl . '?tab=soon'; ?>" class="date-tab <?php echo $activeTab === 'soon' ? 'active' : ''; ?>"><?php echo htmlspecialchars(t('soon')); ?></a>
                <a href="<?php echo $tabBaseUrl . '?tab=tomorrow'; ?>" class="date-tab <?php echo $activeTab === 'tomorrow' ? 'active' : ''; ?>"><?php echo htmlspecialchars(t('tomorrow')); ?></a>
            </div>
        </nav>
        <?php endif; ?>

        <section class="content-section" id="mainContent">
            <h2 class="sr-only"><?php echo htmlspecialchars(t('live_games', 'accessibility')); ?></h2>
            <?php if ($viewFavorites): ?>
                <div id="favoritesContainer">
                    <div class="no-games">
                        <p><?php echo htmlspecialchars(t('loading_favorites', 'messages')); ?></p>
                    </div>
                </div>
                <template id="templateData">
                    <?php
                    $allGroupedBySport = groupGamesBySport($gamesData);
                    foreach ($allGroupedBySport as $sportName => $sportGames):
                        $sportIconDisplay = getSportIcon($sportName);
                        $translatedSportName = tSport($sportName);
                    ?>
                        <article class="sport-category" data-sport="<?php echo htmlspecialchars($sportName); ?>">
                            <h2 class="sr-only"><?php echo htmlspecialchars($translatedSportName); ?></h2>
                            <details open>
                                <summary class="sport-header">
                                    <span class="sport-title">
                                        <span><?php echo $sportIconDisplay; ?></span>
                                        <span><?php echo htmlspecialchars($translatedSportName); ?></span>
                                        <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                                    </span>
                                </summary>
                                
                                <?php
                                $byCountryLeague = groupByCountryAndLeague($sportGames);
                                foreach ($byCountryLeague as $key => $group):
                                    $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
                                ?>
                                    <section class="country-section">
                                        <details open>
                                            <summary class="country-header">
                                                <span class="country-info">
                                                    <span class="country-flag"><?php echo getCountryFlag($group['country']); ?></span>
                                                    <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                                </span>
                                                <span class="games-count"><?php echo count($group['games']); ?></span>
                                            </summary>
                                            
                                            <ul class="games-list" role="list">
                                                <?php foreach ($group['games'] as $game): 
                                                    $gameId = $game['id'];
                                                    $timeCategory = getTimeCategory($game['date']);
                                                ?>
                                                    <li class="game-card" 
                                                        data-game-id="<?php echo htmlspecialchars($gameId); ?>"
                                                        data-time-category="<?php echo $timeCategory; ?>">
                                                        <div class="game-time"><?php echo formatGameTime($game['date']); ?></div>
                                                        <div class="game-teams">
                                                            <span class="team"><?php echo htmlspecialchars($game['home']); ?></span>
                                                            <span class="vs">vs</span>
                                                            <span class="team"><?php echo htmlspecialchars($game['away']); ?></span>
                                                        </div>
                                                        <button class="favorite-btn" 
                                                                data-game-id="<?php echo htmlspecialchars($gameId); ?>"
                                                                aria-label="<?php echo htmlspecialchars(t('add_to_favorites', 'accessibility')); ?>">
                                                            ☆
                                                        </button>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    </section>
                                <?php endforeach; ?>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </template>
            <?php else: ?>
                <?php if (empty($groupedBySport)): ?>
                    <div class="no-games">
                        <p><?php echo htmlspecialchars(t('no_games_found', 'messages')); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($groupedBySport as $sportName => $sportGames):
                        $sportIconDisplay = getSportIcon($sportName);
                        $translatedSportName = tSport($sportName);
                    ?>
                        <article class="sport-category">
                            <h2 class="sr-only"><?php echo htmlspecialchars($translatedSportName); ?></h2>
                            <details open>
                                <summary class="sport-header">
                                    <span class="sport-title">
                                        <span><?php echo $sportIconDisplay; ?></span>
                                        <span><?php echo htmlspecialchars($translatedSportName); ?></span>
                                        <span class="sport-count-badge"><?php echo count($sportGames); ?></span>
                                    </span>
                                </summary>
                                
                                <?php
                                $byCountryLeague = groupByCountryAndLeague($sportGames);
                                foreach ($byCountryLeague as $key => $group):
                                    $leagueId = 'league-' . md5($sportName . $group['country'] . $group['competition']);
                                ?>
                                    <section class="country-section">
                                        <details open>
                                            <summary class="country-header">
                                                <span class="country-info">
                                                    <span class="country-flag"><?php echo getCountryFlag($group['country']); ?></span>
                                                    <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                                </span>
                                                <span class="games-count"><?php echo count($group['games']); ?></span>
                                            </summary>
                                            
                                            <ul class="games-list" role="list">
                                                <?php foreach ($group['games'] as $game): 
                                                    $gameId = $game['id'];
                                                    $timeCategory = getTimeCategory($game['date']);
                                                ?>
                                                    <li class="game-card" 
                                                        data-game-id="<?php echo htmlspecialchars($gameId); ?>"
                                                        data-time-category="<?php echo $timeCategory; ?>">
                                                        <div class="game-time"><?php echo formatGameTime($game['date']); ?></div>
                                                        <div class="game-teams">
                                                            <span class="team"><?php echo htmlspecialchars($game['home']); ?></span>
                                                            <span class="vs">vs</span>
                                                            <span class="team"><?php echo htmlspecialchars($game['away']); ?></span>
                                                        </div>
                                                        <button class="favorite-btn" 
                                                                data-game-id="<?php echo htmlspecialchars($gameId); ?>"
                                                                aria-label="<?php echo htmlspecialchars(t('add_to_favorites', 'accessibility')); ?>">
                                                            ☆
                                                        </button>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    </section>
                                <?php endforeach; ?>
                            </details>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- RIGHT SIDEBAR -->
    <aside class="right-sidebar">
        <div class="sidebar-widget">
            <?php echo $sidebarContent; ?>
        </div>
    </aside>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h2><?php echo htmlspecialchars(t('sports', 'footer')); ?></h2>
                <ul>
                    <li><a href="<?php echo langUrl('/live-football', $websiteLanguage, $siteDefaultLanguage); ?>">⚽ <?php echo htmlspecialchars(tSport('Football')); ?></a></li>
                    <li><a href="<?php echo langUrl('/live-basketball', $websiteLanguage, $siteDefaultLanguage); ?>">🏀 <?php echo htmlspecialchars(tSport('Basketball')); ?></a></li>
                    <li><a href="<?php echo langUrl('/live-tennis', $websiteLanguage, $siteDefaultLanguage); ?>">🎾 <?php echo htmlspecialchars(tSport('Tennis')); ?></a></li>
                    <li><a href="<?php echo langUrl('/live-ice-hockey', $websiteLanguage, $siteDefaultLanguage); ?>">🏒 <?php echo htmlspecialchars(tSport('Ice Hockey')); ?></a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h2><?php echo htmlspecialchars(t('quick_links', 'footer')); ?></h2>
                <ul>
                    <li><a href="<?php echo $homeUrl; ?>"><?php echo htmlspecialchars(t('home')); ?></a></li>
                    <li><a href="<?php echo $favoritesUrl; ?>">⭐ <?php echo htmlspecialchars(t('favorites')); ?></a></li>
                    <li><a href="<?php echo $homeUrl . '?tab=soon'; ?>"><?php echo htmlspecialchars(t('soon')); ?></a></li>
                    <li><a href="<?php echo $homeUrl . '?tab=tomorrow'; ?>"><?php echo htmlspecialchars(t('tomorrow')); ?></a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h2><?php echo htmlspecialchars(t('about', 'footer')); ?></h2>
                <ul>
                    <li><a href="#"><?php echo htmlspecialchars(t('about_us', 'footer')); ?></a></li>
                    <li><a href="#"><?php echo htmlspecialchars(t('contact', 'footer')); ?></a></li>
                    <li><a href="#"><?php echo htmlspecialchars(t('privacy_policy', 'footer')); ?></a></li>
                    <li><a href="#"><?php echo htmlspecialchars(t('terms_of_service', 'footer')); ?></a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h2><?php echo htmlspecialchars($siteName); ?></h2>
                <p><?php echo htmlspecialchars(t('footer_description', 'footer')); ?></p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. <?php echo htmlspecialchars(t('copyright', 'footer')); ?></p>
        </div>
    </footer>

</body>
</html>