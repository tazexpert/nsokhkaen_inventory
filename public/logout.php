<?php
require_once __DIR__ . '/../config/config.php';
$wasPinOnly = isset($_SESSION['scan_pin_user']) && !isset($_SESSION['user']);
session_destroy();
header('Location: ' . ($wasPinOnly ? 'scan_login.php' : 'index.php'));
exit;
