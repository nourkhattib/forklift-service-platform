<?php
session_start();
require_once __DIR__ . "/db.php"; 

$pageTitle = "Technician Area - Abbas Forklifts";

// --- 1. LOGOUT & SECURITY CHECK ---
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'technician') {
    header("Location: index.php");
    exit;
}

$techId = $_SESSION['user_id'];
$displayName = $_SESSION['username'] ?? 'Dear Technician';

// --- 2. TECHNICIAN REWARD TRACKER LOGIC ---
$TICKET_GOAL = 10;
$REWARD_NAME = 'A day off of your choice! 🏖️'; 

// Count tickets closed in the last 90 days
$stmtClosedCount = $pdo->prepare("
    SELECT COUNT(id) AS closed_count
    FROM service_requests
    WHERE technician_id = ? AND status = 'completed'
    AND close_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) 
");
$stmtClosedCount->execute([$techId]);
$closedTicketsCount = $stmtClosedCount->fetchColumn();

// Calculate progress variables
$progress = min(100, ($closedTicketsCount / $TICKET_GOAL) * 100);
$remainingTickets = max(0, $TICKET_GOAL - $closedTicketsCount);
$isRewardEarned = ($closedTicketsCount >= $TICKET_GOAL);
// ----------------------------------------


// --- 3. FETCH ASSIGNED SERVICE TICKETS ---
$stmtTickets = $pdo->prepare("
SELECT 
    sr.id,
    sr.description,
    sr.status,
    sr.created_at AS open_date,
    sr.technician_notes,
    u.username AS client_name,
    u.email AS client_email,
    i.model AS equipment_model,
    i.serial_number AS equipment_serial
FROM service_requests sr
JOIN users u ON sr.user_id = u.id
LEFT JOIN inventory i ON sr.product_id = i.id
WHERE sr.technician_id = ?
  AND sr.request_type = 'service'
ORDER BY sr.status ASC, sr.created_at DESC
");
$stmtTickets->execute([$techId]);
$tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

// Status mapping for display and Bootstrap color
$statusMap = [
    'new' => ['text' => 'New', 'class' => 'bg-danger'],
    'pending' => ['text' => 'Completed awaiting closure', 'class' => 'bg-primary'],
    'in_progress' => ['text' => 'In Progress', 'class' => 'bg-info'],
    'completed' => ['text' => 'Completed', 'class' => 'bg-success'],
];

// SAVE TECHNICIAN OFF DAYS
if (isset($_POST['action']) && $_POST['action'] === 'save_off_days') {
    $tech_id = $_SESSION['user_id'];

    if (isset($_POST['clear_off_days'])) {
        $stmt = $pdo->prepare("DELETE FROM technician_off_days WHERE technician_id = ?");
        $stmt->execute([$tech_id]);
        $_SESSION['flash'] = "All off days cleared.";
        header("Location: tech_area.php");
        exit();
    }

    $off_days = $_POST['off_days'] ?? [];
    $off_days = array_filter($off_days, fn($d) => !empty($d));

    $stmt = $pdo->prepare("DELETE FROM technician_off_days WHERE technician_id = ?");
    $stmt->execute([$tech_id]);

    if (!empty($off_days)) {
        $stmt = $pdo->prepare("INSERT INTO technician_off_days (technician_id, off_date) VALUES (?, ?)");
        foreach ($off_days as $day) {
            $stmt->execute([$tech_id, $day]);
        }
    }

    $_SESSION['flash'] = "Your off days have been updated.";
    header("Location: tech_area.php");
    exit();
}

$stmtOffDays = $pdo->prepare("SELECT off_date FROM technician_off_days WHERE technician_id = ? ORDER BY off_date ASC");
$stmtOffDays->execute([$techId]);
$savedOffDays = $stmtOffDays->fetchAll(PDO::FETCH_COLUMN);


// UPDATE TICKET STATUS + NOTES
if (isset($_POST['action']) && $_POST['action'] === 'update_ticket') {
    $ticket_id = $_POST['ticket_id'];
    $status = $_POST['new_status'];
    $notes = trim($_POST['technician_notes']);

    if ($status === 'completed') {
        $stmt = $pdo->prepare("
            UPDATE service_requests 
            SET status = ?, technician_notes = ?, close_date = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            UPDATE service_requests 
            SET status = ?, technician_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
    }

    $stmt->execute([$status, $notes, $ticket_id]);
    $_SESSION['flash'] = "Service request updated.";
    header("Location: tech_area.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet"> 
</head>
<body class="page-bg-tech page-with-overlay">
    
<div class="background-logo">
    <img src="imgs/logo.webp" alt="Background Logo" />
</div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="tech_area.php">
                <img src="imgs/logo.webp" alt="Abbas Forklifts Logo" height="40">
                Technician Area
            </a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="btn btn-sm btn-outline-light me-2" href="?logout=1">🔐 Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="container mt-5 pt-5">
        <h2 class="text-white text-center mb-5">Hello, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>! Here are your service calls. ⚙️</h2>
        
        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="alert alert-success text-center" role="alert">
                Ticket #<?= htmlspecialchars($_GET['ticket']) ?> has been updated successfully!
            </div>
        <?php endif; ?>

<div class="row g-4 mb-5">
    <div class="col-lg-6">
        <div class="card service-glass shadow-lg p-4 border-0">
            <h4 class="text-white mb-3 d-flex align-items-center justify-content-between">
                <span>Motivation & Rewards Board ✨</span>
                <?php if ($isRewardEarned): ?>
                    <span class="badge bg-warning text-dark p-2">Congratulations! Reward Earned!</span>
                <?php endif; ?>
            </h4>

            <div class="p-3 rounded" style="background: rgba(255,255,255,0.1);">
                <p class="text-light mb-1">Current Goal: Close <strong><?= $TICKET_GOAL ?> calls</strong> (within last 90 days)</p>
                <p class="text-light fw-bold mb-2">The Reward: <strong><?= $REWARD_NAME ?></strong></p>

                <div class="progress mb-2" style="height: 25px;">
                    <div class="progress-bar <?= $isRewardEarned ? 'bg-success' : 'bg-info' ?> fw-bold"
                         role="progressbar"
                         style="width: <?= $progress ?>%">
                        <?= $closedTicketsCount ?> / <?= $TICKET_GOAL ?>
                    </div>
                </div>

                <p class="text-light text-end mb-0">
                    <?php if ($isRewardEarned): ?>
                        🎉 Message the manager to claim your reward!
                    <?php else: ?>
                        <strong><?= $remainingTickets ?></strong> calls left to close.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card service-glass p-4 shadow-lg">
            <h4 class="text-white mb-3">Select Your Off Days 🗓️</h4>
            <div class="mb-3">
                <label class="text-white mb-2">Your current off days:</label>
                <p class="text-light mb-3">
                    <?php if (!empty($savedOffDays)): ?>
                        <?= implode(' | ', array_map(fn($d) => date("F j, Y", strtotime($d)), $savedOffDays)) ?>
                    <?php else: ?>
                        None selected
                    <?php endif; ?>
                </p>
            </div>

            <form method="POST" action="tech_area.php">
                <input type="hidden" name="action" value="save_off_days">
                <label class="text-white mb-2">Choose your off days:</label>
                <input type="date" name="off_days[]" class="form-control mb-2" value="<?= $savedOffDays[0] ?? '' ?>">
                <input type="date" name="off_days[]" class="form-control mb-2" value="<?= $savedOffDays[1] ?? '' ?>">
                <input type="date" name="off_days[]" class="form-control mb-2" value="<?= $savedOffDays[2] ?? '' ?>">

                <div class="d-flex gap-2">
                    <button class="btn btn-orange flex-grow-1" type="submit">Save Off Days</button>
                    <button class="btn btn-secondary flex-grow-1 rounded" type="submit" name="clear_off_days" value="1">Clear All</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-12">
        <div class="card service-glass shadow-lg p-4">
            <h4 class="text-white mb-4">Service Tickets Assigned to You</h4>
            <ul class="list-group list-group-flush">
                <?php if (count($tickets) > 0): ?>
                    <?php foreach ($tickets as $ticket): 
                        $currentStatus = $statusMap[$ticket['status']] ?? ['text' => 'Unknown', 'class' => 'bg-secondary'];
                        $notesPreview = !empty($ticket['technician_notes']) ? 'Notes available' : 'No notes added';
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex flex-column text-start flex-grow-1 me-3">
                                <span class="fw-bold">
                                    Ticket #<?= $ticket['id'] ?>:
                                    <?= htmlspecialchars($ticket['description']) ?>
                                </span>
                                <small class="text-muted">
                                    Client: <strong><?= htmlspecialchars($ticket['client_name']) ?></strong> |
                                    Equipment: <strong><?= htmlspecialchars($ticket['equipment_model'] ?? 'Not Specified') ?></strong>
                                </small>
                                <small class="text-secondary mt-1"><?= $notesPreview ?></small>
                            </div>

                            <div class="d-flex flex-column align-items-end">
                                <span class="badge rounded-pill <?= $currentStatus['class'] ?>">
                                    <?= $currentStatus['text'] ?>
                                </span>
                                <?php if ($ticket['status'] !== 'completed'): ?>
                                    <button
                                        class="btn btn-orange mt-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editStatusModal"
                                        data-ticket-id="<?= $ticket['id'] ?>"
                                        data-ticket-desc="<?= htmlspecialchars($ticket['description']) ?>"
                                        data-current-status="<?= $ticket['status'] ?>"
                                        data-technician-notes="<?= htmlspecialchars($ticket['technician_notes']) ?>"
                                    >
                                        Update Status
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group-item text-center">
                        No service tickets have been assigned to you.
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
</main>

    <div class="modal fade" id="editStatusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Ticket Status</h5>
                    <button type="button" class="btn-close ms-0" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="tech_area.php"> 
                    <input type="hidden" name="action" value="update_ticket"> 
                    <div class="modal-body">
                        <input type="hidden" name="ticket_id" id="modalTicketId">
                        <p>Updating Ticket: <strong id="modalTicketDesc"></strong></p>
                        
                        <div class="mb-3">
                            <label for="newStatus" class="form-label">New Status:</label>
                            <select name="new_status" id="newStatus" class="form-select" required>
                                <option value="new">New</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="technician_notes" class="form-label">Add Technician Notes:</label>
                            <textarea name="technician_notes" id="technician_notes" class="form-control" rows="3" placeholder="Describe actions taken or next steps..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-orange">Save Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editStatusModal = document.getElementById('editStatusModal');
            if (editStatusModal) {
                editStatusModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget; 
                    const ticketId = button.getAttribute('data-ticket-id');
                    const ticketDesc = button.getAttribute('data-ticket-desc');
                    const currentStatus = button.getAttribute('data-current-status');
                    const existingNotes = button.getAttribute('data-technician-notes');

                    document.getElementById('modalTicketId').value = ticketId;
                    document.getElementById('modalTicketDesc').textContent = ticketDesc;
                    document.getElementById('newStatus').value = currentStatus;
                    document.getElementById('technician_notes').value = existingNotes;
                });
            }
        });
    </script>
    <button id="chatbotButton" class="btn btn-primary rounded-circle"
        style="position: fixed; bottom: 25px; right: 25px; z-index: 9999; width: 60px; height: 60px;">
        🤖
    </button>

    <div id="chatWindow" class="card shadow-lg"
     style="display:none; position:fixed; bottom:100px; right:25px; width:350px; height:450px; z-index:9999;">
    
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <span>AI Technician Assistant</span>
        <div class="d-flex gap-2">
            <button type="button" id="minimizeChat" class="btn btn-sm p-0 text-white" title="Minimize">
                <span style="font-size: 1.5rem; line-height: 0;">&minus;</span>
            </button>
            <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
        </div>
    </div>

    <div class="card-body" id="chatMessages" style="overflow-y:auto; height:320px; background: #f8f9fa;">
        <div class="text-start mb-2 text-muted small">Hello! I am Abbas AI. How can I help you with your tickets?</div>
    </div>

    <div class="p-2 border-top">
        <input id="chatInput" class="form-control" placeholder="Type a message...">
    </div>
    </div>

    <script>
    // Helper to let chatbot.js know who is logged in
    window.CLIENT_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
    </script>
    <script src="chatbot.js"></script>
     
</body>
</html>