<?php
// Base application configuration

// Public URL of this application (protocol + host + path, with trailing slash).
// This is embedded into every printed QR code sticker, so it must be an address
// the SCANNING DEVICE (phone camera) can reach - not necessarily the same address
// you use in your own browser.
//   - Testing only on this PC: http://localhost/nsokhkaen_inventory/
//   - Scanning stickers with a phone on the same WiFi/LAN: use this PC's LAN IP,
//     e.g. http://192.168.1.50/nsokhkaen_inventory/ (see README for how to find it)
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/nsokhkaen_inventory/');

// Path portion of APP_URL, used for all internal links/redirects in the app
define('BASE_URL', parse_url(APP_URL, PHP_URL_PATH) ?: '/');

define('APP_NAME', 'ระบบบริหารจัดการวัสดุและครุภัณฑ์ สำนักงานสถิติจังหวัดขอนแก่น');

// Bundled third-party libraries (no Composer required - plain PHP files under /libs)
require_once __DIR__ . '/../libs/simplexlsx/SimpleXLSX.php';
require_once __DIR__ . '/../libs/simplexlsx/SimpleXLSXGen.php';
require_once __DIR__ . '/../libs/phpqrcode/qrlib.php';

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
