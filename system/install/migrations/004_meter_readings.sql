-- 004: metered water and electricity.
-- Adds unit prices to company settings and properties, and a meter_readings table.
-- Each saved reading is one line on that month's invoice (meter_readings.invoice_id).
-- Run by hand: phpMyAdmin > your database > SQL tab > paste > Go. Safe to run more than once.

-- Company default prices per unit
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'default_water_price');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE settings ADD COLUMN default_water_price DECIMAL(10,2) NOT NULL DEFAULT 150.00',
  'SELECT ''default_water_price already exists'' AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'default_electricity_price');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE settings ADD COLUMN default_electricity_price DECIMAL(10,2) NULL DEFAULT NULL',
  'SELECT ''default_electricity_price already exists'' AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Property prices (NULL = use the company default)
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'water_price');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE properties ADD COLUMN water_price DECIMAL(10,2) NULL DEFAULT NULL',
  'SELECT ''water_price already exists'' AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'properties' AND COLUMN_NAME = 'electricity_price');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE properties ADD COLUMN electricity_price DECIMAL(10,2) NULL DEFAULT NULL',
  'SELECT ''electricity_price already exists'' AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One reading per unit, per utility, per month. The price used is saved on the
-- reading so old bills never change when prices change.
CREATE TABLE IF NOT EXISTS meter_readings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  utility ENUM('water','electricity') NOT NULL,
  period DATE NOT NULL,                              -- first day of the month
  previous_reading DECIMAL(12,2) NOT NULL,
  current_reading DECIMAL(12,2) NOT NULL,
  units_used DECIMAL(12,2) NOT NULL,
  price_per_unit DECIMAL(10,2) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  meter_replaced TINYINT(1) NOT NULL DEFAULT 0,
  invoice_id INT UNSIGNED NULL,                      -- the invoice this reading is a line on (NULL when 0 units)
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reading (unit_id, utility, period),
  KEY idx_client (client_id),
  KEY idx_invoice (invoice_id),
  CONSTRAINT fk_mr_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
