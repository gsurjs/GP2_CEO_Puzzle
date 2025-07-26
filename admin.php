<?php
//administrative panel for managing the game
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/Game.php';

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