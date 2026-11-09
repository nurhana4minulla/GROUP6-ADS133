CREATE TABLE IF NOT EXISTS setup_state (
 name VARCHAR(80) PRIMARY KEY
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(254) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, role ENUM('owner','staff') NOT NULL DEFAULT 'staff',
 session_version INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS slots (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, starts_at DATETIME NOT NULL UNIQUE, is_open BOOLEAN NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS requests (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slot_id INT UNSIGNED NOT NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
 active_slot INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status <> 'declined' THEN slot_id END) STORED UNIQUE,
 client_name VARCHAR(160) NOT NULL, client_kind ENUM('individual','company') NOT NULL,
 contact_person VARCHAR(160), mobile VARCHAR(30) NOT NULL, email VARCHAR(254),
 contact_method VARCHAR(40) NOT NULL, contact_other VARCHAR(160), site_area VARCHAR(160) NOT NULL,
 project_name VARCHAR(200) NOT NULL, project_type VARCHAR(80) NOT NULL, project_other VARCHAR(160),
 nature VARCHAR(80) NOT NULL, nature_other VARCHAR(160), notes TEXT,
 INDEX request_inbox (status, received_at), INDEX received (received_at),
 FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS pool_details (
 request_id INT UNSIGNED PRIMARY KEY, pool_type VARCHAR(80), type_other VARCHAR(160),
 shape VARCHAR(80), shape_other VARCHAR(160), length_m DECIMAL(7,2), width_m DECIMAL(7,2), depth_m DECIMAL(7,2),
 FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS request_features (
 request_id INT UNSIGNED NOT NULL, feature VARCHAR(80) NOT NULL, other_detail VARCHAR(160),
 PRIMARY KEY (request_id, feature), FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS request_facilities (
 request_id INT UNSIGNED NOT NULL, facility VARCHAR(80) NOT NULL, other_detail VARCHAR(160),
 PRIMARY KEY (request_id, facility), FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS meetings (
 request_id INT UNSIGNED PRIMARY KEY, outcome ENUM('proceed','inquiry') NOT NULL, accessibility VARCHAR(40),
 full_address VARCHAR(255), notes TEXT, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS site_visits (
 request_id INT UNSIGNED PRIMARY KEY, starts_at DATETIME NOT NULL,
 FOREIGN KEY (request_id) REFERENCES meetings(request_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS projects (
 request_id INT UNSIGNED PRIMARY KEY, status ENUM('ongoing','completed') NOT NULL DEFAULT 'ongoing',
 started_on DATE NOT NULL, completed_on DATE, notes TEXT,
 INDEX project_status (status), FOREIGN KEY (request_id) REFERENCES meetings(request_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS content (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kind ENUM('company','service','portfolio') NOT NULL,
 slug VARCHAR(100) NOT NULL, title VARCHAR(180) NOT NULL, description TEXT NOT NULL, category VARCHAR(100),
 location VARCHAR(180), project_date DATE, published BOOLEAN NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0,
 UNIQUE (kind,slug), INDEX content_public (kind,published,sort_order)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS content_images (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, content_id INT UNSIGNED NOT NULL, path VARCHAR(255) NOT NULL,
 alt VARCHAR(180) NOT NULL, sort_order INT NOT NULL DEFAULT 0,
 UNIQUE (content_id,path), FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS message_templates (
 event VARCHAR(32) PRIMARY KEY, body VARCHAR(600) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS messages (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id INT UNSIGNED NOT NULL, event VARCHAR(32) NOT NULL,
 occurrence VARCHAR(19) NOT NULL DEFAULT '', due_at DATETIME NOT NULL, expires_at DATETIME, channel ENUM('sms','viber') NOT NULL,
 recipient VARCHAR(30) NOT NULL, body VARCHAR(600) NOT NULL,
 status ENUM('queued','sending','simulated','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
 processed_at DATETIME, error VARCHAR(255),
 UNIQUE (request_id,event,occurrence), INDEX outbox (status,due_at),
 FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE RESTRICT,
 FOREIGN KEY (event) REFERENCES message_templates(event) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS activity (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT UNSIGNED,
 action VARCHAR(80) NOT NULL, target VARCHAR(100) NOT NULL, happened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rate_limits (
 scope VARCHAR(32) NOT NULL, subject CHAR(64) NOT NULL, bucket BIGINT UNSIGNED NOT NULL, hits INT UNSIGNED NOT NULL,
 PRIMARY KEY (scope,subject,bucket), INDEX rate_expiry (bucket)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS reset_tokens (
 token_hash CHAR(64) PRIMARY KEY, admin_id INT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL,
 FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;
