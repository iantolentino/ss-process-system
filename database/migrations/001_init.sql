CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NOT NULL DEFAULT '',
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','policy_owner','process_owner','employee') NOT NULL DEFAULT 'employee',
  department_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (email, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Policy Portal: one row per document, one row per uploaded version (never overwritten).
CREATE TABLE documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('policy','memo','manual','form') NOT NULL,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(120) NOT NULL DEFAULT '',
  owner_id INT UNSIGNED NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
  review_due DATE NULL,
  sharepoint_url VARCHAR(500) NULL,
  current_version_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (type, status),
  FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE document_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  version_no VARCHAR(20) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  sha256 CHAR(64) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  changelog TEXT NOT NULL,
  effective_date DATE NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (document_id, version_no),
  FOREIGN KEY (document_id) REFERENCES documents(id),
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE acknowledgements (
  user_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  acked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, version_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (version_id) REFERENCES document_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Process Library. Steps live inside each version's JSON snapshot (who/position/what/tools/decisions),
-- so history and flowchart come from one source. shortcut: steps not queryable in SQL, split to a table if reporting needs it.
CREATE TABLE processes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  purpose TEXT NOT NULL,
  owner_position VARCHAR(160) NOT NULL DEFAULT '',
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
  current_version_id INT UNSIGNED NULL,
  last_reviewed_at DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (department_id, status),
  FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE process_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  process_id INT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  snapshot JSON NOT NULL,
  changelog TEXT NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (process_id, version_no),
  FOREIGN KEY (process_id) REFERENCES processes(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE positions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  holder_name VARCHAR(160) NOT NULL DEFAULT '',
  reports_to_id INT UNSIGNED NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id),
  FOREIGN KEY (reports_to_id) REFERENCES positions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE change_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  process_id INT UNSIGNED NOT NULL,
  requested_by INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  details TEXT NOT NULL,
  status ENUM('open','approved','rejected','done') NOT NULL DEFAULT 'open',
  reviewed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  FOREIGN KEY (process_id) REFERENCES processes(id),
  FOREIGN KEY (requested_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only by convention: the app only INSERTs. shortcut: add DB triggers or REVOKE if the host allows.
CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(60) NOT NULL,
  entity_id INT UNSIGNED NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  INDEX (entity, entity_id),
  INDEX (user_id, at),
  INDEX (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
