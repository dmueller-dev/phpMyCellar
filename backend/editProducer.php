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
      $producer_id = filter_input(INPUT_POST, 'producer_id', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $producer = sanitizeInput($_POST['producer']);
      $address = sanitizeInput($_POST['address']);
      $producer_desc = sanitizeInput($_POST['producer_desc']);
      $errors = validateProducerInput($producer, $region_id, $address, $producer_desc);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateProducer($conn, $producer_id, $region_id, $producer, $address, $producer_desc)) {
            $conn->commit();
            $success_message = "Producer updated successfully";
          } else {
            $conn->rollback();
            $errors[] = "Error updating producer: Invalid region";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating producer: " . $e->getMessage();
        }
      }
    }
  }

  // Get all subregions for the dropdown
  $regions = getRegions($conn);
  $producers = getProducers($conn);

  // Get selected producer details
  $selected_producer = null;
  if (isset($_GET['producer_id'])) {
    $producer_id = filter_input(INPUT_GET, 'producer_id', FILTER_VALIDATE_INT);
    if ($producer_id !== false && $producer_id !== null) {
      $selected_producer = getProducerDetails($conn, $producer_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Edit producer';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Edit a producer</h3>
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
          <label for="producer_id">Select Producer:</label>
          <select name="producer_id" id="producer_id" onchange="this.form.submit()">
            <option value="">Select a producer</option>
            <?php foreach ($producers as $producer): ?>
            <option value="<?php echo $producer['producer_id']; ?>" <?php echo (isset($_GET['producer_id']) && $_GET['producer_id'] == $producer['producer_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($producer['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($producer['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($producer['producer'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_producer): ?>
          <h3>Update Producer Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="producer_id" value="<?php echo $selected_producer['producer_id']; ?>">
            
            <label for="producer">Producer:</label><br>
            <input type="text" name="producer" id="producer" value="<?php echo htmlspecialchars($selected_producer['producer'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            
            <br>
            <label for="region">Region:</label><br>
            <select name="region_id" id="region_id" required>
              <?php foreach ($regions as $region): ?>
                <option value="<?php echo $region['region_id']; ?>" <?php echo ($region['region_id'] == $selected_producer['region_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            
            <br><br>
            <label for="address">Address:</label>
            <textarea name="address" id="address" rows="6" cols="40" maxlength="200"><?php echo htmlspecialchars($selected_producer['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>

            <br><br>
            <label for="producer_desc">Description:</label>
            <textarea name="producer_desc" id="producer_desc" rows="20" cols="40" maxlength="1500"><?php echo htmlspecialchars($selected_producer['producer_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            
            <br><br>
            <input type="submit" name="update" value="Update Producer">
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
