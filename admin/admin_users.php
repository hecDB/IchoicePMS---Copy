<?php
session_start();

require_once '../config/db_connect.php';

if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

// อนุมัติผู้ใช้
if (isset($_GET['approve_id'])) {
    $uid = intval($_GET['approve_id']);
    $stmt = $pdo->prepare("UPDATE users SET is_approved=1, status='approved' WHERE user_id=?");
    if ($stmt->execute([$uid])) { $_SESSION['approve_success'] = 1; }
    header("Location: admin_users.php");
    exit;
}

// ปฏิเสธผู้ใช้
if (isset($_GET['reject_id'])) {
    $reject_id = intval($_GET['reject_id']);
    $pdo->prepare("UPDATE users SET is_approved=0, status='rejected' WHERE user_id=?")->execute([$reject_id]);
    header('Location: admin_users.php?msg=rejected');
    exit;
}

// ดึงข้อมูลผู้ใช้
$pending_users = $pdo->query("SELECT * FROM users WHERE status='pending' ORDER BY created_at ASC")->fetchAll();
$approved_users = $pdo->query("SELECT * FROM users WHERE status='approved' ORDER BY created_at DESC")->fetchAll();

function formatDate($dt) { return date('d/m/Y H:i', strtotime($dt)); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">

<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../assets/base.css">
<link rel="stylesheet" href="../assets/sidebar.css">
<link rel="stylesheet" href="../assets/components.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


 <style>
            /* Modern Tab Styling */
            .tabs {
                display: flex;
                gap: 4px;
                margin: 20px 0 30px 0;
                padding: 6px;
                background: #f8f9fa;
                border-radius: 14px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                max-width: fit-content;
            }

            .tabbtn {
                padding: 12px 24px;
                border: none;
                border-radius: 10px;
                background: transparent;
                color: #6c757d;
                font-weight: 500;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                white-space: nowrap;
            }

            .tabbtn:hover {
                background: #e9ecef;
                color: #495057;
                transform: translateY(-1px);
            }
            
            .tabbtn.active {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                transform: translateY(-2px);
            }

            /* Tab Content */
            .tabcontent { 
                display: none; 
                animation: fadeIn 0.3s ease-in;
            }
            .tabcontent.active { 
                display: block; 
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

                /* Modern Card Layout */
                .card-sec { 
                    display: grid; 
                    gap: 24px; 
                    margin-top: 30px;
                    grid-template-columns: 1fr;
                }

                .card { 
                    background: #ffffff;
                    border-radius: 20px;
                    padding: 28px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
                    border: 1px solid rgba(0,0,0,0.05);
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }

                .card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }

                .card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 15px 50px rgba(0,0,0,0.12);
                }

                .card-title { 
                    font-weight: 700; 
                    margin-bottom: 20px; 
                    display: flex; 
                    align-items: center; 
                    gap: 12px;
                    color: #2d3748;
                    font-size: 18px;
                }

                .card-title .material-icons {
                    font-size: 24px;
                    padding: 8px;
                    border-radius: 10px;
                    background: rgba(102, 126, 234, 0.1);
                }

                /* Special styling for reset password title */
                .reset-title-container {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 16px 20px;
                    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
                    border: 2px solid #10b981;
                    border-radius: 12px;
                    margin-bottom: 20px;
                    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
                }

                .reset-title-container .check-icon {
                    width: 28px;
                    height: 28px;
                    background: #10b981;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 18px;
                }

                .reset-title-container .title-text {
                    font-size: 18px;
                    font-weight: 700;
                    color: #047857;
                    margin: 0;
                }

                .reset-title-container .count-badge {
                    background: #059669;
                    color: white;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 600;
                    margin-left: auto;
                }

                /* Enhanced User List */
                .user-list { 
                    display: flex; 
                    flex-direction: column; 
                    gap: 16px; 
                }

                .usercard { 
                    display: flex; 
                    align-items: center; 
                    justify-content: space-between; 
                    padding: 20px 24px; 
                    border: 1px solid #e2e8f0;
                    border-radius: 16px; 
                    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }

                .usercard::before {
                    content: '';
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    width: 4px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    transform: translateX(-4px);
                    transition: transform 0.3s ease;
                }

                .usercard:hover::before {
                    transform: translateX(0);
                }

                .usercard:hover { 
                    background: linear-gradient(145deg, #f8fafc 0%, #ffffff 100%);
                    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
                    transform: translateY(-2px);
                    border-color: #cbd5e0;
                }

                .user-info {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                }

                .user-info > div:first-child {
                    font-weight: 600;
                    font-size: 16px;
                    color: #2d3748;
                }

                .user-info small {
                    color: #718096;
                    font-size: 13px;
                }

                .card-empty {
                    text-align: center;
                    padding: 40px 20px;
                    color: #a0aec0;
                    font-style: italic;
                    background: #f7fafc;
                    border-radius: 12px;
                    border: 2px dashed #e2e8f0;
                }
                /* Enhanced Status Badges */
                .status-badge { 
                    padding: 6px 12px; 
                    border-radius: 20px; 
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .status-pending {
                    background: linear-gradient(135deg, #fed7a1 0%, #ffc947 100%);
                    color: #8b4513;
                }

                .status-approved {
                    background: linear-gradient(135deg, #9decf9 0%, #06b6d4 100%);
                    color: #0c4a6e;
                }

                /* Modern Action Buttons */
                .action-btn { 
                    padding: 10px 18px; 
                    border-radius: 12px; 
                    text-decoration: none; 
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer; 
                    transition: all 0.3s ease;
                    border: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }

                .action-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }

                .action-btn.reject { 
                    background: linear-gradient(135deg, #fecaca 0%, #ef4444 100%);
                    color: #7f1d1d;
                }

                .action-btn.btn-approve { 
                    background: linear-gradient(135deg, #bbf7d0 0%, #10b981 100%);
                    color: #064e3b;
                }

                .action-btns {
                    display: flex;
                    gap: 8px;
                }

                /* Reset card */
                /* กรอบใหญ่ครอบ reset-card */
                .reset-container {
                    background: #f7f9ff;          /* พื้นอ่อนด้านหลัง */
                    border-radius: 20px;          /* ขอบโค้งใหญ่ */
                    padding: 20px;                /* ระยะห่างด้านใน */
                    box-shadow: 0 8px 24px rgba(0,0,0,0.08); /* เงาใหญ่รอบๆ */
                }

                /* reset-card เดิมยังคงเป็นการ์ดภายใน */
                .reset-card {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 16px;
                    background: #ffffff;
                    border-radius: 16px;
                    border: 1px solid #f0f0f0;
                    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
                    transition: all 0.25s;
                    margin-bottom: 12px;
                }

                .reset-card:hover {
                    box-shadow: 0 8px 28px rgba(0,0,0,0.12);
                    }
                    /* รายละเอียดผู้ใช้ใน reset card */
                    .reset-card .uc-details {
                        display: flex;
                        flex-direction: column;
                        gap: 4px;
                    }

                    /* ชื่อผู้ใช้ */
                    .reset-card .name {
                        font-weight: 600;
                        font-size: 15px;
                        color: #333;
                    }

                    /* ข้อมูลรอง เช่น email, department */
                    .reset-card .meta {
                        font-size: 13px;
                        color: #666;
                    }

                    /* Badge สถานะ */
                    .reset-card .status-badge.status-pending {
                        background: #fff3cd;
                        color: #856404;
                        font-size: 11px;
                        padding: 3px 8px;
                        border-radius: 8px;
                        margin-left: 6px;
                    }

                    /* ปุ่มดำเนินการรีเซ็ต */
                    .reset-card .action-btn.process {
                        background: #ffc107;
                        color: #fff;
                        padding: 8px 16px;
                        border-radius: 10px;
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        cursor: pointer;
                        transition: all 0.2s;
                    }
                    .reset-card .action-btn.process:hover {
                        background: #e0a800;
                    }



                /* reset request specific */
                        .btn-reset {
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            background: #ffc107;
                            color: #fff !important;
                            font-weight: 600;
                            border: none;
                            outline: none;
                            padding: 9px 22px;
                            border-radius: 10px;
                            font-size: 15px;
                            box-shadow: 0 2px 8px rgba(255,193,7,0.08), 0 1.5px 4px rgba(0,0,0,0.05);
                            cursor: pointer;
                            transition: background 0.18s, box-shadow 0.18s, transform 0.12s;
                        }
                        .btn-reset:hover, .btn-reset:focus {
                            background: #e0a800;
                            box-shadow: 0 4px 16px rgba(255,193,7,0.12), 0 4px 18px rgba(220,148,0,0.09);
                            transform: translateY(-2px) scale(1.04);
                        }
                        .btn-reset .material-icons {
                            font-size: 21px;
                            margin-right: 2px;
                        }   

                /* Enhanced Action Icons */
                .usercard .actions {
                    display: flex;
                    gap: 12px;
                }

                .usercard .actions a {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 42px;
                    height: 42px;
                    border-radius: 12px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }

                .usercard .actions a::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: inherit;
                    filter: brightness(1.1);
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .usercard .actions a:hover::before {
                    opacity: 1;
                }

                .usercard .actions a .material-icons {
                    font-size: 20px;
                    color: #fff;
                    position: relative;
                    z-index: 1;
                }

                .btn-edit {
                    background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
                }
                
                .btn-edit:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
                }

                .btn-delete {
                    background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
                    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
                }
                
                .btn-delete:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
                }
                                
            /* Responsive Design */
            @media (max-width: 768px) {
                .tabs {
                    flex-direction: column;
                    gap: 8px;
                    width: 100%;
                }
                
                .tabbtn {
                    width: 100%;
                    text-align: center;
                }
                
                .usercard {
                    flex-direction: column;
                    gap: 15px;
                    align-items: flex-start;
                }
                
                .usercard .actions {
                    align-self: flex-end;
                }
                
                .action-btns {
                    width: 100%;
                    justify-content: flex-end;
                }
                
                .card {
                    padding: 20px;
                    margin: 0 10px;
                }
            }

            @media (max-width: 480px) {
                .card {
                    padding: 16px;
                    margin: 0 8px;
                }
                
                .usercard .actions a {
                    width: 36px;
                    height: 36px;
                }
                
                .action-btn {
                    padding: 8px 14px;
                    font-size: 12px;
                }
            }

            /* Loading Animation */
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }

            .loading {
                animation: pulse 1.5s ease-in-out infinite;
            }

            /* Success/Error States */
            .success-state {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
            }

            .error-state {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                color: white;
            }
                        
</style>
</head>
<body>

<?php if (!empty($_SESSION['approve_success'])): ?>
<script>
Swal.fire({icon:'success',title:'อนุมัติสำเร็จ!',text:'ผู้ใช้งานได้รับการอนุมัติเรียบร้อยแล้ว',timer:2000,showConfirmButton:false});
</script>
<?php unset($_SESSION['approve_success']); endif; ?>


<?php  include '../templates/sidebar.php'; ?>
<div class="mainwrap">
    <div class="topbar" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 0 0 20px 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <span class="material-icons" style="font-size: 28px;">admin_panel_settings</span>
            <div>
                <h1 style="margin: 0; font-size: 24px; font-weight: 700;">จัดการผู้ใช้งาน</h1>
                <p style="margin: 4px 0 0 0; opacity: 0.9; font-size: 14px;">จัดการสมาชิกและคำขออนุมัติ</p>
            </div>
        </div>
    </div>

    <!-- TAB BUTTONS -->
    <div class="tabs">
        <button class="tabbtn active" data-tab="pending-users">ผู้สมัครใหม่</button>
        <button class="tabbtn" data-tab="approved-users">สมาชิกทั้งหมด</button>
        <button class="tabbtn" data-tab="reset-requests">คำขอรีเซ็ตรหัสผ่าน</button>
    </div>

    <!-- TAB CONTENT -->
    <div id="pending-users" class="tabcontent active">
        <div class="card-sec">
            <div class="card">
                <div class="card-title"><span class="material-icons" style="color:orange;">pending_actions</span> คำขออนุมัติที่รอดำเนินการ (<?=count($pending_users)?>)</div>
                <div class="user-list">
                <?php if(!$pending_users): ?><div class="card-empty">ไม่มีคำขออนุมัติ</div><?php else: foreach($pending_users as $u): ?>
                    <div class="usercard">
                        <div class="user-info">
                            <div><?=htmlspecialchars($u['name'])?> <span class="status-badge status-pending">รออนุมัติ</span></div>
                            <small><?=htmlspecialchars($u['email'])?> &bull; <?=htmlspecialchars($u['department'])?> &bull; สมัครเมื่อ <?=date('d/m/Y', strtotime($u['created_at']))?></small>
                        </div>
                        <div class="action-btns">
                            <a href="#" class="action-btn btn-approve" data-uid="<?=$u['user_id']?>">
                                <span class="material-icons" style="font-size: 16px;">check_circle</span>
                                อนุมัติ
                            </a>
                            <a href="?reject_id=<?=$u['user_id']?>" class="action-btn reject" onclick="return confirm('ไม่อนุมัติและลบผู้ใช้นี้?')">
                                <span class="material-icons" style="font-size: 16px;">cancel</span>
                                ปฏิเสธ
                            </a>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

            <!-- สมาชิกทั้งหมด -->
            <div id="approved-users" class="tabcontent">
                <div class="card-sec">
                    <div class="card">
                        <div class="card-title">
                            <span class="material-icons" style="color:#37cd2e;">verified_user</span> 
                            สมาชิกที่อนุมัติแล้ว (<?=count($approved_users)?>)
                        </div>
                        <div class="user-list">
                        <?php if(!$approved_users): ?>
                            <div class="card-empty">ยังไม่มีสมาชิก</div>
                        <?php else: foreach($approved_users as $u): ?>
                            
                            

                            <div class="usercard" id="user-<?=$u['user_id']?>">
                        <div class="user-info">
                            <div><?=htmlspecialchars($u['name'])?> <span class="status-badge status-approved">อนุมัติแล้ว</span></div>
                            <small>
                                <?=htmlspecialchars($u['email'])?> • <?=htmlspecialchars($u['department'])?> • 
                                สิทธิ์: <?= $u['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน' ?> •
                                เข้าร่วมเมื่อ <?=date('d/m/Y', strtotime($u['created_at']))?>
                            </small>
                        </div>
                        <div class="actions">
                            <a href="javascript:void(0)" class="btn-edit" 
                            onclick="editUser(<?=$u['user_id']?>,'<?=htmlspecialchars($u['name'])?>','<?=htmlspecialchars($u['email'])?>','<?=htmlspecialchars($u['department'])?>','<?=htmlspecialchars($u['role'])?>')"
                            title="แก้ไขข้อมูล">
                            <span class="material-icons">edit</span>
                            </a>
                            <a href="javascript:void(0)" class="btn-delete" 
                            onclick="deleteUser(<?=$u['user_id']?>)"
                            title="ลบผู้ใช้">
                            <span class="material-icons">delete</span>
                            </a>
                        </div>
                    </div>


                        <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>


         <!-- reset-requests -->
           <div id="reset-requests" class="tabcontent">
    <div class="card-sec" id="reset-requests-container">
        <div class="card">
            <!-- Special title container with green frame -->
            <div class="reset-title-container">
                <div class="check-icon">
                    <span class="material-icons">check</span>
                </div>
                <h3 class="title-text">คำขอรีเซ็ตรหัสผ่าน</h3>
                <span class="count-badge">(2)</span>
            </div>

            <div class="user-list" id="reset-list">
                <!-- AJAX จะโหลดเนื้อหาที่นี่ -->
                <div class="card-empty">คลิกที่แท็บนี้เพื่อโหลดข้อมูล</div>
            </div>
        </div>
    </div>
</div>


</div>

<script>
// TAB SWITCH
const tabButtons = document.querySelectorAll('.tabbtn');
const tabContents = document.querySelectorAll('.tabcontent');
tabButtons.forEach(btn=>{
    btn.addEventListener('click', ()=>{
        tabButtons.forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const tabId = btn.dataset.tab;
        tabContents.forEach(tc=>tc.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        if(tabId==='reset-requests') loadResetRequests();
    });
});

// โหลด AJAX tab reset requests
function loadResetRequests(){
    const container = document.getElementById('reset-requests-container');
    container.innerHTML = `
        <div class="card">
            <div class="reset-title-container">
                <div class="check-icon">
                    <span class="material-icons">check</span>
                </div>
                <h3 class="title-text">คำขอรีเซ็ตรหัสผ่าน</h3>
                <span class="count-badge loading">กำลังโหลด...</span>
            </div>
            <div class="user-list" id="reset-list">
                <p style="text-align:center; padding:20px; color:#666;">กำลังโหลดข้อมูล...</p>
            </div>
        </div>
    `;
    
    fetch('admin_reset_requests.php')
    .then(res=>res.text())
    .then(html=>{
        // Count the number of reset requests from the response
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const resetCards = tempDiv.querySelectorAll('.reset-card');
        const count = resetCards.length;
        
        // Update the container with proper count
        container.innerHTML = `
            <div class="card">
                <div class="reset-title-container">
                    <div class="check-icon">
                        <span class="material-icons">check</span>
                    </div>
                    <h3 class="title-text">คำขอรีเซ็ตรหัสผ่าน</h3>
                    <span class="count-badge">(${count})</span>
                </div>
                ${html}
            </div>
        `;
        attachResetEvents(); // attach event หลังโหลด
    })
    .catch(err=>{
        container.innerHTML = `
            <div class="card">
                <div class="reset-title-container">
                    <div class="check-icon">
                        <span class="material-icons">error</span>
                    </div>
                    <h3 class="title-text">คำขอรีเซ็ตรหัสผ่าน</h3>
                    <span class="count-badge">(Error)</span>
                </div>
                <div class="user-list">
                    <p style="text-align:center; padding:20px; color:#ef4444;">โหลดข้อมูลไม่สำเร็จ</p>
                </div>
            </div>
        `;
    });
}

// attach event ให้ปุ่ม reset password
function attachResetEvents(){
    document.querySelectorAll('.btn-reset').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const id = btn.dataset.id;
            Swal.fire({
                title: 'ยืนยันการรีเซ็ต?',
                text: 'คุณต้องการตั้งรหัสใหม่สำหรับผู้ใช้นี้หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, รีเซ็ตเลย',
                cancelButtonText: 'ยกเลิก'
            }).then(result=>{
                if(result.isConfirmed){
                    fetch('process_reset.php', {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: 'id='+id
                    })
                    .then(res=>res.json())
                    .then(data=>{
                        if(data.success){
                            Swal.fire({
                                icon:'success',
                                title:'สำเร็จ!',
                                html:`รหัสผ่านใหม่: <b>${data.new_password}</b><br><button id="copyBtn">Copy รหัส</button>`,
                                didOpen: ()=>{
                                    document.getElementById('copyBtn').addEventListener('click', ()=>{
                                        navigator.clipboard.writeText(data.new_password);
                                        Swal.fire('คัดลอกแล้ว!', '', 'success').then(()=>{
                                            // รีโหลดเฉพาะ container
                                            loadResetRequests();
                                        });
                                    });
                                }
                            });
                            btn.disabled = true;
                            btn.innerText = 'รีเซ็ตแล้ว';
                        } else {
                            Swal.fire('ผิดพลาด', data.message, 'error');
                        }
                    })
                    .catch(()=>Swal.fire('ผิดพลาด','ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์','error'));
                }
            });
        });
    });
}

