<?php

$config = require __DIR__ . '/config/config.php';
$apiKey = $config['GEMINI_API_KEY'] ?? null;

if (!$apiKey) {
    die("❌ GEMINI_API_KEY not found in config/config.php");
}

echo "<pre>Testing Gemini API...\n\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=$apiKey";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => "Hello Gemini — say only: TEST OK"]
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error:\n$error";
    exit;
}

echo "Gemini Raw Response:\n$response\n";
