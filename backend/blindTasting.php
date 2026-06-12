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
      $status = sanitizeInput($_POST['status'] ?? 'draft');
      $img = sanitizeInput($_POST['img']);
      $img_class = sanitizeInput($_POST['img_class']);
      if ($img_class == "null") { $img_class = null; }
      $errorsImg = validateImageInput($img, $img_class);
      if (empty($errorsImg) && empty($errorsDrinkDates)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertTastingNote($conn, $bottle_id, $wine_id, $tasting_date, $_SESSION['user_id'], $tasting_note, $flawed, $dmpts, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $starpts, $drink_from, $drink_through, $status, $blind, $img, $img_class, $favourite)) {
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
?>

<?php
  $page_title = 'Add blind tasting note';
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
          <label for="bottle_id">Select a bottle:</label>
          <select name="bottle_id" id="bottle_id" onchange="this.form.submit()">
            <option value="">Select a bottle</option>
            <?php foreach ($bottles as $bottle): ?>
              <option value="<?php echo $bottle['bottle_id']; ?>" <?php echo (isset($_GET['bottle_id']) && $_GET['bottle_id'] == $bottle['bottle_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($bottle['bottle_id'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_bottle && empty($success_message)): ?>
          <h3>Write tasting note</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="bottle_id" value="<?php echo isset($_POST['bottle_id']) ? htmlspecialchars($_POST['bottle_id'], ENT_QUOTES, 'UTF-8') : $selected_bottle['bottle_id']; ?>">
	    <input type="hidden" name="wine_id" value="<?php echo isset($_POST['wine_id']) ? htmlspecialchars($POST['wine_id'], ENT_QUOTES, 'UTF-8') : $selected_bottle['wine_id']; ?>">
            
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
