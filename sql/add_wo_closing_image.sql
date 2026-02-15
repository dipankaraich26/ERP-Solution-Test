-- Add closing_image column to work_orders for mandatory task picture on WO close
ALTER TABLE `work_orders`
ADD COLUMN `closing_image` VARCHAR(255) DEFAULT NULL AFTER `status`;
