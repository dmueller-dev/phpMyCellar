<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  // Include the database configuration file
  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['updateBlogpost'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $blog_id = filter_input(INPUT_POST, 'blog_id', FILTER_VALIDATE_INT);
      $pub_date = sanitizeInput($_POST['pub_date']);
      $title = sanitizeInput($_POST['title']);
      $content = sanitizeInput($_POST['content']);
      $status = sanitizeInput($_POST['status'] ?? 'draft'); // Default to 'draft' if status not sent (i.e. for users with write privileges)

      if ($blog_id === false || $blog_id === null) {
        $errors[] = "Invalid blog ID.";
      }

      $errorsBlogpost = validateBlogpostInput($title, $content, $status);
      $errors = array_merge($errors, $errorsBlogpost);
      
      // Ownership and published check for 'write' users
      if ($role === 'write' && $blog_id !== false && $blog_id !== null) {
        $check_post = getBlogpostDetails($conn, $blog_id);
        if (!$check_post || $check_post['user_id'] != $_SESSION['user_id']) {
          $errors[] = "You do not have permission to edit this story.";
        } elseif ($check_post['status'] === 'published') {
          $errors[] = "This story has been published and can no longer be edited.";
        }
      }

      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateBlogpost($conn, $blog_id, $pub_date, $title, $content, $status)) {
            $conn->commit();
            $success_message = "Story updated successfully.";
          } else {
            $conn->rollback();
            $errors[] = "Error updating story: Invalid ID?";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating story: " . $e->getMessage();
        }
      }
    }
  }

  // List only their own stories for 'write' users
  $all_posts = getBlogposts($conn);
  $posts = [];
  foreach ($all_posts as $p) {
    if ($role === 'admin' || $p['user_id'] == $_SESSION['user_id']) {
      $posts[] = $p;
    }
  }

  // Get selected blogpost details
  $selected_post = null;
  if (isset($_GET['blog_id'])) {
    $blog_id = filter_input(INPUT_GET, 'blog_id', FILTER_VALIDATE_INT);
    if ($blog_id !== false && $blog_id !== null) {
      $selected_post = getBlogpostDetails($conn, $blog_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Edit story';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Edit a story</h3>
        <?php
          if (!empty($errors)) {
            echo "<div style='color: red;'><ul>";
            foreach ($errors as $error) {
              echo "<li>" . $error . "</li>";
            }
            echo "</ul></div>";
          }

          if (!empty($success_message)) {
            echo "<div style='color: green;'>" . $success_message . "</div>";
          }
        ?>

        <form method="GET">
          <label for="blog_id">Select story:</label>
          <select name="blog_id" id="blog_id" onchange="this.form.submit()" required>
            <option value="">Select a story</option>
            <?php foreach ($posts as $post): ?>
              <option value="<?php echo $post['blog_id']; ?>" <?php echo (isset($_GET['blog_id']) && $_GET['blog_id'] == $post['blog_id']) ? 'selected' : ''; ?>>
                <?php 
                  $status_label = ($post['status'] === 'published') ? ' (Published)' : '';
                  echo htmlspecialchars($post['blog_id'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($post['pub_date'], ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . $status_label; 
                ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_post): ?>
          <?php if ($role === 'write' && $selected_post['status'] === 'published'): ?>
            <div style="padding: 15px; margin-top: 20px; background-color: #f9f9f9; border-left: 4px solid #f39c12;">
              <p><strong>Notice:</strong> This story has been published and can no longer be edited. Please contact an admin if you need to make changes.</p>
            </div>
          <?php else: ?>
            <h3>Update story</h3>
            <form method="POST" accept-charset="UTF-8">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="blog_id" value="<?php echo isset($_POST['blog_id']) ? htmlspecialchars($_POST['blog_id'], ENT_QUOTES, 'UTF-8') : $selected_post['blog_id']; ?>">

              <label for="pub_date">Publication date:</label>
              <br><input type="date" id="pub_date" name="pub_date" value="<?php echo $selected_post['pub_date']; ?>" required>

              <br><br>
              <label for="title">Title:</label>
              <br><input type="text" id="title" name="title" size="40" maxlength="255" value="<?php echo htmlspecialchars($selected_post['title'], ENT_QUOTES, 'UTF-8'); ?>" required>

              <br><br>
              <label for="content">Story content:</label>
              <br><textarea name="content" id="content" rows="20" cols="40" placeholder="<p>...</p>" required><?php echo $selected_post['content']; ?></textarea>

              <br><br>
              <label for="status">Publish story?</label>
              <br>
              <select name="status" id="status" required <?php echo ($role === 'write') ? 'disabled' : ''; ?>>
                <option value="draft" <?php echo ($role === 'write' || $selected_post['status'] == 'draft') ? 'selected' : ''; ?>>draft</option>
                <option value="published" <?php echo ($role !== 'write' && $selected_post['status'] == 'published') ? 'selected' : ''; ?>>published</option>
              </select>
              <?php if ($role === 'write'): ?>
                <p><small><i>Note: Once an admin publishes your story, it will be locked and can no longer be edited.</i></small></p>
              <?php endif; ?>
              
              <br><br>
              <input type="submit" name="updateBlogpost" id="updateBlogpost" value="Update story">
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Latest stories</h3>
      <p>While this website is dedicated to the wines I've tasted, you will find other wine-related articles and stories on my blog:</p>
      <p><i><a href="/blog.php" title="All wine stories">Read all...</a></i></p>
    </div>

    <div class="card">
    <h3>Get in touch</h3>
    <p>
      I don't control access to this website. My texts are open for everyone to read. That means I don't know who you are. If you'd like
      to introduce yourself and connect with me, you may do so via my profile on
      <a href="https://www.cellartracker.com/user.asp?iUserOverride=697203">CellarTracker</a>. I'm always happy to hear and learn from
      other wine enthusiasts.
    </p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
