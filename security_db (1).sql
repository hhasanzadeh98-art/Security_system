-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 25, 2026 at 09:13 AM
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
-- Database: `security_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `guard_schedules`
--

CREATE TABLE `guard_schedules` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `day` int(11) NOT NULL,
  `guard_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guard_schedules`
--

INSERT INTO `guard_schedules` (`id`, `year`, `month`, `day`, `guard_id`, `created_at`) VALUES
(61, 1404, 11, 1, 4, '2026-02-17 05:22:28'),
(62, 1404, 11, 2, 3, '2026-02-17 05:22:28'),
(63, 1404, 11, 3, 2, '2026-02-17 05:22:28'),
(64, 1404, 11, 4, 4, '2026-02-17 05:22:28'),
(65, 1404, 11, 5, 3, '2026-02-17 05:22:28'),
(66, 1404, 11, 6, 2, '2026-02-17 05:22:28'),
(67, 1404, 11, 7, 4, '2026-02-17 05:22:28'),
(68, 1404, 11, 8, 3, '2026-02-17 05:22:28'),
(69, 1404, 11, 9, 2, '2026-02-17 05:22:28'),
(70, 1404, 11, 10, 4, '2026-02-17 05:22:28'),
(71, 1404, 11, 11, 3, '2026-02-17 05:22:28'),
(72, 1404, 11, 12, 2, '2026-02-17 05:22:28'),
(73, 1404, 11, 13, 4, '2026-02-17 05:22:28'),
(74, 1404, 11, 14, 3, '2026-02-17 05:22:28'),
(75, 1404, 11, 15, 2, '2026-02-17 05:22:28'),
(76, 1404, 11, 16, 4, '2026-02-17 05:22:28'),
(77, 1404, 11, 17, 3, '2026-02-17 05:22:28'),
(78, 1404, 11, 18, 2, '2026-02-17 05:22:28'),
(79, 1404, 11, 19, 4, '2026-02-17 05:22:28'),
(80, 1404, 11, 20, 3, '2026-02-17 05:22:28'),
(81, 1404, 11, 21, 2, '2026-02-17 05:22:28'),
(82, 1404, 11, 22, 4, '2026-02-17 05:22:28'),
(83, 1404, 11, 23, 3, '2026-02-17 05:22:28'),
(84, 1404, 11, 24, 2, '2026-02-17 05:22:28'),
(85, 1404, 11, 25, 4, '2026-02-17 05:22:28'),
(86, 1404, 11, 26, 3, '2026-02-17 05:22:28'),
(87, 1404, 11, 27, 2, '2026-02-17 05:22:28'),
(88, 1404, 11, 28, 4, '2026-02-17 05:22:28'),
(89, 1404, 11, 29, 3, '2026-02-17 05:22:28'),
(90, 1404, 11, 30, 2, '2026-02-17 05:22:28'),
(91, 1404, 12, 1, 4, '2026-02-21 07:14:52'),
(92, 1404, 12, 2, 3, '2026-02-21 07:14:52'),
(93, 1404, 12, 3, 2, '2026-02-21 07:14:52'),
(94, 1404, 12, 4, 4, '2026-02-21 07:14:52'),
(95, 1404, 12, 5, 3, '2026-02-21 07:14:52'),
(96, 1404, 12, 6, 2, '2026-02-21 07:14:52'),
(97, 1404, 12, 7, 4, '2026-02-21 07:14:52'),
(98, 1404, 12, 8, 3, '2026-02-21 07:14:52'),
(99, 1404, 12, 9, 2, '2026-02-21 07:14:52'),
(100, 1404, 12, 10, 4, '2026-02-21 07:14:52'),
(101, 1404, 12, 11, 3, '2026-02-21 07:14:52'),
(102, 1404, 12, 12, 2, '2026-02-21 07:14:52'),
(103, 1404, 12, 13, 4, '2026-02-21 07:14:52'),
(104, 1404, 12, 14, 3, '2026-02-21 07:14:52'),
(105, 1404, 12, 15, 2, '2026-02-21 07:14:52'),
(106, 1404, 12, 16, 4, '2026-02-21 07:14:52'),
(107, 1404, 12, 17, 3, '2026-02-21 07:14:52'),
(108, 1404, 12, 18, 2, '2026-02-21 07:14:52'),
(109, 1404, 12, 19, 4, '2026-02-21 07:14:52'),
(110, 1404, 12, 20, 3, '2026-02-21 07:14:52'),
(111, 1404, 12, 21, 2, '2026-02-21 07:14:52'),
(112, 1404, 12, 22, 4, '2026-02-21 07:14:52'),
(113, 1404, 12, 23, 3, '2026-02-21 07:14:52'),
(114, 1404, 12, 24, 2, '2026-02-21 07:14:52'),
(115, 1404, 12, 25, 4, '2026-02-21 07:14:52'),
(116, 1404, 12, 26, 3, '2026-02-21 07:14:52'),
(117, 1404, 12, 27, 2, '2026-02-21 07:14:52'),
(118, 1404, 12, 28, 4, '2026-02-21 07:14:52'),
(119, 1404, 12, 29, 3, '2026-02-21 07:14:52');

-- --------------------------------------------------------

--
-- Table structure for table `guard_shift_reports`
--

CREATE TABLE `guard_shift_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `report_date` date NOT NULL,
  `jalali_year` smallint(5) UNSIGNED NOT NULL,
  `jalali_month` tinyint(3) UNSIGNED NOT NULL,
  `jalali_day` tinyint(3) UNSIGNED NOT NULL,
  `guard_id` int(11) NOT NULL,
  `shift_type` enum('morning','evening','24h','other') NOT NULL DEFAULT '24h',
  `handover_time` time DEFAULT NULL,
  `previous_guard_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `appearance` tinyint(4) DEFAULT 1,
  `vehicle_control` tinyint(4) DEFAULT 1,
  `property_control` tinyint(4) DEFAULT 1,
  `camera_monitoring` tinyint(4) DEFAULT 1,
  `fire_safety` tinyint(4) DEFAULT 1,
  `building_check` tinyint(4) DEFAULT 1,
  `alarm_system` tinyint(4) DEFAULT 1,
  `after_hours_entry` tinyint(4) DEFAULT 0,
  `forbidden_entry` tinyint(4) DEFAULT 0,
  `aquarium_feed` tinyint(4) DEFAULT 0,
  `server_room_status` tinyint(4) DEFAULT 1,
  `fingerprint` tinyint(4) DEFAULT 1,
  `night_rounds` tinyint(4) DEFAULT 1,
  `incidents_text` text DEFAULT NULL,
  `contacts_text` text DEFAULT NULL,
  `notes_text` text DEFAULT NULL,
  `handover_signature` varchar(100) DEFAULT NULL,
  `received_signature` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guard_shift_reports`
