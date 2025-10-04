<?php
session_start();
include '../templates/sidebar.php';
include '../config/db_connect.php';

// ดึงข้อมูลสกุลเงิน
$stmt = $pdo->query("SELECT * FROM currencies ORDER BY is_base DESC, code ASC");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการอัตราแลกเปลี่ยน</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.currency-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.currency-table th {
    background: #1976d2;
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
}

.currency-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
}

.currency-table tr:hover {
    background: #f8f9ff;
}

.rate-input {
    width: 120px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-align: right;
}

.rate-input:focus {
    border-color: #1976d2;
    outline: none;
}

.currency-symbol {
    font-size: 20px;
    font-weight: bold;
    color: #1976d2;
    margin-right: 8px;
}

.base-badge {
    background: #4caf50;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.status-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #4caf50;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.update-btn {
    background: #1976d2;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    margin: 5px;
}

.update-btn:hover {
    background: #1565c0;
}

.update-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.last-updated {
    font-size: 12px;
    color: #666;
}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #1976d2, #42a5f5);
    color: white;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}
</style>
</head>
<body>

<div class="mainwrap">
    <div class="container">
        <h2>จัดการอัตราแลกเปลี่ยน</h2>
        <div class="desc">อัปเดตอัตราแลกเปลี่ยนและจัดการสกุลเงิน</div>

        <!-- สถิติ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="totalCurrencies"><?= count($currencies) ?></div>
                <div class="stat-label">สกุลเงินทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="activeCurrencies"><?= count(array_filter($currencies, fn($c) => $c['is_active'])) ?></div>
                <div class="stat-label">สกุลเงินที่ใช้งาน</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">฿</div>
                <div class="stat-label">สกุลเงินหลัก</div>
            </div>
        </div>

        <!-- ตารางสกุลเงิน -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>รายการสกุลเงิน</h3>
                <div>
                    <button class="update-btn" onclick="updateAllRates()">
                        <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">refresh</span>
                        อัปเดตอัตราทั้งหมด
                    </button>
                    <button class="update-btn" onclick="saveAllRates()">
                        <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>
                        บันทึกการเปลี่ยนแปลง
                    </button>
                </div>
            </div>

            <table class="currency-table">
                <thead>
                    <tr>
                        <th>สกุลเงิน</th>
                        <th>ชื่อ</th>
                        <th>อัตราแลกเปลี่ยน</th>
                        <th>สถานะ</th>
                        <th>อัปเดตล่าสุด</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($currencies as $currency): ?>
                    <tr data-currency-id="<?= $currency['currency_id'] ?>">
                        <td>
                            <span class="currency-symbol"><?= htmlspecialchars($currency['symbol']) ?></span>
                            <strong><?= htmlspecialchars($currency['code']) ?></strong>
                            <?php if($currency['is_base']): ?>
                                <span class="base-badge">หลัก</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($currency['name']) ?></td>
                        <td>
                            <?php if($currency['is_base']): ?>
                                <span style="color: #666;">1.000000</span>
                            <?php else: ?>
                                <input type="number" 
                                       class="rate-input" 
                                       value="<?= number_format($currency['exchange_rate'], 6) ?>"
                                       step="0.000001"
                                       data-original="<?= $currency['exchange_rate'] ?>"
                                       onchange="markChanged(this)">
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($currency['is_base']): ?>
                                <span style="color: #4caf50; font-weight: bold;">เปิดใช้งาน</span>
                            <?php else: ?>
                                <label class="status-toggle">
                                    <input type="checkbox" 
                                           <?= $currency['is_active'] ? 'checked' : '' ?>
                                           onchange="toggleCurrencyStatus(<?= $currency['currency_id'] ?>, this.checked)">
                                    <span class="slider"></span>
                                </label>
                            <?php endif; ?>
                        </td>
                        <td class="last-updated">
                            <?= date('d/m/Y H:i', strtotime($currency['updated_at'])) ?>
                        </td>
                        <td>
                            <?php if(!$currency['is_base']): ?>
                                <button class="update-btn" style="padding: 6px 12px; font-size: 14px;"
                                        onclick="updateSingleRate(<?= $currency['currency_id'] ?>, '<?= $currency['code'] ?>')">
                                    อัปเดต
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- คำแนะนำ -->
        <div class="card" style="background: #fff3e0; border-left: 4px solid #ff9800;">
            <h4 style="color: #ef6c00; margin-top: 0;">คำแนะนำการใช้งาน</h4>
            <ul style="color: #bf360c; line-height: 1.6;">
                <li><strong>อัตราแลกเปลี่ยน:</strong> แสดงจำนวนบาทที่เทียบเท่า 1 หน่วยของสกุลเงินนั้น</li>
                <li><strong>การอัปเดต:</strong> ควรอัปเดตอัตราแลกเปลี่ยนสม่ำเสมอเพื่อความแม่นยำ</li>
                <li><strong>สกุลเงินหลัก:</strong> เป็นบาทไทย (THB) ไม่สามารถเปลี่ยนแปลงได้</li>
                <li><strong>การเปิด/ปิด:</strong> สกุลเงินที่ปิดจะไม่แสดงในการสร้างใบสั่งซื้อ</li>
            </ul>
        </div>
    </div>
