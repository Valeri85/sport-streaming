<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ FIX: Get domain and normalize it (remove www. prefix)
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
    // ✅ FIX: Also normalize the domain from JSON before comparing
    $siteDomain = str_replace('www.', '', strtolower(trim($site['domain'])));
    
    if ($siteDomain === $domain && $site['status'] === 'active') {
        $website = $site;
        break;
    }
}

if (!$website) {
    // ✅ IMPROVED ERROR: Show what we're looking for vs what we have
    die("Website not found. Looking for: '" . htmlspecialchars($domain) . "'. Available domains: " . 
        implode(', ', array_map(function($s) { 
            return "'" . str_replace('www.', '', strtolower(trim($s['domain']))) . "'";
        }, $websites)));
}

// ==========================================
// LOAD AVAILABLE LANGUAGES (Global)
// ==========================================
    $langDir = __DIR__ . '/config/lang/';
    $allActiveLanguages = []; // All globally active languages

    if (is_dir($langDir)) {
        $langFiles = glob($langDir . '*.json');
        foreach ($langFiles as $file) {
            $langCode = basename($file, '.json');
            $langData = json_decode(file_get_contents($file), true);
            
            // Only include globally active languages
            if (isset($langData['language_info']) && ($langData['language_info']['active'] ?? false)) {
                $allActiveLanguages[$langCode] = [
                    'code' => $langCode,
                    'name' => $langData['language_info']['name'] ?? $langCode,
                    'flag_code' => strtoupper($langData['language_info']['flag_code'] ?? $langCode)
                ];
            }
        }
    }

    // ==========================================
    // FILTER LANGUAGES BY WEBSITE'S ENABLED LANGUAGES
    // ==========================================
    $enabledLanguages = $website['enabled_languages'] ?? array_keys($allActiveLanguages);
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

// ==========================================
// DETERMINE ACTIVE LANGUAGE
// Priority: 1. URL path (/es) 2. Cookie 3. Admin default
// ==========================================
$defaultLanguage = $website['language'] ?? 'en';
$websiteLanguage = $defaultLanguage;

// ==========================================
// Clean URL Language Detection
// Check if language code is in URL (from .htaccess rewrite)
// .htaccess passes ?url_lang=xx when URL is /xx or /xx/...
// ==========================================
$urlLang = $_GET['url_lang'] ?? null;

