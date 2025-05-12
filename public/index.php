<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Utils\DateUtils;
use App\Config\DatabaseConfig;

// Set default timezone
DateUtils::setDefaultTimezone('Asia/Tokyo');

// Determine which date to show
$dateOffset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Get target date
$targetDate = DateUtils::getOffsetDate($dateOffset);

// Format date for display
$displayDate = DateUtils::formatDateForDisplay($targetDate);

// Get relative date label
$dateLabel = DateUtils::getRelativeDateLabel($dateOffset);

// Get database connection
$db = DatabaseConfig::getInstance()->getConnection();

// Fetch menu items for the target date
$query = "SELECT * FROM menu_items WHERE date = :date";
$stmt = $db->prepare($query);
$stmt->bindValue(':date', $targetDate->format('Y-m-d'));
$stmt->execute();
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($menuItems === false) {
    $menuItems = [];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shokudou - Today's Menu</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Akashi Shokudou</h1>
            <p class="date">
                <span class="date-label"><?= htmlspecialchars($dateLabel) ?></span>
                <span class="date-full"><?= htmlspecialchars($displayDate) ?></span>
            </p>

            <div class="calendar">
                <div class="calendarButton">
                    <a>calendar</a>
                </div>
            
            <div class="date-navigation">
                <a href="index.php?offset=<?= $dateOffset-1 ?>" class="nav-btn">&laquo; Previous Day</a>
                <a href="index.php" class="nav-btn <?= $dateOffset === 0 ? 'active' : '' ?>">Today</a>
                <a href="index.php?offset=<?= $dateOffset+1 ?>" class="nav-btn">Next Day &raquo;</a>
            </div>
        </header>
        
        <?php if (empty($menuItems)): ?>
            <div class="no-menu">
                <p>No menu items available for today. Please check back later.</p>
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
                            <?php if ($item['tag']): ?>
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
                            <button class="toggle-status" data-id="<?= $item['id'] ?>" data-status="<?= $item['available'] ? 'true' : 'false' ?>">
                                Toggle Status
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>