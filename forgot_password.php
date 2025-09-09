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
    <link rel="stylesheet" href="assets/style.css">
    <title>ลืมรหัสผ่าน</title>
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