-- Migration: Phase 2 - Refunds, Receipts, Cashier Closing Reports
-- Created: 2026-07-24

-- 1. Create refunds table
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_requested_by` (`requested_by`),
  FOREIGN KEY (`sale_id`) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create receipts table (snapshot of sale for reprint/email)
CREATE TABLE IF NOT EXISTS `receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `snapshot` json NOT NULL,
  `created_by` int(11),
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  FOREIGN KEY (`sale_id`) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create cashier_closing_reports table
CREATE TABLE IF NOT EXISTS `cashier_closing_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cashier_id` int(11) NOT NULL,
  `shift_start` datetime NOT NULL,
  `shift_end` datetime NOT NULL,
  `expected_total` decimal(12,2) NOT NULL,
  `counted_cash` decimal(12,2) NOT NULL,
  `discrepancy` decimal(12,2) NOT NULL,
  `status` enum('open','submitted','approved','reconciled') NOT NULL DEFAULT 'submitted',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cashier_id` (`cashier_id`),
  FOREIGN KEY (`cashier_id`) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Seed permissions for Phase 2
INSERT INTO `permissions` (`name`, `description`) VALUES
('refunds.create', 'Create refund requests'),
('refunds.approve', 'Approve refunds'),
('receipts.reprint', 'Reprint stored receipts'),
('receipts.email', 'Email receipts to customers'),
('cashier_closing.create', 'Create cashier closing reports'),
('cashier_closing.view', 'View cashier closing reports'),
('cashier_closing.approve', 'Approve cashier closing reports')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- 5. Grant refunds permissions to admin and manager
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('admin','manager') AND p.name IN ('refunds.create','refunds.approve')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- 6. Grant cashier permissions to cashier role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'cashier' AND p.name IN ('refunds.create','receipts.reprint','receipts.email','cashier_closing.create')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- 7. Grant closing report permissions to manager and accountant
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('manager','accountant') AND p.name IN ('cashier_closing.view','cashier_closing.approve')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- End of migration
