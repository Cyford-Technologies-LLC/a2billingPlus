CREATE TABLE IF NOT EXISTS cc_a2bp_runtime_settings (
  setting_key VARCHAR(191) NOT NULL,
  setting_value TEXT NOT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
