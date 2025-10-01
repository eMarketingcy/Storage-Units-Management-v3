-- ============================================================
-- ALTER TABLE SQL for Payment History
-- Run this in phpMyAdmin to add missing columns
-- ============================================================

-- Database: Your WordPress database
-- Table: wpat_storage_payment_history (replace 'wpat_' with your prefix if different)

-- ============================================================
-- STEP 1: Add payment_token column
-- ============================================================
ALTER TABLE `wpat_storage_payment_history` 
ADD COLUMN `payment_token` VARCHAR(255) DEFAULT NULL 
AFTER `customer_name`;

-- ============================================================
-- STEP 2: Add status column
-- ============================================================
ALTER TABLE `wpat_storage_payment_history` 
ADD COLUMN `status` VARCHAR(20) DEFAULT 'pending' 
AFTER `payment_date`;

-- ============================================================
-- STEP 3: Make transaction_id nullable (if not already)
-- ============================================================
ALTER TABLE `wpat_storage_payment_history` 
MODIFY COLUMN `transaction_id` VARCHAR(255) DEFAULT NULL;

-- ============================================================
-- STEP 4: Make amount nullable (if not already)
-- ============================================================
ALTER TABLE `wpat_storage_payment_history` 
MODIFY COLUMN `amount` DECIMAL(10,2) DEFAULT NULL;

-- ============================================================
-- STEP 5: Make payment_date nullable (if not already)
-- ============================================================
ALTER TABLE `wpat_storage_payment_history` 
MODIFY COLUMN `payment_date` DATETIME DEFAULT NULL;

-- ============================================================
-- STEP 6: Add indexes for better performance
-- ============================================================
ALTER TABLE `wpat_storage_payment_history` 
ADD INDEX `idx_payment_token` (`payment_token`);

ALTER TABLE `wpat_storage_payment_history` 
ADD INDEX `idx_status` (`status`);

-- ============================================================
-- VERIFICATION QUERY
-- Run this to verify the changes were applied correctly
-- ============================================================
SHOW COLUMNS FROM `wpat_storage_payment_history`;

-- Expected columns:
-- id, customer_id, customer_name, payment_token, transaction_id, 
-- amount, currency, payment_months, items_paid, payment_date, 
-- status, created_at

-- ============================================================
-- TEST QUERIES
-- ============================================================

-- Check if any pending payments exist
SELECT COUNT(*) as pending_count 
FROM `wpat_storage_payment_history` 
WHERE status = 'pending';

-- Check if any completed payments exist
SELECT COUNT(*) as completed_count 
FROM `wpat_storage_payment_history` 
WHERE status = 'completed';

-- View recent records
SELECT id, customer_name, payment_token, amount, status, created_at, payment_date
FROM `wpat_storage_payment_history`
ORDER BY created_at DESC
LIMIT 10;

-- ============================================================
-- IMPORTANT NOTES:
-- ============================================================
-- 1. Replace 'wpat_' with your actual WordPress table prefix
-- 2. Backup your database before running these queries
-- 3. Run each ALTER statement one at a time in phpMyAdmin
-- 4. Check for errors after each statement
-- 5. Run the verification query at the end to confirm
-- ============================================================
