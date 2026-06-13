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
      $appellation_id = filter_input(INPUT_POST, 'appellation_id', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $appellation = sanitizeInput($_POST['appellation']);
      $appellation_desc = sanitizeInput($_POST['appellation_desc']);
      $errors = validateAppellationInput($appellation, $region_id, $appellation_desc);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateAppellation($conn, $appellation_id, $region_id, $appellation, $appellation_desc)) {
            $conn->commit();
            $success_message = "Appellation updated successfully";
          } else {
            $conn->rollback();
            $errors[] = "Error updating appellation: Invalid region";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating appellation: " . $e->getMessage();
        }
      }
    }
  }

  // Get all appellations for the dropdown
  $regions = getRegions($conn);
  $appellations = getAppellations($conn);

  // Get selected appellation details
  $selected_appellation = null;
  if (isset($_GET['appellation_id'])) {
    $appellation_id = filter_input(INPUT_GET, 'appellation_id', FILTER_VALIDATE_INT);
    if ($appellation_id !== false && $appellation_id !== null) {
      $selected_appellation = getAppellationDetails($conn, $appellation_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Edit appellation';

  $extra_head = <<<HTML
    <script>
      let allAppellations = [];
      let allRegions = [];

      document.addEventListener("DOMContentLoaded", function() {
        const appellationSelect = document.getElementById('appellation_id');
        if (appellationSelect) {
          // Cache all options on page load (except the placeholder)
          for (let i = 1; i < appellationSelect.options.length; i++) {
            const opt = appellationSelect.options[i];
            allAppellations.push({
              value: opt.value,
              text: opt.textContent,
              selected: opt.selected
            });
          }
        }

        const regionSelect = document.getElementById('region_id');
        if (regionSelect) {
          // Cache all options on page load
          for (let i = 0; i < regionSelect.options.length; i++) {
            const opt = regionSelect.options[i];
            allRegions.push({
              value: opt.value,
              text: opt.textContent,
              selected: opt.selected
            });
          }
        }
      });

      function filterAppellations() {
        const query = document.getElementById('searchAppellationBox').value.toLowerCase().trim();
        const select = document.getElementById('appellation_id');
        if (!select) return;
        
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        // Clear options, preserving the placeholder
        while (select.options.length > 1) {
          select.remove(1);
        }

        // Re-populate with matching options
        allAppellations.forEach(r => {
          const textLower = r.text.toLowerCase();
          const matches = terms.every(term => textLower.includes(term));

          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value;
            opt.textContent = r.text;
            if (r.value === currentValue) {
              opt.selected = true;
            }
            select.appendChild(opt);
          }
        });
      }

      function filterRegions() {
        const query = document.getElementById('searchRegionBox').value.toLowerCase().trim();
        const select = document.getElementById('region_id');
        if (!select) return;
        
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        // Clear options
        while (select.options.length > 0) {
          select.remove(0);
        }

        // Re-populate with matching options
        allRegions.forEach(r => {
          const textLower = r.text.toLowerCase();
          const matches = terms.every(term => textLower.includes(term));

          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value;
            opt.textContent = r.text;
            if (r.value === currentValue) {
              opt.selected = true;
            }
            select.appendChild(opt);
          }
        });
      }
    </script>
  HTML;

  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Edit an appellation</h3>
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
          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Select Appellation:</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchAppellationBox" onkeyup="filterAppellations()"
              placeholder="🔍 Search appellation..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="appellation_id" id="appellation_id" onchange="this.form.submit()"
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="">Select an appellation</option>
              <?php foreach ($appellations as $appellation): ?>
              <option value="<?php echo $appellation['appellation_id']; ?>" <?php echo (isset($_GET['appellation_id']) && $_GET['appellation_id'] == $appellation['appellation_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($appellation['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['appellation'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($selected_appellation): ?>
          <h3>Update Appellation Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="appellation_id" value="<?php echo $selected_appellation['appellation_id']; ?>">
            
            <label for="appellation">Appellation:</label><br>
            <input type="text" name="appellation" id="appellation" value="<?php echo htmlspecialchars($selected_appellation['appellation'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            
            <br>
            <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Region:</label>
            <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
              <input type="text" id="searchRegionBox" onkeyup="filterRegions()"
                placeholder="🔍 Search region..."
                style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
                autocomplete="off">
              <select name="region_id" id="region_id" required
                style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
                <?php foreach ($regions as $region): ?>
                  <option value="<?php echo $region['region_id']; ?>" <?php echo ($region['region_id'] == $selected_appellation['region_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <br><br>
            <label for="appellation_desc">Description:</label>
            <textarea name="appellation_desc" id="appellation_desc" rows="20" cols="40" maxlength="1500"><?php echo htmlspecialchars($selected_appellation['appellation_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            
            <br><br>
            <input type="submit" name="update" value="Update Appellation">
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
