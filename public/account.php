<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT username, full_name, position, role, (pin_hash IS NOT NULL) AS has_pin FROM users WHERE id = :id');
$stmt->execute(['id' => $user['id']]);
$account = $stmt->fetch();

// Reached right after login (staff with no PIN yet - see public/index.php).
// If they already have a PIN (e.g. revisited this URL after setting one),
// treat it as a normal visit to the page instead.
$setupMode = ($_GET['setup_pin'] ?? '') === '1' && !$account['has_pin'];
$afterSetupRedirect = empty($_SESSION['redirect_after_login']) ? (BASE_URL . 'public/dashboard.php') : $_SESSION['redirect_after_login'];
?>

<h3 class="mb-4">บัญชีของฉัน</h3>

<?php if ($setupMode): ?>
<div class="alert alert-warning">
    <i class="bi bi-shield-exclamation"></i>
    กรุณาตั้ง <strong>PIN 6 หลัก</strong> ก่อนเริ่มใช้งาน เพื่อใช้ยืนยันตัวตนตอนเข้าหน้า "สแกน QR Code" โดยไม่ต้องกรอกชื่อผู้ใช้/รหัสผ่านทุกครั้ง
    <a href="<?= htmlspecialchars($afterSetupRedirect) ?>" class="alert-link ms-2">ข้ามไปก่อน (ตั้งทีหลังได้)</a>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">ข้อมูลบัญชี</div>
            <div class="card-body">
                <p class="mb-1"><strong>ชื่อ-นามสกุล:</strong> <?= htmlspecialchars($account['full_name']) ?></p>
                <p class="mb-1"><strong>ชื่อผู้ใช้:</strong> <?= htmlspecialchars($account['username'] ?? '-') ?></p>
                <p class="mb-0"><strong>สิทธิ์การใช้งาน:</strong> <?= $account['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'เจ้าหน้าที่' ?></p>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">เปลี่ยนรหัสผ่าน</div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านปัจจุบัน</label>
                        <input type="password" class="form-control" id="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="new_password" placeholder="อย่างน้อย 6 ตัวอักษร" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="confirm_password" autocomplete="new-password" required>
                    </div>
                    <div id="passwordResult"></div>
                    <button type="submit" class="btn btn-primary">บันทึกรหัสผ่านใหม่</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card" id="pinCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>PIN สำหรับหน้าสแกน QR Code</span>
                <span id="pinStatusBadge"></span>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    ใช้ PIN 6 หลักแทนการล็อกอินตอนเข้าหน้า "สแกน QR Code" เพื่อเบิกวัสดุ/ยืมครุภัณฑ์ - PIN ของแต่ละคนต้องไม่ซ้ำกัน
                    และถูกเก็บแบบ hash เหมือนรหัสผ่าน (กู้คืน PIN เดิมไม่ได้ ต้องตั้งใหม่หากลืม)
                </p>
                <form id="pinForm">
                    <div class="mb-3">
                        <label class="form-label" id="pin_label">PIN ใหม่ (6 หลัก)</label>
                        <input type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" class="form-control" id="pin" placeholder="เช่น 123456" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ยืนยัน PIN</label>
                        <input type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" class="form-control" id="confirm_pin" autocomplete="off" required>
                    </div>
                    <div id="pinResult"></div>
                    <button type="submit" class="btn btn-primary">บันทึก PIN</button>
                    <button type="button" class="btn btn-outline-danger" id="btnRemovePin" style="display:none;">ลบ PIN</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const ACCOUNT_API = '<?= BASE_URL ?>api/account_api.php';
const AFTER_SETUP_REDIRECT = <?= json_encode($afterSetupRedirect) ?>;
const SETUP_MODE = <?= $setupMode ? 'true' : 'false' ?>;
let hasPin = <?= $account['has_pin'] ? 'true' : 'false' ?>;

function renderPinStatus() {
    $('#pinStatusBadge').html(hasPin
        ? '<span class="badge bg-success">ตั้งแล้ว</span>'
        : '<span class="badge bg-secondary">ยังไม่ได้ตั้ง</span>');
    $('#pin_label').text(hasPin ? 'เปลี่ยน PIN เป็น (6 หลัก)' : 'PIN ใหม่ (6 หลัก)');
    $('#btnRemovePin').toggle(hasPin);
}
renderPinStatus();

$('#passwordForm').on('submit', function (e) {
    e.preventDefault();
    const payload = {
        action: 'change_password',
        current_password: $('#current_password').val(),
        new_password: $('#new_password').val(),
        confirm_password: $('#confirm_password').val(),
    };
    $.post(ACCOUNT_API, payload)
        .done((res) => {
            $('#passwordResult').html(`<div class="alert alert-success mt-2 mb-3">${res.message}</div>`);
            $('#passwordForm')[0].reset();
        })
        .fail((xhr) => {
            $('#passwordResult').html(`<div class="alert alert-danger mt-2 mb-3">${xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'}</div>`);
        });
});

$('#pinForm').on('submit', function (e) {
    e.preventDefault();
    const payload = {
        action: 'set_pin',
        pin: $('#pin').val(),
        confirm_pin: $('#confirm_pin').val(),
    };
    $.post(ACCOUNT_API, payload)
        .done((res) => {
            hasPin = true;
            renderPinStatus();
            $('#pinForm')[0].reset();
            if (SETUP_MODE) {
                $('#pinResult').html(`<div class="alert alert-success mt-2 mb-3">${res.message} กำลังพาไปหน้าถัดไป...</div>`);
                setTimeout(() => { window.location.href = AFTER_SETUP_REDIRECT; }, 1000);
            } else {
                $('#pinResult').html(`<div class="alert alert-success mt-2 mb-3">${res.message}</div>`);
            }
        })
        .fail((xhr) => {
            $('#pinResult').html(`<div class="alert alert-danger mt-2 mb-3">${xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'}</div>`);
        });
});

$('#btnRemovePin').on('click', function () {
    if (!confirm('ยืนยันการลบ PIN? จะไม่สามารถใช้ PIN นี้เข้าหน้าสแกน QR Code ได้อีก')) return;
    $.post(ACCOUNT_API, { action: 'remove_pin' })
        .done((res) => {
            hasPin = false;
            renderPinStatus();
            $('#pinResult').html(`<div class="alert alert-success mt-2 mb-3">${res.message}</div>`);
        })
        .fail((xhr) => {
            $('#pinResult').html(`<div class="alert alert-danger mt-2 mb-3">${xhr.responseJSON?.message || 'เกิดข้อผิดพลาด'}</div>`);
        });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
