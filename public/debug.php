<?php
// Temporary debug page — DELETE BEFORE PRODUCTION
echo '<pre>';
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? 'n/a') . "\n";
echo 'REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'n/a') . "\n";
echo 'DB_DRIVER: ' . ($_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'NOT SET') . "\n";
echo 'DB_HOST: ' . ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'NOT SET') . "\n";
echo 'APP_URL: ' . ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'NOT SET') . "\n";
echo 'STORAGE_PATH: ' . ($_ENV['STORAGE_PATH'] ?? getenv('STORAGE_PATH') ?: 'NOT SET') . "\n";
echo '</pre>';
