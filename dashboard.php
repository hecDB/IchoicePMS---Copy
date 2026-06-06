<?php
session_start();
include 'config/db_connect.php';
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: auth/combined_login_register.php');
    exit;
}

// ตรวจสอบ require_password_change
$stmt = $pdo->prepare("SELECT require_password_change FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['require_password_change'] == 1) {
    // แสดง modal สวย ๆ และ redirect
    echo <<<HTML
    <style>
    /* Overlay */
    #passwordModal {
        position: fixed;
        top:0; left:0;
        width:100%; height:100%;
        background: rgba(0,0,0,0.6);
        display:flex;
        justify-content:center;
        align-items:center;
        z-index:9999;
    }
    #passwordModal .modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        text-align:center;
        max-width:400px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        font-family: 'Sarabun', sans-serif;
    }
    #passwordModal button {
        background-color:#007bff;
        color:#fff;
        border:none;
        padding:10px 20px;
        border-radius:5px;
        cursor:pointer;
        margin-top:20px;
        font-size:16px;
    }
    #passwordModal button:hover { background-color:#0056b3; }
    </style>

    <div id="passwordModal">
        <div class="modal-content">
            <h2>แจ้งเตือน</h2>
            <p>คุณต้องตั้งรหัสผ่านใหม่ก่อนเข้าระบบ</p>
            <button onclick="window.location.href='auth/force_change_password.php'">ตั้งรหัสผ่านใหม่</button>
        </div>
    </div>

    <script>
        // ป้องกันกดปุ่มอื่น ๆ
        document.body.style.overflow = 'hidden';
    </script>
HTML;
    exit; // หยุดโหลดหน้าอื่น
}


// สมมติค่าเหล่านี้มีจาก session
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? 'user';

// สถิติทดลอง (ให้ปรับเป็น query ของคุณเอง)
$total_users   = $pdo->query("SELECT COUNT(*) FROM users;")->fetchColumn();
$pending_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending';")->fetchColumn();
$approved_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status='approved';")->fetchColumn();

// ====== สินค้าคงคลัง ======
$sql_stock = "
    SELECT 
        p.product_id,
        p.name,
        p.sku,
        p.barcode,
        SUM(ri.receive_qty) AS total_stock
    FROM products p
    INNER JOIN purchase_order_items poi
        ON poi.product_id = p.product_id
    INNER JOIN receive_items ri
        ON ri.item_id = poi.item_id
    GROUP BY p.product_id, p.name, p.sku, p.barcode
    HAVING total_stock > 0
    ORDER BY p.name
";
$stmt = $pdo->query($sql_stock);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_stock_qty = array_sum(array_column($products, 'total_stock'));

// ====== คำนวณสถานะสต็อกตามเงื่อนไขใหม่ ======
$high_stock_count = 0;
$medium_stock_count = 0;
$low_stock_count = 0;

foreach ($products as $product) {
    $stock = $product['total_stock'];
    if ($stock > 10) {
        $high_stock_count++;
    } elseif ($stock >= 2 && $stock <= 10) {
        $medium_stock_count++;
    } elseif ($stock <= 1 && $stock > 0) {
        $low_stock_count++;
    }
}

// ====== สินค้าหมดแล้ว (total_qty < 1) ======
$sql_low_stock = "
  SELECT 
    p.product_id,
    p.name,
    p.sku,
    p.barcode,
    p.unit,
    p.image,
    p.remark_color,
    SUM(ri.receive_qty) AS total_qty,
    ri.expiry_date
  FROM products p
  LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
  RIGHT JOIN receive_items ri ON ri.item_id = poi.item_id
  GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image, p.remark_color, ri.expiry_date
  HAVING total_qty <= 1
  ORDER BY total_qty ASC, p.name
";
$stmt = $pdo->query($sql_low_stock);
$low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$low_stock_count = count($low_stock_products);
$low_stock_qty = array_sum(array_column($low_stock_products, 'total_qty'));

// ====== สินค้าใกล้หมดอายุ (ใน 90 วัน) ======
$ninety_days_later = date('Y-m-d', strtotime('+90 days'));
$today = date('Y-m-d');

$sql_expiring_soon = "
    SELECT 
        p.product_id
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    WHERE ri.expiry_date IS NOT NULL 
      AND ri.expiry_date BETWEEN ? AND ?
    GROUP BY p.product_id
    HAVING SUM(ri.receive_qty) > 0
";
$stmt_expiring = $pdo->prepare($sql_expiring_soon);
$stmt_expiring->execute([$today, $ninety_days_later]);
$expiring_soon_products = $stmt_expiring->fetchAll(PDO::FETCH_ASSOC);
$expiring_soon_count = count($expiring_soon_products);

