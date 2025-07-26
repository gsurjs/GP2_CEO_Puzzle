<?php
//main game page with database integration
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/Game.php';

//require login
requireLogin();

$db = getDBConnection();
$userObj = new User($db);
$gameObj = new Game($db);

//fetch user prefs
$preferences = $userObj->getPreferences($_SESSION['user_id']);

//handle ajax requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'start_game':
            $result = $gameObj->startNewGame(
                $_SESSION['user_id'],
                $_POST['puzzle_size'] ?? $preferences['default_puzzle_size'],
                $_POST['background_id'] ?? $preferences['preferred_background_image_id']
            );
            echo json_encode($result);
            exit;
            
        case 'update_game':
            $result = $gameObj->updateGameState(
                $_POST['session_id'],
                json_decode($_POST['puzzle_state'], true),
                true
            );
            echo json_encode($result);
            exit;
            
        case 'shuffle':
            $session = $gameObj->getGameSession($_POST['session_id']);
            $shuffled = $gameObj->shufflePuzzle($session['puzzle_state']);
            $result = $gameObj->updateGameState($_POST['session_id'], $shuffled, false);
            $result['puzzle_state'] = $shuffled;
            echo json_encode($result);
            exit;
            
        case 'complete_game':
            $result = $gameObj->completeGame(
                $_POST['session_id'],
                $_SESSION['user_id'],
                $_POST['won'] === 'true'
            );
            
            if ($result['success'] && $_POST['won'] === 'true') {
                // Check for new achievements
                $achievements = $userObj->checkAchievements($_SESSION['user_id']);
                $result['new_achievements'] = $achievements;
            }
            
            echo json_encode($result);
            exit;
    }
}

//get available backgrounds
$stmt = $db->prepare("SELECT * FROM background_images WHERE is_active = 1");
$stmt->execute();
$backgrounds = $stmt->fetchAll();

//get user stats
$stats = $userObj->getStatistics($_SESSION['user_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fifteen Puzzle - Play</title>
    <link rel="stylesheet" href="game-styling.css">
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h1>Fifteen Puzzle</h1>
            <div class="game-info">
                <div>Player: <span><?php echo htmlspecialchars($_SESSION['username']); ?></span></div>
                <div>Total Games: <span><?php echo $stats['total_games']; ?></span></div>
                <div>Games Won: <span><?php echo $stats['games_won'] ?? 0; ?></span></div>
            </div>
        </div>

        <div class="game-container">
            <div>
                <div id="puzzlearea"></div>
            </div>

            <div class="controls">
                <div class="control-group">
                    <h3>Game Controls</h3>
                    <div class="timer" id="timer">00:00</div>
                    <div>Moves: <span id="moveCount">0</span></div>
                    <button id="newGameBtn" class="btn-success">New Game</button>
                    <button id="shuffleBtn">Shuffle</button>
                    <button id="pauseBtn">Pause</button>
                </div>

                <div class="control-group">
                    <h3>Settings</h3>
                    <label>Puzzle Size:</label>
                    <select id="puzzleSize">
                        <option value="3x3">3x3 (Easy)</option>
                        <option value="4x4" selected>4x4 (Normal)</option>
                        <option value="5x5">5x5 (Hard)</option>
                        <option value="6x6">6x6 (Expert)</option>
                    </select>

                    <label>Background:</label>
                    <select id="backgroundSelect">
                        <?php foreach ($backgrounds as $bg): ?>
                            <option value="<?php echo $bg['image_id']; ?>" 
                                    data-url="<?php echo htmlspecialchars($bg['image_url']); ?>">
                                <?php echo htmlspecialchars($bg['image_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>
                        <input type="checkbox" id="soundEnabled" 
                               <?php echo $preferences['sound_enabled'] ? 'checked' : ''; ?>>
                        Enable Sound
                    </label>

                    <label>
                        <input type="checkbox" id="animationsEnabled" 
                               <?php echo $preferences['animations_enabled'] ? 'checked' : ''; ?>>
                        Enable Animations
                    </label>
                </div>

                <div class="control-group">
                    <h3>Your Best Stats</h3>
                    <div class="stats-display">
                        <div class="stat-item">
                            <span>Best Time:</span>
                            <span><?php echo $stats['best_time'] ? gmdate("i:s", $stats['best_time']) : 'N/A'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span>Best Moves:</span>
                            <span><?php echo $stats['best_moves'] ?? 'N/A'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span>Avg Time:</span>
                            <span><?php echo $stats['avg_win_time'] ? gmdate("i:s", round($stats['avg_win_time'])) : 'N/A'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span>Avg Moves:</span>
                            <span><?php echo $stats['avg_moves'] ? round($stats['avg_moves']) : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>

                <div class="control-group">
                    <a href="profile.php"><button>View Profile</button></a>
                    <a href="leaderboard.php"><button>Leaderboard</button></a>
                    <a href="logout.php"><button class="btn-danger">Logout</button></a>
                </div>
            </div>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="achievement-popup" id="achievementPopup">
        <h2>Achievement Unlocked!</h2>
        <h3 id="achievementName"></h3>
        <p id="achievementDesc"></p>
        <button onclick="closeAchievement()">Close</button>
    </div>
</body>
</html>
