<?php
//user profile and statistics page
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/Game.php';

//require login
requireLogin();

$db = getDBConnection();
$userObj = new User($db);
$gameObj = new Game($db);

//handle prefs updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
    } else if ($_POST['action'] === 'update_preferences') {
        $preferences = [
            'default_puzzle_size' => $_POST['default_puzzle_size'] ?? '4x4',
            'preferred_background_image_id' => $_POST['preferred_background_id'] ?? null,
            'sound_enabled' => isset($_POST['sound_enabled']) ? 1 : 0,
            'animations_enabled' => isset($_POST['animations_enabled']) ? 1 : 0,
            'theme_preference' => $_POST['theme_preference'] ?? 'light'
        ];
        
        $result = $userObj->updatePreferences($_SESSION['user_id'], $preferences);
        $_SESSION['message'] = $result['message'];
    }
    
    header('Location: profile.php');
    exit();
}

//get user data
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

//get user prefs
$preferences = $userObj->getPreferences($_SESSION['user_id']);

//get user stats
$stats = $userObj->getStatistics($_SESSION['user_id']);

//get recent games
$recentGames = $gameObj->getUserRecentGames($_SESSION['user_id'], 10);

//get achievements
$achievements = $userObj->getAchievements($_SESSION['user_id']);

//get available backgrounds
$stmt = $db->prepare("SELECT * FROM background_images WHERE is_active = 1");
$stmt->execute();
$backgrounds = $stmt->fetchAll();

//calc win rate
$winRate = $stats['total_games'] > 0 ? round(($stats['games_won'] / $stats['total_games']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1️⃣5️⃣ Puzzle - Your Profile</title>
    <link rel="stylesheet" href="profile-styling.css">
</head>
<body>
    <div class="header">
        <h1>Your Profile</h1>
        <p>Track your progress and achievements</p>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?php 
                    echo htmlspecialchars($_SESSION['message']); 
                    unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <div class="profile-card">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="username"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="email"><?php echo htmlspecialchars($user['email']); ?></div>
                <div class="member-since">
                    Member since <?php echo date('F Y', strtotime($user['registration_date'])); ?>
                </div>
                <a href="game.php" class="btn btn-secondary">Back to Game</a>
            </div>

            <div class="stats-overview">
                <h2>Statistics Overview</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_games']; ?></div>
                        <div class="stat-label">Total Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['games_won'] ?? 0; ?></div>
                        <div class="stat-label">Games Won</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $winRate; ?>%</div>
                        <div class="stat-label">Win Rate</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo $stats['best_time'] ? gmdate("i:s", $stats['best_time']) : '--:--'; ?>
                        </div>
                        <div class="stat-label">Best Time</div>
                    </div>
                </div>
            </div>
        </div>

        <!--recent games -->
        <div class="section">
            <h2>Recent Games</h2>
            <?php if (empty($recentGames)): ?>
                <p>No games played yet. Start playing to see your history!</p>
            <?php else: ?>
                <table class="recent-games-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Puzzle Size</th>
                            <th>Time</th>
                            <th>Moves</th>
                            <th>Background</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGames as $game): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($game['game_date'])); ?></td>
                                <td><?php echo $game['puzzle_size']; ?></td>
                                <td><?php echo gmdate("i:s", $game['time_taken_seconds']); ?></td>
                                <td><?php echo $game['moves_count']; ?></td>
                                <td><?php echo htmlspecialchars($game['background_used'] ?? 'Default'); ?></td>
                                <td>
                                    <?php if ($game['win_status']): ?>
                                        <span class="win-badge">Won</span>
                                    <?php else: ?>
                                        <span class="loss-badge">Abandoned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- achievements -->
        <div class="section">
            <h2>Achievements</h2>
            <div class="achievements-grid">
                <?php foreach ($achievements as $achievement): ?>
                    <div class="achievement-card <?php echo $achievement['earned_date'] ? 'earned' : 'locked'; ?>">
                        <div class="achievement-icon">
                            <?php echo $achievement['earned_date'] ? '🏆' : '🔒'; ?>
                        </div>
                        <div class="achievement-name">
                            <?php echo htmlspecialchars($achievement['achievement_name']); ?>
                        </div>
                        <div class="achievement-desc">
                            <?php echo htmlspecialchars($achievement['achievement_description']); ?>
                        </div>
                        <div class="achievement-points">
                            <?php echo $achievement['points_value']; ?> points
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- prefs -->
        <div class="section">
            <h2>Game Preferences</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_preferences">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="default_puzzle_size">Default Puzzle Size</label>
                    <select id="default_puzzle_size" name="default_puzzle_size">
                        <option value="3x3" <?php echo $preferences['default_puzzle_size'] === '3x3' ? 'selected' : ''; ?>>3x3 (Easy)</option>
                        <option value="4x4" <?php echo $preferences['default_puzzle_size'] === '4x4' ? 'selected' : ''; ?>>4x4 (Normal)</option>
                        <option value="5x5" <?php echo $preferences['default_puzzle_size'] === '5x5' ? 'selected' : ''; ?>>5x5 (Hard)</option>
                        <option value="6x6" <?php echo $preferences['default_puzzle_size'] === '6x6' ? 'selected' : ''; ?>>6x6 (Expert)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="preferred_background_id">Preferred Background</label>
                    <select id="preferred_background_id" name="preferred_background_id">
                        <option value="">No preference</option>
                        <?php foreach ($backgrounds as $bg): ?>
                            <option value="<?php echo $bg['image_id']; ?>" 
                                    <?php echo $preferences['preferred_background_image_id'] == $bg['image_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bg['image_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="theme_preference">Theme</label>
                    <select id="theme_preference" name="theme_preference">
                        <option value="light" <?php echo $preferences['theme_preference'] === 'light' ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo $preferences['theme_preference'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                    </select>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="sound_enabled" name="sound_enabled" 
                           <?php echo $preferences['sound_enabled'] ? 'checked' : ''; ?>>
                    <label for="sound_enabled">Enable sound effects</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="animations_enabled" name="animations_enabled" 
                           <?php echo $preferences['animations_enabled'] ? 'checked' : ''; ?>>
                    <label for="animations_enabled">Enable animations</label>
                </div>

                <button type="submit" class="btn btn-primary">Save Preferences</button>
            </form>
        </div>
    </div>
</body>
</html>