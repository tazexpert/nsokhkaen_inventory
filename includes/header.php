<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>public/dashboard.php">
            <i class="bi bi-box-seam"></i> ระบบบริหารจัดการวัสดุและครุภัณฑ์
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>public/dashboard.php">แดชบอร์ด</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'scan.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>public/scan.php">สแกน QR Code</a>
                </li>
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'materials.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>public/materials.php">วัสดุสิ้นเปลือง</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'assets.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>public/assets.php">ครุภัณฑ์</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>public/reports.php">รายงาน</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>public/settings.php">ตั้งค่าระบบ</a>
                </li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white me-3">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['full_name']) ?>
                (<?= $user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'เจ้าหน้าที่' ?>)
            </span>
            <a href="<?= BASE_URL ?>public/logout.php" class="btn btn-outline-light btn-sm">ออกจากระบบ</a>
        </div>
    </div>
</nav>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="container-fluid py-4">
<script>const BASE_URL_JS = '<?= BASE_URL ?>';</script>
