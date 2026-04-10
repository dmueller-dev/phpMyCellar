<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';
 
  // Include the database configuration file
  global $mysqli, $conn;

  // Initialize error and success messages
  $error = "";
  $success = "";

  // Handle form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $email       = trim($_POST['email'] ?? '');
    $displayname = trim($_POST['displayname'] ?? '');
    $role        = "read";

    // Basic validation
    if (!$username || !$password || !$email || !$displayname || !$role) {
      $error = "All fields are required.";
    } else {
      if ($conn->connect_errno) {
        $error = "Failed to connect to MySQL: " . $conn->connect_error;
      } else {
        $conn->begin_transaction();
        // Check if username already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
          $error = "Username already taken.";
          $stmt->close();
        } else {
          $stmt->close();
          // Hash the password
          $password_hash = password_hash($password, PASSWORD_DEFAULT);

          // Insert new user
          $stmt = $conn->prepare("INSERT INTO users (username, password, displayname, role, email) VALUES (?, ?, ?, ?, ?)");
          $stmt->bind_param("sssss", $username, $password_hash, $displayname, $role, $email);
          if ($stmt->execute()) {
            $conn->commit();
            $success = "User added successfully! They can now <a href='/login.php'>log in</a>.";
          } else {
            $conn->rollback();
            $error = "Failed to register: " . $stmt->error;
          }
        }
        $stmt->close();
              }
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
      <li><a href="index.php" title="Backend Home">Index</a></li>
      <li><a href="browseWines.php" title="Show all wines">Wines</a></li>
      <li><a href="browseBottles.php" title="Show all bottles">Bottles</a></li>
      <li><a href="winemenu.php" title="Show wine menu">Wine menu</a></li>
      <li class="right"><a href="https://dmueller.com" title="Frontend">Go to website</a></li>
    </ul>
  </nav>
</header>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3>Add user</h3>
      <?php
        if ($error!="") {
          echo "<div style='color:red;'>$error</div>";
        } elseif ($success!="") {
          echo "<div style='color:green;'>$success</div>";
        }
      ?>
    </div>
    <div class="card">
      <form method="post" action="" accept-charset="UTF-8">
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" required maxlength="50">
        <br><br>
        <label for="email">Email address:</label>
        <input type="email" name="email" id="email" required maxlength="50">
        <br><br>
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required minlength="6">
        <br><input type="checkbox" onclick="togglePassword()"> Show Password
        <br><br>
        <label for="displayname">Display Name:</label>
        <input type="text" name="displayname" id="displayname" required maxlength="100">
        <br><br>
        <input type="submit" value="Sign Up">
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
  function togglePassword() {
    var x = document.getElementById("password");
    if (x.type === "password") {
      x.type = "text";
    } else {
      x.type = "password";
    }
  }
</script>
