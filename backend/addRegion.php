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
  $region = '';
  $country = '';
  $region_desc = '';

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $region = sanitizeInput($_POST['region']);
      $country = sanitizeInput($_POST['country']);
      $region_desc = sanitizeInput($_POST['region_desc']);
      $errors = validateRegionInput($region, $country, $region_desc);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertRegion($conn, $region, $country, $region_desc)) {
            $inserted_region_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Region inserted successfully. You can now:<br>" .
              "• <a href='addSubregion.php?region_id=" . $inserted_region_id . "'>Add a subregion for " . htmlspecialchars($region, ENT_QUOTES, 'UTF-8') . "</a><br>" .
              "• <a href='addAppellation.php?region_id=" . $inserted_region_id . "'>Add an appellation for " . htmlspecialchars($region, ENT_QUOTES, 'UTF-8') . "</a><br>" .
              "• <a href='addProducer.php?region_id=" . $inserted_region_id . "'>Add a producer in " . htmlspecialchars($region, ENT_QUOTES, 'UTF-8') . "</a>";
            // Clear the form values on successful submission
            $region = '';
            $country = '';
            $region_desc = '';
          } else {
            $conn->rollback();
            $errors[] = "Error inserting region";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating region: " . $e->getMessage();
        }
      }
    }
  } else {
    if (isset($_GET['country'])) {
      $country = sanitizeInput($_GET['country']);
    }
  }

  // Get all regions for the dropdown
  $countries = getCountries($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Add region';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Add a new region</h3>
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
            
          <label for="country">Country:</label><br>
          <select name="country" id="country" required>
            <option value="">Select a country</option>
            <?php foreach ($countries as $country_option): ?>
              <option value="<?php echo $country_option['country']; ?>" <?php echo ($country_option['country'] == $country) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($country_option['country'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="region">Region:</label><br>
          <input type="text" name="region" id="region" required maxlength="100" value="<?php echo htmlspecialchars($region, ENT_QUOTES, 'UTF-8'); ?>">
            
          <br><br>
          <label for="region_desc">Description:</label><br>
          <textarea name="region_desc" id="region_desc" rows="20" cols="40" maxlength="1500" placeholder="<p>...</p>"><?php echo htmlspecialchars($region_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
            
          <br><br>
          <input type="submit" name="update" value="Insert Region">
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