// ==========================================
// 404 FOR DISABLED LANGUAGE URLs
// If URL has a language code that is NOT enabled for this website, return 404
// ==========================================
if ($urlLang) {
    // Check if the language exists globally but is disabled for THIS website
    if (isset($allActiveLanguages[$urlLang]) && !in_array($urlLang, $enabledLanguages)) {
        // Language exists globally but NOT enabled for this website - return 404
        http_response_code(404);
        
        // Get site info for error page
        $siteName = $website['site_name'] ?? 'Website';
        $primaryColor = $website['primary_color'] ?? '#FFA500';
        
        // Show custom 404 page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 - Language Not Available | <?php echo htmlspecialchars($siteName); ?></title>
            <meta name="robots" content="noindex, nofollow">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    color: #fff;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    padding: 20px;
                }
                .error-container { max-width: 500px; }
                .error-code {
                    font-size: 120px;
                    font-weight: 700;
                    color: <?php echo htmlspecialchars($primaryColor); ?>;
                    line-height: 1;
                    margin-bottom: 10px;
                }
                .error-icon { font-size: 64px; margin-bottom: 20px; }
                h1 { font-size: 28px; margin-bottom: 15px; color: #fff; }
                p { font-size: 16px; color: #a0a0a0; margin-bottom: 30px; line-height: 1.6; }
                .available-languages {
                    background: rgba(255,255,255,0.1);
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 30px;
                }
                .available-languages h3 {
                    font-size: 14px;
                    color: #a0a0a0;
                    margin-bottom: 15px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .lang-buttons {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    justify-content: center;
                }
                .lang-btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background: <?php echo htmlspecialchars($primaryColor); ?>;
                    color: #000;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 600;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .lang-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 15px rgba(255,165,0,0.4);
                }
                .home-link {
                    color: <?php echo htmlspecialchars($primaryColor); ?>;
                    text-decoration: none;
                    font-weight: 600;
                }
                .home-link:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">🌐</div>
                <div class="error-code">404</div>
                <h1>Language Not Available</h1>
                <p>
                    The requested language <strong>"<?php echo htmlspecialchars($urlLang); ?>"</strong> 
                    is not available for this website.
                </p>
                
                <div class="available-languages">
                    <h3>Available Languages</h3>
                    <div class="lang-buttons">
                        <?php foreach ($availableLanguages as $code => $info): 
                            $langUrl = ($code === $defaultLanguage) ? '/' : '/' . $code;
                        ?>
                            <a href="<?php echo $langUrl; ?>" class="lang-btn">
                                <?php echo htmlspecialchars($info['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <p>
                    <a href="/" class="home-link">← Go to Homepage</a>
                </p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

if ($urlLang && array_key_exists($urlLang, $availableLanguages)) {
    // Language code in URL
    if ($urlLang === $defaultLanguage) {
        // Default language in URL - redirect to clean URL (only one redirect, SEO friendly)
        // Delete cookie first
        setcookie('user_language', '', time() - 3600, '/');
        
        $redirectPath = '/';
        if (isset($_GET['view']) && $_GET['view'] === 'favorites') {
            $redirectPath = '/favorites';
        } elseif (isset($_GET['sport'])) {
            $redirectPath = '/live-' . $_GET['sport'];
        }
        
        // Preserve tab parameter
        if (isset($_GET['tab'])) {
            $redirectPath .= '?tab=' . $_GET['tab'];
        }
        
        header('Location: ' . $redirectPath, true, 301);
        exit;
    }
    
    // Non-default language in URL - use it and set cookie
    $websiteLanguage = $urlLang;
    setcookie('user_language', $websiteLanguage, time() + (30 * 24 * 60 * 60), '/');
}
// No language in URL - this means default language OR user navigating within site
elseif (!$urlLang) {
    // ✅ KEY FIX: Check if user explicitly clicked default language link
    // We detect this by checking HTTP_REFERER - if they came from a language URL
    // and now on clean URL, they switched to default
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    
    // Check if referer is from same site with a language prefix
    $cameFromLanguageUrl = false;
    if (!empty($referer) && strpos($referer, $currentHost) !== false) {
        // Check if referer had a language code like /es, /fr, etc.
        foreach ($availableLanguages as $code => $langInfo) {
            if ($code !== $defaultLanguage && preg_match('#/' . $code . '(/|$|\?)#', $referer)) {
                $cameFromLanguageUrl = true;
                break;
            }
        }
    }
    
    if ($cameFromLanguageUrl) {
        // User came from a language URL to clean URL = they switched to default
        // Delete the cookie
        setcookie('user_language', '', time() - 3600, '/');
        $websiteLanguage = $defaultLanguage;
    }
    // Check cookie for returning visitors (not switching)
    elseif (isset($_COOKIE['user_language']) && array_key_exists($_COOKIE['user_language'], $availableLanguages)) {
        $websiteLanguage = $_COOKIE['user_language'];
    }
    // Fallback to default language (already set above)
}
// Fallback to default language (already set above)

// Store default language for template use
$siteDefaultLanguage = $defaultLanguage;

// ==========================================
// HELPER: Build language URL
// Returns clean URL for language switcher links
// ==========================================
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

// ==========================================
// HELPER: Build internal link with language prefix
// ==========================================
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
// HELPER: Get SEO data for specific language
// Returns title and description for current page/language
// ==========================================
function getLanguageSeoData($langCode, $domain, $pageType, $sportSlug, $langDir, $defaultSeo) {
    // For English (default), return the default SEO from websites.json
    if ($langCode === 'en') {
        return $defaultSeo;
    }

    // ==========================================
    // GENERATE FAVICON HTML TAGS
    // ==========================================
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

// ==========================================
// HELPER: Generate hreflang tags for all active languages
// ==========================================
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
// GENERATE FAVICON HTML TAGS
// ==========================================
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

// ==========================================
// GENERATE GOOGLE ANALYTICS CODE
// ==========================================
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
// LOAD LANGUAGE FILE
// ==========================================
$langFile = __DIR__ . '/config/lang/' . $websiteLanguage . '.json';

// ==========================================
// RTL LANGUAGE DETECTION
// ==========================================
$rtlLanguages = ['ar', 'he', 'fa', 'ur']; // Arabic, Hebrew, Persian, Urdu
$isRTL = in_array($websiteLanguage, $rtlLanguages);

// Also check language_info from loaded language file if available
if (isset($lang['language_info']['direction'])) {
    $isRTL = ($lang['language_info']['direction'] === 'rtl');
}

$textDirection = $isRTL ? 'rtl' : 'ltr';

// Fallback to English if language file not found
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
// TRANSLATION HELPER FUNCTION
// ==========================================
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

// Function to translate sport name
function tSport($sportName) {
    global $lang;
    
    // Look up in sports translations
    if (isset($lang['sports'][$sportName])) {
        return $lang['sports'][$sportName];
    }
    
    // Fallback: return original name
    return $sportName;
}

$siteName = $website['site_name'];
$logo = $website['logo'];
$primaryColor = $website['primary_color'];
$secondaryColor = $website['secondary_color'];
$language = $website['language'];
$sidebarContent = $website['sidebar_content'];
// NEW: Get analytics and custom head code
$googleAnalyticsId = $website['google_analytics_id'] ?? '';
$customHeadCode = $website['custom_head_code'] ?? '';
$faviconFolder = $website['favicon'] ?? '';

$jsonFile = '/var/www/u1852176/data/www/data/data.json';
$gamesData = [];
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    $gamesData = $data['games'] ?? [];
}

// Function to send Slack notification about new sports
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

// Check for new sports in data.json
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
        // Exact match only - no grouping logic
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
// ROUTE DETECTION (updated for .htaccess params)
// ==========================================
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Check for sport from .htaccess rewrite first, then fallback to old method
$activeSport = $_GET['sport'] ?? null;

if (!$activeSport) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = trim($path, '/');
    
    // Remove language prefix if present for route detection
    if ($urlLang && strpos($path, $urlLang) === 0) {
        $path = ltrim(substr($path, strlen($urlLang)), '/');
    }
    
    if (preg_match('/^live-(.+)$/', $path, $matches)) {
        $activeSport = $matches[1];
    }
}

// Check for favorites from .htaccess rewrite first
$viewFavorites = false;
if (isset($_GET['view']) && $_GET['view'] === 'favorites') {
    $viewFavorites = true;
} elseif (strpos($_SERVER['REQUEST_URI'], '/favorites') !== false) {
    $viewFavorites = true;
}

// ==========================================
// CANONICAL URL & NOINDEX LOGIC
// ==========================================

// Get base canonical URL from website config
$baseCanonicalUrl = $website['canonical_url'] ?? 'https://www.' . $domain;
$baseCanonicalUrl = rtrim($baseCanonicalUrl, '/');

// Determine the canonical URL and whether to index
$canonicalUrl = $baseCanonicalUrl; // Default to homepage
$shouldNoindex = false; // Default: allow indexing

// Build language prefix for canonical URL
$langPrefix = ($websiteLanguage !== $siteDefaultLanguage) ? '/' . $websiteLanguage : '';

if ($viewFavorites) {
    // Favorites page - don't index (user-specific)
    $canonicalUrl = $baseCanonicalUrl . $langPrefix . '/favorites';
    $shouldNoindex = true; // Don't index favorites
    
} elseif ($activeSport) {
    // Sport-specific page
    $canonicalUrl = $baseCanonicalUrl . $langPrefix . '/live-' . $activeSport;
    
    // If there's a tab filter (?tab=soon or ?tab=tomorrow), don't index
    if ($activeTab !== 'all') {
        $shouldNoindex = true; // Don't index filtered views
    }
    
} else {
    // Homepage
    if ($langPrefix) {
        $canonicalUrl = $baseCanonicalUrl . $langPrefix;
    } else {
        $canonicalUrl = $baseCanonicalUrl . '/';
    }
    
    // If homepage has tab filter, don't index
    if ($activeTab !== 'all') {
        $shouldNoindex = true; // Don't index filtered views
    }
}

// ==========================================
// END: CANONICAL URL & NOINDEX LOGIC
// ==========================================

// ==========================================
// SEO: Get language-specific title and description
// ==========================================
$pagesSeo = $website['pages_seo'] ?? [];

// Step 1: Get default (English) SEO from websites.json
$defaultSeoTitle = '';
$defaultSeoDescription = '';

if ($viewFavorites) {
    $defaultSeoTitle = $pagesSeo['favorites']['title'] ?? $pagesSeo['home']['title'] ?? '';
    $defaultSeoDescription = $pagesSeo['favorites']['description'] ?? $pagesSeo['home']['description'] ?? '';
} elseif ($activeSport) {
    $defaultSeoTitle = $pagesSeo['sports'][$activeSport]['title'] ?? $pagesSeo['home']['title'] ?? '';
    $defaultSeoDescription = $pagesSeo['sports'][$activeSport]['description'] ?? $pagesSeo['home']['description'] ?? '';
} else {
    $defaultSeoTitle = $pagesSeo['home']['title'] ?? '';
    $defaultSeoDescription = $pagesSeo['home']['description'] ?? '';
}

// Step 2: Get language-specific SEO (or fallback to English)
$defaultSeo = [
    'title' => $defaultSeoTitle,
    'description' => $defaultSeoDescription
];

// Determine page type for SEO lookup
$seoPageType = 'home';
$seoSportSlug = null;
if ($viewFavorites) {
    $seoPageType = 'favorites'; // Note: favorites may not have translations, will fallback
} elseif ($activeSport) {
    $seoPageType = 'sport';
    $seoSportSlug = $activeSport;
}

// Get language-specific SEO
$languageSeo = getLanguageSeoData($websiteLanguage, $domain, $seoPageType, $seoSportSlug, $langDir, $defaultSeo);

$seoTitle = $languageSeo['title'];
$seoDescription = $languageSeo['description'];

// ==========================================
// Generate hreflang tags
// ==========================================
$hreflangTags = generateHreflangTags($availableLanguages, $siteDefaultLanguage, $baseCanonicalUrl, $activeSport, $viewFavorites);

// ==========================================
// Generate favicon tags
// ==========================================
$faviconDir = __DIR__ . '/images/favicons/';
$faviconTags = generateFaviconTags($faviconFolder, $faviconDir);

// ==========================================
// Generate Google Analytics code
// ==========================================
$analyticsCode = generateGoogleAnalyticsCode($googleAnalyticsId);

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

/**
 * Get sport icon from master icons folder
 * Icons are stored in /shared/icons/sports/ and shared across all websites
 * 
 * @param string $sportName The sport name (e.g., "Ice Hockey", "Football")
 * @return string HTML img tag or emoji fallback
 */
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
            return '<img src="' . $iconPath . '" alt="' . htmlspecialchars($sportName) . '" class="sport-icon-img" width="24" height="24" onerror="this.parentElement.innerHTML=\'⚽\'">';
        }
    }
    
    // If no icon found, show default emoji
    return '⚽';
}

// Function to render logo with RELATIVE path
function renderLogo($logo) {
    // Check if logo contains file extension (is a file)
    if (preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $logo)) {
        $logoFile = htmlspecialchars($logo);
        $logoPath = '/images/logos/' . $logoFile;
        return '<img src="' . $logoPath . '" alt="Logo" class="logo-image" width="48" height="48" style="object-fit: contain;">';
    } else {
        return $logo;
    }
}

