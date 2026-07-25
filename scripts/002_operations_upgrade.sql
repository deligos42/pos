-- Operational upgrade: run once after 001_hardening_schema.sql.
-- All objects are additive; existing sales and inventory are preserved.

ALTER TABLE users
  MODIFY COLUMN role enum('admin','manager','cashier','inventory') NOT NULL DEFAULT 'cashier';

ALTER TABLE sales
  MODIFY COLUMN invoice_no varchar(32) NOT NULL;

CREATE TABLE IF NOT EXISTS sale_returns (
  id int(11) NOT NULL AUTO_INCREMENT,
  sale_id int(11) NOT NULL,
  processed_by int(11) NOT NULL,
  reason varchar(255) DEFAULT NULL,
  refund_total decimal(10,2) NOT NULL DEFAULT 0.00,
  returned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY sale_id (sale_id),
  KEY processed_by (processed_by),
  CONSTRAINT sale_returns_sale_fk FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT sale_returns_user_fk FOREIGN KEY (processed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_return_items (
  id int(11) NOT NULL AUTO_INCREMENT,
  return_id int(11) NOT NULL,
  sale_item_id int(11) NOT NULL,
  product_id int(11) NOT NULL,
  qty int(11) NOT NULL,
  refund_amount decimal(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY return_id (return_id),
  KEY sale_item_id (sale_item_id),
  KEY product_id (product_id),
  CONSTRAINT return_items_return_fk FOREIGN KEY (return_id) REFERENCES sale_returns (id) ON DELETE CASCADE,
  CONSTRAINT return_items_sale_item_fk FOREIGN KEY (sale_item_id) REFERENCES sale_items (id),
  CONSTRAINT return_items_product_fk FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(150) NOT NULL,
  contact_name varchar(100) DEFAULT NULL,
  phone varchar(30) DEFAULT NULL,
  email varchar(100) DEFAULT NULL,
  address text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY supplier_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_orders (
  id int(11) NOT NULL AUTO_INCREMENT,
  po_no varchar(32) NOT NULL,
  supplier_id int(11) DEFAULT NULL,
  created_by int(11) NOT NULL,
  status enum('draft','received','cancelled') NOT NULL DEFAULT 'draft',
  total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
  ordered_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY po_no (po_no),
  KEY supplier_id (supplier_id),
  KEY created_by (created_by),
  CONSTRAINT purchase_orders_supplier_fk FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
  CONSTRAINT purchase_orders_user_fk FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
  id int(11) NOT NULL AUTO_INCREMENT,
  purchase_order_id int(11) NOT NULL,
  product_id int(11) NOT NULL,
  qty int(11) NOT NULL,
  unit_cost decimal(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY purchase_order_id (purchase_order_id),
  KEY product_id (product_id),
  CONSTRAINT purchase_items_order_fk FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
  CONSTRAINT purchase_items_product_fk FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_closings (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  closing_date date NOT NULL,
  expected_cash decimal(10,2) NOT NULL,
  counted_cash decimal(10,2) NOT NULL,
  variance decimal(10,2) NOT NULL,
  notes varchar(255) DEFAULT NULL,
  closed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY user_closing_date (user_id, closing_date),
  CONSTRAINT cash_closings_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commission_payments (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  period_start date NOT NULL,
  period_end date NOT NULL,
  rate decimal(5,2) NOT NULL,
  sales_total decimal(10,2) NOT NULL,
  commission_amount decimal(10,2) NOT NULL,
  status enum('pending','paid') NOT NULL DEFAULT 'pending',
  paid_by int(11) DEFAULT NULL,
  paid_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY commission_period (user_id, period_start, period_end, rate),
  CONSTRAINT commission_user_fk FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT commission_paid_by_fk FOREIGN KEY (paid_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id bigint NOT NULL AUTO_INCREMENT,
  user_id int(11) DEFAULT NULL,
  action varchar(80) NOT NULL,
  entity_type varchar(80) NOT NULL,
  entity_id varchar(80) DEFAULT NULL,
  details text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY entity_lookup (entity_type, entity_id),
  CONSTRAINT audit_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_logs (
  id bigint NOT NULL AUTO_INCREMENT,
  channel enum('email','sms','whatsapp') NOT NULL,
  recipient varchar(120) NOT NULL,
  message varchar(1000) NOT NULL,
  status enum('sent','failed','skipped') NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY channel_created_at (channel, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
