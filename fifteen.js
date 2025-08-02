// Fifteen Puzzle JavaScript Implementation
// Advanced version with local storage for persistence

// Global game state
let gameState = {
    sessionId: null,
    puzzleState: null,
    emptyPos: {x: 0, y: 0},
    moves: 0,
    startTime: null,
    timerInterval: null,
    isPaused: false,
    puzzleSize: 4,
    tileSize: 100,
    backgroundImage: 'background.jpg'
};

// Local storage keys for persistence
const STORAGE_KEYS = {
    STATS: 'fifteen_puzzle_stats',
    PREFERENCES: 'fifteen_puzzle_prefs',
    CURRENT_GAME: 'fifteen_puzzle_current'
};

// Initialize the game when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    loadPreferences();
    loadStats();
    setupEventListeners();
    startNewGame();
});

// Set up all event listeners
function setupEventListeners() {
    document.getElementById('newGameBtn').addEventListener('click', startNewGame);
    document.getElementById('shuffleBtn').addEventListener('click', shufflePuzzle);
    document.getElementById('pauseBtn').addEventListener('click', togglePause);
    document.getElementById('puzzleSize').addEventListener('change', function() {
        if (confirm('Changing puzzle size will start a new game. Continue?')) {
            startNewGame();
        } else {
            // Reset to current size
            this.value = gameState.puzzleSize + 'x' + gameState.puzzleSize;
        }
    });
    document.getElementById('backgroundSelect').addEventListener('change', updateBackground);
    document.getElementById('randomBgBtn').addEventListener('click', selectRandomBackground);
    
    // Preferences listeners
    document.getElementById('soundEnabled').addEventListener('change', savePreferences);
    document.getElementById('animationsEnabled').addEventListener('change', savePreferences);
}

// Load user preferences from local storage
function loadPreferences() {
    const prefs = JSON.parse(localStorage.getItem(STORAGE_KEYS.PREFERENCES) || '{}');
    
    document.getElementById('puzzleSize').value = prefs.puzzleSize || '4x4';
    document.getElementById('backgroundSelect').value = prefs.background || 'background.jpg';
    document.getElementById('soundEnabled').checked = prefs.soundEnabled !== false;
    document.getElementById('animationsEnabled').checked = prefs.animationsEnabled !== false;
    
    gameState.backgroundImage = prefs.background || 'background.jpg';
}

// Save user preferences to local storage
function savePreferences() {
    const prefs = {
        puzzleSize: document.getElementById('puzzleSize').value,
        background: document.getElementById('backgroundSelect').value,
        soundEnabled: document.getElementById('soundEnabled').checked,
        animationsEnabled: document.getElementById('animationsEnabled').checked
    };
    
    localStorage.setItem(STORAGE_KEYS.PREFERENCES, JSON.stringify(prefs));
}

// Load game statistics from local storage
function loadStats() {
    const stats = JSON.parse(localStorage.getItem(STORAGE_KEYS.STATS) || '{}');
    
    document.getElementById('totalGames').textContent = stats.totalGames || 0;
    document.getElementById('gamesWon').textContent = stats.gamesWon || 0;
    document.getElementById('bestTime').textContent = stats.bestTime ? formatTime(stats.bestTime) : 'N/A';
    document.getElementById('bestMoves').textContent = stats.bestMoves || 'N/A';
    document.getElementById('avgTime').textContent = stats.avgTime ? formatTime(Math.round(stats.avgTime)) : 'N/A';
    document.getElementById('avgMoves').textContent = stats.avgMoves ? Math.round(stats.avgMoves) : 'N/A';
}

