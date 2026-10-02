<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Identifies whoever is about to use the scan page with just their 6-digit
// PIN - no username/password needed. Once set, $_SESSION['scan_pin_user']
// (or a full $_SESSION['user'] login) is remembered for the rest of the
// visit, so repeated scans (including ones opened fresh by the phone's own
// camera/QR app) never ask for the PIN again.
if (isset($_SESSION['user']) || isset($_SESSION['scan_pin_user'])) {
    $redirect = empty($_SESSION['redirect_after_login']) ? 'scan.php' : $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');

    if (!preg_match('/^\d{6}$/', $pin)) {
        $error = 'กรุณากรอก PIN 6 หลัก';
    } else {
        $pdo = getDbConnection();
        $row = findUserByPin($pdo, $pin);

        if ($row) {
            $_SESSION['scan_pin_user'] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'full_name' => $row['full_name'],
                'position' => $row['position'],
                'role' => $row['role'],
            ];
            $redirect = empty($_SESSION['redirect_after_login']) ? 'scan.php' : $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }

        $error = 'PIN ไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กรอก PIN - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4">
            <h4 class="text-center mb-1">สแกน QR Code เพื่อเบิก/ยืม/คืน</h4>
            <p class="text-center text-muted mb-4">กรอก PIN 6 หลักของตัวเองเพื่อยืนยันตัวตนก่อนทำรายการ</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">PIN (6 หลัก)</label>
                    <input type="password" name="pin" inputmode="numeric" pattern="\d{6}" maxlength="6"
                        class="form-control form-control-lg text-center" required autofocus autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary w-100">ยืนยัน</button>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                ยังไม่มี PIN? ตั้งได้ที่เมนู "ตั้งค่าระบบ" (สำหรับผู้ดูแลระบบ)<br>
                เป็นเจ้าหน้าที่และต้องการ <a href="index.php">เข้าสู่ระบบด้วยชื่อผู้ใช้/รหัสผ่าน</a> แทน
            </p>
        </div>
    </div>
</div>
</body>
</html>
