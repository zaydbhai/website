<?php
header('Content-Type: application/json');

$subject_code = $_GET['subject_code'] ?? '9701';
$file = 'questions.json';

if (!file_exists($file)) {
    echo json_encode([]);
    exit;
}

// Read and decode JSON dataset
$data = json_decode(file_get_contents($file), true);

if (!is_array($data)) {
    echo json_encode([]);
    exit;
}

// Filter questions matching the requested subject code
$filtered = array_values(array_filter($data, function($q) use ($subject_code) {
    return isset($q['subject_code']) && $q['subject_code'] === $subject_code;
}));

// Return filtered subset
echo json_encode($filtered);
?>