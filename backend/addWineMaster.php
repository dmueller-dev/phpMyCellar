<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  // Include the database configuration file
  global $mysqli, $conn;

  $errors = [];
  $success_message = '';
  $producer_id = '';
  $region_id = '';
  $subregion_id = '';
  $appellation_id = '';
  $vineyard_id = '';
  $name = '';
  $cuvee_yn = '';
  $grape = '';
  $colour = '';
  $style = '';
  $nameconvention = '';

  // Handle form submission
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update'])) {
      // Validate CSRF token
      if (!validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
      }
      // Sanitize and validate inputs
      $producer_id = filter_input(INPUT_POST, 'producer_id', FILTER_VALIDATE_INT);
      $region_id = filter_input(INPUT_POST, 'region_id', FILTER_VALIDATE_INT);
      $subregion_id = filter_input(INPUT_POST, 'subregion_id', FILTER_VALIDATE_INT);
      $appellation_id = filter_input(INPUT_POST, 'appellation_id', FILTER_VALIDATE_INT);
      $vineyard_id = filter_input(INPUT_POST, 'vineyard_id', FILTER_VALIDATE_INT);
      $cuvee_yn = sanitizeInput($_POST['cuvee_yn']);
      $grape = sanitizeInput($_POST['grape']);
      $name = sanitizeInput($_POST['name']);
      $colour = sanitizeInput($_POST['colour']);
      $style = sanitizeInput($_POST['style']);
      $nameconvention = sanitizeInput($_POST['nameconvention']);
      $errors = validateMasterInput($conn, 0, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id);
      if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        try {
          if (insertWineMaster($conn, $name, $nameconvention, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id, $grape, $cuvee_yn, $colour, $style)) {
            $inserted_master_id = $conn->insert_id;
            $conn->commit();
            $success_message = "Wine master inserted successfully. <a href='addWine.php?master_id=" . $inserted_master_id . "'>Add a wine based on this master</a>";
            // Clear the form values on successful submission
            $producer_id = '';
            $region_id = '';
            $subregion_id = '';
            $appellation_id = '';
            $vineyard_id = '';
            $name = '';
            $cuvee_yn = '';
            $grape = '';
            $colour = '';
            $style = '';
            $nameconvention = '';
          } else {
            $conn->rollback();
            $errors[] = "Error inserting wine master";
          }
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = "Error updating wine master: " . $e->getMessage();
        }
      }
    }
  } else {
    if (isset($_GET['producer_id'])) {
      $producer_id = filter_input(INPUT_GET, 'producer_id', FILTER_VALIDATE_INT);
    }
    if (isset($_GET['region_id'])) {
      $region_id = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT);
    }
    if (isset($_GET['subregion_id'])) {
      $subregion_id = filter_input(INPUT_GET, 'subregion_id', FILTER_VALIDATE_INT);
    }
    if (isset($_GET['appellation_id'])) {
      $appellation_id = filter_input(INPUT_GET, 'appellation_id', FILTER_VALIDATE_INT);
    }
    if (isset($_GET['vineyard_id'])) {
      $vineyard_id = filter_input(INPUT_GET, 'vineyard_id', FILTER_VALIDATE_INT);
    }
  }

  // Get all information for the dropdowns
  $producers = getProducers($conn);
  $regions = getRegions($conn);
  $subregions = getSubregions($conn);
  $appellations = getAppellations($conn);
  $vineyards = getVineyards($conn);
  $varieties = getVarieties($conn);
  $colours = getColours($conn);
  $styles = getStyles($conn);
  $nameconventions = getNameConventions($conn);

  // Generate CSRF token
  $csrf_token = generateCSRFToken();
?>

