<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT r.*, u.full_name AS issued_by_name FROM requisitions r
    JOIN users u ON u.id = r.created_by WHERE r.id = :id');
$stmt->execute(['id' => $id]);
$requisition = $stmt->fetch();

if (!$requisition) {
    http_response_code(404);
    die('ไม่พบใบเบิกที่ร้องขอ');
}

$itemsStmt = $pdo->prepare('SELECT * FROM requisition_items WHERE requisition_id = :id ORDER BY id');
$itemsStmt->execute(['id' => $id]);
$items = $itemsStmt->fetchAll();

$minRows = 12;
$blankRows = max(0, $minRows - count($items));

function formatThaiDate(string $datetime): string
{
    $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $ts = strtotime($datetime);
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . ((int) date('Y', $ts) + 543);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเบิกวัสดุ เลขที่ <?= htmlspecialchars($requisition['requisition_no']) ?></title>
    <style>
        body { font-family: 'TH Sarabun New', 'Tahoma', sans-serif; font-size: 16px; margin: 30px; color: #000; }
        .toolbar { margin-bottom: 20px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        h2 { text-align: center; margin-bottom: 4px; }
        .subtitle { text-align: center; margin-top: 0; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #000; padding: 6px 8px; font-size: 15px; }
        th { background: #f0f0f0; }
        .col-no { width: 6%; text-align: center; }
        .col-unit { width: 10%; text-align: center; }
        .col-qty { width: 12%; text-align: center; }
        .col-note { width: 16%; }
        .sign-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 40px; }
        .sign-block { text-align: center; }
        .sign-line { margin: 40px 0 6px; border-bottom: 1px dotted #000; }
        .approve-title { text-align: center; font-weight: bold; margin-top: 40px; }
        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">พิมพ์ใบเบิก</button>
    <a href="requisitions.php">&larr; กลับไปหน้ารายการใบเบิก</a>
</div>

<div class="text-right">เลขที่ <?= htmlspecialchars($requisition['requisition_no']) ?></div>
<h2>ใบเบิกวัสดุ</h2>
<p class="subtitle">สำนักงานสถิติจังหวัดขอนแก่น</p>

<p>ข้าพเจ้าขอเบิกวัสดุเพื่อใช้งาน<?= $requisition['purpose'] ? ' ' . htmlspecialchars($requisition['purpose']) . ' ' : '.............................................................................' ?>ตามรายการข้างล่างนี้</p>

<table>
    <thead>
    <tr>
        <th class="col-no">ลำดับที่</th>
        <th>รายการสิ่งของ</th>
        <th class="col-unit">หน่วยนับ</th>
        <th class="col-qty">จำนวนเบิก</th>
        <th class="col-qty">จำนวนจ่าย</th>
        <th class="col-note">หมายเหตุ</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $i => $item): ?>
        <tr>
            <td class="col-no"><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($item['item_name']) ?><?= $item['item_type'] === 'asset' ? ' (ครุภัณฑ์)' : '' ?></td>
            <td class="col-unit"><?= htmlspecialchars($item['unit'] ?? '-') ?></td>
            <td class="col-qty"><?= (int) $item['quantity_requested'] ?></td>
            <td class="col-qty"><?= (int) $item['quantity_issued'] ?></td>
            <td class="col-note"><?= htmlspecialchars($item['note'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php for ($i = 0; $i < $blankRows; $i++): ?>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
    <?php endfor; ?>
    </tbody>
</table>

<div class="sign-grid">
    <div class="sign-block">
        <div class="sign-line"><?= htmlspecialchars($requisition['requester_name'] ?? '') ?></div>
        <div>ลงชื่อ...........................................ผู้เบิก</div>
        <div>ตำแหน่ง <?= htmlspecialchars($requisition['requester_position'] ?? '') ?></div>
        <div>วันที่ <?= formatThaiDate($requisition['created_at']) ?></div>
    </div>
    <div class="sign-block">
        <div class="sign-line"><?= htmlspecialchars($requisition['issued_by_name']) ?></div>
        <div>ลงชื่อ...........................................ผู้จ่าย</div>
        <div>ตำแหน่ง..................................................................................</div>
        <div>วันที่ <?= formatThaiDate($requisition['created_at']) ?></div>
    </div>
</div>

<div class="approve-title">อนุญาติให้เบิกได้</div>
<div class="sign-block">
    <div class="sign-line" style="width: 300px; margin-left: auto; margin-right: auto;"></div>
    <div>ลงชื่อ...........................................ผู้รับพัสดุ</div>
    <div>ตำแหน่ง..................................................................................</div>
    <div>วันที่..................................................................................</div>
</div>

</body>
</html>
