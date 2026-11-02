<?php
session_start();
require_once __DIR__ . "/db.php";

// --- Handle invoice status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice'])) {
    $invoiceId = (int)$_POST['invoice_id'];
    $newStatus = $_POST['new_status'];

    $validStatuses = ['paid', 'cancelled'];
    if (in_array($newStatus, $validStatuses, true)) {
        $stmt = $pdo->prepare("UPDATE quotes SET status = ?, payment_date = CASE WHEN ? = 'paid' THEN CURDATE() ELSE payment_date END WHERE id = ? AND user_id = ?");
        $stmt->execute([$newStatus, $newStatus, $invoiceId, $_SESSION['user_id']]);
    }

    header("Location: client_area.php");
    exit;
}

$pageTitle = "Client Area - Abbas Forklifts";
$minRedeemPoints = 500; 

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- SECURITY CHECK & DATA FETCHING ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$displayName = $_SESSION['username'] ?? 'Valued Customer';
$loyaltyPoints = $_SESSION['loyalty_points'] ?? 0; 
$canRedeem = $loyaltyPoints >= $minRedeemPoints;


$stmtEquipment = $pdo->prepare("SELECT model, status FROM inventory WHERE user_id = ? ORDER BY status DESC, model ASC");
$stmtEquipment->execute([$userId]);
$equipment = $stmtEquipment->fetchAll(PDO::FETCH_ASSOC);

// Fix: Read from service_requests, map 'created_at' to 'open_date'
$stmtTickets = $pdo->prepare("SELECT id, description, created_at as open_date, status FROM service_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmtTickets->execute([$userId]);
$tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

$stmtInvoices = $pdo->prepare("SELECT id, amount, status, issue_date FROM quotes WHERE user_id = ? ORDER BY issue_date DESC LIMIT 5");
$stmtInvoices->execute([$userId]);
$invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $userId = $_SESSION['user_id'];
    $oldPass = $_POST['old_pass'];
    $newPass = $_POST['new_pass'];
    $confirmPass = $_POST['confirm_pass'];

    // Basic validation
    if ($newPass !== $confirmPass) {
        $msg = "New passwords do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($oldPass, $user['password'])) {
            $hashedNewPass = password_hash($newPass, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashedNewPass, $userId]);
            $msg = "Password updated successfully!";
        } else {
            $msg = "Incorrect current password.";
        }
    }
    $_SESSION['status_message'] = $msg;
    header("Location: client_area.php?update=success"); 
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
   
</head>
<body class="page-bg-client page-with-overlay">

<div class="background-logo">
    <img src="imgs/logo.webp" alt="Background Logo" />
</div>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="imgs/logo.webp" alt="Logo" height="60">
      <span class="fw-bold">Abbas Forklifts</span>
    </a>

    <button class="navbar-toggler d-lg-none" id="navbarToggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <button type="button" class="btn-close btn-close-white d-lg-none" id="navbarClose" style="display: none;"></button>

    <div class="collapse navbar-collapse" id="navbarNav">
  <ul class="navbar-nav me-auto gap-lg-3"> 
    <!-- <li class="nav-item">
        <a class="nav-link <?= ($pageTitle === 'Home') ? 'active' : '' ?>" href="index.php">Home</a>
    </li> -->
    
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle pe-3" href="#" id="catalogDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        Catalog
      </a>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="catalog_forklifts.php">Forklifts</a></li>
        <li><a class="dropdown-item" href="catalog_parts.php">Spare Parts</a></li>
        <li><a class="dropdown-item" href="catalog_pallets.php">Pallet Jacks</a></li>
        <li><a class="dropdown-item" href="catalog_hoses.php">Hydraulic Hoses</a></li>
        <li><a class="dropdown-item" href="catalog_accessories.php">Accessories</a></li>
      </ul>
    </li>

    <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
    <li class="nav-item"><a class="nav-link" href="troubleshooting.php">Troubleshooting</a></li>
    <li class="nav-item"><a class="nav-link" href="contact.php">Contact Us</a></li>
    <li class="nav-item"><a class="nav-link" href="?logout=1">Logout</a></li>
  </ul>
</div>
  </div>
</nav>

<div class="d-flex justify-content-end mb-3 mt-4">
    <button class="btn btn-outline-light btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#settingsModal">
        ⚙️ Settings
    </button>
</div>


