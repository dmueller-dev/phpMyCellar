<?php
/**
 * phpMyCellar - First-Time Installation & Setup Wizard
 *
 * This interactive wizard guides administrators through prerequisite verification,
 * database setup, schema execution, initial administrator configuration, and site settings.
 */

session_start();

// Define paths
define('ROOT_DIR', dirname(__DIR__));
define('INSTALL_DIR', __DIR__);
define('LOCK_FILE', INSTALL_DIR . '/installed.lock');
define('ENV_FILE', ROOT_DIR . '/.env');
define('SCHEMA_FILE', INSTALL_DIR . '/schema.sql');
define('SEED_FILE', INSTALL_DIR . '/seed.sql');

// Determine current step (1 to 5)
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 5) {
  $step = 1;
}

$is_locked = file_exists(LOCK_FILE);
$errors = [];
$success_msg = '';

// Helper: Auto-detect base site URL
function detect_site_url() {
  $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
              (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
              (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
  $protocol = $is_https ? 'https://' : 'http://';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
  return rtrim($protocol . $host, '/');
}

// Helper: Execute multi-query SQL file with error tracking
function execute_sql_file($mysqli, $file_path) {
  if (!file_exists($file_path)) {
    return [false, "SQL file not found: " . basename($file_path)];
  }

  $sql_content = file_get_contents($file_path);
  if ($sql_content === false || trim($sql_content) === '') {
    return [false, "Failed to read SQL file: " . basename($file_path)];
  }

  // Remove comment blocks and split queries
  $lines = explode("\n", $sql_content);
  $clean_sql = '';
  foreach ($lines as $line) {
    $trimmed = trim($line);
    if (strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
      continue;
    }
    $clean_sql .= $line . "\n";
  }

  // Execute using multi_query
  if ($mysqli->multi_query($clean_sql)) {
    do {
      if ($result = $mysqli->store_result()) {
        $result->free();
      }
    } while ($mysqli->more_results() && $mysqli->next_result());

    if ($mysqli->errno) {
      return [false, "SQL execution error in " . basename($file_path) . ": " . $mysqli->error];
    }
  } else {
    return [false, "Failed to execute " . basename($file_path) . ": " . $mysqli->error];
  }

  return [true, ''];
}

// Helper: Generate .env file content
function generate_env_content($db_host, $db_name, $db_user, $db_pass, $site_url, $mail_from) {
  $out = "# ==============================================================================\n";
  $out .= "# phpMyCellar Environment Configuration\n";
  $out .= "# Generated on " . date('r') . "\n";
  $out .= "# ==============================================================================\n\n";
  $out .= "# Database Connection Settings\n";
  $out .= "DB_HOST=" . addcslashes($db_host, '"') . "\n";
  $out .= "DB_NAME=" . addcslashes($db_name, '"') . "\n";
  $out .= "DB_USER=" . addcslashes($db_user, '"') . "\n";
  $out .= "DB_PASS=" . addcslashes($db_pass, '"') . "\n\n";
  $out .= "# Application Base Settings\n";
  $out .= "APP_ENV=production\n";
  $out .= "APP_URL=" . addcslashes($site_url, '"') . "\n\n";
  $out .= "# System Notifications & Mail Sender\n";
  $out .= "MAIL_FROM=" . addcslashes($mail_from, '"') . "\n";
  return $out;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
  $action = $_POST['action'] ?? '';

  // ----------------------------------------------------
  // Action: Step 2 - Database Setup & Execution
  // ----------------------------------------------------
  if ($action === 'setup_db') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_port = (int)($_POST['db_port'] ?? 3306);
    $db_name = trim($_POST['db_name'] ?? 'phpmycellar');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';

    if (empty($db_host) || empty($db_name) || empty($db_user)) {
      $errors[] = 'Database Host, Name, and Username are required.';
    } else {
      // Step 2a: Test connection (first without selecting DB to auto-create if needed)
      mysqli_report(MYSQLI_REPORT_OFF);
      $test_conn = @new mysqli($db_host, $db_user, $db_pass, '', $db_port);
      if ($test_conn->connect_error) {
        $errors[] = 'Could not connect to MySQL server: ' . $test_conn->connect_error;
      } else {
        // Try creating database if not exists
        $safe_dbname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $db_name);
        $create_query = "CREATE DATABASE IF NOT EXISTS `{$safe_dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        if (!$test_conn->query($create_query)) {
          $errors[] = 'Connected to server, but failed to create/select database `' . htmlspecialchars($safe_dbname, ENT_QUOTES, 'UTF-8') . '`: ' . $test_conn->error;
        } else {
          $test_conn->select_db($safe_dbname);
          $test_conn->set_charset("utf8mb4");

          // Step 2b: Execute schema.sql
          list($schema_ok, $schema_err) = execute_sql_file($test_conn, SCHEMA_FILE);
          if (!$schema_ok) {
            $errors[] = 'Error installing database schema: ' . $schema_err;
          } else {
            // Step 2c: Execute seed.sql
            list($seed_ok, $seed_err) = execute_sql_file($test_conn, SEED_FILE);
            if (!$seed_ok) {
              $errors[] = 'Error seeding database initial data: ' . $seed_err;
            } else {
              // Store DB parameters in session and proceed to Step 3
              $_SESSION['install_db'] = [
                'host' => $db_host,
                'port' => $db_port,
                'name' => $safe_dbname,
                'user' => $db_user,
                'pass' => $db_pass
              ];
              header("Location: index.php?step=3");
              exit();
            }
          }
        }
        $test_conn->close();
      }
    }
  }

  // ----------------------------------------------------
  // Action: Step 3 - Create Admin User
  // ----------------------------------------------------
  elseif ($action === 'create_admin') {
    if (empty($_SESSION['install_db'])) {
      $errors[] = 'Database session expired. Please restart database configuration.';
      $step = 2;
    } else {
      $username    = trim($_POST['username'] ?? 'admin');
      $displayname = trim($_POST['displayname'] ?? 'Cellar Master');
      $email       = trim($_POST['email'] ?? '');
      $initials    = strtoupper(trim($_POST['initials'] ?? 'CM'));
      $password    = $_POST['password'] ?? '';
      $password_c  = $_POST['password_confirm'] ?? '';

      if (empty($username) || empty($displayname) || empty($email) || empty($initials) || empty($password)) {
        $errors[] = 'All administrator fields are required.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid administrator email address.';
      } elseif (strlen($password) < 8) {
        $errors[] = 'Administrator password must be at least 8 characters long.';
      } elseif ($password !== $password_c) {
        $errors[] = 'Passwords do not match.';
      } elseif (!preg_match('/^[A-Z0-9]{2,5}$/', $initials)) {
        $errors[] = 'Initials must be between 2 and 5 alphanumeric characters (e.g. "CM").';
      } else {
        $db = $_SESSION['install_db'];
        $conn = @new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], $db['port']);
        if ($conn->connect_error) {
          $errors[] = 'Database connection failed: ' . $conn->connect_error;
        } else {
          $conn->set_charset("utf8mb4");
          $password_hash = password_hash($password, PASSWORD_DEFAULT);

          // Clear any existing user with ID 1 or same username
          $conn->query("DELETE FROM users WHERE user_id = 1 OR username = '" . $conn->real_escape_string($username) . "'");

          $stmt = $conn->prepare("INSERT INTO users (user_id, username, password, displayname, role, email, initials, email_notifications) VALUES (1, ?, ?, ?, 'admin', ?, ?, 1)");
          $stmt->bind_param("sssss", $username, $password_hash, $displayname, $email, $initials);

          if ($stmt->execute()) {
            $_SESSION['install_admin'] = [
              'username' => $username,
              'displayname' => $displayname,
              'email' => $email,
              'initials' => $initials
            ];
            $stmt->close();
            $conn->close();
            header("Location: index.php?step=4");
            exit();
          } else {
            $errors[] = 'Failed to create administrator account: ' . $stmt->error;
            $stmt->close();
          }
          $conn->close();
        }
      }
    }
  }

  // ----------------------------------------------------
  // Action: Step 4 - Site Settings & Finalisation
  // ----------------------------------------------------
  elseif ($action === 'save_settings') {
    if (empty($_SESSION['install_db']) || empty($_SESSION['install_admin'])) {
      $errors[] = 'Installation session expired. Please restart the wizard.';
      $step = 2;
    } else {
      $site_name        = trim($_POST['site_name'] ?? 'phpMyCellar');
      $site_tagline     = trim($_POST['site_tagline'] ?? 'Fine Wine Cellar & Tasting Notes');
      $site_url         = rtrim(trim($_POST['site_url'] ?? detect_site_url()), '/');
      $owner_name       = trim($_POST['owner_name'] ?? $_SESSION['install_admin']['displayname']);
      $owner_email      = trim($_POST['owner_email'] ?? $_SESSION['install_admin']['email']);
      $currency_symbol  = trim($_POST['currency_symbol'] ?? '€');
      $rating_scale     = trim($_POST['rating_scale'] ?? '20-point');
      $wset_mode        = trim($_POST['wset_mode'] ?? 'public');
      if (!in_array($wset_mode, ['public', 'logged_in', 'backend_only', 'disabled'], true)) {
        $wset_mode = 'public';
      }
      $accent_color     = trim($_POST['theme_accent_color'] ?? '#CD5C5C');
      $mail_from        = trim($_POST['mail_from'] ?? $owner_email);

      if (empty($site_name) || empty($site_url)) {
        $errors[] = 'Site Name and Site Base URL are required.';
      } else {
        $db = $_SESSION['install_db'];
        $conn = @new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], $db['port']);
        if ($conn->connect_error) {
          $errors[] = 'Database connection failed: ' . $conn->connect_error;
        } else {
          $conn->set_charset("utf8mb4");

          // Save settings to database
          $settings_to_update = [
            'site_name' => [$site_name, 'general'],
            'site_tagline' => [$site_tagline, 'general'],
            'site_url' => [$site_url, 'general'],
            'owner_name' => [$owner_name, 'general'],
            'owner_email' => [$owner_email, 'general'],
            'currency_symbol' => [$currency_symbol, 'general'],
            'rating_scale' => [$rating_scale, 'general'],
            'wset_mode' => [$wset_mode, 'general'],
            'wset_display_format' => ['standard', 'general'],
            'theme_accent_color' => [$accent_color, 'theme']
          ];

          $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)");
          foreach ($settings_to_update as $k => $v) {
            $stmt->bind_param("sss", $k, $v[0], $v[1]);
            $stmt->execute();
          }
          $stmt->close();
          $conn->close();

          // Write .env file
          $env_content = generate_env_content($db['host'], $db['name'], $db['user'], $db['pass'], $site_url, $mail_from);
          if (@file_put_contents(ENV_FILE, $env_content) === false) {
            $errors[] = 'Failed to write configuration to ' . ENV_FILE . '. Please ensure write permissions on root directory.';
          } else {
            // Write installed.lock file
            $lock_data = "Installed on: " . date('Y-m-d H:i:s T') . "\nSite URL: " . $site_url . "\nAdmin: " . $_SESSION['install_admin']['username'] . "\n";
            @file_put_contents(LOCK_FILE, $lock_data);

            // Clean install session data
            unset($_SESSION['install_db']);
            unset($_SESSION['install_admin']);

            header("Location: index.php?step=5");
            exit();
          }
        }
      }
    }
  }
}

