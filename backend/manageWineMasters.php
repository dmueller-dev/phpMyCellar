<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Wine Master Management Dashboard.
   *
   * Combines creation and editing of wine cuvée master definitions.
   * Required privileges: 'add_wine_master' or 'edit_wine_master'
   */
  $can_add = hasPrivilege($conn, 'add_wine_master');
  $can_edit = hasPrivilege($conn, 'edit_wine_master');

  if (!$can_add && !$can_edit) {
    header("Location: /backend/index.php");
    exit();
  }

  global $mysqli, $conn;

  $errors = [];
  $success_message = '';

  // Pre-load lookup data
  $producers = getProducers($conn);
  $regions = getRegions($conn);
  $subregions = getSubregions($conn);
  $appellations = getAppellations($conn);
  $vineyards = getVineyards($conn);
  $varieties = getVarieties($conn);
  $colours = getColours($conn);
  $styles = getStyles($conn);
  $nameconventions = getNameConventions($conn);

  // Determine requested master for editing
  $requested_master_id = filter_input(INPUT_GET, 'master_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'edit_master', FILTER_VALIDATE_INT);
  $filter_producer_id = filter_input(INPUT_GET, 'producer_id', FILTER_VALIDATE_INT);
  $filter_region_id = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT);

  $is_edit = false;
  $master_id = null;
  $producer_id = $filter_producer_id ?: '';
  $region_id = $filter_region_id ?: '';
  $subregion_id = filter_input(INPUT_GET, 'subregion_id', FILTER_VALIDATE_INT) ?: '';
  $appellation_id = filter_input(INPUT_GET, 'appellation_id', FILTER_VALIDATE_INT) ?: '';
  $vineyard_id = filter_input(INPUT_GET, 'vineyard_id', FILTER_VALIDATE_INT) ?: '';
  $name = '';
  $cuvee_yn = 'no';
  $grape = '';
  $colour = 'red';
  $style = 'still';
  $nameconvention = 'vintage_producer_name';

  if ($requested_master_id) {
    $existing = getMasterDetails($conn, $requested_master_id);
    if ($existing) {
      $is_edit = true;
      $master_id = (int)$existing['master_id'];
      $producer_id = (int)$existing['producer_id'];
      $region_id = (int)$existing['region_id'];
      $subregion_id = $existing['subregion_id'] ? (int)$existing['subregion_id'] : '';
      $appellation_id = $existing['appellation_id'] ? (int)$existing['appellation_id'] : '';
      $vineyard_id = $existing['vineyard_id'] ? (int)$existing['vineyard_id'] : '';
      $name = $existing['name'];
      $cuvee_yn = $existing['cuvee_yn'];
      $grape = $existing['grape'];
      $colour = $existing['colour'];
      $style = $existing['style'];
      $nameconvention = $existing['nameconvention'];
    } else {
      $errors[] = "Requested wine master #" . $requested_master_id . " not found.";
    }
  }

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die("CSRF token validation failed");
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    $producer_id_input = filter_input(INPUT_POST, 'producer_id', FILTER_VALIDATE_INT);
    $region_id_input = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
    $subregion_id_input = filter_input(INPUT_POST, 'subregion_id', FILTER_VALIDATE_INT) ?: null;
    $appellation_id_input = filter_input(INPUT_POST, 'appellation_id', FILTER_VALIDATE_INT) ?: null;
    $vineyard_id_input = filter_input(INPUT_POST, 'vineyard_id', FILTER_VALIDATE_INT) ?: null;
    $cuvee_yn_input = sanitizeInput($_POST['cuvee_yn'] ?? 'no');
    $grape_input = sanitizeInput($_POST['grape'] ?? '');
    $name_input = sanitizeInput($_POST['name'] ?? '');
    $colour_input = sanitizeInput($_POST['colour'] ?? '');
    $style_input = sanitizeInput($_POST['style'] ?? '');
    $nameconvention_input = sanitizeInput($_POST['nameconvention'] ?? '');

    $valErrors = validateMasterInput($conn, $is_edit ? $master_id : 0, $producer_id_input, $region_id_input, $subregion_id_input, $appellation_id_input, $vineyard_id_input);
    if (!empty($valErrors)) {
      $errors = array_merge($errors, $valErrors);
    } else {
      if ($action === 'add') {
        if (!$can_add) {
          die("Permission denied: You do not have permission to add wine masters.");
        }
        $conn->begin_transaction();
        try {
          if (insertWineMaster($conn, $name_input, $nameconvention_input, $producer_id_input, $region_id_input, $subregion_id_input, $appellation_id_input, $vineyard_id_input, $grape_input, $cuvee_yn_input, $colour_input, $style_input)) {
            $inserted_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Wine cuv&eacute;e master inserted successfully! <a href='manageWines.php?master_id=" . $inserted_id . "'>Add a vintage for this cuv&eacute;e &rarr;</a>";
            $name = '';
            $vineyard_id = '';
            $is_edit = false;
          } else {
            $conn->rollback();
            $errors[] = "Failed to insert wine master.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error inserting wine master: " . $e->getMessage();
        }
      } elseif ($action === 'edit') {
        if (!$can_edit) {
          die("Permission denied: You do not have permission to edit wine masters.");
        }
        $post_master_id = filter_input(INPUT_POST, 'master_id', FILTER_VALIDATE_INT);
        $conn->begin_transaction();
        try {
          if (updateMaster($conn, $post_master_id, $producer_id_input, $region_id_input, $subregion_id_input, $appellation_id_input, $vineyard_id_input, $name_input, $cuvee_yn_input, $grape_input, $colour_input, $style_input, $nameconvention_input)) {
            $conn->commit();
            $success_message = "Wine cuv&eacute;e master updated successfully.";
            $master_id = $post_master_id;
            $producer_id = $producer_id_input;
            $region_id = $region_id_input;
            $subregion_id = $subregion_id_input ?: '';
            $appellation_id = $appellation_id_input ?: '';
            $vineyard_id = $vineyard_id_input ?: '';
            $name = $name_input;
            $cuvee_yn = $cuvee_yn_input;
            $grape = $grape_input;
            $colour = $colour_input;
            $style = $style_input;
            $nameconvention = $nameconvention_input;
            $is_edit = true;
          } else {
            $conn->rollback();
            $errors[] = "Failed to update wine master.";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Database error updating wine master: " . $e->getMessage();
        }
      }
    }
  }

  // Fetch wine masters with vintage statistics
  $masters_stmt = $conn->prepare("
    SELECT wm.master_id, wm.nameconvention, wm.name, wm.grape, wm.colour, wm.style, wm.cuvee_yn,
           p.producer_id, p.producer, r.region_id, r.region, r.country, v.vineyard,
           COUNT(DISTINCT w.wine_id) AS vintage_count
    FROM wines_master wm
    LEFT JOIN producers p ON wm.producer_id = p.producer_id
    LEFT JOIN regions r ON wm.region_id = r.region_id
    LEFT JOIN vineyards v ON wm.vineyard_id = v.vineyard_id
    LEFT JOIN wines w ON wm.master_id = w.master_id
    GROUP BY wm.master_id, wm.nameconvention, wm.name, wm.grape, wm.colour, wm.style, wm.cuvee_yn, p.producer_id, p.producer, r.region_id, r.region, r.country, v.vineyard
    ORDER BY r.country ASC, r.region ASC, p.producer ASC, wm.name ASC
  ");
  $master_records = [];
  if ($masters_stmt) {
    $masters_stmt->execute();
    $res = $masters_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $master_records[] = $row;
    }
    $masters_stmt->close();
  }

  $totalMasters = count($master_records);
  $withVintages = 0;
  foreach ($master_records as $rec) {
    if ((int)$rec['vintage_count'] > 0) $withVintages++;
  }

  $csrf_token = generateCSRFToken();
  $page_title = 'Manage Wine Masters - Cellar Administration';

  $extra_head = <<<HTML
    <script>
      let allProducers = [];
      let allRegions = [];
      let allSubregions = [];
      let allAppellations = [];
      let allVineyards = [];

      function getSelectedProducerName() {
        const select = document.getElementById('producer_id');
        if (!select || select.selectedIndex < 0) return '';
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return '';
        if (opt.getAttribute('data-producer')) return opt.getAttribute('data-producer').trim();
        const parts = opt.textContent.split(':');
        return parts[parts.length - 1].trim();
      }

      function getSelectedVineyardName() {
        const select = document.getElementById('vineyard_id');
        if (!select || select.selectedIndex < 0) return '';
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return '';
        if (opt.getAttribute('data-vineyard')) return opt.getAttribute('data-vineyard').trim();
        const parts = opt.textContent.split(':');
        return parts[parts.length - 1].trim();
      }

      function getSelectedGrape() {
        const select = document.getElementById('grape');
        if (!select || select.selectedIndex < 0) return '';
        const opt = select.options[select.selectedIndex];
        return (opt && opt.value) ? opt.value.trim() : '';
      }

      function getEnteredName() {
        const input = document.getElementById('name');
        return input ? input.value.trim() : '';
      }

      function getSelectedConvention() {
        const select = document.getElementById('nameconvention');
        if (!select || select.selectedIndex < 0) return '';
        const opt = select.options[select.selectedIndex];
        return (opt && opt.value) ? opt.value.trim() : '';
      }

      function updateWineNamePreview() {
        const previewWine = document.getElementById('previewWineName');
        const previewBadge = document.getElementById('previewConventionBadge');
        if (!previewWine || !previewBadge) return;

        const convention = getSelectedConvention();
        const producer = getSelectedProducerName();
        const vineyard = getSelectedVineyardName();
        const grape = getSelectedGrape();
        const name = getEnteredName();

        if (!convention) {
          previewBadge.textContent = 'none selected';
          previewWine.innerHTML = '<span style="color: #999; font-weight: normal; font-style: italic;">Select a naming convention to preview the wine name</span>';
          return;
        }

        previewBadge.textContent = convention;
        const sampleVintage = new Date().getFullYear() - 1;
        const p = producer || '<span style="color: #999; font-weight: normal;">[Producer]</span>';
        const v = vineyard || '<span style="color: #999; font-weight: normal;">[Vineyard]</span>';
        const g = grape || '<span style="color: #999; font-weight: normal;">[Variety]</span>';
        const n = name || '<span style="color: #999; font-weight: normal;">[Name]</span>';

        let parts = [sampleVintage];
        if (convention === 'vintage_name') {
          parts.push(n);
        } else if (convention === 'vintage_producer') {
          parts.push(p);
        } else if (convention === 'vintage_producer_grape_name') {
          parts.push(p, g, n);
        } else if (convention === 'vintage_producer_vineyard_grape_name') {
          parts.push(p, v, g, n);
        } else if (convention === 'vintage_producer_vineyard_name') {
          parts.push(p, v, n);
        } else {
          parts.push(p, n);
        }

        previewWine.innerHTML = parts.join(' ');
      }

      document.addEventListener("DOMContentLoaded", function() {
        const prodSelect = document.getElementById('producer_id');
        if (prodSelect) {
          for (let i = 1; i < prodSelect.options.length; i++) {
            const opt = prodSelect.options[i];
            allProducers.push({
              value: opt.value,
              text: opt.textContent,
              producer: opt.getAttribute('data-producer') || ''
            });
          }
          prodSelect.addEventListener('change', updateWineNamePreview);
        }

        const regSelect = document.getElementById('region_id');
        if (regSelect) {
          for (let i = 1; i < regSelect.options.length; i++) {
            const opt = regSelect.options[i];
            allRegions.push({ value: opt.value, text: opt.textContent });
          }
        }

        const subregSelect = document.getElementById('subregion_id');
        if (subregSelect) {
          for (let i = 1; i < subregSelect.options.length; i++) {
            const opt = subregSelect.options[i];
            allSubregions.push({ value: opt.value, text: opt.textContent });
          }
        }

        const appSelect = document.getElementById('appellation_id');
        if (appSelect) {
          for (let i = 1; i < appSelect.options.length; i++) {
            const opt = appSelect.options[i];
            allAppellations.push({ value: opt.value, text: opt.textContent });
          }
        }

        const vineSelect = document.getElementById('vineyard_id');
        if (vineSelect) {
          for (let i = 1; i < vineSelect.options.length; i++) {
            const opt = vineSelect.options[i];
            allVineyards.push({
              value: opt.value,
              text: opt.textContent,
              vineyard: opt.getAttribute('data-vineyard') || ''
            });
          }
          vineSelect.addEventListener('change', updateWineNamePreview);
        }

        const grpSelect = document.getElementById('grape');
        if (grpSelect) grpSelect.addEventListener('change', updateWineNamePreview);

        const convSelect = document.getElementById('nameconvention');
        if (convSelect) convSelect.addEventListener('change', updateWineNamePreview);

        const nameInput = document.getElementById('name');
        if (nameInput) {
          nameInput.addEventListener('input', updateWineNamePreview);
          nameInput.addEventListener('keyup', updateWineNamePreview);
        }

        updateWineNamePreview();
      });

      function filterProducers() {
        const query = document.getElementById('searchProducerBox').value.toLowerCase().trim();
        const select = document.getElementById('producer_id');
        if (!select || !allProducers) return;
        const currentVal = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allProducers.forEach(p => {
          const matches = terms.every(term => p.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = p.value;
            opt.textContent = p.text;
            if (p.producer) opt.setAttribute('data-producer', p.producer);
            if (p.value === currentVal) opt.selected = true;
            select.appendChild(opt);
          }
        });
        updateWineNamePreview();
      }

      function filterRegions() {
        const query = document.getElementById('searchRegionBox').value.toLowerCase().trim();
        const select = document.getElementById('region_id');
        if (!select || !allRegions) return;
        const currentVal = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allRegions.forEach(r => {
          const matches = terms.every(term => r.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value;
            opt.textContent = r.text;
            if (r.value === currentVal) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function filterSubregions() {
        const query = document.getElementById('searchSubregionBox').value.toLowerCase().trim();
        const select = document.getElementById('subregion_id');
        if (!select || !allSubregions) return;
        const currentVal = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allSubregions.forEach(sr => {
          const matches = terms.every(term => sr.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = sr.value;
            opt.textContent = sr.text;
            if (sr.value === currentVal) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function filterAppellations() {
        const query = document.getElementById('searchAppellationBox').value.toLowerCase().trim();
        const select = document.getElementById('appellation_id');
        if (!select || !allAppellations) return;
        const currentVal = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allAppellations.forEach(a => {
          const matches = terms.every(term => a.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = a.value;
            opt.textContent = a.text;
            if (a.value === currentVal) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function filterVineyards() {
        const query = document.getElementById('searchVineyardBox').value.toLowerCase().trim();
        const select = document.getElementById('vineyard_id');
        if (!select || !allVineyards) return;
        const currentVal = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allVineyards.forEach(v => {
          const matches = terms.every(term => v.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = v.value;
            opt.textContent = v.text;
            if (v.vineyard) opt.setAttribute('data-vineyard', v.vineyard);
            if (v.value === currentVal) opt.selected = true;
            select.appendChild(opt);
          }
        });
        updateWineNamePreview();
      }
    </script>
  HTML;

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
          &#127870; Manage Cuv&eacute;e Masters
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="manageProducers.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            &larr; Producers
          </a>
          <a href="manageWines.php" class="btn-action" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Manage Vintages &rarr;
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
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 12px 16px; margin-bottom: 20px; color: #2e7d32;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <!-- High-level Metrics -->
      <div class="admin-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="admin-kpi-card" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($totalMasters); ?></span>
            <span class="kpi-icon">&#127870;</span>
          </div>
          <div class="kpi-label">Cuv&eacute;e Masters</div>
          <div class="kpi-sub">Total master wine profiles</div>
        </div>

        <div class="admin-kpi-card kpi-accent-green" style="cursor: default;">
          <div class="kpi-top">
            <span class="kpi-val"><?php echo number_format($withVintages); ?></span>
            <span class="kpi-icon">&#128197;</span>
          </div>
          <div class="kpi-label">Active Cuv&eacute;es</div>
          <div class="kpi-sub">With specific vintages tracked</div>
        </div>
      </div>

      <!-- Responsive Dual Grid: Master Directory & Form -->
      <div class="admin-dual-manage-grid unequal">
        
        <!-- Left: Searchable Master Directory -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.1rem;">
              Existing Masters (<?php echo count($master_records); ?>)
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageWineMasters.php" style="font-size: 0.85rem; color: #0d6efd; text-decoration: none;">+ Add New</a>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
            <select id="producerFilter" style="flex: 1; min-width: 140px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 44px;" onchange="filterMasterList()">
              <option value="">All Producers</option>
              <?php foreach ($producers as $p): ?>
                <option value="<?php echo (int)$p['producer_id']; ?>" <?php echo ($filter_producer_id === (int)$p['producer_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($p['producer'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="masterSearch" class="admin-search-box" style="flex: 2; min-width: 160px; margin-bottom: 0;" placeholder="Search cuv&eacute;es..." onkeyup="filterMasterList()">
          </div>

          <div class="admin-table-container" style="max-height: 580px; overflow-y: auto;">
            <table id="masterTable">
              <thead>
                <tr>
                  <th>Cuv&eacute;e / Producer</th>
                  <th>Terroir</th>
                  <th style="text-align: center;">Vintages</th>
                  <th style="text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($master_records)): ?>
                  <tr>
                    <td colspan="4" style="text-align: center; color: #777; padding: 20px;">No wine masters found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($master_records as $m): ?>
                    <tr data-producer-id="<?php echo (int)$m['producer_id']; ?>" style="<?php echo ($is_edit && $master_id === (int)$m['master_id']) ? 'background-color: #fff9e6;' : ''; ?>">
                      <td>
                        <strong><?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div style="font-size: 0.78rem; color: #555;">
                          <?php echo htmlspecialchars($m['producer'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?>
                          <?php if (!empty($m['grape'])): ?> &bull; <?php echo htmlspecialchars($m['grape'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <div style="font-size: 0.88rem;"><?php echo htmlspecialchars($m['region'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div style="font-size: 0.78rem; color: #888;"><?php echo htmlspecialchars($m['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                      </td>
                      <td style="text-align: center;">
                        <?php if ((int)$m['vintage_count'] > 0): ?>
                          <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; background: #e8f5e9; color: #2e7d32; font-size: 0.82rem; font-weight: bold;">
                            <?php echo (int)$m['vintage_count']; ?>
                          </span>
                        <?php else: ?>
                          <span style="color: #bbb; font-size: 0.82rem;">0</span>
                        <?php endif; ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <?php if ($can_edit): ?>
                          <a href="manageWineMasters.php?master_id=<?php echo (int)$m['master_id']; ?>" class="btn-action btn-secondary" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none;">
                            Edit
                          </a>
                        <?php endif; ?>
                        <?php if (hasPrivilege($conn, 'add_wine')): ?>
                          <a href="manageWines.php?master_id=<?php echo (int)$m['master_id']; ?>" title="Add vintage" style="font-size: 0.8rem; padding: 4px 8px; text-decoration: none; color: #0d6efd;">
                            + Vintage
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right: Add / Edit Master Form -->
        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #333; font-size: 1.15rem;">
              <?php echo $is_edit ? '&#9998; Edit Master #' . $master_id : '&#10010; Add Cuv&eacute;e Master'; ?>
            </h3>
            <?php if ($is_edit): ?>
              <a href="manageWineMasters.php" style="font-size: 0.85rem; color: #dc3545; text-decoration: none;">Cancel</a>
            <?php endif; ?>
          </div>

          <!-- Live Naming Preview Box -->
          <div style="background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid var(--secondary-accent, firebrick); border-radius: 6px; padding: 12px 14px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <span style="font-size: 11px; text-transform: uppercase; font-weight: bold; color: #718096; letter-spacing: 0.5px;">Live Naming Preview</span>
              <span id="previewConventionBadge" style="font-size: 11px; background: #edf2f7; color: #4a5568; padding: 2px 6px; border-radius: 3px; font-family: monospace;"></span>
            </div>
            <div id="previewWineName" style="font-size: 1.05rem; font-weight: bold; color: #1a202c; word-break: break-word;"></div>
          </div>

          <form method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
              <input type="hidden" name="master_id" value="<?php echo (int)$master_id; ?>">
            <?php endif; ?>

            <div style="margin-bottom: 15px;">
              <label for="searchProducerBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Producer: <span style="color: red;">*</span>
              </label>
              <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                <input type="text" id="searchProducerBox" placeholder="🔍 Search producer..." onkeyup="filterProducers()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                <select name="producer_id" id="producer_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                  <option value="">-- Select Producer --</option>
                  <?php foreach ($producers as $p): ?>
                    <option value="<?php echo (int)$p['producer_id']; ?>" data-producer="<?php echo htmlspecialchars($p['producer'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((int)$producer_id === (int)$p['producer_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($p['country'] . ': ' . $p['region'] . ' — ' . $p['producer'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-bottom: 15px;">
              <label for="name" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Cuv&eacute;e / Wine Name: <span style="color: red;">*</span>
              </label>
              <input type="text" name="name" id="name" required maxlength="100" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Roman&eacute;e-Conti Grand Cru" style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
            </div>

            <div class="form-row-2col" style="margin-bottom: 15px;">
              <div>
                <label for="nameconvention" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Naming Convention: <span style="color: red;">*</span>
                </label>
                <select name="nameconvention" id="nameconvention" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <?php foreach ($nameconventions as $nc): ?>
                    <option value="<?php echo htmlspecialchars($nc['nameconvention'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($nc['nameconvention'] === $nameconvention) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($nc['nameconvention'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="grape" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Grape Variety / Blend: <span style="color: red;">*</span>
                </label>
                <select name="grape" id="grape" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="">-- Select Variety --</option>
                  <?php foreach ($varieties as $v): ?>
                    <option value="<?php echo htmlspecialchars($v['grape'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($v['grape'] === $grape) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($v['grape'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Geography Fields -->
            <div class="form-row-2col" style="margin-bottom: 15px;">
              <div>
                <label for="searchRegionBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Region: <span style="color: red;">*</span>
                </label>
                <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                  <input type="text" id="searchRegionBox" placeholder="🔍 Search region..." onkeyup="filterRegions()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                  <select name="region_id" id="region_id" required style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                    <option value="">-- Select Region --</option>
                    <?php foreach ($regions as $r): ?>
                      <option value="<?php echo (int)$r['region_id']; ?>" <?php echo ((int)$region_id === (int)$r['region_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['country'] . ': ' . $r['region'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div>
                <label for="searchSubregionBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Subregion:
                </label>
                <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                  <input type="text" id="searchSubregionBox" placeholder="🔍 Search subregion..." onkeyup="filterSubregions()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                  <select name="subregion_id" id="subregion_id" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                    <option value="">-- None / Select Subregion --</option>
                    <?php foreach ($subregions as $sr): ?>
                      <option value="<?php echo (int)$sr['subregion_id']; ?>" <?php echo ((int)$subregion_id === (int)$sr['subregion_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sr['region'] . ': ' . $sr['subregion'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-row-2col" style="margin-bottom: 15px;">
              <div>
                <label for="searchAppellationBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Appellation:
                </label>
                <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                  <input type="text" id="searchAppellationBox" placeholder="🔍 Search appellation..." onkeyup="filterAppellations()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                  <select name="appellation_id" id="appellation_id" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                    <option value="">-- None / Select Appellation --</option>
                    <?php foreach ($appellations as $a): ?>
                      <option value="<?php echo (int)$a['appellation_id']; ?>" <?php echo ((int)$appellation_id === (int)$a['appellation_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['region'] . ': ' . $a['appellation'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div>
                <label for="searchVineyardBox" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Single Vineyard / Climat:
                </label>
                <div style="border: 1px solid #ccc; border-radius: 4px; background: white;">
                  <input type="text" id="searchVineyardBox" placeholder="🔍 Search vineyard..." onkeyup="filterVineyards()" style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px 10px; box-sizing: border-box; font-family: inherit; font-size: 0.9rem; background: #fafafa; border-radius: 4px 4px 0 0;" autocomplete="off">
                  <select name="vineyard_id" id="vineyard_id" style="width: 100%; border: none; padding: 8px 10px; border-radius: 0 0 4px 4px; box-sizing: border-box; min-height: 44px; font-family: inherit; font-size: 0.9rem;">
                    <option value="">-- None / Select Vineyard --</option>
                    <?php foreach ($vineyards as $vy): ?>
                      <option value="<?php echo (int)$vy['vineyard_id']; ?>" data-vineyard="<?php echo htmlspecialchars($vy['vineyard'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((int)$vineyard_id === (int)$vy['vineyard_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($vy['region'] . ': ' . $vy['vineyard'], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Wine Characteristics -->
            <div class="form-row-3col" style="margin-bottom: 20px;">
              <div>
                <label for="colour" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Colour: <span style="color: red;">*</span>
                </label>
                <select name="colour" id="colour" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <?php foreach ($colours as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['colour'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($c['colour'] === $colour) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars(ucfirst($c['colour']), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="style" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Style: <span style="color: red;">*</span>
                </label>
                <select name="style" id="style" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <?php foreach ($styles as $st): ?>
                    <option value="<?php echo htmlspecialchars($st['style'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($st['style'] === $style) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars(ucfirst($st['style']), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label for="cuvee_yn" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                  Special Cuv&eacute;e: <span style="color: red;">*</span>
                </label>
                <select name="cuvee_yn" id="cuvee_yn" required style="width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
                  <option value="no" <?php echo ($cuvee_yn === 'no') ? 'selected' : ''; ?>>No</option>
                  <option value="yes" <?php echo ($cuvee_yn === 'yes') ? 'selected' : ''; ?>>Yes</option>
                </select>
              </div>
            </div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn-action" style="flex: 1; padding: 10px 16px; font-size: 0.95rem; cursor: pointer; min-height: 44px;">
                <?php echo $is_edit ? 'Update Wine Master' : 'Insert Wine Master'; ?>
              </button>
              <?php if ($is_edit): ?>
                <a href="manageWineMasters.php" class="btn-action btn-secondary" style="padding: 10px 16px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
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

<script>
  function filterMasterList() {
    const searchVal = document.getElementById('masterSearch').value.toLowerCase().trim();
    const producerVal = document.getElementById('producerFilter').value;
    const table = document.getElementById('masterTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.cells.length < 4) continue;
      
      const rowProducer = row.getAttribute('data-producer-id');
      const textContent = row.textContent.toLowerCase();

      const matchesSearch = !searchVal || textContent.includes(searchVal);
      const matchesProducer = !producerVal || (rowProducer === producerVal);

      if (matchesSearch && matchesProducer) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    }
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
