-- Nubia KC Estates Management System
-- Import this file in phpMyAdmin (Import tab) into an empty database.
-- MySQL 5.7+ / MariaDB 10.3+
-- Fresh installs: import this file only. Existing installs: run the files in
-- install/migrations/ in number order instead (each one is safe to run once).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Company details printed on statements and invoices (one row, id = 1)
CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  company_name VARCHAR(160) NOT NULL,
  address VARCHAR(255) NOT NULL,
  phone VARCHAR(80) NOT NULL,
  email VARCHAR(160) NOT NULL,
  website VARCHAR(160) NOT NULL,
  default_fee_pct DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  rent_due_day TINYINT UNSIGNED NOT NULL DEFAULT 5,
  payment_instructions VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (id, company_name, address, phone, email, website, default_fee_pct, rent_due_day, payment_instructions)
VALUES (1, 'Nubia KC Estates Ltd', 'Crescent Business Centre, Parklands, Nairobi', '+254 703 277 912',
        'info@nubiakcestates.co.ke', 'nubiakcestates.com', 8.00, 5, 'Pay by M-Pesa or bank transfer. Quote your unit number as the reference.');

-- Tenants, owners (landlords) and buyers
CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  type ENUM('Tenant','Owner','Buyer') NOT NULL DEFAULT 'Tenant',
  phone VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  id_no VARCHAR(40) NULL,
  kra_pin VARCHAR(20) NULL,
  based_in VARCHAR(120) NULL,
  bank_details VARCHAR(255) NULL,
  status ENUM('Active','Dormant') NOT NULL DEFAULT 'Active',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_type (type),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Buildings / estates
CREATE TABLE IF NOT EXISTS properties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  location VARCHAR(120) NOT NULL,
  type ENUM('Residential','Commercial','Mixed use','Land') NOT NULL DEFAULT 'Residential',
  owner_id INT UNSIGNED NULL,
  fee_pct DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prop_owner FOREIGN KEY (owner_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Units inside a property
CREATE TABLE IF NOT EXISTS units (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id INT UNSIGNED NOT NULL,
  unit_no VARCHAR(30) NOT NULL,
  category ENUM('Rent','Sale') NOT NULL DEFAULT 'Rent',
  bedrooms VARCHAR(20) NULL,
  monthly_amount DECIMAL(12,2) NOT NULL DEFAULT 0,   -- rent, or instalment for a unit sold on a payment plan
  service_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('Available','Occupied','Sold','Dormant') NOT NULL DEFAULT 'Available',
  client_id INT UNSIGNED NULL,                        -- current tenant or buyer
  start_date DATE NULL,                               -- lease start / sale date
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unit (property_id, unit_no),
  CONSTRAINT fk_unit_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_unit_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Monthly charges. One invoice per unit per month, so billing can never run twice.
CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  unit_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,                            -- YYYY-MM
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  service_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_label VARCHAR(120) NULL,
  total DECIMAL(12,2) NOT NULL,
  due_date DATE NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unit_period (unit_id, period),
  KEY idx_client (client_id),
  KEY idx_period (period),
  CONSTRAINT fk_inv_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Money received
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_on DATE NOT NULL,
  method ENUM('M-Pesa','Bank','Cash','Cheque') NOT NULL DEFAULT 'M-Pesa',
  reference VARCHAR(60) NULL,
  notes VARCHAR(255) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reference (reference),                -- blocks the same M-Pesa code being entered twice
  KEY idx_client (client_id),
  KEY idx_paid_on (paid_on),
  CONSTRAINT fk_pay_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Repairs and maintenance
CREATE TABLE IF NOT EXISTS maintenance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NULL,
  title VARCHAR(160) NOT NULL,
  details TEXT NULL,
  priority ENUM('Low','Normal','Urgent') NOT NULL DEFAULT 'Normal',
  status ENUM('Open','In progress','Done') NOT NULL DEFAULT 'Open',
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  charge_owner TINYINT(1) NOT NULL DEFAULT 1,         -- deduct the cost on the owner statement
  reported_on DATE NOT NULL,
  completed_on DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mt_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_mt_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Who did what
CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Failed sign-in attempts, for lockout
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Property listings shown on the website
CREATE TABLE IF NOT EXISTS listings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  purpose ENUM('rent','sale') NOT NULL DEFAULT 'rent',
  type ENUM('apartment','house','townhouse','land','commercial') NOT NULL DEFAULT 'apartment',
  area VARCHAR(80) NOT NULL,                 -- e.g. Kilimani, Westlands, Thika
  address VARCHAR(200) NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,    -- KES; monthly for rent, total for sale
  bedrooms TINYINT UNSIGNED NULL,
  bathrooms TINYINT UNSIGNED NULL,
  size_sqm DECIMAL(10,2) NULL,
  summary VARCHAR(300) NULL,                 -- card text and meta description
  description TEXT NULL,
  features TEXT NULL,                        -- one per line
  property_id INT UNSIGNED NULL,             -- optional link to a managed property
  unit_id INT UNSIGNED NULL,
  status ENUM('draft','review','published','let','sold','archived') NOT NULL DEFAULT 'draft',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  title_verified TINYINT(1) NOT NULL DEFAULT 0,  -- set by an administrator; controls the badge on the website
  cover_photo_id INT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_public (status, featured, published_at),
  KEY idx_area (area),
  CONSTRAINT fk_listing_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_listing_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  path_lg VARCHAR(255) NOT NULL,             -- relative to uploads_url
  path_sm VARCHAR(255) NOT NULL,
  path_th VARCHAR(255) NOT NULL,
  width SMALLINT UNSIGNED NOT NULL,
  height SMALLINT UNSIGNED NOT NULL,
  alt VARCHAR(200) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_listing (listing_id, sort_order),
  CONSTRAINT fk_photo_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
