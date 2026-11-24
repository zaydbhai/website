<?php
// Start a secure server session
session_start();

// Set header for JSON response
header('Content-Type: application/json');

// Define your secure PINs on the server side (Hidden from F12 / Inspect Element!)
$pins = [
    "ahmed" => "1111",
    "talha" => "2222",
    "suleiman" => "3333",
    "abdul bari khan" => "4444",
    "zayd" => "6666",
    "sufyan" => "7777",
    "ayan" => "8888",
];

// Read POST data sent from the login form
$input = json_decode(file_get_contents("php://input"), true);
$user = strtolower(trim($input['user'] ?? ''));
$pin = trim($input['pin'] ?? '');

// Verify credentials
if (isset($pins[$user]) && $pins[$user] === $pin) {
    $_SESSION['logged_in_user'] = $user;
    echo json_encode(["status" => "success", "user" => $user]);
} else {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid passcode"]);
}
?>