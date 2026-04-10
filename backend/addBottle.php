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
  $numBottles = '';
  $wine_id = '';
  $format = '';
  $bin_id = '';
  $store_id = '';
  $purchase_date = '';
  $purchase_price = '';
  $arrival_date = '';
  $status = '';
  $drink_from = '';
  $drink_through = '';
  $consumption_date = '';
  $consumption_note = '';
  $for_sale = '';
  $note_id = '';

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $numBottles = filter_input(INPUT_POST, 'numBottles', FILTER_VALIDATE_INT);
      $wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $format = sanitizeInput($_POST['format']);
      $bin_id = filter_input(INPUT_POST, 'bin_id', FILTER_VALIDATE_INT);
      $store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
      $purchase_date = sanitizeInput($_POST['purchase_date']);
      $purchase_price = number_format(filter_input(INPUT_POST, 'purchase_price', FILTER_VALIDATE_FLOAT),2,'.','');
      $arrival_date = sanitizeInput($_POST['arrival_date']);
      $status = sanitizeInput($_POST['status']);
      $drink_from = filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT);
      $drink_through = filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT);
      $consumption_date = sanitizeInput($_POST['consumption_date']);
      $consumption_note = sanitizeInput($_POST['consumption_note']);
      $for_sale = sanitizeInput($_POST['for_sale']);
      $note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
      $errors = validateBottleInput(0, $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          for ($n = 1; $n <= $numBottles; $n++) {
            if (insertBottle($conn, $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id)) {
              $conn->commit();
              if ($n>=$numBottles) {
                $success_message = $numBottles . " bottle(s) inserted successfully";
                // Clear the form values on successful submission
                $numBottles = '';
                $wine_id = '';
                $format = '';
                $bin_id = '';
                $store_id = '';
                $purchase_date = '';
                $purchase_price = '';
                $arrival_date = '';
                $status = '';
                $drink_from = '';
                $drink_through = '';
                $consumption_date = '';
                $consumption_note = '';
                $for_sale = '';
                $note_id = '';
              }
            } else {
              $conn->rollback();
              $errors[] = "Error inserting bottles";
            }
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error inserting bottles: " . $e->getMessage();
        }
      }
    }
  }

  // Get all information for the dropdowns
  $wines = getWines($conn);
  $formats = getFormats($conn);
  $stores = getStores($conn);
  $storageLocations = getStorageLocations($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Add bottles</title>
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
      <li><a class="active" href="browseBottles.php" title="Show all bottles">Bottles</a></li>
      <li><a href="winemenu.php" title="Show wine menu">Wine menu</a></li>
      <li class="right"><a href="https://dmueller.com" title="Frontend">Go to website</a></li>
    </ul>
  </nav>
</header>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Add new bottles</h3>
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
            
          <label for="wine_id">Wine:</label><br>
          <select name="wine_id" id="wine_id" required>
            <option value="">Select a wine</option>
            <?php foreach ($wines as $wine): ?>
              <option value="<?php echo $wine['wine_id']; ?>" <?php echo ($wine['wine_id'] == $wine_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($wine['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($wine['region'], ENT_QUOTES, 'UTF-8') . ": " . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']) ; ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="format">Format:</label><br>
          <select name="format" id="format" required>
            <option value="">Select a format</option>
            <?php foreach ($formats as $format_db): ?>
              <option value="<?php echo $format_db['format']; ?>" <?php echo ($format_db['format'] == $format) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($format_db['format'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($format_db['format_desc'], ENT_QUOTES, 'UTF-8') . ")"; ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="bin_id">Storage location:</label><br>
          <select name="bin_id" id="bin_id">
            <option value="" selected>Select a storage location</option>
            <?php foreach ($storageLocations as $storageLocation): ?>
              <option value="<?php echo $storageLocation['bin_id']; ?>" <?php echo ($storageLocation['bin_id'] == $bin_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($storageLocation['cellar_name'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($storageLocation['bin_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="store_id">Purchased from:</label><br>
          <select name="store_id" id="store_id" required>
            <option value="">Select a store</option>
            <?php foreach ($stores as $store): ?>
              <option value="<?php echo $store['store_id']; ?>" <?php echo ($store['store_id'] == $store_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($store['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="purchase_date">Purchase date:</label><br>
          <input type="date" id="purchase_date" name="purchase_date" value="<?php echo htmlspecialchars(sanitizeInput($purchase_date), ENT_QUOTES, 'UTF-8'); ?>" required>
            
          <br><br>
          <label for="purchase_price">Purchase price:</label><br>
          <input type="text" id="purchase_price" name="purchase_price" maxlength="7" size="8" value="<?php echo $purchase_price; ?>" placeholder="0.00">

          <br><br>
          <label for="arrival_date">Arrival date:</label><br>
          <input type="date" id="arrival_date" name="arrival_date" value="<?php echo ($arrival_date!=null) ? htmlspecialchars(sanitizeInput($arrival_date), ENT_QUOTES, 'UTF-8') : ''; ?>">
            
          <br><br>
          <label for="status">Status:</label><br>
          <select name="status" id="status" required>
            <option value="in cellar" <?php echo ($status == 'in cellar') ? 'selected' : ''; ?>>in cellar</option>
            <option value="pending delivery" <?php echo ($status == 'pending delivery') ? 'selected' : ''; ?>>pending delivery</option>
            <option value="consumed" <?php echo ($status == 'consumed') ? 'selected' : ''; ?>>consumed</option>
            <option value="lost" <?php echo ($status == 'lost') ? 'selected' : ''; ?>>lost</option>
            <option value="sold" <?php echo ($status == 'sold') ? 'selected' : ''; ?>>sold</option>
          </select>

          <br><br>
          <label for="drink_from">Drink from</label> <input type="text" id="drink_from" name="drink_from" maxlength="4" size="5" value="<?php echo ($drink_from!=null) ? $drink_from : ''; ?>" placeholder="yyyy">
          <label for="drink_through">through</label> <input type="text" id="drink_through" name="drink_through" maxlength="4" size="5" value="<?php echo ($drink_through!=null) ? $drink_through : ''; ?>" placeholder="yyyy">

          <br><br>
          <label for="consumption_date">Consumption date:</label><br>
          <input type="date" id="consumption_date" name="consumption_date" value="<?php echo ($consumption_date!=null) ? htmlspecialchars(sanitizeInput($consumption_date), ENT_QUOTES, 'UTF-8') : ''; ?>">

          <br><br>
	  <label for="consumption_note">Consumption note:</label>
          <br><textarea name="consumption_note" rows="5" cols="40" placeholder="<p>...</p>"><?php echo ($consumption_note!=null) ? htmlspecialchars(sanitizeInput($consumption_note), ENT_QUOTES, 'UTF-8') : ''; ?></textarea>

          <br><br>
          <label for="for_sale">For sale?</label><br>
          <select name="for_sale" id="for_sale" required>
            <option value="no" <?php echo ($for_sale == 'no') ? 'selected' : ''; ?>>no</option>
            <option value="yes" <?php echo ($for_sale == 'yes') ? 'selected' : ''; ?>>yes</option>
          </select>

          <br><br>
          <label for="note_id">Tasting note ID:</label> <input type="text" id="note_id" name="note_id" maxlength="6" size="7" value="<?php echo ($note_id!=null) ? $note_id : ''; ?>" placeholder="00000">

          <br><br>
          <label for="numBottles">Number of bottles:</label><br>
          <select name="numBottles" id="numBottles" required>
            <option value="1" selected>1</option>
            <option value="2" <?php echo ($numBottles == 2) ? 'selected' : ''; ?>>2</option>
            <option value="3" <?php echo ($numBottles == 3) ? 'selected' : ''; ?>>3</option>
            <option value="4" <?php echo ($numBottles == 4) ? 'selected' : ''; ?>>4</option>
            <option value="5" <?php echo ($numBottles == 5) ? 'selected' : ''; ?>>5</option>
            <option value="6" <?php echo ($numBottles == 6) ? 'selected' : ''; ?>>6</option>
            <option value="7" <?php echo ($numBottles == 7) ? 'selected' : ''; ?>>7</option>
            <option value="8" <?php echo ($numBottles == 8) ? 'selected' : ''; ?>>8</option>
            <option value="9" <?php echo ($numBottles == 9) ? 'selected' : ''; ?>>9</option>
            <option value="10" <?php echo ($numBottles == 10) ? 'selected' : ''; ?>>10</option>
            <option value="11" <?php echo ($numBottles == 11) ? 'selected' : ''; ?>>11</option>
            <option value="12" <?php echo ($numBottles == 12) ? 'selected' : ''; ?>>12</option>
          </select>
            
          <br><br>
          <input type="submit" name="update" value="Add Bottles">
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
