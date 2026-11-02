<?php
// logout.php
session_start(); // <--- 1. Must start session to destroy it

// 2. Fixed path: removed "/inc" because db.php is in the root
require_once __DIR__ . "/db.php"; 

// Unset all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Redirect to home
header("Location: index.php");
exit;
?>