<?php
/**
 * Dynamic Multilingual Sitemap Generator for Multi-Domain Streaming Websites
 * 
 * FEATURES:
 * - Sitemap Index: Lists all language-specific sitemaps
 * - Language Sitemaps: Contains URLs for specific language with hreflang tags
 * - Smart lastmod: Updates based on game availability
 * - Bidirectional hreflang: All language versions link to each other
 * - x-default: Points to English (default language)
 * - NEW: Only includes languages enabled for this specific website
 * 
 * URL ROUTING (via .htaccess):
 * - sitemap.xml         → sitemap.php (outputs sitemap INDEX)
 * - sitemap-en.xml      → sitemap.php?lang=en (outputs English sitemap)
 * - sitemap-de.xml      → sitemap.php?lang=de (outputs German sitemap)
 * 
 * LASTMOD LOGIC:
 * - ALL sport categories ALWAYS appear in sitemap (never removed)
 * - If sport has games TODAY or FUTURE → update lastmod to today
 * - If sport has NO games today → keep old lastmod from last game date
 * - First time sport appears → set lastmod to today
 * - NO /favorites URL (user-specific, has noindex)
 * - Tracks lastmod per page in config/sitemap-lastmod.json
 * 
 * Location: /var/www/u1852176/data/www/streaming/sitemap.php
 */

// Prevent any output before XML declaration
ob_start();

// ==========================================
// GET REQUEST PARAMETERS
// ==========================================
$requestedLang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : null;

// ==========================================
// GET DOMAIN AND LOAD WEBSITE CONFIG
// ==========================================
$domain = $_SERVER['HTTP_HOST'];
$domain = str_replace('www.', '', strtolower(trim($domain)));

$configFile = __DIR__ . '/config/websites.json';

if (!file_exists($configFile)) {
    header('HTTP/1.1 404 Not Found');
    die('Configuration file not found');
}

$configContent = file_get_contents($configFile);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];

// Find the website configuration for current domain
$website = null;
foreach ($websites as $site) {
    $siteDomain = str_replace('www.', '', strtolower(trim($site['domain'])));
    
    if ($siteDomain === $domain && $site['status'] === 'active') {
        $website = $site;
        break;
    }
}

// If domain not found or inactive, return 404
if (!$website) {
    header('HTTP/1.1 404 Not Found');
    die('Website not found or inactive');
}

// Get base URL (use canonical_url if available, otherwise construct it)
$baseUrl = $website['canonical_url'] ?? 'https://www.' . $domain;
$baseUrl = rtrim($baseUrl, '/');

// Get sports categories for this website
$sportsCategories = $website['sports_categories'] ?? [];

// Get default language for this website (usually 'en')
$defaultLanguage = $website['language'] ?? 'en';

// ==========================================
// LOAD ALL GLOBALLY ACTIVE LANGUAGES
// ==========================================
$langDir = __DIR__ . '/config/lang/';
$allActiveLanguages = [];

if (is_dir($langDir)) {
    $files = glob($langDir . '*.json');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if ($data && isset($data['language_info']) && ($data['language_info']['active'] ?? false)) {
            $code = $data['language_info']['code'];
            $allActiveLanguages[$code] = [
                'code' => $code,
                'name' => $data['language_info']['name'] ?? $code
            ];
        }
    }
}

// ==========================================
// FILTER BY WEBSITE'S ENABLED LANGUAGES
// ==========================================
$enabledLanguages = $website['enabled_languages'] ?? array_keys($allActiveLanguages);
$activeLanguages = [];

foreach ($allActiveLanguages as $code => $langInfo) {
    if (in_array($code, $enabledLanguages)) {
        $activeLanguages[$code] = $langInfo;
    }
}

// Sort: Default language first, then alphabetically
uksort($activeLanguages, function($a, $b) use ($activeLanguages, $defaultLanguage) {
    if ($a === $defaultLanguage) return -1;
    if ($b === $defaultLanguage) return 1;
    return strcmp($activeLanguages[$a]['name'], $activeLanguages[$b]['name']);
});

// If no languages found, fallback to English only
if (empty($activeLanguages)) {
    $activeLanguages = ['en' => ['code' => 'en', 'name' => 'English']];
}

// ==========================================
// VALIDATE REQUESTED LANGUAGE
// Return 404 if language is not enabled for this website
// ==========================================
if ($requestedLang !== null) {
    // Check if language is enabled for this website
    if (!isset($activeLanguages[$requestedLang])) {
        header('HTTP/1.1 404 Not Found');
        die('Language sitemap not found - language not enabled for this website');
    }
}

