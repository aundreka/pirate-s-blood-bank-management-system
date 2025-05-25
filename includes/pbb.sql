-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 25, 2025 at 07:36 AM
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
-- Database: `pbb`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_appeals`
--

CREATE TABLE `admin_appeals` (
  `appeal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_appeals`
--

INSERT INTO `admin_appeals` (`appeal_id`, `user_id`, `reason`, `status`, `created_at`, `processed_at`, `processed_by`, `admin_notes`) VALUES
(1, 1, 'gi', 'rejected', '2025-05-25 02:28:15', '2025-05-25 03:35:37', 2, ''),
(2, 5, 'i want to be admin bc ahjsfbhkzsbfjklsdbjl', 'approved', '2025-05-25 03:51:09', '2025-05-25 03:51:39', 2, '');

--
-- Triggers `admin_appeals`
--
DELIMITER $$
CREATE TRIGGER `update_user_type_on_approval` AFTER UPDATE ON `admin_appeals` FOR EACH ROW BEGIN
    -- If status changed from pending/rejected to approved
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        UPDATE `login` 
        SET `user_type` = 'admin' 
        WHERE `user_id` = NEW.user_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `blood_inventory`
--

CREATE TABLE `blood_inventory` (
  `blood_type` varchar(3) NOT NULL,
  `available_units` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_inventory`
--

INSERT INTO `blood_inventory` (`blood_type`, `available_units`, `last_updated`) VALUES
('A+', 0, '2025-05-24 12:11:29'),
('A-', 0, '2025-05-25 03:17:08'),
('AB+', 0, '2025-05-24 12:11:29'),
('AB-', 0, '2025-05-24 14:07:20'),
('B+', 0, '2025-05-24 12:11:29'),
('B-', 0, '2025-05-24 12:11:29'),
('O+', 1, '2025-05-25 03:38:47'),
('O-', 0, '2025-05-24 12:11:29');

-- --------------------------------------------------------

--
-- Table structure for table `blood_log`
--