<main class="container mt-5 pt-5">
    <h2 class="text-white text-center mb-4">Hello, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> 👋</h2>

    <div class="card service-glass shadow-lg p-3 mb-5 text-white">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="mb-0">✨ Loyalty Points: <span class="fw-bold"><?= (int)$loyaltyPoints ?></span></h4>
            <div>
                <?php if ($canRedeem): ?>
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#actionModal"
                            data-modal-title="Redeem Loyalty Points" 
                            data-modal-body="Form to redeem <?= $loyaltyPoints ?> points for a discount!">
                        🎁 Redeem Points!
                    </button>
                <?php else: ?>
                    <button class="btn btn-outline-warning me-2" disabled>
                        You need <?= $minRedeemPoints - $loyaltyPoints ?> more points to redeem!
                    </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#actionModal"
                        data-modal-title="Loyalty Points Information" 
                        data-modal-body="Our loyalty program rewards you for every service and purchase. Every 500 points are worth a significant discount.">
                     What is this?
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card service-box service-glass shadow-lg p-3">
                <h5 class="text-white">Your Equipment</h5>
                <ul class="list-group list-group-flush">
                    <?php if (count($equipment) > 0): ?>
                        <?php foreach ($equipment as $item): 
                            $statusClass = $item['status'] === 'active' ? 'bg-success' : 'bg-warning text-dark';
                            $statusText = $item['status'] === 'active' ? 'Active' : 'In Service';
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($item['model']) ?>
                                <span class="badge rounded-pill <?= $statusClass ?>"><?= $statusText ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center">No registered equipment found.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card service-box service-glass shadow-lg p-3">
                <h5 class="text-white">Recent Service Requests</h5>
                <ul class="list-group list-group-flush">
                    <?php if (count($tickets) > 0): ?>
                        <?php foreach ($tickets as $ticket): 
                            $badgeClass = $ticket['status'] === 'open' ? 'bg-danger' : ($ticket['status'] === 'in_progress' ? 'bg-primary' : 'bg-secondary');
                            $statusText = ['open' => 'Open', 'in_progress' => 'In Progress', 'closed' => 'Closed', 'cancelled' => 'Cancelled'][$ticket['status']] ?? $ticket['status'];
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="fw-bold">#<?= $ticket['id'] ?>:</span> <?= htmlspecialchars($ticket['description']) ?>
                                </span>
                                <span class="badge rounded-pill <?= $badgeClass ?>"><?= $statusText ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center">No service requests found.</li>
                    <?php endif; ?>
                </ul>
                <button class="btn btn-sm btn-secondary mt-2 w-100" data-bs-toggle="modal" data-bs-target="#actionModal" 
                        data-modal-title="Open New Service Request" 
                        data-modal-body="<p>Please fill out the form to open a request:</p><form><div class='mb-3'><label for='desc' class='form-label'>Issue Description</label><textarea class='form-control' id='desc'></textarea></div><button type='submit' class='btn btn-primary'>Send Request</button></form>">
                    Open New Request
                </button>
            </div>
        </div>

        <div class="card service-box service-glass shadow-lg p-3">
            <h5 class="text-white">Recent Invoices</h5>
            <ul class="list-group list-group-flush">
                <?php if (count($invoices) > 0): ?>
                    <?php foreach ($invoices as $invoice): 
                        $badgeClass = [
                            'paid' => 'bg-success',
                            'pending' => 'bg-warning text-dark',
                            'overdue' => 'bg-danger',
                            'cancelled' => 'bg-secondary'
                        ][$invoice['status']] ?? 'bg-info';
                        
                        $statusText = [
                            'paid' => 'Paid',
                            'pending' => 'Pending',
                            'overdue' => 'Overdue',
                            'cancelled' => 'Cancelled'
                        ][$invoice['status']] ?? 'Other';
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                            <span>
                                <span class="fw-bold">#<?= $invoice['id'] ?></span> - ₪<?= number_format($invoice['amount'], 2) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($invoice['issue_date']) ?></small>
                            </span>
                            <div class="d-flex gap-1 mt-2">
                                <button class="btn btn-info btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#invoiceDetailsModal"
                                        data-invoice-id="<?= $invoice['id'] ?>">
                                    📄 Details
                                </button>
                                <?php if ($invoice['status'] === 'pending'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                        <input type="hidden" name="update_invoice" value="1">

                                        <button type="submit" name="new_status" value="paid" class="btn btn-success btn-sm">Mark as Paid</button>
                                        <button type="submit" name="new_status" value="cancelled" class="btn btn-danger btn-sm">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group-item text-center">No invoices found.</li>
                <?php endif; ?>
            </ul>

            <button class="btn btn-sm btn-secondary mt-2 w-100" data-bs-toggle="modal" data-bs-target="#actionModal" 
                    data-modal-title="All Invoices" 
                    data-modal-body="<p>A table displaying all your historical invoices, including download options.</p><p>...</p>">
                Show All
            </button>
        </div>
    </div>
</main>

<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel"></h5>
                <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Account Settings</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <button class="btn btn-primary w-100 mb-2"
                        data-bs-toggle="modal"
                        data-bs-target="#passwordModal"
                        data-bs-dismiss="modal">
                    🔒 Change Password
                </button>
                <button class="btn btn-secondary w-100"
                        data-bs-toggle="modal"
                        data-bs-target="#detailsModal"
                        data-bs-dismiss="modal">
                    📝 Update Personal Details
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_password">
                
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="password" name="old_pass" class="form-control mb-2" placeholder="Current Password" required>
                    <input type="password" name="new_pass" class="form-control mb-2" placeholder="New Password" required>
                    <input type="password" name="confirm_pass" class="form-control mb-2" placeholder="Confirm New Password" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="details.php">
                <div class="modal-header">
                    <h5 class="modal-title">Update Personal Details</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" name="company" class="form-control mb-2" placeholder="Company Name">
                    <input type="email" name="email" class="form-control mb-2" placeholder="Contact Email">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="invoiceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h5 class="modal-title">Invoice Details</h5>
            </div>
            <div class="modal-body" id="invoice-details-content">
                Loading data...
            </div>
        </div>
    </div>
</div>

<button id="chatbotButton" class="btn btn-primary rounded-circle"
        style="position: fixed; bottom: 25px; right: 25px; z-index: 9999; width: 60px; height: 60px;">
    🤖
</button>

<div id="chatWindow" class="card shadow-lg"
     style="display:none; position:fixed; bottom:100px; right:25px; width:350px; height:450px; z-index:9999;">
    
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <span>AI Customer Assistant</span>
        <div class="d-flex gap-2">
            <button type="button" id="minimizeChat" class="btn btn-sm p-0 text-white" title="Minimize">
                <span style="font-size: 1.5rem; line-height: 0;">&minus;</span>
            </button>
            <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
        </div>
    </div>

    <div class="card-body" id="chatMessages" style="overflow-y:auto; height:320px; background: #f8f9fa;">
        <div class="text-start mb-2 text-muted small">Hello! I am Abbas AI. How can I help you today?</div>
    </div>

    <div class="p-2 border-top">
        <input id="chatInput" class="form-control" placeholder="Type a message...">
    </div>
</div>

<script>
        document.addEventListener('DOMContentLoaded', function () {
            const actionModal = document.getElementById('actionModal');
            if (actionModal) {
                actionModal.addEventListener('show.bs.modal', function (event) {
                   
                    const button = event.relatedTarget; 
                    const title = button.getAttribute('data-modal-title');
                    const bodyContent = button.getAttribute('data-modal-body');

                    const modalTitle = actionModal.querySelector('.modal-title');
                    const modalBody = actionModal.querySelector('.modal-body');

                    modalTitle.textContent = title;
                    modalBody.innerHTML = bodyContent;
                });
            }
        });

       
            document.getElementById("invoiceDetailsModal").addEventListener("show.bs.modal", function (event) {
                const invoiceId = event.relatedTarget.getAttribute("data-invoice-id");

                fetch("fetch_invoice_details.php?id=" + invoiceId)
                    .then(res => res.text())
                    .then(html => {
                        document.getElementById("invoice-details-content").innerHTML = html;
                    });
            });

            document.addEventListener('DOMContentLoaded', function() {
    const toggler = document.getElementById('navbarToggler');
    const closeBtn = document.getElementById('navbarClose');
    const nav = document.getElementById('navbarNav');

    // When hamburger is clicked
    toggler.addEventListener('click', () => {
      setTimeout(() => {
        if (toggler.getAttribute('aria-expanded') === 'true') {
          toggler.style.display = 'none';   
          closeBtn.style.display = 'block';   
        }
      }, 150);
    });

    // When close button is clicked
    closeBtn.addEventListener('click', () => {
      const bsCollapse = bootstrap.Collapse.getInstance(nav) || new bootstrap.Collapse(nav, { toggle: false });
      bsCollapse.hide();
      closeBtn.style.display = 'none';
      toggler.style.display = 'block';
    });

    // Close when clicking outside the menu
    document.addEventListener('click', (e) => {
      const isInside = nav.contains(e.target) || toggler.contains(e.target) || closeBtn.contains(e.target);
      if (!isInside && nav.classList.contains('show')) {
        const bsCollapse = bootstrap.Collapse.getInstance(nav) || new bootstrap.Collapse(nav, { toggle: false });
        bsCollapse.hide();
        closeBtn.style.display = 'none';
        toggler.style.display = 'block';
      }
    });

    // Reset on resize to desktop
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 992) {
        closeBtn.style.display = 'none';
        toggler.style.display = 'block';
      }
    });
  });

window.CLIENT_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
<script src="chatbot.js"></script>
</body>
</html>