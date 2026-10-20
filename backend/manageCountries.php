<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage countries and geographical terroir classifications.
   *
   * Required privilege: 'manage_countries'
   */
  if (!hasPrivilege($conn, 'manage_countries')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Determine requested country for editing
  $requested_country = sanitizeInput($_GET['country'] ?? $_GET['country_select'] ?? '');
  $is_edit = false;
  $country = '';
  $country_desc = '';

  if ($requested_country !== '') {
    $existing = getCountryDetails($conn, $requested_country);
    if ($existing) {
      $is_edit = true;
      $country = $existing['country'];
      $country_desc = $existing['country_desc'] ?? '';
    } else {
      $errors[] = "Requested country not found.";
    }
  }

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $country_input = sanitizeInput($_POST['country'] ?? '');
    $country_desc_input = sanitizeInput($_POST['country_desc'] ?? '');

    $valErrors = validateCountryInput($country_input, $country_desc_input);
    if (!empty($valErrors)) {
      $errors = array_merge($errors, $valErrors);
    } else {
      if ($action === 'add') {
        $conn->begin_transaction();
        try {
          if (insertCountry($conn, $country_input, $country_desc_input)) {
            $conn->commit();
            $success_message = "Country '" . htmlspecialchars($country_input, ENT_QUOTES, 'UTF-8') . "' inserted successfully. <a href='manageRegions.php?country=" . urlencode($country_input) . "'>Add a region for this country &rarr;</a>";
            $country = '';
            $country_desc = '';
            $is_edit = false;
          } else {
            $conn->rollback();
            $errors[] = "Failed to insert country. It may already exist in the database.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting country: " . $e->getMessage();
        }
      } elseif ($action === 'edit') {
        $original_country = sanitizeInput($_POST['original_country'] ?? $country_input);
        $conn->begin_transaction();
        try {
          if (updateCountry($conn, $original_country, $country_desc_input)) {
            $conn->commit();
            $success_message = "Country '" . htmlspecialchars($original_country, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
            $country = $original_country;
            $country_desc = $country_desc_input;
            $is_edit = true;
          } else {
            $conn->rollback();
            $errors[] = "Failed to update country details.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating country: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch all countries with associated region and wine master statistics
  $countries_stmt = $conn->prepare("
    SELECT c.country, c.country_desc,
           COUNT(DISTINCT r.region_id) AS region_count,
           COUNT(DISTINCT wm.master_id) AS master_count
    FROM countries c
    LEFT JOIN regions r ON c.country = r.country
    LEFT JOIN wines_master wm ON r.region_id = wm.region_id
    GROUP BY c.country, c.country_desc
    ORDER BY c.country ASC
  ");
  $country_records = [];
  if ($countries_stmt) {
    $countries_stmt->execute();
    $res = $countries_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $country_records[] = $row;
    }
    $countries_stmt->close();
  }

  $totalCountries = count($country_records);
  $countriesWithRegions = 0;
  $countriesWithWines = 0;
  foreach ($country_records as $rec) {
    if ((int)$rec['region_count'] > 0) $countriesWithRegions++;
    if ((int)$rec['master_count'] > 0) $countriesWithWines++;
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Countries - Cellar Administration';
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
          &#127758; Manage Countries
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageRegions.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Regions &rarr;
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
            <span class="kpi-val"><?php echo number_format($totalCountries); ?></span>
            <span class="kpi-icon">&#127758;</span>
          </div>
          <div class="kpi-label">Countries Recorded</div>
          <div class="kpi-sub">Total geographical entries</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($countriesWithRegions); ?></span>
            <span class="kpi-icon">&#127966;&#65039;</span>
          </div>
          <div class="kpi-label">With Wine Regions</div>
          <div class="kpi-sub">Subdivided wine origins</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($countriesWithWines); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Active in Cellar Catalogue</div>
          <div class="kpi-sub">Represented in wine masters</div>
        </div>
      </div>

      <!-- Mobile Segmented View Switcher -->
      <div class="admin-mobile-pane-switch">
        <button type="button" id="tabBtnList" class="admin-mobile-pane-btn <?php echo !$is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('list')">
          📋 Countries (<?php echo count($country_records); ?>)
        </button>
        <button type="button" id="tabBtnForm" class="admin-mobile-pane-btn <?php echo $is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('form')">
          <?php echo $is_edit ? '✏️ Edit Country' : '➕ Add Country'; ?>
        </button>
      </div>

      <!-- Responsive Dual Grid: Country Directory & Form -->
      <div class="admin-dual-manage-grid unequal">
        
        <!-- Left: Searchable Countries Directory -->
        <div id="adminDualListPane" class="admin-dual-pane <?php echo $is_edit ? 'mobile-hidden' : ''; ?>">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.1rem;">
              Existing Countries (<?php echo count($country_records); ?>)
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageCountries.php" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none;">+ Add New</a>
            <?php endif; ?>
          </div>

          <input type="text" id="countrySearch" class="admin-search-box" placeholder="Search countries..." onkeyup="filterCountryList()">

          <div class="admin-table-container" style="max-height: 520px; overflow-y: auto;">
            <table id="countryTable">
              <thead>
                <tr>
                  <th>Country</th>
                  <th style="text-align: center;">Regions</th>
                  <th style="text-align: center;">Wines</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($country_records)): ?>
                  <tr><td colspan="4" style="text-align: center; color: #777;">No countries recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($country_records as $c): ?>
                    <tr style="<?php echo ($is_edit && $country === $c['country']) ? 'background: #fff8e7; font-weight: 500;' : ''; ?>">
                      <td>
                        <strong><?php echo htmlspecialchars($c['country'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      </td>
                      <td data-label="Regions" style="text-align: center;">
                        <a href="manageRegions.php?country=<?php echo urlencode($c['country']); ?>" title="View regions" style="text-decoration: none; color: #555;">
                          <?php echo (int)$c['region_count']; ?>
                        </a>
                      </td>
                      <td data-label="Wines" style="text-align: center; color: #666;">
                        <?php echo (int)$c['master_count']; ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <a href="manageCountries.php?country=<?php echo urlencode($c['country']); ?>" class="terroir-btn terroir-btn-edit" style="padding: 4px 10px; font-size: 0.8rem;" title="Edit country">
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
            <?php echo $is_edit ? '✏️ Edit Country: ' . htmlspecialchars($country, ENT_QUOTES, 'UTF-8') : '➕ Add New Country'; ?>
          </h3>

          <form method="POST" action="manageCountries.php<?php echo $is_edit ? '?country=' . urlencode($country) : ''; ?>" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
              <input type="hidden" name="original_country" value="<?php echo htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>

            <div style="margin-bottom: 14px;">
              <label for="country_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Country Name: <span style="color: red;">*</span>
              </label>
              <input type="text" id="country_field" name="country" required maxlength="100"
                     value="<?php echo htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?>"
                     <?php echo $is_edit ? 'readonly style="background-color: #f6f6f6; cursor: not-allowed;"' : ''; ?>
                     style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; min-height: 44px;"
                     placeholder="e.g. France, Italy, Spain">
              <?php if ($is_edit): ?>
                <small style="color: #666;">Country names serve as unique terroir keys and cannot be renamed directly.</small>
              <?php endif; ?>
            </div>

            <div style="margin-bottom: 18px;">
              <label for="country_desc_field" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;">
                Description / Editorial Notes:
              </label>
              <textarea id="country_desc_field" name="country_desc" rows="10" maxlength="1500"
                        style="width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; font-family: inherit;"
                        placeholder="<p>Overview of the country's winemaking traditions, key climatic influences, and major viticultural regions...</p>"><?php echo htmlspecialchars($country_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <small style="color: #777;">Optional HTML permitted (e.g. &lt;p&gt;, &lt;em&gt;). Maximum 1,500 characters.</small>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <button type="submit" class="btn-action" style="padding: 10px 20px; font-size: 0.95rem; min-height: 44px; cursor: pointer; border: none;">
                <?php echo $is_edit ? 'Save Country Changes' : 'Insert Country'; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageCountries.php" style="text-decoration: none; padding: 10px 18px; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center; background: #eee; color: #333; border-radius: 4px; box-sizing: border-box;">
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
function filterCountryList() {
  const query = document.getElementById('countrySearch').value.toLowerCase().trim();
  const rows = document.querySelectorAll('#countryTable tbody tr');
  rows.forEach(r => {
    const text = r.textContent.toLowerCase();
    r.style.display = text.includes(query) ? '' : 'none';
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