CREATE TABLE `blood_log` (
  `log_id` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL,
  `blood_type` varchar(3) NOT NULL,
  `units_donated` int(11) NOT NULL DEFAULT 1,
  `donor_id` int(11) DEFAULT NULL,
  `operation_type` enum('DONATION','WITHDRAWAL','ADJUSTMENT') DEFAULT 'DONATION',
  `timestamp_update` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_log`
--

INSERT INTO `blood_log` (`log_id`, `donation_id`, `blood_type`, `units_donated`, `donor_id`, `operation_type`, `timestamp_update`, `notes`) VALUES
(1, 1, 'A+', 0, 1, 'ADJUSTMENT', '2025-05-24 12:22:20', 'Donation submitted - pending admin approval'),
(2, 1, 'AB-', 1, 1, 'DONATION', '2025-05-24 12:22:58', 'Blood type updated during donation modification'),
(3, 1, 'O+', 1, 1, 'DONATION', '2025-05-24 12:23:55', 'Blood type updated during donation modification'),
(4, 1, 'O+', 0, 1, 'ADJUSTMENT', '2025-05-24 12:34:06', 'Status changed from Pending to Approved'),
(5, 1, 'O+', 0, 1, 'ADJUSTMENT', '2025-05-24 12:34:06', 'Donation approved by admin'),
(6, 1, 'O+', 1, 1, 'DONATION', '2025-05-24 12:35:26', 'Donation completed and added to inventory'),
(7, 1, 'O+', 0, 1, 'ADJUSTMENT', '2025-05-24 12:35:26', 'Status changed from Approved to Completed'),
(8, 1, 'O+', 1, 1, 'DONATION', '2025-05-24 12:35:26', 'Donation completed by admin'),
(9, 2, 'B+', 0, 1, 'ADJUSTMENT', '2025-05-24 12:35:57', 'Donation submitted - pending admin approval'),
(10, 2, 'B+', 0, 1, 'ADJUSTMENT', '2025-05-24 12:46:36', 'Status changed from Pending to Approved'),
(11, 2, 'B+', 0, 1, 'ADJUSTMENT', '2025-05-24 12:46:36', 'Donation approved by admin'),
(12, NULL, 'A+', 0, 1, 'ADJUSTMENT', '2025-05-24 13:03:05', 'Blood request updated by user - Request ID: 1 - Blood type changed from B+ to A+'),
(13, NULL, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 13:03:43', 'Blood request updated by user - Request ID: 1 - Blood type changed from A+ to AB-'),
(14, 3, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:01:51', 'Donation submitted - pending admin approval'),
(15, 3, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:02:22', 'Status changed from Pending to Cancelled'),
(16, 4, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:02:31', 'Donation submitted - pending admin approval'),
(17, 4, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:02:48', 'Status changed from Pending to Cancelled'),
(18, 5, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:02:58', 'Donation submitted - pending admin approval'),
(19, 5, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:03:24', 'Status changed from Pending to Cancelled'),
(20, 6, 'A-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:03:33', 'Donation submitted - pending admin approval'),
(21, 6, 'A-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:03:59', 'Status changed from Pending to Cancelled'),
(22, 7, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:04:08', 'Donation submitted - pending admin approval'),
(23, 7, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:05:11', 'Status changed from Pending to Cancelled'),
(24, 8, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:05:21', 'Donation submitted - pending admin approval'),
(25, 8, 'AB-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:07:20', 'Status changed from Pending to Cancelled'),
(26, 9, 'A-', 0, 1, 'ADJUSTMENT', '2025-05-24 14:07:31', 'Donation submitted - pending admin approval'),
(27, 9, 'A-', 0, 1, 'ADJUSTMENT', '2025-05-24 18:55:44', 'Status changed from Pending to Cancelled'),
(28, 10, 'O+', 0, 1, 'ADJUSTMENT', '2025-05-24 18:55:55', 'Donation submitted - pending admin approval'),
(29, 11, 'A+', 0, 3, 'ADJUSTMENT', '2025-05-25 03:13:56', 'Donation submitted - pending admin approval'),
(30, 11, 'A-', 1, 3, 'DONATION', '2025-05-25 03:14:10', 'Blood type updated during donation modification'),
(31, NULL, 'AB-', 0, 3, 'ADJUSTMENT', '2025-05-25 03:16:58', 'Blood request updated by user - Request ID: 3 - Blood type changed from A+ to AB-'),
(32, 11, 'A-', 0, 3, 'ADJUSTMENT', '2025-05-25 03:17:08', 'Status changed from Pending to Cancelled'),
(33, 12, 'A+', 0, 4, 'ADJUSTMENT', '2025-05-25 03:38:17', 'Donation submitted - pending admin approval'),
(34, 12, 'O+', 1, 4, 'DONATION', '2025-05-25 03:38:43', 'Blood type updated during donation modification'),
(35, 12, 'O+', 0, 4, 'ADJUSTMENT', '2025-05-25 03:38:47', 'Status changed from Pending to Cancelled'),
(36, NULL, 'A-', 0, 4, 'ADJUSTMENT', '2025-05-25 03:38:50', 'Blood request updated by user - Request ID: 5'),
(37, 13, 'A-', 0, 4, 'ADJUSTMENT', '2025-05-25 03:44:53', 'Donation submitted - pending admin approval'),
(38, 14, 'A+', 0, 5, 'ADJUSTMENT', '2025-05-25 03:45:52', 'Donation submitted - pending admin approval'),
(39, 14, 'AB-', 1, 5, 'DONATION', '2025-05-25 03:46:31', 'Blood type updated during donation modification'),
(40, NULL, 'O+', 0, 5, 'ADJUSTMENT', '2025-05-25 03:46:38', 'Blood request updated by user - Request ID: 6 - Blood type changed from A+ to O+'),
(41, 10, 'O+', 0, 1, 'ADJUSTMENT', '2025-05-25 03:51:55', 'Status changed from Pending to Rejected'),
(42, 10, 'O+', 0, 1, 'ADJUSTMENT', '2025-05-25 03:51:55', 'Donation rejectd by admin - Admin notes: dgdgdf'),
(43, 13, 'A-', 0, 4, 'ADJUSTMENT', '2025-05-25 03:51:59', 'Status changed from Pending to Approved'),
(44, 13, 'A-', 0, 4, 'ADJUSTMENT', '2025-05-25 03:51:59', 'Donation approved by admin');

-- --------------------------------------------------------

--
-- Table structure for table `donate_blood`
--

CREATE TABLE `donate_blood` (
  `donation_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `blood_type` varchar(3) NOT NULL,
  `medical_history` longtext DEFAULT NULL,
  `weight` float DEFAULT NULL CHECK (`weight` >= 50),
  `hospital_location` varchar(255) DEFAULT NULL,
  `date_of_donation` date DEFAULT curdate(),
  `donation_status` enum('Pending','Approved','Completed','Rejected','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_notes` text DEFAULT NULL COMMENT 'Admin notes for donation decisions'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donate_blood`
--

INSERT INTO `donate_blood` (`donation_id`, `donor_id`, `blood_type`, `medical_history`, `weight`, `hospital_location`, `date_of_donation`, `donation_status`, `created_at`, `updated_at`, `admin_notes`) VALUES
(1, 1, 'O+', 'nlnne bebks bbhsebrhser hbsebrh', 56, 'Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines', '0000-00-00', 'Completed', '2025-05-24 12:22:20', '2025-05-24 12:35:26', ''),
(2, 1, 'B+', 'fghg fg fghfghfgjg fgj', 56, 'Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines', '2025-05-24', 'Approved', '2025-05-24 12:35:57', '2025-05-24 12:46:36', ''),
(3, 1, 'AB-', 'sdgsd sadg asdgfdaadg df', 64, 'WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines', '2025-05-24', 'Cancelled', '2025-05-24 14:01:51', '2025-05-24 14:02:22', NULL),
(4, 1, 'AB-', 'dgd dfh dfh dfh d hd hg hgf d', 65, 'WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines', '2025-05-24', 'Cancelled', '2025-05-24 14:02:31', '2025-05-24 14:02:48', NULL),
(5, 1, 'AB-', 'hgfs f fshfg fggjjfg', 65, 'Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City, Iloilo, Philippines', '2025-05-24', 'Cancelled', '2025-05-24 14:02:58', '2025-05-24 14:03:24', NULL),
(6, 1, 'A-', 'dd f hf r jr jrsj rsjsr', 65, 'WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines', '2025-05-24', 'Cancelled', '2025-05-24 14:03:33', '2025-05-24 14:03:59', NULL),
(7, 1, 'AB-', 'dfsgda yryrhdthdhdh td', 64, 'WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines', '2025-05-24', 'Cancelled', '2025-05-24 14:04:08', '2025-05-24 14:05:11', NULL),
(8, 1, 'AB-', 'da hfdhg d hdf hdh df df', 65, 'Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines', '2025-05-24', 'Cancelled', '2025-05-24 14:05:21', '2025-05-24 14:07:20', NULL),
(9, 1, 'A-', 'dfgdfgdfz dfgfdgfddfgg', 56, 'Cedar Hill Medical Complex - 12 Gen. Luna St, San Fernando, Pampanga, Philippines', '2025-05-24', 'Cancelled', '2025-05-24 14:07:31', '2025-05-24 18:55:44', NULL),
(10, 1, 'O+', 'sdfsdf dfdsfsdfsdfsdfsdsdfsd', 67, 'Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines', '2025-05-24', 'Rejected', '2025-05-24 18:55:55', '2025-05-25 03:51:55', 'dgdgdf'),
(11, 3, 'A-', 'dfgdfgdfgdfgfdgdfgdfgdfgdf', 56, 'WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines', '0000-00-00', 'Cancelled', '2025-05-25 03:13:56', '2025-05-25 03:17:08', NULL),
(12, 4, 'O+', 'fgjfjfgjfgjgfjgfjfgj', 67, 'Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna, Philippines', '0000-00-00', 'Cancelled', '2025-05-25 03:38:17', '2025-05-25 03:38:47', NULL),
(13, 4, 'A-', 'dgfjfgjfgjgjgjj fgd dfjfjj', 76, 'Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City, Iloilo, Philippines', '2025-05-25', 'Approved', '2025-05-25 03:44:53', '2025-05-25 03:51:59', ''),
(14, 5, 'AB-', 'hsjkdhfjsdkhfjksjkhf hjksdh', 50, 'Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines', '0000-00-00', 'Pending', '2025-05-25 03:45:52', '2025-05-25 03:46:31', NULL);

--
-- Triggers `donate_blood`
--
DELIMITER $$
CREATE TRIGGER `log_status_changes` AFTER UPDATE ON `donate_blood` FOR EACH ROW BEGIN
    -- Log all status changes for audit trail (but don't affect inventory until completed)
    IF NEW.donation_status != OLD.donation_status THEN
        INSERT INTO blood_log (donation_id, blood_type, units_donated, donor_id, operation_type, notes, timestamp_update)
        VALUES (NEW.donation_id, NEW.blood_type, 0, NEW.donor_id, 'ADJUSTMENT', 
                CONCAT('Status changed from ', OLD.donation_status, ' to ', NEW.donation_status), CURRENT_TIMESTAMP);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_inventory_after_completion` AFTER UPDATE ON `donate_blood` FOR EACH ROW BEGIN
    -- Only update inventory when donation is COMPLETED (final step after admin approval)
    IF NEW.donation_status = 'Completed' AND OLD.donation_status != 'Completed' THEN
        -- Update blood inventory
        INSERT INTO blood_inventory (blood_type, available_units, last_updated) 
        VALUES (NEW.blood_type, 1, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE 
            available_units = available_units + 1,
            last_updated = CURRENT_TIMESTAMP;
        
        -- Log the successful donation
        INSERT INTO blood_log (donation_id, blood_type, units_donated, donor_id, operation_type, notes, timestamp_update)
        VALUES (NEW.donation_id, NEW.blood_type, 1, NEW.donor_id, 'DONATION', 'Donation completed and added to inventory', CURRENT_TIMESTAMP);
    END IF;
    
    -- If blood type changed during update and donation was already completed, adjust inventory
    IF NEW.donation_status = 'Completed' AND OLD.donation_status = 'Completed' AND NEW.blood_type != OLD.blood_type THEN
        -- Remove from old blood type
        UPDATE blood_inventory 
        SET available_units = GREATEST(available_units - 1, 0),
            last_updated = CURRENT_TIMESTAMP
        WHERE blood_type = OLD.blood_type;
        
        -- Add to new blood type
        INSERT INTO blood_inventory (blood_type, available_units, last_updated) 
        VALUES (NEW.blood_type, 1, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE 
            available_units = available_units + 1,
            last_updated = CURRENT_TIMESTAMP;
        
        -- Log the blood type correction
        INSERT INTO blood_log (donation_id, blood_type, units_donated, donor_id, operation_type, notes, timestamp_update)
        VALUES (NEW.donation_id, OLD.blood_type, -1, NEW.donor_id, 'ADJUSTMENT', 'Blood type corrected - removed from inventory', CURRENT_TIMESTAMP),
               (NEW.donation_id, NEW.blood_type, 1, NEW.donor_id, 'ADJUSTMENT', 'Blood type corrected - added to inventory', CURRENT_TIMESTAMP);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `event_image` varchar(255) DEFAULT NULL,
  `max_slots` int(11) NOT NULL DEFAULT 50,
  `booked_slots` int(11) NOT NULL DEFAULT 0,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `start_time`, `end_time`, `location`, `event_image`, `max_slots`, `booked_slots`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Monthly Community Blood Drive', 'Our regular monthly blood drive open to all community members. Help us maintain adequate blood supplies for local hospitals.', '2025-06-01', '09:00:00', '15:00:00', 'Pirate\'s Blood Bank Main Center', 'assets/img/events/1.jpg', 80, 35, 'upcoming', NULL, '2025-05-24 20:10:00', '2025-05-25 03:50:19'),
(2, 'University Campus Drive', 'Special blood drive at State University. Students, faculty, and staff welcome. Free health screening included.', '2025-06-05', '10:00:00', '16:00:00', 'State University Student Center', 'assets/img/events/2.jpg', 100, 22, 'upcoming', NULL, '2025-05-24 20:10:00', '2025-05-25 02:47:54'),
(3, 'Corporate Partner Drive - TechCorp', 'Exclusive blood drive for TechCorp employees and their families. Convenient workplace location.', '2025-06-08', '11:00:00', '14:00:00', 'TechCorp Headquarters', 'assets/img/events/3.jpg', 50, 19, 'upcoming', NULL, '2025-05-24 20:10:00', '2025-05-25 02:47:56'),
(4, 'Emergency Blood Drive', 'Urgent drive due to high demand from local hospitals. All blood types needed, especially O-negative.', '2025-05-28', '08:00:00', '17:00:00', 'Community Center Downtown', 'assets/img/events/4.jpg', 120, 96, 'upcoming', NULL, '2025-05-24 20:10:00', '2025-05-25 02:47:58'),
(5, 'High School Health Fair Drive', 'Blood drive as part of the annual health fair. Educational booths and health screenings available.', '2025-06-12', '09:00:00', '13:00:00', 'Central High School Gymnasium', 'assets/img/events/5.jpg', 60, 8, 'upcoming', NULL, '2025-05-24 20:10:00', '2025-05-25 02:48:00'),
(6, 'Memorial Day Weekend Drive', 'Special holiday drive to ensure adequate supplies during the long weekend when donations typically drop.', '2025-05-31', '10:00:00', '16:00:00', 'Memorial Park Community Hall', 'assets/img/events/6.jpg', 90, 67, 'upcoming', NULL, '2025-05-24 20:10:00', '2025-05-25 02:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `registration_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('registered','attended','cancelled','no_show') DEFAULT 'registered',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_registrations`
--

INSERT INTO `event_registrations` (`registration_id`, `event_id`, `user_id`, `registration_date`, `status`, `notes`) VALUES
(1, 4, 1, '2025-05-24 20:13:36', 'registered', NULL),
(2, 3, 1, '2025-05-24 20:13:44', 'registered', NULL),
(3, 6, 1, '2025-05-25 01:34:43', 'cancelled', NULL),
(4, 1, 5, '2025-05-25 03:50:02', 'cancelled', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard`
--

CREATE TABLE `leaderboard` (
  `id` int(11) NOT NULL,
  `blood_type` varchar(3) DEFAULT NULL,
  `top_donor_list` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_donor_list`)),
  `most_requested_bt` varchar(3) DEFAULT NULL,
  `demand_stats` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`demand_stats`)),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login`
--

CREATE TABLE `login` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','user') NOT NULL,
  `verification_question` enum('What is your mother''s maiden name?','What was the name of your first pet?','What is your favorite color?','What city were you born in?') NOT NULL,
  `verification_answer` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login`
--

INSERT INTO `login` (`user_id`, `username`, `password`, `user_type`, `verification_question`, `verification_answer`, `created_at`) VALUES
(1, 'aundrekaa', '$2y$10$0CguZL6mT.olULwL46aDN.nMIulBJF5yBCyWpuwYLrwLq.euiO1mG', 'user', 'What is your favorite color?', 'PINK', '2025-05-24 12:11:56'),
(2, 'admin', '$2y$10$ohEpAeMcsg4OHEfy08CoW.Ad4mqst2MwpIizEQNlYgYMmDWztJuDu', 'admin', 'What is your favorite color?', 'PINK', '2025-05-24 12:13:15'),
(3, 'gab', '$2y$10$Gg8/NjAWGmpQRtyHp9zFoO/P4uihBmAnPYJ8lkXcTriLqMmGjAjuq', 'user', 'What is your mother\'s maiden name?', 'A', '2025-05-25 03:12:51'),
(4, 'test', '$2y$10$577EyrzImI4Xp4Qmf5xHCOaRqi0kmVOPKk9QeSpgbYSdoFbdOMAsG', 'user', 'What is your mother\'s maiden name?', 'TEST', '2025-05-25 03:37:43'),
(5, 'testuser', '$2y$10$OpyJZAbQOJTg6GTrTkguqulI2B2K/oTa1AqG1hNcja6cn3vQd2kIm', 'admin', 'What is your mother\'s maiden name?', 'FEN', '2025-05-25 03:45:14');

-- --------------------------------------------------------

--
-- Table structure for table `recipient_form`
--

CREATE TABLE `recipient_form` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `blood_type_needed` varchar(3) NOT NULL,
  `medical_conditions` longtext NOT NULL,
  `urgency_level` enum('Low','Medium','High') NOT NULL,
  `hospital_location` varchar(255) NOT NULL,
  `units_needed` int(11) DEFAULT 1,
  `request_status` enum('Pending','Approved','Fulfilled','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `needed_by_date` date NOT NULL,
  `doctor_contact` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipient_form`
--

INSERT INTO `recipient_form` (`request_id`, `user_id`, `blood_type_needed`, `medical_conditions`, `urgency_level`, `hospital_location`, `units_needed`, `request_status`, `created_at`, `needed_by_date`, `doctor_contact`, `emergency_contact`, `updated_at`) VALUES
(1, 1, 'AB-', 'asjkjksa nlfkjsd', 'Medium', 'Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines', 1, 'Pending', '2025-05-24 12:51:49', '2025-05-31', '', '', '2025-05-24 13:03:43'),
(2, 1, 'A-', 'zdfdsfdsfv', 'High', 'Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna, Philippines', 1, 'Pending', '2025-05-25 01:22:18', '2025-06-01', '', '', '2025-05-25 01:22:18'),
(3, 3, 'AB-', 'sdgsdgsdasdasdasd', 'High', 'Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines', 1, 'Cancelled', '2025-05-25 03:16:12', '2025-06-01', '', '', '2025-05-25 03:21:53'),
(4, 3, 'A-', 'dsfsdgsdgsdg', 'High', 'MetroCare Medical Center - 456 West Avenue, Quezon City, Metro Manila, Philippines', 6, 'Pending', '2025-05-25 03:22:05', '2025-06-01', '', '', '2025-05-25 03:22:05'),
(5, 4, 'A-', 'dgdgdfgfdgdfg', 'Medium', 'Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines', 4, 'Cancelled', '2025-05-25 03:38:36', '2025-06-01', '', '', '2025-05-25 03:38:56'),
(6, 5, 'O+', 'gdfggdfgdfgdfgfdg', 'Medium', 'Cedar Hill Medical Complex - 12 Gen. Luna St, San Fernando, Pampanga, Philippines', 3, 'Cancelled', '2025-05-25 03:46:14', '2025-06-01', '', '', '2025-05-25 03:47:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_appeals`
--
ALTER TABLE `admin_appeals`
  ADD PRIMARY KEY (`appeal_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD PRIMARY KEY (`blood_type`);

--
-- Indexes for table `blood_log`
--
ALTER TABLE `blood_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `blood_type` (`blood_type`),
  ADD KEY `idx_blood_log_donation` (`donation_id`),
  ADD KEY `idx_blood_log_timestamp` (`timestamp_update`);

--
-- Indexes for table `donate_blood`
--
ALTER TABLE `donate_blood`
  ADD PRIMARY KEY (`donation_id`),
  ADD KEY `blood_type` (`blood_type`),
  ADD KEY `idx_donate_blood_donor_status` (`donor_id`,`donation_status`),
  ADD KEY `idx_donate_blood_date` (`date_of_donation`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `event_date` (`event_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`registration_id`),
  ADD UNIQUE KEY `event_user` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blood_type` (`blood_type`),
  ADD KEY `most_requested_bt` (`most_requested_bt`);

--
-- Indexes for table `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `recipient_form`
--
ALTER TABLE `recipient_form`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `blood_type_needed` (`blood_type_needed`),
  ADD KEY `idx_recipient_form_user_status` (`user_id`,`request_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_appeals`
--
ALTER TABLE `admin_appeals`
  MODIFY `appeal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `blood_log`
--
ALTER TABLE `blood_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `donate_blood`
--
ALTER TABLE `donate_blood`
  MODIFY `donation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leaderboard`
--
ALTER TABLE `leaderboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login`
--
ALTER TABLE `login`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recipient_form`
--
ALTER TABLE `recipient_form`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_appeals`
--
ALTER TABLE `admin_appeals`
  ADD CONSTRAINT `admin_appeals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `login` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_appeals_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `login` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `blood_log`
--
ALTER TABLE `blood_log`
  ADD CONSTRAINT `blood_log_ibfk_1` FOREIGN KEY (`donation_id`) REFERENCES `donate_blood` (`donation_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blood_log_ibfk_2` FOREIGN KEY (`donor_id`) REFERENCES `login` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blood_log_ibfk_3` FOREIGN KEY (`blood_type`) REFERENCES `blood_inventory` (`blood_type`);

--
-- Constraints for table `donate_blood`
--
ALTER TABLE `donate_blood`
  ADD CONSTRAINT `donate_blood_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `login` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `donate_blood_ibfk_2` FOREIGN KEY (`blood_type`) REFERENCES `blood_inventory` (`blood_type`);

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `login` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD CONSTRAINT `leaderboard_ibfk_1` FOREIGN KEY (`blood_type`) REFERENCES `blood_inventory` (`blood_type`),
  ADD CONSTRAINT `leaderboard_ibfk_2` FOREIGN KEY (`most_requested_bt`) REFERENCES `blood_inventory` (`blood_type`);

--
-- Constraints for table `recipient_form`
--
ALTER TABLE `recipient_form`
  ADD CONSTRAINT `recipient_form_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `login` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recipient_form_ibfk_2` FOREIGN KEY (`blood_type_needed`) REFERENCES `blood_inventory` (`blood_type`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
