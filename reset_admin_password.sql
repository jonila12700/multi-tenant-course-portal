SET @admin_email = 'admin@test.com';
SET @admin_password_hash = '$2y$10$H3HWtvx2UMv/qCY0SjepJeXugHui1zvBdx8NgQWvfCEVLbPa9mS1S';
SET @tenant_slug = 'demo-academy';

INSERT INTO tenants (name, slug, status)
SELECT 'Demo Academy', @tenant_slug, 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM tenants WHERE slug = @tenant_slug
);

SET @target_tenant_id = (
    SELECT tenant_id
    FROM users
    WHERE email = @admin_email
    ORDER BY id ASC
    LIMIT 1
);

SET @target_tenant_id = COALESCE(
    @target_tenant_id,
    (SELECT id FROM tenants WHERE slug = @tenant_slug LIMIT 1)
);

UPDATE tenants
SET status = 'active'
WHERE id = @target_tenant_id;

INSERT INTO users (tenant_id, name, email, password_hash, role, status)
SELECT @target_tenant_id, 'Demo Admin', @admin_email, @admin_password_hash, 'tenant_admin', 'active'
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE tenant_id = @target_tenant_id
    AND email = @admin_email
);

UPDATE users
SET password_hash = @admin_password_hash,
    role = 'tenant_admin',
    status = 'active'
WHERE tenant_id = @target_tenant_id
AND email = @admin_email;

SELECT tenants.slug AS organization_code, users.email, 'Admin12345' AS password
FROM users
INNER JOIN tenants ON tenants.id = users.tenant_id
WHERE users.tenant_id = @target_tenant_id
AND users.email = @admin_email;
