-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 15, 2026 at 10:22 AM
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
-- Database: `protech`
--

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'placed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `seller_id`, `total_amount`, `status`, `created_at`) VALUES
(1, 16, 17, 1521.00, 'placed', '2026-03-15 17:20:22');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES
(1, 1, 21, 1, 1521.00, '2026-03-15 17:20:22');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `icon_class` varchar(100) NOT NULL DEFAULT 'fa-solid fa-box-open',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `brand`, `category`, `description`, `price`, `stock`, `icon_class`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 17, 'ProBook X1 Ultra', 'Lenovo', 'Laptops', '15.6-inch 4K OLED with Intel i9, 32GB RAM, and 1TB SSD.', 1499.00, 15, 'fa-solid fa-laptop', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:18'),
(2, 17, 'SlimBook Air 14', 'Acer', 'Laptops', 'Thin and light work laptop with all-day battery life.', 899.00, 24, 'fa-solid fa-laptop', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:20'),
(3, 17, 'TowerMax Pro 5000', 'ASUS', 'Desktops', 'Ryzen 9 desktop with RTX graphics for demanding workflows.', 2299.00, 8, 'fa-solid fa-desktop', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:22'),
(4, 17, 'CompactDesk Mini', 'Dell', 'Desktops', 'Small-form-factor workstation for office and home setups.', 1099.00, 18, 'fa-solid fa-server', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:23'),
(5, 17, 'MechStrike RGB Keyboard', 'Logitech', 'Peripherals', 'Mechanical keyboard with RGB lighting and hot-swappable switches.', 149.00, 45, 'fa-solid fa-keyboard', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:24'),
(6, 17, 'PrecisionGlide Mouse', 'Razer', 'Peripherals', 'Wireless ergonomic mouse with high-DPI sensor.', 79.00, 56, 'fa-solid fa-computer-mouse', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:25'),
(7, 17, 'NetPro Wi-Fi 7 Router', 'TP-Link', 'Networking', 'Tri-band router with mesh-ready Wi-Fi 7 performance.', 349.00, 20, 'fa-solid fa-wifi', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:40'),
(8, 17, 'SwitchPro 24-Port', 'Cisco', 'Networking', 'Managed gigabit switch with VLAN and PoE support.', 249.00, 12, 'fa-solid fa-ethernet', 1, '2026-03-15 16:09:40', '2026-03-15 17:18:39'),
(9, 17, 'ZenWork Studio 16', 'ASUS', 'Laptops', '16-inch creator laptop with RTX graphics and color-accurate display.', 1899.00, 9, 'fa-solid fa-laptop', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:38'),
(10, 17, 'TravelMate Lite 13', 'HP', 'Laptops', 'Compact business laptop with strong battery life for daily travel.', 749.00, 28, 'fa-solid fa-laptop', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:37'),
(11, 17, 'PowerEdge Gamer X', 'MSI', 'Laptops', 'Gaming laptop with fast refresh panel and high-end cooling.', 1699.00, 11, 'fa-solid fa-laptop', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:36'),
(12, 17, 'CreatorCube X', 'HP', 'Desktops', 'Quiet desktop tower tuned for design, editing, and multitasking.', 1399.00, 13, 'fa-solid fa-computer', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:35'),
(13, 17, 'OfficeCore SFF', 'Lenovo', 'Desktops', 'Reliable desktop for teams needing solid everyday performance.', 799.00, 31, 'fa-solid fa-desktop', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:34'),
(14, 17, 'RenderStation Z8', 'Acer', 'Desktops', 'High-memory workstation desktop designed for 3D and CAD workloads.', 2599.00, 6, 'fa-solid fa-server', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:33'),
(15, 17, 'VisionPro 4K Monitor', 'Dell', 'Peripherals', '27-inch 4K IPS monitor with USB-C docking support.', 499.00, 22, 'fa-solid fa-display', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:32'),
(16, 17, 'ClearVoice Headset', 'Logitech', 'Peripherals', 'Noise-cancelling headset for support, meetings, and streaming.', 119.00, 39, 'fa-solid fa-headset', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:31'),
(17, 17, 'DockHub Thunderbolt', 'Anker', 'Peripherals', 'Multi-port dock with dual display support and fast charging.', 199.00, 27, 'fa-solid fa-plug', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:30'),
(18, 17, 'MeshLink AX3000', 'TP-Link', 'Networking', 'Dual-node mesh kit for whole-home wireless coverage.', 279.00, 17, 'fa-solid fa-network-wired', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:28'),
(19, 17, 'SecureGate Firewall', 'Cisco', 'Networking', 'Small business firewall appliance with VPN and threat filtering.', 599.00, 7, 'fa-solid fa-shield-halved', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:28'),
(20, 17, 'CloudBridge Access Point', 'Ubiquiti', 'Networking', 'Ceiling-mount access point for stable office Wi-Fi.', 189.00, 26, 'fa-solid fa-tower-broadcast', 1, '2026-03-15 16:29:00', '2026-03-15 17:18:27'),
(21, 17, 'Oten', 'Basta', 'Peripherals', 'Basta mao nana', 1521.00, 151, 'fa-solid fa-box-open', 1, '2026-03-15 17:19:31', '2026-03-15 17:20:22');

-- --------------------------------------------------------

--
-- Table structure for table `signup_attempts`
--

CREATE TABLE `signup_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'customer',
  `seller_status` varchar(20) NOT NULL DEFAULT 'not_applicable',
  `store_name` varchar(150) DEFAULT NULL,
  `temp_password` varchar(255) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `email`, `password_hash`, `role`, `seller_status`, `store_name`, `temp_password`, `avatar_path`, `is_verified`, `created_at`) VALUES
(4, 'rodlyn', 'calunsag', 'rody', 'rodelyncalunsag1999@gmail.com', '$2y$10$ykCwul02.7djbYHotkqGSOU/IpUXu3vSLu1wAxeetfnS8fmDYcoHW', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-10 23:59:16'),
(6, 'dewey', 'raven', 'palautot', 'cabahugdewey@gmail.com', '$2y$10$woe1obQtl1/dZdoEWSRQwussuCDeuGrcVA1kun4pC3RYI44j4KItq', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-11 07:53:46'),
(8, 'johnie niel', 'derubio', 'yobalsi', 'argydy2003@gmail.com', '$2y$10$oV70B1JtOYxlKt3xm.llp.q2iPijrCVb/vm8KJGMnZLUmFAPIG2N.', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-11 08:04:09'),
(9, 'lyndy', 'gementiza', 'lyndy04', 'gementizalyndy12@gmail.com', '$2y$10$15nVa7JoyPuMelbo77PX7er0sEd0MBYbfwiQ7E5iwR8E1BILmbgO6', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-11 08:05:36'),
(10, 'Edward', 'Epano', 'EDWARD', 'eduardo.espano@nmsc.edu.ph', '$2y$10$pJNgtuVAgHKeK5qX5DAUYeAN/UxUGKqc3l8GCapFO78B2jOVSQawm', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-11 12:07:32'),
(13, 'hidear', 'talirongan', 'hidear', 'pydear@gmail.com', '$2y$10$v36r4sG6bRDCZhG0S5t4geNijq6CUJ6v5MpPzU3tFGrY6aZ08xlLi', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-11 14:27:34'),
(14, 'hidear', 'talirongan', 'hidear2', 'spydear@gmail.com', '$2y$10$EiY0jSuziZhgt.IjcIc1wuQyR4EIH9EK4gYCceeF7BtWEi2mp/LJG', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-11 14:30:18'),
(15, 'ace', 'clerk', 'xavier', 'xavierazcona0422@gmail.com', '$2y$10$.38djK6GzlPgFSVFBLO7YO1iCDBoUDKc/I78yT0SqON.6gCI22zHG', 'customer', 'not_applicable', NULL, NULL, NULL, 0, '2026-03-14 14:01:39'),
(16, 'Januard', 'Amarille', 'januard', 'januardamarille@gmail.ocm', '$2y$10$xFPDArUpRW47PF8U9e1NSeklF6tFqsOgsCqLPXAZ25vYLjxWOOyvi', 'customer', 'not_applicable', '', NULL, 'media/avatars/avatar_0d433b070ac046ae4eb42732.gif', 1, '2026-03-15 16:11:41'),
(17, 'Nycea', 'Labang', 'nycea', 'vespidlemming@gmail.com', '$2y$10$ZuNa97QvQ7Ao59nnWtMGOuT040s.ZUrhL0yAQpZotsG9FXR37hMQi', 'seller', 'approved', 'PLBIO', NULL, NULL, 1, '2026-03-15 16:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `verification_tokens`
--

CREATE TABLE `verification_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_tokens`
--

INSERT INTO `verification_tokens` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 16, '68d63814e80ab09eb4baf7f7dcfbaacf889622322f1c81af7360e115d8f4a830', '2026-03-15 10:11:41', '2026-03-15 16:11:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token` (`token`),
  ADD KEY `fk_ev_user` (`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_brand` (`brand`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_seller` (`seller_id`);

--
-- Indexes for table `signup_attempts`
--
ALTER TABLE `signup_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD UNIQUE KEY `uq_username` (`username`);

--
-- Indexes for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `signup_attempts`
--
ALTER TABLE `signup_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
