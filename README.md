# Fifteen Puzzle

This is a classic Fifteen Puzzle game built with PHP and MySQL. It provides a complete user and game management system, including user registration, login, game progress tracking, leaderboards, and an admin panel.

## Features

  * **Classic Gameplay**: Solve the 15-puzzle by arranging tiles in order.
  * **Multiple Difficulty Levels**: Choose from various puzzle sizes, including 3x3 (Easy), 4x4 (Normal), 5x5 (Hard), and 6x6 (Expert).
  * **User Authentication**: A complete user registration and login system.
  * **Personalized Profiles**: Each user has a profile to track their game statistics, recent games, and achievements.
  * **Game Preferences**: Users can set their default puzzle size, preferred background, and other settings.
  * **Global Leaderboards**: Compete with other players and see top scores on the leaderboard, filterable by puzzle size.
  * **Achievement System**: Unlock achievements for accomplishing certain milestones.
  * **Admin Panel**: An administrative dashboard to manage users, background images, and perform system maintenance.
  * **Customizable Backgrounds**: Choose from various background images for the puzzle.

## Project Structure

```
/
|-- classes/
|   |-- Game.php             # Handles all game-related logic
|   +-- User.php             # Handles all user-related logic
|-- config/                  # Configuration files (database connection)
|-- images/
|   +-- backgrounds/         # Puzzle background images
|-- sql/
|   |-- database-schema.sql  # The full database schema
|   +-- drop-tables.sql      # Script to drop all database tables
|-- .gitignore               # Files and directories to be ignored by git
|-- admin.php                # Admin management panel
|-- game.php                 # The main game interface
|-- index.php                # The application's home page
|-- leaderboard.php          # Displays the game leaderboards
|-- login.php                # User login page
|-- logout.php               # Ends the user session
|-- profile.php              # User profile and statistics page
|-- register.php             # User registration page
|-- *.css                    # Stylesheets for the application
```

## Database

The application uses a MySQL database to store all its data. The schema is defined in `sql/database-schema.sql` and includes the following key tables:

  * `users`: Stores user information, including credentials and roles (player or admin).
  * `game_stats`: Logs every completed game, including time taken, moves, and win status.
  * `game_sessions`: Tracks active (unfinished) games.
  * `background_images`: Stores information about the available background images for the puzzle.
  * `user_preferences`: Saves individual user preferences, like default puzzle size and theme.
  * `achievements`: Defines the available achievements in the game.
  * `user_achievements`: A junction table that links users to the achievements they have earned.

Additionally, there is a `leaderboard` view for efficiently querying top scores.