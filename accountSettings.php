<?php 
  session_start();

  // Include the database configuration file
  require 'db_connect.php';
  $mysqli->query("SET NAMES utf8");

  // Check if user is logged in
  if (!isset($_SESSION['user_id'])) {
    die("<h2>Access Denied</h2><p>You must be <a href='/login.php'>logged in</a> to change your password.</p>");
  } else {
    // --- FETCH USER DETAILS ---
    $user_id = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("SELECT username, displayname, email, password FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $displayname, $email, $hashed_password);
    if (!$stmt->fetch()) {
      $stmt->close();
      die("<h2>User not found.</h2>");
    }
    $stmt->close();
  }

  // Initialize error and success messages
  $error = "";
  $success = "";

  // Process form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
      $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
      $error = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
      $error = "New password must be at least 8 characters long.";
    } elseif (!password_verify($old_password, $hashed_password)) {
      $error = "Current password is incorrect.";
    } else {
      // Update password
      $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
      $update = $mysqli->prepare("UPDATE users SET password = ? WHERE user_id = ?");
      $update->bind_param('si', $new_hashed, $user_id);
      if ($update->execute()) {
        $success = "Password updated successfully!";
      } else {
        $error = "Failed to update password. Please try again.";
      }
      $update->close();
      $mysqli->close();
    }
  }
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Dominik Mueller - Wine is my hobby</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <meta name="description" content="On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.">
  <meta name="keywords" content="Dominik Mueller,wine database,wine tastings,tasting notes,fine wine,wine collection,wine cellar">
  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="https://dmueller.com/styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">
</head>

<body>

<header class="titles">
  <h1 class="site-title">Dominik Mueller</h1>
  <h2 class="sub-title">Wine is my hobby. Fine wine tasting notes and experiences.</h2>
</header>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <li><a href="/index.php" title="Back to homepage">Home</a></li>
      <li><a href="/wines.php" title="Wine database">Wine database</a></li>
      <li><a href="/tnotes.php" title="Fine wine tasting notes">Tasting notes</a></li>
      <li><a href="/blog.php" title="My wine blog">Stories</a></li>
      <?php
        if (!isset($_SESSION['user_id'])) {
          echo "<li class='right'><a href='/login.php' title='Login'>Login</a></li>";
        } elseif (isset($_SESSION['user_id'])) {
          echo "<li><a style='font-style:italic;' href='/winemenu.php' title='Carte des vins'>Carte des vins</a></li>";
          echo "<li class='right'><a href='/logout.php' title='Logout'>Logout</a></li>";
          echo "<li class='right'><a href='/accountSettings.php' title='My account'>My account</a></li>";
        }
      ?>
    </ul>
  </nav>
</header>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3>Change password</h3>
      <?php
        if ($error!="") {
          echo "<div style='color:red;'>$error</div>";
        } elseif ($success!="") {
          echo "<div style='color:green;'>$success</div>";
        }
      ?>
    </div>
    <div class="card">
      <form method="post" autocomplete="off" accept-charset="UTF-8">
        <label for="username">Username:</label>
        <input type="text" id="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <br><br>
        <label for="email">Email:</label>
        <input type="email" id="email" value="<?= htmlspecialchars($email) ?>" disabled readonly>
        <br><br>
        <label for="displayname">Full name:</label>
        <input type="text" id="displayname" value="<?= htmlspecialchars($displayname, ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <p>If you like to change your email address or full display name, please contact me via the details below.</p>
        <hr><br>
        <label for="old_password">Current Password:</label>
        <input type="password" name="old_password" id="old_password" required>
        <br><br>
        <label for="new_password">New Password:</label>
        <input type="password" name="new_password" id="new_password" required>
        <br>
        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" name="confirm_password" id="confirm_password" required>
        <br><br>
        <input type="checkbox" onclick="togglePasswords()"> Show Passwords
        <br><br>
        <button type="submit">Change Password</button>
      </form>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <h3>Get in touch</h3>
      <p>I control access to some parts of this website. My tasting notes and blog posts are reserved for members only - as is
      my <em>carte des vins</em>, an interactive account of the wines in my personal cellar, which are ready to be drunk. 
      Registered users can also leave comments on my notes and enjoy a safe and well-behaved place to discuss their favourite
      topic, wine. User accounts are free, I don't want to make money from this site, but usually only friends and family have
      access. If you'd like to introduce yourself and connect with me, you may do so using my details below. I'm always happy
      to hear and learn from other wine enthusiasts.</p>
    </div>
  </div>
</div>

<div class="footer">
  <footer>
    <p style="float:right;margin-top:0;">
      <a href="/privacy.php" title="Privacy policy">Privacy policy</a>
      <br><a href="/impressum.php" title="Impressum / Imprint">Impressum / Imprint</a>
    </p>
    <address>
      Contact details:<br>
      Dominik Mueller<br>
      Muehlstr. 24<br>
      76532 Baden-Baden<br>
      GERMANY<br><br>
      E-Mail: <a href="mailto:dm@dmueller.com" title="Contact me by email">dm@dmueller.com</a>
    </address>
    <p align="center">
      <small><u>Cookie notice:</u><br>This website uses session cookies for members logging in only. Aside from that,<br>the
      website uses <strong>no</strong> cookies. Refer to the <a href="/privacy.php" alt="Privacy policy">privacy policy</a>
      for details. Have fun!</small>
    </p>
  </footer>
</div>

</body>

</html>

<script>
function togglePasswords() {
  var x = document.getElementById("old_password");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
  }
  var x = document.getElementById("new_password");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
  }
  var x = document.getElementById("confirm_password");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
  }
}
</script>