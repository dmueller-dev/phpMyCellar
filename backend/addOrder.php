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

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $store_id = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
    $order_date = sanitizeInput($_POST['order_date'] ?? '');
    $shipping_paid = filter_input(INPUT_POST, 'shipping_paid', FILTER_VALIDATE_FLOAT);
    if ($shipping_paid === false || $shipping_paid < 0) {
      $shipping_paid = 0.00;
    }

    // Retrieve arrays of wine line items
    $wine_ids = $_POST['wine_id'] ?? [];
    $formats = $_POST['format'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $total_prices = $_POST['total_price'] ?? [];

    // Basic Validations
    if (!$store_id) {
      $errors[] = "Please select a store.";
    }
    if (empty($order_date)) {
      $errors[] = "Please enter an order date.";
    }
    if (empty($wine_ids)) {
      $errors[] = "Please add at least one wine to this order.";
    }

    // Validate each line item
    $valid_items = [];
    $total_order_qty = 0;

    for ($i = 0; $i < count($wine_ids); $i++) {
      $w_id = filter_var($wine_ids[$i], FILTER_VALIDATE_INT);
      $fmt = sanitizeInput($formats[$i] ?? '');
      $qty = filter_var($quantities[$i], FILTER_VALIDATE_INT);
      $price = filter_var($total_prices[$i], FILTER_VALIDATE_FLOAT);

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

      $valid_items[] = [
        'wine_id' => $w_id,
        'format' => $fmt,
        'quantity' => $qty,
        'total_price' => $price
      ];
      $total_order_qty += $qty;
    }

    // File Upload Pre-validation
    $has_files = !empty($_FILES['invoice_files']['name'][0]);
    $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
    $allowed_mimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    $upload_queue = [];

    if ($has_files && empty($errors)) {
      for ($i = 0; $i < count($_FILES['invoice_files']['name']); $i++) {
        if ($_FILES['invoice_files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
          continue;
        }
        if ($_FILES['invoice_files']['error'][$i] !== UPLOAD_ERR_OK) {
          $errors[] = "Error uploading file: " . htmlspecialchars($_FILES['invoice_files']['name'][$i]);
          continue;
        }

        $file_name = $_FILES['invoice_files']['name'][$i];
        $file_size = $_FILES['invoice_files']['size'][$i];
        $file_tmp = $_FILES['invoice_files']['tmp_name'][$i];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Strict MIME-type checking
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
        if ($file_size > 10 * 1024 * 1024) { // 10MB
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

    // Proceed if there are no validation errors
    if (empty($errors)) {
      $conn->begin_transaction();
      try {
        // 1. Insert Order
        $order_id = insertOrder($conn, $store_id, $order_date, $shipping_paid, 'pending delivery');
        if (!$order_id) {
          throw new Exception("Failed to insert the order into the database.");
        }

        // 2. Insert Order Items and Create Bottles
        foreach ($valid_items as $item) {
          $item_success = insertOrderItem($conn, $order_id, $item['wine_id'], $item['format'], $item['quantity'], $item['total_price']);
          if (!$item_success) {
            throw new Exception("Failed to save order line items.");
          }

          // Calculate Proportional Price/Bottle:
          // price_per_bottle = (item_total_price / quantity) + (shipping_paid / total_order_qty)
          $shipping_share = ($total_order_qty > 0) ? ($shipping_paid / $total_order_qty) : 0.00;
          $unit_price = ($item['total_price'] / $item['quantity']) + $shipping_share;
          $unit_price = round($unit_price, 2);

          // Create individual bottle records in 'pending delivery' status
          for ($b = 0; $b < $item['quantity']; $b++) {
            $bottle_success = insertOrderBottle($conn, $item['wine_id'], $item['format'], $store_id, $order_date, $unit_price, 'pending delivery', $order_id);
            if (!$bottle_success) {
              throw new Exception("Failed to automatically generate bottle records.");
            }
          }
        }

        // 3. Move and Record Uploaded Documents
        foreach ($upload_queue as $up_file) {
          $safe_name = "order_" . $order_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $up_file['extension'];
          $dest_path = __DIR__ . "/../uploads/invoices/" . $safe_name;

          if (move_uploaded_file($up_file['tmp_name'], $dest_path)) {
            $doc_success = insertOrderDocument($conn, $order_id, "/uploads/invoices/" . $safe_name, $up_file['original_name']);
            if (!$doc_success) {
              throw new Exception("Failed to link document '" . $up_file['original_name'] . "' with the order.");
            }
          } else {
            throw new Exception("Failed to save uploaded file to invoices folder.");
          }
        }

        $conn->commit();
        $success_message = "Order successfully created! " . $total_order_qty . " bottles have been marked as 'pending delivery'.<br>" .
                           "• <a href='manageOrders.php'>Manage open orders & accept delivery</a><br>" .
                           "• <a href='addOrder.php'>Create another order</a>";
        
        // Clear variables for form resetting
        $store_id = '';
        $order_date = date('Y-m-d');
        $shipping_paid = '0.00';
        $wine_ids = [];
        $formats = [];
        $quantities = [];
        $total_prices = [];
      } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Transaction failed: " . $e->getMessage();
      }
    }
  }

  // Pre-populate dropdowns
  $wines = getWines($conn);
  $formats_list = getFormats($conn);
  $stores = getStores($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();

  $page_title = 'Create Wine Order';

  // Head Javascript for dynamic forms
  $extra_head = <<<HTML
    <style>
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
      .btn-remove:hover {
        background-color: #cc1f1a;
      }
      .drag-drop-zone {
        border: 2px dashed indianred;
        background-color: #fffafb;
        padding: 20px;
        text-align: center;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
        margin-bottom: 15px;
      }
      .drag-drop-zone:hover {
        background-color: #fff0f2;
      }
      .autocomplete-container {
        position: relative;
        width: 100%;
      }
    </style>

    <script>
      let allWines = [];

      document.addEventListener("DOMContentLoaded", function() {
        // Build JSON representation of wines from PHP for filtering
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
        
        // Add initial row if none exists
        if (document.querySelectorAll('.order-item-row').length === 0) {
          addWineRow();
        }
      });

      function addWineRow() {
        const tableBody = document.getElementById('order_items_body');
        const rowIndex = tableBody.children.length;
        
        const row = document.createElement('tr');
        row.className = 'order-item-row';
        row.id = 'row_' + rowIndex;

        let optionsHTML = '<option value="">-- Select Wine --</option>';
        allWines.forEach(w => {
          optionsHTML += `<option value="\${w.value}">\${w.text}</option>`;
        });

        let formatsHTML = '';
        const formatsSelect = document.getElementById('format_master_source');
        if (formatsSelect) {
          formatsHTML = formatsSelect.innerHTML;
        }

        row.innerHTML = `
          <td>
            <div class="autocomplete-container">
              <input type="text" class="search-box" placeholder="🔍 Filter wine..." onkeyup="filterWineDropdown(\${rowIndex})" style="width:100%; padding: 5px; font-family: Georgia, serif; font-size: small; box-sizing: border-box; margin-bottom:4px; border: 1px solid #ccc; border-radius: 4px;">
              <select name="wine_id[]" id="wine_select_\${rowIndex}" required style="width:100%; padding: 6px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
                \${optionsHTML}
              </select>
            </div>
          </td>
          <td>
            <select name="format[]" required style="width:100%; padding: 6px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
              \${formatsHTML}
            </select>
          </td>
          <td>
            <input type="number" name="quantity[]" min="1" value="1" required onchange="calculatePrices()" onkeyup="calculatePrices()" style="width:70px; padding: 5px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
          </td>
          <td>
            <input type="number" name="total_price[]" step="0.01" min="0" placeholder="0.00" required onchange="calculatePrices()" onkeyup="calculatePrices()" style="width:100px; padding: 5px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
          </td>
          <td style="text-align: center;">
            <button type="button" class="btn-remove" onclick="removeWineRow(\${rowIndex})">Remove</button>
          </td>
        `;
        tableBody.appendChild(row);
        calculatePrices();
      }

      function removeWineRow(index) {
        const row = document.getElementById('row_' + index);
        if (row) {
          row.remove();
        }
        calculatePrices();
      }

      function filterWineDropdown(index) {
        const searchInput = document.querySelector('#row_' + index + ' .search-box');
        const select = document.getElementById('wine_select_' + index);
        if (!searchInput || !select) return;

        const query = searchInput.value.toLowerCase().trim();
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);

        // Clear select except default option
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
            if (w.value === currentValue) {
              opt.selected = true;
            }
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
        const shippingPaid = parseFloat(shippingInput.value) || 0;

        for (let i = 0; i < qtyInputs.length; i++) {
          const qty = parseInt(qtyInputs[i].value) || 0;
          const price = parseFloat(priceInputs[i].value) || 0.00;
          totalQty += qty;
          totalItemsPrice += price;
        }

        document.getElementById('calc_total_qty').textContent = totalQty;
        document.getElementById('calc_items_total').textContent = totalItemsPrice.toFixed(2);
        
        const totalOverall = totalItemsPrice + shippingPaid;
        document.getElementById('calc_overall_total').textContent = totalOverall.toFixed(2);
        
        // Show proportional calculation in rows
        for (let i = 0; i < qtyInputs.length; i++) {
          const qty = parseInt(qtyInputs[i].value) || 0;
          const price = parseFloat(priceInputs[i].value) || 0.00;
          const rowId = qtyInputs[i].closest('tr').id;
          
          if (qty > 0) {
            const shippingShare = (totalQty > 0) ? (shippingPaid / totalQty) : 0;
            const bottleCost = (price / qty) + shippingShare;
            
            // Check if calculation display exists, if not create it
            let calcDisplay = qtyInputs[i].parentNode.querySelector('.bottle-calc-info');
            if (!calcDisplay) {
              calcDisplay = document.createElement('div');
              calcDisplay.className = 'bottle-calc-info';
              calcDisplay.style.fontSize = '10px';
              calcDisplay.style.color = '#777';
              calcDisplay.style.marginTop = '3px';
              qtyInputs[i].parentNode.appendChild(calcDisplay);
            }
            calcDisplay.innerHTML = `est: <strong>\${bottleCost.toFixed(2)}</strong> / btl`;
          }
        }
      }

      function triggerFileInput() {
        document.getElementById('invoice_files').click();
      }

      function handleFileSelect(input) {
        const fileListDisplay = document.getElementById('file_list_display');
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
          ul.style.fontSize = 'xs-small';
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
  <div class="column main" style="width: 100%; float: none;">
    <div class="card">
      <section>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 15px;">
          <h2 style="margin: 0; font-family: Georgia, serif;">Create New Wine Order</h2>
          <a href="manageOrders.php" class="btn-action" style="font-size: small; background-color: #6c757d;">📂 Manage Open Orders</a>
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

        <form method="POST" enctype="multipart/form-data" accept-charset="UTF-8" style="font-family: Georgia, serif;">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div>
              <label for="store_id" style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Purchased From (Store):</label>
              <select name="store_id" id="store_id" required style="width:100%; padding: 8px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">-- Select Store --</option>
                <?php foreach ($stores as $store): ?>
                  <option value="<?php echo $store['store_id']; ?>" <?php echo (isset($store_id) && $store_id == $store['store_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($store['store_name'] . " (" . $store['country'] . ")", ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label for="order_date" style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Order Date:</label>
              <input type="date" name="order_date" id="order_date" required value="<?php echo isset($order_date) ? htmlspecialchars($order_date, ENT_QUOTES, 'UTF-8') : date('Y-m-d'); ?>" style="width:100%; padding: 8px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>

            <div>
              <label for="shipping_paid" style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Shipping Paid:</label>
              <input type="number" name="shipping_paid" id="shipping_paid" step="0.01" min="0" value="<?php echo isset($shipping_paid) ? htmlspecialchars($shipping_paid, ENT_QUOTES, 'UTF-8') : '0.00'; ?>" onchange="calculatePrices()" onkeyup="calculatePrices()" style="width:100%; padding: 8px; font-family: Georgia, serif; font-size: small; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>
          </div>

          <h3 style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 25px; margin-bottom: 10px; font-family: Georgia, serif;">Order Items</h3>
          <p style="font-size: 11px; color: #666; margin-top: 0;">Add one or more wines to this purchase. Individual bottle records will be created automatically in your inventory with 'pending delivery' status.</p>

          <table class="order-table">
            <thead>
              <tr>
                <th style="width: 45%;">Wine Select</th>
                <th style="width: 18%;">Format</th>
                <th style="width: 12%;">Qty</th>
                <th style="width: 18%;">Total Price Paid</th>
                <th style="width: 7%; text-align: center;">Action</th>
              </tr>
            </thead>
            <tbody id="order_items_body">
              <!-- Dynamically populated via Javascript -->
            </tbody>
          </table>

          <div style="margin-top: 15px; display: flex; justify-content: flex-start;">
            <button type="button" class="btn-action" onclick="addWineRow()" style="background-color: darkred; padding: 8px 15px; font-size: small;">➕ Add Wine Line</button>
          </div>

          <div style="background-color: #fcfcfc; border: 1px solid #ddd; padding: 15px; border-radius: 6px; margin-top: 25px; display: flex; justify-content: flex-end;">
            <div style="font-family: Georgia, serif; text-align: right; line-height: 1.6; font-size: small;">
              <div>Total Bottles: <strong id="calc_total_qty">0</strong></div>
              <div>Wine Subtotal: €<strong id="calc_items_total">0.00</strong></div>
              <div style="border-top: 1px solid #ccc; margin-top: 5px; padding-top: 5px; font-size: 15px;">
                Total Order Value: <strong style="color: darkred;">€<span id="calc_overall_total">0.00</span></strong>
              </div>
            </div>
          </div>

          <h3 style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 30px; margin-bottom: 10px; font-family: Georgia, serif;">Invoice Documents</h3>
          <p style="font-size: 11px; color: #666; margin-top: 0;">Upload any PDFs or invoice screenshots. These files will be linked with this order, and will automatically link with the generated bottles.</p>

          <div class="drag-drop-zone" onclick="triggerFileInput()">
            <div style="font-size: 24px; margin-bottom: 8px;">📄</div>
            <strong style="font-size: small; color: firebrick;">Click here to select files</strong> or drag-and-drop them
            <p style="margin: 5px 0 0 0; font-size: 10px; color: #666;">Allowed formats: PDF, PNG, JPG, JPEG, GIF, WEBP (Max 10MB per file)</p>
          </div>
          <input type="file" name="invoice_files[]" id="invoice_files" multiple onchange="handleFileSelect(this)" style="display: none;">
          <div id="file_list_display" style="margin-bottom: 25px; background: #fffdfd; border-radius: 4px; padding: 5px 10px;"></div>

          <hr style="border: 0; border-top: 1px solid #ccc; margin: 30px 0 20px 0;">

          <div style="display: flex; justify-content: center; gap: 15px;">
            <button type="submit" name="submit" class="btn-action" style="font-size: 14px; padding: 10px 25px; font-weight: bold; background-color: firebrick;">💾 Save Purchase Order</button>
            <a href="index.php" class="btn-action btn-secondary" style="font-size: 14px; padding: 10px 25px;">Cancel</a>
          </div>
        </form>
      </section>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
