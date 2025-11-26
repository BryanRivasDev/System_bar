-- Migration: Add Super Admin support
-- Run this SQL to add the is_super_admin column to the users table

USE bar_system;

-- Add is_super_admin column to users table
ALTER TABLE users ADD COLUMN is_super_admin TINYINT(1) DEFAULT 0 AFTER role_id;

-- Mark the first admin user (ID 1) as Super Admin
UPDATE users SET is_super_admin = 1 WHERE id = 1 AND role_id = 1;

-- Verify the change
SELECT id, name, username, role_id, is_super_admin FROM users WHERE role_id = 1;
