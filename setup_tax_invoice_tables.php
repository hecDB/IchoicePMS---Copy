<?php
// Run once to create tax invoice storage tables.
require_once __DIR__ . '/config/db_connect.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tax_invoices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inv_no VARCHAR(50) NOT NULL,
        inv_date DATE NOT NULL,
        ref_no VARCHAR(100) DEFAULT NULL,
        platform VARCHAR(100) DEFAULT NULL,
        payment_method VARCHAR(50) DEFAULT NULL,
        customer VARCHAR(255) NOT NULL,
        tax_id VARCHAR(32) DEFAULT NULL,
        branch VARCHAR(100) DEFAULT NULL,
        address TEXT,
        discount DECIMAL(14,2) NOT NULL DEFAULT 0,
        shipping DECIMAL(14,2) NOT NULL DEFAULT 0,
        special_discount DECIMAL(14,2) NOT NULL DEFAULT 0,
        subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_after_discount DECIMAL(14,2) NOT NULL DEFAULT 0,
        before_vat DECIMAL(14,2) NOT NULL DEFAULT 0,
        vat DECIMAL(14,2) NOT NULL DEFAULT 0,
        grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
        payable DECIMAL(14,2) NOT NULL DEFAULT 0,
        amount_text VARCHAR(512) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_inv_no (inv_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tax_invoice_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT UNSIGNED NOT NULL,
        item_no INT UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        qty DECIMAL(14,2) NOT NULL DEFAULT 0,
        unit VARCHAR(50) DEFAULT NULL,
        price DECIMAL(14,2) NOT NULL DEFAULT 0,
        line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_tax_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES tax_invoices(id) ON DELETE CASCADE,
        KEY idx_invoice (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Tax invoice tables are ready.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "Error creating tables: " . $e->getMessage();
}
