<?php
//display game leaderboards
require_once 'config/database.php';
require_once 'classes/Game.php';

$db = getDBConnection();
$gameObj = new Game($db);

//get selected puzzle size
$selectedSize = $_GET['size'] ?? 'all';
$validSizes = ['all', '3x3', '4x4', '5x5', '6x6'];
if (!in_array($selectedSize, $validSizes)) {
    $selectedSize = 'all';
}

//get leaderboard data
$leaderboard = $gameObj->getLeaderboard($selectedSize === 'all' ? null : $selectedSize, 50);

//get unique puzzle sizes for filter
$stmt = $db->query("SELECT DISTINCT puzzle_size FROM game_stats ORDER BY puzzle_size");
$availableSizes = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1️⃣5️⃣ Puzzle - Leaderboard</title>
    <link rel="stylesheet" href="leaderboard-styling.css">
</head>
<body>
    <div class="header">
        <h1>🏆 Leaderboard</h1>
        <p>Top players from around the world</p>
    </div>

    <div class="container">
        <div class="nav-bar">
            <div class="filter-buttons">
                <a href="?size=all" class="filter-btn <?php echo $selectedSize === 'all' ? 'active' : ''; ?>">All Sizes</a>
                <?php foreach ($availableSizes as $size): ?>
                    <a href="?size=<?php echo $size; ?>" 
                       class="filter-btn <?php echo $selectedSize === $size ? 'active' : ''; ?>">
                        <?php echo $size; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a href="<?php echo isLoggedIn() ? 'game.php' : 'index.php'; ?>" class="back-btn">
                Back to <?php echo isLoggedIn() ? 'Game' : 'Home'; ?>
            </a>
        </div>

        <div class="leaderboard-table">
            <div class="table-header">
                <?php echo $selectedSize === 'all' ? 'All Puzzle Sizes' : $selectedSize . ' Puzzle'; ?>
            </div>

            <?php if (empty($leaderboard)): ?>
                <div class="empty-state">
                    <div class="trophy-icon">🏆</div>
                    <h2>No Records Yet</h2>
                    <p>Be the first to complete a puzzle and claim the top spot!</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="10%">Rank</th>
                            <th width="20%">Player</th>
                            <?php if ($selectedSize === 'all'): ?>
                                <th width="10%">Size</th>
                            <?php endif; ?>
                            <th width="15%">Time</th>
                            <th width="10%">Moves</th>
                            <th width="20%">Background</th>
                            <th width="15%">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $index => $entry): ?>
                            <tr>
                                <td>
                                    <span class="rank rank-<?php echo $index + 1; ?>">
                                        <?php 
                                        if ($index + 1 <= 3) {
                                            echo ['🥇', '🥈', '🥉'][$index];
                                        } else {
                                            echo '#' . ($index + 1);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="username"><?php echo htmlspecialchars($entry['username']); ?></span>
                                </td>
                                <?php if ($selectedSize === 'all'): ?>
                                    <td>
                                        <span class="puzzle-size"><?php echo $entry['puzzle_size']; ?></span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="time"><?php echo gmdate("i:s", $entry['time_taken_seconds']); ?></span>
                                </td>
                                <td>
                                    <span class="moves"><?php echo $entry['moves_count']; ?></span>
                                </td>
                                <td>
                                    <span class="background-name">
                                        <?php echo htmlspecialchars($entry['background_used'] ?? 'Default'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="date">
                                        <?php echo date('M d, Y', strtotime($entry['game_date'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>