<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>🎯 ศูนย์บันทึกสินค้าตีกลับ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Sarabun', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 2rem; }
        
        .main-container { 
            max-width: 1200px; 
            background: white; 
            border-radius: 20px; 
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .content { padding: 3rem 2rem; }
        
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .btn-card {
            display: block;
            padding: 1rem;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-weight: 500;
            text-align: center;
            margin-top: 1rem;
            transition: all 0.3s;
        }
        
        .btn-primary-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-primary-card:hover { transform: translateX(5px); color: white; }
        
        .btn-info-card { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .btn-info-card:hover { transform: translateX(5px); color: white; }
        
        .btn-success-card { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .btn-success-card:hover { transform: translateX(5px); color: white; }
        
        .btn-warning-card { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .btn-warning-card:hover { transform: translateX(5px); color: white; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
        
        .section { margin-bottom: 3rem; }
        
        .section h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 0.75rem 0;
            padding-left: 2rem;
            position: relative;
        }
        
        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .alert-banner {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .footer {
            background: #f3f4f6;
            padding: 2rem;
            text-align: center;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <h1>📦 ศูนย์บันทึกสินค้าตีกลับ</h1>
            <p style="font-size: 1.1rem; margin: 0; opacity: 0.95;">ระบบบันทึกและจัดการสินค้าตีกลับจากลูกค้า</p>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Alert -->
            <div class="alert-banner">
                <strong>⚠️ สำคัญ:</strong> ต้องสร้างตารางฐานข้อมูลก่อนใช้งาน
            </div>

            <!-- Getting Started -->
            <div class="section">
                <h2>🚀 เริ่มต้นอย่างรวดเร็ว (3 ขั้นตอน)</h2>
                
                <div class="grid">
                    <!-- Step 1 -->
                    <div class="card">
                        <div class="card-body">
                            <div class="card-icon">⚙️</div>
                            <h5 class="card-title">ขั้นตอนที่ 1: ตั้งค่าฐานข้อมูล</h5>
                            <p class="card-text">สร้างตารางและข้อมูลเริ่มต้น</p>
                            <a href="setup_return_items_table.php" class="btn-card btn-primary-card" target="_blank">
                                🔧 สร้างตารางฐานข้อมูล
                            </a>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="card">
                        <div class="card-body">
                            <div class="card-icon">📝</div>
                            <h5 class="card-title">ขั้นตอนที่ 2: บันทึกสินค้า</h5>
                            <p class="card-text">บันทึกการตีกลับสินค้าจากลูกค่า</p>
                            <a href="returns/return_items.php" class="btn-card btn-success-card" target="_blank">
                                ➕ บันทึกสินค้าตีกลับ
                            </a>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="card">
                        <div class="card-body">
                            <div class="card-icon">📊</div>
                            <h5 class="card-title">ขั้นตอนที่ 3: จัดการ</h5>
                            <p class="card-text">อนุมัติและติดตามสินค้าตีกลับ</p>
                            <a href="returns/return_dashboard.php" class="btn-card btn-info-card" target="_blank">
                                📈 ไปยังแดชบอร์ด
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="section">
                <h2>✨ ฟีเจอร์หลัก</h2>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-primary mb-3">🎯 การบันทึก</h5>
                        <ul class="feature-list">
                            <li>ค้นหา PO ตามเลขที่ PO หรือเลขที่ออก</li>
                            <li>ตรวจสอบรายการสินค้าในใบสั่งซื้อ</li>
                            <li>เลือกสินค้าที่ต้องการตีกลับ</li>
                            <li>กรอกจำนวนและเหตุผล</li>
                            <li>บันทึกหมายเหตุเพิ่มเติม</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-success mb-3">📊 การจัดการ</h5>
                        <ul class="feature-list">
                            <li>ดูแดชบอร์ดสรุปข้อมูล</li>
                            <li>ค้นหาและกรองตามสถานะ</li>
                            <li>ดูรายละเอียดการตีกลับ</li>
                            <li>อนุมัติการตีกลับ</li>
                            <li>ปฏิเสธพร้อมเหตุผล</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Return Types -->
            <div class="section">
                <h2>🏷️ ประเภทการตีกลับ</h2>
                
                <div class="row">
                    <!-- Returnable -->
                    <div class="col-md-6 mb-3">
                        <div class="card border-success" style="border-left: 5px solid #10b981 !important;">
                            <div class="card-body">
                                <h5 class="text-success">✓ สามารถคืนสต็อก</h5>
                                <p class="text-muted">สินค้าสามารถนำกลับเข้าสต็อกได้</p>
                                <ul class="feature-list" style="margin-bottom: 0;">
                                    <li>จัดส่งไม่สำเร็จ</li>
                                    <li>ยกเลิกคำสั่งซื้อ</li>
                                    <li>ลูกค้าปฏิเสธรับสินค้า</li>
                                    <li>ส่งผิด</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Non-Returnable -->
                    <div class="col-md-6 mb-3">
                        <div class="card border-danger" style="border-left: 5px solid #ef4444 !important;">
                            <div class="card-body">
                                <h5 class="text-danger">✗ ไม่สามารถคืนสต็อก</h5>
                                <p class="text-muted">สินค้าเก็บข้อมูลเฉพาะไม่สามารถคืน</p>
                                <ul class="feature-list" style="margin-bottom: 0;">
                                    <li>ชำรุด / เสียหาย</li>
                                    <li>สินค้าปลอม</li>
                                    <li>อื่นๆ</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documentation -->
            <div class="section">
                <h2>📚 เอกสารและความช่วยเหลือ</h2>
                
                <div class="grid">
                    <div class="card">
                        <div class="card-body">
                            <div class="card-icon">🚀</div>
                            <h5 class="card-title">Quick Start Guide</h5>
                            <p class="card-text">คู่มือเริ่มต้นแบบรวดเร็ว ครอบคลุมทุกขั้นตอน</p>
                            <a href="returns/QUICKSTART.php" class="btn-card btn-warning-card" target="_blank">
                                📖 อ่านคู่มือ
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="card-icon">📖</div>
                            <h5 class="card-title">Documentation</h5>
                            <p class="card-text">เอกสารโดยละเอียด API และตาราง</p>
                            <a href="returns/RETURN_SYSTEM_DOCUMENTATION.md" class="btn-card btn-info-card" target="_blank">
                                📋 อ่านเอกสาร
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="card-icon">🎯</div>
                            <h5 class="card-title">System Overview</h5>
                            <p class="card-text">สรุประบบและฟีเจอร์ทั้งหมด</p>
                            <a href="returns/README.md" class="btn-card btn-primary-card" target="_blank">
                                📝 ดูสรุป
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Workflow -->
            <div class="section">
                <h2>🔄 สถานะและขั้นตอน</h2>
                
                <div style="background: #f3f4f6; padding: 2rem; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                        <div style="text-align: center; flex: 1; min-width: 150px;">
                            <div style="background: #fef3c7; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                                <span style="font-size: 2rem;">📝</span><br>
                                <strong>pending</strong><br>
                                <small>รอการอนุมัติ</small>
                            </div>
                        </div>
                        
                        <div style="flex: 1; text-align: center;">➡️</div>
                        
                        <div style="text-align: center; flex: 1; min-width: 150px;">
                            <div style="background: #dbeafe; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                                <span style="font-size: 2rem;">✅</span><br>
                                <strong>approved</strong><br>
                                <small>อนุมัติแล้ว</small>
                            </div>
                        </div>
                        
                        <div style="flex: 1; text-align: center;">➡️</div>
                        
                        <div style="text-align: center; flex: 1; min-width: 150px;">
                            <div style="background: #d1fae5; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                                <span style="font-size: 2rem;">🎉</span><br>
                                <strong>completed</strong><br>
                                <small>เสร็จสิ้น</small>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 1rem; color: #ef4444;">
                        <strong>❌ หรือปฏิเสธ (rejected)</strong>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="section">
                <h2>🔗 ลิงก์ด่วน</h2>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="setup_return_items_table.php" class="btn btn-lg btn-outline-primary w-100 py-3" target="_blank">
                            <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">settings</span>
                            Setup Database
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="returns/return_items.php" class="btn btn-lg btn-outline-success w-100 py-3" target="_blank">
                            <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">add_circle</span>
                            Record Return
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="returns/return_dashboard.php" class="btn btn-lg btn-outline-info w-100 py-3" target="_blank">
                            <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">dashboard</span>
                            Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Support -->
            <div class="alert alert-info" role="alert">
                <h5>💡 ต้องการความช่วยเหลือ?</h5>
                <ul class="mb-0">
                    <li>อ่าน <strong>Quick Start Guide</strong> เพื่อเริ่มต้น</li>
                    <li>ดู <strong>Documentation</strong> สำหรับรายละเอียด</li>
                    <li>ตรวจสอบ Browser Console สำหรับข้อผิดพลาด</li>
                    <li>ติดต่อผู้ดูแลระบบหากพบปัญหา</li>
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>ระบบสินค้าตีกลับ (Return Items System)</strong></p>
            <p style="margin-bottom: 0.5rem; color: #9ca3af;">เวอร์ชัน 1.0.0 | IchoicePMS</p>
            <p style="margin: 0; font-size: 0.9rem;">© 2025 All rights reserved</p>
        </div>
    </div>
</body>
</html>
