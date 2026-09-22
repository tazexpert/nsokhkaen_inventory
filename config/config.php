<?php
// Base application configuration
define('BASE_URL', '/');
define('APP_NAME', 'ระบบบริหารจัดการวัสดุและครุภัณฑ์ สำนักงานสถิติจังหวัดขอนแก่น');

// Bundled third-party libraries (no Composer required - plain PHP files under /libs)
require_once __DIR__ . '/../libs/simplexlsx/SimpleXLSX.php';
require_once __DIR__ . '/../libs/simplexlsx/SimpleXLSXGen.php';
require_once __DIR__ . '/../libs/phpqrcode/qrlib.php';

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
