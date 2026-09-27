<?php
// app.php — shared setup: database, sessions, login checks, permissions, layout.
// Every page starts with: require 'app.php';

session_start();

// ---------- 1. Database connection (XAMPP defaults) ----------
$pdo = new PDO(
    'mysql:host=localhost;dbname=cms_practice;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// ---------- 2. Small helpers ----------

// Escape output to stop XSS
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

// Flash message: shown once on the next page
function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

// ---------- 3. CSRF protection for every POST form ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid form token. Go back and try again.');
    }
}

// ---------- 4. Authentication ----------
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

// Load the user's role + permission codes into the session at login
function login_user(PDO $pdo, array $user): void
{
    session_regenerate_id(true); // prevent session fixation

    $stmt = $pdo->prepare(
        'SELECT p.code FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ?'
    );
    $stmt->execute([$user['role_id']]);

    $_SESSION['user'] = [
        'id'          => (int) $user['id'],
        'name'        => $user['name'],
        'role'        => $user['role_name'],
        'client_id'   => $user['client_id'] ? (int) $user['client_id'] : null,
        'permissions' => $stmt->fetchAll(PDO::FETCH_COLUMN),
    ];
}

// ---------- 5. Authorization ----------

// Does the logged-in user have this permission?
function can(string $permission): bool
{
    $user = current_user();
    return $user && in_array($permission, $user['permissions'], true);
}

// Stop the request if they don't
function authorize(string $permission): void
{
    if (!can($permission)) {
        http_response_code(403);
        exit('403 — You do not have permission to do this.');
    }
}

// Can this user see/act on items for this client?
function can_access_client(int $clientId): bool
{
    $user = current_user();
    if (can('content.view_all')) {
        return true;
    }
    return $user && $user['client_id'] === $clientId;
}

// ---------- 6. Page layout ----------
function page_header(string $title): void
{
    $user = current_user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Mini CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">Mini CMS</a>
        <?php if ($user): ?>
            <div class="d-flex align-items-center gap-3 text-white">
                <?php if (can('users.manage')): ?>
                    <a class="btn btn-sm btn-outline-light" href="users.php">Users</a>
                <?php endif; ?>
                <?php if (can('invoices.manage')): ?>
                    <a class="btn btn-sm btn-outline-light" href="billing.php">Billing</a>
                <?php endif; ?>
                <span><?= e($user['name']) ?>
                    <span class="badge bg-info text-dark"><?= e($user['role']) ?></span></span>
                <a class="btn btn-sm btn-outline-warning" href="logout.php">Logout</a>
            </div>
        <?php endif; ?>
    </div>
</nav>
<div class="container">
    <?php if ($msg = flash()): ?>
        <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php
}

function page_footer(): void
{
    echo '</div></body></html>';
}
