UPDATE users
INNER JOIN tenants ON tenants.id = users.tenant_id
SET users.password_hash = '$2y$10$H3HWtvx2UMv/qCY0SjepJeXugHui1zvBdx8NgQWvfCEVLbPa9mS1S',
    users.role = 'tenant_admin',
    users.status = 'active',
    tenants.status = 'active'
WHERE users.email = 'admin@test.com'
AND tenants.slug = 'demo-academy';

INSERT INTO tenants (id, name, slug, status)
SELECT 1, 'Demo Academy', 'demo-academy', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM tenants WHERE slug = 'demo-academy'
);

INSERT INTO users (tenant_id, name, email, password_hash, role, status)
SELECT tenants.id, 'Demo Admin', 'admin@test.com',
       '$2y$10$H3HWtvx2UMv/qCY0SjepJeXugHui1zvBdx8NgQWvfCEVLbPa9mS1S',
       'tenant_admin', 'active'
FROM tenants
WHERE tenants.slug = 'demo-academy'
AND NOT EXISTS (
    SELECT 1
    FROM users
    WHERE users.tenant_id = tenants.id
    AND users.email = 'admin@test.com'
);
