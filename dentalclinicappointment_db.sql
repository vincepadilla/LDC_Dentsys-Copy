-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2025 at 03:42 PM
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
-- Database: `dentalclinicappointment_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointments`
--

CREATE TABLE `tbl_appointments` (
  `appointment_id` int(11) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `patient_name` varchar(100) NOT NULL,
  `service` varchar(100) NOT NULL,
  `dentist` varchar(100) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(255) NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `status` enum('Pending','Confirmed','Reschedule','Completed','Cancelled','No-Show') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_appointments`
--

INSERT INTO `tbl_appointments` (`appointment_id`, `userID`, `patient_name`, `service`, `dentist`, `appointment_date`, `appointment_time`, `time_slot`, `status`, `created_at`) VALUES
(104, 56, 'Von Jeres Sabado', 'General (Cleaning)', 'Dr. Allen', '2025-05-30', '1:00PM-2:00PM', 'fifthBatch', 'Reschedule', '2025-05-28 01:54:33'),
(105, 57, 'Ashley Nicole Gonzales', 'General (Extraction)', 'Dr. Carol', '2025-06-03', '8:00AM-9:00AM', 'firstBatch', 'Pending', '2025-05-28 02:00:21'),
(106, 58, 'Kenneth Jana', 'Orthodontics (Braces)', 'Dr. Carol', '2025-05-31', '1:00PM-2:00PM', 'fifthBatch', 'Completed', '2025-05-28 23:39:53'),
(107, 58, 'Kenneth Jana', 'Brace-Adjustments', 'Dr. Carol', '2025-06-01', '8:00AM-9:00AM', 'firstBatch', 'Confirmed', '2025-05-28 23:45:38'),
(108, 59, 'Vince Padilla', 'General (Checkups)', 'Dr. Allen', '2025-06-02', '1:00PM-2:00PM', 'fifthBatch', 'Completed', '2025-05-29 05:48:16'),
(109, 60, 'Juan Dela Cruz', 'General (Cleaning)', 'Dr. Allen', '2025-06-02', '9:00AM-10:00AM', 'secondBatch', 'Pending', '2025-05-31 00:33:06'),
(110, 61, 'Naruto Uzumaki', 'Orthodontics (Braces)', 'Dr. Carol', '2025-06-03', '2:00PM-3:00PM', 'sixthBatch', 'Reschedule', '2025-06-01 13:37:14');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dentists`
--

CREATE TABLE `tbl_dentists` (
  `dentist_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_dentists`
--

INSERT INTO `tbl_dentists` (`dentist_id`, `first_name`, `last_name`, `specialization`, `email`, `phone`, `status`, `date_added`) VALUES
(1, 'Allen', 'Lobregat', 'Dentist', 'allenlobregat07@gmail.com', '09135427605', 'active', '2025-05-03 00:17:10'),
(2, 'Ann Caroline', 'Ong', 'Dentist', 'ongcarol8@gmail.com', '09935641073', 'active', '2025-05-03 00:19:33');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patients`
--

CREATE TABLE `tbl_patients` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `birthdate` date NOT NULL,
  `age` int(50) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_patients`
--

INSERT INTO `tbl_patients` (`patient_id`, `user_id`, `first_name`, `last_name`, `birthdate`, `age`, `gender`, `email`, `phone`, `address`, `created_at`) VALUES
(76, 56, 'Von', 'Jeres Sabado', '2005-02-08', 19, 'Male', 'kirito.nakamura7@gmail.com', '0928676572', 'Anahaw St, Brgy Comembo, Taguig City, 1284', '2025-05-28 01:54:33'),
(77, 57, 'Ashley Nicole', 'Gonzales', '2004-08-09', 20, 'Male', 'kirito.nakamura3@gmail.com', '09286765223', 'Lawin St, Brgy Rizal, Taguig City, 1205', '2025-05-28 02:00:21'),
(78, 58, 'Kenneth', 'Jana', '2005-05-28', 20, 'Male', 'arisukazamoto@gmail.com', '0928676520', 'Anahaw St, Brgy Comembo, Taguig City, 1365', '2025-05-28 23:39:53'),
(79, 59, 'Vince', 'Padilla', '2003-06-10', 21, 'Male', 'padillavincehenrick@gmail.com', '0926768585', 'Lawin St Taguig City, Brgy Rizal, Taguig City, 1205', '2025-05-29 05:48:16'),
(80, 60, 'Juan', 'Dela Cruz', '2003-06-20', 21, 'Male', 'vincehenrick.padilla@depedmakati.ph', '09286765204', 'Sampaguita St, Brgy Pembo, Taguig City, 1852', '2025-05-31 00:33:06'),
(81, 61, 'Naruto', 'Uzumaki', '2004-04-14', 21, 'Male', 'lewib37922@cigidea.com', '0928675585', 'Tokushima, Easter Shikoku, Japan City, 8902', '2025-06-01 13:37:14');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payment`
--

CREATE TABLE `tbl_payment` (
  `payment_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `method` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL CHECK (`amount` >= 500),
  `reference_no` varchar(100) NOT NULL,
  `proof_image` varchar(200) NOT NULL,
  `status` enum('paid','pending','failed','refund') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_payment`
--

INSERT INTO `tbl_payment` (`payment_id`, `appointment_id`, `user_id`, `method`, `account_name`, `account_number`, `amount`, `reference_no`, `proof_image`, `status`, `created_at`) VALUES
(37, 104, 56, 'PayMaya', 'Von Jeres Sabado', '096357752', 500.00, '0225576', 'uploads/68366cd9cfff3_mayaproof.jpg', 'paid', '2025-05-28 09:54:33'),
(38, 105, 57, 'GCash', 'Ashley Gonzales', '0958675563', 500.00, '3636562', 'uploads/68366e3554db4_proof.jpg', 'paid', '2025-05-28 10:00:21'),
(39, 106, 58, 'GCash', 'Kenneth Jana', '0928655220', 500.00, '988464', 'uploads/68379ec969a54_screeshot2025.jpg', 'paid', '2025-05-29 07:39:53'),
(40, 108, 59, 'GCash', 'Vince Padilla', '0999878784', 500.00, '0216484', 'uploads/6837f520cb027_2d111202-4d47-4e08-b6f4-b71643bb9c9b.jpg', 'paid', '2025-05-29 13:48:16'),
(41, 109, 60, 'GCash', 'Juan Dela Cruz', '09512269824', 500.00, '023224', 'uploads/683a4e42632c2_screenshotgcash.jpg', 'pending', '2025-05-31 08:33:06'),
(42, 110, 61, 'PayMaya', 'Naruto Uzumaki', '09256522317', 500.00, '755423', 'uploads/683c578a492fd_screenshot2025845.jpg', 'paid', '2025-06-01 21:37:14');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_services`
--

CREATE TABLE `tbl_services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_services`
--

INSERT INTO `tbl_services` (`service_id`, `service_name`, `description`, `price`) VALUES
(3, 'Orthodontics', 'Braces and aligners for a perfectly straight smile.', 25000.00),
(9, 'General Dentistry', 'Regular checkups, cleanings, Tooth Extraction, Fillings, and Preventive Care.', 15000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('patient','admin','staff') DEFAULT 'patient',
  `email_verify` varchar(50) NOT NULL,
  `email_verified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `first_name`, `last_name`, `email`, `password_hash`, `phone`, `role`, `email_verify`, `email_verified_at`) VALUES
(56, 'von', 'Von Jeres', 'Sabado', 'kirito.nakamura7@gmail.com', '$2y$10$2rbesRT4V5UeF2UqLLo21u2zmo45QiLYBlAU8K1EjXW8H.KXIb3lm', '0928676572', 'patient', 'verified', '2025-05-28 01:53:07'),
(57, 'ashley', 'Ashley Nicole', 'Gonzales', 'kirito.nakamura3@gmail.com', '$2y$10$HRk4OatBhsl9M6TPHVIqyefG9vb7Bl1z2yQzzGAvVDHKjoKk85om.', '09286765223', 'patient', 'verified', '2025-05-28 01:58:25'),
(58, 'kenneth', 'Kenneth', 'Jana', 'arisukazamoto@gmail.com', '$2y$10$FMYjsvIRAM1wDemuqEqwle/L9bZ4XGzkPq56SrrXJyCZJjWYi4f1O', '0928676520', 'patient', 'verified', '2025-05-28 23:36:19'),
(59, 'vince', 'Vince', 'Padilla', 'padillavincehenrick@gmail.com', '$2y$10$YrRHCuul1vMgvZpbj56FmuD4ZMMoIQevnCeoblEwx6FVVNqJDQIq6', '0926768585', 'patient', 'verified', '2025-05-29 05:44:47'),
(60, 'juan', 'Juan', 'Dela Cruz', 'vincehenrick.padilla@depedmakati.ph', '$2y$10$Wd2FYL4jRKL2kTpfurcW3OKINIewBmOlULGW9ykx/nWcg6n4AUgpy', '09286765204', 'patient', 'verified', '2025-05-31 00:14:39'),
(61, 'naruto', 'Naruto', 'Uzumaki', 'lewib37922@cigidea.com', '$2y$10$8BFc9m/lZqwpqndQvB2VuuXaX1NsM.0rbU4S7o2XX7t8mKk3WVo0.', '0928675585', 'patient', 'verified', '2025-06-01 13:32:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointment_user` (`userID`);

--
-- Indexes for table `tbl_dentists`
--
ALTER TABLE `tbl_dentists`
  ADD PRIMARY KEY (`dentist_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `fk_user` (`user_id`);

--
-- Indexes for table `tbl_payment`
--
ALTER TABLE `tbl_payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_appointment` (`appointment_id`),
  ADD KEY `fk_payment_user` (`user_id`);

--
-- Indexes for table `tbl_services`
--
ALTER TABLE `tbl_services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `tbl_dentists`
--
ALTER TABLE `tbl_dentists`
  MODIFY `dentist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `tbl_payment`
--
ALTER TABLE `tbl_payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `tbl_services`
--
ALTER TABLE `tbl_services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  ADD CONSTRAINT `fk_appointment_user` FOREIGN KEY (`userID`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_payment`
--
ALTER TABLE `tbl_payment`
  ADD CONSTRAINT `fk_payment_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
