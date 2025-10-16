<?php
// Include the database configuration file
require 'dbConnectBackend.php';

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

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Edit subregion</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="https://dmueller.com/styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">
</head>

<body>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <li><a href="index.php" title="Backend Home">Index</a></li>
      <li><a href="browseWines.php" title="Show all wines">Wines</a></li>
      <li><a href="browseBottles.php" title="Show all bottles">Bottles</a></li>
      <li><a href="winemenu.php" title="Show wine menu">Wine menu</a></li>
      <li class="right"><a href="https://dmueller.com" title="Frontend">Go to website</a></li>
    </ul>
  </nav>
</header>

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

<div class="footer">
  <footer>
    <p style="float:right;margin-top:0;"><a href="/impressum.php" title="Impressum / Imprint">Impressum / Imprint</a></p>
    <address>
      Contact details:<br>
      Dominik Mueller<br>
      Muehlstr. 24<br>
      76532 Baden-Baden<br>
      GERMANY<br><br>
      E-Mail: <a href="mailto:dm@dmueller.com" title="Contact me by email">dm@dmueller.com</a>
    </address>
    <p align="center"><small>This website uses <strong>no</strong> cookies. Have fun!</small></p>
  </footer>
</div>

</body>

</html>

<?php
// Close the database connection
$conn->close();
?>