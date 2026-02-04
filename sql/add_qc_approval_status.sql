-- Add 'qc_approval' status to work_orders ENUM
-- Workflow: Open/Created → Released → In Progress → Completed → QC Approval → Closed

ALTER TABLE `work_orders`
MODIFY COLUMN `status` ENUM('open','created','released','in_progress','completed','qc_approval','closed','cancelled')
DEFAULT 'open';
