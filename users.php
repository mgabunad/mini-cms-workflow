<?php
// users.php — admin-only page to add users and change roles.
require 'app.php';
require_login();
authorize('users.manage');

$roles   = $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
$clients = $pdo->query('SELECT * FROM clients ORDER BY name')->fetchAll();
$roleIds = array_map('intval', array_column($roles, 'id'));
$clientRoleId = 4; // "client" role must be linked to a client

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    // ----- Add a user -----
    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId   = (int) ($_POST['role_id'] ?? 0);
        $clientId = ($_POST['client_id'] ?? '') !== '' ? (int) $_POST['client_id'] : null;

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (!in_array($roleId, $roleIds, true)) $errors[] = 'Pick a valid role.';
        if ($roleId === $clientRoleId && $clientId === null) $errors[] = 'Client users need a client.';
        if ($roleId !== $clientRoleId) $clientId = null; // staff are not tied to one client

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role_id, client_id) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $roleId, $clientId]);
                flash("User $name added.");
                redirect('users.php');
            } catch (PDOException $ex) {
                $errors[] = 'That email is already used.';
            }
        }
        flash(implode(' ', $errors));
        redirect('users.php');
    }

    // ----- Change a user's role -----
    if ($action === 'role') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);

        if ($userId === current_user()['id']) {
            flash("You can't change your own role.");
        } elseif ($roleId === $clientRoleId) {
            flash('To make someone a client user, add them as a new user with a client.');
        } elseif (in_array($roleId, $roleIds, true)) {
            $stmt = $pdo->prepare('UPDATE users SET role_id = ?, client_id = NULL WHERE id = ?');
            $stmt->execute([$roleId, $userId]);
            flash('Role updated. It applies the next time they log in.');
        }
        redirect('users.php');
    }
}

$users = $pdo->query(
    'SELECT u.*, r.name AS role_name, c.name AS client_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN clients c ON c.id = u.client_id
     ORDER BY u.id'
)->fetchAll();

// Permission matrix for display
$matrix = $pdo->query(
    'SELECT p.code, p.label, GROUP_CONCAT(rp.role_id) AS role_ids
     FROM permissions p
     LEFT JOIN role_permissions rp ON rp.permission_id = p.id
     GROUP BY p.id ORDER BY p.id'
)->fetchAll();

page_header('Users');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Add user</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input name="name" class="form-control mb-2" placeholder="Name" required>
                    <input name="email" type="email" class="form-control mb-2" placeholder="Email" autocomplete="off" required>
                    <input name="password" type="password" class="form-control mb-2" placeholder="Password (8+ chars)" autocomplete="new-password" required>
                    <select name="role_id" class="form-select mb-2">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="client_id" class="form-select mb-3">
                        <option value="">— Client (only for client users) —</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-dark w-100">Add user</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Users</h2>
                <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Name</th><th>Email</th><th>Client</th><th>Role</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= e($u['name']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><?= e($u['client_name'] ?? '—') ?></td>
                            <td>
                                <?php if ((int) $u['id'] === current_user()['id'] || (int) $u['role_id'] === $clientRoleId): ?>
                                    <span class="badge bg-info text-dark"><?= e($u['role_name']) ?></span>
                                <?php else: ?>
                                    <form method="post" class="d-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="role">
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <select name="role_id" class="form-select form-select-sm">
                                            <?php foreach ($roles as $r): if ((int) $r['id'] === $clientRoleId) continue; ?>
                                                <option value="<?= (int) $r['id'] ?>" <?= $r['id'] == $u['role_id'] ? 'selected' : '' ?>>
                                                    <?= e($r['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-dark">Save</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Permission matrix</h2>
                <div class="table-responsive">
                <table class="table table-sm text-center">
                    <thead>
                        <tr>
                            <th class="text-start">Permission</th>
                            <?php foreach ($roles as $r): ?><th><?= e($r['name']) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($matrix as $p): $has = explode(',', (string) $p['role_ids']); ?>
                        <tr>
                            <td class="text-start"><?= e($p['label']) ?></td>
                            <?php foreach ($roles as $r): ?>
                                <td><?= in_array((string) $r['id'], $has, true) ? '✅' : '—' ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
