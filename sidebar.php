<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once 'db_connect.php';

$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? 'user';

function isActive($file) {
    return (basename($_SERVER['PHP_SELF'])==$file) ? ' active' : '';
}

// สำหรับแจ้งเตือนผู้ใช้รออนุมัติ
$pending_count = 0;
if($user_role === 'admin') {
    $pending_count = $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
}

// แจ้งเตือนสินค้าเข้ามาใหม่ (ยังไม่ตรวจสอบ)
$pending_product_count = 0;
if($user_role === 'admin' ||$user_role === 'staff'  ) {
    $pending_product_count = $pdo->query("SELECT COUNT(*) FROM products WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY);")->fetchColumn();
}

?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body { margin:0; }
.sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: 230px;
    background: #fff;
    border-right: 1px solid #e5e5e5;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s;
}
.sidebar.collapsed { transform: translateX(-100%); }
.sidebar-header {
    display: flex; align-items: center; gap: 10px;
    padding: 18px 18px 10px 18px;
    font-size: 22px; font-weight: 600;
    color: #0856cd;
}
.brand-text { font-size: 20px; font-weight: 700; }
.label { padding: 0 18px; margin-top: 10px; color: #888; font-size: 13px; }
.menu-list { flex: 1; display: flex; flex-direction: column; gap: 2px; margin-top: 8px; }
.menu-item {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px;
    color: #222; text-decoration: none;
    border-left: 4px solid transparent;
    transition: background 0.15s, border-color 0.15s;
    font-size: 15px;
    position: relative;
}
.menu-item.active, .menu-item:hover {
    background: #f0f4ff;
    border-left: 4px solid #0856cd;
    color: #0856cd;
}
.menu-text { flex: 1; }
.notification i { font-size: 15px; color: orange; }
.pending-badge {
    position: absolute; top: 8px; right: 18px;
    background: red; color: white; font-size: 12px; font-weight: bold;
    border-radius: 50%; padding: 3px 6px; min-width: 18px; text-align: center; line-height: 1;
}
.sidebar-footer {
    padding: 16px 18px; border-top: 1px solid #eee; display: flex; align-items: center; gap: 10px;
    background: #fafbfc;
}
.avatar { background: #e5e9f2; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.username { font-size: 15px; }
.logout-link { margin-left: auto; color: #888; text-decoration: none; font-size: 20px; }
.sidebar-toggle {
    display: none; position: fixed; top: 16px; left: 16px; z-index: 1100;
    background: #0856cd; color: #fff; border: none; border-radius: 50%; width: 40px; height: 40px; align-items: center; justify-content: center; font-size: 24px;
}
@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .sidebar-toggle { display: flex; }
    .mainwrap { margin-left: 0 !important; }
}
@media (min-width: 901px) {
    .sidebar { transform: translateX(0); }
    .mainwrap { margin-left: 230px; transition: margin-left 0.2s; }
}
</style>
<button class="sidebar-toggle" id="sidebarToggle"><span class="material-icons">menu</span></button>
<div class="sidebar" id="sidebarNav">
    <div class="sidebar-header">
        <span class="material-icons">apps</span>
        <span class="brand-text">IchoicePMS</span>
    </div>
    <div class="label">เมนูหลัก</div>
    <nav class="menu-list">
        <a href="dashboard.php" class="menu-item<?=isActive('dashboard.php')?>">
            <span class="material-icons">dashboard</span>
            <span class="menu-text">แดชบอร์ด</span>
        </a>
        <a href="purchase_orders.php" class="menu-item<?=isActive('purchase_orders.php')?>">
            <span class="material-icons">add</span>
            <span class="menu-text">ใบสั่งซื้อสินค้า</span>
        </a>
        <a href="import_excel.php" class="menu-item<?=isActive('import_excel.php')?>">
            <span class="material-icons">upload_file</span>
            <span class="menu-text">อัปโหลด Excel</span>
        </a>
        <a href="import_product.php" class="menu-item<?=isActive('import_product.php')?>">
            <span class="material-icons">add</span>
            <span class="menu-text">เพิ่มสินค้า</span>
        </a>
        <a href="receive_items_view.php" class="menu-item<?=isActive('receive_items_view.php')?>">
            <span class="material-icons">assignment_turned_in</span>
            <span class="menu-text">รับสินค้า</span>
            <div class="notification" style="position: relative; display: inline-block;">
                <i class="fas fa-bell"></i>
                <?php if($pending_product_count > 0): ?>
                    <span class="pending-badge"><?= $pending_product_count ?></span>
                <?php endif; ?>
            </div>
        </a>
        <!-- <a href="product_activity.php" class="menu-item<?=isActive('product_activity.php')?>">
            <span class="material-icons">inventory</span>
            <span class="menu-text">ความเคลื่อนไหวสินค้า</span> -->
            
        <!-- </a> -->
        <?php if($user_role === 'admin'): ?>
            <a href="admin_users.php" class="menu-item<?=isActive('admin_users.php')?>">
                <span class="material-icons">supervisor_account</span>
                <span class="menu-text">จัดการผู้ใช้งาน</span>
                <div class="notification" style="position: relative; display: inline-block;">
                    <i class="fas fa-bell"></i>
                    <?php if($pending_count > 0): ?>
                        <span class="pending-badge"><?= $pending_count ?></span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <span class="avatar"><span class="material-icons">person</span></span>
        <span class="username" style="font-weight:500;">
            <?=htmlspecialchars($user_name)?>
            <span style="font-size:12px;color:#375dfa;margin-left:6px;">
                <?php
                    if($user_role=='admin')      echo "(Admin)";
                    elseif($user_role=='sales')  echo "(ฝ่ายขาย)";
                    elseif($user_role=='warehouse') echo "(คลังสินค้า)";
                ?>
            </span>
        </span>
        <a class="logout-link" href="logout.php"><span class="material-icons">logout</span></a>
    </div>
</div>
<script>
// Responsive sidebar toggle
const sidebar = document.getElementById('sidebarNav');
const toggleBtn = document.getElementById('sidebarToggle');
if(toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function(){
        sidebar.classList.toggle('open');
    });
    // ปิด sidebar เมื่อคลิกนอก sidebar (จอเล็ก)
    document.addEventListener('click', function(e){
        if(window.innerWidth <= 900 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
}
</script>