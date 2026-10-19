<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Manage wine merchants, retailers, and purchase sources.
   *
   * Required privilege: 'manage_stores' (or 'manage_orders')
   */
  if (!hasPrivilege($conn, 'manage_stores') && !hasPrivilege($conn, 'manage_orders')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Selected store for editing
  $editStoreId = filter_input(INPUT_GET, 'edit_store', FILTER_VALIDATE_INT);
  $editingStore = null;
  if ($editStoreId) {
    $editingStore = getStoreDetails($conn, $editStoreId);
    if (!$editingStore) {
      $errors[] = "Wine merchant not found.";
      $editStoreId = null;
    }
  }

  // Handle Form Submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');

    // Action: Add Store
    if ($action === 'add_store') {
      $store_name = sanitizeInput($_POST['store_name'] ?? '');
      $country = sanitizeInput($_POST['country'] ?? '');
      $address = sanitizeInput($_POST['address'] ?? '');

      try {
        $newId = insertStore($conn, $store_name, $country, $address);
        if ($newId) {
          $success_message = "Wine merchant '" . htmlspecialchars($store_name, ENT_QUOTES, 'UTF-8') . "' added successfully.";
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Edit Store
    elseif ($action === 'edit_store') {
      $store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
      $store_name = sanitizeInput($_POST['store_name'] ?? '');
      $country = sanitizeInput($_POST['country'] ?? '');
      $address = sanitizeInput($_POST['address'] ?? '');

      try {
        if (updateStore($conn, $store_id, $store_name, $country, $address)) {
          $success_message = "Wine merchant updated successfully.";
          $editStoreId = null;
          $editingStore = null;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    // Action: Delete Store
    elseif ($action === 'delete_store') {
      $store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
      if (empty($store_id)) {
        $errors[] = "Invalid merchant ID.";
      } else {
        try {
          if (deleteStore($conn, $store_id)) {
            $success_message = "Wine merchant removed successfully.";
          }
        } catch (Exception $e) {
          $errors[] = $e->getMessage();
        }
      }
    }
  }

  // Fetch all stores and countries
  $stores = getStoresWithStats($conn);
  $countries = getCountries($conn);

  // Calculate summary metrics
  $totalStores = count($stores);
  $totalBottlesSourced = 0;
  $totalOrdersSourced = 0;
  foreach ($stores as $s) {
    $totalBottlesSourced += (int)$s['total_bottles'];
    $totalOrdersSourced += (int)$s['total_orders'];
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Wine Merchants - Cellar Administration';
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
          &#127978; Wine Merchants &amp; Retailers
        </h2>
        <div>
          <?php if (hasPrivilege($conn, 'manage_orders') || hasPrivilege($conn, 'add_order')): ?>
            <a href="manageOrders.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px; margin-right: 8px;">
              Manage Orders &rarr;
            </a>
            <a href="manageOrders.php?action=create" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
              + New Order
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
            <span class="kpi-val"><?php echo number_format($totalStores); ?></span>
            <span class="kpi-icon">&#127978;</span>
          </div>
          <div class="kpi-label">Recorded Merchants</div>
          <div class="kpi-sub">
            <span>Wine shops, négociants &amp; importers</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-blue" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalOrdersSourced); ?></span>
            <span class="kpi-icon">&#128230;</span>
          </div>
          <div class="kpi-label">Purchase Orders</div>
          <div class="kpi-sub">
            <span>Orders linked to stores</span>
          </div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalBottlesSourced); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Bottles Sourced</div>
          <div class="kpi-sub">
            <span>Inventory purchased from stores</span>
          </div>
        </div>
      </div>

      <?php $is_edit = !empty($editingStore); ?>
      <!-- Mobile Segmented View Switcher -->
      <div class="admin-mobile-pane-switch">
        <button type="button" id="tabBtnList" class="admin-mobile-pane-btn <?php echo !$is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('list')">
          📋 Merchants (<?php echo count($stores); ?>)
        </button>
        <button type="button" id="tabBtnForm" class="admin-mobile-pane-btn <?php echo $is_edit ? 'active' : ''; ?>" onclick="switchAdminMobilePane('form')">
          <?php echo $is_edit ? '✏️ Edit Merchant' : '➕ Add Merchant'; ?>
        </button>
      </div>

      <!-- Main Layout: Table on Left / Form on Right -->
      <div class="admin-storage-manage-grid">
        
        <!-- Stores List -->
        <div id="adminDualListPane" class="admin-dual-pane <?php echo $is_edit ? 'mobile-hidden' : ''; ?>">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <h3 style="margin: 0; color: #333;">Merchant Directory</h3>
            <input type="text" id="storeFilterInput" placeholder="Filter by merchant or country..." 
                   oninput="filterStoreTable()" 
                   style="padding: 6px 10px; font-size: 0.88rem; width: 240px; border: 1px solid #ccc; border-radius: 4px;">
          </div>

          <?php if (empty($stores)): ?>
            <p style="color: #666; font-style: italic;">No wine merchants have been added yet. Use the form to record your first store.</p>
          <?php else: ?>
            <div class="admin-table-container">
              <table id="storesTable" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background-color: #f7f7f7; text-align: left; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px 10px;">Merchant Name</th>
                    <th style="padding: 8px 10px;">Country</th>
                    <th style="padding: 8px 10px;">Address / Notes</th>
                    <th style="padding: 8px 10px; text-align: right;">Orders</th>
                    <th style="padding: 8px 10px; text-align: right;">Bottles</th>
                    <th style="padding: 8px 10px; text-align: right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($stores as $store): ?>
                    <?php
                      $sId = (int)$store['store_id'];
                      $oCount = (int)$store['total_orders'];
                      $bCount = (int)$store['total_bottles'];
                      $isEditingThis = ($editingStore && (int)$editingStore['store_id'] === $sId);
                    ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo $isEditingThis ? 'background-color: #fff9e6;' : ''; ?>">
                      <td style="padding: 8px 10px; font-weight: 600; color: #222;">
                        <?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td data-label="Country" style="padding: 8px 10px; color: #555;">
                        <?php echo htmlspecialchars($store['country'], ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td data-label="Address" style="padding: 8px 10px; color: #666; font-size: 0.85rem;">
                        <?php echo !empty($store['address']) ? htmlspecialchars($store['address'], ENT_QUOTES, 'UTF-8') : '&mdash;'; ?>
                      </td>
                      <td data-label="Orders" style="padding: 8px 10px; text-align: right;">
                        <?php echo $oCount; ?>
                      </td>
                      <td data-label="Bottles" style="padding: 8px 10px; text-align: right;">
                        <strong><?php echo $bCount; ?></strong>
                      </td>
                      <td style="padding: 8px 10px; text-align: right; white-space: nowrap;">
                        <a href="manageStores.php?edit_store=<?php echo $sId; ?>" style="color: #1a73e8; text-decoration: none; margin-right: 8px; font-size: 0.85rem;" title="Edit merchant details">
                          Edit
                        </a>
                        <?php if ($oCount === 0 && $bCount === 0): ?>
                          <form action="manageStores.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete wine merchant \'<?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?>\'?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_store">
                            <input type="hidden" name="store_id" value="<?php echo $sId; ?>">
                            <button type="submit" style="background: none; border: none; color: #d32f2f; cursor: pointer; padding: 0; font-size: 0.85rem; text-decoration: underline;" title="Delete unused merchant">
                              Delete
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color: #ccc; font-size: 0.85rem;" title="Cannot delete: Merchant is linked to orders or bottles">Delete</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar: Add / Edit Store Form -->
        <div id="adminDualFormPane" class="admin-dual-pane <?php echo !$is_edit ? 'mobile-hidden' : ''; ?>">
          <div style="background-color: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px;">
            <h3 style="margin-top: 0; color: var(--secondary-accent, firebrick); border-bottom: 1px solid #eee; padding-bottom: 6px;">
              <?php echo $editingStore ? 'Edit Wine Merchant' : 'Add New Merchant'; ?>
            </h3>

            <form action="manageStores.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
              <input type="hidden" name="action" value="<?php echo $editingStore ? 'edit_store' : 'add_store'; ?>">
              <?php if ($editingStore): ?>
                <input type="hidden" name="store_id" value="<?php echo (int)$editingStore['store_id']; ?>">
              <?php endif; ?>

              <div style="margin-bottom: 12px;">
                <label for="store_name" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Merchant / Store Name:</label>
                <input type="text" id="store_name" name="store_name" maxlength="50" required 
                       value="<?php echo htmlspecialchars($editingStore['store_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="e.g. Berry Bros. &amp; Rudd, Millésimes" 
                       style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;">
              </div>

              <div style="margin-bottom: 12px;">
                <label for="store_country" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Country:</label>
                <select name="country" id="store_country" required style="width: 100%; padding: 7px; font-size: 0.9rem;">
                  <option value="">-- Select Country --</option>
                  <?php foreach ($countries as $c): ?>
                    <?php 
                      $cName = $c['country'];
                      $sel = ($editingStore && $editingStore['country'] === $cName);
                    ?>
                    <option value="<?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $sel ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div style="margin-bottom: 16px;">
                <label for="store_address" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">Address, Website or Notes:</label>
                <textarea id="store_address" name="address" rows="3" maxlength="255"
                          placeholder="Optional address, website URL, or contact details"
                          style="width: 100%; padding: 7px; font-size: 0.9rem; box-sizing: border-box;"><?php echo htmlspecialchars($editingStore['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>

              <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-action" style="flex: 1; padding: 8px 14px; font-size: 0.9rem;">
                  <?php echo $editingStore ? 'Update Merchant' : '+ Create Merchant'; ?>
                </button>
                <?php if ($editingStore): ?>
                  <a href="manageStores.php" class="btn-action btn-secondary" style="text-decoration: none; padding: 8px 12px; font-size: 0.9rem; text-align: center;">
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
  function filterStoreTable() {
    const query = document.getElementById('storeFilterInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#storesTable tbody tr');
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(query) ? '' : 'none';
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
