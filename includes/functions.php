<?php
/**
 * Shared Functions for Streaming Websites
 * 
 * This file contains all helper functions used across multiple streaming files:
 * - index.php
 * - api/load-games.php
 * - sitemap.php
 * - robots.php
 * 
 * USAGE: Include config.php first, then this file:
 *        require_once __DIR__ . '/includes/config.php';
 *        require_once __DIR__ . '/includes/functions.php';
 * 
 * NOTE: Some functions use constants from config.php (STREAMING_ROOT, etc.)
 *       If config.php is not loaded, functions will use fallback paths.
 * 
 * Location: /var/www/u1852176/data/www/streaming/includes/functions.php
 * 
 * @version 1.1
 * @date 2024-12
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'functions.php') {
    die('Direct access not allowed');
}

// ==========================================
// WEBSITE CONFIGURATION FUNCTIONS
// Used in: index.php, sitemap.php, robots.php
// ==========================================

/**
 * Normalize domain (remove www. prefix and lowercase)
 * 
 * @param string $domain The domain to normalize
 * @return string Normalized domain
 */
function normalizeDomain($domain) {
    return str_replace('www.', '', strtolower(trim($domain)));
}

/**
 * Load website configuration by domain
 * 
 * @param string $domain The domain to find
 * @param string $configFile Path to websites.json
 * @return array|null Website config array or null if not found
 */
function loadWebsiteConfig($domain, $configFile) {
    if (!file_exists($configFile)) {
        return null;
    }
    
    $configContent = file_get_contents($configFile);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    $normalizedDomain = normalizeDomain($domain);
    
    foreach ($websites as $site) {
        $siteDomain = normalizeDomain($site['domain']);
        
        if ($siteDomain === $normalizedDomain && $site['status'] === 'active') {
            return $site;
        }
    }
    
    return null;
}

/**
 * Load all globally active languages
 * 
 * @param string $langDir Path to language files directory
 * @return array Array of active languages with code, name, flag_code
 */
function loadActiveLanguages($langDir) {
    $allActiveLanguages = [];
    
    if (!is_dir($langDir)) {
        return $allActiveLanguages;
    }
    
    $langFiles = glob($langDir . '*.json');
    foreach ($langFiles as $file) {
        $langCode = basename($file, '.json');
        $langData = json_decode(file_get_contents($file), true);
        
        // Only include globally active languages
        if (isset($langData['language_info']) && ($langData['language_info']['active'] ?? false)) {
            $allActiveLanguages[$langCode] = [
                'code' => $langCode,
                'name' => $langData['language_info']['name'] ?? $langCode,
                'flag_code' => strtoupper($langData['language_info']['flag'] ?? 'GB')
            ];
        }
    }
    
    return $allActiveLanguages;
}

/**
 * Filter languages by website's enabled languages
 * 
 * @param array $allActiveLanguages All globally active languages
 * @param array $enabledLanguages Array of enabled language codes for this website
 * @return array Filtered array of available languages
 */
function filterEnabledLanguages($allActiveLanguages, $enabledLanguages) {
    $availableLanguages = [];
    
    foreach ($allActiveLanguages as $code => $langInfo) {
        if (in_array($code, $enabledLanguages)) {
            $availableLanguages[$code] = $langInfo;
        }
    }
    
    // Ensure at least English is always available
    if (empty($availableLanguages) && isset($allActiveLanguages['en'])) {
        $availableLanguages['en'] = $allActiveLanguages['en'];
    }
    
    return $availableLanguages;
}

// ==========================================
// SITEMAP HELPER FUNCTIONS
// Used in: sitemap.php
// ==========================================

/**
 * Check if a sport has games TODAY or in the FUTURE
 * 
 * @param string $sportName The sport name to check
 * @param array $gamesData Array of game data
 * @return bool True if sport has upcoming games
 */
