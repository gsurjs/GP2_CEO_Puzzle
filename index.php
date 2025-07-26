<?php
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>1️⃣5️⃣ Puzzle - Home</title>
</head>
<body>
    <div class="container">
        <h1>Fifteen Puzzle</h1>
        <p>Challenge yourself with solving the CEO's puzzling situation! Arrange the numbered tiles in order by sliding them into the empty space. Track your progress, compete on leaderboards, and unlock achievements!</p>
        
        <div class="buttons">
            <?php if (isLoggedIn()): ?>
                <a href="game.php" class="btn btn-primary">Play Game</a>
                <a href="profile.php" class="btn btn-secondary">My Profile</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Login</a>
                <a href="register.php" class="btn btn-secondary">Register</a>
            <?php endif; ?>
            <a href="leaderboard.php" class="btn btn-secondary">Leaderboard</a>
        </div>
        
        <div class="features">
            <h2>Features</h2>
            <ul>
                <li>Multiple difficulty levels (3x3 to 6x6)</li>
                <li>Various background themes</li>
                <li>Achievement system</li>
                <li>Global leaderboards</li>
                <li>Personal statistics tracking</li>
                <li>Save and resume games</li>
            </ul>
        </div>
    </div>
</body>
</html>