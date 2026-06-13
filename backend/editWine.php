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

<?php
  $page_title = 'Edit wine';

  $extra_head = <<<HTML
    <script>
      let allWines = [];
      let allMasters = [];

      document.addEventListener("DOMContentLoaded", function() {
        const wineSelect = document.getElementById('wine_id');
        if (wineSelect) {
          for (let i = 1; i < wineSelect.options.length; i++) {
            const opt = wineSelect.options[i];
            allWines.push({
              value: opt.value,
              text: opt.textContent,
              selected: opt.selected
            });
          }
        }

        const masterSelect = document.getElementById('master_id');
        if (masterSelect) {
          for (let i = 1; i < masterSelect.options.length; i++) {
            const opt = masterSelect.options[i];
            allMasters.push({
              value: opt.value,
              text: opt.textContent,
              selected: opt.selected
            });
          }
        }
      });

      function filterWines() {
        const query = document.getElementById('searchWineBox').value.toLowerCase().trim();
        const select = document.getElementById('wine_id');
        if (!select) return;
        
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        while (select.options.length > 1) {
          select.remove(1);
        }

        allWines.forEach(w => {
          const textLower = w.text.toLowerCase();
          const matches = terms.every(term => textLower.includes(term));

          if (matches) {
            const opt = document.createElement('option');
            opt.value = w.value;
            opt.textContent = w.text;
            if (w.value === currentValue) {
              opt.selected = true;
            }
            select.appendChild(opt);
          }
        });
      }

      function filterMasters() {
        const query = document.getElementById('searchMasterBox').value.toLowerCase().trim();
        const select = document.getElementById('master_id');
        if (!select) return;
        
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        while (select.options.length > 1) {
          select.remove(1);
        }

        allMasters.forEach(m => {
          const textLower = m.text.toLowerCase();
          const matches = terms.every(term => textLower.includes(term));

          if (matches) {
            const opt = document.createElement('option');
            opt.value = m.value;
            opt.textContent = m.text;
            if (m.value === currentValue) {
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
          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Select Wine:</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchWineBox" onkeyup="filterWines()"
              placeholder="🔍 Search wine..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="wine_id" id="wine_id" onchange="this.form.submit()"
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="">Select a wine</option>
              <?php foreach ($wines as $wine): ?>
              <option value="<?php echo $wine['wine_id']; ?>" <?php echo (isset($_GET['wine_id']) && $_GET['wine_id'] == $wine['wine_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($wine['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($wine['region'], ENT_QUOTES, 'UTF-8') . ": " . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']) ; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($selected_wine): ?>
          <h3>Update Wine Details</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="wine_id" value="<?php echo $selected_wine['wine_id']; ?>">
            
            <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Master:</label>
            <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
              <input type="text" id="searchMasterBox" onkeyup="filterMasters()"
                placeholder="🔍 Search master..."
                style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
                autocomplete="off">
              <select name="master_id" id="master_id" required
                style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
                <option value="">Select a master</option>
                <?php foreach ($masters as $master): ?>
                  <option value="<?php echo $master['master_id']; ?>" <?php echo ($selected_wine['master_id'] == $master['master_id']) ? 'selected' : ''; ?>>
                    <?php echo
                      htmlspecialchars($master['country'], ENT_QUOTES, 'UTF-8') . ": " .
                      htmlspecialchars($master['region'], ENT_QUOTES, 'UTF-8') . ": " .
                      htmlspecialchars($master['producer'], ENT_QUOTES, 'UTF-8') . ": " .
                      htmlspecialchars($master['grape'], ENT_QUOTES, 'UTF-8') . ": " .
                      (!empty($master['vineyard']) ? htmlspecialchars($master['vineyard'], ENT_QUOTES, 'UTF-8') . ": " : "") .
                      htmlspecialchars($master['name'], ENT_QUOTES, 'UTF-8');
                    ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