<?php
  $page_title = 'Add master';

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
        if (opt.getAttribute('data-producer')) {
          return opt.getAttribute('data-producer').trim();
        }
        const parts = opt.textContent.split(':');
        return parts[parts.length - 1].trim();
      }

      function getSelectedVineyardName() {
        const select = document.getElementById('vineyard_id');
        if (!select || select.selectedIndex < 0) return '';
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return '';
        if (opt.getAttribute('data-vineyard')) {
          return opt.getAttribute('data-vineyard').trim();
        }
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
          // Default: vintage_producer_name or fallback
          parts.push(p, n);
        }

        previewWine.innerHTML = parts.join(' ');
      }

      document.addEventListener("DOMContentLoaded", function() {
        const producerSelect = document.getElementById('producer_id');
        if (producerSelect) {
          for (let i = 1; i < producerSelect.options.length; i++) {
            const opt = producerSelect.options[i];
            allProducers.push({
              value: opt.value,
              text: opt.textContent,
              selected: opt.selected,
              producer: opt.getAttribute('data-producer') || ''
            });
          }
          producerSelect.addEventListener('change', updateWineNamePreview);
        }

        const regionSelect = document.getElementById('region_id');
        if (regionSelect) {
          for (let i = 1; i < regionSelect.options.length; i++) {
            const opt = regionSelect.options[i];
            allRegions.push({ value: opt.value, text: opt.textContent, selected: opt.selected });
          }
        }

        const subregionSelect = document.getElementById('subregion_id');
        if (subregionSelect) {
          for (let i = 1; i < subregionSelect.options.length; i++) {
            const opt = subregionSelect.options[i];
            allSubregions.push({ value: opt.value, text: opt.textContent, selected: opt.selected });
          }
        }

        const appellationSelect = document.getElementById('appellation_id');
        if (appellationSelect) {
          for (let i = 1; i < appellationSelect.options.length; i++) {
            const opt = appellationSelect.options[i];
            allAppellations.push({ value: opt.value, text: opt.textContent, selected: opt.selected });
          }
        }

        const vineyardSelect = document.getElementById('vineyard_id');
        if (vineyardSelect) {
          for (let i = 1; i < vineyardSelect.options.length; i++) {
            const opt = vineyardSelect.options[i];
            allVineyards.push({
              value: opt.value,
              text: opt.textContent,
              selected: opt.selected,
              vineyard: opt.getAttribute('data-vineyard') || ''
            });
          }
          vineyardSelect.addEventListener('change', updateWineNamePreview);
        }

        const grapeSelect = document.getElementById('grape');
        if (grapeSelect) {
          grapeSelect.addEventListener('change', updateWineNamePreview);
        }

        const nameInput = document.getElementById('name');
        if (nameInput) {
          nameInput.addEventListener('input', updateWineNamePreview);
          nameInput.addEventListener('keyup', updateWineNamePreview);
          nameInput.addEventListener('change', updateWineNamePreview);
        }

        const nameConvSelect = document.getElementById('nameconvention');
        if (nameConvSelect) {
          nameConvSelect.addEventListener('change', updateWineNamePreview);
        }

        updateWineNamePreview();
      });

      function filterProducers() {
        const query = document.getElementById('searchProducerBox').value.toLowerCase().trim();
        const select = document.getElementById('producer_id');
        if (!select) return;
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allProducers.forEach(r => {
          const matches = terms.every(term => r.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value;
            opt.textContent = r.text;
            if (r.producer) opt.setAttribute('data-producer', r.producer);
            if (r.value === currentValue) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function filterRegions() {
        const query = document.getElementById('searchRegionBox').value.toLowerCase().trim();
        const select = document.getElementById('region_id');
        if (!select) return;
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allRegions.forEach(r => {
          const matches = terms.every(term => r.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value; opt.textContent = r.text;
            if (r.value === currentValue) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function filterSubregions() {
        const query = document.getElementById('searchSubregionBox').value.toLowerCase().trim();
        const select = document.getElementById('subregion_id');
        if (!select) return;
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allSubregions.forEach(r => {
          const matches = terms.every(term => r.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value; opt.textContent = r.text;
            if (r.value === currentValue) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function filterAppellations() {
        const query = document.getElementById('searchAppellationBox').value.toLowerCase().trim();
        const select = document.getElementById('appellation_id');
        if (!select) return;
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allAppellations.forEach(r => {
          const matches = terms.every(term => r.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value; opt.textContent = r.text;
            if (r.value === currentValue) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }

      function filterVineyards() {
        const query = document.getElementById('searchVineyardBox').value.toLowerCase().trim();
        const select = document.getElementById('vineyard_id');
        if (!select) return;
        const currentValue = select.value;
        const terms = query.split(/\s+/).filter(t => t.length > 0);
        while (select.options.length > 1) select.remove(1);
        allVineyards.forEach(r => {
          const matches = terms.every(term => r.text.toLowerCase().includes(term));
          if (matches) {
            const opt = document.createElement('option');
            opt.value = r.value;
            opt.textContent = r.text;
            if (r.vineyard) opt.setAttribute('data-vineyard', r.vineyard);
            if (r.value === currentValue) opt.selected = true;
            select.appendChild(opt);
          }
        });
      }
    </script>
  HTML;

  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Add a new wine master</h3>
        <?php
          if (!empty($errors)) {
            echo "<div style='color: red;'><ul>";
            foreach ($errors as $error) {
              echo "<li>" . $error . "</li>";
            }
            echo "</ul></div>";
          }

          if (!empty($success_message)) {
            echo "<div style='color: green;'>" . $success_message . "</div>";
          }
        ?>

        <form method="POST" accept-charset="UTF-8">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Producer:</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchProducerBox" onkeyup="filterProducers()"
              placeholder="🔍 Search producer..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="producer_id" id="producer_id" required
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="">Select a producer</option>
              <?php foreach ($producers as $producer): ?>
                <option value="<?php echo $producer['producer_id']; ?>" data-producer="<?php echo htmlspecialchars($producer['producer'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($producer['producer_id'] == $producer_id) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($producer['country'], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($producer['region'], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($producer['producer'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Region:</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchRegionBox" onkeyup="filterRegions()"
              placeholder="🔍 Search region..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="region_id" id="region_id" required
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="">Select a region</option>
              <?php foreach ($regions as $region): ?>
                <option value="<?php echo $region['region_id']; ?>" <?php echo ($region['region_id'] == $region_id) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($region['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($region['region'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Subregion (optional):</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchSubregionBox" onkeyup="filterSubregions()"
              placeholder="🔍 Search subregion..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="subregion_id" id="subregion_id"
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="" selected>Select a subregion</option>
              <?php foreach ($subregions as $subregion): ?>
                <option value="<?php echo $subregion['subregion_id']; ?>" <?php echo ($subregion['subregion_id'] == $subregion_id) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($subregion['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($subregion['subregion'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Appellation (optional):</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchAppellationBox" onkeyup="filterAppellations()"
              placeholder="🔍 Search appellation..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="appellation_id" id="appellation_id"
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="" selected>Select an appellation</option>
              <?php foreach ($appellations as $appellation): ?>
                <option value="<?php echo $appellation['appellation_id']; ?>" <?php echo ($appellation['appellation_id'] == $appellation_id) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($appellation['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['region'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($appellation['appellation'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <label style="font-size: small; font-weight: bold; display: block; margin-bottom: 5px;">Vineyard (optional):</label>
          <div style="border: 1px solid #ccc; border-radius: 4px; max-width: 400px; font-family: Georgia, serif; box-sizing: border-box; background: white; margin-bottom: 15px;">
            <input type="text" id="searchVineyardBox" onkeyup="filterVineyards()"
              placeholder="🔍 Search vineyard..."
              style="width: 100%; border: none; border-bottom: 1px solid #eee; padding: 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 4px 4px 0 0; background: #fafafa;"
              autocomplete="off">
            <select name="vineyard_id" id="vineyard_id"
              style="width: 100%; border: none; padding: 8px 36px 8px 8px; box-sizing: border-box; font-family: Georgia, serif; font-size: small; outline: none; border-radius: 0 0 4px 4px; background: transparent; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23666%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 14px auto;">
              <option value="" selected>Select a vineyard</option>
              <?php foreach ($vineyards as $vineyard): ?>
                <option value="<?php echo $vineyard['vineyard_id']; ?>" data-vineyard="<?php echo htmlspecialchars($vineyard['vineyard'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($vineyard['vineyard_id'] == $vineyard_id) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($vineyard['country'], ENT_QUOTES, 'UTF-8') . ": " . htmlspecialchars($vineyard['region'], ENT_QUOTES, 'UTF-8') . ": " . (($vineyard['appellation']!=null) ? htmlspecialchars($vineyard['appellation'], ENT_QUOTES, 'UTF-8') . ": " : "") . htmlspecialchars($vineyard['vineyard'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <br><br>
          <label for="cuvee_yn">Cuvée yes/no:</label><br>
          <select name="cuvee_yn" id="cuvee_yn" required>
            <option value="">Select an option</option>
            <option value="yes" <?php echo ($cuvee_yn == 'yes') ? 'selected' : ''; ?>>Yes</option>
            <option value="no" <?php echo ($cuvee_yn == 'no') ? 'selected' : ''; ?>>No</option>
          </select>

          <br><br>
          <label for="grape">Variety:</label><br>
          <select name="grape" id="grape" required>
            <option value="">Select a variety</option>
            <?php foreach ($varieties as $variety): ?>
              <option value="<?php echo $variety['grape']; ?>" <?php echo ($variety['grape'] == $grape) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($variety['grape'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="colour">Colour:</label><br>
          <select name="colour" id="colour" required>
            <option value="">Select a colour</option>
            <?php foreach ($colours as $colour_db): ?>
              <option value="<?php echo $colour_db['colour']; ?>" <?php echo ($colour_db['colour'] == $colour) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($colour_db['colour'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="style">Style:</label><br>
          <select name="style" id="style" required>
            <option value="">Select a style</option>
            <?php foreach ($styles as $style_db): ?>
              <option value="<?php echo $style_db['style']; ?>" <?php echo ($style_db['style'] == $style) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($style_db['style'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <br><br>
          <label for="name">Name:</label><br>
          <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" size="50" required maxlength="100">

          <br><br>
          <label for="nameconvention">Name convention:</label><br>
          <select name="nameconvention" id="nameconvention" required>
            <option value="">Select a convention</option>
            <?php foreach ($nameconventions as $nameconvention_db): ?>
              <option value="<?php echo $nameconvention_db['nameconvention']; ?>" <?php echo ($nameconvention_db['nameconvention'] == $nameconvention) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($nameconvention_db['nameconvention'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <div id="wineNamePreviewCard" style="margin-top: 15px; margin-bottom: 20px; padding: 12px 15px; background: #fafafa; border: 1px solid #e0e0e0; border-left: 4px solid firebrick; border-radius: 4px; max-width: 500px; font-family: Georgia, serif; box-sizing: border-box;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
              <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #888;">
                🍷 Wine Name Preview
              </span>
              <span id="previewConventionBadge" style="font-size: 11px; background: #eee; color: #555; padding: 2px 6px; border-radius: 3px; font-family: monospace;">
                none selected
              </span>
            </div>
            <div id="previewWineName" style="font-size: 15px; font-weight: bold; color: firebrick; min-height: 20px; word-break: break-word;">
              <span style="color: #999; font-weight: normal; font-style: italic;">Select a naming convention to preview the wine name</span>
            </div>
          </div>

          <br><br>
          <input type="submit" name="update" value="Insert Wine Master">
        </form>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Latest stories</h3>
      <p>While this website is dedicated to the wines I've tasted, you will find other wine-related articles and stories on my blog:</p>
      <p><i><a href="/blog.php" title="All wine stories">Read all...</a></i></p>
    </div>

    <div class="card">
    <h3>Get in touch</h3>
    <p>
      I don't control access to this website. My texts are open for everyone to read. That means I don't know who you are. If you'd like
      to introduce yourself and connect with me, you may do so via my profile on
      <a href="https://www.cellartracker.com/user.asp?iUserOverride=697203">CellarTracker</a>. I'm always happy to hear and learn from
      other wine enthusiasts.
    </p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
