<?php
/**
 * Dynamic robots.txt Generator for Multi-Domain Streaming Websites
 * 
 * This file generates a unique robots.txt for each domain
 * based on the domain that is requesting it.
 * 
 * FEATURES:
 * - Adds Disallow rules for languages not enabled for this website
 * - Blocks crawling of user-specific pages (/favorites)
 * - Blocks crawling of filtered views (?tab=soon, ?tab=tomorrow)
 * - Points to sitemap index
 * 
 * REFACTORED: Now uses shared functions and constants from includes/
 * 
 * Location: /var/www/u1852176/data/www/streaming/robots.php
 */

// Prevent any output before robots.txt content
ob_start();

// ==========================================
// LOAD CONFIGURATION AND SHARED FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

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
$baseUrl = rtrim($baseUrl, '/'); // Remove trailing slash

// ==========================================
// LOAD LANGUAGES
// Uses shared functions: loadActiveLanguages()
// Uses constants: LANG_DIR
// ==========================================
$allActiveLanguages = loadActiveLanguages(LANG_DIR);

// Get just the language codes for comparison
$allActiveLangCodes = array_keys($allActiveLanguages);

// ==========================================
// GET ENABLED LANGUAGES FOR THIS WEBSITE
// ==========================================
$enabledLanguages = $website['enabled_languages'] ?? $allActiveLangCodes;

// Find disabled languages (globally active but not enabled for this site)
$disabledLanguages = array_diff($allActiveLangCodes, $enabledLanguages);

// ==========================================
// CLEAR OUTPUT BUFFER AND SET HEADERS
// ==========================================
ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');

// ==========================================
// OUTPUT ROBOTS.TXT CONTENT
// ==========================================

echo "# Robots.txt for " . htmlspecialchars($website['site_name']) . "\n";
echo "# Domain: " . htmlspecialchars($domain) . "\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "\n";

// Allow all bots to crawl everything
echo "User-agent: *\n";
echo "Allow: /\n";
echo "\n";

// ==========================================
// Disallow disabled language URLs
// ==========================================
if (!empty($disabledLanguages)) {
    echo "# Prevent indexing of disabled language URLs for this website\n";
    foreach ($disabledLanguages as $langCode) {
        echo "Disallow: /" . $langCode . "\n";
        echo "Disallow: /" . $langCode . "/\n";
    }
    echo "\n";
}

// Disallow specific sport pages (if any should be hidden)
echo "# Prevent indexing of specific sport pages\n";
echo "Disallow: /live-winter-sports\n";
echo "Disallow: /*/live-winter-sports\n";
echo "Disallow: /live-combat-sports\n";
echo "Disallow: /*/live-combat-sports\n";
echo "\n";

// Disallow favorites (user-specific, has noindex anyway)
echo "# Prevent crawling of user-specific pages\n";
echo "Disallow: /favorites\n";
echo "Disallow: /*/favorites\n";
echo "\n";

// Disallow CMS directory
echo "# Prevent crawling of CMS admin area\n";
echo "Disallow: /cms/\n";
echo "\n";

// Disallow config directory
echo "# Prevent crawling of configuration files\n";
echo "Disallow: /config/\n";
echo "\n";

// Disallow API directory
echo "# Prevent crawling of API directory\n";
echo "Disallow: /api/\n";
echo "\n";

// Disallow includes directory
echo "# Prevent crawling of includes directory\n";
echo "Disallow: /includes/\n";
echo "\n";

// Disallow filtered views (tab parameters)
echo "# Prevent crawling of filtered views\n";
echo "Disallow: /*?tab=soon\n";
echo "Disallow: /*?tab=tomorrow\n";
echo "\n";

// Point to sitemap index
echo "# Sitemap location (index file containing all language sitemaps)\n";
echo "Sitemap: " . $baseUrl . "/sitemap.xml\n";

exit;