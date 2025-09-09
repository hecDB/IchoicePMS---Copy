<?php
session_start();
include 'db_connect.php';
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: combined_login_register.php');
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
            <button onclick="window.location.href='force_change_password.php'">ตั้งรหัสผ่านใหม่</button>
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
$total_products = count($products);
$total_stock_qty = array_sum(array_column($products, 'total_stock'));

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

?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>IchoicePMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
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
      padding: 24px 22px; margin-bottom: 24px;
    }
    .card-title { font-size: 22px; font-weight: 600; margin-bottom: 10px; }
    @media (max-width: 900px) {
      .mainwrap { margin-left: 0 !important; padding: 18px 4vw 12vw 4vw; }
      .card-sec { padding: 0; }
    }
    @media (min-width: 901px) {
      .mainwrap { margin-left: 230px; transition: margin-left 0.2s; }
    }
  </style>
</head>
<body>
<?php  include 'sidebar.php';?>
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
        <div class="card" style="display:flex;gap:32px;flex-wrap:wrap;justify-content:space-between;margin-top:20px;">
          <div style="flex:1 1 170px;min-width:155px;max-width:280px;">
            <div style="display:flex;align-items:center;gap:17px;">
              <span class="material-icons" style="font-size:41px;color:#ff6b6b;background:#ffe5e5;border-radius:13px;padding:7px;">inventory_2</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">รายการสินค้าทั้งหมด</div>
                <div style="font-size:26px;font-weight:bold;"><?=$total_products?></div>
              </div>
            </div>
          </div>
          <div style="flex:1 1 170px;min-width:155px;max-width:280px;">
            <div style="display:flex;align-items:center;gap:17px;">
              <span class="material-icons" style="font-size:41px;color:#1abc9c;background:#d4f8f4;border-radius:13px;padding:7px;">inventory</span>
              <div>
                <div style="font-size:15px;color:#6c7fb0;">จำนวนสินค้าทั้งหมด</div>
                <div style="font-size:26px;font-weight:bold;"><?=$total_stock_qty?></div>
              </div>
            </div>
          </div>
          <!-- สินค้าใกล้หมด -->
          <a href="low_stock.php" 
              style="flex:1 1 170px;min-width:155px;max-width:280px;
                      display:flex;align-items:center;gap:17px;
                      text-decoration:none;background:#f0f8ff;
                      border-radius:13px;padding:12px;
                      transition:0.2s;cursor:pointer;"
              onmouseover="this.style.background='#d4f8f4';"
              onmouseout="this.style.background='#f0f8ff';">
                <span class="material-icons" 
                      style="font-size:41px;color:#e67e22;background:#fdebd0;
                            border-radius:13px;padding:7px;">
                    warning
                </span>
                <div>
                    <div style="font-size:15px;color:#6c7fb0;">สินค้าใกล้หมด</div>
                    <div style="font-size:26px;font-weight:bold;"><?=$low_stock_qty?></div>
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
</body>
</html>