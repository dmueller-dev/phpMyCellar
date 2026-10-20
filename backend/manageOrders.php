<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Order Management Dashboard.
   *
   * Combines purchase order creation, line item management, invoice document uploads,
   * delivery intake, and historical ledger tracking.
   * Required privileges: 'manage_orders' or 'add_order'
   */
  $can_manage_orders = hasPrivilege($conn, 'manage_orders');
  $can_add_order = hasPrivilege($conn, 'add_order');

  if (!$can_manage_orders && !$can_add_order) {
    header("Location: /backend/index.php");
    exit();
  }

  global $conn;

  $errors = [];
  $success_message = '';

  // Retrieve flash messages from session if any
  if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
  }

  // Check if we are editing an existing order
  $edit_order_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
  $is_edit = false;
  $prepopulated_items = [];
  $form_store_id = '';
  $form_order_date = date('Y-m-d');
  $form_shipping_paid = '0.00';
  $form_discount = '0.00';

  if ($edit_order_id) {
    $order_sql = "SELECT store_id, order_date, shipping_paid, discount, status FROM orders WHERE order_id = ?";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param("i", $edit_order_id);
    $stmt->execute();
    $stmt->bind_result($db_store_id, $db_order_date, $db_shipping_paid, $db_discount, $db_status);
    if ($stmt->fetch()) {
      if ($db_status !== 'pending delivery') {
        $errors[] = "Only open orders (pending delivery) can be edited.";
        $edit_order_id = null;
      } else {
        $is_edit = true;
        $form_store_id = $db_store_id;
        $form_order_date = $db_order_date;
        $form_shipping_paid = number_format($db_shipping_paid, 2, '.', '');
        $form_discount = number_format($db_discount, 2, '.', '');
      }
    } else {
      $errors[] = "Order not found.";
      $edit_order_id = null;
    }
    $stmt->close();

    if ($is_edit) {
      $prepopulated_items = getOrderItems($conn, $edit_order_id);
    }
  }

  // Handle saving an order (Create or Edit)
  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'save_order') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $post_edit_id = filter_input(INPUT_POST, 'edit_order_id', FILTER_VALIDATE_INT);
    $is_edit_post = (bool)$post_edit_id;

    $form_store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
    $form_order_date = sanitizeInput($_POST['order_date'] ?? '');
    
    $shipping_paid = filter_input(INPUT_POST, 'shipping_paid', FILTER_VALIDATE_FLOAT);
    $form_shipping_paid = ($shipping_paid !== false && $shipping_paid >= 0) ? $shipping_paid : 0.00;

    $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
    $form_discount = ($discount !== false && $discount >= 0) ? $discount : 0.00;

    $wine_ids = $_POST['wine_id'] ?? [];
    $formats = $_POST['format'] ?? [];
    $drink_froms = $_POST['drink_from'] ?? [];
    $drink_throughs = $_POST['drink_through'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $total_prices = $_POST['total_price'] ?? [];

    if (!$form_store_id) {
      $errors[] = "Please select a store.";
    }
    if (empty($form_order_date)) {
      $errors[] = "Please enter an order date.";
    }
    if (empty($wine_ids)) {
      $errors[] = "Please add at least one wine to this order.";
    }

    $valid_items = [];
    $total_order_qty = 0;

    for ($i = 0; $i < count($wine_ids); $i++) {
      $w_id = filter_var($wine_ids[$i], FILTER_VALIDATE_INT);
      $fmt = sanitizeInput($formats[$i] ?? '');
      $qty = filter_var($quantities[$i], FILTER_VALIDATE_INT);
      $price = filter_var($total_prices[$i], FILTER_VALIDATE_FLOAT);
      $df_raw = trim($drink_froms[$i] ?? '');
      $dt_raw = trim($drink_throughs[$i] ?? '');

      if (!$w_id) {
        $errors[] = "Line " . ($i + 1) . ": Invalid wine selected.";
        continue;
      }
      if (empty($fmt)) {
        $errors[] = "Line " . ($i + 1) . ": Please select a format.";
        continue;
      }
      if (!$qty || $qty <= 0) {
        $errors[] = "Line " . ($i + 1) . ": Quantity must be greater than 0.";
        continue;
      }
      if ($price === false || $price < 0) {
        $errors[] = "Line " . ($i + 1) . ": Total price must be greater than or equal to 0.";
        continue;
      }

      $df = null;
      $dt = null;
      if ($df_raw !== '') {
        if (!is_numeric($df_raw) || strlen($df_raw) != 4) {
          $errors[] = "Line " . ($i + 1) . ": 'Drink from' must be a 4-digit year (e.g. 2025).";
          continue;
        }
        $df = (int)$df_raw;
      }
      if ($dt_raw !== '') {
        if (!is_numeric($dt_raw) || strlen($dt_raw) != 4) {
          $errors[] = "Line " . ($i + 1) . ": 'Drink through' must be a 4-digit year (e.g. 2030).";
          continue;
        }
        $dt = (int)$dt_raw;
      }
      if ($df !== null && $dt !== null && $df > $dt) {
        $errors[] = "Line " . ($i + 1) . ": Start of drinking window ({$df}) must be before or equal to end of drinking window ({$dt}).";
        continue;
      }

      $valid_items[] = [
        'wine_id' => $w_id,
        'format' => $fmt,
        'quantity' => $qty,
        'total_price' => $price,
        'drink_from' => $df,
        'drink_through' => $dt
      ];
      $total_order_qty += $qty;
    }

    // Invoice file uploads
    $has_files = !empty($_FILES['invoice_files']['name'][0]);
    $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
    $allowed_mimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    $upload_queue = [];

    if ($has_files && empty($errors)) {
      for ($i = 0; $i < count($_FILES['invoice_files']['name']); $i++) {
        if ($_FILES['invoice_files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($_FILES['invoice_files']['error'][$i] !== UPLOAD_ERR_OK) {
          $errors[] = "Error uploading file: " . htmlspecialchars($_FILES['invoice_files']['name'][$i]);
          continue;
        }

        $file_name = $_FILES['invoice_files']['name'][$i];
        $file_size = $_FILES['invoice_files']['size'][$i];
        $file_tmp = $_FILES['invoice_files']['tmp_name'][$i];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (class_exists('finfo')) {
          $finfo = new finfo(FILEINFO_MIME_TYPE);
          $mime = $finfo->file($file_tmp);
        } else {
          $mime = $_FILES['invoice_files']['type'][$i];
        }

        if (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) {
          $errors[] = "File '" . htmlspecialchars($file_name) . "' has an invalid file type. Allowed: PDF, PNG, JPG, JPEG, GIF, WEBP.";
          continue;
        }
        if ($file_size > 10 * 1024 * 1024) {
          $errors[] = "File '" . htmlspecialchars($file_name) . "' exceeds the 10MB size limit.";
          continue;
        }

        $upload_queue[] = [
          'tmp_name' => $file_tmp,
          'original_name' => $file_name,
          'extension' => $ext
        ];
      }
    }

    if (!empty($errors)) {
      $prepopulated_items = [];
      for ($i = 0; $i < count($wine_ids); $i++) {
        if (!empty($wine_ids[$i])) {
          $prepopulated_items[] = [
            'wine_id' => $wine_ids[$i],
            'format' => $formats[$i] ?? '',
            'quantity' => $quantities[$i] ?? 1,
            'total_price' => $total_prices[$i] ?? '0.00',
            'drink_from' => $drink_froms[$i] ?? '',
            'drink_through' => $drink_throughs[$i] ?? ''
          ];
        }
      }
    }

    if (empty($errors)) {
      $conn->begin_transaction();
      try {
        if ($is_edit_post) {
          $sql_up = "UPDATE orders SET store_id = ?, order_date = ?, shipping_paid = ?, discount = ? WHERE order_id = ? AND status = 'pending delivery'";
          $stmt_up = $conn->prepare($sql_up);
          if (!$stmt_up) throw new Exception("Failed to prepare order update statement.");
          $stmt_up->bind_param("isddi", $form_store_id, $form_order_date, $form_shipping_paid, $form_discount, $post_edit_id);
          if (!$stmt_up->execute()) throw new Exception("Failed to update order details.");
          $stmt_up->close();
          $order_id = $post_edit_id;

          // Delete previously linked items and pending bottles
          $stmt_del = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
          $stmt_del->bind_param("i", $order_id);
          $stmt_del->execute();
          $stmt_del->close();

          $stmt_del_btl = $conn->prepare("DELETE FROM bottles WHERE order_id = ? AND status = 'pending delivery'");
          $stmt_del_btl->bind_param("i", $order_id);
          $stmt_del_btl->execute();
          $stmt_del_btl->close();
        } else {
          $order_id = insertOrder($conn, $form_store_id, $form_order_date, $form_shipping_paid, $form_discount, 'pending delivery');
          if (!$order_id) throw new Exception("Failed to insert the order into the database.");
        }

        // Insert Order Items and Create Pending Bottles
        foreach ($valid_items as $item) {
          $item_success = insertOrderItem($conn, $order_id, $item['wine_id'], $item['format'], $item['quantity'], $item['total_price']);
          if (!$item_success) throw new Exception("Failed to save order line items.");

          $shipping_share = ($total_order_qty > 0) ? ($form_shipping_paid / $total_order_qty) : 0.00;
          $discount_share = ($total_order_qty > 0) ? ($form_discount / $total_order_qty) : 0.00;
          $unit_price = ($item['total_price'] / $item['quantity']) + $shipping_share - $discount_share;
          $unit_price = max(0.00, round($unit_price, 2));

          for ($b = 0; $b < $item['quantity']; $b++) {
            $bottle_success = insertOrderBottle($conn, $item['wine_id'], $item['format'], $form_store_id, $form_order_date, $unit_price, 'pending delivery', $order_id, $item['drink_from'], $item['drink_through']);
            if (!$bottle_success) throw new Exception("Failed to automatically generate bottle records.");
          }
        }

        // Move and Record Uploaded Documents
        foreach ($upload_queue as $up_file) {
          $safe_name = "order_" . $order_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $up_file['extension'];
          $dest_path = __DIR__ . "/../uploads/invoices/" . $safe_name;

          if (move_uploaded_file($up_file['tmp_name'], $dest_path)) {
            chmod($dest_path, 0644);
            $doc_success = insertOrderDocument($conn, $order_id, "/uploads/invoices/" . $safe_name, $up_file['original_name']);
            if (!$doc_success) throw new Exception("Failed to link document '" . $up_file['original_name'] . "' with the order.");
          } else {
            throw new Exception("Failed to save uploaded file to invoices folder.");
          }
        }

        $conn->commit();

        if ($is_edit_post) {
          $_SESSION['success_message'] = "Order #{$order_id} successfully updated! {$total_order_qty} bottle records have been regenerated.";
        } else {
          $_SESSION['success_message'] = "Purchase order #{$order_id} created successfully! {$total_order_qty} bottles recorded with 'pending delivery' status.";
        }
        header("Location: manageOrders.php");
        exit();
      } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Transaction failed: " . $e->getMessage();
      }
    }
  }

  // Handle accepting delivery of an order
  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'accept_delivery') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $arrival_date = sanitizeInput($_POST['arrival_date'] ?? '');
    $bin_ids = $_POST['bin_id'] ?? [];
    $drink_froms = $_POST['drink_from'] ?? [];
    $drink_throughs = $_POST['drink_through'] ?? [];

    if (!$order_id) $errors[] = "Invalid order selected.";
    if (empty($arrival_date)) $errors[] = "Please select an arrival date.";

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
      $conn->begin_transaction();
      try {
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

        $pending_bottles = getPendingOrderBottles($conn, $order_id);
        $pending_bottle_ids = array_column($pending_bottles, 'bottle_id');

        $bin_counts = [];
        foreach ($bin_ids as $btl_id => $bin_id) {
          $btl_id = (int)$btl_id;
          $bin_id = empty($bin_id) ? null : (int)$bin_id;
          if (!in_array($btl_id, $pending_bottle_ids)) continue;
          if ($bin_id === null || $bin_id <= 0) {
            throw new Exception("Please assign a storage location for all bottles before accepting delivery.");
          }
          $bin_counts[$bin_id] = ($bin_counts[$bin_id] ?? 0) + 1;
        }

        foreach ($bin_counts as $b_id => $qty) {
          $capCheck = checkStorageBinCapacity($conn, $b_id, $qty);
          if (!$capCheck['allowed']) throw new Exception($capCheck['error']);
        }

        $up_bottle_sql = "UPDATE bottles SET storage_location = ?, arrival_date = ?, drink_from = ?, drink_through = ?, status = 'in cellar' WHERE bottle_id = ? AND order_id = ?";
        $stmt_up = $conn->prepare($up_bottle_sql);
        if (!$stmt_up) throw new Exception("Failed to prepare bottle update statement.");

        foreach ($bin_ids as $btl_id => $bin_id) {
          $btl_id = (int)$btl_id;
          $bin_id = empty($bin_id) ? null : (int)$bin_id;
          $df = (!empty($drink_froms[$btl_id]) && is_numeric($drink_froms[$btl_id])) ? (int)$drink_froms[$btl_id] : null;
          $dt = (!empty($drink_throughs[$btl_id]) && is_numeric($drink_throughs[$btl_id])) ? (int)$drink_throughs[$btl_id] : null;

          if (!in_array($btl_id, $pending_bottle_ids)) continue;
          if ($bin_id === null || $bin_id <= 0) throw new Exception("Please assign a storage location for all bottles.");

          $stmt_up->bind_param("isiisi", $bin_id, $arrival_date, $df, $dt, $btl_id, $order_id);
          if (!$stmt_up->execute()) throw new Exception("Error updating bottle #" . $btl_id);
        }
        $stmt_up->close();

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
  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    if (!$order_id) $errors[] = "Invalid order selected.";

    if (empty($errors)) {
      $conn->begin_transaction();
      try {
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

        $del_btl_sql = "DELETE FROM bottles WHERE order_id = ? AND status = 'pending delivery'";
        $stmt_del_btl = $conn->prepare($del_btl_sql);
        $stmt_del_btl->bind_param("i", $order_id);
        $stmt_del_btl->execute();
        $stmt_del_btl->close();

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

  // Fetch orders by status
  $open_orders = getOrders($conn, 'pending delivery');
  $closed_orders = getOrders($conn, 'delivered');
  $cancelled_orders = getOrders($conn, 'cancelled');

  // Pre-load lookup data
  $storage_locations = getStorageLocations($conn);
  $wines = getWines($conn);
  $formats_list = getFormats($conn);
  $stores = getStores($conn);

  // Active Tab
  $active_tab = 'open';
  if ($is_edit || (isset($_GET['action']) && $_GET['action'] === 'create') || (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'save_order')) {
    $active_tab = 'create';
  } elseif (isset($_GET['tab']) && in_array($_GET['tab'], ['open', 'create', 'closed', 'cancelled'], true)) {
    $active_tab = $_GET['tab'];
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Wine Order Management - Cellar Administration';

  $extra_head = <<<HTML
    <style>
      .tab-nav {
        display: flex;
        gap: 8px;
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
        font-family: inherit;
        font-size: 0.9rem;
        min-height: 40px;
      }
      .tab-btn.active {
        background: var(--secondary-accent, firebrick);
        color: white;
      }
      @media screen and (max-width: 720px) {
        .tab-nav {
          flex-wrap: nowrap;
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          padding-bottom: 8px;
        }
        .tab-btn {
          flex-shrink: 0;
          white-space: nowrap;
        }
        .order-card-header {
          flex-direction: column;
          align-items: flex-start;
          gap: 6px;
        }
        .order-table tr {
          display: block;
          background: #fff;
          border: 1px solid #ddd;
          border-radius: 8px;
          margin-bottom: 14px;
          padding: 12px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .order-table thead {
          display: none;
        }
        .order-table td {
          display: flex;
          flex-direction: column;
          align-items: flex-start;
          padding: 6px 0;
          border-bottom: 1px solid #f0f0f0;
        }
        .order-table td::before {
          content: attr(data-label);
          font-weight: 600;
          color: #555;
          margin-bottom: 4px;
          font-size: 0.82rem;
        }
        .order-table td:last-child {
          border-bottom: none;
          align-items: flex-end;
          padding-top: 8px;
        }
        .btn-remove {
          min-height: 44px;
          min-width: 80px;
          font-size: 0.85rem;
        }
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
      .order-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
      }
      .order-table th, .order-table td {
        padding: 8px 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
      }
      .order-table th {
        background-color: #f9f9f9;
        font-weight: bold;
        font-size: small;
      }
      .btn-remove {
        background-color: #e3342f;
        color: white;
        border: none;
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
      }
      .drag-drop-zone {
        border: 2px dashed var(--secondary-accent, firebrick);
        background-color: #fffafb;
        padding: 20px;
        text-align: center;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
        margin-bottom: 15px;
      }
    </style>

    <script>
      let allWines = [];

      document.addEventListener("DOMContentLoaded", function() {
        const masterSelect = document.getElementById('wine_master_source');
        if (masterSelect) {
          for (let i = 1; i < masterSelect.options.length; i++) {
            const opt = masterSelect.options[i];
            allWines.push({
              value: opt.value,
              text: opt.textContent
            });
          }
        }
HTML;

  if (!empty($prepopulated_items)) {
    foreach ($prepopulated_items as $item) {
      $w_id = (int)$item['wine_id'];
      $fmt = json_encode($item['format']);
      $qty = (int)$item['quantity'];
      $tot_pr = json_encode(number_format((float)$item['total_price'], 2, '.', ''));
      $d_from = json_encode($item['drink_from'] ?? '');
      $d_through = json_encode($item['drink_through'] ?? '');
      $extra_head .= "\n        addWineRow({$w_id}, {$fmt}, {$qty}, {$tot_pr}, {$d_from}, {$d_through});";
    }
  } else {
    $extra_head .= "\n        if (document.querySelectorAll('.order-item-row').length === 0) { addWineRow(); }";
  }

  $extra_head .= <<<HTML
      });

      function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => {
          el.style.display = 'none';
        });
        document.querySelectorAll('.tab-btn').forEach(el => {
          el.classList.remove('active');
        });
        
        const targetContent = document.getElementById(tabId + '_content');
        const targetBtn = document.getElementById(tabId + '_btn');
        if (targetContent) targetContent.style.display = 'block';
        if (targetBtn) targetBtn.classList.add('active');
      }

      function toggleDeliveryForm(orderId) {
        const form = document.getElementById('delivery_form_order_' + orderId);
        if (form.style.display === 'block') {
          form.style.display = 'none';
        } else {
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
        if (confirm("Are you sure you want to cancel Order #" + orderId + "? All associated pending bottle records will be deleted permanently.")) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <input type="hidden" name="action" value="cancel_order">
            <input type="hidden" name="order_id" value="\${orderId}">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      }

      function addWineRow(wineId = '', format = '', quantity = 1, totalPrice = '', drinkFrom = '', drinkThrough = '') {
        const tableBody = document.getElementById('order_items_body');
        if (!tableBody) return;
        const rowIndex = tableBody.children.length;
        
        const row = document.createElement('tr');
        row.className = 'order-item-row';
        row.id = 'row_' + rowIndex;

        let optionsHTML = '<option value="">-- Select Wine --</option>';
        allWines.forEach(w => {
          const selected = (w.value == wineId) ? 'selected' : '';
          optionsHTML += `<option value="\${w.value}" \${selected}>\${w.text}</option>`;
        });

        let formatsHTML = '';
        const formatsSelect = document.getElementById('format_master_source');
        if (formatsSelect) {
          const tempSelect = document.createElement('select');
          tempSelect.innerHTML = formatsSelect.innerHTML;
          if (format) {
            for (let i = 0; i < tempSelect.options.length; i++) {
              if (tempSelect.options[i].value === format) {
                tempSelect.options[i].selected = true;
              } else {
                tempSelect.options[i].selected = false;
              }
            }
          }
          formatsHTML = tempSelect.innerHTML;
        }

        row.innerHTML = `
          <td data-label="Wine Cuvée">
            <div style="position: relative; width: 100%;">
              <input type="text" class="search-box" placeholder="🔍 Filter wine..." onkeyup="filterWineDropdown(\${rowIndex})" style="width:100%; padding: 5px; font-size: small; box-sizing: border-box; margin-bottom:4px; border: 1px solid #ccc; border-radius: 4px;">
              <select name="wine_id[]" id="wine_select_\${rowIndex}" required style="width:100%; padding: 6px; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
                \${optionsHTML}
              </select>
            </div>
          </td>
          <td data-label="Format">
            <select name="format[]" required style="width:100%; padding: 6px; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
              \${formatsHTML}
            </select>
          </td>
          <td data-label="Drinking Window">
            <div style="display: flex; align-items: center; gap: 4px;">
              <input type="text" name="drink_from[]" maxlength="4" size="4" value="\${drinkFrom || ''}" placeholder="yyyy" title="Drink from (yyyy)" style="width:52px; padding: 5px; font-size: small; border: 1px solid #ccc; border-radius: 4px; text-align: center;">
              <span style="color: #666; font-size: small;">&ndash;</span>
              <input type="text" name="drink_through[]" maxlength="4" size="4" value="\${drinkThrough || ''}" placeholder="yyyy" title="Drink through (yyyy)" style="width:52px; padding: 5px; font-size: small; border: 1px solid #ccc; border-radius: 4px; text-align: center;">
            </div>
          </td>
          <td data-label="Qty">
            <input type="number" name="quantity[]" min="1" value="\${quantity}" required onchange="calculatePrices()" onkeyup="calculatePrices()" style="width:60px; padding: 5px; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
          </td>
          <td data-label="Total Price">
            <input type="number" name="total_price[]" step="0.01" min="0" value="\${totalPrice}" placeholder="0.00" required onchange="calculatePrices()" onkeyup="calculatePrices()" style="width:90px; padding: 5px; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
          </td>
          <td data-label="Action" style="text-align: center;">
            <button type="button" class="btn-remove" onclick="removeWineRow(\${rowIndex})">Remove</button>
          </td>
        `;
        tableBody.appendChild(row);
        calculatePrices();
      }

      function removeWineRow(index) {
        const row = document.getElementById('row_' + index);
        if (row) row.remove();
        calculatePrices();
      }

      function filterWineDropdown(index) {
        const searchInput = document.querySelector('#row_' + index + ' .search-box');
        const select = document.getElementById('wine_select_' + index);
        if (!searchInput || !select) return;

        const query = searchInput.value.toLowerCase().trim();
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        while (select.options.length > 1) {
          select.remove(1);
        }

        allWines.forEach(w => {
          const textLower = w.text.toLowerCase();
          const matches = terms.every(term => textLower.includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = w.value;
            opt.textContent = w.text;
            if (w.value === currentValue) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function calculatePrices() {
        let totalQty = 0;
        let totalItemsPrice = 0.00;
        
        const qtyInputs = document.getElementsByName('quantity[]');
        const priceInputs = document.getElementsByName('total_price[]');
        
        const shippingInput = document.getElementById('shipping_paid');
        const shippingPaid = shippingInput ? (parseFloat(shippingInput.value) || 0) : 0;

        const discountInput = document.getElementById('discount');
        const discount = discountInput ? (parseFloat(discountInput.value) || 0) : 0;

        for (let i = 0; i < qtyInputs.length; i++) {
          const qty = parseInt(qtyInputs[i].value) || 0;
          const price = parseFloat(priceInputs[i].value) || 0.00;
          totalQty += qty;
          totalItemsPrice += price;
        }

        const calcQty = document.getElementById('calc_total_qty');
        const calcItems = document.getElementById('calc_items_total');
        const calcOverall = document.getElementById('calc_overall_total');

        if (calcQty) calcQty.textContent = totalQty;
        if (calcItems) calcItems.textContent = totalItemsPrice.toFixed(2);
        
        const totalOverall = totalItemsPrice + shippingPaid - discount;
        if (calcOverall) calcOverall.textContent = totalOverall.toFixed(2);
      }

      function triggerFileInput() {
        const fileInput = document.getElementById('invoice_files');
        if (fileInput) fileInput.click();
      }

      function handleFileSelect(input) {
        const fileListDisplay = document.getElementById('file_list_display');
        if (!fileListDisplay) return;
        fileListDisplay.innerHTML = '';
        if (input.files.length > 0) {
          const title = document.createElement('p');
          title.style.margin = '5px 0 2px 0';
          title.style.fontSize = 'small';
          title.style.fontWeight = 'bold';
          title.textContent = 'Selected files for upload:';
          fileListDisplay.appendChild(title);

          const ul = document.createElement('ul');
          ul.style.margin = '0';
          ul.style.paddingLeft = '20px';
          ul.style.fontSize = '0.82rem';
          ul.style.color = '#555';

          for (let i = 0; i < input.files.length; i++) {
            const li = document.createElement('li');
            li.textContent = `\${input.files[i].name} (\${(input.files[i].size / 1024 / 1024).toFixed(2)} MB)`;
            ul.appendChild(li);
          }
          fileListDisplay.appendChild(ul);
        }
      }
    </script>
  HTML;

  require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hidden master dropdowns used as templates in JavaScript -->
<select id="wine_master_source" style="display: none;">
  <option value="">Select a wine</option>
  <?php foreach ($wines as $wine): ?>
    <option value="<?php echo $wine['wine_id']; ?>">
      <?php echo htmlspecialchars($wine['country'] . ": " . $wine['region'] . " - " . getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
    </option>
  <?php endforeach; ?>
</select>

<select id="format_master_source" style="display: none;">
  <?php foreach ($formats_list as $fmt): ?>
    <option value="<?php echo htmlspecialchars($fmt['format'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($fmt['format'] == '750ml' || $fmt['format'] == '0.75l' || $fmt['format'] == '75cl') ? 'selected' : ''; ?>>
      <?php echo htmlspecialchars($fmt['format_desc'] ?: $fmt['format'], ENT_QUOTES, 'UTF-8'); ?>
    </option>
  <?php endforeach; ?>
</select>

<div class="row">
  <div class="column main" style="width: 100%; max-width: 1200px; margin: 0 auto; float: none;">
    
    <div style="margin-bottom: 16px;">
      <a href="index.php" style="text-decoration: none; color: var(--secondary-accent, firebrick); font-size: 0.95rem;">
        &larr; Return to Admin Hub
      </a>
    </div>

    <div class="card">
      <div class="admin-card-header">
        <h2 style="margin: 0; color: var(--secondary-accent, firebrick);">
          &#128230; Wine Purchase Orders &amp; Intake
        </h2>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="manageBottles.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Bottles &rarr;
          </a>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div style="background-color: #ffdddd; border-left: 5px solid #f44336; padding: 12px 16px; margin-bottom: 20px;">
          <strong style="color: #a00;">Please correct the following errors:</strong>
          <ul style="margin: 8px 0 0; padding-left: 20px; color: #a00;">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 12px 16px; margin-bottom: 20px; color: #2e7d32; line-height: 1.5;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <!-- High-level Order Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: pointer;" onclick="switchTab('open')">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo count($open_orders); ?></span>
            <span class="kpi-icon">&#128230;</span>
          </div>
          <div class="kpi-label">Pending Deliveries</div>
          <div class="kpi-sub">Awaiting cellar intake</div>
        </div>

        <div class="admin-kpi-card" style="cursor: pointer;" onclick="switchTab('create')">
          <div class="kpi-top">
            <span class="kpi-val">&#10010;</span>
            <span class="kpi-icon">&#128221;</span>
          </div>
          <div class="kpi-label"><?php echo $is_edit ? "Edit Order #{$edit_order_id}" : "Create Order"; ?></div>
          <div class="kpi-sub">Record new wine acquisition</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: pointer;" onclick="switchTab('closed')">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo count($closed_orders); ?></span>
            <span class="kpi-icon">&#9989;</span>
          </div>
          <div class="kpi-label">Delivered Orders</div>
          <div class="kpi-sub">Completed cellar deliveries</div>
        </div>

        <div class="admin-kpi-card" style="cursor: pointer;" onclick="switchTab('cancelled')">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo count($cancelled_orders); ?></span>
            <span class="kpi-icon">&#10060;</span>
          </div>
          <div class="kpi-label">Cancelled Orders</div>
          <div class="kpi-sub">Revoked purchases</div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="tab-nav">
        <button id="open_btn" class="tab-btn <?php echo ($active_tab === 'open') ? 'active' : ''; ?>" onclick="switchTab('open')">
          Pending Deliveries (<?php echo count($open_orders); ?>)
        </button>
        <button id="create_btn" class="tab-btn <?php echo ($active_tab === 'create') ? 'active' : ''; ?>" onclick="switchTab('create')">
          <?php echo $is_edit ? "✎ Edit Order #{$edit_order_id}" : "➕ Create Purchase Order"; ?>
        </button>
        <button id="closed_btn" class="tab-btn <?php echo ($active_tab === 'closed') ? 'active' : ''; ?>" onclick="switchTab('closed')">
          Order History (<?php echo count($closed_orders); ?>)
        </button>
        <button id="cancelled_btn" class="tab-btn <?php echo ($active_tab === 'cancelled') ? 'active' : ''; ?>" onclick="switchTab('cancelled')">
          Cancelled (<?php echo count($cancelled_orders); ?>)
        </button>
      </div>

      <!-- TAB 1: OPEN ORDERS -->
      <div id="open_content" class="tab-content" style="display: <?php echo ($active_tab === 'open') ? 'block' : 'none'; ?>;">
        <?php if (empty($open_orders)): ?>
          <div style="text-align: center; color: #777; padding: 40px; border: 1px dashed #ccc; border-radius: 6px; background-color: #fcfcfc;">
            <p style="margin: 0 0 10px 0; font-size: 16px;">No open orders found.</p>
            <button type="button" class="btn-action" onclick="switchTab('create')" style="font-size: 0.9rem;">
              Click here to record a new purchase order...
            </button>
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
                  <strong style="color: var(--secondary-accent, firebrick); font-size: 15px;">&euro;<?php echo number_format($overall_total, 2); ?></strong>
                </div>
              </div>

              <div class="order-card-body">
                <?php if (!empty($docs)): ?>
                  <div style="margin-bottom: 12px; border-bottom: 1px solid #f3f4f6; padding-bottom: 8px;">
                    <strong style="font-size: small; color: #555; display: block; margin-bottom: 5px;">Attached Documents:</strong>
                    <?php foreach ($docs as $doc): ?>
                      <a href="<?php echo htmlspecialchars($doc['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="doc-badge" title="Click to view file">
                        &#128196; <?php echo htmlspecialchars($doc['file_name'], ENT_QUOTES, 'UTF-8'); ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="admin-table-container">
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
                        $wine_title = getWineName($item['nameconvention'], $item['vintage'], $item['name'], $item['producer'], $item['grape'], $item['vineyard']);
                        $shipping_share = ($total_qty > 0) ? ($order['shipping_paid'] / $total_qty) : 0;
                        $discount_share = ($total_qty > 0) ? (($order['discount'] ?? 0) / $total_qty) : 0;
                        $unit_cost = ($item['total_price'] / $item['quantity']) + $shipping_share - $discount_share;
                      ?>
                        <tr>
                          <td data-label="Wine Details" class="card-primary-cell">
                            <strong><?php echo htmlspecialchars($wine_title, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span style="font-size: 11px; color: #777; display: block;"><?php echo htmlspecialchars($item['country'] . ': ' . $item['region'], ENT_QUOTES, 'UTF-8'); ?></span>
                          </td>
                          <td data-label="Format"><?php echo htmlspecialchars($item['format'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td data-label="Drinking Window" style="text-align: center;">
                            <?php 
                              if (!empty($item['drink_from']) || !empty($item['drink_through'])) {
                                echo htmlspecialchars(($item['drink_from'] ?? '?') . ' – ' . ($item['drink_through'] ?? '?'));
                              } else {
                                echo '<span style="color:#aaa;">—</span>';
                              }
                            ?>
                          </td>
                          <td data-label="Qty" style="text-align: center;"><?php echo $item['quantity']; ?></td>
                          <td data-label="Total Price" style="text-align: right;">&euro;<?php echo number_format($item['total_price'], 2); ?></td>
                          <td data-label="Proportional Price/btl" style="text-align: right; color: #777;">&euro;<?php echo number_format($unit_cost, 2); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px; align-items: center; flex-wrap: wrap;">
                  <button type="button" class="btn-action" onclick="toggleDeliveryForm(<?php echo $order_id; ?>)" style="font-size: 13px; padding: 6px 14px;">
                    &#127870; Accept Delivery &amp; Assign Storage
                  </button>
                  <a href="manageOrders.php?edit=<?php echo $order_id; ?>" class="btn-action btn-secondary" style="font-size: 13px; padding: 6px 14px; text-decoration: none;">
                    &#9998; Edit Order
                  </a>
                  <button type="button" class="btn-action btn-secondary" onclick="confirmCancelOrder(<?php echo $order_id; ?>)" style="font-size: 13px; padding: 6px 14px; color: #a00;">
                    Cancel Order
                  </button>
                </div>

                <!-- Hidden Delivery Acceptance Form -->
                <div id="delivery_form_order_<?php echo $order_id; ?>" class="delivery-form-container">
                  <h4 style="margin: 0 0 10px 0; color: var(--secondary-accent, firebrick);">Accept Delivery for Order #<?php echo $order_id; ?></h4>
                  <form method="POST" accept-charset="UTF-8">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="accept_delivery">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

                    <div style="display: flex; gap: 15px; margin-bottom: 12px; align-items: center; flex-wrap: wrap;">
                      <div>
                        <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 4px;">Arrival Date:</label>
                        <input type="date" name="arrival_date" required value="<?php echo date('Y-m-d'); ?>" style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                      </div>

                      <div style="margin-left: auto; display: flex; align-items: flex-end; gap: 8px;">
                        <div>
                          <label style="font-size: 11px; font-weight: bold; display: block; margin-bottom: 4px; color: #555;">Quick Set All Bins:</label>
                          <select id="bulk_location_order_<?php echo $order_id; ?>" style="padding: 5px; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">-- Choose bin for all --</option>
                            <?php foreach ($storage_locations as $sl): ?>
                              <option value="<?php echo $sl['bin_id']; ?>"><?php echo htmlspecialchars($sl['cellar_name'] . ': ' . $sl['bin_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <button type="button" class="btn-action btn-secondary" onclick="applyBulkLocation(<?php echo $order_id; ?>)" style="padding: 5px 10px; font-size: small;">Apply</button>
                      </div>
                    </div>

                    <div class="pending-bottles-grid">
                      <?php foreach ($pending_bottles as $b): ?>
                        <div class="bottle-row">
                          <div style="font-weight: bold; font-size: 12px; margin-bottom: 4px;">
                            Bottle #<?php echo $b['bottle_id']; ?>: <?php echo htmlspecialchars(getWineName($b['nameconvention'], $b['vintage'], $b['name'], $b['producer'], $b['grape'], $b['vineyard']), ENT_QUOTES, 'UTF-8'); ?>
                          </div>
                          <div>
                            <select name="bin_id[<?php echo $b['bottle_id']; ?>]" required style="width: 100%; padding: 4px; font-size: small; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 4px;">
                              <option value="">-- Assign bin --</option>
                              <?php foreach ($storage_locations as $sl): ?>
                                <option value="<?php echo $sl['bin_id']; ?>"><?php echo htmlspecialchars($sl['cellar_name'] . ': ' . $sl['bin_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 15px; text-align: right;">
                      <button type="submit" class="btn-action" style="padding: 8px 18px; font-size: small;">Confirm Cellar Intake</button>
                    </div>
                  </form>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- TAB 2: CREATE / EDIT ORDER -->
      <div id="create_content" class="tab-content" style="display: <?php echo ($active_tab === 'create') ? 'block' : 'none'; ?>;">
        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px;">
          <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <?php echo $is_edit ? "✎ Edit Purchase Order #{$edit_order_id}" : "➕ Record New Wine Purchase Order"; ?>
          </h3>

          <form method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="save_order">
            <?php if ($is_edit): ?>
              <input type="hidden" name="edit_order_id" value="<?php echo (int)$edit_order_id; ?>">
            <?php endif; ?>

            <div class="form-row-3col" style="margin-bottom: 16px;">
              <div>
                <label for="store_id" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Purchased From (Store): <span style="color: red;">*</span>
                </label>
                <select name="store_id" id="store_id" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="">-- Select Store --</option>
                  <?php foreach ($stores as $store): ?>
                    <option value="<?php echo (int)$store['store_id']; ?>" <?php echo ((int)$form_store_id === (int)$store['store_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($store['store_name'] . ' (' . $store['country'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="order_date" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Order Date: <span style="color: red;">*</span>
                </label>
                <input type="date" name="order_date" id="order_date" required value="<?php echo htmlspecialchars($form_order_date, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>

              <div>
                <label for="shipping_paid" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Shipping Paid (&euro;):
                </label>
                <input type="number" name="shipping_paid" id="shipping_paid" step="0.01" min="0" value="<?php echo htmlspecialchars($form_shipping_paid, ENT_QUOTES, 'UTF-8'); ?>" onchange="calculatePrices()" onkeyup="calculatePrices()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>
            </div>

            <div class="form-row-3col" style="margin-bottom: 20px;">
              <div>
                <label for="discount" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Order Discount (&euro;):
                </label>
                <input type="number" name="discount" id="discount" step="0.01" min="0" value="<?php echo htmlspecialchars($form_discount, ENT_QUOTES, 'UTF-8'); ?>" onchange="calculatePrices()" onkeyup="calculatePrices()" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              </div>
            </div>

            <h4 style="margin: 20px 0 8px; color: #333; font-size: 1.05rem;">Order Line Items</h4>
            <div class="admin-table-container" style="overflow-x: auto; margin-bottom: 12px;">
              <table class="order-table" style="min-width: 650px;">
                <thead>
                  <tr>
                    <th style="width: 38%;">Wine Cuv&eacute;e</th>
                    <th style="width: 16%;">Format</th>
                    <th style="width: 18%;">Drinking Window</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 12%;">Total Price</th>
                    <th style="width: 6%; text-align: center;">Action</th>
                  </tr>
                </thead>
                <tbody id="order_items_body">
                  <!-- Dynamically populated via JS -->
                </tbody>
              </table>
            </div>

            <div style="margin-bottom: 20px;">
              <button type="button" class="btn-action" onclick="addWineRow()" style="padding: 8px 16px; font-size: 0.88rem;">
                &#10010; Add Wine Line
              </button>
            </div>

            <!-- Price Totals Box -->
            <div style="background-color: #fcfcfc; border: 1px solid #ddd; padding: 15px; border-radius: 6px; margin-bottom: 24px; display: flex; justify-content: flex-end;">
              <div style="text-align: right; line-height: 1.6; font-size: small;">
                <div>Total Bottles: <strong id="calc_total_qty">0</strong></div>
                <div>Wine Subtotal: &euro;<strong id="calc_items_total">0.00</strong></div>
                <div style="border-top: 1px solid #ccc; margin-top: 5px; padding-top: 5px; font-size: 15px;">
                  Total Order Value: <strong style="color: var(--secondary-accent, firebrick);">&euro;<span id="calc_overall_total">0.00</span></strong>
                </div>
              </div>
            </div>

            <h4 style="margin: 20px 0 8px; color: #333; font-size: 1.05rem;">Invoice Documents</h4>
            <div class="drag-drop-zone" onclick="triggerFileInput()">
              <div style="font-size: 24px; margin-bottom: 8px;">&#128196;</div>
              <strong style="font-size: small; color: var(--secondary-accent, firebrick);">Click here to select files</strong> or drag-and-drop them
              <p style="margin: 5px 0 0 0; font-size: 11px; color: #666;">Allowed formats: PDF, PNG, JPG, JPEG, GIF, WEBP (Max 10MB per file)</p>
            </div>
            <input type="file" name="invoice_files[]" id="invoice_files" multiple onchange="handleFileSelect(this)" style="display: none;">
            <div id="file_list_display" style="margin-bottom: 25px;"></div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                &#128190; <?php echo $is_edit ? "Update Purchase Order" : "Save Purchase Order"; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageOrders.php" class="btn-action btn-secondary" style="padding: 12px 20px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
                  Cancel
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <!-- TAB 3: DELIVERED ORDERS -->
      <div id="closed_content" class="tab-content" style="display: <?php echo ($active_tab === 'closed') ? 'block' : 'none'; ?>;">
        <?php if (empty($closed_orders)): ?>
          <div style="text-align: center; color: #777; padding: 40px; border: 1px dashed #ccc; border-radius: 6px;">
            No delivered orders in ledger yet.
          </div>
        <?php else: ?>
          <?php foreach ($closed_orders as $order): 
            $order_id = $order['order_id'];
            $items = getOrderItems($conn, $order_id);
            $docs = getOrderDocuments($conn, $order_id);
            $total_qty = array_sum(array_column($items, 'quantity'));
            $total_value = array_sum(array_column($items, 'total_price'));
            $overall_total = $total_value + $order['shipping_paid'] - ($order['discount'] ?? 0.00);
          ?>
            <div class="order-card">
              <div class="order-card-header">
                <div>
                  <span style="background-color: #2e7d32; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-right: 10px;">DELIVERED</span>
                  <strong>Order #<?php echo $order_id; ?></strong>
                  <span style="color: #666; font-size: 13px; margin-left: 10px;">from <strong><?php echo htmlspecialchars($order['store_name'], ENT_QUOTES, 'UTF-8'); ?></strong> on <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                </div>
                <div>
                  <strong>&euro;<?php echo number_format($overall_total, 2); ?></strong> (<?php echo $total_qty; ?> btls)
                </div>
              </div>
              <div class="order-card-body">
                <?php if (!empty($docs)): ?>
                  <div style="margin-bottom: 10px;">
                    <?php foreach ($docs as $doc): ?>
                      <a href="<?php echo htmlspecialchars($doc['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="doc-badge">
                        &#128196; <?php echo htmlspecialchars($doc['file_name'], ENT_QUOTES, 'UTF-8'); ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="admin-table-container">
                  <table class="order-items-detail">
                    <thead>
                      <tr>
                        <th>Wine Details</th>
                        <th>Format</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Total Price</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($items as $item): ?>
                        <tr>
                          <td data-label="Wine Details" class="card-primary-cell"><?php echo htmlspecialchars(getWineName($item['nameconvention'], $item['vintage'], $item['name'], $item['producer'], $item['grape'], $item['vineyard']), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td data-label="Format"><?php echo htmlspecialchars($item['format'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td data-label="Qty" style="text-align: center;"><?php echo $item['quantity']; ?></td>
                          <td data-label="Total Price" style="text-align: right;">&euro;<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- TAB 4: CANCELLED ORDERS -->
      <div id="cancelled_content" class="tab-content" style="display: <?php echo ($active_tab === 'cancelled') ? 'block' : 'none'; ?>;">
        <?php if (empty($cancelled_orders)): ?>
          <div style="text-align: center; color: #777; padding: 40px; border: 1px dashed #ccc; border-radius: 6px;">
            No cancelled orders found.
          </div>
        <?php else: ?>
          <?php foreach ($cancelled_orders as $order): 
            $order_id = $order['order_id'];
            $items = getOrderItems($conn, $order_id);
          ?>
            <div class="order-card" style="opacity: 0.8;">
              <div class="order-card-header">
                <div>
                  <span style="background-color: #666; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-right: 10px;">CANCELLED</span>
                  <strong>Order #<?php echo $order_id; ?></strong>
                  <span style="color: #666; font-size: 13px; margin-left: 10px;">from <strong><?php echo htmlspecialchars($order['store_name'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