?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>IchoicePMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/base.css">
  <link rel="stylesheet" href="assets/sidebar.css">
  <link rel="stylesheet" href="assets/components.css">
  <style>
    body { margin:0; background:#f4f6f9; }
    .mainwrap { min-height:100vh; padding:32px 18px 18px 18px; }
    .topbar {
      font-size: 22px; font-weight: 600; color: #375dfa;
      margin-bottom: 24px; background: #fff; border-radius: 10px;
      padding: 18px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .card-sec { max-width: 1200px; margin: 0 auto; }
    .card {
      background: #fff; border-radius: 13px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      padding: 24px 22px; margin-bottom: 24px; transition: all 0.3s ease;
    }
    .card-title { font-size: 22px; font-weight: 600; margin-bottom: 10px; }
    
    /* User Guide Card Styles */
    .user-guide-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      position: relative;
      overflow: hidden;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .user-guide-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
      transition: all 0.6s ease;
    }
    
    .user-guide-card:hover::before {
      left: 100%;
    }
    
    .user-guide-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
    }
    
    .guide-icon-container {
      background: rgba(255,255,255,0.15);
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
      backdrop-filter: blur(10px);
      transition: all 0.3s ease;
    }
    
    .user-guide-card:hover .guide-icon-container {
      background: rgba(255,255,255,0.25);
      transform: scale(1.05);
    }
    
    .guide-content {
      flex: 1;
      padding-left: 4px;
    }
    
    .guide-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .guide-description {
      font-size: 15px;
      opacity: 0.9;
      line-height: 1.4;
    }
    
    .guide-button {
      background: rgba(255,255,255,0.2);
      padding: 12px 16px;
      border-radius: 25px;
      font-size: 15px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
    }
    
    .user-guide-card:hover .guide-button {
      background: rgba(255,255,255,0.3);
      transform: scale(1.05);
    }
    
    /* Responsive adjustments */
    @media (max-width: 900px) {
      .mainwrap { padding: 18px 4vw 12vw 4vw; }
      .card-sec { padding: 0; }
      
      .guide-title {
        font-size: 18px;
      }
      
      .guide-description {
        font-size: 14px;
      }
      
      .guide-button {
        padding: 10px 14px;
        font-size: 14px;
      }
    }
    
    @media (max-width: 600px) {
      .user-guide-card a {
        flex-direction: column;
        text-align: center;
        gap: 16px;
      }
      
      .guide-content {
        padding-left: 0;
      }
    }
  </style>