// Update and save game statistics
function updateStats(won, timeTaken, moves) {
    const stats = JSON.parse(localStorage.getItem(STORAGE_KEYS.STATS) || '{}');
    
    stats.totalGames = (stats.totalGames || 0) + 1;
    
    if (won) {
        stats.gamesWon = (stats.gamesWon || 0) + 1;
        
        // Update best stats
        if (!stats.bestTime || timeTaken < stats.bestTime) {
            stats.bestTime = timeTaken;
        }
        if (!stats.bestMoves || moves < stats.bestMoves) {
            stats.bestMoves = moves;
        }
        
        // Update averages
        const previousWins = stats.gamesWon - 1;
        if (previousWins > 0) {
            stats.avgTime = ((stats.avgTime * previousWins) + timeTaken) / stats.gamesWon;
            stats.avgMoves = ((stats.avgMoves * previousWins) + moves) / stats.gamesWon;
        } else {
            stats.avgTime = timeTaken;
            stats.avgMoves = moves;
        }
    }
    
    localStorage.setItem(STORAGE_KEYS.STATS, JSON.stringify(stats));
    loadStats(); // Refresh display
}

// Function to select random background
function selectRandomBackground() {
    const select = document.getElementById('backgroundSelect');
    const options = Array.from(select.options);
    
    if (options.length > 0) {
        const randomOption = options[Math.floor(Math.random() * options.length)];
        select.value = randomOption.value;
        updateBackground();
        savePreferences();
        
        // Visual feedback
        const button = document.getElementById('randomBgBtn');
        button.textContent = '✨';
        setTimeout(() => {
            button.textContent = '🎲';
        }, 500);
    }
}

// Start a new game
function startNewGame() {
    // Stop timer if running
    if (gameState.timerInterval) {
        clearInterval(gameState.timerInterval);
    }

    const puzzleSize = document.getElementById('puzzleSize').value;
    const [rows, cols] = puzzleSize.split('x').map(n => parseInt(n));
    
    gameState.puzzleSize = rows;
    gameState.moves = 0;
    gameState.startTime = new Date();
    gameState.isPaused = false;
    
    // Create solved puzzle
    gameState.puzzleState = generateSolvedPuzzle(rows, cols);
    
    // Shuffle it
    gameState.puzzleState = shufflePuzzleState(gameState.puzzleState);
    
    initializePuzzle();
    updateDisplay();
    startTimer();
    
    // Save preferences
    savePreferences();
}

// Generate a solved puzzle state
function generateSolvedPuzzle(rows, cols) {
    const puzzle = [];
    let number = 1;
    
    for (let i = 0; i < rows; i++) {
        puzzle[i] = [];
        for (let j = 0; j < cols; j++) {
            if (i === rows - 1 && j === cols - 1) {
                puzzle[i][j] = 0; // Empty space
            } else {
                puzzle[i][j] = number++;
            }
        }
    }
    
    return puzzle;
}

// Initialize the puzzle display
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

