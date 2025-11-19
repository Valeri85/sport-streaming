<?php
/**
 * Dynamic Sitemap Generator for Multi-Domain Streaming Websites
 * 
 * This file generates a unique sitemap.xml for each domain
 * based on the domain that is requesting it.
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
$baseUrl = rtrim($baseUrl, '/'); // Remove trailing slash

// Get sports categories for this website
$sportsCategories = $website['sports_categories'] ?? [];

// Get today's date for lastmod
$today = date('Y-m-d');

// Build URLs array
$urls = [];

// Add homepage
$urls[] = [
    'loc' => $baseUrl . '/',
    'lastmod' => $today,
    'priority' => '1.0'
];

// Add favorites page
$urls[] = [
    'loc' => $baseUrl . '/favorites',
    'lastmod' => $today,
    'priority' => '0.9'
];

// Add sport pages - all get priority 0.9
foreach ($sportsCategories as $sportName) {
    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
    
    $urls[] = [
        'loc' => $baseUrl . '/live-' . $sportSlug,
        'lastmod' => $today,
        'priority' => '0.9'
    ];
}

// Clear output buffer
ob_end_clean();

// Set XML headers
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex'); // Don't index the sitemap itself

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