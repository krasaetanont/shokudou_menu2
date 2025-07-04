<?php
// Start session to check login status
session_start();

// Require the autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Use the database configuration
use App\Config\DatabaseConfig;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Set content type to JSON
header('Content-Type: application/json');

// if (isset($_SESSION['access_token'])) {
//     // User is logged in
//     $isLoggedIn = true;
// } else {
//     // User is not logged in
//     $isLoggedIn = false;
// }
$isLoggedIn = true; // For testing purposes, we assume the user is logged in
// Check if user is logged in (inverted the logic - it was backwards)
if (!$isLoggedIn) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['id']) || !isset($_POST['available'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Sanitize inputs
$id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
$available = $_POST['available'] === '1' ? true : false; // Fixed boolean conversion

// Validate ID
if (!$id || $id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid item ID'
    ]);
    exit;
}

try {
    // Get database connection
    $db = DatabaseConfig::getInstance()->getConnection();
    
    // Update the menu item availability
    $stmt = $db->prepare('UPDATE menu SET available = :available WHERE id = :id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':available', $available, PDO::PARAM_BOOL);
    
    $result = $stmt->execute();
    
    // Check if any rows were affected
    $rowCount = $stmt->rowCount();
    
    if ($rowCount > 0) {
        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Menu item updated successfully',
            'data' => [
                'id' => (int)$id,
                'available' => $available
            ]
        ]);
    } else {
        // No rows were updated
        echo json_encode([
            'success' => false,
            'message' => 'Item not found or no changes made'
        ]);
    }
} catch (PDOException $e) {
    // Log the error
    error_log('Database error: ' . $e->getMessage());
    
    // Send error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Log other errors
    error_log('Error: ' . $e->getMessage());
    
    // Send error response
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}