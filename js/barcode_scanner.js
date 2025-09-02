// barcode_scanner.js

// ตรวจสอบว่า jQuery โหลดแล้ว
$(document).ready(function() {
    $(document).on('click', '.scan-btn', function() {
        const $btn = $(this);
        const $input = $btn.closest('td').find('input'); // input ของแถวเดียวกัน
        const $readerDiv = $btn.siblings('.reader-container');

        // แสดงกล้อง
        $readerDiv.show();

        // สร้าง instance ของ Html5Qrcode
        const html5QrCode = new Html5Qrcode($readerDiv[0]);

        html5QrCode.start(
            { facingMode: "environment" }, // กล้องหลัง
            { fps: 10, qrbox: 250 },       // เฟรมต่อวินาทีและขนาดสแกน
            (decodedText) => {
                $input.val(decodedText);   // ใส่ค่าลง input
                html5QrCode.stop();        // หยุดกล้อง
                $readerDiv.hide();         // ซ่อน div กล้อง
            },
            (errorMessage) => {
                // console.log(errorMessage); // สำหรับ debug
            }
        ).catch(err => console.error(err));
    });
});