// ปุ่ม approve user (ถ้ามี)
document.addEventListener('click', function(e){
    const approveBtn = e.target.closest('.btn-approve');
    if(approveBtn){
        e.preventDefault();
        const uid = approveBtn.dataset.uid;
        Swal.fire({
            title:'ยืนยันการอนุมัติ?',
            icon:'question',
            showCancelButton:true,
            confirmButtonText:'ใช่, อนุมัติ!',
            cancelButtonText:'ยกเลิก'
        }).then(res=>{
            if(res.isConfirmed) window.location='?approve_id='+uid;
        });
    }
});




function deleteUser(userId) {
    Swal.fire({
        title: "ยืนยันการลบ?",
        text: "คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "ลบ",
        cancelButtonText: "ยกเลิก"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("delete_user.php?id=" + userId, { method: "GET" })
            .then(res => res.text())
            .then(data => {
                Swal.fire("ลบสำเร็จ", "", "success");
                document.getElementById("user-" + userId).remove();
            })
            .catch(err => Swal.fire("Error", err, "error"));
        }
    });
}

// แก้ไข user -----------------------
function editUser(userId, name, email, department, role) {
    Swal.fire({
        title: '<h3 style="margin:0; font-size:20px; color:#2261ad;">✏️ แก้ไขข้อมูลผู้ใช้</h3>',
        html: `
            <div style="text-align:left;">
                <label style="font-weight:600;">ชื่อ:</label>
                <input id="swal-name" class="swal2-input" value="${name}" style="margin-top:5px;">
                
                <label style="font-weight:600;">อีเมล:</label>
                <input id="swal-email" class="swal2-input" value="${email}" style="margin-top:5px;">
                
                <label style="font-weight:600;">หน่วยงาน:</label>
                <input id="swal-dept" class="swal2-input" value="${department}" style="margin-top:5px;">
                
                <label style="font-weight:600;">สิทธิ์:</label>
                <select id="swal-role" class="swal2-input" style="margin-top:5px;">
                    <option value="user" ${role === "user" ? "selected" : ""}>ผู้ใช้งาน</option>
                    <option value="admin" ${role === "admin" ? "selected" : ""}>ผู้ดูแลระบบ</option>
                </select>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: "💾 บันทึก",
        cancelButtonText: "❌ ยกเลิก",
        width: "450px",
        customClass: {
            popup: "rounded-xl shadow-lg",
            confirmButton: "swal2-confirm btn btn-primary",
            cancelButton: "swal2-cancel btn btn-secondary"
        },
        preConfirm: () => {
            return {
                name: document.getElementById("swal-name").value,
                email: document.getElementById("swal-email").value,
                department: document.getElementById("swal-dept").value,
                role: document.getElementById("swal-role").value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
           fetch("update_user.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        user_id: userId,
                        name: result.value.name,
                        email: result.value.email,
                        department: result.value.department,
                        role: result.value.role
                    })
                })
                .then(res => res.json())
               

                .then(data => {
                    if(data.success){
                        Swal.fire({
                            icon: "success",
                            title: "✅ บันทึกสำเร็จ",
                            text: "ข้อมูลถูกแก้ไขเรียบร้อยแล้ว",
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload(); // รีเฟรชหน้าใหม่
                        });
                    } else {
                        Swal.fire("❌ ไม่สำเร็จ", data.message, "error");
                    }
                })


                .catch(err => {
                    Swal.fire("⚠️ เกิดข้อผิดพลาด", err.message, "error");
                });
            }

    });
}


</script>
</body>
</html>
