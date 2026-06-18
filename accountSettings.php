<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Include the database configuration file
  global $mysqli, $conn;
  
  // Check if user is logged in
  if (!isset($_SESSION['user_id'])) {
    die("<h2>Access Denied</h2><p>You must be <a href='/login.php'>logged in</a> to change your password.</p>");
  } else {
    // --- FETCH USER DETAILS ---
    $user_id = $_SESSION['user_id'];
    try {
      $stmt = $mysqli->prepare("SELECT username, displayname, email, password, initials, email_notifications FROM users WHERE user_id = ?");
      if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($username, $displayname, $email, $hashed_password, $initials, $email_notifications);
        if (!$stmt->fetch()) {
          $stmt->close();
          die("<h2>User not found.</h2>");
        }
        $stmt->close();
      } else {
        throw new Exception("Unable to prepare statement");
      }
    } catch (Throwable $e) {
      // Fallback if email_notifications column doesn't exist yet
      try {
        $stmt = $mysqli->prepare("SELECT username, displayname, email, password, initials FROM users WHERE user_id = ?");
        if ($stmt) {
          $stmt->bind_param('i', $user_id);
          $stmt->execute();
          $stmt->bind_result($username, $displayname, $email, $hashed_password, $initials);
          if (!$stmt->fetch()) {
            $stmt->close();
            die("<h2>User not found.</h2>");
          }
          $stmt->close();
          $email_notifications = 0; // Default to 0 / off
        } else {
          die("<h2>Database Error</h2><p>Failed to prepare user query.</p>");
        }
      } catch (Throwable $ex) {
        die("<h2>Database Error</h2><p>Failed to query user details: " . htmlspecialchars($ex->getMessage()) . "</p>");
      }
    }
  }

  // Generate CSRF token for security
  $csrf_token = generateCSRFToken();

  // Initialize error and success messages
  $error = "";
  $success = "";
  $notif_error = "";
  $notif_success = "";

  // Process form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
      if (isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
          $notif_error = "Security check failed. Please try again.";
        } else {
          $email_notif = isset($_POST['email_notifications']) ? 1 : 0;
          $update = $mysqli->prepare("UPDATE users SET email_notifications = ? WHERE user_id = ?");
          if (!$update) {
            $notif_error = "Failed to update notification settings. (Database schema not updated yet)";
          } else {
            $update->bind_param('ii', $email_notif, $user_id);
            if ($update->execute()) {
              $notif_success = "Notification preferences updated successfully!";
              $email_notifications = $email_notif; // Update local variable for page display
            } else {
              $notif_error = "Failed to update notification settings.";
            }
            $update->close();
          }
        }
      } elseif (isset($_POST['action']) && $_POST['action'] === 'unsubscribe_all') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
          $notif_error = "Security check failed. Please try again.";
        } else {
          $delete = $mysqli->prepare("DELETE FROM subscriptions WHERE user_id = ?");
          if (!$delete) {
            $notif_error = "Failed to unsubscribe. (Database schema not updated yet)";
          } else {
            $delete->bind_param('i', $user_id);
            if ($delete->execute()) {
              $notif_success = "Unsubscribed from all discussions.";
            } else {
              $notif_error = "Failed to unsubscribe. Please try again.";
            }
            $delete->close();
          }
        }
      } elseif (isset($_POST['action']) && $_POST['action'] === 'unsubscribe_single') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
          $notif_error = "Security check failed. Please try again.";
        } else {
          $item_id = isset($_POST['sub_item_id']) ? (int)$_POST['sub_item_id'] : 0;
          $item_type = $_POST['sub_item_type'] ?? '';
          $delete = $mysqli->prepare("DELETE FROM subscriptions WHERE user_id = ? AND item_id = ? AND item_type = ?");
          if (!$delete) {
            $notif_error = "Failed to unsubscribe. (Database schema not updated yet)";
          } else {
            $delete->bind_param('iis', $user_id, $item_id, $item_type);
            if ($delete->execute()) {
              $notif_success = "Successfully unsubscribed from discussion.";
            } else {
              $notif_error = "Failed to unsubscribe. Please try again.";
            }
            $delete->close();
          }
        }
      } else {
        // Password change form
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
          $error = "Security check failed. Please try again.";
        } else {
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
          }
        }
      }
    } catch (Throwable $e) {
      $error = "An error occurred: " . htmlspecialchars($e->getMessage());
      $notif_error = "An error occurred: " . htmlspecialchars($e->getMessage());
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
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <label for="username">Username:</label>
        <input type="text" id="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <br><br>
        <label for="email">Email:</label>
        <input type="email" id="email" value="<?= htmlspecialchars($email) ?>" disabled readonly>
        <br><br>
        <label for="displayname">Full name:</label>
        <input type="text" id="displayname" value="<?= htmlspecialchars($displayname, ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <br><br>
        <label for="initials">Initials:</label>
        <input type="text" id="initials" value="<?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <p>If you like to change your email address, full display name or initials, please contact me via the details below.</p>
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

    <!-- Notification Preferences Card -->
    <div class="card">
      <h3>Notification Preferences</h3>
      <?php
        if ($notif_error != "") {
          echo "<div style='color:red; margin-bottom:10px;'>$notif_error</div>";
        } elseif ($notif_success != "") {
          echo "<div style='color:green; margin-bottom:10px;'>$notif_success</div>";
        }
      ?>
      <form method="post" autocomplete="off" accept-charset="UTF-8">
        <input type="hidden" name="action" value="update_notifications">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
          <input type="checkbox" name="email_notifications" value="1" <?= $email_notifications == 1 ? 'checked' : '' ?>>
          Receive email alerts when there is a new comment on items I follow
        </label>
        <br>
        <button type="submit">Save Notification Preferences</button>
      </form>
    </div>

    <!-- Active Subscriptions Management Card -->
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h3 style="margin:0;">My Subscriptions</h3>
        <?php
          // Check if there are subscriptions before showing the Unsubscribe All button
          $sub_count = 0;
          try {
            $check_sub = $mysqli->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ?");
            if ($check_sub) {
              $check_sub->bind_param("i", $user_id);
              $check_sub->execute();
              $check_sub->bind_result($sub_count);
              $check_sub->fetch();
              $check_sub->close();
            }
          } catch (Throwable $e) {
            $sub_count = 0;
          }
          
          if ($sub_count > 0):
        ?>
          <form method="post" style="margin:0;" onsubmit="return confirm('Are you sure you want to unsubscribe from all discussions?');">
            <input type="hidden" name="action" value="unsubscribe_all">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <button type="submit" class="btn-action">Unsubscribe from all</button>
          </form>
        <?php endif; ?>
      </div>
      
      <?php
      // Fetch all subscriptions for rendering in settings
      $sub_sql = "SELECT 
                    s.item_id, 
                    s.item_type, 
                    s.created_at,
                    -- Wine details
                    w.vintage AS w_vintage,
                    wm.name AS w_name,
                    p.producer AS w_producer,
                    -- Tasting Note details
                    tn_w.vintage AS tn_vintage,
                    tn_wm.name AS tn_name,
                    tn_p.producer AS tn_producer,
                    -- Blog details
                    bp.title AS bp_title
                  FROM subscriptions s
                  LEFT JOIN wines w ON s.item_type = 'wine' AND s.item_id = w.wine_id
                  LEFT JOIN wines_master wm ON w.master_id = wm.master_id
                  LEFT JOIN producers p ON wm.producer_id = p.producer_id
                  LEFT JOIN tnotes tn ON s.item_type = 'tnote' AND s.item_id = tn.note_id
                  LEFT JOIN wines tn_w ON tn.wine_id = tn_w.wine_id
                  LEFT JOIN wines_master tn_wm ON tn_w.master_id = tn_wm.master_id
                  LEFT JOIN producers tn_p ON tn_wm.producer_id = tn_p.producer_id
                  LEFT JOIN blogposts bp ON s.item_type = 'blog' AND s.item_id = bp.blog_id
                  WHERE s.user_id = ?
                  ORDER BY s.created_at DESC";

      $sub_result = null;
      try {
        $stmt = $mysqli->prepare($sub_sql);
        if ($stmt) {
          $stmt->bind_param("i", $user_id);
          $stmt->execute();
          $sub_result = $stmt->get_result();
        }
      } catch (Throwable $e) {
        $notif_error = "Unable to fetch subscriptions: " . htmlspecialchars($e->getMessage());
      }


      if ($sub_result && $sub_result->num_rows > 0):
      ?>
        <!-- Instant Search Box -->
        <div style="margin: 15px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <input type="text" id="subSearchBox" onkeyup="filterSubscriptions()" 
                 placeholder="🔍 Search subscriptions by topic, type, or date..." 
                 style="padding: 8px 12px; font-family: Georgia, serif; border: 1px solid #ccc; border-radius: 4px; max-width: 350px; flex: 1; font-size: 14px;">
        </div>

        <table class="settings-table" id="subsTable">
          <thead>
            <tr>
              <th>Discussion Topic</th>
              <th>Type</th>
              <th>Followed Since</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            while ($sub_row = $sub_result->fetch_assoc()) {
              $item_id = $sub_row['item_id'];
              $item_type = $sub_row['item_type'];
              $created_at_dt = !empty($sub_row['created_at']) ? date_create($sub_row['created_at']) : false;
              $sub_date = $created_at_dt ? date_format($created_at_dt, "j M Y") : 'N/A';
              
              $item_name = "";
              $item_url = "";
              if ($item_type === 'wine') {
                $vintage = $sub_row['w_vintage'] ?? 'NV';
                $producer = $sub_row['w_producer'] ?? '';
                $name = $sub_row['w_name'] ?? '';
                $item_name = trim("$vintage $producer $name");
                $item_url = "/wine.php?id=$item_id";
              } elseif ($item_type === 'tnote') {
                $vintage = $sub_row['tn_vintage'] ?? 'NV';
                $producer = $sub_row['tn_producer'] ?? '';
                $name = $sub_row['tn_name'] ?? '';
                $item_name = "Tasting note on " . trim("$vintage $producer $name");
                $item_url = "/tnote.php?id=$item_id";
              } elseif ($item_type === 'blog') {
                $title = $sub_row['bp_title'] ?? '';
                $item_name = "Story: " . trim($title);
                $item_url = "/blogpost.php?id=$item_id";
              }

              $display_type = "";
              if ($item_type === 'wine') {
                $display_type = "Wine";
              } elseif ($item_type === 'tnote') {
                $display_type = "Tasting Note";
              } elseif ($item_type === 'blog') {
                $display_type = "Story";
              }
              ?>
              <tr class="sub-row" 
                  data-name="<?= htmlspecialchars(strtolower($item_name), ENT_QUOTES, 'UTF-8') ?>" 
                  data-type="<?= htmlspecialchars(strtolower($display_type), ENT_QUOTES, 'UTF-8') ?>" 
                  data-date="<?= htmlspecialchars(strtolower($sub_date), ENT_QUOTES, 'UTF-8') ?>">
                <td><a href="<?= htmlspecialchars($item_url) ?>"><?= htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') ?></a></td>
                <td><?= $display_type ?></td>
                <td><?= $sub_date ?></td>
                <td>
                  <form method="post" style="margin:0; padding:0;" onsubmit="return confirm('Are you sure you want to unsubscribe from this discussion?');">
                    <input type="hidden" name="action" value="unsubscribe_single">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="sub_item_id" value="<?= $item_id ?>">
                    <input type="hidden" name="sub_item_type" value="<?= $item_type ?>">
                    <button type="submit" class="btn-action btn-secondary" style="padding:4px 8px; font-size:11px;">Unsubscribe</button>
                  </form>
                </td>
              </tr>
              <?php
            }
            ?>
          </tbody>
        </table>

        <!-- Dynamic Pagination Controls -->
        <div id="subPagination" style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: small; color: #64748b;">
          <div id="paginationInfo">Showing 0-0 of 0 subscriptions</div>
          <div style="display: flex; gap: 5px;">
            <button id="btnPrevSub" class="btn-action btn-secondary" onclick="prevSubPage()" style="padding: 4px 10px; font-size: 12px;">Previous</button>
            <button id="btnNextSub" class="btn-action btn-secondary" onclick="nextSubPage()" style="padding: 4px 10px; font-size: 12px;">Next</button>
          </div>
        </div>
      <?php else: ?>
        <p style="color:#64748b; margin-top:15px; margin-bottom:0;">You are not currently subscribed to any discussions.</p>
      <?php
      endif;
      if ($stmt) {
        $stmt->close();
      }
      ?>
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

  // Active Subscriptions Pagination and Filter Controller
  let currentSubPage = 1;
  const subsPerPage = 20;
  let filteredRows = [];

  function initSubscriptionsPagination() {
    const table = document.getElementById('subsTable');
    if (!table) return;
    
    const rows = Array.from(table.querySelectorAll('.sub-row'));
    filteredRows = rows;
    
    showSubPage(1);
  }

  function showSubPage(page) {
    const table = document.getElementById('subsTable');
    if (!table) return;
    
    const totalRows = filteredRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / subsPerPage));
    
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentSubPage = page;
    
    const allRows = table.querySelectorAll('.sub-row');
    allRows.forEach(row => row.style.display = 'none');
    
    const startIndex = (currentSubPage - 1) * subsPerPage;
    const endIndex = Math.min(startIndex + subsPerPage, totalRows);
    
    for (let i = startIndex; i < endIndex; i++) {
      filteredRows[i].style.display = '';
    }
    
    const info = document.getElementById('paginationInfo');
    if (info) {
      if (totalRows === 0) {
        info.textContent = 'No matching subscriptions';
      } else {
        info.textContent = `Showing ${startIndex + 1}–${endIndex} of ${totalRows} subscriptions`;
      }
    }
    
    const btnPrev = document.getElementById('btnPrevSub');
    const btnNext = document.getElementById('btnNextSub');
    if (btnPrev) btnPrev.disabled = (currentSubPage === 1 || totalRows === 0);
    if (btnNext) btnNext.disabled = (currentSubPage === totalPages || totalRows === 0);
  }

  function prevSubPage() {
    if (currentSubPage > 1) {
      showSubPage(currentSubPage - 1);
    }
  }

  function nextSubPage() {
    const totalPages = Math.ceil(filteredRows.length / subsPerPage);
    if (currentSubPage < totalPages) {
      showSubPage(currentSubPage + 1);
    }
  }

  function filterSubscriptions() {
    const query = document.getElementById('subSearchBox').value.toLowerCase().trim();
    const table = document.getElementById('subsTable');
    if (!table) return;
    
    const allRows = Array.from(table.querySelectorAll('.sub-row'));
    
    if (query === '') {
      filteredRows = allRows;
    } else {
      filteredRows = allRows.filter(row => {
        const name = row.getAttribute('data-name') || '';
        const type = row.getAttribute('data-type') || '';
        const date = row.getAttribute('data-date') || '';
        return name.includes(query) || type.includes(query) || date.includes(query);
      });
    }
    
    showSubPage(1);
  }

  document.addEventListener('DOMContentLoaded', initSubscriptionsPagination);
</script>
