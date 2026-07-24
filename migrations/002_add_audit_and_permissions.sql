-- Migration: Add Audit Logging and Role-Based Permissions
-- Created: 2026-07-24

-- 1. Create roles table
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `description` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create permissions table
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `description` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create role_permissions junction table
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create audit_log table
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11),
  `old_value` json,
  `new_value` json,
  `change_reason` varchar(255),
  `ip_address` varchar(45),
  `user_agent` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_entity_type` (`entity_type`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add columns to users table if they don't exist
ALTER TABLE `users` ADD COLUMN `role_id` int(11) AFTER `role`;
ALTER TABLE `users` ADD COLUMN `is_active` boolean DEFAULT 1 AFTER `role_id`;
ALTER TABLE `users` ADD COLUMN `phone_number` varchar(20) AFTER `is_active`;

-- 6. Modify users.role to allow NULL (will use role_id instead)
ALTER TABLE `users` MODIFY COLUMN `role` enum('admin','cashier','manager','inventory_clerk','accountant') DEFAULT 'cashier';

-- 7. Insert default roles
INSERT INTO `roles` (`name`, `description`) VALUES
('admin', 'Full system access'),
('manager', 'Access to reports, closings, and staff management'),
('cashier', 'Point of sale access only'),
('inventory_clerk', 'Inventory and purchase order management'),
('accountant', 'Financial reports and reconciliation')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- 8. Insert default permissions
INSERT INTO `permissions` (`name`, `description`) VALUES
-- Core
('dashboard.view', 'View dashboard'),
('sales.create', 'Create sales transactions'),
('sales.view', 'View sales history'),
('sales.edit', 'Edit sales'),

-- Inventory
('products.view', 'View product catalog'),
('products.create', 'Add new products'),
('products.edit', 'Edit product details'),
('products.delete', 'Delete products'),
('inventory.adjust', 'Adjust stock levels'),
('purchase_orders.manage', 'Create and manage purchase orders'),

-- Customers
('customers.view', 'View customer list'),
('customers.create', 'Add customers'),
('customers.edit', 'Edit customer details'),
('customers.delete', 'Delete customers'),

-- Users & Roles
('users.view', 'View user list'),
('users.create', 'Add users'),
('users.edit', 'Edit user details'),
('users.delete', 'Delete users'),
('roles.manage', 'Manage roles and permissions'),

-- Financial
('refunds.create', 'Create refunds'),
('refunds.approve', 'Approve refunds'),
('commissions.view', 'View commission data'),
('commissions.approve', 'Approve commissions'),
('payslips.view', 'View payslips'),
('payslips.generate', 'Generate payslips'),

-- Reports
('reports.view', 'View reports'),
('reports.export', 'Export report data'),
('audit_log.view', 'View audit log'),
('cashier_closing.view', 'View cashier closing reports'),
('cashier_closing.approve', 'Approve cashier closing reports'),

-- Receipts
('receipts.reprint', 'Reprint receipts'),
('receipts.email', 'Email receipts to customers'),

-- Admin
('settings.view', 'View system settings'),
('settings.edit', 'Edit system settings'),
('backup.create', 'Create database backups'),
('backup.restore', 'Restore from backup')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- 9. Assign permissions to admin role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'admin'
ON DUPLICATE KEY UPDATE `role_id`=`role_id`;

-- 10. Assign permissions to manager role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'manager' AND p.name IN (
  'dashboard.view', 'sales.view', 'products.view', 'customers.view',
  'users.view', 'reports.view', 'reports.export', 'audit_log.view',
  'cashier_closing.view', 'cashier_closing.approve', 'receipts.reprint'
)
ON DUPLICATE KEY UPDATE `role_id`=`role_id`;

-- 11. Assign permissions to cashier role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'cashier' AND p.name IN (
  'dashboard.view', 'sales.create', 'sales.view', 'products.view',
  'customers.view', 'customers.create', 'receipts.reprint'
)
ON DUPLICATE KEY UPDATE `role_id`=`role_id`;

-- 12. Assign permissions to inventory_clerk role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'inventory_clerk' AND p.name IN (
  'dashboard.view', 'products.view', 'products.edit', 'inventory.adjust',
  'purchase_orders.manage', 'reports.view', 'audit_log.view'
)
ON DUPLICATE KEY UPDATE `role_id`=`role_id`;

-- 13. Assign permissions to accountant role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'accountant' AND p.name IN (
  'dashboard.view', 'sales.view', 'reports.view', 'reports.export',
  'audit_log.view', 'cashier_closing.view', 'commissions.view',
  'payslips.view'
)
ON DUPLICATE KEY UPDATE `role_id`=`role_id`;

-- 14. Set existing admin users to admin role
UPDATE `users` SET `role_id` = (SELECT id FROM roles WHERE name='admin') WHERE `role` = 'admin' AND `role_id` IS NULL;

-- 15. Set existing cashier users to cashier role
UPDATE `users` SET `role_id` = (SELECT id FROM roles WHERE name='cashier') WHERE `role` = 'cashier' AND `role_id` IS NULL;
