<?php
require_once __DIR__ . '/../config/config.php';
$wasPinOnly = ($_SESSION['user']['via_pin'] ?? false) === true;
session_destroy();
header('Location: ' . ($wasPinOnly ? 'scan_login.php' : 'index.php'));
exit;
