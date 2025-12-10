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
 * - Only includes languages enabled for this specific website
 * 
 * REFACTORED: Now uses shared functions and constants from includes/
 * 
 * URL ROUTING (via .htaccess):
 * - sitemap.xml         → sitemap.php (outputs sitemap INDEX)
 * - sitemap-en.xml      → sitemap.php?lang=en (outputs English sitemap)
 * - sitemap-de.xml      → sitemap.php?lang=de (outputs German sitemap)
 * 
 * Location: /var/www/u1852176/data/www/streaming/sitemap.php
 */

// Prevent any output before XML declaration
ob_start();

// ==========================================
// LOAD CONFIGURATION AND SHARED FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// ==========================================
// GET REQUEST PARAMETERS
// ==========================================
$requestedLang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : null;

// ==========================================
// GET DOMAIN AND LOAD WEBSITE CONFIG
// Uses shared functions: normalizeDomain(), loadWebsiteConfig()
// Uses constants: WEBSITES_CONFIG_FILE
// ==========================================
$domain = normalizeDomain($_SERVER['HTTP_HOST']);

$website = loadWebsiteConfig($domain, WEBSITES_CONFIG_FILE);

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
$defaultLanguage = $website['language'] ?? DEFAULT_LANGUAGE;

// ==========================================
// LOAD LANGUAGES
// Uses shared functions: loadActiveLanguages(), filterEnabledLanguages()
// Uses constants: LANG_DIR
// ==========================================
$allActiveLanguages = loadActiveLanguages(LANG_DIR);

// Filter by website's enabled languages
$enabledLanguages = $website['enabled_languages'] ?? array_keys($allActiveLanguages);
$activeLanguages = filterEnabledLanguages($allActiveLanguages, $enabledLanguages);

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
// Uses constant: DATA_JSON_FILE
// ==========================================
$gamesData = [];

if (file_exists(DATA_JSON_FILE)) {
    $gamesData = json_decode(file_get_contents(DATA_JSON_FILE), true)['games'] ?? [];
}

// ==========================================
// LOAD LASTMOD TRACKING FILE
// Uses constant: SITEMAP_LASTMOD_FILE
// ==========================================
$lastmodData = [];

if (file_exists(SITEMAP_LASTMOD_FILE)) {
    $content = file_get_contents(SITEMAP_LASTMOD_FILE);
    $lastmodData = json_decode($content, true) ?: [];
}

// Initialize domain in lastmodData if not exists
if (!isset($lastmodData[$domain])) {
    $lastmodData[$domain] = [];
}

// ==========================================
// NOTE: The following functions are now in includes/functions.php:
// - sportHasUpcomingGames()
// - hasAnyUpcomingGames()
// - getSmartLastmod()
// - buildSitemapLanguageUrl()
// ==========================================

// ==========================================
// BUILD PAGE DATA WITH LASTMOD
// ==========================================
$pages = [];

// Homepage - uses shared function hasAnyUpcomingGames()
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

// Sport Pages - uses shared function getSmartLastmod()
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
// Uses constant: SITEMAP_LASTMOD_FILE
// ==========================================
$jsonContent = json_encode($lastmodData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(SITEMAP_LASTMOD_FILE, $jsonContent);

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
    // Uses shared function buildSitemapLanguageUrl()
    // ==========================================
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset' . "\n";
    echo '  xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    echo '  xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    
    foreach ($pages as $page) {
        $url = buildSitemapLanguageUrl($baseUrl, $requestedLang, $defaultLanguage, $page['path']);
        
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
        echo '    <lastmod>' . $page['lastmod'] . '</lastmod>' . "\n";
        echo '    <priority>' . $page['priority'] . '</priority>' . "\n";
        
        // Hreflang tags ONLY for enabled languages
        foreach ($activeLanguages as $code => $langInfo) {
            $hrefUrl = buildSitemapLanguageUrl($baseUrl, $code, $defaultLanguage, $page['path']);
            echo '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($code) . '" href="' . htmlspecialchars($hrefUrl) . '"/>' . "\n";
        }
        
        // x-default (points to default language)
        $xDefaultUrl = buildSitemapLanguageUrl($baseUrl, $defaultLanguage, $defaultLanguage, $page['path']);
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefaultUrl) . '"/>' . "\n";
        
        echo '  </url>' . "\n";
    }
    
    echo '</urlset>';
}

exit;