-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Sep 17, 2026 at 02:05 PM
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
-- Database: `avr_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `purpose` varchar(64) NOT NULL DEFAULT 'registration',
  `code_hash` varchar(255) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `sent_count` int(11) NOT NULL DEFAULT 1,
  `last_sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `email`, `purpose`, `code_hash`, `attempts`, `sent_count`, `last_sent_at`, `expires_at`, `ip`, `created_at`) VALUES
(1, 'daiskidaks@gmail.com', 'registration', '$2y$10$PygnmMJbZbswrKmdVQ4ZjuRsnXX1Le5MSaOlpDMGTqCy5URhZyNJi', 0, 1, '2025-10-23 19:45:55', '2025-10-23 13:55:55', '::1', '2025-10-23 19:45:55');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `delivery_location` text NOT NULL,
  `payment_method` enum('COD') NOT NULL DEFAULT 'COD',
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `delivery_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 0,
  `order_status` enum('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_region` varchar(32) DEFAULT NULL,
  `delivery_span_days` int(11) NOT NULL DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_name`, `contact_number`, `delivery_location`, `payment_method`, `product_name`, `quantity`, `total_price`, `delivery_date`, `created_at`, `updated_at`, `user_id`, `customer_email`, `is_paid`, `order_status`, `shipping_region`, `delivery_span_days`) VALUES
(115, 'Mel Laurence Balasico', '09917476402', 'blk 67 lot 31, Apple street, Canlubang, City of Calamba, Laguna', 'COD', 'AVR 10 and others', 3, 139050.00, '2025-11-13', '2025-11-12 14:15:49', '2025-11-12 14:15:49', 45, 'lorenzbalasico@gmail.com', 0, 'pending', 'R4A', 3);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `serial_number` varchar(64) DEFAULT NULL,
  `warranty_start` date DEFAULT NULL,
  `warranty_end` date DEFAULT NULL,
  `warranty_duration_days` int(11) NOT NULL DEFAULT 365,
  `receipt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `product_image`, `quantity`, `unit_price`, `total_price`, `created_at`, `serial_number`, `warranty_start`, `warranty_end`, `warranty_duration_days`, `receipt`) VALUES
(241, 115, 1, 'AVR 10', 'picture products/product 1/1.jpg', 1, 70000.00, 70000.00, '2025-11-12 14:15:49', NULL, NULL, NULL, 365, NULL),
(242, 115, 6, 'AVR 20KVA BLUE', 'picture products/product 6/1.jpg', 1, 55000.00, 55000.00, '2025-11-12 14:15:49', NULL, NULL, NULL, 365, NULL),
(243, 115, 8, '500VA AVR', 'picture products/product 8/1.jpg', 1, 10000.00, 10000.00, '2025-11-12 14:15:49', NULL, NULL, NULL, 365, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_messages`
--

CREATE TABLE `order_messages` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kind` enum('user','admin') NOT NULL DEFAULT 'user',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `admin_id` int(11) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `from_admin` tinyint(1) NOT NULL DEFAULT 0,
  `seen_by_customer` tinyint(1) NOT NULL DEFAULT 0,
  `from_bot` tinyint(1) NOT NULL DEFAULT 0,
  `is_bot` tinyint(1) NOT NULL DEFAULT 0,
  `sender_type` varchar(16) NOT NULL DEFAULT 'customer',
  `sender` varchar(32) DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `specification` text DEFAULT NULL,
  `short_description` varchar(512) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `type` enum('AVR','UPS','TVSS','Other') DEFAULT 'Other',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `price`, `specification`, `short_description`, `stock`, `type`, `is_active`, `image`, `created_at`, `updated_at`) VALUES
