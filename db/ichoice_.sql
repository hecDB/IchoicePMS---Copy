-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 16, 2025 at 10:29 AM
-- Server version: 10.11.6-MariaDB-0+deb12u1-log
-- PHP Version: 8.4.12

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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`ichoice_admin`@`%` PROCEDURE `insert_locations_updated` ()   BEGIN
    DECLARE r CHAR(2);
    DECLARE b INT;
    DECLARE s INT;

    -- แถว A-X
    SET r = 'A';
    WHILE r <= 'X' DO
        SET b = 1;
        WHILE b <= 10 DO
            SET s = 1;
            WHILE s <= 10 DO
                INSERT INTO locations (row_code, bin, shelf, description)
                VALUES (r, b, s, CONCAT('แถว ', r, ' ล็อค ', b, ' ชั้น ', s));
                SET s = s + 1;
            END WHILE;
            SET b = b + 1;
        END WHILE;
        SET r = CHAR(ASCII(r) + 1);
    END WHILE;

    -- แถวพิเศษ T
    SET b = 1;
    WHILE b <= 10 DO
        SET s = 1;
        WHILE s <= 10 DO
            INSERT INTO locations (row_code, bin, shelf, description)
            VALUES ('T', b, s, CONCAT('ตู้ ล็อค ', b, ' ชั้น ', s));
            SET s = s + 1;
        END WHILE;
        SET b = b + 1;
    END WHILE;

    -- SALE(บน)
    SET b = 1;
    WHILE b <= 10 DO
        SET s = 1;
        WHILE s <= 10 DO
            INSERT INTO locations (row_code, bin, shelf, description)
            VALUES ('SALE(บน)', b, s, CONCAT('SALE(บน) ล็อค ', b, ' ชั้น ', s));
            SET s = s + 1;
        END WHILE;
        SET b = b + 1;
    END WHILE;

    -- SALE(ล่าง)
    SET b = 1;
    WHILE b <= 10 DO
        SET s = 1;
        WHILE s <= 10 DO
            INSERT INTO locations (row_code, bin, shelf, description)
            VALUES ('SALE(ล่าง)', b, s, CONCAT('SALE(ล่าง) ล็อค ', b, ' ชั้น ', s));
            SET s = s + 1;
        END WHILE;
        SET b = b + 1;
    END WHILE;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `currency_id` int(11) NOT NULL,
  `code` varchar(3) NOT NULL COMMENT 'รหัสสกุลเงิน เช่น THB, USD',
  `name` varchar(50) NOT NULL COMMENT 'ชื่อเต็มของสกุลเงิน',
  `symbol` varchar(5) NOT NULL COMMENT 'สัญลักษณ์ เช่น ฿, $',
  `exchange_rate` decimal(10,6) DEFAULT 1.000000 COMMENT 'อัตราแลกเปลี่ยนต่อบาท',
  `is_base` tinyint(1) DEFAULT 0 COMMENT 'เป็นสกุลเงินหลักหรือไม่',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'ใช้งานได้หรือไม่',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`currency_id`, `code`, `name`, `symbol`, `exchange_rate`, `is_base`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'THB', 'Thai Baht', '฿', 1.000000, 1, 1, '2025-10-04 04:48:19', '2025-10-04 04:48:19'),
(2, 'USD', 'US Dollar', '$', 39.000000, 0, 1, '2025-10-04 04:48:19', '2025-10-09 00:17:07'),
(3, 'EUR', 'Euro', '€', 38.000000, 0, 1, '2025-10-04 04:48:19', '2025-10-13 01:51:32'),
(4, 'JPY', 'Japanese Yen', '¥', 0.260000, 0, 1, '2025-10-04 04:48:19', '2025-10-13 01:51:34'),
(5, 'GBP', 'British Pound', '£', 49.000000, 0, 1, '2025-10-04 04:48:19', '2025-10-13 01:51:32');

-- --------------------------------------------------------

--
-- Table structure for table `expiry_notifications`
--

CREATE TABLE `expiry_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_date` date NOT NULL,
  `acknowledged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expiry_notifications`
--

