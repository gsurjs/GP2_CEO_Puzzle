<?php
// Admin creation
require_once 'config/database.php';

try {
    $db = getDBConnection();
    echo "Database connected successfully.<br>";
    
    // Delete existing admin user if exists
    $stmt = $db->prepare("DELETE FROM users WHERE username = 'admin'");
    $stmt->execute();
    echo "Removed any existing admin user.<br>";
    
    // Creates a new admin user with known password
    $username = 'admin';
    $password = 'admin123';
    $email = 'admin@example.com';
    $role = 'admin';
    
    // This will generate password hash
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Inserts the admin user
    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, email, role, registration_date) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([$username, $passwordHash, $email, $role]);
    
    if ($result) {
        echo "✅ Admin user created successfully!<br>";
        echo "Username: <strong>admin</strong><br>";
        echo "Password: <strong>admin123</strong><br>";
        echo "Email: <strong>admin@example.com</strong><br>";
        echo "Role: <strong>admin</strong><br>";
        
        // Also create default preferences for admin
        $adminId = $db->lastInsertId();
        $stmt = $db->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
        $stmt->execute([$adminId]);
        echo "✅ Admin preferences created.<br>";
        
        echo "<br><a href='login.php'>Click here to login as admin</a>";
        
    } else {
        echo "❌ Failed to create admin user.";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>