// System Pre-Check Evaluation (Step 1)
$checks = [
  'php_version' => [
    'title' => 'PHP Version (>= 7.4, 8.x recommended)',
    'pass' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'current' => PHP_VERSION,
    'required' => '>= 7.4.0',
    'mandatory' => true
  ],
  'ext_mysqli' => [
    'title' => 'MySQLi Extension',
    'pass' => extension_loaded('mysqli'),
    'current' => extension_loaded('mysqli') ? 'Installed' : 'Missing',
    'required' => 'Required',
    'mandatory' => true
  ],
  'ext_mbstring' => [
    'title' => 'Multibyte String (mbstring)',
    'pass' => extension_loaded('mbstring'),
    'current' => extension_loaded('mbstring') ? 'Installed' : 'Missing',
    'required' => 'Required',
    'mandatory' => true
  ],
  'ext_session' => [
    'title' => 'Session Support',
    'pass' => extension_loaded('session'),
    'current' => extension_loaded('session') ? 'Installed' : 'Missing',
    'required' => 'Required',
    'mandatory' => true
  ],
  'ext_json' => [
    'title' => 'JSON Extension',
    'pass' => extension_loaded('json'),
    'current' => extension_loaded('json') ? 'Installed' : 'Missing',
    'required' => 'Required',
    'mandatory' => true
  ],
  'ext_fileinfo' => [
    'title' => 'FileInfo Extension (MIME Detection)',
    'pass' => extension_loaded('fileinfo'),
    'current' => extension_loaded('fileinfo') ? 'Installed' : 'Missing',
    'required' => 'Recommended',
    'mandatory' => false
  ],
  'ext_gd' => [
    'title' => 'GD or ImageMagick (Image Processing)',
    'pass' => extension_loaded('gd') || extension_loaded('imagick'),
    'current' => (extension_loaded('gd') ? 'GD ' : '') . (extension_loaded('imagick') ? 'Imagick' : (!extension_loaded('gd') ? 'Missing' : '')),
    'required' => 'Recommended',
    'mandatory' => false
  ],
  'perm_root' => [
    'title' => 'Root Directory Writable (for .env creation)',
    'pass' => is_writable(ROOT_DIR),
    'current' => is_writable(ROOT_DIR) ? 'Writable' : 'Read-only',
    'required' => 'Writable',
    'mandatory' => true
  ],
  'perm_install' => [
    'title' => 'install/ Directory Writable (for lockfile creation)',
    'pass' => is_writable(INSTALL_DIR),
    'current' => is_writable(INSTALL_DIR) ? 'Writable' : 'Read-only',
    'required' => 'Writable',
    'mandatory' => true
  ],
  'perm_uploads' => [
    'title' => 'uploads/ Directory Writable',
    'pass' => is_dir(ROOT_DIR . '/uploads') && is_writable(ROOT_DIR . '/uploads'),
    'current' => is_dir(ROOT_DIR . '/uploads') && is_writable(ROOT_DIR . '/uploads') ? 'Writable' : 'Missing or Read-only',
    'required' => 'Writable',
    'mandatory' => true
  ]
];

