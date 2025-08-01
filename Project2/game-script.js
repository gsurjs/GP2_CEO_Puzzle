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

        // ✅ Automatically shuffle right after creating the new game
        shufflePuzzle();
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
            gameState.sessionId = data.session_id;
            gameState.puzzleState = data.puzzle_state;
            gameState.moves = 0;
            gameState.startTime = new Date();
            gameState.isPaused = false;

            const rows = gameState.puzzleSize;
            const cols = gameState.puzzleSize;

            initializePuzzle();
            updateDisplay();
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