<?php
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>

<h3 class="mb-4">รายงานการเบิก/ยืม-คืน</h3>

<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">ชื่อพัสดุ/รายการ</label>
                <input type="text" id="f_keyword" class="form-control" placeholder="ค้นหาชื่อรายการ">
            </div>
            <div class="col-md-3">
                <label class="form-label">วันที่เริ่มต้น</label>
                <input type="date" id="f_start" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">วันที่สิ้นสุด</label>
                <input type="date" id="f_end" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" onclick="loadReport()">ค้นหา</button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-success" onclick="exportReport()"><i class="bi bi-file-earmark-excel"></i> ส่งออก Excel</button>
</div>

<div class="table-responsive">
<table class="table table-striped" id="reportTable">
    <thead>
    <tr>
        <th>ประเภท</th><th>ชื่อรายการ</th><th>จำนวนที่เบิก/ยืมในช่วงเวลา</th><th>ยอดคงเหลือ/สถานะปัจจุบัน</th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
</div>

<script>
const REPORT_API = BASE_URL_JS + 'api/report_api.php';

function getFilters() {
    return {
        keyword: $('#f_keyword').val(),
        start_date: $('#f_start').val(),
        end_date: $('#f_end').val(),
    };
}

function loadReport() {
    $.get(REPORT_API, { action: 'summary', ...getFilters() }, function (res) {
        const tbody = $('#reportTable tbody').empty();
        res.data.forEach((r) => {
            tbody.append(`
                <tr>
                    <td>${r.item_type === 'material' ? 'วัสดุสิ้นเปลือง' : 'ครุภัณฑ์'}</td>
                    <td>${r.name}</td>
                    <td>${r.tx_quantity}</td>
                    <td>${r.balance}</td>
                </tr>
            `);
        });
        if (!res.data.length) {
            tbody.append('<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูล</td></tr>');
        }
    });
}

function exportReport() {
    const params = new URLSearchParams({ action: 'export', ...getFilters() });
    window.location = REPORT_API + '?' + params.toString();
}

$(document).ready(loadReport);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
