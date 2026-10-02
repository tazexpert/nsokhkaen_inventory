<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$id = (int) ($_GET['id'] ?? 0);

// ผู้เบิก (requester) on the printout is the logged-in account that actually
// submitted the slip (u.full_name/u.position), not the free-text
// requester_name/requester_position fields - those can be filled in for
// someone else (e.g. a staff member recording a request on a colleague's
// behalf) and aren't necessarily the person who should sign as ผู้เบิก here.
$stmt = $pdo->prepare('SELECT r.*, u.full_name AS creator_name, u.position AS creator_position
    FROM requisitions r JOIN users u ON u.id = r.created_by WHERE r.id = :id');
$stmt->execute(['id' => $id]);
$requisition = $stmt->fetch();

if (!$requisition) {
    http_response_code(404);
    die('ไม่พบใบเบิกที่ร้องขอ');
}

// ผู้จ่าย (issuer) and the approver signing under "อนุญาติให้เบิกได้" are
// normally the same one or two people on every slip, so they're configured
// once in "ตั้งค่าระบบ" instead of being typed by hand on each printout.
$settingsStmt = $pdo->query("SELECT `key`, `value` FROM settings
    WHERE `key` IN ('issuer_name', 'issuer_position', 'approver_name', 'approver_position')");
$signatorySettings = array_column($settingsStmt->fetchAll(), 'value', 'key');

$itemsStmt = $pdo->prepare('SELECT * FROM requisition_items WHERE requisition_id = :id ORDER BY id');
$itemsStmt->execute(['id' => $id]);
$items = $itemsStmt->fetchAll();

$minRows = 12;
$blankRows = max(0, $minRows - count($items));

// Keeps the paper-form look of a blank line to sign/write on when a value
// isn't set, instead of a label with nothing after it.
function blankIfEmpty(?string $value, int $dots = 66): string
{
    $value = trim($value ?? '');
    return $value !== '' ? htmlspecialchars($value) : str_repeat('.', $dots);
}

// The "ลงชื่อ..." line: the dots ARE the signature line, so when the name is
// known it replaces the dots directly instead of being printed separately
// above a second line.
function signatureLine(?string $name, int $dots = 43): string
{
    $name = trim($name ?? '');
    return $name !== '' ? htmlspecialchars($name) : str_repeat('.', $dots);
}

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
        body { font-family: 'TH Sarabun New', 'Tahoma', sans-serif; font-size: 16pt; line-height: 1.15; margin: 20px; color: #000; }
        .toolbar { margin-bottom: 15px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        h2 { text-align: center; margin: 0 0 2px; font-size: 18pt; }
        .subtitle { text-align: center; margin: 0 0 8px; }
        .intro { margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #000; padding: 2px 6px; line-height: 1.1; }
        th { background: #f0f0f0; }
        .col-no { width: 6%; text-align: center; }
        .col-unit { width: 10%; text-align: center; }
        .col-qty { width: 12%; text-align: center; }
        .col-note { width: 16%; }
        .sign-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .sign-block { text-align: center; }
        .approve-title { text-align: center; font-weight: bold; font-size: 18pt; margin-top: 12px; }
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

<p class="intro">ข้าพเจ้าขอเบิกวัสดุเพื่อใช้งาน<?= $requisition['purpose'] ? ' ' . htmlspecialchars($requisition['purpose']) . ' ' : '.............................................................................' ?>ตามรายการข้างล่างนี้</p>

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
        <div>ลงชื่อ <?= signatureLine($requisition['creator_name']) ?> ผู้เบิก</div>
        <div>ตำแหน่ง <?= blankIfEmpty($requisition['creator_position']) ?></div>
        <div>วันที่ <?= formatThaiDate($requisition['created_at']) ?></div>
    </div>
    <div class="sign-block">
        <div>ลงชื่อ <?= signatureLine($signatorySettings['issuer_name'] ?? null) ?> ผู้จ่ายวัสดุ</div>
        <div>ตำแหน่ง <?= blankIfEmpty($signatorySettings['issuer_position'] ?? null) ?></div>
        <div>วันที่ <?= formatThaiDate($requisition['created_at']) ?></div>
    </div>
</div>

<div class="sign-grid">
    <div>
        <div class="approve-title">อนุญาติให้เบิกได้</div>
        <div class="sign-block">
            <div>ลงชื่อ <?= signatureLine($signatorySettings['approver_name'] ?? null) ?></div>
            <div>ตำแหน่ง <?= blankIfEmpty($signatorySettings['approver_position'] ?? null) ?></div>
            <div>วันที่ <?= formatThaiDate($requisition['created_at']) ?></div>
        </div>
    </div>
    <div></div>
</div>

</body>
</html>
