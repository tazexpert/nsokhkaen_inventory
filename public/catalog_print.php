<?php
// Printable catalog of materials or assets, grouped by category, each item
// showing its photo and a scannable QR code - for printing and keeping as
// a physical reference binder/board.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDbConnection();
$type = $_GET['type'] ?? 'material';

if ($type === 'asset') {
    $rows = $pdo->query("
        SELECT a.asset_code AS code, a.qr_code, a.name, a.image_path, a.brand_model, a.status, a.storage_location,
               c.name AS category_name
        FROM assets a
        LEFT JOIN categories c ON c.id = a.category_id
        ORDER BY (c.name IS NULL), c.name ASC, a.name ASC
    ")->fetchAll();
    $title = 'แคตตาล็อกครุภัณฑ์';
    $statusLabel = ['available' => 'พร้อมใช้งาน', 'borrowed' => 'ถูกยืมอยู่', 'maintenance' => 'ซ่อมบำรุง', 'disposed' => 'จำหน่ายแล้ว'];
} else {
    $type = 'material';
    $rows = $pdo->query("
        SELECT m.material_code AS code, m.qr_code, m.name, m.image_path, m.unit, m.unit_cost, m.stock_qty, m.storage_location,
               c.name AS category_name
        FROM materials m
        LEFT JOIN categories c ON c.id = m.category_id
        ORDER BY (c.name IS NULL), c.name ASC, m.name ASC
    ")->fetchAll();
    $title = 'แคตตาล็อกวัสดุสิ้นเปลือง';
}

// Group rows under their category name (NULL -> "ไม่ระบุหมวดหมู่") while
// preserving the SQL sort order.
$groups = [];
foreach ($rows as $row) {
    $cat = $row['category_name'] ?? 'ไม่ระบุหมวดหมู่';
    $groups[$cat][] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { font-family: 'TH Sarabun New', 'Tahoma', sans-serif; margin: 20px; color: #000; }
        .toolbar { margin-bottom: 20px; }
        h2 { text-align: center; margin-bottom: 2px; }
        .subtitle { text-align: center; color: #555; margin-top: 0; margin-bottom: 24px; }
        .cat-heading {
            background: #f0f0f0;
            border-left: 4px solid #333;
            padding: 6px 10px;
            margin-top: 24px;
            margin-bottom: 12px;
            font-size: 18px;
        }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .card {
            border: 1px solid #999;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            gap: 10px;
            page-break-inside: avoid;
        }
        .card .photo {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border: 1px solid #ccc;
            border-radius: 4px;
            flex-shrink: 0;
        }
        .card .photo-placeholder {
            width: 70px;
            height: 70px;
            border: 1px dashed #ccc;
            border-radius: 4px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bbb;
            font-size: 11px;
        }
        .card .qr {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
        }
        .card .info { flex: 1; min-width: 0; }
        .card .code { font-size: 11px; color: #666; }
        .card .name { font-weight: bold; font-size: 14px; margin: 2px 0; }
        .card .detail { font-size: 12px; color: #333; }
        @media print {
            .toolbar { display: none; }
            .cat-heading { break-after: avoid; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">พิมพ์แคตตาล็อก</button>
    <span>ทั้งหมด <?= count($rows) ?> รายการ ใน <?= count($groups) ?> หมวดหมู่</span>
</div>

<h2><?= htmlspecialchars($title) ?></h2>
<p class="subtitle">สำนักงานสถิติจังหวัดขอนแก่น — พิมพ์เมื่อ <?= date('d/m/') . ((int) date('Y') + 543) ?></p>

<?php foreach ($groups as $categoryName => $items): ?>
    <div class="cat-heading">หมวดหมู่ <?= htmlspecialchars($categoryName) ?> (<?= count($items) ?> รายการ)</div>
    <div class="grid">
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
                    <?php if ($type === 'material'): ?>
                        <div class="detail">ราคา <?= number_format((float) $item['unit_cost'], 2) ?> บาท/<?= htmlspecialchars($item['unit']) ?></div>
                        <div class="detail">คงเหลือ <?= (int) $item['stock_qty'] ?> <?= htmlspecialchars($item['unit']) ?></div>
                    <?php else: ?>
                        <div class="detail"><?= htmlspecialchars($item['brand_model'] ?: '-') ?></div>
                        <div class="detail">สถานะ: <?= htmlspecialchars($statusLabel[$item['status']] ?? $item['status']) ?></div>
                    <?php endif; ?>
                    <?php if ($item['storage_location']): ?>
                        <div class="detail">ที่จัดเก็บ: <?= htmlspecialchars($item['storage_location']) ?></div>
                    <?php endif; ?>
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
