<?php
require '../vendor/autoload.php'; // โหลด PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Border;

// สร้าง spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ดึงประเภทสินค้าจากฐานข้อมูล
require '../config/db_connect.php';
$categories_stmt = $pdo->query("SELECT category_id, category_name FROM product_category ORDER BY category_name");
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
$category_list = implode(',', array_map(fn($c) => $c['category_name'], $categories));

// กำหนดหัวคอลัมน์
$headers = ['sku','barcode','name','ประเภท','ภาพ','หน่วยนับ','แถว','ล๊อค','ชั้น','จำนวน','ราคาต้นทุน','ราคาขาย','สกุลเงิน','EXP','สีสินค้า(ถ้ามี)','ชนิดการแบ่งขาย(ถ้ามี)', 'หมายเหตุอื่นๆ'];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col.'1', $header);
    $col++;
}

// สไตล์หัวตาราง
$styleArray = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'] // ตัวหนังสือสีขาว
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4F81BD'] // พื้นหลังฟ้าเข้ม
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

// นำสไตล์ไปใช้กับแถวหัวตาราง (A1:Q1)
$sheet->getStyle('A1:Q1')->applyFromArray($styleArray);

// ปรับความกว้างคอลัมน์อัตโนมัติ
foreach (range('A','Q') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// เพิ่มตัวอย่างข้อมูลในแถวที่ 2
$exampleData = [
    'PRD001', // sku
    '1234567890123', // barcode  
    'ตัวอย่างสินค้า', // name
    '[เลือกจากรายการ]', // ประเภท - จะมี dropdown ให้เลือก
    'product.jpg', // ภาพ
    'ชิ้น', // หน่วยนับ
    'A', // แถว
    '1', // ล๊อค
    '1', // ชั้น
    '10', // จำนวน
    '100.00', // ราคาต้นทุน
    '150.00', // ราคาขาย
    '[เลือกจากรายการ]', // สกุลเงิน - จะมี dropdown ให้เลือก
    '2025-12-31', // EXP
    'สีแดง', // สีสินค้า(ถ้ามี)
    '1', // ชนิดการแบ่งขาย(ถ้ามี)
    'หมายเหตุเพิ่มเติม' // หมายเหตุอื่นๆ
];

// ใส่ข้อมูลตัวอย่างในแถวที่ 2 (ไม่รวมคอลัมน์ประเภทและสกุลเงินเพราะจะตั้งแยก)
$col = 'A';
foreach ($exampleData as $index => $data) {
    if ($index !== 3 && $index !== 12) { // ข้ามคอลัมน์ประเภท (index 3) และสกุลเงิน (index 12) เพราะจะให้ผู้ใช้เลือกจาก dropdown
        $sheet->setCellValue($col.'2', $data);
    }
    $col++;
}

// จัดสไตล์แถวตัวอย่าง
$sheet->getStyle('A2:Q2')->applyFromArray([
    'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0']
    ]
]);

// ===== เพิ่ม Data Validation สำหรับคอลัมน์ประเภท (Column D) =====
$categoryValidation = $sheet->getCell('D2')->getDataValidation();
$categoryValidation->setType(DataValidation::TYPE_LIST);
$categoryValidation->setErrorStyle(DataValidation::STYLE_STOP);
$categoryValidation->setAllowBlank(false);
$categoryValidation->setShowInputMessage(true);
$categoryValidation->setShowErrorMessage(true);
$categoryValidation->setShowDropDown(true);
$categoryValidation->setFormula1('"' . $category_list . '"');
$categoryValidation->setPromptTitle('เลือกประเภทสินค้า');
$categoryValidation->setPrompt('กรุณาเลือกประเภทสินค้าจากรายการ');
$categoryValidation->setErrorTitle('ประเภทไม่ถูกต้อง');
$categoryValidation->setError('กรุณาเลือกประเภทสินค้าจากรายการที่มี');

// คัดลอก Data Validation ไปยังแถวอื่นๆ (ตั้งแต่แถว 3 ถึง 1000)
for ($row = 3; $row <= 1000; $row++) {
    $validation = $sheet->getCell('D' . $row)->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_STOP);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setFormula1('"' . $category_list . '"');
    $validation->setPromptTitle('เลือกประเภทสินค้า');
    $validation->setPrompt('กรุณาเลือกประเภทสินค้าจากรายการ');
    $validation->setErrorTitle('ประเภทไม่ถูกต้อง');
    $validation->setError('กรุณาเลือกประเภทสินค้าจากรายการที่มี');
}

// เน้นคอลัมน์ประเภทด้วยสีฟ้าอ่อน
$sheet->getStyle('D1:D1000')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E3F2FD'] // สีฟ้าอ่อน
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'A0A0A0']
        ]
    ]
]);

