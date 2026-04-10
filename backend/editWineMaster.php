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
      $master_id = filter_input(INPUT_POST, 'master_id', FILTER_VALIDATE_INT);
      $producer_id = filter_input(INPUT_POST, 'producer_id', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $subregion_id = filter_input(INPUT_POST, 'subregion_id', FILTER_VALIDATE_INT);
      $appellation_id = filter_input(INPUT_POST, 'appellation_id', FILTER_VALIDATE_INT);
      $vineyard_id = filter_input(INPUT_POST, 'vineyard_id', FILTER_VALIDATE_INT);
      $name = sanitizeInput($_POST['name']);
      $cuvee_yn = sanitizeInput($_POST['cuvee_yn']);
      $variety = sanitizeInput($_POST['grape']);
      $colour = sanitizeInput($_POST['colour']);
      $style = sanitizeInput($_POST['style']);
      $nameconvention = sanitizeInput($_POST['nameconvention']);
      $errors = validateMasterInput($conn, $master_id, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateMaster($conn, $master_id, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id, $name, $cuvee_yn, $variety, $colour, $style, $nameconvention)) {
            $conn->commit();
            $success_message = "Master updated successfully";
          } else {
            $conn->rollback();
            $errors[] = "Error updating master: Invalid master ID.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating master: " . $e->getMessage();
        }
      }
    }
  }

  // Get all wines for the dropdown
  $masters = getWineMasters($conn);
  $producers = getProducers($conn);
  $regions = getRegions($conn);
  $subregions = getSubregions($conn);
  $appellations = getAppellations($conn);
  $vineyards = getVineyards($conn);
  $varieties = getVarieties($conn);
  $colours = getColours($conn);
  $styles = getStyles($conn);
  $nameconventions = getNameConventions($conn);

  // Get selected master details
  $selected_wine = null;
  if (isset($_GET['master_id'])) {
    $master_id = filter_input(INPUT_GET, 'master_id', FILTER_VALIDATE_INT);
    if ($master_id !== false && $master_id !== null) {
      $selected_wine = getMasterDetails($conn, $master_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Edit master</title>
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
          <label for="master_id">Select Wine Master:</label>
          <select name="master_id" id="master_id" onchange="this.form.submit()">
            <option value="">Select a wine</option>
            <?php foreach ($masters as $master): ?>
            <option value="<?php echo $master['master_id']; ?>" <?php echo (isset($_GET['master_id']) && $_GET['master_id'] == $master['master_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($master['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($master['region'], ENT_QUOTES, 'UTF-8') . ": " .  htmlspecialchars($master['producer'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($master['grape'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($master['name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_wine): ?>
          <h3>Update Wine Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="master_id" value="<?php echo $selected_wine['master_id']; ?>">
            
            <label for="producer_id">Producer:</label><br>
            <select name="producer_id" id="producer_id" required>
              <option value="">Select a producer</option>
              <?php foreach ($producers as $producer): ?>
                <option value="<?php echo $producer['producer_id']; ?>" <?php echo ($selected_wine['producer_id'] == $producer['producer_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($producer['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($producer['region'], ENT_QUOTES, 'UTF-8') . ": " .  htmlspecialchars($producer['producer'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="region_id">Region:</label><br>
            <select name="region_id" id="region_id" required>
              <option value="">Select a region</option>
              <?php foreach ($regions as $region): ?>
                <option value="<?php echo $region['region_id']; ?>" <?php echo ($selected_wine['region_id'] == $region['region_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="subregion_id">Subregion:</label><br>
            <select name="subregion_id" id="subregion_id">
              <option value="" selected>Select a subregion</option>
              <?php foreach ($subregions as $subregion): ?>
                <option value="<?php echo $subregion['subregion_id']; ?>" <?php echo ($selected_wine['subregion_id'] == $subregion['subregion_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($subregion['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['subregion'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="appellation_id">Appellation:</label><br>
            <select name="appellation_id" id="appellation_id">
              <option value="" selected>Select an appellation</option>
              <?php foreach ($appellations as $appellation): ?>
                <option value="<?php echo $appellation['appellation_id']; ?>" <?php echo ($selected_wine['appellation_id'] == $appellation['appellation_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($appellation['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['appellation'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="vineyard_id">Vineyard:</label><br>
            <select name="vineyard_id" id="vineyard_id">
              <option value="" selected>Select a vineyard</option>
              <?php foreach ($vineyards as $vineyard): ?>
                <option value="<?php echo $vineyard['vineyard_id']; ?>" <?php echo ($selected_wine['vineyard_id'] == $vineyard['vineyard_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($vineyard['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($vineyard['region'], ENT_QUOTES, 'UTF-8') . ": " . (($vineyard['appellation']!=null) ? htmlspecialchars($vineyard['appellation'], ENT_QUOTES, 'UTF-8') . ": " : "") . htmlspecialchars($vineyard['vineyard'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="cuvee_yn">Cuvée yes/no:</label><br>
            <select name="cuvee_yn" id="cuvee_yn" required>
              <option value="yes" <?php echo ($selected_wine['cuvee_yn'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
              <option value="no" <?php echo ($selected_wine['cuvee_yn'] == 'no') ? 'selected' : ''; ?>>No</option>
            </select>

            <br><br>
            <label for="grape">Variety:</label><br>
            <select name="grape" id="grape" required>
              <option value="">Select a variety</option>
              <?php foreach ($varieties as $variety): ?>
                <option value="<?php echo $variety['grape']; ?>" <?php echo ($selected_wine['grape'] == $variety['grape']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($variety['grape'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="colour">Colour:</label><br>
            <select name="colour" id="colour" required>
              <option value="">Select a colour</option>
              <?php foreach ($colours as $colour): ?>
                <option value="<?php echo $colour['colour']; ?>" <?php echo ($selected_wine['colour'] == $colour['colour']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($colour['colour'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="style">Style:</label><br>
            <select name="style" id="style" required>
              <option value="">Select a style</option>
              <?php foreach ($styles as $style): ?>
                <option value="<?php echo $style['style']; ?>" <?php echo ($selected_wine['style'] == $style['style']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($style['style'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <label for="name">Name:</label><br>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($selected_wine['name'], ENT_QUOTES, 'UTF-8'); ?>" size="50" required maxlength="100">

            <br><br>
            <label for="nameconvention">Name convention:</label><br>
            <select name="nameconvention" id="nameconvention" required>
              <option value="">Select a convention</option>
              <?php foreach ($nameconventions as $nameconvention): ?>
                <option value="<?php echo $nameconvention['nameconvention']; ?>" <?php echo ($selected_wine['nameconvention'] == $nameconvention['nameconvention']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($nameconvention['nameconvention'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <br><br>
            <input type="submit" name="update" value="Update Master">
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
