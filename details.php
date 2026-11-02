<?php
session_start();
require_once "db.php";



$request_id = (int)($_GET['id'] ?? 0);
if (!$request_id) die("Invalid request ID");

// Fetch request info, including product details and assigned technician
$stmt = $pdo->prepare("
    SELECT sr.*, u.username AS client_name, s.name AS tech_name, p.name AS product_name, p.image_path AS product_image
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    LEFT JOIN staff s ON sr.technician_id = s.id
    LEFT JOIN products p ON sr.product_id = p.id
    WHERE sr.id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();
if (!$request) die("Request not found");

// CSRF token
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">'; }

// Add progress update (optional field on service_requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) die("CSRF validation failed");

    $log_text = trim($_POST['log_text'] ?? '');
    if ($log_text) {
        $stmt = $pdo->prepare("UPDATE service_requests SET progress = CONCAT(IFNULL(progress, ''), ?) WHERE id = ?");
        $stmt->execute(["\n" . date('Y-m-d H:i') . " - " . $log_text, $request_id]);
    }

    header("Location: details.php?id=$request_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request #<?= $request_id ?> Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">


<div class="container-fluid mt-4">
    <h2>Request #<?= $request_id ?> Details</h2>
    <div class="card bg-dark text-white mb-3 p-3">
        <p><strong>Client:</strong> <?= htmlspecialchars($request['client_name']) ?></p>
        <p><strong>Assigned Technician:</strong> <?= htmlspecialchars($request['tech_name'] ?? 'Unassigned') ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($request['status']) ?></p>
        <p><strong>Type:</strong> <?= ucfirst($request['request_type']) ?></p>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($request['description'])) ?></p>

        <?php if ($request['request_type'] === 'product' && $request['product_image']): ?>
            <p><strong>Product:</strong> <?= htmlspecialchars($request['product_name']) ?></p>
            <img src="<?= htmlspecialchars($request['product_image']) ?>" class="img-fluid img-thumbnail mb-3" style="max-width:300px;">
        <?php endif; ?>

        <?php if (!empty($request['progress'])): ?>
            <hr>
            <h4>Progress Updates</h4>
            <pre class="bg-secondary p-2"><?= htmlspecialchars($request['progress']) ?></pre>
        <?php endif; ?>
    </div>

    <hr>
    <h4>Add Progress Update</h4>
    <form method="POST" action="details.php">
        <?= csrf_field() ?>
        <input type="hidden" name="ticket_id" value="<?= $request_id ?>">
        <div class="mb-3">
            <textarea name="log_text" class="form-control bg-dark text-white" rows="4" placeholder="Enter update..." required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Add Update</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
