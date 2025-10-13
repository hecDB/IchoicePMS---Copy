<?php
session_start();
require '../config/db_connect.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/combined_login_register.php");
    exit();
}

// ดึงข้อมูลรูปแบบแท็กทั้งหมด
$sql_patterns = "SELECT * FROM tag_patterns ORDER BY platform, created_at DESC";
$stmt_patterns = $pdo->query($sql_patterns);
$patterns = $stmt_patterns->fetchAll(PDO::FETCH_ASSOC);

// สรุปสถิติ
$stats = [
    'total_patterns' => count($patterns),
    'shopee_patterns' => count(array_filter($patterns, fn($p) => $p['platform'] === 'Shopee')),
    'lazada_patterns' => count(array_filter($patterns, fn($p) => $p['platform'] === 'Lazada')),
    'active_patterns' => count(array_filter($patterns, fn($p) => $p['is_active'] == 1))
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการรูปแบบเลขแท็ก - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link href="../assets/modern-table.css" rel="stylesheet">
    <link href="../assets/mainwrap-modern.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8fafc;
        }
        
        .pattern-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .pattern-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .pattern-card.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }
        
        .platform-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .platform-shopee {
            background: #ee4d2d;
            color: white;
        }
        
        .platform-lazada {
            background: #0f136d;
            color: white;
        }
        
        .regex-code {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #f1f5f9;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.85rem;
            border: 1px solid #e2e8f0;
        }
        
        .test-result {
            margin-top: 0.5rem;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .test-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .test-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .pattern-examples {
            background: #f8fafc;
            padding: 0.75rem;
            border-radius: 6px;
            margin-top: 0.5rem;
        }
        
        .example-tag {
            display: inline-block;
            background: white;
            padding: 0.25rem 0.5rem;
            margin: 0.125rem;
            border-radius: 4px;
            font-size: 0.8rem;
            border: 1px solid #d1d5db;
            font-family: monospace;
        }
        
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .pattern-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background: white;
            color: #6b7280;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-icon.btn-edit:hover {
            background: #dbeafe;
            color: #1d4ed8;
            border-color: #93c5fd;
        }
        
        .btn-icon.btn-delete:hover {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        
        .btn-icon.btn-toggle:hover {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }
        
        @media (max-width: 768px) {
            .pattern-card {
                padding: 1rem;
            }
            
            .pattern-actions {
                justify-content: center;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>

<?php include '../templates/sidebar.php'; ?>

<div class="mainwrap">
    <div class="container-fluid py-4">
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">label</span>
                    จัดการรูปแบบเลขแท็ก
                </h1>
                <p class="text-muted mb-0">กำหนดและจัดการรูปแบบเลขแท็กสำหรับแพลตฟอร์มต่างๆ</p>
            </div>
            <div>
                <button class="btn-modern btn-modern-primary" data-bs-toggle="modal" data-bs-target="#addPatternModal">
                    <span class="material-icons" style="font-size: 1.25rem;">add</span>
                    เพิ่มรูปแบบใหม่
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card stats-primary">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รูปแบบทั้งหมด</div>
                                <div class="stats-value"><?= $stats['total_patterns'] ?></div>
                                <div class="stats-subtitle">รูปแบบทั้งหมด</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">label</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card stats-shopee">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">Shopee</div>
                                <div class="stats-value"><?= $stats['shopee_patterns'] ?></div>
                                <div class="stats-subtitle">รูปแบบ Shopee</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">storefront</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card stats-lazada">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">Lazada</div>
                                <div class="stats-value"><?= $stats['lazada_patterns'] ?></div>
                                <div class="stats-subtitle">รูปแบบ Lazada</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">shopping_bag</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card stats-success">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">ใช้งานอยู่</div>
                                <div class="stats-value"><?= $stats['active_patterns'] ?></div>
                                <div class="stats-subtitle">รูปแบบที่เปิดใช้</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">check_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patterns List -->
        <div class="row">
            <?php if (empty($patterns)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <span class="material-icons mb-3" style="font-size: 4rem; color: #d1d5db;">label_off</span>
                        <h5 class="text-muted">ยังไม่มีรูปแบบเลขแท็ก</h5>
                        <p class="text-muted mb-3">เริ่มต้นโดยการเพิ่มรูปแบบเลขแท็กสำหรับแพลตฟอร์มต่างๆ</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatternModal">
                            <span class="material-icons me-2">add</span>
                            เพิ่มรูปแบบแรก
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($patterns as $pattern): ?>
                    <div class="col-xl-6 col-lg-6 mb-4">
                        <div class="pattern-card <?= $pattern['is_active'] ? '' : 'inactive' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="platform-badge platform-<?= strtolower($pattern['platform']) ?>">
                                            <?= htmlspecialchars($pattern['platform']) ?>
                                        </span>
                                        <?php if (!$pattern['is_active']): ?>
                                            <span class="badge bg-secondary ms-2">ปิดใช้งาน</span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="mb-1"><?= htmlspecialchars($pattern['pattern_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($pattern['description']) ?></small>
                                </div>
                                <div class="pattern-actions">
                                    <button class="btn-icon btn-edit" title="แก้ไข" 
                                            onclick="editPattern(<?= $pattern['pattern_id'] ?>)">
                                        <span class="material-icons" style="font-size: 1rem;">edit</span>
                                    </button>
                                    <button class="btn-icon btn-toggle" title="<?= $pattern['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>"
                                            onclick="togglePattern(<?= $pattern['pattern_id'] ?>, <?= $pattern['is_active'] ? 'false' : 'true' ?>)">
                                        <span class="material-icons" style="font-size: 1rem;">
                                            <?= $pattern['is_active'] ? 'toggle_on' : 'toggle_off' ?>
                                        </span>
                                    </button>
                                    <button class="btn-icon" title="ตรวจสอบการใช้งาน" 
                                            onclick="checkUsage(<?= $pattern['pattern_id'] ?>)">
                                        <span class="material-icons" style="font-size: 1rem;">analytics</span>
                                    </button>
                                    <button class="btn-icon btn-delete" title="ลบ" 
                                            onclick="deletePattern(<?= $pattern['pattern_id'] ?>)">
                                        <span class="material-icons" style="font-size: 1rem;">delete</span>
                                    </button>
                                </div>
                            </div>

                            <div class="regex-code mb-3">
                                <strong>Regular Expression:</strong><br>
                                <code><?= htmlspecialchars($pattern['regex_pattern']) ?></code>
                            </div>

                            <?php if (!empty($pattern['example_tags'])): ?>
                                <div class="pattern-examples">
                                    <strong class="text-muted">ตัวอย่างเลขแท็ก:</strong>
                                    <div class="mt-2">
                                        <?php 
                                        $examples = explode(',', $pattern['example_tags']);
                                        foreach ($examples as $example): 
                                        ?>
                                            <span class="example-tag"><?= htmlspecialchars(trim($example)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        สร้างเมื่อ: <?= date('d/m/Y H:i', strtotime($pattern['created_at'])) ?>
                                    </small>
                                    <button class="btn btn-sm btn-outline-primary" onclick="testPattern(<?= $pattern['pattern_id'] ?>)">
                                        <span class="material-icons me-1" style="font-size: 1rem;">play_arrow</span>
                                        ทดสอบ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไขรูปแบบ -->
<div class="modal fade" id="addPatternModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">add</span>
                    เพิ่มรูปแบบเลขแท็กใหม่
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="patternForm">
                    <input type="hidden" id="pattern_id" name="pattern_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="platform" class="form-label">แพลตฟอร์ม</label>
                            <select class="form-select" id="platform" name="platform" required>
                                <option value="">เลือกแพลตฟอร์ม</option>
                                <option value="Shopee">Shopee</option>
                                <option value="Lazada">Lazada</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="pattern_name" class="form-label">ชื่อรูปแบบ</label>
                            <input type="text" class="form-control" id="pattern_name" name="pattern_name" 
                                   placeholder="เช่น: Shopee Standard Format" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">คำอธิบาย</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                                  placeholder="อธิบายรูปแบบเลขแท็กนี้"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="regex_pattern" class="form-label">
                            Regular Expression Pattern
                            <span class="text-muted">(รูปแบบสำหรับตรวจสอบ)</span>
                        </label>
                        <input type="text" class="form-control" id="regex_pattern" name="regex_pattern"
                               placeholder="เช่น: ^SPE[0-9]{10}$" required>
                        <div class="form-text">
                            ใช้ Regular Expression เพื่อกำหนดรูปแบบเลขแท็ก 
                            <a href="#" data-bs-toggle="modal" data-bs-target="#regexHelpModal">ดูตัวอย่าง</a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="example_tags" class="form-label">ตัวอย่างเลขแท็ก</label>
                        <input type="text" class="form-control" id="example_tags" name="example_tags"
                               placeholder="SPE1234567890, SPE9876543210 (คั่นด้วยจุลภาค)">
                        <div class="form-text">ใส่ตัวอย่างเลขแท็กที่ตรงกับรูปแบบ คั่นด้วยจุลภาค</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                เปิดใช้งานรูปแบบนี้
                            </label>
                        </div>
                    </div>

                    <!-- Live Test Section -->
                    <div class="border-top pt-3">
                        <h6 class="mb-3">ทดสอบรูปแบบแบบทันที</h6>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="test_tag" placeholder="ใส่เลขแท็กเพื่อทดสอบ">
                            <button class="btn btn-outline-secondary" type="button" onclick="liveTest()">
                                <span class="material-icons">play_arrow</span>
                            </button>
                        </div>
                        <div id="live_test_result"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="savePattern()">
                    <span class="material-icons me-1">save</span>
                    บันทึก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal ทดสอบรูปแบบ -->
<div class="modal fade" id="testPatternModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">science</span>
                    ทดสอบรูปแบบเลขแท็ก
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="test_pattern_info"></div>
                <div class="mt-3">
                    <label for="bulk_test_tags" class="form-label">ใส่เลขแท็กเพื่อทดสอบ (คั่นด้วยบรรทัดใหม่)</label>
                    <textarea class="form-control" id="bulk_test_tags" rows="5" 
                              placeholder="SPE1234567890&#10;LAZ9876543210&#10;ABC123"></textarea>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="runBulkTest()">
                        <span class="material-icons me-1">play_arrow</span>
                        ทดสอบทั้งหมด
                    </button>
                </div>
                <div id="bulk_test_results" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal ตัวอย่าง Regex -->
<div class="modal fade" id="regexHelpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">help</span>
                    ตัวอย่าง Regular Expression
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>รูปแบบ</th>
                                <th>Regular Expression</th>
                                <th>คำอธิบาย</th>
                                <th>ตัวอย่าง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Shopee มาตรฐาน</td>
                                <td><code>^SPE[0-9]{10}$</code></td>
                                <td>ขึ้นต้นด้วย SPE ตามด้วยตัวเลข 10 หลัก</td>
                                <td>SPE1234567890</td>
                            </tr>
                            <tr>
                                <td>Lazada มาตรฐาน</td>
                                <td><code>^LAZ[0-9]{8,12}$</code></td>
                                <td>ขึ้นต้นด้วย LAZ ตามด้วยตัวเลข 8-12 หลัก</td>
                                <td>LAZ12345678</td>
                            </tr>
                            <tr>
                                <td>Shopee ใหม่</td>
                                <td><code>^[0-9]{12}SH$</code></td>
                                <td>ตัวเลข 12 หลัก ลงท้ายด้วย SH</td>
                                <td>123456789012SH</td>
                            </tr>
                            <tr>
                                <td>ตัวเลขล้วน</td>
                                <td><code>^[0-9]{10,15}$</code></td>
                                <td>ตัวเลข 10-15 หลัก</td>
                                <td>1234567890</td>
                            </tr>
                            <tr>
                                <td>ตัวอักษรผสม</td>
                                <td><code>^[A-Z]{2,3}[0-9]{8,10}$</code></td>
                                <td>ตัวอักษรใหญ่ 2-3 ตัว ตามด้วยตัวเลข 8-10 หลัก</td>
                                <td>AB12345678</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info">
                    <h6><span class="material-icons align-middle me-2">info</span>สัญลักษณ์ที่ใช้บ่อย</h6>
                    <ul class="mb-0">
                        <li><code>^</code> = เริ่มต้นสตริง</li>
                        <li><code>$</code> = สิ้นสุดสตริง</li>
                        <li><code>[0-9]</code> = ตัวเลข 0-9</li>
                        <li><code>[A-Z]</code> = ตัวอักษรใหญ่ A-Z</li>
                        <li><code>{n}</code> = จำนวน n ตัวพอดี</li>
                        <li><code>{n,m}</code> = จำนวน n ถึง m ตัว</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-shopee {
    background: linear-gradient(135deg, #ee4d2d 0%, #ff6b47 100%);
    color: white;
}

.stats-shopee .stats-icon {
    color: rgba(255, 255, 255, 0.8);
}

.stats-lazada {
    background: linear-gradient(135deg, #0f136d 0%, #1e40af 100%);
    color: white;
}

.stats-lazada .stats-icon {
    color: rgba(255, 255, 255, 0.8);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// เก็บข้อมูลรูปแบบปัจจุบันที่กำลังทดสอบ
let currentTestPattern = null;

// ฟังก์ชันบันทึกรูปแบบ
function savePattern() {
    const formData = new FormData(document.getElementById('patternForm'));
    
    // Validate form
    const platform = formData.get('platform');
    const patternName = formData.get('pattern_name');
    const regexPattern = formData.get('regex_pattern');
    
    if (!platform || !patternName || !regexPattern) {
        Swal.fire('กรุณากรอกข้อมูลให้ครบถ้วน', '', 'warning');
        return;
    }
    
    // ทดสอบ regex ก่อนบันทึก
    try {
        new RegExp(regexPattern);
    } catch (e) {
        Swal.fire('รูปแบบ Regular Expression ไม่ถูกต้อง', e.message, 'error');
        return;
    }
    
    Swal.fire({
        title: 'กำลังบันทึก...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => { Swal.showLoading(); }
    });
    
    $.ajax({
        url: '../api/tag_management_api.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกสำเร็จ',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        }
    });
}

// ฟังก์ชันแก้ไขรูปแบบ
function editPattern(patternId) {
    Swal.fire({
        title: 'กำลังโหลด...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => { Swal.showLoading(); }
    });
    
    $.ajax({
        url: '../api/tag_management_api.php',
        method: 'GET',
        data: { action: 'get', pattern_id: patternId },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                const pattern = response.data;
                
                // กรอกข้อมูลในฟอร์ม
                $('#pattern_id').val(pattern.pattern_id);
                $('#platform').val(pattern.platform);
                $('#pattern_name').val(pattern.pattern_name);
                $('#description').val(pattern.description);
                $('#regex_pattern').val(pattern.regex_pattern);
                $('#example_tags').val(pattern.example_tags);
                $('#is_active').prop('checked', pattern.is_active == 1);
                
                // เปลี่ยนหัวข้อ modal
                $('#addPatternModal .modal-title').html('<span class="material-icons align-middle me-2">edit</span>แก้ไขรูปแบบเลขแท็ก');
                
                $('#addPatternModal').modal('show');
            } else {
                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถโหลดข้อมูลได้', 'error');
        }
    });
}

// ฟังก์ชันเปิด/ปิดใช้งานรูปแบบ
function togglePattern(patternId, isActive) {
    const action = isActive ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
    
    Swal.fire({
        title: `ยืนยันการ${action}?`,
        text: `คุณต้องการ${action}รูปแบบนี้หรือไม่?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '../api/tag_management_api.php',
                method: 'PUT',
                data: {
                    action: 'toggle',
                    pattern_id: patternId,
                    is_active: isActive ? 1 : 0
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: `${action}สำเร็จ`,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                }
            });
        }
    });
}

// ฟังก์ชันลบรูปแบบ
function deletePattern(patternId) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        html: `
            <p>คุณต้องการลบรูปแบบนี้หรือไม่?</p>
            <div class="alert alert-warning mt-2">
                <small><i class="material-icons" style="font-size: 1rem;">warning</i> 
                การลบจะไม่สามารถยกเลิกได้ และจะตรวจสอบการใช้งานก่อน</small>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc3545',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: '../api/tag_management_api.php?pattern_id=' + patternId,
                    method: 'DELETE',
                    data: JSON.stringify({ pattern_id: patternId }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMsg = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMsg = response.message;
                            }
                        } catch (e) {
                            // ใช้ error message เดิม
                        }
                        reject(errorMsg);
                    }
                });
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'ลบสำเร็จ',
                text: 'รูปแบบเลขแท็กถูกลบแล้ว',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    }).catch((error) => {
        Swal.fire({
            icon: 'error',
            title: 'ไม่สามารถลบได้',
            text: error,
            confirmButtonText: 'เข้าใจแล้ว'
        });
    });
}

// ฟังก์ชันทดสอบรูปแบบ
function testPattern(patternId) {
    currentTestPattern = patternId;
    
    $.ajax({
        url: '../api/tag_management_api.php',
        method: 'GET',
        data: { action: 'get', pattern_id: patternId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const pattern = response.data;
                $('#test_pattern_info').html(`
                    <div class="alert alert-info">
                        <h6><strong>${pattern.pattern_name}</strong> (${pattern.platform})</h6>
                        <p class="mb-1"><strong>รูปแบบ:</strong> <code>${pattern.regex_pattern}</code></p>
                        <p class="mb-0"><strong>คำอธิบาย:</strong> ${pattern.description || 'ไม่มีคำอธิบาย'}</p>
                    </div>
                `);
                $('#bulk_test_tags').val(pattern.example_tags ? pattern.example_tags.replace(/,/g, '\n') : '');
                $('#bulk_test_results').html('');
                $('#testPatternModal').modal('show');
            }
        }
    });
}

// ฟังก์ชันทดสอบหลายเลขแท็กพร้อมกัน
function runBulkTest() {
    const tags = $('#bulk_test_tags').val().split('\n').filter(tag => tag.trim());
    
    if (tags.length === 0) {
        Swal.fire('กรุณาใส่เลขแท็กเพื่อทดสอบ', '', 'warning');
        return;
    }
    
    $.ajax({
        url: '../api/tag_management_api.php',
        method: 'POST',
        data: {
            action: 'test',
            pattern_id: currentTestPattern,
            test_tags: tags
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayBulkTestResults(response.results);
            } else {
                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
            }
        }
    });
}

// แสดงผลการทดสอบ
function displayBulkTestResults(results) {
    let html = '<div class="border rounded p-3 bg-light"><h6>ผลการทดสอบ:</h6>';
    let passCount = 0;
    let failCount = 0;
    
    results.forEach(result => {
        if (result.valid) {
            passCount++;
            html += `<div class="test-success mb-1">✓ ${result.tag} - ผ่าน</div>`;
        } else {
            failCount++;
            html += `<div class="test-error mb-1">✗ ${result.tag} - ไม่ผ่าน</div>`;
        }
    });
    
    html += `<div class="mt-2 text-muted">สรุป: ผ่าน ${passCount} รายการ, ไม่ผ่าน ${failCount} รายการ</div></div>`;
    $('#bulk_test_results').html(html);
}

// ทดสอบแบบทันที
function liveTest() {
    const testTag = $('#test_tag').val().trim();
    const regexPattern = $('#regex_pattern').val().trim();
    
    if (!testTag || !regexPattern) {
        $('#live_test_result').html('<div class="test-error">กรุณาใส่เลขแท็กและรูปแบบ</div>');
        return;
    }
    
    try {
        const regex = new RegExp(regexPattern);
        const isValid = regex.test(testTag);
        
        if (isValid) {
            $('#live_test_result').html('<div class="test-success">✓ เลขแท็กตรงกับรูปแบบ</div>');
        } else {
            $('#live_test_result').html('<div class="test-error">✗ เลขแท็กไม่ตรงกับรูปแบบ</div>');
        }
    } catch (e) {
        $('#live_test_result').html('<div class="test-error">รูปแบบ Regular Expression ไม่ถูกต้อง</div>');
    }
}

// รีเซ็ตฟอร์มเมื่อปิด modal
$('#addPatternModal').on('hidden.bs.modal', function () {
    document.getElementById('patternForm').reset();
    $('#pattern_id').val('');
    $('#live_test_result').html('');
    $('#addPatternModal .modal-title').html('<span class="material-icons align-middle me-2">add</span>เพิ่มรูปแบบเลขแท็กใหม่');
});

// ฟังก์ชันตรวจสอบการใช้งานรูปแบบ
function checkUsage(patternId) {
    Swal.fire({
        title: 'กำลังตรวจสอบ...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => { Swal.showLoading(); }
    });
    
    $.ajax({
        url: '../api/tag_management_api.php',
        method: 'GET',
        data: { action: 'check_usage', pattern_id: patternId },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                const usageCount = response.usage_count || 0;
                const examples = response.examples || [];
                
                let examplesHtml = '';
                if (examples.length > 0) {
                    examplesHtml = '<div class="mt-3"><h6>ตัวอย่างการใช้งาน:</h6><ul class="list-group list-group-flush">';
                    examples.forEach(example => {
                        examplesHtml += `<li class="list-group-item d-flex justify-content-between">
                            <span class="font-monospace">${example.issue_tag}</span>
                            <small class="text-muted">${example.sale_date}</small>
                        </li>`;
                    });
                    examplesHtml += '</ul></div>';
                }
                
                Swal.fire({
                    title: 'สถิติการใช้งาน',
                    html: `
                        <div class="text-center">
                            <div class="alert ${usageCount > 0 ? 'alert-info' : 'alert-success'}">
                                <h4><i class="material-icons" style="font-size: 2rem;">${usageCount > 0 ? 'analytics' : 'check_circle'}</i></h4>
                                <h5>${usageCount > 0 ? `มีการใช้งาน ${usageCount} รายการ` : 'ไม่มีการใช้งาน'}</h5>
                                <p class="mb-0">${usageCount > 0 ? 'รูปแบบนี้ถูกใช้งานในระบบ' : 'รูปแบบนี้ยังไม่ถูกใช้งาน สามารถลบได้'}</p>
                            </div>
                            ${examplesHtml}
                        </div>
                    `,
                    confirmButtonText: 'เข้าใจแล้ว',
                    width: '600px'
                });
            } else {
                Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถตรวจสอบการใช้งานได้', 'error');
        }
    });
}

// ทดสอบแบบทันทีเมื่อพิมพ์
$('#regex_pattern, #test_tag').on('input', function() {
    if ($('#regex_pattern').val().trim() && $('#test_tag').val().trim()) {
        liveTest();
    } else {
        $('#live_test_result').html('');
    }
});
</script>

</body>
</html>