<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Identifies whoever is about to use the scan page with just their 6-digit
// PIN - no username/password needed. A correct PIN logs them in exactly
// like index.php does (sets $_SESSION['user']), so they get full access
// everywhere their role allows for the rest of the visit, not just the
// scan page - including page loads triggered by scanning another sticker
// with the phone's own camera/QR app.
if (isset($_SESSION['user'])) {
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
            $_SESSION['user'] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'full_name' => $row['full_name'],
                'role' => $row['role'],
                'has_pin' => true,
                // Only used to pick the "(ยืนยันด้วย PIN)" badge and the
                // "เปลี่ยนผู้ใช้" logout label - never for access control.
                'via_pin' => true,
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>กรอก PIN - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --accent: #2f8fe0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #fafbfc;
            font-family: "Segoe UI", "Sarabun", Tahoma, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pin-screen {
            width: 100%;
            max-width: 360px;
            padding: 32px 24px 24px;
            text-align: center;
        }
        .pin-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        .pin-subtitle {
            font-size: 0.8rem;
            color: #9aa1a9;
            margin-bottom: 28px;
        }
        .pin-dots {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin-bottom: 28px;
        }
        .pin-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            background: transparent;
            transition: background 0.15s ease;
        }
        .pin-dot.filled {
            background: var(--accent);
        }
        .pin-dots.shake {
            animation: shake 0.4s;
        }
        @keyframes shake {
            10%, 90% { transform: translateX(-2px); }
            20%, 80% { transform: translateX(4px); }
            30%, 50%, 70% { transform: translateX(-8px); }
            40%, 60% { transform: translateX(8px); }
        }
        .pin-hint {
            font-size: 0.78rem;
            color: var(--accent);
            margin-bottom: 20px;
            min-height: 18px;
        }
        .pin-error {
            font-size: 0.78rem;
            color: #e0432f;
            margin-bottom: 20px;
            min-height: 18px;
        }
        .pin-keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            max-width: 280px;
            margin: 0 auto;
        }
        .pin-key {
            border: none;
            background: transparent;
            color: var(--accent);
            font-size: 1.6rem;
            font-weight: 500;
            padding: 16px 0;
            border-radius: 50%;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
        .pin-key:active {
            background: rgba(47, 143, 224, 0.12);
        }
        .pin-key.empty {
            cursor: default;
            pointer-events: none;
        }
        .pin-key.backspace {
            font-size: 1.3rem;
        }
        .pin-footer {
            margin-top: 28px;
            font-size: 0.78rem;
            color: #9aa1a9;
            line-height: 1.6;
        }
        .pin-footer a {
            color: var(--accent);
            text-decoration: none;
        }
        .pin-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="pin-screen">
    <div class="pin-title">สแกน QR Code เพื่อเบิกวัสดุ</div>
    <div class="pin-subtitle">สำนักงานสถิติจังหวัดขอนแก่น</div>

    <div class="pin-dots" id="pinDots">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="pin-dot"></div>
        <?php endfor; ?>
    </div>

    <?php if ($error): ?>
        <div class="pin-error" id="pinMessage"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
        <div class="pin-hint" id="pinMessage"><i class="bi bi-shield-lock"></i> ใส่รหัส PIN ที่ตั้งไว้</div>
    <?php endif; ?>

    <div class="pin-keypad" id="pinKeypad">
        <button type="button" class="pin-key" data-digit="1">1</button>
        <button type="button" class="pin-key" data-digit="2">2</button>
        <button type="button" class="pin-key" data-digit="3">3</button>
        <button type="button" class="pin-key" data-digit="4">4</button>
        <button type="button" class="pin-key" data-digit="5">5</button>
        <button type="button" class="pin-key" data-digit="6">6</button>
        <button type="button" class="pin-key" data-digit="7">7</button>
        <button type="button" class="pin-key" data-digit="8">8</button>
        <button type="button" class="pin-key" data-digit="9">9</button>
        <button type="button" class="pin-key empty"></button>
        <button type="button" class="pin-key" data-digit="0">0</button>
        <button type="button" class="pin-key backspace" id="pinBackspace"><i class="bi bi-backspace"></i></button>
    </div>

    <form method="post" id="pinForm" style="display:none;">
        <input type="hidden" name="pin" id="pinValue">
    </form>

    <div class="pin-footer">
        ยังไม่มี PIN? ตั้งได้ที่เมนู "ตั้งค่าระบบ" (สำหรับผู้ดูแลระบบ)<br>
        เป็นเจ้าหน้าที่และต้องการ <a href="index.php">เข้าสู่ระบบด้วยชื่อผู้ใช้/รหัสผ่าน</a> แทน
    </div>
</div>

<script>
    let pin = '';
    const dots = document.querySelectorAll('.pin-dot');
    const dotsWrap = document.getElementById('pinDots');
    const keypad = document.getElementById('pinKeypad');
    const form = document.getElementById('pinForm');
    const pinValue = document.getElementById('pinValue');
    const hadError = <?= $error ? 'true' : 'false' ?>;

    function renderDots() {
        dots.forEach((dot, i) => dot.classList.toggle('filled', i < pin.length));
    }

    function submitPin() {
        pinValue.value = pin;
        form.submit();
    }

    function addDigit(d) {
        if (pin.length >= 6) return;
        pin += d;
        renderDots();
        if (pin.length === 6) {
            setTimeout(submitPin, 150);
        }
    }

    function removeDigit() {
        pin = pin.slice(0, -1);
        renderDots();
    }

    keypad.addEventListener('click', (e) => {
        const key = e.target.closest('.pin-key');
        if (!key) return;
        if (key.id === 'pinBackspace') {
            removeDigit();
        } else if (key.dataset.digit !== undefined) {
            addDigit(key.dataset.digit);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key >= '0' && e.key <= '9') addDigit(e.key);
        else if (e.key === 'Backspace') removeDigit();
    });

    if (hadError) {
        dotsWrap.classList.add('shake');
        setTimeout(() => dotsWrap.classList.remove('shake'), 400);
    }
</script>
</body>
</html>
