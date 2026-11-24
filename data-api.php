<?php
session_start();
header('Content-Type: application/json');

// 1. Ensure user is logged in
if (!isset($_SESSION['logged_in_user'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$user = preg_replace('/[^a-z0-9_]/', '', strtolower($_SESSION['logged_in_user']));
$dir = __DIR__ . '/user_data';

// Create a secure storage directory if it doesn't exist
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filePath = $dir . '/' . $user . '.json';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        // Ensure it returns valid JSON object if file is empty
        echo (empty($content) || $content === '[]') ? '{}' : $content;
    } else {
        echo '{}';
    }
} elseif ($method === 'POST') {
    $inputData = file_get_contents('php://input');
    if (json_decode($inputData) !== null) {
        file_put_contents($filePath, $inputData);
        echo json_encode(["status" => "success"]);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    }
}
?>