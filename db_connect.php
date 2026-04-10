<?php
  // Prevent direct access to this file
  if (!defined('INCLUDED_VIA_APP')) {
    die('Direct access not permitted');
  }

  // Read .env file
  $env = parse_ini_file(__DIR__.'/.env');
  $_ENV = array_merge($_ENV, $env);

  // Load details
  $dbHost = $_ENV['DB_HOST'];
  $dbName = $_ENV['DB_NAME'];
  $dbUser = $_ENV['DB_USER'];
  $dbPass = $_ENV['DB_PASS'];

  // Define connection
  $mysqli = new mysqli($dbHost,$dbUser,$dbPass,$dbName);

  // Check connection
  if ($mysqli -> connect_errno)
  {
    exit();
  }
?>
