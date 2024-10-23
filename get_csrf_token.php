
<?php
session_start();
require_once 'functions.php';

header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Generate and store new token
$newToken = generateCsrfToken();
$_SESSION['csrf_token'] = $newToken;

echo json_encode(['csrf_token' => $newToken]);