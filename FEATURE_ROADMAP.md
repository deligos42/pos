# POS System - High-Value Features Implementation Roadmap

## 📋 Executive Summary

This document outlines the implementation plan for 10 advanced POS features that will transform the system from basic transaction processing to a fully-featured retail management solution.

**Total Estimated Effort:** 40-60 hours of development  
**Database Tables to Add:** 8 new tables  
**Files to Create/Modify:** 35-40 files

---

## 🎯 Priority & Phasing

### Phase 1: Foundation (Critical) - Week 1
Essential infrastructure that enables other features.

- **Audit Logging System** ⭐⭐⭐
  - Tracks all critical changes
  - Required for compliance and accountability
  - Foundation for refunds, permission changes

- **Role-Based Permissions** ⭐⭐⭐
  - Extend beyond admin/cashier
  - Add: Manager, Inventory Clerk, Accountant
  - Secures advanced features

### Phase 2: Revenue Cycle (High Impact) - Week 2-3
Core business operations.

- **Sales Returns/Refunds** ⭐⭐⭐
  - Automatic stock restoration
  - Audit-tracked with approvals
  - ~8-10 hours

- **Receipt Reprint & Email** ⭐⭐⭐
  - Search historical sales
  - Reprint/email to customer
  - ~5-7 hours

- **Cashier Closing Reports** ⭐⭐
  - Daily reconciliation
  - Cash expected vs. counted
  - Variance analysis
  - ~6-8 hours

### Phase 3: Operational Intelligence (High Value) - Week 3-4
Better visibility and decision-making.

- **Audit Log Viewer** ⭐⭐⭐
  - Dashboard showing all changes
  - Filter by user, date, action
  - Required for phase 1

- **Dashboard Charts** ⭐⭐
  - Daily sales trend
  - Top 10 products
  - Profit margin analysis
  - ~7-10 hours

- **Low-Stock Alerts & PO System** ⭐⭐
  - Automatic alerts
  - Purchase order generation
  - Supplier tracking
  - ~8-10 hours

### Phase 4: Financial Management (Medium Impact) - Week 4-5
Payroll and commissions.

- **Commission Tracking** ⭐⭐
  - Per-user sales commissions
  - Approval workflow
  - Payslip generation
  - ~8-10 hours

### Phase 5: Data Management (Essential) - Week 5
Backup and recovery.

- **Backup/Export/Restore Tools** ⭐⭐
  - Full database backup
  - CSV export (sales, inventory)
  - One-click restore
  - ~5-7 hours

### Phase 6: Communication (Nice to Have) - Week 5-6
External notifications.

- **SMS/WhatsApp Notifications** ⭐
  - Verification codes
  - Low-stock alerts
  - Sales confirmations
  - Integration: Twilio or AWS SNS
  - ~6-8 hours

---

## 📊 Database Schema Changes

### New Tables Required

```sql
-- 1. audit_log
CREATE TABLE audit_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  action VARCHAR(50),           -- 'sale', 'refund', 'price_edit', 'stock_adjust', 'user_change'
  entity_type VARCHAR(50),      -- 'sales', 'products', 'users', 'customers'
  entity_id INT,
  old_value JSON,
  new_value JSON,
  change_reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 2. roles (extend current role enum)
CREATE TABLE roles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) UNIQUE,       -- 'admin', 'manager', 'cashier', 'inventory_clerk', 'accountant'
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. permissions
CREATE TABLE permissions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE,      -- 'view_audit_log', 'approve_refunds', etc.
  description VARCHAR(255)
);

-- 4. role_permissions (junction)
CREATE TABLE role_permissions (
  role_id INT,
  permission_id INT,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

-- 5. refunds
CREATE TABLE refunds (
  id INT PRIMARY KEY AUTO_INCREMENT,
  original_sale_id INT NOT NULL,
  user_id INT NOT NULL,
  reason VARCHAR(255),
  refund_amount DECIMAL(10,2),
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  approved_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (original_sale_id) REFERENCES sales(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- 6. purchase_orders
CREATE TABLE purchase_orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  po_number VARCHAR(50) UNIQUE,
  supplier_id INT,
  product_id INT,
  quantity INT,
  unit_cost DECIMAL(10,2),
  status ENUM('pending', 'ordered', 'received', 'cancelled') DEFAULT 'pending',
  created_at TIMESTAMP,
  received_at TIMESTAMP NULL,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 7. cashier_closing_reports
CREATE TABLE cashier_closing_reports (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cashier_id INT NOT NULL,
  report_date DATE,
  opening_balance DECIMAL(10,2),
  expected_cash DECIMAL(10,2),     -- Sum of cash sales
  counted_cash DECIMAL(10,2),
  variance DECIMAL(10,2),
  status ENUM('draft', 'submitted', 'approved') DEFAULT 'draft',
  approved_by INT,
  notes TEXT,
  created_at TIMESTAMP,
  FOREIGN KEY (cashier_id) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- 8. commissions
CREATE TABLE commissions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cashier_id INT NOT NULL,
  sale_id INT,
  commission_percent DECIMAL(5,2),
  commission_amount DECIMAL(10,2),
  status ENUM('earned', 'pending_approval', 'approved', 'paid') DEFAULT 'earned',
  approved_by INT,
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  FOREIGN KEY (cashier_id) REFERENCES users(id),
  FOREIGN KEY (sale_id) REFERENCES sales(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- 9. payslips
CREATE TABLE payslips (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cashier_id INT NOT NULL,
  period_start DATE,
  period_end DATE,
  total_sales DECIMAL(10,2),
  total_commission DECIMAL(10,2),
  bonus DECIMAL(10,2) DEFAULT 0,
  deductions DECIMAL(10,2) DEFAULT 0,
  net_pay DECIMAL(10,2),
  status ENUM('draft', 'generated', 'approved', 'paid') DEFAULT 'draft',
  approved_by INT,
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  FOREIGN KEY (cashier_id) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);
```

