<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions, DB connection, and RBAC gatekeeping)
  require_once __DIR__ . '/../includes/init.php';

  global $mysqli, $conn;

  // Initialize error and success messages
  $error = "";
  $success = "";
  $csrf_token = generateCSRFToken();

  // Get all users for the selector dropdown
  $users = getAllUsersList($conn);

  // Determine selected user ID
  $selected_user_id = null;
  if (isset($_GET['user_id'])) {
    $selected_user_id = (int)$_GET['user_id'];
  } elseif (isset($_POST['user_id'])) {
    $selected_user_id = (int)$_POST['user_id'];
  } elseif (!empty($users)) {
    $selected_user_id = (int)$users[0]['user_id'];
  }

  // Get available roles
  $allRoles = getAllRoles($conn);
  $allowedRoles = [];
  foreach ($allRoles as $r) {
    if ($r['role_name'] !== 'public' && $r['role_name'] !== 'admin') {
      $allowedRoles[] = $r;
    }
  }

  // Handle form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      $error = "Security check failed. Please refresh the page and try again.";
    } else {
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
        // Refresh user list
        $users = getAllUsersList($conn);
      } else {
        $error = $result['error'];
      }
    }
  }

  // Fetch selected user details
  $selectedUser = $selected_user_id ? getUserDetails($conn, $selected_user_id) : null;
?>

