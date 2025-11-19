<?php
/**
 * Dynamic robots.txt Generator for Multi-Domain Streaming Websites
 * 
 * This file generates a unique robots.txt for each domain
 * based on the domain that is requesting it.
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

// Point to sitemap
echo "# Sitemap location\n";
echo "Sitemap: " . $baseUrl . "/sitemap.xml\n";

exit;