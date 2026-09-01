<?php
  // Prevent direct access to this file
  if (!defined('INCLUDED_VIA_APP')) {
    die('Direct access not permitted');
  }

  global $conn, $mysqli;
?>

<?php
  // Check if page is private/backend to enforce strict no-index headers and meta tags
  $is_private_page = function_exists('isPrivatePage') ? isPrivatePage() : (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/backend/') !== false);
  if ($is_private_page && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
  }

  $site_title = function_exists('getSiteTitle') ? getSiteTitle() : 'phpMyCellar';
  $site_tagline = function_exists('getSiteTagline') ? getSiteTagline() : 'Fine Wine Cellar & Tasting Notes';
  $owner_name = function_exists('getOwnerName') ? getOwnerName() : 'Cellar Master';
  $default_desc = function_exists('getSiteSetting') ? getSiteSetting('meta_description', 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.') : 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.';
  $logo_path = function_exists('getSiteSetting') ? getSiteSetting('logo_url', '/uploads/img/logo_web.webp') : '/uploads/img/logo_web.webp';

  $resolved_title = isset($page_title) ? $page_title : ($site_title . ' - ' . $site_tagline);
  $resolved_desc = strip_tags(isset($meta_desc) ? $meta_desc : $default_desc);
  
  if (isset($meta_keywords)) {
    $resolved_keywords = is_array($meta_keywords) ? implode(', ', $meta_keywords) : $meta_keywords;
  } else {
    $resolved_keywords = $owner_name . ', ' . $site_title . ', wine database, wine tastings, tasting notes, fine wine, wine collection, wine cellar';
  }

  if ($is_private_page) {
    $resolved_robots = 'noindex, nofollow, noarchive, nosnippet, noimageindex';
    $resolved_canonical = null;
  } else {
    $resolved_robots = isset($meta_robots) ? $meta_robots : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    if (isset($canonical_url)) {
      $resolved_canonical = function_exists('getAbsoluteUrl') ? getAbsoluteUrl($canonical_url) : $canonical_url;
    } else {
      $script_name = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
      $resolved_canonical = function_exists('getAbsoluteUrl') ? getAbsoluteUrl($script_name === '/index.php' ? '/' : $script_name) : '/';
    }
  }

  $og_site_name = $site_title;
  $og_type_val = isset($og_type) ? $og_type : 'website';
  $og_image_val = isset($og_image) && !empty($og_image) ? (function_exists('getAbsoluteUrl') ? getAbsoluteUrl($og_image) : $og_image) : (function_exists('getAbsoluteUrl') ? getAbsoluteUrl($logo_path) : $logo_path);
  $og_image_alt_val = isset($og_image_alt) ? $og_image_alt : $resolved_title;
  $twitter_card_val = isset($twitter_card) ? $twitter_card : (!empty($og_image) ? 'summary_large_image' : 'summary');
?>
<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title><?php echo htmlspecialchars($resolved_title, ENT_QUOTES, 'UTF-8'); ?></title>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="<?php echo htmlspecialchars($owner_name, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="description" content="<?php echo htmlspecialchars($resolved_desc, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="keywords" content="<?php echo htmlspecialchars($resolved_keywords, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="robots" content="<?php echo htmlspecialchars($resolved_robots, ENT_QUOTES, 'UTF-8'); ?>">

  <?php if (!$is_private_page && !empty($resolved_canonical)): ?>
  <link rel="canonical" href="<?php echo htmlspecialchars($resolved_canonical, ENT_QUOTES, 'UTF-8'); ?>">

  <!-- Open Graph / Facebook / LinkedIn / AI Summaries -->
  <meta property="og:locale" content="en_GB">
  <meta property="og:site_name" content="<?php echo htmlspecialchars($og_site_name, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:type" content="<?php echo htmlspecialchars($og_type_val, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($resolved_title, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($resolved_desc, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:url" content="<?php echo htmlspecialchars($resolved_canonical, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:image" content="<?php echo htmlspecialchars($og_image_val, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:image:alt" content="<?php echo htmlspecialchars($og_image_alt_val, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if (!empty($article_meta) && is_array($article_meta)): ?>
  <?php if (!empty($article_meta['published_time'])): ?>
  <meta property="article:published_time" content="<?php echo htmlspecialchars(date('c', strtotime($article_meta['published_time'])), ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <?php if (!empty($article_meta['modified_time'])): ?>
  <meta property="article:modified_time" content="<?php echo htmlspecialchars(date('c', strtotime($article_meta['modified_time'])), ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <?php if (!empty($article_meta['author'])): ?>
  <meta property="article:author" content="<?php echo htmlspecialchars($article_meta['author'], ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <?php endif; ?>

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="<?php echo htmlspecialchars($twitter_card_val, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($resolved_title, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($resolved_desc, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image_val, ENT_QUOTES, 'UTF-8'); ?>">

  <!-- Schema.org JSON-LD Structured Data -->
  <?php if (!empty($json_ld)): ?>
  <script type="application/ld+json">
<?php echo json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
  </script>
  <?php endif; ?>
  <?php endif; ?>

  <link rel="stylesheet" href="/includes/styles.css">
  <?php
    $theme_accent    = function_exists('getSiteSetting') ? getSiteSetting('theme_accent_color', '#CD5C5C') : '#CD5C5C';
    $theme_secondary = function_exists('getSiteSetting') ? getSiteSetting('theme_accent_secondary', '#B22222') : '#B22222';
    $theme_hover     = function_exists('getSiteSetting') ? getSiteSetting('theme_accent_hover', '#8B0000') : '#8B0000';
    if (!empty($theme_accent) || !empty($theme_secondary) || !empty($theme_hover)):
  ?>
  <style>
    :root {
      <?php if (!empty($theme_accent)): ?>--primary-accent: <?php echo htmlspecialchars($theme_accent, ENT_QUOTES, 'UTF-8'); ?>;<?php endif; ?>
      <?php if (!empty($theme_secondary)): ?>--secondary-accent: <?php echo htmlspecialchars($theme_secondary, ENT_QUOTES, 'UTF-8'); ?>;<?php endif; ?>
      <?php if (!empty($theme_hover)): ?>--primary-accent-hover: <?php echo htmlspecialchars($theme_hover, ENT_QUOTES, 'UTF-8'); ?>;<?php endif; ?>
    }
  </style>
  <?php endif; ?>
  <link rel="icon" href="/uploads/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/uploads/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/uploads/img/cropped-wineglassicon-180x180.webp">

  <?php
    // Dynamically load the WYSIWYG integration script for logged-in backend pages
    if (isset($_SESSION['user_id']) && strpos($_SERVER['SCRIPT_NAME'], '/backend/') !== false) {
      echo '<script src="/includes/wysiwyg.js" defer></script>' . "\n";
    }
  ?>

  <?php if (isset($extra_head)) echo $extra_head; ?>
</head>

<body>

<header>
  <a href="/" title="<?php echo htmlspecialchars($site_title . ' - ' . $site_tagline, ENT_QUOTES, 'UTF-8'); ?>">
    <img src="<?php echo htmlspecialchars($logo_path, ENT_QUOTES, 'UTF-8'); ?>" class="logo" alt="<?php echo htmlspecialchars($site_title . ' - ' . $site_tagline, ENT_QUOTES, 'UTF-8'); ?>">
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
      <li><a class="<?php echo ($currentPage == 'wines.php') ? 'active' : ''; ?>" href="/wines.php" title="Wine database">Wine database</a></li>
      <li class="dropdown">
        <label for="drop-tnotes" class="menu-label <?php echo ($currentPage == 'tnotes.php' || $currentPage == 'vintages.php') ? 'active' : ''; ?>">Tasting notes</label>
        <input type="checkbox" id="drop-tnotes" class="drop-check">
        <label for="drop-tnotes" class="drop-icon">&#9660;</label>
        <ul class="submenu">
          <li><a class="<?php echo ($currentPage == 'tnotes.php') ? 'active' : ''; ?>" href="/tnotes.php" title="Browse all tasting notes">Browse tasting notes</a></li>
          <li><a class="<?php echo ($currentPage == 'vintages.php') ? 'active' : ''; ?>" href="/vintages.php" title="Vintage reports">Vintage reports</a></li>
        </ul>
      </li>
      <li><a class="<?php echo ($currentPage == 'blog.php') ? 'active' : ''; ?>" href="/blog.php" title="My wine blog">Stories</a></li>
      <?php
        if (!isset($_SESSION['user_id'])) {
          $redirect_param = '';
          if ($currentPage !== 'login.php' && $currentPage !== 'logout.php') {
            $redirect_param = '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
          }
          echo "<li class='right'><a class='".(($currentPage == 'login.php') ? 'active ' : '')."' href='/login.php" . $redirect_param . "' title='Login'>Login</a></li>";
        } elseif (isset($_SESSION['user_id'])) {
          echo "<li class='dropdown right'>" .
            "<label for='drop-account' class='menu-label" . (($currentPage == 'accountSettings.php') ? ' active' : '') . "'>My account</label>" .
            "<input type='checkbox' id='drop-account' class='drop-check'>" .
            "<label for='drop-account' class='drop-icon'>&#9660;</label>" .
            "<ul class='submenu'>" .
            "<li><a class='" . (($currentPage == 'accountSettings.php') ? 'active ' : '') . "' href='/accountSettings.php' title='Account settings'>Settings</a></li>" .
            "<li><a href='/logout.php' title='Logout'>Logout</a></li>" .
            "</ul></li>";

          // Fetch unread notification count and render the notification bell
          $unread_count = getUnreadNotificationCount($conn, $_SESSION['user_id']);
          $badge_html = ($unread_count > 0) ? " <span class='notification-badge'>$unread_count</span>" : "";
          echo "<li class='right'>" .
            "<a class='notification-bell " . (($currentPage == 'notifications.php') ? 'active ' : '') . "' href='/notifications.php' title='Notifications'>🔔" . $badge_html . "</a>" .
            "</li>";

          $isContributeActive = in_array($currentPage, [
            'addTastingNote.php',
            'blindTasting.php',
            'editTastingNote.php',
            'addBlogpost.php',
            'editBlogpost.php'
          ]);
          $isAdminActive = (strpos($currentPath, '/backend/') !== false && !$isContributeActive);

          $canManagePrivileges = hasPrivilege($conn, 'manage_privileges');
          $canManageUsers = hasPrivilege($conn, 'manage_users');
          $canBrowseBottles = hasPrivilege($conn, 'browse_bottles');
          $canAddBottle = hasPrivilege($conn, 'add_bottle');
          $canBrowseWines = hasPrivilege($conn, 'browse_wines');
          $canAddWine = hasPrivilege($conn, 'add_wine');

          $hasAdminMenuItems = $canManagePrivileges || $canManageUsers || $canBrowseBottles || $canAddBottle || $canBrowseWines || $canAddWine;

          if ($hasAdminMenuItems) {
            echo "<li class='dropdown right'>" .
              "<label for='drop-admin' class='menu-label" . ($isAdminActive ? ' active' : '') . "'>Admin</label>" .
              "<input type='checkbox' id='drop-admin' class='drop-check'>" .
              "<label for='drop-admin' class='drop-icon'>&#9660;</label>" .
              "<ul class='submenu'>" .
              "<li><a class='" . (($currentPath == '/backend/index.php') ? 'active' : '') . "' href='/backend/index.php' title='Dashboard'>Dashboard</a></li>";
            if ($canBrowseBottles) {
              echo "<li><a class='" . (($currentPath == '/backend/browseBottles.php') ? 'active' : '') . "' href='/backend/browseBottles.php' title='Browse all bottles'>Browse bottles</a></li>";
            }
            if ($canAddBottle) {
              echo "<li><a class='" . (($currentPath == '/backend/addBottle.php') ? 'active' : '') . "' href='/backend/addBottle.php' title='Add bottle'>Add bottle</a></li>";
            }
            if ($canBrowseWines) {
              echo "<li><a class='" . (($currentPath == '/backend/browseWines.php') ? 'active' : '') . "' href='/backend/browseWines.php' title='Browse all wines'>Browse wines</a></li>";
            }
            if ($canAddWine) {
              echo "<li><a class='" . (($currentPath == '/backend/addWine.php') ? 'active' : '') . "' href='/backend/addWine.php' title='Add wine'>Add wine</a></li>";
            }
            if ($canManagePrivileges) {
              echo "<li><a class='" . (($currentPath == '/backend/settings.php') ? 'active' : '') . "' href='/backend/settings.php' title='Site settings & branding'>Site settings</a></li>";
              echo "<li><a class='" . (($currentPath == '/backend/manageStaticPages.php' || $currentPath == '/backend/editStaticPage.php') ? 'active' : '') . "' href='/backend/manageStaticPages.php' title='Manage static pages'>Static pages</a></li>";
              echo "<li><a class='" . (($currentPath == '/backend/managePrivileges.php') ? 'active' : '') . "' href='/backend/managePrivileges.php' title='User & role privileges'>User & role privileges</a></li>";
            }
            if ($canManageUsers) {
              echo "<li><a class='" . (($currentPath == '/backend/addUser.php') ? 'active' : '') . "' href='/backend/addUser.php' title='Add user'>Add user</a></li>";
              echo "<li><a class='" . (($currentPath == '/backend/editUser.php') ? 'active' : '') . "' href='/backend/editUser.php' title='Edit user & reset password'>Edit user</a></li>";
            }
            echo "</ul></li>";
          }

          $canAddNote = hasPrivilege($conn, 'add_tasting_note');
          $canEditNote = hasPrivilege($conn, 'edit_tasting_note') || hasPrivilege($conn, 'edit_all_tasting_notes');
          $canAddBlog = hasPrivilege($conn, 'add_blogpost');
          $canEditBlog = hasPrivilege($conn, 'edit_blogpost') || hasPrivilege($conn, 'edit_all_blogposts');

          $hasContributeMenuItems = $canAddNote || $canEditNote || $canAddBlog || $canEditBlog;

          if ($hasContributeMenuItems) {
            echo "<li class='dropdown right'>" .
              "<label for='drop-contribute' class='menu-label" . ($isContributeActive ? ' active' : '') . "'>Contribute</label>" .
              "<input type='checkbox' id='drop-contribute' class='drop-check'>" .
              "<label for='drop-contribute' class='drop-icon'>&#9660;</label>" .
              "<ul class='submenu'>";
            if ($canAddNote) {
              echo "<li><a class='" . (($currentPath == '/backend/addTastingNote.php') ? 'active' : '') . "' href='/backend/addTastingNote.php' title='New tasting note'>Write tasting note</a></li>" .
                   "<li><a class='" . (($currentPath == '/backend/blindTasting.php') ? 'active' : '') . "' href='/backend/blindTasting.php' title='New blind tasting note'>Write <em>blind</em> tasting note</a></li>";
            }
            if ($canEditNote) {
              echo "<li><a class='" . (($currentPath == '/backend/editTastingNote.php') ? 'active' : '') . "' href='/backend/editTastingNote.php' title='Edit tasting notes'>Edit tasting note</a></li>";
            }
            if ($canAddBlog) {
              echo "<li><a class='" . (($currentPath == '/backend/addBlogpost.php') ? 'active' : '') . "' href='/backend/addBlogpost.php' title='Add new story'>Write story</a></li>";
            }
            if ($canEditBlog) {
              echo "<li><a class='" . (($currentPath == '/backend/editBlogpost.php') ? 'active' : '') . "' href='/backend/editBlogpost.php' title='Edit a blogpost'>Edit story</a></li>";
            }
            echo "</ul></li>";
          }

          if (hasPrivilege($conn, 'view_cellar_menu')) {
            echo "<li class='dropdown right'>" .
              "<label for='drop-friends' class='menu-label" . (($currentPage == 'winemenu.php') ? ' active' : '') . "'>For friends</label>" .
              "<input type='checkbox' id='drop-friends' class='drop-check'>" .
              "<label for='drop-friends' class='drop-icon'>&#9660;</label>" .
              "<ul class='submenu'>" .
              "<li><a class='" . (($currentPage == 'winemenu.php') ? 'active ' : '') . "' href='/winemenu.php' title='Carte des vins'>Carte des vins</a></li>" .
              "</ul></li>";
          }
        }
      ?>
    </ul>
  </nav>
</header>
