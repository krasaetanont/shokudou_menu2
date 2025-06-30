<?php
session_start();

// echo "<pre>Session data: " . print_r($_SESSION, true) . "</pre>";
// First, let's enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Make sure the path to the autoloader is correct
require_once __DIR__ . '/../vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// if (isset($_SESSION['access_token'])) {
//     // User is logged in
//     $isLoggedIn = true;
// } else {
//     // User is not logged in
//     $isLoggedIn = false;
// }
$isLoggedIn = true; // For testing purposes, we assume the user is logged in

// Simple function to safely get a database connection
function getDbConnection() {
    try {
        // Using direct database connection for simplicity
        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASS'];
        $port = $_ENV['DB_PORT'] ?: '5432';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};";
        
        $db = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        return $db;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Set default timezone
date_default_timezone_set('Asia/Tokyo');

// Determine which date to show
$dateOffset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$specificDate = isset($_GET['date']) ? $_GET['date'] : null;

// Get target date
$targetDate = new DateTime('now');

if ($specificDate) {
    // If a specific date is provided via calendar selection
    try {
        $targetDate = new DateTime($specificDate);
        // Calculate offset from today for relative date label
        $today = new DateTime('now');
        $interval = $targetDate->diff($today);
        $dateOffset = $interval->days * ($interval->invert ? 1 : -1);
    } catch (Exception $e) {
        // Invalid date format, fall back to current date
        $dateOffset = 0;
    }
} elseif ($dateOffset !== 0) {
    // Use offset navigation
    $targetDate->modify("{$dateOffset} days");
}

// Format date for display
$displayDate = $targetDate->format('Y-m-d');

// Get relative date label
if ($dateOffset < 0) {
    $dateLabel = abs($dateOffset) . ' day(s) ago';
} elseif ($dateOffset > 0) {
    $dateLabel = $dateOffset . ' day(s) from now';
} else {
    $dateLabel = 'Today';
}

// Get database connection
$db = getDbConnection();

// Format date for database query
$formattedDate = $targetDate->format('Y-m-d');

// Get menu items for the date
$stmt = $db->prepare("SELECT id, name, price, available, tag FROM menu WHERE available_date = ? ORDER BY name");
$stmt->execute([$formattedDate]);
$menuItems = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shokudou - Today's Menu</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/calendar.css">
</head>
<body>
    <div class="container">
        <header>
            <a href="/team1/toyouke/public/index.php">
                <h1>Toyo<strong>U</strong>ke</h1>
            </a>
            <div class="loginButton">
                <?php if ($isLoggedIn): ?>
                    <a href="/team1/toyouke/src/api/logout.php">Logout</a>
                <?php else: ?>
                    <a href="/team1/toyouke/src/pages/login.php">Login</a>
                <?php endif; ?>
            </div>
        </header>
        <div class="datePart">
            <p class="date">
                <span class="date-label"><?= htmlspecialchars($dateLabel) ?></span>
                <span class="date-full"><?= htmlspecialchars($displayDate) ?></span>
            </p>

            <div class="calendar">
                <a href="#" id="calendar-button">Calendar</a>
            </div>

            <div class="date-navigation">
                <a href="index.php?offset=<?= $dateOffset-1 ?>" class="nav-btn">&laquo; Previous Day</a>
                <a href="index.php" class="nav-btn <?= $dateOffset === 0 ? 'active' : '' ?>">Today</a>
                <a href="index.php?offset=<?= $dateOffset+1 ?>" class="nav-btn">Next Day &raquo;</a>
            </div>
        </div>
        
        <?php if (empty($menuItems)): ?>
            <div class="no-menu">
                <p>No menu items available for this day. Please check back later.</p>
            </div>
        <?php else: ?>
            <table id="menu-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Set</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menuItems as $item): ?>
                    <tr data-id="<?= $item['id'] ?>">
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>¥<?= number_format($item['price']/100, 0) ?></td>
                        <td>
                            <?php if (!empty($item['tag'])): ?>
                                <span class="tag"><?= htmlspecialchars($item['tag']) ?></span>
                            <?php else: ?>
                                <span class="tag none">No Tags</span>
                            <?php endif; ?>
                        </td>
                        <td class="status">
                            <span class="status-indicator <?= $item['available'] ? 'available' : 'unavailable' ?>">
                                <?= $item['available'] ? 'Available' : 'Unavailable' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-available <?= $item['available'] ? 'active' : '' ?>" 
                                        data-id="<?= $item['id'] ?>"
                                        <?= $item['available'] ? 'disabled' : '' ?>>
                                    Available
                                </button>
                                <button class="btn-unavailable <?= !$item['available'] ? 'active' : '' ?>"
                                        data-id="<?= $item['id'] ?>"
                                        <?= !$item['available'] ? 'disabled' : '' ?>>
                                    Unavailable
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="upload-popup-buttons">
        <a href="#" id="uploadMenuButton" class="upload-btn">Upload Menu Items</a>
    </div>
    <!-- upload popup -->
    <div class="upload-popup" id="uploadPopup">
        <div class="upload-popup-content">
            <h3>Upload Menu Items</h3>
            <p>upload a PDF file </p>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="file" name="menuFile" id="inputField" accept=".pdf" required>
                <button type="submit" class="upload-button" name="submit" id="uploadButton">Upload</button>
            </form>
            <button type="button" class="cancel-btn" id="cancelUpload">Cancel</button>
        </div>
    </div>
    <!-- Login Popup -->
    <div class="login-popup" id="loginPopup">
        <div class="login-popup-content">
            <h3>Please Login</h3>
            <p>You need to be logged in to change menu availability.</p>
            <div class="login-popup-buttons">
                <a href="/team1/toyouke/src/api/login.php" class="login-btn">Login</a>
                <button class="cancel-btn" id="cancelLogin">Cancel</button>
            </div>
        </div>
    </div>

    <div class="footer">
    <p>&copy; 2025 Akashi Shokudou. All rights reserved.</p>
    <p>Powered by <a href="/team1/toyouke/src/api/login.php">Shokudou</a></p>
    <p>
        **お問い合わせ先:**<br>
        〒673-0892 兵庫県明石市本町1丁目1-1<br>
        電話: 078-123-4567 (代表)
    </p>
    <p>
        **運営元:**<br>
        明石食堂運営事務局 </p>
    </div>
    <script src="assets/script.js"></script>
    <script src="assets/calendar.js"></script>
</body>
</html>