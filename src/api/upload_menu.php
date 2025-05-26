<?php

// Start session to check login status
session_start();

// Require the autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Set content type to JSON
header('Content-Type: application/json');

if (isset($_SESSION['access_token'])) {
    // User is logged in
    $isLoggedIn = true;
} else {
    // User is not logged in
    $isLoggedIn = false;
}

// Check if user is logged in (inverted the logic - it was backwards)
if (!$isLoggedIn) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

header('Content-Type: application/json');

// Only process POST requests with file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $pattern = "/^\d{4}\.(0[1-9]|1[0-2])\.pdf$/";

    if (!preg_match($pattern, $file['name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid file name format. Please use YYYY.MM.pdf format.'
        ]);
        exit;
    }

    $uploadDir = '/var/www/html/shokudouMenu2/menuFile/';
    $uploadFile = $uploadDir . basename($file['name']);

    if (file_exists($uploadFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'File already exists. Please rename the file and try again.'
        ]);
        exit;
    }

    // Ensure the upload directory exists and is writable
    // if (!is_dir($uploadDir)) {
    //     if (!mkdir($uploadDir, 0755, true)) {
    //         echo json_encode([
    //             'success' => false,
    //             'message' => 'Failed to create upload directory.'
    //         ]);
    //         exit;
    //     }
    // }

    // if (!is_writable($uploadDir)) {
    //     echo json_encode([
    //         'success' => false,
    //         'message' => 'Upload directory is not writable.'
    //     ]);
    //     exit;
    // }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully.',
            'fileName' => htmlspecialchars($file['name'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error uploading file.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or invalid request method.'
    ]);
}
