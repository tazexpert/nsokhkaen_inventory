<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h3 class="mb-0">รายงานการเบิกวัสดุ</h3>
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์รายงาน</button>
</div>

<div class="card mb-4 no-print">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">วันที่เริ่มต้น</label>
                <?php renderThaiDateSelect('f_start') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">วันที่สิ้นสุด</label>
                <?php renderThaiDateSelect('f_end') ?>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" onclick="loadReport()">ค้นหา</button>
            </div>
        </div>
    </div>
</div>

<div id="reportSheet">
    <div class="text-center mb-4 d-none d-print-block">
        <h4 class="mb-1">รายงานการเบิกวัสดุ</h4>
        <div class="small text-muted">สำนักงานสถิติจังหวัดขอนแก่น</div>
        <div class="small text-muted" id="reportRangeText"></div>
    </div>

    <div class="card mb-4 report-section">
        <div class="card-header">1. กราฟจำนวนการเบิกวัสดุ</div>
        <div class="card-body">
            <div class="chart-bars" id="chartBars"></div>
            <p class="text-center text-muted small mb-0" id="chartEmpty" style="display:none;">ไม่มีข้อมูลการเบิกในช่วงเวลาที่เลือก</p>
        </div>
    </div>

    <div class="card mb-4 report-section">
        <div class="card-header">2. มูลค่าวัสดุที่เบิก</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" id="valueTable">
                <thead>
                <tr><th style="width:50px;">ลำดับ</th><th>รายการ</th><th class="text-end">จำนวน</th><th class="text-end">ราคา</th><th class="text-end">มูลค่าสินค้า</th></tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end">มูลค่ารวมทั้งหมด</td>
                    <td class="text-end" id="totalValueCell">0.00</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card report-section">
        <div class="card-header">3. วัสดุที่ควรจัดหา (ใกล้หมดสต๊อก)</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" id="lowStockTable">
                <thead>
                <tr><th style="width:50px;">ลำดับ</th><th>รายการ</th><th class="text-end">คงเหลือ</th><th class="text-end">จุดสั่งซื้อขั้นต่ำ</th><th>หน่วย</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .chart-bars {
        display: flex;
        align-items: flex-end;
        gap: 6px;
        height: 220px;
        box-sizing: border-box;
        padding: 24px 8px 0; /* top padding reserves room for the tallest bar's value label */
        margin-bottom: 70px;
        border-left: 1px solid #999;
        border-bottom: 1px solid #999;
    }
    .bar-col {
        flex: 1 1 0;
        min-width: 4px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
        position: relative;
    }
    .bar {
        width: 100%;
        max-width: 24px;
        background: #2a78d6;
        border-radius: 4px 4px 0 0;
        position: relative;
        /* Chrome drops background-color by default when printing unless
           told otherwise - without this the bars (plain color fills, no
           border) render as blank space on the printed page. */
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .bar-value {
        font-size: 11px;
        color: #333;
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        white-space: nowrap;
        margin-bottom: 2px;
    }
    .bar-label {
        position: absolute;
        top: 100%;
        left: 50%;
        font-size: 10px;
        color: #555;
        white-space: nowrap;
        max-width: 90px;
        overflow: hidden;
        text-overflow: ellipsis;
        transform: translateX(-100%) rotate(-45deg);
        transform-origin: top right;
        margin-top: 6px;
    }
    @media print {
        @page { size: A4 portrait; margin: 12mm; }
        nav, footer, .no-print { display: none !important; }
        .container-fluid { padding: 0 !important; }
        .report-section { page-break-inside: avoid; border: none !important; }
        .card-header { background: none !important; border-bottom: 1px solid #000 !important; font-weight: bold; }
        #valueTable, #lowStockTable { page-break-inside: auto; }
        #valueTable tr, #lowStockTable tr { page-break-inside: avoid; }
    }
</style>

<script>
const REPORT_API = BASE_URL_JS + 'api/report_api.php';

function getFilters() {
    return {
        start_date: thaiDateSelectGet('f_start'),
        end_date: thaiDateSelectGet('f_end'),
    };
}

function formatNumber(n) {
    return Number(n).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function loadReport() {
    const filters = getFilters();
    $.get(REPORT_API, { action: 'summary', ...filters }, function (res) {
        renderRangeText(filters);
        renderChart(res.data.items);
        renderValueTable(res.data.items, res.data.total_value);
        renderLowStock(res.data.low_stock);
    });
}

function isoToThaiDate(iso) {
    const [y, m, d] = iso.split('-').map((n) => parseInt(n, 10));
    return `${d} ${THAI_MONTHS[m - 1]} ${y + 543}`;
}

function renderRangeText(filters) {
    const start = filters.start_date || null;
    const end = filters.end_date || null;
    let text = 'ทุกช่วงเวลา';
    if (start && end) text = `ช่วงวันที่ ${isoToThaiDate(start)} ถึง ${isoToThaiDate(end)}`;
    else if (start) text = `ตั้งแต่วันที่ ${isoToThaiDate(start)}`;
    else if (end) text = `ถึงวันที่ ${isoToThaiDate(end)}`;
    $('#reportRangeText').text(text + ' — พิมพ์เมื่อ ' + isoToThaiDate(new Date().toISOString().slice(0, 10)));
}

function renderChart(items) {
    const container = $('#chartBars').empty();
    if (!items.length) {
        $('#chartEmpty').show();
        return;
    }
    $('#chartEmpty').hide();
    const chartHeight = 196; // chart-bars content height = 220px - 24px top padding
    const max = Math.max(...items.map((i) => i.qty));
    items.forEach((item) => {
        const px = max > 0 ? Math.round((item.qty / max) * chartHeight) : 0;
        const col = $(`
            <div class="bar-col" title="${item.name}: ${item.qty} ${item.unit}">
                <div class="bar" style="height:${px}px">
                    <div class="bar-value">${item.qty}</div>
                </div>
                <div class="bar-label">${item.name}</div>
            </div>
        `);
        container.append(col);
    });
}

function renderValueTable(items, totalValue) {
    const tbody = $('#valueTable tbody').empty();
    items.forEach((item, i) => {
        tbody.append(`
            <tr>
                <td>${i + 1}</td>
                <td>${item.name}</td>
                <td class="text-end">${item.qty} ${item.unit}</td>
                <td class="text-end">${formatNumber(item.unit_cost)}</td>
                <td class="text-end">${formatNumber(item.value)}</td>
            </tr>
        `);
    });
    if (!items.length) {
        tbody.append('<tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูล</td></tr>');
    }
    $('#totalValueCell').text(formatNumber(totalValue));
}

function renderLowStock(lowStock) {
    const tbody = $('#lowStockTable tbody').empty();
    lowStock.forEach((m, i) => {
        tbody.append(`
            <tr>
                <td>${i + 1}</td>
                <td>${m.name}</td>
                <td class="text-end text-danger fw-bold">${m.stock_qty}</td>
                <td class="text-end">${m.min_stock}</td>
                <td>${m.unit}</td>
            </tr>
        `);
    });
    if (!lowStock.length) {
        tbody.append('<tr><td colspan="5" class="text-center text-muted">ไม่มีวัสดุใกล้หมดสต๊อก</td></tr>');
    }
}

$(document).ready(loadReport);
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/th.js"></script>
<script src="<?= BASE_URL ?>assets/js/thai-date-select.js<?= assetVersion('assets/js/thai-date-select.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
