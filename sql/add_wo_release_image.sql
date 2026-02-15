-- Add release_image column to work_orders for mandatory task picture on release
ALTER TABLE `work_orders`
ADD COLUMN `release_image` VARCHAR(255) DEFAULT NULL AFTER `status`;