<?php
  $page_title = 'Dominik Mueller - Edit user';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0;">Edit User & Reset Password</h3>
        <div>
          <a href="/backend/addUser.php" style="font-size: 0.9em; text-decoration: none; padding: 6px 12px; background: #555; color: white; border-radius: 4px;">➕ Add New User</a>
          <?php if ($selectedUser): ?>
            <a href="/backend/managePrivileges.php?tab=user&user_id=<?= (int)$selectedUser['user_id'] ?>" style="font-size: 0.9em; text-decoration: none; padding: 6px 12px; background: #6c757d; color: white; border-radius: 4px; margin-left: 5px;">🛡️ Privileges</a>
          <?php endif; ?>
        </div>
      </div>
      <p style="margin-top: 8px; margin-bottom: 0; color: #555;">Select a user to modify their account profile, assign a base role, or reset their login credentials.</p>
      <?php
        if ($error != "") {
          echo "<div style='margin-top: 15px; padding: 10px 15px; background-color: #fee; border-left: 5px solid #d9534f; color: #a94442;'><strong>Error:</strong> " . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</div>";
        } elseif ($success != "") {
          echo "<div style='margin-top: 15px; padding: 10px 15px; background-color: #efe; border-left: 5px solid #5cb85c; color: #3c763d;'><strong>Success:</strong> " . $success . "</div>";
        }
      ?>
    </div>

    <!-- User Selector Card -->
    <div class="card">
      <label for="userSearchInput" style="font-weight: bold;">Select User to Edit:</label>
      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 8px;">
        <input type="text" id="userSearchInput" placeholder="Filter by name, username, or initials..." style="padding: 6px 10px; width: 280px;" oninput="filterUserDropdown()">
        <select id="userSelectDropdown" onchange="window.location.href='editUser.php?user_id=' + this.value;" style="padding: 6px 10px; min-width: 260px;">
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['user_id'] ?>" <?= ((int)$u['user_id'] === $selected_user_id) ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['displayname'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>) [<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>]
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- User Edit Form Card -->
    <?php if ($selectedUser): ?>
      <div class="card">
        <form method="post" action="editUser.php?user_id=<?= (int)$selectedUser['user_id'] ?>" accept-charset="UTF-8">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="action" value="edit_user">
          <input type="hidden" name="user_id" value="<?= (int)$selectedUser['user_id'] ?>">

          <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px;">
            <div style="font-size: 0.9em; color: #666;">
              Editing User <strong>#<?= (int)$selectedUser['user_id'] ?></strong>: <span style="color: #222; font-weight: bold;"><?= htmlspecialchars($selectedUser['displayname'], ENT_QUOTES, 'UTF-8') ?></span> (<code><?= htmlspecialchars($selectedUser['username'], ENT_QUOTES, 'UTF-8') ?></code>)
            </div>
          </div>

          <label for="username"><strong>Username:</strong></label>
          <br><input type="text" name="username" id="username" required maxlength="50" value="<?= htmlspecialchars($selectedUser['username'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; max-width: 400px; padding: 6px 8px;">
          <br><br>

          <label for="displayname"><strong>Display Name:</strong></label>
          <br><input type="text" name="displayname" id="displayname" required maxlength="100" value="<?= htmlspecialchars($selectedUser['displayname'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; max-width: 400px; padding: 6px 8px;">
          <br><br>

          <label for="email"><strong>Email address:</strong></label>
          <br><input type="email" name="email" id="email" required maxlength="50" value="<?= htmlspecialchars($selectedUser['email'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; max-width: 400px; padding: 6px 8px;">
          <br><br>

          <label for="initials"><strong>Initials (2-5 alphanumeric characters):</strong></label>
          <br><input type="text" name="initials" id="initials" required minlength="2" maxlength="5" pattern="[A-Za-z0-9]{2,5}" title="2 to 5 alphanumeric characters" value="<?= htmlspecialchars($selectedUser['initials'], ENT_QUOTES, 'UTF-8') ?>" style="width: 120px; text-transform: uppercase; padding: 6px 8px;">
          <br><br>

          <label for="role"><strong>User Role:</strong></label><br>
          <?php if ((int)$selectedUser['user_id'] === 1): ?>
            <input type="hidden" name="role" value="admin">
            <input type="text" value="Administrator (Root Superadmin - Locked)" disabled style="width: 100%; max-width: 400px; padding: 6px 8px; background: #e9ecef; color: #495057;">
            <div style="font-size: 0.85em; color: #666; margin-top: 4px;">User #1 is the permanent root administrator and cannot have their role changed.</div>
          <?php else: ?>
            <select name="role" id="role" style="padding: 6px 10px; width: 100%; max-width: 400px;">
              <?php foreach ($allowedRoles as $r): ?>
                <option value="<?= htmlspecialchars($r['role_name'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedUser['role'] === $r['role_name'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <br><br>

          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
            <input type="checkbox" name="email_notifications" id="email_notifications" value="1" <?= (!empty($selectedUser['email_notifications'])) ? 'checked' : '' ?>>
            <span><strong>Receive email notifications</strong> (for discussion comments and wine replies)</span>
          </label>
          <br>

          <!-- Password Reset Sub-section -->
          <div style="background: #fafbfc; border: 1px solid #d0d7de; border-radius: 6px; padding: 18px; margin-top: 15px; margin-bottom: 25px;">
            <h4 style="margin: 0 0 8px 0; color: #24292f; display: flex; align-items: center; gap: 6px;">
              <span>🔑</span> Reset User Password
            </h4>
            <p style="font-size: 0.9em; color: #57606a; margin: 0 0 12px 0;">
              Leave this field blank to keep the user's existing password unchanged. Enter a new password or generate one below to reset.
            </p>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <input type="password" name="new_password" id="new_password" minlength="6" placeholder="Enter new password (optional)" style="padding: 7px 10px; width: 280px;">
              <button type="button" onclick="generateSecurePassword()" style="padding: 7px 14px; background: #0969da; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em;">
                🎲 Generate Password
              </button>
            </div>

            <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
              <input type="checkbox" id="showPassCheckbox" onclick="togglePasswordVisibility()">
              <label for="showPassCheckbox" style="font-size: 0.9em; cursor: pointer;">Show Password</label>
            </div>

            <div id="passwordNotice" style="display: none; margin-top: 12px; padding: 10px 12px; background: #ddf4ff; border: 1px solid #54aeff; border-radius: 4px; color: #0969da; font-size: 0.9em;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <strong>New Generated Password:</strong> <code id="generatedPasswordDisplay" style="font-size: 1.1em; font-weight: bold; background: white; padding: 2px 6px; border-radius: 3px; border: 1px solid #0969da;"></code>
                </div>
                <button type="button" onclick="copyGeneratedPassword()" style="padding: 4px 10px; font-size: 0.85em; background: #0969da; color: white; border: none; border-radius: 3px; cursor: pointer;">
                  📋 Copy
                </button>
              </div>
              <div style="margin-top: 4px; font-size: 0.85em; color: #57606a;">
                Make sure to copy and send this temporary password to the user, then click <strong>Save Changes</strong> below.
              </div>
            </div>
          </div>

          <div style="display: flex; gap: 10px; align-items: center;">
            <input type="submit" value="Save Changes" style="padding: 8px 20px; font-size: 1em; cursor: pointer;">
            <a href="editUser.php?user_id=<?= (int)$selectedUser['user_id'] ?>" style="text-decoration: none; padding: 8px 16px; background: #f0f0f0; color: #333; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95em;">Cancel</a>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="card">
        <p style="color: #666;">No users found in the database. <a href="addUser.php">Add a new user</a>.</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="column side">
    <div class="card">
      <h3>User Administration</h3>
      <p>As an administrator, you can manage user profiles, change display names, and reset forgotten passwords.</p>
      <hr>
      <h4>Security Backstops</h4>
      <ul style="padding-left: 18px; font-size: 0.9em; color: #444; line-height: 1.5;">
        <li><strong>Root Admin (#1)</strong>: Dominik Müller's account is permanently locked to the <code>admin</code> role.</li>
        <li><strong>Admin Role Safeguard</strong>: Non-admin users cannot be elevated to the <code>admin</code> role via the web interface.</li>
        <li><strong>Privilege Customization</strong>: Fine-grained permissions (such as allowing specific readers to post tasting notes) can be configured in <a href="managePrivileges.php">User & Role Privileges</a>.</li>
      </ul>
      <hr>
      <p style="margin-bottom: 0;">Need to add someone new? <br><a href="addUser.php">➕ Create new user account</a></p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
  // Cache user dropdown options for client-side search
  let allUserOptions = [];

  document.addEventListener("DOMContentLoaded", function() {
    const select = document.getElementById("userSelectDropdown");
    if (select) {
      for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        allUserOptions.push({
          value: opt.value,
          text: opt.textContent,
          selected: opt.selected
        });
      }
    }
  });

  function filterUserDropdown() {
    const query = document.getElementById("userSearchInput").value.toLowerCase().trim();
    const select = document.getElementById("userSelectDropdown");
    if (!select) return;

    const currentSelectedValue = select.value;
    select.innerHTML = "";

    const filtered = allUserOptions.filter(opt => opt.text.toLowerCase().includes(query));

    if (filtered.length === 0) {
      const emptyOpt = document.createElement("option");
      emptyOpt.textContent = "-- No matching users --";
      emptyOpt.disabled = true;
      select.appendChild(emptyOpt);
    } else {
      filtered.forEach(opt => {
        const newOpt = document.createElement("option");
        newOpt.value = opt.value;
        newOpt.textContent = opt.text;
        if (opt.value === currentSelectedValue) {
          newOpt.selected = true;
        }
        select.appendChild(newOpt);
      });
    }
  }

  function togglePasswordVisibility() {
    const passInput = document.getElementById("new_password");
    const checkbox = document.getElementById("showPassCheckbox");
    if (passInput) {
      passInput.type = checkbox.checked ? "text" : "password";
    }
  }

  function generateSecurePassword() {
    const chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*";
    let password = "";
    // Generate 12-character strong random password
    for (let i = 0; i < 12; i++) {
      const randIndex = Math.floor(Math.random() * chars.length);
      password += chars[randIndex];
    }

    const passInput = document.getElementById("new_password");
    const showCheck = document.getElementById("showPassCheckbox");
    const notice = document.getElementById("passwordNotice");
    const display = document.getElementById("generatedPasswordDisplay");

    if (passInput) {
      passInput.value = password;
      passInput.type = "text";
      if (showCheck) showCheck.checked = true;
    }

    if (notice && display) {
      display.textContent = password;
      notice.style.display = "block";
    }
  }

  function copyGeneratedPassword() {
    const display = document.getElementById("generatedPasswordDisplay");
    if (display && display.textContent) {
      navigator.clipboard.writeText(display.textContent).then(function() {
        alert("Password copied to clipboard!");
      }).catch(function() {
        // Fallback
        const passInput = document.getElementById("new_password");
        if (passInput) {
          passInput.select();
          document.execCommand("copy");
          alert("Password copied to clipboard!");
        }
      });
    }
  }
</script>
