-- Migration: add ticket fields for cash appointment flow
ALTER TABLE `appointments`
  ADD COLUMN `ticket_code` VARCHAR(32) NULL AFTER `status`,
  ADD COLUMN `ticket_expires_at` DATETIME NULL AFTER `ticket_code`,
  ADD COLUMN `ticket_status` ENUM('issued','used','expired') NOT NULL DEFAULT 'issued' AFTER `ticket_expires_at`,
  ADD COLUMN `arrival_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ticket_status`;

-- Index for quick lookup by ticket
CREATE INDEX idx_appointments_ticket_code ON `appointments` (`ticket_code`);
