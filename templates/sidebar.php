<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../config/db_connect.php';

$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? 'user';

function isActive($file) {
    return (basename($_SERVER['PHP_SELF']) === $file) ? ' active' : '';
}

// ปรับเส้นทางให้เหมาะสมกับตำแหน่งของไฟล์ที่เรียกใช้
function getPath($targetPath) {
    $currentFile = $_SERVER['PHP_SELF'];
    $currentDir = dirname($currentFile);
    
    // แปลง backslash เป็น forward slash สำหรับ Windows
    $currentDir = str_replace('\\', '/', $currentDir);
    
    // ลบ leading slash
    $currentDir = ltrim($currentDir, '/');
    $targetPath = ltrim($targetPath, '/');
    
    // ถ้าอยู่ที่ root level (currentDir จะเป็น empty)
    if (empty($currentDir)) {
        return $targetPath;
    } else {
        // นับจำนวนระดับของโฟลเดอร์
        $levels = substr_count($currentDir, '/') + 1;
        $backPath = str_repeat('../', $levels);
        return $backPath . $targetPath;
    }
}

// ดึงจำนวนรายการที่รอการอนุมัติ
try {
    $pending_count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 0")->fetchColumn();
} catch (PDOException $e) {
    $pending_count = 0;
}

// ดึงจำนวนสินค้าที่รอรับเข้า
try {
    $pending_po_sql = "
        SELECT COUNT(*) 
        FROM purchase_orders po 
        WHERE po.status IN ('pending', 'partial')
    ";
    $pending_product_count = $pdo->query($pending_po_sql)->fetchColumn();
} catch (PDOException $e) {
    $pending_product_count = 0;
}
?>
<style>
body {
    margin: 0;
    font-family: 'Prompt', sans-serif;
}

/* Category Styles */
.menu-category {
    margin: 8px 0;
}

.category-header {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s ease;
    background: rgba(56, 93, 250, 0.08);
    color: #385dfa;
    font-weight: 600;
    user-select: none;
}

.category-header:hover {
    background: rgba(56, 93, 250, 0.15);
    transform: translateX(2px);
}

.category-header .material-icons:first-child {
    margin-right: 12px;
    font-size: 20px;
}

.category-text {
    flex: 1;
    font-size: 14px;
}

.category-arrow {
    transition: transform 0.3s ease;
    font-size: 18px;
    color: #385dfa;
}

.category-header.expanded .category-arrow {
    transform: rotate(180deg);
}

/* Submenu Styles */
.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    padding-left: 20px;
}

.submenu.expanded {
    max-height: 500px;
}

.submenu-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    text-decoration: none;
    color: #64748b;
    border-radius: 6px;
    margin: 2px 0;
    transition: all 0.2s ease;
    position: relative;
    font-size: 13px;
}

.submenu-item:hover {
    background: rgba(56, 93, 250, 0.1);
    color: #385dfa;
    transform: translateX(3px);
}

