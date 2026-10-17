<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage wine regions and geographical terroir classifications.
   *
   * Required privilege: 'manage_regions'
   */
  if (!hasPrivilege($conn, 'manage_regions')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Get available countries for dropdowns
  $countries = getCountries($conn);

  // Determine requested region for editing or filtering
  $requested_region_id = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_region', FILTER_VALIDATE_INT);
  $filter_country = sanitizeInput($_GET['country'] ?? '');

  $is_edit = false;
  $region_id = null;
  $country = $filter_country;
  $region = '';
  $region_desc = '';

  if ($requested_region_id) {
    $existing = getRegionDetails($conn, $requested_region_id);
    if ($existing) {
      $is_edit = true;
      $region_id = (int)$existing['region_id'];
      $country = $existing['country'];
      $region = $existing['region'];
      $region_desc = $existing['region_desc'] ?? '';
    } else {
      $errors[] = "Requested wine region not found.";
    }
  }

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $country_input = sanitizeInput($_POST['country'] ?? '');
    $region_input = sanitizeInput($_POST['region'] ?? '');
    $region_desc_input = sanitizeInput($_POST['region_desc'] ?? '');

    $valErrors = validateRegionInput($region_input, $country_input, $region_desc_input);
    if (!empty($valErrors)) {
      $errors = array_merge($errors, $valErrors);
    } else {
      if ($action === 'add') {
        $conn->begin_transaction();
        try {
          if (insertRegion($conn, $country_input, $region_input, $region_desc_input)) {
            $inserted_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Region '" . htmlspecialchars($region_input, ENT_QUOTES, 'UTF-8') . "' inserted successfully. <a href='manageSubregions.php?region_id=" . $inserted_id . "'>Add a subregion for this region &rarr;</a>";
            $region = '';
            $region_desc = '';
            $is_edit = false;
          } else {
            $conn->rollback();
            $errors[] = "Failed to insert region. A region with that name may already exist in this country.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting region: " . $e->getMessage();
        }
      } elseif ($action === 'edit') {
        $post_region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
        $conn->begin_transaction();
        try {
          if (updateRegion($conn, $post_region_id, $country_input, $region_input, $region_desc_input)) {
            $conn->commit();
            $success_message = "Region '" . htmlspecialchars($region_input, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
            $region_id = $post_region_id;
            $country = $country_input;
            $region = $region_input;
            $region_desc = $region_desc_input;
            $is_edit = true;
          } else {
            $conn->rollback();
            $errors[] = "Failed to update region details. Invalid region ID or database constraint violation.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating region: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch all regions with statistics
  $regions_stmt = $conn->prepare("
    SELECT r.region_id, r.region, r.country, r.region_desc,
           COUNT(DISTINCT sr.subregion_id) AS subregion_count,
           COUNT(DISTINCT wm.master_id) AS master_count
    FROM regions r
    LEFT JOIN subregions sr ON r.region_id = sr.region_id
    LEFT JOIN x_wine_master wm ON r.region_id = wm.region_id
    GROUP BY r.region_id, r.region, r.country, r.region_desc
    ORDER BY r.country ASC, r.region ASC
  ");
  $region_records = [];
  if ($regions_stmt) {
    $regions_stmt->execute();
    $res = $regions_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $region_records[] = $row;
    }
    $regions_stmt->close();
  }

  $totalRegions = count($region_records);
  $subdividedRegions = 0;
  $regionsWithWines = 0;
  foreach ($region_records as $rec) {
    if ((int)$rec['subregion_count'] > 0) $subdividedRegions++;
    if ((int)$rec['master_count'] > 0) $regionsWithWines++;
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Wine Regions - Cellar Administration';
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
          &#127966;&#65039; Manage Wine Regions
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageCountries.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &larr; Countries
          </a>
          <a href="manageSubregions.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Subregions &rarr;
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

      <!-- High-level Terroir Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalRegions); ?></span>
            <span class="kpi-icon">&#127966;&#65039;</span>
          </div>
          <div class="kpi-label">Wine Regions</div>
          <div class="kpi-sub">Total viticultural areas</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($subdividedRegions); ?></span>
            <span class="kpi-icon">&#128506;&#65039;</span>
          </div>
          <div class="kpi-label">Subdivided Areas</div>
          <div class="kpi-sub">With specific subregions</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($regionsWithWines); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Active in Cellar</div>
          <div class="kpi-sub">Represented in wine masters</div>
        </div>
      </div>

      <!-- Responsive Dual Grid: Region Directory & Form -->
      <div class="admin-dual-manage-grid unequal">
        
        <!-- Left: Searchable Regions Directory -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.1rem;">
              Existing Regions (<?php echo count($region_records); ?>)
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageRegions.php" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none;">+ Add New</a>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
            <select id="countryFilter" style="flex: 1; min-width: 140px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px;" onchange="filterRegionList()">
              <option value="">All Countries</option>
              <?php foreach ($countries as $c): ?>
                <option value="<?php echo htmlspecialchars($c['country'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filter_country === $c['country']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($c['country'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="regionSearch" class="admin-search-box" style="flex: 2; min-width: 160px; margin-bottom: 0;" placeholder="Search regions..." onkeyup="filterRegionList()">
          </div>

          <div class="admin-table-container" style="max-height: 520px; overflow-y: auto;">
            <table id="regionTable">
              <thead>
                <tr>
                  <th>Region / Country</th>
                  <th style="text-align: center;">Subregions</th>
                  <th style="text-align: center;">Wines</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($region_records)): ?>
                  <tr><td colspan="4" style="text-align: center; color: #777;">No regions recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($region_records as $r): ?>
                    <tr data-country="<?php echo htmlspecialchars(strtolower($r['country']), ENT_QUOTES, 'UTF-8'); ?>"
                        style="<?php echo ($is_edit && $region_id === (int)$r['region_id']) ? 'background: #fff8e7; font-weight: 500;' : ''; ?>">
                      <td>
                        <strong><?php echo htmlspecialchars($r['region'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($r['country'], ENT_QUOTES, 'UTF-8'); ?></small>
                      </td>
                      <td style="text-align: center;">
                        <a href="manageSubregions.php?region_id=<?php echo (int)$r['region_id']; ?>" title="View subregions" style="text-decoration: none; color: #555;">
                          <?php echo (int)$r['subregion_count']; ?>
                        </a>
                      </td>
                      <td style="text-align: center; color: #666;">
                        <?php echo (int)$r['master_count']; ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <a href="manageRegions.php?region_id=<?php echo (int)$r['region_id']; ?>" class="terroir-btn terroir-btn-edit" style="padding: 4px 10px; font-size: 0.8rem;" title="Edit region">
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
            <?php echo $is_edit ? '✏️ Edit Region: ' . htmlspecialchars($region, ENT_QUOTES, 'UTF-8') : '➕ Add New Region'; ?>
          </h3>

          <form method="POST" action="manageRegions.php<?php echo $is_edit ? '?region_id=' . $region_id : ''; ?>" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
              <input type="hidden" name="region_id" value="<?php echo (int)$region_id; ?>">
            <?php endif; ?>

            <div style="margin-bottom: 14px;">
              <label for="country_select_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Country: <span style="color: red;">*</span>
              </label>
              <select id="country_select_field" name="country" required style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; min-height: 44px;">
                <option value="">Select country...</option>
                <?php foreach ($countries as $c): ?>
                  <option value="<?php echo htmlspecialchars($c['country'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($country === $c['country']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['country'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="margin-bottom: 14px;">
              <label for="region_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Region Name: <span style="color: red;">*</span>
              </label>
              <input type="text" id="region_field" name="region" required maxlength="100"
                     value="<?php echo htmlspecialchars($region, ENT_QUOTES, 'UTF-8'); ?>"
                     style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; min-height: 44px;"
                     placeholder="e.g. Bordeaux, Rioja, Tuscany, Barossa Valley">
            </div>

            <div style="margin-bottom: 18px;">
              <label for="region_desc_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Description / Editorial Notes:
              </label>
              <textarea id="region_desc_field" name="region_desc" rows="10" maxlength="1500"
                        style="width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; font-family: inherit;"
                        placeholder="<p>Geological characteristics, meso-climates, soil compositions, and signature grape varieties...</p>"><?php echo htmlspecialchars($region_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <small style="color: #777;">Optional HTML permitted (e.g. &lt;p&gt;, &lt;em&gt;). Maximum 1,500 characters.</small>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <button type="submit" class="btn-action" style="padding: 10px 20px; font-size: 0.95rem; min-height: 44px; cursor: pointer; border: none;">
                <?php echo $is_edit ? 'Save Region Changes' : 'Insert Region'; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageRegions.php" style="text-decoration: none; padding: 10px 18px; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center; background: #eee; color: #333; border-radius: 4px; box-sizing: border-box;">
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
function filterRegionList() {
  const query = document.getElementById('regionSearch').value.toLowerCase().trim();
  const selectedCountry = document.getElementById('countryFilter').value.toLowerCase().trim();
  const rows = document.querySelectorAll('#regionTable tbody tr');
  rows.forEach(r => {
    const text = r.textContent.toLowerCase();
    const rowCountry = r.getAttribute('data-country') || '';
    const matchesQuery = text.includes(query);
    const matchesCountry = (selectedCountry === '' || rowCountry === selectedCountry);
    r.style.display = (matchesQuery && matchesCountry) ? '' : 'none';
  });
}
document.addEventListener('DOMContentLoaded', filterRegionList);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
