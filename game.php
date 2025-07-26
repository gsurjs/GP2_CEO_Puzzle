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
