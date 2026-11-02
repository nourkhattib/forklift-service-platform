<?php
require "db.php";

session_start();
$userId = $_SESSION['user_id'] ?? 0;

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT 
        q.id AS invoice_id,
        q.amount,
        q.status AS invoice_status,
        q.issue_date,
        q.payment_date,

        sr.id AS request_id,
        sr.description AS request_description,
        sr.status AS request_status,
        sr.technician_id,
        sr.technician_notes,

        s.name AS technician_name,
        s.email AS technician_email

    FROM quotes q
    LEFT JOIN service_requests sr ON sr.id = q.service_id
    LEFT JOIN staff s ON sr.technician_id = s.id
    WHERE q.id = ? AND q.user_id = ?
");

$stmt->execute([$id, $userId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo "<p class='text-danger'>Invoice not found.</p>";
    exit;
}
?>

<h5>Invoice #<?= $invoice['invoice_id'] ?></h5>

<p><strong>Amount:</strong> ₪<?= number_format($invoice['amount'], 2) ?></p>
<p><strong>Status:</strong> <?= ucfirst($invoice['invoice_status']) ?></p>
<p><strong>Issue Date:</strong> <?= $invoice['issue_date'] ?></p>

<hr>

<h6>Assigned Technician:</h6>
<p><?= $invoice['technician_name'] ?: 'No technician assigned' ?></p>

<hr>

<h6>Technician Notes:</h6>
<p><?= nl2br($invoice['technician_notes'] ?? '—') ?></p>


<?php if ($invoice['invoice_status'] !== 'paid'): ?>
    <form method="POST">
        <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
        <input type="hidden" name="update_invoice" value="1">
        <button class="btn btn-danger w-100 mt-3" name="new_status" value="cancelled">
            Cancel Invoice
        </button>
    </form>
<?php endif; ?>