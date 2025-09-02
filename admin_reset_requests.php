<?php
session_start();
require_once 'db_connect.php';
if($_SESSION['user_role']!=='admin'){ http_response_code(403); exit; }

$reqs = $pdo->query("SELECT * FROM password_reset_requests WHERE status='pending' ORDER BY requested_at ASC")->fetchAll();
?>

<?php if(!$reqs): ?>
<p>ไม่มีคำขอรีเซ็ต</p>
<?php else: ?>
<?php foreach($reqs as $r): ?>
<div class="usercard reset-card">
    <div>
        <div><b><?=htmlspecialchars($r['user_name'])?></b> <span style="color:#f7b731;">รอรีเซ็ต</span></div>
        <div><?=htmlspecialchars($r['user_email'])?> &bull; <?=$r['requested_at']?></div>
    </div>
    <button class="btn-reset" data-id="<?=$r['id']?>">ตั้งรหัสใหม่</button>
</div>
<?php endforeach; ?>
<?php endif; ?>