(1, NULL, 'AVR 10', 70000.00, '10KVA, Single phase, 230V, 2 Wire + G. Input: 230V +/-20% Output: 230 +/-3%. Analog Servo Type AVR. With output contactor. Manual Bypass Breaker.', NULL, 0, 'AVR', 1, 'picture products/product 1/1.jpg', '2025-09-02 16:14:21', '2025-11-12 14:15:49'),
(2, NULL, 'AVR 15KVA', 80000.00, '15KVA 3 phase, 230V, 2 Wire + G. Input: 230V +/-20% Output: 230 +/-3%. Analog Servo Type AVR. With output contactor. Manual Bypass Breaker.', NULL, 10, 'AVR', 1, 'picture products/product 2/1.jpg', '2025-09-02 16:14:21', '2025-11-12 06:01:28'),
(3, NULL, 'AVR 20KVA', 85000.00, '20KVA 3 phase, 230V, 2 Wire + G. Input: 230V +/-20% Output: 230 +/-3%. Analog Servo Type AVR. With output contactor. Manual Bypass Breaker.', NULL, 1, 'AVR', 1, 'picture products/product 3/1.jpg', '2025-09-02 16:14:21', '2025-11-12 06:30:22'),
(4, NULL, 'AVR 30KVA', 90000.00, '30KVA 3 phase, 230V, 2 Wire + G. Input: 230V +/-20% Output: 230 +/-3%. Analog Servo Type AVR. With output contactor. Manual Bypass Breaker.', NULL, 10, 'AVR', 1, 'picture products/product 4/1.jpg', '2025-09-02 16:14:21', '2025-11-12 06:08:04'),
(5, NULL, 'TVSS 100KA/ SINGLE PHASE', 17000.00, 'TVSS. Surge suppression. 100KA /phase. Single Phase. 240V and 120-0-120V Split Phase.', NULL, 13, 'TVSS', 1, 'picture products/product 5/1.jpg', '2025-09-02 16:14:21', '2025-11-12 06:01:28'),
(6, NULL, 'AVR 20KVA BLUE', 55000.00, 'Heavy-duty AVR. 20KVA, 400V, 3 phase 4 wire and G. Input Voltage: 400 +/-20% Output Voltage: 400V +/-2%. Digital Display LCD. With Built-in Surge Protection Device.', NULL, 16, 'AVR', 1, 'picture products/product 6/1.jpg', '2025-09-02 16:14:21', '2025-11-12 14:15:49'),
(7, NULL, '3000VA AVR', 9000.00, '3000VA. Input Voltage: 150V-270V AC Output Voltage: 230V +/- 2.5% can be adjusted to 220V to 240V. Single Phase, 2 Wire + Ground.', NULL, 17, 'AVR', 1, 'picture products/product 7/1.jpg', '2025-09-02 16:14:21', '2025-11-12 06:08:04'),
(8, NULL, '500VA AVR', 10000.00, '5000VA. Input Voltage: 150V-270V AC Output Voltage: 230V +/- 2.5% can be adjusted to 220V to 240V. Single Phase, 2 Wire + Ground. Manual Bypass Function.', NULL, 13, 'AVR', 1, 'picture products/product 8/1.jpg', '2025-09-02 16:14:21', '2025-11-12 14:15:49'),
(9, NULL, '1KVA DIGITAL AVR', 13000.00, '1KVA Digital Servo Motor Automatic Voltage Regulator. Input: 150V-250VAC Output: 220V +/-2%. For any type of loads. LED Digital Display. Servo Type AVR.', NULL, 11, 'AVR', 1, 'picture products/product 9/1.jpg', '2025-09-02 16:14:21', '2025-10-06 12:43:18'),
(10, NULL, '5KVA DIGITAL AVR', 25000.00, 'Mid-range AVR. 5KVA Digital Servo Motor Automatic Voltage Regulator. Input: 150V-250VAC Output: 220V +/-2%. With Bypass Switch. For any type of loads.', NULL, 11, 'AVR', 1, 'picture products/product 10/1.jpg', '2025-09-02 16:14:21', '2025-11-12 06:08:04'),
(11, NULL, 'UPS 100V TO 300V AC', 20000.00, 'Uninterrupted Power Supply. Online Double Conversion. Input Voltage: 100V to 300V AC. Output Voltage: 230V +/-1% can be set to 220VAC to 240VAC. Capacity: 2KVA.', NULL, 8, 'UPS', 1, 'picture products/product 11/1.jpg', '2025-09-02 16:14:21', '2025-10-06 13:15:13'),
(12, NULL, '50KVA AVR', 70000.00, '50KVA SCR Non Contact Automatic Voltage Regulator. Input: 230V +/- 20% Output: 230V +/- 1%. Frequency:50/60Hz. With Phase Sequence Relay.', NULL, 5, 'AVR', 1, 'picture products/product 12/1.jpg', '2025-09-02 16:14:21', '2025-10-11 06:29:47'),
(13, NULL, 'SINGLE PHASE UPS', 8000.00, 'Compact UPS. Single Phase. 230V. 50/60HZ with External Battery Pack.', NULL, 7, 'UPS', 1, 'picture products/product 13/1.jpg', '2025-09-02 16:14:21', '2025-11-12 06:08:04'),
(14, NULL, 'UPS 1KVA', 10000.00, 'Professional UPS. 1KVA Offline Ups Single Phase. 2 Wire + Ground.', NULL, 4, 'UPS', 1, 'picture products/product 14/1.jpg', '2025-09-02 16:14:21', '2025-10-06 13:12:33'),
(15, NULL, '10KVA/9K DOUBLE CONVERSION', 15000.00, 'Enterprise UPS. 10KVA/9K Double Conversion Online Up. Single Phase Input Single Phase Output.', NULL, 20, 'UPS', 1, 'picture products/product 15/1.jpg', '2025-09-02 16:14:21', '2025-10-08 08:57:15');

