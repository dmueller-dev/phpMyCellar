<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Bottle Management Dashboard.
   *
   * Combines batch addition of cellar bottles with single bottle modification.
   * Required privileges: 'add_bottle' or 'edit_bottle'
   */
  $can_add = hasPrivilege($conn, 'add_bottle');
  $can_edit = hasPrivilege($conn, 'edit_bottle');

  if (!$can_add && !$can_edit) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Determine current mode ('add' or 'edit')
  $requested_bottle_id = filter_input(INPUT_GET, 'bottle_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_bottle', FILTER_VALIDATE_INT);
  $mode = filter_input(INPUT_GET, 'mode', FILTER_DEFAULT) ?? '';

  if ($requested_bottle_id && $can_edit) {
    $mode = 'edit';
  } elseif ($mode === 'edit' && $can_edit) {
    $mode = 'edit';
  } elseif ($mode === 'add' && $can_add) {
    $mode = 'add';
  } else {
    $mode = $can_add ? 'add' : 'edit';
  }

  // Pre-load lookup data
  $wines = getWines($conn);
  $formats = getFormats($conn);
  $stores = getStores($conn);
  $storageLocations = getStorageLocations($conn);
  $bottles = getBottles($conn);

  // Form field variables for ADD mode
  $add_wine_id = filter_input(INPUT_GET, 'wine_id', FILTER_VALIDATE_INT) ?: '';
  $add_numBottles = 1;
  $add_format = '0.75';
  $add_bin_id = '';
  $add_store_id = '';
  $add_purchase_date = date('Y-m-d');
  $add_purchase_price = '';
  $add_arrival_date = '';
  $add_status = 'in cellar';
  $add_drink_from = '';
  $add_drink_through = '';
  $add_consumption_date = '';
  $add_consumption_note = '';
  $add_for_sale = 'no';
  $add_restricted = 0;
  $add_note_id = '';

  // Form field variables for EDIT mode
  $selected_bottle = null;
  if ($requested_bottle_id) {
    $selected_bottle = getBottleDetails($conn, $requested_bottle_id);
    if (!$selected_bottle) {
      $errors[] = "Requested bottle #" . $requested_bottle_id . " not found.";
    }
  }

  // Handle Form Submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    if ($action === 'add') {
      if (!$can_add) {
        die("Permission denied: You do not have privilege to add bottles.");
      }

      $numBottles = filter_input(INPUT_POST, 'numBottles', FILTER_VALIDATE_INT) ?: 1;
      $wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $format = sanitizeInput($_POST['format'] ?? '');
      $bin_id = filter_input(INPUT_POST, 'bin_id', FILTER_VALIDATE_INT) ?: null;
      $store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
      $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
      $purchase_price_raw = filter_input(INPUT_POST, 'purchase_price', FILTER_VALIDATE_FLOAT);
      $purchase_price = ($purchase_price_raw !== false && $purchase_price_raw !== null) ? number_format($purchase_price_raw, 2, '.', '') : null;
      $arrival_date = sanitizeInput($_POST['arrival_date'] ?? '') ?: null;
      $status = sanitizeInput($_POST['status'] ?? 'in cellar');
      $drink_from = filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT) ?: null;
      $drink_through = filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT) ?: null;
      $consumption_date = sanitizeInput($_POST['consumption_date'] ?? '') ?: null;
      $consumption_note = sanitizeInput($_POST['consumption_note'] ?? '') ?: null;
      $for_sale = sanitizeInput($_POST['for_sale'] ?? 'no');
      $note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT) ?: null;
      $restricted = filter_input(INPUT_POST, 'restricted', FILTER_VALIDATE_INT) ?? 0;

      $valErrors = validateBottleInput(0, $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id, $restricted);
      if (!empty($valErrors)) {
        $errors = array_merge($errors, $valErrors);
      }

      if ($bin_id && $status === 'in cellar') {
        $capCheck = checkStorageBinCapacity($conn, $bin_id, $numBottles);
        if (!$capCheck['allowed']) {
          $errors[] = $capCheck['error'];
        }
      }

      if (empty($errors)) {
        $conn->begin_transaction();
        try {
          $inserted_count = 0;
          for ($n = 1; $n <= $numBottles; $n++) {
            if (insertBottle($conn, $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id, $restricted)) {
              $inserted_count++;
            } else {
              throw new Exception("Error inserting bottle #{$n}");
            }
          }
          $conn->commit();
          $success_message = $inserted_count . " bottle(s) inserted successfully into inventory. " .
            "<a href='manageTastingNotes.php?wine_id=" . $wine_id . "' style='color: inherit; text-decoration: underline;'>Add tasting note &rarr;</a> | " .
            "<a href='browseBottles.php' style='color: inherit; text-decoration: underline;'>Browse bottles &rarr;</a>";
          
          // Refresh bottles list
          $bottles = getBottles($conn);
          $storageLocations = getStorageLocations($conn);
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting bottles: " . $e->getMessage();
        }
      }
    } elseif ($action === 'edit') {
      if (!$can_edit) {
        die("Permission denied: You do not have privilege to edit bottles.");
      }

      $bottle_id = filter_input(INPUT_POST, 'bottle_id', FILTER_VALIDATE_INT);
      $wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
      $format = sanitizeInput($_POST['format'] ?? '');
      $bin_id = filter_input(INPUT_POST, 'bin_id', FILTER_VALIDATE_INT) ?: null;
      $store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
      $purchase_date = sanitizeInput($_POST['purchase_date'] ?? '');
      $purchase_price_raw = filter_input(INPUT_POST, 'purchase_price', FILTER_VALIDATE_FLOAT);
      $purchase_price = ($purchase_price_raw !== false && $purchase_price_raw !== null) ? number_format($purchase_price_raw, 2, '.', '') : null;
      $arrival_date = sanitizeInput($_POST['arrival_date'] ?? '') ?: null;
      $status = sanitizeInput($_POST['status'] ?? 'in cellar');
      $drink_from = filter_input(INPUT_POST, 'drink_from', FILTER_VALIDATE_INT) ?: null;
      $drink_through = filter_input(INPUT_POST, 'drink_through', FILTER_VALIDATE_INT) ?: null;
      $consumption_date = sanitizeInput($_POST['consumption_date'] ?? '') ?: null;
      $consumption_note = sanitizeInput($_POST['consumption_note'] ?? '') ?: null;
      $for_sale = sanitizeInput($_POST['for_sale'] ?? 'no');
      $note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT) ?: null;
      $restricted = filter_input(INPUT_POST, 'restricted', FILTER_VALIDATE_INT) ?? 0;

      $valErrors = validateBottleInput($bottle_id, $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id, $restricted);
      if (!empty($valErrors)) {
        $errors = array_merge($errors, $valErrors);
      }

      if ($bin_id && $status === 'in cellar') {
        $capCheck = checkStorageBinCapacity($conn, $bin_id, 1, $bottle_id);
        if (!$capCheck['allowed']) {
          $errors[] = $capCheck['error'];
        }
      }

      if (empty($errors)) {
        $conn->begin_transaction();
        try {
          if (updateBottle($conn, $bottle_id, $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id, $restricted)) {
            $conn->commit();
            $success_message = "Bottle #" . $bottle_id . " updated successfully.";
            $selected_bottle = getBottleDetails($conn, $bottle_id);
            // Refresh bottles list
            $bottles = getBottles($conn);
            $storageLocations = getStorageLocations($conn);
          } else {
            $conn->rollback();
            $errors[] = "Failed to update bottle #" . $bottle_id . ". Please verify that bottle ID exists.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating bottle: " . $e->getMessage();
        }
      }
    }
  }

  // Summary Metrics
  $stats_stmt = $conn->query("
    SELECT 
      SUM(CASE WHEN status = 'in cellar' THEN 1 ELSE 0 END) AS in_cellar,
      SUM(CASE WHEN status = 'pending delivery' THEN 1 ELSE 0 END) AS pending_delivery,
      SUM(CASE WHEN status = 'consumed' THEN 1 ELSE 0 END) AS consumed,
      COUNT(*) AS total_bottles
    FROM bottles
  ");
  $metrics = $stats_stmt ? $stats_stmt->fetch_assoc() : ['in_cellar' => 0, 'pending_delivery' => 0, 'consumed' => 0, 'total_bottles' => 0];

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Bottles & Inventory - Cellar Administration';
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
          &#127870; Manage Bottles &amp; Cellar Inventory
        </h2>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="browseBottles.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Browse Cellar &rarr;
          </a>
          <a href="manageOrders.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Purchase Orders &rarr;
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
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 12px 16px; margin-bottom: 20px; color: #2e7d32;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <!-- High-level Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format((int)$metrics['in_cellar']); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">In Cellar</div>
          <div class="kpi-sub">Available physical inventory</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format((int)$metrics['pending_delivery']); ?></span>
            <span class="kpi-icon">&#128230;</span>
          </div>
          <div class="kpi-label">Pending Delivery</div>
          <div class="kpi-sub">On order from merchants</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format((int)$metrics['consumed']); ?></span>
            <span class="kpi-icon">&#129346;</span>
          </div>
          <div class="kpi-label">Consumed</div>
          <div class="kpi-sub">Tasted &amp; archived bottles</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format((int)$metrics['total_bottles']); ?></span>
            <span class="kpi-icon">&#128202;</span>
          </div>
          <div class="kpi-label">Total Recorded</div>
          <div class="kpi-sub">Lifetime cellar ledger</div>
        </div>
      </div>

      <!-- Mode Selector Tabs -->
      <div class="admin-mode-tabs" style="margin-bottom: 20px;">
        <?php if ($can_add): ?>
          <a href="manageBottles.php?mode=add" class="admin-tab-btn <?php echo ($mode === 'add') ? 'active' : ''; ?>">
            &#10010; Add Bottles to Inventory
          </a>
        <?php endif; ?>
        <?php if ($can_edit): ?>
          <a href="manageBottles.php?mode=edit" class="admin-tab-btn <?php echo ($mode === 'edit') ? 'active' : ''; ?>">
            &#9998; Edit Existing Bottle
          </a>
        <?php endif; ?>
      </div>

      <!-- ADD MODE -->
      <?php if ($mode === 'add' && $can_add): ?>
        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
          <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            &#10010; Record New Bottles
          </h3>

          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add">

            <!-- Row 1: Wine & Format -->
            <div class="form-row-3col" style="margin-bottom: 16px;">
              <div style="grid-column: span 2;">
                <label for="searchWineBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Select Wine: <span style="color: red;">*</span>
                </label>
                <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                  <input type="text" id="searchWineBox" placeholder="🔍 Filter wine cuv&eacute;es..." onkeyup="filterWineDropdown('wine_id', 'searchWineBox')" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                  <select name="wine_id" id="wine_id" required style="width: 100%; border: none; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; border-radius: 0 0 4px 4px; min-height: 44px;">
                    <option value="">-- Select Wine Cuv&eacute;e --</option>
                    <?php foreach ($wines as $w): ?>
                      <option value="<?php echo (int)$w['wine_id']; ?>" <?php echo ((int)$add_wine_id === (int)$w['wine_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['country'] . ': ' . $w['region'] . ' — ' . getWineName($w['nameconvention'], $w['vintage'], $w['name'], $w['producer'], $w['grape'], $w['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div>
                <label for="numBottles" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Quantity (Bottles): <span style="color: red;">*</span>
                </label>
                <select name="numBottles" id="numBottles" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px; font-size: 0.95rem;">
                  <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($add_numBottles === $i) ? 'selected' : ''; ?>>
                      <?php echo $i; ?> bottle<?php echo ($i > 1) ? 's' : ''; ?>
                    </option>
                  <?php endfor; ?>
                </select>
                <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Enables bulk batch entry</div>
              </div>
            </div>

            <!-- Row 2: Format, Location, Store -->
            <div class="form-row-3col" style="margin-bottom: 16px;">
              <div>
                <label for="format" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Bottle Format: <span style="color: red;">*</span>
                </label>
                <select name="format" id="format" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="">-- Select Format --</option>
                  <?php foreach ($formats as $f): ?>
                    <option value="<?php echo htmlspecialchars($f['format'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($f['format'] === $add_format) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($f['format'] . ' (' . $f['format_desc'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="bin_id" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Storage Location:
                </label>
                <select name="bin_id" id="bin_id" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="">-- No bin assigned --</option>
                  <?php foreach ($storageLocations as $sl): ?>
                    <?php
                      $capText = '';
                      if (!empty($sl['max_capacity'])) {
                        $isFull = ((int)$sl['current_bottles'] >= (int)$sl['max_capacity']);
                        $capText = ' (' . (int)$sl['current_bottles'] . ' / ' . (int)$sl['max_capacity'] . ' btls' . ($isFull ? ' — Full' : '') . ')';
                      } elseif (isset($sl['current_bottles']) && (int)$sl['current_bottles'] > 0) {
                        $capText = ' (' . (int)$sl['current_bottles'] . ' btls)';
                      }
                    ?>
                    <option value="<?php echo (int)$sl['bin_id']; ?>" <?php echo ((int)$add_bin_id === (int)$sl['bin_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($sl['cellar_name'] . ': ' . $sl['bin_name'] . $capText, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="store_id" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Purchased From: <span style="color: red;">*</span>
                </label>
                <select name="store_id" id="store_id" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="">-- Select Store --</option>
                  <?php foreach ($stores as $s): ?>
                    <option value="<?php echo (int)$s['store_id']; ?>">
                      <?php echo htmlspecialchars($s['country'] . ': ' . $s['store_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Row 3: Purchase Date, Price, Arrival Date, Status -->
            <div class="form-row-3col" style="margin-bottom: 16px;">
              <div>
                <label for="purchase_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Purchase Date: <span style="color: red;">*</span>
                </label>
                <input type="date" name="purchase_date" id="purchase_date" required value="<?php echo htmlspecialchars($add_purchase_date, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="purchase_price" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Purchase Price per Bottle:
                </label>
                <input type="number" step="0.01" name="purchase_price" id="purchase_price" placeholder="0.00" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="status" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Status: <span style="color: red;">*</span>
                </label>
                <select name="status" id="status" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="in cellar" selected>in cellar</option>
                  <option value="pending delivery">pending delivery</option>
                  <option value="consumed">consumed</option>
                  <option value="lost">lost</option>
                  <option value="sold">sold</option>
                </select>
              </div>
            </div>

            <!-- Row 4: Drinking Window & Flags -->
            <div class="form-row-3col" style="margin-bottom: 16px;">
              <div>
                <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Drinking Window (Years):
                </label>
                <div style="display: flex; gap: 8px; align-items: center;">
                  <input type="text" name="drink_from" maxlength="4" placeholder="From" style="flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center;">
                  <span>&ndash;</span>
                  <input type="text" name="drink_through" maxlength="4" placeholder="To" style="flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center;">
                </div>
              </div>

              <div>
                <label for="for_sale" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Available for Sale:
                </label>
                <select name="for_sale" id="for_sale" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="no" selected>No</option>
                  <option value="yes">Yes</option>
                </select>
              </div>

              <div>
                <label for="restricted" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Private Reserve / Restricted:
                </label>
                <select name="restricted" id="restricted" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="0" selected>No (Publicly Visible)</option>
                  <option value="1">Yes (Private Reserve Only)</option>
                </select>
              </div>
            </div>

            <div style="margin-top: 24px;">
              <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                &#10010; Add Bottles to Cellar
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- EDIT MODE -->
      <?php if ($mode === 'edit' && $can_edit): ?>
        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
          <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            &#9998; Select &amp; Edit Bottle
          </h3>

          <!-- Bottle Selector Dropdown with Filter -->
          <div style="margin-bottom: 24px; max-width: 600px;">
            <label for="searchBottleBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
              Find Bottle to Edit:
            </label>
            <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
              <input type="text" id="searchBottleBox" placeholder="🔍 Search by bottle ID, producer, or bin..." onkeyup="filterBottleDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
              <select id="bottleSelector" onchange="if(this.value) window.location.href='manageBottles.php?mode=edit&bottle_id=' + this.value;" style="width: 100%; border: none; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; border-radius: 0 0 4px 4px; min-height: 44px;">
                <option value="">-- Choose Bottle --</option>
                <?php foreach ($bottles as $b): ?>
                  <option value="<?php echo (int)$b['bottle_id']; ?>" <?php echo ($requested_bottle_id === (int)$b['bottle_id']) ? 'selected' : ''; ?>>
                    #<?php echo (int)$b['bottle_id']; ?> — <?php echo htmlspecialchars(($b['cellar_name'] ?? 'No Cellar') . ' / ' . ($b['bin_name'] ?? 'No Bin') . ' — ' . getWineName($b['nameconvention'], $b['vintage'], $b['name'], $b['producer'], $b['grape'], $b['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <?php if ($selected_bottle): ?>
            <form method="POST" accept-charset="UTF-8" style="background: white; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px;">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="bottle_id" value="<?php echo (int)$selected_bottle['bottle_id']; ?>">

              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h4 style="margin: 0; color: var(--secondary-accent, firebrick); font-size: 1.05rem;">
                  Editing Bottle #<?php echo (int)$selected_bottle['bottle_id']; ?>
                </h4>
                <a href="manageTastingNotes.php?wine_id=<?php echo (int)$selected_bottle['wine_id']; ?>" style="font-size: 0.88rem; color: #0d6efd; text-decoration: none;">
                  + Add Tasting Note &rarr;
                </a>
              </div>

              <!-- Row 1: Wine & Format -->
              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="searchEditWineBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Wine Cuv&eacute;e: <span style="color: red;">*</span>
                  </label>
                  <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <input type="text" id="searchEditWineBox" placeholder="🔍 Filter wine cuvées..." onkeyup="filterWineDropdown('edit_wine_id', 'searchEditWineBox')" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                    <select name="wine_id" id="edit_wine_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                      <option value="">-- Select Wine Cuv&eacute;e --</option>
                      <?php foreach ($wines as $w): ?>
                        <option value="<?php echo (int)$w['wine_id']; ?>" <?php echo ((int)$selected_bottle['wine_id'] === (int)$w['wine_id']) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($w['country'] . ': ' . $w['region'] . ' — ' . getWineName($w['nameconvention'], $w['vintage'], $w['name'], $w['producer'], $w['grape'], $w['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div>
                  <label for="edit_format" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Bottle Format: <span style="color: red;">*</span>
                  </label>
                  <select name="format" id="edit_format" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                    <?php foreach ($formats as $f): ?>
                      <option value="<?php echo htmlspecialchars($f['format'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($f['format'] === $selected_bottle['format']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['format'] . ' (' . $f['format_desc'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Row 2: Location, Store, Status -->
              <div class="form-row-3col" style="margin-bottom: 16px;">
                <div>
                  <label for="edit_bin_id" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Storage Location:
                  </label>
                  <select name="bin_id" id="edit_bin_id" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                    <option value="">-- No bin assigned --</option>
                    <?php foreach ($storageLocations as $sl): ?>
                      <?php
                        $capText = '';
                        if (!empty($sl['max_capacity'])) {
                          $isFull = ((int)$sl['current_bottles'] >= (int)$sl['max_capacity']);
                          $capText = ' (' . (int)$sl['current_bottles'] . ' / ' . (int)$sl['max_capacity'] . ' btls' . ($isFull ? ' — Full' : '') . ')';
                        } elseif (isset($sl['current_bottles']) && (int)$sl['current_bottles'] > 0) {
                          $capText = ' (' . (int)$sl['current_bottles'] . ' btls)';
                        }
                        $isSelected = ((int)($selected_bottle['storage_location'] ?? 0) === (int)$sl['bin_id']);
                      ?>
                      <option value="<?php echo (int)$sl['bin_id']; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sl['cellar_name'] . ': ' . $sl['bin_name'] . $capText, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label for="edit_store_id" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Purchased From: <span style="color: red;">*</span>
                  </label>
                  <select name="store_id" id="edit_store_id" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                    <?php foreach ($stores as $s): ?>
                      <option value="<?php echo (int)$s['store_id']; ?>" <?php echo ((int)$selected_bottle['purchased_from'] === (int)$s['store_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['country'] . ': ' . $s['store_name'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label for="edit_status" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Bottle Status: <span style="color: red;">*</span>
                  </label>
                  <select name="status" id="edit_status" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                    <option value="in cellar" <?php echo ($selected_bottle['status'] === 'in cellar') ? 'selected' : ''; ?>>in cellar</option>
                    <option value="pending delivery" <?php echo ($selected_bottle['status'] === 'pending delivery') ? 'selected' : ''; ?>>pending delivery</option>
                    <option value="consumed" <?php echo ($selected_bottle['status'] === 'consumed') ? 'selected' : ''; ?>>consumed</option>
                    <option value="lost" <?php echo ($selected_bottle['status'] === 'lost') ? 'selected' : ''; ?>>lost</option>
                    <option value="sold" <?php echo ($selected_bottle['status'] === 'sold') ? 'selected' : ''; ?>>sold</option>
                  </select>
                </div>
              </div>

              <!-- Row 3: Dates & Price -->
              <div class="form-row-3col" style="margin-bottom: 16px;">
                <div>
                  <label for="edit_purchase_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Purchase Date: <span style="color: red;">*</span>
                  </label>
                  <input type="date" name="purchase_date" id="edit_purchase_date" required value="<?php echo htmlspecialchars($selected_bottle['purchase_date'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>

                <div>
                  <label for="edit_purchase_price" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Purchase Price:
                  </label>
                  <input type="number" step="0.01" name="purchase_price" id="edit_purchase_price" value="<?php echo htmlspecialchars($selected_bottle['purchase_price'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>

                <div>
                  <label for="edit_arrival_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Arrival Date:
                  </label>
                  <input type="date" name="arrival_date" id="edit_arrival_date" value="<?php echo htmlspecialchars($selected_bottle['arrival_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>
              </div>

              <!-- Row 4: Drinking Window & Flags -->
              <div class="form-row-3col" style="margin-bottom: 16px;">
                <div>
                  <label style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Drinking Window (Years):
                  </label>
                  <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="text" name="drink_from" maxlength="4" value="<?php echo htmlspecialchars($selected_bottle['drink_from'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="From" style="flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center;">
                    <span>&ndash;</span>
                    <input type="text" name="drink_through" maxlength="4" value="<?php echo htmlspecialchars($selected_bottle['drink_through'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="To" style="flex: 1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px; text-align: center;">
                  </div>
                </div>

                <div>
                  <label for="edit_for_sale" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Available for Sale:
                  </label>
                  <select name="for_sale" id="edit_for_sale" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                    <option value="no" <?php echo ($selected_bottle['for_sale'] === 'no') ? 'selected' : ''; ?>>No</option>
                    <option value="yes" <?php echo ($selected_bottle['for_sale'] === 'yes') ? 'selected' : ''; ?>>Yes</option>
                  </select>
                </div>

                <div>
                  <label for="edit_restricted" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Private Reserve / Restricted:
                  </label>
                  <select name="restricted" id="edit_restricted" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                    <option value="0" <?php echo (empty($selected_bottle['restricted'])) ? 'selected' : ''; ?>>No (Publicly Visible)</option>
                    <option value="1" <?php echo (!empty($selected_bottle['restricted']) && (int)$selected_bottle['restricted'] === 1) ? 'selected' : ''; ?>>Yes (Private Reserve Only)</option>
                  </select>
                </div>
              </div>

              <!-- Row 5: Consumption Details & Note ID -->
              <div class="form-row-2col" style="margin-bottom: 16px;">
                <div>
                  <label for="edit_consumption_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Consumption Date:
                  </label>
                  <input type="date" name="consumption_date" id="edit_consumption_date" value="<?php echo htmlspecialchars($selected_bottle['consumption_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>

                <div>
                  <label for="edit_note_id" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                    Linked Tasting Note ID:
                  </label>
                  <input type="text" name="note_id" id="edit_note_id" maxlength="6" value="<?php echo htmlspecialchars($selected_bottle['note_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 104" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                </div>
              </div>

              <div style="margin-bottom: 20px;">
                <label for="edit_consumption_note" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Consumption Note / Occasion:
                </label>
                <textarea name="consumption_note" id="edit_consumption_note" rows="3" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem;" placeholder="Notes on dinner party, cork condition, provenance, etc."><?php echo htmlspecialchars($selected_bottle['consumption_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>

              <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                  Update Bottle #<?php echo (int)$selected_bottle['bottle_id']; ?>
                </button>
                <a href="manageBottles.php?mode=edit" class="btn-action btn-secondary" style="padding: 12px 20px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
                  Cancel
                </a>
              </div>
            </form>
          <?php else: ?>
            <div style="text-align: center; padding: 30px; background: white; border: 1px dashed #ccc; border-radius: 6px; color: #777;">
              Please select a bottle above or use the inventory table below to load bottle details for editing.
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Quick Inventory Directory / Lookup -->
      <div style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
            Quick Cellar Inventory Directory (<?php echo count($bottles); ?> tracked)
          </h3>
          <input type="text" id="quickSearchBox" class="admin-search-box" style="margin-bottom: 0; max-width: 320px;" placeholder="Search inventory ledger..." onkeyup="filterInventoryTable()">
        </div>

        <div class="admin-table-container" style="max-height: 480px; overflow-y: auto;">
          <table id="bottlesLedgerTable">
            <thead>
              <tr>
                <th style="width: 70px;">ID</th>
                <th>Wine Details</th>
                <th>Storage Bin</th>
                <th>Vintage</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($bottles)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: #777; padding: 20px;">No bottles currently recorded.</td>
                </tr>
              <?php else: ?>
                <?php foreach (array_slice($bottles, 0, 100) as $b): ?>
                  <tr style="<?php echo ($requested_bottle_id === (int)$b['bottle_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                    <td><strong>#<?php echo (int)$b['bottle_id']; ?></strong></td>
                    <td>
                      <div><strong><?php echo htmlspecialchars(getWineName($b['nameconvention'], $b['vintage'], $b['name'], $b['producer'], $b['grape'], $b['vineyard']), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                      <div style="font-size: 0.78rem; color: #777;"><?php echo htmlspecialchars($b['producer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td>
                      <div style="font-size: 0.88rem;"><?php echo htmlspecialchars($b['cellar_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></div>
                      <div style="font-size: 0.78rem; color: #888;"><?php echo htmlspecialchars($b['bin_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($b['vintage'] ?? 'NV', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align: right; white-space: nowrap;">
                      <?php if ($can_edit): ?>
                        <a href="manageBottles.php?mode=edit&bottle_id=<?php echo (int)$b['bottle_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                          Edit
                        </a>
                      <?php endif; ?>
                      <a href="manageTastingNotes.php?wine_id=<?php echo (int)$b['wine_id']; ?>" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                        + Note
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($bottles) > 100): ?>
          <div style="font-size: 0.82rem; color: #888; margin-top: 8px; text-align: right;">
            Showing latest 100 bottles. <a href="browseBottles.php" style="color: #0d6efd;">View all in Cellar Browser &rarr;</a>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
  let cachedWineOptions = [];
  let cachedBottleOptions = [];

  document.addEventListener("DOMContentLoaded", function() {
    const wineSel = document.getElementById('wine_id');
    if (wineSel) {
      for (let i = 1; i < wineSel.options.length; i++) {
        const opt = wineSel.options[i];
        cachedWineOptions.push({ value: opt.value, text: opt.textContent });
      }
    }

    const btlSel = document.getElementById('bottleSelector');
    if (btlSel) {
      for (let i = 1; i < btlSel.options.length; i++) {
        const opt = btlSel.options[i];
        cachedBottleOptions.push({ value: opt.value, text: opt.textContent });
      }
    }
  });

  function filterWineDropdown(selectId, searchBoxId) {
    const query = document.getElementById(searchBoxId).value.toLowerCase().trim();
    const select = document.getElementById(selectId);
    if (!select) return;

    const currentVal = select.value;
    const terms = query.split(/\s+/).filter(t => t.length > 0);

    while (select.options.length > 1) {
      select.remove(1);
    }

    cachedWineOptions.forEach(optData => {
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

  function filterBottleDropdown() {
    const query = document.getElementById('searchBottleBox').value.toLowerCase().trim();
    const select = document.getElementById('bottleSelector');
    if (!select) return;

    const currentVal = select.value;
    const terms = query.split(/\s+/).filter(t => t.length > 0);

    while (select.options.length > 1) {
      select.remove(1);
    }

    cachedBottleOptions.forEach(optData => {
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

  function filterInventoryTable() {
    const searchVal = document.getElementById('quickSearchBox').value.toLowerCase().trim();
    const table = document.getElementById('bottlesLedgerTable');
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
