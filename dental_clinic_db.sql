-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 10, 2025 at 03:47 PM
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
-- Database: `dental_clinic_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` varchar(10) NOT NULL,
  `patient_id` varchar(10) NOT NULL,
  `team_id` varchar(10) NOT NULL,
  `service_id` varchar(10) NOT NULL,
  `branch` varchar(20) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(20) NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `status` enum('Pending','Confirmed','Reschedule','Complete','Cancelled','No-show') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `team_id`, `service_id`, `branch`, `appointment_date`, `appointment_time`, `time_slot`, `status`, `created_at`) VALUES
('A001', 'P026', 'T001', 'S1001', 'Comembo Branch', '2025-11-04', '9:00AM-10:00AM', 'secondBatch', 'Complete', '2025-11-03 13:49:53'),
('A002', 'P027', 'T001', 'S1001', 'Comembo Branch', '2025-11-09', '10:00AM-11:00AM', 'thirdBatch', 'Pending', '2025-11-06 01:18:40'),
('A003', 'P028', 'T001', 'S002', 'Taytay Rizal Branch', '2025-11-09', '1:00PM-2:00PM', 'fifthBatch', 'Pending', '2025-11-07 00:43:06'),
('A004', 'P029', 'T001', 'S001', 'Comembo Branch', '2025-11-13', '1:00PM-2:00PM', 'fifthBatch', 'Pending', '2025-11-08 09:17:58'),
('A005', 'P030', 'T001', 'S003', 'Comembo Branch', '2025-11-13', '2:00PM-3:00PM', 'sixthBatch', 'Pending', '2025-11-09 12:04:11');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_time_slots`
--

CREATE TABLE `blocked_time_slots` (
  `block_id` varchar(10) NOT NULL,
  `dentist_id` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_by` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dental_analytics_logs`
--

CREATE TABLE `dental_analytics_logs` (
  `logs_id` varchar(10) NOT NULL,
  `patient_id` varchar(10) NOT NULL,
  `team_id` varchar(10) NOT NULL,
  `appointment_id` varchar(10) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dental_blogs`
--

