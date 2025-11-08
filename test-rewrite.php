<?php
// Test file to check if URL rewriting is working
// Upload this as test-rewrite.php and visit: yourdomain.com/test-rewrite.php

echo "<h2>URL Rewriting Test</h2>";
echo "<pre>";

echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";

echo "\n--- Parsed URL ---\n";
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "Path: " . $path . "\n";
echo "Trimmed: " . trim($path, '/') . "\n";

echo "\n--- Pattern Matching ---\n";
if (preg_match('/^live-(.+)$/', trim($path, '/'), $matches)) {
    echo "✓ Pattern matched!\n";
    echo "Sport: " . $matches[1] . "\n";
} else {
    echo "✗ Pattern did not match\n";
}

echo "\n--- mod_rewrite Status ---\n";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "✓ mod_rewrite is enabled\n";
    } else {
        echo "✗ mod_rewrite is NOT enabled\n";
    }
} else {
    echo "? Cannot detect (function not available)\n";
}

echo "\n--- .htaccess Test ---\n";
if (file_exists('.htaccess')) {
    echo "✓ .htaccess file exists\n";
    echo "File size: " . filesize('.htaccess') . " bytes\n";
} else {
    echo "✗ .htaccess file NOT found\n";
}

echo "\n--- Test Links ---\n";
echo "Try these URLs:\n";
echo "1. " . $_SERVER['HTTP_HOST'] . "/live-basketball\n";
echo "2. " . $_SERVER['HTTP_HOST'] . "/live-football\n";
echo "3. " . $_SERVER['HTTP_HOST'] . "/live-ice-hockey\n";

echo "</pre>";

echo "<h3>Quick Test</h3>";
echo "<p>Click these links to test:</p>";
echo "<ul>";
echo "<li><a href='/live-basketball'>Live Basketball</a></li>";
echo "<li><a href='/live-football'>Live Football</a></li>";
echo "<li><a href='/live-tennis'>Live Tennis</a></li>";
echo "</ul>";
?>