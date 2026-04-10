<?php
// Define a constant to protect included files from direct access
if (!defined('INCLUDED_VIA_APP')) {
  define('INCLUDED_VIA_APP', true);
}

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
    $wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
    $master_id = filter_input(INPUT_POST, 'master_id', FILTER_VALIDATE_INT);
    $ct_id = filter_input(INPUT_POST, 'ct_id', FILTER_VALIDATE_INT);
    $vintage = filter_input(INPUT_POST, 'vintage', FILTER_VALIDATE_INT);
    $wine_desc = sanitizeInput($_POST['wine_desc']);
    $errors = validateWineInput($wine_id, $master_id, $vintage, $wine_desc, $ct_id);
    if (empty($errors)) {
      // Start transaction
      $conn->begin_transaction();
      try {
        if (updateWine($conn, $wine_id, $master_id, $vintage, $wine_desc, $ct_id)) {
          $conn->commit();
          $success_message = "Wine updated successfully";
        } else {
          $conn->rollback();
          $errors[] = "Error updating wine: Invalid wine or master ID.";
        }
      } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Error updating wine: " . $e->getMessage();
      }
    }
  }
}

// Get all wines for the dropdown
$wines = getWines($conn);
$masters = getWineMasters($conn);
$vintages = getVintages($conn);

// Get selected wine details
$selected_wine = null;
if (isset($_GET['wine_id'])) {
  $wine_id = filter_input(INPUT_GET, 'wine_id', FILTER_VALIDATE_INT);
  if ($wine_id !== false && $wine_id !== null) {
    $selected_wine = getWineDetails($conn, $wine_id);
  }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Edit wine</title>
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
      <li><a class="active" href="browseWines.php" title="Show all wines">Wines</a></li>
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
        <h3>Edit a wine</h3>
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
          <label for="wine_id">Select Wine:</label>
          <select name="wine_id" id="wine_id" onchange="this.form.submit()">
            <option value="">Select a wine</option>
            <?php foreach ($wines as $wine): ?>
            <option value="<?php echo $wine['wine_id']; ?>" <?php echo (isset($_GET['wine_id']) && $_GET['wine_id'] == $wine['wine_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($wine['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($wine['region'], ENT_QUOTES, 'UTF-8') . ": " . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']) ; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_wine): ?>
          <h3>Update Wine Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="wine_id" value="<?php echo $selected_wine['wine_id']; ?>">
            
            <label for="master_id">Master:</label>
            <select name="master_id" id="master_id" required>
              <option value="">Select a master</option>
              <?php foreach ($masters as $master): ?>
                <option value="<?php echo $master['master_id']; ?>" <?php echo ($selected_wine['master_id'] == $master['master_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($master['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($master['region'], ENT_QUOTES, 'UTF-8') . ": " .  htmlspecialchars($master['producer'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($master['grape'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($master['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            
            <br><br>
            <label for="vintage">Vintage:</label>
            <select name="vintage" id="vintage" required>
              <option value="0">NV</option>
              <?php foreach ($vintages as $vintage): ?>
                <option value="<?php echo $vintage['vintage']; ?>" <?php echo ($selected_wine['vintage'] == $vintage['vintage']) ? 'selected' : ''; ?>>
                  <?php echo $vintage['vintage']; ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="ct_id">CT ID:</label>
            <input type="text" name="ct_id" id="ct_id" value="<?php echo htmlspecialchars($selected_wine['ct_id'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <br><br>
            <label for="wine_desc">Description:</label>
            <textarea name="wine_desc" id="wine_desc" rows="20" cols="40" maxlength="2000"><?php echo htmlspecialchars($selected_wine['wine_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea><br>
            
            <input type="submit" name="update" value="Update Wine">
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
