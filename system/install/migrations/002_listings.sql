-- 002: property listings shown on nubiakcestates.com/find-property.
-- Run by hand: phpMyAdmin > your database > SQL tab > paste > Go. Safe to run more than once.

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
