<?php
session_start();
require_once __DIR__ . "/db.php"; 

$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($username) || empty($email) || empty($password)) {
    echo "<script>alert('Please fill in all fields.'); window.history.back();</script>";
    exit;
}

// Hash the password securely
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo "<script>alert('This email is already registered. Please log in.'); window.history.back();</script>";
        exit;
    }

    // Insert new user into the database
    $stmt = $pdo->prepare("INSERT INTO users (email, username, password, loyalty_points) VALUES (?, ?, ?, 0)");
    $stmt->execute([$email, $username, $hashed_password]);

    $new_user_id = $pdo->lastInsertId();

    // Set session for immediate login and redirect
    $_SESSION['user_id'] = $new_user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'client';
    $_SESSION['loyalty_points'] = 0;

    // Redirect to the client area upon successful registration
    header("Location: client_area.php");
    exit;

} catch (PDOException $e) {
    // Handle database errors
    echo "<script>alert('Database error during registration. Please try again later.'); window.history.back();</script>";
    // Optional: Log error: error_log("Signup error: " . $e->getMessage());
    exit;
}
?>