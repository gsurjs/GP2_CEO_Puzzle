<?php
//user registration page
require_once 'config/database.php';
require_once 'classes/User.php';

//redirect edge case if already logged in
if (isLoggedIn()) {
    header('Location: game.php');
    exit();
}

$db = getDBConnection();
$userObj = new User($db);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = 'All fields are required.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $result = $userObj->register($username, $password, $email);
            
            if ($result['success']) {
                $success = 'Registration successful! You can now login.';
                // Clear form data
                $username = $email = '';
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
    <title>1️⃣5️⃣ Puzzle - Register</title>
    <link rel="stylesheet" href="register-styling.css">
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="puzzle-icon">
                <?php for ($i = 0; $i < 16; $i++): ?>
                    <div class="puzzle-piece"></div>
                <?php endfor; ?>
            </div>
            <h1>Fifteen Puzzle</h1>
            <p>Create your account</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <div class="password-requirements">
                    Password requirements:
                    <ul>
                        <li>At least 6 characters long</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
            <a href="login.php" class="btn btn-secondary" style="display: block; text-align: center; text-decoration: none;">Back to Login</a>
        </form>

        <div class="links">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>