.submenu-item.active {
    background: linear-gradient(135deg, #385dfa 0%, #4f46e5 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(56, 93, 250, 0.3);
}

.submenu-item .material-icons {
    margin-right: 10px;
    font-size: 18px;
}

.submenu-text {
    flex: 1;
}

.pending-badge {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: auto;
    font-weight: 600;
    min-width: 16px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

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
        <!-- แดชบอร์ด -->
        <a href="<?= getPath('dashboard.php') ?>" class="menu-item<?=isActive('dashboard.php')?>">
            <span class="material-icons">dashboard</span>
            <span class="menu-text">แดชบอร์ด</span>
        </a>

        <!-- หมวดหมู่: จัดการคำสั่งซื้อ -->
        <div class="menu-category">
            <div class="category-header" onclick="toggleCategory('orders')">
                <span class="material-icons">shopping_cart</span>
                <span class="category-text">จัดการคำสั่งซื้อ</span>
                <span class="material-icons category-arrow">keyboard_arrow_down</span>
            </div>
            <div class="submenu" id="orders-submenu">
                <a href="<?= getPath('orders/purchase_orders.php') ?>" class="submenu-item<?=isActive('purchase_orders.php')?>">
                    <span class="material-icons">list_alt</span>
                    <span class="submenu-text">รายการใบสั่งซื้อ</span>
                </a>
                <a href="<?= getPath('orders/purchase_order_create.php') ?>" class="submenu-item<?=isActive('purchase_order_create.php')?>">
                    <span class="material-icons">add_shopping_cart</span>
                    <span class="submenu-text">สร้างใบสั่งซื้อใหม่</span>
                </a>
                <a href="<?= getPath('orders/purchase_order_create_new_product.php') ?>" class="submenu-item<?=isActive('purchase_order_create_new_product.php')?>">
                    <span class="material-icons">new_releases</span>
                    <span class="submenu-text">ซื้อสินค้าใหม่</span>
                </a>
                <a href="<?= getPath('receive/receive_po_items.php') ?>" class="submenu-item<?=isActive('receive_po_items.php')?>">
                    <span class="material-icons">input</span>
                    <span class="submenu-text">รับเข้าสินค้า</span>
                    <?php if($pending_product_count > 0): ?>
                        <span class="pending-badge"><?= $pending_product_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= getPath('receive/quick_receive.php') ?>" class="submenu-item<?=isActive('quick_receive.php')?>">
                    <span class="material-icons">qr_code_scanner</span>
                    <span class="submenu-text">รับสินค้าด่วน (Scan)</span>
                </a>
            </div>
        </div>

        <!-- หมวดหมู่: จัดการสินค้า -->
        <div class="menu-category">
            <div class="category-header" onclick="toggleCategory('products')">
                <span class="material-icons">inventory_2</span>
                <span class="category-text">
                    อัปโหลดสินค้า
                </span>
                <span class="material-icons category-arrow">keyboard_arrow_down</span>
            </div>
            <div class="submenu" id="products-submenu">
                
                <a href="<?= getPath('imports/import_excel.php') ?>" class="submenu-item<?=isActive('import_excel.php')?>">
                    <span class="material-icons">upload_file</span>
                    <span class="submenu-text">อัปโหลด Excel</span>
                </a>
            </div>
        </div>

        <!-- หมวดหมู่: จัดการสต็อก -->
        <div class="menu-category">
            <div class="category-header" onclick="toggleCategory('stock')">
                <span class="material-icons">warehouse</span>
                <span class="category-text">จัดการสต็อก</span>
                <span class="material-icons category-arrow">keyboard_arrow_down</span>
            </div>
            <div class="submenu" id="stock-submenu">
                <a href="<?= getPath('receive/receive_items_view.php') ?>" class="submenu-item<?=isActive('receive_items_view.php')?>">
                    <span class="material-icons">assignment_turned_in</span>
                    <span class="submenu-text">ความเคลื่อนไหวสินค้า</span>
                    <?php if($pending_product_count > 0): ?>
                        <span class="pending-badge"><?= $pending_product_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= getPath('receive/transaction_view_separated.php') ?>" class="submenu-item<?=isActive('transaction_view_separated.php')?>">
                    <span class="material-icons">new_releases</span>
                    <span class="submenu-text">สินค้าซื้อใหม่</span>
                </a>
                <a href="<?= getPath('stock/low_stock.php') ?>" class="submenu-item<?=isActive('low_stock.php')?>">
                    <span class="material-icons">warning</span>
                    <span class="submenu-text">สินค้าใกล้หมด</span>
                </a>
                <a href="<?= getPath('stock/product_management.php') ?>" class="submenu-item<?=isActive('product_management.php')?>">
                    <span class="material-icons">inventory_2</span>
                    <span class="submenu-text">จัดการสินค้า</span>
                </a>
                <a href="<?= getPath('stock/expiring_soon.php') ?>" class="submenu-item<?=isActive('expiring_soon.php')?>">
                    <span class="material-icons">schedule</span>
                    <span class="submenu-text">สินค้าใกล้หมดอายุ</span>
                </a>
                <a href="<?= getPath('stock/product_holding.php') ?>" class="submenu-item<?=isActive('product_holding.php')?>">
                    <span class="material-icons">pending_actions</span>
                    <span class="submenu-text">สินค้ารอสร้างโปรโมชั่น</span>
                </a>
                <a href="<?= getPath('stock/missing_products.php') ?>" class="submenu-item<?=isActive('missing_products.php')?>">
                    <span class="material-icons">inventory_2</span>
                    <span class="submenu-text">บันทึกสินค้าสูญหาย</span>
                </a>
                <a href="<?= getPath('stock/missing_products_list.php') ?>" class="submenu-item<?=isActive('missing_products_list.php')?>">
                    <span class="material-icons">warning</span>
                    <span class="submenu-text">รายการสินค้าสูญหาย</span>
                </a>
            </div>
        </div>

        <!-- หมวดหมู่: ยืมสินค้า -->
        <div class="menu-category">
            <div class="category-header" onclick="toggleCategory('borrow')">
                <span class="material-icons">card_giftcard</span>
                <span class="category-text">ยืมสินค้า</span>
                <span class="material-icons category-arrow">keyboard_arrow_down</span>
            </div>
            <div class="submenu" id="borrow-submenu">
                <a href="<?= getPath('borrow/borrow_items.php') ?>" class="submenu-item<?=isActive('borrow_items.php')?>">
                    <span class="material-icons">assignment</span>
                    <span class="submenu-text">รายการยืมสินค้า</span>
                </a>
            </div>
        </div>

        <!-- หมวดหมู่: ขายสินค้า -->
        <div class="menu-category">
            <div class="category-header" onclick="toggleCategory('sales')">
                <span class="material-icons">point_of_sale</span>
                <span class="category-text">ขายสินค้า</span>
                <span class="material-icons category-arrow">keyboard_arrow_down</span>
            </div>
            <div class="submenu" id="sales-submenu">
                <a href="<?= getPath('issue/issue_product.php') ?>" class="submenu-item<?=isActive('issue_product.php')?>">
                    <span class="material-icons">shopping_cart_checkout</span>
                    <span class="submenu-text">ยิงสินค้าออก (ขาย)</span>
                </a>
                <a href="<?= getPath('sales/sales_orders.php') ?>" class="submenu-item<?=isActive('sales_orders.php')?>">
                    <span class="material-icons">receipt_long</span>
                    <span class="submenu-text">รายการขาย</span>
                </a>
                <a href="<?= getPath('sales/tag_management.php') ?>" class="submenu-item<?=isActive('tag_management.php')?>">
                    <span class="material-icons">label</span>
                    <span class="submenu-text">จัดการเลขแท็ก</span>
                </a>
            </div>
        </div>

        <!-- หมวดหมู่: ผู้ดูแลระบบ (Admin only) -->
        <?php if ($user_role === 'admin'): ?>
        <div class="menu-category">
            <div class="category-header" onclick="toggleCategory('admin')">
                <span class="material-icons">admin_panel_settings</span>
                <span class="category-text">ผู้ดูแลระบบ</span>
                <span class="material-icons category-arrow">keyboard_arrow_down</span>
            </div>
            <div class="submenu" id="admin-submenu">
                <a href="<?= getPath('admin/admin_users.php') ?>" class="submenu-item<?=isActive('admin_users.php')?>">
                    <span class="material-icons">supervisor_account</span>
                    <span class="submenu-text">จัดการผู้ใช้งาน</span>
                    <?php if($pending_count > 0): ?>
                        <span class="pending-badge"><?= $pending_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= getPath('settings/currency_management.php') ?>" class="submenu-item<?=isActive('currency_management.php')?>">
                    <span class="material-icons">attach_money</span>
                    <span class="submenu-text">จัดการสกุลเงิน</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <span class="avatar"><span class="material-icons">person</span></span>
        <span class="username" style="font-weight:500;">
            <?php echo htmlspecialchars($user_name); ?>
            <?php if($user_role === 'admin'): ?>
                <span class="role-badge" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    font-size: 10px;
                    padding: 2px 6px;
                    border-radius: 8px;
                    margin-left: 4px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                ">ADMIN</span>
            <?php endif; ?>
        </span>
        <a class="logout-link" href="<?= getPath('auth/logout.php') ?>"><span class="material-icons">logout</span></a>
    </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop" tabindex="-1"></div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const sidebar = document.getElementById('sidebarNav');
    const backdrop = document.getElementById('sidebarBackdrop'); 
    const toggle = document.getElementById('mobileNavToggle');
    
    function updateToggle(){ if(window.innerWidth <= 1024){ toggle.style.display='inline-flex'; } else { toggle.style.display='none'; sidebar.classList.remove('visible'); } }
    window.addEventListener('resize', updateToggle); updateToggle();
    toggle.addEventListener('click', function(){ sidebar.classList.add('visible'); document.body.style.overflow='hidden'; });
    backdrop.addEventListener('click', function(){ sidebar.classList.remove('visible'); document.body.style.overflow=''; });
    
    // เปิดหมวดหมู่ที่มี active item อัตโนมัติ
    initializeActiveCategory();
});

