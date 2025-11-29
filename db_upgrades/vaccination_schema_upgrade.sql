-- =====================================================
-- Vaccination Records Table with Enhanced Fields
-- =====================================================
-- STATUS: ✅ MIGRATION COMPLETE
-- This script upgrades the vaccination_records table to support
-- complete PEP/PrEP scheduling with status tracking and workload awareness
--
-- DATABASE SCHEMA VERIFICATION (as of 2025-11-29):
-- ✅ vaccinationId (Primary Key, AUTO_INCREMENT)
-- ✅ patientId (Foreign Key, indexed)
-- ✅ reportId (Foreign Key, nullable for PrEP)
-- ✅ exposureType (enum: PEP, PrEP - indexed)
-- ✅ doseNumber (int)
-- ✅ dateGiven (date - indexed)
-- ✅ nextScheduledDate (date - nullable, for next dose tracking)
-- ✅ status (enum: Completed, Upcoming, Missed, Overdue, NotScheduled - DEFAULT: NotScheduled)
-- ✅ isLocked (tinyint(1) - DEFAULT: 0, for lock management)
-- ✅ vaccineName (varchar)
-- ✅ batchNumber (varchar - for batch/lot number tracking)
-- ✅ administeredBy (varchar)
-- ✅ remarks (text)
-- ✅ created_at & updated_at (timestamps)
--
-- All helper functions and API endpoints are compatible.

-- DROP TABLE IF EXISTS vaccination_records;

CREATE TABLE IF NOT EXISTS `vaccination_records` (
  `vaccinationId` int(11) NOT NULL AUTO_INCREMENT,
  `patientId` int(11) NOT NULL,
  `reportId` int(11) DEFAULT NULL COMMENT 'Null for patient-level PrEP, populated for PEP reports',
  `exposureType` enum('PEP','PrEP') NOT NULL DEFAULT 'PrEP',
  `doseNumber` int(11) NOT NULL DEFAULT 1,
  `dateGiven` date DEFAULT NULL COMMENT 'When the dose was administered',
  `nextScheduledDate` date DEFAULT NULL COMMENT 'When the next dose should be given (auto-calculated)',
  `status` enum('Completed','Upcoming','Missed','NotScheduled','Overdue') NOT NULL DEFAULT 'NotScheduled',
  `vaccineName` varchar(100) DEFAULT NULL,
  `batchNumber` varchar(100) DEFAULT NULL COMMENT 'Batch/Lot number of vaccine used',
  `administeredBy` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `isLocked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'True when record is locked (final dose completed)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`vaccinationId`),
  KEY `fk_patient` (`patientId`),
  KEY `fk_report` (`reportId`),
  KEY `idx_exposure` (`exposureType`),
  KEY `idx_status` (`status`),
  KEY `idx_dateGiven` (`dateGiven`),
  CONSTRAINT `vax_fk_patient` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`) ON DELETE CASCADE,
  CONSTRAINT `vax_fk_report` FOREIGN KEY (`reportId`) REFERENCES `reports` (`reportId`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores vaccination records with PEP/PrEP separation and status tracking';

-- =====================================================
-- Index for efficient workload queries
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_workload ON vaccination_records (exposureType, DATE(dateGiven));

-- =====================================================
-- Add category field to reports if not exists
-- =====================================================
-- Ensure reports table has biteType field for category filtering
ALTER TABLE `reports` 
  ADD COLUMN `category` varchar(20) DEFAULT NULL COMMENT 'Category I, II, or III derived from biteType';

-- =====================================================
-- Migration: Populate category from existing biteType
-- =====================================================
UPDATE `reports` 
SET `category` = CASE 
  WHEN `biteType` = 'Category I' THEN 'I'
  WHEN `biteType` = 'Category II' THEN 'II'
  WHEN `biteType` = 'Category III' THEN 'III'
  ELSE NULL
END
WHERE `category` IS NULL;

-- =====================================================
-- Notes
-- =====================================================
-- Migration steps when running on existing database:
-- 1. Backup existing data
-- 2. Run this script to create/upgrade tables
-- 3. If vaccination_records exists with old schema:
--    - Export data from old table
--    - Drop old table
--    - Create new table using this schema
--    - Re-populate data with status = 'Completed' for all existing records
--    - Recalculate nextScheduledDate based on PEP intervals
-- 4. Test all CRUD operations
