<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage wine subregions and terroir classifications.
   *
   * Required privilege: 'manage_subregions'
   */
  if (!hasPrivilege($conn, 'manage_subregions')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Get regions for dropdown
  $regions = getRegions($conn);

  // Determine requested subregion for editing
  $requested_subregion_id = filter_input(INPUT_GET, 'subregion_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_subregion', FILTER_VALIDATE_INT);
  $filter_region_id = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT);

  $is_edit = false;
  $subregion_id = null;
  $region_id = $filter_region_id ?: '';
  $subregion = '';
  $subregion_desc = '';

  if ($requested_subregion_id) {
    $existing = getSubregionDetails($conn, $requested_subregion_id);
    if ($existing) {
      $is_edit = true;
      $subregion_id = (int)$existing['subregion_id'];
      $region_id = (int)$existing['region_id'];
      $subregion = $existing['subregion'];
      $subregion_desc = $existing['subregion_desc'] ?? '';
    } else {
      $errors[] = "Requested subregion not found.";
    }
  }

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $subregion_input = sanitizeInput($_POST['subregion'] ?? '');
    $region_id_input = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
    $subregion_desc_input = sanitizeInput($_POST['subregion_desc'] ?? '');

    $valErrors = validateSubregionInput($subregion_input, $region_id_input, $subregion_desc_input);
    if (!empty($valErrors)) {
      $errors = array_merge($errors, $valErrors);
    } else {
      if ($action === 'add') {
        $conn->begin_transaction();
        try {
          if (insertSubregion($conn, $subregion_input, $region_id_input, $subregion_desc_input)) {
            $inserted_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Subregion '" . htmlspecialchars($subregion_input, ENT_QUOTES, 'UTF-8') . "' inserted successfully. <a href='manageAppellations.php?subregion_id=" . $inserted_id . "'>Add an appellation for this subregion &rarr;</a>";
            $subregion = '';
            $subregion_desc = '';
            $is_edit = false;
          } else {
            $conn->rollback();
            $errors[] = "Failed to insert subregion. A subregion with that name may already exist in this region.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting subregion: " . $e->getMessage();
        }
      } elseif ($action === 'edit') {
        $post_subregion_id = filter_input(INPUT_POST, 'subregion_id', FILTER_VALIDATE_INT);
        $conn->begin_transaction();
        try {
          if (updateSubregion($conn, $post_subregion_id, $region_id_input, $subregion_input, $subregion_desc_input)) {
            $conn->commit();
            $success_message = "Subregion '" . htmlspecialchars($subregion_input, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
            $subregion_id = $post_subregion_id;
            $region_id = $region_id_input;
            $subregion = $subregion_input;
            $subregion_desc = $subregion_desc_input;
            $is_edit = true;
          } else {
            $conn->rollback();
            $errors[] = "Failed to update subregion details.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating subregion: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch all subregions with statistics
  $subregions_stmt = $conn->prepare("
    SELECT sr.subregion_id, sr.subregion, sr.region_id, sr.subregion_desc,
           r.region, r.country,
           COUNT(DISTINCT a.appellation_id) AS appellation_count,
           COUNT(DISTINCT wm.master_id) AS master_count
    FROM subregions sr
    JOIN regions r ON sr.region_id = r.region_id
    LEFT JOIN appellations a ON sr.subregion_id = a.subregion_id
    LEFT JOIN wines_master wm ON sr.subregion_id = wm.subregion_id
    GROUP BY sr.subregion_id, sr.subregion, sr.region_id, sr.subregion_desc, r.region, r.country
    ORDER BY r.country ASC, r.region ASC, sr.subregion ASC
  ");
  $subregion_records = [];
  if ($subregions_stmt) {
    $subregions_stmt->execute();
    $res = $subregions_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $subregion_records[] = $row;
    }
    $subregions_stmt->close();
  }

  $totalSubregions = count($subregion_records);
  $withAppellations = 0;
  $withWines = 0;
  foreach ($subregion_records as $rec) {
    if ((int)$rec['appellation_count'] > 0) $withAppellations++;
    if ((int)$rec['master_count'] > 0) $withWines++;
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Subregions - Cellar Administration';
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
          &#128506;&#65039; Manage Subregions
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageRegions.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &larr; Regions
          </a>
          <a href="manageAppellations.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Appellations &rarr;
          </a>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div style="background-color: #ffdddd; border-left: 5px solid #f44336; padding: 10px 15px; margin-bottom: 20px;">
          <ul style="margin: 0; padding-left: 20px; color: #a00;">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 10px 15px; margin-bottom: 20px; color: #2e7d32;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <!-- High-level Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalSubregions); ?></span>
            <span class="kpi-icon">&#128506;&#65039;</span>
          </div>
          <div class="kpi-label">Subregions</div>
          <div class="kpi-sub">Total sub-districts recorded</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($withAppellations); ?></span>
            <span class="kpi-icon">&#127991;&#65039;</span>
          </div>
          <div class="kpi-label">With Appellations</div>
          <div class="kpi-sub">Classified sub-zones</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($withWines); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Active in Cellar</div>
          <div class="kpi-sub">Represented in wine masters</div>
        </div>
      </div>

      <!-- Mobile Segmented View Switcher -->
      <div class="admin-mobile-pane-switch">
        <button type="button" id="tabBtnList" class="admin-mobile-pane-btn <?php echo !$is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('list')">
          📋 Subregions (<?php echo count($subregion_records); ?>)
        </button>
        <button type="button" id="tabBtnForm" class="admin-mobile-pane-btn <?php echo $is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('form')">
          <?php echo $is_edit ? '✏️ Edit Subregion' : '➕ Add Subregion'; ?>
        </button>
      </div>

      <!-- Responsive Dual Grid: Subregion Directory & Form -->
      <div class="admin-dual-manage-grid unequal">
        
        <!-- Left: Searchable Subregions Directory -->
        <div id="adminDualListPane" class="admin-dual-pane <?php echo $is_edit ? 'mobile-hidden' : ''; ?>">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.1rem;">
              Existing Subregions (<?php echo count($subregion_records); ?>)
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageSubregions.php" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none;">+ Add New</a>
            <?php endif; ?>
          </div>

          <div class="admin-filter-bar" style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
            <select id="regionFilter" style="flex: 1; min-width: 140px; max-width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px;" onchange="filterSubregionList()">
              <option value="">All Regions</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?php echo (int)$r['region_id']; ?>" <?php echo ($filter_region_id === (int)$r['region_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($r['region'] . ' (' . $r['country'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="subregionSearch" class="admin-search-box" style="flex: 2; min-width: 160px; max-width: 100%; box-sizing: border-box; margin-bottom: 0;" placeholder="Search subregions..." oninput="filterSubregionList()" onkeyup="filterSubregionList()">
          </div>

          <div class="admin-table-container" style="max-height: 520px; overflow-y: auto;">
            <table id="subregionTable">
              <thead>
                <tr>
                  <th>Subregion / Region</th>
                  <th style="text-align: center;">Appellations</th>
                  <th style="text-align: center;">Wines</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($subregion_records)): ?>
                  <tr><td colspan="4" style="text-align: center; color: #777;">No subregions recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($subregion_records as $sr): ?>
                    <tr data-region-id="<?php echo (int)$sr['region_id']; ?>"
                        style="<?php echo ($is_edit && $subregion_id === (int)$sr['subregion_id']) ? 'background: #fff8e7; font-weight: 500;' : ''; ?>">
                      <td class="card-primary-cell">
                        <strong><?php echo htmlspecialchars($sr['subregion'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($sr['region'] . ', ' . $sr['country'], ENT_QUOTES, 'UTF-8'); ?></small>
                      </td>
                      <td data-label="Appellations" style="text-align: center;">
                        <a href="manageAppellations.php?subregion_id=<?php echo (int)$sr['subregion_id']; ?>" title="View appellations" style="text-decoration: none; color: #555;">
                          <?php echo (int)$sr['appellation_count']; ?>
                        </a>
                      </td>
                      <td data-label="Wines" style="text-align: center; color: #666;">
                        <?php echo (int)$sr['master_count']; ?>
                      </td>
                      <td class="card-actions-cell" style="text-align: right;">
                        <a href="manageSubregions.php?subregion_id=<?php echo (int)$sr['subregion_id']; ?>" class="terroir-btn terroir-btn-edit" style="padding: 4px 10px; font-size: 0.8rem;" title="Edit subregion">
                          Edit
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right: Add / Edit Form Card -->
        <div id="adminDualFormPane" class="admin-system-card admin-dual-pane <?php echo !$is_edit ? 'mobile-hidden' : ''; ?>">
          <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick);">
            <?php echo $is_edit ? '✏️ Edit Subregion: ' . htmlspecialchars($subregion, ENT_QUOTES, 'UTF-8') : '➕ Add New Subregion'; ?>
          </h3>

          <form method="POST" action="manageSubregions.php<?php echo $is_edit ? '?subregion_id=' . $subregion_id : ''; ?>" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
              <input type="hidden" name="subregion_id" value="<?php echo (int)$subregion_id; ?>">
            <?php endif; ?>

            <div style="margin-bottom: 14px;">
              <label for="searchRegionBox" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Parent Region: <span style="color: red;">*</span>
              </label>
              <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <input type="text" id="searchRegionBox" placeholder="🔍 Filter regions..." onkeyup="filterRegionDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                <select id="region_select_field" name="region_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.95rem;">
                  <option value="">Select region...</option>
                  <?php foreach ($regions as $r): ?>
                    <option value="<?php echo (int)$r['region_id']; ?>" <?php echo ((int)$region_id === (int)$r['region_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($r['region'] . ' (' . $r['country'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-bottom: 14px;">
              <label for="subregion_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Subregion Name: <span style="color: red;">*</span>
              </label>
              <input type="text" id="subregion_field" name="subregion" required maxlength="100"
                     value="<?php echo htmlspecialchars($subregion, ENT_QUOTES, 'UTF-8'); ?>"
                     style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; min-height: 44px;"
                     placeholder="e.g. M&eacute;doc, C&ocirc;te de Nuits, Chianti Classico">
            </div>

            <div style="margin-bottom: 18px;">
              <label for="subregion_desc_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Description / Editorial Notes:
              </label>
              <textarea id="subregion_desc_field" name="subregion_desc" rows="10" maxlength="1500"
                        style="width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; font-family: inherit;"
                        placeholder="<p>Distinguishing valley aspects, geological formations, elevation, and key viticultural nuances...</p>"><?php echo htmlspecialchars($subregion_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <small style="color: #777;">Optional HTML permitted (e.g. &lt;p&gt;, &lt;em&gt;). Maximum 1,500 characters.</small>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <button type="submit" class="btn-action" style="padding: 10px 20px; font-size: 0.95rem; min-height: 44px; cursor: pointer; border: none;">
                <?php echo $is_edit ? 'Save Subregion Changes' : 'Insert Subregion'; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageSubregions.php" style="text-decoration: none; padding: 10px 18px; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center; background: #eee; color: #333; border-radius: 4px; box-sizing: border-box;">
                  Cancel / Switch to New
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
function filterSubregionList() {
  const query = document.getElementById('subregionSearch').value.toLowerCase().trim();
  const selectedRegion = document.getElementById('regionFilter').value;
  const rows = document.querySelectorAll('#subregionTable tbody tr');
  rows.forEach(r => {
    const text = r.textContent.toLowerCase();
    const rowRegion = r.getAttribute('data-region-id') || '';
    const matchesQuery = text.includes(query);
    const matchesRegion = (selectedRegion === '' || rowRegion === selectedRegion);
    r.style.display = (matchesQuery && matchesRegion) ? '' : 'none';
  });
}

let cachedRegionOptions = null;
function filterRegionDropdown() {
  const sel = document.getElementById('region_select_field');
  const input = document.getElementById('searchRegionBox');
  if (!sel || !input) return;
  if (!cachedRegionOptions) {
    cachedRegionOptions = Array.from(sel.options);
  }
  const filter = input.value.toLowerCase().trim();
  const currentVal = sel.value;

  sel.innerHTML = '';
  cachedRegionOptions.forEach(opt => {
    if (!filter || opt.text.toLowerCase().includes(filter) || opt.value === '') {
      const cloned = opt.cloneNode(true);
      if (cloned.value === currentVal) {
        cloned.selected = true;
      }
      sel.appendChild(cloned);
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  filterSubregionList();
  const regSel = document.getElementById('region_select_field');
  if (regSel) {
    cachedRegionOptions = Array.from(regSel.options);
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