### Schema Modifications

```sql
-- Add to users table
ALTER TABLE users ADD COLUMN role_id INT AFTER role;
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT 1;
ALTER TABLE users ADD COLUMN phone_number VARCHAR(20);

-- Add to sales table (for email tracking)
ALTER TABLE sales ADD COLUMN receipt_emailed_at TIMESTAMP NULL;
ALTER TABLE sales ADD COLUMN receipt_email_count INT DEFAULT 0;

-- Add to products table
ALTER TABLE products ADD COLUMN supplier_id INT;
ALTER TABLE products ADD COLUMN reorder_quantity INT DEFAULT 10;
ALTER TABLE products ADD COLUMN last_restock_date TIMESTAMP NULL;
```

---

## 🔄 Feature Dependency Graph

```
Foundation:
├── Audit Logging (independent)
└── Roles/Permissions (independent)

Built on Foundation:
├── Refunds (uses Audit Logging)
├── Receipt Reprint (uses Audit Logging)
└── Permission checks on all features

Revenue:
├── Cashier Closing Reports (uses Audit Logging)
└── Commission Tracking (uses Sales, Audit Logging)

Operations:
├── Dashboard Charts (independent, reads sales)
├── Low-Stock Alerts (independent, uses Audit Logging for PO)
└── Audit Log Viewer (uses Audit Logging table)

Data Management:
├── Backup Tools (independent)
└── Export Tools (independent)

Communication:
└── SMS/WhatsApp (independent)
```

---

## 📁 Files to Create/Modify

### New Pages/Features

**Phase 1:**
- `admin/permissions.php` - Manage roles & permissions
- `admin/audit_log.php` - View audit log

**Phase 2:**
- `admin/refunds.php` - Process refunds
- `admin/receipt_reprint.php` - Search & reprint receipts
- `admin/cashier_closing.php` - Daily closing reports

**Phase 3:**
- `admin/dashboard.php` - Enhanced with charts
- `admin/purchase_orders.php` - PO management
- `admin/suppliers.php` - Supplier master data

**Phase 4:**
- `admin/commissions.php` - Commission tracking
- `admin/payslips.php` - Payslip generation & approval

**Phase 5:**
- `admin/backup.php` - Backup/restore tools
- `admin/data_export.php` - Export to CSV

**Phase 6:**
- `config/notifications.php` - SMS/WhatsApp config
- `admin/notification_settings.php` - Admin panel

### Core Files to Modify

- `config/db.php` - Migration runner
- `includes/functions.php` - Add helpers for all features
- `includes/auth.php` - Add permission checking
- `config/bootstrap.php` - New file for initialization

---

## 🚀 Implementation Order (Recommended)

1. **Update Database Schema** - Add all tables + fields
2. **Audit Logging Helper Functions** - Write once, use everywhere
3. **Role-Based Permissions System** - Secure the app
4. **Permission Checking Middleware** - Wrap sensitive features
5. **Sales Returns/Refunds** - High-impact business process
6. **Receipt Reprint/Email** - Quick win, high customer value
7. **Cashier Closing Reports** - Daily reconciliation
8. **Dashboard Charts** - Visibility & analytics
9. **Audit Log Viewer** - Transparency & compliance
10. **Commission Tracking** - Payroll integration
11. **Low-Stock Alerts & POs** - Inventory optimization
12. **Backup/Export Tools** - Disaster recovery
13. **SMS Notifications** - Communication channel

---

## 💡 Implementation Notes

### Architecture Decisions

1. **Audit Logging Strategy**
   - Central `audit_log()` function in `includes/functions.php`
   - Every critical action calls: `audit_log(user_id, 'action', 'entity_type', entity_id, old_val, new_val)`
   - Automatic timestamps and user tracking

2. **Permission Model**
   - Database-driven (not hardcoded)
   - Role-based access control (RBAC)
   - Helper function: `user_can('action_name')`
   - Middleware on sensitive pages: `require_permission('view_audit_log')`

3. **Refund Strategy**
   - Refunds are a separate entity (not sale deletion)
   - Links back to original sale
   - Automatic stock restoration
   - Requires approval if amount > threshold

4. **Receipt Email**
   - Uses existing mail.php configuration
   - Queue system (optional) for bulk sends
   - Tracks email send count & date

5. **Charts & Reporting**
   - Use Chart.js or Chart.js library
   - Real-time data from sales table
   - Cache results for performance

---

## 🔐 Security Considerations

- ✅ Audit every write operation
- ✅ Permission check before sensitive actions
- ✅ Secure backup encryption
- ✅ Email verification before sending receipts
- ✅ Approval workflows for refunds > threshold
- ✅ Rate limiting on SMS sends
- ✅ Sensitive data masked in audit logs (passwords, etc.)

---

## 📈 Success Metrics

After implementation:
- ✅ Compliance audit trail for all transactions
- ✅ Role-based security with granular permissions
- ✅ <1% cash discrepancies after closing report
- ✅ <5% stock variance with PO system
- ✅ 90% of refunds processed within 1 hour
- ✅ Full data backup on-demand
- ✅ Dashboard provides actionable insights

---

**Next Step:** Review this plan, adjust priorities, and I'll begin Phase 1 implementation.

Should I proceed with building the database schema and foundation first? 🚀