INSERT INTO `expiry_notifications` (`id`, `user_id`, `notification_date`, `acknowledged_at`, `created_at`) VALUES
(1, 1, '2025-11-15', '2025-11-15 04:18:24', '2025-11-15 04:18:24');

-- --------------------------------------------------------

--
-- Table structure for table `issue_items`
--

CREATE TABLE `issue_items` (
  `issue_id` int(11) NOT NULL,
  `sale_order_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `receive_id` int(11) DEFAULT NULL,
  `issue_qty` int(11) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `issued_by` int(11) NOT NULL,
  `remark` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `shelf` int(11) NOT NULL,
  `bin` int(11) NOT NULL,
  `row_code` varchar(10) DEFAULT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `shelf`, `bin`, `row_code`, `description`) VALUES
(1, 3, 2, 'H', 'แถว H ล็อค 2 ชั้น 3'),
(2, 3, 2, 'H', 'แถว H ล็อค 2 ชั้น 3'),
(3, 3, 2, 'H', 'แถว H ล็อค 2 ชั้น 3'),
(4, 3, 2, 'H', 'แถว H ล็อค 2 ชั้น 3'),
(5, 1, 1, 'A', 'แถว A ล็อค 1 ชั้น 1'),
(6, 3, 3, 'A', 'แถว A ล็อค 3 ชั้น 3'),
(7, 3, 2, 'A', 'แถว A ล็อค 2 ชั้น 3'),
(8, 7, 2, 'A', 'แถว A ล็อค 2 ชั้น 7'),
(9, 2, 2, '5', 'แถว 5 ล็อค 2 ชั้น 2');

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

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
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','processed','denied') DEFAULT 'pending'
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
  `product_id` int(11) NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remark_color` varchar(100) NOT NULL,
  `remark_split` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `sku`, `barcode`, `unit`, `image`, `remark_color`, `remark_split`, `created_by`, `created_at`) VALUES
(5, 'ตัวอย่างสินค้า', 'prd001', '1234567890123', 'ชิ้น', 'images/product.jpg', 'สีแดง', 1, 1, '2025-11-15 10:03:15'),
(6, 'ซิงค์ พิโคลิเนต Zinc Picolinate 15 mg 60 Capsules - Thorne Research', 'thorne-research-zinc-picolinate-15-mg-60-capsules', '693749210023', '', 'images/https://res.bigseller.pro/sku/images/merchantsku/536850/32771552_1695110332985.jpg?imageView2/1/w/300/h/300', '', 0, 1, '2025-11-15 10:03:15'),
(7, 'วิตามินบี Stress B-Complex 60 Capsules (Thorne Research®) วิตามินบีรวม', 'thorne-research-stress-b-complex-60-capsules', '693749002963', 'ขวด', 'images/', '', 0, 1, '2025-11-15 10:14:57'),
(8, 'ธาตุเหล็ก Iron Bisglycinate 25 mg 60 Capsules - Thorne Research', 'thorne-research-iron-bisglycinate-25mg-60capsules', '693749003458', 'ขวด', 'images/', '', 0, 1, '2025-11-15 10:14:57'),
(9, '[Thorne Research] Creatine ครีเอทีน แบบผง ผลิตพลังงานระดับเซลล์ของร่างกาย เพิ่มมวลกาย', 'thorne-research-creatine-450-g', '693749006350', 'ขวด', 'images/', '', 0, 1, '2025-11-15 10:14:57'),
(10, '(REACH®) Waxed Dental Floss 50.2m or 182.8m รีช ไหมขัดฟัน เคลือบแว็กซ์', 'reach-cleanburst-cinnamon-waxed-floss-50.2-m-สีแดง', '693749750031', 'ชิ้น', 'images/', '', 0, 1, '2025-11-15 10:44:14');

-- --------------------------------------------------------

--
-- Table structure for table `product_location`
--

