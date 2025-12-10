<?php
/**
 * Configuration Constants for Streaming Websites
 * 
 * This file contains all path constants and configuration values
 * used across multiple streaming files. Centralizing paths here means:
 * - Easy to update if server paths change
 * - No hardcoded paths scattered across files
 * - Single source of truth for all configurations
 * 
 * USAGE: Include this file at the top of any PHP file:
 *        require_once __DIR__ . '/includes/config.php';
 * 
 * NOTE: This file should be included BEFORE functions.php
 *       as some functions may use these constants.
 * 
 * Location: /var/www/u1852176/data/www/streaming/includes/config.php
 * 
 * @version 1.0
 * @date 2024-12
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    die('Direct access not allowed');
}

// ==========================================
// ROOT PATHS
// ==========================================

/**
 * Root directory of the streaming websites
 * All streaming site files are located here
 */
define('STREAMING_ROOT', dirname(__DIR__));

/**
 * Root directory for shared data (used by CMS and streaming sites)
 * Contains data.json with all games data
 */
define('DATA_ROOT', '/var/www/u1852176/data/www/data');

/**
 * CMS root directory
 * The admin interface at watchlivesport.online
 */
define('CMS_ROOT', '/var/www/u1852176/data/www/watchlivesport.online');

// ==========================================
// DATA FILES
// ==========================================

/**
 * Main games data JSON file
 * Contains all games from all sports, updated by scraper
 */
define('DATA_JSON_FILE', DATA_ROOT . '/data.json');

/**
 * Websites configuration file
 * Contains settings for all 20+ streaming websites
 */
define('WEBSITES_CONFIG_FILE', STREAMING_ROOT . '/config/websites.json');

/**
 * Slack webhook configuration
 * Used for notifications about new sports
 */
define('SLACK_CONFIG_FILE', STREAMING_ROOT . '/config/slack-config.json');

/**
 * Sitemap lastmod tracking file
 * Stores last modification dates for sitemap pages
 */
define('SITEMAP_LASTMOD_FILE', STREAMING_ROOT . '/config/sitemap-lastmod.json');

/**
 * Notified sports tracking file
 * Tracks which new sports have been notified via Slack
 */
define('NOTIFIED_SPORTS_FILE', STREAMING_ROOT . '/config/notified-sports.json');

// ==========================================
// DIRECTORIES
// ==========================================

/**
 * Language files directory
 * Contains JSON files for each language (en.json, es.json, etc.)
 */
define('LANG_DIR', STREAMING_ROOT . '/config/lang/');

/**
 * Website configuration directory
 */
define('CONFIG_DIR', STREAMING_ROOT . '/config/');

/**
 * Shared resources directory
 * Contains icons, flags, and other shared assets
 */
define('SHARED_DIR', STREAMING_ROOT . '/shared/');

/**
 * Sport icons directory (master icons shared across all sites)
 */
define('SPORT_ICONS_DIR', SHARED_DIR . 'icons/sports/');

/**
 * General icons directory (home icon, etc.)
 */
define('ICONS_DIR', SHARED_DIR . 'icons/');

/**
 * Country flags directory
 */
define('FLAGS_DIR', SHARED_DIR . 'icons/flags/');

/**
 * Website logos directory
 */
define('LOGOS_DIR', STREAMING_ROOT . '/images/logos/');

/**
 * Website favicons directory
 */
define('FAVICONS_DIR', STREAMING_ROOT . '/images/favicons/');

// ==========================================
// URL PATHS (for HTML output)
// ==========================================

/**
 * URL path to sport icons (relative to domain root)
 */
define('SPORT_ICONS_URL', '/shared/icons/sports/');

/**
 * URL path to general icons
 */
define('ICONS_URL', '/shared/icons/');

/**
 * URL path to flags
 */
define('FLAGS_URL', '/shared/icons/flags/');

/**
 * URL path to logos
 */
define('LOGOS_URL', '/images/logos/');

/**
 * URL path to favicons
 */
define('FAVICONS_URL', '/images/favicons/');

// ==========================================
// DEFAULT VALUES
// ==========================================

/**
 * Default language code
 */
define('DEFAULT_LANGUAGE', 'en');

/**
 * Default primary color (orange)
 */
define('DEFAULT_PRIMARY_COLOR', '#FFA500');

/**
 * Default secondary color
 */
define('DEFAULT_SECONDARY_COLOR', '#FF8C00');

/**
 * RTL (Right-to-Left) languages
 */
define('RTL_LANGUAGES', ['ar', 'he', 'fa', 'ur']);

/**
 * Supported icon file extensions (in order of preference)
 */
define('ICON_EXTENSIONS', ['webp', 'svg', 'avif', 'png']);

/**
 * Cookie expiration time for user language preference (30 days in seconds)
 */
define('LANGUAGE_COOKIE_EXPIRY', 30 * 24 * 60 * 60);

// ==========================================
// GAME TIME THRESHOLDS
// ==========================================

/**
 * "Soon" threshold in seconds (30 minutes)
 * Games starting within this time are marked as "soon"
 */
define('SOON_THRESHOLD_SECONDS', 1800);

// ==========================================
// API CONFIGURATION
// ==========================================

/**
 * Default number of sports to load per API request
 */
define('DEFAULT_SPORTS_PER_LOAD', 2);

// ==========================================
// DEBUG MODE
// Set to false in production
// ==========================================
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}