</div>

<script>
let changedRates = new Map();

// ทำเครื่องหมายว่ามีการเปลี่ยนแปลง
function markChanged(input) {
    const currencyId = input.closest('tr').dataset.currencyId;
    const originalValue = parseFloat(input.dataset.original);
    const newValue = parseFloat(input.value);
    
    if (Math.abs(newValue - originalValue) > 0.000001) {
        changedRates.set(currencyId, newValue);
        input.style.borderColor = '#ff9800';
        input.style.backgroundColor = '#fff3e0';
    } else {
        changedRates.delete(currencyId);
        input.style.borderColor = '#ddd';
        input.style.backgroundColor = '#fff';
    }
    
    updateSaveButtonState();
}

// อัปเดตสถานะปุ่มบันทึก
function updateSaveButtonState() {
    const saveBtn = document.querySelector('button[onclick="saveAllRates()"]');
    if (changedRates.size > 0) {
        saveBtn.style.background = '#ff9800';
        saveBtn.innerHTML = `<span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>บันทึก (${changedRates.size})`;
    } else {
        saveBtn.style.background = '#1976d2';
        saveBtn.innerHTML = `<span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>บันทึกการเปลี่ยนแปลง`;
    }
}

// บันทึกการเปลี่ยนแปลงทั้งหมด
async function saveAllRates() {
    if (changedRates.size === 0) {
        Swal.fire('แจ้งเตือน', 'ไม่มีการเปลี่ยนแปลงที่ต้องบันทึก', 'info');
        return;
    }
    
    try {
        const rates = Object.fromEntries(changedRates);
        const response = await fetch('../api/currency_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                update_rates: true,
                rates: rates
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire('สำเร็จ', result.message, 'success');
            
            // อัปเดต UI
            changedRates.forEach((rate, currencyId) => {
                const row = document.querySelector(`tr[data-currency-id="${currencyId}"]`);
                const input = row.querySelector('.rate-input');
                const timeCell = row.querySelector('.last-updated');
                
                input.dataset.original = rate;
                input.style.borderColor = '#ddd';
                input.style.backgroundColor = '#fff';
                timeCell.textContent = new Date().toLocaleString('th-TH');
            });
            
            changedRates.clear();
            updateSaveButtonState();
        } else {
            Swal.fire('เกิดข้อผิดพลาด', result.error, 'error');
        }
    } catch (error) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    }
}

// อัปเดตอัตราเดียว
async function updateSingleRate(currencyId, currencyCode) {
    // จำลองการดึงอัตราจาก API
    const mockRates = {
        'USD': 39.25,
        'EUR': 42.15,
        'JPY': 0.26,
        'GBP': 49.80
    };
    
    const newRate = mockRates[currencyCode];
    if (newRate) {
        const row = document.querySelector(`tr[data-currency-id="${currencyId}"]`);
        const input = row.querySelector('.rate-input');
        
        input.value = newRate.toFixed(6);
        markChanged(input);
        
        Swal.fire('อัปเดตแล้ว', `อัตราแลกเปลี่ยน ${currencyCode} = ${newRate.toFixed(6)} บาท`, 'success');
    }
}

// อัปเดตอัตราทั้งหมด
async function updateAllRates() {
    const result = await Swal.fire({
        title: 'อัปเดตอัตราแลกเปลี่ยน',
        text: 'ต้องการอัปเดตอัตราแลกเปลี่ยนทั้งหมดจากแหล่งข้อมูลภายนอกหรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'อัปเดต',
        cancelButtonText: 'ยกเลิก'
    });
    
    if (result.isConfirmed) {
        // จำลองการอัปเดต
        const mockRates = {
            'USD': 39.25 + (Math.random() - 0.5) * 2,
            'EUR': 42.15 + (Math.random() - 0.5) * 2,
            'JPY': 0.26 + (Math.random() - 0.5) * 0.05,
            'GBP': 49.80 + (Math.random() - 0.5) * 3
        };
        
        document.querySelectorAll('.rate-input').forEach(input => {
            const row = input.closest('tr');
            const currencyCode = row.querySelector('strong').textContent;
            
            if (mockRates[currencyCode]) {
                input.value = mockRates[currencyCode].toFixed(6);
                markChanged(input);
            }
        });
        
        Swal.fire('สำเร็จ', 'อัปเดตอัตราแลกเปลี่ยนทั้งหมดแล้ว กรุณาตรวจสอบและบันทึก', 'success');
    }
}

// เปิด/ปิดสกุลเงิน
async function toggleCurrencyStatus(currencyId, isActive) {
    try {
        const response = await fetch('../api/currency_api.php', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                currency_id: currencyId,
                is_active: isActive ? 1 : 0
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // อัปเดตสถิติ
            const activeCount = document.querySelectorAll('input[type="checkbox"]:checked').length + 1; // +1 สำหรับ THB
            document.getElementById('activeCurrencies').textContent = activeCount;
        } else {
            Swal.fire('เกิดข้อผิดพลาด', result.error, 'error');
            // รีเซ็ตสถานะ
            event.target.checked = !isActive;
        }
    } catch (error) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        event.target.checked = !isActive;
    }
}
</script>

</body>
</html>