<?php
// Printable catalog of materials or assets, each item showing its photo,
// a scannable QR code, code and name only (no other details) - large cards,
// 2x3 (6) per A4 portrait page, for printing and keeping as a physical
// reference binder/board.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDbConnection();
$type = $_GET['type'] ?? 'material';

if ($type === 'asset') {
    $rows = $pdo->query("
        SELECT asset_code AS code, qr_code, name, image_path
        FROM assets
        ORDER BY asset_code ASC
    ")->fetchAll();
    $title = 'แคตตาล็อกครุภัณฑ์';
} else {
    $type = 'material';
    $rows = $pdo->query("
        SELECT material_code AS code, qr_code, name, image_path
        FROM materials
        ORDER BY material_code ASC
    ")->fetchAll();
    $title = 'แคตตาล็อกวัสดุสิ้นเปลือง';
}

// 6 cards (2 columns x 3 rows) per A4 page.
$pages = array_chunk($rows, 6);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: 'TH Sarabun New', 'Tahoma', sans-serif; margin: 20px; color: #000; }
        .toolbar { margin-bottom: 20px; }
        h2 { text-align: center; margin-bottom: 2px; }
        .subtitle { text-align: center; color: #555; margin-top: 0; margin-bottom: 24px; }
        .page {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(3, 1fr);
            gap: 8mm;
            page-break-after: always;
        }
        .page:last-child { page-break-after: auto; }
        .card {
            border: 1px solid #999;
            border-radius: 8px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 16px;
            page-break-inside: avoid;
        }
        .card .photo {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border: 1px solid #ccc;
            border-radius: 6px;
            flex-shrink: 0;
        }
        .card .photo-placeholder {
            width: 110px;
            height: 110px;
            border: 1px dashed #ccc;
            border-radius: 6px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bbb;
            font-size: 13px;
        }
        .card .qr {
            width: 100px;
            height: 100px;
            flex-shrink: 0;
        }
        .card .info { flex: 1; min-width: 0; }
        .card .code { font-size: 16px; color: #555; }
        .card .name { font-weight: bold; font-size: 24px; margin-top: 4px; line-height: 1.25; }
        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">พิมพ์แคตตาล็อก</button>
    <span>ทั้งหมด <?= count($rows) ?> รายการ</span>
</div>

<h2><?= htmlspecialchars($title) ?></h2>
<p class="subtitle">สำนักงานสถิติจังหวัดขอนแก่น — พิมพ์เมื่อ <?= date('d/m/') . ((int) date('Y') + 543) ?></p>

<?php foreach ($pages as $items): ?>
    <div class="page">
        <?php foreach ($items as $item): ?>
            <div class="card">
                <?php if ($item['image_path']): ?>
                    <img class="photo" src="<?= BASE_URL ?><?= htmlspecialchars($item['image_path']) ?>" alt="">
                <?php else: ?>
                    <div class="photo-placeholder">ไม่มีรูป</div>
                <?php endif; ?>
                <img class="qr" src="qr_image.php?data=<?= urlencode(buildQrScanUrl($item['qr_code'])) ?>" alt="QR">
                <div class="info">
                    <div class="code"><?= htmlspecialchars($item['code']) ?></div>
                    <div class="name"><?= htmlspecialchars($item['name']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php if (!$rows): ?>
    <p class="text-muted">ยังไม่มีข้อมูล</p>
<?php endif; ?>

</body>
</html>
