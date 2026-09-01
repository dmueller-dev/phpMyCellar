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

      if ($blog_id === false || $blog_id === null) {
        $errors[] = "Invalid blog ID.";
      }

      $canEditAll = hasPrivilege($conn, 'edit_all_blogposts');
      $canPublish = hasPrivilege($conn, 'publish_blogpost');
      $check_post = ($blog_id !== false && $blog_id !== null) ? getBlogpostDetails($conn, $blog_id) : null;

      // Ownership and published check
      if (!$canEditAll && $check_post) {
        if ($check_post['user_id'] != $_SESSION['user_id']) {
          $errors[] = "You do not have permission to edit this story.";
        } elseif ($check_post['status'] === 'published' && !$canPublish) {
          $errors[] = "This story has been published and can no longer be edited.";
        }
      }

      $status = $canPublish ? sanitizeInput($_POST['status'] ?? 'draft') : ($check_post ? $check_post['status'] : 'draft');

      $errorsBlogpost = validateBlogpostInput($title, $content, $status);
      $errors = array_merge($errors, $errorsBlogpost);

      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateBlogpost($conn, $blog_id, $pub_date, $title, $content, $status)) {
            // Delete existing linked wines
            $stmt = $conn->prepare("DELETE FROM x_blog_wines WHERE blog_id = ?");
            $stmt->bind_param("i", $blog_id);
            $stmt->execute();
            $stmt->close();

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

            // Delete existing linked tasting notes
            $stmt = $conn->prepare("DELETE FROM x_blog_tnotes WHERE blog_id = ?");
            $stmt->bind_param("i", $blog_id);
            $stmt->execute();
            $stmt->close();

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

  $canEditAll = hasPrivilege($conn, 'edit_all_blogposts');
  $canPublish = hasPrivilege($conn, 'publish_blogpost');

  // List only their own stories for non-editors
  $all_posts = getBlogposts($conn);
  $posts = [];
  foreach ($all_posts as $p) {
    if ($canEditAll || $p['user_id'] == $_SESSION['user_id']) {
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

  // Get all wines and tasting notes
  $wines = getWines($conn);
  $tasting_notes = getTastingNotes($conn);

  // Fetch currently linked elements or handle POST state on validation error
  $linked_wines = [];
  $linked_tnotes = [];
  
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['wines']) && is_array($_POST['wines'])) {
      $linked_wines = array_map('intval', $_POST['wines']);
    }
    if (isset($_POST['tnotes']) && is_array($_POST['tnotes'])) {
      $linked_tnotes = array_map('intval', $_POST['tnotes']);
    }
  } elseif ($selected_post) {
    // Fetch linked wines from DB
    $stmt = $conn->prepare("SELECT wine_id FROM x_blog_wines WHERE blog_id = ?");
    $stmt->bind_param("i", $selected_post['blog_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $linked_wines[] = $row['wine_id'];
    }
    $stmt->close();

    // Fetch linked tasting notes from DB
    $stmt = $conn->prepare("SELECT note_id FROM x_blog_tnotes WHERE blog_id = ?");
    $stmt->bind_param("i", $selected_post['blog_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $linked_tnotes[] = $row['note_id'];
    }
    $stmt->close();
  }
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
          <?php if (!$canPublish && !$canEditAll && $selected_post['status'] === 'published'): ?>
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
              <select name="status" id="status" required <?php echo (!$canPublish) ? 'disabled' : ''; ?>>
                <option value="draft" <?php echo (!$canPublish || $selected_post['status'] == 'draft') ? 'selected' : ''; ?>>draft</option>
                <option value="published" <?php echo ($canPublish && $selected_post['status'] == 'published') ? 'selected' : ''; ?>>published</option>
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
                  <?php 
                    $scale = getRatingScale();
                    foreach ($tasting_notes as $tnote): 
                  ?>
                    <?php 
                      $rating_badge = formatNoteRatingBadge($tnote, $scale, true);
                      $search_text = implode(' ', array_filter([
                        $tnote['tasting_date'],
                        $tnote['country'],
                        $tnote['region'],
                        $tnote['producer'],
                        $tnote['vintage'] ? $tnote['vintage'] : 'NV',
                        $tnote['name'],
                        $tnote['grape'],
                        $tnote['vineyard'],
                        $rating_badge
                      ]));
                      $wine_name = getWineName($tnote['nameconvention'], $tnote['vintage'], $tnote['name'], $tnote['producer'], $tnote['grape'], $tnote['vineyard']);
                      $score = ($rating_badge !== 'NR') ? ' (' . $rating_badge . ')' : '';
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
