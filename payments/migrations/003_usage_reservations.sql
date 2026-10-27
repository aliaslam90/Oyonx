ALTER TABLE plugin_accounts ADD session_version CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;
ALTER TABLE plugin_usage_events
 ADD state VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'completed',
 ADD expires_at DATETIME NULL;
ALTER TABLE plugin_checkouts ADD request_state VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'open';
