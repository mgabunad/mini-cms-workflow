<?php
// billing.php — create invoices for published content (admin only).
require 'app.php';
$user = require_login();
authorize('invoices.manage');

// ---------- Handle POST forms (mark paid / create invoice) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // ----- Mark an invoice as paid -----
    if (($_POST['action'] ?? '') === 'pay') {
        $id = (int) ($_POST['invoice_id'] ?? 0);

        // "AND status = 'unpaid'" stops paying the same invoice twice
        $stmt = $pdo->prepare(
            "UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'unpaid'"
        );
        $stmt->execute([$id]);

        flash($stmt->rowCount() === 1 ? "Invoice #$id marked as paid." : 'That invoice is already paid.');
        redirect('billing.php');
    }
    // ----- Create an invoice -----

    $contentId = (int) ($_POST['content_id'] ?? 0);
    $amount    = $_POST['amount'] ?? '';

    // Amount must be a positive number with at most 2 decimals
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
        flash('Enter a valid amount, like 150 or 150.50.');
        redirect('billing.php');
    }

    // Only published content can be billed
    $stmt = $pdo->prepare("SELECT * FROM content_items WHERE id = ? AND status = 'published'");
    $stmt->execute([$contentId]);
    $item = $stmt->fetch();

    if (!$item) {
        flash('Only published content can be invoiced.');
        redirect('billing.php');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO invoices (client_id, content_id, amount, created_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$item['client_id'], $contentId, $amount, $user['id']]);
        flash('Invoice created for "' . $item['title'] . '".');
    } catch (PDOException $ex) {
        // content_id is UNIQUE in the database, so a second invoice fails here
        flash('That content already has an invoice.');
    }
    redirect('billing.php');
}

// ---------- Published content that has NO invoice yet ----------
$billable = $pdo->query(
    "SELECT ci.id, ci.title, c.name AS client_name
     FROM content_items ci
     JOIN clients c ON c.id = ci.client_id
     LEFT JOIN invoices i ON i.content_id = ci.id
     WHERE ci.status = 'published' AND i.id IS NULL
     ORDER BY ci.title"
)->fetchAll();

// ---------- All invoices ----------
$invoices = $pdo->query(
    'SELECT i.*, ci.title, c.name AS client_name
     FROM invoices i
     JOIN content_items ci ON ci.id = i.content_id
     JOIN clients c        ON c.id  = i.client_id
     ORDER BY i.created_at DESC'
)->fetchAll();

// ---------- Totals ----------
$totals = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN status = 'unpaid' THEN amount END), 0) AS unpaid,
        COALESCE(SUM(CASE WHEN status = 'paid'   THEN amount END), 0) AS paid
     FROM invoices"
)->fetch();

page_header('Billing');
?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-warning">
            <div class="card-body">
                <div class="text-muted small">Unpaid</div>
                <div class="h3 mb-0">$<?= number_format($totals['unpaid'], 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-success">
            <div class="card-body">
                <div class="text-muted small">Paid</div>
                <div class="h3 mb-0">$<?= number_format($totals['paid'], 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Create invoice</h2>

                <?php if (!$billable): ?>
                    <p class="text-muted small mb-0">
                        No published content to bill. Publish something first.
                    </p>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <label class="form-label">Published content</label>
                        <select name="content_id" class="form-select mb-2" required>
                            <?php foreach ($billable as $b): ?>
                                <option value="<?= (int) $b['id'] ?>">
                                    <?= e($b['title']) ?> — <?= e($b['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label">Amount (USD)</label>
                        <input name="amount" class="form-control mb-3" placeholder="150.00"
                               inputmode="decimal" required>
                        <button class="btn btn-dark w-100">Create invoice</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Invoices</h2>

                <?php if (!$invoices): ?>
                    <p class="text-muted">No invoices yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>#</th><th>Content</th><th>Client</th><th class="text-end">Amount</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><?= (int) $inv['id'] ?></td>
                            <td><?= e($inv['title']) ?></td>
                            <td><?= e($inv['client_name']) ?></td>
                            <td class="text-end">$<?= number_format($inv['amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : 'warning text-dark' ?>">
                                    <?= e($inv['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($inv['status'] === 'unpaid'): ?>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="pay">
                                        <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
                                        <button class="btn btn-sm btn-success">Mark paid</button>
                                    </form>
                                <?php else: ?>
                                    <span class="small text-muted"><?= e(date('M j, Y', strtotime($inv['paid_at']))) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
