<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Blog & Editorial Stories Management Dashboard.
   *
   * Combines authoring new articles and updating existing stories,
   * linking cellar wines and tasting notes, and handling publication states.
   * Required privileges: 'add_blogpost' or 'edit_blogpost'
   */
  $can_add = hasPrivilege($conn, 'add_blogpost');
  $can_edit = hasPrivilege($conn, 'edit_blogpost');
  $can_edit_all = hasPrivilege($conn, 'edit_all_blogposts');
  $can_publish = hasPrivilege($conn, 'publish_blogpost');

  if (!$can_add && !$can_edit) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Determine current mode ('add' or 'edit')
  $requested_blog_id = filter_input(INPUT_GET, 'blog_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_post', FILTER_VALIDATE_INT);
  $mode_param = sanitizeInput($_GET['mode'] ?? $_POST['mode'] ?? '');

  if ($requested_blog_id && $can_edit) {
    $mode = 'edit';
  } elseif ($mode_param === 'edit' && $can_edit) {
    $mode = 'edit';
  } elseif ($mode_param === 'add' && $can_add) {
    $mode = 'add';
  } else {
    $mode = $can_add ? 'add' : 'edit';
  }

  // Pre-load lookup data
  $wines = getWines($conn);
  $tasting_notes = getTastingNotes($conn);
  $scale = getRatingScale();

  $all_posts = getBlogposts($conn);
  $accessible_posts = [];
  foreach ($all_posts as $p) {
    if ($can_edit_all || (int)$p['user_id'] === (int)$_SESSION['user_id']) {
      $accessible_posts[] = $p;
    }
  }

  // Loaded story details for EDIT mode
  $selected_post = null;
  $linked_wines = [];
  $linked_tnotes = [];

  if ($requested_blog_id) {
    $selected_post = getBlogpostDetails($conn, $requested_blog_id);
    if (!$selected_post) {
      $errors[] = "Requested story #" . $requested_blog_id . " not found.";
    } elseif (!$can_edit_all && (int)$selected_post['user_id'] !== (int)$_SESSION['user_id']) {
      $errors[] = "You do not have permission to edit this story.";
      $selected_post = null;
    } else {
      // Fetch linked wines from DB
      $stmt = $conn->prepare("SELECT wine_id FROM x_blog_wines WHERE blog_id = ?");
      if ($stmt) {
        $stmt->bind_param("i", $requested_blog_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $linked_wines[] = (int)$row['wine_id'];
        $stmt->close();
      }

      // Fetch linked tasting notes from DB
      $stmt = $conn->prepare("SELECT note_id FROM x_blog_tnotes WHERE blog_id = ?");
      if ($stmt) {
        $stmt->bind_param("i", $requested_blog_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $linked_tnotes[] = (int)$row['note_id'];
        $stmt->close();
      }
    }
  }

  // Handle Form Submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $pub_date = sanitizeInput($_POST['pub_date'] ?? date('Y-m-d'));
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    
    // Preserve checkbox selections across error rendering
    $linked_wines = isset($_POST['wines']) && is_array($_POST['wines']) ? array_map('intval', $_POST['wines']) : [];
    $linked_tnotes = isset($_POST['tnotes']) && is_array($_POST['tnotes']) ? array_map('intval', $_POST['tnotes']) : [];

    if ($action === 'add') {
      if (!$can_add) die("Permission denied: You cannot author stories.");
      $status = $can_publish ? sanitizeInput($_POST['status'] ?? 'draft') : 'draft';

      $valErrors = validateBlogpostInput($title, $content, $status);
      if (!empty($valErrors)) {
        $errors = array_merge($errors, $valErrors);
      } else {
        $conn->begin_transaction();
        try {
          if (insertBlogpost($conn, $_SESSION['user_id'], $pub_date, $title, $content, $status)) {
            $new_blog_id = $conn->insert_id;

            // Link wines
            if (!empty($linked_wines)) {
              $stmt = $conn->prepare("INSERT INTO x_blog_wines (blog_id, wine_id) VALUES (?, ?)");
              foreach ($linked_wines as $w_id) {
                $stmt->bind_param("ii", $new_blog_id, $w_id);
                $stmt->execute();
              }
              $stmt->close();
            }

            // Link tasting notes
            if (!empty($linked_tnotes)) {
              $stmt = $conn->prepare("INSERT INTO x_blog_tnotes (blog_id, note_id) VALUES (?, ?)");
              foreach ($linked_tnotes as $n_id) {
                $stmt->bind_param("ii", $new_blog_id, $n_id);
                $stmt->execute();
              }
              $stmt->close();
            }

            $conn->commit();
            $success_message = "Story '" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "' published/saved successfully!";
            $title = '';
            $content = '';
            $linked_wines = [];
            $linked_tnotes = [];
            
            // Refresh list
            $all_posts = getBlogposts($conn);
            $accessible_posts = [];
            foreach ($all_posts as $p) {
              if ($can_edit_all || (int)$p['user_id'] === (int)$_SESSION['user_id']) $accessible_posts[] = $p;
            }
          } else {
            $conn->rollback();
            $errors[] = "Failed to create story.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error creating story: " . $e->getMessage();
        }
      }
    } elseif ($action === 'edit') {
      if (!$can_edit) die("Permission denied: You cannot edit stories.");
      $post_blog_id = filter_input(INPUT_POST, 'blog_id', FILTER_VALIDATE_INT);
      $check_post = getBlogpostDetails($conn, $post_blog_id);

      if (!$can_edit_all) {
        if (!$check_post || (int)$check_post['user_id'] !== (int)$_SESSION['user_id']) {
          $errors[] = "You do not have permission to edit this story.";
        } elseif ($check_post['status'] === 'published' && !$can_publish) {
          $errors[] = "This story has been published and can no longer be edited without publishing privileges.";
        }
      }

      $status = $can_publish ? sanitizeInput($_POST['status'] ?? 'draft') : ($check_post ? $check_post['status'] : 'draft');
      $valErrors = validateBlogpostInput($title, $content, $status);

      if (!empty($valErrors)) {
        $errors = array_merge($errors, $valErrors);
      } else {
        $conn->begin_transaction();
        try {
          if (updateBlogpost($conn, $post_blog_id, $pub_date, $title, $content, $status)) {
            // Re-sync linked wines
            $stmt = $conn->prepare("DELETE FROM x_blog_wines WHERE blog_id = ?");
            $stmt->bind_param("i", $post_blog_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($linked_wines)) {
              $stmt = $conn->prepare("INSERT INTO x_blog_wines (blog_id, wine_id) VALUES (?, ?)");
              foreach ($linked_wines as $w_id) {
                $stmt->bind_param("ii", $post_blog_id, $w_id);
                $stmt->execute();
              }
              $stmt->close();
            }

            // Re-sync linked notes
            $stmt = $conn->prepare("DELETE FROM x_blog_tnotes WHERE blog_id = ?");
            $stmt->bind_param("i", $post_blog_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($linked_tnotes)) {
              $stmt = $conn->prepare("INSERT INTO x_blog_tnotes (blog_id, note_id) VALUES (?, ?)");
              foreach ($linked_tnotes as $n_id) {
                $stmt->bind_param("ii", $post_blog_id, $n_id);
                $stmt->execute();
              }
              $stmt->close();
            }

            $conn->commit();
            $success_message = "Story #" . $post_blog_id . " updated successfully.";
            $selected_post = getBlogpostDetails($conn, $post_blog_id);
            
            // Refresh list
            $all_posts = getBlogposts($conn);
            $accessible_posts = [];
            foreach ($all_posts as $p) {
              if ($can_edit_all || (int)$p['user_id'] === (int)$_SESSION['user_id']) $accessible_posts[] = $p;
            }
          } else {
            $conn->rollback();
            $errors[] = "Failed to update story #" . $post_blog_id;
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating story: " . $e->getMessage();
        }
      }
    }
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Stories & Articles - Cellar Administration';
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
          &#128214; Manage Wine Stories &amp; Articles
        </h2>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="/blog.php" target="_blank" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            View Public Blog &rarr;
          </a>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div style="background-color: #ffdddd; border-left: 5px solid #f44336; padding: 12px 16px; margin-bottom: 20px;">
          <strong style="color: #a00;">Please correct the following errors:</strong>
          <ul style="margin: 8px 0 0; padding-left: 20px; color: #a00;">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 12px 16px; margin-bottom: 20px; color: #2e7d32;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <!-- High-level Editorial Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo count($accessible_posts); ?></span>
            <span class="kpi-icon">&#128214;</span>
          </div>
          <div class="kpi-label">Stories &amp; Posts</div>
          <div class="kpi-sub">Total accessible articles</div>
        </div>
      </div>

      <!-- Mode Selector Tabs -->
      <div class="admin-mode-tabs" style="margin-bottom: 20px;">
        <?php if ($can_add): ?>
          <a href="manageBlogposts.php?mode=add" class="admin-tab-btn <?php echo ($mode === 'add') ? 'active' : ''; ?>">
            &#10010; Write New Story
          </a>
        <?php endif; ?>
        <?php if ($can_edit): ?>
          <a href="manageBlogposts.php?mode=edit" class="admin-tab-btn <?php echo ($mode === 'edit') ? 'active' : ''; ?>">
            &#9998; Edit Stories
          </a>
        <?php endif; ?>
        <a href="#storiesDirectorySection" class="admin-tab-btn" style="color: #555;">
          📋 Story Directory (<?php echo count($accessible_posts); ?>)
        </a>
      </div>

      <!-- Form Container -->
      <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
        <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">
          <?php echo ($mode === 'edit') ? ($selected_post ? "&#9998; Edit Story #{$selected_post['blog_id']}" : "&#9998; Select Story to Edit") : "&#10010; Write New Story"; ?>
        </h3>

        <?php if ($mode === 'edit'): ?>
          <div style="max-width: 600px; margin-bottom: 20px;">
            <label for="searchStoryBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
              Choose Story to Edit:
            </label>
            <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
              <input type="text" id="searchStoryBox" placeholder="🔍 Filter stories..." onkeyup="filterStorySelectDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
              <select id="selectStory" onchange="if(this.value) window.location.href='manageBlogposts.php?mode=edit&blog_id=' + this.value;" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                <option value="">-- Select Story --</option>
                <?php foreach ($accessible_posts as $p): ?>
                  <option value="<?php echo (int)$p['blog_id']; ?>" <?php echo ($selected_post && (int)$p['blog_id'] === (int)$selected_post['blog_id']) ? 'selected' : ''; ?>>
                    #<?php echo (int)$p['blog_id']; ?> &mdash; <?php echo htmlspecialchars($p['pub_date'] . ' — ' . $p['title'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($mode === 'add' || ($mode === 'edit' && $selected_post)): ?>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo ($mode === 'edit') ? 'edit' : 'add'; ?>">
            <?php if ($mode === 'edit'): ?>
              <input type="hidden" name="blog_id" value="<?php echo (int)$selected_post['blog_id']; ?>">
            <?php endif; ?>

            <div class="form-row-2col" style="margin-bottom: 16px;">
              <div>
                <label for="title" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Story Title: <span style="color: red;">*</span>
                </label>
                <input type="text" name="title" id="title" required maxlength="255" value="<?php echo ($mode === 'edit') ? htmlspecialchars($selected_post['title'], ENT_QUOTES, 'UTF-8') : (isset($_POST['title']) ? htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8') : ''); ?>" placeholder="Headline of your story..." style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="pub_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Publication Date: <span style="color: red;">*</span>
                </label>
                <?php $p_date = ($mode === 'edit') ? $selected_post['pub_date'] : (isset($_POST['pub_date']) ? $_POST['pub_date'] : date('Y-m-d')); ?>
                <input type="date" name="pub_date" id="pub_date" required value="<?php echo htmlspecialchars($p_date, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>
            </div>

            <div style="margin-bottom: 16px;">
              <label for="content" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Story Content (HTML / Markdown Supported): <span style="color: red;">*</span>
              </label>
              <textarea name="content" id="content" rows="14" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 0.92rem;" placeholder="Write your wine story here. HTML tags like <p>, <h3>, <blockquote>, <em>, <strong> are supported."><?php echo ($mode === 'edit') ? htmlspecialchars($selected_post['content'], ENT_QUOTES, 'UTF-8') : (isset($_POST['content']) ? htmlspecialchars($_POST['content'], ENT_QUOTES, 'UTF-8') : ''); ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
              <label for="status" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Publication Status:
              </label>
              <?php $c_status = ($mode === 'edit') ? ($selected_post['status'] ?? 'draft') : (isset($_POST['status']) ? $_POST['status'] : 'draft'); ?>
              <select name="status" id="status" required <?php echo !$can_publish ? 'disabled' : ''; ?> style="width: 100%; max-width: 300px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                <option value="draft" <?php echo ($c_status === 'draft') ? 'selected' : ''; ?>>Draft</option>
                <option value="published" <?php echo ($c_status === 'published') ? 'selected' : ''; ?>>Published</option>
              </select>
              <?php if (!$can_publish): ?>
                <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Only administrators or editors with publishing privileges can set stories to 'published'.</div>
              <?php endif; ?>
            </div>

            <!-- Linked Elements Accordions -->
            <div class="form-row-2col" style="margin-bottom: 24px;">
              <!-- Linked Wines -->
              <div style="background: white; border: 1px solid #ccc; border-radius: 6px; padding: 14px;">
                <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Linked Wines:</label>
                <input type="text" id="searchWines" placeholder="🔍 Search wines to link..." onkeyup="filterWineCheckboxes()" style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; box-sizing: border-box; font-size: 0.85rem;">
                <div id="winesCheckboxList" style="max-height: 200px; overflow-y: auto; padding: 4px;">
                  <?php foreach ($wines as $wine): 
                    $w_checked = in_array((int)$wine['wine_id'], $linked_wines, true) ? 'checked' : '';
                    $search_term = strtolower(implode(' ', [$wine['country'], $wine['region'], $wine['producer'], $wine['name'], $wine['grape']]));
                  ?>
                    <div class="wine-check-item" data-search="<?php echo htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom: 6px;">
                      <label style="font-size: 0.85rem; cursor: pointer; display: flex; align-items: flex-start; gap: 6px;">
                        <input type="checkbox" name="wines[]" value="<?php echo (int)$wine['wine_id']; ?>" <?php echo $w_checked; ?> style="margin-top: 2px;">
                        <span><?php echo htmlspecialchars($wine['country'] . ': ' . $wine['region'] . ' — ' . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']), ENT_QUOTES, 'UTF-8'); ?></span>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Linked Tasting Notes -->
              <div style="background: white; border: 1px solid #ccc; border-radius: 6px; padding: 14px;">
                <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Linked Tasting Notes:</label>
                <input type="text" id="searchTnotes" placeholder="🔍 Search notes to link..." onkeyup="filterNoteCheckboxes()" style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; box-sizing: border-box; font-size: 0.85rem;">
                <div id="notesCheckboxList" style="max-height: 200px; overflow-y: auto; padding: 4px;">
                  <?php foreach ($tasting_notes as $tn): 
                    $n_checked = in_array((int)$tn['note_id'], $linked_tnotes, true) ? 'checked' : '';
                    $search_term = strtolower(implode(' ', [$tn['tasting_date'], $tn['producer'] ?? '', $tn['name'] ?? '', $tn['country'] ?? '']));
                  ?>
                    <div class="note-check-item" data-search="<?php echo htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom: 6px;">
                      <label style="font-size: 0.85rem; cursor: pointer; display: flex; align-items: flex-start; gap: 6px;">
                        <input type="checkbox" name="tnotes[]" value="<?php echo (int)$tn['note_id']; ?>" <?php echo $n_checked; ?> style="margin-top: 2px;">
                        <span>#<?php echo (int)$tn['note_id']; ?> — <?php echo htmlspecialchars($tn['tasting_date'] . ' — ' . getWineName($tn['nameconvention'], $tn['vintage'], $tn['name'], $tn['producer'], $tn['grape'], $tn['vineyard']), ENT_QUOTES, 'UTF-8'); ?></span>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                <?php echo ($mode === 'edit') ? 'Update Story' : 'Save Story'; ?>
              </button>
              <?php if ($mode === 'edit'): ?>
                <a href="manageBlogposts.php" class="btn-action btn-secondary" style="padding: 12px 20px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
                  Cancel
                </a>
              <?php endif; ?>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- Searchable Stories Directory -->
      <div id="storiesDirectorySection" style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
            Story Directory (<?php echo count($accessible_posts); ?> articles)
          </h3>
          <input type="text" id="storySearchBox" class="admin-search-box" style="margin-bottom: 0; max-width: 320px;" placeholder="Search stories..." onkeyup="filterStoriesTable()" oninput="filterStoriesTable()">
        </div>

        <div class="admin-table-container" style="max-height: 480px; overflow-y: auto;">
          <table id="storiesTable">
            <thead>
              <tr>
                <th style="width: 100px;">Date</th>
                <th>Title</th>
                <th>Author</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($accessible_posts)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: #777; padding: 20px;">No stories found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($accessible_posts as $post): ?>
                  <tr style="<?php echo ($requested_blog_id === (int)$post['blog_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                    <td data-label="Date"><?php echo htmlspecialchars($post['pub_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="card-primary-cell"><strong><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td data-label="Author"><?php echo htmlspecialchars($post['author'] ?? $post['initials'] ?? 'Author', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Status">
                      <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; <?php echo ($post['status'] === 'published') ? 'background: #e8f5e9; color: #2e7d32;' : 'background: #f5f5f5; color: #666;'; ?>">
                        <?php echo htmlspecialchars($post['status'], ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td class="card-actions-cell" style="text-align: right;">
                      <?php if ($can_edit): ?>
                        <a href="manageBlogposts.php?mode=edit&blog_id=<?php echo (int)$post['blog_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                          Edit
                        </a>
                      <?php endif; ?>
                      <a href="/article.php?id=<?php echo (int)$post['blog_id']; ?>" target="_blank" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                        View &rarr;
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  function filterWineCheckboxes() {
    const q = document.getElementById('searchWines').value.toLowerCase().trim();
    document.querySelectorAll('.wine-check-item').forEach(el => {
      const text = el.getAttribute('data-search') || '';
      el.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
  }

  function filterNoteCheckboxes() {
    const q = document.getElementById('searchTnotes').value.toLowerCase().trim();
    document.querySelectorAll('.note-check-item').forEach(el => {
      const text = el.getAttribute('data-search') || '';
      el.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
  }

  function filterStoriesTable() {
    const searchVal = document.getElementById('storySearchBox').value.toLowerCase().trim();
    const table = document.getElementById('storiesTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.cells.length < 5) continue;
      const textContent = row.textContent.toLowerCase();
      row.style.display = (!searchVal || textContent.includes(searchVal)) ? '' : 'none';
    }
  }

  let cachedStorySelectOptions = null;
  function filterStorySelectDropdown() {
    const sel = document.getElementById('selectStory');
    const input = document.getElementById('searchStoryBox');
    if (!sel || !input) return;
    if (!cachedStorySelectOptions) {
      cachedStorySelectOptions = Array.from(sel.options);
    }
    const filter = input.value.toLowerCase().trim();
    const currentVal = sel.value;

    sel.innerHTML = '';
    let hasSelected = false;

    cachedStorySelectOptions.forEach(opt => {
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
    const storySel = document.getElementById('selectStory');
    if (storySel) {
      cachedStorySelectOptions = Array.from(storySel.options);
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
