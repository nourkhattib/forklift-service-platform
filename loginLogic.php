<?php
session_start();

require_once __DIR__ . "/db.php"; 

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// --- 1. Attempt Staff (Admin/Technician) Login ---
$stmtStaff = $pdo->prepare("SELECT id, email, password, name, role FROM staff WHERE email = ?");
$stmtStaff->execute([$email]);
$user = $stmtStaff->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['name']; 
    $_SESSION['role'] = $user['role'];
    $_SESSION['loyalty_points'] = null; 

    if ($user['role'] === 'admin') {
        header("Location: backoffice.php");
    } else { 
        header("Location: tech_area.php");
    }
    exit;
}

$stmtClient = $pdo->prepare("SELECT id, email, password, username, loyalty_points FROM users WHERE email = ?");
$stmtClient->execute([$email]);
$user = $stmtClient->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = 'client'; 
    $_SESSION['loyalty_points'] = $user['loyalty_points'];

    header("Location: client_area.php");
    exit;
}

echo "<script>alert('Invalid email or password'); window.history.back();</script>";
exit;
?>