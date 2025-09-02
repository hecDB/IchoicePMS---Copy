<?php
require_once 'db_connect.php';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $name = trim($_POST['name']);

    if (!$email || !$name) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        // ตรวจสอบว่ามีผู้ใช้นี้หรือไม่
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email=? AND name=? LIMIT 1");
        $stmt->execute([$email, $name]);
        $user = $stmt->fetch();

        if ($user) {
            // ตรวจสอบว่ามี request ที่ยัง pending อยู่หรือไม่
            $check_req = $pdo->prepare("SELECT id FROM password_reset_requests WHERE user_email=? AND user_name=? AND status='pending' LIMIT 1");
            $check_req->execute([$email, $name]);
            if ($check_req->fetch()) {
                $error = "คุณได้ส่งคำขอรีเซ็ตรหัสผ่านแล้ว โปรดรอผู้ดูแลระบบติดต่อกลับ";
            } else {
                // บันทึกคำขอรีเซ็ตรหัสผ่านใหม่
                $ins = $pdo->prepare("INSERT INTO password_reset_requests (user_email, user_name) VALUES (?, ?)");
                $ins->execute([$email, $name]);
                $success = "ส่งคำขอรีเซ็ตรหัสผ่านเรียบร้อยแล้ว กรุณารอผู้ดูแลระบบติดต่อกลับ";
            }
        } else {
            $error = "ข้อมูลไม่ตรงกับผู้ใช้งานในระบบ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">

    <style>
        body { background: #eef4fd; font-family: 'Sarabun', sans-serif;}
        .container {
            background: #fff;
            max-width: 420px;
            margin: 70px auto;
            border-radius: 15px;
            box-shadow: 0 2px 14px #cadbf573;
            padding: 38px 38px 28px 38px;
            text-align: center;
        }
        .title { font-size: 32px; font-weight: bold; color: #193568; margin-bottom: 7px;}
        .desc { color: #929fae; font-size: 17px; margin-bottom: 17px;}
        .icon-key {
            display: inline-block;
            margin: 18px auto 12px auto;
        }
        .section-title {
            font-size: 21px;
            color: #1f283d;
            font-weight: bold;
            margin-bottom: 7px;
        }
        .section-desc {
            color: #4769a8;
            font-size: 15px;
            margin-bottom: 21px;
        }
        .form-group { margin-bottom: 17px; text-align: left;}
        label { display: block; margin-bottom: 8px; color: #324f77; font-weight: bold;}
        input[type="text"], input[type="email"] {
            width: 100%; padding: 11px 15px; border: none; border-radius: 7px;
            background: #e8f0fd; font-size: 16px; color: #334678; outline: none; margin-bottom: 2px;
            box-sizing: border-box;
        }
        .btn-action {
            width: 100%; background: #1675fb; color: #fff; border: none; border-radius: 7px;
            padding: 13px 0; font-size: 18px; font-weight: bold; margin-top: 10px; cursor: pointer; transition: background .18s;
        }
        .btn-action:hover { background: #0856cd;}
        .btn-back {
            width: 100%; background: #f5f8ff; color: #3666ad; border: none; border-radius: 7px;
            padding: 11px 0; font-size: 16px; margin-top: 16px; cursor: pointer; transition: background .18s;
        }
        .btn-back:hover { background: #e1ebff;}
        .success-msg {
            color: #098d3e; background: #e5ffe8; border-radius: 8px; padding: 11px 0; margin-bottom: 18px;
        }
        .error-msg {
            color: #d20000; background: #ffeaea; border-radius: 8px; padding: 11px 0; margin-bottom: 18px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="title">IchoicePMS</div>
    <div class="desc">ระบบจัดการสินค้าด้านคลังสินค้า</div>

    <span class="icon-key">
        <svg width="60" height="60" viewBox="0 0 55 55" fill="none"><circle cx="27.5" cy="27.5" r="27.5" fill="#f1f6ff"/><path d="M27.124 43c3.648 0 8.624-6.02 8.624-11.076 0-3.38-2.599-6.124-5.96-6.124-1.384 0-2.644.46-3.664 1.236A4.742 4.742 0 0 0 25.34 27.37c-.164.184-.32.376-.474.576a6.102 6.102 0 0 0-1.307 2.235A5.014 5.014 0 0 0 23 31.924C23 36.98 23.537 43 27.124 43Z" stroke="#1675fb" stroke-width="2"/><circle cx="35" cy="36" r="2" fill="#1675fb"/></svg>
    </span>
    <div class="section-title">ขอรีเซ็ตรหัสผ่าน</div>
    <div class="section-desc">กรอกข้อมูลเพื่อส่งคำขอรีเซ็ตรหัสผ่านไปยังผู้ดูแลระบบ</div>

    <?php if ($success): ?>
        <div class="success-msg"><?=htmlspecialchars($success)?></div>
    <?php elseif ($error): ?>
        <div class="error-msg"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="form-group">
            <label for="email">อีเมล</label>
            <input id="email" name="email" type="email" placeholder="กรอกอีเมลของคุณ" required>
        </div>
        <div class="form-group">
            <label for="name">ชื่อ-นามสกุล</label>
            <input id="name" name="name" type="text" placeholder="กรอกชื่อ-นามสกุลของคุณ" required>
        </div>
        <button type="submit" class="btn-action">ส่งคำขอรีเซ็ตรหัสผ่าน</button>
    </form>
    <form action="combined_login_register.php" method="get">
        <button type="submit" class="btn-back">กลับไปหน้าเข้าสู่ระบบ</button>
    </form>
</div>
</body>
</html>