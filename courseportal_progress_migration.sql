CREATE TABLE IF NOT EXISTS resource_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_resource_user_progress (resource_id, user_id),
  INDEX idx_resource_progress_tenant (tenant_id, resource_id, completed_at)
);
