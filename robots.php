<?php
/**
 * Dynamic robots.txt Generator for Multi-Domain Streaming Websites
 * 
 * This file generates a unique robots.txt for each domain
 * based on the domain that is requesting it.
 * 
 * NEW: Adds Disallow rules for languages not enabled for this website
 * 
 * Location: /var/www/u1852176/data/www/streaming/robots.php
 */

// Prevent any output before robots.txt content
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
$baseUrl = rtrim($baseUrl, '/'); // Remove trailing slash

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
            $allActiveLanguages[] = $code;
        }
    }
}

// ==========================================
// GET ENABLED LANGUAGES FOR THIS WEBSITE
// ==========================================
$enabledLanguages = $website['enabled_languages'] ?? $allActiveLanguages;

// Find disabled languages (globally active but not enabled for this site)
$disabledLanguages = array_diff($allActiveLanguages, $enabledLanguages);

// Clear output buffer
ob_end_clean();

// Set text/plain header for robots.txt
header('Content-Type: text/plain; charset=utf-8');

// Generate robots.txt content
echo "# Robots.txt for " . htmlspecialchars($website['site_name']) . "\n";
echo "# Domain: " . htmlspecialchars($domain) . "\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "\n";

// Allow all bots to crawl everything
echo "User-agent: *\n";
echo "Allow: /\n";
echo "\n";

// ==========================================
// NEW: Disallow disabled language URLs
// ==========================================
if (!empty($disabledLanguages)) {
    echo "# Prevent indexing of disabled language URLs for this website\n";
    foreach ($disabledLanguages as $langCode) {
        echo "Disallow: /" . $langCode . "\n";
        echo "Disallow: /" . $langCode . "/\n";
    }
    echo "\n";
}

// Disallow specific sport pages
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

// Disallow CMS directory (if you want to hide it from search engines)
echo "# Prevent crawling of CMS admin area\n";
echo "Disallow: /cms/\n";
echo "\n";

// Disallow config directory
echo "# Prevent crawling of configuration files\n";
echo "Disallow: /config/\n";
echo "\n";

// Disallow API directory (if you have one)
echo "# Prevent crawling of API directory\n";
echo "Disallow: /api/\n";
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