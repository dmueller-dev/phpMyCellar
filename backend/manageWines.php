<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Wine Vintage Management Dashboard.
   *
   * Combines creation and editing of specific wine vintages.
   * Required privileges: 'add_wine' or 'edit_wine'
   */
  $can_add = hasPrivilege($conn, 'add_wine');
  $can_edit = hasPrivilege($conn, 'edit_wine');

  if (!$can_add && !$can_edit) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Pre-load lookup data
  $masters = getWineMasters($conn);
  $vintages = getVintages($conn);

  // Determine requested wine for editing
  $requested_wine_id = filter_input(INPUT_GET, 'wine_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_wine', FILTER_VALIDATE_INT);
  $filter_master_id = filter_input(INPUT_GET, 'master_id', FILTER_VALIDATE_INT);

  $is_edit = false;
  $wine_id = null;
  $master_id = $filter_master_id ?: '';
  $ct_id = '';
  $vintage = '';
  $wine_desc = '';

  if ($requested_wine_id) {
    $existing = getWineDetails($conn, $requested_wine_id);
    if ($existing) {
      $is_edit = true;
      $wine_id = (int)$existing['wine_id'];
      $master_id = (int)$existing['master_id'];
      $ct_id = $existing['ct_id'] ?? '';
      $vintage = (int)$existing['vintage'];
      $wine_desc = $existing['wine_desc'] ?? '';
    } else {
      $errors[] = "Requested wine vintage #" . $requested_wine_id . " not found.";
    }
  }

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $master_id_input = filter_input(INPUT_POST, 'master_id', FILTER_VALIDATE_INT);
    $ct_id_input = filter_input(INPUT_POST, 'ct_id', FILTER_VALIDATE_INT) ?: null;
    $vintage_input = filter_input(INPUT_POST, 'vintage', FILTER_VALIDATE_INT);
    if ($vintage_input === null) {
      $vintage_input = 0; // NV
    }
    $wine_desc_input = sanitizeInput($_POST['wine_desc'] ?? '');

    $valErrors = validateWineInput($is_edit ? $wine_id : 0, $master_id_input, $vintage_input, $wine_desc_input, $ct_id_input);
    if (!empty($valErrors)) {
      $errors = array_merge($errors, $valErrors);
    } else {
      if ($action === 'add') {
        if (!$can_add) {
          die("Permission denied: You do not have permission to add wine vintages.");
        }
        $conn->begin_transaction();
        try {
          if (insertWine($conn, $master_id_input, $ct_id_input, $vintage_input, $wine_desc_input)) {
            $inserted_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Wine vintage inserted successfully! " .
              "<a href='manageBottles.php?mode=add&wine_id=" . $inserted_id . "'>Add bottles of this vintage &rarr;</a> | " .
              "<a href='manageTastingNotes.php?wine_id=" . $inserted_id . "'>Record tasting note &rarr;</a>";
            $ct_id = '';
            $wine_desc = '';
            $is_edit = false;
          } else {
            $conn->rollback();
            $errors[] = "Failed to insert wine vintage.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting wine vintage: " . $e->getMessage();
        }
      } elseif ($action === 'edit') {
        if (!$can_edit) {
          die("Permission denied: You do not have permission to edit wine vintages.");
        }
        $post_wine_id = filter_input(INPUT_POST, 'wine_id', FILTER_VALIDATE_INT);
        $conn->begin_transaction();
        try {
          if (updateWine($conn, $post_wine_id, $master_id_input, $vintage_input, $wine_desc_input, $ct_id_input)) {
            $conn->commit();
            $success_message = "Wine vintage updated successfully.";
            $wine_id = $post_wine_id;
            $master_id = $master_id_input;
            $vintage = $vintage_input;
            $ct_id = $ct_id_input ?? '';
            $wine_desc = $wine_desc_input;
            $is_edit = true;
          } else {
            $conn->rollback();
            $errors[] = "Failed to update wine vintage.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating wine: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch all wine vintages with bottle statistics
  $wines_stmt = $conn->prepare("
    SELECT w.wine_id, w.master_id, w.vintage, w.ct_id, w.wine_desc,
           wm.name, wm.nameconvention, wm.grape,
           p.producer, r.region, r.country, vy.vineyard,
           COUNT(b.bottle_id) AS bottle_count
    FROM wines w
    LEFT JOIN wines_master wm ON w.master_id = wm.master_id
    LEFT JOIN producers p ON wm.producer_id = p.producer_id
    LEFT JOIN regions r ON wm.region_id = r.region_id
    LEFT JOIN vineyards vy ON wm.vineyard_id = vy.vineyard_id
    LEFT JOIN bottles b ON w.wine_id = b.wine_id
    GROUP BY w.wine_id, w.master_id, w.vintage, w.ct_id, w.wine_desc, wm.name, wm.nameconvention, wm.grape, p.producer, r.region, r.country, vy.vineyard
    ORDER BY r.country ASC, r.region ASC, p.producer ASC, wm.name ASC, w.vintage DESC
  ");
  $wine_records = [];
  if ($wines_stmt) {
    $wines_stmt->execute();
    $res = $wines_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $wine_records[] = $row;
    }
    $wines_stmt->close();
  }

  $totalWines = count($wine_records);
  $totalBottles = 0;
  foreach ($wine_records as $rec) {
    $totalBottles += (int)$rec['bottle_count'];
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Wine Vintages - Cellar Administration';
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
          &#127870; Manage Wine Vintages
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageWineMasters.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &larr; Cuv&eacute;e Masters
          </a>
          <a href="manageBottles.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Bottles &rarr;
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
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalWines); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Recorded Vintages</div>
          <div class="kpi-sub">Distinct wine vintage editions</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalBottles); ?></span>
            <span class="kpi-icon">&#129346;</span>
          </div>
          <div class="kpi-label">Inventory Bottles</div>
          <div class="kpi-sub">Total physical bottles linked</div>
        </div>
      </div>

      <!-- Mobile Segmented View Switcher -->
      <div class="admin-mobile-pane-switch">
        <button type="button" id="tabBtnList" class="admin-mobile-pane-btn <?php echo !$is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('list')">
          📋 Vintages (<?php echo count($wine_records); ?>)
        </button>
        <button type="button" id="tabBtnForm" class="admin-mobile-pane-btn <?php echo $is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('form')">
          <?php echo $is_edit ? '✏️ Edit Vintage' : '➕ Add Vintage'; ?>
        </button>
      </div>

      <!-- Responsive Dual Grid: Wine Directory & Form -->
      <div class="admin-dual-manage-grid unequal">
        
        <!-- Left: Searchable Wine Directory -->
        <div id="adminDualListPane" class="admin-dual-pane <?php echo $is_edit ? 'mobile-hidden' : ''; ?>">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.1rem;">
              Existing Vintages (<?php echo count($wine_records); ?>)
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageWines.php" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none;">+ Add New</a>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
            <select id="masterFilter" style="flex: 1; min-width: 140px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px;" onchange="filterWineList()">
              <option value="">All Cuv&eacute;es</option>
              <?php foreach ($masters as $m): ?>
                <option value="<?php echo (int)$m['master_id']; ?>" <?php echo ($filter_master_id === (int)$m['master_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($m['producer'] . ' — ' . $m['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="wineSearch" class="admin-search-box" style="flex: 2; min-width: 160px; margin-bottom: 0;" placeholder="Search wine vintages..." onkeyup="filterWineList()">
          </div>

          <div class="admin-table-container" style="max-height: 580px; overflow-y: auto;">
            <table id="wineTable">
              <thead>
                <tr>
                  <th>Wine / Vintage</th>
                  <th>Terroir</th>
                  <th style="text-align: center;">Bottles</th>
                  <th style="text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($wine_records)): ?>
                  <tr>
                    <td colspan="4" style="text-align: center; color: #777; padding: 20px;">No wine vintages found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($wine_records as $w): ?>
                    <tr data-master-id="<?php echo (int)$w['master_id']; ?>" style="<?php echo ($is_edit && $wine_id === (int)$w['wine_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                      <td>
                        <strong><?php echo htmlspecialchars(getWineName($w['nameconvention'], $w['vintage'], $w['name'], $w['producer'], $w['grape'], $w['vineyard']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div style="font-size: 0.78rem; color: #555;">
                          Vintage: <strong><?php echo ((int)$w['vintage'] === 0) ? 'NV' : (int)$w['vintage']; ?></strong>
                          <?php if (!empty($w['ct_id'])): ?> &bull; CT #<?php echo (int)$w['ct_id']; ?><?php endif; ?>
                        </div>
                      </td>
                      <td data-label="Terroir">
                        <div style="font-size: 0.88rem;"><?php echo htmlspecialchars($w['region'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div style="font-size: 0.78rem; color: #888;"><?php echo htmlspecialchars($w['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                      </td>
                      <td data-label="Bottles" style="text-align: center;">
                        <?php if ((int)$w['bottle_count'] > 0): ?>
                          <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; background: #e8f5e9; color: #2e7d32; font-size: 0.82rem; font-weight: bold;">
                            <?php echo (int)$w['bottle_count']; ?>
                          </span>
                        <?php else: ?>
                          <span style="color: #bbb; font-size: 0.82rem;">0</span>
                        <?php endif; ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <?php if ($can_edit): ?>
                          <a href="manageWines.php?wine_id=<?php echo (int)$w['wine_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                            Edit
                          </a>
                        <?php endif; ?>
                        <?php if (hasPrivilege($conn, 'add_bottle')): ?>
                          <a href="manageBottles.php?mode=add&wine_id=<?php echo (int)$w['wine_id']; ?>" title="Add bottles" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                            + Bottle
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right: Add / Edit Wine Form -->
        <div id="adminDualFormPane" class="admin-dual-pane <?php echo !$is_edit ? 'mobile-hidden' : ''; ?>" style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
              <?php echo $is_edit ? '&#9998; Edit Wine #' . $wine_id : '&#10010; Add Wine Vintage'; ?>
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageWines.php" style="font-size: 0.85rem; color: #dc3545; text-decoration: none;">Cancel</a>
            <?php endif; ?>
          </div>

          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
              <input type="hidden" name="wine_id" value="<?php echo (int)$wine_id; ?>">
            <?php endif; ?>

            <div style="margin-bottom: 15px;">
              <label for="searchMasterBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Cuv&eacute;e Master: <span style="color: red;">*</span>
              </label>
              <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <input type="text" id="searchMasterBox" placeholder="🔍 Search cuvée master by producer, name, region..." onkeyup="filterMasterDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                <select name="master_id" id="master_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                  <option value="">-- Select Cuv&eacute;e Master --</option>
                  <?php foreach ($masters as $m): ?>
                    <option value="<?php echo (int)$m['master_id']; ?>" <?php echo ((int)$master_id === (int)$m['master_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($m['country'] . ': ' . $m['region'] . ' — ' . $m['producer'] . ' — ' . $m['name'] . ' (' . $m['grape'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row-2col" style="margin-bottom: 15px;">
              <div>
                <label for="vintage" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Vintage Year: <span style="color: red;">*</span>
                </label>
                <select name="vintage" id="vintage" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="0" <?php echo ($vintage === 0) ? 'selected' : ''; ?>>NV (Non-Vintage)</option>
                  <?php foreach ($vintages as $v): ?>
                    <option value="<?php echo (int)$v['vintage']; ?>" <?php echo ((int)$vintage === (int)$v['vintage']) ? 'selected' : ''; ?>>
                      <?php echo (int)$v['vintage']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="ct_id" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  CellarTracker ID:
                </label>
                <input type="text" name="ct_id" id="ct_id" value="<?php echo htmlspecialchars($ct_id, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 123456" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>
            </div>

            <div style="margin-bottom: 20px;">
              <label for="wine_desc" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Vintage Report &amp; Notes:
              </label>
              <textarea name="wine_desc" id="wine_desc" rows="6" maxlength="2000" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem;" placeholder="Vintage conditions, weather, blend proportions, etc."><?php echo htmlspecialchars($wine_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <div style="font-size: 0.78rem; color: #888; margin-top: 4px;">HTML formatting supported (e.g. &lt;p&gt;, &lt;em&gt;).</div>
            </div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn-action" style="flex: 1; padding: 10px 16px; font-size: 0.95rem; cursor: pointer; min-height: 44px;">
                <?php echo $is_edit ? 'Update Wine Vintage' : 'Insert Wine Vintage'; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageWines.php" class="btn-action btn-secondary" style="padding: 10px 16px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
                  Cancel
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>

      </div>

    </div>
  </div>
</div>

<script>
  let cachedMasterOptions = [];

  document.addEventListener("DOMContentLoaded", function() {
    const masterSel = document.getElementById('master_id');
    if (masterSel) {
      for (let i = 1; i < masterSel.options.length; i++) {
        const opt = masterSel.options[i];
        cachedMasterOptions.push({ value: opt.value, text: opt.textContent });
      }
    }
  });

  function filterMasterDropdown() {
    const query = document.getElementById('searchMasterBox').value.toLowerCase().trim();
    const select = document.getElementById('master_id');
    if (!select || !cachedMasterOptions) return;

    const currentVal = select.value;
    const terms = query.split(/\s+/).filter(t => t.length > 0);

    while (select.options.length > 1) {
      select.remove(1);
    }

    cachedMasterOptions.forEach(optData => {
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

  function filterWineList() {
    const searchVal = document.getElementById('wineSearch').value.toLowerCase().trim();
    const masterVal = document.getElementById('masterFilter').value;
    const table = document.getElementById('wineTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.cells.length < 4) continue;
      
      const rowMaster = row.getAttribute('data-master-id');
      const textContent = row.textContent.toLowerCase();

      const matchesSearch = !searchVal || textContent.includes(searchVal);
      const matchesMaster = !masterVal || (rowMaster === masterVal);

      if (matchesSearch && matchesMaster) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    }
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
