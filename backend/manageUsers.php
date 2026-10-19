<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialisation file (handles sessions, DB connection, and RBAC gatekeeping)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified User Management Dashboard.
   *
   * Combines user account creation, profile editing, password resets,
   * role assignments, and links to RBAC privilege customisation.
   * Required privilege: 'manage_users'
   */
  if (!hasPrivilege($conn, 'manage_users')) {
    header("Location: /backend/index.php");
    exit();
  }

  $can_add = true;
  $can_edit = true;

  global $mysqli, $conn;

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

  // Get all users
  $users = getAllUsersList($conn);

  // Determine mode
  $requested_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_user', FILTER_VALIDATE_INT);
  $mode_param = sanitizeInput($_GET['mode'] ?? $_POST['mode'] ?? '');

  if ($requested_user_id && $can_edit) {
    $mode = 'edit';
  } elseif ($mode_param === 'add' && $can_add) {
    $mode = 'add';
  } elseif ($mode_param === 'edit' && $can_edit) {
    $mode = 'edit';
  } else {
    $mode = $can_edit ? 'edit' : 'add';
  }

  $selected_user_id = $requested_user_id ?: (!empty($users) ? (int)$users[0]['user_id'] : null);
  $selectedUser = $selected_user_id ? getUserDetails($conn, $selected_user_id) : null;

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      $error = "Security check failed. Please refresh the page and try again.";
    } else {
      $action = sanitizeInput($_POST['action'] ?? '');

      if ($action === 'add_user') {
        if (!$can_add) die("Permission denied: You cannot create new users.");

        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $email       = trim($_POST['email'] ?? '');
        $displayname = trim($_POST['displayname'] ?? '');
        $initials    = strtoupper(trim($_POST['initials'] ?? ''));
        $role        = trim($_POST['role'] ?? 'read');

        $validRoleNames = array_column($allowedRoles, 'role_name');
        if ($role === 'admin' || !in_array($role, $validRoleNames, true)) {
          $error = "Invalid role selected. Administrator accounts cannot be created via the interface.";
        } elseif (!$username || !$password || !$email || !$displayname || !$role || !$initials) {
          $error = "All fields are required.";
        } elseif (!preg_match('/^[A-Z0-9]{2,5}$/', $initials)) {
          $error = "Initials must be between 2 and 5 alphanumeric characters.";
        } else {
          $conn->begin_transaction();
          try {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
              $stmt->close();
              throw new Exception("Username already taken.");
            }
            $stmt->close();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_ins = $conn->prepare("INSERT INTO users (username, password, displayname, role, email, initials) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("ssssss", $username, $password_hash, $displayname, $role, $email, $initials);
            if ($stmt_ins->execute()) {
              $new_id = $conn->insert_id;
              $conn->commit();
              $success = "User '" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "' created successfully with role '" . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . "'!";
              $users = getAllUsersList($conn);
              $selected_user_id = $new_id;
              $selectedUser = getUserDetails($conn, $new_id);
            } else {
              throw new Exception($stmt_ins->error);
            }
            $stmt_ins->close();
          } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to register user: " . $e->getMessage();
          }
        }
      } elseif ($action === 'edit_user') {
        if (!$can_edit) die("Permission denied: You cannot edit users.");

        $user_id             = (int)($_POST['user_id'] ?? 0);
        $username            = trim($_POST['username'] ?? '');
        $displayname         = trim($_POST['displayname'] ?? '');
        $email               = trim($_POST['email'] ?? '');
        $initials            = strtoupper(trim($_POST['initials'] ?? ''));
        $role                = trim($_POST['role'] ?? 'read');
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $new_password        = $_POST['new_password'] ?? '';

        $result = updateUserDetails($conn, $user_id, $username, $displayname, $email, $initials, $role, $email_notifications, $new_password);

        if ($result['success']) {
          $selected_user_id = $user_id;
          if ($result['password_reset']) {
            $success = "User details updated and password successfully reset for '" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "'!";
          } else {
            $success = "User details updated successfully for '" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "'!";
          }
          $users = getAllUsersList($conn);
          $selectedUser = getUserDetails($conn, $user_id);
        } else {
          $error = $result['error'];
        }
      }
    }
  }

  $page_title = 'Manage Users - Cellar Administration';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main" style="width: 100%; max-width: 1200px; margin: 0 auto;">
    
    <div style="margin-bottom: 16px;">
      <a href="index.php" style="text-decoration: none; color: var(--secondary-accent, firebrick); font-size: 0.95rem;">
        &larr; Return to Admin Hub
      </a>
    </div>

    <div class="card">
      <div class="admin-card-header">
        <h2 style="margin: 0; color: var(--secondary-accent, firebrick);">
          &#128101; User Accounts &amp; Access Control
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="managePrivileges.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &#128737;&#65039; User &amp; Role Privileges &rarr;
          </a>
        </div>
      </div>

      <?php if (!empty($error)): ?>
        <div style="background-color: #ffdddd; border-left: 5px solid #f44336; padding: 12px 16px; margin-bottom: 20px; color: #a00;">
          <strong>Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 12px 16px; margin-bottom: 20px; color: #2e7d32;">
          <strong>Success:</strong> <?php echo $success; ?>
        </div>
      <?php endif; ?>

      <!-- High-level User Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo count($users); ?></span>
            <span class="kpi-icon">&#128101;</span>
          </div>
          <div class="kpi-label">Registered Accounts</div>
          <div class="kpi-sub">Total active users in cellar</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo count($roles); ?></span>
            <span class="kpi-icon">&#128737;&#65039;</span>
          </div>
          <div class="kpi-label">Configured Roles</div>
          <div class="kpi-sub">Distinct RBAC access tiers</div>
        </div>
      </div>

      <!-- Mode Selector Tabs -->
      <div class="admin-mode-tabs" style="margin-bottom: 20px;">
        <?php if ($can_edit): ?>
          <a href="manageUsers.php?mode=edit" class="admin-tab-btn <?php echo ($mode === 'edit') ? 'active' : ''; ?>">
            &#9998; Edit User &amp; Reset Password
          </a>
        <?php endif; ?>
        <?php if ($can_add): ?>
          <a href="manageUsers.php?mode=add" class="admin-tab-btn <?php echo ($mode === 'add') ? 'active' : ''; ?>">
            &#10010; Add New User
          </a>
        <?php endif; ?>
        <a href="#usersDirectorySection" class="admin-tab-btn" style="color: #555;">
          📋 User Directory (<?php echo count($users); ?>)
        </a>
      </div>

      <?php if ($mode === 'add' && $can_add): ?>
        <!-- ADD USER FORM -->
        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
          <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            &#10010; Create New User Account
          </h3>

          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add_user">

            <div class="form-row-2col" style="margin-bottom: 16px;">
              <div>
                <label for="username" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Username: <span style="color: red;">*</span>
                </label>
                <input type="text" name="username" id="username" required maxlength="50" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="displayname" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Display Name: <span style="color: red;">*</span>
                </label>
                <input type="text" name="displayname" id="displayname" required maxlength="100" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>
            </div>

            <div class="form-row-2col" style="margin-bottom: 16px;">
              <div>
                <label for="email" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Email Address: <span style="color: red;">*</span>
                </label>
                <input type="email" name="email" id="email" required maxlength="50" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="initials" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Initials (2&ndash;5 Alphanumeric): <span style="color: red;">*</span>
                </label>
                <input type="text" name="initials" id="initials" required minlength="2" maxlength="5" pattern="[A-Za-z0-9]{2,5}" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; text-transform: uppercase;">
              </div>
            </div>

            <div class="form-row-2col" style="margin-bottom: 20px;">
              <div>
                <label for="password" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Initial Password: <span style="color: red;">*</span>
                </label>
                <div style="display: flex; gap: 8px;">
                  <input type="password" name="password" id="password" required minlength="6" style="flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <button type="button" onclick="generateNewUserPassword()" class="btn-action btn-secondary" style="font-size: 0.85rem; padding: 8px 14px; white-space: nowrap;">
                    &#127922; Generate
                  </button>
                </div>
                <div style="margin-top: 6px;">
                  <label style="font-size: 0.85rem; cursor: pointer;">
                    <input type="checkbox" onclick="togglePasswordVisibility('password')"> Show Password
                  </label>
                </div>
              </div>

              <div>
                <label for="role" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  User Role: <span style="color: red;">*</span>
                </label>
                <select name="role" id="role" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <?php foreach ($allowedRoles as $r): ?>
                    <option value="<?php echo htmlspecialchars($r['role_name'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($r['role_name'] === 'read') ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Admin accounts cannot be created via the web interface.</div>
              </div>
            </div>

            <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
              &#10010; Create User Account
            </button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($mode === 'edit' && $can_edit): ?>
        <!-- EDIT USER FORM & SELECTOR -->
        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
          <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            &#9998; Edit User Profile &amp; Reset Password
          </h3>

          <div style="margin-bottom: 20px; max-width: 600px;">
            <label for="searchUserBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
              Choose User to Edit:
            </label>
            <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
              <input type="text" id="searchUserBox" placeholder="🔍 Filter users..." onkeyup="filterUserSelectDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
              <select id="userSelect" onchange="window.location.href='manageUsers.php?mode=edit&user_id=' + this.value;" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                <?php foreach ($users as $u): ?>
                  <option value="<?php echo (int)$u['user_id']; ?>" <?php echo ((int)$u['user_id'] === $selected_user_id) ? 'selected' : ''; ?>>
                    #<?php echo (int)$u['user_id']; ?>: <?php echo htmlspecialchars($u['displayname'] . ' (' . $u['username'] . ') [' . $u['role'] . ']', ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <?php if ($selectedUser): ?>
            <form method="POST" accept-charset="UTF-8" style="background: white; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="action" value="edit_user">
              <input type="hidden" name="user_id" value="<?php echo (int)$selectedUser['user_id']; ?>">

              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h4 style="margin: 0; color: var(--secondary-accent, firebrick); font-size: 1.05rem;">
                  Editing User #<?php echo (int)$selectedUser['user_id']; ?>: <?php echo htmlspecialchars($selectedUser['displayname'], ENT_QUOTES, 'UTF-8'); ?>
                </h4>
                <a href="managePrivileges.php?tab=user&user_id=<?php echo (int)$selectedUser['user_id']; ?>" style="font-size: 0.88rem; color: #0d6efd; text-decoration: none;">
                  &#128737;&#65039; Customise Privileges &rarr;
                </a>
              </div>

              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="edit_username" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Username: <span style="color: red;">*</span>
                  </label>
                  <input type="text" name="username" id="edit_username" required maxlength="50" value="<?php echo htmlspecialchars($selectedUser['username'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>

                <div>
                  <label for="edit_displayname" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Display Name: <span style="color: red;">*</span>
                  </label>
                  <input type="text" name="displayname" id="edit_displayname" required maxlength="100" value="<?php echo htmlspecialchars($selectedUser['displayname'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>
              </div>

              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="edit_email" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Email Address: <span style="color: red;">*</span>
                  </label>
                  <input type="email" name="email" id="edit_email" required maxlength="50" value="<?php echo htmlspecialchars($selectedUser['email'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>

                <div>
                  <label for="edit_initials" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Initials (2&ndash;5 Alphanumeric): <span style="color: red;">*</span>
                  </label>
                  <input type="text" name="initials" id="edit_initials" required minlength="2" maxlength="5" pattern="[A-Za-z0-9]{2,5}" value="<?php echo htmlspecialchars($selectedUser['initials'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; text-transform: uppercase;">
                </div>
              </div>

              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="edit_role" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    User Role: <span style="color: red;">*</span>
                  </label>
                  <?php if ((int)$selectedUser['user_id'] === 1): ?>
                    <input type="hidden" name="role" value="admin">
                    <input type="text" value="Administrator (Root Superadmin - Locked)" disabled style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; background: #eee;">
                  <?php else: ?>
                    <select name="role" id="edit_role" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                      <?php foreach ($allowedRoles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r['role_name'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($selectedUser['role'] === $r['role_name']) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; margin-top: 24px;">
                  <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                    <input type="checkbox" name="email_notifications" value="1" <?php echo !empty($selectedUser['email_notifications']) ? 'checked' : ''; ?>>
                    <span><strong>Receive email notifications</strong> (comments &amp; replies)</span>
                  </label>
                </div>
              </div>

              <!-- Password Reset Sub-panel -->
              <div style="background: #fafbfc; border: 1px solid #d0d7de; border-radius: 6px; padding: 16px; margin-top: 15px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 6px 0; color: #24292f; font-size: 0.95rem;">
                  &#128273; Reset Password
                </h4>
                <div style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                  Leave blank to preserve existing password. Enter a new password or generate one below.
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                  <input type="password" name="new_password" id="new_password" minlength="6" placeholder="Enter new password (optional)" style="padding: 8px 10px; width: 280px; border: 1px solid #ccc; border-radius: 4px; min-height: 40px;">
                  <button type="button" onclick="generateResetPassword()" class="btn-action btn-secondary" style="padding: 8px 14px; font-size: 0.85rem;">
                    &#127922; Generate
                  </button>
                  <label style="font-size: 0.85rem; cursor: pointer;">
                    <input type="checkbox" onclick="togglePasswordVisibility('new_password')"> Show Password
                  </label>
                </div>
              </div>

              <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                  Save Changes
                </button>
                <a href="manageUsers.php?mode=edit" class="btn-action btn-secondary" style="padding: 12px 20px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
                  Cancel
                </a>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Searchable User Ledger Directory -->
      <div id="usersDirectorySection" style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
            Registered User Directory (<?php echo count($users); ?> accounts)
          </h3>
          <input type="text" id="userSearchBox" class="admin-search-box" style="margin-bottom: 0; max-width: 320px;" placeholder="Search users..." onkeyup="filterUsersTable()">
        </div>

        <div class="admin-table-container" style="max-height: 480px; overflow-y: auto;">
          <table id="usersTable">
            <thead>
              <tr>
                <th style="width: 60px;">ID</th>
                <th>User / Display Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Initials</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr style="<?php echo ($selected_user_id === (int)$u['user_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                  <td data-label="User ID"><strong>#<?php echo (int)$u['user_id']; ?></strong></td>
                  <td class="card-primary-cell">
                    <div><strong><?php echo htmlspecialchars($u['displayname'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div style="font-size: 0.78rem; color: #777;"><code><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></code></div>
                  </td>
                  <td data-label="Email"><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td data-label="Role">
                    <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; <?php echo ($u['role'] === 'admin') ? 'background: #fce4ec; color: #c2185b;' : 'background: #e3f2fd; color: #1565c0;'; ?>">
                      <?php echo htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td data-label="Initials"><strong><?php echo htmlspecialchars($u['initials'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                  <td style="text-align: right; white-space: nowrap;">
                    <?php if ($can_edit): ?>
                      <a href="manageUsers.php?mode=edit&user_id=<?php echo (int)$u['user_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                        Edit
                      </a>
                    <?php endif; ?>
                    <a href="managePrivileges.php?tab=user&user_id=<?php echo (int)$u['user_id']; ?>" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                      Privileges &rarr;
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  function togglePasswordVisibility(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    el.type = (el.type === 'password') ? 'text' : 'password';
  }

  function generateRandomPassword() {
    const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*';
    let pass = '';
    for (let i = 0; i < 12; i++) {
      pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pass;
  }

  function generateNewUserPassword() {
    const p = generateRandomPassword();
    const el = document.getElementById('password');
    if (el) {
      el.value = p;
      el.type = 'text';
    }
  }

  function generateResetPassword() {
    const p = generateRandomPassword();
    const el = document.getElementById('new_password');
    if (el) {
      el.value = p;
      el.type = 'text';
    }
  }

  function filterUsersTable() {
    const searchVal = document.getElementById('userSearchBox').value.toLowerCase().trim();
    const table = document.getElementById('usersTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.cells.length < 6) continue;
      const textContent = row.textContent.toLowerCase();
      row.style.display = (!searchVal || textContent.includes(searchVal)) ? '' : 'none';
    }
  }

  let cachedUserSelectOptions = null;
  function filterUserSelectDropdown() {
    const sel = document.getElementById('userSelect');
    const input = document.getElementById('searchUserBox');
    if (!sel || !input) return;
    if (!cachedUserSelectOptions) {
      cachedUserSelectOptions = Array.from(sel.options);
    }
    const filter = input.value.toLowerCase().trim();
    const currentVal = sel.value;

    sel.innerHTML = '';
    let hasSelected = false;

    cachedUserSelectOptions.forEach(opt => {
      if (!filter || opt.text.toLowerCase().includes(filter) || opt.value === '') {
        const cloned = opt.cloneNode(true);
        if (cloned.value === currentVal) {
          cloned.selected = true;
          hasSelected = true;
        }
        sel.appendChild(cloned);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    const userSel = document.getElementById('userSelect');
    if (userSel) {
      cachedUserSelectOptions = Array.from(userSel.options);
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