</head>
<body>
<?php  include 'templates/sidebar.php';?>
<div class="mainwrap">
    <div class="topbar">
        แดชบอร์ด (Dashboard)
    </div>
    <div class="card-sec">
        <div class="card">
            <div class="card-title">
                <span class="material-icons" style="color:#375dfa;">dashboard</span>
                ยินดีต้อนรับ, <span style="color:#375dfa;"><?=$user_name?></span>
            </div>
            <div style="color:#7d869c;margin-bottom:5px;font-size:16px;">
                สถานะ: <b><?=($user_role == 'admin') ? 'ผู้ดูแลระบบ' : 'สมาชิกทั่วไป'?></b>
            </div>
        </div>

        <!-- User Guide Card -->
        <div class="card user-guide-card">
            <a href="orders/user_guide.html" 
               target="_blank"
               style="display: flex; align-items: center; justify-content: space-between; 
                      text-decoration: none; color: inherit; width: 100%; position: relative; z-index: 1;">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div class="guide-icon-container">
                        <span class="material-icons" style="font-size: 36px; color: white;">menu_book</span>
                    </div>
                    <div class="guide-content">
                        <div class="guide-title">
                            � คู่มือการใช้งานระบบ
                        </div>
                        <div class="guide-description">
                            เรียนรู้วิธีใช้งานระบบจัดการใบสั่งซื้อ การจัดการสินค้า และฟีเจอร์ต่างๆ อย่างละเอียด
                        </div>
                    </div>
                </div>
                <div class="guide-button">
                    เปิดคู่มือ
                    <span class="material-icons" style="font-size: 20px;">open_in_new</span>
                </div>
            </a>
        </div>
        <?php if($user_role === 'admin') { ?>
        <div class="card" style="display:flex;gap:32px;flex-wrap:wrap;justify-content:space-between;">
          <div style="flex:1 1 170px;min-width:155px;max-width:280px;">
            <div style="display:flex;align-items:center;gap:17px;">
              <span class="material-icons" style="font-size:41px;color:#23c783;background:#d6fbe5;border-radius:13px;padding:7px;">supervisor_account</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">สมาชิกทั้งหมด</div>
                <div style="font-size:26px;font-weight:bold;"><?=$total_users?></div>
              </div>
            </div>
          </div>
          <div style="flex:1 1 170px;min-width:155px;max-width:280px;">
            <div style="display:flex;align-items:center;gap:17px;">
              <span class="material-icons" style="font-size:41px;color:#ffc034;background:#fff4d3;border-radius:13px;padding:7px;">pending_actions</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">รออนุมัติ</div>
                <div style="font-size:26px;font-weight:bold;"><?=$pending_users?></div>
              </div>
            </div>
          </div>
          <div style="flex:1 1 170px;min-width:155px;max-width:280px;">
            <div style="display:flex;align-items:center;gap:17px;">
              <span class="material-icons" style="font-size:41px;color:#49b6ff;background:#d8f2ff;border-radius:13px;padding:7px;">verified_user</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">อนุมัติแล้ว</div>
                <div style="font-size:26px;font-weight:bold;"><?=$approved_users?></div>
              </div>
            </div>
          </div>
        </div>
        <?php } ?>

        <!-- สถานะสต็อกสินค้า -->
        <div class="card" style="display:flex;gap:32px;flex-wrap:wrap;justify-content:space-between;margin-top:20px;">
          <!-- สต็อกเพียงพอ -->
          <a href="stock/product_management.php" 
             style="flex:1 1 170px;min-width:155px;max-width:280px;
                    display:flex;align-items:center;gap:17px;
                    text-decoration:none;background:#dcfce7;
                    border-radius:13px;padding:12px;
                    transition:0.2s;cursor:pointer;"
             onmouseover="this.style.background='#bbf7d0';"
             onmouseout="this.style.background='#dcfce7';">
              <span class="material-icons" style="font-size:41px;color:#16a34a;background:#bbf7d0;border-radius:13px;padding:7px;">check_circle</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">สต็อกเพียงพอ</div>
                <div style="font-size:26px;font-weight:bold;"><?=$high_stock_count?></div>
                <div style="font-size:12px;color:#16a34a;">มากกว่า 10 ชิ้น</div>
              </div>
          </a>

          <!-- สต็อกปานกลาง -->
          <a href="stock/product_management.php" 
             style="flex:1 1 170px;min-width:155px;max-width:280px;
                    display:flex;align-items:center;gap:17px;
                    text-decoration:none;background:#fef3c7;
                    border-radius:13px;padding:12px;
                    transition:0.2s;cursor:pointer;"
             onmouseover="this.style.background='#fde68a';"
             onmouseout="this.style.background='#fef3c7';">
              <span class="material-icons" style="font-size:41px;color:#d97706;background:#fde68a;border-radius:13px;padding:7px;">warning</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">สต็อกปานกลาง</div>
                <div style="font-size:26px;font-weight:bold;"><?=$medium_stock_count?></div>
                <div style="font-size:12px;color:#d97706;">2-10 ชิ้น</div>
              </div>
          </a>

          <!-- สต็อกต่ำ -->
          <a href="stock/low_stock.php" 
              style="flex:1 1 170px;min-width:155px;max-width:280px;
                      display:flex;align-items:center;gap:17px;
                      text-decoration:none;background:#fee2e2;
                      border-radius:13px;padding:12px;
                      transition:0.2s;cursor:pointer;"
              onmouseover="this.style.background='#fecaca';"
              onmouseout="this.style.background='#fee2e2';">
                <span class="material-icons" 
                      style="font-size:41px;color:#dc2626;background:#fecaca;
                            border-radius:13px;padding:7px;">
                    remove_circle
                </span>
                <div>
                    <div style="font-size:15px;color:#6c7fb0;">สต็อกต่ำ</div>
                    <div style="font-size:26px;font-weight:bold;"><?=$low_stock_count?></div>
                    <div style="font-size:12px;color:#dc2626;">เหลือ 1 ชิ้น</div>
                </div>
            </a>
        </div>

        <!-- ข้อมูลเพิ่มเติม -->
        <div class="card" style="display:flex;gap:32px;flex-wrap:wrap;justify-content:space-between;margin-top:20px;">
          <a href="stock/expiring_soon.php" 
             style="flex:1 1 170px;min-width:155px;max-width:280px;
                    display:flex;align-items:center;gap:17px;
                    text-decoration:none;background:#fff8e1;
                    border-radius:13px;padding:12px;
                    transition:0.2s;cursor:pointer;"
             onmouseover="this.style.background='#ffecb3';"
             onmouseout="this.style.background='#fff8e1';">
              <span class="material-icons" style="font-size:41px;color:#f57f17;background:#fff176;border-radius:13px;padding:7px;">event_busy</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">สินค้าใกล้หมดอายุ</div>
                <div style="font-size:26px;font-weight:bold;"><?=$expiring_soon_count?></div>
              </div>
          </a>

          <!-- สินค้าคงคลังทั้งหมด -->
          <a href="stock/product_management.php" 
             style="flex:1 1 170px;min-width:155px;max-width:280px;
                    display:flex;align-items:center;gap:17px;
                    text-decoration:none;background:#f0f8ff;
                    border-radius:13px;padding:12px;
                    transition:0.2s;cursor:pointer;"
             onmouseover="this.style.background='#d4f8f4';"
             onmouseout="this.style.background='#f0f8ff';">
              <span class="material-icons" style="font-size:41px;color:#1abc9c;background:#d4f8f4;border-radius:13px;padding:7px;">inventory</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">รายการสินค้าทั้งหมด</div>
                <div style="font-size:26px;font-weight:bold;"><?=$total_products?></div>
              </div>
          </a>
        </div>
        <div class="card" style="margin-top:34px;">
            <div class="card-title" style="font-size:18px;">
                <span class="material-icons" style="color:#6c7fb0;font-size:21px;">tips_and_updates</span>
                ประกาศ / ข่าวสาร
            </div>
            <div style="color:#7d869c; margin-top:16px; font-size:16px;">
                - ตัวอย่าง: ระบบนี้อยู่ระหว่างทดสอบ (เวอร์ชันใหม่)<br>
                - คุณสามารถเพิ่มประกาศ, ข่าวสาร, กิจกรรม หรือสถิติพิเศษอื่น ๆ ในส่วนนี้<br>
                - หรือจะแสดงกราฟ/ตาราง/shortcut อื่น ก็ได้เช่นกัน 🎉
            </div>
        </div>
    </div>