$all_mandatory_passed = true;
foreach ($checks as $c) {
  if ($c['mandatory'] && !$c['pass']) {
    $all_mandatory_passed = false;
    break;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installation Wizard - phpMyCellar</title>
  <style>
    :root {
      --primary: #CD5C5C;
      --primary-dark: #8B0000;
      --primary-light: #F9EBEA;
      --bg: #F8F9FA;
      --surface: #FFFFFF;
      --text: #2C3E50;
      --text-muted: #6C757D;
      --border: #E9ECEF;
      --success: #28A745;
      --success-bg: #E8F5E9;
      --danger: #DC3545;
      --danger-bg: #FDEDEC;
      --warning: #E67E22;
      --radius: 8px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.6;
      padding: 30px 15px;
    }

    .wizard-container {
      max-width: 800px;
      margin: 0 auto;
      background: var(--surface);
      border-radius: var(--radius);
      box-shadow: 0 4px 20px rgba(0,0,0,0.06);
      border: 1px solid var(--border);
      overflow: hidden;
    }

    .wizard-header {
      background: #FFFFFF;
      padding: 30px;
      text-align: center;
      border-bottom: 1px solid var(--border);
    }
    .wizard-header h1 {
      font-size: 26px;
      color: var(--primary-dark);
      margin-bottom: 6px;
      font-weight: 700;
    }
    .wizard-header p {
      color: var(--text-muted);
      font-size: 15px;
    }

    /* Stepper Progress Indicator */
    .stepper {
      display: flex;
      justify-content: space-between;
      padding: 20px 30px;
      background: #FAFAFA;
      border-bottom: 1px solid var(--border);
      position: relative;
    }
    .step-item {
      flex: 1;
      text-align: center;
      position: relative;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-muted);
    }
    .step-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #E0E0E0;
      color: #FFF;
      margin-bottom: 6px;
      font-weight: 700;
      font-size: 13px;
    }
    .step-item.active {
      color: var(--primary-dark);
    }
    .step-item.active .step-badge {
      background: var(--primary);
      box-shadow: 0 0 0 3px var(--primary-light);
    }
    .step-item.completed {
      color: var(--success);
    }
    .step-item.completed .step-badge {
      background: var(--success);
    }

    .wizard-body {
      padding: 35px 30px;
    }

    /* Alerts */
    .alert {
      padding: 14px 18px;
      border-radius: var(--radius);
      margin-bottom: 25px;
      font-size: 14px;
      line-height: 1.5;
    }
    .alert-danger {
      background: var(--danger-bg);
      color: #922B21;
      border: 1px solid #F5B7B1;
    }
    .alert-success {
      background: var(--success-bg);
      color: #1E8449;
      border: 1px solid #A9DFBF;
    }
    .alert-warning {
      background: #FEF9E7;
      color: #B7950B;
      border: 1px solid #F9E79F;
    }

    /* Tables & Forms */
    .check-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }
    .check-table th, .check-table td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid var(--border);
      font-size: 14px;
    }
    .check-table th {
      background: #F8F9FA;
      color: var(--text-muted);
      font-weight: 600;
    }
    .badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
    }
    .badge-pass { background: var(--success-bg); color: var(--success); }
    .badge-fail { background: var(--danger-bg); color: var(--danger); }
    .badge-warn { background: #FEF9E7; color: var(--warning); }

    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      font-size: 14px;
      color: var(--text);
    }
    .form-control {
      width: 100%;
      padding: 10px 12px;
      font-size: 14px;
      border: 1px solid #CED4DA;
      border-radius: 6px;
      outline: none;
      transition: border-color 0.15s ease-in-out;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px var(--primary-light);
    }
    .form-row {
      display: flex;
      gap: 15px;
    }
    .form-row .form-group {
      flex: 1;
    }
    .form-help {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 4px;
    }

    /* Action Buttons */
    .wizard-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }
    .btn {
      display: inline-block;
      padding: 10px 22px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      transition: background-color 0.15s ease-in-out;
    }
    .btn-primary {
      background: var(--primary);
      color: #FFFFFF;
    }
    .btn-primary:hover {
      background: var(--primary-dark);
    }
    .btn-secondary {
      background: #E9ECEF;
      color: var(--text);
    }
    .btn-secondary:hover {
      background: #DEE2E6;
    }
    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Lock Screen */
    .lock-box {
      text-align: center;
      padding: 40px 20px;
    }
    .lock-icon {
      font-size: 48px;
      color: var(--primary);
      margin-bottom: 15px;
    }
  </style>