function toggleCategory(categoryName) {
    const header = document.querySelector(`[onclick="toggleCategory('${categoryName}')"]`);
    const submenu = document.getElementById(categoryName + '-submenu');
    const categories = ['orders', 'products', 'stock', 'borrow', 'sales', 'admin'];
    
    if (submenu.classList.contains('expanded')) {
        // ปิดหมวดหมู่นี้
        submenu.classList.remove('expanded');
        header.classList.remove('expanded');
        localStorage.removeItem('expandedCategory_' + categoryName);
    } else {
        // ปิดหมวดหมู่อื่นๆ ทั้งหมดก่อน
        categories.forEach(otherCategory => {
            if (otherCategory !== categoryName) {
                const otherSubmenu = document.getElementById(otherCategory + '-submenu');
                const otherHeader = document.querySelector(`[onclick="toggleCategory('${otherCategory}')"]`);
                
                if (otherSubmenu && otherHeader) {
                    otherSubmenu.classList.remove('expanded');
                    otherHeader.classList.remove('expanded');
                    localStorage.removeItem('expandedCategory_' + otherCategory);
                }
            }
        });
        
        // เปิดหมวดหมู่ที่เลือก
        submenu.classList.add('expanded');
        header.classList.add('expanded');
        localStorage.setItem('expandedCategory_' + categoryName, 'true');
    }
}

function initializeActiveCategory() {
    const categories = ['orders', 'products', 'stock', 'sales', 'admin'];
    
    // ปิดหมวดหมู่ทั้งหมดก่อน
    categories.forEach(categoryName => {
        const submenu = document.getElementById(categoryName + '-submenu');
        const header = document.querySelector(`[onclick="toggleCategory('${categoryName}')"]`);
        
        if (submenu && header) {
            submenu.classList.remove('expanded');
            header.classList.remove('expanded');
        }
    });
    
    // ลบ localStorage ทั้งหมด
    categories.forEach(categoryName => {
        localStorage.removeItem('expandedCategory_' + categoryName);
    });
    
    // เปิดเฉพาะหมวดหมู่ที่มี active item
    const activeItem = document.querySelector('.submenu-item.active');
    if (activeItem) {
        const submenu = activeItem.closest('.submenu');
        if (submenu) {
            const categoryName = submenu.id.replace('-submenu', '');
            const header = document.querySelector(`[onclick="toggleCategory('${categoryName}')"]`);
            
            if (header) {
                submenu.classList.add('expanded');
                header.classList.add('expanded');
                localStorage.setItem('expandedCategory_' + categoryName, 'true');
            }
        }
    }
}
</script>