--

INSERT INTO `guard_shift_reports` (`id`, `report_date`, `jalali_year`, `jalali_month`, `jalali_day`, `guard_id`, `shift_type`, `handover_time`, `previous_guard_id`, `created_at`, `updated_at`, `appearance`, `vehicle_control`, `property_control`, `camera_monitoring`, `fire_safety`, `building_check`, `alarm_system`, `after_hours_entry`, `forbidden_entry`, `aquarium_feed`, `server_room_status`, `fingerprint`, `night_rounds`, `incidents_text`, `contacts_text`, `notes_text`, `handover_signature`, `received_signature`) VALUES
(1, '2025-11-03', 1404, 8, 12, 1, '24h', NULL, NULL, '2026-02-24 11:07:36', NULL, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL),
(18, '2025-11-03', 1404, 8, 12, 2, '24h', NULL, NULL, '2026-02-24 11:39:04', NULL, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 1, 'تست آزمایشی - بدون مشکل', '', '', NULL, NULL),
(19, '2026-02-24', 1404, 12, 5, 2, '24h', NULL, NULL, '2026-02-24 11:44:07', '2026-02-24 14:16:15', 1, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, 1, '', '', 'asdیی', NULL, NULL),
(20, '2026-02-24', 1404, 12, 5, 3, '24h', '15:00:00', 2, '2026-02-24 11:51:44', '2026-02-24 15:09:51', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, '13\r\n1', '1', '1', 'رضا 2', ''),
(24, '2026-02-25', 1404, 12, 6, 2, '24h', NULL, NULL, '2026-02-25 11:30:34', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', 'علی 1', '');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `user_id`, `start_time`, `end_time`) VALUES
(77, 3, '2026-02-21 06:15:52', '2026-02-22 09:04:27'),
(78, 2, '2026-02-22 07:00:00', '2026-02-22 12:00:00'),
(79, 2, '2026-02-22 13:00:00', '2026-02-23 10:00:00'),
(86, 4, '2026-02-23 07:00:00', '2026-02-23 09:00:00'),
(87, 4, '2026-02-23 10:00:00', '2026-02-24 09:00:00'),
(88, 3, '2026-02-24 08:18:49', '2026-02-24 13:24:37'),
(90, 3, '2026-02-24 13:29:51', '2026-02-24 14:18:51'),
(91, 4, '2026-02-24 15:23:15', '2026-02-24 15:23:17'),
(92, 4, '2026-02-24 15:23:20', '2026-02-25 07:35:00'),
(93, 2, '2026-02-25 08:09:01', '2026-02-25 09:38:14');

-- --------------------------------------------------------

--
-- Table structure for table `shift_settings`
--

CREATE TABLE `shift_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `start_hour` int(11) NOT NULL DEFAULT 6,
  `start_minute` int(11) NOT NULL DEFAULT 0,
  `end_hour` int(11) NOT NULL DEFAULT 6,
  `end_minute` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_settings`
--

INSERT INTO `shift_settings` (`id`, `setting_name`, `start_hour`, `start_minute`, `end_hour`, `end_minute`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'default_shift', 6, 0, 6, 0, 1, '2026-02-15 07:33:39', '2026-02-24 04:56:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guard') NOT NULL,
  `full_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `full_name`) VALUES
(1, 'admin', '$2y$10$o/C29HJ1kdzg6jzxszh5reVPNc9W9Ue5i9QjdJddgmfdyV.DexQQ2', 'admin', 'مدیر سیستم'),
(2, 'guard1', '$2y$10$n5IEh5IuDGEc/uldwxVwmugXLVFuo6k389IfLvZFgTi4qhMHDxIIC', 'guard', 'علی 1'),
(3, 'guard2', '$2y$10$BRDpCP3VfzC0gXOedRjsH.co2PKplo3bDhYQTCjqTaKThLyRJwOL2', 'guard', 'رضا 2'),
(4, 'guard3', '$2y$10$c62tGAWQVfCc5tCbS9LDoOcXKA0tqXx7F.g.wZxNHnbsMr05/VcMm', 'guard', 'حسن 3');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `guard_schedules`
--
ALTER TABLE `guard_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_schedule` (`year`,`month`,`day`),
  ADD KEY `guard_id` (`guard_id`);

--
-- Indexes for table `guard_shift_reports`
--
ALTER TABLE `guard_shift_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date_guard` (`report_date`,`guard_id`),
  ADD KEY `idx_jalali` (`jalali_year`,`jalali_month`,`jalali_day`),
  ADD KEY `guard_id` (`guard_id`),
  ADD KEY `previous_guard_id` (`previous_guard_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shift_settings`
--
ALTER TABLE `shift_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `guard_schedules`
--
ALTER TABLE `guard_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=208;

--
-- AUTO_INCREMENT for table `guard_shift_reports`
--
ALTER TABLE `guard_shift_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `shift_settings`
--
ALTER TABLE `shift_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `guard_schedules`
--
ALTER TABLE `guard_schedules`
  ADD CONSTRAINT `guard_schedules_ibfk_1` FOREIGN KEY (`guard_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `guard_shift_reports`
--
ALTER TABLE `guard_shift_reports`
  ADD CONSTRAINT `guard_shift_reports_ibfk_1` FOREIGN KEY (`guard_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `guard_shift_reports_ibfk_2` FOREIGN KEY (`previous_guard_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
