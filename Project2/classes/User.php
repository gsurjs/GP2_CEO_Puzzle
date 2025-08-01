<?php
//user management class

class User {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    //register new user
    public function register($username, $password, $email) {
        // Validate input
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Username must be between 3 and 50 characters'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        //check for already existing user
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }
        
        //hash password and insert user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, email, registration_date) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $passwordHash, $email]);
            
            $userId = $this->db->lastInsertId();
            
            //create default prefs
            $stmt = $this->db->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    //login user
    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT user_id, username, password_hash, email, role 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            return ['success' => true, 'message' => 'Login successful'];
        }
        
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    //get user prefs
    public function getPreferences($userId) {
        $stmt = $this->db->prepare("
            SELECT up.*, bi.image_url as preferred_background_url
            FROM user_preferences up
            LEFT JOIN background_images bi ON up.preferred_background_image_id = bi.image_id
            WHERE up.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    //update user prefs
    public function updatePreferences($userId, $preferences) {
        $allowedFields = ['default_puzzle_size', 'preferred_background_image_id', 
                         'sound_enabled', 'animations_enabled', 'theme_preference'];
        
        $updates = [];
        $params = [];
        
        foreach ($preferences as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No valid preferences to update'];
        }
        
        $params[] = $userId;
        $sql = "UPDATE user_preferences SET " . implode(', ', $updates) . " WHERE user_id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'message' => 'Preferences updated successfully'];
        } catch (PDOException $e) {
            error_log("Preference update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update preferences'];
        }
    }
    
    //get user stats
    public function getStatistics($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_games,
                SUM(win_status) as games_won,
                AVG(CASE WHEN win_status = 1 THEN time_taken_seconds END) as avg_win_time,
                AVG(CASE WHEN win_status = 1 THEN moves_count END) as avg_moves,
                MIN(CASE WHEN win_status = 1 THEN time_taken_seconds END) as best_time,
                MIN(CASE WHEN win_status = 1 THEN moves_count END) as best_moves
            FROM game_stats
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    //get user achievments
    public function getAchievements($userId) {
        $stmt = $this->db->prepare("
            SELECT a.*, ua.earned_date
            FROM achievements a
            LEFT JOIN user_achievements ua ON a.achievement_id = ua.achievement_id AND ua.user_id = ?
            ORDER BY a.points_value DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    //check and award achievs
    public function checkAchievements($userId) {
        $stats = $this->getStatistics($userId);
        $awarded = [];
        
        //first win
        if ($stats['games_won'] >= 1) {
            $awarded[] = $this->awardAchievement($userId, 1);
        }
        
        //marathon
        if ($stats['total_games'] >= 100) {
            $awarded[] = $this->awardAchievement($userId, 4);
        }
        
        
        return array_filter($awarded);
    }
    
    //award achievs
    private function awardAchievement($userId, $achievementId) {
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO user_achievements (user_id, achievement_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $achievementId]);
            
            if ($stmt->rowCount() > 0) {
                $stmt = $this->db->prepare("SELECT * FROM achievements WHERE achievement_id = ?");
                $stmt->execute([$achievementId]);
                return $stmt->fetch();
            }
        } catch (PDOException $e) {
            error_log("Achievement award error: " . $e->getMessage());
        }
        
        return null;
    }
}