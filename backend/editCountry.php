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
      $country = sanitizeInput($_POST['country']);
      $country_desc = sanitizeInput($_POST['country_desc']);
      $errors = validateCountryInput($country, $country_desc);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateCountry($conn, $country, $country_desc)) {
            $conn->commit();
            $success_message = "Country updated successfully";
          } else {
            $conn->rollback();
            $errors[] = "Error updating country: Invalid country";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating country: " . $e->getMessage();
        }
      }
    }
  }

  // Get all regions for the dropdown
  $countries = getCountries($conn);

  // Get selected region details
  $selected_country = null;
  if (isset($_GET['country_select'])) {
    $country = sanitizeInput($_GET['country_select']);
    if ($country !== false && $country !== null) {
      $selected_country = getCountryDetails($conn, $country);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Edit country</title>
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
        <h3>Edit a country</h3>
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
          <label for="country">Select country:</label>
          <select name="country_select" id="country_select" onchange="this.form.submit()">
            <option value="">Select a country</option>
            <?php foreach ($countries as $country): ?>
            <option value="<?php echo $country['country']; ?>" <?php echo (isset($_GET['country_select']) && $_GET['country_select'] == $country['country']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($country['country'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_country): ?>
          <h3>Update Country Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <label for="country">Country:</label>
            <input type="text" name="country" id="country" value="<?php echo htmlspecialchars($selected_country['country'], ENT_QUOTES, 'UTF-8'); ?>" readonly required maxlength="100"><br>
            
            <label for="country_desc">Description:</label>
            <textarea name="country_desc" id="country_desc" rows="20" cols="40" maxlength="1500"><?php echo htmlspecialchars($selected_country['country_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea><br>
            
            <input type="submit" name="update" value="Update Country">
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
