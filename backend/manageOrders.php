<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  global $conn;

  $errors = [];
  $success_message = '';

  // Retrieve flash messages from session if any
  if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
  }

  // Handle accepting delivery of an order
  if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'accept_delivery') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $arrival_date = sanitizeInput($_POST['arrival_date'] ?? '');
    $bin_ids = $_POST['bin_id'] ?? []; // Map of bottle_id => bin_id
    $drink_froms = $_POST['drink_from'] ?? []; // Map of bottle_id => drink_from
    $drink_throughs = $_POST['drink_through'] ?? []; // Map of bottle_id => drink_through

    if (!$order_id) {
      $errors[] = "Invalid order selected.";
    }
    if (empty($arrival_date)) {
      $errors[] = "Please select an arrival date.";
    }

    // Validate drinking windows for each bottle
    foreach ($bin_ids as $btl_id => $bin_id) {
      $df_raw = trim($drink_froms[$btl_id] ?? '');
      $dt_raw = trim($drink_throughs[$btl_id] ?? '');
      if ($df_raw !== '' && (!is_numeric($df_raw) || strlen($df_raw) != 4)) {
        $errors[] = "Bottle #{$btl_id}: 'Drink from' must be a 4-digit year (e.g. 2025).";
      }
      if ($dt_raw !== '' && (!is_numeric($dt_raw) || strlen($dt_raw) != 4)) {
        $errors[] = "Bottle #{$btl_id}: 'Drink through' must be a 4-digit year (e.g. 2030).";
      }
      if ($df_raw !== '' && $dt_raw !== '' && is_numeric($df_raw) && is_numeric($dt_raw) && (int)$df_raw > (int)$dt_raw) {
        $errors[] = "Bottle #{$btl_id}: Start of drinking window ({$df_raw}) must be before or equal to end of drinking window ({$dt_raw}).";
      }
    }

    if (empty($errors)) {
      // Begin transaction
      $conn->begin_transaction();
      try {
        // Double check order status
        $check_sql = "SELECT status FROM orders WHERE order_id = ? FOR UPDATE";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $order_id);
        $stmt_check->execute();
        $stmt_check->bind_result($order_status);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($order_status !== 'pending delivery') {
          throw new Exception("This order has already been delivered or is not in pending state.");
        }

        // Fetch pending bottles for this order to verify ownership
        $pending_bottles = getPendingOrderBottles($conn, $order_id);
        $pending_bottle_ids = array_column($pending_bottles, 'bottle_id');

        // Prepare statements for bulk updating
        $up_bottle_sql = "UPDATE bottles SET storage_location = ?, arrival_date = ?, drink_from = ?, drink_through = ?, status = 'in cellar' WHERE bottle_id = ? AND order_id = ?";
        $stmt_up = $conn->prepare($up_bottle_sql);
        if (!$stmt_up) {
          throw new Exception("Failed to prepare bottle update statement.");
        }

        foreach ($bin_ids as $btl_id => $bin_id) {
          $btl_id = (int)$btl_id;
          $bin_id = empty($bin_id) ? null : (int)$bin_id;
          $df = (!empty($drink_froms[$btl_id]) && is_numeric($drink_froms[$btl_id])) ? (int)$drink_froms[$btl_id] : null;
          $dt = (!empty($drink_throughs[$btl_id]) && is_numeric($drink_throughs[$btl_id])) ? (int)$drink_throughs[$btl_id] : null;

          // Check if bottle belongs to this order
          if (!in_array($btl_id, $pending_bottle_ids)) {
            continue;
          }

          if ($bin_id === null || $bin_id <= 0) {
            throw new Exception("Please assign a storage location for all bottles before accepting delivery.");
          }

          $stmt_up->bind_param("isiisi", $bin_id, $arrival_date, $df, $dt, $btl_id, $order_id);
          if (!$stmt_up->execute()) {
            throw new Exception("Error updating bottle #" . $btl_id);
          }
        }
        $stmt_up->close();

        // Update the order status to 'delivered'
        $up_order_sql = "UPDATE orders SET status = 'delivered' WHERE order_id = ?";
        $stmt_order = $conn->prepare($up_order_sql);
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $stmt_order->close();

        $conn->commit();
        $success_message = "Delivery accepted successfully! All bottles are now marked as 'in cellar' and filed in their assigned storage bins.";
      } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Failed to accept delivery: " . $e->getMessage();
      }
    }
  }

  // Handle cancelling an order
  if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

    if (!$order_id) {
      $errors[] = "Invalid order selected.";
    }

    if (empty($errors)) {
      $conn->begin_transaction();
      try {
        // Double check status
        $check_sql = "SELECT status FROM orders WHERE order_id = ? FOR UPDATE";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $order_id);
        $stmt_check->execute();
        $stmt_check->bind_result($order_status);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($order_status !== 'pending delivery') {
          throw new Exception("Only open orders (pending delivery) can be cancelled.");
        }

        // 1. Delete associated pending bottles from bottles table
        $del_btl_sql = "DELETE FROM bottles WHERE order_id = ? AND status = 'pending delivery'";
        $stmt_del_btl = $conn->prepare($del_btl_sql);
        $stmt_del_btl->bind_param("i", $order_id);
        $stmt_del_btl->execute();
        $stmt_del_btl->close();

        // 2. Set the order status to 'cancelled'
        $up_order_sql = "UPDATE orders SET status = 'cancelled' WHERE order_id = ?";
        $stmt_order = $conn->prepare($up_order_sql);
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $stmt_order->close();

        $conn->commit();
        $success_message = "Order #{$order_id} has been cancelled successfully. Associated pending bottle records have been removed.";
      } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Failed to cancel order: " . $e->getMessage();
      }
    }
  }

  // Fetch open, closed, and cancelled orders
  $open_orders = getOrders($conn, 'pending delivery');
  $closed_orders = getOrders($conn, 'delivered');
  $cancelled_orders = getOrders($conn, 'cancelled');

  // Fetch storage bins list
  $storage_locations = getStorageLocations($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();

  $page_title = 'Wine Order Management';

  $extra_head = <<<HTML
    <style>
      .tab-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 8px;
        flex-wrap: wrap;
      }
      .tab-btn {
        background: #eaeaea;
        color: #333;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-family: Georgia, serif;
        font-size: small;
      }
      .tab-btn.active {
        background: firebrick;
        color: white;
      }
      .order-card {
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
      }
      .order-card-header {
        background-color: #f7f7f7;
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
        flex-wrap: wrap;
        gap: 10px;
      }
      .order-card-body {
        padding: 15px;
      }
      .order-items-detail {
        width: 100%;
        border-collapse: collapse;
        font-size: small;
        margin-top: 10px;
      }
      .order-items-detail th, .order-items-detail td {
        padding: 6px 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
      }
      .order-items-detail th {
        background-color: #fcfcfc;
        color: #555;
      }
      .delivery-form-container {
        background-color: #fffafb;
        border: 1px solid #f2cfcf;
        border-radius: 6px;
        padding: 15px;
        margin-top: 15px;
        display: none;
      }
      .doc-badge {
        display: inline-block;
        background: #e2e8f0;
        color: #334155;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        text-decoration: none !important;
        margin-right: 5px;
        margin-bottom: 5px;
      }
      .doc-badge:hover {
        background: #cbd5e1;
      }
      .pending-bottles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 10px;
        margin-top: 10px;
        max-height: 250px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        padding: 10px;
        border-radius: 4px;
        background: white;
      }
      .bottle-row {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background: #f8fafc;
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #f1f5f9;
      }
    </style>

    <script>
      function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => {
          el.style.display = 'none';
        });
        document.querySelectorAll('.tab-btn').forEach(el => {
          el.classList.remove('active');
        });
        
        document.getElementById(tabId + '_content').style.display = 'block';
        document.getElementById(tabId + '_btn').classList.add('active');
      }

      function toggleDeliveryForm(orderId) {
        const form = document.getElementById('delivery_form_order_' + orderId);
        if (form.style.display === 'block') {
          form.style.display = 'none';
        } else {
          // Hide all others
          document.querySelectorAll('.delivery-form-container').forEach(el => {
            el.style.display = 'none';
          });
          form.style.display = 'block';
          form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      function applyBulkLocation(orderId) {
        const bulkSelect = document.getElementById('bulk_location_order_' + orderId);
        const selectedVal = bulkSelect.value;
        if (!selectedVal) {
          alert('Please select a storage location first.');
          return;
        }

        const btlSelects = document.querySelectorAll('#delivery_form_order_' + orderId + ' select[name^="bin_id"]');
        btlSelects.forEach(select => {
          select.value = selectedVal;
        });
      }

      function confirmCancelOrder(orderId) {
        if (confirm("Are you sure you want to cancel Order #" + orderId + "? This will permanently delete all pending delivery bottle records associated with this order.")) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = 'manageOrders.php';
          
          const csrfInput = document.createElement('input');
          csrfInput.type = 'hidden';
          csrfInput.name = 'csrf_token';
          csrfInput.value = '{$csrf_token}';
          
          const actionInput = document.createElement('input');
          actionInput.type = 'hidden';
          actionInput.name = 'action';
          actionInput.value = 'cancel_order';
          
          const orderInput = document.createElement('input');
          orderInput.type = 'hidden';
          orderInput.name = 'order_id';
          orderInput.value = orderId;
          
          form.appendChild(csrfInput);
          form.appendChild(actionInput);
          form.appendChild(orderInput);
          
          document.body.appendChild(form);
          form.submit();
        }
      }
    </script>
  HTML;

  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main" style="width: 100%; float: none;">
    <div class="card">
      <section>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 15px;">
          <h2 style="margin: 0; font-family: Georgia, serif;">Order Management</h2>
          <a href="addOrder.php" class="btn-action" style="font-size: small; background-color: firebrick;">➕ Create New Order</a>
        </div>

        <?php
          if (!empty($errors)) {
            echo "<div style='color: red; background-color: #fdf2f2; border: 1px solid #f8b4b4; padding: 12px; border-radius: 4px; margin-bottom: 15px;'><strong>Errors:</strong><ul>";
            foreach ($errors as $error) {
              echo "<li>" . $error . "</li>";
            }
            echo "</ul></div>";
          }

          if (!empty($success_message)) {
            echo "<div style='color: green; background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 4px; margin-bottom: 20px; line-height: 1.5;'>" . $success_message . "</div>";
          }
        ?>

        <!-- Tabs -->
        <div class="tab-nav">
          <button id="open_btn" class="tab-btn active" onclick="switchTab('open')">Pending Deliveries (<?php echo count($open_orders); ?>)</button>
          <button id="closed_btn" class="tab-btn" onclick="switchTab('closed')">Order History (<?php echo count($closed_orders); ?>)</button>
          <button id="cancelled_btn" class="tab-btn" onclick="switchTab('cancelled')">Cancelled Orders (<?php echo count($cancelled_orders); ?>)</button>
        </div>

        <!-- 1. OPEN ORDERS TAB -->
        <div id="open_content" class="tab-content" style="display: block;">
          <?php if (empty($open_orders)): ?>
            <div style="text-align: center; color: #777; padding: 40px; border: 1px dashed #ccc; border-radius: 6px; background-color: #fcfcfc;">
              <p style="margin: 0 0 10px 0; font-size: 16px;">No open orders found.</p>
              <a href="addOrder.php" style="color: firebrick;">Click here to record a new purchase order...</a>
            </div>
          <?php else: ?>
            <?php foreach ($open_orders as $order): 
              $order_id = $order['order_id'];
              $items = getOrderItems($conn, $order_id);
              $docs = getOrderDocuments($conn, $order_id);
              $pending_bottles = getPendingOrderBottles($conn, $order_id);
              $total_qty = array_sum(array_column($items, 'quantity'));
              $total_value = array_sum(array_column($items, 'total_price'));
              $overall_total = $total_value + $order['shipping_paid'] - ($order['discount'] ?? 0.00);
            ?>
              <div class="order-card">
                <div class="order-card-header">
                  <div>
                    <span style="background-color: indianred; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-right: 10px; vertical-align: middle;">OPEN</span>
                    <strong style="font-size: 15px;">Order #<?php echo $order_id; ?></strong>
                    <span style="color: #666; font-size: 13px; margin-left: 10px;">from <strong><?php echo htmlspecialchars($order['store_name'], ENT_QUOTES, 'UTF-8'); ?></strong> on <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                  </div>
                  <div>
                    <strong style="color: firebrick; font-size: 15px;">€<?php echo number_format($overall_total, 2); ?></strong>
                  </div>
                </div>

                <div class="order-card-body">
                  <!-- Documents list if they exist -->
                  <?php if (!empty($docs)): ?>
                    <div style="margin-bottom: 12px; border-bottom: 1px solid #f3f4f6; padding-bottom: 8px;">
                      <strong style="font-size: small; color: #555; display: block; margin-bottom: 5px;">Attached Documents:</strong>
                      <?php foreach ($docs as $doc): ?>
                        <a href="<?php echo htmlspecialchars($doc['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="doc-badge" title="Click to view file">
                          📄 <?php echo htmlspecialchars($doc['file_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <!-- Items list -->
                  <table class="order-items-detail">
                    <thead>
                      <tr>
                        <th>Wine Details</th>
                        <th>Format</th>
                        <th style="text-align: center;">Drinking Window</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Total Price</th>
                        <th style="text-align: right; color: #777;">Proportional Price/btl</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($items as $item): 
                        $shipping_share = ($total_qty > 0) ? ($order['shipping_paid'] / $total_qty) : 0.00;
                        $discount_share = ($total_qty > 0) ? (($order['discount'] ?? 0.00) / $total_qty) : 0.00;
                        $calc_btl_price = ($item['total_price'] / $item['quantity']) + $shipping_share - $discount_share;
                        $calc_btl_price = max(0.00, $calc_btl_price);
                        $window_display = (!empty($item['drink_from']) || !empty($item['drink_through'])) 
                          ? htmlspecialchars(($item['drink_from'] ?: '...') . ' – ' . ($item['drink_through'] ?: '...'), ENT_QUOTES, 'UTF-8') 
                          : '<span style="color: #bbb;">—</span>';
                      ?>
                        <tr>
                          <td>
                            <strong><?php echo htmlspecialchars($item['country'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($item['region'], ENT_QUOTES, 'UTF-8') . ")"; ?></strong>: 
                            <?php echo getWineName($item['nameconvention'], $item['vintage'], $item['name'], $item['producer'], $item['grape'], $item['vineyard']); ?>
                          </td>
                          <td><?php echo htmlspecialchars($item['format'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td style="text-align: center; font-size: small;"><?php echo $window_display; ?></td>
                          <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                          <td style="text-align: right;">€<?php echo number_format($item['total_price'], 2); ?></td>
                          <td style="text-align: right; color: #555; font-style: italic;">€<?php echo number_format($calc_btl_price, 2); ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr style="background-color: #fafbfc; font-weight: bold; border-top: 1px solid #ddd;">
                        <td colspan="3">Order Subtotal:</td>
                        <td style="text-align: center;"><?php echo $total_qty; ?></td>
                        <td style="text-align: right;">€<?php echo number_format($total_value, 2); ?></td>
                        <td></td>
                      </tr>
                      <?php if ($order['shipping_paid'] > 0): ?>
                        <tr style="color: #666; font-size: xs-small;">
                          <td colspan="4">Shipping & handling:</td>
                          <td style="text-align: right;">€<?php echo number_format($order['shipping_paid'], 2); ?></td>
                          <td></td>
                        </tr>
                      <?php endif; ?>
                      <?php if (($order['discount'] ?? 0.00) > 0.00): ?>
                        <tr style="color: green; font-size: xs-small; font-weight: bold;">
                          <td colspan="4">Applied Discount:</td>
                          <td style="text-align: right;">-€<?php echo number_format($order['discount'], 2); ?></td>
                          <td></td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>

                  <!-- Interactive Actions Row -->
                  <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; border-top: 1px solid #eee; padding-top: 15px;">
                    <div style="display: flex; gap: 8px;">
                      <a href="addOrder.php?edit=<?php echo $order_id; ?>" class="btn-action" style="background-color: #0d6efd; font-size: small; padding: 8px 15px;">✏️ Edit Order</a>
                      <button type="button" class="btn-action" onclick="confirmCancelOrder(<?php echo $order_id; ?>)" style="background-color: #dc3545; font-size: small; padding: 8px 15px;">❌ Cancel Order</button>
                    </div>
                    <button type="button" class="btn-action" onclick="toggleDeliveryForm(<?php echo $order_id; ?>)" style="background-color: darkgreen; font-weight: bold; padding: 8px 15px;">🚚 Accept Delivery & File Bottles</button>
                  </div>

                  <!-- 2. EXPANDED ACCEPT DELIVERY FORM -->
                  <div id="delivery_form_order_<?php echo $order_id; ?>" class="delivery-form-container">
                    <h4 style="margin: 0 0 10px 0; font-family: Georgia, serif; color: darkgreen; border-bottom: 1px solid #f2cfcf; padding-bottom: 4px;">Fulfill Delivery for Order #<?php echo $order_id; ?></h4>
                    <p style="font-size: 11px; color: #555; margin-top: 0;">Specify the arrival date and assign storage locations for each generated bottle below. You can assign them all to the same bin using the bulk options, or customize individual rows.</p>

                    <form method="POST" accept-charset="UTF-8">
                      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                      <input type="hidden" name="action" value="accept_delivery">
                      <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

                      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px; background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #f3d8d8;">
                        <div>
                          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 4px;">Arrival Date:</label>
                          <input type="date" name="arrival_date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 6px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                        </div>

                        <div>
                          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 4px;">Bulk Storage Assignment (All Bottles):</label>
                          <div style="display: flex; gap: 8px;">
                            <select id="bulk_location_order_<?php echo $order_id; ?>" style="flex: 1; padding: 6px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
                              <option value="">-- Choose Bin --</option>
                              <?php foreach ($storage_locations as $bin): ?>
                                <option value="<?php echo $bin['bin_id']; ?>">
                                  <?php echo htmlspecialchars($bin['cellar_name'] . " / " . $bin['bin_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-action" onclick="applyBulkLocation(<?php echo $order_id; ?>)" style="background-color: darkgreen; font-size: 11px; padding: 6px 10px;">Apply</button>
                          </div>
                        </div>
                      </div>

                      <strong style="font-size: small; color: #444;">Bottle Storage Allocation & Drinking Window Override:</strong>
                      <div class="pending-bottles-grid">
                        <?php foreach ($pending_bottles as $btl): ?>
                          <div class="bottle-row">
                            <div style="font-size: small; margin-bottom: 5px;">
                              <span style="color: firebrick; font-weight: bold;">Bottle #<?php echo $btl['bottle_id']; ?></span> - 
                              <strong><?php echo $btl['vintage'] ?: 'N/V'; ?></strong> <?php echo htmlspecialchars($btl['name'], ENT_QUOTES, 'UTF-8'); ?> 
                              <span style="font-size: 10px; color: #666;">(<?php echo htmlspecialchars($btl['format'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                              <div style="flex: 1; min-width: 180px;">
                                <select name="bin_id[<?php echo $btl['bottle_id']; ?>]" required style="width: 100%; padding: 4px; font-size: xs-small; font-family: Georgia, serif; border: 1px solid #ccc; border-radius: 4px;">
                                  <option value="">-- Select Storage Location --</option>
                                  <?php foreach ($storage_locations as $bin): ?>
                                    <option value="<?php echo $bin['bin_id']; ?>">
                                      <?php echo htmlspecialchars($bin['cellar_name'] . " / " . $bin['bin_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div style="display: flex; align-items: center; gap: 4px; font-size: 11px;">
                                <label style="color: #666;">Window:</label>
                                <input type="text" name="drink_from[<?php echo $btl['bottle_id']; ?>]" maxlength="4" size="4" value="<?php echo htmlspecialchars($btl['drink_from'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="yyyy" title="Drink from" style="width: 48px; padding: 3px; font-size: 11px; font-family: Georgia, serif; border: 1px solid #ccc; border-radius: 3px; text-align: center;">
                                <span>–</span>
                                <input type="text" name="drink_through[<?php echo $btl['bottle_id']; ?>]" maxlength="4" size="4" value="<?php echo htmlspecialchars($btl['drink_through'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="yyyy" title="Drink through" style="width: 48px; padding: 3px; font-size: 11px; font-family: Georgia, serif; border: 1px solid #ccc; border-radius: 3px; text-align: center;">
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>

                      <div style="margin-top: 15px; display: flex; justify-content: center; gap: 10px;">
                        <button type="submit" class="btn-action" style="background-color: firebrick; font-weight: bold; padding: 8px 20px;">Confirm Delivery Reception</button>
                        <button type="button" class="btn-action btn-secondary" onclick="toggleDeliveryForm(<?php echo $order_id; ?>)" style="padding: 8px 20px;">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- 2. ORDER HISTORY TAB -->
        <div id="closed_content" class="tab-content" style="display: none;">
          <?php if (empty($closed_orders)): ?>
            <p style="text-align: center; color: #777; padding: 30px;">No historical orders found.</p>
          <?php else: ?>
            <?php foreach ($closed_orders as $order): 
              $order_id = $order['order_id'];
              $items = getOrderItems($conn, $order_id);
              $docs = getOrderDocuments($conn, $order_id);
              $total_qty = array_sum(array_column($items, 'quantity'));
              $total_value = array_sum(array_column($items, 'total_price'));
              $overall_total = $total_value + $order['shipping_paid'] - ($order['discount'] ?? 0.00);
            ?>
              <div class="order-card" style="border-color: #cbd5e1; opacity: 0.85;">
                <div class="order-card-header" style="background-color: #f8fafc;">
                  <div>
                    <span style="background-color: #64748b; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-right: 10px; vertical-align: middle;">DELIVERED</span>
                    <strong style="font-size: 14px;">Order #<?php echo $order_id; ?></strong>
                    <span style="color: #666; font-size: 12px; margin-left: 10px;">from <strong><?php echo htmlspecialchars($order['store_name'], ENT_QUOTES, 'UTF-8'); ?></strong> on <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                  </div>
                  <div>
                    <strong style="color: #475569; font-size: 14px;">€<?php echo number_format($overall_total, 2); ?></strong>
                  </div>
                </div>

                <div class="order-card-body" style="padding: 10px 15px;">
                  <!-- Documents list if they exist -->
                  <?php if (!empty($docs)): ?>
                    <div style="margin-bottom: 8px;">
                      <?php foreach ($docs as $doc): ?>
                        <a href="<?php echo htmlspecialchars($doc['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="doc-badge" style="background-color: #f1f5f9; color: #475569;">
                          📄 <?php echo htmlspecialchars($doc['file_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <!-- Simple item counts -->
                  <div style="font-size: small; color: #555;">
                    Contains <strong><?php echo $total_qty; ?></strong> bottles of 
                    <?php 
                      $wine_names_list = array_map(function($itm) {
                        return $itm['quantity'] . 'x ' . ($itm['vintage'] ?: 'N/V') . ' ' . $itm['name'];
                      }, $items);
                      echo htmlspecialchars(implode(', ', $wine_names_list), ENT_QUOTES, 'UTF-8');
                    ?>.
                    <?php if (($order['discount'] ?? 0.00) > 0.00): ?>
                      <span style="color: green; font-weight: bold; margin-left: 5px;">(Saved €<?php echo number_format($order['discount'], 2); ?> discount)</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- 3. CANCELLED ORDERS TAB -->
        <div id="cancelled_content" class="tab-content" style="display: none;">
          <?php if (empty($cancelled_orders)): ?>
            <p style="text-align: center; color: #777; padding: 30px;">No cancelled orders found.</p>
          <?php else: ?>
            <?php foreach ($cancelled_orders as $order): 
              $order_id = $order['order_id'];
              $items = getOrderItems($conn, $order_id);
              $docs = getOrderDocuments($conn, $order_id);
              $total_qty = array_sum(array_column($items, 'quantity'));
              $total_value = array_sum(array_column($items, 'total_price'));
              $overall_total = $total_value + $order['shipping_paid'] - ($order['discount'] ?? 0.00);
            ?>
              <div class="order-card" style="border-color: #f1f5f9; opacity: 0.7;">
                <div class="order-card-header" style="background-color: #f8fafc;">
                  <div>
                    <span style="background-color: #94a3b8; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-right: 10px; vertical-align: middle;">CANCELLED</span>
                    <strong style="font-size: 14px; text-decoration: line-through;">Order #<?php echo $order_id; ?></strong>
                    <span style="color: #666; font-size: 12px; margin-left: 10px;">from <strong><?php echo htmlspecialchars($order['store_name'], ENT_QUOTES, 'UTF-8'); ?></strong> on <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                  </div>
                  <div>
                    <strong style="color: #64748b; font-size: 14px; text-decoration: line-through;">€<?php echo number_format($overall_total, 2); ?></strong>
                  </div>
                </div>

                <div class="order-card-body" style="padding: 10px 15px;">
                  <!-- Simple item counts -->
                  <div style="font-size: small; color: #777;">
                    Was for <strong><?php echo $total_qty; ?></strong> bottles of 
                    <?php 
                      $wine_names_list = array_map(function($itm) {
                        return $itm['quantity'] . 'x ' . ($itm['vintage'] ?: 'N/V') . ' ' . $itm['name'];
                      }, $items);
                      echo htmlspecialchars(implode(', ', $wine_names_list), ENT_QUOTES, 'UTF-8');
                    ?>.
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </section>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