CREATE TABLE `product_location` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_location`
--

INSERT INTO `product_location` (`id`, `product_id`, `location_id`, `created_at`, `updated_at`) VALUES
(2, 5, 5, '2025-11-15 03:03:15', '2025-11-15 03:03:15'),
(3, 6, 6, '2025-11-15 03:03:15', '2025-11-15 03:03:15'),
(4, 7, 7, '2025-11-15 03:14:57', '2025-11-15 03:14:57'),
(5, 8, 7, '2025-11-15 03:14:57', '2025-11-15 03:14:57'),
(6, 9, 8, '2025-11-15 03:14:57', '2025-11-15 03:14:57'),
(7, 10, 9, '2025-11-15 03:44:14', '2025-11-15 03:44:14');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `po_id` int(11) NOT NULL,
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
  `total_amount_base` decimal(15,2) DEFAULT 0.00 COMMENT 'ยอดรวมในสกุลเงินฐาน (บาท)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`po_id`, `po_number`, `supplier_id`, `order_date`, `total_amount`, `ordered_by`, `status`, `remark`, `created_at`, `currency_id`, `exchange_rate`, `total_amount_original`, `total_amount_base`) VALUES
(3, 'PO1511202501', 1, '2025-11-15 00:00:00', 2909.40, 1, 'completed', '', '2025-11-15 10:49:23', 2, 39.000000, 70.00, 2730.00),
(4, 'IMEXCAL00002', 1, '2025-11-15 10:14:57', 8083.86, 1, 'completed', 'imported from excel', '2025-11-15 10:48:49', 1, 1.000000, 0.00, 0.00),
(5, 'PO1511202502', 2, '2025-11-15 00:00:00', 975.00, 1, 'completed', '', '2025-11-15 10:37:16', 2, 39.000000, 25.00, 975.00),
(6, 'IMEXCAL00003', 1, '2025-11-15 10:37:40', 7994.16, 1, 'completed', 'imported from excel', '2025-11-15 10:37:40', 1, 1.000000, 0.00, 0.00),
(7, 'IMEXCAL00004', 1, '2025-11-15 10:44:14', 900.00, 1, 'completed', 'imported from excel', '2025-11-15 10:44:14', 1, 1.000000, 0.00, 0.00),
(8, 'po1511202503', 4, '2025-11-15 00:00:00', 10943.40, 1, 'pending', '', '2025-11-15 10:49:50', 2, 39.000000, 2.30, 89.70),
(9, 'PO1511202504', 2, '2025-11-15 00:00:00', 10062.00, 1, 'pending', '', '2025-11-15 10:59:19', 2, 39.000000, 258.00, 10062.00),
(10, 'PO1511202505', 2, '2025-11-15 00:00:00', 38025.00, 1, 'pending', '', '2025-11-15 11:00:52', 2, 39.000000, 975.00, 38025.00),
(11, 'PO1511202500', 2, '2025-11-15 00:00:00', 8346.00, 1, 'completed', '', '2025-11-15 11:59:38', 2, 39.000000, 214.00, 8346.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `item_id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `sale_price` decimal(10,2) NOT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency_id` int(11) DEFAULT 1 COMMENT 'สกุลเงินของรายการ',
  `price_original` decimal(10,2) DEFAULT 0.00 COMMENT 'ราคาต่อหน่วยในสกุลเงินต้นฉบับ',
  `price_base` decimal(10,2) DEFAULT 0.00 COMMENT 'ราคาต่อหน่วยในสกุลเงินฐาน (บาท)',
  `currency` varchar(3) DEFAULT 'THB' COMMENT 'Currency code (THB, USD, etc.)',
  `original_price` decimal(10,2) DEFAULT NULL COMMENT 'Original price in the specified currency'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`item_id`, `po_id`, `product_id`, `qty`, `price_per_unit`, `sale_price`, `total`, `created_at`, `currency_id`, `price_original`, `price_base`, `currency`, `original_price`) VALUES
(7, 3, 6, 5.00, 546.00, 0.00, 2730.00, '2025-11-15 03:06:38', 2, 14.00, 546.00, 'THB', NULL),
(8, 4, 7, 6.00, 832.10, 1898.00, 4992.60, '2025-11-15 03:14:57', 1, 0.00, 0.00, 'THB', 832.10),
(9, 4, 8, 6.00, 499.26, 1.00, 2995.56, '2025-11-15 03:14:57', 1, 0.00, 0.00, 'THB', 499.26),
(10, 4, 9, 6.00, 1.00, 3.00, 6.00, '2025-11-15 03:14:57', 1, 0.00, 0.00, 'THB', 1.00),
(11, 5, 7, 1.00, 975.00, 0.00, 975.00, '2025-11-15 03:35:19', 2, 25.00, 975.00, 'THB', NULL),
(12, 6, 7, 6.00, 832.10, 1898.00, 4992.60, '2025-11-15 03:37:40', 1, 0.00, 0.00, 'THB', 832.10),
(13, 6, 8, 6.00, 499.26, 1.00, 2995.56, '2025-11-15 03:37:40', 1, 0.00, 0.00, 'THB', 499.26),
(14, 6, 9, 6.00, 1.00, 3.00, 6.00, '2025-11-15 03:37:40', 1, 0.00, 0.00, 'THB', 1.00),
(15, 7, 10, 10.00, 90.00, 550.00, 900.00, '2025-11-15 03:44:14', 1, 0.00, 0.00, 'THB', 90.00),
(16, 8, 10, 3.00, 3498.30, 0.00, 10494.90, '2025-11-15 03:49:50', 2, 89.70, 3498.30, 'THB', NULL),
(17, 4, 10, 1.00, 89.70, 0.00, 89.70, '2025-11-15 03:48:49', 2, 2.30, 89.70, 'THB', NULL),
(18, 3, 0, 2.00, 89.70, 0.00, 179.40, '2025-11-15 03:49:23', 2, 2.30, 89.70, 'THB', NULL),
(19, 8, 0, 5.00, 89.70, 0.00, 448.50, '2025-11-15 03:49:39', 2, 2.30, 89.70, 'THB', NULL),
(20, 9, 9, 6.00, 1677.00, 0.00, 10062.00, '2025-11-15 03:59:19', 2, 43.00, 1677.00, 'THB', NULL),
(21, 10, 7, 39.00, 975.00, 0.00, 38025.00, '2025-11-15 04:00:52', 2, 25.00, 975.00, 'THB', NULL),
(22, 11, 9, 4.00, 1677.00, 0.00, 6708.00, '2025-11-15 04:49:49', 2, 43.00, 1677.00, 'THB', NULL),
(23, 11, 6, 3.00, 546.00, 0.00, 1638.00, '2025-11-15 04:49:49', 2, 14.00, 546.00, 'THB', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `receive_items`
--

CREATE TABLE `receive_items` (
  `receive_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `receive_qty` decimal(10,2) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `remark_color` varchar(50) DEFAULT NULL,
  `remark_split` varchar(50) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receive_items`
--

INSERT INTO `receive_items` (`receive_id`, `item_id`, `po_id`, `receive_qty`, `expiry_date`, `remark_color`, `remark_split`, `remark`, `created_by`, `created_at`) VALUES
(7, 7, 3, 3.00, NULL, NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2025-11-15 03:08:14'),
(8, 7, 3, 2.00, NULL, NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2025-11-15 03:08:30'),
(9, 8, 4, 6.00, '2025-10-27', '', '0', '', 1, '2025-11-15 03:14:57'),
(10, 9, 4, 6.00, '1970-01-01', '', '0', '', 1, '2025-11-15 03:14:57'),
(11, 10, 4, 6.00, '1970-01-01', '', '0', '', 1, '2025-11-15 03:14:57'),
(12, 11, 5, 1.00, NULL, NULL, NULL, 'รับสินค้าจาก PO: PO1511202502', 1, '2025-11-15 03:37:16'),
(13, 12, 6, 6.00, '2025-10-27', '', '0', '', 1, '2025-11-15 03:37:40'),
(14, 13, 6, 6.00, '1970-01-01', '', '0', '', 1, '2025-11-15 03:37:40'),
(15, 14, 6, 6.00, '1970-01-01', '', '0', '', 1, '2025-11-15 03:37:40'),
(16, 15, 7, 10.00, '1970-01-01', '', '0', '', 1, '2025-11-15 03:44:14'),
(17, 22, 11, 3.00, NULL, NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2025-11-15 04:53:29'),
(18, 23, 11, 2.00, NULL, NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2025-11-15 04:53:29'),
(19, 22, 11, 1.00, NULL, NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2025-11-15 04:59:38'),
(20, 23, 11, 1.00, NULL, NULL, NULL, 'รับสินค้าจาก PO (Batch)', 1, '2025-11-15 04:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
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
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `sale_order_id` int(11) NOT NULL,
  `issue_tag` varchar(255) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'ยอดรวมทั้งหมด',
  `total_items` int(11) DEFAULT 0 COMMENT 'จำนวนรายการสินค้าทั้งหมด',
  `issued_by` int(11) NOT NULL COMMENT 'ผู้ทำรายการ',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุ',
  `sale_date` datetime DEFAULT current_timestamp() COMMENT 'วันที่ขาย',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางรายการขายหลัก';

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `name`, `phone`, `email`, `address`) VALUES
(1, 'AMAZON', '', '', ''),
(2, 'iherb.com', '', '', ''),
(3, 'walmart.com', '', '', ''),
(4, 'ebay.com', '', '', ''),
(5, 'target.com', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `tag_patterns`
--

CREATE TABLE `tag_patterns` (
  `pattern_id` int(11) NOT NULL,
  `platform` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pattern_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `regex_pattern` varchar(500) NOT NULL,
  `example_tags` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tag_patterns`
