<?php
//game management class

class Game {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Start a new game session
    public function startNewGame($userId, $puzzleSize = '4x4', $backgroundImageId = null) {
        $sessionId = bin2hex(random_bytes(32));
        
        // Get puzzle dimensions
        list($rows, $cols) = explode('x', $puzzleSize);
        $rows = intval($rows);
        $cols = intval($cols);
        
        // Generate initial puzzle state (solved)
        $puzzleState = $this->generateSolvedPuzzle($rows, $cols);
        
        // FIX: Scramble the puzzle immediately so it starts scrambled
        $puzzleState = $this->shufflePuzzle($puzzleState);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO game_sessions (session_id, user_id, puzzle_state, puzzle_size, background_image_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId, 
                $userId, 
                json_encode($puzzleState), 
                $puzzleSize, 
                $backgroundImageId
            ]);
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'puzzle_state' => $puzzleState,
                'puzzle_size' => $puzzleSize
            ];
        } catch (PDOException $e) {
            error_log("Game start error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to start game'];
        }
    }
    
    //generate solved puzzle state
    private function generateSolvedPuzzle($rows, $cols) {
        $puzzle = [];
        $number = 1;
        
        for ($i = 0; $i < $rows; $i++) {
            $puzzle[$i] = [];
            for ($j = 0; $j < $cols; $j++) {
                if ($i == $rows - 1 && $j == $cols - 1) {
                    $puzzle[$i][$j] = 0; // Empty space
                } else {
                    $puzzle[$i][$j] = $number++;
                }
            }
        }
        
        return $puzzle;
    }
    
    //get current game session
    public function getGameSession($sessionId) {
        $stmt = $this->db->prepare("
            SELECT gs.*, bi.image_url as background_url
            FROM game_sessions gs
            LEFT JOIN background_images bi ON gs.background_image_id = bi.image_id
            WHERE gs.session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        
        if ($session) {
            $session['puzzle_state'] = json_decode($session['puzzle_state'], true);
        }
        
        return $session;
    }
    
    //update game state
    public function updateGameState($sessionId, $puzzleState, $incrementMoves = true) {
        try {
            if ($incrementMoves) {
                $stmt = $this->db->prepare("
                    UPDATE game_sessions 
                    SET puzzle_state = ?, moves_made = moves_made + 1, last_update = NOW()
                    WHERE session_id = ?
                ");
            } else {
                $stmt = $this->db->prepare("
                    UPDATE game_sessions 
                    SET puzzle_state = ?, last_update = NOW()
                    WHERE session_id = ?
                ");
            }
            
            $stmt->execute([json_encode($puzzleState), $sessionId]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Game update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update game state'];
        }
    }
    
    //shuffle puzzle (maintaining solvability)
    public function shufflePuzzle($puzzleState) {
        $rows = count($puzzleState);
        $cols = count($puzzleState[0]);
        
        // Find empty space
        $emptyRow = $emptyCol = 0;
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                if ($puzzleState[$i][$j] == 0) {
                    $emptyRow = $i;
                    $emptyCol = $j;
                    break 2;
                }
            }
        }
        
        //make 100-300 random valid moves
        $moves = rand(100, 300);
        $directions = [[0, 1], [0, -1], [1, 0], [-1, 0]];
        
        for ($m = 0; $m < $moves; $m++) {
            $validMoves = [];
            
            foreach ($directions as $dir) {
                $newRow = $emptyRow + $dir[0];
                $newCol = $emptyCol + $dir[1];
                
                if ($newRow >= 0 && $newRow < $rows && $newCol >= 0 && $newCol < $cols) {
                    $validMoves[] = [$newRow, $newCol];
                }
            }
            
            if (!empty($validMoves)) {
                $move = $validMoves[array_rand($validMoves)];
                
                // Swap tiles
                $puzzleState[$emptyRow][$emptyCol] = $puzzleState[$move[0]][$move[1]];
                $puzzleState[$move[0]][$move[1]] = 0;
                
                $emptyRow = $move[0];
                $emptyCol = $move[1];
            }
        }
        
        return $puzzleState;
    }
    
    //check if puzzle is solved
    public function checkWin($puzzleState) {
        $rows = count($puzzleState);
        $cols = count($puzzleState[0]);
        $expected = 1;
        
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                if ($i == $rows - 1 && $j == $cols - 1) {
                    return $puzzleState[$i][$j] == 0;
                } else if ($puzzleState[$i][$j] != $expected++) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    //complete game and save statistics
    public function completeGame($sessionId, $userId, $won = true) {
        $session = $this->getGameSession($sessionId);
        
        if (!$session) {
            return ['success' => false, 'message' => 'Invalid game session'];
        }
        
        $timeTaken = strtotime($session['last_update']) - strtotime($session['start_time']);
        
        try {
            // Save game statistics
            $stmt = $this->db->prepare("
                INSERT INTO game_stats (user_id, puzzle_size, time_taken_seconds, moves_count, 
                                      background_image_id, win_status, game_date)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $session['puzzle_size'],
                $timeTaken,
                $session['moves_made'],
                $session['background_image_id'],
                $won ? 1 : 0
            ]);
            
            //delete game session
            $stmt = $this->db->prepare("DELETE FROM game_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            
            return [
                'success' => true,
                'time_taken' => $timeTaken,
                'moves_made' => $session['moves_made']
            ];
        } catch (PDOException $e) {
            error_log("Game completion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to save game statistics'];
        }
    }
    
    //get leaderboard
    public function getLeaderboard($puzzleSize = null, $limit = 10) {
        $sql = "
            SELECT u.username, gs.puzzle_size, gs.time_taken_seconds, gs.moves_count, 
                   gs.game_date, bi.image_name as background_used
            FROM game_stats gs
            JOIN users u ON gs.user_id = u.user_id
            LEFT JOIN background_images bi ON gs.background_image_id = bi.image_id
            WHERE gs.win_status = 1
        ";
        
        $params = [];
        if ($puzzleSize) {
            $sql .= " AND gs.puzzle_size = ?";
            $params[] = $puzzleSize;
        }
        
        $sql .= " ORDER BY gs.time_taken_seconds ASC, gs.moves_count ASC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // get user's recent games
    public function getUserRecentGames($userId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT gs.*, bi.image_name as background_used
            FROM game_stats gs
            LEFT JOIN background_images bi ON gs.background_image_id = bi.image_id
            WHERE gs.user_id = ?
            ORDER BY gs.game_date DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    //clean up old abandoned sessions
    public function cleanupOldSessions($hoursOld = 24) {
        $stmt = $this->db->prepare("
            DELETE FROM game_sessions 
            WHERE last_update < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$hoursOld]);
        return $stmt->rowCount();
    }
}