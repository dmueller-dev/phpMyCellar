<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialisation file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Tasting Note Management Dashboard.
   *
   * Consolidates standard tasting notes (addTastingNote.php), blind tasting sessions
   * (blindTasting.php), and tasting note editing (editTastingNote.php), including
   * WSET SAT BLIC evaluations, bottle consumption tracking, image formatting, and
   * favourite designations.
   *
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
  $saved_wine_id = null;
  $has_post_error = false;

  // Determine operational mode: 'add', 'blind', or 'edit'
  $requested_note_id = filter_input(INPUT_GET, 'note_id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_GET, 'edit_note', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
  $bottle_id_param = filter_input(INPUT_GET, 'bottle_id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'bottle_id', FILTER_VALIDATE_INT);
  $wine_id_param = filter_input(INPUT_GET, 'wine_id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
  $mode_param = sanitizeInput($_GET['mode'] ?? $_POST['mode'] ?? '');

  if ($requested_note_id && $can_edit) {
    $mode = 'edit';
  } elseif ($mode_param === 'edit' && $can_edit) {
    $mode = 'edit';
  } elseif (($mode_param === 'blind' || ($mode_param === '' && $bottle_id_param && !$wine_id_param)) && $can_add) {
    $mode = 'blind';
  } elseif (($mode_param === 'add' || $mode_param === 'standard') && $can_add) {
    $mode = 'add';
  } else {
    $mode = $can_add ? 'add' : 'edit';
  }

  // Ledger display limit (100, 250, 500, 1000, or 'all')
  $ledger_limit = sanitizeInput($_GET['limit'] ?? 'all');
  if ($ledger_limit !== 'all') {
    $ledger_limit = (int)$ledger_limit;
    if (!in_array($ledger_limit, [100, 250, 500, 1000], true)) {
      $ledger_limit = 'all';
    }
  }

  $scale = getRatingScale();
  $wset_enabled = isWsetSATEntryEnabled();
  $wset_mode = function_exists('getWsetSATMode') ? getWsetSATMode() : 'disabled';

  // Handle Form Submissions
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      http_response_code(403);
      die("CSRF token validation failed.");
    }

    // Action 1: Add or Update Tasting Note (supports both updateTastingNote and legacy 'update')
    if (isset($_POST['postTastingNote']) || isset($_POST['updateTastingNote']) || isset($_POST['update'])) {
      $is_post_edit = isset($_POST['updateTastingNote']) || isset($_POST['update']);

      if ($is_post_edit && !$can_edit) {
        $errors[] = "You do not have permission to edit tasting notes.";
      } elseif (!$is_post_edit && !$can_add) {
        $errors[] = "You do not have permission to add tasting notes.";
      }

      $post_bottle_id = filter_input(INPUT_POST, 'bottle_id', FILTER_VALIDATE_INT) ?: null;
      $post_wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $post_tasting_date = sanitizeInput($_POST['tasting_date'] ?? '');
      $post_tasting_note = sanitizeInput($_POST['tasting_note'] ?? '');
      $post_flawed = sanitizeInput($_POST['flawed'] ?? $_POST['flawed_yn'] ?? 'no');
      $post_favourite = sanitizeInput($_POST['favourite'] ?? 'no');
      $post_blind = sanitizeInput($_POST['blind'] ?? (($mode === 'blind') ? 'blind' : 'not blind'));
      if ($post_blind !== 'blind' && $post_blind !== 'not blind') {
        $post_blind = 'blind';
      }

      // Parse 20-point score (0 to 20) and legacy dmpts parameter
      $raw_pts_20 = isset($_POST['pts_20']) ? trim((string)$_POST['pts_20']) : '';
      if ($raw_pts_20 === '' && isset($_POST['dmpts']) && trim((string)$_POST['dmpts']) !== '') {
        @trigger_error(
          'POST parameter "dmpts" is deprecated since version 1.0.1 and will be removed in version 2.0.0. Use "pts_20" instead.',
          E_USER_DEPRECATED
        );
        $raw_pts_20 = trim((string)$_POST['dmpts']);
      }
      $pts_20 = ($raw_pts_20 === '') ? null : filter_var($raw_pts_20, FILTER_VALIDATE_INT);

      // Parse 100-point score (50 to 100)
      $raw_pts_100 = isset($_POST['pts_100']) ? trim((string)$_POST['pts_100']) : '';
      $pts_100 = ($raw_pts_100 === '') ? null : filter_var($raw_pts_100, FILTER_VALIDATE_INT);

      // Parse WSET SAT BLIC criteria
      $wset_balance = null;
      $wset_length = null;
      $wset_intensity = null;
      $wset_complexity = null;
      $wsetpts = null;

      $has_wset_post = (
        (isset($_POST['wset_balance']) && $_POST['wset_balance'] !== '') ||
        (isset($_POST['wset_length']) && $_POST['wset_length'] !== '') ||
        (isset($_POST['wset_intensity']) && $_POST['wset_intensity'] !== '') ||
        (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] !== '')
      );

      if ($wset_enabled && $has_wset_post) {
        $raw_b = (isset($_POST['wset_balance']) && $_POST['wset_balance'] !== '') ? filter_input(INPUT_POST, 'wset_balance', FILTER_VALIDATE_FLOAT) : null;
        $raw_l = (isset($_POST['wset_length']) && $_POST['wset_length'] !== '') ? filter_input(INPUT_POST, 'wset_length', FILTER_VALIDATE_FLOAT) : null;
        $raw_i = (isset($_POST['wset_intensity']) && $_POST['wset_intensity'] !== '') ? filter_input(INPUT_POST, 'wset_intensity', FILTER_VALIDATE_FLOAT) : null;
        $raw_c = (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] !== '') ? filter_input(INPUT_POST, 'wset_complexity', FILTER_VALIDATE_FLOAT) : null;
        if ($raw_b !== false && $raw_b !== null) $wset_balance = number_format($raw_b, 1, '.', '');
        if ($raw_l !== false && $raw_l !== null) $wset_length = number_format($raw_l, 1, '.', '');
        if ($raw_i !== false && $raw_i !== null) $wset_intensity = number_format($raw_i, 1, '.', '');
        if ($raw_c !== false && $raw_c !== null) $wset_complexity = number_format($raw_c, 1, '.', '');
        if ($wset_balance !== null || $wset_length !== null || $wset_intensity !== null || $wset_complexity !== null) {
          $wsetpts = number_format((float)$wset_balance + (float)$wset_length + (float)$wset_intensity + (float)$wset_complexity, 1, '.', '');
        }
      }

      // Parse drinking window years
      $raw_drink_from = trim((string)($_POST['drink_from'] ?? ''));
      $raw_drink_through = trim((string)($_POST['drink_through'] ?? ''));
      $drink_from = ($raw_drink_from === '') ? null : $raw_drink_from;
      $drink_through = ($raw_drink_through === '') ? null : $raw_drink_through;
      $errorsDrinkDates = validateDrinkDatesInput($drink_from ?? '', $drink_through ?? '');
      if (empty($errorsDrinkDates)) {
        $drink_from = ($drink_from !== null) ? (int)$drink_from : null;
        $drink_through = ($drink_through !== null) ? (int)$drink_through : null;
      }

      // Parse image filename and formatting/alignment class
      $img = sanitizeInput($_POST['img'] ?? '');
      $img_class = sanitizeInput($_POST['img_class'] ?? '');
      if ($img_class === 'null' || $img_class === '') {
        $img_class = null;
      }
      $errorsImg = validateImageInput($img, $img_class);

      if ($is_post_edit) {
        $post_note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
        $check_note = $post_note_id ? getNoteDetails($conn, $post_note_id) : null;
        $post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: ($check_note ? (int)$check_note['user_id'] : (int)$_SESSION['user_id']);

        if (!$check_note) {
          $errors[] = "Error updating tasting note: Invalid note ID.";
        } elseif (!$can_edit_all) {
          if ((int)$check_note['user_id'] !== (int)$_SESSION['user_id']) {
            $errors[] = "You do not have permission to edit this tasting note.";
          } elseif ($check_note['status'] === 'published' && !$can_publish) {
            $errors[] = "This tasting note has been published and can no longer be edited.";
          }
        }

        // Preserve existing WSET SAT scores if WSET entry is disabled globally during edit
        if (!$wset_enabled && $check_note) {
          $wset_balance = $check_note['wset_balance'] ?? null;
          $wset_length = $check_note['wset_length'] ?? null;
          $wset_intensity = $check_note['wset_intensity'] ?? null;
          $wset_complexity = $check_note['wset_complexity'] ?? null;
          $wsetpts = $check_note['wsetpts'] ?? null;
        }

        $post_status = $can_publish ? sanitizeInput($_POST['status'] ?? 'draft') : ($check_note ? $check_note['status'] : 'draft');
        if ($post_status !== 'published' && $post_status !== 'draft') {
          $post_status = 'draft';
        }
        $valErrors = validateNoteInput($post_note_id, $post_wine_id, $post_tasting_date, $post_user_id, $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_blind, $post_status, $img, $img_class, $post_favourite);
      } else {
        $post_status = $can_publish ? sanitizeInput($_POST['status'] ?? 'draft') : 'draft';
        if ($post_status !== 'published' && $post_status !== 'draft') {
          $post_status = 'draft';
        }
        $valErrors = validateNoteInput(null, $post_wine_id, $post_tasting_date, $_SESSION['user_id'], $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_blind, $post_status, $img, $img_class, $post_favourite);
      }

      if (!empty($errorsImg)) {
        $errors[] = $errorsImg[0];
      }
      if (!empty($errorsDrinkDates)) {
        $errors[] = $errorsDrinkDates[0];
      }
      if (!empty($valErrors)) {
        $errors = array_merge($errors, $valErrors);
      }

      if (empty($errors)) {
        $conn->begin_transaction();
        try {
          if ($is_post_edit) {
            if (updateTastingNote($conn, $post_note_id, $post_wine_id, $post_tasting_date, $post_user_id, $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_blind, $post_status, $img, $img_class, $post_favourite)) {
              $conn->commit();
              $success_message = "Tasting note #" . (int)$post_note_id . " updated successfully.";
              $requested_note_id = $post_note_id;
              $saved_wine_id = $post_wine_id;
              $tasting_note_id = $post_note_id;
            } else {
              throw new Exception("Error updating tasting note record.");
            }
          } else {
            if (insertTastingNote($conn, $post_bottle_id, $post_wine_id, $post_tasting_date, $_SESSION['user_id'], $post_tasting_note, $post_flawed, $pts_20, $pts_100, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $drink_from, $drink_through, $post_status, $post_blind, $img, $img_class, $post_favourite)) {
              $tasting_note_id = $conn->insert_id;
              $conn->commit();
              $success_message = "Note added successfully.";
              $bottle_id = $post_bottle_id;
              $tasting_date = $post_tasting_date;
              $saved_wine_id = $post_wine_id;
            } else {
              throw new Exception("Error adding tasting note.");
            }
          }
        } catch (Exception $e) {
          $conn->rollback();
          $has_post_error = true;
          $errors[] = "Database error saving tasting note: " . $e->getMessage();
        }
      } else {
        $has_post_error = true;
      }
    }

    // Action 2: Confirm Cellar Bottle Consumption
    if (isset($_POST['confirmConsume'])) {
      $consume_bottle_id = filter_input(INPUT_POST, 'consume_bottle_id', FILTER_VALIDATE_INT);
      $consumption_date = sanitizeInput($_POST['consumption_date'] ?? date('Y-m-d'));
      $consume_note_id = filter_input(INPUT_POST, 'tasting_note_id', FILTER_VALIDATE_INT) ?: null;

      if ($consume_bottle_id && !empty($consumption_date)) {
        if (markBottleAsConsumed($conn, $consume_bottle_id, $consumption_date, $consume_note_id)) {
          $success_message = "Bottle #" . (int)$consume_bottle_id . " marked as consumed in inventory.";
        } else {
          $errors[] = "Error marking bottle #" . (int)$consume_bottle_id . " as consumed.";
        }
      } else {
        $errors[] = "Invalid inputs for marking bottle as consumed.";
      }
    }
  }

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
  $add_wine_id = 0;

  if ($bottle_id_param) {
    $selected_bottle = getBottleDetails($conn, $bottle_id_param);
    if ($selected_bottle) {
      $wine_name = getWineName($selected_bottle["nameconvention"], $selected_bottle["vintage"], $selected_bottle["name"], $selected_bottle["producer"], $selected_bottle["grape"], $selected_bottle["vineyard"]);
      $add_wine_id = (int)$selected_bottle['wine_id'];
    }
  }

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
  if ($requested_note_id && $mode === 'edit') {
    $selected_note = getNoteDetails($conn, $requested_note_id);
    if (!$selected_note) {
      $errors[] = "Requested tasting note #" . (int)$requested_note_id . " not found.";
    } elseif (!$can_edit_all && (int)$selected_note['user_id'] !== (int)$_SESSION['user_id']) {
      $errors[] = "You do not have permission to edit this tasting note.";
      $selected_note = null;
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
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
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
          <strong><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></strong>
          <div style="margin-top: 6px; font-size: 0.9rem;">
            <?php if (!empty($tasting_note_id)): ?>
              <a href="/tnotes.php?id=<?php echo (int)$tasting_note_id; ?>" style="color: #1b5e20; text-decoration: underline; margin-right: 14px;">View tasting note &rarr;</a>
            <?php endif; ?>
            <?php if (!empty($saved_wine_id)): ?>
              <a href="/wines.php?id=<?php echo (int)$saved_wine_id; ?>" style="color: #1b5e20; text-decoration: underline; margin-right: 14px;">View updated wine page &rarr;</a>
            <?php endif; ?>
            <?php if (!empty($bottle_id)): ?>
              <a href="/backend/browseBottles.php" style="color: #1b5e20; text-decoration: underline; margin-right: 14px;">Return to browse bottles &rarr;</a>
            <?php endif; ?>
            <?php if ($mode === 'blind'): ?>
              <a href="manageTastingNotes.php?mode=blind" style="color: #1b5e20; text-decoration: underline;">New blind tasting note &rarr;</a>
            <?php elseif ($mode === 'add'): ?>
              <a href="manageTastingNotes.php?mode=add" style="color: #1b5e20; text-decoration: underline;">New tasting note &rarr;</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($bottle_id) && !empty($tasting_date) && strpos($success_message, 'added successfully') !== false): ?>
          <div style="margin: 20px 0; padding: 16px; border: 1px solid var(--secondary-accent, firebrick); background-color: #fff8f8; border-radius: 6px;">
            <p style="margin: 0 0 12px 0; color: #333;">
              Would you like to mark cellar bottle <strong>#<?php echo htmlspecialchars((string)$bottle_id, ENT_QUOTES, 'UTF-8'); ?></strong> as consumed on <strong><?php echo htmlspecialchars($tasting_date, ENT_QUOTES, 'UTF-8'); ?></strong>?
            </p>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="consume_bottle_id" value="<?php echo htmlspecialchars((string)$bottle_id, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="consumption_date" value="<?php echo htmlspecialchars($tasting_date, ENT_QUOTES, 'UTF-8'); ?>">
              <?php if (!empty($tasting_note_id)): ?>
                <input type="hidden" name="tasting_note_id" value="<?php echo (int)$tasting_note_id; ?>">
              <?php endif; ?>
              <button type="submit" name="confirmConsume" class="btn-action" style="padding: 8px 18px; font-weight: bold; margin-right: 10px; min-height: 44px;">
                Yes, mark as consumed
              </button>
            </form>
            <a href="manageTastingNotes.php?mode=<?php echo urlencode($mode); ?>" class="btn-action btn-secondary" style="padding: 8px 18px; text-decoration: none; min-height: 44px; display: inline-flex; align-items: center;">
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
          &#128203; Notes Directory (<?php echo count($accessible_notes); ?>)
        </a>
      </div>

      <!-- Form Container -->
      <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 18px;">
          <h3 style="margin: 0; color: #333; font-size: 1.2rem;">
            <?php 
              if ($mode === 'edit') {
                echo $selected_note ? "&#9998; Editing Tasting Note #" . (int)$selected_note['note_id'] : "&#9998; Select Tasting Note to Edit";
              } elseif ($mode === 'blind') {
                echo "&#127917; Record Blind Tasting Assessment";
              } else {
                echo "&#10010; Record Tasting Note";
              }
            ?>
          </h3>
        </div>

        <?php if ($mode === 'edit'): ?>
          <!-- Select Note to Edit (always shown in edit mode for quick switching) -->
          <div style="max-width: 680px; margin-bottom: 20px;">
            <label for="searchNoteBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
              Select Tasting Note:
            </label>
            <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
              <input type="text" id="searchNoteBox" placeholder="&#128269; Search note ID, date, wine name, producer..." onkeyup="filterDropdown('selectNote', 'searchNoteBox', cachedNoteOptions)" oninput="filterDropdown('selectNote', 'searchNoteBox', cachedNoteOptions)" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
              <select id="selectNote" onchange="if(this.value) window.location.href='manageTastingNotes.php?mode=edit&note_id=' + this.value;" style="width: 100%; border: none; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; border-radius: 0 0 4px 4px; min-height: 44px;">
                <option value="">-- Select a tasting note --</option>
                <?php foreach ($accessible_notes as $n): 
                  $status_label = ($n['status'] === 'published') ? ' (Published)' : '';
                  $rating_label = formatRatingDisplay($n, $scale, true);
                  $full_wine_title = getWineName($n['nameconvention'], $n['vintage'], $n['name'], $n['producer'], $n['grape'], $n['vineyard']);
                  $display_wine_title = ($n['blind'] === 'blind') ? 'Blind tasting' : $full_wine_title;
                  $search_note_str = implode(' ', array_filter([$n['note_id'], $n['tasting_date'], $rating_label, $full_wine_title, $n['producer'] ?? '', $n['region'] ?? '', $n['country'] ?? '']));
                ?>
                  <option value="<?php echo (int)$n['note_id']; ?>"
                          data-search="<?php echo htmlspecialchars($search_note_str, ENT_QUOTES, 'UTF-8'); ?>"
                          <?php echo ($selected_note && (int)$selected_note['note_id'] === (int)$n['note_id']) ? 'selected' : ''; ?>>
                    #<?php echo (int)$n['note_id']; ?> - <?php echo htmlspecialchars($n['tasting_date'] . (!empty($rating_label) ? ' - ' . $rating_label : '') . ' - ' . $display_wine_title . $status_label, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($mode === 'edit' && !$selected_note): ?>
          <p style="color: #666; font-size: 0.92rem; margin: 0;">Select a tasting note from the dropdown above or from the directory below to edit its details.</p>
        <?php elseif ($mode === 'edit' && $selected_note && !$can_publish && !$can_edit_all && $selected_note['status'] === 'published'): ?>
          <div style="padding: 15px; margin-top: 10px; background-color: #fff8e6; border-left: 4px solid #f39c12; border-radius: 4px;">
            <p style="margin: 0;"><strong>Notice:</strong> This tasting note has been published and can no longer be edited. Please contact an administrator if you need to make changes.</p>
          </div>
        <?php else: ?>
          <?php
            // Resolve active field values (preserving $_POST values on validation errors)
            $val_wine_id = $has_post_error && isset($_POST['wine_id'])
              ? (int)$_POST['wine_id']
              : (($mode === 'edit' && $selected_note) ? (int)$selected_note['wine_id'] : (int)$add_wine_id);

            $val_tasting_date = $has_post_error && isset($_POST['tasting_date'])
              ? (string)$_POST['tasting_date']
              : (($mode === 'edit' && $selected_note) ? (string)$selected_note['tasting_date'] : date('Y-m-d'));

            $val_tasting_note = $has_post_error && isset($_POST['tasting_note'])
              ? (string)$_POST['tasting_note']
              : (($mode === 'edit' && $selected_note) ? (string)($selected_note['tasting_note'] ?? '') : '');

            $val_flawed = $has_post_error && (isset($_POST['flawed']) || isset($_POST['flawed_yn']))
              ? (string)($_POST['flawed'] ?? $_POST['flawed_yn'])
              : (($mode === 'edit' && $selected_note) ? (string)($selected_note['flawed_yn'] ?? 'no') : 'no');

            $val_pts_100 = $has_post_error && isset($_POST['pts_100'])
              ? (string)$_POST['pts_100']
              : (($mode === 'edit' && $selected_note && $selected_note['pts_100'] !== null) ? (string)$selected_note['pts_100'] : '');

            $raw_existing_20 = ($mode === 'edit' && $selected_note) ? ($selected_note['pts_20'] ?? $selected_note['dmpts'] ?? null) : null;
            $val_pts_20 = $has_post_error && isset($_POST['pts_20'])
              ? (string)$_POST['pts_20']
              : (($raw_existing_20 !== null && $raw_existing_20 !== '') ? (string)$raw_existing_20 : '');

            $val_favourite = $has_post_error && isset($_POST['favourite'])
              ? (string)$_POST['favourite']
              : (($mode === 'edit' && $selected_note) ? (string)($selected_note['favourite'] ?? 'no') : 'no');

            $val_blind = $has_post_error && isset($_POST['blind'])
              ? (string)$_POST['blind']
              : (($mode === 'edit' && $selected_note) ? (string)($selected_note['blind'] ?? 'not blind') : (($mode === 'blind') ? 'blind' : 'not blind'));
            if ($val_blind !== 'blind' && $val_blind !== 'not blind') {
              $val_blind = 'blind';
            }

            $val_drink_from = $has_post_error && isset($_POST['drink_from'])
              ? (string)$_POST['drink_from']
              : (($mode === 'edit' && $selected_note && $selected_note['drinkwindow_min'] !== null) ? (string)$selected_note['drinkwindow_min'] : '');

            $val_drink_through = $has_post_error && isset($_POST['drink_through'])
              ? (string)$_POST['drink_through']
              : (($mode === 'edit' && $selected_note && $selected_note['drinkwindow_max'] !== null) ? (string)$selected_note['drinkwindow_max'] : '');

            $val_img = $has_post_error && isset($_POST['img'])
              ? (string)$_POST['img']
              : (($mode === 'edit' && $selected_note && $selected_note['img'] !== null) ? (string)$selected_note['img'] : '');

            $val_img_class = $has_post_error && isset($_POST['img_class'])
              ? (string)$_POST['img_class']
              : (($mode === 'edit' && $selected_note && !empty($selected_note['img_class'])) ? (string)$selected_note['img_class'] : 'null');

            $val_status = $has_post_error && isset($_POST['status'])
              ? (string)$_POST['status']
              : (($mode === 'edit' && $selected_note) ? (string)($selected_note['status'] ?? 'draft') : 'draft');
          ?>

          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($mode === 'edit' && $selected_note): ?>
              <input type="hidden" name="updateTastingNote" value="1">
              <input type="hidden" name="note_id" value="<?php echo (int)$selected_note['note_id']; ?>">
              <input type="hidden" name="user_id" value="<?php echo (int)$selected_note['user_id']; ?>">
            <?php else: ?>
              <input type="hidden" name="postTastingNote" value="1">
              <?php if ($selected_bottle && $mode !== 'blind'): ?>
                <input type="hidden" name="bottle_id" value="<?php echo (int)$selected_bottle['bottle_id']; ?>">
              <?php endif; ?>
            <?php endif; ?>

            <!-- Row 1: Target Identification & Tasting Date -->
            <?php if ($mode === 'blind'): ?>
              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="searchBottleBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Select Cellar Bottle: <span style="color: red;">*</span>
                  </label>
                  <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <input type="text" id="searchBottleBox" placeholder="&#128269; Search bottle ID or info..." onkeyup="filterDropdown('bottle_id', 'searchBottleBox', cachedBottleOptions)" oninput="filterDropdown('bottle_id', 'searchBottleBox', cachedBottleOptions)" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                    <select name="bottle_id" id="bottle_id" required onchange="if(this.value) window.location.href='manageTastingNotes.php?mode=blind&bottle_id=' + this.value;" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                      <option value="">-- Select a bottle from cellar --</option>
                      <?php foreach ($bottles as $b): 
                        $b_search_parts = [
                          $b['bottle_id'],
                          $b['producer'] ?? '',
                          $b['name'] ?? '',
                          $b['vintage'] ?? '',
                          $b['grape'] ?? '',
                          $b['vineyard'] ?? ''
                        ];
                        $b_search_text = implode(' ', array_filter($b_search_parts));
                      ?>
                        <option value="<?php echo (int)$b['bottle_id']; ?>"
                                data-search="<?php echo htmlspecialchars($b_search_text, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo ($selected_bottle && (int)$selected_bottle['bottle_id'] === (int)$b['bottle_id']) ? 'selected' : ''; ?>>
                          Bottle #<?php echo (int)$b['bottle_id']; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Cellar identity concealed during blind assessment</div>
                </div>

                <div>
                  <label for="tasting_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Tasting Date: <span style="color: red;">*</span>
                  </label>
                  <input type="date" name="tasting_date" id="tasting_date" required value="<?php echo htmlspecialchars($val_tasting_date, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>
              </div>

              <?php if ($selected_bottle): ?>
                <input type="hidden" name="wine_id" value="<?php echo (int)$selected_bottle['wine_id']; ?>">
                <div style="margin-bottom: 16px; padding: 10px 14px; background: white; border: 1px solid #ccc; border-radius: 4px;">
                  <details>
                    <summary style="cursor: pointer; font-weight: bold; font-size: 0.9rem; color: var(--secondary-accent, firebrick);">Reveal the wine?</summary>
                    <div style="margin-top: 8px; font-size: 0.95rem; color: #333;">
                      <?php echo htmlspecialchars($wine_name, ENT_QUOTES, 'UTF-8'); ?> (Bottle #<?php echo (int)$selected_bottle['bottle_id']; ?>)
                    </div>
                  </details>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="searchWineBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Wine Cuv&eacute;e: <span style="color: red;">*</span>
                  </label>
                  <?php
                    $conceal_edit_wine = ($mode === 'edit' && $selected_note && ($selected_note['blind'] ?? '') === 'blind');
                  ?>
                  <?php if ($conceal_edit_wine): ?>
                    <details style="border: 1px solid #ccc; border-radius: 4px; background: white; padding: 8px 10px;">
                      <summary style="cursor: pointer; font-weight: 600; font-size: 0.9rem; color: var(--secondary-accent, firebrick);">Show / change the wine (blind tasting)</summary>
                      <div style="margin-top: 8px; border: 1px solid #ddd; border-radius: 4px;">
                  <?php else: ?>
                    <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                  <?php endif; ?>
                      <input type="text" id="searchWineBox" placeholder="&#128269; Filter wine cuv&eacute;es..." onkeyup="filterDropdown('wine_id', 'searchWineBox', cachedWineOptions)" oninput="filterDropdown('wine_id', 'searchWineBox', cachedWineOptions)" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                      <select name="wine_id" id="wine_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                        <option value="">-- Select Wine --</option>
                        <?php foreach ($wines as $w): 
                          $w_search_terms = [
                            $w['country'] ?? '',
                            $w['region'] ?? '',
                            $w['producer'] ?? '',
                            !empty($w['vintage']) ? $w['vintage'] : 'NV',
                            $w['name'] ?? '',
                            $w['grape'] ?? '',
                            $w['vineyard'] ?? ''
                          ];
                          $w_search_str = implode(' ', array_filter(array_map('trim', $w_search_terms)));
                        ?>
                          <option value="<?php echo (int)$w['wine_id']; ?>"
                                  data-search="<?php echo htmlspecialchars($w_search_str, ENT_QUOTES, 'UTF-8'); ?>"
                                  <?php echo ($val_wine_id === (int)$w['wine_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($w['country'] . ': ' . $w['region'] . ' - ' . getWineName($w['nameconvention'], $w['vintage'], $w['name'], $w['producer'], $w['grape'], $w['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                  <?php if ($conceal_edit_wine): ?>
                      </div>
                    </details>
                  <?php else: ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($selected_bottle && $mode === 'add'): ?>
                    <div style="font-size: 0.82rem; color: #555; margin-top: 4px;">
                      Recording note for <strong>Bottle #<?php echo (int)$selected_bottle['bottle_id']; ?></strong>
                    </div>
                  <?php endif; ?>
                </div>

                <div>
                  <label for="tasting_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Tasting Date: <span style="color: red;">*</span>
                  </label>
                  <input type="date" name="tasting_date" id="tasting_date" required value="<?php echo htmlspecialchars($val_tasting_date, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>
              </div>
            <?php endif; ?>

            <!-- Row 2: Scores & Assessment -->
            <?php if ($wset_enabled): ?>
              <?php
                if ($has_post_error) {
                  $cur_b = (isset($_POST['wset_balance']) && $_POST['wset_balance'] !== '') ? (string)(float)$_POST['wset_balance'] : '';
                  $cur_l = (isset($_POST['wset_length']) && $_POST['wset_length'] !== '') ? (string)(float)$_POST['wset_length'] : '';
                  $cur_i = (isset($_POST['wset_intensity']) && $_POST['wset_intensity'] !== '') ? (string)(float)$_POST['wset_intensity'] : '';
                  $cur_c = (isset($_POST['wset_complexity']) && $_POST['wset_complexity'] !== '') ? (string)(float)$_POST['wset_complexity'] : '';
                } elseif ($mode === 'edit' && $selected_note) {
                  $cur_b = (isset($selected_note['wset_balance']) && $selected_note['wset_balance'] !== null) ? (string)(float)$selected_note['wset_balance'] : '';
                  $cur_l = (isset($selected_note['wset_length']) && $selected_note['wset_length'] !== null) ? (string)(float)$selected_note['wset_length'] : '';
                  $cur_i = (isset($selected_note['wset_intensity']) && $selected_note['wset_intensity'] !== null) ? (string)(float)$selected_note['wset_intensity'] : '';
                  $cur_c = (isset($selected_note['wset_complexity']) && $selected_note['wset_complexity'] !== null) ? (string)(float)$selected_note['wset_complexity'] : '';
                } else {
                  $cur_b = '';
                  $cur_l = '';
                  $cur_i = '';
                  $cur_c = '';
                }
              ?>
              <div style="background: white; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                  <h4 style="margin: 0; color: var(--secondary-accent, firebrick); font-size: 0.95rem;">
                    WSET SAT Evaluation (BLIC Framework)
                    <?php if ($wset_mode === 'backend_only'): ?>
                      <small style="color: #777; font-weight: normal;">(Internal - hidden on public site)</small>
                    <?php elseif ($wset_mode === 'logged_in'): ?>
                      <small style="color: #777; font-weight: normal;">(Visible to logged-in users only)</small>
                    <?php endif; ?>
                  </h4>
                  <small style="color: #64748b;">Balance &bull; Length &bull; Intensity &bull; Complexity</small>
                </div>
                <div class="form-row-2col">
                  <div>
                    <label for="wset_balance" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Balance (0.0 - 1.0):</label>
                    <select name="wset_balance" id="wset_balance" onchange="updateWsetRecommendation()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.9rem;">
                      <option value="" <?php echo ($cur_b === '') ? 'selected' : ''; ?>>-- Not evaluated --</option>
                      <option value="0" <?php echo ($cur_b === '0') ? 'selected' : ''; ?>>0.0 (Unbalanced)</option>
                      <option value="0.5" <?php echo ($cur_b === '0.5') ? 'selected' : ''; ?>>0.5 (Moderate)</option>
                      <option value="1" <?php echo ($cur_b === '1') ? 'selected' : ''; ?>>1.0 (Well Balanced)</option>
                    </select>
                  </div>
                  <div>
                    <label for="wset_length" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Length / Finish (0.0 - 1.0):</label>
                    <select name="wset_length" id="wset_length" onchange="updateWsetRecommendation()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.9rem;">
                      <option value="" <?php echo ($cur_l === '') ? 'selected' : ''; ?>>-- Not evaluated --</option>
                      <option value="0" <?php echo ($cur_l === '0') ? 'selected' : ''; ?>>0.0 (Short)</option>
                      <option value="0.5" <?php echo ($cur_l === '0.5') ? 'selected' : ''; ?>>0.5 (Medium)</option>
                      <option value="1" <?php echo ($cur_l === '1') ? 'selected' : ''; ?>>1.0 (Long)</option>
                    </select>
                  </div>
                  <div>
                    <label for="wset_intensity" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Intensity (0.0 - 1.0):</label>
                    <select name="wset_intensity" id="wset_intensity" onchange="updateWsetRecommendation()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.9rem;">
                      <option value="" <?php echo ($cur_i === '') ? 'selected' : ''; ?>>-- Not evaluated --</option>
                      <option value="0" <?php echo ($cur_i === '0') ? 'selected' : ''; ?>>0.0 (Light / Lacking)</option>
                      <option value="0.5" <?php echo ($cur_i === '0.5') ? 'selected' : ''; ?>>0.5 (Medium)</option>
                      <option value="1" <?php echo ($cur_i === '1') ? 'selected' : ''; ?>>1.0 (Pronounced)</option>
                    </select>
                  </div>
                  <div>
                    <label for="wset_complexity" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Complexity (0.0 - 1.0):</label>
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
                <label for="pts_20" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  <?php echo htmlspecialchars(!empty($_SESSION['initials']) ? $_SESSION['initials'] : 'DM', ENT_QUOTES, 'UTF-8'); ?> Points (20-Point Scale, 0 - 20):
                </label>
                <input type="number" min="0" max="20" step="1" name="pts_20" id="pts_20" value="<?php echo htmlspecialchars($val_pts_20, ENT_QUOTES, 'UTF-8'); ?>" placeholder="0 - 20" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="pts_100" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">100-Point Score (50 - 100):</label>
                <input type="number" min="50" max="100" step="1" name="pts_100" id="pts_100" value="<?php echo htmlspecialchars($val_pts_100, ENT_QUOTES, 'UTF-8'); ?>" placeholder="50 - 100" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="flawed" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Flawed / Corked:</label>
                <select name="flawed" id="flawed" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="no" <?php echo ($val_flawed === 'no') ? 'selected' : ''; ?>>No (Clean)</option>
                  <option value="yes" <?php echo ($val_flawed === 'yes') ? 'selected' : ''; ?>>Yes (Flawed Bottle)</option>
                </select>
              </div>
            </div>

            <div style="margin-bottom: 16px;">
              <label for="tasting_note" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Tasting Note &amp; Qualitative Impressions: <span style="color: red;">*</span>
              </label>
              <textarea name="tasting_note" id="tasting_note" rows="10" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 0.92rem;" placeholder="<p>Appearance, aroma profile, palate structure, tannins, acidity, finish, and general impressions...</p>"><?php echo htmlspecialchars($val_tasting_note, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <!-- Drinking Window, Favourite & Blind Tasting -->
            <div class="form-row-3col" style="margin-bottom: 16px;">
              <div>
                <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Drinking Window (Years):</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                  <input type="text" id="drink_from" name="drink_from" maxlength="4" value="<?php echo htmlspecialchars($val_drink_from, ENT_QUOTES, 'UTF-8'); ?>" placeholder="From (yyyy)" style="flex: 1; min-width: 0; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center; box-sizing: border-box;">
                  <span>-</span>
                  <input type="text" id="drink_through" name="drink_through" maxlength="4" value="<?php echo htmlspecialchars($val_drink_through, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Through (yyyy)" style="flex: 1; min-width: 0; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center; box-sizing: border-box;">
                </div>
              </div>

              <div>
                <label for="favourite" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Favourite?</label>
                <select name="favourite" id="favourite" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="no" <?php echo ($val_favourite === 'no') ? 'selected' : ''; ?>>No</option>
                  <option value="yes" <?php echo ($val_favourite === 'yes') ? 'selected' : ''; ?>>Yes (Favourite Wine)</option>
                </select>
              </div>

              <div>
                <label for="blind" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Tasted Blind?</label>
                <select name="blind" id="blind" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="not blind" <?php echo ($val_blind === 'not blind') ? 'selected' : ''; ?>>Not blind</option>
                  <option value="blind" <?php echo ($val_blind === 'blind') ? 'selected' : ''; ?>>Blind</option>
                </select>
              </div>
            </div>

            <!-- Image & Publication -->
            <div class="form-row-3col" style="margin-bottom: 20px;">
              <div>
                <label for="img" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Image Filename:</label>
                <input type="text" name="img" id="img" placeholder="filename.jpg" value="<?php echo htmlspecialchars($val_img, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Image file in <code>/uploads/img/</code></div>
              </div>

              <div>
                <label for="img_class" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Image Formatting / Alignment:</label>
                <select name="img_class" id="img_class" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="null" <?php echo ($val_img_class === 'null' || $val_img_class === '') ? 'selected' : ''; ?>>-- None --</option>
                  <option value="inline left" <?php echo ($val_img_class === 'inline left') ? 'selected' : ''; ?>>inline left</option>
                  <option value="block center" <?php echo ($val_img_class === 'block center') ? 'selected' : ''; ?>>block center</option>
                </select>
                <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Required when an image filename is specified</div>
              </div>

              <div>
                <label for="status" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">Publication Status:</label>
                <select name="status" id="status" required <?php echo !$can_publish ? 'disabled' : ''; ?> style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="draft" <?php echo ($val_status === 'draft') ? 'selected' : ''; ?>>draft</option>
                  <option value="published" <?php echo ($val_status === 'published') ? 'selected' : ''; ?>>published</option>
                </select>
                <?php if (!$can_publish): ?>
                  <div style="font-size: 0.78rem; color: #777; margin-top: 4px;"><i>Note: Once an admin publishes your tasting note, it will be locked and can no longer be edited.</i></div>
                <?php endif; ?>
              </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                <?php echo ($mode === 'edit') ? 'Update Tasting Note' : 'Post Tasting Note'; ?>
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
      <?php
        $totalNotesCount = count($accessible_notes);
        $displayNotes = ($ledger_limit === 'all') ? $accessible_notes : array_slice($accessible_notes, 0, $ledger_limit);
      ?>
      <div id="notesJournalSection" style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
            Tasting Journal Directory (<span id="notesCountDisplay"><?php echo count($displayNotes); ?></span> notes)
          </h3>
          <input type="text" id="noteSearchBox" class="admin-search-box" style="margin-bottom: 0; max-width: 320px;" placeholder="Search notes..." onkeyup="filterNotesTable()" oninput="filterNotesTable()">
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
              <?php if (empty($displayNotes)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: #777; padding: 20px;">No tasting notes found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($displayNotes as $note): 
                  $n_title = getWineName($note['nameconvention'], $note['vintage'], $note['name'], $note['producer'], $note['grape'], $note['vineyard']);
                  $is_fav = (($note['favourite'] ?? 'no') === 'yes');
                  $is_flawed = (($note['flawed_yn'] ?? 'no') === 'yes');
                  $has_100 = (isset($note['pts_100']) && $note['pts_100'] !== null && $note['pts_100'] !== '');
                  $score_20_val = $note['pts_20'] ?? $note['dmpts'] ?? null;
                  $has_20 = ($score_20_val !== null && $score_20_val !== '');
                  $has_wset = (isset($note['wsetpts']) && $note['wsetpts'] !== null && $note['wsetpts'] !== '');
                ?>
                  <tr style="<?php echo ($requested_note_id === (int)$note['note_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                    <td data-label="Date"><?php echo htmlspecialchars($note['tasting_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="card-primary-cell">
                      <div>
                        <strong><?php echo htmlspecialchars($n_title, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($is_fav): ?>
                          <span title="Favourite wine" style="color: #d97706; margin-left: 4px;">&#9733;</span>
                        <?php endif; ?>
                        <?php if (($note['blind'] ?? '') === 'blind'): ?>
                          <span style="font-size: 0.75rem; color: #555; font-style: italic; margin-left: 4px;">(Blind)</span>
                        <?php endif; ?>
                      </div>
                      <div style="font-size: 0.78rem; color: #777;">
                        <?php echo htmlspecialchars($note['producer'] ?? '', ENT_QUOTES, 'UTF-8'); ?> &bull; 
                        <?php echo htmlspecialchars($note['region'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    </td>
                    <td data-label="Score" style="text-align: center;">
                      <?php if ($is_flawed): ?>
                        <span style="color: #b91c1c; font-size: 0.82rem; font-weight: bold;">Flawed</span>
                      <?php elseif ($scale === '100-point' && $has_100): ?>
                        <span style="font-weight: bold;"><?php echo (int)$note['pts_100']; ?></span><span style="font-size: 0.78rem; color: #888;">/100</span>
                      <?php elseif ($has_20): ?>
                        <span style="font-weight: bold;"><?php echo (int)$score_20_val; ?></span><span style="font-size: 0.78rem; color: #888;">/20</span>
                      <?php elseif ($has_100): ?>
                        <span style="font-weight: bold;"><?php echo (int)$note['pts_100']; ?></span><span style="font-size: 0.78rem; color: #888;">/100</span>
                      <?php elseif ($has_wset): ?>
                        <span style="font-weight: bold; color: #2e7d32;"><?php echo htmlspecialchars((string)$note['wsetpts'], ENT_QUOTES, 'UTF-8'); ?></span><span style="font-size: 0.78rem; color: #888;">/4</span>
                      <?php else: ?>
                        <span style="color: #aaa;">-</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Status">
                      <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; <?php echo ($note['status'] === 'published') ? 'background: #e8f5e9; color: #2e7d32;' : 'background: #f5f5f5; color: #666;'; ?>">
                        <?php echo htmlspecialchars($note['status'] ?? 'draft', ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td class="card-actions-cell" style="text-align: right;">
                      <?php if ($can_edit): ?>
                        <a href="manageTastingNotes.php?mode=edit&note_id=<?php echo (int)$note['note_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                          Edit
                        </a>
                      <?php endif; ?>
                      <a href="/tnotes.php?id=<?php echo (int)$note['note_id']; ?>" target="_blank" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                        Note &rarr;
                      </a>
                      <a href="/wines.php?id=<?php echo (int)$note['wine_id']; ?>" target="_blank" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                        Wine &rarr;
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.85rem; color: #666; margin-top: 10px;">
          <div>
            Showing:
            <?php 
              $limitOptions = [100, 250, 500, 1000, 'all'];
              foreach ($limitOptions as $idx => $opt):
                $isCurrent = ($ledger_limit === $opt);
                $optParams = $_GET;
                if ($opt === 'all') {
                  unset($optParams['limit']);
                } else {
                  $optParams['limit'] = $opt;
                }
                $optUrl = 'manageTastingNotes.php?' . http_build_query($optParams) . '#notesJournalSection';
                $label = ($opt === 'all') ? 'All (default)' : number_format($opt);
            ?>
              <?php if ($idx > 0): ?> &middot; <?php endif; ?>
              <?php if ($isCurrent): ?>
                <strong style="color: var(--secondary-accent, firebrick); font-weight: 700;"><?php echo $label; ?></strong>
              <?php else: ?>
                <a href="<?php echo htmlspecialchars($optUrl, ENT_QUOTES, 'UTF-8'); ?>" style="color: #0d6efd; text-decoration: none;"><?php echo $label; ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
            notes
          </div>
          <div>
            <a href="/tnotes.php" style="color: #0d6efd; text-decoration: none;">Browse all published notes &rarr;</a>
          </div>
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
    // Cache dropdown options including hidden data-search metadata
    const noteSel = document.getElementById('selectNote');
    if (noteSel) {
      for (let i = 1; i < noteSel.options.length; i++) {
        const opt = noteSel.options[i];
        const searchMeta = opt.getAttribute('data-search') || '';
        cachedNoteOptions.push({
          value: opt.value,
          text: opt.textContent,
          search: (searchMeta + ' ' + opt.textContent).toLowerCase()
        });
      }
    }

    const btlSel = document.getElementById('bottle_id');
    if (btlSel) {
      for (let i = 1; i < btlSel.options.length; i++) {
        const opt = btlSel.options[i];
        const searchMeta = opt.getAttribute('data-search') || '';
        cachedBottleOptions.push({
          value: opt.value,
          text: opt.textContent,
          search: (searchMeta + ' ' + opt.textContent).toLowerCase()
        });
      }
    }

    const wineSel = document.getElementById('wine_id');
    if (wineSel) {
      for (let i = 1; i < wineSel.options.length; i++) {
        const opt = wineSel.options[i];
        const searchMeta = opt.getAttribute('data-search') || '';
        cachedWineOptions.push({
          value: opt.value,
          text: opt.textContent,
          search: (searchMeta + ' ' + opt.textContent).toLowerCase()
        });
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
      const targetText = optData.search || optData.text.toLowerCase();
      const matches = terms.every(term => targetText.includes(term));
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
      pts100 = '98 - 100';
      pts20 = '15 - 20';
      default100 = 99;
      default20 = 18;
      badgeBg = '#d1fae5';
      badgeColor = '#065f46';
    } else if (rounded >= 3.5) {
      quality = 'Extraordinary';
      pts100 = '95 - 97';
      pts20 = '13 or 14';
      default100 = 96;
      default20 = 14;
      badgeBg = '#d1fae5';
      badgeColor = '#065f46';
    } else if (rounded >= 3.0) {
      quality = 'Outstanding';
      pts100 = '93 - 94';
      pts20 = '11 or 12';
      default100 = 94;
      default20 = 12;
      badgeBg = '#e0f2fe';
      badgeColor = '#0369a1';
    } else if (rounded >= 2.5) {
      quality = 'Very Good';
      pts100 = '90 - 92';
      pts20 = '9 or 10';
      default100 = 91;
      default20 = 10;
      badgeBg = '#e0f2fe';
      badgeColor = '#0369a1';
    } else if (rounded >= 2.0) {
      quality = 'Good';
      pts100 = '85 - 89';
      pts20 = '7 or 8';
      default100 = 87;
      default20 = 8;
      badgeBg = '#fef3c7';
      badgeColor = '#92400e';
    } else if (rounded >= 1.5) {
      quality = 'Good';
      pts100 = '80 - 84';
      pts20 = '5 or 6';
      default100 = 82;
      default20 = 6;
      badgeBg = '#fef3c7';
      badgeColor = '#92400e';
    } else if (rounded >= 1.0) {
      quality = 'Acceptable / Mediocre';
      pts100 = '75 - 79';
      pts20 = '3 or 4';
      default100 = 77;
      default20 = 4;
      badgeBg = '#f1f5f9';
      badgeColor = '#475569';
    } else if (rounded >= 0.5) {
      quality = 'Below Average';
      pts100 = '70 - 74';
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
      sugEl.innerHTML = '<strong>' + pts100 + ' pts</strong> <small style="color:#64748b;">(&quot;' + quality + '&quot; &bull; ' + pts20 + ' on 20-pt)</small>';
      if (btnEl) {
        btnEl.textContent = 'Apply ' + default100;
        btnEl.style.display = 'inline-block';
      }
    } else {
      activeSuggestedScore = default20;
      sugEl.innerHTML = '<strong>' + pts20 + ' pts</strong> <small style="color:#64748b;">(&quot;' + quality + '&quot; &bull; ' + pts100 + ' on 100-pt)</small>';
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
    if (!table) return;
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.cells.length < 5) continue;
      const textContent = row.textContent.toLowerCase();
      const isVisible = (!searchVal || textContent.includes(searchVal));
      if (isVisible) {
        row.style.display = '';
        row.classList.remove('admin-row-hidden');
        visibleCount++;
      } else {
        row.style.display = 'none';
        row.classList.add('admin-row-hidden');
      }
    }

    const countEl = document.getElementById('notesCountDisplay');
    if (countEl) {
      countEl.textContent = visibleCount;
    }
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
