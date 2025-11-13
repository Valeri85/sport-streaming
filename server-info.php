<?php
// Server Detection and Compression Test Script
// Upload this as server-info.php and visit it in browser

echo "<h2>Server Information</h2>";
echo "<pre>";

// Detect server type
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n\n";

if (stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
    echo "✓ You are using Apache\n";
} elseif (stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) {
    echo "✓ You are using Nginx\n";
} else {
    echo "? Unknown server type\n";
}

echo "\n--- Apache Modules (if available) ---\n";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    
    // Check for compression modules
    $compressionModules = ['mod_deflate', 'mod_gzip', 'mod_brotli'];
    foreach ($compressionModules as $mod) {
        if (in_array($mod, $modules)) {
            echo "✓ $mod is enabled\n";
        } else {
            echo "✗ $mod is NOT enabled\n";
        }
    }
    
    // Check for rewrite module
    if (in_array('mod_rewrite', $modules)) {
        echo "✓ mod_rewrite is enabled\n";
    }
} else {
    echo "? Cannot detect modules (not Apache or function disabled)\n";
}

echo "\n--- PHP Configuration ---\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Zlib (compression): " . (extension_loaded('zlib') ? '✓ Enabled' : '✗ Disabled') . "\n";
echo "Output Buffering: " . ini_get('output_buffering') . "\n";

echo "\n--- Current Compression Status ---\n";
if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
    echo "Browser Accepts: " . $_SERVER['HTTP_ACCEPT_ENCODING'] . "\n";
} else {
    echo "Browser encoding info not available\n";
}

// Check if current response is compressed
$headers = headers_list();
$compressed = false;
foreach ($headers as $header) {
    if (stripos($header, 'Content-Encoding') !== false) {
        echo "✓ Response is compressed: $header\n";
        $compressed = true;
    }
}
if (!$compressed) {
    echo "✗ Response is NOT compressed\n";
}

echo "\n--- Recommendations ---\n";
if (stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
    echo "→ You can enable compression via .htaccess\n";
    echo "→ Check if mod_deflate is available\n";
} elseif (stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) {
    echo "→ You need to configure compression in nginx.conf\n";
    echo "→ Contact your hosting provider\n";
} else {
    echo "→ Use PHP compression as fallback\n";
}

echo "</pre>";

echo "<h3>Test Compression</h3>";
echo "<p>Open your browser's Network tab and reload this page.</p>";
echo "<p>Look for 'Content-Encoding: gzip' or 'Content-Encoding: br' in response headers.</p>";
?>