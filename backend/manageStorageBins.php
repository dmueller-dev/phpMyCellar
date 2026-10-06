<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage storage cellars, bins, and maximum capacities.
   *
   * Required privilege: 'manage_storage_bins' (or 'browse_bottles')
   */
  if (!hasPrivilege($conn, 'manage_storage_bins') && !hasPrivilege($conn, 'browse_bottles')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Current user ID for ownership assignment
  $currentUserId = $_SESSION['user_id'] ?? 1;

  // Selected bin for editing
  $editBinId = filter_input(INPUT_GET, 'edit_bin', FILTER_VALIDATE_INT);
  $editingBin = null;
  if ($editBinId) {
    $editingBin = getStorageBinDetails($conn, $editBinId);
    if (!$editingBin) {
      $errors[] = "Storage bin not found.";
      $editBinId = null;
    }
  }

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    // Action: Add Storage Bin
    if ($action === 'add_bin') {
      $bin_name = sanitizeInput($_POST['bin_name'] ?? '');
      $cellar_id = filter_input(INPUT_POST, 'cellar_id', FILTER_VALIDATE_INT);
      $max_capacity_raw = trim($_POST['max_capacity'] ?? '');
      $max_capacity = ($max_capacity_raw !== '' && is_numeric($max_capacity_raw)) ? (int)$max_capacity_raw : null;

      if (empty($bin_name)) {
        $errors[] = "Bin name is required.";
      } elseif (mb_strlen($bin_name, 'UTF-8') > 10) {
        $errors[] = "Bin name must not exceed 10 characters.";
      }

      if (empty($cellar_id)) {
        $errors[] = "Please select a cellar.";
      }

      if ($max_capacity !== null && $max_capacity < 1) {
        $errors[] = "Maximum capacity must be at least 1 bottle or left empty for unlimited.";
      }

      if (empty($errors)) {
        try {
          $newId = insertStorageBin($conn, $bin_name, $cellar_id, $max_capacity);
          if ($newId) {
            $success_message = "Storage bin '" . htmlspecialchars($bin_name, ENT_QUOTES, 'UTF-8') . "' created successfully.";
          } else {
            $errors[] = "Failed to create storage bin. A bin with that name may already exist in this cellar.";
          }
        } catch (Exception $e) {
          $errors[] = "Error creating bin: " . $e->getMessage();
        }
      }
    }

    // Action: Edit Storage Bin
    elseif ($action === 'edit_bin') {
      $bin_id = filter_input(INPUT_POST, 'bin_id', FILTER_VALIDATE_INT);
      $bin_name = sanitizeInput($_POST['bin_name'] ?? '');
      $cellar_id = filter_input(INPUT_POST, 'cellar_id', FILTER_VALIDATE_INT);
      $max_capacity_raw = trim($_POST['max_capacity'] ?? '');
      $max_capacity = ($max_capacity_raw !== '' && is_numeric($max_capacity_raw)) ? (int)$max_capacity_raw : null;

      if (empty($bin_id)) {
        $errors[] = "Invalid storage bin ID.";
      }
      if (empty($bin_name)) {
        $errors[] = "Bin name is required.";
      } elseif (mb_strlen($bin_name, 'UTF-8') > 10) {
        $errors[] = "Bin name must not exceed 10 characters.";
      }
      if (empty($cellar_id)) {
        $errors[] = "Please select a cellar.";
      }
      if ($max_capacity !== null && $max_capacity < 1) {
        $errors[] = "Maximum capacity must be at least 1 bottle or left empty for unlimited.";
      }

      if (empty($errors)) {
        try {
          if (updateStorageBin($conn, $bin_id, $bin_name, $cellar_id, $max_capacity)) {
            $success_message = "Storage bin updated successfully.";
            $editBinId = null;
            $editingBin = null;
          } else {
            $errors[] = "Failed to update storage bin. A bin with that name may already exist in this cellar.";
          }
        } catch (Exception $e) {
          $errors[] = "Error updating bin: " . $e->getMessage();
        }
      }
    }

    // Action: Delete Storage Bin
    elseif ($action === 'delete_bin') {
      $bin_id = filter_input(INPUT_POST, 'bin_id', FILTER_VALIDATE_INT);
      if (empty($bin_id)) {
        $errors[] = "Invalid storage bin ID.";
      } else {
        try {
          if (deleteStorageBin($conn, $bin_id)) {
            $success_message = "Storage bin removed successfully.";
          }
        } catch (Exception $e) {
          $errors[] = $e->getMessage();
        }
      }
    }

    // Action: Add Cellar
    elseif ($action === 'add_cellar') {
      $cellar_name = sanitizeInput($_POST['cellar_name'] ?? '');
      if (empty($cellar_name)) {
        $errors[] = "Cellar name is required.";
      } elseif (mb_strlen($cellar_name, 'UTF-8') > 50) {
        $errors[] = "Cellar name must not exceed 50 characters.";
      }

      if (empty($errors)) {
        try {
          $newCellarId = insertCellar($conn, $cellar_name, $currentUserId);
          if ($newCellarId) {
            $success_message = "Cellar '" . htmlspecialchars($cellar_name, ENT_QUOTES, 'UTF-8') . "' created successfully.";
          } else {
            $errors[] = "Failed to create cellar.";
          }
        } catch (Exception $e) {
          $errors[] = "Error creating cellar: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch all cellars and bins with statistics
  $cellars = getCellars($conn);
  $storageBins = getStorageBinsWithStats($conn);

  // Calculate summary metrics
  $totalCellars = count($cellars);
  $totalBins = count($storageBins);
  $totalBottlesStored = 0;
  $totalCapacity = 0;
  $hasCappedBins = false;

  foreach ($storageBins as $b) {
    $totalBottlesStored += $b['current_bottles'];
    if ($b['max_capacity'] !== null) {
      $totalCapacity += $b['max_capacity'];
      $hasCappedBins = true;
    }
  }

  $totalOccupancyPct = ($hasCappedBins && $totalCapacity > 0) ? round(($totalBottlesStored / $totalCapacity) * 100) : null;

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Storage Bins - Cellar Administration';
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
          &#128452;&#65039; Manage Cellars &amp; Storage Bins
        </h2>
        <div>
          <a href="browseBottles.php?sort=location" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Browse Stored Bottles &rarr;
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

      <!-- High-level Cellar Capacity Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalCellars); ?></span>
            <span class="kpi-icon">&#127963;&#65039;</span>
          </div>
          <div class="kpi-label">Cellars</div>
          <div class="kpi-sub">
            <span><?php echo number_format($totalBins); ?> storage bins configured</span>
          </div>
        </div>

        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalBottlesStored); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Bottles in Bins</div>
          <div class="kpi-sub">
            <span>Active physical inventory</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo $hasCappedBins ? number_format($totalCapacity) : '&infin;'; ?></span>
            <span class="kpi-icon">&#128230;</span>
          </div>
          <div class="kpi-label">Configured Capacity</div>
          <div class="kpi-sub">
            <span><?php echo ($totalOccupancyPct !== null) ? $totalOccupancyPct . '% cellar utilisation' : 'Unlimited / unconstrained'; ?></span>
          </div>
        </div>
      </div>

      <!-- Main Layout: Table on Left / Forms on Right -->
      <div class="admin-storage-manage-grid">
        
        <!-- Storage Bins List -->
        <div>
          <h3 style="margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 8px;">
            Storage Locations &amp; Capacity
          </h3>

          <?php if (empty($storageBins)): ?>
            <p style="color: #666; font-style: italic;">No storage bins have been configured yet. Use the form to add your first bin.</p>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Cellar</th>
                    <th style="padding: 8px 10px;">Bin</th>
                    <th style="padding: 8px 10px; text-align: right;">Occupancy</th>
                    <th style="padding: 8px 10px; text-align: right;">Capacity</th>
                    <th style="padding: 8px 10px; min-width: 130px;">Usage %</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                    $curCellar = null;
                    foreach ($storageBins as $bin): 
                      $isDifferentCellar = ($bin['cellar_name'] !== $curCellar);
                      $curCellar = $bin['cellar_name'];
                  ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo ($editingBin && $editingBin['bin_id'] == $bin['bin_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                      <td style="padding: 8px 10px; font-weight: <?php echo $isDifferentCellar ? '600' : 'normal'; ?>; color: #333;">
                        <?php echo htmlspecialchars($bin['cellar_name'], ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td style="padding: 8px 10px; font-weight: 600;">
                        <a href="browseBottles.php?sort=location" title="View bottles in this bin" style="text-decoration: none; color: #2c2c2c;">
                          <?php echo htmlspecialchars($bin['bin_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      </td>
                      <td style="padding: 8px 10px; text-align: right;">
                        <strong><?php echo (int)$bin['current_bottles']; ?></strong> btl<?php echo ((int)$bin['current_bottles'] === 1 ? '' : 's'); ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right; color: <?php echo ($bin['max_capacity'] !== null) ? '#333' : '#888'; ?>;">
                        <?php echo ($bin['max_capacity'] !== null) ? (int)$bin['max_capacity'] : '&mdash;'; ?>
                      </td>
                      <td style="padding: 8px 10px;">
                        <?php if ($bin['max_capacity'] !== null && $bin['max_capacity'] > 0): ?>
                          <?php
                            $pct = (int)$bin['usage_pct'];
                            $barClass = 'storage-bar-normal';
                            if ($pct >= 100) {
                              $barClass = 'storage-bar-full';
                            } elseif ($pct >= 80) {
                              $barClass = 'storage-bar-warning';
                            }
                          ?>
                          <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="storage-progress-container" style="flex: 1; height: 10px; background-color: #eee; border-radius: 5px; overflow: hidden;">
                              <div class="<?php echo $barClass; ?>" style="width: <?php echo min(100, $pct); ?>%; height: 100%;"></div>
                            </div>
                            <span class="storage-pct-badge <?php echo ($pct >= 100) ? 'storage-pct-full' : (($pct >= 80) ? 'storage-pct-warning' : ''); ?>" style="font-size: 0.78rem;">
                              <?php echo $pct; ?>%
                            </span>
                          </div>
                        <?php else: ?>
                          <span style="color: #999; font-size: 0.8rem;">Unlimited</span>
                        <?php endif; ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                        <a href="manageStorageBins.php?edit_bin=<?php echo (int)$bin['bin_id']; ?>" style="color: #1a73e8; text-decoration: none; margin-right: 8px; font-size: 0.85rem;" title="Edit bin">
                          Edit
                        </a>
                        <?php if ((int)$bin['current_bottles'] === 0): ?>
                          <form action="manageStorageBins.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete storage bin \'<?php echo htmlspecialchars($bin['bin_name'], ENT_QUOTES, 'UTF-8'); ?>\'?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_bin">
                            <input type="hidden" name="bin_id" value="<?php echo (int)$bin['bin_id']; ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;" title="Delete empty bin">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Bin contains stored bottles">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar: Add / Edit Bin Form & Add Cellar -->
        <div>
          <!-- Add / Edit Bin Card -->
          <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
              <?php echo $editingBin ? 'Edit Storage Bin' : 'Add New Storage Bin'; ?>
            </h3>

            <form action="manageStorageBins.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="action" value="<?php echo $editingBin ? 'edit_bin' : 'add_bin'; ?>">
              <?php if ($editingBin): ?>
                <input type="hidden" name="bin_id" value="<?php echo (int)$editingBin['bin_id']; ?>">
              <?php endif; ?>

              <div style="margin-bottom: 12px;">
                <label for="bin_cellar_id" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Cellar:</label>
                <select name="cellar_id" id="bin_cellar_id" required style="width: 100%; padding: 7px; font-size: 0.9rem;">
                  <option value="">-- Select Cellar --</option>
                  <?php foreach ($cellars as $c): ?>
                    <?php 
                      $sel = false;
                      if ($editingBin && $editingBin['cellar_id'] == $c['cellar_id']) {
                        $sel = true;
                      } elseif (!$editingBin && count($cellars) === 1) {
                        $sel = true;
                      }
                    ?>
                    <option value="<?php echo (int)$c['cellar_id']; ?>" <?php echo $sel ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($c['cellar_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div style="margin-bottom: 12px;">
                <label for="bin_name" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Bin Name / Code:</label>
                <input type="text" id="bin_name" name="bin_name" maxlength="10" required 
                       value="<?php echo htmlspecialchars($editingBin['bin_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="e.g. A1, Rack 2" 
                       style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                <small style="color: #666; font-size: 0.78rem;">Maximum 10 characters.</small>
              </div>

              <div style="margin-bottom: 16px;">
                <label for="max_capacity" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Maximum Capacity (Bottles):</label>
                <input type="number" id="max_capacity" name="max_capacity" min="1" step="1" 
                       value="<?php echo htmlspecialchars($editingBin['max_capacity'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="Leave blank for unlimited" 
                       style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                <small style="color: #666; font-size: 0.78rem;">Optional. Leave blank if this bin has no strict capacity limit.</small>
              </div>

              <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                  <?php echo $editingBin ? 'Update Bin' : '+ Create Bin'; ?>
                </button>
                <?php if ($editingBin): ?>
                  <a href="manageStorageBins.php" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
                    Cancel
                  </a>
                <?php endif; ?>
              </div>
            </form>
          </div>

          <!-- Add Cellar Card -->
          <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
            <h3 style="margin-top: 0; color: #444; border-bottom: 1px solid #eee; padding-bottom: 6px; font-size: 1rem;">
              + Add Another Cellar
            </h3>

            <form action="manageStorageBins.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="action" value="add_cellar">

              <div style="margin-bottom: 12px;">
                <label for="cellar_name" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Cellar Name:</label>
                <input type="text" id="cellar_name" name="cellar_name" maxlength="50" required 
                       placeholder="e.g. Main Cellar, Wine Fridge" 
                       style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
              </div>

              <button type="submit" class="btn-action btn-secondary" style="width: 100%; padding: 7px 12px; font-size: 0.88rem;">
                + Create Cellar
              </button>
            </form>
          </div>

        </div>

      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
