<?php session_start(); ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ทดสอบฟังก์ชั่น Search & Edit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h2>ทดสอบฟังก์ชั่นค้นหาและปุ่มแก้ไข</h2>
    
    <!-- Search Box -->
    <div class="mb-3">
        <label for="custom-search" class="form-label">ค้นหา:</label>
        <input type="text" class="form-control" id="custom-search" placeholder="ค้นหาในตาราง...">
    </div>
    
    <!-- Test Table -->
    <table id="receive-table" class="table table-striped">
        <thead>
            <tr>
                <th>SKU</th>
                <th>ชื่อสินค้า</th>
                <th>ผู้เพิ่ม</th>
                <th>วันที่</th>
                <th>จำนวน</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <tr data-id="1">
                <td>TEST001</td>
                <td>สินค้าทดสอบ 1</td>
                <td>Admin</td>
                <td>12/10/2025</td>
                <td>10</td>
                <td>
                    <button class="btn btn-primary btn-sm edit-btn" data-id="1">
                        <span class="material-icons">edit</span> แก้ไข
                    </button>
                </td>
            </tr>
            <tr data-id="2">
                <td>TEST002</td>
                <td>สินค้าทดสอบ 2</td>
                <td>User</td>
                <td>11/10/2025</td>
                <td>20</td>
                <td>
                    <button class="btn btn-primary btn-sm edit-btn" data-id="2">
                        <span class="material-icons">edit</span> แก้ไข
                    </button>
                </td>
            </tr>
            <tr data-id="3">
                <td>TEST003</td>
                <td>สินค้าทดสอบ 3</td>
                <td>Manager</td>
                <td>10/10/2025</td>
                <td>30</td>
                <td>
                    <button class="btn btn-primary btn-sm edit-btn" data-id="3">
                        <span class="material-icons">edit</span> แก้ไข
                    </button>
                </td>
            </tr>
        </tbody>
    </table>
    
    <div id="debug-info" class="mt-4">
        <h4>Debug Information:</h4>
        <div id="debug-output" class="border p-3 bg-light" style="height: 200px; overflow-y: auto;"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
function addDebug(message) {
    const output = document.getElementById('debug-output');
    const time = new Date().toLocaleTimeString();
    output.innerHTML += `[${time}] ${message}<br>`;
    output.scrollTop = output.scrollHeight;
    console.log(message);
}

$(document).ready(function() {
    addDebug('=== Starting Test ===');
    addDebug('jQuery version: ' + $.fn.jquery);
    addDebug('DataTable available: ' + (typeof $.fn.DataTable));
    addDebug('Table element found: ' + $('#receive-table').length);
    
    // Initialize DataTable
    let receiveTable = $('#receive-table').DataTable({
        pageLength: 10,
        language: {
            "search": "ค้นหา:",
            "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
            "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
            "paginate": {
                "first": "หน้าแรก",
                "last": "หน้าสุดท้าย", 
                "next": "ถัดไป",
                "previous": "ก่อนหน้า"
            }
        },
        drawCallback: function() {
            addDebug('DataTable drawCallback triggered');
            bindEditButtonEvents();
        },
        initComplete: function() {
            addDebug('DataTable initialized successfully');
            bindEditButtonEvents();
        }
    });
    
    function bindEditButtonEvents() {
        addDebug('Binding edit button events...');
        const editButtons = $('.edit-btn');
        addDebug('Found edit buttons: ' + editButtons.length);
        
        // Remove existing handlers
        editButtons.off('click.editHandler');
        
        // Bind new handlers
        editButtons.on('click.editHandler', function(e){
            e.preventDefault();
            e.stopPropagation();
            const id = $(this).data('id');
            addDebug('Edit button clicked for ID: ' + id);
            alert('แก้ไขรายการ ID: ' + id + '\n(ในระบบจริงจะเปิด Modal)');
        });
        
        addDebug('Edit button events bound successfully');
    }
    
    // Custom search functionality
    $('#custom-search').on('keyup input', function() {
        const value = this.value;
        addDebug('Search input: "' + value + '"');
        
        if (receiveTable && typeof receiveTable.search === 'function') {
            receiveTable.search(value).draw();
            addDebug('Search executed successfully');
        } else {
            addDebug('ERROR: receiveTable.search is not available');
        }
    });
    
    // Event delegation fallback
    $(document).on('click', '.edit-btn', function(e) {
        const id = $(this).data('id');
        addDebug('Fallback edit handler triggered for ID: ' + id);
    });
    
    // Test buttons after 2 seconds
    setTimeout(function() {
        addDebug('=== Running automated tests ===');
        const buttons = $('.edit-btn');
        addDebug('Total buttons found: ' + buttons.length);
        
        buttons.each(function(index) {
            const btn = $(this);
            const id = btn.data('id');
            addDebug('Button ' + index + ': ID=' + id + ', classes=' + btn.attr('class'));
        });
        
        addDebug('=== All tests complete ===');
    }, 2000);
});
</script>
</body>
</html>