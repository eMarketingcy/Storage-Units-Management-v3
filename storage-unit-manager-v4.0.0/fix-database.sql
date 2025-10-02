-- Fix for BLOB/TEXT column default value error
-- Run this SQL in phpMyAdmin if you still see the error

-- Fix the storage_settings table
ALTER TABLE wpat_storage_settings MODIFY COLUMN setting_value longtext;

-- Verify the change
DESCRIBE wpat_storage_settings;
