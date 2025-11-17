<?php
/**
 * DIAGNOSTIC TOOL - Upload this to your website's document root
 * Access it via: https://yourdomain.com/test-icon-path.php
 * This will show you the actual paths and help debug the icon issue
 */

$domain = $_SERVER['HTTP_HOST'];
$domain = str_replace('www.', '', $domain);

echo "<h1>Sport Icon Path Diagnostic</h1>";
echo "<hr>";

// 1. Check document root
echo "<h2>1. Document Root</h2>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>This File Location:</strong> " . __FILE__ . "</p>";
echo "<p><strong>This File Directory:</strong> " . __DIR__ . "</p>";

// 2. Check images directory
echo "<hr><h2>2. Images Directory Checks</h2>";

$possiblePaths = [
    __DIR__ . '/images/sports/',
    $_SERVER['DOCUMENT_ROOT'] . '/images/sports/',
    '/var/www/u1852176/data/www/streaming/images/sports/',
    __DIR__ . '/../images/sports/',
];

foreach ($possiblePaths as $path) {
    echo "<p><strong>Path:</strong> $path<br>";
    echo "<strong>Exists:</strong> " . (file_exists($path) ? '✅ YES' : '❌ NO') . "<br>";
    echo "<strong>Readable:</strong> " . (is_readable($path) ? '✅ YES' : '❌ NO') . "<br>";
    
    if (file_exists($path) && is_readable($path)) {
        $files = glob($path . 'sport_*');
        echo "<strong>Files found:</strong> " . count($files) . "<br>";
        if (count($files) > 0) {
            echo "<strong>Sample files:</strong><br>";
            foreach (array_slice($files, 0, 3) as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $perms = substr(sprintf('%o', fileperms($file)), -4);
                echo "&nbsp;&nbsp;&nbsp;&nbsp;- $filename (Size: $size bytes, Perms: $perms)<br>";
            }
        }
    }
    echo "</p>";
}

// 3. Check config file
echo "<hr><h2>3. Website Config Check</h2>";
$configFile = __DIR__ . '/config/websites.json';
if (!file_exists($configFile)) {
    $configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
}

if (file_exists($configFile)) {
    echo "<p><strong>Config file found:</strong> $configFile</p>";
    $configContent = file_get_contents($configFile);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    $currentWebsite = null;
    foreach ($websites as $site) {
        if ($site['domain'] === $domain) {
            $currentWebsite = $site;
            break;
        }
    }
    
    if ($currentWebsite) {
        echo "<p><strong>Current website:</strong> " . htmlspecialchars($currentWebsite['site_name']) . "</p>";
        $sportsIcons = $currentWebsite['sports_icons'] ?? [];
        echo "<p><strong>Sports icons configured:</strong> " . count($sportsIcons) . "</p>";
        
        if (count($sportsIcons) > 0) {
            echo "<p><strong>Icon mappings:</strong></p>";
            echo "<ul>";
            foreach ($sportsIcons as $sport => $iconFile) {
                echo "<li><strong>$sport:</strong> $iconFile</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: red;'><strong>Current website not found in config!</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>Config file not found!</strong></p>";
}

// 4. Test actual icon access
echo "<hr><h2>4. Test Icon Access</h2>";

if (isset($currentWebsite) && isset($sportsIcons) && count($sportsIcons) > 0) {
    $firstIcon = array_values($sportsIcons)[0];
    $firstSport = array_keys($sportsIcons)[0];
    
    echo "<p><strong>Testing icon:</strong> $firstSport → $firstIcon</p>";
    
    // Try to find the file
    $foundPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path . $firstIcon)) {
            $foundPath = $path . $firstIcon;
            break;
        }
    }
    
    if ($foundPath) {
        echo "<p style='color: green;'><strong>✅ Icon file found at:</strong> $foundPath</p>";
        $webPath = '/images/sports/' . $firstIcon;
        $fullUrl = 'https://' . $domain . $webPath;
        
        echo "<p><strong>Web path should be:</strong> $webPath</p>";
        echo "<p><strong>Full URL should be:</strong> <a href='$fullUrl' target='_blank'>$fullUrl</a></p>";
        
        echo "<h3>Icon Preview:</h3>";
        echo "<img src='$webPath' alt='Test' style='width: 64px; height: 64px; border: 2px solid #ccc; padding: 10px; background: #f0f0f0;' onerror=\"this.parentElement.innerHTML += '<br><strong style=color:red;>❌ Failed to load from: $webPath</strong>'\">";
    } else {
        echo "<p style='color: red;'><strong>❌ Icon file NOT found in any location!</strong></p>";
    }
}

// 5. Recommendation
echo "<hr><h2>5. Recommendation</h2>";
echo "<p><strong>Based on the checks above:</strong></p>";
echo "<ol>";
echo "<li>Find which path exists and contains sport_* files</li>";
echo "<li>Ensure that path is accessible from the web as /images/sports/</li>";
echo "<li>Check file permissions (should be 644 for files, 755 for directories)</li>";
echo "<li>If files are in /var/www/u1852176/data/www/streaming/images/sports/, you may need to create a symlink or adjust document root</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>Delete this file after testing!</strong></p>";
?>