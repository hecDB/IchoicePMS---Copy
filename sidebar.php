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
<!-- sidebar styling is centralized in assets/sidebar.css to avoid per-page conflicts -->
<!-- mobile nav toggle (visible on small screens) -->
<button class="mobile-nav-toggle show" id="mobileNavToggle" aria-label="เปิดเมนู" style="position:fixed;left:16px;top:12px;z-index:2100;background:#fff;border:0;border-radius:8px;padding:6px 8px;box-shadow:0 4px 12px rgba(0,0,0,0.08);display:none;">
    <i class="fa fa-bars" style="font-size:18px;color:#0856cd"></i>
</button>
<div class="sidebar" id="sidebarNav" role="navigation" aria-label="เมนูหลัก">
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
<div class="sidebar-backdrop" id="sidebarBackdrop" tabindex="-1"></div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var toggle = document.getElementById('mobileNavToggle');
    var sidebar = document.getElementById('sidebarNav');
    var backdrop = document.getElementById('sidebarBackdrop');
    // show toggle on small screens
    function updateToggle(){ if(window.innerWidth <= 1024){ toggle.style.display='inline-flex'; } else { toggle.style.display='none'; sidebar.classList.remove('visible'); } }
    updateToggle(); window.addEventListener('resize', updateToggle);
    toggle.addEventListener('click', function(){ sidebar.classList.add('visible'); document.body.style.overflow='hidden'; });
    backdrop.addEventListener('click', function(){ sidebar.classList.remove('visible'); document.body.style.overflow=''; });
    // close on Escape
    document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ sidebar.classList.remove('visible'); document.body.style.overflow=''; } });
});
</script>