--

INSERT INTO `tag_patterns` (`pattern_id`, `platform`, `pattern_name`, `description`, `regex_pattern`, `example_tags`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Lazada', 'รูปแบบมาตรฐาน', 'รูปแบบมาตรฐานของแพลตฟอร์ม', '^[0-9]{14}$', '', 1, '2025-10-12 19:22:14', '2025-10-12 19:50:01'),
(5, 'General', 'รูปแบบทั่วไป', 'รูปแบบทั่วไปสำหรับแพลตฟอร์มอื่น', '^[A-Z]{2}[0-9]{6,10}$', NULL, 1, '2025-10-12 19:22:14', '2025-10-12 19:22:14'),
(6, 'Shopee', 'Shopee มาตรฐาน 2025 (ตัวอักษร+ตัวเลข)', 'รูปแบบใหม่ 5-6 ตัวอักษร (A-Z, a-z) ตามด้วย 8 ตัวเลข สำหรับปี 2025', '^[0-9]{6}[A-Z,0-9]{8}$', '', 1, '2025-10-12 19:34:25', '2025-10-13 01:50:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `role` enum('staff','manager','admin') DEFAULT 'staff',
  `is_approved` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `require_password_change` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `department`, `role`, `is_approved`, `status`, `created_at`, `require_password_change`) VALUES
(1, 'Admin Demo', 'admin@ichoicepms.com', '$2y$10$wX47BCVAdo3K89ZWLOOW2OLbXqoH9STNPw.BGaIgS7aXN5fyTfZAC', 'admin', 'admin', 1, 'approved', '2025-08-25 19:59:13', 0);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`currency_id`);

