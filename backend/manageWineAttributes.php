<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage wine styles, colours, and title naming conventions.
   *
   * Required privilege: 'manage_wine_attributes' (or 'manage_privileges')
   */
  if (!hasPrivilege($conn, 'manage_wine_attributes') && !hasPrivilege($conn, 'manage_privileges')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Active tab state
  $activeTab = $_GET['tab'] ?? 'styles';
  if (!in_array($activeTab, ['styles', 'colours', 'conventions'], true)) {
    $activeTab = 'styles';
  }

  // Selected items for editing
  $editStyleCode = isset($_GET['edit_style']) ? trim($_GET['edit_style']) : null;
  $editingStyle = null;
  if ($editStyleCode) {
    $stylesList = getStylesWithStats($conn);
    foreach ($stylesList as $s) {
      if ($s['style'] === $editStyleCode) {
        $editingStyle = $s;
        $activeTab = 'styles';
        break;
      }
    }
  }

  $editConvCode = isset($_GET['edit_conv']) ? trim($_GET['edit_conv']) : null;
  $editingConv = null;
  if ($editConvCode) {
    $convList = getDisplayOptionsWithStats($conn);
    foreach ($convList as $c) {
      if ($c['nameconvention'] === $editConvCode) {
        $editingConv = $c;
        $activeTab = 'conventions';
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

    // Styles Actions
    if ($action === 'add_style') {
      $activeTab = 'styles';
      $style = sanitizeInput($_POST['style'] ?? '');
      $style_desc = sanitizeInput($_POST['style_desc'] ?? '');

      try {
        if (insertStyle($conn, $style, $style_desc)) {
          $success_message = "Wine style '" . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . "' created successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    } elseif ($action === 'edit_style') {
      $activeTab = 'styles';
      $style = sanitizeInput($_POST['style'] ?? '');
      $style_desc = sanitizeInput($_POST['style_desc'] ?? '');

      try {
        if (updateStyle($conn, $style, $style_desc)) {
          $success_message = "Wine style updated successfully.";
          $editingStyle = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    } elseif ($action === 'delete_style') {
      $activeTab = 'styles';
      $style = sanitizeInput($_POST['style'] ?? '');
      try {
        if (deleteStyle($conn, $style)) {
          $success_message = "Wine style removed successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Colours Actions
    elseif ($action === 'add_colour') {
      $activeTab = 'colours';
      $colour = sanitizeInput($_POST['colour'] ?? '');
      try {
        if (insertColour($conn, $colour)) {
          $success_message = "Wine colour '" . htmlspecialchars($colour, ENT_QUOTES, 'UTF-8') . "' added successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    } elseif ($action === 'delete_colour') {
      $activeTab = 'colours';
      $colour = sanitizeInput($_POST['colour'] ?? '');
      try {
        if (deleteColour($conn, $colour)) {
          $success_message = "Wine colour removed successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Naming Conventions Actions
    elseif ($action === 'add_conv') {
      $activeTab = 'conventions';
      $nameconv = sanitizeInput($_POST['nameconvention'] ?? '');
      $description = sanitizeInput($_POST['description'] ?? '');
      try {
        if (insertDisplayOption($conn, $nameconv, $description)) {
          $success_message = "Naming convention added successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    } elseif ($action === 'edit_conv') {
      $activeTab = 'conventions';
      $nameconv = sanitizeInput($_POST['nameconvention'] ?? '');
      $description = sanitizeInput($_POST['description'] ?? '');
      try {
        if (updateDisplayOption($conn, $nameconv, $description)) {
          $success_message = "Naming convention updated successfully.";
          $editingConv = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    } elseif ($action === 'delete_conv') {
      $activeTab = 'conventions';
      $nameconv = sanitizeInput($_POST['nameconvention'] ?? '');
      try {
        if (deleteDisplayOption($conn, $nameconv)) {
          $success_message = "Naming convention removed successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
  }

  // Fetch updated data
  $styles = getStylesWithStats($conn);
  $colours = getColoursWithStats($conn);
  $conventions = getDisplayOptionsWithStats($conn);

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Wine Attributes - Catalog Administration';
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
          &#9881;&#65039; Wine Classifications &amp; Attributes
        </h2>
        <div>
          <?php if (hasPrivilege($conn, 'browse_wines')): ?>
            <a href="browseWines.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
              Wine Catalog &rarr;
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

      <!-- Navigation Tabs -->
      <div class="admin-mode-tabs" style="display: flex; gap: 10px; border-bottom: 1px solid #ddd; margin-bottom: 20px;">
        <a href="?tab=styles" class="admin-mode-tab <?= $activeTab === 'styles' ? 'active' : '' ?>" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'styles' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          &#127870; Wine Styles (<?php echo count($styles); ?>)
        </a>
        <a href="?tab=colours" class="admin-mode-tab <?= $activeTab === 'colours' ? 'active' : '' ?>" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'colours' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          &#127912; Wine Colours (<?php echo count($colours); ?>)
        </a>
        <a href="?tab=conventions" class="admin-mode-tab <?= $activeTab === 'conventions' ? 'active' : '' ?>" style="text-decoration: none; padding: 9px 18px; border-radius: 4px 4px 0 0; font-weight: bold; <?php echo $activeTab === 'conventions' ? 'background: #333; color: #fff;' : 'background: #f0f0f0; color: #333;'; ?>">
          &#127991;&#65039; Naming Conventions (<?php echo count($conventions); ?>)
        </a>
      </div>

      <!-- TAB 1: WINE STYLES -->
      <?php if ($activeTab === 'styles'): ?>
        <?php $is_edit_style = !empty($editingStyle); ?>
        <!-- Mobile Switcher for Dual-Pane Layout -->
        <div class="admin-mobile-pane-switch">
          <button type="button" class="admin-pane-btn <?= !$is_edit_style ? 'active' : '' ?>" id="tabBtnList" onclick="switchAdminMobilePane('list')">
            📋 Wine Styles
          </button>
          <button type="button" class="admin-pane-btn <?= $is_edit_style ? 'active' : '' ?>" id="tabBtnForm" onclick="switchAdminMobilePane('form')">
            <?= $is_edit_style ? '✏️ Edit Style' : '➕ Add Style' ?>
          </button>
        </div>

        <div class="admin-storage-manage-grid">
          <div id="adminDualListPane" class="admin-dual-pane <?= $is_edit_style ? 'mobile-hidden' : '' ?>">
            <h3 style="margin-top: 0; color: #333;">Registered Wine Styles</h3>
            <div class="admin-table-container">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Style Name</th>
                    <th style="padding: 8px 10px;">Description</th>
                    <th style="padding: 8px 10px; text-align: right;">Cuv&eacute;es</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($styles as $st): ?>
                    <?php 
                      $sName = $st['style']; 
                      $mCount = (int)$st['total_masters'];
                      $isEditingThis = ($editingStyle && $editingStyle['style'] === $sName);
                    ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                      <td data-label="Style Name" class="card-primary-cell" style="padding: 8px 10px; font-weight: bold; color: #222; white-space: nowrap;">
                        <?php echo htmlspecialchars($sName, ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td data-label="Description" style="padding: 8px 10px; color: #555; font-size: 0.85rem;">
                        <?php echo !empty($st['style_desc']) ? htmlspecialchars($st['style_desc'], ENT_QUOTES, 'UTF-8') : '<span style="color:#aaa;">&mdash;</span>'; ?>
                      </td>
                      <td data-label="Cuv&eacute;es" style="padding: 8px 10px; text-align: right;">
                        <?php echo $mCount; ?>
                      </td>
                      <td data-label="Actions" style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                        <a href="manageWineAttributes.php?tab=styles&edit_style=<?php echo urlencode($sName); ?>" style="color: #1a73e8; text-decoration: none; margin-right: 8px; font-size: 0.85rem;">
                          Edit
                        </a>
                        <?php if ($mCount === 0): ?>
                          <form action="manageWineAttributes.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete style \'<?php echo htmlspecialchars($sName, ENT_QUOTES, 'UTF-8'); ?>\'?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_style">
                            <input type="hidden" name="style" value="<?php echo htmlspecialchars($sName, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Linked to master cuvées">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Add / Edit Style Form -->
          <div id="adminDualFormPane" class="admin-dual-pane <?= !$is_edit_style ? 'mobile-hidden' : '' ?>">
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <?php echo $editingStyle ? 'Edit Wine Style' : 'Add Wine Style'; ?>
              </h3>

              <form action="manageWineAttributes.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="<?php echo $editingStyle ? 'edit_style' : 'add_style'; ?>">

                <div style="margin-bottom: 12px;">
                  <label for="style_code" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Style Code / Name:</label>
                  <?php if ($editingStyle): ?>
                    <input type="text" id="style_code" name="style" readonly value="<?php echo htmlspecialchars($editingStyle['style'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 7px; background: #eee; border: 1px solid #ccc; box-sizing: border-box;">
                  <?php else: ?>
                    <input type="text" id="style_code" name="style" maxlength="32" required placeholder="e.g. orange, natural, pet-nat" style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                  <?php endif; ?>
                </div>

                <div style="margin-bottom: 16px;">
                  <label for="style_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Style Description:</label>
                  <textarea id="style_desc" name="style_desc" rows="4" placeholder="Sugar level, carbonation method, or vinification notes..." style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editingStyle['style_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div style="display: flex; gap: 8px;">
                  <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                    <?php echo $editingStyle ? 'Update Style' : '+ Create Style'; ?>
                  </button>
                  <?php if ($editingStyle): ?>
                    <a href="manageWineAttributes.php?tab=styles" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
                      Cancel
                    </a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
        </div>

      <!-- TAB 2: WINE COLOURS -->
      <?php elseif ($activeTab === 'colours'): ?>
        <!-- Mobile Switcher for Dual-Pane Layout -->
        <div class="admin-mobile-pane-switch">
          <button type="button" class="admin-pane-btn active" id="tabBtnList" onclick="switchAdminMobilePane('list')">
            📋 Wine Colours
          </button>
          <button type="button" class="admin-pane-btn" id="tabBtnForm" onclick="switchAdminMobilePane('form')">
            ➕ Add Colour
          </button>
        </div>

        <div class="admin-storage-manage-grid">
          <div id="adminDualListPane" class="admin-dual-pane">
            <h3 style="margin-top: 0; color: #333;">Wine Colour Categories</h3>
            <div class="admin-table-container">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Colour</th>
                    <th style="padding: 8px 10px; text-align: right;">Cuv&eacute;es</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($colours as $col): ?>
                    <?php 
                      $cName = $col['colour']; 
                      $mCount = (int)$col['total_masters'];
                      $dotColor = '#777';
                      if ($cName === 'red') $dotColor = '#800020';
                      elseif ($cName === 'white') $dotColor = '#d4af37';
                      elseif ($cName === 'rosé') $dotColor = '#ff69b4';
                      elseif ($cName === 'orange') $dotColor = '#ff8c00';
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                      <td data-label="Colour" class="card-primary-cell" style="padding: 8px 10px; font-weight: bold; color: #222;">
                        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo $dotColor; ?>; margin-right: 8px; vertical-align: middle;"></span>
                        <?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td data-label="Cuv&eacute;es" style="padding: 8px 10px; text-align: right;">
                        <?php echo $mCount; ?>
                      </td>
                      <td data-label="Actions" style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                        <?php if ($mCount === 0): ?>
                          <form action="manageWineAttributes.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete colour \'<?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?>\'?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_colour">
                            <input type="hidden" name="colour" value="<?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Linked to master cuvées">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Add Colour Form -->
          <div id="adminDualFormPane" class="admin-dual-pane mobile-hidden">
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                + Add Wine Colour
              </h3>

              <form action="manageWineAttributes.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_colour">

                <div style="margin-bottom: 16px;">
                  <label for="colour_name" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Colour Name:</label>
                  <input type="text" id="colour_name" name="colour" maxlength="24" required placeholder="e.g. tawny, amber" style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                  <small style="color: #666; font-size: 0.78rem;">Maximum 24 characters.</small>
                </div>

                <button type="submit" class="btn-action" style="width: 100%; padding: 8px 14px; font-size: 0.9rem;">
                  + Add Colour
                </button>
              </form>
            </div>
          </div>
        </div>

      <!-- TAB 3: NAMING CONVENTIONS -->
      <?php else: ?>
        <?php $is_edit_conv = !empty($editingConv); ?>
        <!-- Mobile Switcher for Dual-Pane Layout -->
        <div class="admin-mobile-pane-switch">
          <button type="button" class="admin-pane-btn <?= !$is_edit_conv ? 'active' : '' ?>" id="tabBtnList" onclick="switchAdminMobilePane('list')">
            📋 Naming Conventions
          </button>
          <button type="button" class="admin-pane-btn <?= $is_edit_conv ? 'active' : '' ?>" id="tabBtnForm" onclick="switchAdminMobilePane('form')">
            <?= $is_edit_conv ? '✏️ Edit Convention' : '➕ Add Convention' ?>
          </button>
        </div>

        <div class="admin-storage-manage-grid">
          <div id="adminDualListPane" class="admin-dual-pane <?= $is_edit_conv ? 'mobile-hidden' : '' ?>">
            <h3 style="margin-top: 0; color: #333;">Title Naming Conventions</h3>
            <div class="admin-table-container">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Convention Code</th>
                    <th style="padding: 8px 10px;">Example / Description</th>
                    <th style="padding: 8px 10px; text-align: right;">Cuv&eacute;es</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($conventions as $conv): ?>
                    <?php 
                      $cCode = $conv['nameconvention']; 
                      $mCount = (int)$conv['total_masters'];
                      $isEditingThis = ($editingConv && $editingConv['nameconvention'] === $cCode);
                    ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                      <td data-label="Convention Code" class="card-primary-cell" style="padding: 8px 10px; font-family: monospace; font-weight: bold; color: #222; font-size: 0.88rem;">
                        <?php echo htmlspecialchars($cCode, ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td data-label="Example / Description" style="padding: 8px 10px; color: #555; font-size: 0.85rem;">
                        <?php echo htmlspecialchars($conv['description'], ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td data-label="Cuv&eacute;es" style="padding: 8px 10px; text-align: right;">
                        <?php echo $mCount; ?>
                      </td>
                      <td data-label="Actions" style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                        <a href="manageWineAttributes.php?tab=conventions&edit_conv=<?php echo urlencode($cCode); ?>" style="color: #1a73e8; text-decoration: none; margin-right: 8px; font-size: 0.85rem;">
                          Edit
                        </a>
                        <?php if ($mCount === 0): ?>
                          <form action="manageWineAttributes.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete naming convention \'<?php echo htmlspecialchars($cCode, ENT_QUOTES, 'UTF-8'); ?>\'?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_conv">
                            <input type="hidden" name="nameconvention" value="<?php echo htmlspecialchars($cCode, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Linked to master cuvées">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Add / Edit Convention Form -->
          <div id="adminDualFormPane" class="admin-dual-pane <?= !$is_edit_conv ? 'mobile-hidden' : '' ?>">
            <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
              <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <?php echo $editingConv ? 'Edit Naming Convention' : 'Add Naming Convention'; ?>
              </h3>

              <form action="manageWineAttributes.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="<?php echo $editingConv ? 'edit_conv' : 'add_conv'; ?>">

                <div style="margin-bottom: 12px;">
                  <label for="nameconvention_code" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Convention Code:</label>
                  <?php if ($editingConv): ?>
                    <input type="text" id="nameconvention_code" name="nameconvention" readonly value="<?php echo htmlspecialchars($editingConv['nameconvention'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 7px; background: #eee; border: 1px solid #ccc; box-sizing: border-box;">
                  <?php else: ?>
                    <input type="text" id="nameconvention_code" name="nameconvention" maxlength="255" required placeholder="e.g. vintage_producer_appellation" style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                  <?php endif; ?>
                </div>

                <div style="margin-bottom: 16px;">
                  <label for="conv_desc" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Example / Description:</label>
                  <input type="text" id="conv_desc" name="description" maxlength="255" required value="<?php echo htmlspecialchars($editingConv['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. E.g. 2018 Château Margaux Margaux" style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
                </div>

                <div style="display: flex; gap: 8px;">
                  <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                    <?php echo $editingConv ? 'Update Convention' : '+ Add Convention'; ?>
                  </button>
                  <?php if ($editingConv): ?>
                    <a href="manageWineAttributes.php?tab=conventions" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
