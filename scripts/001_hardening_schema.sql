-- Apply this after the base schema when upgrading an existing installation.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS phone varchar(30) DEFAULT NULL AFTER full_name,
  ADD COLUMN IF NOT EXISTS id_number varchar(50) DEFAULT NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS email varchar(100) DEFAULT NULL AFTER id_number,
  ADD COLUMN IF NOT EXISTS email_verified tinyint(1) NOT NULL DEFAULT 0 AFTER role,
  ADD COLUMN IF NOT EXISTS email_verification_code varchar(6) DEFAULT NULL AFTER email_verified,
  ADD COLUMN IF NOT EXISTS email_verification_expires_at datetime DEFAULT NULL AFTER email_verification_code,
  ADD COLUMN IF NOT EXISTS email_verification_resend_count int(11) NOT NULL DEFAULT 0 AFTER email_verification_expires_at,
  ADD COLUMN IF NOT EXISTS email_verification_last_sent_at datetime DEFAULT NULL AFTER email_verification_resend_count,
  ADD COLUMN IF NOT EXISTS profile_photo varchar(255) DEFAULT NULL AFTER email_verification_last_sent_at;

ALTER TABLE users ADD UNIQUE KEY email (email);

CREATE TABLE IF NOT EXISTS recommendation_letters (
  id int(11) NOT NULL AUTO_INCREMENT,
  ref_no varchar(50) NOT NULL,
  user_id int(11) NOT NULL,
  generated_by int(11) DEFAULT NULL,
  generated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ref_no (ref_no),
  KEY user_id (user_id),
  KEY generated_by (generated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  category varchar(100) NOT NULL,
  description varchar(255) DEFAULT NULL,
  amount decimal(10,2) NOT NULL,
  expense_date date NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY expense_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
