<?php
// Define a constant to protect included files from direct access
define('INCLUDED_VIA_APP', true);

// Include the initialization file (handles sessions and database connection)
require_once __DIR__ . '/includes/init.php';

// Include the database configuration file
global $mysqli, $conn;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

$user_id = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

$error = "";
$success = "";

// Handle actions (mark read, delete, mark all read, clear all)
if (isset($_GET['action'])) {
  try {
    $action = $_GET['action'];
    $action_token = $_GET['csrf_token'] ?? '';

    if (!validateCSRFToken($action_token)) {
      $error = "Security check failed. Please try again.";
    } else {
      if ($action === 'mark_read') {
        $notif_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        if ($stmt) {
          $stmt->bind_param("ii", $notif_id, $user_id);
          if ($stmt->execute()) {
            $success = "Notification marked as read.";
          }
          $stmt->close();
        } else {
          $error = "Failed to update notification. (Database schema not updated yet)";
        }
      } elseif ($action === 'delete') {
        $notif_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $stmt = $mysqli->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
        if ($stmt) {
          $stmt->bind_param("ii", $notif_id, $user_id);
          if ($stmt->execute()) {
            $success = "Notification deleted.";
          }
          $stmt->close();
        } else {
          $error = "Failed to delete notification. (Database schema not updated yet)";
        }
      } elseif ($action === 'mark_all_read') {
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        if ($stmt) {
          $stmt->bind_param("i", $user_id);
          if ($stmt->execute()) {
            $success = "All notifications marked as read.";
          }
          $stmt->close();
        } else {
          $error = "Failed to mark all as read. (Database schema not updated yet)";
        }
      } elseif ($action === 'clear_all') {
        $stmt = $mysqli->prepare("DELETE FROM notifications WHERE user_id = ?");
        if ($stmt) {
          $stmt->bind_param("i", $user_id);
          if ($stmt->execute()) {
            $success = "All notifications cleared.";
          }
          $stmt->close();
        } else {
          $error = "Failed to clear notifications. (Database schema not updated yet)";
        }
      }
      
      // Redirect to clear URL parameters and prevent double submission
      header("Location: /notifications.php");
      exit;
    }
  } catch (Throwable $e) {
    $error = "Action failed: " . htmlspecialchars($e->getMessage());
  }
}

// Fetch all notifications with commenter name, comment snippet, and wine/tasting note info
$sql = "SELECT 
          n.*, 
          u.displayname AS sender_name, 
          c.content,
          -- Wine Details
          w.vintage AS w_vintage,
          wm.name AS w_name,
          p.producer AS w_producer,
          -- Tasting Note Details
          tn_w.vintage AS tn_vintage,
          tn_wm.name AS tn_name,
          tn_p.producer AS tn_producer,
          -- Blog Details
          bp.title AS bp_title
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.user_id
        LEFT JOIN comments c ON n.comment_id = c.comment_id
        LEFT JOIN wines w ON n.item_type = 'wine' AND n.item_id = w.wine_id
        LEFT JOIN wines_master wm ON w.master_id = wm.master_id
        LEFT JOIN producers p ON wm.producer_id = p.producer_id
        LEFT JOIN tnotes tn ON n.item_type = 'tnote' AND n.item_id = tn.note_id
        LEFT JOIN wines tn_w ON tn.wine_id = tn_w.wine_id
        LEFT JOIN wines_master tn_wm ON tn_w.master_id = tn_wm.master_id
        LEFT JOIN producers tn_p ON tn_wm.producer_id = tn_p.producer_id
        LEFT JOIN blogposts bp ON n.item_type = 'blog' AND n.item_id = bp.blog_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC";

$stmt = null;
$result = null;
try {
  $stmt = $mysqli->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
  }
} catch (Throwable $e) {
  $error = "Unable to fetch notifications: " . htmlspecialchars($e->getMessage());
}

// Page header details
$page_title = 'Dominik Mueller - Notifications';
$meta_desc = 'Manage your tasting notes and wine comment notifications.';