-- --------------------------------------------------------

--
-- Table structure for table `product_ratings`
--

CREATE TABLE `product_ratings` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `service_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','customer') DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other','') DEFAULT '',
  `last_profile_updated` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `name`, `location`, `phone`, `username`, `avatar`, `password`, `created_at`, `role`, `status`, `dob`, `gender`, `last_profile_updated`) VALUES
(8, 'admin@avr.com', 'Freddie Rick', 'Mayapa, City of Calamba, Laguna', '09178365017', 'admin', NULL, '$2y$10$HpNTzFvcS9ZiQZqaHy.6RuCDa6vP69KvaEKCkYNqdtM7lJR5f3K6a', '2025-05-13 12:50:27', 'admin', 'active', '1980-10-06', 'male', NULL),
(45, 'lorenzbalasico@gmail.com', 'Mel Laurence Balasico', 'blk 67 lot 31, Apple street, Canlubang, City of Calamba, Laguna', '09917476402', 'Laurence', NULL, '$2y$10$EqUItXWfVAdoH8lTADIWruCWkQrg3tj.hMJIBY.sBtj8jAhI61Uhq', '2025-10-01 04:16:13', 'customer', 'active', '2005-03-17', 'male', NULL),
(49, 'miegeminiano@gmail.com', 'MA. Isabelle E. Geminiano', 'Canlubang, City of Calamba, Laguna', '09774724824', 'Isabelle', NULL, '$2y$10$3by.pRZN86jg8G4wnRiCo.fowfcIw/ulXWIURIrwwp9TacFY693.i', '2025-10-03 03:21:55', 'admin', 'active', '2004-10-31', 'female', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warranty_receipts`
--

