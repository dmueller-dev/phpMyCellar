<?php
  if (!defined('INCLUDED_VIA_APP')) {
    die('Direct access not permitted');
  }

  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  $env = @parse_ini_file(__DIR__.'/../.env');
  if ($env) {
    $_ENV = array_merge($_ENV, $env);
  }

  $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
  $dbName = $_ENV['DB_NAME'] ?? '';
  $dbUser = $_ENV['DB_USER'] ?? '';
  $dbPass = $_ENV['DB_PASS'] ?? '';

  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

  if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
  }

  $mysqli->set_charset("utf8mb4");

  // Alias for backend compatibility
  $conn = $mysqli;

  // Load backend and frontend helper functions
  require_once __DIR__ . '/functions.php';
?>
