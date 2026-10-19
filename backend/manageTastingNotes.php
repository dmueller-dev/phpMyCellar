<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Tasting Note Management Dashboard.
   *
   * Combines standard tasting notes, blind tasting sessions, WSET SAT evaluations,
   * note editing, and cellar consumption tracking.
   * Required privileges: 'add_tasting_note' or 'edit_tasting_note'
   */
  $can_add = hasPrivilege($conn, 'add_tasting_note');
  $can_edit = hasPrivilege($conn, 'edit_tasting_note');
  $can_edit_all = hasPrivilege($conn, 'edit_all_tasting_notes');
  $can_publish = hasPrivilege($conn, 'publish_tasting_note');

  if (!$can_add && !$can_edit) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';
  $tasting_note_id = null;

  // Determine operational mode: 'add', 'blind', or 'edit'
  $requested_note_id = filter_input(INPUT_GET, 'note_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_note', FILTER_VALIDATE_INT);
  $mode_param = sanitizeInput($_GET['mode'] ?? $_POST['mode'] ?? '');

  if ($requested_note_id && $can_edit) {
    $mode = 'edit';
  } elseif ($mode_param === 'blind' && $can_add) {
    $mode = 'blind';
  } elseif ($mode_param === 'edit' && $can_edit) {
    $mode = 'edit';
  } elseif ($mode_param === 'add' && $can_add) {
    $mode = 'add';
  } else {
    $mode = $can_add ? 'add' : 'edit';
  }

  $scale = getRatingScale();
  $wset_enabled = isWsetSATEntryEnabled();

  // Pre-load lookup data
  $wines = getWines($conn);
  $bottles = ($mode === 'blind') ? getBottlesInCellar($conn) : [];
  $all_notes = getTastingNotes($conn);
  $accessible_notes = [];
  foreach ($all_notes as $n) {
    if ($can_edit_all || (int)$n['user_id'] === (int)$_SESSION['user_id']) {
      $accessible_notes[] = $n;
    }
  }

  // Pre-selected bottle or wine for ADD / BLIND mode
  $selected_bottle = null;
  $selected_wine = null;
  $wine_name = '';

  $bottle_id_param = filter_input(INPUT_GET, 'bottle_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'bottle_id', FILTER_VALIDATE_INT);
  if ($bottle_id_param) {
    $selected_bottle = getBottleDetails($conn, $bottle_id_param);
    if ($selected_bottle) {
      $wine_name = getWineName($selected_bottle["nameconvention"], $selected_bottle["vintage"], $selected_bottle["name"], $selected_bottle["producer"], $selected_bottle["grape"], $selected_bottle["vineyard"]);
      $add_wine_id = (int)$selected_bottle['wine_id'];
    }
  }

  $wine_id_param = filter_input(INPUT_GET, 'wine_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
  if (!$selected_bottle && $wine_id_param) {
    $selected_wine = getWineDetails($conn, $wine_id_param);
    if ($selected_wine) {
      $add_wine_id = (int)$selected_wine['wine_id'];
      foreach ($wines as $w) {
        if ((int)$w['wine_id'] === $add_wine_id) {
          $wine_name = getWineName($w["nameconvention"], $w["vintage"], $w["name"], $w["producer"], $w["grape"], $w["vineyard"]);
          break;
        }
      }
    }
  }

  // Loaded note details for EDIT mode
  $selected_note = null;
  if ($requested_note_id) {
    $selected_note = getNoteDetails($conn, $requested_note_id);
    if (!$selected_note) {
      $errors[] = "Requested tasting note #" . $requested_note_id . " not found.";
    } elseif (!$can_edit_all && (int)$selected_note['user_id'] !== (int)$_SESSION['user_id']) {
      $errors[] = "You do not have permission to edit this tasting note.";
      $selected_note = null;
    }
  }

  // Handle Form Submissions
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    // Action 1: Add or Update Tasting Note
    if (isset($_POST['postTastingNote']) || isset($_POST['updateTastingNote'])) {
      $is_post_edit = isset($_POST['updateTastingNote']);

      $post_bottle_id = filter_input(INPUT_POST, 'bottle_id', FILTER_VALIDATE_INT) ?: null;
      $post_wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $post_tasting_date = sanitizeInput($_POST['tasting_date'] ?? date('Y-m-d'));
      $post_tasting_note = sanitizeInput($_POST['tasting_note'] ?? '');
      $post_flawed = sanitizeInput($_POST['flawed'] ?? 'no');
      $post_favourite = sanitizeInput($_POST['favourite'] ?? 'no');
      $post_blind = sanitizeInput($_POST['blind'] ?? 'not blind');

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
        if ($raw_b !== false && $raw_b !== null) $wset_balance = number_format($raw_b, 1, '.', '');
        if ($raw_l !== false && $raw_l !== null) $wset_length = number_format($raw_l, 1, '.', '');
        if ($raw_i !== false && $raw_i !== null) $wset_intensity = number_format($raw_i, 1, '.', '');
        if ($raw_c !== false && $raw_c !== null) $wset_complexity = number_format($raw_c, 1, '.', '');
        if ($wset_balance !== null || $wset_length !== null || $wset_intensity !== null || $wset_complexity !== null) {
          $wsetpts = number_format((float)$wset_balance + (float)$wset_length + (float)$wset_intensity + (float)$wset_complexity, 1, '.', '');
        }
      }

      $drink_from = filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT);
      $drink_through = filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT);
      $errorsDrinkDates = validateDrinkDatesInput($drink_from, $drink_through);

      $img = sanitizeInput($_POST['img'] ?? '');
      $img_class = sanitizeInput($_POST['img_class'] ?? '');
      if ($img_class === 'null' || empty($img_class)) $img_class = null;
      $errorsImg = validateImageInput($img, $img_class);

      if ($is_post_edit) {
        $post_note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
        $post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: (int)$_SESSION['user_id'];
        $check_note = getNoteDetails($conn, $post_note_id);

        if (!$can_edit_all) {
          if (!$check_note || (int)$check_note['user_id'] !== (int)$_SESSION['user_id']) {
            $errors[] = "You do not have permission to edit this tasting note.";
          } elseif ($check_note['status'] === 'published' && !$can_publish) {
            $errors[] = "This tasting note has been published and can no longer be edited without publishing privileges.";
          }
        }

        $post_status = $can_publish ? sanitizeInput($_POST['status'] ?? 'draft') : ($check_note ? $check_note['status'] : 'draft');
        $valErrors = validateNoteInput($post_note_id, $post_wine_id, $post_tasting_date, $post_user_id, $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_blind, $post_status, $img, $img_class, $post_favourite);
      } else {
        $post_status = $can_publish ? sanitizeInput($_POST['status'] ?? 'draft') : 'draft';
        $valErrors = validateNoteInput(null, $post_wine_id, $post_tasting_date, $_SESSION['user_id'], $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_blind, $post_status, $img, $img_class, $post_favourite);
      }

      if (!empty($errorsImg)) $errors[] = $errorsImg[0];
      if (!empty($errorsDrinkDates)) $errors[] = $errorsDrinkDates[0];
      if (!empty($valErrors)) $errors = array_merge($errors, $valErrors);

      if (empty($errors)) {
        $conn->begin_transaction();
        try {
          if ($is_post_edit) {
            if (updateTastingNote($conn, $post_note_id, $post_wine_id, $post_tasting_date, $post_user_id, $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_blind, $post_status, $img, $img_class, $post_favourite)) {
              $conn->commit();
              $success_message = "Tasting note #" . $post_note_id . " updated successfully.";
              $selected_note = getNoteDetails($conn, $post_note_id);
            } else {
              throw new Exception("Error updating tasting note record.");
            }
          } else {
            if (insertTastingNote($conn, $post_bottle_id, $post_wine_id, $post_tasting_date, $_SESSION['user_id'], $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_status, $post_blind, $img, $img_class, $post_favourite)) {
              $tasting_note_id = $conn->insert_id;
              $conn->commit();
              $success_message = "Tasting note added successfully!";
              $bottle_id = $post_bottle_id;
              $tasting_date = $post_tasting_date;
            } else {
              throw new Exception("Error saving tasting note.");
            }
          }
          // Refresh list of accessible notes
          $all_notes = getTastingNotes($conn);
          $accessible_notes = [];
          foreach ($all_notes as $n) {
            if ($can_edit_all || (int)$n['user_id'] === (int)$_SESSION['user_id']) $accessible_notes[] = $n;
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error saving tasting note: " . $e->getMessage();
        }
      }
    }

    // Action 2: Confirm Cellar Bottle Consumption
    if (isset($_POST['confirmConsume'])) {
      $consume_bottle_id = filter_input(INPUT_POST, 'consume_bottle_id', FILTER_VALIDATE_INT);
      $consumption_date = sanitizeInput($_POST['consumption_date'] ?? date('Y-m-d'));
      $consume_note_id = filter_input(INPUT_POST, 'tasting_note_id', FILTER_VALIDATE_INT) ?: null;

      if ($consume_bottle_id && !empty($consumption_date)) {
        if (markBottleAsConsumed($conn, $consume_bottle_id, $consumption_date, $consume_note_id)) {
          $success_message = "Bottle #" . $consume_bottle_id . " marked as consumed in inventory!";
        } else {
          $errors[] = "Error marking bottle #" . $consume_bottle_id . " as consumed.";
        }
      }
    }
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Tasting Notes - Cellar Administration';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main" style="width: 100%; max-width: 1200px; margin: 0 auto;">
    
    <div style="margin-bottom: 16px;">
      <a href="index.php" style="text-decoration: none; color: var(--secondary-accent, firebrick); font-size: 0.95rem;">
        &larr; Return to Admin Hub
      </a>
    </div>

    <div class="card">
      <div class="admin-card-header">
        <h2 style="margin: 0; color: var(--secondary-accent, firebrick);">
          &#128221; Tasting Journal &amp; Notes
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageBottles.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &larr; Cellar Bottles
          </a>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div style="background-color: #ffdddd; border-left: 5px solid #f44336; padding: 12px 16px; margin-bottom: 20px;">
          <strong style="color: #a00;">Please correct the following errors:</strong>
          <ul style="margin: 8px 0 0; padding-left: 20px; color: #a00;">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 12px 16px; margin-bottom: 20px; color: #2e7d32; line-height: 1.5;">
          <?php echo $success_message; ?>
        </div>

        <?php if (!empty($bottle_id) && !empty($tasting_date) && strpos($success_message, 'added successfully') !== false): ?>
          <div style="margin: 20px 0; padding: 16px; border: 1px solid var(--secondary-accent, firebrick); background-color: #fff8f8; border-radius: 6px;">
            <p style="margin: 0 0 12px 0; color: #333;">
              Would you like to mark cellar bottle <strong>#<?php echo htmlspecialchars($bottle_id, ENT_QUOTES, 'UTF-8'); ?></strong> as consumed on <strong><?php echo htmlspecialchars($tasting_date, ENT_QUOTES, 'UTF-8'); ?></strong>?
            </p>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="consume_bottle_id" value="<?php echo htmlspecialchars($bottle_id, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="consumption_date" value="<?php echo htmlspecialchars($tasting_date, ENT_QUOTES, 'UTF-8'); ?>">
              <?php if (!empty($tasting_note_id)): ?>
                <input type="hidden" name="tasting_note_id" value="<?php echo (int)$tasting_note_id; ?>">
              <?php endif; ?>
              <button type="submit" name="confirmConsume" class="btn-action" style="padding: 8px 18px; font-weight: bold; margin-right: 10px; min-height: 40px;">
                Yes, mark as consumed
              </button>
            </form>
            <a href="manageTastingNotes.php" class="btn-action btn-secondary" style="padding: 8px 18px; text-decoration: none; min-height: 40px; display: inline-flex; align-items: center;">
              No, leave in cellar
            </a>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- High-level Journal Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo count($accessible_notes); ?></span>
            <span class="kpi-icon">&#128221;</span>
          </div>
          <div class="kpi-label">Tasting Notes</div>
          <div class="kpi-sub">Accessible journal reviews</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo $wset_enabled ? 'WSET SAT' : strtoupper($scale); ?></span>
            <span class="kpi-icon">&#127891;</span>
          </div>
          <div class="kpi-label">Active Assessment Scale</div>
          <div class="kpi-sub"><?php echo $wset_enabled ? 'BLIC Qualitative Framework' : 'Standard Cellar Scoring'; ?></div>
        </div>
      </div>

      <!-- Mode Selector Tabs -->
      <div class="admin-mode-tabs" style="margin-bottom: 20px;">
        <?php if ($can_add): ?>
          <a href="manageTastingNotes.php?mode=add" class="admin-tab-btn <?php echo ($mode === 'add') ? 'active' : ''; ?>">
            &#10010; Standard Tasting Note
          </a>
          <a href="manageTastingNotes.php?mode=blind" class="admin-tab-btn <?php echo ($mode === 'blind') ? 'active' : ''; ?>">
            &#127917; Blind Tasting Note
          </a>
        <?php endif; ?>
        <?php if ($can_edit): ?>
          <a href="manageTastingNotes.php?mode=edit" class="admin-tab-btn <?php echo ($mode === 'edit') ? 'active' : ''; ?>">
            &#9998; Edit Tasting Note
          </a>
        <?php endif; ?>
        <a href="#notesJournalSection" class="admin-tab-btn" style="color: #555;">
          📋 Notes Directory (<?php echo count($accessible_notes); ?>)
        </a>
      </div>

      <!-- Form Container -->
      <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 18px;">
          <h3 style="margin: 0; color: #333; font-size: 1.2rem;">
            <?php 
              if ($mode === 'edit') {
                echo $selected_note ? "&#9998; Editing Tasting Note #{$selected_note['note_id']}" : "&#9998; Select Note to Edit";
              } elseif ($mode === 'blind') {
                echo "&#127917; Record Blind Tasting Assessment";
              } else {
                echo "&#10010; Record Tasting Note";
              }
            ?>
          </h3>
        </div>

        <?php if ($mode === 'edit' && !$selected_note): ?>
          <!-- Select Note to Edit -->
          <div style="max-width: 600px; margin-bottom: 20px;">
            <label for="searchNoteBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
              Choose Note to Edit:
            </label>
            <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
              <input type="text" id="searchNoteBox" placeholder="🔍 Search notes by date, cuvée, producer..." onkeyup="filterDropdown('selectNote', 'searchNoteBox', cachedNoteOptions)" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
              <select id="selectNote" onchange="if(this.value) window.location.href='manageTastingNotes.php?mode=edit&note_id=' + this.value;" style="width: 100%; border: none; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; border-radius: 0 0 4px 4px; min-height: 44px;">
                <option value="">-- Select Tasting Note --</option>
                <?php foreach ($accessible_notes as $n): ?>
                  <option value="<?php echo (int)$n['note_id']; ?>">
                    #<?php echo (int)$n['note_id']; ?> — <?php echo htmlspecialchars($n['tasting_date'] . ' — ' . getWineName($n['nameconvention'], $n['vintage'], $n['name'], $n['producer'], $n['grape'], $n['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php else: ?>
          
          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($mode === 'edit'): ?>
              <input type="hidden" name="updateTastingNote" value="1">
              <input type="hidden" name="note_id" value="<?php echo (int)$selected_note['note_id']; ?>">
              <input type="hidden" name="user_id" value="<?php echo (int)$selected_note['user_id']; ?>">
            <?php else: ?>
              <input type="hidden" name="postTastingNote" value="1">
            <?php endif; ?>

            <!-- Row 1: Target Identification -->
            <?php if ($mode === 'blind'): ?>
              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="searchBottleBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Select Cellar Bottle: <span style="color: red;">*</span>
                  </label>
                  <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <input type="text" id="searchBottleBox" placeholder="🔍 Search cellar bottle by ID..." onkeyup="filterDropdown('bottle_id', 'searchBottleBox', cachedBottleOptions)" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                    <select name="bottle_id" id="bottle_id" required onchange="window.location.href='manageTastingNotes.php?mode=blind&bottle_id=' + this.value;" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                      <option value="">-- Choose Bottle from Cellar --</option>
                      <?php foreach ($bottles as $b): ?>
                        <option value="<?php echo (int)$b['bottle_id']; ?>" <?php echo ($selected_bottle && (int)$selected_bottle['bottle_id'] === (int)$b['bottle_id']) ? 'selected' : ''; ?>>
                          Bottle #<?php echo (int)$b['bottle_id']; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Cellar identity concealed during assessment</div>
                </div>

                <?php if ($selected_bottle): ?>
                  <input type="hidden" name="wine_id" value="<?php echo (int)$selected_bottle['wine_id']; ?>">
                  <div>
                    <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Bottle Identity (Blind Tasting):</label>
                    <div style="padding: 10px 14px; background: white; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; box-sizing: border-box; display: flex; align-items: center; justify-content: space-between;">
                      <span id="revealedName" style="filter: blur(5px); transition: filter 0.3s; cursor: pointer;" onclick="this.style.filter='none';" title="Click to reveal identity">
                        <?php echo htmlspecialchars($wine_name, ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                      <small style="color: #0d6efd; cursor: pointer;" onclick="document.getElementById('revealedName').style.filter='none';">Reveal</small>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="searchWineBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Wine Cuv&eacute;e: <span style="color: red;">*</span>
                  </label>
                  <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <input type="text" id="searchWineBox" placeholder="🔍 Filter wine cuvées..." onkeyup="filterDropdown('wine_id', 'searchWineBox', cachedWineOptions)" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                    <select name="wine_id" id="wine_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                      <option value="">-- Select Wine --</option>
                      <?php 
                        $current_wine_id = ($mode === 'edit') ? (int)$selected_note['wine_id'] : ($add_wine_id ?? 0);
                        foreach ($wines as $w): 
                      ?>
                        <option value="<?php echo (int)$w['wine_id']; ?>" <?php echo ($current_wine_id === (int)$w['wine_id']) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($w['country'] . ': ' . $w['region'] . ' — ' . getWineName($w['nameconvention'], $w['vintage'], $w['name'], $w['producer'], $w['grape'], $w['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div>
                  <label for="tasting_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Tasting Date: <span style="color: red;">*</span>
                  </label>
                  <?php $note_date = ($mode === 'edit') ? $selected_note['tasting_date'] : date('Y-m-d'); ?>
                  <input type="date" name="tasting_date" id="tasting_date" required value="<?php echo htmlspecialchars($note_date, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>
              </div>
            <?php endif; ?>

            <!-- Row 2: Scores & Assessment -->
            <?php if ($wset_enabled): ?>
              <?php
                $cur_b = ($mode === 'edit' && isset($selected_note['wset_balance']) && $selected_note['wset_balance'] !== null) ? (string)(float)$selected_note['wset_balance'] : '';
                $cur_l = ($mode === 'edit' && isset($selected_note['wset_length']) && $selected_note['wset_length'] !== null) ? (string)(float)$selected_note['wset_length'] : '';
                $cur_i = ($mode === 'edit' && isset($selected_note['wset_intensity']) && $selected_note['wset_intensity'] !== null) ? (string)(float)$selected_note['wset_intensity'] : '';
                $cur_c = ($mode === 'edit' && isset($selected_note['wset_complexity']) && $selected_note['wset_complexity'] !== null) ? (string)(float)$selected_note['wset_complexity'] : '';
              ?>
              <div style="background: white; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                  <h4 style="margin: 0; color: var(--secondary-accent, firebrick); font-size: 0.95rem;">
                    WSET SAT Evaluation (BLIC Framework)
                  </h4>
                  <small style="color: #64748b;">Balance &bull; Length &bull; Intensity &bull; Complexity</small>
                </div>
                <div class="form-row-2col">
                  <div>
                    <label for="wset_balance" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Balance (0.0 &ndash; 1.0):</label>
                    <select name="wset_balance" id="wset_balance" onchange="updateWsetRecommendation()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.9rem;">
                      <option value="" <?php echo ($cur_b === '') ? 'selected' : ''; ?>>-- Not evaluated --</option>
                      <option value="0" <?php echo ($cur_b === '0') ? 'selected' : ''; ?>>0.0 (Unbalanced)</option>
                      <option value="0.5" <?php echo ($cur_b === '0.5') ? 'selected' : ''; ?>>0.5 (Moderate)</option>
                      <option value="1" <?php echo ($cur_b === '1') ? 'selected' : ''; ?>>1.0 (Well Balanced)</option>
                    </select>
                  </div>
                  <div>
                    <label for="wset_length" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Length / Finish (0.0 &ndash; 1.0):</label>
                    <select name="wset_length" id="wset_length" onchange="updateWsetRecommendation()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.9rem;">
                      <option value="" <?php echo ($cur_l === '') ? 'selected' : ''; ?>>-- Not evaluated --</option>
                      <option value="0" <?php echo ($cur_l === '0') ? 'selected' : ''; ?>>0.0 (Short)</option>
                      <option value="0.5" <?php echo ($cur_l === '0.5') ? 'selected' : ''; ?>>0.5 (Medium)</option>
                      <option value="1" <?php echo ($cur_l === '1') ? 'selected' : ''; ?>>1.0 (Long)</option>
                    </select>
                  </div>
                  <div>
                    <label for="wset_intensity" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Intensity (0.0 &ndash; 1.0):</label>
                    <select name="wset_intensity" id="wset_intensity" onchange="updateWsetRecommendation()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.9rem;">
                      <option value="" <?php echo ($cur_i === '') ? 'selected' : ''; ?>>-- Not evaluated --</option>
                      <option value="0" <?php echo ($cur_i === '0') ? 'selected' : ''; ?>>0.0 (Light / Lacking)</option>
                      <option value="0.5" <?php echo ($cur_i === '0.5') ? 'selected' : ''; ?>>0.5 (Medium)</option>
                      <option value="1" <?php echo ($cur_i === '1') ? 'selected' : ''; ?>>1.0 (Pronounced)</option>
                    </select>
                  </div>
                  <div>
                    <label for="wset_complexity" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Complexity (0.0 &ndash; 1.0):</label>
                    <select name="wset_complexity" id="wset_complexity" onchange="updateWsetRecommendation()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.9rem;">
                      <option value="" <?php echo ($cur_c === '') ? 'selected' : ''; ?>>-- Not evaluated --</option>
                      <option value="0" <?php echo ($cur_c === '0') ? 'selected' : ''; ?>>0.0 (Simple)</option>
                      <option value="0.5" <?php echo ($cur_c === '0.5') ? 'selected' : ''; ?>>0.5 (Some Nuance)</option>
                      <option value="1" <?php echo ($cur_c === '1') ? 'selected' : ''; ?>>1.0 (Complex / Layered)</option>
                    </select>
                  </div>
                </div>

                <!-- Dynamic Recommendation Card -->
                <div id="wsetRecommendationCard" style="margin-top: 14px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                  <div>
                    <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: bold;">
                      Calculated WSET SAT Score:
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                      <span id="wset_total_display" style="font-size: 1.25rem; font-weight: bold; color: var(--secondary-accent, firebrick);">-- / 4.0</span>
                      <span id="wset_quality_badge" style="display: inline-block; padding: 3px 9px; font-size: 0.82rem; font-weight: 600; border-radius: 4px; background: #e2e8f0; color: #334155;">Unrated</span>
                    </div>
                  </div>

                  <div style="text-align: right;">
                    <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: bold;">
                      Rating Recommendation:
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 2px; justify-content: flex-end;">
                      <span id="wset_suggested_score" style="font-size: 0.95rem; font-weight: 500; color: #0f172a;">
                        <span style="color: #94a3b8; font-style: italic;">Select BLIC criteria to view score suggestion</span>
                      </span>
                      <button type="button" id="applyWsetScoreBtn" style="display: none; padding: 5px 12px; font-size: 0.82rem; background: var(--secondary-accent, firebrick); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;" onclick="applyWsetScore()">
                        Apply
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="form-row-3col" style="margin-bottom: 16px;">
              <div>
                <label for="pts_100" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">100-Point Score:</label>
                <input type="number" min="50" max="100" name="pts_100" id="pts_100" value="<?php echo ($mode === 'edit') ? htmlspecialchars($selected_note['pts_100'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="e.g. 94" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="pts_20" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">20-Point Score:</label>
                <input type="number" min="10" max="20" name="pts_20" id="pts_20" value="<?php echo ($mode === 'edit') ? htmlspecialchars($selected_note['pts_20'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="e.g. 17" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="flawed" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Flawed / Corked:</label>
                <?php $current_flawed = ($mode === 'edit') ? ($selected_note['flawed_yn'] ?? 'no') : 'no'; ?>
                <select name="flawed" id="flawed" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="no" <?php echo ($current_flawed === 'no') ? 'selected' : ''; ?>>No (Clean)</option>
                  <option value="yes" <?php echo ($current_flawed === 'yes') ? 'selected' : ''; ?>>Yes (Flawed Bottle)</option>
                </select>
              </div>
            </div>

            <div style="margin-bottom: 16px;">
              <label for="tasting_note" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Tasting Note &amp; Qualitative Notes: <span style="color: red;">*</span>
              </label>
              <textarea name="tasting_note" id="tasting_note" rows="8" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 0.92rem;" placeholder="Appearance, aroma profile, palate structure, tannins, acidity, finish, and general impressions..."><?php echo ($mode === 'edit') ? htmlspecialchars($selected_note['tasting_note'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            </div>

            <!-- Drinking Window & Metadata -->
            <div class="form-row-3col" style="margin-bottom: 20px;">
              <div>
                <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Drinking Window (Years):</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                  <input type="text" name="drink_from" maxlength="4" value="<?php echo ($mode === 'edit') ? htmlspecialchars($selected_note['drink_from'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="From" style="flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center;">
                  <span>&ndash;</span>
                  <input type="text" name="drink_through" maxlength="4" value="<?php echo ($mode === 'edit') ? htmlspecialchars($selected_note['drink_through'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="To" style="flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center;">
                </div>
              </div>

              <div>
                <label for="blind" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Blind Tasting Mode:</label>
                <?php $current_blind = ($mode === 'edit') ? ($selected_note['blind'] ?? 'not blind') : (($mode === 'blind') ? 'single blind' : 'not blind'); ?>
                <select name="blind" id="blind" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="not blind" <?php echo ($current_blind === 'not blind') ? 'selected' : ''; ?>>Not Blind</option>
                  <option value="single blind" <?php echo ($current_blind === 'single blind') ? 'selected' : ''; ?>>Single Blind</option>
                  <option value="double blind" <?php echo ($current_blind === 'double blind') ? 'selected' : ''; ?>>Double Blind</option>
                </select>
              </div>

              <div>
                <label for="status" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Publication Status:</label>
                <?php $current_status = ($mode === 'edit') ? ($selected_note['status'] ?? 'draft') : 'draft'; ?>
                <select name="status" id="status" <?php echo !$can_publish ? 'disabled' : ''; ?> style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="draft" <?php echo ($current_status === 'draft') ? 'selected' : ''; ?>>Draft</option>
                  <option value="published" <?php echo ($current_status === 'published') ? 'selected' : ''; ?>>Published</option>
                  <option value="archived" <?php echo ($current_status === 'archived') ? 'selected' : ''; ?>>Archived</option>
                </select>
              </div>
            </div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                <?php echo ($mode === 'edit') ? 'Update Tasting Note' : 'Record Tasting Note'; ?>
              </button>
              <?php if ($mode === 'edit'): ?>
                <a href="manageTastingNotes.php" class="btn-action btn-secondary" style="padding: 12px 20px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
                  Cancel
                </a>
              <?php endif; ?>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- Searchable Tasting Notes Journal Ledger -->
      <div id="notesJournalSection" style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
            Tasting Journal Directory (<?php echo count($accessible_notes); ?> notes)
          </h3>
          <input type="text" id="noteSearchBox" class="admin-search-box" style="margin-bottom: 0; max-width: 320px;" placeholder="Search notes..." onkeyup="filterNotesTable()">
        </div>

        <div class="admin-table-container" style="max-height: 480px; overflow-y: auto;">
          <table id="notesTable">
            <thead>
              <tr>
                <th style="width: 100px;">Date</th>
                <th>Wine &amp; Cuv&eacute;e</th>
                <th style="text-align: center;">Score</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($accessible_notes)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: #777; padding: 20px;">No tasting notes found.</td>
                </tr>
              <?php else: ?>
                <?php foreach (array_slice($accessible_notes, 0, 100) as $note): 
                  $n_title = getWineName($note['nameconvention'], $note['vintage'], $note['name'], $note['producer'], $note['grape'], $note['vineyard']);
                ?>
                  <tr style="<?php echo ($requested_note_id === (int)$note['note_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                    <td data-label="Date"><?php echo htmlspecialchars($note['tasting_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="card-primary-cell">
                      <div><strong><?php echo htmlspecialchars($n_title, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                      <div style="font-size: 0.78rem; color: #777;">
                        <?php echo htmlspecialchars($note['producer'] ?? '', ENT_QUOTES, 'UTF-8'); ?> &bull; 
                        <?php echo htmlspecialchars($note['region'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    </td>
                    <td data-label="Score" style="text-align: center;">
                      <?php if (!empty($note['pts_100'])): ?>
                        <span style="font-weight: bold;"><?php echo (int)$note['pts_100']; ?></span><span style="font-size: 0.78rem; color: #888;">/100</span>
                      <?php elseif (!empty($note['pts_20'])): ?>
                        <span style="font-weight: bold;"><?php echo (int)$note['pts_20']; ?></span><span style="font-size: 0.78rem; color: #888;">/20</span>
                      <?php elseif (!empty($note['wsetpts'])): ?>
                        <span style="font-weight: bold; color: #2e7d32;"><?php echo htmlspecialchars($note['wsetpts'], ENT_QUOTES, 'UTF-8'); ?></span><span style="font-size: 0.78rem; color: #888;">/4</span>
                      <?php else: ?>
                        <span style="color: #aaa;">—</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Status">
                      <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; <?php echo ($note['status'] === 'published') ? 'background: #e8f5e9; color: #2e7d32;' : 'background: #f5f5f5; color: #666;'; ?>">
                        <?php echo htmlspecialchars($note['status'] ?? 'draft', ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <?php if ($can_edit): ?>
                        <a href="manageTastingNotes.php?mode=edit&note_id=<?php echo (int)$note['note_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                          Edit
                        </a>
                      <?php endif; ?>
                      <a href="/wines.php?id=<?php echo (int)$note['wine_id']; ?>" target="_blank" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                        View Wine &rarr;
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  let cachedNoteOptions = [];
  let cachedBottleOptions = [];
  let cachedWineOptions = [];
  let activeSuggestedScore = null;
  const ratingScale = "<?php echo htmlspecialchars($scale, ENT_QUOTES, 'UTF-8'); ?>";

  document.addEventListener("DOMContentLoaded", function() {
    // Cache dropdown options
    const noteSel = document.getElementById('selectNote');
    if (noteSel) {
      for (let i = 1; i < noteSel.options.length; i++) {
        const opt = noteSel.options[i];
        cachedNoteOptions.push({ value: opt.value, text: opt.textContent });
      }
    }

    const btlSel = document.getElementById('bottle_id');
    if (btlSel) {
      for (let i = 1; i < btlSel.options.length; i++) {
        const opt = btlSel.options[i];
        cachedBottleOptions.push({ value: opt.value, text: opt.textContent });
      }
    }

    const wineSel = document.getElementById('wine_id');
    if (wineSel) {
      for (let i = 1; i < wineSel.options.length; i++) {
        const opt = wineSel.options[i];
        cachedWineOptions.push({ value: opt.value, text: opt.textContent });
      }
    }

    // Initialise WSET Recommendation if elements present
    updateWsetRecommendation();
  });

  function filterDropdown(selectId, searchBoxId, cachedOptions) {
    const query = document.getElementById(searchBoxId).value.toLowerCase().trim();
    const select = document.getElementById(selectId);
    if (!select || !cachedOptions) return;

    const currentVal = select.value;
    const terms = query.split(/\s+/).filter(t => t.length > 0);

    while (select.options.length > 1) {
      select.remove(1);
    }

    cachedOptions.forEach(optData => {
      const textLower = optData.text.toLowerCase();
      const matches = terms.every(term => textLower.includes(term));
      if (matches) {
        const opt = document.createElement('option');
        opt.value = optData.value;
        opt.textContent = optData.text;
        if (optData.value === currentVal) opt.selected = true;
        select.appendChild(opt);
      }
    });
  }

  function updateWsetRecommendation() {
    const wb = document.getElementById('wset_balance');
    const wl = document.getElementById('wset_length');
    const wi = document.getElementById('wset_intensity');
    const wc = document.getElementById('wset_complexity');
    const totalEl = document.getElementById('wset_total_display');
    const badgeEl = document.getElementById('wset_quality_badge');
    const sugEl = document.getElementById('wset_suggested_score');
    const btnEl = document.getElementById('applyWsetScoreBtn');

    if (!wb || !wl || !wi || !wc || !totalEl || !badgeEl || !sugEl) return;

    const hasAny = (wb.value !== '' || wl.value !== '' || wi.value !== '' || wc.value !== '');
    if (!hasAny) {
      totalEl.textContent = '-- / 4.0';
      badgeEl.textContent = 'Unrated';
      badgeEl.style.backgroundColor = '#e2e8f0';
      badgeEl.style.color = '#334155';
      sugEl.innerHTML = '<span style="color: #94a3b8; font-style: italic;">Select BLIC criteria to view score suggestion</span>';
      if (btnEl) btnEl.style.display = 'none';
      activeSuggestedScore = null;
      return;
    }

    const total = (Number(wb.value || 0) + Number(wl.value || 0) + Number(wi.value || 0) + Number(wc.value || 0));
    const rounded = Math.round(total * 10) / 10;
    totalEl.textContent = rounded.toFixed(1) + ' / 4.0';

    let quality = 'Poor / Faulty';
    let pts100 = '< 70';
    let pts20 = '0';
    let default100 = 65;
    let default20 = 0;
    let badgeBg = '#fed7d7';
    let badgeColor = '#9b2c2c';

    if (rounded >= 4.0) {
      quality = 'Extraordinary / Perfection';
      pts100 = '98–100';
      pts20 = '15–20';
      default100 = 99;
      default20 = 18;
      badgeBg = '#d1fae5';
      badgeColor = '#065f46';
    } else if (rounded >= 3.5) {
      quality = 'Extraordinary';
      pts100 = '95–97';
      pts20 = '13 or 14';
      default100 = 96;
      default20 = 14;
      badgeBg = '#d1fae5';
      badgeColor = '#065f46';
    } else if (rounded >= 3.0) {
      quality = 'Outstanding';
      pts100 = '93–94';
      pts20 = '11 or 12';
      default100 = 94;
      default20 = 12;
      badgeBg = '#e0f2fe';
      badgeColor = '#0369a1';
    } else if (rounded >= 2.5) {
      quality = 'Very Good';
      pts100 = '90–92';
      pts20 = '9 or 10';
      default100 = 91;
      default20 = 10;
      badgeBg = '#e0f2fe';
      badgeColor = '#0369a1';
    } else if (rounded >= 2.0) {
      quality = 'Good';
      pts100 = '85–89';
      pts20 = '7 or 8';
      default100 = 87;
      default20 = 8;
      badgeBg = '#fef3c7';
      badgeColor = '#92400e';
    } else if (rounded >= 1.5) {
      quality = 'Good';
      pts100 = '80–84';
      pts20 = '5 or 6';
      default100 = 82;
      default20 = 6;
      badgeBg = '#fef3c7';
      badgeColor = '#92400e';
    } else if (rounded >= 1.0) {
      quality = 'Acceptable / Mediocre';
      pts100 = '75–79';
      pts20 = '3 or 4';
      default100 = 77;
      default20 = 4;
      badgeBg = '#f1f5f9';
      badgeColor = '#475569';
    } else if (rounded >= 0.5) {
      quality = 'Below Average';
      pts100 = '70–74';
      pts20 = '1 or 2';
      default100 = 72;
      default20 = 2;
      badgeBg = '#ffedd5';
      badgeColor = '#9a3412';
    }

    badgeEl.textContent = quality;
    badgeEl.style.backgroundColor = badgeBg;
    badgeEl.style.color = badgeColor;

    if (ratingScale === '100-point') {
      activeSuggestedScore = default100;
      sugEl.innerHTML = '<strong>' + pts100 + ' pts</strong> <small style=\"color:#64748b;\">(&quot;' + quality + '&quot; &bull; ' + pts20 + ' on 20-pt)</small>';
      if (btnEl) {
        btnEl.textContent = 'Apply ' + default100;
        btnEl.style.display = 'inline-block';
      }
    } else {
      activeSuggestedScore = default20;
      sugEl.innerHTML = '<strong>' + pts20 + ' pts</strong> <small style=\"color:#64748b;\">(&quot;' + quality + '&quot; &bull; ' + pts100 + ' on 100-pt)</small>';
      if (btnEl) {
        btnEl.textContent = 'Apply ' + default20;
        btnEl.style.display = 'inline-block';
      }
    }
  }

  function applyWsetScore() {
    if (activeSuggestedScore === null) return;
    if (ratingScale === '100-point') {
      const field = document.getElementById('pts_100');
      if (field) {
        field.value = activeSuggestedScore;
        field.focus();
      }
    } else {
      const field = document.getElementById('pts_20');
      if (field) {
        field.value = activeSuggestedScore;
        field.focus();
      }
    }
  }

  function filterNotesTable() {
    const searchVal = document.getElementById('noteSearchBox').value.toLowerCase().trim();
    const table = document.getElementById('notesTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.cells.length < 5) continue;
      const textContent = row.textContent.toLowerCase();
      row.style.display = (!searchVal || textContent.includes(searchVal)) ? '' : 'none';
    }
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
