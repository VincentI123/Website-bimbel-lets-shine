-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 28, 2025 at 10:22 AM
-- Server version: 10.1.38-MariaDB
-- PHP Version: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bimbel_letshine`
--

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `exam_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `homepage_banners`
--

CREATE TABLE `homepage_banners` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `homepage_banners`
--

INSERT INTO `homepage_banners` (`id`, `title`, `alt_text`, `image_path`, `display_order`, `is_active`, `created_at`) VALUES
(1, '1', '2341234', 'assets/img/banners/banner_1762179693_download 1.jpg', 0, 0, '2025-11-03 14:21:33'),
(2, '3325', '', 'assets/img/banners/banner_1763729575_eusfsh.jpg', 0, 1, '2025-11-03 14:21:55'),
(3, 'dgadg', 'adgadsgadg', 'assets/img/banners/banner_1763729730_unnamed.jpg', 0, 1, '2025-11-03 14:26:42'),
(4, 'aegraega', 'aegaeg', 'assets/img/banners/banner_1763729532_afgafg.jpg', 0, 1, '2025-11-03 14:27:02'),
(5, 'aegaeg', 'aegaeg', 'assets/img/banners/banner_1763729330_afgafgag.jpg', 0, 1, '2025-11-03 14:27:13'),
(6, 'aegaeg', 'aegaeg', 'assets/img/banners/banner_1763729088_promosi natal.jpg', 0, 1, '2025-11-03 14:27:24');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `subject_id`, `teacher_id`, `day_of_week`, `start_time`, `end_time`, `room`, `created_at`) VALUES
(17, 15, 14, 'Tuesday', '09:30:00', '11:00:00', 'Ruangan 4', '2025-10-31 04:38:12'),
(18, 13, 15, 'Tuesday', '09:30:00', '11:00:00', 'Ruangan 5', '2025-10-31 11:47:42'),
(19, 13, 21, 'Wednesday', '12:00:00', '13:30:00', 'Ruangan 5', '2025-11-02 15:58:26'),
(20, 7, 15, 'Monday', '09:30:00', '11:00:00', 'Ruangan 1', '2025-11-02 15:58:59'),
(21, 17, 21, 'Wednesday', '09:30:00', '11:00:00', 'Ruangan 2', '2025-11-03 01:27:50'),
(22, 16, 14, 'Thursday', '09:30:00', '11:00:00', 'Ruangan 4', '2025-11-03 01:28:10'),
(24, 13, 15, 'Saturday', '09:30:00', '11:00:00', 'Ruangan 6', '2025-11-03 02:31:02'),
(25, 15, 21, 'Tuesday', '09:30:00', '11:00:00', 'Ruangan 3', '2025-11-05 13:21:31'),
(26, 13, 15, 'Wednesday', '12:00:00', '13:30:00', 'Ruangan 3', '2025-11-05 14:09:34'),
(27, 12, 37, 'Friday', '13:30:00', '15:00:00', 'Ruangan 1', '2025-11-07 03:45:25'),
(28, 10, 21, 'Thursday', '13:30:00', '15:00:00', 'Ruangan 1', '2025-11-07 04:43:07'),
(29, 13, 15, 'Friday', '18:00:00', '19:30:00', 'Ruangan 4', '2025-11-21 07:06:15'),
(30, 15, 21, 'Thursday', '09:30:00', '11:00:00', 'Ruangan 6', '2025-11-21 07:06:34'),
(31, 13, 15, 'Tuesday', '12:00:00', '13:30:00', 'Ruangan 2', '2025-11-21 13:24:41'),
(32, 10, 14, 'Friday', '12:00:00', '13:30:00', 'Ruangan 2', '2025-11-21 14:35:45'),
(33, 10, 14, 'Saturday', '09:30:00', '11:00:00', 'Ruangan 1', '2025-11-22 01:23:26'),
(35, 18, 43, 'Thursday', '14:30:00', '16:00:00', 'Ruangan 6', '2025-11-25 12:58:23'),
(36, 8, 37, 'Friday', '15:00:00', '16:30:00', 'Ruangan 2', '2025-11-28 07:51:47');

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `student_enrollments`
--

