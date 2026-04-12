<?php
  // Prevent direct access to this file
  if (!defined('INCLUDED_VIA_APP')) {
    die('Direct access not permitted');
  }
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title><?php echo isset($page_title) ? $page_title : 'Dominik Mueller - Wine is my hobby'; ?></title>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <meta name="description" content="<?php echo isset($meta_desc) ? $meta_desc : 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.'; ?>">
  <meta name="keywords" content="Dominik Mueller,wine database,wine tastings,tasting notes,fine wine,wine collection,wine cellar">

  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="/styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">

  <?php if (isset($extra_head)) echo $extra_head; ?>
</head>

<body>

<header>
  <a href="https://dmueller.com" title="Dominik Mueller - Fine wine tasting notes">
    <img src="/img/logo_web.webp" class="logo" alt="Dominik Mueller - Fine wine tasting notes">
  </a>
</header>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <?php 
        $currentPage = basename($_SERVER['SCRIPT_NAME']); 
        $currentPath = $_SERVER['SCRIPT_NAME'];
      ?>
      <li><a class="<?php echo ($currentPath == '/index.php' || $currentPath == '/') ? 'active' : ''; ?>" href="/index.php" title="Back to homepage">Home</a></li>
      <li><a class="<?php echo ($currentPage == 'wines.php' || $currentPage == 'wine.php') ? 'active' : ''; ?>" href="/wines.php" title="Wine database">Wine database</a></li>
      <li><a class="<?php echo ($currentPage == 'tnotes.php' || $currentPage == 'tnote.php') ? 'active' : ''; ?>" href="/tnotes.php" title="Fine wine tasting notes">Tasting notes</a></li>
      <li><a class="<?php echo ($currentPage == 'blog.php' || $currentPage == 'blogpost.php') ? 'active' : ''; ?>" href="/blog.php" title="My wine blog">Stories</a></li>
      <?php
        if (!isset($_SESSION['user_id'])) {
          echo "<li class='right'><a class='".(($currentPage == 'login.php') ? 'active ' : '')."' href='/login.php' title='Login'>Login</a></li>";
        } elseif (isset($_SESSION['user_id'])) {
          echo "<li class='dropdown right'>" .
            "<a href='#' style='cursor:default;'>My account</a>" .
            "<input type='checkbox' id='drop-account' class='drop-check'>" .
            "<label for='drop-account' class='drop-icon'>&#9660;</label>" .
            "<ul class='submenu'>" .
            "<li><a class='" . (($currentPage == 'accountSettings.php') ? 'active ' : '') . "' href='/accountSettings.php' title='Account settings'>Settings</a></li>" .
            "<li><a href='/logout.php' title='Logout'>Logout</a></li>" .
            "</ul></li>";

          $role = $_SESSION['role'] ?? 'read';

          if ($role === 'admin') {
            echo "<li class='dropdown right'>" .
              "<a href='#' style='cursor:default;'>Admin</a>" .
              "<input type='checkbox' id='drop-admin' class='drop-check'>" .
              "<label for='drop-admin' class='drop-icon'>&#9660;</label>" .
              "<ul class='submenu'>" .
              "<li><a class='" . (($currentPath == '/backend/index.php') ? 'active' : '') . "' href='/backend/index.php' title='Dashboard'>Dashboard</a></li>" .
              "<li><a class='" . (($currentPath == '/backend/addBottle.php') ? 'active' : '') . "' href='/backend/addBottle.php' title='Add bottle'>Add bottle</a></li>" .
              "<li><a class='" . (($currentPath == '/backend/addWine.php') ? 'active' : '') . "' href='/backend/addWine.php' title='Add wine'>Add wine</a></li>" .
              "<li><a class='" . (($currentPath == '/backend/addUser.php') ? 'active' : '') . "' href='/backend/addUser.php' title='User management'>Add user</a></li>" .
              "</ul></li>";
          }

          if ($role === 'write' || $role === 'admin') {
            echo "<li class='dropdown right'>" .
              "<a href='#' style='cursor:default;'>Contribute</a>" .
              "<input type='checkbox' id='drop-contribute' class='drop-check'>" .
              "<label for='drop-contribute' class='drop-icon'>&#9660;</label>" .
              "<ul class='submenu'>" .
              "<li><a class='" . (($currentPath == '/backend/addTastingNote.php') ? 'active' : '') . "' href='/backend/addTastingNote.php' title='New tasting note'>Tasting note</a></li>" .
              "</ul></li>";
          }
          
          echo "<li class='dropdown right'>" .
            "<a href='#' style='cursor:default;'>For friends</a>" .
            "<input type='checkbox' id='drop-friends' class='drop-check'>" .
            "<label for='drop-friends' class='drop-icon'>&#9660;</label>" .
            "<ul class='submenu'>" .
            "<li><a class='" . (($currentPage == 'winemenu.php') ? 'active ' : '') . "' href='/winemenu.php' title='Carte des vins'>Carte des vins</a></li>" .
            "</ul></li>";
        }
      ?>
    </ul>
  </nav>
</header>
