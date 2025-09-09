<?php
require 'vendor/autoload.php'; // โหลด PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// สร้าง spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// กำหนดหัวคอลัมน์
$headers = ['sku','barcode','name','ภาพ','หน่วยนับ','แถว','ล๊อค','ชั้น','จำนวน','ราคาต้นทุน','ราคาขาย','EXP','สีสินค้า(ถ้ามี)','ชนิดการแบ่งขาย(ถ้ามี)', 'หมายเหตุอื่นๆ'];

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

// นำสไตล์ไปใช้กับแถวหัวตาราง (A1:J1)
$sheet->getStyle('A1:O1')->applyFromArray($styleArray);

// ปรับความกว้างคอลัมน์อัตโนมัติ
foreach (range('A','O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ตั้งชื่อไฟล์
$filename = "import_template.xlsx";

// ส่งออกให้ดาวน์โหลด
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