INSERT INTO `student_enrollments` (`id`, `student_id`, `schedule_id`, `enrolled_at`) VALUES
(36, 7, 18, '2025-10-31 11:53:04'),
(37, 39, 18, '2025-10-31 11:53:04'),
(38, 35, 18, '2025-10-31 11:53:04'),
(39, 7, 19, '2025-11-02 15:58:26'),
(40, 8, 19, '2025-11-02 15:58:26'),
(41, 12, 19, '2025-11-02 15:58:26'),
(45, 7, 21, '2025-11-03 01:27:50'),
(46, 6, 21, '2025-11-03 01:27:50'),
(47, 35, 21, '2025-11-03 01:27:50'),
(48, 19, 22, '2025-11-03 01:28:10'),
(49, 6, 22, '2025-11-03 01:28:10'),
(50, 39, 22, '2025-11-03 01:28:10'),
(57, 7, 24, '2025-11-03 02:31:02'),
(60, 7, 26, '2025-11-05 14:09:34'),
(61, 6, 26, '2025-11-05 14:09:35'),
(62, 39, 26, '2025-11-05 14:09:35'),
(63, 12, 26, '2025-11-05 14:09:35'),
(64, 8, 17, '2025-11-06 07:23:43'),
(69, 13, 20, '2025-11-07 02:20:48'),
(70, 36, 20, '2025-11-07 02:20:48'),
(71, 13, 25, '2025-11-07 02:21:14'),
(72, 12, 25, '2025-11-07 02:21:14'),
(73, 36, 25, '2025-11-07 02:21:14'),
(74, 5, 27, '2025-11-07 03:45:25'),
(75, 11, 27, '2025-11-07 03:45:25'),
(76, 33, 27, '2025-11-07 03:45:25'),
(77, 19, 27, '2025-11-07 03:45:25'),
(78, 6, 27, '2025-11-07 03:45:25'),
(79, 38, 27, '2025-11-07 03:45:25'),
(80, 5, 28, '2025-11-07 04:43:07'),
(81, 33, 28, '2025-11-07 04:43:07'),
(82, 8, 28, '2025-11-07 04:43:07'),
(83, 13, 28, '2025-11-07 04:43:07'),
(84, 12, 28, '2025-11-07 04:43:07'),
(85, 36, 28, '2025-11-07 04:43:07'),
(86, 35, 29, '2025-11-21 07:06:15'),
(87, 33, 30, '2025-11-21 07:06:34'),
(88, 12, 31, '2025-11-21 13:24:41'),
(89, 33, 32, '2025-11-21 14:35:45'),
(90, 6, 32, '2025-11-21 14:35:45'),
(94, 5, 33, '2025-11-22 01:53:52'),
(95, 12, 33, '2025-11-22 01:53:52'),
(100, 36, 36, '2025-11-28 07:51:47');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` mediumtext,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `description`, `created_at`) VALUES
(7, 'Bahasa Indonesia', '', '2025-10-24 03:00:55'),
(8, 'PKN', '', '2025-10-24 03:01:05'),
(9, 'Matematika', '', '2025-10-24 03:01:40'),
(10, 'IPA', '', '2025-10-24 03:01:59'),
(11, 'IPAS', '', '2025-10-24 03:02:05'),
(12, 'Mandarin', '', '2025-10-24 03:02:15'),
(13, 'English', '', '2025-10-24 03:02:23'),
(14, 'Kimia', '', '2025-10-24 03:02:30'),
(15, 'Fisika', '', '2025-10-24 03:02:40'),
(16, 'Calistung', '', '2025-10-24 03:02:49'),
(17, 'Portugis', '', '2025-10-27 13:46:48'),
(18, 'Jepang', '', '2025-10-29 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `id` int(11) NOT NULL,
  `time_value` varchar(20) NOT NULL,
  `specific_days` varchar(255) DEFAULT NULL,
  `time_label` varchar(50) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`id`, `time_value`, `specific_days`, `time_label`, `display_order`) VALUES
(1, '09:30,11:00', 'Monday,Tuesday,Wednesday,Thursday,Friday', '09:30 - 11:00', 1),
(2, '10:30,12:00', 'Saturday', '10:30 - 12:00', 2),
(3, '12:00,13:30', 'Monday,Tuesday,Wednesday,Thursday,Friday', '12:00 - 13:30', 3),
(4, '13:00,14:30', 'Saturday', '13:00 - 14:30', 4),
(5, '13:30,15:00', 'Monday,Tuesday,Wednesday,Thursday,Friday', '13:30 - 15:00', 5),
(6, '14:30,16:00', 'Saturday', '14:30 - 16:00', 6),
(7, '15:00,16:30', 'Monday,Tuesday,Wednesday,Thursday,Friday', '15:00 - 16:30', 7),
(8, '16:30,18:00', 'Monday,Tuesday,Wednesday,Thursday,Friday', '16:30 - 18:00', 8);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('pending','approved') NOT NULL DEFAULT 'approved',
  `phone_number` varchar(20) DEFAULT NULL,
  `address` mediumtext,
  `student_id` varchar(20) DEFAULT NULL,
  `grade_level` varchar(50) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `parent_name` varchar(255) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `subjects` mediumtext,
  `available_days` mediumtext COMMENT 'Hari yang tersedia, format: Monday, Tuesday, ...',
  `available_times` mediumtext COMMENT 'Waktu yang tersedia, format: 09:30 - 11:00, 12:00 - 13:30',
  `employment_status` enum('full-time','part-time') NOT NULL DEFAULT 'part-time',
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `status`, `phone_number`, `address`, `student_id`, `grade_level`, `school`, `parent_name`, `parent_phone`, `subjects`, `available_days`, `available_times`, `employment_status`, `birth_date`, `birth_place`, `created_at`, `updated_at`) VALUES
(1, 'Admin Utama', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'part-time', NULL, NULL, '2025-10-06 02:10:17', '2025-10-06 02:10:17'),
(5, 'Alex Smith', 'alex.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved', '+62 815 6789 0123', 'Jakarta, Indonesia', 'STU-2024-001', '', '', '', '', 'Calistung, IPA, Matematika', 'Wednesday, Thursday, Friday, Saturday', '09:30 - 11:00, 12:00 - 13:30, 13:00 - 14:30, 13:30 - 15:00', 'part-time', '2006-03-15', '', '2025-10-06 02:10:17', '2025-11-07 02:22:21'),
(6, 'Jessica Brown', 'jessica.brown', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved', '+62 816 7890 1234', 'Bandung, Indonesia', 'STU-2024-002', '', '', '', '', 'Bahasa Indonesia, Calistung, Fisika, IPA, Jepang, Mandarin, PKN', 'Wednesday, Friday, Saturday', '10:30 - 12:00, 12:00 - 13:30, 13:30 - 15:00', 'part-time', '2006-05-20', '', '2025-10-06 02:10:17', '2025-11-06 08:46:55'),
(7, 'David Wilson', 'david.wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved', '+62 817 8901 2345', 'Surabaya, Indonesia', 'STU-2024-003', '', '', '', '', 'Fisika, IPA, Jepang, Kimia', 'Wednesday, Thursday, Friday, Saturday', '10:30 - 12:00, 12:00 - 13:30, 13:00 - 14:30, 16:30 - 18:00', 'part-time', '2007-01-10', '', '2025-10-06 02:10:17', '2025-11-06 08:47:09'),
(8, 'Maria Garcia', 'maria.garcia', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'approved', '+62 818 9012 3456', 'Medan, Indonesia', 'STU-2024-004', '', '', '', '', 'Bahasa Indonesia, Calistung, Fisika, IPA, Jepang, Kimia, Mandarin, PKN, Portugis', 'Tuesday, Thursday, Friday', '10:30 - 12:00, 13:00 - 14:30, 13:30 - 15:00, 15:00 - 16:30, 16:30 - 18:00, 18:00 - 19:30', 'part-time', '2005-08-25', '', '2025-10-06 02:10:17', '2025-11-06 08:46:28'),
(9, 'Teacher', 'teacher', '$2y$10$8b1t6XAbDHLsIUUPclo4ferETxcJojx7VQ41lt3QPcPwn0hyi.H.G', 'teacher', 'approved', '', '', NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, 'part-time', '0000-00-00', '', '2025-10-13 07:35:33', '2025-10-29 14:09:04'),
(11, 'Atomic', 'atomic', '$2y$10$.guEjsVZRmpYj2AEkOfM2OvWFpuHwXTLIDqodJro1QzJf.bcO1G9y', 'student', 'approved', '081123456789', 'Simp KDA', NULL, 'Junior High', 'Osaka', 'Andy', '081123456789', 'Bahasa Indonesia, IPA, Kimia, Mandarin', 'Monday, Friday', '13:30 - 15:00, 15:00 - 16:30, 16:30 - 18:00', 'part-time', '2025-10-08', 'Pacific', '2025-10-14 15:17:58', '2025-11-06 08:43:43'),
(12, 'Rover Uzumaki', 'rover', '$2y$10$VYt6XDAI2hypGgdcWUaO/.2BeeVSv/s6RyXkx75P.yq8MG0h8fv3.', 'student', 'approved', '', '', NULL, '', '', '', '', 'IPA, Mandarin, Matematika', 'Tuesday, Thursday, Saturday', '09:30 - 11:00, 12:00 - 13:30, 13:30 - 15:00', 'part-time', '0000-00-00', '', '2025-10-22 04:15:15', '2025-11-24 10:42:59'),
(13, 'Naimi', 'naimi', '$2y$10$91j1umyKTIUQqu79i3ls8OwYz5N4qSCNBPSN8UusgaDOw1Xkx9EiK', 'student', 'approved', 'ef', 'efef', NULL, 'Pre-Nursery', 'Tokyo', 'Naimi', '081123456789', 'Fisika, IPAS, Kimia', 'Monday, Tuesday, Thursday, Friday', '09:30 - 11:00, 12:00 - 13:30, 13:00 - 14:30, 14:30 - 16:00', 'part-time', '2025-10-15', 'Wewewe', '2025-10-22 04:26:16', '2025-11-25 13:05:29'),
(14, 'Maitriyana', 'Maitriyana', '$2y$10$pnvLPgXjiNLqhHIWojyA/.UIxFrt8LlwpNhRF38SadRQrh9Y6dCKC', 'teacher', 'approved', '0812-345-67', 'Batam', NULL, NULL, NULL, NULL, NULL, 'English, IPA, Mandarin', '', '', 'full-time', '2004-02-19', 'Batam', '2025-10-24 02:58:46', '2025-11-28 09:03:57'),
(15, 'Gojo', 'Gojo', '$2y$10$0.KmDeRUSSFVrr7t6FVT2ul7ZpsihYKy16cxKT1AGTLrfmk.JvbM6', 'teacher', 'approved', '081234567890', 'Tokyo', NULL, NULL, NULL, NULL, NULL, 'Bahasa Indonesia, Calistung, English, Fisika, IPA, IPAS, Kimia, Mandarin, Matematika, PKN', '', '', 'full-time', '2022-02-22', 'Tokyo', '2025-10-25 02:56:14', '2025-11-06 08:40:37'),
(19, 'Columbina A', 'columbina', '$2y$10$WAMNfLmOKwk5gOy5UXR7.uIInCqPfEp1OEvjQq5fZBuoNClZ3sp5K', 'student', 'approved', '12125', 'wefwe', NULL, 'Senior High', 'Osaka', 'Andy', '081123456789', 'Bahasa Indonesia, Calistung, English, Fisika, IPA, IPAS, Kimia, Mandarin, Matematika, PKN, Portugis', 'Monday, Wednesday, Friday', '10:30 - 12:00, 12:00 - 13:30, 13:30 - 15:00', 'part-time', '2025-10-13', 'Tokyo', '2025-10-27 12:53:30', '2025-11-06 08:44:48'),
(21, 'Lumine Gojo', 'lumine', '$2y$10$S6lXMw3LVRHCDmrBSXyawe1ECg48KGtOgUodcOXaMC/Zc8LZ53tbu', 'teacher', 'approved', '081234567890', 'tokyo', NULL, NULL, NULL, NULL, NULL, 'Bahasa Indonesia, Calistung, English, Fisika, IPA, IPAS, Kimia, Mandarin, Matematika, PKN, Portugis', 'Thursday, Friday', '09:30 - 11:00, 13:30 - 15:00, 16:30 - 18:00', 'part-time', '2025-10-09', 'Wewewe', '2025-10-28 03:33:02', '2025-11-06 08:42:38'),
(33, 'Columbina', 'columbina1', '$2y$10$s/oFIEMUQ7YEw4pHCtfr8O366Jkj3iJvSSUqDQKdyojBv2LIcgfyO', 'student', 'approved', '', '', NULL, '', '', '', '', 'English, Jepang', 'Thursday, Friday, Saturday', '09:30 - 11:00, 12:00 - 13:30, 13:30 - 15:00, 14:30 - 16:00', 'part-time', '0000-00-00', '', '2025-10-29 09:10:48', '2025-11-06 08:44:36'),
(35, 'Naruto', 'naruto', '$2y$10$qrhBAiJgCjEhWXW.dzJiEOi8VUcgV/HS/Tym7JlDLYKaOXfw1uqVe', 'student', 'approved', '3463-6346-34634', 'japan', NULL, 'Senior High', 'Osaka', 'Naimi', '2352-2352-35235', 'English, Fisika, IPA, Jepang, Kimia, PKN', 'Wednesday, Friday, Saturday', '13:00 - 14:30, 13:30 - 15:00, 14:30 - 16:00, 18:00 - 19:30', 'part-time', '2025-10-13', 'Wewewe', '2025-10-30 07:20:47', '2025-11-25 14:34:00'),
(36, 'Sasuke', 'sasuke', '$2y$10$I4R3AOQQ6.JcRGwgWEdTauwjNkeL1k5AfCCp3vOUKj30YiLTsed7S', 'student', 'approved', '0811-2345-6789', 'koeo iwjoiwg 3rt3', NULL, 'Elementary', 'Osaka', 'Naimi', '0811-2345-6789', 'Calistung, Fisika, IPA, IPAS, Jepang, Kimia, Mandarin, Matematika, PKN, Portugis', 'Monday, Tuesday, Wednesday, Thursday, Friday, Saturday', '09:30 - 11:00, 10:30 - 12:00 (Sabtu), 12:00 - 13:30, 13:00 - 14:30, 13:30 - 15:00 (Sabtu), 14:30 - 16:00, 15:00 - 16:30, 16:30 - 18:00', 'part-time', '2025-10-06', 'Pacific', '2025-10-31 02:17:58', '2025-11-25 13:03:57'),
(37, 'Lauma', 'lauma', '$2y$10$9OKGiRXosv8VcTfAY6Skc.kuvyrm30ngkZoE8l1Ca9hP2/p1Vuuge', 'teacher', 'approved', '1212-5434-43343', '44t', NULL, NULL, NULL, NULL, NULL, 'Mandarin, PKN', 'Friday', '13:30 - 15:00, 15:00 - 16:30', 'part-time', '0000-00-00', 'Singapore', '2025-10-31 02:43:29', '2025-11-10 09:28:07'),
(38, 'Kakashi', 'kakashi', '$2y$10$8vCMV/MGhlkmWu0MUxYw.O/7TtzfiE9ze/oQjALSCKbDOmD6em5ue', 'student', 'approved', '0811-2345-6789', 'konoha', NULL, 'Kindergarten', 'Konoha', 'Naimi', '0811-2345-6789', 'IPA, Jepang, Mandarin', 'Tuesday, Friday', '10:30 - 12:00, 13:30 - 15:00, 16:30 - 18:00', 'part-time', '1999-12-12', 'Konoha', '2025-10-31 03:20:21', '2025-11-06 08:46:37'),
(39, 'Minato', 'minato', '$2y$10$NOKscpC1WJyIp3i.N8iiJe0uo1Q6mv82LS0NYjYlUicjlheL5eejS', 'student', 'approved', '1235-6347-13471', 'konoha', NULL, 'Senior High', 'Konoha', 'Hagoromo', '2352-2352-35235', 'Fisika, IPAS, Jepang, Mandarin, Matematika, PKN', 'Wednesday', '12:00 - 13:30', 'part-time', '2025-10-28', 'Korea', '2025-10-31 03:27:58', '2025-11-06 08:46:06'),
(43, 'Yakoro', 'yakoro', '$2y$10$szkitGnZXqJ2DasDpmattulVRXKEEvzKnYqC.SnPmOI4Pp7JuxC.O', 'teacher', 'approved', '0812-3456-7899', 'Tokyo', NULL, NULL, NULL, NULL, NULL, 'IPA, Jepang, Kimia, PKN', 'Wednesday, Thursday', '13:00 - 14:30, 14:30 - 16:00', 'part-time', '1998-06-09', 'Tokyo', '2025-11-21 13:23:29', '2025-11-21 13:23:29'),
(44, 'Vincent', 'vincent', '$2y$10$nIorYH11e0h5Eur5JoQemunVSB57JC4VDUYRqJtGj1hX955kmFdsm', 'student', 'approved', '0812-3456-7890', 'Tokyo', NULL, 'Senior High', 'Tokyo', 'Naimi', '0811-2345-6789', 'Fisika, IPA, IPAS, Jepang, Mandarin', 'Monday, Tuesday, Wednesday, Thursday, Friday, Saturday', '09:30 - 11:00, 10:30 - 12:00 (Sabtu), 12:00 - 13:30, 13:00 - 14:30, 13:30 - 15:00 (Sabtu), 14:30 - 16:00, 15:00 - 16:30, 16:30 - 18:00', 'part-time', '2005-06-20', 'Batam', '2025-11-21 14:32:21', '2025-11-25 13:03:24'),
(48, 'Columbina', 'hhh', '$2y$10$LpFve7tgM8L2Mie6pwQuMeBuonxY.z.MU5OX3/Z9KBKmmxNXmSZYG', 'student', 'approved', '0812-3456-7890', 'Tokyo', NULL, 'Senior High', 'T', 'Andy', '0811-2345-6789', 'Bahasa Indonesia', 'Monday', '09:30 - 11:00', 'part-time', '2025-11-14', 'Pacific', '2025-11-25 08:31:33', '2025-11-28 04:01:20'),
(49, 'E', 'e', '$2y$10$9jFxZCWbuixyLvxX3p8s7eveDHU8Ssg.5ZbFFVOVtgqc/jzA8XlNW', 'student', 'approved', '1212-5', 'E', NULL, 'Junior High', '32r23', 'Naimi', '0811-2345-6789', 'Calistung', 'Friday', '13:30 - 15:00 (Sabtu)', 'part-time', '2025-10-29', 'E', '2025-11-25 08:46:36', '2025-11-25 08:46:36'),
(50, 'Ega', 'ega62', '$2y$10$goNZZPmnXcMFAvs7S..ZXeNKzMKlsJ7hJ/hXBN2KBsiWrkImqgnMK', 'student', 'approved', '3252-3523-5235235', 'EGAEG', NULL, 'Junior High', '235', 'Andy', '2352-3523-5325235', 'IPA, Kimia, PKN', 'Friday', '10:30 - 12:00 (Sabtu)', 'part-time', '2025-11-12', 'Singapore', '2025-11-25 08:54:22', '2025-11-25 13:05:01'),
(51, 'Gojo', 'b', '$2y$10$MikY7YbqscEJLMS8CvocouumtXwdO.bADXb1iJgFzQU/Ey8peWEoS', 'teacher', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'part-time', NULL, NULL, '2025-11-27 04:00:37', '2025-11-27 04:00:37'),
(55, 'Gojo', 'adsgasdg', '$2y$10$4uR9HdB/oPd1dKGcSiZvD.OZf6fZ.3Ni3ZzU/1r8o/vFw2ks5utj2', 'teacher', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'part-time', NULL, NULL, '2025-11-28 02:48:40', '2025-11-28 02:48:40'),
(60, 'Drrarah', '6iwiw58w5iw', '$2y$10$2olDz1UeSymkam/PYKa/SOIg/HoOruj6NDvp6dGWQmRRKdF./CmfS', 'teacher', 'approved', '4263-6236', 'arharharh', NULL, NULL, NULL, NULL, NULL, 'Matematika', 'Friday', '12:00 - 13:30, 16:30 - 18:00', 'part-time', '2025-11-17', 'Aryaryarharh', '2025-11-28 03:30:14', '2025-11-28 03:53:34'),
(62, 'Wywyw', 'uw6uwuw5uw', '$2y$10$9SzF8.CwfeO5xLU7m8O3zec2JWQ6AKSfchMto.jDbSd/F0r9LbdK.', 'teacher', 'approved', '2572-725', '5yw5yw5w', NULL, NULL, NULL, NULL, NULL, 'Jepang', 'Friday', '09:30 - 11:00, 13:00 - 14:30, 13:30 - 15:00, 16:30 - 18:00', 'part-time', '2025-11-06', 'Stjstjstjs', '2025-11-28 03:44:09', '2025-11-28 03:44:09'),
(63, 'Suuuaqq', '14146146', '$2y$10$jLnfZG8rrIok/MHRT61rTeMDvLKLE1uuTY7sEnMaZjt3Gq0gvSmWm', 'teacher', 'approved', '5272-7572', 'Q57Q5YQ5YQ35HQ5HQ', NULL, NULL, NULL, NULL, NULL, 'Jepang', 'Friday', '16:30 - 18:00', 'part-time', '0001-02-10', 'Jtsjstjstjs', '2025-11-28 04:00:45', '2025-11-28 04:00:45'),
(64, 'Eeuetu', 'eeuetu25', '$2y$10$3YhRdw3Rq3HDQD8frL4bOugkQ31f85j9PIpD9DNk1ce8vscWI06fi', 'student', 'approved', '5388-2542-85285825', 'etruetruetuer', NULL, 'Senior High', 'Konoha', 'Euertuwuwuyu Areh', '2548-2458-24582458', 'Fisika, Matematika', 'Tuesday, Friday', '16:30 - 18:00', 'part-time', '2025-11-10', 'Batam', '2025-11-28 06:33:57', '2025-11-28 06:33:57'),
(65, 'Wywyw', 'wreywery', '$2y$10$hV5pFcnAvUOqYcGWoBHFteOgdmumDsIIUAJYEOV7F2yZ6lonNjfqm', 'student', 'pending', '5555-5555-55623', '5YW5YW5YW5YW5', NULL, 'Senior High', 'Osaka', 'Wrwrhwr', '4143-6461-36132', 'IPA, Kimia', 'Friday', '13:30 - 15:00, 16:30 - 18:00', 'part-time', '2025-10-29', 'Thwrhwerhwer', '2025-11-28 07:53:10', '2025-11-28 07:53:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `homepage_banners`
--
ALTER TABLE `homepage_banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `homepage_banners`
--
ALTER TABLE `homepage_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exams_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exams_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `student_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_enrollments_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
