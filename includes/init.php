<?php
  if (!defined('INCLUDED_VIA_APP')) {
    die('Direct access not permitted');
  }

  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  $envPath = __DIR__ . '/../.env';
  $lockPath = __DIR__ . '/../install/installed.lock';
  $installWizard = __DIR__ . '/../install/index.php';

  if (!file_exists($envPath) && !file_exists($lockPath) && file_exists($installWizard)) {
    header("Location: /install/index.php");
    exit();
  }

  $env = @parse_ini_file($envPath);
  if ($env) {
    $_ENV = array_merge($_ENV, $env);
  }

  $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
  $dbName = $_ENV['DB_NAME'] ?? '';
  $dbUser = $_ENV['DB_USER'] ?? '';
  $dbPass = $_ENV['DB_PASS'] ?? '';

  mysqli_report(MYSQLI_REPORT_OFF);
  $mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);

  if ($mysqli->connect_error) {
    if (!file_exists($lockPath) && file_exists($installWizard)) {
      header("Location: /install/index.php");
      exit();
    }
    die("Database connection failed: " . $mysqli->connect_error);
  }

  $mysqli->set_charset("utf8mb4");

  // Alias for backend compatibility
  $conn = $mysqli;

  // Load backend and frontend helper functions
  require_once __DIR__ . '/functions.php';

  // Enforce no-index header early for private and backend pages
  if (function_exists('isPrivatePage') && isPrivatePage() && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
  }

  // Enforce Dynamic Privilege Access Control for the backend
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
  if (strpos($scriptName, '/backend/') !== false) {
    if (!isset($_SESSION['user_id'])) {
      // Not logged in, redirect to login
      header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
      exit();
    }

    $currentScript = basename($scriptName);
    if ($currentScript === 'index.php') {
      if (!canAccessBackend($mysqli, $_SESSION['user_id'])) {
        header("Location: /index.php");
        exit();
      }
    } else {
      $scriptMap = getPrivilegeScriptMap();
      if (isset($scriptMap[$currentScript])) {
        $requiredPriv = $scriptMap[$currentScript];
        if (!hasPrivilege($mysqli, $requiredPriv, $_SESSION['user_id'])) {
          header("Location: /backend/index.php");
          exit();
        }
      } else {
        if (!canAccessBackend($mysqli, $_SESSION['user_id'])) {
          header("Location: /index.php");
          exit();
        }
      }
    }
  }
