<?php
session_start();
require_once __DIR__ . "/db.php";

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php"); 
    exit;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">'; }
function csrf_check() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(400); die('CSRF validation failed');
    }
}

function ensure_uploads_dir() {
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}
function handle_upload($fileField) {
    if (empty($_FILES[$fileField]['name'])) return null;
    if (!is_uploaded_file($_FILES[$fileField]['tmp_name'])) return null;

    $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES[$fileField]['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) return null;

    $ext = $allowed[$mime];
    $safe = bin2hex(random_bytes(8)) . "." . $ext;
    $destDir = ensure_uploads_dir();
    $dest = $destDir . "/" . $safe;
    if (!move_uploaded_file($_FILES[$fileField]['tmp_name'], $dest)) return null;

    return "uploads/" . $safe;
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($_POST['action'] === 'add_product') {
        $type  = $_POST['type'] ?? 'forklifts';
        $fields = [
            'name' => trim($_POST['name'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'price' => $_POST['price'] !== '' ? (float)$_POST['price'] : null,
            'description' => trim($_POST['description'] ?? ''),
            'lift_capacity' => $_POST['lift_capacity'] ?? null,
            'mast_length' => $_POST['mast_length'] ?? null,
            'engine_type' => $_POST['engine_type'] ?? null,
            'condition' => $_POST['condition'] ?? null, 
            'compatible_models' => $_POST['compatible_models'] ?? null,
            'subtype' => $_POST['subtype'] ?? null,
            'purpose' => $_POST['purpose'] ?? null,
            'image_path' => handle_upload('image')
        ];

        if ($fields['name'] === '') {
            $flash = ['danger', 'Product name is required.'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO products 
                (type, name, model, price, description, lift_capacity, mast_length, engine_type, `condition`, compatible_models, subtype, purpose, image_path) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $type, $fields['name'], $fields['model'], $fields['price'], $fields['description'],
                $fields['lift_capacity'], $fields['mast_length'], $fields['engine_type'], $fields['condition'], 
                $fields['compatible_models'], $fields['subtype'], $fields['purpose'], $fields['image_path']
            ]);
            $flash = ['success', 'Product added successfully.'];
        }
    }

    if ($_POST['action'] === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $img = $pdo->prepare("SELECT image_path FROM products WHERE id=?");
            $img->execute([$id]);
            if ($row = $img->fetch()) {
                $p = $row['image_path'] ? __DIR__ . '/' . $row['image_path'] : null;
                if ($p && is_file($p)) @unlink($p);
            }
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            $flash = ['success', "Product #$id deleted."];
        }
    }

    if ($_POST['action'] === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE products SET active = 1 - active WHERE id=?")->execute([$id]);
            $flash = ['success', "Product #$id status updated."];
        }
    }

    if ($_POST['action'] === 'ticket_status') {
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $new = $_POST['new_status'] ?? 'in_progress';
        
        $valid_statuses = ['open','in_progress','completed','cancelled']; 
        
        if ($tid && in_array($new, $valid_statuses, true)) {
            $stmt = $pdo->prepare("UPDATE service_requests SET status=? WHERE id=?");
            $stmt->execute([$new, $tid]);
            $flash = ['success', "Ticket #$tid status updated to $new."];
        }
    }
    
    // --- NEW ACTION: ASSIGN TECHNICIAN ---
    if ($_POST['action'] === 'assign_technician') {
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $tech_id = (int)($_POST['technician_id'] ?? 0);
        
        if ($tid > 0 && $tech_id > 0) {
            // Update technician_id and set status to 'in_progress'
            $stmt = $pdo->prepare("UPDATE service_requests SET technician_id=?, status='in_progress' WHERE id=?");
            $stmt->execute([$tech_id, $tid]);
            $flash = ['success', "Ticket #$tid assigned to Tech #$tech_id and status set to 'In Progress'."];
        } elseif ($tid > 0 && $tech_id === 0) {
             // Option to manually unassign
            $stmt = $pdo->prepare("UPDATE service_requests SET technician_id=NULL WHERE id=?");
            $stmt->execute([$tid]);
            $flash = ['success', "Ticket #$tid successfully unassigned."];
        } else {
            $flash = ['danger', 'Invalid ticket or technician ID for assignment.'];
        }
    }
    // --- END NEW ACTION ---

    if ($_POST['action'] === 'add_technician') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($name === '' || $email === '' || $password === '') {
            $flash = ['danger', 'Name, Email, and Password are required for a new technician.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash = ['danger', 'Invalid email format.'];
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO staff (name, email, password, role) VALUES (?, ?, ?, 'technician')");
                $stmt->execute([$name, $email, $hashed_password]);
                $flash = ['success', 'New technician added successfully.'];
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $flash = ['danger', 'Error: A technician with this email already exists.'];
                } else {
                    $flash = ['danger', 'An unexpected error occurred while adding the technician.'];
                }
            }
        }
    }
    
    if ($flash) {
        // Adjust redirect hash for the service tab
        $hash = (in_array($_POST['action'], ['assign_technician', 'ticket_status'])) ? '#service' : (($_POST['action'] === 'add_technician') ? '#technician' : '');
        header("Location: backoffice.php?flash=" . urlencode($flash[0]) . "&msg=" . urlencode($flash[1]) . $hash);
        exit;
    }
}

