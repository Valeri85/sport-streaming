<?php
/**
 * Dynamic Sitemap Generator for Multi-Domain Streaming Websites
 * 
 * CORRECT LOGIC:
 * - <lastmod> updates ONLY when sport has games TODAY or FUTURE
 * - If no games today → KEEP old lastmod (don't change)
 * - Tracks lastmod per page in config/sitemap-lastmod.json
 * 
 * Location: /var/www/u1852176/data/www/streaming/sitemap.php
 */

// Prevent any output before XML declaration
ob_start();

// Get current domain and normalize it
$domain = $_SERVER['HTTP_HOST'];
$domain = str_replace('www.', '', strtolower(trim($domain)));

// Load websites configuration
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
    // Start of today (00:00:00)
    $today = strtotime('today 00:00:00');
    
    foreach ($gamesData as $game) {
        // Exact match only - no grouping
        if (strtolower($game['sport']) === strtolower($sportName)) {
            $gameTime = strtotime($game['date']);
            
            // Game is today or in the future
            if ($gameTime >= $today) {
                return true;
            }
        }
    }
    
    return false;
}

// ==========================================
// FUNCTION: Check if ANY sport has games TODAY or FUTURE
// (For homepage - shows all sports)
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
    
    // Check if this sport has upcoming games
    if (sportHasUpcomingGames($sportName, $gamesData)) {
        // Has games today/future → update lastmod to today
        $lastmodData[$domain][$pageKey] = $today;
        return $today;
    } else {
        // No games today → keep old lastmod from JSON
        // If no old lastmod exists, use a default old date
        $oldLastmod = $lastmodData[$domain][$pageKey] ?? '2025-01-01';
        return $oldLastmod;
    }
}

// ==========================================
// BUILD URLS ARRAY
// ==========================================
$urls = [];

// ==========================================
// HOMEPAGE
// ==========================================
// Homepage shows all sports, so check if ANY sport has games today/future
if (hasAnyUpcomingGames($gamesData)) {
    $homeLastmod = date('Y-m-d');
    $lastmodData[$domain]['home'] = $homeLastmod;
} else {
    $homeLastmod = $lastmodData[$domain]['home'] ?? '2025-01-01';
}

$urls[] = [
    'loc' => $baseUrl . '/',
    'lastmod' => $homeLastmod,
    'priority' => '1.0'
];

// ==========================================
// FAVORITES PAGE
// ==========================================
// Favorites depends on games data, so same logic as homepage
if (hasAnyUpcomingGames($gamesData)) {
    $favoritesLastmod = date('Y-m-d');
    $lastmodData[$domain]['favorites'] = $favoritesLastmod;
} else {
    $favoritesLastmod = $lastmodData[$domain]['favorites'] ?? '2025-01-01';
}

$urls[] = [
    'loc' => $baseUrl . '/favorites',
    'lastmod' => $favoritesLastmod,
    'priority' => '0.9'
];

// ==========================================
// SPORT PAGES
// ==========================================
foreach ($sportsCategories as $sportName) {
    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
    $pageKey = 'live-' . $sportSlug;
    
    // Get smart lastmod for this sport
    $sportLastmod = getSmartLastmod($pageKey, $sportName, $gamesData, $lastmodData, $domain);
    
    $urls[] = [
        'loc' => $baseUrl . '/' . $pageKey,
        'lastmod' => $sportLastmod,
        'priority' => '0.9'
    ];
}

// ==========================================
// SAVE UPDATED LASTMOD DATA BACK TO JSON
// ==========================================
$jsonContent = json_encode($lastmodData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($lastmodFile, $jsonContent);

// Clear output buffer
ob_end_clean();

// Set XML headers
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

// Generate XML sitemap
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $url) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
    echo '    <priority>' . $url['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

echo '</urlset>';
exit;