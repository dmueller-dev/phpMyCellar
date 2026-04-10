<?php
// Define a constant to protect included files from direct access
if (!defined('INCLUDED_VIA_APP')) {
  define('INCLUDED_VIA_APP', true);
}

// Include the database configuration file
require 'dbConnectBackend.php';

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
    $dmpts = filter_input(INPUT_POST, 'dmpts', FILTER_VALIDATE_INT);
    $wset_balance = number_format(filter_input(INPUT_POST, 'wset_balance', FILTER_VALIDATE_FLOAT),1,'.','');
    $wset_length = number_format(filter_input(INPUT_POST, 'wset_length', FILTER_VALIDATE_FLOAT),1,'.','');
    $wset_intensity = number_format(filter_input(INPUT_POST, 'wset_intensity', FILTER_VALIDATE_FLOAT),1,'.','');
    $wset_complexity = number_format(filter_input(INPUT_POST, 'wset_complexity', FILTER_VALIDATE_FLOAT),1,'.','');
    $wsetpts=number_format($wset_balance+$wset_length+$wset_intensity+$wset_complexity, 1, '.', '');
    $starpts = filter_input(INPUT_POST, 'starpts', FILTER_VALIDATE_INT);
    $drink_from = filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT);
    $drink_through = filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT);
    $errorsDrinkDates = validateDrinkDatesInput($drink_from, $drink_through);
    $blind = sanitizeInput($_POST['blind']);
    $status = sanitizeInput($_POST['status']);
    $img = sanitizeInput($_POST['img']);
    $img_class = sanitizeInput($_POST['img_class']);
    if ($img_class == "null") { $img_class = null; }
    $errorsImg = validateImageInput($img, $img_class);
    $errors = validateNoteInput($note_id, $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $dmpts, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $starpts, $drink_from, $drink_through, $blind, $status, $img, $img_class);
    if (empty($errorsDrinkDates) && empty($errorsImg) && empty($errors)) {
      // Start transaction
      $conn->begin_transaction();
      try {
        if (updateTastingNote($conn, $note_id, $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $dmpts, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $starpts, $drink_from, $drink_through, $blind, $status, $img, $img_class)) {
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

// Get all wines for the dropdown
$notes = getTastingNotes($conn);
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

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Edit tasting note</title>
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
          <label for="note_id">Select tasting note:</label>
          <select name="note_id" id="note_id" onchange="this.form.submit()" required>
            <option value="">Select a tasting note</option>
            <?php foreach ($notes as $note): ?>
              <option value="<?php echo $note['note_id']; ?>" <?php echo (isset($_GET['note_id']) && $_GET['note_id'] == $note['note_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($note['note_id'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($note['tasting_date'], ENT_QUOTES, 'UTF-8') . ": " . (!empty($note['dmpts']) ? 'DM' . htmlspecialchars($note['dmpts'], ENT_QUOTES, 'UTF-8') . ': ' : '') . (($note['blind']=='blind') ? 'Blind tasting' : getWineName($note['nameconvention'], $note['vintage'], $note['name'], $note['producer'], $note['grape'], $note['vineyard'])) ; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if ($selected_note): ?>
          <h3>Update Tasting Note</h3>
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="user_id" value="1">
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

            WSET<br>
	    <label for="wset_balance">Balance:</label>
            <select name="wset_balance" id="wset_balance">
              <option value="" <?php echo ($selected_note['wset_balance'] == null) ? 'selected' : 'selected'; ?>></option>
              <option value="0" <?php echo ($selected_note['wset_balance'] == '0') ? 'selected' : ''; ?>>0.0</option>
              <option value="0.5" <?php echo ($selected_note['wset_balance'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
              <option value="1" <?php echo ($selected_note['wset_balance'] == '1') ? 'selected' : ''; ?>>1.0</option>
            </select>
            <br>
	    <label for="wset_length">Length:</label>
            <select name="wset_length" id="wset_length">
              <option value="" <?php echo ($selected_note['wset_length'] == null) ? 'selected' : 'selected'; ?>></option>
              <option value="0" <?php echo ($selected_note['wset_length'] == '0') ? 'selected' : ''; ?>>0.0</option>
              <option value="0.5" <?php echo ($selected_note['wset_length'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
              <option value="1" <?php echo ($selected_note['wset_length'] == '1') ? 'selected' : ''; ?>>1.0</option>
            </select>
            <br>
	    <label for="wset_intensity">Intensity:</label>
            <select name="wset_intensity" id="wset_intensity">
              <option value="" <?php echo ($selected_note['wset_intensity'] == null) ? 'selected' : 'selected'; ?>></option>
              <option value="0" <?php echo ($selected_note['wset_intensity'] == '0') ? 'selected' : ''; ?>>0.0</option>
              <option value="0.5" <?php echo ($selected_note['wset_intensity'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
              <option value="1" <?php echo ($selected_note['wset_intensity'] == '1') ? 'selected' : ''; ?>>1.0</option>
            </select>
            <br>
	    <label for="wset_intensity">Complexity:</label>
            <select name="wset_complexity" id="wset_complexity">
              <option value="" <?php echo ($selected_note['wset_complexity'] == null) ? 'selected' : 'selected'; ?>></option>
              <option value="0" <?php echo ($selected_note['wset_complexity'] == '0') ? 'selected' : ''; ?>>0.0</option>
              <option value="0.5" <?php echo ($selected_note['wset_complexity'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
              <option value="1" <?php echo ($selected_note['wset_complexity'] == '1') ? 'selected' : ''; ?>>1.0</option>
            </select>
            <hr>
            <label for="wsetpts">WSET points:</label>
            <select name="wsetpts" id="wsetpts" disabled>
              <option value="0" <?php echo ($selected_note['wsetpts'] == '0') ? 'selected' : ''; ?>>0.0</option>
              <option value="0.5" <?php echo ($selected_note['wsetpts'] == '0.5') ? 'selected' : ''; ?>>0.5</option>
              <option value="1" <?php echo ($selected_note['wsetpts'] == '1') ? 'selected' : ''; ?>>1.0</option>
              <option value="1.5" <?php echo ($selected_note['wsetpts'] == '1.5') ? 'selected' : ''; ?>>1.5</option>
              <option value="2" <?php echo ($selected_note['wsetpts'] == '2') ? 'selected' : ''; ?>>2.0</option>
              <option value="2.5" <?php echo ($selected_note['wsetpts'] == '2.5') ? 'selected' : ''; ?>>2.5</option>
              <option value="3" <?php echo ($selected_note['wsetpts'] == '3') ? 'selected' : ''; ?>>3.0</option>
              <option value="3.5" <?php echo ($selected_note['wsetpts'] == '3.5') ? 'selected' : ''; ?>>3.5</option>
              <option value="4" <?php echo ($selected_note['wsetpts'] == '4') ? 'selected' : ''; ?>>4.0</option>
            </select>

	    <br><br>
            <label for="dmpts">DM points:</label>
            <select name="dmpts" id="dmpts">
              <option value="" <?php echo ($selected_note['dmpts'] == null) ? 'selected' : 'selected'; ?>></option>
              <option value="0" <?php echo ($selected_note['dmpts'] == '0') ? 'selected' : ''; ?>>0</option>
              <option value="1" <?php echo ($selected_note['dmpts'] == '1') ? 'selected' : ''; ?>>1</option>
              <option value="2" <?php echo ($selected_note['dmpts'] == '2') ? 'selected' : ''; ?>>2</option>
              <option value="3" <?php echo ($selected_note['dmpts'] == '3') ? 'selected' : ''; ?>>3</option>
              <option value="4" <?php echo ($selected_note['dmpts'] == '4') ? 'selected' : ''; ?>>4</option>
              <option value="5" <?php echo ($selected_note['dmpts'] == '5') ? 'selected' : ''; ?>>5</option>
              <option value="6" <?php echo ($selected_note['dmpts'] == '6') ? 'selected' : ''; ?>>6</option>
              <option value="7" <?php echo ($selected_note['dmpts'] == '7') ? 'selected' : ''; ?>>7</option>
              <option value="8" <?php echo ($selected_note['dmpts'] == '8') ? 'selected' : ''; ?>>8</option>
              <option value="9" <?php echo ($selected_note['dmpts'] == '9') ? 'selected' : ''; ?>>9</option>
              <option value="10" <?php echo ($selected_note['dmpts'] == '10') ? 'selected' : ''; ?>>10</option>
              <option value="11" <?php echo ($selected_note['dmpts'] == '11') ? 'selected' : ''; ?>>11</option>
              <option value="12" <?php echo ($selected_note['dmpts'] == '12') ? 'selected' : ''; ?>>12</option>
              <option value="13" <?php echo ($selected_note['dmpts'] == '13') ? 'selected' : ''; ?>>13</option>
              <option value="14" <?php echo ($selected_note['dmpts'] == '14') ? 'selected' : ''; ?>>14</option>
              <option value="15" <?php echo ($selected_note['dmpts'] == '15') ? 'selected' : ''; ?>>15</option>
              <option value="16" <?php echo ($selected_note['dmpts'] == '16') ? 'selected' : ''; ?>>16</option>
              <option value="17" <?php echo ($selected_note['dmpts'] == '17') ? 'selected' : ''; ?>>17</option>
              <option value="18" <?php echo ($selected_note['dmpts'] == '18') ? 'selected' : ''; ?>>18</option>
              <option value="19" <?php echo ($selected_note['dmpts'] == '19') ? 'selected' : ''; ?>>19</option>
              <option value="20" <?php echo ($selected_note['dmpts'] == '20') ? 'selected' : ''; ?>>20</option>
            </select>
            <p id="suggestedDMpts" style="margin-top:5px;"></p>

            <label for="starpts">Stars:</label>
            <select name="starpts" id="starpts">
              <option value="" <?php echo ($selected_note['starpts'] == null) ? 'selected' : 'selected'; ?>></option>
              <option value="0" <?php echo ($selected_note['starpts'] == '0') ? 'selected' : ''; ?>>0</option>
              <option value="1" <?php echo ($selected_note['starpts'] == '1') ? 'selected' : ''; ?>>1</option>
              <option value="2" <?php echo ($selected_note['starpts'] == '2') ? 'selected' : ''; ?>>2</option>
              <option value="3" <?php echo ($selected_note['starpts'] == '3') ? 'selected' : ''; ?>>3</option>
              <option value="4" <?php echo ($selected_note['starpts'] == '4') ? 'selected' : ''; ?>>4</option>
              <option value="5" <?php echo ($selected_note['starpts'] == '5') ? 'selected' : ''; ?>>5</option>
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
            <select name="status" id="status" required>
              <option value="draft" <?php echo ($selected_note['status'] == 'draft') ? 'selected' : 'selected'; ?>>draft</option>
              <option value="published" <?php echo ($selected_note['status'] == 'published') ? 'selected' : ''; ?>>published</option>
            </select>

            <br><br>
            <input type="submit" name="update" value="Update Tasting Note">
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

<script>
const wset_b = document.getElementById('wset_balance');
const wset_l = document.getElementById('wset_length');
const wset_i = document.getElementById('wset_intensity');
const wset_c = document.getElementById('wset_complexity');
const wsetpts = document.getElementById('wsetpts');
const suggestedDMpts = document.getElementById('suggestedDMpts');

function updateTotal() {
  if (wset_b.value != "" && wset_l.value != "" && wset_i.value != "" && wset_c.value != "") {
    wsetpts.value = Number(wset_b.value) + Number(wset_l.value) + Number(wset_i.value) + Number(wset_c.value);
  }
  if (Number(wsetpts.value)==0) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 0 (\"poor\")</em></small>";
  } else if (Number(wsetpts.value)==0.5) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 1 or 2 (\"subpar\")</em></small>";
  } else if (Number(wsetpts.value)==1) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 3 or 4 (\"passable\")</em></small>";
  } else if (Number(wsetpts.value)==1.5) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 5 or 6 (\"good\")</em></small>";
  } else if (Number(wsetpts.value)==2) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 7 or 8 (\"good\")</em></small>";
  } else if (Number(wsetpts.value)==2.5) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 9 or 10 (\"very good\")</em></small>";
  } else if (Number(wsetpts.value)==3) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 11 or 12 (\"very good\")</em></small>";
  } else if (Number(wsetpts.value)==3.5) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 13 or 14 (\"excellent\")</em></small>";
  } else if (Number(wsetpts.value)==4) {
    suggestedDMpts.innerHTML = "<small><em>Suggested points: 15 to 20 (\"excellent\", \"grand vin\", or \"one-of-a-kind\")</em></small>";
  }
}

wset_b.addEventListener('change', updateTotal);
wset_l.addEventListener('change', updateTotal);
wset_i.addEventListener('change', updateTotal);
wset_c.addEventListener('change', updateTotal);

window.onload = updateTotal;
</script>

</body>

</html>

<?php
// Close the database connection
$conn->close();
?>
