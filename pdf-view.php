<?php
session_start();

// 1. Check if the user is authenticated
if (!isset($_SESSION['logged_in_user'])) {
    http_response_code(401);
    die("Unauthorized access.");
}

// 2. Get the requested file path parameter
$file = $_GET['file'] ?? '';
$file = trim(str_replace(['\\', '..'], ['/', ''], $file), '/');

$baseDir = realpath(__DIR__ . '/resources');
$fullPathToTest = __DIR__ . '/' . $file;
$targetPath = file_exists($fullPathToTest) ? realpath($fullPathToTest) : false;

// 3. Validate path and file existence securely
if ($targetPath && $baseDir && strpos($targetPath, $baseDir) === 0 && file_exists($targetPath)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($targetPath) . '"');
    header('Content-Length: ' . filesize($targetPath));
    readfile($targetPath);
    exit;
} else {
    http_response_code(403);
    die("Access denied or file not found.");
}
?>