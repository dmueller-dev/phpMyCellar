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

  // Enforce Role-Based Access Control (RBAC) for the backend
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
  if (strpos($scriptName, '/backend/') !== false) {
    if (!isset($_SESSION['user_id'])) {
      // Not logged in, redirect to login
      header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
      exit();
    }

    $role = $_SESSION['role'] ?? 'read';

    if ($role === 'read') {
      // Read-only users have no backend access
      header("Location: /index.php");
      exit();
    }

    if ($role === 'write') {
      // Write users only have access to specific scripts
      $allowedScripts = ['addTastingNote.php', 'editTastingNote.php', 'blindTasting.php', 'addBlogpost.php', 'editBlogpost.php'];
      $currentScript = basename($scriptName);
      if (!in_array($currentScript, $allowedScripts)) {
        header("Location: /index.php");
        exit();
      }
    }
  }

  // Load backend and frontend helper functions
  require_once __DIR__ . '/functions.php';
