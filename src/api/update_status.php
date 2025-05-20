<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Config\DatabaseConfig;

header('Content-Type: application/json');
session_start();

$response = [
    'success' => false,
    'message' => 'Unknown error occurred'
];

$isLoggedIn = true; // For now

if (!$isLoggedIn) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'redirect' => '/shokudouMenu2/src/pages/login.html'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['item_id'], $_POST['available'])) {
        $itemId = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        $availableInput = $_POST['available'];

        if ($itemId === false || !in_array($availableInput, ['0', '1'], true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid parameters'
            ]);
            exit;
        }

        $available = $availableInput === '1'; // Convert to boolean

        try {
            $db = DatabaseConfig::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE menu SET available = :available WHERE id = :id");
            $result = $stmt->execute([
                ':available' => $available,
                ':id' => $itemId
            ]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Menu item status updated successfully'
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update menu item status'
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log('Update status error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}
