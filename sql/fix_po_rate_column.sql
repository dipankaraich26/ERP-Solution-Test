-- Fix for PO value showing 0
-- This script adds the rate column if missing and sets default values

-- Add rate column if it doesn't exist
ALTER TABLE `purchase_orders`
ADD COLUMN IF NOT EXISTS `rate` DECIMAL(15,2) DEFAULT 0.00 AFTER `qty`;

-- For existing records where rate is NULL or 0,
-- you may need to manually update them with actual rates
-- Example update query (uncomment and modify as needed):
-- UPDATE purchase_orders SET rate = 100.00 WHERE rate IS NULL OR rate = 0;

-- Check current PO values
SELECT
    po_no,
    part_no,
    qty,
    rate,
    (qty * rate) as line_total,
    purchase_date
FROM purchase_orders
ORDER BY purchase_date DESC
LIMIT 10;
