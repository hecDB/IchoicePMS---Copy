<?php
session_start();

require_once 'db_connect.php';

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

<link rel="stylesheet" href="assets/base.css">
<link rel="stylesheet" href="assets/sidebar.css">
<link rel="stylesheet" href="assets/components.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


 <style>
            /* TAB */
                .tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                }

             
                .tabbtn:hover {
                    background: #e9ebf5;          
                }
                .tabbtn.active {
                    background: #ffffff;           /* พื้นหลังขาวเด่น */
                    color: #0b0d66ff;             
                    border: 2px solid #b0baf5;    /* ขอบฟ้าอ่อน */
                }

                /* Tab Content */
                .tabcontent { display:none; }
                .tabcontent.active { display:block; }

                /* Card */
                .card-sec { display:flex; gap:16px; flex-wrap:wrap; margin-top:20px; }

                .card { 
                    flex:1; min-width:300px; 
                    background:#ffffff;          /* พื้นขาว */
                    border-radius:16px;          /* ขอบโค้ง */
                    padding:20px; 
                    box-shadow:0 6px 20px rgba(0,0,0,0.08); /* เงานุ่ม */
                }
                .card-title { font-weight:bold; margin-bottom:12px; display:flex; align-items:center; gap:8px; }

                /* User List */
                .user-list { display:flex; flex-direction:column; gap:10px; }
                .usercard { 
                    display:flex; align-items:center; justify-content:space-between; 
                    padding:12px 16px; 
                    border:1px solid #eee; 
                    border-radius:12px; 
                    background:#ffffff; 
                    box-shadow:0 2px 6px rgba(0,0,0,0.05);
                    transition: all 0.25s;
                }
                .usercard:hover { 
                    background:#f7f9ff; 
                    box-shadow:0 4px 12px rgba(0,0,0,0.1);
                }
                .status-badge { 
                    background:#f0f0f0; 
                    padding:2px 6px; 
                    border-radius:6px; 
                    font-size:12px; 
                }

                /* Action Buttons */
                .action-btn { 
                    padding:6px 12px; 
                    border-radius:8px; 
                    text-decoration:none; 
                    font-size:13px; 
                    cursor:pointer; 
                    transition:0.2s;
                }
                .action-btn.reject { background:#f8d7da;color:#721c24; }
                .action-btn.btn-approve { background:#d4edda;color:#155724; }

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

                .usercard .actions {
                    display: flex;
                    gap: 8px;
                }

                .usercard .actions a {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 34px;
                    height: 34px;
                    border-radius: 10px;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .usercard .actions a .material-icons {
                    font-size: 18px;
                    color: #fff;
                }

                .btn-edit {
                    background: #3498db;
                }
                .btn-edit:hover {
                    background: #2d83c5;
                    transform: translateY(-2px);
                }

                .btn-delete {
                    background: #e74c3c;
                }
                .btn-delete:hover {
                    background: #c0392b;
                    transform: translateY(-2px);
                }
                                
                        
    </style>
    <style>
    /* Small-screen adjustments to match receive_items_view.php layout */
    
    </style>
</head>
<body>

<?php if (!empty($_SESSION['approve_success'])): ?>
<script>
Swal.fire({icon:'success',title:'อนุมัติสำเร็จ!',text:'ผู้ใช้งานได้รับการอนุมัติเรียบร้อยแล้ว',timer:2000,showConfirmButton:false});
</script>
<?php unset($_SESSION['approve_success']); endif; ?>


<?php  include 'sidebar.php'; ?>
<div class="mainwrap">
    <div class="topbar">จัดการผู้ใช้</div>

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
                        <div><?=htmlspecialchars($u['name'])?> <span class="status-badge status-pending">รออนุมัติ</span><br><small><?=htmlspecialchars($u['email'])?> &bull; <?=htmlspecialchars($u['department'])?></small></div>
                        <div class="action-btns">
                            <a href="#" class="action-btn btn-approve" data-uid="<?=$u['user_id']?>">อนุมัติ</a>
                            <a href="?reject_id=<?=$u['user_id']?>" class="action-btn reject" onclick="return confirm('ไม่อนุมัติลบผู้ใช้นี้?')">ปฏิเสธ</a>
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
                            <?=htmlspecialchars($u['name'])?>
                            <br>
                            <small><?=htmlspecialchars($u['email'])?> • <?=htmlspecialchars($u['department'])?></small>
                        </div>
                        <span class="status-badge">อนุมัติแล้ว</span>
                        <div class="actions">
                            <a href="javascript:void(0)" class="btn-edit" 
                            onclick="editUser(<?=$u['user_id']?>,'<?=htmlspecialchars($u['name'])?>','<?=htmlspecialchars($u['email'])?>','<?=htmlspecialchars($u['department'])?>','<?=htmlspecialchars($u['role'])?>')">
                            <span class="material-icons">edit</span>
                            </a>
                            <a href="javascript:void(0)" class="btn-delete" 
                            onclick="deleteUser(<?=$u['user_id']?>)">
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
            <div class="card-title">
                <span class="material-icons" style="color:#ffc107;">lock_reset</span>
                คำขอรีเซ็ตรหัสผ่าน
            </div>

            <div class="user-list" id="reset-list">
                <!-- AJAX จะโหลด admin_reset_requests.php ที่นี่ -->

                <!-- ตัวอย่างการ์ดผู้ใช้ -->
                <div class="reset-card">
                    <div class="uc-details">
                        <div class="name">ชื่อผู้ใช้ <span class="status-badge status-pending">รอรีเซ็ต</span></div>
                        <div class="meta">user@example.com &bull; 28-08-2025 15:30</div>
                    </div>
                    <button class="action-btn process btn-reset" data-id="1">
                        <span class="material-icons">lock_reset</span> ตั้งรหัสใหม่
                    </button>
                </div>

                <!-- หากไม่มีคำขอ -->
                <div class="card-empty" id="no-reset">ยังไม่มีคำขอรีเซ็ต</div>

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
    container.innerHTML = '<p>กำลังโหลด...</p>';
    fetch('admin_reset_requests.php')
    .then(res=>res.text())
    .then(html=>{
        container.innerHTML = html;
        attachResetEvents(); // attach event หลังโหลด
    })
    .catch(err=>container.innerHTML='<p>โหลดข้อมูลไม่สำเร็จ</p>');
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
