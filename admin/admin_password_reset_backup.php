<?php
session_start();
include '../templates/sidebar.php';

// admin check
if($_SESSION['user_role']!=='admin'){ header("Location: login.php"); exit; }

if(isset($_POST['approve_id'])) {
    $req_id = intval($_POST['approve_id']);
    $stmt = $pdo->prepare("SELECT * FROM password_reset_requests WHERE id=?");
    $stmt->execute([$req_id]);
    if($req = $stmt->fetch()) {
        $newpass = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"),0,8);
        $hash_new = password_hash($newpass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password=?, require_password_change=1 WHERE email=?")
            ->execute([$hash_new, $req['user_email']]);
        $pdo->prepare("UPDATE password_reset_requests SET status='processed' WHERE id=?")
            ->execute([$req_id]);
        $info = "ออกรหัสใหม่สำหรับ ".$req['user_email'].": <b>$newpass</b>";
    }
}

$reqs = $pdo->query("SELECT * FROM password_reset_requests WHERE status='pending' ORDER BY requested_at ASC")->fetchAll();
?>

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background:#eef4fd; font-family: 'Prompt',sans-serif;}
        .container { max-width:800px; margin:40px auto; background:#fff; border-radius:12px; padding:30px 28px; box-shadow:0 4px 15px rgba(0,0,0,0.1);}
        .title { font-size:28px; font-weight:bold; color: #155ad8; margin-bottom:20px; }
        .info-msg { color:#078138; background:#eaffa7; border-radius:8px; padding:12px; margin-bottom:20px; font-weight:500;}
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { padding:12px; text-align:left; }
        th { background:#cce0ff; font-weight:600; }
        tr:nth-child(even) { background:#f7faff; }
        tr:hover { background:#e3f0ff; }
        .action-btns { display:flex; gap:6px; }
        .icon-btn { display:flex; align-items:center; gap:4px; padding:6px 12px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none; border:1px solid #ccc; cursor:pointer; transition:all 0.2s ease;}
        .icon-btn.process { background-color:#0d6efd; color:#fff; border-color:#0d6efd; }
        .icon-btn.process:hover { background-color:#0b5ed7; }
        .material-icons { font-size:18px; }
    </style>
</head>

<div class="container">
    <h2 class="title">รายการขอรหัสผ่านใหม่</h2>
    <?php if(!empty($info)) echo "<div class='info-msg'>$info</div>"; ?>
    <table>
        <tr>
            <th>อีเมล</th>
            <th>ชื่อผู้ใช้</th>
            <th>วันที่ขอ</th>
            <th>สถานะ</th>
            <th>Action</th>
        </tr>
        <?php foreach($reqs as $r): ?>
        <tr>
            <td><?=$r['user_email']?></td>
            <td><?=$r['user_name']?></td>
            <td><?=$r['requested_at']?></td>
            <td><?=$r['status']?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="approve_id" value="<?=$r['id']?>">
                    <button type="submit" class="icon-btn process">
                        <span class="material-icons">lock_reset</span> ตั้งรหัสผ่านใหม่
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
