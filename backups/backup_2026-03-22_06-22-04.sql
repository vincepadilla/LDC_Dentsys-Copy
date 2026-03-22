-- Database Backup
-- Generated: 2026-03-22 06:22:04
-- Database: dental_clinic_db

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `appointment_id` varchar(10) NOT NULL,
  `patient_id` varchar(10) NOT NULL,
  `team_id` varchar(10) NOT NULL,
  `service_id` varchar(10) NOT NULL,
  `branch` varchar(20) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(20) NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `status` enum('Pending','Confirmed','Reschedule','Completed','Cancelled','No-show','Follow-Up') DEFAULT NULL,
  `ticket_code` varchar(32) DEFAULT NULL,
  `ticket_expires_at` datetime DEFAULT NULL,
  `ticket_status` enum('issued','used','expired') NOT NULL DEFAULT 'issued',
  `arrival_verified` tinyint(1) NOT NULL DEFAULT 0,
  `reschedule_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `request_note` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`appointment_id`),
  KEY `fk_appointment_patient` (`patient_id`),
  KEY `fk_appointment_team` (`team_id`),
  KEY `fk_appointment_service` (`service_id`),
  KEY `idx_appointments_ticket_code` (`ticket_code`),
  CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointment_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointment_team` FOREIGN KEY (`team_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

--
-- Table structure for table `archived_appointments`
--

DROP TABLE IF EXISTS `archived_appointments`;
CREATE TABLE `archived_appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` varchar(20) DEFAULT NULL,
  `patient_name` varchar(100) DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL,
  `dentist` varchar(100) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `appointment_time` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_appointments`
--

--
-- Table structure for table `blocked_time_slots`
--

DROP TABLE IF EXISTS `blocked_time_slots`;
CREATE TABLE `blocked_time_slots` (
  `block_id` varchar(10) NOT NULL,
  `dentist_id` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_by` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`block_id`),
  UNIQUE KEY `unique_blocked_slot` (`dentist_id`,`date`,`time_slot`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `blocked_time_slots_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `blocked_time_slots_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `user_account` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blocked_time_slots`
--

INSERT INTO `blocked_time_slots` (`block_id`, `dentist_id`, `date`, `time_slot`, `reason`, `created_by`, `created_at`, `updated_at`) VALUES ('BLK001', 'T001', '2026-03-21', 'sixthBatch', 'Blocked by admin', 'U0005', '2026-03-21 13:02:59', '2026-03-21 13:02:59');
INSERT INTO `blocked_time_slots` (`block_id`, `dentist_id`, `date`, `time_slot`, `reason`, `created_by`, `created_at`, `updated_at`) VALUES ('BLK002', 'T001', '2026-03-23', 'firstBatch', 'Tinatamad si Nicole', 'U0005', '2026-03-21 14:27:26', '2026-03-21 14:27:26');

--
-- Table structure for table `clinic_closures`
--

DROP TABLE IF EXISTS `clinic_closures`;
CREATE TABLE `clinic_closures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `closure_date` date NOT NULL,
  `closure_type` enum('full_day','no_new_appointments') NOT NULL DEFAULT 'full_day',
  `reason` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_active_closure` (`closure_date`,`status`),
  KEY `idx_closure_date` (`closure_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_closures`
--

INSERT INTO `clinic_closures` (`id`, `closure_date`, `closure_type`, `reason`, `status`, `created_at`) VALUES ('6', '2025-11-26', 'full_day', 'Weather', 'inactive', '2025-11-23 20:56:04');
INSERT INTO `clinic_closures` (`id`, `closure_date`, `closure_type`, `reason`, `status`, `created_at`) VALUES ('7', '2025-11-30', 'full_day', 'Holiday: Bonifacio Day', 'inactive', '2025-11-23 20:59:35');
INSERT INTO `clinic_closures` (`id`, `closure_date`, `closure_type`, `reason`, `status`, `created_at`) VALUES ('8', '2025-11-27', 'full_day', 'Emergency: Dentist Feel Sick', 'inactive', '2025-11-23 21:02:14');
INSERT INTO `clinic_closures` (`id`, `closure_date`, `closure_type`, `reason`, `status`, `created_at`) VALUES ('9', '2025-11-24', 'full_day', 'Emergency: Vacation', 'inactive', '2025-11-23 21:03:41');
INSERT INTO `clinic_closures` (`id`, `closure_date`, `closure_type`, `reason`, `status`, `created_at`) VALUES ('10', '2025-11-29', 'full_day', 'Emergency', 'active', '2025-11-24 15:05:57');
INSERT INTO `clinic_closures` (`id`, `closure_date`, `closure_type`, `reason`, `status`, `created_at`) VALUES ('11', '2026-03-12', 'full_day', 'Holiday', 'active', '2026-03-02 20:04:28');

--
-- Table structure for table `dental_blogs`
--

DROP TABLE IF EXISTS `dental_blogs`;
CREATE TABLE `dental_blogs` (
  `blog_id` varchar(10) NOT NULL,
  `title` varchar(20) NOT NULL,
  `content` text NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` enum('published','draft','archived') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`blog_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dental_blogs`
--

INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B001', 'Unlock Your Best Smi', 'Hey there, smile squad! What truly makes a dazzling, healthy smile? It’s consistent daily care! We believe a radiant smile boosts your confidence and overall well-being.\n\nLet\'s talk essentials:\n*   **Brush Up!** Aim for two minutes, twice a day, with fluoride toothpaste. Think of it as a mini-spa for your teeth!\n*   **Floss is Boss!** Don\'t skip this crucial step. Flossing daily removes plaque between teeth, preventing cavities and gum disease.\n*   **Rinse & Shine:** A good mouthwash can be a fantastic addition, helping reduce bacteria and freshen your breath.\n\nRemember, regular check-ups with us are your secret weapon for keeping everything in tip-top shape. We\'re here to support your journey to a healthy, happy smile that truly shines. Keep those pearly whites gleaming!', '2025-11-12 16:09:46', 'published', '2025-11-12 16:09:46');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B002', 'Unlock Your Confiden', 'Your smile is a powerful tool – it brightens your day and connects you with others! At our modern clinic, we believe everyone deserves a healthy, radiant smile. The best part? Achieving it is simpler than you think with just a few consistent habits.\n\nStart with the essentials: brush for two minutes, twice a day, using fluoride toothpaste. This daily ritual effectively removes food particles and fights plaque buildup. Don\'t skip flossing! It\'s your secret weapon against hidden plaque between teeth and under the gum line, preventing cavities and gum disease. A quick swish with an antimicrobial mouthwash can offer an extra boost of freshness.\n\nBeyond your daily routine, consider what you eat. Limiting sugary snacks and drinks reduces fuel for harmful bacteria. And crucially, don\'t forget your regular dental check-ups and cleanings! These professional visits are vital for early detection, prevention, and keeping your oral health in peak condition. Let\'s keep your smile sparkling bright!', '2025-11-13 13:18:01', 'published', '2025-11-13 13:18:01');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B003', 'Your Daily Dose of D', 'Ever wonder what makes a smile truly radiant? It\'s more than just genetics; it\'s a consistent routine of care and a little bit of love! Here at [Your Clinic Name], we believe everyone deserves to flash their brightest grin with confidence.\n\nYour journey to a healthier, happier smile starts right at home. Remember the golden rules: brush twice a day for two minutes with fluoride toothpaste, and don\'t forget to floss daily to banish those hidden food particles and plaque. Consider a tongue scraper for fresher breath, too! What you eat plays a big role – fresh fruits and veggies help keep your gums healthy, while limiting sugary snacks protects against cavities.\n\nBut even the best home care needs a professional touch. Regular check-ups and cleanings are crucial for catching issues early and maintaining optimal oral health. Let\'s partner up to keep your smile sparkling and your confidence soaring! Ready to glow? Schedule your next visit today!', '2025-11-22 19:34:57', 'published', '2025-11-22 19:34:57');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B004', 'Dental Health Tip of', 'Keep your smile healthy by brushing twice a day and visiting your dentist regularly!', '2025-11-23 20:38:31', 'published', '2025-11-23 20:38:31');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B005', 'Dental Health Tip of', 'Keep your smile healthy by brushing twice a day and visiting your dentist regularly!', '2025-12-26 11:19:51', 'published', '2025-12-26 11:19:51');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B006', 'Dental Health Tip of', 'Keep your smile healthy by brushing twice a day and visiting your dentist regularly!', '2026-01-03 13:40:13', 'published', '2026-01-03 13:40:13');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B007', 'Dental Health Tip of', 'Keep your smile healthy by brushing twice a day and visiting your dentist regularly!', '2026-01-08 09:00:16', 'published', '2026-01-08 09:00:16');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B008', 'Dental Health Tip of', 'Keep your smile healthy by brushing twice a day and visiting your dentist regularly!', '2026-02-03 08:52:23', 'published', '2026-02-03 08:52:23');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B009', 'Dental Health Tip of', 'Keep your smile healthy by brushing twice a day and visiting your dentist regularly!', '2026-02-14 13:36:15', 'published', '2026-02-14 13:36:15');
INSERT INTO `dental_blogs` (`blog_id`, `title`, `content`, `published_at`, `status`, `created_at`) VALUES ('B010', 'Sparkle & Shine: Qui', 'Want to keep your smile looking and feeling its best? It\'s easier than you think! A few simple daily habits can make a huge difference in your oral health and overall confidence.\n\nFirst, let\'s talk brushing. Aim for two minutes, twice a day, using a soft-bristled brush. Don\'t forget to gently brush your tongue too, to combat bad breath and improve freshness. Next up: flossing. This essential step reaches where your toothbrush can\'t, removing plaque and food particles between your teeth and under the gumline. Make it a daily habit – your gums will thank you!\n\nBeyond your routine, consider your diet. Limiting sugary snacks and drinks helps protect your teeth from decay. And finally, don\'t underestimate the power of regular dental check-ups and professional cleanings. Our friendly team is here to help keep your smile sparkling and healthy. Book your next visit today and let your confident smile shine!', '2026-02-20 09:20:12', 'published', '2026-02-20 09:20:12');

--
-- Table structure for table `dentist_schedule`
--

DROP TABLE IF EXISTS `dentist_schedule`;
CREATE TABLE `dentist_schedule` (
  `schedule_id` varchar(10) NOT NULL,
  `dentist_id` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `time_slot` enum('firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch') NOT NULL,
  `status` enum('available','blocked','booked') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  UNIQUE KEY `unique_slot` (`dentist_id`,`date`,`time_slot`),
  CONSTRAINT `dentist_schedule_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `multidisciplinary_dental_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dentist_schedule`
--

INSERT INTO `dentist_schedule` (`schedule_id`, `dentist_id`, `date`, `time_slot`, `status`, `created_at`, `updated_at`) VALUES ('DS001', 'T001', '2025-11-12', 'secondBatch', 'available', '2025-11-03 21:47:47', '2025-11-03 21:47:47');

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `feedback_text` text NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `unique_user_feedback` (`user_id`),
  KEY `fk_feedback_appointment` (`appointment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `user_id`, `patient_name`, `feedback_text`, `appointment_id`, `status`, `created_at`, `updated_at`) VALUES ('1', 'U0007', 'Von Sabado', 'Im So Happy and Satisfied', '0', 'rejected', '2025-11-22 16:58:51', '2026-02-21 12:43:47');
INSERT INTO `feedback` (`feedback_id`, `user_id`, `patient_name`, `feedback_text`, `appointment_id`, `status`, `created_at`, `updated_at`) VALUES ('2', 'U0009', 'Charmmain Rabano', 'The treatment is nice and fast.', '0', 'approved', '2025-11-24 15:13:06', '2025-11-24 15:13:06');

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_name` varchar(255) NOT NULL,
  `holiday_date` date NOT NULL,
  `recurrence` enum('once','yearly') NOT NULL DEFAULT 'once',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_holiday_date` (`holiday_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

--
-- Table structure for table `multidisciplinary_dental_team`
--

DROP TABLE IF EXISTS `multidisciplinary_dental_team`;
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`team_id`),
  KEY `fk_team_user` (`user_id`),
  CONSTRAINT `fk_team_user` FOREIGN KEY (`user_id`) REFERENCES `user_account` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `multidisciplinary_dental_team`
--

INSERT INTO `multidisciplinary_dental_team` (`team_id`, `user_id`, `first_name`, `last_name`, `specialization`, `email`, `phone`, `status`, `last_active`, `created_at`) VALUES ('T001', 'U0005', 'Michelle', 'Landero', 'Dentist', 'arisukazamoto@gmail.com', '0919299223', 'inactive', '2026-03-22 09:56:33', '2025-11-03 09:51:03');

--
-- Table structure for table `patient_bill_status`
--

DROP TABLE IF EXISTS `patient_bill_status`;
CREATE TABLE `patient_bill_status` (
  `id` varchar(10) NOT NULL,
  `patient_id` varchar(10) NOT NULL,
  `treatment_id` varchar(10) DEFAULT NULL,
  `appointment_id` varchar(10) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `updated_by` varchar(10) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `treatment_id` (`treatment_id`),
  KEY `appointment_id` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_bill_status`
--

--
-- Table structure for table `patient_information`
--

DROP TABLE IF EXISTS `patient_information`;
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`patient_id`),
  KEY `fk_patient_user` (`user_id`),
  CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `user_account` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_information`
--

INSERT INTO `patient_information` (`patient_id`, `user_id`, `first_name`, `last_name`, `birthdate`, `gender`, `phone`, `email`, `address`, `created_at`) VALUES ('P001', 'U0017', 'Vjay', 'Dela Cruz', '2008-02-20', 'Male', '09393949945', 'padillav@ojt.dap.edu.ph', 'Gladiola St. Rizal', '2026-03-21 13:09:38');
INSERT INTO `patient_information` (`patient_id`, `user_id`, `first_name`, `last_name`, `birthdate`, `gender`, `phone`, `email`, `address`, `created_at`) VALUES ('P002', 'U0001', 'Vince Henrick', 'Padilla', '2015-11-04', 'Male', '09938383851', 'kirito.nakamura7@gmail.com', 'Pinagsama St Taguig City', '2026-03-21 14:02:19');
INSERT INTO `patient_information` (`patient_id`, `user_id`, `first_name`, `last_name`, `birthdate`, `gender`, `phone`, `email`, `address`, `created_at`) VALUES ('P003', 'U0019', 'Thomas', 'Shelby', '2008-03-01', 'Male', '09949594964', 'kirito.nakamura2@gmail.com', 'Dyan lang sa tabi', '2026-03-21 14:38:43');
INSERT INTO `patient_information` (`patient_id`, `user_id`, `first_name`, `last_name`, `birthdate`, `gender`, `phone`, `email`, `address`, `created_at`) VALUES ('P004', 'U0020', 'Luis', 'Rodriguez', '2008-03-05', 'male', '09286765223', 'biscottocookiesdefemela@gmail.com', 'Doctor Jose P. Rizal Extension Taytay', '2026-03-22 08:51:35');

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `payment_id` varchar(10) NOT NULL,
  `appointment_id` varchar(10) NOT NULL,
  `method` varchar(50) NOT NULL,
  `account_name` varchar(50) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','paid','refunded','failed') DEFAULT 'pending',
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_appointment` (`appointment_id`),
  CONSTRAINT `fk_payment_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

--
-- Table structure for table `promotional_emails`
--

DROP TABLE IF EXISTS `promotional_emails`;
CREATE TABLE `promotional_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_email` (`email`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotional_emails`
--

--
-- Table structure for table `refund_requests`
--

DROP TABLE IF EXISTS `refund_requests`;
CREATE TABLE `refund_requests` (
  `id` varchar(10) NOT NULL,
  `payment_id` varchar(10) NOT NULL,
  `appointment_id` varchar(10) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `status` enum('pending','processed','refunded') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `refund_requests`
--

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `service_id` varchar(10) NOT NULL,
  `service_category` varchar(50) NOT NULL,
  `sub_service` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S001', 'General Dentistry', 'Checkups', 'Routine dental checkups involve a comprehensive examination and cleaning to prevent oral disease, while a tooth extraction is the removal of a tooth that is too damaged or infected to be saved', '0.00', '2025-11-01 11:44:24');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S002', 'Orthodontics', 'Braces', 'To provide a comprehensive overview that captures the essential ideas while leaving out the non-essential details. A good summary is always much shorter than the original.', '0.00', '2025-11-02 08:37:46');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S003', 'Oral Surgery', 'Tooth Extraction (Bunot)', 'The professional, gentle removal of a tooth that is irreparably damaged, decayed, or causing crowding and infection. We prioritize patient comfort and use local anesthesia to ensure a smooth procedure, helping to protect the overall health of your mouth.', '0.00', '2025-11-09 19:02:31');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S004', 'Endodontics', 'Root Canal Treatment', 'A procedure to save a severely damaged tooth when the pulp (nerve) inside is infected or inflamed. We carefully clean, sterilize, and seal the internal root canal system to eliminate pain, infection, and the need for extraction, preserving the natural tooth structures etc', '0.00', '2025-11-09 19:01:04');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S005', 'Prosthodontics Treatments (Pustiso)', 'Crowns', 'Dental crowns are custom-made caps placed entirely over a damaged or weakened tooth. They are used to restore the tooth\'s shape, strength, and appearance following a root canal or extensive decay, providing protection and improving function.', '0.00', '2025-11-09 19:12:36');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S1001', 'General Dentistry', 'Oral Prophylaxis (Cleaning)', 'A professional dental cleaning is a procedure typically performed by a dental hygienist or dentist to thoroughly clean your teeth and maintain optimal oral health.', '0.00', '2025-11-02 08:33:50');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S1002', 'General Dentistry', 'Fluoride Application', 'Professional Fluoride Treatment Topical application to demineralize weak enamel and significantly reduce the risk of cavities, promoting long-term oral health for all ages.', '0.00', '2025-11-09 18:56:37');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S1003', 'General Dentistry', 'Pit & Fissure Sealants', 'A fast, painless, protective barrier applied to the chewing surfaces of back teeth (molars). This thin, tooth-colored coating instantly seals the deep grooves to block out food, plaque, and bacteria, effectively preventing over 80% of cavities in the sealed areas', '0.00', '2025-11-09 18:58:07');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S1004', 'General Dentistry', 'Tooth Restoration (Pasta)', 'A procedure to repair teeth damaged by decay, fractures, or cracks. We gently remove the damaged material and restore the tooth\'s shape, function, and appearance using durable, tooth-colored composite resin (or other chosen materials). This prevents further decay and eliminates sensitivity.', '0.00', '2025-11-09 18:59:55');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S2001', 'Orthodontics', 'Retainers', 'Custom-made dental appliances used after orthodontic treatment (like braces or aligners). Retainers are essential to stabilize and maintain the new position of your teeth, preventing them from shifting back and ensuring your beautifully straight smile lasts a lifetime.', '0.00', '2025-11-09 19:08:05');
INSERT INTO `services` (`service_id`, `service_category`, `sub_service`, `description`, `price`, `created_at`) VALUES ('S5001', 'Prosthodontics Treatments (Pustiso)', 'Dentures', 'Removable appliances that replace missing teeth and surrounding tissues. We provide full (complete) and partial dentures that are custom designed to restore your ability to chew, speak clearly, and improve your smile and facial contours.', '0.00', '2025-11-09 19:13:27');

--
-- Table structure for table `site_content`
--

DROP TABLE IF EXISTS `site_content`;
CREATE TABLE `site_content` (
  `content_id` int(11) NOT NULL AUTO_INCREMENT,
  `content_key` varchar(100) NOT NULL,
  `content_value` text DEFAULT NULL,
  `content_type` varchar(50) DEFAULT 'text',
  `section` varchar(50) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`content_id`),
  UNIQUE KEY `content_key` (`content_key`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_content`
--

INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('1', 'hero_title', 'Your Smile Deserves the Best Treatment', 'text', 'hero', '2026-02-21 12:53:34', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('2', 'hero_subtitle', 'Professional dental care in a comfortable and friendly environment', 'text', 'hero', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('3', 'services_title', 'Our Services', 'text', 'services', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('4', 'services_subtitle', 'Comprehensive dental care for the whole family', 'text', 'services', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('5', 'contact_title', 'Contact Us', 'text', 'contact', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('6', 'contact_subtitle', 'Send us a message about appointments, services, or any other concerns about us.', 'text', 'contact', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('7', 'contact_help_title', 'We\'re here to help', 'text', 'contact', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('8', 'contact_help_text', 'Call us, send an email, or use the form to send your questions and we \'ll get back to you as soon as possible.', 'text', 'contact', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('9', 'contact_hours', 'Mon - Sun: 8:00 AM - 8:00 PM', 'text', 'contact', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('10', 'contact_phone', '0922 861 1987', 'text', 'contact', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('11', 'contact_email', 'landerodentalclinic@gmail.com', 'text', 'contact', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('12', 'location_title', 'Visit Our Clinics', 'text', 'location', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('13', 'location_subtitle', 'Find us in Comembo, Taguig City or Taytay, Rizal. Use the map and contact details below for easy navigation.', 'text', 'location', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('14', 'location_comembo', 'Anahaw St. Comembo, Taguig City', 'text', 'location', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('15', 'location_taytay', 'Lot 2 Block 5, Turquoise Corner, Golden City Subd, Amber, Dolores, Taytay, 1920 Rizal', 'text', 'location', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('16', 'dentist_title', 'Our Dentist', 'text', 'dentist', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('17', 'dentist_subtitle', 'Meet Our Professional Dentist', 'text', 'dentist', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('18', 'dentist_name', 'Dr. Michelle Landero', 'text', 'dentist', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('19', 'dentist_specialty', 'Dentist', 'text', 'dentist', '2026-02-21 12:51:31', '2026-02-21 12:38:44');
INSERT INTO `site_content` (`content_id`, `content_key`, `content_value`, `content_type`, `section`, `updated_at`, `created_at`) VALUES ('20', 'dentist_experience', 'With over 10 years of experience in providing exceptional dental care.', 'text', 'dentist', '2026-02-21 12:51:31', '2026-02-21 12:38:44');

--
-- Table structure for table `system_alerts`
--

DROP TABLE IF EXISTS `system_alerts`;
CREATE TABLE `system_alerts` (
  `alert_id` varchar(10) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `role` enum('dentist','patient','admin') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_appointment_id` varchar(10) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`alert_id`),
  KEY `user_id` (`user_id`),
  KEY `related_appointment_id` (`related_appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_alerts`
--

INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL001', 'U0005', 'admin', 'Pending Appointment - Action Required', 'There is a pending appointment that requires your attention:\n\nPatient: Juan Dela Cruz\nService: Braces\nDentist: Dr. Michelle Landero\nDate: 2026-01-29\nTime: 8:00AM-9:00AM\n\nPlease review and confirm or cancel this appointment.', 'A009', '0', '2026-02-17 08:42:24');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL002', 'U0005', 'admin', 'Pending Appointment - Action Required', 'There is a pending appointment that requires your attention:\n\nPatient: Mark Laungayan\nService: Braces\nDentist: Dr. Michelle Landero\nDate: 2026-02-17\nTime: 8:00AM-9:00AM\n\nPlease review and confirm or cancel this appointment.', 'A011', '0', '2026-02-17 08:42:24');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL003', 'U0005', 'dentist', 'Inactivity Alert - Appointment with Vince Henrick Padilla', 'You have a confirmed appointment scheduled:\n\nPatient: Vince Henrick Padilla\nDate: 2026-02-21\nTime: 7:00PM-8:00PM\n\nPlease update your account status to active or contact the administrator.', 'A010', '0', '2026-02-17 08:52:13');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL004', 'U0001', 'patient', 'Appointment Alert - Dentist Inactive', 'Your confirmed appointment may be affected:\n\nDentist: Dr. Michelle Landero\nDate: 2026-02-21\nTime: 7:00PM-8:00PM\n\nThe assigned dentist is currently inactive. Please contact the clinic for assistance.', 'A010', '0', '2026-02-17 08:52:13');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL005', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Mark Laungayan on February 18, 2026 at 3:00PM-4:00PM for Braces.', 'A011', '0', '2026-02-17 09:14:55');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL006', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Vince Henrick Padilla on February 21, 2026 at 7:00PM-8:00PM for Oral Prophylaxis (Cleaning).', 'A010', '0', '2026-02-17 09:14:55');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL007', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Jane Cruz on February 27, 2026 at 5:00PM-6:00PM for Braces.', 'A008', '0', '2026-02-17 09:14:55');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL008', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Mark Laungayan on February 18, 2026 at 3:00PM-4:00PM for Braces.', 'A011', '0', '2026-02-17 09:16:01');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL009', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Vince Henrick Padilla on February 21, 2026 at 7:00PM-8:00PM for Oral Prophylaxis (Cleaning).', 'A010', '0', '2026-02-17 09:16:01');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL010', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Jane Cruz on February 27, 2026 at 5:00PM-6:00PM for Braces.', 'A008', '0', '2026-02-17 09:16:01');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL011', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Mark Laungayan on February 17, 2026 at 9:00AM-10:00AM for Braces.', 'A011', '0', '2026-02-17 09:22:44');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL012', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Vince Henrick Padilla on February 21, 2026 at 7:00PM-8:00PM for Oral Prophylaxis (Cleaning).', 'A010', '0', '2026-02-17 09:22:44');
INSERT INTO `system_alerts` (`alert_id`, `user_id`, `role`, `title`, `message`, `related_appointment_id`, `is_read`, `created_at`) VALUES ('AL013', 'U0005', 'admin', 'Dentist Logged Out - Appointment Alert', 'Dr. Michelle Landero has logged out. They have an appointment with Jane Cruz on February 27, 2026 at 5:00PM-6:00PM for Braces.', 'A008', '0', '2026-02-17 09:22:44');

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'text',
  `section` varchar(50) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('1', 'appointment_slot_duration', '30', 'text', 'appointment', '2026-03-07 12:57:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('2', 'max_appointments_per_day', '5', 'text', 'appointment', '2026-03-07 12:57:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('3', 'advance_booking_limit', '2', 'text', 'appointment', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('4', 'walk_ins_enabled', '0', 'text', 'appointment', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('5', 'gcash_enabled', '1', 'text', 'payment', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('6', 'maya_enabled', '1', 'text', 'payment', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('7', 'reservation_fee_amount', '400', 'text', 'payment', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('8', 'appointment_confirmation_email', '1', 'text', 'email', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('9', 'appointment_reminder_notifications', '1', 'text', 'email', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('10', 'promotional_campaign_emails', '0', 'text', 'email', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('11', 'default_user_role', 'patient', 'text', 'security', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('12', 'account_verification', 'email', 'text', 'security', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('13', 'max_login_attempts', '5', 'text', 'security', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('14', 'session_timeout', '3600', 'text', 'security', '2026-03-22 13:20:28', '2026-03-07 12:52:48');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `section`, `updated_at`, `created_at`) VALUES ('15', 'maintenance_mode', '0', 'text', 'maintenance', '2026-03-22 13:20:28', '2026-03-07 12:52:48');

--
-- Table structure for table `treatment_history`
--

DROP TABLE IF EXISTS `treatment_history`;
CREATE TABLE `treatment_history` (
  `treatment_id` varchar(10) NOT NULL,
  `patient_id` varchar(10) NOT NULL,
  `treatment` varchar(50) NOT NULL,
  `prescription_given` varchar(50) NOT NULL,
  `treatment_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`treatment_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `treatment_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_history`
--

--
-- Table structure for table `user_account`
--

DROP TABLE IF EXISTS `user_account`;
CREATE TABLE `user_account` (
  `user_id` varchar(10) NOT NULL,
  `role` enum('patient','dentist','admin','super-admin') NOT NULL,
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_account`
--

INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0001', 'patient', 'active', '2026-03-22 13:01:04', 'vince', 'Vince Henrick', 'Padilla', '2015-11-04', 'Male', 'Lawin St Taguig City', '$2y$10$0iOkoTCVPQas8LMIlMJxR.Qn3Ct5szu0sFofMZO.BlcWyc4oB0XXm', 'kirito.nakamura7@gmail.com', '09938383851', 'verified', '2025-11-01 10:24:21');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0003', 'patient', 'blocked', NULL, 'naruto12', 'Naruto', 'Uzumaki', '2015-11-15', 'Male', 'Pinagsama Taguig City', '$2y$10$3z4B7P1ZA1l8rbearzvOCu2tqa9oGTCIqi7gv/BDgM7JErvjb0F46', 'kirito.nakamura3@gmail.com', '09286765223', 'verified', '2025-11-01 13:28:23');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0004', 'patient', 'active', NULL, 'ashley', 'Ashley', 'Gonzales', '2016-11-30', 'Male', 'Anahaw St Comembo Taguig City', '$2y$10$cKZ21NJJca/NNuyaUl.Q5eFTHSJ9TUafKK.4SRBesVOIAlBjaS6Ye', 'lafox77022@dwakm.com', '09949495656', 'verified', '2025-11-03 08:29:29');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0005', 'admin', 'active', '2026-03-22 09:30:08', 'admin', 'Michelle Landero', 'Landero', '2018-11-12', 'Male', 'Kyoto Japan', '$2y$10$VkO.yPV1Xi/.7FQgWjgYHuI2Gckbjp/jTBdmmXJXafHpKrI6e7que', 'arisukazamoto@gmail.com', '0919299223', 'verified', '2025-11-03 08:48:04');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0006', 'patient', 'active', '2026-01-21 12:08:16', 'kenneth', 'Kenneth', 'Jana', '2005-07-06', 'male', 'Anahaw St, Comembo. Taguig City', '$2y$10$Prd1QuepoUXja3./fNpPNu92.cwqynUThplFLOfNL83suy8C9tB6e', 'bodagi7557@limtu.com', '09988976545', 'verified', '2025-11-08 08:31:58');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0007', 'patient', 'active', '2025-11-23 20:56:21', 'von', 'Von', 'Sabado', '2007-11-07', 'male', 'Lawin St Taguig City', '$2y$10$VZzSR9BkzQMglf0IZ1N/3OixRpwsBns2tk04GAC/PD4A3ctqAAKli', 'vonjeresespi1@gmail.com', '09287977979', 'verified', '2025-11-13 12:57:23');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0008', 'patient', 'active', '2025-12-02 10:23:10', 'charles', 'Charles', 'Ramos', '2005-11-23', 'male', 'Amarillo St Taguig City', '$2y$10$xksl0yu97OmyBJHm.WjWFOxBTxgLB07fso.n62oVWeOsnu7axudSG', 'yeyof71832@gyknife.com', '92867657245', 'verified', '2025-11-16 14:53:58');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0009', 'patient', 'active', '2025-12-02 10:23:57', 'cha', 'Charmmain', 'Rabano', '2007-07-02', 'male', 'Miyapis St', '$2y$10$.DT72DajqWL2e2Spzx8xXeqzoO8zL6xkQUJi1C0JKVxcDiv0oZlvy', 'winoc52801@fermiro.com', '09286765072', 'verified', '2025-11-16 21:41:02');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0010', 'patient', 'active', '2025-12-02 10:35:21', 'charlesramos', 'Charles', 'Ramos', '2004-11-18', 'male', 'Anahaw St, Brgy Comembo, Taguig City, 1284', '$2y$10$e509QovcsisdG5VCdGf8Be5vA/5EypvE/ROI431QQ8ijffK1w553C', 'flowprince4@gmail.com', '02934023432', 'verified', '2025-11-20 16:26:14');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0011', 'patient', 'active', '2026-01-21 12:09:49', 'juan', 'Juan', 'Dela Cruz', '2004-06-15', 'male', 'Sta Cruz, Taytay Rizal', '$2y$10$Kh8iodJoGZq6oKTxza0r7e.aO/7Uh5GPghTavRDg9pGo1hxx5cuKO', 'mlanderodentalclinic@gmail.com', '09286765204', 'verified', '2025-11-22 20:07:14');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0012', 'patient', 'active', '2025-12-19 12:46:48', 'arzen', 'Arzen', 'Navor', '2007-12-12', 'male', 'Sta Cruz, Taytay Rizal', '$2y$10$ZGEE4wu90rNGVxahLR0OqedBvHyIjSHzDEMeSOFbuFUk9eUJUq1Bm', 'arzennavor@gmail.com', '09778776562', 'verified', '2025-12-19 12:46:36');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0013', 'patient', 'active', '2026-01-03 14:20:58', 'mike', 'Mike', 'Wheeler', '1994-07-25', 'male', '112 Lincoln Ave Hawkings Sub', '$2y$10$.dnP97oxvUQiEpS9o5RVpuh6WFn7v0QJhYbnY.QNKjupKKBD8oYU.', 'yaweti1928@hudisk.com', '09528676520', 'verified', '2026-01-03 12:28:21');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0014', 'patient', 'active', '2026-01-21 09:03:27', 'jane', 'Jane', 'Cruz', '2008-01-10', 'male', 'Bldg 6 Doctor Jose P. Rizal Extension', '$2y$10$raUshW7ZtJsXD0zn101BiOqq1T0kbxPaeQGbKZKqvY5mca/8FMsga', 'mikewheelerpogi@gmail.com', '09556765130', 'verified', '2026-01-21 09:03:14');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0015', 'patient', 'active', '2026-02-21 12:07:07', 'marklaungayan', 'Mark', 'Laungayan', '2008-02-13', 'male', 'Lawin St Taguig City', '$2y$10$OIf7eCa84NZsZZBQMYta0ewAGSK7BMhay00u/n3or3dcnxtn1osFe', 'uzumakinaruto6012@gmail.com', '09887979656', 'verified', '2026-02-17 08:31:18');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0016', 'patient', 'active', '2026-02-21 21:35:38', 'elone', 'Elone', 'Musk', '2008-02-20', 'male', 'Bulacan', '$2y$10$YGN2vmQOQkhgf8K2VQxFBOwBvtNvQdJae4NMeg7wncUUOiIG4uiXa', 'hexeje5409@bitonc.com', '09228611945', 'verified', '2026-02-21 20:43:25');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0017', 'patient', 'active', '2026-03-21 19:41:19', 'vjay', 'Vjay', 'Dela Cruz', '2008-02-20', 'male', 'Dito lang sa gedi', '$2y$10$KGgKA7K21fNrTli1pB38uO1b2L.H4Jg7ox0xgnccerw1uwZ5cp6kC', 'padillav@ojt.dap.edu.ph', '09393949945', 'verified', '2026-02-28 09:20:41');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0018', 'super-admin', 'active', '2026-03-22 12:58:53', 'superadmin', 'Super', 'Admin', '2008-03-01', 'male', 'super-admin', '$2y$10$ugouCNgpcnxMXde422QOSuxdMcbisInx0qoUxkWEIUaDbmPIsqqbW', 'thomasshellby1204@gmail.com', '09949594964', 'verified', '2026-03-02 20:23:35');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0019', 'patient', 'active', '2026-03-21 14:32:44', 'thomas', 'Thomas', 'Shelby', '2008-03-01', 'male', 'Dyan lang sa tabi', '$2y$10$EoeKf.XDnN0O/9VCrfs4D.UEXnuUKltDQKZjO1FyEKMOD/nHn5FGe', 'kirito.nakamura2@gmail.com', '09949594964', 'verified', '2026-03-06 20:16:54');
INSERT INTO `user_account` (`user_id`, `role`, `status`, `last_login`, `username`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `password_hash`, `email`, `phone`, `contactNumber_verify`, `created_at`) VALUES ('U0020', 'patient', 'active', '2026-03-22 10:07:34', 'luis', 'Luis', 'Rodriguez', '2008-03-05', 'male', 'Doctor Jose P. Rizal Extension Taytay', '$2y$10$swIlNtbUNkv5d76kRxeBgOh0HlaXdxoPPXSybOoqQ1FQEzS/DHI32', 'biscottocookiesdefemela@gmail.com', '09286765223', 'verified', '2026-03-22 08:50:18');

--
-- Table structure for table `walkin_appointments`
--

DROP TABLE IF EXISTS `walkin_appointments`;
CREATE TABLE `walkin_appointments` (
  `walkin_id` varchar(20) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `service` varchar(100) NOT NULL,
  `sub_service` varchar(100) NOT NULL,
  `dentist_name` varchar(100) NOT NULL DEFAULT 'Dr. Michelle Landero',
  `branch` varchar(100) NOT NULL,
  `status` varchar(50) DEFAULT 'Walk-in',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`walkin_id`),
  KEY `fk_walkin_patient` (`patient_id`),
  CONSTRAINT `fk_walkin_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_information` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `walkin_appointments`
--

COMMIT;
