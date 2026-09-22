<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminApi();
$pdo = getDbConnection();
$action = $_REQUEST['action'] ?? 'summary';

$keyword = sanitizeString($_GET['keyword'] ?? '');
$startDate = sanitizeString($_GET['start_date'] ?? '');
$endDate = sanitizeString($_GET['end_date'] ?? '');

$data = buildReportData($pdo, $keyword, $startDate, $endDate);

if ($action === 'export') {
    exportReportExcel($data);
} else {
    jsonResponse(['success' => true, 'data' => $data]);
}

function buildReportData(PDO $pdo, string $keyword, string $startDate, string $endDate): array
{
    $dateCondition = '';
    $params = [];
    if ($startDate !== '') {
        $dateCondition .= ' AND mt.created_at >= :start_date';
        $params['start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate !== '') {
        $dateCondition .= ' AND mt.created_at <= :end_date';
        $params['end_date'] = $endDate . ' 23:59:59';
    }

    $sql = "SELECT m.id, m.name, m.stock_qty,
                COALESCE(SUM(CASE WHEN mt.transaction_type = 'withdraw' THEN mt.quantity ELSE 0 END), 0) AS tx_quantity
            FROM materials m
            LEFT JOIN material_transactions mt ON mt.material_id = m.id $dateCondition
            WHERE 1=1";
    if ($keyword !== '') {
        $sql .= ' AND m.name LIKE :keyword';
        $params['keyword'] = "%$keyword%";
    }
    $sql .= ' GROUP BY m.id, m.name, m.stock_qty ORDER BY m.name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();

    $result = [];
    foreach ($materials as $m) {
        $result[] = [
            'item_type' => 'material',
            'name' => $m['name'],
            'tx_quantity' => (int) $m['tx_quantity'],
            'balance' => $m['stock_qty'] . ' (คงเหลือ)',
        ];
    }

    $dateConditionAsset = '';
    $paramsAsset = [];
    if ($startDate !== '') {
        $dateConditionAsset .= ' AND at.created_at >= :start_date';
        $paramsAsset['start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate !== '') {
        $dateConditionAsset .= ' AND at.created_at <= :end_date';
        $paramsAsset['end_date'] = $endDate . ' 23:59:59';
    }

    $statusLabel = ['available' => 'พร้อมใช้งาน', 'borrowed' => 'ถูกยืมอยู่', 'maintenance' => 'ซ่อมบำรุง', 'disposed' => 'จำหน่ายแล้ว'];

    $sqlAsset = "SELECT a.id, a.name, a.status,
                COALESCE(SUM(CASE WHEN at.action = 'borrow' THEN 1 ELSE 0 END), 0) AS tx_quantity
            FROM assets a
            LEFT JOIN asset_transactions at ON at.asset_id = a.id $dateConditionAsset
            WHERE 1=1";
    if ($keyword !== '') {
        $sqlAsset .= ' AND a.name LIKE :keyword';
        $paramsAsset['keyword'] = "%$keyword%";
    }
    $sqlAsset .= ' GROUP BY a.id, a.name, a.status ORDER BY a.name';

    $stmt = $pdo->prepare($sqlAsset);
    $stmt->execute($paramsAsset);
    $assets = $stmt->fetchAll();

    foreach ($assets as $a) {
        $result[] = [
            'item_type' => 'asset',
            'name' => $a['name'],
            'tx_quantity' => (int) $a['tx_quantity'],
            'balance' => $statusLabel[$a['status']] ?? $a['status'],
        ];
    }

    return $result;
}

function exportReportExcel(array $data): void
{
    $rows = [['ประเภท', 'ชื่อรายการ', 'จำนวนที่เบิก/ยืมในช่วงเวลา', 'ยอดคงเหลือ/สถานะปัจจุบัน']];

    foreach ($data as $r) {
        $rows[] = [
            $r['item_type'] === 'material' ? 'วัสดุสิ้นเปลือง' : 'ครุภัณฑ์',
            $r['name'],
            $r['tx_quantity'],
            $r['balance'],
        ];
    }

    \Shuchkin\SimpleXLSXGen::fromArray($rows, 'รายงาน')
        ->downloadAs('report_' . date('Ymd_His') . '.xlsx');
    exit;
}
