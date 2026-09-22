<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h3 class="mb-4">สแกน QR Code เพื่อเบิก/ยืม/คืน</h3>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body text-center">
                <div id="reader" style="width: 100%;"></div>
                <button id="btnToggleScan" class="btn btn-primary mt-3">
                    <i class="bi bi-camera"></i> เปิดกล้องสแกน
                </button>
                <hr>
                <div class="input-group">
                    <input type="text" id="manualQr" class="form-control" placeholder="หรือกรอกรหัส QR ด้วยตนเอง">
                    <button class="btn btn-outline-secondary" id="btnManualLookup">ค้นหา</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div id="resultCard" class="card d-none">
            <div class="card-header">รายละเอียดรายการที่สแกน</div>
            <div class="card-body" id="resultBody"></div>
        </div>
        <div id="alertBox"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const API_URL = '<?= BASE_URL ?>api/scan_api.php';
const QR_FROM_LINK = <?= json_encode($_GET['qr'] ?? '') ?>;
let html5QrCode = null;
let scanning = false;

function showAlert(message, type = 'danger') {
    $('#alertBox').html(`<div class="alert alert-${type} mt-3">${message}</div>`);
}

function stopScanner() {
    if (html5QrCode && scanning) {
        html5QrCode.stop().then(() => {
            scanning = false;
            $('#btnToggleScan').html('<i class="bi bi-camera"></i> เปิดกล้องสแกน');
        }).catch(() => {});
    }
}

$('#btnToggleScan').on('click', function () {
    if (scanning) {
        stopScanner();
        return;
    }

    html5QrCode = html5QrCode || new Html5Qrcode('reader');
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            stopScanner();
            lookupQr(decodedText);
        },
        () => {}
    ).then(() => {
        scanning = true;
        $('#btnToggleScan').html('<i class="bi bi-stop-circle"></i> หยุดสแกน');
    }).catch((err) => {
        showAlert('ไม่สามารถเปิดกล้องได้: ' + err);
    });
});

$('#btnManualLookup').on('click', function () {
    const qr = $('#manualQr').val().trim();
    if (qr) lookupQr(qr);
});

function lookupQr(qrCode) {
    $('#alertBox').empty();
    $.get(API_URL, { action: 'lookup', qr_code: qrCode })
        .done((res) => renderResult(res))
        .fail((xhr) => showAlert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function renderResult(res) {
    if (!res.success) {
        showAlert(res.message);
        $('#resultCard').addClass('d-none');
        return;
    }

    $('#resultCard').removeClass('d-none');

    if (res.item_type === 'material') {
        renderMaterial(res.data);
    } else {
        renderAsset(res.data);
    }
}

function renderMaterial(m) {
    $('#resultBody').html(`
        <span class="badge bg-info mb-2">วัสดุสิ้นเปลือง</span>
        <h5>${m.name}</h5>
        <p class="mb-1">รหัส: ${m.material_code}</p>
        <p class="mb-1">คงเหลือ: <strong>${m.stock_qty}</strong> ${m.unit}</p>
        <div class="mb-3">
            <label class="form-label">จำนวนที่ต้องการเบิก</label>
            <input type="number" min="1" max="${m.stock_qty}" class="form-control" id="withdrawQty" value="1">
        </div>
        <div class="mb-3">
            <label class="form-label">หมายเหตุ</label>
            <input type="text" class="form-control" id="withdrawNote">
        </div>
        <button class="btn btn-primary" onclick="doWithdraw(${m.id})">บันทึกการเบิก</button>
    `);
}

function doWithdraw(materialId) {
    const quantity = $('#withdrawQty').val();
    const note = $('#withdrawNote').val();
    $.post(API_URL, { action: 'withdraw', material_id: materialId, quantity, note })
        .done((res) => {
            showAlert(res.message, 'success');
            $('#resultCard').addClass('d-none');
        })
        .fail((xhr) => showAlert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function renderAsset(a) {
    const statusMap = { available: 'พร้อมใช้งาน', borrowed: 'ถูกยืมอยู่', maintenance: 'ซ่อมบำรุง', disposed: 'จำหน่ายแล้ว' };
    let actionHtml = '';

    if (a.status === 'available') {
        actionHtml = `
            <div class="mb-3">
                <label class="form-label">ชื่อผู้ยืม</label>
                <input type="text" class="form-control" id="borrowerName">
            </div>
            <div class="mb-3">
                <label class="form-label">หมายเหตุ</label>
                <input type="text" class="form-control" id="borrowNote">
            </div>
            <button class="btn btn-warning" onclick="doBorrow(${a.id})">บันทึกการยืม</button>
        `;
    } else if (a.status === 'borrowed') {
        actionHtml = `
            <div class="mb-3">
                <label class="form-label">หมายเหตุการคืน</label>
                <input type="text" class="form-control" id="returnNote">
            </div>
            <button class="btn btn-success" onclick="doReturn(${a.id})">บันทึกการคืน</button>
        `;
    } else {
        actionHtml = `<div class="alert alert-secondary mb-0">ครุภัณฑ์รายการนี้ไม่สามารถยืม/คืนได้ในสถานะปัจจุบัน</div>`;
    }

    $('#resultBody').html(`
        <span class="badge bg-success mb-2">ครุภัณฑ์</span>
        <h5>${a.name}</h5>
        <p class="mb-1">รหัส: ${a.asset_code}</p>
        <p class="mb-3">สถานะ: <strong>${statusMap[a.status] || a.status}</strong></p>
        ${actionHtml}
    `);
}

function doBorrow(assetId) {
    const borrower_name = $('#borrowerName').val();
    const note = $('#borrowNote').val();
    $.post(API_URL, { action: 'borrow', asset_id: assetId, borrower_name, note })
        .done((res) => {
            showAlert(res.message, 'success');
            $('#resultCard').addClass('d-none');
        })
        .fail((xhr) => showAlert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

function doReturn(assetId) {
    const note = $('#returnNote').val();
    $.post(API_URL, { action: 'return', asset_id: assetId, note })
        .done((res) => {
            showAlert(res.message, 'success');
            $('#resultCard').addClass('d-none');
        })
        .fail((xhr) => showAlert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'));
}

// Opened directly from a printed QR code sticker (scan.php?qr=...) - look it up
// immediately instead of waiting for the camera or manual entry.
if (QR_FROM_LINK) {
    $('#manualQr').val(QR_FROM_LINK);
    $(document).ready(() => lookupQr(QR_FROM_LINK));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
