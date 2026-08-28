<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  global $conn;

  $errors = [];
  $success_message = '';
  $page_key = trim($_GET['key'] ?? ($_POST['page_key'] ?? ''));

  if (empty($page_key)) {
    header('Location: manageStaticPages.php');
    exit;
  }

  // Handle form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die('CSRF token validation failed');
    }

    $page_title_input = sanitizeInput($_POST['page_title'] ?? '');
    $page_content_input = $_POST['page_content'] ?? '';
    $meta_desc_input = sanitizeInput($_POST['meta_description'] ?? '');

    if (empty($page_title_input)) {
      $errors[] = 'Page title cannot be empty.';
    }

    if (empty($errors)) {
      if (saveStaticPage($page_key, $page_title_input, $page_content_input, $meta_desc_input)) {
        $success_message = 'Static page updated successfully.';
      } else {
        $errors[] = 'Failed to update static page in the database.';
      }
    }
  }

  $page_data = getStaticPage($page_key);
  if (!$page_data && empty($success_message)) {
    // If not in DB yet, create a skeleton
    $page_data = [
      'page_key' => $page_key,
      'page_title' => ucfirst(str_replace('_', ' ', $page_key)),
      'page_content' => '',
      'meta_description' => ''
    ];
  }

  $page_title = 'Edit Static Page: ' . htmlspecialchars($page_data['page_title'] ?? $page_key, ENT_QUOTES, 'UTF-8');
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h2>Edit Static Page: <code><?php echo htmlspecialchars($page_key, ENT_QUOTES, 'UTF-8'); ?></code></h2>
        <p><a href="manageStaticPages.php">&larr; Back to all static pages</a></p>

        <?php if (!empty($errors)): ?>
          <div style="background-color:#ffdddd;border-left:5px solid #f44336;padding:10px 15px;margin-bottom:20px;">
            <ul style="margin:0;padding-left:20px;color:#a00;">
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
          <div style="background-color:#ddffdd;border-left:5px solid #4CAF50;padding:10px 15px;margin-bottom:20px;color:#2e7d32;">
            <?php echo $success_message; ?>
          </div>
        <?php endif; ?>

        <form action="editStaticPage.php?key=<?php echo urlencode($page_key); ?>" method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
          <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($page_key, ENT_QUOTES, 'UTF-8'); ?>">

          <div style="margin-bottom:15px;">
            <label for="page_title"><strong>Page Title:</strong></label><br>
            <input type="text" id="page_title" name="page_title" value="<?php echo htmlspecialchars($page_data['page_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required style="width:100%;max-width:500px;padding:8px;">
          </div>

          <div style="margin-bottom:15px;">
            <label for="meta_description"><strong>Meta Description:</strong></label><br>
            <input type="text" id="meta_description" name="meta_description" value="<?php echo htmlspecialchars($page_data['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;max-width:600px;padding:8px;">
            <br><small style="color:#666;">SEO description used for search engines and social cards.</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="page_content"><strong>Page Content (HTML):</strong></label><br>
            <textarea id="page_content" name="page_content" rows="18" cols="60" style="width:100%;padding:8px;font-family:Georgia,serif;"><?php echo htmlspecialchars($page_data['page_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            <br><small style="color:#666;">The WYSIWYG editor is enabled on this field for formatted editing.</small>
          </div>

          <div style="margin-top:20px;">
            <button type="submit" style="padding:10px 24px;font-size:16px;background-color:#7B1113;color:#fff;border:none;border-radius:4px;cursor:pointer;">Save Page Content</button>
            <a href="manageStaticPages.php" style="margin-left:15px;color:#555;text-decoration:none;">Cancel</a>
          </div>
        </form>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Static Page Tips</h3>
      <p>Standard page keys used across the site:</p>
      <ul>
        <li><code>welcome</code>: Front page introductory card.</li>
        <li><code>get_in_touch</code>: Front page contact &amp; cellar access info card.</li>
        <li><code>impressum</code>: Impressum / Legal notice page.</li>
        <li><code>privacy</code>: Privacy policy &amp; cookie notice page.</li>
        <li><code>rating_guide</code>: Guide to wine rating scales (20-point &amp; WSET).</li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
