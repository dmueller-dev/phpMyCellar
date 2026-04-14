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
    if (isset($_POST['postBlogpost'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $pub_date = sanitizeInput($_POST['pub_date']);
      $title = sanitizeInput($_POST['title']);
      $content = sanitizeInput($_POST['content']);
      $status = sanitizeInput($_POST['status'] ?? 'draft'); // Default to 'draft' if status not sent (i.e. for users with write privileges)

      $errorsBlogpost = validateBlogpostInput($title, $content, $status);
      
      if (empty($errorsBlogpost)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertBlogpost($conn, $_SESSION['user_id'], $pub_date, $title, $content, $status)) {
            $conn->commit();
            $success_message = "Story added successfully.";
          } else {
            $conn->rollback();
            $errors[] = "Error adding story.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error adding story: " . $e->getMessage();
        }
      } else {
        $errors = array_merge($errors, $errorsBlogpost);
      }
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Add story';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>New story</h3>
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
            echo "<p><a href='https://dmueller.com/backend/addBlogpost.php'>New story.</a></p>";
          }
        ?>

        <?php if (empty($success_message)): ?>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <label for="pub_date">Publication date:</label>
            <br><input type="date" id="pub_date" name="pub_date" value="<?php echo isset($_POST['pub_date']) ? htmlspecialchars(sanitizeInput($_POST['pub_date']), ENT_QUOTES, 'UTF-8') : date('Y-m-d'); ?>" required>

            <br><br>
            <label for="title">Title:</label>
            <br><input type="text" id="title" name="title" size="40" maxlength="255" value="<?php echo isset($_POST['title']) ? htmlspecialchars(sanitizeInput($_POST['title']), ENT_QUOTES, 'UTF-8') : ''; ?>" required>

            <br><br>
            <label for="content">Story content:</label>
            <br><textarea name="content" id="content" rows="20" cols="40" placeholder="<p>...</p>" required><?php echo isset($_POST['content']) ? htmlspecialchars(sanitizeInput($_POST['content']), ENT_QUOTES, 'UTF-8') : ''; ?></textarea>

            <br><br>
            <label for="status">Publish story?</label>
            <br>
            <select name="status" id="status" required <?php echo ($role === 'write') ? 'disabled' : ''; ?>>
              <option value="draft" <?php echo ($role === 'write' || empty($_POST['status']) || (isset($_POST['status']) && $_POST['status'] == 'draft')) ? 'selected' : ''; ?>>draft</option>
              <option value="published" <?php echo ($role !== 'write' && isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>published</option>
            </select>
            <?php if ($role === 'write'): ?>
              <p><small><i>Note: Once an admin publishes your story, it will be locked and can no longer be edited.</i></small></p>
            <?php endif; ?>
            
            <input type="submit" name="postBlogpost" id="postBlogpost" value="Post story">
          </form>
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
