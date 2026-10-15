<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage grape varieties and viticultural descriptions.
   *
   * Required privilege: 'manage_varieties' (or 'edit_wine_master')
   */
  if (!hasPrivilege($conn, 'manage_varieties') && !hasPrivilege($conn, 'edit_wine_master')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Selected variety for editing
  $editGrapeName = isset($_GET['edit_grape']) ? trim($_GET['edit_grape']) : null;
  $editingVariety = null;
  if ($editGrapeName !== null && $editGrapeName !== '') {
    $editingVariety = getVarietyDetails($conn, $editGrapeName);
    if (!$editingVariety) {
      $errors[] = "Grape variety not found.";
      $editGrapeName = null;
    }
  }

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    // Action: Add Variety
    if ($action === 'add_variety') {
      $grape = sanitizeInput($_POST['grape'] ?? '');
      $grape_desc = sanitizeInput($_POST['grape_desc'] ?? '');

      try {
        if (insertVariety($conn, $grape, $grape_desc)) {
          $success_message = "Grape variety '" . htmlspecialchars($grape, ENT_QUOTES, 'UTF-8') . "' catalogued successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Edit Variety
    elseif ($action === 'edit_variety') {
      $old_grape = sanitizeInput($_POST['old_grape'] ?? '');
      $grape = sanitizeInput($_POST['grape'] ?? '');
      $grape_desc = sanitizeInput($_POST['grape_desc'] ?? '');

      try {
        if (updateVariety($conn, $old_grape, $grape, $grape_desc)) {
          $success_message = "Grape variety '" . htmlspecialchars($grape, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
          $editGrapeName = null;
          $editingVariety = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Delete Variety
    elseif ($action === 'delete_variety') {
      $grape = sanitizeInput($_POST['grape'] ?? '');
      try {
        if (deleteVariety($conn, $grape)) {
          $success_message = "Grape variety '" . htmlspecialchars($grape, ENT_QUOTES, 'UTF-8') . "' removed successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
  }

  // Fetch all varieties with usage statistics
  $varieties = getVarietiesWithStats($conn);

  $totalVarieties = count($varieties);
  $activeVarieties = 0;
  foreach ($varieties as $v) {
    if ((int)$v['total_masters'] > 0) {
      $activeVarieties++;
    }
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Grape Varieties - Catalog Administration';
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
          &#127815; Grape Varieties
        </h2>
        <div>
          <?php if (hasPrivilege($conn, 'browse_wines')): ?>
            <a href="browseWines.php?sort=variety" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px; margin-right: 8px;">
              Browse by Variety &rarr;
            </a>
          <?php endif; ?>
          <?php if (hasPrivilege($conn, 'add_wine_master')): ?>
            <a href="addWineMaster.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
              + New Wine Master
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

      <!-- KPI Cards -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalVarieties); ?></span>
            <span class="kpi-icon">&#127815;</span>
          </div>
          <div class="kpi-label">Recorded Varieties</div>
          <div class="kpi-sub">
            <span>Noble, regional &amp; PIWI varieties</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($activeVarieties); ?></span>
            <span class="kpi-icon">&#127991;&#65039;</span>
          </div>
          <div class="kpi-label">Active in Wine Catalog</div>
          <div class="kpi-sub">
            <span>Used in registered master cuv&eacute;es</span>
          </div>
        </div>
      </div>

      <!-- Main Layout: Table on Left / Form on Right -->
      <div class="admin-storage-manage-grid">
        
        <!-- Varieties List -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <h3 style="margin: 0; color: #333;">Grape Varieties Directory</h3>
            <input type="text" id="varietyFilterInput" placeholder="Filter varieties..." 
                   oninput="filterVarietyTable()" 
                   style="padding: 6px 10px; font-size: 0.88rem; width: 220px; border: 1px solid #ccc; border-radius: 4px;">
          </div>

          <?php if (empty($varieties)): ?>
            <p style="color: #666; font-style: italic;">No grape varieties recorded yet. Add your first variety using the form.</p>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table id="varietiesTable" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Grape Variety</th>
                    <th style="padding: 8px 10px;">Description &amp; Origin</th>
                    <th style="padding: 8px 10px; text-align: right;">Cuv&eacute;es</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($varieties as $v): ?>
                    <?php
                      $gName = $v['grape'];
                      $mCount = (int)$v['total_masters'];
                      $isEditingThis = ($editingVariety && $editingVariety['grape'] === $gName);
                    ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                      <td style="padding: 8px 10px; font-weight: 600; color: #222; white-space: nowrap;">
                        <?php echo htmlspecialchars($gName, ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td style="padding: 8px 10px; color: #555; font-size: 0.85rem;">
                        <?php echo !empty($v['grape_desc']) ? htmlspecialchars($v['grape_desc'], ENT_QUOTES, 'UTF-8') : '<span style="color:#aaa;">&mdash;</span>'; ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right;">
                        <?php if ($mCount > 0): ?>
                          <a href="browseWines.php?q=<?php echo urlencode($gName); ?>" style="text-decoration: none; font-weight: bold; color: #2e7d32;" title="View wines crafted with this grape">
                            <?php echo $mCount; ?>
                          </a>
                        <?php else: ?>
                          <span style="color: #999;">0</span>
                        <?php endif; ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                        <a href="manageVarieties.php?edit_grape=<?php echo urlencode($gName); ?>" style="color: #1a73e8; text-decoration: none; margin-right: 8px; font-size: 0.85rem;" title="Edit grape details">
                          Edit
                        </a>
                        <?php if ($mCount === 0): ?>
                          <form action="manageVarieties.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete grape variety \'<?php echo htmlspecialchars($gName, ENT_QUOTES, 'UTF-8'); ?>\'?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_variety">
                            <input type="hidden" name="grape" value="<?php echo htmlspecialchars($gName, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;" title="Delete unused variety">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Used in master cuvées">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar: Add / Edit Variety Form -->
        <div>
          <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
            <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
              <?php echo $editingVariety ? 'Edit Grape Variety' : 'Add Grape Variety'; ?>
            </h3>

            <form action="manageVarieties.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="action" value="<?php echo $editingVariety ? 'edit_variety' : 'add_variety'; ?>">
              <?php if ($editingVariety): ?>
                <input type="hidden" name="old_grape" value="<?php echo htmlspecialchars($editingVariety['grape'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php endif; ?>

              <div style="margin-bottom: 12px;">
                <label for="grape_name" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Grape Variety Name:</label>
                <input type="text" id="grape_name" name="grape" maxlength="255" required 
                       value="<?php echo htmlspecialchars($editingVariety['grape'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="e.g. Syrah, Chenin Blanc, Savagnin" 
                       style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
              </div>

              <div style="margin-bottom: 16px;">
                <label for="grape_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Viticultural Description / Origin:</label>
                <textarea id="grape_desc" name="grape_desc" rows="5" 
                          placeholder="Typicity notes, origin region, or winemaking characteristics..."
                          style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editingVariety['grape_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>

              <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                  <?php echo $editingVariety ? 'Update Variety' : '+ Add Variety'; ?>
                </button>
                <?php if ($editingVariety): ?>
                  <a href="manageVarieties.php" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
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
</div>

<script>
  function filterVarietyTable() {
    const query = document.getElementById('varietyFilterInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#varietiesTable tbody tr');
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(query) ? '' : 'none';
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
