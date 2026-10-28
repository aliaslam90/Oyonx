CREATE TABLE plugin_rate_limits (
 bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 expires_at DATETIME NOT NULL,
 attempts INT UNSIGNED NOT NULL,
 KEY expiry(expires_at)
) ENGINE=InnoDB;
CREATE TABLE plugin_customers (
 account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 environment VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 external_customer_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(account_id,environment),
 UNIQUE KEY customer(environment,external_customer_id),
 FOREIGN KEY(account_id) REFERENCES plugin_accounts(id)
) ENGINE=InnoDB;
CREATE TABLE plugin_checkouts (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 plan_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 external_session_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 environment VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at DATETIME NOT NULL,
 FOREIGN KEY(account_id) REFERENCES plugin_accounts(id),
 FOREIGN KEY(plan_code) REFERENCES plugin_plans(code)
) ENGINE=InnoDB;
ALTER TABLE plugin_accounts ADD sessions_valid_after DATETIME NULL;
CREATE TABLE plugin_billing_locks (
 lock_name VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO plugin_billing_locks(lock_name) VALUES('stripe-events');
