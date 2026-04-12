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
    if (isset($_POST['update'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $subregion_id = filter_input(INPUT_POST, 'subregion_id', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $subregion = sanitizeInput($_POST['subregion']);
      $subregion_desc = sanitizeInput($_POST['subregion_desc']);
      $errors = validateSubregionInput($subregion, $region_id, $subregion_desc);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateSubregion($conn, $subregion_id, $region_id, $subregion, $subregion_desc)) {
            $conn->commit();
            $success_message = "Subregion updated successfully";
          } else {
            $conn->rollback();
            $errors[] = "Error updating subregion: Invalid region";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating subregion: " . $e->getMessage();
        }
      }
    }
  }

  // Get all subregions for the dropdown
  $regions = getRegions($conn);
  $subregions = getSubregions($conn);

  // Get selected subregion details
  $selected_subregion = null;
  if (isset($_GET['subregion_id'])) {
    $subregion_id = filter_input(INPUT_GET, 'subregion_id', FILTER_VALIDATE_INT);
    if ($subregion_id !== false && $subregion_id !== null) {
      $selected_subregion = getSubregionDetails($conn, $subregion_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Edit subregion';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Edit a subregion</h3>
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
          <label for="subregion_id">Select Subregion:</label>
          <select name="subregion_id" id="subregion_id" onchange="this.form.submit()">
            <option value="">Select a subregion</option>
            <?php foreach ($subregions as $subregion): ?>
            <option value="<?php echo $subregion['subregion_id']; ?>" <?php echo (isset($_GET['subregion_id']) && $_GET['subregion_id'] == $subregion['subregion_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($subregion['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['subregion'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_subregion): ?>
          <h3>Update Subregion Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="subregion_id" value="<?php echo $selected_subregion['subregion_id']; ?>">
            
            <label for="subregion">Subregion:</label><br>
            <input type="text" name="subregion" id="subregion" value="<?php echo htmlspecialchars($selected_subregion['subregion'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            
            <br>
            <label for="region">Region:</label><br>
            <select name="region_id" id="region_id" required>
              <?php foreach ($regions as $region): ?>
                <option value="<?php echo $region['region_id']; ?>" <?php echo ($region['region_id'] == $selected_subregion['region_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            
            <br><br>
            <label for="subregion_desc">Description:</label>
            <textarea name="subregion_desc" id="subregion_desc" rows="20" cols="40" maxlength="1500"><?php echo htmlspecialchars($selected_subregion['subregion_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            
            <br><br>
            <input type="submit" name="update" value="Update Subregion">
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