if (isset($_GET['flash']) && isset($_GET['msg'])) {
    $flash = [$_GET['flash'], $_GET['msg']];
}


function fetch_products(PDO $pdo, $type) {
    $stmt = $pdo->prepare("SELECT id, type, name, model, price, description, lift_capacity, mast_length, engine_type, `condition`, compatible_models, subtype, purpose, image_path, active FROM products WHERE type=? ORDER BY id DESC");
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

$rowsFork  = fetch_products($pdo, 'forklifts');
$rowsPal   = fetch_products($pdo, 'pallets');
$rowsParts = fetch_products($pdo, 'parts');
$rowsAcc   = fetch_products($pdo, 'accessories');


// PHP logic for technician table
$action = $_POST['action'] ?? '';

// FETCH TECHNICIANS
$stmt = $pdo->query("
    SELECT s.id, s.name, s.email,
           COALESCE(COUNT(sr.id),0) AS closed_tickets
    FROM staff s
    LEFT JOIN service_requests sr
         ON sr.technician_id = s.id
        AND sr.status = 'completed'
    WHERE s.role = 'technician'
    GROUP BY s.id, s.name, s.email
    ORDER BY s.name ASC
");

$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add extra fields
foreach ($technicians as &$t) {
    $t['off_days'] = [];
    $t['motivation_points'] = $t['closed_tickets'];
}
unset($t);

// Add off days
$stmt = $pdo->query("SELECT technician_id, off_date FROM technician_off_days");
while ($row = $stmt->fetch()) {
    foreach ($technicians as &$t) {
        if ($t['id'] == $row['technician_id']) {
            $t['off_days'][] = $row['off_date'];
        }
    }
}
unset($t);

// UPDATE TECHNICIAN
if ($action === 'update_technician') {

    $techId = $_POST['technician_id'] ?? 0;
    if (!$techId) { die("Invalid technician ID"); }

    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = trim($_POST['password']);

    if ($pass === "") {
        $stmt = $pdo->prepare("
            UPDATE staff
            SET name = ?, email = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $techId]);

    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE staff
            SET name = ?, email = ?, password = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $hash, $techId]);
    }
    header("Location: backoffice.php?updated=1");
    exit;
}

// DELETE TECHNICIAN
if ($action === 'delete_technician') {

    $techId = $_POST['technician_id'] ?? 0;
    if (!$techId) { die("Invalid technician ID"); }

    $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->execute([$techId]);

    header("Location: backoffice.php?deleted=1");
    exit;
}

