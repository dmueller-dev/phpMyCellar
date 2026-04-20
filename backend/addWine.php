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
  $master_id = '';
  $ct_id = '';
  $vintage = '';
  $wine_desc = '';

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $master_id = filter_input(INPUT_POST, 'master_id', FILTER_VALIDATE_INT);
      $ct_id = filter_input(INPUT_POST, 'ct_id', FILTER_VALIDATE_INT);
      $vintage = filter_input(INPUT_POST, 'vintage', FILTER_VALIDATE_INT);
      $wine_desc = sanitizeInput($_POST['wine_desc']);
      $errors = validateWineInput(0, $master_id, $vintage, $wine_desc, $ct_id);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertWine($conn, $master_id, $ct_id, $vintage, $wine_desc)) {
            $conn->commit();
            $success_message = "Wine inserted successfully";
            // Clear the form values on successful submission
            $master_id = '';
            $ct_id = '';
            $vintage = '';
            $wine_desc = '';
          } else {
            $conn->rollback();
            $errors[] = "Error inserting wine";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating wine: " . $e->getMessage();
        }
      }
    }
  }

  // Get all information for the dropdowns
  $masters = getWineMasters($conn);
  $vintages = getVintages($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Add wine';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Add a new wine</h3>
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

        <form method="POST" accept-charset="UTF-8">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
          <label for="master_id">Master:</label><br>
          <select name="master_id" id="master_id" required>
            <option value="">Select a master</option>
            <?php foreach ($masters as $master): ?>
              <option value="<?php echo $master['master_id']; ?>" <?php echo ($master['master_id'] == $master_id) ? 'selected' : ''; ?>>
                <?php echo
                  htmlspecialchars($master['country'], ENT_QUOTES, 'UTF-8') . ": " .
                  htmlspecialchars($master['region'], ENT_QUOTES, 'UTF-8') . ": " .
                  htmlspecialchars($master['producer'], ENT_QUOTES, 'UTF-8') . ": " .
                  htmlspecialchars($master['grape'], ENT_QUOTES, 'UTF-8') . ": " .
                  (!empty($master['vineyard']) ? htmlspecialchars($master['vineyard'], ENT_QUOTES, 'UTF-8') . ": " : "") .
                  htmlspecialchars($master['name'], ENT_QUOTES, 'UTF-8');
                ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="ct_id">CT ID:</label><br>
          <input type="text" name="ct_id" id="ct_id" value="<?php echo htmlspecialchars($ct_id, ENT_QUOTES, 'UTF-8'); ?>">
            
          <br><br>
          <label for="vintage">Vintage:</label><br>
          <select name="vintage" id="vintage" required>
            <option value="0">NV</option>
            <?php foreach ($vintages as $vintage_db): ?>
              <option value="<?php echo $vintage_db['vintage']; ?>" <?php echo ($vintage_db['vintage'] == $vintage) ? 'selected' : ''; ?>>
                <?php echo $vintage_db['vintage']; ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="wine_desc">Description:</label><br>
          <textarea name="wine_desc" id="wine_desc" rows="20" cols="40" maxlength="2000" placeholder="<p>...</p>"><?php echo htmlspecialchars($wine_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
            
          <br><br>
          <input type="submit" name="update" value="Insert Wine">
        </form>
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
