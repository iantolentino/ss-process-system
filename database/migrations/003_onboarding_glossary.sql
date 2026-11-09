-- A user's position (for role-specific onboarding). Deleting a position just clears it on users.
ALTER TABLE users
  ADD COLUMN position_id INT UNSIGNED NULL AFTER department_id,
  ADD FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL;

CREATE TABLE glossary (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  term VARCHAR(120) NOT NULL UNIQUE,
  definition TEXT NOT NULL,
  department_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Checklist per department; position_id NULL = everyone in the department. week_no 0 = ongoing.
CREATE TABLE onboarding_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NULL,
  week_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(255) NOT NULL,
  detail TEXT NOT NULL,
  process_id INT UNSIGNED NULL,
  document_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (department_id),
  FOREIGN KEY (department_id) REFERENCES departments(id),
  FOREIGN KEY (position_id) REFERENCES positions(id),
  FOREIGN KEY (process_id) REFERENCES processes(id),
  FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE onboarding_progress (
  user_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  done_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, item_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (item_id) REFERENCES onboarding_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
