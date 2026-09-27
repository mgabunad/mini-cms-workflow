<?php
// action.php — every change to content goes through here.
// The server re-checks permissions and the current status on EVERY request,
// so hiding a button in the UI is never the only protection.

require 'app.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
check_csrf();

$action = $_POST['action'] ?? '';

// ---------- Create a new draft ----------
if ($action === 'create') {
    authorize('content.create');

    $clientId = (int) ($_POST['client_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $body     = trim($_POST['body'] ?? '');

    if ($title === '' || mb_strlen($title) > 200 || !can_access_client($clientId)) {
        flash('Please enter a valid title and client.');
        redirect('index.php');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO content_items (client_id, title, body, created_by) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$clientId, $title, $body, $user['id']]);
    log_action($pdo, (int) $pdo->lastInsertId(), $user['id'], 'created');

    flash('Draft saved.');
    redirect('index.php');
}

// ---------- Workflow transitions ----------
// action => [permission needed, status it must be in now, status it moves to]
$transitions = [
    'submit'  => ['content.submit',  'draft',     'in_review'],
    'approve' => ['content.approve', 'in_review', 'approved'],
    'reject'  => ['content.approve', 'in_review', 'draft'],
    'publish' => ['content.publish', 'approved',  'published'],
];

if (!isset($transitions[$action])) {
    http_response_code(400);
    exit('Unknown action.');
}

[$permission, $from, $to] = $transitions[$action];
authorize($permission);

$id   = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM content_items WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item || !can_access_client((int) $item['client_id'])) {
    http_response_code(404);
    exit('Content not found.');
}

// Approval gate: can only move from the correct status
if ($item['status'] !== $from) {
    flash("Can't $action: item is \"{$item['status']}\", not \"$from\".");
    redirect('index.php');
}

// "AND status = ?" guards against two people clicking at the same time
$stmt = $pdo->prepare('UPDATE content_items SET status = ? WHERE id = ? AND status = ?');
$stmt->execute([$to, $id, $from]);

if ($stmt->rowCount() === 1) {
    log_action($pdo, $id, $user['id'], $action);
    flash('"' . $item['title'] . '" moved to ' . str_replace('_', ' ', $to) . '.');
} else {
    flash('Someone else changed this item first. Please refresh.');
}
redirect('index.php');


function log_action(PDO $pdo, int $contentId, int $userId, string $action): void
{
    $stmt = $pdo->prepare('INSERT INTO content_log (content_id, user_id, action) VALUES (?, ?, ?)');
    $stmt->execute([$contentId, $userId, $action]);
}