</div>

<!-- Expiry Notification Modal -->
<div id="expiryModal" style="display: none;">
    <div class="expiry-modal-overlay">
        <div class="expiry-modal-content">
            <div class="expiry-modal-header">
                <div class="expiry-icon">
                    <span style="color: #ef4444; font-size: 3rem;">✕</span>
                </div>
                <h2 class="expiry-title">แจ้งเตือนสินค้าหมดอายุ!</h2>
                <p class="expiry-subtitle">
                    พบสินค้าที่จะหมดอายุใน <span id="expiry-days">7</span> วันข้างหน้า
                    <br><span id="expiry-count">15</span> รายการ
                </p>
            </div>
            
            <div class="expiry-modal-body">
                <div class="expiry-summary">
                    <div class="expiry-stat" id="expired-stat" style="display: none;">
                        <span class="stat-number" id="expired-count">0</span>
                        <span class="stat-label">หมดอายุแล้ว</span>
                    </div>
                    <div class="expiry-stat" id="expiring-stat" style="display: none;">
                        <span class="stat-number" id="expiring-count">0</span>
                        <span class="stat-label">ใกล้หมดอายุ</span>
                    </div>
                </div>
                
                <div class="expiry-details">
                    <p class="detail-text">กรุณาตรวจสอบและติดตามการ<br>ปฏิบัติงานรับทราบแล้ว</p>
                </div>
            </div>
            
            <div class="expiry-modal-footer">
                <button id="acknowledge-btn" class="btn-acknowledge">
                    รับทราบ
                </button>
                <button id="view-details-btn" class="btn-details">
                    ดูรายงาน
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Expiry Notification Modal Styles */
.expiry-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    font-family: 'Sarabun', sans-serif;
}

.expiry-modal-content {
    background: white;
    border-radius: 16px;
    padding: 0;
    max-width: 480px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.expiry-modal-header {
    text-align: center;
    padding: 32px 32px 24px;
    border-bottom: 1px solid #f1f5f9;
}

.expiry-icon {
    margin-bottom: 16px;
}

.expiry-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 12px 0;
}

