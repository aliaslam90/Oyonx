-- MySQL 8 / MariaDB, InnoDB. Review on staging before applying.
-- No production database has been created or migrated.
CREATE TABLE plugin_accounts (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE plugin_products (
  slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE plugin_plans (
  code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  currency CHAR(3) CHARACTER SET ascii NOT NULL,
  amount_minor INT UNSIGNED NOT NULL,
  interval_unit VARCHAR(16) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE plugin_plan_products (
  plan_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  product_slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY(plan_code, product_slug),
  FOREIGN KEY(plan_code) REFERENCES plugin_plans(code),
  FOREIGN KEY(product_slug) REFERENCES plugin_products(slug)
) ENGINE=InnoDB;

CREATE TABLE plugin_provider_prices (
  provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  environment VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  external_price_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  plan_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY(provider, environment, external_price_id),
  FOREIGN KEY(plan_code) REFERENCES plugin_plans(code)
) ENGINE=InnoDB;

CREATE TABLE plugin_subscriptions (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  plan_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  environment VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  external_subscription_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status VARCHAR(32) CHARACTER SET ascii NOT NULL,
  access_until DATETIME NULL,
  cancel_at_period_end BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY provider_subscription(provider, environment, external_subscription_id),
  KEY account_access(account_id, status, access_until),
  FOREIGN KEY(account_id) REFERENCES plugin_accounts(id),
  FOREIGN KEY(plan_code) REFERENCES plugin_plans(code)
) ENGINE=InnoDB;

-- Trial window policy is deliberately not seeded until owner confirms reset rules.
-- Lock this row in a transaction for each authorized use; don't trust client counters.
CREATE TABLE plugin_trial_usage (
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  product_slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  window_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  uses_consumed INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY(account_id, product_slug, window_key),
  FOREIGN KEY(account_id) REFERENCES plugin_accounts(id),
  FOREIGN KEY(product_slug) REFERENCES plugin_products(slug)
) ENGINE=InnoDB;

-- Repeated client requests must not consume another free use.
CREATE TABLE plugin_usage_events (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  product_slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY account_request(account_id, request_key),
  FOREIGN KEY(account_id) REFERENCES plugin_accounts(id),
  FOREIGN KEY(product_slug) REFERENCES plugin_products(slug)
) ENGINE=InnoDB;

-- Insert and process a verified provider event atomically with entitlement updates.
CREATE TABLE plugin_webhook_events (
  provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  environment VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  external_event_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(provider, environment, external_event_id)
) ENGINE=InnoDB;

-- Store only digests of bearer/reset/verification tokens, never raw tokens.
CREATE TABLE plugin_account_tokens (
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  purpose VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY expiry(expires_at),
  FOREIGN KEY(account_id) REFERENCES plugin_accounts(id)
) ENGINE=InnoDB;
