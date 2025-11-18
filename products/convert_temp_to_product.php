<?php
session_start();
require_once '../config/db_connect.php';
require_once '../auth/auth_check.php';

// Check permission - only admin can approve
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
if ($user_role !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access Denied - Admin only';
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'approve_and_convert') {
        $temp_product_id = (int)$_POST['temp_product_id'];
        $sku = trim($_POST['sku'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $remark_color = trim($_POST['remark_color'] ?? '');
        $remark_split = (int)($_POST['remark_split'] ?? 0);

        try {
            $pdo->beginTransaction();

            // ตรวจสอบ temp product
            $stmt = $pdo->prepare("SELECT * FROM temp_products WHERE temp_product_id = ?");
            $stmt->execute([$temp_product_id]);
            $temp_product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$temp_product) {
                throw new Exception('ไม่พบสินค้าชั่วคราวนี้');
            }

            // ตรวจสอบ SKU ถ้ากรอก
            if (!empty($sku)) {
                $stmt = $pdo->prepare("SELECT product_id FROM products WHERE sku = ? LIMIT 1");
                $stmt->execute([$sku]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception('SKU นี้มีอยู่แล้ว');
                }
            }

            // ตรวจสอบ Barcode ถ้ากรอก
            if (!empty($barcode)) {
                $stmt = $pdo->prepare("SELECT product_id FROM products WHERE barcode = ? LIMIT 1");
                $stmt->execute([$barcode]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Barcode นี้มีอยู่แล้ว');
                }
            }

            // สร้างสินค้าใหม่
            $created_by = $_SESSION['user_id'];
            
            // ถ้า SKU/Barcode ว่าง ให้ใช้ NULL
            $final_sku = empty($sku) ? null : $sku;
            $final_barcode = empty($barcode) ? null : $barcode;

            $stmt = $pdo->prepare("
                INSERT INTO products 
                (name, sku, barcode, unit, remark_color, remark_split, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$temp_product['product_name'], $final_sku, $final_barcode, 
                            $temp_product['unit'], $remark_color, $remark_split, $created_by]);

            $product_id = $pdo->lastInsertId();

            // อัปเดต purchase_order_items เพื่อเชื่อมต่อสินค้าใหม่
            $stmt = $pdo->prepare("
                UPDATE purchase_order_items 
                SET product_id = ? 
                WHERE temp_product_id = ?
            ");
            $stmt->execute([$product_id, $temp_product_id]);

            // อัปเดต temp_products สถานะเป็น converted
            $convert_status = 'converted';
            $stmt = $pdo->prepare("
                UPDATE temp_products 
                SET status = ?, approved_by = ?, approved_at = NOW() 
                WHERE temp_product_id = ?
            ");
            $stmt->execute([$convert_status, $created_by, $temp_product_id]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'แปลงสินค้าเป็นถาวรสำเร็จ',
                'product_id' => $product_id
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    // Reject action
    if ($_POST['action'] === 'reject') {
        $temp_product_id = (int)$_POST['temp_product_id'];
        $reject_reason = trim($_POST['reason'] ?? '');

        try {
            $reject_status = 'rejected';
            $stmt = $pdo->prepare("
                UPDATE temp_products 
                SET status = ?, remark = ?, approved_by = ?, approved_at = NOW() 
                WHERE temp_product_id = ?
            ");
            $approved_by = $_SESSION['user_id'];
            $stmt->execute([$reject_status, $reject_reason, $approved_by, $temp_product_id]);

            echo json_encode([
                'success' => true,
                'message' => 'ปฏิเสธสินค้าเรียบร้อย'
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// GET - Display pending temp products
$sql = "
    SELECT 
        tp.*, 
        po.po_number, 
        po.po_date,
        s.name as supplier_name,
        u.user_name
    FROM temp_products tp
    JOIN purchase_orders po ON tp.po_id = po.po_id
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN users u ON tp.created_by = u.user_id
    WHERE tp.status IN ('pending_approval', 'draft')
    ORDER BY tp.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$pending_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติสินค้าใหม่</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/modern-sidebar.css">
    <link rel="stylesheet" href="../assets/modern-table.css">
    <style>
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        tbody tr:hover {
            background: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-draft {
            background: #95a5a6;
            color: white;
        }
        .badge-pending {
            background: #f39c12;
            color: white;
        }
        .btn-group-table {
            display: flex;
            gap: 5px;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-approve {
            background: #27ae60;
            color: white;
        }
        .btn-approve:hover {
            background: #229954;
        }
        .btn-reject {
            background: #e74c3c;
            color: white;
        }
        .btn-reject:hover {
            background: #c0392b;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #7f8c8d;
        }
        .close:hover {
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ อนุมัติและแปลงสินค้าใหม่</h1>
        <p style="color: #666;">สินค้าที่จำเป็นต้องอนุมัติจากใบ PO สำหรับสินค้าใหม่</p>

        <?php if (empty($pending_products)): ?>
            <div class="empty-state">
                <p>ไม่มีสินค้าที่รอการอนุมัติ 🎉</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>ประเภท</th>
                            <th>หน่วยนับ</th>
                            <th>ใบ PO</th>
                            <th>ซัพพลายเยอร์</th>
                            <th>สร้างโดย</th>
                            <th>สถานะ</th>
                            <th>การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_products as $product): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <?php if (!empty($product['product_image'])): ?>
                                        <img src="data:image/*;base64,<?php echo htmlspecialchars($product['product_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['product_category'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                <td><?php echo htmlspecialchars($product['po_number']); ?></td>
                                <td><?php echo htmlspecialchars($product['supplier_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($product['user_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $product['status']; ?>">
                                        <?php 
                                        $status_th = [
                                            'draft' => 'ร่าง',
                                            'pending_approval' => 'รอการอนุมัติ'
                                        ];
                                        echo $status_th[$product['status']] ?? $product['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group-table">
                                        <button class="btn btn-approve" onclick="openApproveModal(<?php echo $product['temp_product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>', '<?php echo htmlspecialchars($product['product_category'] ?? ''); ?>', '<?php echo htmlspecialchars($product['product_image'] ?? ''); ?>')">
                                            ✓ อนุมัติ
                                        </button>
                                        <button class="btn btn-reject" onclick="openRejectModal(<?php echo $product['temp_product_id']; ?>)">
                                            ✕ ปฏิเสธ
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal สำหรับอนุมัติ -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>อนุมัติและแปลงเป็นสินค้าถาวร</h3>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
            </div>
            <form id="approveForm">
                <input type="hidden" id="temp_product_id" name="temp_product_id">
                
                <div class="form-group" style="text-align: center;">
                    <img id="productImageModal" style="display:none; width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                </div>

                <div class="form-group">
                    <label>ชื่อสินค้า:</label>
                    <p id="productName" style="font-weight: 600; color: #2c3e50;"></p>
                </div>

                <div class="form-group">
                    <label>ประเภทสินค้า:</label>
                    <p id="productCategory" style="font-weight: 600; color: #2c3e50;"></p>
                </div>

                <div class="form-group">
                    <label for="sku">SKU (ถ้ามี)</label>
                    <input type="text" id="sku" name="sku" placeholder="เช่น PROD-001">
                </div>

                <div class="form-group">
                    <label for="barcode">Barcode (ถ้ามี)</label>
                    <input type="text" id="barcode" name="barcode" placeholder="เช่น 1234567890123">
                </div>

                <div class="form-group">
                    <label for="remark_color">สีหรือหมายเหตุ</label>
                    <input type="text" id="remark_color" name="remark_color" placeholder="เช่น สีแดง, ขนาดใหญ่">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="remark_split" name="remark_split" value="1">
                        สินค้าสามารถแยกออกมาเป็นหลายแถวได้
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-approve">✓ ยืนยันอนุมัติ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สำหรับปฏิเสธ -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ปฏิเสธการเพิ่มสินค้า</h3>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <form id="rejectForm">
                <input type="hidden" id="reject_temp_product_id" name="temp_product_id">
                
                <div class="form-group">
                    <label for="reject_reason">เหตุผลการปฏิเสธ</label>
                    <textarea id="reject_reason" name="reason" placeholder="ระบุเหตุผลการปฏิเสธ"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-reject">✕ ยืนยันปฏิเสธ</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApproveModal(tempProductId, productName, productCategory, productImage) {
            document.getElementById('temp_product_id').value = tempProductId;
            document.getElementById('productName').textContent = productName;
            document.getElementById('productCategory').textContent = productCategory || '-';
            
            // Display image if available
            const imgElement = document.getElementById('productImageModal');
            if (productImage) {
                imgElement.src = 'data:image/*;base64,' + productImage;
                imgElement.style.display = 'block';
            } else {
                imgElement.style.display = 'none';
            }
            
            // Clear form fields
            document.getElementById('sku').value = '';
            document.getElementById('barcode').value = '';
            document.getElementById('remark_color').value = '';
            document.getElementById('remark_split').checked = false;
            
            document.getElementById('approveModal').style.display = 'block';
        }

        function openRejectModal(tempProductId) {
            document.getElementById('reject_temp_product_id').value = tempProductId;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(e) {
            const approveModal = document.getElementById('approveModal');
            const rejectModal = document.getElementById('rejectModal');
            
            if (e.target === approveModal) approveModal.style.display = 'none';
            if (e.target === rejectModal) rejectModal.style.display = 'none';
        }

        document.getElementById('approveForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'approve_and_convert');
            formData.append('temp_product_id', document.getElementById('temp_product_id').value);
            formData.append('sku', document.getElementById('sku').value);
            formData.append('barcode', document.getElementById('barcode').value);
            formData.append('remark_color', document.getElementById('remark_color').value);
            formData.append('remark_split', document.getElementById('remark_split').checked ? 1 : 0);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('ข้อผิดพลาด: ' + result.message);
                }
            } catch (error) {
                alert('เกิดข้อผิดพลาด: ' + error.message);
            }
        });

        document.getElementById('rejectForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'reject');
            formData.append('temp_product_id', document.getElementById('reject_temp_product_id').value);
            formData.append('reason', document.getElementById('reject_reason').value);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('ข้อผิดพลาด: ' + result.message);
                }
            } catch (error) {
                alert('เกิดข้อผิดพลาด: ' + error.message);
            }
        });
    </script>
</body>
</html>
