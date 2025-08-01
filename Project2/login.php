<?php
//user login page
require_once 'config/database.php';
require_once 'classes/User.php';

//redirect if already logged in
if (isLoggedIn()) {
    header('Location: game.php');
    exit();
}

$db = getDBConnection();
$userObj = new User($db);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $result = $userObj->login($username, $password);
            
            if ($result['success']) {
                header('Location: game.php');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1️⃣5️⃣ Puzzle - Login</title>
    <link rel="stylesheet" href="login-styling.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="puzzle-icon">
                <?php for ($i = 0; $i < 16; $i++): ?>
                    <div class="puzzle-piece"></div>
                <?php endfor; ?>
            </div>
            <h1>Fifteen Puzzle</h1>
            <p>Login to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>
            <a href="register.php" class="btn btn-secondary" style="display: block; text-align: center; text-decoration: none;">Create Account</a>
        </form>

        <div class="links">
            <a href="index.php">Back to Home</a>
        </div>

        <div class="demo-info">
            <h3>Demo Accounts</h3>
            <p><strong>Player:</strong> demo / demo123</p>
            <p><strong>Admin:</strong> admin / admin123</p>
        </div>
    </div>
</body>
</html>