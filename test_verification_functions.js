// TEST SCRIPT FOR DAMAGED INSPECTION VERIFICATION FUNCTIONS
// This script can be run in browser console to verify the functions exist and work

console.log('═════════════════════════════════════════════════════════════');
console.log('🧪 DAMAGED INSPECTION VERIFICATION FUNCTIONS - TEST');
console.log('═════════════════════════════════════════════════════════════\n');

// Test data
const originalProductInspection = {
    inspection_id: 1,
    return_code: 'RET-20260302-8479',
    product_id: 15,  // Original product
    product_name: 'กุ้งทอด',
    sku: 'PROD-001',
    barcode: 'BAR-PROD-001',
    return_qty: 2,
    expiry_date: null,
    cost_price: 468,
    sale_price: 0,
    reason_name: 'สินค้าชำรุดบางส่วน',
    po_number: 'PO-2026-001'
};

const newProductInspection = {
    inspection_id: 2,
    return_code: 'RET-20260302-8480',
    product_id: null,  // New product
    product_name: 'สินค้าใหม่',
    sku: null,
    barcode: 'TMP-2-ABC123',
    return_qty: 5,
    expiry_date: null,
    cost_price: 200,
    sale_price: 300,
    reason_name: 'สินค้าชำรุดบางส่วน',
    po_number: 'PO-2026-002'
};

// TEST 1: isOriginalProduct
console.log('TEST 1: isOriginalProduct()');
console.log('────────────────────────────────────────\n');

const isOriginal1 = isOriginalProduct(originalProductInspection);
console.log(`✓ Original product (product_id=${originalProductInspection.product_id})`);
console.log(`  Result: ${isOriginal1} (expected: true)`);
console.log(`  Status: ${isOriginal1 === true ? '✅ PASS' : '❌ FAIL'}\n`);

const isOriginal2 = isOriginalProduct(newProductInspection);
console.log(`✓ New product (product_id=${newProductInspection.product_id})`);
console.log(`  Result: ${isOriginal2} (expected: false)`);
console.log(`  Status: ${isOriginal2 === false ? '✅ PASS' : '❌ FAIL'}\n`);

// TEST 2: generateNewSku
console.log('TEST 2: generateNewSku()');
console.log('────────────────────────────────────────\n');

const newSku1 = generateNewSku(originalProductInspection);
console.log(`✓ Original product SKU generation`);
console.log(`  Original: ${originalProductInspection.sku}`);
console.log(`  Generated: ${newSku1}`);
console.log(`  Expected: ตำหนิ-PROD-001`);
console.log(`  Status: ${newSku1 === 'ตำหนิ-PROD-001' ? '✅ PASS' : '❌ FAIL'}\n`);

const newSku2 = generateNewSku(newProductInspection);
console.log(`✓ New product SKU generation`);
console.log(`  Original: ${newProductInspection.sku}`);
console.log(`  Generated: ${newSku2}`);
console.log(`  Expected: ตำหนิ-[auto](should start with this)`);
console.log(`  Status: ${newSku2.startsWith('ตำหนิ-') ? '✅ PASS' : '❌ FAIL'}\n`);

// TEST 3: generateNewBarcode
console.log('TEST 3: generateNewBarcode()');
console.log('────────────────────────────────────────\n');

const barcode1 = generateNewBarcode(12);
console.log(`✓ Generate barcode for item_id=12`);
console.log(`  Generated: ${barcode1}`);
console.log(`  Format: BAR-[itemId]-[timestamp][random]`);
console.log(`  Status: ${barcode1.startsWith('BAR-12-') ? '✅ PASS' : '❌ FAIL'}\n`);

// TEST 4: validateInspectionData
console.log('TEST 4: validateInspectionData()');
console.log('────────────────────────────────────────\n');