$clients = $pdo->query("
    SELECT id, username, email, loyalty_points 
    FROM users 
    ORDER BY username ASC
")->fetchAll();

$stats = [
    'fork'  => (int)$pdo->query("SELECT COUNT(*) c FROM products WHERE type='forklifts' AND active=1")->fetch()['c'],
    'pal'   => (int)$pdo->query("SELECT COUNT(*) c FROM products WHERE type='pallets' AND active=1")->fetch()['c'],
    'parts' => (int)$pdo->query("SELECT COUNT(*) c FROM products WHERE type='parts' AND active=1")->fetch()['c'],
    'acc'   => (int)$pdo->query("SELECT COUNT(*) c FROM products WHERE type='accessories' AND active=1")->fetch()['c'],
    'sum'   => (float)($pdo->query("SELECT COALESCE(SUM(price),0) s FROM products WHERE active=1")->fetch()['s']),
    'open_tickets' => (int)$pdo->query("SELECT COUNT(*) c FROM service_requests WHERE status IN ('open','in_progress')")->fetch()['c'],
];

// Fetch all service requests with client and product info
// Product & Service requests
$requests = $pdo->query("
    SELECT 
        sr.*,
        u.username,
        sr.request_type AS request_type,

        -- product details
        p.name AS product_name,
        p.type AS product_type,

        -- product quote
        qp.id     AS product_quote_id,
        qp.amount AS product_quote_amount,
        qp.status AS product_quote_status,

        -- service quote
        qs.id     AS service_quote_id,
        qs.amount AS service_quote_amount,
        qs.status AS service_quote_status,

        -- technician
        s.name AS technician_name

    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id

    LEFT JOIN products p 
        ON sr.product_id = p.id 
        AND sr.request_type = 'product'

    LEFT JOIN quotes qp 
        ON qp.product_request_id = sr.id
        AND sr.request_type = 'product'

    LEFT JOIN quotes qs 
        ON qs.service_id = sr.id
        AND sr.request_type = 'service'

    LEFT JOIN staff s 
        ON sr.technician_id = s.id

    ORDER BY sr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);


// Service tickets with assigned technician
$tickets = $pdo->query("
  SELECT 
      st.id, st.open_date, st.description AS issue_text, st.status, st.technician_id,
      u.username AS client_name,
      s.name AS technician_name  
  FROM service_tickets st
  JOIN users u ON st.user_id = u.id 
  LEFT JOIN staff s ON st.technician_id = s.id
  ORDER BY st.id DESC 
  LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = "Backoffice Management - Abbas Forklifts";

$ticketStatusMap = [
    'open' => ['text' => 'New/Open', 'class' => 'badge bg-danger'],
    'in_progress' => ['text' => 'In Progress', 'class' => 'badge bg-primary'],
    'closed' => ['text' => 'Closed', 'class' => 'badge bg-success'],
    'cancelled' => ['text' => 'Cancelled', 'class' => 'badge bg-secondary'],
];

// Handle actions for service requests

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? '';
    $reqId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

    // Load request & type (fail fast)
    $stmt = $pdo->prepare("SELECT id, user_id, request_type, status FROM service_requests WHERE id = ?");
    $stmt->execute([$reqId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        $flash = ['danger', 'Invalid request ID.'];
    } else {
        $userId = $req['user_id'];
        $rtype  = $req['request_type']; // 'product' or 'service'

        // ---------- SEND QUOTE ----------
        if ($action === 'send_quote') {
            $amount = isset($_POST['quote_amount']) ? (float)$_POST['quote_amount'] : 0.0;
        
            if ($amount <= 0) {
                $flash = ['danger', 'Please provide a valid quote amount.'];
            } else {
                if ($rtype === 'product') {
                    $check = $pdo->prepare("SELECT id, status FROM quotes WHERE product_request_id = ?");
                    $check->execute([$reqId]);
                    $existing = $check->fetch(PDO::FETCH_ASSOC);
        
                    if ($existing) {
                        $stmt = $pdo->prepare("UPDATE quotes SET amount = ?, status = 'pending', issue_date = CURDATE() WHERE id = ?");
                        $stmt->execute([$amount, $existing['id']]);
                        $quoteId = $existing['id'];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO quotes (user_id, product_request_id, amount, status, issue_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                        $stmt->execute([$userId, $reqId, $amount]);
                        $quoteId = $pdo->lastInsertId();
                    }
                    // Keep service_requests.status in sync
                    $pdo->prepare("UPDATE service_requests SET status = 'pending' WHERE id = ?")->execute([$reqId]);
        
                } else { // service quote
                    $check = $pdo->prepare("SELECT id, status FROM quotes WHERE service_id = ?");
                    $check->execute([$reqId]);
                    $existing = $check->fetch(PDO::FETCH_ASSOC);
        
                    if ($existing) {
                        $stmt = $pdo->prepare("UPDATE quotes SET amount = ?, status = 'pending', issue_date = CURDATE() WHERE id = ?");
                        $stmt->execute([$amount, $existing['id']]);
                        $quoteId = $existing['id'];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO quotes (user_id, service_id, amount, status, issue_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                        $stmt->execute([$userId, $reqId, $amount]);
                        $quoteId = $pdo->lastInsertId();
                    }
        
                    // Update service request status
                    $pdo->prepare("UPDATE service_requests SET status = 'completed' WHERE id = ?")->execute([$reqId]);
                }
                        $flash = ['success', 'Quote created/updated.'];
            }
        }
        
        // --- NEW: Handle status change to paid ---
        if ($action === 'update_quote_status') {
            $quoteId = $_POST['quote_id'] ?? 0;
            $newStatus = $_POST['status'] ?? '';
        
            if (!$quoteId || !$newStatus) die("Invalid request.");
        
            // Update quote status
            $stmt = $pdo->prepare("UPDATE quotes SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $quoteId]);
        
            // If quote is paid, set service_requests.status = 'completed'
            if ($newStatus === 'paid') {
                // Check if it's a service or product quote
                $stmt = $pdo->prepare("SELECT service_id, product_request_id FROM quotes WHERE id = ?");
                $stmt->execute([$quoteId]);
                $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        
                if ($quote['service_id']) {
                    $pdo->prepare("UPDATE service_requests SET status = 'completed' WHERE id = ?")->execute([$quote['service_id']]);
                } elseif ($quote['product_request_id']) {
                    $pdo->prepare("UPDATE service_requests SET status = 'completed' WHERE id = ?")->execute([$quote['product_request_id']]);
                }
            }
        
            $flash = ['success', 'Quote status updated.'];
            header("Location: backoffice.php?tab=service&flash=" . urlencode($flash[0]) . "&msg=" . urlencode($flash[1]));
            exit;
        }
        

        // ---------- CANCEL REQUEST ----------
        elseif ($action === 'cancel_request') {
            // For products -> update quotes.product_request_id
            if ($rtype === 'product') {
                $pdo->prepare("UPDATE quotes SET status = 'cancelled' WHERE product_request_id = ?")->execute([$reqId]);
            } else {
                $pdo->prepare("UPDATE quotes SET status = 'cancelled' WHERE service_id = ?")->execute([$reqId]);
            }
            $pdo->prepare("UPDATE service_requests SET status = 'cancelled' WHERE id = ?")->execute([$reqId]);
            $flash = ['danger', 'Request cancelled.'];
        }

        // ---------- NOT AVAILABLE ----------
        elseif ($action === 'not_available') {
            if ($rtype === 'product') {
                $pdo->prepare("UPDATE quotes SET status = 'cancelled' WHERE product_request_id = ?")->execute([$reqId]);
                $pdo->prepare("UPDATE service_requests SET status = 'not_available' WHERE id = ?")->execute([$reqId]);
                $flash = ['secondary', 'Product marked not available.'];
            } else {
                // for services, mark request not available
                $pdo->prepare("UPDATE service_requests SET status = 'not_available' WHERE id = ?")->execute([$reqId]);
                $flash = ['secondary', 'Service marked not available.'];
            }
        }
    }

    // redirect back to service tab with flash
    if ($flash) {
        header("Location: backoffice.php?tab=service&updated_id={$reqId}&flash=" 
       . urlencode($flash[0]) . "&msg=" . urlencode($flash[1]));
            exit;

        
    }
}

// ================== END STATUS UPDATE ==================

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body { margin: 0; padding-top: 0 !important; font-family: Arial, sans-serif; background-color: #121212; color: #fff; }
        label { font-weight: bold; }
        .background-logo {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: url('imgs/logo.webp') center center no-repeat;
            background-size: 400px; opacity: .05; z-index: -1;
        }
        .navbar-custom { background-color: #1f1f1f; }
        .card { background-color: #1e1e1e; color: #fff; border: none; transition: transform .2s; }
        .card:hover { transform: scale(1.02); }
        .btn-orange { background-color: #ff6600; color: #fff; border: none; }
        .btn-orange:hover { background-color: #e65c00; }
        .tab-content { margin-top: 30px; }
        .img-thumb { width: 64px; height: 48px; object-fit: cover; border-radius: 6px; }
        .table-dark td, .table-dark th { vertical-align: middle; }
    </style>
</head>
<body>

<div class="background-logo"></div>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <img src="imgs/logo.webp" alt="Logo" height="50">
            Abbas Forklifts - Backoffice
        </a>
        <a class="btn btn-sm btn-orange ms-auto" href="?logout=1">🔐 Logout</a>
    </div>
</nav>

<main class="container mt-5 pt-5">
    <h1 class="mb-4 text-center">Central Management System</h1>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs nav-fill" id="adminTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#forklifts" type="button">Forklifts</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pallets" type="button">Pallets</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#parts" type="button">Parts</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#accessories" type="button">Accessories</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#service" type="button">Service Tickets</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#technician" type="button">Technicians</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#client" type="button">Clients</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reports" type="button">Reports</button></li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        
        <?php 
        $productTabs = [
            'forklifts' => ['rows' => $rowsFork, 'title' => 'Forklift Inventory'],
            'pallets' => ['rows' => $rowsPal, 'title' => 'Pallets & Jacks Inventory'],
            'parts' => ['rows' => $rowsParts, 'title' => 'Parts Inventory'],
            'accessories' => ['rows' => $rowsAcc, 'title' => 'Accessories Inventory'],
        ];
        
        $isFirst = true;
        foreach ($productTabs as $type => $data):
        ?>
        <div class="tab-pane fade <?= $isFirst ? 'show active' : '' ?>" id="<?= $type ?>" role="tabpanel">
            <h2 class="d-flex justify-content-between align-items-center">
                <?= $data['title'] ?>
                <button class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#addProductModal" data-type="<?= $type ?>">+ Add New</button>
            </h2>
            <div class="table-responsive mt-3">
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Model</th>
                            <th>Price</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data['rows']) > 0): ?>
                            <?php foreach ($data['rows'] as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <?php if ($row['image_path'] && file_exists(__DIR__ . '/' . $row['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="Product" class="img-thumb">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['model']) ?></td>
                                <td>$<?= number_format($row['price'], 2) ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $row['active'] ? 'btn-success' : 'btn-danger' ?>">
                                            <?= $row['active'] ? 'YES' : 'NO' ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete product #<?= $row['id'] ?>?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center">No <?= $type ?> found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php 
        $isFirst = false; 
        endforeach; 
        ?>
  <!-- MERGED SERVICE REQUESTS + SERVICE TICKETS TAB -->
        <div class="tab-pane fade" id="service" role="tabpanel">
        
        <h3 class="mt-3 text-warning">Service Tickets & Requests</h3>
        
        <div class="table-responsive mt-3">
        <table class="table table-dark table-striped table-hover align-middle shadow-sm rounded">
        
        <thead class="bg-dark text-warning">
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Client</th>
            <th>Technician</th>
            <th>Type</th>
            <th>Details</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        
        <tbody>
        
        <?php
        // 1. Tag each with a "source" so we know if it's service or request
        foreach ($requests as &$r) { $r['source'] = 'request'; }
        foreach ($tickets as &$t)  { $t['source'] = 'ticket'; }
        
        // 2. Merge for display only
        $merged = array_merge($requests, $tickets);
        
        // 3. Sort by newest first
        usort($merged, fn($a, $b) => strtotime(($b['created_at'] ?? $b['open_date'])) - strtotime(($a['created_at'] ?? $a['open_date'])));
        ?>
        
        <?php foreach ($merged as $item): ?>
        
        <tr>
        
        <!-- ID -->
        <td><?= $item['id'] ?></td>
        
        <!-- DATE -->
        <td><?= htmlspecialchars($item['created_at'] ?? date('Y-m-d', strtotime($item['open_date']))) ?></td>
        
        <!-- CLIENT -->
        <td><?= htmlspecialchars($item['username'] ?? $item['client_name']) ?></td>
        <td>
             <?= !empty($item['technician_name']) 
             ? htmlspecialchars($item['technician_name']) 
           : '<span class="text-muted" style="color:white">Unassigned</span>' ?>
         </td>

        <!-- TYPE -->
        <td>
            <?php if ($item['request_type'] === 'product'): ?>
              <span class="badge bg-info">Product Request</span>
            <?php else: ?>
              <span class="badge bg-primary">Service Request</span>
             <?php endif; ?>
        </td>

        
        <!-- DETAILS -->
        <td class="text-light">
          <?php if ($item['source'] === 'request'): ?>
              <strong><?= ucfirst($item['request_type']) ?></strong><br>
              <?= htmlspecialchars($item['product_name'] ?? '-') ?>
              <small class="text-muted">(<?= $item['product_type'] ?? '' ?>)</small><br>
              <?= htmlspecialchars($item['description']) ?>
          <?php else: ?>
              <?= htmlspecialchars(substr($item['issue_text'],0,80)) ?><?= strlen($item['issue_text'])>80?'...':'' ?>
          <?php endif; ?>
        </td>
        
        <!-- STATUS -->
        <td>
        <?php
        // determine display status
        if ($item['source'] === 'request') {
            if ($item['request_type'] === 'product') {
                $finalStatus = $item['product_quote_status'] ?? $item['status'];
                $quoteId = $item['product_quote_id'] ?? null;
            } else { // service request
                // prefer service_quote_status if present else request.status
                $finalStatus = $item['service_quote_status'] ?? $item['status'];
                $quoteId = $item['service_quote_id'] ?? null;
            }
        } else {
            // ticket row - use ticket status
            $finalStatus = $item['status'] ?? 'unknown';
            $quoteId = null;
        }
        
        $badgeClass =
            ($finalStatus=='completed'?'success':
            ($finalStatus=='cancelled'?'danger':
            ($finalStatus=='not_available'?'secondary':
            ($finalStatus=='pending'?'info':
            ($finalStatus=='paid'?'success':'warning')))));
        
        ?>
        <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($finalStatus) ?></span>
        </td>
        
        <!-- ACTIONS -->
        <td>

<?php if ($item['source'] === 'request'): ?>

    <?php if ($item['request_type'] === 'product'): ?>
        <?php
        // --- FIX: Fetch latest quote for product request ---
        $stmtQuote = $pdo->prepare("SELECT status, amount FROM quotes WHERE product_request_id = ? ORDER BY id DESC LIMIT 1");
        $stmtQuote->execute([$item['id']]);
        $quote = $stmtQuote->fetch(PDO::FETCH_ASSOC);
        $quoteStatus = $quote['status'] ?? null;
        $finalStatus = ($quoteStatus === 'paid') ? 'closed' : $item['status'];
        ?>

        <?php if ($finalStatus === 'closed'): ?>
            <span class="badge bg-success w-100 text-center">Closed</span>
        <?php else: ?>
            <?php $quoteAmount = $item['product_quote_amount'] ?? ''; ?>

            <!-- Send / Update Quote -->
            <form method="POST" class="mb-1 quote-form" data-request-id="<?= $item['id'] ?>">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="send_quote">
                <input type="hidden" name="request_id" value="<?= $item['id'] ?>">
                <div class="input-group input-group-sm mb-1">
                    <input type="number" 
                           name="quote_amount" 
                           step="0.01" 
                           class="form-control form-control-sm bg-dark text-light"
                           value="<?= htmlspecialchars($quoteAmount) ?>"
                           placeholder="Enter quote"
                           required>
                    <button class="btn btn-sm btn-success quote-btn" type="submit">
                        <?= empty($quote) ? 'Send Quote' : 'Update Quote' ?>
                    </button>
                </div>
            </form>

            <!-- NOT AVAILABLE -->
            <form method="POST" class="mb-1">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="not_available">
                <input type="hidden" name="request_id" value="<?= $item['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary w-100">Not Available</button>
            </form>

            <!-- CANCEL -->
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="cancel_request">
                <input type="hidden" name="request_id" value="<?= $item['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger w-100">Cancel</button>
            </form>

        <?php endif; ?>

    <?php else: // --- SERVICE REQUEST ACTIONS --- ?>
        <?php
        // --- FIX: Fetch latest quote for service request ---
        $stmtQuote = $pdo->prepare("SELECT status, amount FROM quotes WHERE service_id = ? ORDER BY id DESC LIMIT 1");
        $stmtQuote->execute([$item['id']]);
        $quote = $stmtQuote->fetch(PDO::FETCH_ASSOC);
        $quoteStatus = $quote['status'] ?? null;
        $finalStatus = ($quoteStatus === 'paid') ? 'closed' : $item['status'];
        ?>

        <?php if ($finalStatus === 'closed'): ?>
            <!-- --- FIX: Closed status hides all buttons --- -->
            <span class="badge bg-success w-100 text-center">Closed</span>
        <?php else: ?>

            <!-- --- FIX: Assign / Re-assign Tech button only if quote not sent --- -->
            <?php if (!$quoteStatus): ?>
                <button 
                    class="btn btn-sm btn-warning mb-1 w-100"
                    data-bs-toggle="modal"
                    data-bs-target="#assignTechModal"
                    data-ticket-id="<?= $item['id'] ?>"
                    data-current-tech="<?= $item['technician_id'] ?? 0 ?>"
                    <?= in_array($finalStatus, ['cancelled', 'not_available']) ? 'disabled' : '' ?> >
                    <?= !empty($item['technician_id']) ? 'Re-assign Tech' : 'Assign Tech' ?>
                </button>
            <?php endif; ?>

            <!-- --- FIX: Send / Update Quote button only if completed and no quote yet --- -->
            <?php if ($item['status'] === 'completed' && !$quoteStatus): ?>
                <form method="POST" class="mt-1">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="send_quote">
                    <input type="hidden" name="request_id" value="<?= $item['id'] ?>">
                    <div class="input-group input-group-sm">
                        <input type="number" 
                               name="quote_amount" 
                               step="0.01" 
                               class="form-control form-control-sm bg-dark text-light"
                               placeholder="Enter quote"
                               required>
                        <button class="btn btn-sm btn-success" type="submit">Send Quote</button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- --- CANCEL REQUEST BUTTON --- -->
            <?php if (!in_array($finalStatus, ['cancelled', 'not_available'])): ?>
                <form method="POST" class="mt-1">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel_request">
                    <input type="hidden" name="request_id" value="<?= $item['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger w-100">Cancel Request</button>
                </form>
            <?php else: ?>
                <button class="btn btn-sm btn-secondary w-100 mt-1" disabled>Request Cancelled</button>
            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>

<?php else: // --- TICKET ROW --- ?>
    <!-- SERVICE TICKET ACTIONS -->
    <a href="details.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-info mb-1 w-100">View / Update</a>

    <button 
        class="btn btn-sm btn-warning w-100"
        data-bs-toggle="modal"
        data-bs-target="#assignTechModal"
        data-ticket-id="<?= $item['id'] ?>"
        data-current-tech="<?= $item['technician_id'] ?? 0 ?>">
        <?= $item['technician_id'] ? 'Re-assign Tech' : 'Assign Tech' ?>
    </button>

<?php endif; ?>

</td>    
        </tr>
        
        <?php endforeach; ?>
        
        </tbody>
    </table>
</div>
</div>
        
<!-- technician column -->
<div class="tab-pane fade" id="technician" role="tabpanel">
    <h2 class="d-flex justify-content-between align-items-center">
        Technician Motivation Program
        <button class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#addTechnicianModal">
            + Add New
        </button>
    </h2>

    <div class="alert alert-info">
        Motivation Points are calculated automatically: <strong>1 Point for every successful Closed Service Tickets.</strong>
    </div>

    <div class="table-responsive mt-3">
        <table class="table table-dark table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Closed Tickets</th>
                    <th>Motivation Points</th>
                    <th>Actions</th> <!-- New Actions Column -->
                </tr>
            </thead>
            <tbody>
                <?php if (count($technicians) > 0): ?>
                    <?php foreach ($technicians as $tech): ?>
                        <tr>
                            <td><?= $tech['id'] ?></td>
                            <td><?= htmlspecialchars($tech['name']) ?></td>
                            <td><?= htmlspecialchars($tech['email']) ?></td>
                            <td><?= $tech['closed_tickets'] ?></td>
                            <td class="text-warning fw-bold"><?= $tech['motivation_points'] ?></td>
                            <td>
                                <!-- Update Button -->
                                <button 
                                    class="btn btn-sm btn-info mb-1 w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateTechnicianModal"
                                    data-tech-id="<?= $tech['id'] ?>"
                                    data-tech-name="<?= htmlspecialchars($tech['name'], ENT_QUOTES) ?>"
                                    data-tech-email="<?= htmlspecialchars($tech['email'], ENT_QUOTES) ?>"
                                >
                                    Update
                                </button>

                                <!-- Delete Button -->
                                <form method="POST" class="mb-0">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_technician">
                                    <input type="hidden" name="technician_id" value="<?= $tech['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger w-100"
                                        onclick="return confirm('Are you sure you want to delete this technician?');">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No technicians found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Update Technician Modal -->
<div class="modal fade" id="updateTechnicianModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update_technician">
            <input type="hidden" name="technician_id" id="updateTechId">

            <div class="modal-header">
                <h5 class="modal-title">Update Technician</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label for="updateTechName" class="form-label">Name</label>
                    <input type="text" class="form-control" id="updateTechName" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="updateTechEmail" class="form-label">Email</label>
                    <input type="email" class="form-control" id="updateTechEmail" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="updateTechPassword" class="form-label">Password (leave blank to keep unchanged)</label>
                    <input type="password" class="form-control" id="updateTechPassword" name="password">
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

 <!-- Clients column -->
   <div class="tab-pane fade" id="client" role="tabpanel">
        <h2>Client Loyalty Program</h2>
            <div class="table-responsive mt-3">
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Loyalty Points</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                     <tbody>
                        <?php if (count($clients) > 0): ?>
                            <?php foreach ($clients as $client): ?>
                            <tr id="client_row_<?= $client['id'] ?>">
                                <td><?= $client['id'] ?></td>
                                <td><?= htmlspecialchars($client['username']) ?></td>
                                <td><?= htmlspecialchars($client['email']) ?></td>
                                <td class="text-info fw-bold"><?= (int)$client['loyalty_points'] ?></td>

                                <!-- DELETE BUTTON -->
                                <td>
                                    <button 
                                        class="btn btn-danger btn-sm delete-client-btn"
                                        data-client-id="<?= $client['id'] ?>"
                                        data-client-name="<?= htmlspecialchars($client['username']) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteClientModal"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="5" class="text-center">No clients found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                </table>
            </div>
        </div>
<!-- DELETE CLIENT MODAL -->
<div class="modal fade" id="deleteClientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_client">
                <input type="hidden" name="client_id" id="deleteClientId">

                <div class="modal-body">
                    <p>
                        Are you sure you want to delete client:
                        <strong id="deleteClientName"></strong>?
                    </p>
                    <p class="text-warning mb-0">This action cannot be undone.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger">Delete Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- inventory panel  -->
        <div class="tab-pane fade" id="reports" role="tabpanel">
            <h2>Inventory & Service Summary</h2>
            <div class="row row-cols-1 row-cols-md-3 g-4 mt-3">
                <div class="col">
                    <div class="card p-3">
                        <h5>Total Inventory Value (Active)</h5>
                        <p class="fs-3 text-warning">$<?= number_format($stats['sum'], 2) ?></p>
                    </div>
                </div>
                <div class="col">
                    <div class="card p-3">
                        <h5>Open Service Tickets</h5>
                        <p class="fs-3 text-danger"><?= $stats['open_tickets'] ?></p>
                    </div>
                </div>
                <div class="col">
                    <div class="card p-3">
                        <h5>Active Forklifts</h5>
                        <p class="fs-3 text-success"><?= $stats['fork'] ?></p>
                    </div>
                </div>
                <div class="col">
                    <div class="card p-3">
                        <h5>Active Pallets/Jacks</h5>
                        <p class="fs-3 text-success"><?= $stats['pal'] ?></p>
                    </div>
                </div>
                <div class="col">
                    <div class="card p-3">
                        <h5>Active Parts</h5>
                        <p class="fs-3 text-success"><?= $stats['parts'] ?></p>
                    </div>
                </div>
                <div class="col">
                    <div class="card p-3">
                        <h5>Active Accessories</h5>
                        <p class="fs-3 text-success"><?= $stats['acc'] ?></p>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</main>

<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="type" id="productTypeHidden"> 

                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name*</label><input type="text" name="name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Model</label><input type="text" name="model" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Condition</label><select name="condition" class="form-select"><option>new</option><option>used</option></select></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>

                        <div class="col-md-4"><label class="form-label">Lift Capacity</label><input type="text" name="lift_capacity" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Mast Length</label><input type="text" name="mast_length" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Engine Type</label><input type="text" name="engine_type" class="form-control"></div>
                        
                        <div class="col-md-4"><label class="form-label">Compatible Models</label><input type="text" name="compatible_models" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Subtype</label><input type="text" name="subtype" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Purpose</label><input type="text" name="purpose" class="form-control"></div>
                        
                        <div class="col-12"><label class="form-label">Image</label><input type="file" name="image" class="form-control"></div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-orange">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white">Update Ticket Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ticket_status">
                    <input type="hidden" name="ticket_id" id="ticketIdHidden">
                    
                    <div class="mb-3">
                        <label for="newStatusSelect" class="form-label">New Status</label>
                        <select name="new_status" id="newStatusSelect" class="form-select">
                            <option value="open">New/Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="closed">Closed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-orange">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Get today's date
$today = date('Y-m-d');
?>

<div class="modal fade" id="assignTechModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white">
                    Assign Technician to Ticket <span id="assignTicketIdTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign_technician">
                    <input type="hidden" name="ticket_id" id="assignTicketIdHidden">

                    <div class="mb-3">
                        <label for="technicianSelect" class="form-label text-white">Select Technician</label>
                        <select name="technician_id" id="technicianSelect" class="form-select" required>
                            <option value="0">-- Unassign Ticket (Clear) --</option>

                            <?php foreach ($technicians as $tech): ?>
                                <?php 
                                // Check if tech is off today
                                $isOffToday = in_array($today, $tech['off_days']); 
                                if ($isOffToday) continue; // Skip technicians off today
                                ?>
                                <option value="<?= $tech['id'] ?>">
                                    <?= htmlspecialchars($tech['name']) ?>
                                    <?php if (!empty($tech['off_days'])): ?>
                                        (Off: <?= implode(' | ', $tech['off_days']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-light">
                            Selecting a technician will automatically set the ticket status to "In Progress".
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange">Assign & Start</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addTechnicianModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white">Add New Technician</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_technician">
                    
                    <div class="mb-3">
                        <label for="techName" class="form-label">Full Name*</label>
                        <input type="text" name="name" id="techName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="techEmail" class="form-label">Email*</label>
                        <input type="email" name="email" id="techEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="techPassword" class="form-label">Initial Password*</label>
                        <input type="password" name="password" id="techPassword" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-orange">Create Technician</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JS for Add Product Modal
    const addProductModal = document.getElementById('addProductModal');
    if (addProductModal) {
        addProductModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 
            const productType = button.getAttribute('data-type');
            document.getElementById('productTypeHidden').value = productType;
            this.querySelector('.modal-title').textContent = 'Add New ' + productType.charAt(0).toUpperCase() + productType.slice(1);
        });
    }

    // JS for Edit Ticket Status Modal
    const editTicketModal = document.getElementById('editTicketModal');
    if (editTicketModal) {
        editTicketModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 
            const ticketId = button.getAttribute('data-ticket-id');
            const currentStatus = button.getAttribute('data-current-status');
            
            document.getElementById('ticketIdHidden').value = ticketId;
            document.getElementById('newStatusSelect').value = currentStatus;
        });
    }

    // NEW JS for Assign Technician Modal
    const assignTechModal = document.getElementById('assignTechModal');
    if (assignTechModal) {
        assignTechModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 
            const ticketId = button.getAttribute('data-ticket-id');
            const currentTechId = button.getAttribute('data-current-tech');
            
            document.getElementById('assignTicketIdHidden').value = ticketId;
            document.getElementById('assignTicketIdTitle').textContent = '#' + ticketId;
            document.getElementById('technicianSelect').value = currentTechId; // Select the currently assigned tech
        });
    }
    // END NEW JS

    // JS to activate the correct tab based on URL hash (if available) or default to Forklifts
    document.addEventListener('DOMContentLoaded', function() {
        let defaultTabTarget = '#forklifts';

        if (location.hash) {
            defaultTabTarget = location.hash;
        }
        
        var triggerEl = document.querySelector('button[data-bs-target="' + defaultTabTarget + '"]');
        if (triggerEl) {
            var tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    });

setTimeout(() => {
    let alert = document.querySelector(".alert");
    if (alert) alert.remove();
}, 3000);


document.querySelectorAll('.quote-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // prevent default form submission

        const requestId = form.dataset.requestId;
        const formData = new FormData(form);

        fetch('backoffice.php', {
            method: 'POST',
            body: formData,
        })
        .then(res => res.text()) // or res.json() if you return JSON
        .then(data => {
            // Optional: show a temporary notification
            const btn = form.querySelector('.quote-btn');
            btn.textContent = 'Update Quote'; // change button immediately
            btn.classList.remove('btn-success');
            btn.classList.add('btn-warning');

            // Clear input field if you want
            // form.querySelector('input[name="quote_amount"]').value = '';

            // Optional: show flash for 3s
            const flash = document.createElement('div');
            flash.className = 'alert alert-success';
            flash.style.position = 'fixed';
            flash.style.top = '10px';
            flash.style.right = '10px';
            flash.style.zIndex = 9999;
            flash.textContent = 'Quote sent successfully';
            document.body.appendChild(flash);
            setTimeout(() => flash.remove(), 3000);
        })
        .catch(err => {
            console.error(err);
            alert('Error sending quote.');
        });
    });
});

<!-- Script to populate Update Modal -->

    var updateModal = document.getElementById('updateTechnicianModal');
    updateModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('updateTechId').value = button.getAttribute('data-tech-id');
        document.getElementById('updateTechName').value = button.getAttribute('data-tech-name');
        document.getElementById('updateTechEmail').value = button.getAttribute('data-tech-email');
    });
        
        document.addEventListener("DOMContentLoaded", () => {
            const deleteModal = document.getElementById('deleteClientModal');

            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                const clientId = button.getAttribute('data-client-id');
                const clientName = button.getAttribute('data-client-name');

                document.getElementById('deleteClientId').value = clientId;
                document.getElementById('deleteClientName').textContent = clientName;
            });
        });
</script>

<button id="chatbotButton" class="btn btn-primary rounded-circle"
        style="position: fixed; bottom: 25px; right: 25px; z-index: 9999; width: 60px; height: 60px;">
    🤖
</button>

<div id="chatWindow" class="card shadow-lg"
     style="
        display:none;
        position:fixed;
        bottom:100px;
        right:25px;
        width:350px;
        height:450px;
        z-index:9999;
        background:#ffffff;
        color:#111111;
     ">

    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>AI Customer Assistant</span>
        <div class="d-flex gap-2">
            <button type="button" id="minimizeChat" class="btn btn-sm p-0 text-white" title="Minimize">
                <span style="font-size: 1.5rem; line-height: 0;">&minus;</span>
            </button>
            <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
        </div>
    </div>

    <div class="card-body"
         id="chatMessages"
         style="
            overflow-y:auto;
            height:320px;
            background:#f8f9fa;
            color:#111111;
         ">

        <div class="text-start mb-2 small"
             style="color:#6c757d;">
            Hello! I am Abbas AI. How can I help you today?
        </div>
    </div>

    <div class="p-2 border-top" style="background:#ffffff;">
        <input id="chatInput"
               class="form-control"
               placeholder="Type a message..."
               style="color:#111111;">
    </div>
</div>


<script>
    // Helper to let chatbot.js know who is logged in
    window.CLIENT_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
</script>
<script src="chatbot.js"></script>
</body>
</html>