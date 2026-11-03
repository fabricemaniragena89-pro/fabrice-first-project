CREATE DATABASE IF NOT EXISTS cooperative_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cooperative_management;

CREATE TABLE IF NOT EXISTS cooperatives (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  registration_number VARCHAR(80) NOT NULL UNIQUE,
  district VARCHAR(100) NULL,
  sector VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(160) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cooperative_id BIGINT UNSIGNED NULL,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('system_admin','cooperative_manager','staff','auditor') NOT NULL DEFAULT 'staff',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_cooperative FOREIGN KEY (cooperative_id) REFERENCES cooperatives(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cooperative_id BIGINT UNSIGNED NOT NULL,
  member_number VARCHAR(60) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  national_id VARCHAR(40) NULL,
  sex ENUM('female','male','other','unspecified') NOT NULL DEFAULT 'unspecified',
  date_of_birth DATE NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  address VARCHAR(255) NULL,
  joined_on DATE NOT NULL,
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_number_per_coop (cooperative_id, member_number),
  UNIQUE KEY uq_member_national_id_per_coop (cooperative_id, national_id),
  KEY idx_members_name (last_name, first_name),
  KEY idx_members_status (status),
  CONSTRAINT fk_members_cooperative FOREIGN KEY (cooperative_id) REFERENCES cooperatives(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Create your first administrator hash locally with:
-- php -r "echo password_hash('ChangeThisPassword!', PASSWORD_DEFAULT), PHP_EOL;"
-- Then insert the hash, not the plain-text password:
-- INSERT INTO users (full_name,email,password_hash,role) VALUES ('System Administrator','admin@example.com','PASTE_HASH_HERE','system_admin');
