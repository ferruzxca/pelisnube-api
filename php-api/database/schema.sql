SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS promo_recipients;
DROP TABLE IF EXISTS promo_campaigns;
DROP TABLE IF EXISTS password_otps;
DROP TABLE IF EXISTS payment_attempts;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS subscription_plans;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS content_sections;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS contents;
DROP TABLE IF EXISTS users;

DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS subscription_history;
DROP TABLE IF EXISTS plan_history;
DROP TABLE IF EXISTS section_history;
DROP TABLE IF EXISTS content_history;
DROP TABLE IF EXISTS user_history;
DROP TABLE IF EXISTS password_otps;
DROP TABLE IF EXISTS payment_attempts;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS subscription_plans;
DROP TABLE IF EXISTS content_sections;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS contents;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id CHAR(36) NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('SUPER_ADMIN', 'ADMIN', 'USER') NOT NULL DEFAULT 'USER',
  status ENUM('PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  preferred_lang ENUM('es', 'en') NOT NULL DEFAULT 'es',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_uk (email),
  KEY users_role_idx (role),
  KEY users_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contents (
  id CHAR(36) NOT NULL,
  title VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NOT NULL,
  type ENUM('MOVIE', 'SERIES') NOT NULL,
  synopsis TEXT NOT NULL,
  year SMALLINT NOT NULL,
  duration SMALLINT NOT NULL,
  rating DECIMAL(3,1) NOT NULL,
  trailer_watch_url VARCHAR(512) NOT NULL,
  trailer_embed_url VARCHAR(512) NULL,
  poster_url VARCHAR(512) NOT NULL,
  banner_url VARCHAR(512) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY contents_slug_uk (slug),
  KEY contents_type_idx (type),
  KEY contents_is_active_idx (is_active),
  KEY contents_year_idx (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sections (
  id CHAR(36) NOT NULL,
  section_key VARCHAR(120) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_home_visible TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY sections_key_uk (section_key),
  KEY sections_sort_order_idx (sort_order),
  KEY sections_visible_idx (is_home_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id CHAR(36) NOT NULL,
  section_id CHAR(36) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY content_sections_content_section_uk (content_id, section_id),
  KEY content_sections_section_idx (section_id),
  CONSTRAINT content_sections_content_fk FOREIGN KEY (content_id) REFERENCES contents(id),
  CONSTRAINT content_sections_section_fk FOREIGN KEY (section_id) REFERENCES sections(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  content_id CHAR(36) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY favorites_user_content_uk (user_id, content_id),
  KEY favorites_user_idx (user_id),
  KEY favorites_content_idx (content_id),
  CONSTRAINT favorites_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT favorites_content_fk FOREIGN KEY (content_id) REFERENCES contents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_plans (
  id CHAR(36) NOT NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  price_monthly DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'MXN',
  quality VARCHAR(80) NOT NULL,
  screens TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY plans_code_uk (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  plan_id CHAR(36) NOT NULL,
  status ENUM('PENDING', 'ACTIVE', 'CANCELED', 'EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  started_at DATETIME NOT NULL,
  renewal_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY subscriptions_user_uk (user_id),
  KEY subscriptions_plan_idx (plan_id),
  KEY subscriptions_status_idx (status),
  CONSTRAINT subscriptions_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT subscriptions_plan_fk FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_attempts (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NULL,
  user_email VARCHAR(191) NULL,
  plan_id CHAR(36) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'MXN',
  card_last4 VARCHAR(4) NOT NULL,
  card_brand VARCHAR(50) NOT NULL,
  status ENUM('SUCCESS', 'FAILED') NOT NULL,
  reason VARCHAR(255) NULL,
  metadata LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY payment_user_idx (user_id),
  KEY payment_plan_idx (plan_id),
  KEY payment_status_idx (status),
  CONSTRAINT payment_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT payment_plan_fk FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_otps (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 5,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY password_otps_user_idx (user_id),
  KEY password_otps_is_used_idx (is_used),
  CONSTRAINT password_otps_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id CHAR(36) NULL,
  actor_role VARCHAR(40) NULL,
  target_type VARCHAR(60) NOT NULL,
  target_id VARCHAR(80) NULL,
  action VARCHAR(80) NOT NULL,
  before_state LONGTEXT NULL,
  after_state LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY audit_actor_idx (actor_user_id),
  KEY audit_target_idx (target_type, target_id),
  KEY audit_action_idx (action),
  KEY audit_created_idx (created_at),
  CONSTRAINT audit_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  action VARCHAR(80) NOT NULL,
  snapshot LONGTEXT NOT NULL,
  actor_user_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_history_user_idx (user_id),
  KEY user_history_created_idx (created_at),
  CONSTRAINT user_history_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT user_history_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id CHAR(36) NOT NULL,
  action VARCHAR(80) NOT NULL,
  snapshot LONGTEXT NOT NULL,
  actor_user_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY content_history_content_idx (content_id),
  KEY content_history_created_idx (created_at),
  CONSTRAINT content_history_content_fk FOREIGN KEY (content_id) REFERENCES contents(id),
  CONSTRAINT content_history_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE section_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id CHAR(36) NOT NULL,
  action VARCHAR(80) NOT NULL,
  snapshot LONGTEXT NOT NULL,
  actor_user_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY section_history_section_idx (section_id),
  KEY section_history_created_idx (created_at),
  CONSTRAINT section_history_section_fk FOREIGN KEY (section_id) REFERENCES sections(id),
  CONSTRAINT section_history_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plan_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id CHAR(36) NOT NULL,
  action VARCHAR(80) NOT NULL,
  snapshot LONGTEXT NOT NULL,
  actor_user_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY plan_history_plan_idx (plan_id),
  KEY plan_history_created_idx (created_at),
  CONSTRAINT plan_history_plan_fk FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
  CONSTRAINT plan_history_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscription_id CHAR(36) NOT NULL,
  action VARCHAR(80) NOT NULL,
  snapshot LONGTEXT NOT NULL,
  actor_user_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY subscription_history_subscription_idx (subscription_id),
  KEY subscription_history_created_idx (created_at),
  CONSTRAINT subscription_history_subscription_fk FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
  CONSTRAINT subscription_history_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  action_key VARCHAR(120) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  attempt_count INT NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY rate_limit_action_subject_uk (action_key, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
