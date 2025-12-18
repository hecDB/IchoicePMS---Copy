<?php
/**
 * ตรวจสอบการทำงานของระบบบันทึกข้อมูล receive_id และ expiry_date
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบระบบสินค้าตีกลับ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
            font-weight: 600;
        }
        .card-body {
            padding: 1.5rem;
        }
        .feature-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .feature-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .status-complete {
            background: #d1fae5;
            color: #065f46;
        }
        .status-active {
            background: #dbeafe;
            color: #0c4a6e;
        }
        .alert-custom {
            border: none;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="text-center text-white mb-4">
            <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                📦 ระบบสินค้าตีกลับ
            </h1>
            <p style="font-size: 1.1rem; opacity: 0.9;">
                ปรับปรุงการบันทึกลัตชุด (receive_id) และวันหมดอายุ
            </p>
        </div>

        <!-- Overview -->
        <div class="card">
            <div class="card-header">
                ✅ ระบบถูกปรับปรุงแล้ว
            </div>
            <div class="card-body">
                <div class="alert alert-custom alert-success">
                    <h5 class="alert-heading">🎉 ยินดีด้วย! ระบบพร้อมใช้งาน</h5>
                    <p>ได้เพิ่มการบันทึกข้อมูลสำคัญสำหรับการติดตามลัตชุด (receive_id) และวันหมดอายุของสินค้าตีกลับแล้ว</p>
                </div>

                <h5 class="mt-4 mb-3">📋 ส่วนประกอบของระบบ:</h5>
                
                <div class="feature-item">
                    <span class="feature-icon">📍</span>
                    <strong>receive_id (ลัตชุด/ชุดสินค้า)</strong>
                    <br>
                    <small class="text-muted">บันทึกว่าสินค้าออกมาจากลัตชุดไหน เพื่อติดตามและวิเคราะห์ปัญหาคุณภาพ</small>
                </div>

                <div class="feature-item">
                    <span class="feature-icon">📅</span>
                    <strong>expiry_date (วันหมดอายุ)</strong>
                    <br>
                    <small class="text-muted">บันทึกวันหมดอายุของสินค้า เพื่อวางแผนการใช้สินค้าและติดตามความสดใหม่</small>
                </div>

                <div class="feature-item">
                    <span class="feature-icon">🔗</span>
                    <strong>ลิงก์ข้อมูลระหว่างตาราง</strong>
                    <br>
                    <small class="text-muted">เชื่อมโยงข้อมูล issue_items → returned_items → damaged_return_inspections</small>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div class="card">
            <div class="card-header">
                ✨ ฟีเจอร์ที่เพิ่มเข้ามา
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="feature-item">
                            <h6>📊 ตารางที่อัปเดต</h6>
                            <ul class="mb-0" style="padding-left: 1.2rem;">
                                <li>returned_items (+ receive_id)</li>
                                <li>damaged_return_inspections (+ receive_id)</li>
                                <li>issue_items (+ expiry_date)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="feature-item">
                            <h6>🔄 การบันทึกข้อมูล</h6>
                            <ul class="mb-0" style="padding-left: 1.2rem;">
                                <li>จับ receive_id จาก issue_items</li>
                                <li>บันทึก expiry_date</li>
                                <li>บันทึกทั้งการตีกลับปกติและชำรุด</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <h5 class="mt-4 mb-3">🎯 ประโยชน์:</h5>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <span class="status-badge status-complete">✓ ติดตามลัตชุด</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <span class="status-badge status-complete">✓ วิเคราะห์คุณภาพ</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <span class="status-badge status-complete">✓ จัดการหมดอายุ</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Flow -->
        <div class="card">
            <div class="card-header">
                🔄 ลำดับการบันทึกข้อมูล
            </div>
            <div class="card-body">
                <div style="background: white; padding: 2rem; border-radius: 8px; border: 2px dashed #667eea;">
                    <div class="text-center mb-4">
                        <div style="display: inline-block; background: #e0e7ff; padding: 1rem; border-radius: 8px; min-width: 200px;">
                            <strong>ค้นหา Sales Order</strong><br>
                            <small>(issue_tag)</small>
                        </div>
                    </div>

                    <div class="text-center mb-4">↓</div>

                    <div class="text-center mb-4">
                        <div style="display: inline-block; background: #e0e7ff; padding: 1rem; border-radius: 8px; min-width: 200px;">
                            <strong>ดึงข้อมูล issue_items</strong><br>
                            <small>receive_id + expiry_date</small>
                        </div>
                    </div>

                    <div class="text-center mb-4">↓</div>

                    <div class="text-center mb-4">
                        <div style="display: inline-block; background: #d1fae5; padding: 1rem; border-radius: 8px; min-width: 200px;">
                            <strong>บันทึกใน returned_items</strong><br>
                            <small>✓ receive_id ✓ expiry_date</small>
                        </div>
                    </div>

                    <div class="text-center mb-4">↓</div>

                    <div class="text-center">
                        <div style="display: inline-block; background: #e0e7ff; padding: 1rem; border-radius: 8px; min-width: 250px;">
                            <strong>ถ้าชำรุด → สร้าง Inspection</strong><br>
                            <small>damaged_return_inspections (+ receive_id + expiry_date)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Testing -->
        <div class="card">
            <div class="card-header">
                🧪 วิธีการทดสอบ
            </div>
            <div class="card-body">
                <h5 class="mb-3">ขั้นตอนที่ 1: ตรวจสอบโครงสร้าง</h5>
                <div class="alert alert-custom alert-info">
                    <code>เปิดไฟล์: test_receive_id_capture.php</code>
                    <br>
                    <small>จะแสดงว่าคอลัมน์ receive_id ถูกเพิ่มเข้ามา</small>
                </div>

                <h5 class="mt-4 mb-3">ขั้นตอนที่ 2: ทดสอบการบันทึก</h5>
                <ol>
                    <li>เปิด <code>returns/return_items.php</code></li>
                    <li>ค้นหา Sales Order ที่มีข้อมูล receive_id</li>
                    <li>สร้างการตีกลับสินค้า</li>
                    <li>ตรวจสอบว่า receive_id ถูกบันทึก</li>
                </ol>

                <h5 class="mt-4 mb-3">ขั้นตอนที่ 3: ตรวจสอบฐานข้อมูล</h5>
                <div class="alert alert-custom alert-secondary">
                    <code>
SELECT return_id, return_code, receive_id, expiry_date<br>
FROM returned_items<br>
WHERE return_from_sales = 1<br>
LIMIT 5;
                    </code>
                </div>
            </div>
        </div>

        <!-- Files -->
        <div class="card">
            <div class="card-header">
                📁 ไฟล์ที่เกี่ยวข้อง
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ไฟล์</th>
                            <th>การเปลี่ยนแปลง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>api/returned_items_api.php</code></td>
                            <td>เพิ่ม receive_id capture, update expiry_date handling</td>
                        </tr>
                        <tr>
                            <td><code>test_receive_id_capture.php</code></td>
                            <td>ไฟล์ทดสอบใหม่</td>
                        </tr>
                        <tr>
                            <td><code>RECEIVE_ID_TRACKING_UPDATE.md</code></td>
                            <td>เอกสารรายละเอียด</td>
                        </tr>
                        <tr>
                            <td><code>RETURN_SYSTEM_SUMMARY.md</code></td>
                            <td>สรุประบบ</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-white mt-5">
            <p style="font-size: 0.95rem; opacity: 0.8;">
                ✨ ระบบบันทึกสินค้าตีกลับได้รับการปรับปรุงแล้ว | 
                ติดตามลัตชุด (receive_id) และวันหมดอายุ (expiry_date)
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
