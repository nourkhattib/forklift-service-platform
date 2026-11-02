<?php
session_start();
require_once __DIR__ . "/db.php"; 

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'technician') {
       header("Location: index.php");
    exit;
}

$techId = $_SESSION['user_id'];
$techName = $_SESSION['username'];
$ticketId = $_POST['ticket_id'] ?? null;
$newStatus = $_POST['new_status'] ?? null;
$notes = trim($_POST['technician_notes'] ?? ''); 

$currentDateTime = date('Y-m-d H:i:s'); 

if (!$ticketId || !$newStatus) {
    header("Location: tech_area.php?error=validation_failed");
    exit;
}

try {
    $setClauses = "status = ?";
    $params = [$newStatus];
    
    if ($newStatus === 'closed' || $newStatus === 'ready_for_pickup') {
        $setClauses .= ", close_date = ?";
        $params[] = $currentDateTime;
    } else {
        $setClauses .= ", close_date = NULL";
    }

    $newNoteEntry = "\n-- [{$currentDateTime} by {$techName}] Status: {$newStatus}. Notes: {$notes}";
    
    $setClauses .= ", technician_notes = CONCAT(IFNULL(technician_notes, ''), ?)";
    $params[] = $newNoteEntry;

    $params[] = $ticketId;
    $params[] = $techId;
    
    $stmt = $pdo->prepare("
        UPDATE service_tickets 
        SET {$setClauses} 
        WHERE id = ? AND technician_id = ?
    ");
    $stmt->execute($params);


    if ($newStatus === 'closed') {
        
        $stmtUser = $pdo->prepare("SELECT user_id FROM service_tickets WHERE id = ?");
        $stmtUser->execute([$ticketId]);
        $userId = $stmtUser->fetchColumn();
        
        $POINTS_AWARDED = 50; 

        if ($userId) {
          
            $stmtLoyalty = $pdo->prepare("
                UPDATE users 
                SET loyalty_points = loyalty_points + ? 
                WHERE id = ?
            ");
            $stmtLoyalty->execute([$POINTS_AWARDED, $userId]);
            
        }
    }

    header("Location: tech_area.php?status=success&ticket=$ticketId");
    exit;

} catch (PDOException $e) {
      error_log("Ticket update failed: " . $e->getMessage());
    header("Location: tech_area.php?status=db_error");
    exit;
}
?>