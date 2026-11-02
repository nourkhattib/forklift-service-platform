<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1) Session context ---
$userId   = $_SESSION['user_id'] ?? null;
$username = $_SESSION['name'] ?? ($_SESSION['username'] ?? 'Guest');
$role     = $_SESSION['role'] ?? 'guest';

// Normalize role values (avoid unexpected strings)
$validRoles = ['guest', 'client', 'technician', 'admin'];
if (!in_array($role, $validRoles, true)) {
    // If logged in but role is unknown, force re-login instead of guessing
    if ($userId) {
        echo json_encode(["reply" => "שגיאת התחברות: תפקיד משתמש לא תקין. אנא התחבר מחדש."]);
        exit;
    }
    $role = 'guest';
}

// If logged in but missing/guest role, do not guess
if ($userId && $role === 'guest') {
    echo json_encode(["reply" => "שגיאת התחברות: חסר תפקיד משתמש. אנא התחבר מחדש."]);
    exit;
}

// Normalize actor identity (backwards compatible with your current session debt)
$actorType = 'guest';
$actorId   = null;

if ($userId) {
    if (in_array($role, ['admin', 'technician'], true)) {
        $actorType = 'staff';
        $actorId   = (int)$userId;
    } else {
        // Customer path
        $actorType = 'client';
        $actorId   = (int)$userId;
        $role      = 'client'; // enforce clean label
    }
}

// --- 2) Chat History Management (Memory) ---
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// --- 3) Read input ---
$inputData = json_decode(file_get_contents("php://input"), true);


$userMessage = trim((string)($inputData["message"] ?? ""));

if ($userMessage === '') {
    echo json_encode(["reply" => "שלום! איך אפשר לעזור?"]);
    exit;
}

if (mb_strlen($userMessage, 'UTF-8') > 1000) {
    echo json_encode(["reply" => "ההודעה ארוכה מדי. נסה לקצר בבקשה."]);
    exit;
}

// --- 4) Payload + integrity ---
$__cfg = @include __DIR__ . '/config/config.php';
$sharedSecret = (is_array($__cfg) && !empty($__cfg['AI_SHARED_SECRET'])) ? $__cfg['AI_SHARED_SECRET'] : (getenv('AI_SHARED_SECRET') ?: ''); // from config/config.php (not committed)
$ts = time();

$payload = [
    "message"    => $userMessage,
    "actor_type" => $actorType,
    "actor_id"   => $actorId,   // int or null
    "username"   => $username,
    "role"       => $role,
    "ts"         => $ts,
    "history"    => $_SESSION['chat_history'] // Send previous context to Python
];

// IMPORTANT: sign the exact body you send
$body = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($body === false) {
    echo json_encode(["reply" => "שגיאה: לא ניתן לעבד את הבקשה."]);
    exit;
}

$sig  = hash_hmac('sha256', $body, $sharedSecret);

// --- 5) Proxy to Python ---
$ch = curl_init('http://127.0.0.1:8000/chat');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-AI-Signature: ' . $sig
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --- 6) Error handling ---
if ($curlError || $httpCode !== 200) {
    $aid = $actorId ?? 'null';
    error_log("Python AI Server Error: $curlError (HTTP $httpCode) actorType=$actorType actorId=$aid role=$role");
    echo json_encode(["reply" => "שגיאת מערכת: שירות ה-AI לא זמין כרגע. נסה שוב מאוחר יותר."]);
    exit;
}

// --- 7) Update History & Output ---
$responseData = json_decode($response, true);

if (isset($responseData['reply'])) {
    // Append the NEW interaction to history (User + Bot)
    $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $userMessage];
    $_SESSION['chat_history'][] = ['role' => 'model', 'content' => $responseData['reply']];

    // Limit history length (e.g., keep last 10 turns to save tokens)
    if (count($_SESSION['chat_history']) > 10) {
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -10);
    }
}

// Output result to frontend
echo $response;
?>