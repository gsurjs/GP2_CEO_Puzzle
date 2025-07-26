-- 1. users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('player', 'admin') DEFAULT 'player' NOT NULL,
    registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    INDEX idx_username (username),
    INDEX idx_email (email)
);

-- 2. background images table (referenced by other tables)
CREATE TABLE background_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    image_name VARCHAR(100) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    uploaded_by_user_id INT,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
);

-- 3. game stats table
CREATE TABLE game_stats (
    stat_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    puzzle_size VARCHAR(10) NOT NULL,
    time_taken_seconds INT NOT NULL,
    moves_count INT NOT NULL,
    background_image_id INT,
    win_status BOOLEAN NOT NULL,
    game_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (background_image_id) REFERENCES background_images(image_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_game_date (game_date),
    INDEX idx_win_status (win_status)
);

-- 4. user preferences table
CREATE TABLE user_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    default_puzzle_size VARCHAR(10) DEFAULT '4x4',
    preferred_background_image_id INT,
    sound_enabled BOOLEAN DEFAULT TRUE,
    animations_enabled BOOLEAN DEFAULT TRUE,
    theme_preference ENUM('light', 'dark') DEFAULT 'light',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (preferred_background_image_id) REFERENCES background_images(image_id) ON DELETE SET NULL
);

-- 5. leaderboard view (for easy access to top scores)
CREATE VIEW leaderboard AS
SELECT 
    u.username,
    gs.puzzle_size,
    gs.time_taken_seconds,
    gs.moves_count,
    gs.game_date,
    bi.image_name as background_used
FROM game_stats gs
JOIN users u ON gs.user_id = u.user_id
LEFT JOIN background_images bi ON gs.background_image_id = bi.image_id
WHERE gs.win_status = TRUE
ORDER BY gs.puzzle_size, gs.time_taken_seconds ASC, gs.moves_count ASC;

-- 6. user achievements table (for gamification)
CREATE TABLE achievements (
    achievement_id INT PRIMARY KEY AUTO_INCREMENT,
    achievement_name VARCHAR(100) NOT NULL,
    achievement_description TEXT,
    achievement_icon VARCHAR(255),
    points_value INT DEFAULT 0
);

-- 7. user achievements junction table
CREATE TABLE user_achievements (
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(achievement_id) ON DELETE CASCADE
);

-- 8. game sessions table (for tracking active games)
CREATE TABLE game_sessions (
    session_id VARCHAR(64) PRIMARY KEY,
    user_id INT,
    puzzle_state JSON,
    moves_made INT DEFAULT 0,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_update DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    puzzle_size VARCHAR(10) NOT NULL,
    background_image_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (background_image_id) REFERENCES background_images(image_id) ON DELETE SET NULL,
    INDEX idx_user_session (user_id),
    INDEX idx_last_update (last_update)
);

-- insert default background images
INSERT INTO background_images (image_name, image_url) VALUES
('CEO', 'images/backgrounds/coldplayceo-400x400.png');

-- insert sample achievements
INSERT INTO achievements (achievement_name, achievement_description, points_value) VALUES
('First Win', 'Complete your first puzzle', 10),
('Speed Demon', 'Complete a 4x4 puzzle in under 60 seconds', 50),
('Perfectionist', 'Complete a puzzle in the minimum number of moves', 100),
('Marathon', 'Play 100 games', 200),
('Master Puzzler', 'Complete a 6x6 puzzle', 150),
('Daily Player', 'Play every day for a week', 75);

-- insert admin user (password: admin123)
INSERT INTO users (username, password_hash, email, role) VALUES
('admin', '$2y$10$YourHashedPasswordHere', 'admin@fifteenpuzzle.com', 'admin');

-- create indexes for better performance
CREATE INDEX idx_stats_user_date ON game_stats(user_id, game_date);
CREATE INDEX idx_stats_puzzle_size ON game_stats(puzzle_size);
CREATE INDEX idx_sessions_update ON game_sessions(last_update);