// Create a puzzle tile
function createTile(row, col, value) {
    const tile = document.createElement('div');
    tile.className = 'puzzlepiece';
    tile.textContent = value;
    tile.id = 'tile' + value;
    
    // Set position
    tile.style.left = (col * gameState.tileSize) + 'px';
    tile.style.top = (row * gameState.tileSize) + 'px';
    
    // Calculate WHERE THIS TILE BELONGS in the solved puzzle (not where it currently is)
    const solvedRow = Math.floor((value - 1) / gameState.puzzleSize);
    const solvedCol = (value - 1) % gameState.puzzleSize;
    
    // Set background to show the portion from the solved position
    tile.style.backgroundPosition = 
        `${-solvedCol * gameState.tileSize}px ${-solvedRow * gameState.tileSize}px`;
    
    // Set background size
    tile.style.backgroundSize = 
        `${gameState.puzzleSize * gameState.tileSize}px ${gameState.puzzleSize * gameState.tileSize}px`;
    
    // Store position
    tile.dataset.row = row;
    tile.dataset.col = col;
    
    // Event handlers
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

// Check if a tile can be moved
function isMovable(tile) {
    const row = parseInt(tile.dataset.row);
    const col = parseInt(tile.dataset.col);
    
    return (Math.abs(row - gameState.emptyPos.y) === 1 && col === gameState.emptyPos.x) ||
           (Math.abs(col - gameState.emptyPos.x) === 1 && row === gameState.emptyPos.y);
}

// Move a tile to the empty space
function moveTile(tile) {
    if (!isMovable(tile)) return;
    
    const row = parseInt(tile.dataset.row);
    const col = parseInt(tile.dataset.col);
    const value = parseInt(tile.textContent);
    
    // Update puzzle state
    gameState.puzzleState[gameState.emptyPos.y][gameState.emptyPos.x] = value;
    gameState.puzzleState[row][col] = 0;
    
    // Animate if enabled
    if (document.getElementById('animationsEnabled').checked) {
        tile.style.transition = 'all 0.2s ease';
    } else {
        tile.style.transition = 'none';
    }
    
    // Move tile visually
    tile.style.left = (gameState.emptyPos.x * gameState.tileSize) + 'px';
    tile.style.top = (gameState.emptyPos.y * gameState.tileSize) + 'px';
    tile.dataset.row = gameState.emptyPos.y;
    tile.dataset.col = gameState.emptyPos.x;
    
    // Update empty position
    gameState.emptyPos = {x: col, y: row};
    
    // Update moves
    gameState.moves++;
    updateDisplay();
    
    // Play move sound if enabled
    if (document.getElementById('soundEnabled').checked) {
        playMoveSound();
    }
    
    // Check for win
    if (checkWin()) {
        handleWin();
    }
}

// Shuffle the current puzzle
function shufflePuzzle() {
    if (!gameState.puzzleState) return;
    
    gameState.puzzleState = shufflePuzzleState(gameState.puzzleState);
    gameState.moves = 0;
    gameState.startTime = new Date();
    gameState.isPaused = false;
    
    initializePuzzle();
    updateDisplay();
    
    if (gameState.timerInterval) {
        clearInterval(gameState.timerInterval);
    }
    startTimer();
}

// Shuffle puzzle state (maintaining solvability)
function shufflePuzzleState(puzzleState) {
    const rows = puzzleState.length;
    const cols = puzzleState[0].length;
    
    // Find empty space
    let emptyRow = 0, emptyCol = 0;
    for (let i = 0; i < rows; i++) {
        for (let j = 0; j < cols; j++) {
            if (puzzleState[i][j] === 0) {
                emptyRow = i;
                emptyCol = j;
                break;
            }
        }
    }
    
    // Make 100-300 random valid moves
    const moves = Math.floor(Math.random() * 200) + 100;
    const directions = [[0, 1], [0, -1], [1, 0], [-1, 0]];
    
    for (let m = 0; m < moves; m++) {
        const validMoves = [];
        
        for (const dir of directions) {
            const newRow = emptyRow + dir[0];
            const newCol = emptyCol + dir[1];
            
            if (newRow >= 0 && newRow < rows && newCol >= 0 && newCol < cols) {
                validMoves.push([newRow, newCol]);
            }
        }
        
        if (validMoves.length > 0) {
            const move = validMoves[Math.floor(Math.random() * validMoves.length)];
            
            // Swap tiles
            puzzleState[emptyRow][emptyCol] = puzzleState[move[0]][move[1]];
            puzzleState[move[0]][move[1]] = 0;
            
            emptyRow = move[0];
            emptyCol = move[1];
        }
    }
    
    return puzzleState;
}

// Check if puzzle is solved
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

// Handle win condition
function handleWin() {
    clearInterval(gameState.timerInterval);
    
    const timeTaken = Math.floor((new Date() - gameState.startTime) / 1000);
    
    // Update statistics
    updateStats(true, timeTaken, gameState.moves);
    
    // Show win message
    setTimeout(() => {
        alert(`Congratulations! You solved the puzzle in ${formatTime(timeTaken)} with ${gameState.moves} moves!`);
        
        // Check for achievements
        checkAchievements(timeTaken, gameState.moves);
        
        // Start new game after a short delay
        setTimeout(() => {
            if (confirm('Would you like to start a new game?')) {
                startNewGame();
            }
        }, 1000);
    }, 100);
}

// Check and show achievements
function checkAchievements(timeTaken, moves) {
    const stats = JSON.parse(localStorage.getItem(STORAGE_KEYS.STATS) || '{}');
    
    // First win achievement
    if (stats.gamesWon === 1) {
        showAchievement('First Victory!', 'You completed your first puzzle!');
    }
    
    // Speed demon (under 60 seconds on 4x4)
    if (gameState.puzzleSize === 4 && timeTaken < 60) {
        showAchievement('Speed Demon!', 'Completed a 4x4 puzzle in under 60 seconds!');
    }
    
    // Marathon (100 games)
    if (stats.totalGames === 100) {
        showAchievement('Marathon Runner!', 'You\'ve played 100 games!');
    }
}

// Show achievement popup
function showAchievement(name, description) {
    document.getElementById('achievementName').textContent = name;
    document.getElementById('achievementDesc').textContent = description;
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('achievementPopup').style.display = 'block';
}

// Close achievement popup
function closeAchievement() {
    document.getElementById('overlay').style.display = 'none';
    document.getElementById('achievementPopup').style.display = 'none';
}

// Toggle pause state
function togglePause() {
    gameState.isPaused = !gameState.isPaused;
    document.getElementById('pauseBtn').textContent = gameState.isPaused ? 'Resume' : 'Pause';
    
    if (gameState.isPaused) {
        clearInterval(gameState.timerInterval);
    } else {
        startTimer();
    }
}

// Start the timer
function startTimer() {
    gameState.timerInterval = setInterval(updateTimer, 1000);
}

// Update the timer display
function updateTimer() {
    const elapsed = Math.floor((new Date() - gameState.startTime) / 1000);
    document.getElementById('timer').textContent = formatTime(elapsed);
}

// Format time as MM:SS
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// Update move counter and other displays
function updateDisplay() {
    document.getElementById('moveCount').textContent = gameState.moves;
}

// Update background image
function updateBackground() {
    const select = document.getElementById('backgroundSelect');
    const imageUrl = select.value;
    gameState.backgroundImage = imageUrl;
    
    const tiles = document.querySelectorAll('.puzzlepiece');
    tiles.forEach(tile => {
        tile.style.backgroundImage = `url('${imageUrl}')`;
    });
    
    savePreferences();
}

// Play move sound (placeholder)
function playMoveSound() {
    // Create a simple beep sound
    if (typeof AudioContext !== 'undefined') {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    }
}

// Utility functions for modal dialogs
function showLeaderboard() {
    const stats = JSON.parse(localStorage.getItem(STORAGE_KEYS.STATS) || '{}');
    let message = 'Local Leaderboard:\n\n';
    message += `Total Games: ${stats.totalGames || 0}\n`;
    message += `Games Won: ${stats.gamesWon || 0}\n`;
    message += `Win Rate: ${stats.totalGames ? Math.round((stats.gamesWon || 0) / stats.totalGames * 100) : 0}%\n`;
    message += `Best Time: ${stats.bestTime ? formatTime(stats.bestTime) : 'N/A'}\n`;
    message += `Best Moves: ${stats.bestMoves || 'N/A'}\n`;
    
    alert(message);
}

function showProfile() {
    const stats = JSON.parse(localStorage.getItem(STORAGE_KEYS.STATS) || '{}');
    const prefs = JSON.parse(localStorage.getItem(STORAGE_KEYS.PREFERENCES) || '{}');
    
    let message = 'Player Profile:\n\n';
    message += `Preferred Size: ${prefs.puzzleSize || '4x4'}\n`;
    message += `Background: ${prefs.background || 'background.jpg'}\n`;
    message += `Sound: ${prefs.soundEnabled !== false ? 'Enabled' : 'Disabled'}\n`;
    message += `Animations: ${prefs.animationsEnabled !== false ? 'Enabled' : 'Disabled'}\n\n`;
    message += `Statistics:\n`;
    message += `Total Games: ${stats.totalGames || 0}\n`;
    message += `Games Won: ${stats.gamesWon || 0}\n`;
    message += `Average Time: ${stats.avgTime ? formatTime(Math.round(stats.avgTime)) : 'N/A'}\n`;
    message += `Average Moves: ${stats.avgMoves ? Math.round(stats.avgMoves) : 'N/A'}\n`;
    
    alert(message);
}

function showInstructions() {
    let message = 'How to Play Fifteen Puzzle:\n\n';
    message += '1. Click on tiles adjacent to the empty space to move them\n';
    message += '2. Arrange all numbered tiles in order (1-15) with the empty space in the bottom-right\n';
    message += '3. Use the Shuffle button to scramble the puzzle\n';
    message += '4. Try to solve it in the fewest moves and fastest time!\n\n';
    message += 'Features:\n';
    message += '• Multiple puzzle sizes (3x3 to 6x6)\n';
    message += '• Different background images\n';
    message += '• Sound effects and animations\n';
    message += '• Local statistics tracking\n';
    message += '• Achievement system\n\n';
    message += 'Hover over tiles next to the empty space to see them highlighted!';
    
    alert(message);
}

// Reset all data (for testing/demo purposes)
function resetAllData() {
    if (confirm('This will reset all your statistics and preferences. Are you sure?')) {
        localStorage.removeItem(STORAGE_KEYS.STATS);
        localStorage.removeItem(STORAGE_KEYS.PREFERENCES);
        localStorage.removeItem(STORAGE_KEYS.CURRENT_GAME);
        location.reload();
    }
}

// Auto-save current game state
function saveCurrentGame() {
    const gameData = {
        puzzleState: gameState.puzzleState,
        moves: gameState.moves,
        startTime: gameState.startTime ? gameState.startTime.getTime() : null,
        puzzleSize: gameState.puzzleSize,
        backgroundImage: gameState.backgroundImage
    };
    
    localStorage.setItem(STORAGE_KEYS.CURRENT_GAME, JSON.stringify(gameData));
}

// Load saved game state
function loadCurrentGame() {
    const savedGame = localStorage.getItem(STORAGE_KEYS.CURRENT_GAME);
    
    if (savedGame) {
        const gameData = JSON.parse(savedGame);
        
        if (confirm('Resume your previous game?')) {
            gameState.puzzleState = gameData.puzzleState;
            gameState.moves = gameData.moves;
            gameState.startTime = gameData.startTime ? new Date(gameData.startTime) : new Date();
            gameState.puzzleSize = gameData.puzzleSize;
            gameState.backgroundImage = gameData.backgroundImage;
            
            // Update UI
            document.getElementById('puzzleSize').value = gameState.puzzleSize + 'x' + gameState.puzzleSize;
            document.getElementById('backgroundSelect').value = gameState.backgroundImage;
            
            initializePuzzle();
            updateDisplay();
            startTimer();
            
            return true;
        }
    }
    
    return false;
}

// Enhanced initialization with game loading
document.addEventListener('DOMContentLoaded', function() {
    loadPreferences();
    loadStats();
    setupEventListeners();
    
    // Try to load saved game, otherwise start new
    if (!loadCurrentGame()) {
        startNewGame();
    }
    
    // Auto-save game state periodically
    setInterval(saveCurrentGame, 10000); // Save every 10 seconds
});

// Save game when page is about to close
window.addEventListener('beforeunload', function() {
    saveCurrentGame();
});

// Console commands for development/testing
window.fifteenPuzzle = {
    reset: resetAllData,
    solve: function() {
        gameState.puzzleState = generateSolvedPuzzle(gameState.puzzleSize, gameState.puzzleSize);
        initializePuzzle();
        updateDisplay();
    },
    stats: function() {
        return JSON.parse(localStorage.getItem(STORAGE_KEYS.STATS) || '{}');
    },
    cheat: function() {
        // Move to one move away from solution
        const solved = generateSolvedPuzzle(gameState.puzzleSize, gameState.puzzleSize);
        // Swap last two tiles to make it solvable in one move
        if (gameState.puzzleSize >= 3) {
            const temp = solved[gameState.puzzleSize-1][gameState.puzzleSize-3];
            solved[gameState.puzzleSize-1][gameState.puzzleSize-3] = solved[gameState.puzzleSize-1][gameState.puzzleSize-2];
            solved[gameState.puzzleSize-1][gameState.puzzleSize-2] = temp;
        }
        gameState.puzzleState = solved;
        initializePuzzle();
        updateDisplay();
        console.log('Puzzle is now one move away from solution!');
    }
};
