<?php
// Base application configuration
define('BASE_URL', '/');
define('APP_NAME', 'ระบบบริหารจัดการวัสดุและครุภัณฑ์ สำนักงานสถิติจังหวัดขอนแก่น');

// Composer autoload (PhpSpreadsheet, endroid/qr-code)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
