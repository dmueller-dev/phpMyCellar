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
      $vineyard_id = filter_input(INPUT_POST, 'vineyard_id', FILTER_VALIDATE_INT);
      $appellation_id = filter_input(INPUT_POST, 'appellation_id', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $vineyard = sanitizeInput($_POST['vineyard']);
      $vineyard_desc = sanitizeInput($_POST['vineyard_desc']);
      $errors = validateVineyardInput($vineyard, $appellation_id, $region_id, $vineyard_desc);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateVineyard($conn, $vineyard_id, $appellation_id, $region_id, $vineyard, $vineyard_desc)) {
            $conn->commit();
            $success_message = "Vineyard updated successfully";
          } else {
            $conn->rollback();
            $errors[] = "Error updating vineyard: Invalid region or appellation";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating vineyard: " . $e->getMessage();
        }
      }
    }
  }

  // Get all vineyards for the dropdown
  $regions = getRegions($conn);
  $appellations = getAppellations($conn);
  $vineyards = getVineyards($conn);

  // Get selected vineyard details
  $selected_vineyard = null;
  if (isset($_GET['vineyard_id'])) {
    $vineyard_id = filter_input(INPUT_GET, 'vineyard_id', FILTER_VALIDATE_INT);
    if ($vineyard_id !== false && $vineyard_id !== null) {
      $selected_vineyard = getVineyardDetails($conn, $vineyard_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Edit vineyard';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Edit a vineyard</h3>
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
          <label for="vineyard_id">Select Vineyard:</label>
          <select name="vineyard_id" id="vineyard_id" onchange="this.form.submit()">
            <option value="">Select a vineyard</option>
            <?php foreach ($vineyards as $vineyard): ?>
            <option value="<?php echo $vineyard['vineyard_id']; ?>" <?php echo (isset($_GET['vineyard_id']) && $_GET['vineyard_id'] == $vineyard['vineyard_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($vineyard['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($vineyard['region'], ENT_QUOTES, 'UTF-8') . ": " . (($vineyard['appellation']!=null) ? htmlspecialchars($vineyard['appellation'], ENT_QUOTES, 'UTF-8') . ": " : "") . htmlspecialchars($vineyard['vineyard'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_vineyard): ?>
          <h3>Update Vineyard Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="vineyard_id" value="<?php echo $selected_vineyard['vineyard_id']; ?>">
            
            <label for="vineyard">Vineyard:</label><br>
            <input type="text" name="vineyard" id="vineyard" value="<?php echo htmlspecialchars($selected_vineyard['vineyard'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            
            <br>
            <label for="region_id">Region:</label><br>
            <select name="region_id" id="region_id" required>
              <?php foreach ($regions as $region): ?>
                <option value="<?php echo $region['region_id']; ?>" <?php echo ($region['region_id'] == $selected_vineyard['region_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br>
            <label for="appellation_id">Appellation:</label><br>
            <select name="appellation_id" id="appellation_id">
              <option value="null" selected>Without appellation</option>
              <?php foreach ($appellations as $appellation): ?>
                <option value="<?php echo $appellation['appellation_id']; ?>" <?php echo ($appellation['appellation_id'] == $selected_vineyard['appellation_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($appellation['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['appellation'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            
            <br><br>
            <label for="vineyard_desc">Description:</label>
            <textarea name="vineyard_desc" id="vineyard_desc" rows="20" cols="40" maxlength="1500"><?php echo htmlspecialchars($selected_vineyard['vineyard_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            
            <br><br>
            <input type="submit" name="update" value="Update Vineyard">
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
