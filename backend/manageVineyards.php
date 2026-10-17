<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage single vineyards, climats, and lieux-dits.
   *
   * Required privilege: 'manage_vineyards'
   */
  if (!hasPrivilege($conn, 'manage_vineyards')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Get regions and appellations for dropdowns
  $regions = getRegions($conn);
  $appellations = getAppellations($conn);

  // Determine requested vineyard for editing
  $requested_vineyard_id = filter_input(INPUT_GET, 'vineyard_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_vineyard', FILTER_VALIDATE_INT);
  $filter_region_id = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT);
  $filter_appellation_id = filter_input(INPUT_GET, 'appellation_id', FILTER_VALIDATE_INT);

  $is_edit = false;
  $vineyard_id = null;
  $region_id = $filter_region_id ?: '';
  $appellation_id = $filter_appellation_id ?: '';
  $vineyard = '';
  $vineyard_desc = '';

  if ($requested_vineyard_id) {
    $existing = getVineyardDetails($conn, $requested_vineyard_id);
    if ($existing) {
      $is_edit = true;
      $vineyard_id = (int)$existing['vineyard_id'];
      $region_id = (int)$existing['region_id'];
      $appellation_id = $existing['appellation_id'] ? (int)$existing['appellation_id'] : '';
      $vineyard = $existing['vineyard'];
      $vineyard_desc = $existing['vineyard_desc'] ?? '';
    } else {
      $errors[] = "Requested vineyard not found.";
    }
  }

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $vineyard_input = sanitizeInput($_POST['vineyard'] ?? '');
    $region_id_input = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
    $appellation_id_input = filter_input(INPUT_POST, 'appellation_id', FILTER_VALIDATE_INT) ?: null;
    $vineyard_desc_input = sanitizeInput($_POST['vineyard_desc'] ?? '');

    $valErrors = validateVineyardInput($vineyard_input, $appellation_id_input, $region_id_input, $vineyard_desc_input);
    if (!empty($valErrors)) {
      $errors = array_merge($errors, $valErrors);
    } else {
      if ($action === 'add') {
        $conn->begin_transaction();
        try {
          if (insertVineyard($conn, $vineyard_input, $region_id_input, $appellation_id_input, $vineyard_desc_input)) {
            $inserted_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Vineyard '" . htmlspecialchars($vineyard_input, ENT_QUOTES, 'UTF-8') . "' inserted successfully. <a href='manageWineMasters.php?vineyard_id=" . $inserted_id . "&region_id=" . $region_id_input . ($appellation_id_input ? "&appellation_id=" . $appellation_id_input : "") . "'>Add a wine master for this vineyard &rarr;</a>";
            $vineyard = '';
            $vineyard_desc = '';
            $is_edit = false;
          } else {
            $conn->rollback();
            $errors[] = "Failed to insert vineyard. A vineyard with that name may already exist in this region/appellation.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting vineyard: " . $e->getMessage();
        }
      } elseif ($action === 'edit') {
        $post_vineyard_id = filter_input(INPUT_POST, 'vineyard_id', FILTER_VALIDATE_INT);
        $conn->begin_transaction();
        try {
          if (updateVineyard($conn, $post_vineyard_id, $appellation_id_input, $region_id_input, $vineyard_input, $vineyard_desc_input)) {
            $conn->commit();
            $success_message = "Vineyard '" . htmlspecialchars($vineyard_input, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
            $vineyard_id = $post_vineyard_id;
            $region_id = $region_id_input;
            $appellation_id = $appellation_id_input ?: '';
            $vineyard = $vineyard_input;
            $vineyard_desc = $vineyard_desc_input;
            $is_edit = true;
          } else {
            $conn->rollback();
            $errors[] = "Failed to update vineyard details.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating vineyard: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch all vineyards with statistics
  $vineyards_stmt = $conn->prepare("
    SELECT v.vineyard_id, v.vineyard, v.region_id, v.appellation_id, v.vineyard_desc,
           r.region, r.country, a.appellation,
           COUNT(DISTINCT wm.master_id) AS master_count
    FROM vineyards v
    JOIN regions r ON v.region_id = r.region_id
    LEFT JOIN appellations a ON v.appellation_id = a.appellation_id
    LEFT JOIN x_wine_master wm ON v.vineyard_id = wm.vineyard_id
    GROUP BY v.vineyard_id, v.vineyard, v.region_id, v.appellation_id, v.vineyard_desc, r.region, r.country, a.appellation
    ORDER BY r.country ASC, r.region ASC, v.vineyard ASC
  ");
  $vineyard_records = [];
  if ($vineyards_stmt) {
    $vineyards_stmt->execute();
    $res = $vineyards_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $vineyard_records[] = $row;
    }
    $vineyards_stmt->close();
  }

  $totalVineyards = count($vineyard_records);
  $withAppellations = 0;
  $withWines = 0;
  foreach ($vineyard_records as $rec) {
    if (!empty($rec['appellation_id'])) $withAppellations++;
    if ((int)$rec['master_count'] > 0) $withWines++;
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Vineyards - Cellar Administration';
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
          &#127793; Manage Single Vineyards &amp; Climats
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageAppellations.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &larr; Appellations
          </a>
          <a href="manageProducers.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Producers &rarr;
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
            <span class="kpi-val"><?php echo number_format($totalVineyards); ?></span>
            <span class="kpi-icon">&#127793;</span>
          </div>
          <div class="kpi-label">Single Vineyards</div>
          <div class="kpi-sub">Total parcels and plots recorded</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($withAppellations); ?></span>
            <span class="kpi-icon">&#127991;&#65039;</span>
          </div>
          <div class="kpi-label">Within Appellations</div>
          <div class="kpi-sub">Assigned to certified cru zones</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($withWines); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Active in Cellar</div>
          <div class="kpi-sub">Single-vineyard cuv&eacute;es</div>
        </div>
      </div>

      <!-- Responsive Dual Grid: Vineyard Directory & Form -->
      <div class="admin-dual-manage-grid unequal">
        
        <!-- Left: Searchable Vineyards Directory -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.1rem;">
              Existing Vineyards (<?php echo count($vineyard_records); ?>)
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageVineyards.php" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none;">+ Add New</a>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
            <select id="regionFilter" style="flex: 1; min-width: 140px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px;" onchange="filterVineyardList()">
              <option value="">All Regions</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?php echo (int)$r['region_id']; ?>" <?php echo ($filter_region_id === (int)$r['region_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($r['region'] . ' (' . $r['country'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="vineyardSearch" class="admin-search-box" style="flex: 2; min-width: 160px; margin-bottom: 0;" placeholder="Search vineyards..." onkeyup="filterVineyardList()">
          </div>

          <div class="admin-table-container" style="max-height: 520px; overflow-y: auto;">
            <table id="vineyardTable">
              <thead>
                <tr>
                  <th>Vineyard / Region</th>
                  <th>Appellation</th>
                  <th style="text-align: center;">Wines</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($vineyard_records)): ?>
                  <tr><td colspan="4" style="text-align: center; color: #777;">No vineyards recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($vineyard_records as $v): ?>
                    <tr data-region-id="<?php echo (int)$v['region_id']; ?>"
                        style="<?php echo ($is_edit && $vineyard_id === (int)$v['vineyard_id']) ? 'background: #fff8e7; font-weight: 500;' : ''; ?>">
                      <td>
                        <strong><?php echo htmlspecialchars($v['vineyard'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($v['region'] . ', ' . $v['country'], ENT_QUOTES, 'UTF-8'); ?></small>
                      </td>
                      <td style="color: #555; font-size: 0.88rem;">
                        <?php echo !empty($v['appellation']) ? htmlspecialchars($v['appellation'], ENT_QUOTES, 'UTF-8') : '<em style="color:#999;">None</em>'; ?>
                      </td>
                      <td style="text-align: center; color: #666;">
                        <?php echo (int)$v['master_count']; ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <a href="manageVineyards.php?vineyard_id=<?php echo (int)$v['vineyard_id']; ?>" class="terroir-btn terroir-btn-edit" style="padding: 4px 10px; font-size: 0.8rem;" title="Edit vineyard">
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
        <div class="admin-system-card">
          <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick);">
            <?php echo $is_edit ? '✏️ Edit Vineyard: ' . htmlspecialchars($vineyard, ENT_QUOTES, 'UTF-8') : '➕ Add New Vineyard'; ?>
          </h3>

          <form method="POST" action="manageVineyards.php<?php echo $is_edit ? '?vineyard_id=' . $vineyard_id : ''; ?>" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
              <input type="hidden" name="vineyard_id" value="<?php echo (int)$vineyard_id; ?>">
            <?php endif; ?>

            <div style="margin-bottom: 14px;">
              <label for="searchRegionBox" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Wine Region: <span style="color: red;">*</span>
              </label>
              <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <input type="text" id="searchRegionBox" placeholder="🔍 Filter regions..." onkeyup="filterRegionDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                <select id="region_select_field" name="region_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.95rem;" onchange="filterAppellationsByRegion()">
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
              <label for="searchAppellationBox" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Appellation (Optional):
              </label>
              <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <input type="text" id="searchAppellationBox" placeholder="🔍 Filter appellations..." onkeyup="filterAppellationDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                <select id="appellation_select_field" name="appellation_id" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.95rem;">
                  <option value="">None / Regional designation</option>
                  <?php foreach ($appellations as $a): ?>
                    <option value="<?php echo (int)$a['appellation_id']; ?>"
                            data-region-id="<?php echo (int)$a['region_id']; ?>"
                            <?php echo ((int)$appellation_id === (int)$a['appellation_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($a['appellation'] . ' (' . $a['region'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-bottom: 14px;">
              <label for="vineyard_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Vineyard Name: <span style="color: red;">*</span>
              </label>
              <input type="text" id="vineyard_field" name="vineyard" required maxlength="100"
                     value="<?php echo htmlspecialchars($vineyard, ENT_QUOTES, 'UTF-8'); ?>"
                     style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; min-height: 44px;"
                     placeholder="e.g. Clos Saint-Jacques, To Kalon, Cannubi">
            </div>

            <div style="margin-bottom: 18px;">
              <label for="vineyard_desc_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Description / Climat Characteristics:
              </label>
              <textarea id="vineyard_desc_field" name="vineyard_desc" rows="10" maxlength="1500"
                        style="width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; font-family: inherit;"
                        placeholder="<p>Aspect, slope inclination, soil strata, sun exposure, and historical significance...</p>"><?php echo htmlspecialchars($vineyard_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <small style="color: #777;">Optional HTML permitted (e.g. &lt;p&gt;, &lt;em&gt;). Maximum 1,500 characters.</small>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <button type="submit" class="btn-action" style="padding: 10px 20px; font-size: 0.95rem; min-height: 44px; cursor: pointer; border: none;">
                <?php echo $is_edit ? 'Save Vineyard Changes' : 'Insert Vineyard'; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageVineyards.php" style="text-decoration: none; padding: 10px 18px; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center; background: #eee; color: #333; border-radius: 4px; box-sizing: border-box;">
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
function filterVineyardList() {
  const query = document.getElementById('vineyardSearch').value.toLowerCase().trim();
  const selectedRegion = document.getElementById('regionFilter').value;
  const rows = document.querySelectorAll('#vineyardTable tbody tr');
  rows.forEach(r => {
    const text = r.textContent.toLowerCase();
    const rowRegion = r.getAttribute('data-region-id') || '';
    const matchesQuery = text.includes(query);
    const matchesRegion = (selectedRegion === '' || rowRegion === selectedRegion);
    r.style.display = (matchesQuery && matchesRegion) ? '' : 'none';
  });
}

let cachedRegionOptions = null;
let cachedAppellationOptions = null;

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

function filterAppellationsByRegion() {
  const regionId = document.getElementById('region_select_field') ? document.getElementById('region_select_field').value : '';
  const appSelect = document.getElementById('appellation_select_field');
  const appSearch = document.getElementById('searchAppellationBox');
  if (!appSelect) return;
  if (!cachedAppellationOptions) {
    cachedAppellationOptions = Array.from(appSelect.options);
  }

  const filter = appSearch ? appSearch.value.toLowerCase().trim() : '';
  const currentVal = appSelect.value;
  let hasSelected = false;

  appSelect.innerHTML = '';
  cachedAppellationOptions.forEach(opt => {
    if (opt.value === '') {
      const cloned = opt.cloneNode(true);
      if (cloned.value === currentVal) {
        cloned.selected = true;
        hasSelected = true;
      }
      appSelect.appendChild(cloned);
      return;
    }
    const rId = opt.getAttribute('data-region-id');
    const regionMatches = (regionId === '' || rId === regionId);
    const searchMatches = (!filter || opt.text.toLowerCase().includes(filter));

    if (regionMatches && searchMatches) {
      const cloned = opt.cloneNode(true);
      if (cloned.value === currentVal) {
        cloned.selected = true;
        hasSelected = true;
      }
      appSelect.appendChild(cloned);
    }
  });

  if (!hasSelected && currentVal !== '') {
    appSelect.value = '';
  }
}

function filterAppellationDropdown() {
  filterAppellationsByRegion();
}

document.addEventListener('DOMContentLoaded', () => {
  const regSel = document.getElementById('region_select_field');
  if (regSel) {
    cachedRegionOptions = Array.from(regSel.options);
  }
  const appSel = document.getElementById('appellation_select_field');
  if (appSel) {
    cachedAppellationOptions = Array.from(appSel.options);
  }

  filterVineyardList();
  filterAppellationsByRegion();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