function sportHasUpcomingGames($sportName, $gamesData) {
    $today = strtotime('today 00:00:00');
    
    foreach ($gamesData as $game) {
        if (strtolower($game['sport']) === strtolower($sportName)) {
            $gameTime = strtotime($game['date']);
            if ($gameTime >= $today) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if ANY sport has games TODAY or in the FUTURE
 * 
 * @param array $gamesData Array of game data
 * @return bool True if any game is upcoming
 */
function hasAnyUpcomingGames($gamesData) {
    $today = strtotime('today 00:00:00');
    
    foreach ($gamesData as $game) {
        $gameTime = strtotime($game['date']);
        if ($gameTime >= $today) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get smart lastmod for a sitemap page
 * Updates lastmod to today if sport has upcoming games, otherwise keeps old value
 * 
 * @param string $pageKey The page key (e.g., 'live-football')
 * @param string $sportName The sport name
 * @param array $gamesData Array of game data
 * @param array &$lastmodData Reference to lastmod tracking data
 * @param string $domain The website domain
 * @return string Date string in Y-m-d format
 */
function getSmartLastmod($pageKey, $sportName, $gamesData, &$lastmodData, $domain) {
    $today = date('Y-m-d');
    
    if (sportHasUpcomingGames($sportName, $gamesData)) {
        $lastmodData[$domain][$pageKey] = $today;
        return $today;
    } else {
        if (isset($lastmodData[$domain][$pageKey])) {
            return $lastmodData[$domain][$pageKey];
        } else {
            $lastmodData[$domain][$pageKey] = $today;
            return $today;
        }
    }
}

/**
 * Build sitemap URL for specific language
 * 
 * @param string $baseUrl Base URL of the website
 * @param string $langCode Language code
 * @param string $defaultLanguage Default language code
 * @param string $path Page path (e.g., 'live-football')
 * @return string Full URL for sitemap
 */
function buildSitemapLanguageUrl($baseUrl, $langCode, $defaultLanguage, $path = '') {
    if ($langCode === $defaultLanguage) {
        return $baseUrl . '/' . ltrim($path, '/');
    } else {
        if (empty($path) || $path === '/') {
            return $baseUrl . '/' . $langCode;
        } else {
            return $baseUrl . '/' . $langCode . '/' . ltrim($path, '/');
        }
    }
}

// ==========================================
// GAME & TIME FUNCTIONS
// Used in: index.php, api/load-games.php
// ==========================================

/**
 * Format game time from date string
 * 
 * @param string $dateString The date string to format
 * @return string Formatted time (H:i format, e.g., "14:30")
 */
function formatGameTime($dateString) {
    $timestamp = strtotime($dateString);
    return date('H:i', $timestamp);
}

/**
 * Get time category for a game
 * Determines if game is "soon" (within 30 min), "tomorrow", or "other"
 * 
 * @param string $dateString The game date/time string
 * @return string Category: 'soon', 'tomorrow', or 'other'
 */
function getTimeCategory($dateString) {
    $gameTime = strtotime($dateString);
    $now = time();
    $diff = $gameTime - $now;
    
    // Within 30 minutes (1800 seconds) and not in the past
    if ($diff <= 1800 && $diff >= 0) {
        return 'soon';
    }
    
    // Tomorrow (from midnight to 23:59:59)
    $tomorrow = strtotime('tomorrow 00:00:00');
    $dayAfter = strtotime('tomorrow 23:59:59');
    
    if ($gameTime >= $tomorrow && $gameTime <= $dayAfter) {
        return 'tomorrow';
    }
    
    return 'other';
}

// ==========================================
// COUNTRY & FLAG FUNCTIONS
// Used in: index.php, api/load-games.php
// ==========================================

/**
 * Get country flag emoji from country file name
 * 
 * @param string $countryFile Country file name (e.g., "united-states.png")
 * @return string Flag emoji or default globe emoji
 */
function getCountryFlag($countryFile) {
    $country = str_replace('.png', '', $countryFile);
    $country = str_replace('-', ' ', $country);
    
    // Map of country names to flag emojis
    $flags = [
        'United states' => '🇺🇸',
        'Russia' => '🇷🇺',
        'Germany' => '🇩🇪',
        'Italy' => '🇮🇹',
        'International' => '🌍',
        'Europe' => '🇪🇺',
        'Worldwide' => '🌐',
        'Colombia' => '🇨🇴',
        'Spain' => '🇪🇸',
        'France' => '🇫🇷',
        'England' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
        'United kingdom' => '🇬🇧',
        'Brazil' => '🇧🇷',
        'Argentina' => '🇦🇷',
        'Mexico' => '🇲🇽',
        'Canada' => '🇨🇦',
        'Australia' => '🇦🇺',
        'Japan' => '🇯🇵',
        'China' => '🇨🇳',
        'South korea' => '🇰🇷',
        'India' => '🇮🇳',
        'Netherlands' => '🇳🇱',
        'Belgium' => '🇧🇪',
        'Portugal' => '🇵🇹',
        'Poland' => '🇵🇱',
        'Turkey' => '🇹🇷',
        'Greece' => '🇬🇷',
        'Sweden' => '🇸🇪',
        'Norway' => '🇳🇴',
        'Denmark' => '🇩🇰',
        'Finland' => '🇫🇮',
        'Switzerland' => '🇨🇭',
        'Austria' => '🇦🇹',
        'Czech republic' => '🇨🇿',
        'Croatia' => '🇭🇷',
        'Serbia' => '🇷🇸',
        'Ukraine' => '🇺🇦',
        'Romania' => '🇷🇴',
        'Hungary' => '🇭🇺',
        'Scotland' => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
        'Wales' => '🏴󠁧󠁢󠁷󠁬󠁳󠁿',
        'Ireland' => '🇮🇪',
        'South africa' => '🇿🇦',
        'Egypt' => '🇪🇬',
        'Saudi arabia' => '🇸🇦',
        'Uae' => '🇦🇪',
        'Qatar' => '🇶🇦',
        'Israel' => '🇮🇱',
        'Chile' => '🇨🇱',
        'Peru' => '🇵🇪',
        'Ecuador' => '🇪🇨',
        'Uruguay' => '🇺🇾',
        'Paraguay' => '🇵🇾',
        'Venezuela' => '🇻🇪',
        'New zealand' => '🇳🇿',
    ];
    
    return $flags[$country] ?? '🌐';
}

/**
 * Get country display name from country file name
 * 
 * @param string $countryFile Country file name (e.g., "united-states.png")
 * @return string Formatted country name (e.g., "United States")
 */
function getCountryName($countryFile) {
    $country = str_replace('.png', '', $countryFile);
    return str_replace('-', ' ', ucwords($country));
}

// ==========================================
// GAME GROUPING FUNCTIONS
// Used in: index.php, api/load-games.php
// ==========================================

/**
 * Group games by sport category
 * 
 * @param array $games Array of game data
 * @return array Games grouped by sport name
 */
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

/**
 * Group games by country and league/competition
 * 
 * @param array $games Array of game data
 * @return array Games grouped by country and competition
 */
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

// ==========================================
// ICON FUNCTIONS
// Used in: index.php, api/load-games.php
// ==========================================

/**
 * Get sport icon from master icons folder
 * Icons are stored in /shared/icons/sports/ and shared across all websites
 * 
 * @param string $sportName The sport name (e.g., "Ice Hockey", "Football")
 * @param string|null $basePath Optional custom base path (defaults to SPORT_ICONS_DIR)
 * @return string HTML img tag or emoji fallback
 */
function getSportIcon($sportName, $basePath = null) {
    // Convert sport name to filename: "Ice Hockey" -> "ice-hockey"
    $filename = strtolower($sportName);
    $filename = str_replace(' ', '-', $filename);
    $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    $filename = trim($filename, '-');
    
    // Use provided base path or determine from constant/default
    if ($basePath === null) {
        if (defined('SPORT_ICONS_DIR')) {
            $basePath = SPORT_ICONS_DIR;
        } elseif (defined('STREAMING_ROOT')) {
            $basePath = STREAMING_ROOT . '/shared/icons/sports/';
        } else {
            // Fallback: assume we're in /includes/ directory
            $basePath = dirname(__DIR__) . '/shared/icons/sports/';
        }
    }
    
    // Get URL path from constant or default
    $urlPath = defined('SPORT_ICONS_URL') ? SPORT_ICONS_URL : '/shared/icons/sports/';
    
    // Get extensions from constant or default
    $extensions = defined('ICON_EXTENSIONS') ? ICON_EXTENSIONS : ['webp', 'svg', 'avif'];
    
    foreach ($extensions as $ext) {
        $fullPath = $basePath . $filename . '.' . $ext;
        if (file_exists($fullPath)) {
            $iconPath = $urlPath . $filename . '.' . $ext;
            return '<img src="' . $iconPath . '" alt="' . htmlspecialchars($sportName) . '" class="sport-icon-img" width="24" height="24" onerror="this.parentElement.innerHTML=\'⚽\'">';
        }
    }
    
    // If no icon found, show default emoji
    return '⚽';
}

/**
 * Get home icon from master icons folder
 * Home icon is stored in /shared/icons/home.webp (not in sports subfolder)
 * 
 * @param string|null $basePath Optional custom base path (defaults to ICONS_DIR)
 * @return string HTML img tag or emoji fallback
 */
function getHomeIcon($basePath = null) {
    // Use provided base path or determine from constant/default
    if ($basePath === null) {
        if (defined('ICONS_DIR')) {
            $basePath = ICONS_DIR;
        } elseif (defined('STREAMING_ROOT')) {
            $basePath = STREAMING_ROOT . '/shared/icons/';
        } else {
            $basePath = dirname(__DIR__) . '/shared/icons/';
        }
    }
    
    // Get URL path from constant or default
    $urlPath = defined('ICONS_URL') ? ICONS_URL : '/shared/icons/';
    
    // Get extensions from constant or default
    $extensions = defined('ICON_EXTENSIONS') ? ICON_EXTENSIONS : ['webp', 'svg', 'avif'];
    
    foreach ($extensions as $ext) {
        $fullPath = $basePath . 'home.' . $ext;
        if (file_exists($fullPath)) {
            $iconPath = $urlPath . 'home.' . $ext;
            return '<img src="' . $iconPath . '" alt="Home" class="sport-icon-img" width="24" height="24" onerror="this.parentElement.innerHTML=\'🏠\'">';
        }
    }
    
    // If no icon found, show default emoji
    return '🏠';
}

/**
 * Render logo with relative path
 * 
 * @param string $logo Logo filename or emoji/text
 * @return string HTML img tag or the original logo text
 */
function renderLogo($logo) {
    // Check if logo contains file extension (is a file)
    if (preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $logo)) {
        $logoFile = htmlspecialchars($logo);
        $logoPath = (defined('LOGOS_URL') ? LOGOS_URL : '/images/logos/') . $logoFile;
        return '<img src="' . $logoPath . '" alt="Logo" class="logo-image" width="48" height="48" style="object-fit: contain;">';
    } else {
        return $logo;
    }
}

// ==========================================
// URL BUILDING FUNCTIONS
// Used in: index.php
// ==========================================

/**
 * Build language URL for language switcher
 * Returns clean URL for language switcher links
 * 
 * @param string $langCode Language code (e.g., 'en', 'es')
 * @param string $defaultLang Default language code
 * @param string|null $activeSport Current sport slug or null
 * @param bool $viewFavorites Whether viewing favorites page
 * @param string $activeTab Current tab ('all', 'soon', 'tomorrow')
 * @return string The constructed URL path
 */
function buildLanguageUrl($langCode, $defaultLang, $activeSport = null, $viewFavorites = false, $activeTab = 'all') {
    // Build the path
    if ($langCode === $defaultLang) {
        // Default language = no prefix, clean URL
        $path = '/';
        if ($viewFavorites) {
            $path = '/favorites';
        } elseif ($activeSport) {
            $path = '/live-' . $activeSport;
        }
    } else {
        // Non-default language = add prefix
        $path = '/' . $langCode;
        if ($viewFavorites) {
            $path .= '/favorites';
        } elseif ($activeSport) {
            $path .= '/live-' . $activeSport;
        }
    }
    
    // Add tab parameter if not 'all'
    if ($activeTab !== 'all' && !$viewFavorites) {
        $path .= '?tab=' . $activeTab;
    }
    
    return $path;
}

/**
 * Build internal link with language prefix
 * 
 * @param string $path The path to link to (e.g., '/live-football')
 * @param string $websiteLanguage Current website language
 * @param string $defaultLanguage Default language code
 * @return string The constructed URL path with language prefix if needed
 */
function langUrl($path, $websiteLanguage, $defaultLanguage) {
    // If current language is NOT the default, add prefix
    if ($websiteLanguage !== $defaultLanguage) {
        // Handle root path
        if ($path === '/') {
            return '/' . $websiteLanguage;
        }
        // Handle other paths
        return '/' . $websiteLanguage . $path;
    }
    // Default language - no prefix
    return $path;
}

// ==========================================
// SEO FUNCTIONS
// Used in: index.php
// ==========================================

/**
 * Get SEO data for specific language
 * Returns title and description for current page/language
 * 
 * @param string $langCode Language code
 * @param string $domain Website domain
 * @param string $pageType Page type ('home', 'sport', 'favorites')
 * @param string|null $sportSlug Sport slug for sport pages
 * @param string $langDir Language files directory path
 * @param array $defaultSeo Default SEO data (English)
 * @return array SEO data with 'title' and 'description' keys
 */
function getLanguageSeoData($langCode, $domain, $pageType, $sportSlug, $langDir, $defaultSeo) {
    // For English (default), return the default SEO from websites.json
    if ($langCode === 'en') {
        return $defaultSeo;
    }

    // Try to load language-specific SEO
    $langFile = $langDir . $langCode . '.json';
    if (!file_exists($langFile)) {
        return $defaultSeo; // Fallback to English
    }
    
    $langData = json_decode(file_get_contents($langFile), true);
    if (!$langData || !isset($langData['seo'])) {
        return $defaultSeo; // Fallback to English
    }
    
    // Normalize domain for comparison (remove www., lowercase)
    $normalizedDomain = strtolower(str_replace('www.', '', trim($domain)));
    
    // Find matching domain key (case-insensitive search)
    $seoData = null;
    foreach ($langData['seo'] as $key => $value) {
        $normalizedKey = strtolower(str_replace('www.', '', trim($key)));
        if ($normalizedKey === $normalizedDomain) {
            $seoData = $value;
            break;
        }
    }
    
    if (!$seoData) {
        return $defaultSeo; // Fallback to English
    }
    
    // Get SEO based on page type
    if ($pageType === 'home') {
        $title = $seoData['home']['title'] ?? '';
        $description = $seoData['home']['description'] ?? '';
    } elseif ($pageType === 'sport' && $sportSlug) {
        // Check case-insensitive for sport slug
        $title = '';
        $description = '';
        if (isset($seoData['sports'])) {
            foreach ($seoData['sports'] as $sKey => $sValue) {
                if (strtolower($sKey) === strtolower($sportSlug)) {
                    $title = $sValue['title'] ?? '';
                    $description = $sValue['description'] ?? '';
                    break;
                }
            }
        }
    } else {
        return $defaultSeo; // Unknown page type
    }
    
    // If language-specific SEO exists, use it; otherwise fallback to English
    return [
        'title' => !empty($title) ? $title : $defaultSeo['title'],
        'description' => !empty($description) ? $description : $defaultSeo['description']
    ];
}

/**
 * Generate hreflang tags for all active languages
 * 
 * @param array $availableLanguages Array of available language data
 * @param string $defaultLanguage Default language code
 * @param string $baseCanonicalUrl Base canonical URL for the website
 * @param string|null $activeSport Current sport slug or null
 * @param bool $viewFavorites Whether viewing favorites page
 * @return string HTML string of hreflang link tags
 */
function generateHreflangTags($availableLanguages, $defaultLanguage, $baseCanonicalUrl, $activeSport, $viewFavorites) {
    $tags = [];
    
    foreach ($availableLanguages as $code => $langInfo) {
        // Build URL for this language
        if ($code === $defaultLanguage) {
            // Default language = no prefix
            $url = $baseCanonicalUrl;
            if ($viewFavorites) {
                $url .= '/favorites';
            } elseif ($activeSport) {
                $url .= '/live-' . $activeSport;
            } else {
                $url .= '/';
            }
        } else {
            // Non-default language = add prefix
            $url = $baseCanonicalUrl . '/' . $code;
            if ($viewFavorites) {
                $url .= '/favorites';
            } elseif ($activeSport) {
                $url .= '/live-' . $activeSport;
            }
        }
        
        $tags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($code) . '" href="' . htmlspecialchars($url) . '">';
    }
    
    // Add x-default (points to default language version)
    $xDefaultUrl = $baseCanonicalUrl;
    if ($viewFavorites) {
        $xDefaultUrl .= '/favorites';
    } elseif ($activeSport) {
        $xDefaultUrl .= '/live-' . $activeSport;
    } else {
        $xDefaultUrl .= '/';
    }
    $tags[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefaultUrl) . '">';
    
    return implode("\n    ", $tags);
}

// ==========================================
// HEAD TAG GENERATION FUNCTIONS
// Used in: index.php
// ==========================================

/**
 * Generate favicon HTML tags
 * 
 * @param string $websiteId Website ID or favicon folder name
 * @param string $faviconDir Base directory for favicons
 * @return string HTML string of favicon link tags
 */
function generateFaviconTags($websiteId, $faviconDir) {
    if (empty($websiteId)) {
        return '';
    }
    
    $faviconPath = '/images/favicons/' . $websiteId . '/';
    $faviconFullPath = $faviconDir . $websiteId . '/';
    
    // Check if favicon folder exists
    if (!file_exists($faviconFullPath . 'favicon-32x32.png')) {
        return '';
    }
    
    $tags = '<!-- FAVICONS -->' . "\n    ";
    $tags .= '<link rel="icon" type="image/png" sizes="32x32" href="' . $faviconPath . 'favicon-32x32.png">' . "\n    ";
    $tags .= '<link rel="icon" type="image/png" sizes="16x16" href="' . $faviconPath . 'favicon-16x16.png">' . "\n    ";
    $tags .= '<link rel="apple-touch-icon" sizes="180x180" href="' . $faviconPath . 'apple-touch-icon.png">' . "\n    ";
    $tags .= '<link rel="icon" type="image/png" sizes="192x192" href="' . $faviconPath . 'android-chrome-192x192.png">' . "\n    ";
    $tags .= '<link rel="icon" type="image/png" sizes="512x512" href="' . $faviconPath . 'android-chrome-512x512.png">';
    
    return $tags;
}

/**
 * Generate Google Analytics tracking code
 * 
 * @param string $analyticsId Google Analytics ID (e.g., "G-XXXXXXXXXX")
 * @return string HTML/JS code for Google Analytics or empty string
 */
function generateGoogleAnalyticsCode($analyticsId) {
    if (empty($analyticsId)) {
        return '';
    }
    
    // Validate format (G-XXXXXXXXXX)
    if (!preg_match('/^G-[A-Z0-9]+$/i', $analyticsId)) {
        return '';
    }
    
    $code = '<!-- Google tag (gtag.js) -->' . "\n    ";
    $code .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($analyticsId) . '"></script>' . "\n    ";
    $code .= '<script>' . "\n    ";
    $code .= '    window.dataLayer = window.dataLayer || [];' . "\n    ";
    $code .= '    function gtag(){dataLayer.push(arguments);}' . "\n    ";
    $code .= '    gtag(\'js\', new Date());' . "\n    ";
    $code .= '    gtag(\'config\', \'' . htmlspecialchars($analyticsId) . '\');' . "\n    ";
    $code .= '</script>';
    
    return $code;
}

// ==========================================
// NOTIFICATION FUNCTIONS
// Used in: index.php
// ==========================================

/**
 * Send Slack notification about new sports
 * 
 * @param array $newSports Array of new sport names
 * @param string $siteName Website name
 * @param string|null $configPath Path to slack config file (optional)
 * @return mixed Result from curl or false on failure
 */
function sendNewSportsNotification($newSports, $siteName, $configPath = null) {
    // Use provided path or default
    if ($configPath === null) {
        if (defined('STREAMING_ROOT')) {
            $configPath = STREAMING_ROOT . '/config/slack-config.json';
        } else {
            $configPath = dirname(__DIR__) . '/config/slack-config.json';
        }
    }
    
    if (!file_exists($configPath)) {
        return false;
    }
    
    $slackConfig = json_decode(file_get_contents($configPath), true);
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

/**
 * Check for new sports in data.json and notify if found
 * 
 * @param array $gamesData Array of game data from data.json
 * @param array $configuredSports Array of configured sport names
 * @param string $siteName Website name
 * @param int|string $websiteId Website ID
 * @param string|null $notifiedFilePath Path to notified sports tracking file
 * @return void
 */
function checkForNewSports($gamesData, $configuredSports, $siteName, $websiteId, $notifiedFilePath = null) {
    // Get all unique sports from games data
    $dataSports = [];
    foreach ($gamesData as $game) {
        $sport = $game['sport'] ?? '';
        if ($sport && !in_array($sport, $dataSports)) {
            $dataSports[] = $sport;
        }
    }
    
    // Find sports not in configured list
    $newSports = [];
    foreach ($dataSports as $sport) {
        // Exact match only - no grouping logic
        if (!in_array($sport, $configuredSports)) {
            $newSports[] = $sport;
        }
    }
    
    if (!empty($newSports)) {
        // Determine notified file path
        if ($notifiedFilePath === null) {
            if (defined('STREAMING_ROOT')) {
                $notifiedFilePath = STREAMING_ROOT . '/config/notified-sports.json';
            } else {
                $notifiedFilePath = dirname(__DIR__) . '/config/notified-sports.json';
            }
        }
        
        $notifiedData = [];
        
        if (file_exists($notifiedFilePath)) {
            $notifiedData = json_decode(file_get_contents($notifiedFilePath), true) ?: [];
        }
        
        if (!isset($notifiedData[$websiteId])) {
            $notifiedData[$websiteId] = [];
        }
        
        // Find sports we haven't notified about yet
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
                file_put_contents($notifiedFilePath, json_encode($notifiedData, JSON_PRETTY_PRINT));
            }
        }
    }
}

// ==========================================
// TRANSLATION FUNCTIONS
// Used in: index.php
// Note: These require $lang global variable to be set
// ==========================================

/**
 * Translate a key from language file
 * Requires global $lang variable to be set
 * 
 * @param string $key Translation key
 * @param string $section Section in language file (default: 'ui')
 * @return string Translated text or formatted key as fallback
 */
function t($key, $section = 'ui') {
    global $lang;
    
    // Handle nested keys like 'footer.sports'
    if (strpos($key, '.') !== false) {
        $parts = explode('.', $key);
        $section = $parts[0];
        $key = $parts[1];
    }
    
    // Return translated text or key as fallback
    if (isset($lang[$section][$key])) {
        return $lang[$section][$key];
    }
    
    // Fallback: return key formatted nicely
    return ucfirst(str_replace('_', ' ', $key));
}

/**
 * Translate sport name
 * Requires global $lang variable to be set
 * 
 * @param string $sportName Sport name in English
 * @return string Translated sport name or original if not found
 */
function tSport($sportName) {
    global $lang;
    
    // Look up in sports translations
    if (isset($lang['sports'][$sportName])) {
        return $lang['sports'][$sportName];
    }
    
    // Fallback: return original name
    return $sportName;
}