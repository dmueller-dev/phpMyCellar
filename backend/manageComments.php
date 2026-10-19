<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Site-wide comment moderation, status management, and spam control.
   *
   * Post-Moderation Architecture:
   * phpMyCellar employs a post-moderation model. Comments submitted by authenticated
   * members with the 'post_comments' privilege publish immediately with status 'approved'
   * (the database schema default). This interface serves as a retrospective moderation
   * dashboard enabling administrators and moderators to review live discussion threads,
   * correct typographical or inappropriate content, flag spam, or permanently delete
   * comments across stories, tasting notes, and wines.
   *
   * Required privilege: 'moderate_comments'
   * Note: Fallback to 'manage_privileges' is deprecated in 1.1.0 and scheduled for removal in v2.0.0.
   */
  if (!hasPrivilege($conn, 'moderate_comments') && !hasPrivilege($conn, 'manage_privileges')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Filter params
  $statusFilter = $_GET['status'] ?? 'all';
  if (!in_array($statusFilter, ['all', 'approved', 'pending', 'spam'], true)) {
    $statusFilter = 'all';
  }

  $typeFilter = $_GET['type'] ?? 'all';
  if (!in_array($typeFilter, ['all', 'blog', 'tnote', 'wine'], true)) {
    $typeFilter = 'all';
  }

  $search = trim($_GET['q'] ?? '');

  // Pagination
  $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
  if ($page < 1) $page = 1;
  $limit = 30;
  $offset = ($page - 1) * $limit;

  // Selected comment for editing
  $editCommentId = filter_input(INPUT_GET, 'edit_comment', FILTER_VALIDATE_INT);
  $editingComment = null;
  if ($editCommentId) {
    try {
      // Transitional schema introspection fallback for 1.x; scheduled for removal in v2.0.0.
      $meta = getCommentsTableMetadata($conn);
      $contentCol = $meta['content_col'];
      $statusSelect = $meta['has_status'] ? "status" : "'approved' AS status";
      $chk = $conn->prepare("SELECT comment_id, {$contentCol} AS comment, {$statusSelect} FROM comments WHERE comment_id = ?");
      if ($chk) {
        $chk->bind_param("i", $editCommentId);
        $chk->execute();
        $res = $chk->get_result();
        $editingComment = ($res && $r = $res->fetch_assoc()) ? $r : null;
        $chk->close();
      }
    } catch (Throwable $e) {
      error_log("manageComments edit lookup error: " . $e->getMessage());
    }
    if (!$editingComment) {
      $errors[] = "Comment not found.";
      $editCommentId = null;
    }
  }

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);

    // Action: Update Status
    if ($action === 'set_status') {
      $new_status = sanitizeInput($_POST['status'] ?? '');
      try {
        if ($comment_id && updateCommentStatus($conn, $comment_id, $new_status)) {
          $success_message = "Comment #" . $comment_id . " status updated to '" . htmlspecialchars($new_status, ENT_QUOTES, 'UTF-8') . "'.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Edit Comment Text
    elseif ($action === 'edit_comment_text') {
      $comment_text = sanitizeInput($_POST['comment'] ?? '');
      try {
        if ($comment_id && updateCommentText($conn, $comment_id, $comment_text)) {
          $success_message = "Comment #" . $comment_id . " text updated successfully.";
          $editCommentId = null;
          $editingComment = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Delete Comment
    elseif ($action === 'delete_comment') {
      try {
        if ($comment_id && deleteComment($conn, $comment_id)) {
          $success_message = "Comment #" . $comment_id . " deleted successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
  }

  // Fetch comments and counts
  $comments = getBackendComments($conn, $statusFilter, $typeFilter, $search, $limit, $offset);
  $totalCount = getBackendCommentsCount($conn, $statusFilter, $typeFilter, $search);
  $totalPages = ceil($totalCount / $limit);

  // Overall metrics
  $allCount = getBackendCommentsCount($conn, 'all');
  $approvedCount = getBackendCommentsCount($conn, 'approved');
  $pendingCount = getBackendCommentsCount($conn, 'pending');
  $spamCount = getBackendCommentsCount($conn, 'spam');

  $csrf_token = generateCSRFToken();
  $page_title = 'Comment Moderation - Administration';
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
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #f0e6e6; padding-bottom: 12px; margin-bottom: 16px;">
        <h2 style="margin: 0; color: var(--secondary-accent, firebrick);">
          &#128172; Comment Moderation &amp; Management
        </h2>
      </div>

      <p style="color: #666; font-size: 0.88rem; margin-top: 0; margin-bottom: 20px; line-height: 1.4;">
        phpMyCellar operates on a <strong>post-moderation</strong> model: comments submitted by registered members publish immediately. Use this dashboard to retrospectively review discussions, edit inappropriate wording, flag spam, or remove comments across tasting notes, stories, and wines.
      </p>

      <?php if (!empty($errors)): ?>
        <div style="background-color: #ffdddd; border-left: 5px solid #f44336; padding: 10px 15px; margin-bottom: 20px;">
          <ul style="margin: 0; padding-left: 20px; color: #a00;">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 10px 15px; margin-bottom: 20px; color: #2e7d32;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <!-- Edit Modal / Inline Card -->
      <?php if ($editingComment): ?>
        <div style="background: #fff9e6; border: 1px solid #ffe082; border-radius: 6px; padding: 16px; margin-bottom: 24px;">
          <h3 style="margin-top: 0; color: #795548;">Edit Comment #<?php echo (int)$editingComment['comment_id']; ?></h3>
          <form action="manageComments.php?<?php echo http_build_query($_GET); ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="edit_comment_text">
            <input type="hidden" name="comment_id" value="<?php echo (int)$editingComment['comment_id']; ?>">

            <div style="margin-bottom: 12px;">
              <label for="comment_edit_text" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Comment Content:</label>
              <textarea id="comment_edit_text" name="comment" rows="4" required style="width: 100%; padding: 8px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editingComment['comment'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div style="display: flex; gap: 8px;">
              <button type="submit" class="btn-action" style="padding: 7px 16px; font-size: 0.88rem;">
                Save Changes
              </button>
              <?php 
                $cancelQuery = $_GET;
                unset($cancelQuery['edit_comment']);
              ?>
              <a href="manageComments.php?<?php echo http_build_query($cancelQuery); ?>" class="btn-action btn-secondary" style="text-decoration: none; padding: 7px 14px; font-size: 0.88rem;">
                Cancel
              </a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- KPI Summary -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($allCount); ?></span>
            <span class="kpi-icon">&#128172;</span>
          </div>
          <div class="kpi-label">Total Comments</div>
          <div class="kpi-sub">
            <span>Across all discussions</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($approvedCount); ?></span>
            <span class="kpi-icon">&#9989;</span>
          </div>
          <div class="kpi-label">Approved</div>
          <div class="kpi-sub">
            <span>Visible publicly or to members</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-gold" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($pendingCount); ?></span>
            <span class="kpi-icon">&#9203;</span>
          </div>
          <div class="kpi-label">Pending Moderation</div>
          <div class="kpi-sub">
            <span>Awaiting review</span>
          </div>
        </div>

        <div class="admin-kpi-card" style="border-top-color: #d32f2f; cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($spamCount); ?></span>
            <span class="kpi-icon">&#128683;</span>
          </div>
          <div class="kpi-label">Flagged / Spam</div>
          <div class="kpi-sub">
            <span>Hidden from public view</span>
          </div>
        </div>
      </div>

      <!-- Filter Controls -->
      <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        
        <!-- Status Tabs -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <?php
            $statuses = [
              'all' => 'All (' . $allCount . ')',
              'approved' => 'Approved (' . $approvedCount . ')',
              'pending' => 'Pending (' . $pendingCount . ')',
              'spam' => 'Spam (' . $spamCount . ')',
            ];
            foreach ($statuses as $stKey => $stLabel):
              $isActive = ($statusFilter === $stKey);
              $params = $_GET;
              $params['status'] = $stKey;
              $params['page'] = 1;
          ?>
            <a href="manageComments.php?<?php echo http_build_query($params); ?>" 
               style="text-decoration: none; padding: 5px 12px; border-radius: 4px; font-size: 0.85rem; font-weight: <?php echo $isActive ? 'bold' : 'normal'; ?>; <?php echo $isActive ? 'background: #333; color: #fff;' : 'background: #fff; border: 1px solid #ccc; color: #333;'; ?>">
              <?php echo $stLabel; ?>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Search & Type Filter -->
        <form method="GET" action="manageComments.php" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
          <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8'); ?>">
          
          <select name="type" onchange="this.form.submit()" style="padding: 5px 8px; font-size: 0.85rem;">
            <option value="all" <?php echo ($typeFilter === 'all') ? 'selected' : ''; ?>>All Entity Types</option>
            <option value="blog" <?php echo ($typeFilter === 'blog') ? 'selected' : ''; ?>>Stories / Blogposts</option>
            <option value="tnote" <?php echo ($typeFilter === 'tnote') ? 'selected' : ''; ?>>Tasting Notes</option>
            <option value="wine" <?php echo ($typeFilter === 'wine') ? 'selected' : ''; ?>>Wine Database</option>
          </select>

          <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search comment or user..." style="padding: 5px 8px; font-size: 0.85rem; width: 180px;">
          <button type="submit" class="btn-action" style="padding: 5px 10px; font-size: 0.85rem;">Search</button>
          <?php if (!empty($search) || $typeFilter !== 'all'): ?>
            <a href="manageComments.php?status=<?php echo htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8'); ?>" style="font-size: 0.82rem; color: #d32f2f; text-decoration: none;">Reset</a>
          <?php endif; ?>
        </form>

      </div>

      <!-- Comments Table -->
      <?php if (empty($comments)): ?>
        <p style="color: #666; font-style: italic; padding: 20px 0;">No comments found matching the active filters.</p>
      <?php else: ?>
        <div class="admin-table-container">
          <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
              <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                <th style="padding: 8px 10px; width: 60px;">ID</th>
                <th style="padding: 8px 10px; width: 140px;">Author &amp; Date</th>
                <th style="padding: 8px 10px; width: 220px;">Target Item</th>
                <th style="padding: 8px 10px;">Comment Text</th>
                <th style="padding: 8px 10px; width: 90px; text-align: center;">Status</th>
                <th style="padding: 8px 10px; text-align: right; width: 180px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($comments as $com): ?>
                <?php
                  $cId = (int)$com['comment_id'];
                  $status = $com['status'];
                  $author = !empty($com['displayname']) ? $com['displayname'] : (!empty($com['username']) ? $com['username'] : 'Guest');
                  $initials = !empty($com['initials']) ? $com['initials'] : '??';

                  // Determine Target URL and Label
                  $targetUrl = '#';
                  $targetLabel = 'Unknown';
                  if (!empty($com['blog_id'])) {
                    $targetUrl = '/blogpost.php?id=' . (int)$com['blog_id'];
                    $targetLabel = 'Story: ' . htmlspecialchars($com['blog_title'] ?? ('Story #' . $com['blog_id']), ENT_QUOTES, 'UTF-8');
                  } elseif (!empty($com['note_id'])) {
                    $targetUrl = '/tnote.php?id=' . (int)$com['note_id'];
                    $vintage = !empty($com['wine_vintage']) ? $com['wine_vintage'] . ' ' : '';
                    $producer = !empty($com['wine_producer']) ? $com['wine_producer'] . ' ' : '';
                    $cuvee = $com['wine_master_name'] ?? ('Note #' . $com['note_id']);
                    $targetLabel = 'Tasting Note: ' . htmlspecialchars($vintage . $producer . $cuvee, ENT_QUOTES, 'UTF-8');
                  } elseif (!empty($com['wine_id'])) {
                    $targetUrl = '/wine.php?id=' . (int)$com['wine_id'];
                    $vintage = !empty($com['wine_vintage']) ? $com['wine_vintage'] . ' ' : '';
                    $cuvee = $com['wine_master_name'] ?? ('Wine #' . $com['wine_id']);
                    $targetLabel = 'Wine: ' . htmlspecialchars($vintage . $cuvee, ENT_QUOTES, 'UTF-8');
                  }
                ?>
                <tr style="border-bottom: 1px solid #eee;">
                  <td data-label="Comment ID" style="padding: 8px 10px; color: #888; font-size: 0.85rem;">
                    #<?php echo $cId; ?>
                  </td>
                  <td class="card-primary-cell" style="padding: 8px 10px;">
                    <strong><?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div style="color: #666; font-size: 0.78rem;"><?php echo !empty($com['comment_date']) ? date('M j, Y H:i', strtotime($com['comment_date'])) : 'Unknown date'; ?></div>
                  </td>
                  <td data-label="Target" style="padding: 8px 10px; font-size: 0.85rem;">
                    <a href="<?php echo htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="text-decoration: none; color: #1a73e8; font-weight: 500;" title="View discussion on page">
                      <?php echo $targetLabel; ?> &#8599;
                    </a>
                  </td>
                  <td data-label="Comment" style="padding: 8px 10px; color: #333; font-size: 0.88rem;">
                    <?php echo nl2br(htmlspecialchars($com['comment'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
                  </td>
                  <td data-label="Status" style="padding: 8px 10px; text-align: center;">
                    <?php if ($status === 'approved'): ?>
                      <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem; font-weight: bold; background: #e8f5e9; color: #2e7d32;">APPROVED</span>
                    <?php elseif ($status === 'pending'): ?>
                      <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem; font-weight: bold; background: #fff8e1; color: #f57f17;">PENDING</span>
                    <?php else: ?>
                      <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem; font-weight: bold; background: #ffebee; color: #c62828;">SPAM</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                    <!-- Status toggles -->
                    <?php if ($status !== 'approved'): ?>
                      <form action="manageComments.php?<?php echo http_build_query($_GET); ?>" method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="comment_id" value="<?php echo $cId; ?>">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" style="background: none; border: none; color: #2e7d32; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline; margin-right: 6px;" title="Approve comment">
                          Approve
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ($status !== 'spam'): ?>
                      <form action="manageComments.php?<?php echo http_build_query($_GET); ?>" method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="comment_id" value="<?php echo $cId; ?>">
                        <input type="hidden" name="status" value="spam">
                        <button type="submit" style="background: none; border: none; color: #e65100; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline; margin-right: 6px;" title="Flag as spam">
                          Spam
                        </button>
                      </form>
                    <?php endif; ?>

                    <!-- Edit link -->
                    <?php
                      $editParams = $_GET;
                      $editParams['edit_comment'] = $cId;
                    ?>
                    <a href="manageComments.php?<?php echo http_build_query($editParams); ?>" style="color: #1a73e8; text-decoration: none; margin-right: 6px; font-size: 0.85rem;">
                      Edit
                    </a>

                    <!-- Delete button -->
                    <form action="manageComments.php?<?php echo http_build_query($_GET); ?>" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete comment #<?php echo $cId; ?>?');">
                      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                      <input type="hidden" name="action" value="delete_comment">
                      <input type="hidden" name="comment_id" value="<?php echo $cId; ?>">
                      <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;">
                        Delete
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php
                $pParams = $_GET;
                $pParams['page'] = $i;
              ?>
              <a href="manageComments.php?<?php echo http_build_query($pParams); ?>" 
                 style="padding: 4px 10px; border-radius: 3px; font-size: 0.85rem; text-decoration: none; <?php echo ($page === $i) ? 'background: #333; color: #fff; font-weight: bold;' : 'background: #eee; color: #333;'; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
