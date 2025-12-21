-- Add source fields to bookings_availability table
ALTER TABLE `bookings_availability`
ADD COLUMN `sourceType` ENUM('entry', 'section') NOT NULL DEFAULT 'section' AFTER `isActive`,
ADD COLUMN `sourceId` INT(11) NULL AFTER `sourceType`,
ADD COLUMN `sourceHandle` VARCHAR(255) NULL AFTER `sourceId`;

-- Add indexes for better performance
CREATE INDEX `idx_availability_source` ON `bookings_availability` (`sourceType`, `sourceId`, `isActive`);
CREATE INDEX `idx_availability_source_handle` ON `bookings_availability` (`sourceType`, `sourceHandle`, `isActive`);

-- Add source fields to bookings_reservations table
ALTER TABLE `bookings_reservations`
ADD COLUMN `sourceType` ENUM('entry', 'section') NULL AFTER `status`,
ADD COLUMN `sourceId` INT(11) NULL AFTER `sourceType`,
ADD COLUMN `sourceHandle` VARCHAR(255) NULL AFTER `sourceId`;

-- Add indexes for better performance
CREATE INDEX `idx_reservations_source` ON `bookings_reservations` (`sourceType`, `sourceId`);
CREATE INDEX `idx_reservations_source_handle` ON `bookings_reservations` (`sourceType`, `sourceHandle`);
