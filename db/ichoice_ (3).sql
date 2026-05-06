-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 20, 2026 at 11:44 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ichoice_`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `description`, `created_at`) VALUES
(1, 1, 'cancel_po_item', 'ยกเลิกสินค้าจาก PO PO-2026-00003 (cancel_partial): เหตุผล=out_of_stock, จำนวน=1', '2026-03-20 15:01:49');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_categories`
--

CREATE TABLE `borrow_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `borrow_categories`
--

INSERT INTO `borrow_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'โฆษณา / Marketing', 'สินค้าใช้สำหรับการโฆษณาหรือการตลาด', '2025-11-21 06:30:04'),
(2, 'ตรวจสอบ / QC', 'สินค้าสำหรับการตรวจสอบคุณภาพหรือทดสอบ', '2025-11-21 06:30:04'),
(3, 'เปรียบเทียบ / Demo', 'สินค้าสำหรับการสาธิตหรือเปรียบเทียบกับคู่แข่ง', '2025-11-21 06:30:04'),
(4, 'วิจัย / Research', 'สินค้าสำหรับการวิจัยและพัฒนา', '2025-11-21 06:30:04'),
(5, 'อื่นๆ', 'อื่นๆ', '2025-11-21 06:30:04');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_items`
--

CREATE TABLE `borrow_items` (
  `borrow_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `borrow_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `image` longblob DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`borrow_item_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_borrow_id` (`borrow_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `currency_id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(5) NOT NULL COMMENT 'รหัสสกุลเงิน เช่น THB, USD',
  `name` varchar(50) NOT NULL COMMENT 'ชื่อเต็มของสกุลเงิน',
  `symbol` varchar(5) NOT NULL COMMENT 'สัญลักษณ์ เช่น ฿, $',
  `exchange_rate` decimal(10,6) DEFAULT 1.000000 COMMENT 'อัตราแลกเปลี่ยนต่อบาท',
  `is_base` tinyint(1) DEFAULT 0 COMMENT 'เป็นสกุลเงินหลักหรือไม่',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'ใช้งานได้หรือไม่',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`currency_id`, `code`, `name`, `symbol`, `exchange_rate`, `is_base`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'THB', 'Thai Baht', '฿', 1.000000, 1, 1, '2025-10-03 21:48:19', '2025-10-03 21:48:19'),
(2, 'USD', 'US Dollar', '$', 39.000000, 0, 1, '2025-10-03 21:48:19', '2025-11-21 07:14:33'),
(3, 'EUR', 'Euro', '€', 40.000000, 0, 1, '2025-10-03 21:48:19', '2025-12-03 06:20:09'),
(4, 'JPY', 'Japanese Yen', '¥', 0.260000, 0, 1, '2025-10-03 21:48:19', '2025-10-12 18:51:34'),
(5, 'GBP', 'British Pound', '£', 49.000000, 0, 1, '2025-10-03 21:48:19', '2025-10-12 18:51:32');

-- --------------------------------------------------------

--
-- Table structure for table `damaged_return_inspections`
--

CREATE TABLE `damaged_return_inspections` (
  `inspection_id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `return_code` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `receive_id` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `po_id` int(11) DEFAULT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `return_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason_id` int(11) NOT NULL DEFAULT 0,
  `reason_name` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `new_sku` varchar(100) DEFAULT NULL,
  `new_product_id` int(11) DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT NULL,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `restock_qty` decimal(12,2) DEFAULT NULL,
  `defect_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `inspected_by` int(11) DEFAULT NULL,
  `inspected_at` datetime DEFAULT NULL,
  `restocked_by` int(11) DEFAULT NULL,
  `restocked_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`inspection_id`),
  UNIQUE KEY `uniq_return_id` (`return_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expiry_notifications`
--

CREATE TABLE `expiry_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_date` date NOT NULL,
  `acknowledged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_notification_date` (`user_id`,`notification_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expiry_notifications`
--

INSERT INTO `expiry_notifications` (`id`, `user_id`, `notification_date`, `acknowledged_at`, `created_at`) VALUES
(1, 1, '2026-03-02', '2026-03-02 03:14:03', '2026-03-02 03:14:03');

-- --------------------------------------------------------

--
-- Table structure for table `issue_items`
--

CREATE TABLE `issue_items` (
  `issue_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_order_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `receive_id` int(11) DEFAULT NULL,
  `issue_qty` int(11) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `issued_by` int(11) NOT NULL,
  `remark` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`issue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `issue_items`
--

INSERT INTO `issue_items` (`issue_id`, `sale_order_id`, `product_id`, `receive_id`, `issue_qty`, `expiry_date`, `sale_price`, `cost_price`, `issued_by`, `remark`, `created_at`) VALUES
(1, 1, 16, 12, 1, '2026-03-31', 300.00, 12.00, 1, 'ยิงสินค้าจากแท็ค: 125478965485', '2026-03-20 15:27:22');

-- --------------------------------------------------------

--
-- Table structure for table `item_borrows`
--

CREATE TABLE `item_borrows` (
  `borrow_id` int(11) NOT NULL AUTO_INCREMENT,
  `borrow_number` varchar(50) NOT NULL,
  `borrow_date` datetime DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `borrower_name` varchar(100) NOT NULL,
  `borrower_phone` varchar(20) DEFAULT NULL,
  `borrower_email` varchar(100) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `expected_return_date` datetime DEFAULT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `status` enum('active','returned','overdue','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`borrow_id`),
  UNIQUE KEY `borrow_number` (`borrow_number`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_borrow_date` (`borrow_date`),
  KEY `idx_expected_return` (`expected_return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL AUTO_INCREMENT,
  `shelf` int(11) NOT NULL,
  `bin` int(11) NOT NULL,
  `row_code` varchar(10) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

-- 🔥 แถว A-X (rows A to X, bins 1-10, shelves 1-10)
INSERT INTO `locations` (`row_code`, `bin`, `shelf`, `description`)
WITH RECURSIVE nums AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nums WHERE n < 10
),
letters AS (
    SELECT 'A' AS r UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D' UNION SELECT 'E'
    UNION SELECT 'F' UNION SELECT 'G' UNION SELECT 'H' UNION SELECT 'I' UNION SELECT 'J'
    UNION SELECT 'K' UNION SELECT 'L' UNION SELECT 'M' UNION SELECT 'N' UNION SELECT 'O'
    UNION SELECT 'P' UNION SELECT 'Q' UNION SELECT 'R' UNION SELECT 'S' UNION SELECT 'T'
    UNION SELECT 'U' UNION SELECT 'V' UNION SELECT 'W' UNION SELECT 'X'
)
SELECT
    l.r,
    b.n,
    s.n,
    CONCAT('แถว ', l.r, ' ล็อค ', b.n, ' ชั้น ', s.n)
FROM letters l
CROSS JOIN nums b
CROSS JOIN nums s;

-- 🔥 SALE บน
INSERT INTO `locations` (`row_code`, `bin`, `shelf`, `description`)
WITH RECURSIVE nums AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nums WHERE n < 10
)
SELECT
    'SALE_TOP',
    b.n,
    s.n,
    CONCAT('SALE บน ล็อค ', b.n, ' ชั้น ', s.n)
FROM nums b
CROSS JOIN nums s;

-- 🔥 SALE ล่าง
INSERT INTO `locations` (`row_code`, `bin`, `shelf`, `description`)
WITH RECURSIVE nums AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nums WHERE n < 10
)
SELECT
    'SALE_BOTTOM',
    b.n,
    s.n,
    CONCAT('SALE ล่าง ล็อค ', b.n, ' ชั้น ', s.n)
FROM nums b
CROSS JOIN nums s;

-- --------------------------------------------------------

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `menu_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `menu_name`) VALUES
(4, 'จัดการผู้ใช้'),
(1, 'จัดการสินค้า'),
(2, 'รายงานสต็อก'),
(3, 'ใบสั่งซื้อ');

-- --------------------------------------------------------

--
-- Table structure for table `missing_products`
--

CREATE TABLE `missing_products` (
  `missing_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity_missing` decimal(10,2) NOT NULL COMMENT 'จำนวนที่สูญหายหรือหาไม่เจอ',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
  `reported_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`missing_id`),
  KEY `fk_product_id` (`product_id`),
  KEY `fk_reported_by` (`reported_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตารางบันทึกสินค้าสูญหายหรือหาไม่เจอ';

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(100) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','processed','denied') DEFAULT 'pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_requests`
--

INSERT INTO `password_reset_requests` (`id`, `user_email`, `user_name`, `requested_at`, `status`) VALUES
(1, 'stock@ichoicepms.com', 'stock_1', '2025-08-26 22:39:00', 'processed'),
(2, 'stock@ichoicepms.com', 'stock_1', '2025-08-26 22:39:14', 'processed'),
(3, 'stock@ichoicepms.com', 'stock_1', '2025-08-27 22:26:11', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remark_color` varchar(100) NOT NULL,
  `remark_weight` varchar(255) DEFAULT NULL,
  `remark_split` int(11) NOT NULL,
  `is_active` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `product_category_id` int(11) DEFAULT NULL,
  `category_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อประเภท',
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `sku`, `barcode`, `unit`, `image`, `remark_color`, `remark_weight`, `remark_split`, `is_active`, `created_by`, `created_at`, `product_category_id`, `category_name`) VALUES
(1, 'ปากกาลูกลื่นสีน้ำเงิน', 'prd001', '1234567000001', 'ชิ้น', 'images/pen_blue.jpg', 'สีน้ำเงิน', NULL, 1, 1, 1, '2026-03-03 15:18:51', 5, NULL),
(2, 'สมุดโน้ต A5', 'prd002', '1234567000002', 'เล่ม', 'images/notebook_a5.jpg', 'สีขาว', NULL, 1, 1, 1, '2026-03-03 15:18:51', 5, NULL),
(3, 'แฟ้มเอกสารพลาสติก', 'prd003', '1234567000003', 'ชิ้น', 'images/file_plastic.jpg', 'สีใส', NULL, 1, 1, 1, '2026-03-03 15:18:51', 5, NULL),
(4, 'น้ำดื่ม 600ml', 'prd004', '1234567000004', 'ขวด', 'images/water600.jpg', '-', NULL, 1, 1, 1, '2026-03-03 15:18:51', 6, NULL),
(5, 'กาแฟกระป๋อง', 'prd005', '1234567000005', 'กระป๋อง', 'images/coffee_can.jpg', '-', NULL, 1, 1, 1, '2026-03-03 15:18:51', 6, NULL),
(6, 'กระดาษ A4 80gsm', 'prd006', '1234567000006', 'รีม', 'images/paper_a4.jpg', 'สีขาว', NULL, 1, 1, 1, '2026-03-03 15:18:51', 6, NULL),
(7, 'สาย USB Type-C', 'prd007', '1234567000007', 'เส้น', 'images/usb_typec.jpg', 'สีดำ', NULL, 1, 1, 1, '2026-03-03 15:18:51', 6, NULL),
(8, 'เมาส์ไร้สาย', 'prd008', '1234567000008', 'ชิ้น', 'images/mouse_wireless.jpg', 'สีดำ', NULL, 1, 1, 1, '2026-03-03 15:18:51', 3, NULL),
(9, 'แชมพู 450ml', 'prd009', '1234567000009', 'ขวด', 'images/shampoo.jpg', 'สีชมพู', NULL, 1, 1, 1, '2026-03-03 15:18:51', 3, NULL),
(10, 'สบู่ก้อน', 'prd010', '1234567000010', 'ก้อน', 'images/soap.jpg', 'สีขาว', NULL, 1, 1, 1, '2026-03-03 15:18:51', 3, NULL),
(11, 'กาแฟกระป๋อง (สินค้ามีตำหนิ)', 'ตำหนิ-prd005', '1234567000005', 'กระป๋อง', 'images/coffee_can.jpg', '-', NULL, 1, 1, 1, '2026-03-03 15:22:05', 6, NULL),
(12, 'สบู่ก้อน (สินค้ามีตำหนิ)', 'ตำหนิ-prd010', 'BAR-9-TBBE71B89E31', 'ก้อน', 'images/soap.jpg', 'สีขาว', NULL, 1, 1, 1, '2026-03-03 15:28:13', 3, NULL),
(13, 'น้ำดื่ม 600ml (สินค้ามีตำหนิ)', 'ตำหนิ-prd004', 'BAR-12-TBBK6O4C3EF2', 'ขวด', 'images/water600.jpg', '-', NULL, 1, 1, 1, '2026-03-03 17:37:36', 6, NULL),
(14, 'สาย USB Type-C (สินค้ามีตำหนิ)', 'ตำหนิ-prd007', 'BAR-13-TBBKIOA9B526', 'เส้น', 'images/usb_typec.jpg', 'สีดำ', NULL, 1, 1, 1, '2026-03-03 17:44:48', 6, NULL),
(15, 'กระดาษ A4 80gsm (สินค้ามีตำหนิ)', 'ตำหนิ-prd006', 'BAR-14-TBBKTH689E23', 'รีม', 'images/paper_a4.jpg', 'สีขาว', NULL, 1, 1, 1, '2026-03-03 17:51:17', 6, NULL),
(16, 'กระปิ', 'ตำหนิ--กระปิ', 'TMP-16-7H13Z6I12', 'หน่วย', 'images/temp_product_13_1773993540.jpg', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ--) | จำนวนที่รับกลับ: 2 | เอกสารคืนสินค้า: RET-20260320-3312', '0.3', 0, 1, 1, '2026-03-20 14:59:04', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_category`
--

CREATE TABLE `product_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `product_category`
--

INSERT INTO `product_category` (`category_id`, `category_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'อาหารเสริม', 'วิตามิน อาหารเสริม สารอาหาร', '2025-11-21 06:47:35', '2025-11-21 06:47:35'),
(2, 'เครื่องใช้ไฟฟ้า', 'อุปกรณ์ไฟฟ้า เครื่องใช้ในครัว', '2025-11-21 06:47:35', '2025-11-21 06:47:35'),
(3, 'เครื่องสำอาง/ความงาม', 'เครื่องสำอาง สกินแคร์ ผลิตภัณฑ์ความงาม', '2025-11-21 06:47:35', '2025-11-21 06:47:35'),
(4, 'สำหรับแม่และเด็ก', 'ผลิตภัณฑ์แม่และเด็ก นม ผ้าอ้อม', '2025-11-21 06:47:35', '2025-11-21 06:47:35'),
(5, 'สัตว์เลี้ยง', 'อาหารสัตว์เลี้ยง อุปกรณ์สัตว์เลี้ยง', '2025-11-21 06:47:35', '2025-11-21 06:47:35'),
(6, 'เครื่องใช้ในบ้าน/ออฟฟิศ', 'เฟอร์นิเจอร์ เครื่องใช้บ้าน สำนักงาน', '2025-11-21 06:47:35', '2025-11-21 06:47:35');

-- --------------------------------------------------------

--
-- Table structure for table `product_holding`
--

CREATE TABLE `product_holding` (
  `holding_id` int(11) NOT NULL AUTO_INCREMENT,
  `holding_code` varchar(50) NOT NULL COMMENT 'รหัสอ้างอิงพักสินค้า เช่น HOLD-20251121-001',
  `product_id` int(11) NOT NULL COMMENT 'ID สินค้า',
  `receive_id` int(11) NOT NULL COMMENT 'ID การรับเข้า (เพื่อลดจำนวนในตาราง receive_items)',
  `original_sku` varchar(50) DEFAULT NULL COMMENT 'SKU เดิม',
  `new_sku` varchar(50) DEFAULT NULL COMMENT 'SKU ใหม่ (อาจจะยังไม่กำหนด)',
  `holding_qty` int(11) NOT NULL COMMENT 'จำนวนสินค้าที่พักไว้',
  `cost_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาต้นทุนต่อหน่วย',
  `sale_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาขายต่อหน่วย (พร้อมส่วนลด)',
  `holding_reason` varchar(255) DEFAULT NULL COMMENT 'เหตุผลการพักสินค้า เช่น "โปรโมชั่นสินค้าใกล้หมดอายุ"',
  `promo_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อโปรโมชั่น',
  `promo_discount` int(11) DEFAULT NULL COMMENT 'ส่วนลด (%)',
  `expiry_date` date DEFAULT NULL COMMENT 'วันหมดอายุของสินค้า',
  `days_to_expire` int(11) DEFAULT NULL COMMENT 'จำนวนวันที่เหลือก่อนหมดอายุ',
  `status` enum('holding','moved_to_sale','returned_to_stock','disposed') DEFAULT 'holding' COMMENT 'สถานะ: holding=พักไว้, moved_to_sale=ย้ายไปขาย, returned_to_stock=คืนกลับคลัง, disposed=ทำลาย',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'เวลาสร้างบันทึก',
  `created_by` int(11) DEFAULT NULL COMMENT 'ผู้สร้าง (user_id)',
  `moved_at` datetime DEFAULT NULL COMMENT 'เวลาที่ย้ายไปขาย',
  `moved_by` int(11) DEFAULT NULL COMMENT 'ผู้ย้ายไปขาย',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
  PRIMARY KEY (`holding_id`),
  UNIQUE KEY `holding_code` (`holding_code`),
  KEY `idx_holding_code` (`holding_code`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_receive_id` (`receive_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_created_at` (`created_at`),
  KEY `created_by` (`created_by`),
  KEY `moved_by` (`moved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางพักสินค้าใกล้หมดอายุ';

-- --------------------------------------------------------

--
-- Table structure for table `product_location`
--

CREATE TABLE `product_location` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_location`
--

INSERT INTO `product_location` (`id`, `product_id`, `location_id`, `created_at`, `updated_at`) VALUES
(1, 1, 5, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(2, 2, 11, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(3, 3, 20, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(4, 4, 110, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(5, 5, 111, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(6, 6, 210, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(7, 7, 220, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(8, 8, 221, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(9, 9, 310, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(10, 10, 311, '2026-03-03 08:18:51', '2026-03-03 08:18:51'),
(11, 16, 1013, '2026-03-20 07:59:04', '2026-03-20 07:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `po_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(20) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL COMMENT 'ลูกค้า',
  `order_date` datetime DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `ordered_by` int(11) DEFAULT NULL COMMENT 'ผู้บันทึก',
  `status` enum('pending','partial','completed','cancel') DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency_id` int(11) DEFAULT 1 COMMENT 'สกุลเงินที่ใช้',
  `exchange_rate` decimal(10,6) DEFAULT 1.000000 COMMENT 'อัตราแลกเปลี่ยนขณะสั่งซื้อ',
  `total_amount_original` decimal(15,2) DEFAULT 0.00 COMMENT 'ยอดรวมในสกุลเงินต้นฉบับ',
  `total_amount_base` decimal(15,2) DEFAULT 0.00 COMMENT 'ยอดรวมในสกุลเงินฐาน (บาท)',
  PRIMARY KEY (`po_id`),
  KEY `fk_purchase_orders_currency` (`currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`po_id`, `po_number`, `supplier_id`, `order_date`, `total_amount`, `ordered_by`, `status`, `remark`, `created_at`, `currency_id`, `exchange_rate`, `total_amount_original`, `total_amount_base`) VALUES
(1, 'IMEXCAL00001', 1, '2026-03-03 09:18:51', 639340.00, 1, 'completed', 'imported from excel (Original: 12 USD) (Original: 95 USD) (Original: 45 USD) (Original: 150 USD) (Original: 89 USD) (Original: 15 USD)', '2026-03-03 15:18:51', 1, 1.000000, 0.00, 0.00),
(2, 'PO-2026-00001', 1, '2026-03-03 00:00:00', 2340.00, 1, 'completed', '\nครบตามสั่ง [รับดี: 1 + ชำรุด(ขายได้): 4]\nครบตามสั่ง [รับดี: 1 + ชำรุด(ขายได้): 4]', '2026-03-03 16:10:38', 2, 39.000000, 60.00, 2340.00),
(3, 'PO-2026-00002', 2, '2026-03-03 00:00:00', 1755.00, 1, 'completed', '\nครบตามสั่ง [ชำรุด(ขายได้): 3]\nครบตามสั่ง [ชำรุด(ขายได้): 3]\nครบตามสั่ง [ชำรุด(ขายได้): 3]', '2026-03-03 16:14:58', 2, 39.000000, 45.00, 1755.00),
(4, 'PO-2026-00003', 2, '2026-03-03 00:00:00', 29.25, 1, 'completed', '\nครบตามสั่ง [รับดี: 1 + ชำรุด(ขายได้): 1 + ชำรุด(ขายไม่ได้): 2 + ยกเลิก: 1]', '2026-03-20 15:02:38', 2, 39.000000, 0.75, 29.25),
(5, 'PO-2026-00004', 5, '2026-03-03 00:00:00', 16185.00, 1, 'completed', '\nครบตามสั่ง [รับดี: 3 + ชำรุด(ขายได้): 5 + ชำรุด(ขายไม่ได้): 1]', '2026-03-20 15:00:45', 2, 39.000000, 415.00, 16185.00),
(6, 'PO-New-2026-00006', 5, '2026-03-03 00:00:00', 120.00, 1, 'partial', 'New Product Purchase', '2026-03-03 18:09:46', 1, 1.000000, 120.00, 120.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `temp_product_id` int(11) DEFAULT NULL COMMENT 'ลิงก์ไปยัง temp_products หากเป็นสินค้าใหม่',
  `qty` decimal(10,2) DEFAULT NULL,
  `is_cancelled` tinyint(1) DEFAULT 0,
  `is_partially_cancelled` tinyint(1) DEFAULT 0,
  `cancel_qty` int(11) DEFAULT 0,
  `cancel_qty_reason` float DEFAULT 0,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(100) DEFAULT NULL,
  `cancel_notes` text DEFAULT NULL,
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `sale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency_id` int(11) DEFAULT 1 COMMENT 'สกุลเงินของรายการ',
  `price_original` decimal(10,2) DEFAULT 0.00 COMMENT 'ราคาต่อหน่วยในสกุลเงินต้นฉบับ',
  `price_base` decimal(10,2) DEFAULT 0.00 COMMENT 'ราคาต่อหน่วยในสกุลเงินฐาน (บาท)',
  `unit_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคา/หน่วย (alias for price_per_unit)',
  `unit` varchar(20) DEFAULT NULL COMMENT 'หน่วยนับ',
  `po_item_amount` decimal(12,2) DEFAULT NULL COMMENT 'ยอดรวมรายการ',
  PRIMARY KEY (`item_id`),
  KEY `idx_temp_product_id` (`temp_product_id`),
  KEY `fk_purchase_order_items_currency` (`currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`item_id`, `po_id`, `product_id`, `temp_product_id`, `qty`, `is_cancelled`, `is_partially_cancelled`, `cancel_qty`, `cancel_qty_reason`, `cancelled_by`, `cancelled_at`, `cancel_reason`, `cancel_notes`, `price_per_unit`, `sale_price`, `total`, `created_at`, `currency_id`, `price_original`, `price_base`, `unit_price`, `unit`, `po_item_amount`) VALUES
(1, 1, 1, NULL, 50.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 5.00, 10.00, 250.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(2, 1, 2, NULL, 30.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 25.00, 35.00, 750.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(3, 1, 3, NULL, 40.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 12.00, 20.00, 480.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(4, 1, 4, NULL, 100.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 6.00, 10.00, 600.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(5, 1, 5, NULL, 80.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 468.00, 702.00, 37440.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(6, 1, 6, NULL, 25.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 3705.00, 4680.00, 92625.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(7, 1, 7, NULL, 60.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 1755.00, 3081.00, 105300.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(8, 1, 8, NULL, 35.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 5850.00, 9750.00, 204750.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(9, 1, 9, NULL, 45.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 3471.00, 5031.00, 156195.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(10, 1, 10, NULL, 70.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 585.00, 975.00, 40950.00, '2026-03-03 08:18:51', 1, 0.00, 0.00, NULL, NULL, NULL),
(11, 2, 5, NULL, 5.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 468.00, 702.00, 2340.00, '2026-03-03 08:20:29', 2, 12.00, 468.00, NULL, NULL, NULL),
(12, 3, 10, NULL, 3.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 585.00, 0.00, 1755.00, '2026-03-03 08:27:12', 2, 15.00, 585.00, NULL, NULL, NULL),
(13, 4, 4, NULL, 5.00, 0, 1, 1, 0, 1, '2026-03-20 15:01:49', 'out_of_stock', '', 5.85, 10.00, 29.25, '2026-03-20 08:02:38', 2, 0.15, 5.85, NULL, NULL, NULL),
(14, 5, 6, NULL, 2.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 3705.00, 0.00, 7410.00, '2026-03-03 10:43:57', 2, 95.00, 3705.00, NULL, NULL, NULL),
(15, 5, 7, NULL, 5.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 1755.00, 3081.00, 8775.00, '2026-03-20 08:00:45', 2, 45.00, 1755.00, NULL, NULL, NULL),
(16, 6, NULL, 11, 10.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 12.00, 0.00, 120.00, '2026-03-03 11:02:22', 1, 12.00, 120.00, NULL, NULL, NULL),
(17, 6, 16, 13, 0.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 12.00, 300.00, NULL, '2026-03-20 07:59:04', 1, 0.00, 0.00, 300.00, NULL, NULL),
(18, 5, 14, NULL, 2.00, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 1755.00, 3081.00, 3510.00, '2026-03-20 07:59:56', 1, 0.00, 0.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `receive_items`
--

CREATE TABLE `receive_items` (
  `receive_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `receive_qty` decimal(10,2) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `remark_color` varchar(50) DEFAULT NULL,
  `remark_split` varchar(50) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`receive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receive_items`
--

INSERT INTO `receive_items` (`receive_id`, `item_id`, `po_id`, `receive_qty`, `expiry_date`, `remark_color`, `remark_split`, `remark`, `created_by`, `created_at`) VALUES
(1, 1, 1, 50.00, '2027-12-31', 'สีน้ำเงิน', '1', 'สินค้าขายดี', 1, '2026-03-03 08:18:51'),
(2, 2, 1, 30.00, '2028-12-31', 'สีขาว', '1', 'ปกแข็ง', 1, '2026-03-03 08:18:51'),
(3, 3, 1, 40.00, '2028-06-30', 'สีใส', '1', 'กันน้ำ', 1, '2026-03-03 08:18:51'),
(4, 4, 1, 100.00, '2026-05-31', '-', '1', 'ควรเก็บในที่ร่ม', 1, '2026-03-03 08:18:51'),
(5, 5, 1, 80.00, '2026-03-31', '-', '1', 'สินค้าหมุนเวียนเร็ว', 1, '2026-03-03 08:18:51'),
(6, 6, 1, 25.00, '2029-12-31', 'สีขาว', '1', '500 แผ่น/รีม', 1, '2026-03-03 08:18:51'),
(7, 7, 1, 60.00, '2030-12-31', 'สีดำ', '1', 'รองรับชาร์จเร็ว', 1, '2026-03-03 08:18:51'),
(8, 8, 1, 35.00, '2030-12-31', 'สีดำ', '1', 'รับประกัน 1 ปี', 1, '2026-03-03 08:18:51'),
(9, 9, 1, 45.00, '2027-08-31', 'สีชมพู', '1', 'กลิ่นดอกไม้', 1, '2026-03-03 08:18:51'),
(10, 10, 1, 70.00, '2027-01-31', 'สีขาว', '1', 'เหมาะกับผิวแพ้ง่าย', 1, '2026-03-03 08:18:51'),
(11, 11, 2, 1.00, '2026-05-30', NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2026-03-03 08:20:29'),
(12, 17, 6, 1.00, '2026-03-31', NULL, NULL, 'รับเข้า (อนุมัติสินค้าชำรุด) | temp_product_id=13 | product_id=16 | สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ--) | จำนวนที่รับกลับ: 2 | เอกสารคืนสินค้า: RET-20260320-3312 | หมายเหตุ: [ขายได้]', 1, '2026-03-20 07:59:04'),
(13, 18, 5, 2.00, NULL, NULL, NULL, '[Defect Item] SKU: ตำหนิ-prd007 from RET-20260320-7441 - [ขายได้] [ขายได้]', 1, '2026-03-20 07:59:56'),
(14, 15, 5, 1.00, NULL, NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2026-03-20 08:00:45'),
(15, 13, 4, 1.00, '2026-03-31', NULL, NULL, 'รับสินค้าจาก PO: PO-2026-00003', 1, '2026-03-20 08:02:38');

-- --------------------------------------------------------

--
-- Table structure for table `returned_items`
--

CREATE TABLE `returned_items` (
  `return_id` int(11) NOT NULL AUTO_INCREMENT,
  `return_code` varchar(50) NOT NULL COMMENT 'เลขที่สินค้าตีกลับ เช่น RET-2025-001',
  `po_id` int(11) DEFAULT NULL COMMENT 'PO ที่เกี่ยวข้อง (NULL ถ้าตีกลับจาก sales order)',
  `item_id` int(11) NOT NULL COMMENT 'item_id จาก purchase_order_items หรือ issue_items',
  `product_id` int(11) DEFAULT NULL,
  `temp_product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `original_qty` decimal(10,2) NOT NULL COMMENT 'จำนวนออกเดิม',
  `return_qty` decimal(10,2) NOT NULL COMMENT 'จำนวนที่ตีกลับ',
  `reason_id` int(11) NOT NULL COMMENT 'เหตุผลการตีกลับ',
  `reason_name` varchar(255) NOT NULL,
  `return_status` varchar(50) NOT NULL DEFAULT 'pending',
  `is_returnable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=สามารถคืนสต็อก, 0=ไม่สามารถคืน',
  `return_from_sales` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=ตีกลับจาก sales, 0=ตีกลับจาก purchase',
  `notes` longtext DEFAULT NULL COMMENT 'หมายเหตุต่างๆ',
  `expiry_date` date DEFAULT NULL COMMENT 'วันหมดอายุ (หากมี)',
  `created_by` int(11) NOT NULL COMMENT 'ผู้บันทึก',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `new_sku` varchar(100) DEFAULT NULL,
  `new_barcode` varchar(100) DEFAULT NULL,
  `new_product_id` int(11) DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT NULL,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `restock_qty` decimal(12,2) DEFAULT NULL,
  `defect_notes` longtext DEFAULT NULL,
  `inspected_by` int(11) DEFAULT NULL,
  `inspected_at` datetime DEFAULT NULL,
  `restocked_by` int(11) DEFAULT NULL,
  `restocked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`return_id`),
  UNIQUE KEY `return_code` (`return_code`),
  KEY `idx_return_code` (`return_code`),
  KEY `idx_po_id` (`po_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_reason_id` (`reason_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_is_returnable` (`is_returnable`),
  KEY `idx_return_from_sales` (`return_from_sales`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `returned_items`
--

INSERT INTO `returned_items` (`return_id`, `return_code`, `po_id`, `item_id`, `product_id`, `temp_product_id`, `product_name`, `sku`, `barcode`, `original_qty`, `return_qty`, `reason_id`, `reason_name`, `return_status`, `is_returnable`, `return_from_sales`, `notes`, `expiry_date`, `created_by`, `created_at`, `updated_at`, `new_sku`, `new_barcode`, `new_product_id`, `cost_price`, `sale_price`, `restock_qty`, `defect_notes`, `inspected_by`, `inspected_at`, `restocked_by`, `restocked_at`) VALUES
(8, 'RET-20260303-2510', 2, 11, 5, 4, 'กาแฟกระป๋อง', 'prd005', '1234567000005', 1.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd005 (Barcode: BAR-8-TBBDWT2A1247) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 09:22:05 | บันทึกลง temp_products (ID: 1)\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd005 (Barcode: BAR-8-TBBFAC2C1A51) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 09:51:48 | บันทึกลง temp_products (ID: 4)', '2026-05-30', 1, '2026-03-03 08:20:38', '2026-03-03 08:51:48', 'ตำหนิ-prd005', 'BAR-8-TBBFAC2C1A51', 11, 468.00, 702.00, 1.00, '[ขายได้] [ขายได้]\n[ขายได้] [ขายได้] [ขายได้]', 1, '2026-03-03 15:51:48', 1, '2026-03-03 15:51:48'),
(9, 'RET-20260303-4173', 3, 12, 10, 7, 'สบู่ก้อน', 'prd010', '1234567000010', 3.00, 2.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd010 (Barcode: BAR-9-TBBE71B89E31) จำนวน 2 โดยผู้ใช้ 1 เวลา 2026-03-03 09:28:13 | บันทึกลง temp_products (ID: 2)\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd010 (Barcode: BAR-9-TBBGCY85F057) จำนวน 2 โดยผู้ใช้ 1 เวลา 2026-03-03 10:14:58 | บันทึกลง temp_products (ID: 7)', NULL, 1, '2026-03-03 08:27:25', '2026-03-03 09:14:58', 'ตำหนิ-prd010', 'BAR-9-TBBGCY85F057', 12, 585.00, 0.00, 2.00, '[ขายได้] [ขายได้]\n[ขายได้] [ขายได้] [ขายได้]', 1, '2026-03-03 16:14:58', 1, '2026-03-03 16:14:58'),
(10, 'RET-20260303-8732', 3, 12, 10, 6, 'สบู่ก้อน', 'prd010', '1234567000010', 3.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd010 (Barcode: BAR-10-TBBEUBA78296) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 09:42:11 | บันทึกลง temp_products (ID: 3)\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd010 (Barcode: BAR-10-TBBG69B37535) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 10:10:57 | บันทึกลง temp_products (ID: 6)', NULL, 1, '2026-03-03 08:41:30', '2026-03-03 09:10:57', 'ตำหนิ-prd010', 'BAR-10-TBBG69B37535', 12, 585.00, 0.00, 1.00, '[ขายได้] [ขายได้]\n[ขายได้] [ขายได้] [ขายได้]', 1, '2026-03-03 16:10:57', 1, '2026-03-03 16:10:57'),
(11, 'RET-20260303-2017', 2, 11, 5, 5, 'กาแฟกระป๋อง', 'prd005', '1234567000005', 1.00, 3.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd005 (Barcode: BAR-11-TBBG5Q3E3482) จำนวน 3 โดยผู้ใช้ 1 เวลา 2026-03-03 10:10:38 | บันทึกลง temp_products (ID: 5)', '2026-05-30', 1, '2026-03-03 08:47:46', '2026-03-03 09:10:38', 'ตำหนิ-prd005', 'BAR-11-TBBG5Q3E3482', 11, 468.00, 702.00, 3.00, '[ขายได้] [ขายได้]', 1, '2026-03-03 16:10:38', 1, '2026-03-03 16:10:38'),
(12, 'RET-20260303-6894', 4, 13, 4, 8, 'น้ำดื่ม 600ml', 'prd004', '1234567000004', 5.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd004 (Barcode: BAR-12-TBBK6O4C3EF2) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 11:37:36 | บันทึกลง temp_products (ID: 8)', NULL, 1, '2026-03-03 10:37:03', '2026-03-03 10:37:36', 'ตำหนิ-prd004', 'BAR-12-TBBK6O4C3EF2', 13, 5.85, 0.00, 1.00, '[ขายได้] [ขายได้]', 1, '2026-03-03 17:37:36', 1, '2026-03-03 17:37:36'),
(13, 'RET-20260303-0024', 5, 15, 7, 9, 'สาย USB Type-C', 'prd007', '1234567000007', 5.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd007 (Barcode: BAR-13-TBBKIOA9B526) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 11:44:48 | บันทึกลง temp_products (ID: 9)', NULL, 1, '2026-03-03 10:44:06', '2026-03-03 10:44:48', 'ตำหนิ-prd007', 'BAR-13-TBBKIOA9B526', 14, 1755.00, 0.00, 1.00, '[ขายได้] [ขายได้]', 1, '2026-03-03 17:44:48', 1, '2026-03-03 17:44:48'),
(14, 'RET-20260303-6809', 5, 14, 6, 10, 'กระดาษ A4 80gsm', 'prd006', '1234567000006', 2.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd006 (Barcode: BAR-14-TBBKTH689E23) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 11:51:17 | บันทึกลง temp_products (ID: 10)', NULL, 1, '2026-03-03 10:50:59', '2026-03-03 10:51:17', 'ตำหนิ-prd006', 'BAR-14-TBBKTH689E23', 15, 3705.00, 0.00, 1.00, '[ขายได้] [ขายได้]', 1, '2026-03-03 17:51:17', 1, '2026-03-03 17:51:17'),
(15, 'RET-20260303-9791', 6, 16, NULL, 12, 'กระปิ', '-', 'TMP-16-6707ORC5O', 10.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-- (Barcode: BAR-15-TBBLOA693318) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 12:09:46 | บันทึกลง temp_products (ID: 12)', NULL, 1, '2026-03-03 11:02:30', '2026-03-03 11:09:46', 'ตำหนิ--', 'BAR-15-TBBLOA693318', NULL, 12.00, 0.00, 1.00, '[ขายได้] [ขายได้]', 1, '2026-03-03 18:09:46', 1, '2026-03-03 18:09:46'),
(16, 'RET-20260303-1476', 5, 14, 6, NULL, 'กระดาษ A4 80gsm', 'prd006', '1234567000006', 2.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd006 (Barcode: BAR-16-TBBLPQDC7352) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-03 12:10:38', NULL, 1, '2026-03-03 11:10:30', '2026-03-03 11:10:38', 'ตำหนิ-prd006', 'BAR-16-TBBLPQDC7352', 15, 3705.00, 0.00, 1.00, '[ขายได้] [ขายได้]', 1, '2026-03-03 18:10:38', 1, '2026-03-03 18:10:38'),
(17, 'RET-20260320-3312', 6, 17, NULL, 13, 'กระปิ', '-', 'TMP-16-7H13Z6I12', 10.00, 2.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-- (Barcode: BAR-17-TC6U3S7A223A) จำนวน 2 โดยผู้ใช้ 1 เวลา 2026-03-20 08:57:28 | บันทึกลง temp_products (ID: 13)', NULL, 1, '2026-03-20 07:57:19', '2026-03-20 07:59:04', 'ตำหนิ--', 'BAR-17-TC6U3S7A223A', NULL, 12.00, 0.00, 2.00, '[ขายได้] [ขายได้]', 1, '2026-03-20 14:57:28', 1, '2026-03-20 14:57:28'),
(18, 'RET-20260320-7441', 5, 15, 7, NULL, 'สาย USB Type-C', 'prd007', '1234567000007', 5.00, 2.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 1, 0, '[ขายได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd007 (Barcode: BAR-18-TC6U7WC9E351) จำนวน 2 โดยผู้ใช้ 1 เวลา 2026-03-20 08:59:56', NULL, 1, '2026-03-20 07:59:47', '2026-03-20 07:59:56', 'ตำหนิ-prd007', 'BAR-18-TC6U7WC9E351', 14, 1755.00, 3081.00, 2.00, '[ขายได้] [ขายได้]', 1, '2026-03-20 14:59:56', 1, '2026-03-20 14:59:56'),
(19, 'RET-20260320-7988', 5, 15, 7, NULL, 'สาย USB Type-C', 'prd007', '1234567000007', 5.00, 1.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 0, 0, '[ทิ้ง/ใช้ไม่ได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd007 (Barcode: BAR-19-TC6U8UFB285E) จำนวน 1 โดยผู้ใช้ 1 เวลา 2026-03-20 09:00:30', NULL, 1, '2026-03-20 08:00:21', '2026-03-20 08:00:30', 'ตำหนิ-prd007', 'BAR-19-TC6U8UFB285E', 14, 1755.00, 3081.00, 1.00, '[ทิ้ง/ใช้ไม่ได้] [ทิ้ง/ใช้ไม่ได้]', 1, '2026-03-20 15:00:30', 1, '2026-03-20 15:00:30'),
(20, 'RET-20260320-3762', 4, 13, 4, NULL, 'น้ำดื่ม 600ml', 'prd004', '1234567000004', 5.00, 2.00, 8, 'สินค้าชำรุดบางส่วน', 'completed', 0, 0, '[ทิ้ง/ใช้ไม่ได้]\n[INSPECTED] เปลี่ยน SKU เป็น ตำหนิ-prd004 (Barcode: BAR-20-TC6UBTE465E2) จำนวน 2 โดยผู้ใช้ 1 เวลา 2026-03-20 09:02:17', NULL, 1, '2026-03-20 08:02:09', '2026-03-20 08:02:17', 'ตำหนิ-prd004', 'BAR-20-TC6UBTE465E2', 13, 6.00, 10.00, 2.00, '[ทิ้ง/ใช้ไม่ได้] [ทิ้ง/ใช้ไม่ได้]', 1, '2026-03-20 15:02:17', 1, '2026-03-20 15:02:17');

-- --------------------------------------------------------

--
-- Table structure for table `return_reasons`
--

CREATE TABLE `return_reasons` (
  `reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `reason_code` varchar(20) NOT NULL,
  `reason_name` varchar(255) NOT NULL,
  `is_returnable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=สามารถคืนสต็อก, 0=ไม่สามารถคืน',
  `category` varchar(50) NOT NULL COMMENT 'returnable, non-returnable',
  `description` text DEFAULT NULL COMMENT 'รายละเอียดเหตุผล',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reason_id`),
  UNIQUE KEY `reason_code` (`reason_code`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_reasons`
--

INSERT INTO `return_reasons` (`reason_id`, `reason_code`, `reason_name`, `is_returnable`, `category`, `description`, `is_active`, `created_at`) VALUES
(1, '001', 'จัดส่งไม่สำเร็จ', 1, 'returnable', 'สินค้าจัดส่งไม่สำเร็จ - สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56'),
(2, '002', 'ยกเลิกคำสั่งซื้อ', 1, 'returnable', 'ลูกค้าขอยกเลิกคำสั่งซื้อ - สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56'),
(3, '003', 'ชำรุด/เสียหาย', 0, 'non-returnable', 'สินค้าชำรุดหรือเสียหาย - ไม่สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56'),
(4, '004', 'ลูกค้าปฏิเสธรับสินค้า', 1, 'returnable', 'ลูกค้าปฏิเสธการรับสินค้า - สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56'),
(5, '005', 'ส่งผิด', 1, 'returnable', 'ส่งสินค้าผิดรายการ - สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56'),
(6, '006', 'สินค้าปลอม', 0, 'non-returnable', 'สินค้าปลอมหรือหลอก - ไม่สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56'),
(7, '007', 'อื่นๆ', 0, 'non-returnable', 'เหตุผลอื่นๆ - ไม่สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56'),
(8, '008', 'สินค้าชำรุดบางส่วน', 1, 'non-returnable', 'สินค้ามีตำหนิ- สามารถคืนเข้าสต็อก', 1, '2025-12-12 09:35:56');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'staff'),
(2, 'manager'),
(3, 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `role_menu_permissions`
--

CREATE TABLE `role_menu_permissions` (
  `role_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`role_id`,`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `sale_order_id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_tag` varchar(255) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'ยอดรวมทั้งหมด',
  `total_items` int(11) DEFAULT 0 COMMENT 'จำนวนรายการสินค้าทั้งหมด',
  `issued_by` int(11) NOT NULL COMMENT 'ผู้ทำรายการ',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุ',
  `sale_date` datetime DEFAULT current_timestamp() COMMENT 'วันที่ขาย',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sale_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางรายการขายหลัก';

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`sale_order_id`, `issue_tag`, `platform`, `total_amount`, `total_items`, `issued_by`, `remark`, `sale_date`, `created_at`, `updated_at`) VALUES
(1, '125478965485', 'TikTok', 300.00, 1, 1, 'แท็คส่งออก: 125478965485 | แพลตฟอร์ม: TikTok | รูปแบบ: TikTok-TH-J&T Express', '2026-03-20 15:27:22', '2026-03-20 15:27:22', '2026-03-20 15:27:22');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `name`, `phone`, `email`, `address`) VALUES
(1, 'AMAZON', '', '', ''),
(2, 'iherb.com', '', '', ''),
(3, 'walmart.com', '', '', ''),
(4, 'ebay.com', '', '', ''),
(5, 'target.com', '', '', ''),
(6, 'ทดสอบ', '25465765', 'sdffasdfasd@sdfjkdf.com', 'as6df4asd4f16asdf');

-- --------------------------------------------------------

--
-- Table structure for table `tag_patterns`
--

CREATE TABLE `tag_patterns` (
  `pattern_id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pattern_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `regex_pattern` varchar(500) NOT NULL,
  `example_tags` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pattern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tag_patterns`
--

INSERT INTO `tag_patterns` (`pattern_id`, `platform`, `pattern_name`, `description`, `regex_pattern`, `example_tags`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Lazada', 'Lazada-TH-Flash Express', 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว', '^TH[0-9]{6}[A-Z0-9]{5,7}$', 'TH123456ABCDE, TH654321XYZ12, TH000000FLASH1', 1, '2025-12-19 06:41:29', '2025-12-19 06:41:29'),
(2, 'Lazada', 'Lazada-TH-LEX TH (LEXPU)', 'LEXPU + ตัวเลข 10 หลัก', '^LEXPU[0-9]{10}$', 'LEXPU1234567890, LEXPU9876543210, LEXPU0000000000', 1, '2025-12-19 06:41:29', '2025-12-19 06:41:29'),
(3, 'Lazada', 'Lazada-TH-LEX TH (LEXDO)', 'LEXDO + ตัวเลข 10 หลัก', '^LEXDO[0-9]{10}$', 'LEXDO1234567890, LEXDO9876543210, LEXDO0000000000', 1, '2025-12-19 06:41:29', '2025-12-19 06:41:29'),
(4, 'Shopee', 'Shopee-TH-EMS Thailand Post', 'ตัวอักษร 2 ตัว + ตัวเลข 9 หลัก + TH', '^[A-Z]{2}[0-9]{9}TH$', 'AA123456789TH, ZZ987654321TH, AB000000000TH', 1, '2025-12-19 06:41:29', '2025-12-19 06:41:29'),
(5, 'Shopee', 'Shopee-TH-Express Delivery (SHP Food)', 'ตัวเลขล้วน 19 หลัก', '^[0-9]{19}$', '1234567890123456789, 9876543210987654321, 5555555555555555555', 1, '2025-12-19 06:41:29', '2025-12-19 06:41:29'),
(6, 'Shopee', 'Shopee-TH-SPX Express', 'TH + ตัวเลข 12 หลัก + ตัวเลขหรือ A-Z 1 ตัว', '^TH[0-9]{12}[A-Z0-9]$', 'TH123456789012A, TH654321098765Z, TH000000000000X', 1, '2025-12-19 06:41:29', '2025-12-19 06:41:29'),
(7, 'TikTok', 'TikTok-TH-J&T Express', 'ตัวเลขล้วน 12 หลัก', '^[0-9]{12}$', '123456789012, 987654321098, 000000000000', 1, '2025-12-19 06:41:29', '2025-12-19 06:41:29'),
(8, 'Shopee', 'Shopee-TH-Flash Express', 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว', '^TH[0-9]{6}[A-Z0-9]{5,7}$', 'TH123456ABCDE, TH654321XYZ12, TH000000FLASH1', 1, '2025-12-19 07:12:16', '2025-12-19 07:12:16'),
(9, 'ทั่วไป', 'Pickup', 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว', '^TH[0-9]{6}[A-Z0-9]{5,7}$', 'TH123456ABCDE, TH654321XYZ12, TH000000PICKUP', 1, '2025-12-19 07:12:16', '2025-12-19 07:21:38'),
(10, 'Shopee', 'ShopeeTP', 'WB หรือ EA นำหน้า ตามด้วยตัวเลข 9 หลัก และลงท้าย TH (Thai Post)', '^(WB|EA)[0-9]{9}TH$', 'WB123456789TH, EA987654321TH, WB000000000TH', 1, '2026-02-05 01:52:44', '2026-02-05 01:52:44'),
(11, 'Shopee', 'ShopeeFlash', 'TH ตามด้วยตัวอักษร/ตัวเลข 12-13 ตัว (Flash Express)', '^TH[A-Z0-9]{12,13}$', 'THA1B2C3D4E5F6, TH1234567890ABC, THZXCVBNM12345', 1, '2026-02-05 01:52:44', '2026-02-05 01:52:44'),
(12, 'Lazada', 'LazadaFlashBulky', 'TH + ตัวเลข 7 หลัก + อักษร/ตัวเลข 6 ตัว (Flash Bulky)', '^TH[0-9]{7}[A-Z0-9]{6}$', 'TH1234567ABCDEF, TH7654321ZXCVBN, TH0000000FLASH1', 1, '2026-02-05 01:52:44', '2026-02-05 01:52:44'),
(13, 'Shopee', 'FlashRegular', 'TH ตามด้วยตัวอักษร/ตัวเลข 12-13 ตัว (Flash Regular)', '^TH[A-Z0-9]{12,13}$', 'THQWERTY123456, TH1234567890ZX, THFLASH1234567', 1, '2026-02-05 01:52:44', '2026-02-05 01:52:44'),
(14, 'Shopee', 'DeliveryFood', 'ตัวเลขล้วน 19 หลัก (Food Delivery)', '^[0-9]{19}$', '1234567890123456789, 9876543210987654321, 5555555555555555555', 1, '2026-02-05 01:52:44', '2026-02-05 01:52:44'),
(15, 'Shopee', 'Shopee-TH-Instant Delivery (ส่งทันที)', 'ตัวเลขล้วน 19 หลัก', '^[0-9]{19}$', '1111111111111111111, 2222222222222222222, 9999999999999999999', 1, '2026-02-05 01:52:44', '2026-02-05 01:52:44'),
(16, 'TikTok', 'TikTok-TH-Flash Express (Pickup)', 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว', '^TH[0-9]{6}[A-Z0-9]{5,7}$', 'TH123456ABCDE, TH654321XYZ12, TH000000PICKUP', 1, '2026-02-05 01:52:44', '2026-02-05 01:52:44');

-- --------------------------------------------------------

--
-- Table structure for table `tax_invoices`
--

CREATE TABLE `tax_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_type` varchar(50) NOT NULL COMMENT 'ประเภทเอกสาร: tax_invoice, payment_voucher, quotation, invoice',
  `inv_no` varchar(100) NOT NULL COMMENT 'เลขที่เอกสาร',
  `sales_tag` varchar(100) DEFAULT NULL COMMENT 'เลขแท็กรายการขายสินค้า',
  `inv_date` date NOT NULL COMMENT 'วันที่ออกเอกสาร',
  `platform` varchar(100) DEFAULT NULL COMMENT 'ช่องทางการสั่งซื้อ',
  `customer_name` varchar(255) NOT NULL COMMENT 'ชื่อลูกค้า/บริษัท',
  `customer_tax_id` varchar(20) DEFAULT NULL COMMENT 'เลขประจำตัวผู้เสียภาษี',
  `customer_address` text DEFAULT NULL COMMENT 'ที่อยู่ลูกค้า',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'รวมเงิน',
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลด',
  `shipping` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ค่าจัดส่ง',
  `before_vat` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'มูลค่าก่อนภาษี',
  `vat` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ภาษีมูลค่าเพิ่ม',
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'รวมทั้งสิ้น',
  `special_discount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดพิเศษ',
  `payable` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'จำนวนเงินที่ชำระ',
  `amount_text` varchar(500) DEFAULT NULL COMMENT 'จำนวนเงินเป็นตัวอักษร',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active' COMMENT 'สถานะเอกสาร',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inv_no` (`inv_no`),
  KEY `idx_inv_no` (`inv_no`),
  KEY `idx_doc_type` (`doc_type`),
  KEY `idx_inv_date` (`inv_date`),
  KEY `idx_sales_tag` (`sales_tag`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บข้อมูลใบกำกับภาษีและเอกสารอื่นๆ';

--
-- Dumping data for table `tax_invoices`
--

INSERT INTO `tax_invoices` (`id`, `doc_type`, `inv_no`, `sales_tag`, `inv_date`, `platform`, `customer_name`, `customer_tax_id`, `customer_address`, `subtotal`, `discount`, `shipping`, `before_vat`, `vat`, `grand_total`, `special_discount`, `payable`, `amount_text`, `created_at`, `updated_at`, `created_by`, `status`, `notes`) VALUES
(1, 'tax_invoice', '202601-001', 'หฟกด', '2026-03-20', 'Lazada', 'ฟหกดฟหกด', 'ฟหกดฟหกด', 'ฟกหดฟหก', 15.00, 0.00, 0.00, 14.02, 0.98, 15.00, 0.00, 15.00, 'สิบห้าบาทสตางค์', '2026-03-20 10:11:33', '2026-03-20 10:11:33', NULL, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tax_invoice_items`
--

CREATE TABLE `tax_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL COMMENT 'อ้างอิงไปยัง tax_invoices.id',
  `seq` int(11) NOT NULL DEFAULT 1 COMMENT 'ลำดับรายการสินค้า',
  `item_name` varchar(500) NOT NULL COMMENT 'รายละเอียดสินค้า/บริการ',
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00 COMMENT 'จำนวน',
  `unit` varchar(50) NOT NULL DEFAULT 'ชิ้น' COMMENT 'หน่วยนับ',
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ราคาต่อหน่วย',
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'จำนวนเงิน',
  `product_id` int(11) DEFAULT NULL COMMENT 'อ้างอิงรหัสสินค้า',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บรายละเอียดสินค้าในใบกำกับภาษี';

--
-- Dumping data for table `tax_invoice_items`
--

INSERT INTO `tax_invoice_items` (`id`, `invoice_id`, `seq`, `item_name`, `qty`, `unit`, `unit_price`, `total_price`, `product_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'ตัวอย่างสินค้าฟหกด', 1.00, 'ชิ้น', 2.00, 2.00, NULL, NULL, '2026-03-20 10:11:33', '2026-03-20 10:11:33'),
(2, 1, 2, 'ดฟหกดฟหก', 1.00, 'ชิ้น', 5.00, 5.00, NULL, NULL, '2026-03-20 10:11:33', '2026-03-20 10:11:33'),
(3, 1, 3, 'ฟกหดฟหก', 1.00, 'ชิ้น', 8.00, 8.00, NULL, NULL, '2026-03-20 10:11:33', '2026-03-20 10:11:33');

-- --------------------------------------------------------

--
-- Table structure for table `temp_products`
--

CREATE TABLE `temp_products` (
  `temp_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) NOT NULL COMMENT 'ชื่อสินค้าเบื้องต้น',
  `product_category` varchar(100) DEFAULT NULL COMMENT 'ประเภทสินค้า',
  `product_image` longblob DEFAULT NULL COMMENT 'รูปภาพสินค้า (Base64 encoded)',
  `provisional_sku` varchar(255) DEFAULT NULL COMMENT 'SKU ชั่วคราว',
  `provisional_barcode` varchar(50) DEFAULT NULL COMMENT 'Barcode ชั่วคราว',
  `unit` varchar(20) DEFAULT 'ชิ้น' COMMENT 'หน่วยนับ',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
  `remark_weight` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','rejected','converted') DEFAULT 'draft' COMMENT 'สถานะ',
  `po_id` int(11) NOT NULL COMMENT 'ใบ PO ที่อ้างอิง',
  `source_type` varchar(255) NOT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'วันหมดอายุ',
  `sale_price` decimal(12,2) DEFAULT NULL COMMENT 'ราคาขาย',
  `created_by` int(11) NOT NULL COMMENT 'สร้างโดย user_id',
  `approved_by` int(11) DEFAULT NULL COMMENT 'อนุมัติโดย user_id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้าง',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'วันที่อนุมัติ',
  PRIMARY KEY (`temp_product_id`),
  KEY `fk_po_id` (`po_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_category` (`product_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางสินค้าชั่วคราวสำหรับ PO ใหม่';

--
-- Dumping data for table `temp_products`
--

INSERT INTO `temp_products` (`temp_product_id`, `product_name`, `product_category`, `product_image`, `provisional_sku`, `provisional_barcode`, `unit`, `remark`, `remark_weight`, `status`, `po_id`, `source_type`, `expiry_date`, `sale_price`, `created_by`, `approved_by`, `created_at`, `approved_at`) VALUES
(1, 'กาแฟกระป๋อง', NULL, 0x696d616765732f636f666665655f63616e2e6a7067, 'ตำหนิ-prd005', '1234567000005', 'กระป๋อง', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd005) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-2510 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 2, '', '2026-05-30', NULL, 1, NULL, '2026-03-03 08:22:05', NULL),
(2, 'สบู่ก้อน', NULL, 0x696d616765732f736f61702e6a7067, 'ตำหนิ-prd010', '1234567000010', 'ก้อน', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd010) | จำนวนที่รับกลับ: 2 | เอกสารคืนสินค้า: RET-20260303-4173 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 3, '', NULL, NULL, 1, NULL, '2026-03-03 08:28:13', NULL),
(3, 'สบู่ก้อน', NULL, 0x696d616765732f736f61702e6a7067, 'ตำหนิ-prd010', '1234567000010', 'ก้อน', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd010) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-8732 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 3, '', NULL, NULL, 1, NULL, '2026-03-03 08:42:11', NULL),
(4, 'กาแฟกระป๋อง', NULL, 0x696d616765732f636f666665655f63616e2e6a7067, 'ตำหนิ-prd005', '1234567000005', 'กระป๋อง', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd005) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-2510 | หมายเหตุ: [ขายได้] [ขายได้]', NULL, 'pending_approval', 2, '', '2026-05-30', NULL, 1, NULL, '2026-03-03 08:51:48', NULL),
(5, 'กาแฟกระป๋อง', NULL, 0x696d616765732f636f666665655f63616e2e6a7067, 'ตำหนิ-prd005', '1234567000005', 'กระป๋อง', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd005) | จำนวนที่รับกลับ: 3 | เอกสารคืนสินค้า: RET-20260303-2017 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 2, '', '2026-05-30', NULL, 1, NULL, '2026-03-03 09:10:38', NULL),
(6, 'สบู่ก้อน', NULL, 0x696d616765732f736f61702e6a7067, 'ตำหนิ-prd010', '1234567000010', 'ก้อน', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd010) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-8732 | หมายเหตุ: [ขายได้] [ขายได้]', NULL, 'pending_approval', 3, '', NULL, NULL, 1, NULL, '2026-03-03 09:10:57', NULL),
(7, 'สบู่ก้อน', NULL, 0x696d616765732f736f61702e6a7067, 'ตำหนิ-prd010', '1234567000010', 'ก้อน', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd010) | จำนวนที่รับกลับ: 2 | เอกสารคืนสินค้า: RET-20260303-4173 | หมายเหตุ: [ขายได้] [ขายได้]', NULL, 'pending_approval', 3, '', NULL, NULL, 1, NULL, '2026-03-03 09:14:58', NULL),
(8, 'น้ำดื่ม 600ml', NULL, 0x696d616765732f77617465723630302e6a7067, 'ตำหนิ-prd004', '1234567000004', 'ขวด', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd004) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-6894 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 4, '', NULL, NULL, 1, NULL, '2026-03-03 10:37:36', NULL),
(9, 'สาย USB Type-C', NULL, 0x696d616765732f7573625f74797065632e6a7067, 'ตำหนิ-prd007', '1234567000007', 'เส้น', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd007) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-0024 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 5, '', NULL, NULL, 1, NULL, '2026-03-03 10:44:48', NULL),
(10, 'กระดาษ A4 80gsm', NULL, 0x696d616765732f70617065725f61342e6a7067, 'ตำหนิ-prd006', '1234567000006', 'รีม', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ-prd006) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-6809 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 5, '', NULL, NULL, 1, NULL, '2026-03-03 10:51:17', NULL),
(11, 'กระปิ', 'สัตว์เลี้ยง', NULL, NULL, NULL, 'ชิ้น', NULL, NULL, 'pending_approval', 6, '', NULL, NULL, 1, NULL, '2026-03-03 11:02:22', NULL),
(12, 'กระปิ', NULL, NULL, 'ตำหนิ--', 'TMP-16-6707ORC5O', 'หน่วย', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ--) | จำนวนที่รับกลับ: 1 | เอกสารคืนสินค้า: RET-20260303-9791 | หมายเหตุ: [ขายได้]', NULL, 'pending_approval', 6, '', NULL, NULL, 1, NULL, '2026-03-03 11:09:46', NULL),
(13, 'กระปิ', 'อาหารเสริม', 0x696d616765732f74656d705f70726f647563745f31335f313737333939333534302e6a7067, 'ตำหนิ--กระปิ', 'TMP-16-7H13Z6I12', 'หน่วย', 'สินค้าชำรุดบางส่วน (แปลง SKU เป็น ตำหนิ--) | จำนวนที่รับกลับ: 2 | เอกสารคืนสินค้า: RET-20260320-3312 | หมายเหตุ: [ขายได้]', '0.3', 'converted', 6, 'Damaged', '2026-03-31', 300.00, 1, 1, '2026-03-20 07:57:28', '2026-03-20 07:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `temp_product_locations`
--

CREATE TABLE `temp_product_locations` (
  `temp_product_id` int(11) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `row_code` varchar(50) DEFAULT NULL,
  `bin` varchar(50) DEFAULT NULL,
  `shelf` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`temp_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `temp_product_locations`
--

INSERT INTO `temp_product_locations` (`temp_product_id`, `location_id`, `row_code`, `bin`, `shelf`, `updated_at`) VALUES
(13, 1013, 'K', '1', '4', '2026-03-20 07:58:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `role` enum('staff','manager','admin') DEFAULT 'staff',
  `is_approved` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `require_password_change` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `department`, `role`, `is_approved`, `status`, `created_at`, `require_password_change`) VALUES
(1, 'Admin Demo', 'admin@ichoicepms.com', '$2y$10$MPLIAmLMKj9DZvAVtXdrjeCSM/m03rE1BzuGcgXC4GKQQfrZJnYuS', 'admin', 'admin', 1, 'approved', '2025-08-25 19:59:13', 0);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_purchase_orders_with_currency`
-- (See below for the actual view)
--
CREATE TABLE `v_purchase_orders_with_currency` (
`po_id` int(11)
,`po_number` varchar(20)
,`supplier_id` int(11)
,`order_date` datetime
,`total_amount` decimal(12,2)
,`ordered_by` int(11)
,`status` enum('pending','partial','completed','cancel')
,`remark` text
,`created_at` datetime
,`currency_id` int(11)
,`exchange_rate` decimal(10,6)
,`total_amount_original` decimal(15,2)
,`total_amount_base` decimal(15,2)
,`currency_code` varchar(5)
,`currency_name` varchar(50)
,`currency_symbol` varchar(5)
,`supplier_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `v_purchase_order_items_with_currency`
--

CREATE TABLE `v_purchase_order_items_with_currency` (
  `item_id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `price_original` decimal(10,2) DEFAULT NULL,
  `price_base` decimal(10,2) DEFAULT NULL,
  `currency_code` varchar(3) DEFAULT NULL,
  `currency_symbol` varchar(5) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `product_sku` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_tax_invoices_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_tax_invoices_summary` (
`id` int(11)
,`doc_type` varchar(50)
,`inv_no` varchar(100)
,`sales_tag` varchar(100)
,`inv_date` date
,`platform` varchar(100)
,`customer_name` varchar(255)
,`customer_tax_id` varchar(20)
,`payable` decimal(12,2)
,`status` varchar(20)
,`item_count` bigint(21)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `v_purchase_orders_with_currency`
--
DROP TABLE IF EXISTS `v_purchase_orders_with_currency`;

CREATE OR REPLACE VIEW `v_purchase_orders_with_currency`  AS SELECT `po`.`po_id` AS `po_id`, `po`.`po_number` AS `po_number`, `po`.`supplier_id` AS `supplier_id`, `po`.`order_date` AS `order_date`, `po`.`total_amount` AS `total_amount`, `po`.`ordered_by` AS `ordered_by`, `po`.`status` AS `status`, `po`.`remark` AS `remark`, `po`.`created_at` AS `created_at`, `po`.`currency_id` AS `currency_id`, `po`.`exchange_rate` AS `exchange_rate`, `po`.`total_amount_original` AS `total_amount_original`, `po`.`total_amount_base` AS `total_amount_base`, `c`.`code` AS `currency_code`, `c`.`name` AS `currency_name`, `c`.`symbol` AS `currency_symbol`, `s`.`name` AS `supplier_name` FROM ((`purchase_orders` `po` left join `currencies` `c` on(`po`.`currency_id` = `c`.`currency_id`)) left join `suppliers` `s` on(`po`.`supplier_id` = `s`.`supplier_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_tax_invoices_summary`
--
DROP TABLE IF EXISTS `v_tax_invoices_summary`;

CREATE OR REPLACE VIEW `v_tax_invoices_summary`  AS SELECT `ti`.`id` AS `id`, `ti`.`doc_type` AS `doc_type`, `ti`.`inv_no` AS `inv_no`, `ti`.`sales_tag` AS `sales_tag`, `ti`.`inv_date` AS `inv_date`, `ti`.`platform` AS `platform`, `ti`.`customer_name` AS `customer_name`, `ti`.`customer_tax_id` AS `customer_tax_id`, `ti`.`payable` AS `payable`, `ti`.`status` AS `status`, count(`tii`.`id`) AS `item_count`, `ti`.`created_at` AS `created_at`, `ti`.`updated_at` AS `updated_at` FROM (`tax_invoices` `ti` left join `tax_invoice_items` `tii` on(`ti`.`id` = `tii`.`invoice_id`)) GROUP BY `ti`.`id` ORDER BY `ti`.`created_at` DESC ;

--
-- AUTO_INCREMENT starting values for dumped tables
--

ALTER TABLE `activity_logs` AUTO_INCREMENT=2;
ALTER TABLE `borrow_categories` AUTO_INCREMENT=6;
ALTER TABLE `borrow_items` AUTO_INCREMENT=2;
ALTER TABLE `currencies` AUTO_INCREMENT=18;
ALTER TABLE `expiry_notifications` AUTO_INCREMENT=2;
ALTER TABLE `issue_items` AUTO_INCREMENT=2;
ALTER TABLE `item_borrows` AUTO_INCREMENT=2;
ALTER TABLE `locations` AUTO_INCREMENT=11100;
ALTER TABLE `products` AUTO_INCREMENT=17;
ALTER TABLE `product_category` AUTO_INCREMENT=7;
ALTER TABLE `product_location` AUTO_INCREMENT=12;
ALTER TABLE `purchase_orders` AUTO_INCREMENT=7;
ALTER TABLE `purchase_order_items` AUTO_INCREMENT=19;
ALTER TABLE `receive_items` AUTO_INCREMENT=16;
ALTER TABLE `returned_items` AUTO_INCREMENT=21;
ALTER TABLE `return_reasons` AUTO_INCREMENT=9;
ALTER TABLE `roles` AUTO_INCREMENT=4;
ALTER TABLE `sales_orders` AUTO_INCREMENT=2;
ALTER TABLE `suppliers` AUTO_INCREMENT=7;
ALTER TABLE `tag_patterns` AUTO_INCREMENT=17;
ALTER TABLE `tax_invoices` AUTO_INCREMENT=2;
ALTER TABLE `tax_invoice_items` AUTO_INCREMENT=4;
ALTER TABLE `temp_products` AUTO_INCREMENT=14;
ALTER TABLE `users` AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `borrow_items`
--
ALTER TABLE `borrow_items`
  ADD CONSTRAINT `borrow_items_ibfk_1` FOREIGN KEY (`borrow_id`) REFERENCES `item_borrows` (`borrow_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrow_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `damaged_return_inspections`
--
ALTER TABLE `damaged_return_inspections`
  ADD CONSTRAINT `fk_damaged_return_returned_items` FOREIGN KEY (`return_id`) REFERENCES `returned_items` (`return_id`) ON DELETE CASCADE;

--
-- Constraints for table `item_borrows`
--
ALTER TABLE `item_borrows`
  ADD CONSTRAINT `item_borrows_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `borrow_categories` (`category_id`),
  ADD CONSTRAINT `item_borrows_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `product_holding`
--
ALTER TABLE `product_holding`
  ADD CONSTRAINT `product_holding_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `product_holding_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_holding_ibfk_3` FOREIGN KEY (`moved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_purchase_orders_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`currency_id`) ON UPDATE CASCADE;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_purchase_order_items_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`currency_id`) ON UPDATE CASCADE;

--
-- Constraints for table `returned_items`
--
ALTER TABLE `returned_items`
  ADD CONSTRAINT `fk_returned_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_returned_items_reason_id` FOREIGN KEY (`reason_id`) REFERENCES `return_reasons` (`reason_id`);

--
-- Constraints for table `tax_invoice_items`
--
ALTER TABLE `tax_invoice_items`
  ADD CONSTRAINT `tax_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `tax_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `temp_products`
--
ALTER TABLE `temp_products`
  ADD CONSTRAINT `fk_po_id` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
