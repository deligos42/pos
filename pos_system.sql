-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 23, 2026 at 05:45 PM
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
-- Database: `pos_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Electronics'),
(2, 'Clothing'),
(3, 'Groceries');

-- --------------------------------------------------------

--
-- Table structure for table `recommendation_letters`
--

CREATE TABLE `recommendation_letters` (
  `id` int(11) NOT NULL,
  `ref_no` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `loyalty_points` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `email`, `phone`, `address`, `loyalty_points`, `created_at`) VALUES
(1, 'John', 'Doet', 'john@example.com', '0712345678', '30200', 100, '2026-06-20 08:58:49');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `user_id`, `category`, `description`, `amount`, `expense_date`, `created_at`) VALUES
(1, 1, 'FOOD', 'LUNCH', 200.00, '2026-06-22', '2026-06-22 10:44:46');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `qty_change` int(11) NOT NULL,
  `type` enum('sale','restock','adjust') NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `log_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `product_id`, `user_id`, `qty_change`, `type`, `note`, `log_date`) VALUES
(1, 1, 1, -1, 'sale', 'Sale #INV-20260620-3218', '2026-06-20 09:27:26'),
(2, 2, 1, -1, 'sale', 'Sale #INV-20260621-8122', '2026-06-21 06:45:46'),
(3, 1, 1, -1, 'sale', 'Sale #INV-20260621-7097', '2026-06-21 07:58:14'),
(4, 4, 1, -10, 'sale', 'Sale #INV-20260621-1571', '2026-06-21 08:57:34'),
(5, 1, 2, -1, 'sale', 'Sale #INV-20260622-1628', '2026-06-22 06:39:49'),
(6, 1, 2, -1, 'sale', 'Sale #INV-20260622-3171', '2026-06-22 06:40:19'),
(7, 2, 2, -1, 'sale', 'Sale #INV-20260622-3171', '2026-06-22 06:40:19'),
(8, 1, 1, -1, 'sale', 'Sale #INV-20260622-7418', '2026-06-22 08:31:00'),
(9, 6, 1, -1, 'sale', 'Sale #INV-20260623-9492', '2026-06-22 21:57:38'),
(10, 3, 1, -1, 'sale', 'Sale #INV-20260623-9492', '2026-06-22 21:57:38'),
(11, 2, 1, -1, 'sale', 'Sale #INV-20260623-9492', '2026-06-22 21:57:38'),
(12, 1, 1, -1, 'sale', 'Sale #INV-20260623-9492', '2026-06-22 21:57:38'),
(13, 5, 1, -1, 'sale', 'Sale #INV-20260623-9492', '2026-06-22 21:57:38'),
(14, 1, 1, -16, 'sale', 'Sale #INV-20260623-9305', '2026-06-23 01:56:17'),
(15, 2, 1, -17, 'sale', 'Sale #INV-20260623-9305', '2026-06-23 01:56:17'),
(16, 3, 1, -18, 'sale', 'Sale #INV-20260623-9305', '2026-06-23 01:56:17'),
(17, 6, 1, -11, 'sale', 'Sale #INV-20260623-9305', '2026-06-23 01:56:17'),
(18, 5, 1, -5, 'sale', 'Sale #INV-20260623-9305', '2026-06-23 01:56:17');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `description`, `category_id`, `price`, `cost`, `stock_qty`, `reorder_level`, `created_at`) VALUES
(1, 'P1001', 'Wireless Mouse', NULL, NULL, 250.90, 150.00, 28, 5, '2026-06-20 08:58:48'),
(2, 'P1002', 'T-Shirt (M)', NULL, NULL, 15.00, 5.00, 10, 5, '2026-06-20 08:58:48'),
(3, 'P1003', 'Milk 1L', NULL, NULL, 20.50, 10.00, 81, 5, '2026-06-20 08:58:48'),
(4, 'PQ03', 'HEATER 5X', NULL, NULL, 500.00, 360.00, 0, 5, '2026-06-21 08:56:48'),
(5, 'DR100', 'DRESS 12', NULL, NULL, 500.00, 200.00, 94, 5, '2026-06-22 10:33:26'),
(6, 'P1005', 'SHIRT LX', NULL, NULL, 500.00, 350.00, 88, 5, '2026-06-22 21:55:40');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `sale_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `invoice_no`, `user_id`, `customer_id`, `total_amount`, `discount`, `tax`, `grand_total`, `payment_method`, `sale_date`) VALUES
(1, 'INV-20260620-3218', 1, NULL, 25.50, 5.00, 0.00, 24.50, 'Cash', '2026-06-20 09:27:26'),
(3, 'INV-20260621-7097', 1, NULL, 25.50, 0.00, 0.00, 25.50, 'Cash', '2026-06-21 07:58:14'),
(5, 'INV-20260621-1571', 1, NULL, 5000.00, 0.00, 0.00, 5000.00, 'Cash', '2026-06-21 08:57:34'),
(6, 'INV-20260622-1628', 2, 1, 25.50, 0.00, 0.00, 25.50, 'Cash', '2026-06-22 06:39:49'),
(7, 'INV-20260622-3171', 2, NULL, 40.50, 0.00, 0.00, 40.50, 'Cash', '2026-06-22 06:40:19'),
(8, 'INV-20260622-7418', 1, NULL, 25.50, 2.00, 0.00, 23.50, 'Cash', '2026-06-22 08:31:00'),
(9, 'INV-20260623-9492', 1, NULL, 1043.00, 100.00, 0.00, 943.00, 'Cash', '2026-06-22 21:57:38'),
(10, 'INV-20260623-9305', 1, NULL, 8708.00, 0.00, 0.00, 8708.00, 'Cash', '2026-06-23 01:56:17');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `qty`, `unit_price`, `total`) VALUES
(1, 1, 1, 1, 25.50, 25.50),
(3, 3, 1, 1, 25.50, 25.50),
(4, 5, 4, 10, 500.00, 5000.00),
(5, 6, 1, 1, 25.50, 25.50),
(6, 7, 1, 1, 25.50, 25.50),
(7, 7, 2, 1, 15.00, 15.00),
(8, 8, 1, 1, 25.50, 25.50),
(9, 9, 6, 1, 500.00, 500.00),
(10, 9, 3, 1, 2.50, 2.50),
(11, 9, 2, 1, 15.00, 15.00),
(12, 9, 1, 1, 25.50, 25.50),
(13, 9, 5, 1, 500.00, 500.00),
(14, 10, 1, 16, 25.50, 408.00),
(15, 10, 2, 17, 15.00, 255.00),
(16, 10, 3, 18, 2.50, 45.00),
(17, 10, 6, 11, 500.00, 5500.00),
(18, 10, 5, 5, 500.00, 2500.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','cashier') NOT NULL DEFAULT 'cashier',
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `id_number`, `email`, `role`, `profile_photo`, `created_at`) VALUES
(1, 'admin', '$2y$10$aWe9nvGwu6yKn07yCabdV.QJTFdubZe940QKx/Imgqt0fS9DJgmVe', 'Administrator', '0743067646', '42208690', 'admin123@gmail.com', 'admin', 'assets/profile_photos/user_1_3933a47248d1a404.jpg', '2026-06-20 08:58:48'),
(2, 'w@gmail.com', '$2y$10$0l7aitfU3EmS9WqVlb8.K.Pt2EQxOAOeaYcISV6QQqFCRPPrnH0Ya', 'WILGOS WASWA', '0712345678', '12345678', 'waswawilgos42@gmail.com', 'cashier', NULL, '2026-06-22 06:14:41'),
(3, 'FEL', '$2y$10$VyZAEKIEiss1r87n..STOOKvdGvGnWIFCxY80kJTekAajcDllXCLK', 'KITOO FELIX', '0734567342', '23456789', 'FELIXKIP@GMAIL.COM', 'cashier', NULL, '2026-06-23 01:46:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expense_date` (`expense_date`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `recommendation_letters`
--
ALTER TABLE `recommendation_letters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref_no` (`ref_no`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `recommendation_letters`
--
ALTER TABLE `recommendation_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
