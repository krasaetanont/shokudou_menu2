<?php
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
