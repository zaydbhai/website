<?php
session_start();

$file = $_GET['file'] ?? '';
// Sanitize path to prevent directory traversal attacks
$file = str_replace('..', '', $file);
$filepath = __DIR__ . '/' . $file;

// Check session for protected paths
$is_protected_path = strpos($file, 'resources/Alevel/') !== false;
if ($is_protected_path && !isset($_SESSION['active_user'])) {
    http_response_code(403);
    exit("Access Denied. Please log in.");
}

if (file_exists($filepath)) {
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf'
    ];
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $mime);
    readfile($filepath);
    exit;
} else {
    http_response_code(404);
    exit("File not found.");
}
?>