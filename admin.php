<?php
//administrative panel for managing the game
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'game.php';

//require admin access
requireLogin();
requireAdmin();

$db = getDBConnection();
$userObj = new User($db);
$gameObj = new Game($db);

//handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: admin.php');
        exit;
    }
    
    switch ($_POST['action']) {
        case 'toggle_user':
            $stmt = $db->prepare("UPDATE users SET role = CASE WHEN role = 'admin' THEN 'player' ELSE 'admin' END WHERE user_id = ?");
            $stmt->execute([$_POST['user_id']]);
            $_SESSION['success'] = 'User role updated successfully';
            break;
            
        case 'delete_user':
            if ($_POST['user_id'] != $_SESSION['user_id']) {
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$_POST['user_id']]);
                $_SESSION['success'] = 'User deleted successfully';
            }
            break;
            
        case 'toggle_image':
            $stmt = $db->prepare("UPDATE background_images SET is_active = NOT is_active WHERE image_id = ?");
            $stmt->execute([$_POST['image_id']]);
            $_SESSION['success'] = 'Image status updated';
            break;
            
        case 'add_image':
            $stmt = $db->prepare("INSERT INTO background_images (image_name, image_url, uploaded_by_user_id) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['image_name'], $_POST['image_url'], $_SESSION['user_id']]);
            $_SESSION['success'] = 'New background image added';
            break;
            
        case 'cleanup_sessions':
            $deleted = $gameObj->cleanupOldSessions(24);
            $_SESSION['success'] = "Cleaned up $deleted old game sessions";
            break;
    }
    
    header('Location: admin.php');
    exit;
}

//get stats
$stmt = $db->query("SELECT COUNT(*) as total_users FROM users");
$totalUsers = $stmt->fetch()['total_users'];

$stmt = $db->query("SELECT COUNT(*) as total_games FROM game_stats");
$totalGames = $stmt->fetch()['total_games'];

$stmt = $db->query("SELECT COUNT(*) as active_sessions FROM game_sessions");
$activeSessions = $stmt->fetch()['active_sessions'];

//get all users
$stmt = $db->query("
    SELECT u.*, 
           COUNT(DISTINCT gs.stat_id) as games_played,
           MAX(u.last_login) as last_seen
    FROM users u
    LEFT JOIN game_stats gs ON u.user_id = gs.user_id
    GROUP BY u.user_id
    ORDER BY u.registration_date DESC
");
$users = $stmt->fetchAll();

//get all background images
$stmt = $db->query("
    SELECT bi.*, u.username as uploaded_by,
           COUNT(DISTINCT gs.stat_id) as times_used
    FROM background_images bi
    LEFT JOIN users u ON bi.uploaded_by_user_id = u.user_id
    LEFT JOIN game_stats gs ON bi.image_id = gs.background_image_id
    GROUP BY bi.image_id
    ORDER BY bi.upload_date DESC
");
$images = $stmt->fetchAll();

//get game stats by size
$stmt = $db->query("
    SELECT puzzle_size, 
           COUNT(*) as total_games,
           AVG(time_taken_seconds) as avg_time,
           AVG(moves_count) as avg_moves
    FROM game_stats
    WHERE win_status = 1
    GROUP BY puzzle_size
");
$gameStatsBySize = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1️⃣5️⃣ Puzzle - Admin Panel</title>
    <link rel="stylesheet" href="admin-styling.css">
</head>
<body>
    <div class="admin-header">
        <h1>Admin Panel</h1>
        <nav class="admin-nav">
            <a href="game.php">Back to Game</a>
            <a href="leaderboard.php">Leaderboard</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- stats overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo $totalUsers; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Games Played</h3>
                <div class="value"><?php echo $totalGames; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Sessions</h3>
                <div class="value"><?php echo $activeSessions; ?></div>
            </div>
            <div class="stat-card">
                <h3>Background Images</h3>
                <div class="value"><?php echo count($images); ?></div>
            </div>
        </div>

        <!-- game stats by size -->
        <div class="admin-section">
            <h2>Game Statistics by Puzzle Size</h2>
            <table>
                <thead>
                    <tr>
                        <th>Puzzle Size</th>
                        <th>Total Games</th>
                        <th>Average Time</th>
                        <th>Average Moves</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gameStatsBySize as $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['puzzle_size']); ?></td>
                            <td><?php echo $stat['total_games']; ?></td>
                            <td><?php echo gmdate("i:s", round($stat['avg_time'])); ?></td>
                            <td><?php echo round($stat['avg_moves']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- user management -->
        <div class="admin-section">
            <h2>User Management</h2>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Games Played</th>
                        <th>Registered</th>
                        <th>Last Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['games_played']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($user['registration_date'])); ?></td>
                            <td><?php echo $user['last_seen'] ? date('Y-m-d H:i', strtotime($user['last_seen'])) : 'Never'; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <button type="submit" class="btn btn-warning">
                                                <?php echo $user['role'] === 'admin' ? 'Make Player' : 'Make Admin'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- background image management -->
        <div class="admin-section">
            <h2>Background Image Management</h2>
            
            <!-- add new image form -->
            <form method="POST" style="margin-bottom: 20px;">
                <input type="hidden" name="action" value="add_image">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div style="display: flex; gap: 10px; align-items: end;">
                    <div class="form-group" style="flex: 1;">
                        <label>Image Name:</label>
                        <input type="text" name="image_name" required>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>Image URL:</label>
                        <input type="text" name="image_url" required placeholder="images/backgrounds/new-image.jpg">
                    </div>
                    <button type="submit" class="btn btn-success" style="height: fit-content;">Add Image</button>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Image Name</th>
                        <th>URL</th>
                        <th>Status</th>
                        <th>Times Used</th>
                        <th>Uploaded By</th>
                        <th>Upload Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($images as $image): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($image['image_name']); ?></td>
                            <td><?php echo htmlspecialchars($image['image_url']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $image['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $image['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo $image['times_used']; ?></td>
                            <td><?php echo htmlspecialchars($image['uploaded_by'] ?? 'System'); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($image['upload_date'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_image">
                                    <input type="hidden" name="image_id" value="<?php echo $image['image_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <button type="submit" class="btn btn-warning">
                                        <?php echo $image['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- sys maintenance -->
        <div class="admin-section">
            <h2>System Maintenance</h2>
            <form method="POST">
                <input type="hidden" name="action" value="cleanup_sessions">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" class="btn btn-primary">Clean Up Old Game Sessions</button>
                <p style="margin-top: 10px; color: #666;">
                    This will remove abandoned game sessions older than 24 hours.
                </p>
            </form>
        </div>
    </div>
</body>
</html>