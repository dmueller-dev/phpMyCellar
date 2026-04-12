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
  $producer_id = '';
  $region_id = '';
  $subregion_id = '';
  $appellation_id = '';
  $vineyard_id = '';
  $name = '';
  $cuvee_yn = '';
  $grape = '';
  $colour = '';
  $style = '';
  $nameconvention = '';

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
      $subregion_id = filter_input(INPUT_POST, 'subregion_id', FILTER_VALIDATE_INT);
      $appellation_id = filter_input(INPUT_POST, 'appellation_id', FILTER_VALIDATE_INT);
      $vineyard_id = filter_input(INPUT_POST, 'vineyard_id', FILTER_VALIDATE_INT);
      $cuvee_yn = sanitizeInput($_POST['cuvee_yn']);
      $grape = sanitizeInput($_POST['grape']);
      $name = sanitizeInput($_POST['name']);
      $colour = sanitizeInput($_POST['colour']);
      $style = sanitizeInput($_POST['style']);
      $nameconvention = sanitizeInput($_POST['nameconvention']);
      $errors = validateMasterInput($conn, 0, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertWineMaster($conn, $name, $nameconvention, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id, $grape, $cuvee_yn, $colour, $style)) {
            $conn->commit();
            $success_message = "Wine master inserted successfully";
            // Clear the form values on successful submission
            $producer_id = '';
            $region_id = '';
            $subregion_id = '';
            $appellation_id = '';
            $vineyard_id = '';
            $name = '';
            $cuvee_yn = '';
            $grape = '';
            $colour = '';
            $style = '';
            $nameconvention = '';
          } else {
            $conn->rollback();
            $errors[] = "Error inserting wine master";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating wine master: " . $e->getMessage();
        }
      }
    }
  }

  // Get all information for the dropdowns
  $producers = getProducers($conn);
  $regions = getRegions($conn);
  $subregions = getSubregions($conn);
  $appellations = getAppellations($conn);
  $vineyards = getVineyards($conn);
  $varieties = getVarieties($conn);
  $colours = getColours($conn);
  $styles = getStyles($conn);
  $nameconventions = getNameConventions($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Add master';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Add a new wine master</h3>
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

          <label for="producer_id">Producer:</label><br>
          <select name="producer_id" id="producer_id" required>
            <option value="">Select a producer</option>
            <?php foreach ($producers as $producer): ?>
              <option value="<?php echo $producer['producer_id']; ?>" <?php echo ($producer['producer_id'] == $producer_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($producer['country'], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($producer['region'], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($producer['producer'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="region_id">Region:</label><br>
          <select name="region_id" id="region_id" required>
            <option value="">Select a region</option>
            <?php foreach ($regions as $region): ?>
              <option value="<?php echo $region['region_id']; ?>" <?php echo ($region['region_id'] == $region_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="subregion_id">Subregion:</label><br>
          <select name="subregion_id" id="subregion_id">
            <option value="" selected>Select a subregion</option>
            <?php foreach ($subregions as $subregion): ?>
              <option value="<?php echo $subregion['subregion_id']; ?>" <?php echo ($subregion['subregion_id'] == $subregion_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($subregion['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['subregion'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="appellation_id">Appellation:</label><br>
          <select name="appellation_id" id="appellation_id">
            <option value="" selected>Select an appellation</option>
            <?php foreach ($appellations as $appellation): ?>
              <option value="<?php echo $appellation['appellation_id']; ?>" <?php echo ($appellation['appellation_id'] == $appellation_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($appellation['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['appellation'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="vineyard_id">Vineyard:</label><br>
          <select name="vineyard_id" id="vineyard_id">
            <option value="" selected>Select a vineyard</option>
            <?php foreach ($vineyards as $vineyard): ?>
              <option value="<?php echo $vineyard['vineyard_id']; ?>" <?php echo ($vineyard['vineyard_id'] == $vineyard_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($vineyard['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($vineyard['region'], ENT_QUOTES, 'UTF-8') . ": " . (($vineyard['appellation']!=null) ? htmlspecialchars($vineyard['appellation'], ENT_QUOTES, 'UTF-8') . ": " : "") . htmlspecialchars($vineyard['vineyard'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="cuvee_yn">Cuvée yes/no:</label><br>
          <select name="cuvee_yn" id="cuvee_yn" required>
            <option value="">Select an option</option>
            <option value="yes" <?php echo ($cuvee_yn == 'yes') ? 'selected' : ''; ?>>Yes</option>
            <option value="no" <?php echo ($cuvee_yn == 'no') ? 'selected' : ''; ?>>No</option>
          </select>

          <br><br>
          <label for="grape">Variety:</label><br>
          <select name="grape" id="grape" required>
            <option value="">Select a variety</option>
            <?php foreach ($varieties as $variety): ?>
              <option value="<?php echo $variety['grape']; ?>" <?php echo ($variety['grape'] == $grape) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($variety['grape'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="colour">Colour:</label><br>
          <select name="colour" id="colour" required>
            <option value="">Select a colour</option>
            <?php foreach ($colours as $colour_db): ?>
              <option value="<?php echo $colour_db['colour']; ?>" <?php echo ($colour_db['colour'] == $colour) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($colour_db['colour'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="style">Style:</label><br>
          <select name="style" id="style" required>
            <option value="">Select a style</option>
            <?php foreach ($styles as $style_db): ?>
              <option value="<?php echo $style_db['style']; ?>" <?php echo ($style_db['style'] == $style) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($style_db['style'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="name">Name:</label><br>
          <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" size="50" required maxlength="100">

          <br><br>
          <label for="nameconvention">Name convention:</label><br>
          <select name="nameconvention" id="nameconvention" required>
            <option value="">Select a convention</option>
            <?php foreach ($nameconventions as $nameconvention_db): ?>
              <option value="<?php echo $nameconvention_db['nameconvention']; ?>" <?php echo ($nameconvention_db['nameconvention'] == $nameconvention) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($nameconvention_db['nameconvention'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <input type="submit" name="update" value="Insert Wine Master">
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
