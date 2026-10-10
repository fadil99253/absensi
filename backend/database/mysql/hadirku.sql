CREATE DATABASE IF NOT EXISTS hadirku
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hadirku;

CREATE TABLE IF NOT EXISTS migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
  employee_id VARCHAR(24) NOT NULL,
  name VARCHAR(80) NOT NULL,
  role VARCHAR(80) NOT NULL DEFAULT 'Karyawan',
  department VARCHAR(60) NOT NULL,
  email VARCHAR(120) NULL DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (employee_id),
  KEY employees_active_name_index (active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id VARCHAR(24) NOT NULL,
  type VARCHAR(10) NOT NULL,
  date DATE NOT NULL,
  time VARCHAR(5) NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY attendances_employee_date_type_unique (employee_id, date, type),
  KEY attendances_date_type_index (date, type),
  CONSTRAINT attendances_employee_id_foreign
    FOREIGN KEY (employee_id) REFERENCES employees (employee_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_access_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL,
  abilities TEXT NULL,
  last_used_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY personal_access_tokens_token_unique (token),
  KEY personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id),
  KEY personal_access_tokens_expires_at_index (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES
  ('0001_01_01_000000_create_users_table', 1),
  ('2026_10_10_000001_create_employees_table', 1),
  ('2026_10_10_000002_create_attendances_table', 1),
  ('2026_10_10_000003_create_personal_access_tokens_table', 1);

INSERT IGNORE INTO users (name, email, password, created_at, updated_at) VALUES
  (
    'Admin HadirKu',
    'admin@hadirku.id',
    '$2y$12$plKMDIcf5UfYeb6jimSOOedYRnvtgduNQvnk9zItqRhntGgqoMFz.',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
  );

INSERT INTO employees (employee_id, name, role, department, email, active, created_at, updated_at) VALUES
  ('EMP-001', 'Rizki Pratama', 'Product Designer', 'Produk', 'rizki@hadirku.id', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('EMP-002', 'Nadia Putri', 'Frontend Developer', 'Engineering', 'nadia@hadirku.id', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('EMP-003', 'Bima Saputra', 'Marketing Specialist', 'Marketing', 'bima@hadirku.id', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('EMP-004', 'Salsa Maharani', 'People Operations', 'People', 'salsa@hadirku.id', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('EMP-005', 'Dimas Kurniawan', 'Backend Developer', 'Engineering', 'dimas@hadirku.id', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('EMP-006', 'Alya Rahma', 'Finance Associate', 'Finance', 'alya@hadirku.id', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  role = VALUES(role),
  department = VALUES(department),
  email = VALUES(email),
  active = VALUES(active),
  updated_at = CURRENT_TIMESTAMP;
