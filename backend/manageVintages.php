<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage harvest vintage years and regional vintage character reports.
   *
   * Required privilege: 'manage_vintages' (or 'edit_wine')
   */
  if (!hasPrivilege($conn, 'manage_vintages') && !hasPrivilege($conn, 'edit_wine')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Active tab state
  $activeTab = $_GET['tab'] ?? 'vintages';
  if (!in_array($activeTab, ['vintages', 'reports'], true)) {
    $activeTab = 'vintages';
  }

  // Selected report for editing
  $editReportVintage = filter_input(INPUT_GET, 'edit_v', FILTER_VALIDATE_INT);
  $editReportRegion = filter_input(INPUT_GET, 'edit_r', FILTER_VALIDATE_INT);
  $editingReport = null;
  if ($editReportVintage && $editReportRegion) {
    $reports = getRegionalVintageReports($conn, $editReportVintage, $editReportRegion);
    if (!empty($reports)) {
      $editingReport = $reports[0];
      $activeTab = 'reports';
    } else {
      $errors[] = "Regional vintage report not found.";
    }
  }

  // Filter params for reports tab
  $filterVintage = filter_input(INPUT_GET, 'filter_vintage', FILTER_VALIDATE_INT) ?: null;
  $filterRegion = filter_input(INPUT_GET, 'filter_region', FILTER_VALIDATE_INT) ?: null;

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    // Action: Add Vintage Year
    if ($action === 'add_vintage') {
      $activeTab = 'vintages';
      $vintage = filter_input(INPUT_POST, 'vintage', FILTER_VALIDATE_INT);
      try {
        if ($vintage && insertVintageYear($conn, $vintage)) {
          $success_message = "Vintage year " . (int)$vintage . " added to catalog successfully.";
        } else {
          $errors[] = "Please specify a valid harvest vintage year.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Delete Vintage Year
    elseif ($action === 'delete_vintage') {
      $activeTab = 'vintages';
      $vintage = filter_input(INPUT_POST, 'vintage', FILTER_VALIDATE_INT);
      try {
        if ($vintage && deleteVintageYear($conn, $vintage)) {
          $success_message = "Vintage year " . (int)$vintage . " removed successfully.";
        } else {
          $errors[] = "Invalid vintage year.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Save Regional Vintage Report
    elseif ($action === 'save_report') {
      $activeTab = 'reports';
      $vintage = filter_input(INPUT_POST, 'vintage', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $vintage_desc = sanitizeInput($_POST['vintage_desc'] ?? '');

      try {
        if (!$vintage || !$region_id) {
          $errors[] = "Please select both a vintage year and a wine region.";
        } else {
          if (saveRegionalVintageReport($conn, $vintage, $region_id, $vintage_desc)) {
            $success_message = "Regional vintage character report saved successfully.";
            $editingReport = null;
          }
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Delete Regional Vintage Report
    elseif ($action === 'delete_report') {
      $activeTab = 'reports';
      $vintage = filter_input(INPUT_POST, 'vintage', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);

      try {
        if ($vintage && $region_id && deleteRegionalVintageReport($conn, $vintage, $region_id)) {
          $success_message = "Regional vintage report removed successfully.";
        } else {
          $errors[] = "Invalid vintage report parameters.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
  }

  // Fetch data
  $vintages = getVintagesWithStats($conn);
  $reports = getRegionalVintageReports($conn, $filterVintage, $filterRegion);
  $regions = getRegions($conn);

  $totalVintages = count($vintages);
  $totalReports = count(getRegionalVintageReports($conn));

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Vintages & Reports - Catalog Administration';
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
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #f0e6e6; padding-bottom: 12px; margin-bottom: 16px;">
        <h2 style="margin: 0; color: var(--secondary-accent, firebrick);">
          &#127815; Harvest Vintages &amp; Regional Reports
        </h2>
        <div>
          <a href="/vintages.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px; margin-right: 8px;">
            View Public Reports &rarr;
          </a>
          <?php if (hasPrivilege($conn, 'add_wine')): ?>
            <a href="addWine.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
              + Add Wine Vintage
            </a>
          <?php endif; ?>
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

      <!-- KPI Summary -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalVintages); ?></span>
            <span class="kpi-icon">&#128197;</span>
          </div>
          <div class="kpi-label">Harvest Vintages</div>
          <div class="kpi-sub">
            <span>Configured harvest years</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-purple" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalReports); ?></span>
            <span class="kpi-icon">&#128221;</span>
          </div>
          <div class="kpi-label">Regional Reports</div>
          <div class="kpi-sub">
            <span>Vintage character evaluations</span>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div style="display: flex; gap: 10px; border-bottom: 1px solid #ddd; margin-bottom: 20px;">
        <a href="?tab=vintages" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'vintages' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          &#128197; Harvest Vintage Years (<?php echo $totalVintages; ?>)
        </a>
        <a href="?tab=reports" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'reports' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          &#127757; Regional Character Reports (<?php echo $totalReports; ?>)
        </a>
      </div>

      <!-- TAB 1: HARVEST VINTAGES -->
      <?php if ($activeTab === 'vintages'): ?>
        <div class="admin-storage-manage-grid">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
              <h3 style="margin: 0; color: #333;">Configured Harvest Vintages</h3>
              <input type="text" id="vintageFilterInput" placeholder="Filter year..." 
                     oninput="filterVintageTable()" 
                     style="padding: 6px 10px; font-size: 0.88rem; width: 140px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="overflow-x: auto;">
              <table id="vintagesTable" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Vintage Year</th>
                    <th style="padding: 8px 10px; text-align: right;">Wines Catalogued</th>
                    <th style="padding: 8px 10px; text-align: right;">Tasting Notes</th>
                    <th style="padding: 8px 10px; text-align: right;">Regional Reports</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($vintages as $vRow): ?>
                    <?php
                      $vYear = (int)$vRow['vintage'];
                      $wCount = (int)$vRow['total_wines'];
                      $tnCount = (int)$vRow['total_notes'];
                      $rCount = (int)$vRow['regional_reports_count'];
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                      <td style="padding: 8px 10px; font-weight: bold; font-size: 1rem; color: #222;">
                        <a href="/vintages.php?vintage=<?php echo $vYear; ?>" style="text-decoration: none; color: #222;" title="View public vintage report">
                          <?php echo $vYear; ?> &rarr;
                        </a>
                      </td>
                      <td style="padding: 8px 10px; text-align: right;">
                        <?php echo $wCount; ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right;">
                        <?php echo $tnCount; ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right;">
                        <?php if ($rCount > 0): ?>
                          <a href="manageVintages.php?tab=reports&filter_vintage=<?php echo $vYear; ?>" style="text-decoration: none; color: #1a73e8; font-weight: bold;">
                            <?php echo $rCount; ?> report<?php echo ($rCount === 1 ? '' : 's'); ?>
                          </a>
                        <?php else: ?>
                          <span style="color: #aaa;">&mdash;</span>
                        <?php endif; ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                        <a href="manageVintages.php?tab=reports&prefill_v=<?php echo $vYear; ?>" style="color: #2e7d32; text-decoration: none; margin-right: 8px; font-size: 0.85rem;" title="Write regional character report">
                          + Report
                        </a>
                        <?php if ($wCount === 0 && $rCount === 0): ?>
                          <form action="manageVintages.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete vintage <?php echo $vYear; ?>?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_vintage">
                            <input type="hidden" name="vintage" value="<?php echo $vYear; ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Linked to wines or reports">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Add Vintage Form -->
          <div>
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                + Add Harvest Vintage
              </h3>

              <form action="manageVintages.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_vintage">

                <div style="margin-bottom: 12px;">
                  <label for="vintage_input" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Vintage Year (e.g. 2025):</label>
                  <input type="number" id="vintage_input" name="vintage" min="1750" max="2100" required 
                         value="<?php echo date('Y'); ?>"
                         style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                  <small style="color: #666; font-size: 0.78rem;">Year between 1750 and 2100.</small>
                </div>

                <button type="submit" class="btn-action" style="width: 100%; padding: 8px 14px; font-size: 0.9rem;">
                  + Add Vintage Year
                </button>
              </form>
            </div>
          </div>
        </div>

      <!-- TAB 2: REGIONAL REPORTS -->
      <?php else: ?>
        <div class="admin-storage-manage-grid">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
              <h3 style="margin: 0; color: #333;">Regional Harvest Reports</h3>
              <form method="GET" action="manageVintages.php" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="tab" value="reports">
                <select name="filter_vintage" onchange="this.form.submit()" style="padding: 5px 8px; font-size: 0.85rem;">
                  <option value="">All Vintages</option>
                  <?php foreach ($vintages as $v): ?>
                    <option value="<?php echo (int)$v['vintage']; ?>" <?php echo ($filterVintage === (int)$v['vintage']) ? 'selected' : ''; ?>>
                      <?php echo (int)$v['vintage']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="filter_region" onchange="this.form.submit()" style="padding: 5px 8px; font-size: 0.85rem;">
                  <option value="">All Regions</option>
                  <?php foreach ($regions as $r): ?>
                    <option value="<?php echo (int)$r['region_id']; ?>" <?php echo ($filterRegion === (int)$r['region_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($r['country'] . ': ' . $r['region'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($filterVintage || $filterRegion): ?>
                  <a href="manageVintages.php?tab=reports" style="font-size: 0.82rem; color: #d32f2f; text-decoration: none;">Clear</a>
                <?php endif; ?>
              </form>
            </div>

            <?php if (empty($reports)): ?>
              <p style="color: #666; font-style: italic;">No regional vintage reports match the selected filters. Use the form to write a new harvest evaluation.</p>
            <?php else: ?>
              <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                  <thead>
                    <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                      <th style="padding: 8px 10px;">Vintage</th>
                      <th style="padding: 8px 10px;">Region &amp; Country</th>
                      <th style="padding: 8px 10px;">Evaluation Summary</th>
                      <th style="padding: 8px 10px; text-align: right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reports as $rep): ?>
                      <?php
                        $rVintage = (int)$rep['vintage'];
                        $rRegId = (int)$rep['region_id'];
                        $isEditingThis = ($editingReport && (int)$editingReport['vintage'] === $rVintage && (int)$editingReport['region_id'] === $rRegId);
                      ?>
                      <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                        <td style="padding: 8px 10px; font-weight: bold; white-space: nowrap;">
                          <?php echo $rVintage; ?>
                        </td>
                        <td style="padding: 8px 10px; white-space: nowrap; color: #333;">
                          <strong><?php echo htmlspecialchars($rep['region'], ENT_QUOTES, 'UTF-8'); ?></strong>
                          <div style="color: #777; font-size: 0.8rem;"><?php echo htmlspecialchars($rep['country'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td style="padding: 8px 10px; font-size: 0.85rem; color: #555;">
                          <?php echo nl2br(htmlspecialchars($rep['vintage_desc'], ENT_QUOTES, 'UTF-8')); ?>
                        </td>
                        <td style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                          <a href="manageVintages.php?tab=reports&edit_v=<?php echo $rVintage; ?>&edit_r=<?php echo $rRegId; ?>" style="color: #1a73e8; text-decoration: none; margin-right: 8px; font-size: 0.85rem;">
                            Edit
                          </a>
                          <form action="manageVintages.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this regional vintage report?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_report">
                            <input type="hidden" name="vintage" value="<?php echo $rVintage; ?>">
                            <input type="hidden" name="region_id" value="<?php echo $rRegId; ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;">
                              Delete
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Add / Edit Report Form -->
          <div>
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <?php echo $editingReport ? 'Edit Regional Report' : 'Author Vintage Report'; ?>
              </h3>

              <form action="manageVintages.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="save_report">

                <div style="margin-bottom: 12px;">
                  <label for="report_vintage" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Vintage Year:</label>
                  <?php if ($editingReport): ?>
                    <input type="text" readonly value="<?php echo (int)$editingReport['vintage']; ?>" style="width: 100%; padding: 7px; background: #eee; border: 1px solid #ccc; box-sizing: border-box;">
                    <input type="hidden" name="vintage" value="<?php echo (int)$editingReport['vintage']; ?>">
                  <?php else: ?>
                    <select name="vintage" id="report_vintage" required style="width: 100%; padding: 7px; font-size: 0.9rem;">
                      <option value="">-- Select Vintage Year --</option>
                      <?php 
                        $prefillV = filter_input(INPUT_GET, 'prefill_v', FILTER_VALIDATE_INT);
                        foreach ($vintages as $v): 
                          $vy = (int)$v['vintage'];
                          $sel = ($prefillV && $prefillV === $vy);
                      ?>
                        <option value="<?php echo $vy; ?>" <?php echo $sel ? 'selected' : ''; ?>>
                          <?php echo $vy; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>

                <div style="margin-bottom: 12px;">
                  <label for="report_region_id" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Wine Region:</label>
                  <?php if ($editingReport): ?>
                    <input type="text" readonly value="<?php echo htmlspecialchars($editingReport['region'] . ' (' . $editingReport['country'] . ')', ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 7px; background: #eee; border: 1px solid #ccc; box-sizing: border-box;">
                    <input type="hidden" name="region_id" value="<?php echo (int)$editingReport['region_id']; ?>">
                  <?php else: ?>
                    <select name="region_id" id="report_region_id" required style="width: 100%; padding: 7px; font-size: 0.9rem;">
                      <option value="">-- Select Region --</option>
                      <?php foreach ($regions as $r): ?>
                        <option value="<?php echo (int)$r['region_id']; ?>">
                          <?php echo htmlspecialchars($r['country'] . ': ' . $r['region'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>

                <div style="margin-bottom: 16px;">
                  <label for="vintage_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Harvest &amp; Climate Evaluation:</label>
                  <textarea id="vintage_desc" name="vintage_desc" rows="6" required 
                            placeholder="Weather conditions during flowering, summer heat, harvest rain, acid/tannin balance, aging potential..." 
                            style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editingReport['vintage_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div style="display: flex; gap: 8px;">
                  <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                    <?php echo $editingReport ? 'Update Report' : 'Save Report'; ?>
                  </button>
                  <?php if ($editingReport): ?>
                    <a href="manageVintages.php?tab=reports" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
                      Cancel
                    </a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
  function filterVintageTable() {
    const query = document.getElementById('vintageFilterInput').value.trim();
    const rows = document.querySelectorAll('#vintagesTable tbody tr');
    rows.forEach(row => {
      const year = row.querySelector('td').textContent.trim();
      row.style.display = year.includes(query) ? '' : 'none';
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