.expiry-subtitle {
    font-size: 1rem;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

.expiry-modal-body {
    padding: 24px 32px;
}

.expiry-summary {
    display: flex;
    justify-content: center;
    gap: 24px;
    margin-bottom: 24px;
}

.expiry-stat {
    text-align: center;
    padding: 16px;
    border-radius: 12px;
    min-width: 80px;
}

.expiry-stat:nth-child(1) {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
}

.expiry-stat:nth-child(2) {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
}

.expiry-details {
    text-align: center;
}

.detail-text {
    font-size: 1rem;
    color: #64748b;
    margin: 0;
    line-height: 1.6;
}

.expiry-modal-footer {
    padding: 24px 32px 32px;
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn-acknowledge {
    background: #ef4444;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: 'Sarabun', sans-serif;
}

.btn-acknowledge:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-details {
    background: #64748b;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: 'Sarabun', sans-serif;
}

.btn-details:hover {
    background: #475569;
    transform: translateY(-1px);
}

@media (max-width: 640px) {
    .expiry-modal-content {
        margin: 16px;
        width: calc(100% - 32px);
    }
    
    .expiry-summary {
        flex-direction: column;
        gap: 12px;
    }
    
    .expiry-modal-footer {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    checkExpiryNotifications();
});

async function checkExpiryNotifications() {
    try {
        const response = await fetch('api/expiry_notification_api.php');
        const result = await response.json();
        
        if (result.success && result.show_notification) {
            showExpiryModal(result.data);
        }
    } catch (error) {
        console.error('Error checking expiry notifications:', error);
    }
}

function showExpiryModal(data) {
    const modal = document.getElementById('expiryModal');
    const expiredStat = document.getElementById('expired-stat');
    const expiringStat = document.getElementById('expiring-stat');
    
    // Update counts
    if (data.expired.count > 0) {
        document.getElementById('expired-count').textContent = data.expired.count;
        expiredStat.style.display = 'block';
    }
    
    if (data.expiring.count > 0) {
        document.getElementById('expiring-count').textContent = data.expiring.count;
        expiringStat.style.display = 'block';
    }
    
    // Update summary text
    document.getElementById('expiry-count').textContent = data.total_items;
    
    // Show modal
    modal.style.display = 'block';
    
    // Setup event handlers
    setupModalEventHandlers();
}

function setupModalEventHandlers() {
    const acknowledgeBtn = document.getElementById('acknowledge-btn');
    const viewDetailsBtn = document.getElementById('view-details-btn');
    
    acknowledgeBtn.onclick = async function() {
        try {
            const response = await fetch('api/acknowledge_expiry_api.php', {
                method: 'POST'
            });
            const result = await response.json();
            
            if (result.success) {
                hideExpiryModal();
                // Optional: Show success message
                // alert('รับทราบการแจ้งเตือนเรียบร้อย');
            } else {
                alert('เกิดข้อผิดพลาด: ' + result.message);
            }
        } catch (error) {
            console.error('Error acknowledging notification:', error);
            alert('เกิดข้อผิดพลาดในการบันทึกการรับทราบ');
        }
    };
    
    viewDetailsBtn.onclick = function() {
        // Redirect to expiring soon page with critical filter
        window.location.href = 'stock/expiring_soon.php?filter=critical';
    };
}

function hideExpiryModal() {
    const modal = document.getElementById('expiryModal');
    modal.style.display = 'none';
}

// Close modal when clicking overlay
document.getElementById('expiryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideExpiryModal();
    }
});

// User Guide functionality
function openUserGuide() {
    // Show loading notification
    showGuideNotification('กำลังเปิดคู่มือการใช้งาน...', 'info');
    
    // Open user guide in new tab
    window.open('orders/user_guide.html', '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
    
    // Show success notification after a short delay
    setTimeout(() => {
        showGuideNotification('✅ เปิดคู่มือเรียบร้อยแล้ว!', 'success');
    }, 500);
}

function showGuideNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `guide-notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon material-icons">
                ${type === 'success' ? 'check_circle' : type === 'info' ? 'info' : 'error'}
            </span>
            <span class="notification-text">${message}</span>
        </div>
    `;
    
    // Add styles for notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'info' ? '#3b82f6' : '#ef4444'};
        color: white;
        padding: 12px 16px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-family: 'Sarabun', sans-serif;
        font-size: 14px;
        font-weight: 500;
        transform: translateX(100%);
        transition: all 0.3s ease;
        max-width: 300px;
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Add CSS for notification content
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification-content {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .notification-icon {
        font-size: 18px;
    }
    
    .notification-text {
        flex: 1;
    }
`;
document.head.appendChild(notificationStyles);

// Add click handler to user guide card
document.addEventListener('DOMContentLoaded', function() {
    const guideCard = document.querySelector('.user-guide-card a');
    if (guideCard) {
        guideCard.addEventListener('click', function(e) {
            e.preventDefault();
            openUserGuide();
        });
    }
});
</script>

</body>
</html>