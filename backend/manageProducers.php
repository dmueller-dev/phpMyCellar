<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Producer Management Dashboard.
   *
   * Combines adding new wine estates and editing existing producer profiles.
   * Required privilege: 'manage_producers'
   */
  if (!hasPrivilege($conn, 'manage_producers')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Get regions for dropdown
  $regions = getRegions($conn);

  // Determine requested producer for editing
  $requested_producer_id = filter_input(INPUT_GET, 'producer_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_producer', FILTER_VALIDATE_INT);
  $filter_region_id = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT);

  $is_edit = false;
  $producer_id = null;
  $region_id = $filter_region_id ?: '';
  $producer = '';
  $address = '';
  $producer_desc = '';

  if ($requested_producer_id) {
    $existing = getProducerDetails($conn, $requested_producer_id);
    if ($existing) {
      $is_edit = true;
      $producer_id = (int)$existing['producer_id'];
      $region_id = (int)$existing['region_id'];
      $producer = $existing['producer'];
      $address = $existing['address'] ?? '';
      $producer_desc = $existing['producer_desc'] ?? '';
    } else {
      $errors[] = "Requested producer not found.";
    }
  }

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $producer_input = sanitizeInput($_POST['producer'] ?? '');
    $region_id_input = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
    $address_input = sanitizeInput($_POST['address'] ?? '');
    $producer_desc_input = sanitizeInput($_POST['producer_desc'] ?? '');

    $valErrors = validateProducerInput($producer_input, $region_id_input, $address_input, $producer_desc_input);
    if (!empty($valErrors)) {
      $errors = array_merge($errors, $valErrors);
    } else {
      if ($action === 'add') {
        $conn->begin_transaction();
        try {
          if (insertProducer($conn, $producer_input, $region_id_input, $address_input, $producer_desc_input)) {
            $inserted_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Producer '" . htmlspecialchars($producer_input, ENT_QUOTES, 'UTF-8') . "' inserted successfully. <a href='manageWineMasters.php?producer_id=" . $inserted_id . "&region_id=" . $region_id_input . "'>Add a cuv&eacute;e master for this producer &rarr;</a>";
            $producer = '';
            $address = '';
            $producer_desc = '';
            $is_edit = false;
          } else {
            $conn->rollback();
            $errors[] = "Failed to insert producer. Please check that region is valid.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting producer: " . $e->getMessage();
        }
      } elseif ($action === 'edit') {
        $post_producer_id = filter_input(INPUT_POST, 'producer_id', FILTER_VALIDATE_INT);
        $conn->begin_transaction();
        try {
          if (updateProducer($conn, $post_producer_id, $region_id_input, $producer_input, $address_input, $producer_desc_input)) {
            $conn->commit();
            $success_message = "Producer '" . htmlspecialchars($producer_input, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
            $producer_id = $post_producer_id;
            $region_id = $region_id_input;
            $producer = $producer_input;
            $address = $address_input;
            $producer_desc = $producer_desc_input;
            $is_edit = true;
          } else {
            $conn->rollback();
            $errors[] = "Failed to update producer details.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating producer: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch all producers with cuvée statistics
  $producers_stmt = $conn->prepare("
    SELECT p.producer_id, p.producer, p.region_id, p.address, p.producer_desc,
           r.region, r.country,
           COUNT(DISTINCT wm.master_id) AS master_count
    FROM producers p
    LEFT JOIN regions r ON p.region_id = r.region_id
    LEFT JOIN wines_master wm ON p.producer_id = wm.producer_id
    GROUP BY p.producer_id, p.producer, p.region_id, p.address, p.producer_desc, r.region, r.country
    ORDER BY r.country ASC, r.region ASC, p.producer ASC
  ");
  $producer_records = [];
  if ($producers_stmt) {
    $producers_stmt->execute();
    $res = $producers_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $producer_records[] = $row;
    }
    $producers_stmt->close();
  }

  $totalProducers = count($producer_records);
  $distinctRegions = [];
  $withWines = 0;
  foreach ($producer_records as $rec) {
    if (!empty($rec['region_id'])) {
      $distinctRegions[$rec['region_id']] = true;
    }
    if ((int)$rec['master_count'] > 0) {
      $withWines++;
    }
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Producers - Cellar Administration';
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
          &#127970; Manage Wine Producers &amp; Estates
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageVineyards.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &larr; Vineyards
          </a>
          <a href="manageWineMasters.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Cuv&eacute;es &rarr;
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
            <span class="kpi-val"><?php echo number_format($totalProducers); ?></span>
            <span class="kpi-icon">&#127970;</span>
          </div>
          <div class="kpi-label">Wine Producers</div>
          <div class="kpi-sub">Total wine estates &amp; domains</div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format(count($distinctRegions)); ?></span>
            <span class="kpi-icon">&#127758;</span>
          </div>
          <div class="kpi-label">Origin Regions</div>
          <div class="kpi-sub">Distinct terroir zones represented</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($withWines); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Active Producers</div>
          <div class="kpi-sub">Producers with cuv&eacute;e masters</div>
        </div>
      </div>

      <!-- Responsive Dual Grid: Producer Directory & Form -->
      <div class="admin-dual-manage-grid unequal">
        
        <!-- Left: Searchable Producers Directory -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.1rem;">
              Existing Producers (<?php echo count($producer_records); ?>)
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageProducers.php" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none;">+ Add New</a>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
            <select id="regionFilter" style="flex: 1; min-width: 140px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px;" onchange="filterProducerList()">
              <option value="">All Regions</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?php echo (int)$r['region_id']; ?>" <?php echo ($filter_region_id === (int)$r['region_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($r['region'] . ' (' . $r['country'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="producerSearch" class="admin-search-box" style="flex: 2; min-width: 160px; margin-bottom: 0;" placeholder="Search producers..." onkeyup="filterProducerList()">
          </div>

          <div class="admin-table-container" style="max-height: 540px; overflow-y: auto;">
            <table id="producerTable">
              <thead>
                <tr>
                  <th>Producer</th>
                  <th>Region / Country</th>
                  <th style="text-align: center;">Cuv&eacute;es</th>
                  <th style="text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($producer_records)): ?>
                  <tr>
                    <td colspan="4" style="text-align: center; color: #777; padding: 20px;">No producers found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($producer_records as $p): ?>
                    <tr data-region-id="<?php echo (int)$p['region_id']; ?>" style="<?php echo ($is_edit && $producer_id === (int)$p['producer_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                      <td>
                        <strong><?php echo htmlspecialchars($p['producer'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if (!empty($p['address'])): ?>
                          <div style="font-size: 0.78rem; color: #777; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;" title="<?php echo htmlspecialchars($p['address'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($p['address'], ENT_QUOTES, 'UTF-8'); ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-size: 0.88rem;"><?php echo htmlspecialchars($p['region'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div style="font-size: 0.78rem; color: #888;"><?php echo htmlspecialchars($p['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                      </td>
                      <td style="text-align: center;">
                        <?php if ((int)$p['master_count'] > 0): ?>
                          <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; background: #e8f5e9; color: #2e7d32; font-size: 0.82rem; font-weight: bold;">
                            <?php echo (int)$p['master_count']; ?>
                          </span>
                        <?php else: ?>
                          <span style="color: #bbb; font-size: 0.82rem;">0</span>
                        <?php endif; ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <a href="manageProducers.php?producer_id=<?php echo (int)$p['producer_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                          Edit
                        </a>
                        <a href="manageWineMasters.php?producer_id=<?php echo (int)$p['producer_id']; ?>&region_id=<?php echo (int)$p['region_id']; ?>" title="Add cuv&eacute;e master" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                          + Cuv&eacute;e
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right: Add / Edit Producer Form -->
        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
              <?php echo $is_edit ? '&#9998; Edit Producer' : '&#10010; Add New Producer'; ?>
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageProducers.php" style="font-size: 0.85rem; color: #dc3545; text-decoration: none;">Cancel</a>
            <?php endif; ?>
          </div>

          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
              <input type="hidden" name="producer_id" value="<?php echo (int)$producer_id; ?>">
            <?php endif; ?>

            <div style="margin-bottom: 15px;">
              <label for="producer" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Producer / Estate Name: <span style="color: red;">*</span>
              </label>
              <input type="text" name="producer" id="producer" required maxlength="100" style="width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px;" value="<?php echo htmlspecialchars($producer, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Domaine de la Roman&eacute;e-Conti">
            </div>

            <div style="margin-bottom: 15px;">
              <label for="searchRegionBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Region / Country: <span style="color: red;">*</span>
              </label>
              <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <input type="text" id="searchRegionBox" placeholder="🔍 Filter regions..." onkeyup="filterRegionDropdown()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                <select name="region_id" id="form_region_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                  <option value="">-- Select Region --</option>
                  <?php foreach ($regions as $r): ?>
                    <option value="<?php echo (int)$r['region_id']; ?>" <?php echo ((int)$region_id === (int)$r['region_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($r['country'] . ': ' . $r['region'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-bottom: 15px;">
              <label for="address" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Address &amp; Contact:
              </label>
              <textarea name="address" id="address" rows="3" maxlength="1500" style="width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; font-size: 0.9rem;" placeholder="e.g. 1 Place de l'&Eacute;glise, 21700 Vosne-Roman&eacute;e"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
              <label for="producer_desc" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Producer Description / History:
              </label>
              <textarea name="producer_desc" id="producer_desc" rows="6" maxlength="1500" style="width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; font-size: 0.9rem;" placeholder="Background, winemaking philosophy, biodynamic practices, etc."><?php echo htmlspecialchars($producer_desc, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <div style="font-size: 0.78rem; color: #888; margin-top: 4px;">HTML formatting supported (e.g. &lt;p&gt;, &lt;em&gt;).</div>
            </div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn-action" style="flex: 1; padding: 10px 16px; font-size: 0.95rem; cursor: pointer; min-height: 44px;">
                <?php echo $is_edit ? 'Update Producer' : 'Insert Producer'; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageProducers.php" class="btn-action btn-secondary" style="padding: 10px 16px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
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
  function filterProducerList() {
    const searchVal = document.getElementById('producerSearch').value.toLowerCase().trim();
    const regionVal = document.getElementById('regionFilter').value;
    const table = document.getElementById('producerTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.cells.length < 4) continue; // skip empty message row
      
      const rowRegion = row.getAttribute('data-region-id');
      const textContent = row.textContent.toLowerCase();

      const matchesSearch = !searchVal || textContent.includes(searchVal);
      const matchesRegion = !regionVal || (rowRegion === regionVal);

      if (matchesSearch && matchesRegion) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    }
  }

  let cachedRegionOptions = null;
  function filterRegionDropdown() {
    const sel = document.getElementById('form_region_id');
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
    const regSel = document.getElementById('form_region_id');
    if (regSel) {
      cachedRegionOptions = Array.from(regSel.options);
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