--
-- Indexes for table `expiry_notifications`
--
ALTER TABLE `expiry_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `issue_items`
--
ALTER TABLE `issue_items`
  ADD PRIMARY KEY (`issue_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `product_location`
--
ALTER TABLE `product_location`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`po_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `receive_items`
--
ALTER TABLE `receive_items`
  ADD PRIMARY KEY (`receive_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_menu_permissions`
--
ALTER TABLE `role_menu_permissions`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`sale_order_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `tag_patterns`
--
ALTER TABLE `tag_patterns`
  ADD PRIMARY KEY (`pattern_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `v_purchase_order_items_with_currency`
--
ALTER TABLE `v_purchase_order_items_with_currency`
  ADD PRIMARY KEY (`item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `currency_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expiry_notifications`
--
ALTER TABLE `expiry_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `issue_items`
--
ALTER TABLE `issue_items`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product_location`
--
ALTER TABLE `product_location`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `po_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `receive_items`
--
ALTER TABLE `receive_items`
  MODIFY `receive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `role_menu_permissions`
--
ALTER TABLE `role_menu_permissions`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `sale_order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tag_patterns`
--
ALTER TABLE `tag_patterns`
  MODIFY `pattern_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `v_purchase_order_items_with_currency`
--
ALTER TABLE `v_purchase_order_items_with_currency`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
