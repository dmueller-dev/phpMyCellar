<?php
  $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
  header("Location: /wines.php" . $queryString, true, 301);
  exit;
