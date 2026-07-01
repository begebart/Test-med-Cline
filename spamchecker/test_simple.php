<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log the request
file_put_contents('logs/test.log', date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);

// Get the input
$input = file_get_contents('php://input');
file_put_contents('logs/test.log', "Input: " . substr($input, 0, 200) . "\n", FILE_APPEND);

// Check if we can read JSON
$json_data = json_decode($input, true);
file_put_contents('logs/test.log', "JSON decoded: " . print_r($json_data, true) . "\n---\n", FILE_APPEND);

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Test API virker!',
    'received' => $json_data,
    'method' => $_SERVER['REQUEST_METHOD']
]);
?>