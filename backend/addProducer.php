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
  $producer = '';
  $region_id = '';
  $address = '';
  $producer_desc = '';

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $producer = sanitizeInput($_POST['producer']);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $address = sanitizeInput($_POST['address']);
      $producer_desc = sanitizeInput($_POST['producer_desc']);
      $errors = validateProducerInput($producer, $region_id, $address, $producer_desc);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertProducer($conn, $producer, $region_id, $address, $producer_desc)) {
            $conn->commit();
            $success_message = "Producer inserted successfully";
            // Clear the form values on successful submission
            $producer = '';
            $region_id = '';
            $address = '';
            $producer_desc = '';
          } else {
            $conn->rollback();
            $errors[] = "Error inserting producer";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating producer: " . $e->getMessage();
        }
      }
    }
  }

  // Get all regions for the dropdown
  $regions = getRegions($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Add producer';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Add a new producer</h3>
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

          <label for="producer">Producer:</label><br>
          <input type="text" name="producer" id="producer" required maxlength="100" value="<?php echo htmlspecialchars($producer, ENT_QUOTES, 'UTF-8'); ?>">
            
          <br><br>
          <label for="region_id">Region:</label><br>
          <select name="region_id" id="region_id" required>
            <option value="">Select a region</option>
            <?php foreach ($regions as $region): ?>
              <option value="<?php echo $region['region_id']; ?>" <?php echo ($region['region_id'] == $region_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="address">Address:</label><br>
          <textarea name="address" id="address" rows="20" cols="40" maxlength="1500"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
            
          <br><br>
          <label for="producer_desc">Description:</label><br>
          <textarea name="producer_desc" id="producer_desc" rows="20" cols="40" maxlength="1500" placeholder="<p>...</p>"><?php echo htmlspecialchars($producer_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
            
          <br><br>
          <input type="submit" name="update" value="Insert Producer">
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
