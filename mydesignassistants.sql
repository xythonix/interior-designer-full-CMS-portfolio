-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 09, 2026 at 12:54 PM
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
-- Database: `mydesignassistants`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `created_at`) VALUES
(1, 'moeed', '$2y$10$YSnk9vXmcUczgJFjeVy4fuTGmj7LXY6Zyt9KC5lZxD5phsgq5bjGa', 'admin@mydesignassistants.com', '2026-04-04 12:50:28');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `message` longtext DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_important` tinyint(1) DEFAULT 0,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `to_emails` text NOT NULL,
  `subject` varchar(500) NOT NULL,
  `body` longtext NOT NULL,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `fail_reason` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `from_email`, `to_emails`, `subject`, `body`, `status`, `fail_reason`, `sent_at`) VALUES
(1, 'xythonfreelancer@gmail.com', 'cybersite159@gmail.com, xython159@gmail.com', 'Welcome', '<p>Hi,</p><p>It\'s Moeed, Founder of <strong>MyDesignAssistants. </strong>I am happy to welcome you here on my board.</p><p><br></p><p>Meet our Developer <a href=\"https://xythonix.epizy.com\" rel=\"noopener noreferrer\" target=\"_blank\">xythonix</a></p><p><br></p><p><em>Thanks</em></p>', 'sent', NULL, '2026-04-09 10:38:39'),
(2, 'xythonfreelancer@gmail.com', 'cybersite159@gmail.com', 'Just a casual thing to say \'hi\' to my clients for being with me :)', '<p>hello</p>', 'sent', NULL, '2026-04-09 10:45:45');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `software_used` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `images` longtext DEFAULT NULL,
  `category` varchar(100) DEFAULT 'Interior Design',
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `slug`, `description`, `software_used`, `features`, `thumbnail`, `images`, `category`, `is_featured`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Modern living room', 'modern-living-room', '<h2>Modern Living Room Design</h2><p>All time <strong>favourite </strong>and syaing to <em>people </em>saying that this project has features :</p><ol><li>all time good</li><li>loong lasting</li></ol><p>more about developer here <a href=\"https://xythonix.epizy.com\" rel=\"noopener noreferrer\" target=\"_blank\">xythonix</a></p>', 'Homestyler', '3d renders\r\nall time favorite\r\nwell polished', '/portfolio/uploads/projects/img_69d2332f4126a_1775383343.gif', '[\"\\/portfolio\\/uploads\\/projects\\/img_69d2332f41420_1775383343.png\",\"\\/portfolio\\/uploads\\/projects\\/img_69d2332f414db_1775383343.png\"]', 'Interior Design', 1, 0, '2026-04-04 16:09:20', '2026-04-07 14:46:42'),
(2, 'fghfg', 'fghfg', '<p><em>hfghfghfghfgh</em></p>', 'hfghfg', '', '', '[]', 'Interior Design', 1, 0, '2026-04-07 15:58:30', '2026-04-07 15:58:42'),
(3, 'utyjjyj', 'utyjjyj', '<h1>jgfjjuyj</h1>', 'yjgjhgjh', '', '', '[]', 'Interior Design', 1, 0, '2026-04-07 15:59:13', '2026-04-07 15:59:13'),
(4, 'fjtjjhj', 'fjtjjhj', '<p><u>gjfmgjhj</u></p>', 'ghj', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 15:59:23', '2026-04-07 15:59:23'),
(5, 'jlk;jkl;jpo\'', 'jlkjkljpo', '<p>gfjf gj hgj kjk</p>', 'ghj gkgjhk', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 16:33:32', '2026-04-07 16:33:32'),
(6, 'mbvm kj kjkg', 'mbvm-kj-kjkg', '<p>hjkjhkgh</p>', 'k jhgk gh', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 16:33:38', '2026-04-07 16:33:38'),
(7, 'kghmhm h', 'kghmhm-h', '<p>mjh hghk</p>', 'hlklj', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 16:33:44', '2026-04-07 16:33:44'),
(8, 'hfg hghm', 'hfg-hghm', '<p>j fgjhufty</p>', 'm gmjhfg', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 16:33:51', '2026-04-07 16:33:51'),
(9, 'h df  ghf j', 'h-df-ghf-j', '<p>ggh jf gh</p>', 'g fjhj', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 16:33:57', '2026-04-07 16:33:57'),
(10, 'dsf ghgh fg', 'dsf-ghgh-fg', '<p><strong>df gh fdh </strong></p>', 'dh hj gh', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 16:34:08', '2026-04-07 16:34:08'),
(11, 'ghfdgh df', 'ghfdgh-df', '<p>dh gfjg </p>', 'h g f', '', '', '[]', 'Interior Design', 0, 0, '2026-04-07 16:34:19', '2026-04-07 16:34:19'),
(12, 'ghgh jf', 'ghgh-jf', '<p>g hfdhf</p>', 'ff', '', '', '[]', '3ds max', 0, 0, '2026-04-07 16:34:24', '2026-04-07 16:39:40');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'A. Moeed | MyDesignAssistants', '2026-04-05 09:36:20'),
(2, 'hero_title', 'where Vision meets Design', '2026-04-07 14:42:49'),
(3, 'hero_subtitle', 'Award-winning interior design solutions that breathe life into every moment.', '2026-04-07 14:39:57'),
(4, 'about_text', 'With over 7+ years of experience in interior design, I specialize in creating spaces that are not just beautiful, but deeply functional and emotionally resonant. As the Founder of MyDesignAssistants, I lead a passionate team dedicated to transforming your vision into breath-taking realities from concept to completion.', '2026-04-05 09:38:30'),
(5, 'email', 'hello@mydesignassistants.com', '2026-04-05 09:35:20'),
(6, 'phone', '+1 (555) 123-4567', '2026-04-04 12:50:28'),
(7, 'upwork_url', 'https://upwork.com', '2026-04-04 12:50:28'),
(8, 'fiverr_url', 'https://fiverr.com', '2026-04-04 12:50:28'),
(9, 'meta_description', 'A. Moeed - Professional Interior Designer and Founder of MyDesignAssistants. Specializing in luxury residential and commercial interior design.', '2026-04-04 12:50:28');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 5.0,
  `review_text` longtext DEFAULT NULL,
  `client_image` varchar(500) DEFAULT NULL,
  `platform` varchar(50) DEFAULT 'upwork',
  `is_featured` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `client_name`, `country`, `rating`, `review_text`, `client_image`, `platform`, `is_featured`, `sort_order`, `created_at`) VALUES
(1, 'Sarah Mitchell', 'United States', 4.5, 'Moeed transformed our living room into an absolute masterpiece. His attention to detail and understanding of our vision was incredible. Every piece he selected felt intentional and perfect. We could not be happier!', '', 'upwork', 1, 0, '2026-04-04 12:50:28'),
(2, 'James Thornton', 'United Kingdom', 4.5, 'Exceptional work on our commercial office redesign. The space now feels modern, professional, and incredibly welcoming. His team delivered on time and within budget. Highly recommended!', '', 'upwork', 1, 0, '2026-04-04 12:50:28'),
(3, 'Aisha Rahman', 'UAE', 5.0, 'Working with A. Moeed was a dream. He brought creativity and professionalism that exceeded all our expectations. Our villa now looks like it belongs in a luxury magazine!', NULL, 'fiverr', 1, 0, '2026-04-04 12:50:28'),
(4, 'Hamza', 'Pakistan', 5.0, 'banda kaam ka hai', '', 'direct', 1, 0, '2026-04-07 14:57:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slug` (`slug`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_key` (`setting_key`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
