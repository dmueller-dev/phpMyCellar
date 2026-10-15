<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage qualitative descriptors and classification tiers for scoring scales.
   *
   * Required privilege: 'manage_rating_scales' (or 'manage_privileges')
   */
  if (!hasPrivilege($conn, 'manage_rating_scales') && !hasPrivilege($conn, 'manage_privileges')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Active tab state
  $activeTab = $_GET['tab'] ?? 'scale_20';
  if (!in_array($activeTab, ['scale_20', 'scale_100', 'wsetpts'], true)) {
    $activeTab = 'scale_20';
  }

  // Selected item for editing
  $editPts20 = filter_input(INPUT_GET, 'edit_pts20', FILTER_VALIDATE_INT);
  $editing20 = null;
  if ($editPts20 !== null && $editPts20 !== false) {
    $list20 = getAllScale20Details($conn);
    foreach ($list20 as $r) {
      if ((int)$r['pts'] === $editPts20) {
        $editing20 = $r;
        $activeTab = 'scale_20';
        break;
      }
    }
  }

  $editMin100 = filter_input(INPUT_GET, 'edit_min100', FILTER_VALIDATE_INT);
  $editMax100 = filter_input(INPUT_GET, 'edit_max100', FILTER_VALIDATE_INT);
  $editing100 = null;
  if ($editMin100 !== null && $editMax100 !== null) {
    $list100 = getAllScale100Details($conn);
    foreach ($list100 as $r) {
      if ((int)$r['min_pts'] === $editMin100 && (int)$r['max_pts'] === $editMax100) {
        $editing100 = $r;
        $activeTab = 'scale_100';
        break;
      }
    }
  }

  $editWsetPts = isset($_GET['edit_wset']) ? (float)$_GET['edit_wset'] : null;
  $editingWset = null;
  if ($editWsetPts !== null) {
    $listWset = getAllWsetPtsDetails($conn);
    foreach ($listWset as $r) {
      if (abs((float)$r['pts'] - $editWsetPts) < 0.01) {
        $editingWset = $r;
        $activeTab = 'wsetpts';
        break;
      }
    }
  }

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    // 20-Point Scale Edit
    if ($action === 'update_scale_20') {
      $activeTab = 'scale_20';
      $pts = filter_input(INPUT_POST, 'pts', FILTER_VALIDATE_INT);
      $pts_desc = sanitizeInput($_POST['pts_desc'] ?? '');
      $pts_class = sanitizeInput($_POST['pts_class'] ?? '');

      try {
        if ($pts !== null && updateScale20Descriptor($conn, $pts, $pts_desc, $pts_class)) {
          $success_message = "Descriptor for " . $pts . " points updated successfully.";
          $editing20 = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // 100-Point Scale Edit
    elseif ($action === 'update_scale_100') {
      $activeTab = 'scale_100';
      $min_pts = filter_input(INPUT_POST, 'min_pts', FILTER_VALIDATE_INT);
      $max_pts = filter_input(INPUT_POST, 'max_pts', FILTER_VALIDATE_INT);
      $pts_desc = sanitizeInput($_POST['pts_desc'] ?? '');
      $pts_class = sanitizeInput($_POST['pts_class'] ?? '');

      try {
        if ($min_pts !== null && $max_pts !== null && updateScale100Descriptor($conn, $min_pts, $max_pts, $pts_desc, $pts_class)) {
          $success_message = "Descriptor for range " . $min_pts . "–" . $max_pts . " points updated successfully.";
          $editing100 = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // WSET Scale Edit
    elseif ($action === 'update_wset') {
      $activeTab = 'wsetpts';
      $pts = isset($_POST['pts']) ? (float)$_POST['pts'] : null;
      $wset_desc = sanitizeInput($_POST['wset_desc'] ?? '');

      try {
        if ($pts !== null && updateWsetPtsDescriptor($conn, $pts, $wset_desc)) {
          $success_message = "Descriptor for WSET rating " . number_format($pts, 1) . " updated successfully.";
          $editingWset = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
  }

  // Fetch updated data
  $scale20Rows = getAllScale20Details($conn);
  $scale100Rows = getAllScale100Details($conn);
  $wsetRows = getAllWsetPtsDetails($conn);

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Rating Scales - Tasting Administration';
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
          &#128202; Scoring Scales &amp; Assessment Criteria
        </h2>
        <div>
          <a href="settings.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            System Settings &rarr;
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

      <!-- Navigation Tabs -->
      <div style="display: flex; gap: 10px; border-bottom: 1px solid #ddd; margin-bottom: 20px;">
        <a href="?tab=scale_20" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'scale_20' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          20-Point Scale (0–20 pts)
        </a>
        <a href="?tab=scale_100" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'scale_100' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          100-Point Scale (50–100 pts)
        </a>
        <a href="?tab=wsetpts" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'wsetpts' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          WSET Qualitative Scale (0.0–4.0 pts)
        </a>
      </div>

      <!-- TAB 1: 20-POINT SCALE -->
      <?php if ($activeTab === 'scale_20'): ?>
        <div class="admin-storage-manage-grid">
          <div>
            <h3 style="margin-top: 0; color: #333;">20-Point Scale Qualitative Tiers</h3>
            <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px; width: 60px;">Points</th>
                    <th style="padding: 8px 10px;">Classification</th>
                    <th style="padding: 8px 10px;">Qualitative Descriptor</th>
                    <th style="padding: 8px 10px; text-align: right;">Notes</th>
                    <th style="padding: 8px 10px; text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($scale20Rows as $row): ?>
                    <?php 
                      $p = (int)$row['pts'];
                      $isEditingThis = ($editing20 && (int)$editing20['pts'] === $p);
                    ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                      <td style="padding: 8px 10px; font-weight: bold; font-size: 1.05rem; color: #222;">
                        <?php echo $p; ?>
                      </td>
                      <td style="padding: 8px 10px; white-space: nowrap;">
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; background: #e0e0e0; color: #333;">
                          <?php echo htmlspecialchars($row['pts_class'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                      </td>
                      <td style="padding: 8px 10px; color: #555; font-size: 0.85rem;">
                        <?php echo htmlspecialchars($row['pts_desc'], ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right;">
                        <?php echo (int)$row['total_notes']; ?>
                      </td>
                      <td style="padding: 8px 10px; text-align: right;">
                        <a href="manageRatingScales.php?tab=scale_20&edit_pts20=<?php echo $p; ?>" style="color: #1a73e8; text-decoration: none; font-size: 0.85rem;">
                          Edit
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Edit Descriptor Form -->
          <div>
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <?php echo $editing20 ? 'Edit Score Descriptor (' . (int)$editing20['pts'] . ' pts)' : 'Select Score Tier to Edit'; ?>
              </h3>

              <?php if ($editing20): ?>
                <form action="manageRatingScales.php" method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <input type="hidden" name="action" value="update_scale_20">
                  <input type="hidden" name="pts" value="<?php echo (int)$editing20['pts']; ?>">

                  <div style="margin-bottom: 12px;">
                    <label for="pts_class" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Classification Class:</label>
                    <select name="pts_class" id="pts_class" required style="width: 100%; padding: 7px; font-size: 0.9rem;">
                      <?php 
                        $classes = ['grand vin', 'excellent', 'very good', 'good', 'passable', 'subpar', 'poor'];
                        foreach ($classes as $cls):
                      ?>
                        <option value="<?php echo $cls; ?>" <?php echo ($editing20['pts_class'] === $cls) ? 'selected' : ''; ?>>
                          <?php echo ucfirst($cls); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div style="margin-bottom: 16px;">
                    <label for="pts_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Qualitative Descriptor:</label>
                    <textarea id="pts_desc" name="pts_desc" rows="6" required style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editing20['pts_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </div>

                  <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                      Update Descriptor
                    </button>
                    <a href="manageRatingScales.php?tab=scale_20" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
                      Cancel
                    </a>
                  </div>
                </form>
              <?php else: ?>
                <p style="color: #666; font-size: 0.88rem;">Click <strong>Edit</strong> beside any point rating on the left to customize its definition and classification tier.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

      <!-- TAB 2: 100-POINT SCALE -->
      <?php elseif ($activeTab === 'scale_100'): ?>
        <div class="admin-storage-manage-grid">
          <div>
            <h3 style="margin-top: 0; color: #333;">100-Point Scale Rating Bands</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                  <th style="padding: 8px 10px; width: 100px;">Point Range</th>
                  <th style="padding: 8px 10px;">Classification</th>
                  <th style="padding: 8px 10px;">Qualitative Descriptor</th>
                  <th style="padding: 8px 10px; text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($scale100Rows as $row): ?>
                  <?php 
                    $minP = (int)$row['min_pts'];
                    $maxP = (int)$row['max_pts'];
                    $isEditingThis = ($editing100 && (int)$editing100['min_pts'] === $minP && (int)$editing100['max_pts'] === $maxP);
                  ?>
                  <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                    <td style="padding: 8px 10px; font-weight: bold; color: #222; font-size: 0.95rem;">
                      <?php echo $minP; ?>–<?php echo $maxP; ?> pts
                    </td>
                    <td style="padding: 8px 10px; white-space: nowrap;">
                      <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; background: #e0e0e0; color: #333;">
                        <?php echo htmlspecialchars($row['pts_class'], ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td style="padding: 8px 10px; color: #555; font-size: 0.85rem;">
                      <?php echo htmlspecialchars($row['pts_desc'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td style="padding: 8px 10px; text-align: right;">
                      <a href="manageRatingScales.php?tab=scale_100&edit_min100=<?php echo $minP; ?>&edit_max100=<?php echo $maxP; ?>" style="color: #1a73e8; text-decoration: none; font-size: 0.85rem;">
                        Edit
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Edit Range Form -->
          <div>
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <?php echo $editing100 ? 'Edit Band (' . (int)$editing100['min_pts'] . '–' . (int)$editing100['max_pts'] . ' pts)' : 'Select Rating Band to Edit'; ?>
              </h3>

              <?php if ($editing100): ?>
                <form action="manageRatingScales.php" method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <input type="hidden" name="action" value="update_scale_100">
                  <input type="hidden" name="min_pts" value="<?php echo (int)$editing100['min_pts']; ?>">
                  <input type="hidden" name="max_pts" value="<?php echo (int)$editing100['max_pts']; ?>">

                  <div style="margin-bottom: 12px;">
                    <label for="scale100_class" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Classification Class:</label>
                    <input type="text" id="scale100_class" name="pts_class" required value="<?php echo htmlspecialchars($editing100['pts_class'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                  </div>

                  <div style="margin-bottom: 16px;">
                    <label for="scale100_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Qualitative Descriptor:</label>
                    <textarea id="scale100_desc" name="pts_desc" rows="6" required style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editing100['pts_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </div>

                  <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                      Update Band
                    </button>
                    <a href="manageRatingScales.php?tab=scale_100" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
                      Cancel
                    </a>
                  </div>
                </form>
              <?php else: ?>
                <p style="color: #666; font-size: 0.88rem;">Click <strong>Edit</strong> beside any 100-point rating band on the left to modify its definition.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

      <!-- TAB 3: WSET QUALITATIVE SCALE -->
      <?php else: ?>
        <div class="admin-storage-manage-grid">
          <div>
            <h3 style="margin-top: 0; color: #333;">WSET Qualitative Points &amp; BILC Criteria</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                  <th style="padding: 8px 10px; width: 70px;">WSET Pts</th>
                  <th style="padding: 8px 10px;">Assessment Definition</th>
                  <th style="padding: 8px 10px; text-align: right;">Notes</th>
                  <th style="padding: 8px 10px; text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($wsetRows as $row): ?>
                  <?php 
                    $wPts = (float)$row['pts'];
                    $isEditingThis = ($editingWset && abs((float)$editingWset['pts'] - $wPts) < 0.01);
                  ?>
                  <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                    <td style="padding: 8px 10px; font-weight: bold; color: #222; font-size: 1rem;">
                      <?php echo number_format($wPts, 1); ?>
                    </td>
                    <td style="padding: 8px 10px; color: #555; font-size: 0.85rem;">
                      <?php echo htmlspecialchars($row['wset_desc'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td style="padding: 8px 10px; text-align: right;">
                      <?php echo (int)$row['total_notes']; ?>
                    </td>
                    <td style="padding: 8px 10px; text-align: right;">
                      <a href="manageRatingScales.php?tab=wsetpts&edit_wset=<?php echo number_format($wPts, 1); ?>" style="color: #1a73e8; text-decoration: none; font-size: 0.85rem;">
                        Edit
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Edit WSET Form -->
          <div>
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <?php echo $editingWset ? 'Edit WSET Definition (' . number_format((float)$editingWset['pts'], 1) . ' pts)' : 'Select WSET Point to Edit'; ?>
              </h3>

              <?php if ($editingWset): ?>
                <form action="manageRatingScales.php" method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <input type="hidden" name="action" value="update_wset">
                  <input type="hidden" name="pts" value="<?php echo number_format((float)$editingWset['pts'], 1); ?>">

                  <div style="margin-bottom: 16px;">
                    <label for="wset_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Assessment Definition:</label>
                    <textarea id="wset_desc" name="wset_desc" rows="6" required style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editingWset['wset_desc'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </div>

                  <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                      Update Definition
                    </button>
                    <a href="manageRatingScales.php?tab=wsetpts" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
                      Cancel
                    </a>
                  </div>
                </form>
              <?php else: ?>
                <p style="color: #666; font-size: 0.88rem;">Click <strong>Edit</strong> beside any WSET rating point on the left to modify its qualitative criteria.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
