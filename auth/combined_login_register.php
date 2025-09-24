<?php
session_start();
require_once '../config/db_connect.php';

$tab = $_POST['form_type'] ?? 'login';
$error = '';
$success = '';

// ---- LOGIN FLOW ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['form_type'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $tab = "login";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_approved = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_department'] = $user['department'];
            header("Location: ../dashboard.php");
            exit;
        } else {
            $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือบัญชีนี้ยังไม่ได้รับอนุมัติ";
            $tab = "login";
        }
    }
}

// ---- REGISTER FLOW ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['form_type'] === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if (!$name || !$email || !$password || !$department) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $tab = "register";
    } else {
        // ตรวจสอบซ้ำอีเมล
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "อีเมลนี้ถูกใช้งานแล้ว";
            $tab = "register";
        } else {
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, department, role, is_approved) VALUES (?, ?, ?, ?, ?, 0)");
            if ($stmt->execute([$name, $email, $hashedPass, $department, "staff"])) {
                $success = "สมัครสมาชิกสำเร็จ กรุณารอผู้ดูแลระบบอนุมัติก่อนใช้งาน";
                $tab = "login";
            } else {
                $error = "เกิดข้อผิดพลาดระหว่างสมัครสมาชิก";
                $tab = "register";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">

 <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="login-container">
    <div class="login-title">IchoicePMS</div>
    <div class="login-desc">ระบบจัดการสินค้าด้านคลังสินค้า</div>

    <div class="tab-group">
        <button class="tab-btn <?= $tab == 'login' ? 'tab-active' : '' ?>" id="loginTab" type="button" onclick="showTab('login')">เข้าสู่ระบบ</button>
        <button class="tab-btn <?= $tab == 'register' ? 'tab-active' : '' ?>" id="registerTab" type="button" onclick="showTab('register')">สมัครสมาชิก</button>
    </div>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success-msg"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form id="loginForm" method="post" action="" style="<?= $tab=='register' ? 'display:none' : '';?>">
        <input type="hidden" name="form_type" value="login">
        <div class="form-group">
            <label for="login-email">อีเมล</label>
            <input type="email" id="login-email" name="email" placeholder="กรอกอีเมล" required>
        </div>
        <div class="form-group">
            <label for="login-password">รหัสผ่าน</label>
            <input type="password" id="login-password" name="password" placeholder="กรอกรหัสผ่าน" required>
        </div>
        <button type="submit" class="btn-action">เข้าสู่ระบบ</button>
       <div class="forgot-link">
            <a href="forgot_password.php">ลืมรหัสผ่าน?</a>
        </div>
    </form>

    <!-- Register Form -->
    <form id="registerForm" method="post" action="" style="<?= $tab=='register' ? '' : 'display:none';?>">
        <input type="hidden" name="form_type" value="register">
        <div class="note-box">
            <svg width="20" height="20" fill="#157de6"><circle cx="10" cy="10" r="10"/><path d="M6 10.5l3 3 5-5" stroke="#fff" stroke-width="2" fill="none" /></svg>
            หลังสมัครสมาชิก บัญชีจะต้องได้รับการอนุมัติจากผู้ดูแลระบบก่อนใช้งาน
        </div>
        <div class="form-group">
            <label for="name">ชื่อ-นามสกุล</label>
            <input type="text" id="name" name="name" placeholder="กรอกชื่อ-นามสกุล" required>
        </div>
        <div class="form-group">
            <label for="register-email">อีเมล</label>
            <input type="email" id="register-email" name="email" placeholder="กรอกอีเมล" required>
        </div>
        <div class="form-group">
            <label for="register-password">รหัสผ่าน</label>
            <input type="password" id="register-password" name="password" placeholder="กรอกรหัสผ่าน" required>
        </div>
        <div class="form-group">
            <label for="department">แผนก</label>
            <select id="department" name="department" required>
                <option value="">เลือกแผนก</option>
                <option value="คลังสินค้า">คลังสินค้า</option>
                <option value="ขาย">ขาย</option>
                <option value="จัดซื้อ">จัดซื้อ</option>
                <option value="ผู้จัดการ">ผู้จัดการ</option>
            </select>
        </div>
        <button type="submit" class="btn-action">สมัครสมาชิก</button>
    </form>
</div>

<script>
function showTab(tab) {
    document.getElementById('loginForm').style.display = (tab === 'login') ? '' : 'none';
    document.getElementById('registerForm').style.display = (tab === 'register') ? '' : 'none';
    document.getElementById('loginTab').classList.toggle('tab-active', tab==='login');
    document.getElementById('registerTab').classList.toggle('tab-active', tab==='register');
}
</script>
</body>
</html>