</head>
<body>

<div class="wizard-container">
  <div class="wizard-header">
    <h1>phpMyCellar Setup Wizard</h1>
    <p>Fine Wine Cellar Management & Tasting Notes</p>
  </div>

  <?php if ($is_locked): ?>
    <!-- ==================================================== -->
    <!-- Screen: Installation Locked                          -->
    <!-- ==================================================== -->
    <div class="wizard-body">
      <div class="lock-box">
        <div class="lock-icon">🔒</div>
        <h2 style="margin-bottom:12px;color:var(--text);">Installation is Complete & Locked</h2>
        <p style="color:var(--text-muted);max-width:520px;margin:0 auto 25px;">
          phpMyCellar is already configured. To prevent unauthorized reinstallation, the setup wizard is locked by <code>install/installed.lock</code>.
        </p>
        <p style="font-size:13px;color:#888;margin-bottom:25px;">
          If you wish to re-run the wizard, remove <code>install/installed.lock</code> on your server.
        </p>
        <div>
          <a href="/index.php" class="btn btn-secondary" style="margin-right:10px;">Visit Homepage</a>
          <a href="/login.php" class="btn btn-primary">Log In to Backend</a>
        </div>
      </div>
    </div>

  <?php else: ?>

    <!-- ==================================================== -->
    <!-- Stepper Navigation                                   -->
    <!-- ==================================================== -->
    <div class="stepper">
      <div class="step-item <?php echo ($step === 1 ? 'active' : ($step > 1 ? 'completed' : '')); ?>">
        <div class="step-badge"><?php echo ($step > 1 ? '✓' : '1'); ?></div>
        <div>Prerequisites</div>
      </div>
      <div class="step-item <?php echo ($step === 2 ? 'active' : ($step > 2 ? 'completed' : '')); ?>">
        <div class="step-badge"><?php echo ($step > 2 ? '✓' : '2'); ?></div>
        <div>Database</div>
      </div>
      <div class="step-item <?php echo ($step === 3 ? 'active' : ($step > 3 ? 'completed' : '')); ?>">
        <div class="step-badge"><?php echo ($step > 3 ? '✓' : '3'); ?></div>
        <div>Administrator</div>
      </div>
      <div class="step-item <?php echo ($step === 4 ? 'active' : ($step > 4 ? 'completed' : '')); ?>">
        <div class="step-badge"><?php echo ($step > 4 ? '✓' : '4'); ?></div>
        <div>Site Setup</div>
      </div>
      <div class="step-item <?php echo ($step === 5 ? 'active' : ''); ?>">
        <div class="step-badge">5</div>
        <div>Complete</div>
      </div>
    </div>

    <div class="wizard-body">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <strong>Please address the following errors:</strong>
          <ul style="margin-top:6px;margin-left:20px;">
            <?php foreach ($errors as $e): ?>
              <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- ==================================================== -->
      <!-- Step 1: System Checks                                -->
      <!-- ==================================================== -->
      <?php if ($step === 1): ?>
        <h2 style="margin-bottom:12px;">Step 1: System & Environment Checks</h2>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">
          Verifying that your hosting server meets the requirements for phpMyCellar.
        </p>

        <table class="check-table">
          <thead>
            <tr>
              <th>Requirement</th>
              <th>Required</th>
              <th>Detected</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($checks as $chk): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($chk['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars($chk['required'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($chk['current'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php if ($chk['pass']): ?>
                    <span class="badge badge-pass">✓ Pass</span>
                  <?php elseif ($chk['mandatory']): ?>
                    <span class="badge badge-fail">✕ Fail</span>
                  <?php else: ?>
                    <span class="badge badge-warn">⚠ Warning</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!$all_mandatory_passed): ?>
          <div class="alert alert-warning">
            Some mandatory system requirements or directory permissions are missing. Please resolve the marked items before proceeding.
          </div>
        <?php endif; ?>

        <div class="wizard-actions">
          <div></div>
          <a href="index.php?step=2" class="btn btn-primary <?php echo (!$all_mandatory_passed ? 'disabled' : ''); ?>" <?php echo (!$all_mandatory_passed ? 'onclick="return false;"' : ''); ?>>
            Continue to Database Setup &rarr;
          </a>
        </div>

      <!-- ==================================================== -->
      <!-- Step 2: Database Configuration                       -->
      <!-- ==================================================== -->
      <?php elseif ($step === 2): ?>
        <h2 style="margin-bottom:12px;">Step 2: Database Configuration & Migration</h2>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">
          Enter your MySQL / MariaDB connection credentials. The wizard will create the database if needed and install all required tables and default lookups.
        </p>

        <form method="POST" action="index.php?step=2">
          <input type="hidden" name="action" value="setup_db">

          <div class="form-row">
            <div class="form-group" style="flex:2;">
              <label for="db_host">Database Host</label>
              <input type="text" id="db_host" name="db_host" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_db']['host'] ?? 'localhost', ENT_QUOTES, 'UTF-8'); ?>" required>
              <div class="form-help">Usually <code>localhost</code> or <code>127.0.0.1</code>.</div>
            </div>
            <div class="form-group" style="flex:1;">
              <label for="db_port">Port</label>
              <input type="number" id="db_port" name="db_port" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_db']['port'] ?? 3306, ENT_QUOTES, 'UTF-8'); ?>" required>
              <div class="form-help">Default: <code>3306</code></div>
            </div>
          </div>

          <div class="form-group">
            <label for="db_name">Database Name</label>
            <input type="text" id="db_name" name="db_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_db']['name'] ?? 'phpmycellar', ENT_QUOTES, 'UTF-8'); ?>" required>
            <div class="form-help">Will be created automatically if it does not yet exist.</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="db_user">Database Username</label>
              <input type="text" id="db_user" name="db_user" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_db']['user'] ?? 'root', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
              <label for="db_pass">Database Password</label>
              <input type="password" id="db_pass" name="db_pass" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_db']['pass'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <div class="wizard-actions">
            <a href="index.php?step=1" class="btn btn-secondary">&larr; Back to Prerequisites</a>
            <button type="submit" class="btn btn-primary">Test & Install Database &rarr;</button>
          </div>
        </form>

      <!-- ==================================================== -->
      <!-- Step 3: Administrator Setup                          -->
      <!-- ==================================================== -->
      <?php elseif ($step === 3): ?>
        <h2 style="margin-bottom:12px;">Step 3: Primary Administrator Account</h2>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">
          Create the super-administrator account (User ID #1) for managing your wine cellar notebook and configuring site permissions.
        </p>

        <form method="POST" action="index.php?step=3">
          <input type="hidden" name="action" value="create_admin">

          <div class="form-row">
            <div class="form-group">
              <label for="username">Admin Username</label>
              <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_admin']['username'] ?? 'admin', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
              <label for="displayname">Full Name / Display Name</label>
              <input type="text" id="displayname" name="displayname" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_admin']['displayname'] ?? 'Cellar Master', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group" style="flex:2;">
              <label for="email">Administrator Email</label>
              <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_admin']['email'] ?? 'cellar@example.com', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group" style="flex:1;">
              <label for="initials">Initials (2-5 chars)</label>
              <input type="text" id="initials" name="initials" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_admin']['initials'] ?? 'CM', ENT_QUOTES, 'UTF-8'); ?>" maxlength="5" required>
              <div class="form-help">E.g. <code>CM</code></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="password">Password (Min. 8 characters)</label>
              <input type="password" id="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
              <label for="password_confirm">Confirm Password</label>
              <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8">
            </div>
          </div>

          <div class="wizard-actions">
            <a href="index.php?step=2" class="btn btn-secondary">&larr; Back to Database</a>
            <button type="submit" class="btn btn-primary">Save Admin & Continue &rarr;</button>
          </div>
        </form>

      <!-- ==================================================== -->
      <!-- Step 4: Site Settings                                -->
      <!-- ==================================================== -->
      <?php elseif ($step === 4): ?>
        <h2 style="margin-bottom:12px;">Step 4: Site Branding & Initial Settings</h2>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">
          Configure your cellar's public title, base URL, currency symbol, and preferred rating scale. You can adjust all of these later under Site Settings.
        </p>

        <form method="POST" action="index.php?step=4">
          <input type="hidden" name="action" value="save_settings">

          <div class="form-row">
            <div class="form-group">
              <label for="site_name">Website Name</label>
              <input type="text" id="site_name" name="site_name" class="form-control" value="phpMyCellar" required>
            </div>
            <div class="form-group">
              <label for="site_tagline">Tagline</label>
              <input type="text" id="site_tagline" name="site_tagline" class="form-control" value="Fine Wine Cellar & Tasting Notes">
            </div>
          </div>

          <div class="form-group">
            <label for="site_url">Base URL</label>
            <input type="url" id="site_url" name="site_url" class="form-control" value="<?php echo htmlspecialchars(detect_site_url(), ENT_QUOTES, 'UTF-8'); ?>" required>
            <div class="form-help">Canonical URL where this installation is accessible.</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="currency_symbol">Default Currency Symbol</label>
              <input type="text" id="currency_symbol" name="currency_symbol" class="form-control" value="€" required>
              <div class="form-help">E.g. <code>€</code>, <code>$</code>, <code>£</code>, <code>CHF</code></div>
            </div>
            <div class="form-group">
              <label for="rating_scale">Default Rating Scale</label>
              <select id="rating_scale" name="rating_scale" class="form-control">
                <option value="20-point" selected>20-Point Scale (0 to 20)</option>
                <option value="100-point">100-Point Scale (50 to 100)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="wset_mode">WSET SAT Evaluation (0.0 to 4.0)</label>
              <select id="wset_mode" name="wset_mode" class="form-control">
                <option value="public" selected>Public (Display WSET SAT criteria &amp; scoring)</option>
                <option value="logged_in">Members Only (Visible only to logged-in users)</option>
                <option value="backend_only">Backend Only (Enter in backend, hide on public site)</option>
                <option value="disabled">Disabled (Hide WSET SAT fields)</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="theme_accent_color">Theme Accent Colour</label>
              <div style="display:flex;gap:10px;align-items:center;">
                <input type="color" id="theme_accent_color" name="theme_accent_color" value="#CD5C5C" style="width:45px;height:38px;padding:2px;border:1px solid #ced4da;border-radius:4px;cursor:pointer;" onchange="document.getElementById('color_text').value=this.value;">
                <input type="text" id="color_text" class="form-control" value="#CD5C5C" style="max-width:120px;" onchange="document.getElementById('theme_accent_color').value=this.value;">
              </div>
            </div>
            <div class="form-group">
              <label for="mail_from">System Notification Sender Email</label>
              <input type="email" id="mail_from" name="mail_from" class="form-control" value="<?php echo htmlspecialchars($_SESSION['install_admin']['email'] ?? 'cellar@example.com', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <div class="wizard-actions">
            <a href="index.php?step=3" class="btn btn-secondary">&larr; Back to Administrator</a>
            <button type="submit" class="btn btn-primary">Complete Installation &rarr;</button>
          </div>
        </form>

      <!-- ==================================================== -->
      <!-- Step 5: Installation Complete                        -->
      <!-- ==================================================== -->
      <?php elseif ($step === 5): ?>
        <div style="text-align:center;padding:20px 0;">
          <div style="font-size:52px;color:var(--success);margin-bottom:15px;">🎉</div>
          <h2 style="margin-bottom:12px;color:var(--text);">phpMyCellar Successfully Installed!</h2>
          <p style="color:var(--text-muted);max-width:560px;margin:0 auto 25px;font-size:15px;">
            Your database has been populated, your administrator account is active, and your configuration file <code>.env</code> has been saved.
          </p>

          <div class="alert alert-success" style="max-width:560px;margin:0 auto 30px;text-align:left;">
            <strong>Security Notice:</strong>
            <div style="font-size:13px;margin-top:6px;">
              • The installation wizard has been locked by <code>install/installed.lock</code>.<br>
              • Please ensure your web server restricts access to <code>.env</code> and <code>includes/</code>.
            </div>
          </div>

          <div>
            <a href="/login.php" class="btn btn-primary" style="padding:12px 28px;font-size:15px;margin-right:12px;">Log In to Backend &rarr;</a>
            <a href="/index.php" class="btn btn-secondary" style="padding:12px 24px;font-size:15px;">Visit Homepage</a>
          </div>
        </div>

      <?php endif; ?>

    </div>

  <?php endif; ?>

</div>

</body>
</html>
