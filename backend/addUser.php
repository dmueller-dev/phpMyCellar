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
  $csrf_token = generateCSRFToken();

  // Get available non-admin roles
  $roles = getAllRoles($conn);
  $allowedRoles = [];
  foreach ($roles as $r) {
    if ($r['role_name'] !== 'admin' && $r['role_name'] !== 'public') {
      $allowedRoles[] = $r;
    }
  }

  // Handle form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      $error = "Security check failed. Please refresh the page and try again.";
    } else {
      // Get and sanitize input
      $username    = trim($_POST['username'] ?? '');
      $password    = $_POST['password'] ?? '';
      $email       = trim($_POST['email'] ?? '');
      $displayname = trim($_POST['displayname'] ?? '');
      $initials    = strtoupper(trim($_POST['initials'] ?? ''));
      $role        = trim($_POST['role'] ?? 'read');

      // Security Backstop: Admin role cannot be assigned via GUI
      $validRoleNames = array_column($allowedRoles, 'role_name');
      if ($role === 'admin' || !in_array($role, $validRoleNames)) {
        $error = "Invalid role selected. Administrator accounts cannot be created via the interface.";
      } elseif (!$username || !$password || !$email || !$displayname || !$role || !$initials) {
        $error = "All fields are required.";
      } elseif (!preg_match('/^[A-Z0-9]{2,5}$/', $initials)) {
        $error = "Initials must be between 2 and 5 alphanumeric characters.";
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
            $stmt = $conn->prepare("INSERT INTO users (username, password, displayname, role, email, initials) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $password_hash, $displayname, $role, $email, $initials);
            if ($stmt->execute()) {
              $conn->commit();
              $success = "User added successfully with role '" . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . "'! They can now <a href='/login.php'>log in</a>.";
            } else {
              $conn->rollback();
              $error = "Failed to register: " . $stmt->error;
            }
            $stmt->close();
          }
        }
      }
    }
  }
?>

<?php
  $page_title = 'Dominik Mueller - Add user';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0;">Add user</h3>
        <a href="/backend/editUser.php" style="font-size: 0.9em; text-decoration: none; padding: 6px 12px; background: #555; color: white; border-radius: 4px;">✏️ Edit User & Reset Password</a>
      </div>
      <?php
        if ($error!="") {
          echo "<div style='margin-top: 15px; color:red;'>$error</div>";
        } elseif ($success!="") {
          echo "<div style='margin-top: 15px; color:green;'>$success</div>";
        }
      ?>
    </div>
    <div class="card">
      <form method="post" action="" accept-charset="UTF-8">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <label for="username">Username:</label>
        <br><input type="text" name="username" id="username" required maxlength="50">
        <br><br>
        <label for="email">Email address:</label>
        <br><input type="email" name="email" id="email" required maxlength="50">
        <br><br>
        <label for="password">Password:</label>
        <br><input type="password" name="password" id="password" required minlength="6">
        <br><input type="checkbox" onclick="togglePassword()"> Show Password
        <br><br>
        <label for="displayname">Display Name:</label>
        <br><input type="text" name="displayname" id="displayname" required maxlength="100">
        <br><br>
        <label for="initials">Initials (2-5 characters, alphanumeric):</label>
        <br><input type="text" name="initials" id="initials" required minlength="2" maxlength="5" pattern="[A-Za-z0-9]{2,5}" title="2 to 5 alphanumeric characters" style="text-transform: uppercase;">
        <br><br>
        <label for="role">User Role:</label>
        <br><select name="role" id="role" style="padding: 5px 10px;">
          <?php foreach ($allowedRoles as $r): ?>
            <option value="<?= htmlspecialchars($r['role_name'], ENT_QUOTES, 'UTF-8') ?>" <?= $r['role_name'] === 'read' ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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