// ==========================================
// LOAD GAMES DATA
// ==========================================
$dataJsonFile = '/var/www/u1852176/data/www/data/data.json';
$gamesData = [];

if (file_exists($dataJsonFile)) {
    $gamesData = json_decode(file_get_contents($dataJsonFile), true)['games'] ?? [];
}

// ==========================================
// LOAD LASTMOD TRACKING FILE
// ==========================================
$lastmodFile = __DIR__ . '/config/sitemap-lastmod.json';
$lastmodData = [];

if (file_exists($lastmodFile)) {
    $content = file_get_contents($lastmodFile);
    $lastmodData = json_decode($content, true) ?: [];
}

// Initialize domain in lastmodData if not exists
if (!isset($lastmodData[$domain])) {
    $lastmodData[$domain] = [];
}

// ==========================================
// FUNCTION: Check if sport has games TODAY or FUTURE
// ==========================================
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

// ==========================================
// FUNCTION: Check if ANY sport has games TODAY or FUTURE
// ==========================================
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

// ==========================================
// FUNCTION: Get smart lastmod for a page
// ==========================================
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

// ==========================================
// FUNCTION: Build URL for specific language
// ==========================================
function buildLanguageUrl($baseUrl, $langCode, $defaultLanguage, $path = '') {
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
// BUILD PAGE DATA WITH LASTMOD
// ==========================================
$pages = [];

// Homepage
if (hasAnyUpcomingGames($gamesData)) {
    $homeLastmod = date('Y-m-d');
    $lastmodData[$domain]['home'] = $homeLastmod;
} else {
    if (isset($lastmodData[$domain]['home'])) {
        $homeLastmod = $lastmodData[$domain]['home'];
    } else {
        $homeLastmod = date('Y-m-d');
        $lastmodData[$domain]['home'] = $homeLastmod;
    }
}

$pages[] = [
    'path' => '',
    'lastmod' => $homeLastmod,
    'priority' => '1.0'
];

// Sport Pages
foreach ($sportsCategories as $sportName) {
    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
    $pageKey = 'live-' . $sportSlug;
    
    $sportLastmod = getSmartLastmod($pageKey, $sportName, $gamesData, $lastmodData, $domain);
    
    $pages[] = [
        'path' => $pageKey,
        'lastmod' => $sportLastmod,
        'priority' => '0.9'
    ];
}

// ==========================================
// SAVE UPDATED LASTMOD DATA BACK TO JSON
// ==========================================
$jsonContent = json_encode($lastmodData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($lastmodFile, $jsonContent);

// ==========================================
// CLEAR OUTPUT BUFFER AND SET HEADERS
// ==========================================
ob_end_clean();
header('Content-Type: application/xml; charset=utf-8');

// ==========================================
// OUTPUT XML
// ==========================================

if ($requestedLang === null) {
    // ==========================================
    // SITEMAP INDEX
    // Only lists sitemaps for ENABLED languages
    // ==========================================
    
    $mostRecentLastmod = $homeLastmod;
    foreach ($pages as $page) {
        if ($page['lastmod'] > $mostRecentLastmod) {
            $mostRecentLastmod = $page['lastmod'];
        }
    }
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    foreach ($activeLanguages as $langCode => $langInfo) {
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . htmlspecialchars($baseUrl . '/sitemap-' . $langCode . '.xml') . '</loc>' . "\n";
        echo '    <lastmod>' . $mostRecentLastmod . '</lastmod>' . "\n";
        echo '  </sitemap>' . "\n";
    }
    
    echo '</sitemapindex>';
    
} else {
    // ==========================================
    // LANGUAGE-SPECIFIC SITEMAP
    // Only includes hreflang for ENABLED languages
    // ==========================================
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset' . "\n";
    echo '  xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    echo '  xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    
    foreach ($pages as $page) {
        $url = buildLanguageUrl($baseUrl, $requestedLang, $defaultLanguage, $page['path']);
        
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
        echo '    <lastmod>' . $page['lastmod'] . '</lastmod>' . "\n";
        echo '    <priority>' . $page['priority'] . '</priority>' . "\n";
        
        // Hreflang tags ONLY for enabled languages
        foreach ($activeLanguages as $code => $langInfo) {
            $hrefUrl = buildLanguageUrl($baseUrl, $code, $defaultLanguage, $page['path']);
            echo '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($code) . '" href="' . htmlspecialchars($hrefUrl) . '"/>' . "\n";
        }
        
        // x-default (points to default language)
        $xDefaultUrl = buildLanguageUrl($baseUrl, $defaultLanguage, $defaultLanguage, $page['path']);
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefaultUrl) . '"/>' . "\n";
        
        echo '  </url>' . "\n";
    }
    
    echo '</urlset>';
}

exit;