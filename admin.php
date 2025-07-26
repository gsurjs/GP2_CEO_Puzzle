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