-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 08, 2026 at 01:57 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `traffic_violation_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `processed_videos`
--

CREATE TABLE `processed_videos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `processed_video_url` text NOT NULL,
  `zone_id` bigint(20) UNSIGNED NOT NULL,
  `count_direction_a` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `count_direction_b` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `processing_time_ms` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `processed_videos`
--

INSERT INTO `processed_videos` (`id`, `file_name`, `processed_video_url`, `zone_id`, `count_direction_a`, `count_direction_b`, `processing_time_ms`, `processed_by`, `created_at`, `updated_at`) VALUES
(1, 'video cho yêu cầu 4 (online-video-cutter.com) (1).mp4', 'http://127.0.0.1:5000/out/ca87bc35dc5743c4aabaea0ae772cf71.mp4', 3, 0, 0, 0, 1, '2026-03-05 16:18:39', '2026-03-05 16:18:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','supervisor','viewer') NOT NULL DEFAULT 'viewer',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `name`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin@gmail.com', 'Nguyễn Văn An', '$2y$10$IkgoZUHxUuihdGj4HNvOQuFMxhzHDz98f/UnbLChbBeck4d8M0dUe', 'admin', '2026-03-02 08:55:19', '2026-03-02 08:55:19');

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `violation_type` varchar(100) NOT NULL,
  `evidence_image_url` text NOT NULL,
  `processed_video_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'new',
  `handling_status` varchar(50) NOT NULL DEFAULT 'unprocessed',
  `notes` varchar(500) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violations`
--

INSERT INTO `violations` (`id`, `violation_type`, `evidence_image_url`, `processed_video_id`, `status`, `handling_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'yellow_light', 'http://127.0.0.1:5000/evidence/ca87bc35dc5743c4aabaea0ae772cf71/rl_yellow_68_80bdc61c.jpg', 1, 'detected', 'pending', '', '2026-03-05 16:19:10', '2026-03-05 16:19:10'),
(2, 'red_light', 'http://127.0.0.1:5000/evidence/ca87bc35dc5743c4aabaea0ae772cf71/rl_red_89_49293ff3.jpg', 1, 'detected', 'pending', '', '2026-03-05 16:19:28', '2026-03-05 16:19:28'),
(3, 'red_light', 'http://127.0.0.1:5000/evidence/ca87bc35dc5743c4aabaea0ae772cf71/rl_red_79_2495241b.jpg', 1, 'detected', 'pending', '30M93939', '2026-03-05 16:19:33', '2026-03-08 05:35:43'),
(4, 'red_light', 'http://127.0.0.1:5000/evidence/ca87bc35dc5743c4aabaea0ae772cf71/rl_red_125_d3ae96fb.jpg', 1, 'detected', 'pending', '', '2026-03-05 16:19:40', '2026-03-08 05:25:43');

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `max_speed` int(11) DEFAULT NULL,
  `roboflow_coordinates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roboflow_coordinates`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zones`
--

INSERT INTO `zones` (`id`, `name`, `max_speed`, `roboflow_coordinates`, `created_at`, `updated_at`) VALUES
(1, 'Vùng đếm xe trên Cao tốc (tọa độ 2 chiều)', 5, '{\"raw\":\"[\\r\\n    np.array([[336, 370], [595, 404], [537, 650], [77, 508]]),\\r\\n    np.array([[693, 410], [976, 402], [1210, 537], [731, 601]])\\r\\n]\",\"polygons\":[[[336,370],[595,404],[537,650],[77,508]],[[693,410],[976,402],[1210,537],[731,601]]]}', '2026-03-02 08:37:54', '2026-03-03 04:48:43'),
(2, 'Vượt đèn đỏ tại ngã 4', 50, '{\"raw\":\"[\\r\\n    np.array([[373, 741], [1502, 741]]),\\r\\n    np.array([[988, 113], [1045, 106], [1038, 204], [982, 199]])\\r\\n]\",\"polygons\":[[[373,741],[1502,741]],[[988,113],[1045,106],[1038,204],[982,199]]]}', '2026-03-03 22:04:58', '2026-03-03 22:58:53'),
(3, 'Vượt đèn đỏ tại ngã 4 - Góc camera 2', 50, '{\"raw\":\"[\\r\\n    np.array([[1528, 724], [282, 715]]),\\r\\n    np.array([[1012, 87], [1084, 91], [1075, 195], [1005, 189]])\\r\\n]\",\"polygons\":[[[1528,724],[282,715]],[[1012,87],[1084,91],[1075,195],[1005,189]]]}', '2026-03-04 00:53:19', '2026-03-04 00:53:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `processed_videos`
--
ALTER TABLE `processed_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `processed_videos_zone_id_index` (`zone_id`),
  ADD KEY `processed_videos_processed_by_index` (`processed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_role_index` (`role`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `violations_processed_video_id_index` (`processed_video_id`),
  ADD KEY `violations_status_index` (`status`),
  ADD KEY `violations_handling_status_index` (`handling_status`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zones_name_unique` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `processed_videos`
--
ALTER TABLE `processed_videos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `processed_videos`
--
ALTER TABLE `processed_videos`
  ADD CONSTRAINT `processed_videos_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `processed_videos_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_processed_video_id_foreign` FOREIGN KEY (`processed_video_id`) REFERENCES `processed_videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
