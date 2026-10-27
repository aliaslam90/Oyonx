CREATE TABLE plugin_checkout_attempts (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 plan_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 environment VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL,
 KEY pending_account(account_id,environment,completed_at),
 FOREIGN KEY(account_id) REFERENCES plugin_accounts(id),
 FOREIGN KEY(plan_code) REFERENCES plugin_plans(code)
) ENGINE=InnoDB;
