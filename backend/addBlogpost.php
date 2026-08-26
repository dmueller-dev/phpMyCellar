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
      $canPublish = hasPrivilege($conn, 'publish_blogpost');
      $status = $canPublish ? sanitizeInput($_POST['status'] ?? 'draft') : 'draft';

      $errorsBlogpost = validateBlogpostInput($title, $content, $status);
      
      if (empty($errorsBlogpost)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertBlogpost($conn, $_SESSION['user_id'], $pub_date, $title, $content, $status)) {
            $blog_id = $conn->insert_id;

            // Link selected wines
            if (isset($_POST['wines']) && is_array($_POST['wines'])) {
              foreach ($_POST['wines'] as $wine_id) {
                $wine_id = (int)$wine_id;
                $stmt = $conn->prepare("INSERT INTO x_blog_wines (blog_id, wine_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $blog_id, $wine_id);
                $stmt->execute();
                $stmt->close();
              }
            }

            // Link selected tasting notes
            if (isset($_POST['tnotes']) && is_array($_POST['tnotes'])) {
              foreach ($_POST['tnotes'] as $note_id) {
                $note_id = (int)$note_id;
                $stmt = $conn->prepare("INSERT INTO x_blog_tnotes (blog_id, note_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $blog_id, $note_id);
                $stmt->execute();
                $stmt->close();
              }
            }

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

  // Get all wines and tasting notes
  $wines = getWines($conn);
  $tasting_notes = getTastingNotes($conn);

  // Preserve selections across validation errors
  $linked_wines = [];
  $linked_tnotes = [];
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['wines']) && is_array($_POST['wines'])) {
      $linked_wines = array_map('intval', $_POST['wines']);
    }
    if (isset($_POST['tnotes']) && is_array($_POST['tnotes'])) {
      $linked_tnotes = array_map('intval', $_POST['tnotes']);
    }
  }
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
            <?php $canPublish = hasPrivilege($conn, 'publish_blogpost'); ?>
            <select name="status" id="status" required <?php echo (!$canPublish) ? 'disabled' : ''; ?>>
              <option value="draft" <?php echo (!$canPublish || empty($_POST['status']) || (isset($_POST['status']) && $_POST['status'] == 'draft')) ? 'selected' : ''; ?>>draft</option>
              <option value="published" <?php echo ($canPublish && isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>published</option>
            </select>
            <?php if (!$canPublish): ?>
              <p><small><i>Note: Once an admin publishes your story, it will be locked and can no longer be edited.</i></small></p>
            <?php endif; ?>
            
            <br><br>
            <label style="font-weight: bold;">Link Wines:</label>
            <div style="border: 1px solid #ccc; border-radius: 4px; padding: 10px; background: white; margin-top: 5px; margin-bottom: 15px;">
              <input type="text" id="searchWines" onkeyup="filterWines()" placeholder="🔍 Search wines..." style="width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-family: Georgia, serif; font-size: small;">
              <div id="winesList" style="max-height: 200px; overflow-y: auto; padding: 5px; border: 1px solid #eee; border-radius: 4px;">
                <?php foreach ($wines as $wine): ?>
                  <?php 
                    $search_text = implode(' ', array_filter([
                      $wine['country'],
                      $wine['region'],
                      $wine['producer'],
                      $wine['vintage'] ? $wine['vintage'] : 'NV',
                      $wine['name'],
                      $wine['grape'],
                      $wine['vineyard']
                    ]));
                    $wine_label = htmlspecialchars($wine['country'] . ": " . $wine['region'] . ": " . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']), ENT_QUOTES, 'UTF-8');
                    $checked = in_array($wine['wine_id'], $linked_wines) ? 'checked' : '';
                  ?>
                  <div class="wine-item" data-search="<?php echo htmlspecialchars(strtolower($search_text), ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom: 5px;">
                    <label style="font-weight: normal; cursor: pointer;">
                      <input type="checkbox" name="wines[]" value="<?php echo $wine['wine_id']; ?>" <?php echo $checked; ?>>
                      <?php echo $wine_label; ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <label style="font-weight: bold;">Link Tasting Notes:</label>
            <div style="border: 1px solid #ccc; border-radius: 4px; padding: 10px; background: white; margin-top: 5px; margin-bottom: 15px;">
              <input type="text" id="searchTnotes" onkeyup="filterTnotes()" placeholder="🔍 Search tasting notes..." style="width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-family: Georgia, serif; font-size: small;">
              <div id="tnotesList" style="max-height: 200px; overflow-y: auto; padding: 5px; border: 1px solid #eee; border-radius: 4px;">
                <?php foreach ($tasting_notes as $tnote): ?>
                  <?php 
                    $search_text = implode(' ', array_filter([
                      $tnote['tasting_date'],
                      $tnote['country'],
                      $tnote['region'],
                      $tnote['producer'],
                      $tnote['vintage'] ? $tnote['vintage'] : 'NV',
                      $tnote['name'],
                      $tnote['grape'],
                      $tnote['vineyard'],
                      $tnote['dmpts'] ? 'DM'.$tnote['dmpts'] : ''
                    ]));
                    $wine_name = getWineName($tnote['nameconvention'], $tnote['vintage'], $tnote['name'], $tnote['producer'], $tnote['grape'], $tnote['vineyard']);
                    $score = $tnote['dmpts'] ? ' (DM' . $tnote['dmpts'] . ')' : '';
                    $tnote_label = htmlspecialchars($tnote['tasting_date'] . " - " . $tnote['producer'] . " " . $wine_name . $score, ENT_QUOTES, 'UTF-8');
                    $checked = in_array($tnote['note_id'], $linked_tnotes) ? 'checked' : '';
                  ?>
                  <div class="tnote-item" data-search="<?php echo htmlspecialchars(strtolower($search_text), ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom: 5px;">
                    <label style="font-weight: normal; cursor: pointer;">
                      <input type="checkbox" name="tnotes[]" value="<?php echo $tnote['note_id']; ?>" <?php echo $checked; ?>>
                      <?php echo $tnote_label; ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <script>
            function filterWines() {
              var input = document.getElementById('searchWines');
              var filter = input.value.toLowerCase();
              var items = document.querySelectorAll('.wine-item');
              items.forEach(function(item) {
                var text = item.getAttribute('data-search') || '';
                if (text.indexOf(filter) > -1) {
                  item.style.display = "";
                } else {
                  item.style.display = "none";
                }
              });
            }

            function filterTnotes() {
              var input = document.getElementById('searchTnotes');
              var filter = input.value.toLowerCase();
              var items = document.querySelectorAll('.tnote-item');
              items.forEach(function(item) {
                var text = item.getAttribute('data-search') || '';
                if (text.indexOf(filter) > -1) {
                  item.style.display = "";
                } else {
                  item.style.display = "none";
                }
              });
            }
            </script>
            <br>
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
