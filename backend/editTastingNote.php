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
      $note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
      $wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $tasting_date = sanitizeInput($_POST['tasting_date']);
      $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
      $tasting_note = sanitizeInput($_POST['tasting_note']);
      $flawed_yn = sanitizeInput($_POST['flawed_yn']);

      $scale = getRatingScale();
      $wset_enabled = isWsetSATEnabled();
      
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
      $canEditAll = hasPrivilege($conn, 'edit_all_tasting_notes');
      $canPublish = hasPrivilege($conn, 'publish_tasting_note');
      $check_note = getNoteDetails($conn, $note_id);

      if (!$canEditAll) {
        if (!$check_note || $check_note['user_id'] != $_SESSION['user_id']) {
          $errors[] = "You do not have permission to edit this tasting note.";
        } elseif ($check_note['status'] === 'published' && !$canPublish) {
          $errors[] = "This tasting note has been published and can no longer be edited.";
        }
      }

      $status = $canPublish ? sanitizeInput($_POST['status'] ?? 'draft') : ($check_note ? $check_note['status'] : 'draft');
      $img = sanitizeInput($_POST['img']);
      $img_class = sanitizeInput($_POST['img_class']);
      if ($img_class == "null") { $img_class = null; }
      $errorsImg = validateImageInput($img, $img_class);
      $errors = validateNoteInput($note_id, $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $blind, $status, $img, $img_class, $favourite);

      if (empty($errorsDrinkDates) && empty($errorsImg) && empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (updateTastingNote($conn, $note_id, $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $blind, $status, $img, $img_class, $favourite)) {
            $conn->commit();
            $success_message = "Tasting note updated successfully";
          } else {
            $conn->rollback();
            $errors[] = "Error updating tasting note: Invalid note ID?";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating tasting note: " . $e->getMessage();
        }
      } else {
        if (!empty($errorsImg)) {
          $errors[]=$errorsImg[0];
        } elseif (!empty($errorsDrinkDates)) {
          $errors[]=$errorsDrinkDates[0];
        }
      }
    }
  }

  $canEditAll = hasPrivilege($conn, 'edit_all_tasting_notes');
  $canPublish = hasPrivilege($conn, 'publish_tasting_note');

  // List only their own tasting notes for non-editors
  $all_notes = getTastingNotes($conn);
  $notes = [];
  foreach ($all_notes as $n) {
    if ($canEditAll || $n['user_id'] == $_SESSION['user_id']) {
      $notes[] = $n;
    }
  }

  // Get all wines for the dropdown
  $wines = getWines($conn);

  // Get selected bottle details
  $selected_note = null;
  if (isset($_GET['note_id'])) {
    $note_id = filter_input(INPUT_GET, 'note_id', FILTER_VALIDATE_INT);
    if ($note_id !== false && $note_id !== null) {
      $selected_note = getNoteDetails($conn, $note_id);
    }
  }

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Edit tasting note';

  $extra_head = <<<HTML
    <script>
      let allNotes = [];

      document.addEventListener("DOMContentLoaded", function() {
        const select = document.getElementById('note_id');
        if (select) {
          // Cache all options on page load (except the placeholder)
          for (let i = 1; i < select.options.length; i++) {
            const opt = select.options[i];
            allNotes.push({
              value: opt.value,
              text: opt.textContent,
              selected: opt.selected
            });
          }
        }
      });

      function filterNotes() {
        const query = document.getElementById('searchNoteBox').value.toLowerCase().trim();
        const select = document.getElementById('note_id');
        if (!select) return;
        
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        // Clear options, preserving the placeholder
        while (select.options.length > 1) {
          select.remove(1);
        }

        // Re-populate with matching options
        allNotes.forEach(n => {
          const textLower = n.text.toLowerCase();
          const matches = terms.every(term => textLower.includes(term));

          if (matches) {
            const opt = document.createElement('option');
            opt.value = n.value;
            opt.textContent = n.text;
            if (n.value === currentValue) {
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
        <h3>Edit a tasting note</h3>
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
          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Select tasting note:</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchNoteBox" onkeyup="filterNotes()"
              placeholder="🔍 Search note ID or wine name..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="note_id" id="note_id" onchange="this.form.submit()" required
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="">Select a tasting note</option>
              <?php 
                $scale = getRatingScale();
                $wset_enabled = isWsetSATEnabled();
                foreach ($notes as $note): 
                  $status_label = ($note['status'] === 'published') ? ' (Published)' : '';
                  $rating_label = formatRatingDisplay($note, $scale, true);
              ?>
                <option value="<?php echo $note['note_id']; ?>" <?php echo (isset($_GET['note_id']) && $_GET['note_id'] == $note['note_id']) ? 'selected' : ''; ?>>
                  <?php 
                    echo htmlspecialchars($note['note_id'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($note['tasting_date'], ENT_QUOTES, 'UTF-8') . ": " . (!empty($rating_label) ? htmlspecialchars($rating_label, ENT_QUOTES, 'UTF-8') . ': ' : '') . (($note['blind']=='blind') ? 'Blind tasting' : getWineName($note['nameconvention'], $note['vintage'], $note['name'], $note['producer'], $note['grape'], $note['vineyard'])) . $status_label; 
                  ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($selected_note): ?>
          <?php if (!$canPublish && !$canEditAll && $selected_note['status'] === 'published'): ?>
            <div style="padding: 15px; margin-top: 20px; background-color: #f9f9f9; border-left: 4px solid #f39c12;">
              <p><strong>Notice:</strong> This tasting note has been published and can no longer be edited. Please contact an admin if you need to make changes.</p>
            </div>
          <?php else: ?>
          <h3>Update Tasting Note</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="user_id" value="<?php echo $selected_note['user_id']; ?>">
            <input type="hidden" name="note_id" value="<?php echo isset($_POST['note_id']) ? htmlspecialchars($_POST['note_id'], ENT_QUOTES, 'UTF-8') : $selected_note['note_id']; ?>">
          
            <hr><details>
              <summary><label for="wine_id">Show the wine:</label></summary>
              <select name="wine_id" id="wine_id" required>
                <option value="">Select a wine</option>
                <?php foreach ($wines as $wine): ?>
                  <option value="<?php echo $wine['wine_id']; ?>" <?php echo ($wine['wine_id'] == $selected_note['wine_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($wine['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($wine['region'], ENT_QUOTES, 'UTF-8') . ": " . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']) ; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </details><hr style="margin-top:10px;">

            <br>
            <label for="tasting_date">Tasting date:</label>
            <br><input type="date" id="tasting_date" name="tasting_date" value="<?php echo $selected_note['tasting_date']; ?>" required>

            <br><br>
            <label for="tasting_note">Tasting note:</label>
            <br><textarea name="tasting_note" rows="20" cols="40" placeholder="<p>...</p>" required><?php echo $selected_note['tasting_note']; ?></textarea>

            <br><br>
            <label for="flawed_yn">Flawed?</label><br>
            <select name="flawed_yn" id="flawed_yn" required>
              <option value="no" <?php echo ($selected_note['flawed_yn'] == 'no') ? 'selected' : 'selected'; ?>>no</option>
              <option value="yes" <?php echo ($selected_note['flawed_yn'] == 'yes') ? 'selected' : ''; ?>>yes</option>
            </select>

            <h3>Ratings</h3>

            <?php if ($wset_enabled): ?>
              <strong>WSET Systematic Approach to Tasting</strong><br>
              <label for="wset_balance">Balance:</label>
              <select name="wset_balance" id="wset_balance">
                <option value="" <?php echo ($selected_note['wset_balance'] === null) ? 'selected' : ''; ?>></option>
                <option value="0" <?php echo ($selected_note['wset_balance'] === '0.0' || $selected_note['wset_balance'] === '0') ? 'selected' : ''; ?>>0.0</option>
                <option value="0.5" <?php echo ($selected_note['wset_balance'] === '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo ($selected_note['wset_balance'] === '1.0' || $selected_note['wset_balance'] === '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <br>
              <label for="wset_length">Length:</label>
              <select name="wset_length" id="wset_length">
                <option value="" <?php echo ($selected_note['wset_length'] === null) ? 'selected' : ''; ?>></option>
                <option value="0" <?php echo ($selected_note['wset_length'] === '0.0' || $selected_note['wset_length'] === '0') ? 'selected' : ''; ?>>0.0</option>
                <option value="0.5" <?php echo ($selected_note['wset_length'] === '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo ($selected_note['wset_length'] === '1.0' || $selected_note['wset_length'] === '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <br>
              <label for="wset_intensity">Intensity:</label>
              <select name="wset_intensity" id="wset_intensity">
                <option value="" <?php echo ($selected_note['wset_intensity'] === null) ? 'selected' : ''; ?>></option>
                <option value="0" <?php echo ($selected_note['wset_intensity'] === '0.0' || $selected_note['wset_intensity'] === '0') ? 'selected' : ''; ?>>0.0</option>
                <option value="0.5" <?php echo ($selected_note['wset_intensity'] === '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo ($selected_note['wset_intensity'] === '1.0' || $selected_note['wset_intensity'] === '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <br>
              <label for="wset_complexity">Complexity:</label>
              <select name="wset_complexity" id="wset_complexity">
                <option value="" <?php echo ($selected_note['wset_complexity'] === null) ? 'selected' : ''; ?>></option>
                <option value="0" <?php echo ($selected_note['wset_complexity'] === '0.0' || $selected_note['wset_complexity'] === '0') ? 'selected' : ''; ?>>0.0</option>
                <option value="0.5" <?php echo ($selected_note['wset_complexity'] === '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo ($selected_note['wset_complexity'] === '1.0' || $selected_note['wset_complexity'] === '1') ? 'selected' : ''; ?>>1.0</option>
              </select>
              <hr>
              <label for="wsetpts">WSET SAT points (0.0–4.0):</label>
              <select name="wsetpts" id="wsetpts" disabled>
                <option value="0" <?php echo ($selected_note['wsetpts'] === '0.0' || $selected_note['wsetpts'] === '0') ? 'selected' : ''; ?>>0.0</option>
                <option value="0.5" <?php echo ($selected_note['wsetpts'] === '0.5') ? 'selected' : ''; ?>>0.5</option>
                <option value="1" <?php echo ($selected_note['wsetpts'] === '1.0' || $selected_note['wsetpts'] === '1') ? 'selected' : ''; ?>>1.0</option>
                <option value="1.5" <?php echo ($selected_note['wsetpts'] === '1.5') ? 'selected' : ''; ?>>1.5</option>
                <option value="2" <?php echo ($selected_note['wsetpts'] === '2.0' || $selected_note['wsetpts'] === '2') ? 'selected' : ''; ?>>2.0</option>
                <option value="2.5" <?php echo ($selected_note['wsetpts'] === '2.5') ? 'selected' : ''; ?>>2.5</option>
                <option value="3" <?php echo ($selected_note['wsetpts'] === '3.0' || $selected_note['wsetpts'] === '3') ? 'selected' : ''; ?>>3.0</option>
                <option value="3.5" <?php echo ($selected_note['wsetpts'] === '3.5') ? 'selected' : ''; ?>>3.5</option>
                <option value="4" <?php echo ($selected_note['wsetpts'] === '4.0' || $selected_note['wsetpts'] === '4') ? 'selected' : ''; ?>>4.0</option>
              </select>
              <br><br>
            <?php endif; ?>

            <?php if ($scale === '100-point'): ?>
              <label for="pts_100">Score (100-point scale):</label>
              <br>
              <input type="number" id="pts_100" name="pts_100" min="50" max="100" step="1" value="<?php echo htmlspecialchars($selected_note['pts_100'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="50-100">
            <?php else: ?>
              <?php $selected_20 = $selected_note['pts_20'] ?? $selected_note['dmpts'] ?? null; ?>
              <label for="pts_20"><?php echo htmlspecialchars(!empty($_SESSION['initials']) ? $_SESSION['initials'] : 'DM', ENT_QUOTES, 'UTF-8'); ?> points (20-point scale):</label>
              <br>
              <select name="pts_20" id="pts_20">
                <option value="" <?php echo ($selected_20 === null || $selected_20 === '') ? 'selected' : ''; ?>></option>
                <?php for ($i = 0; $i <= 20; $i++): ?>
                  <option value="<?php echo $i; ?>" <?php echo ($selected_20 !== null && (string)$selected_20 === (string)$i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
              </select>
            <?php endif; ?>
            <p id="suggestedScore" style="margin-top:5px;"></p>

            <label for="favourite">Favourite?</label><br>
            <select name="favourite" id="favourite" required>
              <option value="no" <?php echo ($selected_note['favourite'] == 'no') ? 'selected' : 'selected'; ?>>no</option>
              <option value="yes" <?php echo ($selected_note['favourite'] == 'yes') ? 'selected' : ''; ?>>yes</option>
            </select>

            <br><br>
            <label for="blind">Tasted blind?</label><br>
            <select name="blind" id="blind" required>
              <option value="not blind" <?php echo ($selected_note['blind'] == 'not blind') ? 'selected' : 'selected'; ?>>not blind</option>
              <option value="blind" <?php echo ($selected_note['blind'] == 'blind') ? 'selected' : ''; ?>>blind</option>
            </select>

            <h3>Drinking window</h3>
            <label for="drink_from">Drink from</label> <input type="text" id="drink_from" name="drink_from" maxlength="4" size="5" value="<?php echo ($selected_note['drinkwindow_min']!=null) ? $selected_note['drinkwindow_min'] : ''; ?>" placeholder="yyyy">
            <label for="drink_through"> through</label> <input type="text" id="drink_through" name="drink_through" maxlength="4" size="5" value="<?php echo ($selected_note['drinkwindow_max']!=null) ? $selected_note['drinkwindow_max'] : ''; ?>"  placeholder="yyyy">

            <h3>Image and publication</h3>
            <label for="img">Image:</label>
            <br><input type="text" size="40" name="img" placeholder="filename.jpg" value="<?php echo ($selected_note['img']!=null) ? htmlspecialchars(sanitizeInput($selected_note['img']), ENT_QUOTES, 'UTF-8') : ''; ?>">
            <br><label for="img_class">Image class:</label>
            <br>
            <select name="img_class" id="img_class">
              <option value="null" <?php echo ($selected_note['img_class'] == 'null') ? 'selected' : 'selected'; ?>></option>
              <option value="inline left" <?php echo ($selected_note['img_class'] == 'inline left') ? 'selected' : ''; ?>>inline left</option>
              <option value="block center" <?php echo ($selected_note['img_class'] == 'block center') ? 'selected' : ''; ?>>block center</option>
            </select>

            <br><br>
            <label for="status">Publish note?</label>
            <br>
            <select name="status" id="status" required <?php echo (!$canPublish) ? 'disabled' : ''; ?>>
              <option value="draft" <?php echo (!$canPublish || $selected_note['status'] == 'draft') ? 'selected' : ''; ?>>draft</option>
              <option value="published" <?php echo ($canPublish && $selected_note['status'] == 'published') ? 'selected' : ''; ?>>published</option>
            </select>

            <br><br>
            <input type="submit" name="update" value="Update Tasting Note">
          </form>
          <?php endif; ?>
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
      I don't control access to this website. My texts are open for practical reasons. That means I don't know who you are. If you'd like
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
  const ratingScale = "<?php echo $scale; ?>";

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
