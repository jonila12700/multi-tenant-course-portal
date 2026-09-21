<?php
include "../includes/session.php";
include "../config/db.php";

require_role('tenant_admin');

$tenant_id = (int) $_SESSION['tenant_id'];
$current_user_id = (int) $_SESSION['user_id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    verify_csrf_token();
    $name = trim($_POST['name'] ?? '');
    $email = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $allowed_roles = ['instructor', 'student'];

    if ($name === '' || $email === '' || $password === '' || $role === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($passwordError = password_policy_error($password)) {
        $error = $passwordError;
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = 'Invalid role selected.';
    } else {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (tenant_id, name, email, password_hash, role, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $tenant_id,
                $name,
                $email,
                $password_hash,
                $role
            ]);

            $success = 'User created successfully.';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'This email already exists for your organization.';
            } else {
                $error = 'Failed to create user. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    verify_csrf_token();
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    $allowed_statuses = ['active', 'blocked'];

    if ($user_id === $current_user_id) {
        $error = 'You cannot change your own status.';
    } elseif (!in_array($new_status, $allowed_statuses, true)) {
        $error = 'Invalid status.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET status = ?
            WHERE id = ?
            AND tenant_id = ?
        ");

        $stmt->execute([
            $new_status,
            $user_id,
            $tenant_id
        ]);

        $success = 'User status updated successfully.';
    }
}

$stmt = $pdo->prepare("
    SELECT id, name, email, role, status, created_at
    FROM users
    WHERE tenant_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$tenant_id]);
$users = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/sidebar_admin.php"; ?>

<div class="content">
    <div class="container-fluid">

        <h2 class="mb-4">Manage Users</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                Add New User
            </div>

            <div class="card-body">
                <form method="POST">
                <?php echo csrf_field(); ?>
                    <input type="hidden" name="create_user" value="1">

                    <div class="row">

                        <div class="col-md-3 mb-3">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label>Email</label>
                            <input type="text" inputmode="email" autocomplete="email" name="email" class="form-control" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label>Role</label>
                            <select name="role" class="form-control" required>
                                <option value="">Select role</option>
                                <option value="instructor">Professor</option>
                                <option value="student">Student</option>
                            </select>
                        </div>

                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                Add
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                Users
            </div>

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input
                            type="text"
                            id="userSearch"
                            class="form-control"
                            placeholder="Search users..."
                        >
                    </div>

                    <div class="col-md-4">
                        <select id="roleFilter" class="form-control">
                            <option value="all">All roles</option>
                            <option value="tenant_admin">Admin</option>
                            <option value="instructor">Professor</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                </div>

                <table class="table table-bordered table-striped" id="usersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['status']); ?></td>
                                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                    <td>
                                        <?php if ((int) $user['id'] === $current_user_id): ?>
                                            <span class="text-muted">Current admin</span>
                                        <?php elseif ($user['status'] === 'active'): ?>
                                            <form method="POST" class="status-form" style="display:inline;">
                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="change_status" value="1">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="blocked">

                                                <button type="submit" class="btn btn-warning btn-sm">
                                                    Block
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="status-form" style="display:inline;">
                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="change_status" value="1">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="active">

                                                <button type="submit" class="btn btn-success btn-sm">
                                                    Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    No users found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p id="noResults" class="text-center text-muted" style="display:none;">
                    No matching users found.
                </p>
            </div>
        </div>

        <br>

        <a href="dashboard.php" class="btn btn-secondary">
            Back to Dashboard
        </a>

    </div>
</div>

<script>
const userSearch = document.getElementById('userSearch');
const roleFilter = document.getElementById('roleFilter');
const userRows = document.querySelectorAll('#usersTable tbody tr[data-role]');
const noResults = document.getElementById('noResults');

function filterUsers() {
    const search = userSearch.value.toLowerCase();
    const selectedRole = roleFilter.value;
    let visibleCount = 0;

    userRows.forEach(function (row) {
        const matchesSearch = row.textContent.toLowerCase().includes(search);
        const matchesRole = selectedRole === 'all' || row.dataset.role === selectedRole;

        row.style.display = matchesSearch && matchesRole ? '' : 'none';

        if (matchesSearch && matchesRole) {
            visibleCount++;
        }
    });

    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
}

userSearch.addEventListener('keyup', filterUsers);
roleFilter.addEventListener('change', filterUsers);

document.querySelectorAll('.status-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm('Change this user status?')) {
            event.preventDefault();
        }
    });
});
</script>

<?php include "../includes/footer.php"; ?>



