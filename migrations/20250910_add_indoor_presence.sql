-- Migration: Add indoor presence tables and attendance method columns
-- Date: 2025-09-10

START TRANSACTION;

-- 1) BLE Beacons per Branch
CREATE TABLE IF NOT EXISTS `tbl_branch_beacons` (
  `BeaconID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `BranchID` VARCHAR(15) NOT NULL,
  `MAC` VARCHAR(64) NOT NULL COMMENT 'Uppercase BLE MAC or UUID',
  `Label` VARCHAR(100) NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`BeaconID`),
  KEY `idx_branch_beacons_branch` (`BranchID`),
  UNIQUE KEY `uniq_branch_mac` (`BranchID`,`MAC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Wi‑Fi BSSIDs per Branch
CREATE TABLE IF NOT EXISTS `tbl_branch_wifi` (
  `WifiID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `BranchID` VARCHAR(15) NOT NULL,
  `BSSID` VARCHAR(64) NOT NULL COMMENT 'Uppercase Wi‑Fi BSSID (MAC)',
  `SSID` VARCHAR(100) NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`WifiID`),
  KEY `idx_branch_wifi_branch` (`BranchID`),
  UNIQUE KEY `uniq_branch_bssid` (`BranchID`,`BSSID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Wearable devices (if not already present)
CREATE TABLE IF NOT EXISTS `tbl_wearable_devices` (
  `DeviceID` VARCHAR(64) NOT NULL,
  `EmployeeID` VARCHAR(15) NOT NULL,
  `SecretHash` VARCHAR(128) NOT NULL COMMENT 'Hex-encoded secret',
  `Status` ENUM('active','revoked') NOT NULL DEFAULT 'active',
  `LastSeenAt` DATETIME NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`DeviceID`),
  KEY `idx_wd_employee` (`EmployeeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Anti-replay wearable tokens (if not already present)
CREATE TABLE IF NOT EXISTS `tbl_wearable_tokens` (
  `TokenHash` CHAR(64) NOT NULL,
  `DeviceID` VARCHAR(64) NOT NULL,
  `IssuedAt` DATETIME NOT NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TokenHash`),
  KEY `idx_wt_device` (`DeviceID`),
  CONSTRAINT `fk_wt_device`
    FOREIGN KEY (`DeviceID`) REFERENCES `tbl_wearable_devices` (`DeviceID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Attendance method columns (added conditionally inside procedure below)

-- 6) Align child columns to parent types/collations and add FKs dynamically to avoid #3780

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_apply_indoor_presence_fks $$

CREATE PROCEDURE sp_apply_indoor_presence_fks()
BEGIN
  DECLARE parent_type VARCHAR(255);
  DECLARE parent_charset VARCHAR(64);
  DECLARE parent_collation VARCHAR(64);
  DECLARE emp_type VARCHAR(255);
  DECLARE emp_charset VARCHAR(64);
  DECLARE emp_collation VARCHAR(64);

  -- BranchID alignment for tbl_branch_beacons and tbl_branch_wifi
  SET parent_type = NULL;
  SET parent_charset = NULL;
  SET parent_collation = NULL;
  SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
    INTO parent_type, parent_charset, parent_collation
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_branches' AND COLUMN_NAME = 'BranchID';

  IF parent_type IS NOT NULL THEN
    SET @sql = CONCAT('ALTER TABLE `tbl_branch_beacons` MODIFY COLUMN `BranchID` ', parent_type, ' NOT NULL');
    IF parent_charset IS NOT NULL THEN
      SET @sql = CONCAT(@sql, ' CHARACTER SET ', parent_charset);
    END IF;
    IF parent_collation IS NOT NULL THEN
      SET @sql = CONCAT(@sql, ' COLLATE ', parent_collation);
    END IF;
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('ALTER TABLE `tbl_branch_wifi` MODIFY COLUMN `BranchID` ', parent_type, ' NOT NULL');
    IF parent_charset IS NOT NULL THEN
      SET @sql = CONCAT(@sql, ' CHARACTER SET ', parent_charset);
    END IF;
    IF parent_collation IS NOT NULL THEN
      SET @sql = CONCAT(@sql, ' COLLATE ', parent_collation);
    END IF;
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    -- Add foreign keys now that types match
    SET @sql = 'ALTER TABLE `tbl_branch_beacons` ADD CONSTRAINT `fk_branch_beacons_branch` FOREIGN KEY (`BranchID`) REFERENCES `tbl_branches` (`BranchID`) ON DELETE CASCADE ON UPDATE CASCADE';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = 'ALTER TABLE `tbl_branch_wifi` ADD CONSTRAINT `fk_branch_wifi_branch` FOREIGN KEY (`BranchID`) REFERENCES `tbl_branches` (`BranchID`) ON DELETE CASCADE ON UPDATE CASCADE';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  -- EmployeeID alignment for tbl_wearable_devices
  SET emp_type = NULL;
  SET emp_charset = NULL;
  SET emp_collation = NULL;
  SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
    INTO emp_type, emp_charset, emp_collation
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_employees' AND COLUMN_NAME = 'EmployeeID';

  IF emp_type IS NOT NULL THEN
    SET @sql = CONCAT('ALTER TABLE `tbl_wearable_devices` MODIFY COLUMN `EmployeeID` ', emp_type, ' NOT NULL');
    IF emp_charset IS NOT NULL THEN
      SET @sql = CONCAT(@sql, ' CHARACTER SET ', emp_charset);
    END IF;
    IF emp_collation IS NOT NULL THEN
      SET @sql = CONCAT(@sql, ' COLLATE ', emp_collation);
    END IF;
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = 'ALTER TABLE `tbl_wearable_devices` ADD CONSTRAINT `fk_wd_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  -- Attendance method columns (conditionally add)
  DECLARE hasClockIn INT DEFAULT 0;
  DECLARE hasClockOut INT DEFAULT 0;
  SELECT COUNT(*) INTO hasClockIn FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_attendance' AND COLUMN_NAME = 'ClockInMethod';
  IF hasClockIn = 0 THEN
    SET @sql = 'ALTER TABLE `tbl_attendance` ADD COLUMN `ClockInMethod` VARCHAR(20) NULL AFTER `Remarks`';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
  SELECT COUNT(*) INTO hasClockOut FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_attendance' AND COLUMN_NAME = 'ClockOutMethod';
  IF hasClockOut = 0 THEN
    -- place after ClockInMethod if present, otherwise after Remarks
    IF hasClockIn = 1 THEN
      SET @sql = 'ALTER TABLE `tbl_attendance` ADD COLUMN `ClockOutMethod` VARCHAR(20) NULL AFTER `ClockInMethod`';
    ELSE
      SET @sql = 'ALTER TABLE `tbl_attendance` ADD COLUMN `ClockOutMethod` VARCHAR(20) NULL AFTER `Remarks`';
    END IF;
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END $$

CALL sp_apply_indoor_presence_fks() $$
DROP PROCEDURE IF EXISTS sp_apply_indoor_presence_fks $$

DELIMITER ;

COMMIT;
