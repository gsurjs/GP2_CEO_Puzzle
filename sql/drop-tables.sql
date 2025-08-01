-- Drop tables in reverse order of dependencies to avoid foreign key constraint errors

-- Drop views first
DROP VIEW IF EXISTS leaderboard;

-- Drop junction/dependent tables
DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS game_sessions;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS game_stats;

-- Drop main tables
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS background_images;
DROP TABLE IF EXISTS users;