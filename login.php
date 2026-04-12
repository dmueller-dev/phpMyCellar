<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Generate the token for the form
  $csrf_token = generateCSRFToken();

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the token before processing the Login
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      $error = "Security check failed. Please refresh the page and try again.";
    } else {
      // DB connection
      global $mysqli, $conn;
      
      // Username and password variables
      $username = $_POST['username'];
      $password = $_POST['password'];

      // Perform query using prepared statement to prevent SQL injection
      $stmt = $mysqli->prepare("SELECT user_id, password, role FROM users WHERE username = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();

      // Validate user
      if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $hashedPassword = $user['password'];

        // Verify the password
          if (password_verify($password, $hashedPassword)) {
              $_SESSION['user_id'] = $user['user_id'];
              $_SESSION['role'] = $user['role'];
              $stmt->close();
              header("Location: index.php");
              exit();
          } else {
              $error = "Invalid username or password";
          }
      } else {
        $error = "Invalid username or password";
      }
      $stmt->close();
    }
  }
?>

<?php
  $page_title = 'Dominik Mueller - Wine is my hobby';
  $meta_desc = 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.';
  
  require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3>Login</h3>
      <?php
        if (isset($error)) {
          echo "<p>$error</p>";
          echo "<p>If you do not yet have an account, please contact me using the details below.</p>";
        } else {
          echo "<p>Please log in to access all parts of the website. Thank you.</p>";
          echo "<p>If you do not yet have an account, please contact me using the details below.</p>";
        }
      ?>
    </div>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        Username: <input type="text" name="username"><br>
        Password: <input type="password" name="password"><br>
        <button type="submit">Login</button>
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

<?php require_once 'includes/footer.php'; ?>
