<?php
require 'app.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT u.*, r.name AS role_name
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        login_user($pdo, $user);
        flash('Welcome back, ' . $user['name'] . '!');
        redirect('index.php');
    }

    // Same message for wrong email or wrong password (don't reveal which)
    $error = 'Invalid email or password.';
}

page_header('Login');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Login</h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100">Login</button>
                </form>

                <hr>
                <p class="small text-muted mb-1">Test accounts (password: <code>password123</code>)</p>
                <ul class="small text-muted mb-0">
                    <li>admin@test.com — admin</li>
                    <li>editor@test.com — editor</li>
                    <li>designer@test.com — designer</li>
                    <li>church@test.com — client (Grace Community Church)</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
