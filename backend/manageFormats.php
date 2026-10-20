<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage physical bottle formats and volume sizes.
   *
   * Required privilege: 'manage_formats' (or 'manage_storage_bins')
   */
  if (!hasPrivilege($conn, 'manage_formats') && !hasPrivilege($conn, 'manage_storage_bins')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Selected format for editing
  $editFormatCode = isset($_GET['edit_format']) ? trim($_GET['edit_format']) : null;
  $editingFormat = null;
  if ($editFormatCode !== null && $editFormatCode !== '') {
    $stmt = $conn->prepare("SELECT format, format_desc FROM bottle_formats WHERE format = ?");
    if ($stmt) {
      $stmt->bind_param("s", $editFormatCode);
      $stmt->execute();
      $res = $stmt->get_result();
      $editingFormat = ($res && $r = $res->fetch_assoc()) ? $r : null;
      $stmt->close();
    }
    if (!$editingFormat) {
      $errors[] = "Bottle format not found.";
      $editFormatCode = null;
    }
  }

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    // Action: Add Format
    if ($action === 'add_format') {
      $format = sanitizeInput($_POST['format'] ?? '');
      $format_desc = sanitizeInput($_POST['format_desc'] ?? '');

      try {
        if (insertBottleFormat($conn, $format, $format_desc)) {
          $success_message = "Bottle format '" . htmlspecialchars($format, ENT_QUOTES, 'UTF-8') . "' configured successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Edit Format
    elseif ($action === 'edit_format') {
      $format = sanitizeInput($_POST['format'] ?? '');
      $format_desc = sanitizeInput($_POST['format_desc'] ?? '');

      try {
        if (updateBottleFormat($conn, $format, $format_desc)) {
          $success_message = "Bottle format '" . htmlspecialchars($format, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
          $editFormatCode = null;
          $editingFormat = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Delete Format
    elseif ($action === 'delete_format') {
      $format = sanitizeInput($_POST['format'] ?? '');
      try {
        if (deleteBottleFormat($conn, $format)) {
          $success_message = "Bottle format '" . htmlspecialchars($format, ENT_QUOTES, 'UTF-8') . "' removed successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
  }

  // Fetch all formats with usage statistics
  $formats = getBottleFormatsWithStats($conn);

  $totalFormats = count($formats);
  $totalBottles = 0;
  foreach ($formats as $f) {
    $totalBottles += (int)$f['total_bottles'];
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Bottle Formats - Cellar Administration';
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
          &#127870; Bottle Formats &amp; Volume Sizes
        </h2>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <?php if (hasPrivilege($conn, 'browse_bottles')): ?>
            <a href="browseBottles.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
              Browse Bottles &rarr;
            </a>
          <?php endif; ?>
          <?php if (hasPrivilege($conn, 'add_bottle')): ?>
            <a href="manageBottles.php?mode=add" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
              + Add Bottle
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
            <span class="kpi-val"><?php echo number_format($totalFormats); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Configured Formats</div>
          <div class="kpi-sub">
            <span>Half bottles, standard, magnums &amp; large formats</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalBottles); ?></span>
            <span class="kpi-icon">&#128452;&#65039;</span>
          </div>
          <div class="kpi-label">Cellared Bottles</div>
          <div class="kpi-sub">
            <span>Total inventory stored across formats</span>
          </div>
        </div>
      </div>

      <?php $is_edit = !empty($editingFormat); ?>
      <!-- Mobile Segmented View Switcher -->
      <div class="admin-mobile-pane-switch">
        <button type="button" id="tabBtnList" class="admin-mobile-pane-btn <?php echo !$is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('list')">
          📋 Formats (<?php echo count($formats); ?>)
        </button>
        <button type="button" id="tabBtnForm" class="admin-mobile-pane-btn <?php echo $is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('form')">
          <?php echo $is_edit ? '✏️ Edit Format' : '➕ Add Format'; ?>
        </button>
      </div>

      <!-- Main Layout: Table on Left / Form on Right -->
      <div class="admin-storage-manage-grid">
        
        <!-- Formats List -->
        <div id="adminDualListPane" class="admin-dual-pane <?php echo $is_edit ? 'mobile-hidden' : ''; ?>">
          <h3 style="margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 8px;">
            Standard &amp; Custom Formats
          </h3>

          <?php if (empty($formats)): ?>
            <p style="color: #666; font-style: italic;">No bottle formats recorded. Use the form to configure your first format.</p>
          <?php else: ?>
            <div class="admin-table-container">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Format Code</th>
                    <th style="padding: 8px 10px;">Common Description</th>
                    <th style="padding: 8px 10px; text-align: right;">Cellar Inventory</th>
                    <th style="padding: 8px 10px; text-align: right;">Order Items</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($formats as $fmt): ?>
                    <?php
                      $fCode = $fmt['format'];
                      $bCount = (int)$fmt['total_bottles'];
                      $oCount = (int)$fmt['total_order_items'];
                      $isEditingThis = ($editingFormat && $editingFormat['format'] === $fCode);
                    ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                      <td class="card-primary-cell" style="padding: 8px 10px; font-weight: 600; color: #222; font-family: monospace; font-size: 0.95rem;">
                        <?php echo htmlspecialchars($fCode, ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td data-label="Description" style="padding: 8px 10px; color: #555;">
                        <?php echo !empty($fmt['format_desc']) ? htmlspecialchars($fmt['format_desc'], ENT_QUOTES, 'UTF-8') : '<span style="color:#aaa;">&mdash;</span>'; ?>
                      </td>
                      <td data-label="Inventory" style="padding: 8px 10px; text-align: right;">
                        <strong><?php echo $bCount; ?></strong> btl<?php echo ($bCount === 1 ? '' : 's'); ?>
                      </td>
                      <td data-label="Orders" style="padding: 8px 10px; text-align: right; color: #666;">
                        <?php echo $oCount; ?>
                      </td>
                      <td class="card-actions-cell" style="padding: 8px 10px; text-align: right;">
                        <a href="manageFormats.php?edit_format=<?php echo urlencode($fCode); ?>" style="color: #1a73e8; text-decoration: none; margin-right: 8px; font-size: 0.85rem;" title="Edit description">
                          Edit
                        </a>
                        <?php if ($bCount === 0 && $oCount === 0): ?>
                          <form action="manageFormats.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete format \'<?php echo htmlspecialchars($fCode, ENT_QUOTES, 'UTF-8'); ?>\'?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_format">
                            <input type="hidden" name="format" value="<?php echo htmlspecialchars($fCode, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;" title="Delete unused format">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Format is assigned to bottles or orders">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar: Add / Edit Format Form -->
        <div id="adminDualFormPane" class="admin-dual-pane <?php echo !$is_edit ? 'mobile-hidden' : ''; ?>">
          <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
            <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
              <?php echo $editingFormat ? 'Edit Bottle Format' : 'Add Bottle Format'; ?>
            </h3>

            <form action="manageFormats.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="action" value="<?php echo $editingFormat ? 'edit_format' : 'add_format'; ?>">

              <div style="margin-bottom: 12px;">
                <label for="format_code" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Format Code:</label>
                <?php if ($editingFormat): ?>
                  <input type="text" id="format_code" name="format" readonly
                         value="<?php echo htmlspecialchars($editingFormat['format'], ENT_QUOTES, 'UTF-8'); ?>" 
                         style="width: 100%; padding: 7px; font-size: 0.9rem; background: #eee; border: 1px solid #ccc; box-sizing: border-box;">
                  <small style="color: #666; font-size: 0.78rem;">Format codes cannot be modified once created.</small>
                <?php else: ?>
                  <input type="text" id="format_code" name="format" maxlength="7" required 
                         placeholder="e.g. 200ml, 2250ml" 
                         style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                  <small style="color: #666; font-size: 0.78rem;">Maximum 7 characters (e.g. 750ml, 1500ml).</small>
                <?php endif; ?>
              </div>

              <div style="margin-bottom: 16px;">
                <label for="format_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Common Name / Description:</label>
                <input type="text" id="format_desc" name="format_desc" maxlength="25" 
                       value="<?php echo htmlspecialchars($editingFormat['format_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="e.g. Marie-Jeanne, magnum, half bottle" 
                       style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                <small style="color: #666; font-size: 0.78rem;">Maximum 25 characters.</small>
              </div>

              <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                  <?php echo $editingFormat ? 'Update Format' : '+ Create Format'; ?>
                </button>
                <?php if ($editingFormat): ?>
                  <a href="manageFormats.php" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
