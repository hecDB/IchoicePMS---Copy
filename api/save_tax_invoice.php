<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../config/db_connect.php';

function thaiBahtText($amount) {
    $numText = ['ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
    $unitText = ['','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน'];

    $amount = max(0, floatval($amount));
    $integerPart = floor($amount);
    $satang = round(($amount - $integerPart) * 100);

    $readNumber = function ($num) use (&$readNumber, $numText, $unitText) {
        $num = intval($num);
        if ($num === 0) {
            return $numText[0];
        }
        $result = '';
        $digits = str_split((string)$num);
        $len = count($digits);
        foreach ($digits as $i => $digitChar) {
            $digit = intval($digitChar);
            $pos = $len - $i - 1;
            if ($digit === 0) {
                continue;
            }
            if ($pos === 0 && $digit === 1 && $len > 1) {
                $result .= 'เอ็ด';
            } elseif ($pos === 1 && $digit === 2) {
                $result .= 'ยี่';
            } elseif ($pos === 1 && $digit === 1) {
                $result .= '';
            } else {
                $result .= $numText[$digit];
            }
            $result .= $unitText[$pos];
        }
        return $result;
    };

    $readMillion = function ($num) use (&$readMillion, $readNumber) {
        $num = intval($num);
        if ($num === 0) {
            return '';
        }
        $millions = intdiv($num, 1000000);
        $remainder = $num % 1000000;
        $result = '';
        if ($millions > 0) {
            $result .= $readMillion($millions) . 'ล้าน';
        }
        if ($remainder > 0) {
            $result .= $readNumber($remainder);
        }
        return $result;
    };

    $text = $readMillion($integerPart);
    if ($text === '') {
        $text = $numText[0];
    }
    $text .= 'บาท';
    if ($satang === 0) {
        $text .= 'ถ้วน';
    } else {
        $text .= $readMillion($satang) . 'สตางค์';
    }
    return $text;
}

function respond($status, $payload) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['success' => false, 'error' => 'Invalid JSON body']);
}

$items = $input['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    respond(400, ['success' => false, 'error' => 'กรุณาเพิ่มรายการสินค้า']);
}

$cleanItems = [];
$subtotal = 0;
foreach ($items as $idx => $item) {
    $name = trim($item['name'] ?? '');
    $qty = isset($item['qty']) ? floatval($item['qty']) : 0;
    $unit = trim($item['unit'] ?? '');
    $price = isset($item['price']) ? floatval($item['price']) : 0;
    if ($name === '' || $qty <= 0) {
        respond(400, ['success' => false, 'error' => 'รายการสินค้าต้องมีชื่อและจำนวนมากกว่า 0']);
    }
    $lineTotal = $qty * $price;
    $subtotal += $lineTotal;
    $cleanItems[] = [
        'item_no' => $idx + 1,
        'name' => $name,
        'qty' => $qty,
        'unit' => $unit,
        'price' => $price,
        'line_total' => $lineTotal
    ];
}

$invNo = trim($input['inv_no'] ?? '');
$invDate = trim($input['inv_date'] ?? '');
$customer = trim($input['customer'] ?? '');
$address = trim($input['address'] ?? '');
$docType = trim($input['doc_type'] ?? 'tax_invoice');
$salesTag = trim($input['sales_tag'] ?? '');
if ($invNo === '' || $invDate === '' || $customer === '' || $address === '') {
    respond(400, ['success' => false, 'error' => 'กรุณากรอกข้อมูลใบกำกับภาษีให้ครบ']);
}

$discount = max(0, floatval($input['discount'] ?? 0));
$shipping = max(0, floatval($input['shipping'] ?? 0));
$specialDiscount = max(0, floatval($input['special_discount'] ?? 0));

$totalAfterDiscount = max($subtotal - $discount + $shipping, 0);
$beforeVat = $totalAfterDiscount / 1.07;
$vat = $totalAfterDiscount - $beforeVat;
$grandTotal = $totalAfterDiscount;
$payable = max($grandTotal - $specialDiscount, 0);
$amountText = thaiBahtText($payable);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO tax_invoices (
        doc_type, inv_no, sales_tag, inv_date, platform,
        customer_name, customer_tax_id, customer_address,
        subtotal, discount, shipping, before_vat, vat,
        grand_total, special_discount, payable, amount_text, status
    ) VALUES (
        :doc_type, :inv_no, :sales_tag, :inv_date, :platform,
        :customer_name, :customer_tax_id, :customer_address,
        :subtotal, :discount, :shipping, :before_vat, :vat,
        :grand_total, :special_discount, :payable, :amount_text, 'active'
    )");

    $stmt->execute([
        ':doc_type' => $docType,
        ':inv_no' => $invNo,
        ':sales_tag' => $salesTag ?: null,
        ':inv_date' => $invDate,
        ':platform' => $input['platform'] ?? null,
        ':customer_name' => $customer,
        ':customer_tax_id' => $input['tax_id'] ?? null,
        ':customer_address' => $address,
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':shipping' => $shipping,
        ':before_vat' => $beforeVat,
        ':vat' => $vat,
        ':grand_total' => $grandTotal,
        ':special_discount' => $specialDiscount,
        ':payable' => $payable,
        ':amount_text' => $amountText
    ]);

    $invoiceId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO tax_invoice_items (
        invoice_id, seq, item_name, qty, unit, unit_price, total_price
    ) VALUES (
        :invoice_id, :seq, :item_name, :qty, :unit, :unit_price, :total_price
    )");

    foreach ($cleanItems as $item) {
        $itemStmt->execute([
            ':invoice_id' => $invoiceId,
            ':seq' => $item['item_no'],
            ':item_name' => $item['name'],
            ':qty' => $item['qty'],
            ':unit' => $item['unit'] ?: null,
            ':unit_price' => $item['price'],
            ':total_price' => $item['line_total']
        ]);
    }

    $pdo->commit();

    respond(200, [
        'success' => true,
        'invoice_id' => $invoiceId,
        'amount_text' => $amountText
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {
        respond(409, ['success' => false, 'error' => 'เลขที่ใบกำกับภาษีซ้ำ']);
    }
    respond(500, ['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(500, ['success' => false, 'error' => $e->getMessage()]);
}