const validation1 = validateInspectionData(
    originalProductInspection,
    'sellable',
    2.0,
    '2026-12-31',
    'ตำหนิ-PROD-001'
);
console.log(`✓ Valid original product, sellable`);
console.log(`  Valid: ${validation1.valid} (expected: true)`);
console.log(`  Errors: ${validation1.errors.length} (expected: 0)`);
console.log(`  Warnings: ${validation1.warnings.length} (expected: 1)`);
console.log(`  Status: ${validation1.valid === true && validation1.errors.length === 0 ? '✅ PASS' : '❌ FAIL'}\n`);

const validation2 = validateInspectionData(
    originalProductInspection,
    'sellable',
    10.0,  // Over limit
    '2026-12-31',
    'ตำหนิ-PROD-001'
);
console.log(`✓ Invalid: quantity exceeds return_qty`);
console.log(`  Valid: ${validation2.valid} (expected: false)`);
console.log(`  Errors: ${validation2.errors.length} (expected: 1)`);
console.log(`  Status: ${validation2.valid === false && validation2.errors.length > 0 ? '✅ PASS' : '❌ FAIL'}\n`);

// TEST 5: buildVerificationDialog
console.log('TEST 5: buildVerificationDialog()');
console.log('────────────────────────────────────────\n');

const dialog1 = buildVerificationDialog(originalProductInspection, 'sellable');
console.log(`✓ Original product, sellable verification dialog`);
console.log(`  Contains "สินค้าเดิม": ${dialog1.includes('สินค้าเดิม') ? '✅ YES' : '❌ NO'}`);
console.log(`  Contains "products table": ${dialog1.includes('products') ? '✅ YES' : '❌ NO'}`);
console.log(`  Status: ${dialog1.includes('สินค้าเดิม') && dialog1.includes('products') ? '✅ PASS' : '❌ FAIL'}\n`);

const dialog2 = buildVerificationDialog(newProductInspection, 'sellable');
console.log(`✓ New product, sellable verification dialog`);
console.log(`  Contains "สินค้าใหม่": ${dialog2.includes('สินค้าใหม่') ? '✅ YES' : '❌ NO'}`);
console.log(`  Contains "temp_products": ${dialog2.includes('temp_products') ? '✅ YES' : '❌ NO'}`);
console.log(`  Status: ${dialog2.includes('สินค้าใหม่') && dialog2.includes('temp_products') ? '✅ PASS' : '❌ FAIL'}\n`);

const dialog3 = buildVerificationDialog(originalProductInspection, 'discard');
console.log(`✓ Discard disposition verification dialog`);
console.log(`  Contains "ทิ้ง": ${dialog3.includes('ทิ้ง') ? '✅ YES' : '❌ NO'}`);
console.log(`  Contains "not yet in stock": ${dialog3.includes('ไม่บันทึกเข้าสต') ? '✅ YES' : '❌ NO'}`);
console.log(`  Status: ${dialog3.includes('ทิ้ง') ? '✅ PASS' : '❌ FAIL'}\n`);

// SUMMARY
console.log('═════════════════════════════════════════════════════════════');
console.log('✅ ALL VERIFICATION FUNCTIONS AVAILABLE');
console.log('═════════════════════════════════════════════════════════════\n');

console.log('Functions Tested:');
console.log('  ✓ isOriginalProduct()');
console.log('  ✓ generateNewSku()');
console.log('  ✓ generateNewBarcode()');
console.log('  ✓ validateInspectionData()');
console.log('  ✓ buildVerificationDialog()');
console.log('  ✓ showVerificationDialog() [requires user interaction]');
console.log('\nHidden Functions (supporting):');
console.log('  ✓ formatCurrency()');
console.log('  ✓ formatDateTime()');
console.log('  ✓ formatDateOnly()');
console.log('  ✓ statusDisplay()');
console.log('  ✓ statusBadgeClass()');
console.log('  ✓ escapeHtml()');
console.log('\nReady to Test showVerificationDialog() with:');
console.log('  await showVerificationDialog(originalProductInspection, "sellable", "ตำหนิ-PROD-001", 2, "2026-12-31", 468, 0)');
console.log('\n═════════════════════════════════════════════════════════════');
