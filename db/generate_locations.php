<?php
session_start();
require '../config/db_connect.php';

$message = '';
$success = false;

try {
    // SQL command to generate all location combinations
    $sql = "INSERT INTO locations (shelf, bin, row_code, description)
            SELECT s.shelf, b.bin, r.row_code,
                   CONCAT('แถว ', r.row_code, ' ล็อค ', b.bin, ' ชั้น ', s.shelf) AS description
            FROM (
              SELECT 1 AS shelf UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
              SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
            ) s
            CROSS JOIN (
              SELECT 1 AS bin UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
              SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
            ) b
            CROSS JOIN (
              SELECT 'A' AS row_code UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D' UNION SELECT 'E'
              UNION SELECT 'F' UNION SELECT 'G' UNION SELECT 'H' UNION SELECT 'I' UNION SELECT 'J'
              UNION SELECT 'K' UNION SELECT 'L' UNION SELECT 'M' UNION SELECT 'N' UNION SELECT 'O'
              UNION SELECT 'P' UNION SELECT 'Q' UNION SELECT 'R' UNION SELECT 'S' UNION SELECT 'T'
              UNION SELECT 'U' UNION SELECT 'V' UNION SELECT 'W' UNION SELECT 'X' UNION SELECT 'Y'
              UNION SELECT 'Z'
              UNION SELECT 'ตู้'
              UNION SELECT 'sale(บน)'
              UNION SELECT 'sale(ล่าง)'
            ) r";
    
    // Execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $affected_rows = $stmt->rowCount();
    $success = true;
    $message = "✅ สำเร็จ! สร้างตำแหน่งเก็บสินค้า {$affected_rows} รายการ\n\n";
    $message .= "📊 รายละเอียด:\n";
    $message .= "- แถว (Row): A-Z, ตู้, sale(บน), sale(ล่าง) = 29 แถว\n";
    $message .= "- ล็อค (Bin): 1-10 = 10 ล็อค\n";
    $message .= "- ชั้น (Shelf): 1-10 = 10 ชั้น\n";
    $message .= "- รวมทั้งหมด: 29 × 10 × 10 = {$affected_rows} ตำแหน่ง";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        // Check how many locations already exist
        $check_sql = "SELECT COUNT(*) as count FROM locations";
        $check_stmt = $pdo->query($check_sql);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $existing_count = $result['count'] ?? 0;
        
        $success = false;
        $message = "⚠️ ข้อมูลซ้ำ\n\n";
        $message .= "ตำแหน่งดังกล่าวมีอยู่ในระบบแล้ว จำนวน {$existing_count} รายการ\n\n";
        $message .= "💡 ถ้าต้องการเพิ่มเติม:\n";
        $message .= "1. ลบตำแหน่งเก่า: DELETE FROM locations WHERE 1=1;\n";
        $message .= "2. รัน script นี้ใหม่";
    } else {
        $success = false;
        $message = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} catch (Exception $e) {
    $success = false;
    $message = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างตำแหน่งจัดเก็บสินค้า - IchoicePMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container-main {
            max-width: 600px;
            width: 100%;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            border-bottom: none;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .card-header .material-icons {
            font-size: 2.5rem;
        }

        .card-body {
            padding: 2.5rem;
        }

        .message-box {
            padding: 1.5rem;
            border-radius: 12px;
            font-size: 1rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
        }

        .message-box.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #b1dfbb;
        }

        .message-box.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f1b0b7;
        }

        .message-box.info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border: 1px solid #b8daff;
        }

        .info-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }

        .info-section h5 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-section .material-icons {
            font-size: 1.3rem;
        }

        .info-section p {
            margin: 0.5rem 0;
            color: #555;
            font-size: 0.95rem;
        }

        .info-section code {
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            color: #d63384;
            font-size: 0.9rem;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 2rem;
        }

        .btn-primary-custom {
            flex: 1;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-secondary-custom {
            flex: 1;
            padding: 12px 20px;
            background: #e9ecef;
            color: #333;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary-custom:hover {
            background: #dee2e6;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 1rem;
        }

        .stat-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        .stat-item .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-item .label {
            font-size: 0.85rem;
            color: #888;
            margin-top: 0.3rem;
        }

        .alert-icon {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="card">
            <div class="card-header">
                <h2>
                    <span class="material-icons">storage</span>
                    สร้างตำแหน่งจัดเก็บสินค้า
                </h2>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="message-box success">
                        <?php echo htmlspecialchars($message); ?>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="number">29</div>
                            <div class="label">แถว (Row)</div>
                        </div>
                        <div class="stat-item">
                            <div class="number">10</div>
                            <div class="label">ล็อค (Bin)</div>
                        </div>
                        <div class="stat-item">
                            <div class="number">10</div>
                            <div class="label">ชั้น (Shelf)</div>
                        </div>
                    </div>

                    <div class="info-section" style="margin-top: 2rem;">
                        <h5>
                            <span class="material-icons">check_circle</span>
                            ตำแหน่งจัดเก็บอยู่พร้อมใช้
                        </h5>
                        <p>✅ สามารถเพิ่มสินค้า และกำหนดตำแหน่งได้แล้ว</p>
                        <p>✅ ระบบ modal แสดงตำแหน่งแยก (แถว/ล็อค/ชั้น)</p>
                        <p>✅ สามารถ export/import Excel ได้ปกติ</p>
                    </div>
                <?php else: ?>
                    <div class="alert-icon">⚠️</div>
                    <div class="message-box error">
                        <?php echo htmlspecialchars($message); ?>
                    </div>

                    <div class="info-section">
                        <h5>
                            <span class="material-icons">info</span>
                            วิธีแก้ไข
                        </h5>
                        <p>หากต้องการลบและสร้างใหม่:</p>
                        <p><code>DELETE FROM locations;</code></p>
                        <p style="margin-top: 1rem;">แล้วรัน script นี้อีกครั้ง</p>
                    </div>
                <?php endif; ?>

                <div class="button-group">
                    <a href="../dashboard.php" class="btn-secondary-custom">
                        <span class="material-icons">home</span>
                        หน้าแรก
                    </a>
                    <button onclick="location.reload()" class="btn-primary-custom">
                        <span class="material-icons">refresh</span>
                        รัน script อีกครั้ง
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