$filteredGames = $gamesData;

if ($viewFavorites) {
    $filteredGames = $gamesData;
} else {
    if ($activeSport) {
        // REMOVED GROUPING LOGIC - Now exact match only
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

// Count games for each configured sport - exact match only
foreach ($gamesData as $game) {
    $sport = $game['sport'];
    
    // Exact match only - no grouping
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
<html lang="<?php echo htmlspecialchars($websiteLanguage); ?>" dir="<?php echo $textDirection; ?>">
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
    
    <!-- HREFLANG TAGS FOR MULTILINGUAL SEO -->
    <?php echo $hreflangTags; ?>

    <?php 
    // ==========================================
    // NEW: FAVICON TAGS
    // ==========================================
    if (!empty($faviconTags)): 
    ?>
    <?php echo $faviconTags; ?>

    <?php endif; ?>

    <?php 
    // ==========================================
    // NEW: GOOGLE ANALYTICS
    // ==========================================
    if (!empty($analyticsCode)): 
    ?>
    <?php echo $analyticsCode; ?>

    <?php endif; ?>

    <?php 
    // ==========================================
    // NEW: CUSTOM HEAD CODE (Ads, tracking, etc.)
    // ==========================================
    if (!empty($customHeadCode)): 
    ?>
    <!-- CUSTOM HEAD CODE -->
    <?php echo $customHeadCode; ?>

    <?php endif; ?>

    <link rel="stylesheet" href="/styles.css">
    <script>
        // Pass translations to JavaScript
        window.TRANSLATIONS = <?php echo json_encode($jsTranslations, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/main.js" defer></script>
    <style>
        .date-tab.active {
            background-color: <?php echo $secondaryColor; ?>;
        }
        
        /* Logo image styling */
        .logo-image {
            border-radius: 8px;
        }
    </style>
</head>
<body class="<?php echo $isRTL ? 'rtl' : 'ltr'; ?>"
        data-viewing-favorites="<?php echo $viewFavorites ? 'true' : 'false'; ?>" 
        data-primary-color="<?php echo $primaryColor; ?>"
        data-active-sport="<?php echo $activeSport ?: ''; ?>"
        data-active-tab="<?php echo $activeTab; ?>"
        data-direction="<?php echo $textDirection; ?>">
    
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
                    // Show translated sport name in title
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
            <!-- LANGUAGE SWITCHER - Compact Grid with Flags Only -->
            <div class="language-switcher" id="languageSwitcher">
                <button class="language-toggle" id="languageToggle" aria-label="<?php echo htmlspecialchars(t('change_language', 'accessibility')); ?>" aria-expanded="false">
                    <img src="/shared/icons/flags/<?php echo htmlspecialchars(strtoupper($availableLanguages[$websiteLanguage]['flag_code'] ?? 'GB')); ?>.svg" 
                        alt="<?php echo htmlspecialchars($availableLanguages[$websiteLanguage]['name'] ?? 'Language'); ?>" 
                        class="current-flag"
                        width="24"
                        height="18">
                    <span class="language-arrow">v</span>
                </button>
                <div class="language-dropdown" id="languageDropdown">
                    <?php foreach ($availableLanguages as $code => $langInfo): 
                        $isActive = ($code === $websiteLanguage);
                        $langSwitchUrl = buildLanguageUrl($code, $siteDefaultLanguage, $activeSport, $viewFavorites, $activeTab);
                        $onclickAttr = ($code === $siteDefaultLanguage) ? 'onclick="document.cookie=\'user_language=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;\';"' : '';
                    ?>
                        <a href="<?php echo htmlspecialchars($langSwitchUrl); ?>" 
                        class="language-option <?php echo $isActive ? 'active' : ''; ?>"
                        data-lang-name="<?php echo htmlspecialchars($langInfo['name'] ?? $code); ?>"
                        title="<?php echo htmlspecialchars($langInfo['name'] ?? $code); ?>"
                        <?php echo $onclickAttr; ?>>
                            <img src="/shared/icons/flags/<?php echo htmlspecialchars(strtoupper($langInfo['flag_code'] ?? 'GB')); ?>.svg" 
                                alt="<?php echo htmlspecialchars($langInfo['name'] ?? $code); ?>" 
                                class="lang-flag"
                                width="24"
                                height="18">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- BURGER MENU -->
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
                <a href="<?php echo $sportUrl; ?>" class="menu-item <?php echo $isActive ? 'active' : ''; ?>" onclick="saveScrollPosition(event)">
                    <span class="menu-item-left">
                        <span class="sport-icon"><?php echo $icon; ?></span>
                        <span class="sport-name"><?php echo htmlspecialchars($translatedSportName); ?></span>
                    </span>
                    <span class="sport-count"><?php echo $count; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <?php if (!$viewFavorites): ?>
        <nav class="date-tabs-wrapper" aria-label="<?php echo htmlspecialchars(t('time_filter', 'accessibility')); ?>">
            <div class="date-tabs">
                <?php 
                // Build tab URLs with language prefix
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
                                    $countryFlag = getCountryFlag($group['country']);
                                    $countryName = getCountryName($group['country']);
                                ?>
                                    <section class="competition-group" data-league-id="<?php echo $leagueId; ?>" 
                                             data-country="<?php echo htmlspecialchars($group['country']); ?>"
                                             data-competition="<?php echo htmlspecialchars($group['competition']); ?>">
                                        <h3 class="sr-only"><?php echo htmlspecialchars($countryName . ' - ' . $group['competition']); ?></h3>
                                        <div class="competition-header">
                                            <span class="competition-name">
                                                <span><?php echo $countryFlag; ?></span>
                                                <span><?php echo htmlspecialchars($countryName); ?></span>
                                                <span>•</span>
                                                <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                            </span>
                                            <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>" role="button" aria-label="<?php echo htmlspecialchars(t('favorite_league', 'accessibility')); ?>">☆</span>
                                        </div>
                                        
                                        <?php foreach ($group['games'] as $game): ?>
                                            <details class="game-item-details" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                                <summary class="game-item-summary">
                                                    <time class="game-time" datetime="<?php echo $game['date']; ?>"><?php echo formatGameTime($game['date']); ?></time>
                                                    <span class="game-teams">
                                                        <span class="team">
                                                            <span class="team-icon"></span>
                                                            <?php echo htmlspecialchars($game['match']); ?>
                                                        </span>
                                                    </span>
                                                    <span class="game-actions">
                                                        <span class="link-count-badge" data-game-id="<?php echo $game['id']; ?>">0</span>
                                                        <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>" role="button" aria-label="<?php echo htmlspecialchars(t('favorite_game', 'accessibility')); ?>">☆</span>
                                                    </span>
                                                </summary>
                                                <div class="game-links-container"></div>
                                            </details>
                                        <?php endforeach; ?>
                                    </section>
                                <?php endforeach; ?>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </template>
            <?php elseif (empty($groupedBySport)): ?>
                <div class="no-games">
                    <p><?php echo htmlspecialchars(t('no_games', 'messages')); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($groupedBySport as $sportName => $sportGames): 
                    $sportIconDisplay = getSportIcon($sportName);
                    $sportId = strtolower(str_replace(' ', '-', $sportName));
                    $translatedSportName = tSport($sportName);
                ?>
                    <article class="sport-category" id="<?php echo $sportId; ?>" data-sport="<?php echo htmlspecialchars($sportName); ?>">
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
                                $countryFlag = getCountryFlag($group['country']);
                                $countryName = getCountryName($group['country']);
                            ?>
                                <section class="competition-group" data-league-id="<?php echo $leagueId; ?>" 
                                         data-country="<?php echo htmlspecialchars($group['country']); ?>"
                                         data-competition="<?php echo htmlspecialchars($group['competition']); ?>">
                                    <h3 class="sr-only"><?php echo htmlspecialchars($countryName . ' - ' . $group['competition']); ?></h3>
                                    <div class="competition-header">
                                        <span class="competition-name">
                                            <span><?php echo $countryFlag; ?></span>
                                            <span><?php echo htmlspecialchars($countryName); ?></span>
                                            <span>•</span>
                                            <span><?php echo htmlspecialchars($group['competition']); ?></span>
                                        </span>
                                        <span class="league-favorite" data-league-id="<?php echo $leagueId; ?>" role="button" aria-label="<?php echo htmlspecialchars(t('favorite_league', 'accessibility')); ?>">☆</span>
                                    </div>
                                    
                                    <?php foreach ($group['games'] as $game): ?>
                                        <details class="game-item-details" data-game-id="<?php echo $game['id']; ?>" data-league-id="<?php echo $leagueId; ?>">
                                            <summary class="game-item-summary">
                                                <time class="game-time" datetime="<?php echo $game['date']; ?>"><?php echo formatGameTime($game['date']); ?></time>
                                                <span class="game-teams">
                                                    <span class="team">
                                                        <span class="team-icon"></span>
                                                        <?php echo htmlspecialchars($game['match']); ?>
                                                    </span>
                                                </span>
                                                <span class="game-actions">
                                                    <span class="link-count-badge" data-game-id="<?php echo $game['id']; ?>">0</span>
                                                    <span class="favorite-star" data-game-id="<?php echo $game['id']; ?>" role="button" aria-label="<?php echo htmlspecialchars(t('favorite_game', 'accessibility')); ?>">☆</span>
                                                </span>
                                            </summary>
                                            <div class="game-links-container"></div>
                                        </details>
                                    <?php endforeach; ?>
                                </section>
                            <?php endforeach; ?>
                        </details>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- RIGHT SIDEBAR -->
    <aside class="right-sidebar">
        <div class="sidebar-content">
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