CREATE TABLE `dental_blogs` (
  `blog_id` varchar(10) NOT NULL,
  `title` varchar(20) NOT NULL,
  `content` text NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` enum('published','draft','archived') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dental_blogs`
--

INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES
('', 'Unlock Your Best Smi', 'Your smile is often the first thing people notice – it’s a powerful tool for connection and confidence! But a truly dazzling smile isn\'t just about good genes; it\'s built on consistent, caring habits. Investing in your oral health is investing in your overall well-being.\n\nHere are three simple tips to keep your smile shining brightly:\n\n1.  **Brush Smart, Not Hard:** Use a soft-bristled toothbrush and fluoride toothpaste twice daily for two minutes. Focus on gently cleaning all surfaces of your teeth and gums.\n2.  **Floss Like a Boss:** Daily flossing removes food particles and plaque that brushing misses, preventing cavities and gum disease. It’s a game-changer for fresh breath!\n3.  **Don\'t Skip Your Check-ups:** Regular professional cleanings and examinations are vital. We can spot issues early, keeping your smile healthy and vibrant.\n\nMake these habits part of your routine, and you\'ll be amazed at the difference! Ready to give your smile the care it deserves? We\'re here to help you achieve your brightest, healthiest smile yet.', '2025-11-03 13:55:20', 'published', '2025-11-03 05:55:20'),
('B001', 'Smile Brighter: Simp', 'Your smile is more than just a greeting – it\'s a powerful tool that conveys confidence, happiness, and even boosts your overall well-being! We believe everyone deserves a healthy, radiant smile. And the great news? Achieving one starts with just a few simple, consistent habits right at home.\n\nMake brushing twice a day for two minutes each time your non-negotiable routine. Don\'t forget to floss daily to tackle those sneaky food particles and plaque hiding between your teeth. Choosing water over sugary drinks and snacks also makes a huge difference!\n\nBeyond your home care, regular check-ups and professional cleanings are your smile\'s best friends. These appointments allow our friendly team to spot potential issues early, keep your teeth sparkling, and offer personalized tips.\n\nReady to brighten your day with a healthier smile? We\'re here to help! Schedule your next visit with us and let\'s keep your smile shining its brightest.', '2025-11-02 12:21:29', 'published', '2025-11-02 04:21:29');

-- --------------------------------------------------------

--
-- Table structure for table `dentist_schedule`
--

CREATE TABLE `dentist_schedule` (
  `schedule_id` varchar(10) NOT NULL,
  `dentist_id` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `status` enum('available','blocked','booked') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dentist_schedule`
--

INSERT INTO `dentist_schedule` (`schedule_id`, `dentist_id`, `date`, `time_slot`, `status`, `created_at`, `updated_at`) VALUES
('DS001', 'T001', '2025-11-12', 'secondBatch', 'available', '2025-11-03 13:47:47', '2025-11-03 13:47:47');

-- --------------------------------------------------------

--
-- Table structure for table `multidisciplinary_dental_team`
--

CREATE TABLE `multidisciplinary_dental_team` (
  `team_id` varchar(10) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `specialization` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `status` enum('active','inactive') NOT NULL,
  `last_active` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `multidisciplinary_dental_team`
--

INSERT INTO `multidisciplinary_dental_team` (`team_id`, `user_id`, `first_name`, `last_name`, `specialization`, `email`, `phone`, `status`, `last_active`, `created_at`) VALUES
('T001', 'U0005', 'Arisu', 'Kazamoto', 'Dentist', 'arisukazamoto@gmail.com', '0919299241', 'active', '2025-11-03 09:51:17', '2025-11-03 01:51:03');

-- --------------------------------------------------------

--
-- Table structure for table `patient_information`
--

CREATE TABLE `patient_information` (
  `patient_id` varchar(10) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthdate` date NOT NULL,
  `gender` varchar(10) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(50) NOT NULL,
  `address` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_information`
--

INSERT INTO `patient_information` (`patient_id`, `user_id`, `first_name`, `last_name`, `birthdate`, `gender`, `phone`, `email`, `address`, `created_at`) VALUES
('P016', 'U0001', 'Vince Henrick', 'Padilla', '2005-12-07', 'Male', '09938383851', 'kirito.nakamura7@gmail.com', 'Lawin St Taguig City, Brgy Rizal, Taguig City, 120', '2025-11-01 04:26:33'),
('P023', 'U0001', 'Vince Henrick', 'Padilla', '2005-12-07', 'Male', '09938383851', 'kirito.nakamura7@gmail.com', 'Lawin St Taguig City, Brgy Rizal, Taguig City, 120', '2025-11-01 04:57:30'),
('P024', 'U0003', 'Naruto', 'Uzumaki', '2000-02-05', 'Male', '09286765223', 'kirito.nakamura3@gmail.com', '122 HouseBuild, Pinagsama, Taguig City, 9352', '2025-11-02 00:20:53'),
('P025', 'U0003', 'Naruto', 'Uzumaki', '2001-02-05', 'Male', '09286765223', 'kirito.nakamura3@gmail.com', '122 HouseBuild, Pinagsama, Taguig City, 9352', '2025-11-02 01:00:00'),
('P026', 'U0001', 'Vince Henrick', 'Padilla', '2005-12-07', 'Male', '09938383851', 'kirito.nakamura7@gmail.com', 'Lawin St Taguig City, Brgy Rizal, Taguig City, 120', '2025-11-03 13:49:53'),
('P027', 'U0001', 'Vince Henrick', 'Padilla', '2005-12-07', 'Male', '09938383851', 'kirito.nakamura7@gmail.com', 'Lawin St Taguig City, Brgy Rizal, Taguig City, 120', '2025-11-06 01:18:40'),
('P028', 'U0004', 'Ashley', 'Gonzales', '2005-12-07', 'Male', '09949495656', 'lafox77022@dwakm.com', 'Lawin St Taguig City, Brgy Rizal, Taguig City, 120', '2025-11-07 00:43:06'),
('P029', 'U0006', 'Kenneth', 'Jana', '2005-07-06', 'male', '09988976545', 'bodagi7557@limtu.com', 'Anahaw St, Comembo. Taguig City', '2025-11-08 09:17:58'),
('P030', 'U0001', 'Vince Henrick', 'Padilla', '2015-11-04', 'Male', '09938383851', 'kirito.nakamura7@gmail.com', 'Lawin St Taguig City', '2025-11-09 12:04:11');

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` varchar(10) NOT NULL,
  `appointment_id` varchar(10) NOT NULL,
  `method` varchar(50) NOT NULL,
  `account_name` varchar(50) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','paid','refunded') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `appointment_id`, `method`, `account_name`, `account_number`, `amount`, `reference_no`, `proof_image`, `status`, `created_at`) VALUES
('PY001', 'A001', 'GCash', 'Vince Padilla', '09150692736', 500.00, '3435352', 'uploads/6908b3019a76f_12225935.png', 'pending', '2025-11-03 13:49:53'),
('PY002', 'A002', 'GCash', 'Vince Padilla', '09150692736', 500.00, '3435352', 'uploads/690bf7701f4b5_Dr1001_Stamp.png', 'paid', '2025-11-06 01:18:40'),
('PY003', 'A003', 'GCash', 'Ashley Gonzales', '09838384363', 500.00, '97975', 'uploads/690d409a2cb76_GCash-iBayad_Umak-Receipt-21072025123636.PNG.jpg', 'paid', '2025-11-07 00:43:06'),
('PY004', 'A004', 'GCash', 'Kenneth Jana', '09988979753', 500.00, '242411', 'uploads/690f0ac6cb079_Gemini_Generated_Image_h1zf1gh1zf1gh1zf.png', 'pending', '2025-11-08 09:17:58'),
('PY005', 'A005', 'GCash', 'Vince Padilla', '09883737745', 500.00, '089786', 'uploads/6910833befc21_gizguide-paymaya-gcash-4.png', 'pending', '2025-11-09 12:04:11');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` varchar(10) NOT NULL,
  `service_category` varchar(50) NOT NULL,
  `sub_service` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES
('S001', 'General Dentistry', 'Checkups', 'Routine dental checkups involve a comprehensive examination and cleaning to prevent oral disease, while a tooth extraction is the removal of a tooth that is too damaged or infected to be saved.', 0.00, '2025-11-01 03:44:24'),
('S002', 'Orthodontics', 'Braces', 'To provide a comprehensive overview that captures the essential ideas while leaving out the non-essential details. A good summary is always much shorter than the original.', 0.00, '2025-11-02 00:37:46'),
('S003', 'Oral Surgery', 'Tooth Extraction (Bunot)', 'The professional, gentle removal of a tooth that is irreparably damaged, decayed, or causing crowding and infection. We prioritize patient comfort and use local anesthesia to ensure a smooth procedure, helping to protect the overall health of your mouth.', 0.00, '2025-11-09 11:02:31'),
('S004', 'Endodontics', 'Root Canal Treatment', 'A procedure to save a severely damaged tooth when the pulp (nerve) inside is infected or inflamed. We carefully clean, sterilize, and seal the internal root canal system to eliminate pain, infection, and the need for extraction, preserving the natural tooth structure.', 0.00, '2025-11-09 11:01:04'),
('S005', 'Prosthodontics Treatments (Pustiso)', 'Crowns', 'Dental crowns are custom-made caps placed entirely over a damaged or weakened tooth. They are used to restore the tooth\'s shape, strength, and appearance following a root canal or extensive decay, providing protection and improving function.', 0.00, '2025-11-09 11:12:36'),
('S1001', 'General Dentistry', 'Oral Prophylaxis (Cleaning)', 'A professional dental cleaning is a procedure typically performed by a dental hygienist or dentist to thoroughly clean your teeth and maintain optimal oral health.', 0.00, '2025-11-02 00:33:50'),
('S1002', 'General Dentistry', 'Fluoride Application', 'Professional Fluoride Treatment Topical application to remineralize weak enamel and significantly reduce the risk of cavities, promoting long-term oral health for all ages.', 0.00, '2025-11-09 10:56:37'),
('S1003', 'General Dentistry', 'Pit & Fissure Sealants', 'A fast, painless, protective barrier applied to the chewing surfaces of back teeth (molars). This thin, tooth-colored coating instantly seals the deep grooves to block out food, plaque, and bacteria, effectively preventing over 80% of cavities in the sealed areas', 0.00, '2025-11-09 10:58:07'),
('S1004', 'General Dentistry', 'Tooth Restoration (Pasta)', 'A procedure to repair teeth damaged by decay, fractures, or cracks. We gently remove the damaged material and restore the tooth\'s shape, function, and appearance using durable, tooth-colored composite resin (or other chosen materials). This prevents further decay and eliminates sensitivity.', 0.00, '2025-11-09 10:59:55'),
('S2001', 'Orthodontics', 'Retainers', 'Custom-made dental appliances used after orthodontic treatment (like braces or aligners). Retainers are essential to stabilize and maintain the new position of your teeth, preventing them from shifting back and ensuring your beautifully straight smile lasts a lifetime.', 0.00, '2025-11-09 11:08:05'),
('S5001', 'Prosthodontics Treatments (Pustiso)', 'Dentures', 'Removable appliances that replace missing teeth and surrounding tissues. We provide full (complete) and partial dentures that are custom designed to restore your ability to chew, speak clearly, and improve your smile and facial contours.', 0.00, '2025-11-09 11:13:27');

-- --------------------------------------------------------

--
-- Table structure for table `special_availability`
--

CREATE TABLE `special_availability` (
  `availability_id` varchar(10) NOT NULL,
  `dentist_id` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatment_history`
--

CREATE TABLE `treatment_history` (
  `treatment_id` varchar(10) NOT NULL,
  `patient_id` varchar(10) NOT NULL,
  `treatment` varchar(50) NOT NULL,
  `prescription_given` varchar(50) NOT NULL,
  `treatment_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_history`
--

INSERT INTO `treatment_history` (`treatment_id`, `patient_id`, `treatment`, `prescription_given`, `treatment_cost`, `notes`, `created_at`, `updated_at`) VALUES
('TR0001', 'P026', 'Cleaning', 'Bioflu', 1000.00, 'N/A', '2025-11-10 12:57:03', '2025-11-10 12:57:03');

-- --------------------------------------------------------

--
-- Table structure for table `user_account`
--

CREATE TABLE `user_account` (
  `user_id` varchar(10) NOT NULL,
  `role` enum('patient','dentist','admin') NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `contactNumber_verify` enum('verified','not_verified') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_account`
--

INSERT INTO `user_account` (`user_id`, `role`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES
('U0001', 'patient', 'vince', 'Vince Henrick', 'Padilla', '2015-11-04', 'Male', 'Lawin St Taguig City', '$2y$10$0iOkoTCVPQas8LMIlMJxR.Qn3Ct5szu0sFofMZO.BlcWyc4oB0XXm', 'kirito.nakamura7@gmail.com', '09938383851', 'verified', '2025-11-01 02:24:21'),
('U0003', 'patient', 'naruto12', 'Naruto', 'Uzumaki', '2015-11-15', 'Male', 'Pinagsama Taguig City', '$2y$10$3z4B7P1ZA1l8rbearzvOCu2tqa9oGTCIqi7gv/BDgM7JErvjb0F46', 'kirito.nakamura3@gmail.com', '09286765223', 'verified', '2025-11-01 05:28:23'),
('U0004', 'patient', 'ashley', 'Ashley', 'Gonzales', '2016-11-30', 'Male', 'Anahaw St Comembo Taguig City', '$2y$10$cKZ21NJJca/NNuyaUl.Q5eFTHSJ9TUafKK.4SRBesVOIAlBjaS6Ye', 'lafox77022@dwakm.com', '09949495656', 'verified', '2025-11-03 00:29:29'),
('U0005', 'admin', 'admin', 'Arisu', 'Kazamoto', '2018-11-12', 'Male', 'Kyoto Japan', '$2y$10$VkO.yPV1Xi/.7FQgWjgYHuI2Gckbjp/jTBdmmXJXafHpKrI6e7que', 'arisukazamoto@gmail.com', '09889797656', 'verified', '2025-11-03 00:48:04'),
('U0006', 'patient', 'kenneth', 'Kenneth', 'Jana', '2005-07-06', 'male', 'Anahaw St, Comembo. Taguig City', '$2y$10$Prd1QuepoUXja3./fNpPNu92.cwqynUThplFLOfNL83suy8C9tB6e', 'bodagi7557@limtu.com', '09988976545', 'verified', '2025-11-08 00:31:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointment_patient` (`patient_id`),
  ADD KEY `fk_appointment_team` (`team_id`),
  ADD KEY `fk_appointment_service` (`service_id`);

--
-- Indexes for table `blocked_time_slots`
--
ALTER TABLE `blocked_time_slots`
  ADD PRIMARY KEY (`block_id`),
  ADD UNIQUE KEY `unique_blocked_slot` (`dentist_id`,`date`,`time_slot`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `dental_analytics_logs`
--
ALTER TABLE `dental_analytics_logs`
  ADD PRIMARY KEY (`logs_id`),
  ADD KEY `fk_logs_patient` (`patient_id`),
  ADD KEY `fk_logs_appointment` (`appointment_id`),
  ADD KEY `fk_logs_team` (`team_id`);

--
-- Indexes for table `dental_blogs`
--
ALTER TABLE `dental_blogs`
  ADD PRIMARY KEY (`blog_id`);

--
-- Indexes for table `dentist_schedule`
--
ALTER TABLE `dentist_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `unique_slot` (`dentist_id`,`date`,`time_slot`);

--
-- Indexes for table `multidisciplinary_dental_team`
--
ALTER TABLE `multidisciplinary_dental_team`
  ADD PRIMARY KEY (`team_id`),
  ADD KEY `fk_team_user` (`user_id`);

--
-- Indexes for table `patient_information`
--
ALTER TABLE `patient_information`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `fk_patient_user` (`user_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_appointment` (`appointment_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `special_availability`
--
ALTER TABLE `special_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD KEY `dentist_id` (`dentist_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `treatment_history`
--
ALTER TABLE `treatment_history`
  ADD PRIMARY KEY (`treatment_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `user_account`
--
ALTER TABLE `user_account`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_team` FOREIGN KEY (`team_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `blocked_time_slots`
--
ALTER TABLE `blocked_time_slots`
  ADD CONSTRAINT `blocked_time_slots_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `blocked_time_slots_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `user_account` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dental_analytics_logs`
--
ALTER TABLE `dental_analytics_logs`
  ADD CONSTRAINT `fk_logs_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_logs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_logs_team` FOREIGN KEY (`team_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dentist_schedule`
--
ALTER TABLE `dentist_schedule`
  ADD CONSTRAINT `dentist_schedule_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `multidisciplinary_dental_team`
--
ALTER TABLE `multidisciplinary_dental_team`
  ADD CONSTRAINT `fk_team_user` FOREIGN KEY (`user_id`) REFERENCES `user_account` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_information`
--
ALTER TABLE `patient_information`
  ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `user_account` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `fk_payment_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `special_availability`
--
ALTER TABLE `special_availability`
  ADD CONSTRAINT `special_availability_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `special_availability_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `user_account` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `treatment_history`
--
ALTER TABLE `treatment_history`
  ADD CONSTRAINT `treatment_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`patient_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