// ===== เพิ่ม Data Validation สำหรับคอลัมน์สกุลเงิน (Column M) =====
$currencyValidation = $sheet->getCell('M2')->getDataValidation();
$currencyValidation->setType(DataValidation::TYPE_LIST);
$currencyValidation->setErrorStyle(DataValidation::STYLE_STOP);
$currencyValidation->setAllowBlank(false);
$currencyValidation->setShowInputMessage(true);
$currencyValidation->setShowErrorMessage(true);
$currencyValidation->setShowDropDown(true);
$currencyValidation->setFormula1('"THB,USD"'); // รายการสกุลเงินที่อนุญาต
$currencyValidation->setPromptTitle('เลือกสกุลเงิน');
$currencyValidation->setPrompt('กรุณาเลือกสกุลเงินจากรายการ: THB (บาท), USD (ดอลลาร์)');
$currencyValidation->setErrorTitle('สกุลเงินไม่ถูกต้อง');
$currencyValidation->setError('กรุณาเลือกเฉพาะ THB หรือ USD เท่านั้น');

// คัดลอก Data Validation ไปยังแถวอื่นๆ (ตั้งแต่แถว 3 ถึง 1000 สำหรับการเพิ่มข้อมูล)
for ($row = 3; $row <= 1000; $row++) {
    $validation = $sheet->getCell('M' . $row)->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_STOP);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setFormula1('"THB,USD"');
    $validation->setPromptTitle('เลือกสกุลเงิน');
    $validation->setPrompt('กรุณาเลือกสกุลเงินจากรายการ');
    $validation->setErrorTitle('สกุลเงินไม่ถูกต้อง');
    $validation->setError('กรุณาเลือกเฉพาะ THB หรือ USD เท่านั้น');
}

// ===== ปรับปรุงสีและการจัดรูปแบบเพิ่มเติม =====

// เน้นคอลัมน์สกุลเงินด้วยสีเขียวอ่อน
$sheet->getStyle('M1:M1000')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E8F5E8'] // สีเขียวอ่อน
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'A0A0A0']
        ]
    ]
]);

// เพิ่มข้อความในคอลัมน์สกุลเงิน
$sheet->getComment('M1')->getText()->createTextRun('คลิกที่ลูกศรเพื่อเลือกสกุลเงิน\n- THB: บาท (ค่าเริ่มต้น)\n- USD: ดอลลาร์สหรัฐ');
$sheet->getComment('M1')->setWidth('200px');
$sheet->getComment('M1')->setHeight('80px');

// ===== เพิ่ลคำอธิบายการใช้งานในแถบคำแนะนำ =====
$instructionSheet = $spreadsheet->createSheet();
$instructionSheet->setTitle('คำแนะนำ');

$instructions = [
    ['คำแนะนำการใช้งานแบบฟอร์ม Import Excel'],
    [''],
    ['1. กรอกข้อมูลสินค้าในแถบ "Import Data"'],
    ['2. คอลัมน์ประเภทสินค้า (สีฟ้า): คลิกที่ลูกศรเพื่อเลือก'],
    ['   - เลือกประเภทสินค้าจากรายการที่มีในระบบ'],
    ['3. คอลัมน์สกุลเงิน (สีเขียว): คลิกที่ลูกศรเพื่อเลือก'],
    ['   - THB: สำหรับราคาเป็นบาท'],
    ['   - USD: สำหรับราคาเป็นดอลลาร์สหรัฐ'],
    ['4. อัตราแลกเปลี่ยน: ระบบจะแปลง USD เป็นบาทอัตโนมัติ'],
    ['5. บันทึกแล้วอัปโหลดไฟล์กลับไปยังระบบ'],
    [''],
    ['หมายเหตุ:'],
    ['- คอลัมน์สีฟ้า (ประเภท) และสีเขียว (สกุลเงิน) มี dropdown ให้เลือก'],
    ['- อย่าพิมพ์เอง ให้เลือกจากรายการเสมอ'],
    ['- ราคาให้ใส่ตัวเลขเท่านั้น (เช่น 100.50)']
];

$row = 1;
foreach ($instructions as $instruction) {
    $instructionSheet->setCellValue('A' . $row, $instruction[0]);
    $row++;
}

// จัดรูปแบบแถบคำแนะนำ
$instructionSheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2E7D32']],
]);
$instructionSheet->getColumnDimension('A')->setWidth(80);

// กลับไปที่แถบข้อมูล
$spreadsheet->setActiveSheetIndex(0);

// ตั้งชื่อไฟล์
$filename = "import_template_with_currency.xlsx";

// ส่งออกให้ดาวน์โหลด
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
