-- Production hardening migration for Multi-Tenant Course Portal.
-- Run after importing the base schema.

ALTER TABLE users
    MODIFY role ENUM('super_admin','tenant_admin','instructor','student') NOT NULL DEFAULT 'student';

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_password_reset_token (token_hash),
    KEY idx_password_resets_user (tenant_id, user_id, expires_at),
    CONSTRAINT fk_password_resets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE course_enrollments
    ADD KEY idx_enrollments_course_fk (course_id),
    DROP INDEX unique_course_user,
    ADD UNIQUE KEY unique_course_user_per_tenant (tenant_id, course_id, user_id);

ALTER TABLE resource_progress
    DROP INDEX unique_resource_user_progress,
    ADD UNIQUE KEY unique_resource_user_progress_per_tenant (tenant_id, resource_id, user_id);

ALTER TABLE assignment_submissions
    ADD KEY idx_submissions_assignment_fk (assignment_id),
    DROP INDEX unique_assignment_student,
    ADD UNIQUE KEY unique_assignment_student_per_tenant (tenant_id, assignment_id, student_id);
