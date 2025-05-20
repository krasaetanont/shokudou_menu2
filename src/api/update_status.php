<?php
// src/api/update_status.php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\DatabaseConfig;

// Set proper headers
header('Content-Type: application/json');

// Start the session to get access to session variables
session_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Unknown error occurred'
];

// Check if the user is logged in
// $isLoggedIn = isset($_SESSION['user_id']) || (isset($_COOKIE['user_logged_in']) && $_COOKIE['user_logged_in'] === 'true');
$isLoggedIn = true; // For testing purposes, assume user is logged in

if (!$isLoggedIn) {
    $response['message'] = 'Authentication required';
    $response['redirect'] = '/shokudouMenu2/src/pages/login.html';
    echo json_encode($response);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (isset($_POST['item_id']) && isset($_POST['available'])) {
        $itemId = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        // Fix: Explicitly cast available to boolean
        $available = (bool)filter_var($_POST['available'], FILTER_VALIDATE_INT);
        
        if ($itemId === false) {
            $response['message'] = 'Invalid item ID provided';
            echo json_encode($response);
            exit;
        }
        
        try {
            // Get database connection
            $db = DatabaseConfig::getInstance()->getConnection();
            
            // Prepare and execute the update statement
            $stmt = $db->prepare("UPDATE menu SET available = :available WHERE id = :id");
            $result = $stmt->execute([
                ':available' => $available,
                ':id' => $itemId
            ]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Menu item status updated successfully';
            } else {
                $response['message'] = 'Failed to update menu item status';
            }
        } catch (Exception $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            // Log the error for administrative review
            error_log('Update status error: ' . $e->getMessage());
        }
    } else {
        $response['message'] = 'Missing required parameters';
    }
} else {
    $response['message'] = 'Invalid request method';
}

// Return JSON response
echo json_encode($response);
exit;