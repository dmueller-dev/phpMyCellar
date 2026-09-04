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
  $tasting_note_id = null;

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['postTastingNote'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $bottle_id = filter_input(INPUT_POST, 'bottle_id', FILTER_VALIDATE_INT);
      $wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $tasting_date = sanitizeInput($_POST['tasting_date']);
      $tasting_note = sanitizeInput($_POST['tasting_note']);
      $flawed = sanitizeInput($_POST['flawed']);

      $scale = getRatingScale();
      $wset_enabled = isWsetSATEntryEnabled();

      $pts_20 = filter_input(INPUT_POST, 'pts_20', FILTER_VALIDATE_INT);
      if ($pts_20 === null && isset($_POST['dmpts']) && $_POST['dmpts'] !== '') {
        $pts_20 = filter_input(INPUT_POST, 'dmpts', FILTER_VALIDATE_INT);
      }
      $pts_100 = filter_input(INPUT_POST, 'pts_100', FILTER_VALIDATE_INT);

      $wset_balance = null;
      $wset_length = null;
      $wset_intensity = null;
      $wset_complexity = null;
      $wsetpts = null;

      if ($wset_enabled && isset($_POST['wset_balance']) && $_POST['wset_balance'] !== '') {
        $raw_b = filter_input(INPUT_POST, 'wset_balance', FILTER_VALIDATE_FLOAT);
        $raw_l = filter_input(INPUT_POST, 'wset_length', FILTER_VALIDATE_FLOAT);
        $raw_i = filter_input(INPUT_POST, 'wset_intensity', FILTER_VALIDATE_FLOAT);
        $raw_c = filter_input(INPUT_POST, 'wset_complexity', FILTER_VALIDATE_FLOAT);
        if ($raw_b !== false && $raw_b !== null) { $wset_balance = number_format($raw_b, 1, '.', ''); }
        if ($raw_l !== false && $raw_l !== null) { $wset_length = number_format($raw_l, 1, '.', ''); }
        if ($raw_i !== false && $raw_i !== null) { $wset_intensity = number_format($raw_i, 1, '.', ''); }
        if ($raw_c !== false && $raw_c !== null) { $wset_complexity = number_format($raw_c, 1, '.', ''); }
        if ($wset_balance !== null || $wset_length !== null || $wset_intensity !== null || $wset_complexity !== null) {
          $wsetpts = number_format((float)$wset_balance + (float)$wset_length + (float)$wset_intensity + (float)$wset_complexity, 1, '.', '');
        }
      }

      $favourite = sanitizeInput($_POST['favourite'] ?? 'no');
      $drink_from = filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT);
      $drink_through = filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT);
      $errorsDrinkDates = validateDrinkDatesInput($drink_from, $drink_through);
      $blind = sanitizeInput($_POST['blind']);
      $canPublish = hasPrivilege($conn, 'publish_tasting_note');
      $status = $canPublish ? sanitizeInput($_POST['status'] ?? 'draft') : 'draft';
      $img = sanitizeInput($_POST['img']);
      $img_class = sanitizeInput($_POST['img_class']);
      if ($img_class == "null") { $img_class = null; }
      $errorsImg = validateImageInput($img, $img_class);
      $errors = validateNoteInput(null, $wine_id, $tasting_date, $_SESSION['user_id'], $tasting_note, $flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $blind, $status, $img, $img_class, $favourite);

      if (empty($errorsImg) && empty($errorsDrinkDates) && empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertTastingNote($conn, $bottle_id, $wine_id, $tasting_date, $_SESSION['user_id'], $tasting_note, $flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $status, $blind, $img, $img_class, $favourite)) {
            $tasting_note_id = $conn->insert_id; // Retrieve note ID from database
            $conn->commit();
            $success_message = "Note added successfully.";
          } else {
            $conn->rollback();
            $errors[] = "Error adding tasting note.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error adding tasting note: " . $e->getMessage();
        }
      } else {
        if (!empty($errorsImg)) {
          $errors[]=$errorsImg[0];
        } elseif (!empty($errorsDrinkDates)) {
          $errors[]=$errorsDrinkDates[0];
        }
      }
    } elseif (isset($_POST['confirmConsume'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      $consume_bottle_id = filter_input(INPUT_POST, 'consume_bottle_id', FILTER_VALIDATE_INT);
      $consumption_date = sanitizeInput($_POST['consumption_date']);
      $tasting_note_id = filter_input(INPUT_POST, 'tasting_note_id', FILTER_VALIDATE_INT);
      
      if ($consume_bottle_id && !empty($consumption_date)) {
        if (markBottleAsConsumed($conn, $consume_bottle_id, $consumption_date, $tasting_note_id)) {
          $success_message = "Bottle #" . $consume_bottle_id . " marked as consumed.";
        } else {
          $errors[] = "Error marking bottle as consumed.";
        }
      } else {
        $errors[] = "Invalid inputs for marking bottle as consumed.";
      }
    }
  }

  // Get all bottles for the dropdown
  $bottles = getBottlesInCellar($conn);

  // Get selected bottle details
  $selected_bottle = null;
  if (isset($_GET['bottle_id'])) {
    $bottle_id = filter_input(INPUT_GET, 'bottle_id', FILTER_VALIDATE_INT);
    if ($bottle_id !== false && $bottle_id !== null) {
      $selected_bottle = getBottleDetails($conn, $bottle_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();

  $page_title = 'Add blind tasting note';

  $extra_head = <<<HTML
    <script>
      let allBottles = [];

      document.addEventListener("DOMContentLoaded", function() {
        const select = document.getElementById('bottle_id');
        if (select) {
          // Cache all options on page load (except the placeholder)
          for (let i = 1; i < select.options.length; i++) {
            const opt = select.options[i];
            allBottles.push({
              value: opt.value,
              text: opt.textContent,
              search: opt.getAttribute('data-search') || opt.textContent,
              selected: opt.selected
            });
          }
        }
      });

      function filterBottles() {
        const query = document.getElementById('searchBottleBox').value.toLowerCase().trim();
        const select = document.getElementById('bottle_id');
        if (!select) return;
        
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        // Clear options, preserving the placeholder
        while (select.options.length > 1) {
          select.remove(1);
        }

        // Re-populate with matching options
        allBottles.forEach(b => {
          const searchLower = b.search.toLowerCase();
          const matches = terms.every(term => searchLower.includes(term));

          if (matches) {
            const opt = document.createElement('option');
            opt.value = b.value;
            opt.textContent = b.text;
            opt.setAttribute('data-search', b.search);
            if (b.value === currentValue) {
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
        <h3>New blind tasting</h3>
        <?php
          if (!empty($errors)) {
            echo "<div style='color: red;'><ul>";
            foreach ($errors as $error) {
              echo "<li>" . $error . "</li>";
            }
            echo "</ul></div>";
          }

          if (!empty($success_message)) {
            echo "<div style='color: green; font-weight: bold; margin-bottom: 15px;'>" . $success_message . "</div>";
            
            if (isset($bottle_id) && $bottle_id && isset($tasting_date) && !empty($tasting_date) && $success_message === "Note added successfully.") {
              echo "<div style='margin: 20px 0; padding: 15px; border: 1px solid indianred; background-color: #fff8f8; border-radius: 4px;'>";
              echo "  <p style='margin-top: 0; color: #333;'>Would you like to mark bottle <strong>#" . htmlspecialchars($bottle_id, ENT_QUOTES, 'UTF-8') . "</strong> as consumed on <strong>" . htmlspecialchars($tasting_date, ENT_QUOTES, 'UTF-8') . "</strong>?</p>";
              echo "  <form method='POST' style='display: inline;'>";
              echo "    <input type='hidden' name='csrf_token' value='" . $csrf_token . "'>";
              echo "    <input type='hidden' name='consume_bottle_id' value='" . htmlspecialchars($bottle_id, ENT_QUOTES, 'UTF-8') . "'>";
              echo "    <input type='hidden' name='consumption_date' value='" . htmlspecialchars($tasting_date, ENT_QUOTES, 'UTF-8') . "'>";
              if (isset($tasting_note_id) && $tasting_note_id) {
                echo "    <input type='hidden' name='tasting_note_id' value='" . htmlspecialchars($tasting_note_id, ENT_QUOTES, 'UTF-8') . "'>";
              }
              echo "    <input type='submit' name='confirmConsume' value='Yes' style='background-color: indianred; color: white; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; font-family: inherit; font-weight: bold; margin-right: 10px;'>";
              echo "  </form>";
              echo "  <a href='/backend/blindTasting.php' style='text-decoration: none;'><button type='button' style='background-color: #ccc; color: #333; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; font-family: inherit; font-weight: bold;'>No</button></a>";
              echo "</div>";
            }
            
            echo "<p><a href='/backend/browseBottles.php'>Return to browse bottles</a></p>";
            echo "<p><a href='/backend/blindTasting.php'>New blind tasting note.</a></p>";
          }
        ?>

        <form method="GET">
          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Select a bottle:</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchBottleBox" onkeyup="filterBottles()"
              placeholder="🔍 Search bottle ID or info..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="bottle_id" id="bottle_id" onchange="this.form.submit()"
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="">Select a bottle</option>
              <?php foreach ($bottles as $bottle): ?>
                <?php
                  $search_parts = [
                    $bottle['bottle_id'],
                    $bottle['producer'] ?? '',
                    $bottle['name'] ?? '',
                    $bottle['vintage'] ?? '',
                    $bottle['grape'] ?? '',
                    $bottle['vineyard'] ?? ''
                  ];
                  $search_text = htmlspecialchars(implode(' ', array_filter($search_parts)), ENT_QUOTES, 'UTF-8');
                ?>
                <option value="<?php echo $bottle['bottle_id']; ?>" 
                  data-search="<?php echo $search_text; ?>"
                  <?php echo (isset($_GET['bottle_id']) && $_GET['bottle_id'] == $bottle['bottle_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($bottle['bottle_id'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($selected_bottle && empty($success_message)): ?>
          <h3>Write tasting note</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="bottle_id" value="<?php echo isset($_POST['bottle_id']) ? htmlspecialchars($_POST['bottle_id'], ENT_QUOTES, 'UTF-8') : $selected_bottle['bottle_id']; ?>">
            <input type="hidden" name="wine_id" value="<?php echo isset($_POST['wine_id']) ? htmlspecialchars($_POST['wine_id'], ENT_QUOTES, 'UTF-8') : $selected_bottle['wine_id']; ?>">
            
            <?php $wine_name=htmlspecialchars(getWineName($selected_bottle["nameconvention"],$selected_bottle["vintage"],$selected_bottle["name"],$selected_bottle["producer"],$selected_bottle["grape"],$selected_bottle["vineyard"]), ENT_QUOTES, 'UTF-8'); ?>
            <hr><details><summary>Reveal the wine?</summary><small><?php echo $wine_name; ?></small></details><hr style="margin-top:10px;"><br>

            <label for="tasting_date">Tasting date:</label>
            <br><input type="date" id="tasting_date" name="tasting_date" value="<?php echo isset($_POST['tasting_date']) ? htmlspecialchars(sanitizeInput($_POST['tasting_date']), ENT_QUOTES, 'UTF-8') : ''; ?>" required>

            <br><br>
            <label for="tasting_note">Tasting note:</label>
            <br><textarea name="tasting_note" rows="20" cols="40" placeholder="<p>...</p>" required><?php echo isset($_POST['tasting_note']) ? htmlspecialchars(sanitizeInput($_POST['tasting_note']), ENT_QUOTES, 'UTF-8') : ''; ?></textarea>

            <br><br>
            <label for="flawed">Flawed?</label><br>
            <select name="flawed" id="flawed" required>
              <option value="no" <?php echo (isset($_POST['flawed']) && $_POST['flawed'] == 'no') ? 'selected' : 'selected'; ?>>no</option>
              <option value="yes" <?php echo (isset($_POST['flawed']) && $_POST['flawed'] == 'yes') ? 'selected' : ''; ?>>yes</option>
            </select>

            <h3>Ratings</h3>

            <?php 
              $rating_scale = getRatingScale();
              $wset_enabled = isWsetSATEntryEnabled();
            ?>

            <?php if ($wset_enabled): ?>
              <strong>WSET Systematic Approach to Tasting</strong>
              <?php 
                $wset_mode = getWsetSATMode();
                if ($wset_mode === 'backend_only') {
                  echo " <small style='color:#777; font-weight:normal;'>(Internal &mdash; hidden on public site)</small>";
                } elseif ($wset_mode === 'logged_in') {
                  echo " <small style='color:#777; font-weight:normal;'>(Visible to logged-in users only)</small>";
                }
              ?>
              <br>
              <label for="wset_balance">Balance:</label>
              <select name="wset_balance" id="wset_balance">
                <option value="0" <?php echo (isset($_POST['wset_balance']) && $_POST['wset_balance'] == '0') ? 'selected' : 'selected'; ?>>0.0</option>
                <option value="0.5" <?php echo (isset($_POST['wset_balance']) && $_POST['wset_balance'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo (isset($_POST['wset_balance']) && $_POST['wset_balance'] == '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <br>
              <label for="wset_length">Length:</label>
              <select name="wset_length" id="wset_length">
                <option value="0" <?php echo (isset($_POST['wset_length']) && $_POST['wset_length'] == '0') ? 'selected' : 'selected'; ?>>0.0</option>
                <option value="0.5" <?php echo (isset($_POST['wset_length']) && $_POST['wset_length'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo (isset($_POST['wset_length']) && $_POST['wset_length'] == '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <br>
              <label for="wset_intensity">Intensity:</label>
              <select name="wset_intensity" id="wset_intensity">
                <option value="0" <?php echo (isset($_POST['wset_intensity']) && $_POST['wset_intensity'] == '0') ? 'selected' : 'selected'; ?>>0.0</option>
                <option value="0.5" <?php echo (isset($_POST['wset_intensity']) && $_POST['wset_intensity'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo (isset($_POST['wset_intensity']) && $_POST['wset_intensity'] == '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <br>
              <label for="wset_complexity">Complexity:</label>
              <select name="wset_complexity" id="wset_complexity">
                <option value="0" <?php echo (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] == '0') ? 'selected' : 'selected'; ?>>0.0</option>
                <option value="0.5" <?php echo (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] == '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <hr>
              <label for="wsetpts">WSET SAT points (0.0–4.0):</label>
              <select name="wsetpts" id="wsetpts" disabled>
                <option value="0" <?php echo (isset($_POST['wsetpts']) && $_POST['wsetpts'] == '0') ? 'selected' : ''; ?>>0.0</option>
                <option value="0.5" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '1') ? 'selected' : ''; ?>>1.0</option>
                <option value="1.5" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '1.5') ? 'selected' : ''; ?>>1.5</option>
                <option value="2" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '2') ? 'selected' : ''; ?>>2.0</option>
                <option value="2.5" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '2.5') ? 'selected' : ''; ?>>2.5</option>
                <option value="3" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '3') ? 'selected' : ''; ?>>3.0</option>
                <option value="3.5" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '3.5') ? 'selected' : ''; ?>>3.5</option>
                <option value="4" <?php echo (isset($_POST['wsetpts']) && $_POST['wset_balance']+$_POST['wset_length']+$_POST['wset_intensity']+$_POST['wset_complexity'] == '4') ? 'selected' : ''; ?>>4.0</option>
              </select>
              <br><br>
            <?php endif; ?>

            <?php if ($rating_scale === '100-point'): ?>
              <label for="pts_100">Score (100-point scale):</label>
              <br>
              <input type="number" id="pts_100" name="pts_100" min="50" max="100" step="1" value="<?php echo isset($_POST['pts_100']) ? htmlspecialchars($_POST['pts_100'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="50-100">
            <?php else: ?>
              <label for="pts_20"><?php echo htmlspecialchars(!empty($_SESSION['initials']) ? $_SESSION['initials'] : 'DM', ENT_QUOTES, 'UTF-8'); ?> points (20-point scale):</label>
              <br>
              <select name="pts_20" id="pts_20">
                <option value="" <?php echo (!isset($_POST['pts_20']) || $_POST['pts_20'] === '') ? 'selected' : ''; ?>></option>
                <?php for ($i = 0; $i <= 20; $i++): ?>
                  <option value="<?php echo $i; ?>" <?php echo (isset($_POST['pts_20']) && $_POST['pts_20'] !== '' && (int)$_POST['pts_20'] === $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
              </select>
            <?php endif; ?>
            <p id="suggestedScore" style="margin-top:5px;"></p>

            <label for="favourite">Favourite?</label><br>
            <select name="favourite" id="favourite" required>
              <option value="no" <?php echo (isset($_POST['favourite']) && $_POST['favourite'] == 'no') ? 'selected' : 'selected'; ?>>no</option>
              <option value="yes" <?php echo (isset($_POST['favourite']) && $_POST['favourite'] == 'yes') ? 'selected' : ''; ?>>yes</option>
            </select>

            <br><br>
            <label for="blind">Tasted blind?</label><br>
            <select name="blind" id="blind" required>
              <option value="not blind" <?php echo (isset($_POST['blind']) && $_POST['blind'] == 'not blind') ? 'selected' : 'selected'; ?>>not blind</option>
              <option value="blind" <?php echo (isset($_POST['blind']) && $_POST['blind'] == 'blind') ? 'selected' : ''; ?>>blind</option>
            </select>

            <h3>Drinking window</h3>
            <label for="drink_from">Drink from</label> <input type="text" id="drink_from" name="drink_from" maxlength="4" size="5" value="<?php echo isset($_POST['drink_from']) ? filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT) : ''; ?>" placeholder="yyyy">
            <label for="drink_through"> through</label> <input type="text" id="drink_through" name="drink_through" maxlength="4" size="5" value="<?php echo isset($_POST['drink_through']) ? filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT) : ''; ?>"  placeholder="yyyy">

            <h3>Image and publication</h3>
            <label for="img">Image:</label>
            <br><input type="text" size="40" name="img" placeholder="filename.jpg" value="<?php echo isset($_POST['img']) ? htmlspecialchars(sanitizeInput($_POST['img']), ENT_QUOTES, 'UTF-8') : ''; ?>">
            <br><label for="img_class">Image class:</label>
            <br>
            <select name="img_class" id="img_class">
              <option value="null" <?php echo (isset($_POST['img_class']) && $_POST['img_class'] == 'null') ? 'selected' : 'selected'; ?>></option>
              <option value="inline left" <?php echo (isset($_POST['img_class']) && $_POST['img_class'] == 'inline left') ? 'selected' : ''; ?>>inline left</option>
              <option value="block center" <?php echo (isset($_POST['img_class']) && $_POST['img_class'] == 'block center') ? 'selected' : ''; ?>>block center</option>
            </select>

            <br><br>
            <label for="status">Publish note?</label>
            <br>
            <?php $canPublish = hasPrivilege($conn, 'publish_tasting_note'); ?>
            <select name="status" id="status" required <?php echo (!$canPublish) ? 'disabled' : ''; ?>>
              <option value="draft" <?php echo (!$canPublish || empty($_POST['status']) || (isset($_POST['status']) && $_POST['status'] == 'draft')) ? 'selected' : ''; ?>>draft</option>
              <option value="published" <?php echo ($canPublish && isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>published</option>
            </select>
            <?php if (!$canPublish): ?>
              <p><small><i>Note: Once an admin publishes your tasting note, it will be locked and can no longer be edited.</i></small></p>
            <?php endif; ?>
            
            <input type="submit" name="postTastingNote" id="postTastingNote" value="Post tasting note">
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

<script>
  const wset_b = document.getElementById('wset_balance');
  const wset_l = document.getElementById('wset_length');
  const wset_i = document.getElementById('wset_intensity');
  const wset_c = document.getElementById('wset_complexity');
  const wsetpts = document.getElementById('wsetpts');
  const suggestedScore = document.getElementById('suggestedScore');
  const ratingScale = "<?php echo $rating_scale; ?>";

  function updateTotal() {
    if (!wset_b || !wset_l || !wset_i || !wset_c || !wsetpts) return;
    const total = Number(wset_b.value) + Number(wset_l.value) + Number(wset_i.value) + Number(wset_c.value);
    wsetpts.value = total;
    if (!suggestedScore) return;

    if (ratingScale === '100-point') {
      if (total === 0) {
        suggestedScore.innerHTML = "<small><em>Suggested score: < 70 (\"Poor / Faulty\")</em></small>";
      } else if (total === 0.5) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 70–74 (\"Below Average\")</em></small>";
      } else if (total === 1.0) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 75–79 (\"Acceptable / Mediocre\")</em></small>";
      } else if (total === 1.5) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 80–84 (\"Good\")</em></small>";
      } else if (total === 2.0) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 85–89 (\"Very Good\")</em></small>";
      } else if (total === 2.5) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 90–92 (\"Outstanding\")</em></small>";
      } else if (total === 3.0) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 93–94 (\"Outstanding\")</em></small>";
      } else if (total === 3.5) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 95–97 (\"Extraordinary\")</em></small>";
      } else if (total === 4.0) {
        suggestedScore.innerHTML = "<small><em>Suggested score: 98–100 (\"Extraordinary / Perfection\")</em></small>";
      }
    } else {
      if (total === 0) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 0 (\"poor\")</em></small>";
      } else if (total === 0.5) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 1 or 2 (\"subpar\")</em></small>";
      } else if (total === 1) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 3 or 4 (\"passable\")</em></small>";
      } else if (total === 1.5) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 5 or 6 (\"good\")</em></small>";
      } else if (total === 2) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 7 or 8 (\"good\")</em></small>";
      } else if (total === 2.5) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 9 or 10 (\"very good\")</em></small>";
      } else if (total === 3) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 11 or 12 (\"very good\")</em></small>";
      } else if (total === 3.5) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 13 or 14 (\"excellent\")</em></small>";
      } else if (total === 4) {
        suggestedScore.innerHTML = "<small><em>Suggested points: 15 to 20 (\"excellent\", \"grand vin\", or \"one-of-a-kind\")</em></small>";
      }
    }
  }

  if (wset_b && wset_l && wset_i && wset_c) {
    wset_b.addEventListener('change', updateTotal);
    wset_l.addEventListener('change', updateTotal);
    wset_i.addEventListener('change', updateTotal);
    wset_c.addEventListener('change', updateTotal);
    window.onload = updateTotal;
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
