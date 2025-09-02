<?php
session_start();
require_once 'db_connect.php';
if(!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd1 = $_POST['newpass'] ?? '';
    $pwd2 = $_POST['newpass2'] ?? '';

    // ตรวจสอบครบถ้วนตามมาตรฐาน
    if (!$pwd1 || !$pwd2) {
        $error = "กรุณากรอกรหัสผ่านให้ครบ";
    } elseif ($pwd1 !== $pwd2) {
        $error = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
    } elseif (strlen($pwd1) < 8) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
    } elseif (!preg_match('/[A-Z]/', $pwd1)) {
        $error = "รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่ อย่างน้อย 1 ตัว";
    } elseif (!preg_match('/[a-z]/', $pwd1)) {
        $error = "รหัสผ่านต้องมีตัวอักษรพิมพ์เล็ก อย่างน้อย 1 ตัว";
    } elseif (!preg_match('/[0-9]/', $pwd1)) {
        $error = "รหัสผ่านต้องมีตัวเลข อย่างน้อย 1 ตัว";
    }elseif (!preg_match('/[_!@#$%^&*(),.?":{}|<>]/', $pwd1)) {
        $error = "รหัสผ่านต้องมีอักขระพิเศษ อย่างน้อย 1 ตัว เช่น !@#$%^&*";
    } else {
        // รหัสผ่านผ่านทุกเงื่อนไข
        $pwd_hash = password_hash($pwd1, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password=?, require_password_change=0 WHERE user_id=?");
        $stmt->execute([$pwd_hash, $_SESSION['user_id']]);
       
        // แสดง success modal พร้อมโหลดสวย ๆ
    echo <<<HTML
    <div id="successModal">
        <div class="modal-content">
            <img src="https://i.gifer.com/ZZ5H.gif" alt="loading" width="80">
        
        </div>
    </div>
    <style>
        #successModal {
            position: fixed;
            top:0; left:0;
            width:100%; height:100%;
            background: rgba(0,0,0,0.6);
            display:flex;
            justify-content:center;
            align-items:center;
            z-index:9999;
        }
        #successModal .modal-content {
            background:#fff;
            padding:30px 40px;
            border-radius:12px;
            text-align:center;
            box-shadow:0 8px 20px rgba(0,0,0,0.3);
            font-family:'Sarabun', sans-serif;
        }
        #successModal p {
            margin-top:20px;
            font-size:16px;
            color:#006400;
            font-weight:bold;
        }
    </style>
    <script>
        setTimeout(function(){
            window.location.href = 'dashboard.php';
        }, 2000);
    </script>
HTML;
    exit; // หยุดการโหลดหน้าอื่น

    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">

<style>
body {
    background: linear-gradient(to right, #eef4fd, #d7e7fb);
    font-family: 'Sarabun', sans-serif;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    margin:0;
}
.container{
    background:#fff;
    border-radius:12px;
    padding:40px 30px;
    max-width:400px;
    width:100%;
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    text-align:center;
    position:relative;
}
h3 { margin-bottom:20px; color:#1675fb; }
input[type=password] {
    width:100%;
    padding:14px;
    margin-bottom:20px;
    border:1px solid #ccc;
    border-radius:8px;
    font-size:16px;
}
button {
    width:100%;
    padding:14px;
    font-size:16px;
    background:#1675fb;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
    transition: 0.3s;
}
button:hover { background:#0f53d0; }
.error-msg {
    background:#ffe0e0;
    color:#d8000c;
    padding:10px;
    border-radius:8px;
    margin-bottom:20px;
    text-align:left;
}
.success-msg {
    background:#e0ffe0;
    color:#006400;
    padding:10px;
    border-radius:8px;
    margin-bottom:20px;
    font-weight:bold;
}
.password-info {
    text-align:left;
    font-size:14px;
    color:#555;
    margin-bottom:20px;
}
.password-group {
    position:relative;
    margin-bottom:20px;
}
.toggle-pass {
    position:absolute;
    right:10px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    font-size:18px;
    color:#555;
    user-select:none;
}
</style>
</head>
<body>
<div class="container">
    <h3>ตั้งรหัสผ่านใหม่</h3>

    <?php if(!empty($error)) echo "<div class='error-msg'>$error</div>"; ?>
    <?php if(!empty($success)) echo "<div class='success-msg'>$success</div>"; ?>

    <?php if(empty($success)): ?>
    <p class="password-info">
        รหัสผ่านต้องมีคุณสมบัติดังนี้:<br>
        - ความยาวอย่างน้อย 8 ตัวอักษร<br>
        - ตัวอักษรพิมพ์ใหญ่ (A-Z) อย่างน้อย 1 ตัว<br>
        - ตัวอักษรพิมพ์เล็ก (a-z) อย่างน้อย 1 ตัว<br>
        - ตัวเลข (0-9) อย่างน้อย 1 ตัว<br>
        - อักขระพิเศษ เช่น !@#$%^&* อย่างน้อย 1 ตัว
    </p>

    <form method="post">
        <div class="password-group">
            <input type="password" id="newpass" name="newpass" placeholder="รหัสผ่านใหม่" required>
            <span class="toggle-pass" onclick="togglePassword('newpass')">&#128065;</span>
        </div>
        <div class="password-group">
            <input type="password" id="newpass2" name="newpass2" placeholder="ยืนยันรหัสผ่าน" required>
            <span class="toggle-pass" onclick="togglePassword('newpass2')">&#128065;</span>
        </div>
        <button type="submit">เปลี่ยนรหัสผ่าน</button>
    </form>

    <script>
    function togglePassword(id){
        const input = document.getElementById(id);
        input.type = input.type === "password" ? "text" : "password";
    }
    </script>

    <?php else: ?>
    <script>
        setTimeout(function(){
            window.location.href = 'dashboard.php';
        }, 2000);
    </script>
    <?php endif; ?>
</div>
</body>
</html>