CREATE TABLE `warranty_receipts` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `serial_no` varchar(64) DEFAULT NULL,
  `warranty_start_date` date NOT NULL,
  `warranty_end_date` date NOT NULL,
  `status` enum('active','in_progress','in_transit','claimed','completed','successful','expired') NOT NULL DEFAULT 'active',
  `claimed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `claim_notes` text DEFAULT NULL,
  `buyer_name` varchar(255) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `buyer_phone` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `in_transit_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warranty_receipts`
--

INSERT INTO `warranty_receipts` (`id`, `order_id`, `order_item_id`, `product_id`, `user_id`, `serial_no`, `warranty_start_date`, `warranty_end_date`, `status`, `claimed_at`, `completed_at`, `claim_notes`, `buyer_name`, `buyer_email`, `buyer_phone`, `product_name`, `product_image`, `unit_price`, `quantity`, `subtotal`, `total`, `created_at`, `confirmed_at`, `in_transit_at`) VALUES
(767, 104, 215, 1, 45, 'AVR-20251013-104-215', '2025-10-14', '2026-10-14', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 10', 'picture products/product 1/1.jpg', 70000.00, 5, 350000.00, 350000.00, '2025-10-13 16:55:40', NULL, NULL),
(768, 106, 217, 3, 60, 'AVR-20251013-106-217', '2025-10-14', '2026-10-14', 'active', NULL, NULL, NULL, 'Maki taki', 'Makitakipolmoki@gmail.com', '09929929299', 'AVR 20KVA', 'picture products/product 3/1.jpg', 85000.00, 1, 85000.00, 91800.00, '2025-10-13 17:30:21', NULL, NULL),
(780, 105, 216, 3, 49, 'AVR-20251013-105-216', '2025-10-14', '2026-10-14', 'active', NULL, NULL, NULL, 'MA. Isabelle E. Geminiano', 'miegeminiano@gmail.com', '09774724824', 'AVR 20KVA', 'picture products/product 3/1.jpg', 85000.00, 1, 85000.00, 87550.00, '2025-10-13 18:09:40', NULL, NULL),
(783, 107, 218, 3, 60, 'AVR-20251013-107-218', '2025-10-14', '2026-10-14', 'active', NULL, NULL, NULL, 'Maki taki', 'Makitakipolmoki@gmail.com', '09929929299', 'AVR 20KVA', 'picture products/product 3/1.jpg', 85000.00, 1, 85000.00, 91800.00, '2025-10-13 18:12:25', NULL, NULL),
(799, 109, 220, 3, 45, 'AVR-20251112-109-220', '2025-10-20', '2026-10-20', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 20KVA', 'picture products/product 3/1.jpg', 85000.00, 1, 85000.00, 87550.00, '2025-11-12 05:50:09', NULL, NULL),
(800, 110, 221, 1, 45, 'AVR-20251112-110-221', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 10', 'picture products/product 1/1.jpg', 70000.00, 1, 70000.00, 318012.50, '2025-11-12 05:57:05', NULL, NULL),
(801, 110, 222, 2, 45, 'AVR-20251112-110-222', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 15KVA', 'picture products/product 2/1.jpg', 80000.00, 1, 80000.00, 318012.50, '2025-11-12 05:57:05', NULL, NULL),
(802, 110, 223, 3, 45, 'AVR-20251112-110-223', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 20KVA', 'picture products/product 3/1.jpg', 85000.00, 1, 85000.00, 318012.50, '2025-11-12 05:57:05', NULL, NULL),
(803, 110, 224, 4, 45, 'AVR-20251112-110-224', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 30KVA', 'picture products/product 4/1.jpg', 90000.00, 1, 90000.00, 318012.50, '2025-11-12 05:57:05', NULL, NULL),
(832, 111, 225, 1, 45, 'AVR-20251112-111-225', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 10', 'picture products/product 1/1.jpg', 70000.00, 1, 70000.00, 312000.00, '2025-11-12 06:01:49', NULL, NULL),
(833, 111, 226, 2, 45, 'AVR-20251112-111-226', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 15KVA', 'picture products/product 2/1.jpg', 80000.00, 1, 80000.00, 312000.00, '2025-11-12 06:01:49', NULL, NULL),
(834, 111, 227, 4, 45, 'AVR-20251112-111-227', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 30KVA', 'picture products/product 4/1.jpg', 90000.00, 1, 90000.00, 312000.00, '2025-11-12 06:01:49', NULL, NULL),
(835, 111, 228, 5, 45, 'AVR-20251112-111-228', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'TVSS 100KA/ SINGLE PHASE', 'picture products/product 5/1.jpg', 17000.00, 1, 17000.00, 312000.00, '2025-11-12 06:01:49', NULL, NULL),
(836, 111, 229, 6, 45, 'AVR-20251112-111-229', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 20KVA BLUE', 'picture products/product 6/1.jpg', 55000.00, 1, 55000.00, 312000.00, '2025-11-12 06:01:49', NULL, NULL),
(882, 112, 230, 1, 45, 'AVR-20251112-112-230', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 10', 'picture products/product 1/1.jpg', 70000.00, 1, 70000.00, 202000.00, '2025-11-12 06:08:17', NULL, NULL),
(883, 112, 231, 4, 45, 'AVR-20251112-112-231', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 30KVA', 'picture products/product 4/1.jpg', 90000.00, 1, 90000.00, 202000.00, '2025-11-12 06:08:17', NULL, NULL),
(884, 112, 232, 7, 45, 'AVR-20251112-112-232', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', '3000VA AVR', 'picture products/product 7/1.jpg', 9000.00, 1, 9000.00, 202000.00, '2025-11-12 06:08:17', NULL, NULL),
(885, 112, 233, 10, 45, 'AVR-20251112-112-233', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', '5KVA DIGITAL AVR', 'picture products/product 10/1.jpg', 25000.00, 1, 25000.00, 202000.00, '2025-11-12 06:08:17', NULL, NULL),
(886, 112, 234, 13, 45, 'AVR-20251112-112-234', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'SINGLE PHASE UPS', 'picture products/product 13/1.jpg', 8000.00, 1, 8000.00, 202000.00, '2025-11-12 06:08:17', NULL, NULL),
(907, 113, 235, 1, 45, 'AVR-20251112-113-235', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 10', 'picture products/product 1/1.jpg', 70000.00, 1, 70000.00, 139050.00, '2025-11-12 06:25:08', NULL, NULL),
(908, 113, 236, 6, 45, 'AVR-20251112-113-236', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', 'AVR 20KVA BLUE', 'picture products/product 6/1.jpg', 55000.00, 1, 55000.00, 139050.00, '2025-11-12 06:25:08', NULL, NULL),
(909, 113, 237, 8, 45, 'AVR-20251112-113-237', '2025-11-12', '2026-11-12', 'active', NULL, NULL, NULL, 'Mel Laurence Balasico', 'lorenzbalasico@gmail.com', '09917476402', '500VA AVR', 'picture products/product 8/1.jpg', 10000.00, 1, 10000.00, 139050.00, '2025-11-12 06:25:08', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `session_token` (`session_token`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `purpose` (`purpose`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `orders_status_date_idx` (`order_status`,`delivery_date`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_messages`
--
ALTER TABLE `order_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_order_messages_created` (`created_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `product_ratings`
--
ALTER TABLE `product_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_order_product` (`user_id`,`order_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `order_item_id` (`order_item_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_review` (`product_id`,`user_id`,`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `services_status_date_idx` (`status`,`preferred_date`),
  ADD KEY `services_user_id_idx` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `warranty_receipts`
--
ALTER TABLE `warranty_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_item` (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=248;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=244;

--
-- AUTO_INCREMENT for table `order_messages`
--
ALTER TABLE `order_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `product_ratings`
--
ALTER TABLE `product_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `warranty_receipts`
--
ALTER TABLE `warranty_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=925;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_messages`
--
ALTER TABLE `order_messages`
  ADD CONSTRAINT `order_messages_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_ratings`
--
ALTER TABLE `product_ratings`
  ADD CONSTRAINT `product_ratings_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_ratings_order_item_fk` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_ratings_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_ratings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