require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main" style="width: 100%;">
    <div class="card">
      <div class="notifications-header">
        <h2 style="margin:0;">My Notifications</h2>
        <div>
          <a href="/notifications.php?action=mark_all_read&csrf_token=<?= $csrf_token ?>" class="btn-action btn-secondary" title="Mark all notifications as read">Mark all as read</a>
          <a href="/notifications.php?action=clear_all&csrf_token=<?= $csrf_token ?>" class="btn-action" onclick="return confirm('Are you sure you want to delete all notifications?');" title="Delete all notifications">Clear all</a>
        </div>
      </div>

      <?php
      if ($error != "") {
        echo "<div style='color:red; margin-bottom:15px;'>$error</div>";
      } elseif ($success != "") {
        echo "<div style='color:green; margin-bottom:15px;'>$success</div>";
      }
      ?>

      <?php
      if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $is_read = (int)$row['is_read'];
          $notif_id = $row['notification_id'];
          $sender_name = htmlspecialchars($row['sender_name'] ?? 'Someone', ENT_QUOTES, 'UTF-8');
          $item_id = $row['item_id'];
          $item_type = $row['item_type'];
          $comment_id = $row['comment_id'];
          
          // Formulate item name and direct link
          $item_name = "";
          $link_url = "";
          if ($item_type === 'wine') {
            $vintage = $row['w_vintage'] ?? 'NV';
            $producer = $row['w_producer'] ?? '';
            $name = $row['w_name'] ?? '';
            $item_name = trim("$vintage $producer $name");
            $link_url = "/wines.php?id=$item_id#comment-$comment_id";
          } elseif ($item_type === 'tnote') {
            $vintage = $row['tn_vintage'] ?? 'NV';
            $producer = $row['tn_producer'] ?? '';
            $name = $row['tn_name'] ?? '';
            $item_name = "Tasting note on " . trim("$vintage $producer $name");
            $link_url = "/tnotes.php?id=$item_id#comment-$comment_id";
          } elseif ($item_type === 'blog') {
            $title = $row['bp_title'] ?? '';
            $item_name = "Story: " . trim($title);
            $link_url = "/blog.php?id=$item_id#comment-$comment_id";
          }

          $created_at_dt = !empty($row['created_at']) ? date_create($row['created_at']) : false;
          $pub_time = $created_at_dt ? date_format($created_at_dt, "j F Y H:i") : 'N/A';
          $snippet = mb_strimwidth(strip_tags($row['content'] ?? ''), 0, 150, "...");
          $read_class = $is_read ? "read" : "";
          ?>
          <div class="notification-item <?= $read_class ?>">
            <div>
              <strong><?= $sender_name ?></strong> commented on 
              <a href="<?= htmlspecialchars($link_url) ?>" class="notification-link" onclick="markAsRead(<?= $notif_id ?>)"><?= htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <div class="notification-snippet">"<?= htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') ?>"</div>
            <div class="notification-meta"><?= $pub_time ?></div>
            <div class="notification-actions">
              <?php if (!$is_read): ?>
                <a href="/notifications.php?action=mark_read&id=<?= $notif_id ?>&csrf_token=<?= $csrf_token ?>" class="action-link" title="Mark notification as read">Mark as read</a>
              <?php endif; ?>
              <a href="/notifications.php?action=delete&id=<?= $notif_id ?>&csrf_token=<?= $csrf_token ?>" class="action-link" title="Delete notification" onclick="return confirm('Are you sure you want to delete this notification?');">Delete</a>
            </div>
          </div>
          <?php
        }
      } else {
        echo "<p style='padding: 20px 0; text-align: center; color: #64748b;'>You have no notifications. You're all caught up!</p>";
      }
      if ($stmt) {
        $stmt->close();
      }
      ?>
    </div>
  </div>
</div>

<script>
// Mark read automatically when clicking link via JS helper if desired
function markAsRead(id) {
  // Make a silent background call to mark read, so by the time they hit back, it is read
  const xhr = new XMLHttpRequest();
  xhr.open("GET", "/notifications.php?action=mark_read&id=" + id + "&csrf_token=<?= $csrf_token ?>", true);
  xhr.send();
}
</script>

<?php require_once 'includes/footer.php'; ?>
