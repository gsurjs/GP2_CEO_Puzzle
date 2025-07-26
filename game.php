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
            <h1>1️⃣5️⃣ Puzzle</h1>
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
    <script>
        //game state vars
        let gameState = {
            sessionId: null,
            puzzleState: null,
            emptyPos: {x: 0, y: 0},
            moves: 0,
            startTime: null,
            timerInterval: null,
            isPaused: false,
            puzzleSize: 4,
            tileSize: 100
        };

        const csrfToken = '<?php echo generateCSRFToken(); ?>';

        //init game
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('newGameBtn').addEventListener('click', startNewGame);
            document.getElementById('shuffleBtn').addEventListener('click', shufflePuzzle);
            document.getElementById('pauseBtn').addEventListener('click', togglePause);
            document.getElementById('puzzleSize').addEventListener('change', function() {
                if (confirm('Changing puzzle size will start a new game. Continue?')) {
                    startNewGame();
                }
            });
            document.getElementById('backgroundSelect').addEventListener('change', updateBackground);

            //set init prefs
            document.getElementById('puzzleSize').value = '<?php echo $preferences['default_puzzle_size']; ?>';
            <?php if ($preferences['preferred_background_image_id']): ?>
            document.getElementById('backgroundSelect').value = '<?php echo $preferences['preferred_background_image_id']; ?>';
            <?php endif; ?>

            startNewGame();
        });

        function startNewGame() {
            //stop timer if running
            if (gameState.timerInterval) {
                clearInterval(gameState.timerInterval);
            }

            const puzzleSize = document.getElementById('puzzleSize').value;
            const backgroundId = document.getElementById('backgroundSelect').value;

            fetch('game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=start_game&puzzle_size=${puzzleSize}&background_id=${backgroundId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    gameState.sessionId = data.session_id;
                    gameState.puzzleState = data.puzzle_state;
                    gameState.moves = 0;
                    gameState.startTime = new Date();
                    gameState.isPaused = false;

                    const [rows, cols] = puzzleSize.split('x').map(n => parseInt(n));
                    gameState.puzzleSize = rows;

                    initializePuzzle();
                    updateDisplay();
                    startTimer();
                }
            });
        }

        function initializePuzzle() {
            const puzzleArea = document.getElementById('puzzlearea');
            puzzleArea.innerHTML = '';
            
            // Set puzzle area size
            const areaSize = gameState.puzzleSize * gameState.tileSize;
            puzzleArea.style.width = areaSize + 'px';
            puzzleArea.style.height = areaSize + 'px';

            // Create tiles
            for (let row = 0; row < gameState.puzzleSize; row++) {
                for (let col = 0; col < gameState.puzzleSize; col++) {
                    const value = gameState.puzzleState[row][col];
                    if (value !== 0) {
                        createTile(row, col, value);
                    } else {
                        gameState.emptyPos = {x: col, y: row};
                    }
                }
            }

            updateBackground();
        }

        function createTile(row, col, value) {
            const tile = document.createElement('div');
            tile.className = 'puzzlepiece';
            tile.textContent = value;
            tile.id = 'tile' + value;
            
            //set position
            tile.style.left = (col * gameState.tileSize) + 'px';
            tile.style.top = (row * gameState.tileSize) + 'px';
            
            //calc original position for background
            const origRow = Math.floor((value - 1) / gameState.puzzleSize);
            const origCol = (value - 1) % gameState.puzzleSize;
            tile.style.backgroundPosition = 
                `${-origCol * gameState.tileSize}px ${-origRow * gameState.tileSize}px`;
            
            //set background size
            tile.style.backgroundSize = 
                `${gameState.puzzleSize * gameState.tileSize}px ${gameState.puzzleSize * gameState.tileSize}px`;
            
            //store position
            tile.dataset.row = row;
            tile.dataset.col = col;
            
            //event handlers
            tile.addEventListener('click', function() {
                if (!gameState.isPaused) {
                    moveTile(this);
                }
            });
            
            tile.addEventListener('mouseenter', function() {
                if (!gameState.isPaused && isMovable(this)) {
                    this.classList.add('movablepiece');
                }
            });
            
            tile.addEventListener('mouseleave', function() {
                this.classList.remove('movablepiece');
            });
            
            document.getElementById('puzzlearea').appendChild(tile);
        }

        function isMovable(tile) {
            const row = parseInt(tile.dataset.row);
            const col = parseInt(tile.dataset.col);
            
            return (Math.abs(row - gameState.emptyPos.y) === 1 && col === gameState.emptyPos.x) ||
                   (Math.abs(col - gameState.emptyPos.x) === 1 && row === gameState.emptyPos.y);
        }

        function moveTile(tile) {
            if (!isMovable(tile)) return;
            
            const row = parseInt(tile.dataset.row);
            const col = parseInt(tile.dataset.col);
            const value = parseInt(tile.textContent);
            
            //update puzzle state
            gameState.puzzleState[gameState.emptyPos.y][gameState.emptyPos.x] = value;
            gameState.puzzleState[row][col] = 0;
            
            //move tile visually
            tile.style.left = (gameState.emptyPos.x * gameState.tileSize) + 'px';
            tile.style.top = (gameState.emptyPos.y * gameState.tileSize) + 'px';
            tile.dataset.row = gameState.emptyPos.y;
            tile.dataset.col = gameState.emptyPos.x;
            
            //update empty position
            gameState.emptyPos = {x: col, y: row};
            
            //update moves
            gameState.moves++;
            updateDisplay();
            
            //play move sound if enabled
            if (document.getElementById('soundEnabled').checked) {
                playMoveSound();
            }
            
            //send update to server
            updateGameState();
            
            //check for win
            if (checkWin()) {
                handleWin();
            }
        }

        function shufflePuzzle() {
            if (!gameState.sessionId) return;
            
            fetch('game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=shuffle&session_id=${gameState.sessionId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    gameState.puzzleState = data.puzzle_state;
                    gameState.moves = 0;
                    gameState.startTime = new Date();
                    initializePuzzle();
                    updateDisplay();
                    
                    if (gameState.timerInterval) {
                        clearInterval(gameState.timerInterval);
                    }
                    startTimer();
                }
            });
        }

        function updateGameState() {
            fetch('game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_game&session_id=${gameState.sessionId}&puzzle_state=${JSON.stringify(gameState.puzzleState)}&csrf_token=${csrfToken}`
            });
        }

        function checkWin() {
            let expected = 1;
            for (let row = 0; row < gameState.puzzleSize; row++) {
                for (let col = 0; col < gameState.puzzleSize; col++) {
                    if (row === gameState.puzzleSize - 1 && col === gameState.puzzleSize - 1) {
                        return gameState.puzzleState[row][col] === 0;
                    }
                    if (gameState.puzzleState[row][col] !== expected++) {
                        return false;
                    }
                }
            }
            return true;
        }

        function handleWin() {
            clearInterval(gameState.timerInterval);
            
            const timeTaken = Math.floor((new Date() - gameState.startTime) / 1000);
            
            fetch('game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=complete_game&session_id=${gameState.sessionId}&won=true&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Congratulations! You solved the puzzle in ${formatTime(timeTaken)} with ${gameState.moves} moves!`);
                    
                    // Show achievements if any
                    if (data.new_achievements && data.new_achievements.length > 0) {
                        showAchievements(data.new_achievements);
                    }
                    
                    // Start new game
                    setTimeout(startNewGame, 2000);
                }
            });
        }

        function togglePause() {
            gameState.isPaused = !gameState.isPaused;
            document.getElementById('pauseBtn').textContent = gameState.isPaused ? 'Resume' : 'Pause';
            
            if (gameState.isPaused) {
                clearInterval(gameState.timerInterval);
            } else {
                startTimer();
            }
        }

        function startTimer() {
            gameState.timerInterval = setInterval(updateTimer, 1000);
        }

        function updateTimer() {
            const elapsed = Math.floor((new Date() - gameState.startTime) / 1000);
            document.getElementById('timer').textContent = formatTime(elapsed);
        }

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        function updateDisplay() {
            document.getElementById('moveCount').textContent = gameState.moves;
        }

        function updateBackground() {
            const select = document.getElementById('backgroundSelect');
            const selectedOption = select.options[select.selectedIndex];
            const imageUrl = selectedOption.getAttribute('data-url');
            
            const tiles = document.querySelectorAll('.puzzlepiece');
            tiles.forEach(tile => {
                tile.style.backgroundImage = `url('${imageUrl}')`;
            });
        }

        function playMoveSound() {
        //placeholder
        }

        function showAchievements(achievements) {
            achievements.forEach((achievement, index) => {
                setTimeout(() => {
                    document.getElementById('achievementName').textContent = achievement.achievement_name;
                    document.getElementById('achievementDesc').textContent = achievement.achievement_description;
                    document.getElementById('overlay').style.display = 'block';
                    document.getElementById('achievementPopup').style.display = 'block';
                }, index * 3000);
            });
        }

        function closeAchievement() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('achievementPopup').style.display = 'none';
        }
    </script>
</body>
</html>
