<?php
require 'app.php';
$user = require_login();

// ---------- Load content the user is allowed to see ----------
if (can('content.view_all')) {
    $stmt = $pdo->query(
        'SELECT ci.*, c.name AS client_name, u.name AS author
         FROM content_items ci
         JOIN clients c ON c.id = ci.client_id
         JOIN users u   ON u.id = ci.created_by
         ORDER BY ci.updated_at DESC'
    );
} else {
    // Client users only see their own client's content
    $stmt = $pdo->prepare(
        'SELECT ci.*, c.name AS client_name, u.name AS author
         FROM content_items ci
         JOIN clients c ON c.id = ci.client_id
         JOIN users u   ON u.id = ci.created_by
         WHERE ci.client_id = ?
         ORDER BY ci.updated_at DESC'
    );
    $stmt->execute([$user['client_id']]);
}
$items = $stmt->fetchAll();

$clients = $pdo->query('SELECT * FROM clients ORDER BY name')->fetchAll();

// Status badge colors
$badge = [
    'draft'     => 'secondary',
    'in_review' => 'warning',
    'approved'  => 'primary',
    'published' => 'success',
];

// Which workflow buttons to show for an item
function actions_for(array $item): array
{
    $actions = [];
    $status  = $item['status'];

    if ($status === 'draft' && can('content.submit')) {
        $actions['submit'] = ['Submit for review', 'warning'];
    }
    if ($status === 'in_review' && can('content.approve') && can_access_client((int) $item['client_id'])) {
        $actions['approve'] = ['Approve', 'primary'];
        $actions['reject']  = ['Reject', 'danger'];
    }
    if ($status === 'approved' && can('content.publish')) {
        $actions['publish'] = ['Publish', 'success'];
    }
    return $actions;
}

page_header('Dashboard');
?>
<div class="row g-4">
    <?php if (can('content.create')): ?>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">New content</h2>
                <form method="post" action="action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-2">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-select" required>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Title</label>
                        <input name="title" class="form-control" maxlength="200" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Body</label>
                        <textarea name="body" class="form-control" rows="3"></textarea>
                    </div>
                    <button class="btn btn-dark w-100">Save draft</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= can('content.create') ? 'col-lg-8' : 'col-12' ?>">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Content workflow</h2>
                <p class="small text-muted">draft → in review → approved → published</p>

                <?php if (!$items): ?>
                    <p class="text-muted">No content yet.</p>
                <?php endif; ?>

                <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Title</th><th>Client</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['title']) ?></strong><br>
                                <span class="small text-muted">by <?= e($item['author']) ?></span>
                            </td>
                            <td><?= e($item['client_name']) ?></td>
                            <td>
                                <span class="badge bg-<?= $badge[$item['status']] ?>">
                                    <?= e(str_replace('_', ' ', $item['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php foreach (actions_for($item) as $action => [$label, $color]): ?>
                                    <form method="post" action="action.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="<?= e($action) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="btn btn-sm btn-<?= $color ?>"><?= e($label) ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </td>
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
