<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\DatabaseConfig;

// Set proper headers
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Unknown error occurred'
];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (isset($_POST['item_id']) && isset($_POST['available'])) {
        $itemId = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        $available = filter_var($_POST['available'], FILTER_VALIDATE_INT);
        
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
                ':available' => $available ? true : false,
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