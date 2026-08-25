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
    if (isset($_POST['postTastingNote'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $bottle_id = null;
      $wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $tasting_date = sanitizeInput($_POST['tasting_date']);
      $tasting_note = sanitizeInput($_POST['tasting_note']);
      $flawed = sanitizeInput($_POST['flawed']);
      $dmpts = filter_input(INPUT_POST, 'dmpts', FILTER_VALIDATE_INT);
      $wset_balance = number_format(filter_input(INPUT_POST, 'wset_balance', FILTER_VALIDATE_FLOAT),1,'.','');
      $wset_length = number_format(filter_input(INPUT_POST, 'wset_length', FILTER_VALIDATE_FLOAT),1,'.','');
      $wset_intensity = number_format(filter_input(INPUT_POST, 'wset_intensity', FILTER_VALIDATE_FLOAT),1,'.','');
      $wset_complexity = number_format(filter_input(INPUT_POST, 'wset_complexity', FILTER_VALIDATE_FLOAT),1,'.','');
      $wsetpts=number_format($wset_balance+$wset_length+$wset_intensity+$wset_complexity, 1, '.', '');
      $starpts = filter_input(INPUT_POST, 'starpts', FILTER_VALIDATE_INT);
      $favourite = sanitizeInput($_POST['favourite'] ?? 'no');
      $drink_from = filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT);
      $drink_through = filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT);
      $errorsDrinkDates = validateDrinkDatesInput($drink_from, $drink_through);
      $blind = sanitizeInput($_POST['blind']);
      $status = sanitizeInput($_POST['status'] ?? 'draft'); // Default to 'draft' if status not sent (i.e. for users with write privileges)
      $img = sanitizeInput($_POST['img']);
      $img_class = sanitizeInput($_POST['img_class']);
      if ($img_class == "null") { $img_class = null; }
      $errorsImg = validateImageInput($img, $img_class);
      if (empty($errorsImg) && empty($errorsDrinkDates)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertTastingNote($conn, $bottle_id, $wine_id, $tasting_date, $_SESSION['user_id'], $tasting_note, $flawed, $dmpts, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $starpts, $drink_from, $drink_through, $status, $blind, $img, $img_class, $favourite)) {
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
  $page_title = 'Add tasting note';

  $extra_head = <<<HTML
    <script>
      let allWines = [];

      document.addEventListener("DOMContentLoaded", function() {
        const select = document.getElementById('wine_id');
        if (select) {
          // Cache all options on page load (except the placeholder)
          for (let i = 1; i < select.options.length; i++) {
            const opt = select.options[i];
            allWines.push({
              value: opt.value,
              text: opt.textContent,
              search: (opt.dataset.search || '').toLowerCase() + " " + opt.textContent.toLowerCase(),
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

        // Clear options, preserving the placeholder
        while (select.options.length > 1) {
          select.remove(1);
        }

        // Re-populate with matching options
        allWines.forEach(w => {
          const matches = terms.every(term => w.search.includes(term));

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
    </script>
  HTML;

  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>New tasting note</h3>
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
            if (isset($_GET['wine_id']) || isset($_POST['wine_id'])) {
              $back_wine_id = isset($_POST['wine_id']) ? (int)$_POST['wine_id'] : (int)$_GET['wine_id'];
              echo "<p><a href='/wines.php?id=" . $back_wine_id . "'>View updated wine page</a></p>";
            }
            echo "<p><a href='https://dmueller.com/backend/addTastingNote.php'>New tasting note.</a></p>";
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
              <?php 
                $search_terms = [
                  $wine['country'],
                  $wine['region'],
                  $wine['producer'],
                  $wine['vintage'] ? $wine['vintage'] : 'NV',
                  $wine['name'],
                  $wine['grape'],
                  $wine['vineyard']
                ];
                $search_string = implode(' ', array_filter(array_map('trim', $search_terms)));
              ?>
              <option value="<?php echo $wine['wine_id']; ?>" 
                      data-search="<?php echo htmlspecialchars($search_string, ENT_QUOTES, 'UTF-8'); ?>"
                      <?php echo (isset($_GET['wine_id']) && $_GET['wine_id'] == $wine['wine_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($wine['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($wine['region'], ENT_QUOTES, 'UTF-8') . ": " . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']) ; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($selected_wine && empty($success_message)): ?>
          <h3>Write tasting note</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="wine_id" value="<?php echo isset($_POST['wine_id']) ? htmlspecialchars($_POST['wine_id'], ENT_QUOTES, 'UTF-8') : $selected_wine['wine_id']; ?>">

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

            WSET<br>
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
	    <label for="wset_intensity">Complexity:</label>
            <select name="wset_complexity" id="wset_complexity">
              <option value="0" <?php echo (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] == '0') ? 'selected' : 'selected'; ?>>0.0</option>
              <option value="0.5" <?php echo (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
              <option value="1" <?php echo (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] == '1') ? 'selected' : ''; ?>>1.0</option>
            </select>
            <hr>
            <label for="wsetpts">WSET points:</label>
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
            <label for="dmpts"><?php echo htmlspecialchars(!empty($_SESSION['initials']) ? $_SESSION['initials'] : 'DM', ENT_QUOTES, 'UTF-8'); ?> points:</label>
            <select name="dmpts" id="dmpts">
              <option value="0" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '0') ? 'selected' : 'selected'; ?>>0</option>
              <option value="1" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '1') ? 'selected' : ''; ?>>1</option>
              <option value="2" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '2') ? 'selected' : ''; ?>>2</option>
              <option value="3" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '3') ? 'selected' : ''; ?>>3</option>
              <option value="4" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '4') ? 'selected' : ''; ?>>4</option>
              <option value="5" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '5') ? 'selected' : ''; ?>>5</option>
              <option value="6" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '6') ? 'selected' : ''; ?>>6</option>
              <option value="7" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '7') ? 'selected' : ''; ?>>7</option>
              <option value="8" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '8') ? 'selected' : ''; ?>>8</option>
              <option value="9" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '9') ? 'selected' : ''; ?>>9</option>
              <option value="10" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '10') ? 'selected' : ''; ?>>10</option>
              <option value="11" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '11') ? 'selected' : ''; ?>>11</option>
              <option value="12" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '12') ? 'selected' : ''; ?>>12</option>
              <option value="13" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '13') ? 'selected' : ''; ?>>13</option>
              <option value="14" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '14') ? 'selected' : ''; ?>>14</option>
              <option value="15" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '15') ? 'selected' : ''; ?>>15</option>
              <option value="16" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '16') ? 'selected' : ''; ?>>16</option>
              <option value="17" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '17') ? 'selected' : ''; ?>>17</option>
              <option value="18" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '18') ? 'selected' : ''; ?>>18</option>
              <option value="19" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '19') ? 'selected' : ''; ?>>19</option>
              <option value="20" <?php echo (isset($_POST['dmpts']) && $_POST['dmpts'] == '20') ? 'selected' : ''; ?>>20</option>
            </select>
            <p id="suggestedDMpts" style="margin-top:5px;"></p>

            <label for="starpts">Stars:</label>
            <select name="starpts" id="starpts">
              <option value="0" <?php echo (isset($_POST['starpts']) && $_POST['starpts'] == '0') ? 'selected' : 'selected'; ?>>0</option>
              <option value="1" <?php echo (isset($_POST['starpts']) && $_POST['starpts'] == '1') ? 'selected' : ''; ?>>1</option>
              <option value="2" <?php echo (isset($_POST['starpts']) && $_POST['starpts'] == '2') ? 'selected' : ''; ?>>2</option>
              <option value="3" <?php echo (isset($_POST['starpts']) && $_POST['starpts'] == '3') ? 'selected' : ''; ?>>3</option>
              <option value="4" <?php echo (isset($_POST['starpts']) && $_POST['starpts'] == '4') ? 'selected' : ''; ?>>4</option>
              <option value="5" <?php echo (isset($_POST['starpts']) && $_POST['starpts'] == '5') ? 'selected' : ''; ?>>5</option>
            </select>

            <br><br>
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
            <select name="status" id="status" required <?php echo ($role === 'write') ? 'disabled' : ''; ?>>
              <option value="draft" <?php echo ($role === 'write' || empty($_POST['status']) || (isset($_POST['status']) && $_POST['status'] == 'draft')) ? 'selected' : ''; ?>>draft</option>
              <option value="published" <?php echo ($role !== 'write' && isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>published</option>
            </select>
            <?php if ($role === 'write'): ?>
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
  const suggestedDMpts = document.getElementById('suggestedDMpts');

  function updateTotal() {
    wsetpts.value = Number(wset_b.value) + Number(wset_l.value) + Number(wset_i.value) + Number(wset_c.value);
    if (Number(wsetpts.value)==0) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 0 (\"poor\")</em></small>";
    } else if (Number(wsetpts.value)==0.5) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 1 or 2 (\"subpar\")</em></small>";
    } else if (Number(wsetpts.value)==1) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 3 or 4 (\"passable\")</em></small>";
    } else if (Number(wsetpts.value)==1.5) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 5 or 6 (\"good\")</em></small>";
    } else if (Number(wsetpts.value)==2) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 7 or 8 (\"good\")</em></small>";
    } else if (Number(wsetpts.value)==2.5) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 9 or 10 (\"very good\")</em></small>";
    } else if (Number(wsetpts.value)==3) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 11 or 12 (\"very good\")</em></small>";
    } else if (Number(wsetpts.value)==3.5) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 13 or 14 (\"excellent\")</em></small>";
    } else if (Number(wsetpts.value)==4) {
      suggestedDMpts.innerHTML = "<small><em>Suggest points: 15 to 20 (\"excellent\", \"grand vin\", or \"one-of-a-kind\")</em></small>";
    }
  }

  wset_b.addEventListener('change', updateTotal);
  wset_l.addEventListener('change', updateTotal);
  wset_i.addEventListener('change', updateTotal);
  wset_c.addEventListener('change', updateTotal);

  window.onload = updateTotal;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
