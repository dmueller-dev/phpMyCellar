<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file
  require_once __DIR__ . '/../includes/init.php';

  // Check admin permission
  if (!hasPrivilege($conn, 'manage_privileges')) {
    header("Location: /backend/index.php");
    exit();
  }

  $csrf_token = generateCSRFToken();
  $error = "";
  $success = "";

  // Fetch all roles, categories, and users
  $allRoles = getAllRoles($conn);
  $categories = getAllPrivilegesByCategory($conn);

  // Get user list for user selector
  $userList = [];
  $uRes = $conn->query("SELECT user_id, username, displayname, initials, role FROM users ORDER BY displayname ASC, username ASC");
  if ($uRes) {
    while ($u = $uRes->fetch_assoc()) {
      $userList[] = $u;
    }
    $uRes->free_result();
  }

  // Active tab state
  $activeTab = $_GET['tab'] ?? 'user';
  if (!in_array($activeTab, ['user', 'role', 'create_role'])) {
    $activeTab = 'user';
  }

  // Selected user
  $selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($userList[0]['user_id']) ? (int)$userList[0]['user_id'] : 0);
  // Selected role
  $selectedRoleName = $_GET['role_name'] ?? 'public';

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      $error = "Security check failed. Please refresh the page and try again.";
    } else {
      $action = $_POST['action'] ?? '';

      if ($action === 'save_user_privileges') {
        $activeTab = 'user';
        $postUserId = (int)($_POST['user_id'] ?? 0);
        $selectedUserId = $postUserId;

        if ($postUserId === 1) {
          $error = "The root administrator account (user #1) has full immutable privileges and cannot be modified.";
        } else {
          $newRole = $_POST['user_role'] ?? '';
          $privilegeOverrides = $_POST['privilege_override'] ?? [];

          // 1. Update user role if valid
          if (!empty($newRole)) {
            if ($newRole === 'admin') {
              $error = "Backstop protection: Users cannot be elevated to the Administrator role via the interface.";
            } else {
              updateUserRole($conn, $postUserId, $newRole);
            }
          }

          // 2. Update user overrides
          if (empty($error)) {
            $updated = updateUserPrivilegeOverrides($conn, $postUserId, $privilegeOverrides);
            if ($updated) {
              $success = "User privileges and overrides updated successfully.";
            } else {
              $error = "Failed to update user privileges.";
            }
          }
        }
      } elseif ($action === 'reset_user_privileges') {
        $activeTab = 'user';
        $postUserId = (int)($_POST['user_id'] ?? 0);
        $selectedUserId = $postUserId;

        if ($postUserId === 1) {
          $error = "The root administrator account (user #1) cannot be modified.";
        } else {
          resetUserPrivilegeOverrides($conn, $postUserId);
          $success = "All custom overrides have been reset. User now inherits all permissions directly from their role.";
        }
      } elseif ($action === 'save_role_privileges') {
        $activeTab = 'role';
        $postRoleName = $_POST['role_name'] ?? '';
        $selectedRoleName = $postRoleName;
        $rolePrivs = $_POST['role_privileges'] ?? [];

        if ($postRoleName === 'admin') {
          $error = "The Administrator role inherently possesses all permissions and cannot be modified.";
        } else {
          $res = updateRolePrivileges($conn, $postRoleName, $rolePrivs);
          if ($res) {
            $success = "Privileges for role '" . htmlspecialchars($postRoleName, ENT_QUOTES, 'UTF-8') . "' have been updated.";
          } else {
            $error = "Failed to update role privileges.";
          }
        }
      } elseif ($action === 'create_role') {
        $activeTab = 'create_role';
        $newRoleCode = $_POST['role_code'] ?? '';
        $newDisplayName = $_POST['display_name'] ?? '';
        $newDescription = $_POST['description'] ?? '';

        if (empty($newRoleCode) || empty($newDisplayName)) {
          $error = "Please provide both a unique role code and display name.";
        } else {
          $res = createCustomRole($conn, $newRoleCode, $newDisplayName, $newDescription);
          if ($res) {
            $success = "New role '" . htmlspecialchars($newDisplayName, ENT_QUOTES, 'UTF-8') . "' created successfully.";
            // Refresh role list
            $allRoles = getAllRoles($conn);
            $selectedRoleName = $newRoleCode;
            $activeTab = 'role';
          } else {
            $error = "Failed to create role. Ensure the role code is unique and alphanumeric.";
          }
        }
      }
    }
  }

  // Fetch selected user data
  $selectedUserData = null;
  foreach ($userList as $u) {
    if ((int)$u['user_id'] === $selectedUserId) {
      $selectedUserData = $u;
      break;
    }
  }
  if (!$selectedUserData && !empty($userList)) {
    $selectedUserData = $userList[0];
    $selectedUserId = (int)$selectedUserData['user_id'];
  }

  $selectedUserRole = $selectedUserData['role'] ?? 'read';
  $userOverrides = $selectedUserData ? getUserPrivilegeOverrides($conn, $selectedUserId) : [];
  $userRolePrivileges = getRolePrivileges($conn, $selectedUserRole);
  $publicPrivileges = getRolePrivileges($conn, 'public');

  // Fetch selected role privileges
  $selectedRolePrivileges = getRolePrivileges($conn, $selectedRoleName);

  $page_title = "Dominik Mueller - Manage Privileges & Roles";
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h2>User & Role Privileges Management</h2>
      <p>Configure role-based permissions, public visibility settings, and individual user overrides.</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="card" style="background-color: #fee; border-left: 5px solid #d9534f; color: #a94442;">
        <strong>Error:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="card" style="background-color: #efe; border-left: 5px solid #5cb85c; color: #3c763d;">
        <strong>Success:</strong> <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="card" style="padding-bottom: 0;">
      <div style="display: flex; gap: 10px; border-bottom: 1px solid #ddd;">
        <a href="?tab=user&user_id=<?= $selectedUserId ?>" class="filter-nav" style="margin-bottom: -1px; text-decoration: none; padding: 8px 16px; border-radius: 4px 4px 0 0; font-weight: bold; <?= $activeTab === 'user' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;' ?>">
          👤 Individual User Privileges
        </a>
        <a href="?tab=role&role_name=<?= urlencode($selectedRoleName) ?>" class="filter-nav" style="margin-bottom: -1px; text-decoration: none; padding: 8px 16px; border-radius: 4px 4px 0 0; font-weight: bold; <?= $activeTab === 'role' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;' ?>">
          🛡️ Role Privileges & Public Settings
        </a>
        <a href="?tab=create_role" class="filter-nav" style="margin-bottom: -1px; text-decoration: none; padding: 8px 16px; border-radius: 4px 4px 0 0; font-weight: bold; <?= $activeTab === 'create_role' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;' ?>">
          ➕ Create Custom Role
        </a>
      </div>
    </div>

    <?php if ($activeTab === 'user'): ?>
      <!-- ================= TAB 1: USER PRIVILEGES & OVERRIDES ================= -->
      <div class="card">
        <h3>Select User</h3>
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
          <input type="text" id="userSearchInput" placeholder="Filter users by name or username..." style="padding: 6px 10px; width: 280px;" oninput="filterUserDropdown()">
          <select id="userSelectDropdown" onchange="window.location.href='?tab=user&user_id=' + this.value;" style="padding: 6px 10px; min-width: 250px;">
            <?php foreach ($userList as $u): ?>
              <option value="<?= (int)$u['user_id'] ?>" <?= (int)$u['user_id'] === $selectedUserId ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['displayname'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>) [<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>]
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($selectedUserData): ?>
          <form method="post" action="?tab=user&user_id=<?= $selectedUserId ?>" style="margin-top: 15px;">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="save_user_privileges">
            <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">

            <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                  <h4 style="margin: 0; font-size: 1.1em;"><?= htmlspecialchars($selectedUserData['displayname'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($selectedUserData['username'], ENT_QUOTES, 'UTF-8') ?>)</h4>
                  <div style="color: #666; font-size: 0.9em; margin-top: 4px;">
                    User ID: #<?= (int)$selectedUserData['user_id'] ?> | Initials: <?= htmlspecialchars($selectedUserData['initials'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                    | <a href="/backend/editUser.php?user_id=<?= $selectedUserId ?>" style="text-decoration: none; color: #0969da; font-weight: 500;">✏️ Edit Profile & Reset Password</a>
                  </div>
                </div>
                <div>
                  <?php if ($selectedUserId === 1): ?>
                    <span style="background: #28a745; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold;">Root Administrator (Permanent Full Access)</span>
                  <?php else: ?>
                    <label for="user_role" style="font-weight: bold; margin-right: 5px;">Base Role:</label>
                    <select name="user_role" id="user_role" style="padding: 5px 8px;">
                      <?php foreach ($allRoles as $r): ?>
                        <?php if ($r['role_name'] === 'public') continue; ?>
                        <?php if ($r['role_name'] === 'admin' && $selectedUserData['role'] !== 'admin') continue; ?>
                        <option value="<?= htmlspecialchars($r['role_name'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedUserData['role'] === $r['role_name'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <?php if ($selectedUserId === 1): ?>
              <div style="padding: 15px; background: #e8f4fd; border: 1px solid #b8daff; border-radius: 4px; color: #004085; margin-bottom: 20px;">
                ℹ️ User #1 is the system superadministrator. All permissions are permanently granted and cannot be revoked.
              </div>
            <?php else: ?>
              <p style="font-size: 0.95em; color: #555;">
                Permissions follow a 3-state hierarchy:
                <br>• <strong>Inherit from Role (Default)</strong>: Adopts the permission granted or denied to the user's base role.
                <br>• <strong>Explicitly Grant (Allow)</strong>: Overrides the role and grants the right to this specific user.
                <br>• <strong>Explicitly Deny (Revoke)</strong>: Overrides the role and strips the right from this specific user.
              </p>

              <?php foreach ($categories as $categoryName => $privList): ?>
                <div style="margin-top: 25px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden;">
                  <div style="background: #eaedf1; padding: 10px 15px; font-weight: bold; font-size: 1em; border-bottom: 1px solid #ddd;">
                    📁 <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                      <tr style="background: #fdfdfd; border-bottom: 1px solid #eee; font-size: 0.85em; color: #555; text-align: left;">
                        <th style="padding: 8px 12px; width: 35%;">Privilege</th>
                        <th style="padding: 8px 12px; width: 20%;">Role Default</th>
                        <th style="padding: 8px 12px; width: 30%;">User Override</th>
                        <th style="padding: 8px 12px; width: 15%;">Effective Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($privList as $p): ?>
                        <?php
                          $code = $p['privilege_code'];
                          $isAdminOnly = (int)$p['is_admin_only'] === 1;
                          $roleHasIt = in_array($code, $userRolePrivileges) || in_array($code, $publicPrivileges);
                          $overrideVal = isset($userOverrides[$code]) ? (int)$userOverrides[$code] : 'inherit';

                          $effectiveAllowed = false;
                          if ($isAdminOnly) {
                            $effectiveAllowed = ($selectedUserData['role'] === 'admin');
                          } elseif ($overrideVal === 1) {
                            $effectiveAllowed = true;
                          } elseif ($overrideVal === 0) {
                            $effectiveAllowed = false;
                          } else {
                            $effectiveAllowed = $roleHasIt;
                          }
                        ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                          <td style="padding: 10px 12px; vertical-align: top;">
                            <div style="font-weight: bold; color: #333;"><?= htmlspecialchars($p['privilege_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div style="font-size: 0.8em; color: #777;"><?= htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8') ?></div>
                          </td>
                          <td style="padding: 10px 12px; vertical-align: middle;">
                            <?php if ($roleHasIt): ?>
                              <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">✓ Allowed by Role</span>
                            <?php else: ?>
                              <span style="background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">✗ Denied by Role</span>
                            <?php endif; ?>
                          </td>
                          <td style="padding: 10px 12px; vertical-align: middle;">
                            <?php if ($isAdminOnly): ?>
                              <span style="color: #999; font-size: 0.85em; font-style: italic;">🔒 Admin Backstop Protected</span>
                            <?php else: ?>
                              <select name="privilege_override[<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>]" style="padding: 4px 8px; font-size: 0.9em;">
                                <option value="inherit" <?= $overrideVal === 'inherit' ? 'selected' : '' ?>>Inherit (Default)</option>
                                <option value="grant" <?= $overrideVal === 1 ? 'selected' : '' ?>>Explicitly Grant (Allow)</option>
                                <option value="deny" <?= $overrideVal === 0 ? 'selected' : '' ?>>Explicitly Deny (Revoke)</option>
                              </select>
                            <?php endif; ?>
                          </td>
                          <td style="padding: 10px 12px; vertical-align: middle;">
                            <?php if ($effectiveAllowed): ?>
                              <strong style="color: #28a745;">✅ ALLOWED</strong>
                            <?php else: ?>
                              <strong style="color: #dc3545;">⛔ DENIED</strong>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endforeach; ?>

              <div style="margin-top: 20px; display: flex; gap: 15px; align-items: center;">
                <button type="submit" style="padding: 8px 20px; font-weight: bold; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                  💾 Save User Privileges
                </button>
                <button type="submit" name="action" value="reset_user_privileges" onclick="return confirm('Reset all custom privilege overrides for this user to inherit from role?');" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                  🔄 Reset to Role Defaults
                </button>
              </div>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>

    <?php elseif ($activeTab === 'role'): ?>
      <!-- ================= TAB 2: ROLE PRIVILEGES & PUBLIC SETTINGS ================= -->
      <div class="card">
        <h3>Select Role</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
          <?php foreach ($allRoles as $r): ?>
            <a href="?tab=role&role_name=<?= urlencode($r['role_name']) ?>" class="filter-nav" style="text-decoration: none; padding: 6px 14px; border-radius: 4px; <?= $selectedRoleName === $r['role_name'] ? 'background: #007bff; color: white; font-weight: bold;' : 'background: #f1f1f1; color: #333;' ?>">
              <?= htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') ?>
              <span style="font-size: 0.8em; opacity: 0.8;">(<?= (int)$r['user_count'] ?> users)</span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php
          $selectedRoleInfo = null;
          foreach ($allRoles as $r) {
            if ($r['role_name'] === $selectedRoleName) {
              $selectedRoleInfo = $r;
              break;
            }
          }
        ?>

        <?php if ($selectedRoleName === 'public'): ?>
          <div style="background: #e8f4fd; border: 1px solid #b8daff; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 6px 0; color: #004085;">🌐 Public / Unauthenticated Visitor Settings</h4>
            <p style="margin: 0; font-size: 0.95em; color: #004085;">
              Permissions granted to the <strong>Public</strong> role are accessible to <em>any visitor without logging in</em>.
              <br>For example, check <strong>View Tasting Notes & Vintages</strong> or <strong>Read Stories / Blog</strong> to make those content sections public!
            </p>
          </div>
        <?php elseif ($selectedRoleName === 'admin'): ?>
          <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; padding: 15px; margin-bottom: 20px; color: #155724;">
            <h4 style="margin: 0 0 6px 0;">🛡️ Administrator Role</h4>
            <p style="margin: 0; font-size: 0.95em;">
              The Administrator role has permanent, full system permissions across all features and cannot be restricted.
            </p>
          </div>
        <?php endif; ?>

        <form method="post" action="?tab=role&role_name=<?= urlencode($selectedRoleName) ?>">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="action" value="save_role_privileges">
          <input type="hidden" name="role_name" value="<?= htmlspecialchars($selectedRoleName, ENT_QUOTES, 'UTF-8') ?>">

          <?php foreach ($categories as $categoryName => $privList): ?>
            <div style="margin-top: 25px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden;">
              <div style="background: #eaedf1; padding: 10px 15px; font-weight: bold; font-size: 1em; border-bottom: 1px solid #ddd;">
                📁 <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>
              </div>
              <table style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr style="background: #fdfdfd; border-bottom: 1px solid #eee; font-size: 0.85em; color: #555; text-align: left;">
                    <th style="padding: 8px 12px; width: 10%;">Granted</th>
                    <th style="padding: 8px 12px; width: 35%;">Privilege</th>
                    <th style="padding: 8px 12px; width: 55%;">Description</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($privList as $p): ?>
                    <?php
                      $code = $p['privilege_code'];
                      $isAdminOnly = (int)$p['is_admin_only'] === 1;
                      $isGranted = in_array($code, $selectedRolePrivileges) || $selectedRoleName === 'admin';
                      $isDisabled = ($selectedRoleName === 'admin') || ($isAdminOnly && $selectedRoleName !== 'admin');
                    ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                      <td style="padding: 10px 12px; text-align: center; vertical-align: middle;">
                        <input type="checkbox" name="role_privileges[]" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $isGranted ? 'checked' : '' ?> <?= $isDisabled ? 'disabled' : '' ?> style="transform: scale(1.2);">
                      </td>
                      <td style="padding: 10px 12px; vertical-align: top;">
                        <div style="font-weight: bold; color: #333;"><?= htmlspecialchars($p['privilege_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="font-size: 0.8em; color: #888;"><code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code></div>
                      </td>
                      <td style="padding: 10px 12px; vertical-align: middle; font-size: 0.9em; color: #555;">
                        <?= htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($isAdminOnly): ?>
                          <span style="display: block; color: #d9534f; font-size: 0.8em; margin-top: 2px;">🔒 Administrator Only (Backstop Protected)</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>

          <?php if ($selectedRoleName !== 'admin'): ?>
            <div style="margin-top: 20px;">
              <button type="submit" style="padding: 8px 20px; font-weight: bold; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                💾 Save Role Privileges
              </button>
            </div>
          <?php endif; ?>
        </form>
      </div>

    <?php elseif ($activeTab === 'create_role'): ?>
      <!-- ================= TAB 3: CREATE CUSTOM ROLE ================= -->
      <div class="card">
        <h3>Create a New Custom Role</h3>
        <p>Define a new role and configure its privileges in the Role Privileges tab.</p>

        <form method="post" action="?tab=create_role" style="max-width: 500px; margin-top: 15px;">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="action" value="create_role">

          <div style="margin-bottom: 15px;">
            <label for="role_code" style="font-weight: bold; display: block; margin-bottom: 5px;">Role Code (ID):</label>
            <input type="text" id="role_code" name="role_code" required pattern="[a-z0-9_]{2,30}" placeholder="e.g. sommelier, editor" style="width: 100%; padding: 6px 10px;">
            <small style="color: #666;">Lowercase letters, numbers, and underscores only.</small>
          </div>

          <div style="margin-bottom: 15px;">
            <label for="display_name" style="font-weight: bold; display: block; margin-bottom: 5px;">Display Name:</label>
            <input type="text" id="display_name" name="display_name" required placeholder="e.g. Sommelier / Taster" style="width: 100%; padding: 6px 10px;">
          </div>

          <div style="margin-bottom: 15px;">
            <label for="description" style="font-weight: bold; display: block; margin-bottom: 5px;">Description:</label>
            <textarea id="description" name="description" rows="3" placeholder="Description of this role's intended purpose..." style="width: 100%; padding: 6px 10px;"></textarea>
          </div>

          <button type="submit" style="padding: 8px 20px; font-weight: bold; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            ➕ Create Role
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Privileges Architecture</h3>
      <p>
        <strong>1. Public Access:</strong> Unauthenticated users inherit rights from the <code>public</code> role.
      </p>
      <p>
        <strong>2. User Overrides:</strong> Overrides can grant or revoke any individual permission per user.
      </p>
      <p>
        <strong>3. Security Backstop:</strong> Sensitive administration tools (<code>manage_users</code>, <code>manage_privileges</code>) and the <code>admin</code> role are strictly locked and protected against escalation.
      </p>
    </div>
  </div>
</div>

<script>
function filterUserDropdown() {
  const filter = document.getElementById('userSearchInput').value.toLowerCase();
  const select = document.getElementById('userSelectDropdown');
  const options = select.options;
  for (let i = 0; i < options.length; i++) {
    const text = options[i].text.toLowerCase();
    if (text.indexOf(filter) > -1) {
      options[i].style.display = "";
    } else {
      options[i].style.display = "none";
    }
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
