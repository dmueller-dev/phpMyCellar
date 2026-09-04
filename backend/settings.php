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
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die('CSRF token validation failed');
    }

    $site_name = sanitizeInput($_POST['site_name'] ?? '');
    $site_tagline = sanitizeInput($_POST['site_tagline'] ?? '');
    $site_url = trim($_POST['site_url'] ?? '');
    $owner_name = sanitizeInput($_POST['owner_name'] ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $owner_address = trim($_POST['owner_address'] ?? '');
    $currency_symbol = trim($_POST['currency_symbol'] ?? '€');
    $rating_scale = trim($_POST['rating_scale'] ?? '20-point');
    $wset_mode = trim($_POST['wset_mode'] ?? 'public');
    if (!in_array($wset_mode, ['public', 'logged_in', 'backend_only', 'disabled'], true)) {
      $wset_mode = 'public';
    }
    $wset_display_format = trim($_POST['wset_display_format'] ?? 'standard');
    if (!in_array($wset_display_format, ['standard', 'detailed'], true)) {
      $wset_display_format = 'standard';
    }
    $meta_description = sanitizeInput($_POST['meta_description'] ?? '');
    $theme_accent_color = trim($_POST['theme_accent_color'] ?? '#CD5C5C');
    $theme_accent_secondary = trim($_POST['theme_accent_secondary'] ?? '#B22222');
    $theme_accent_hover = trim($_POST['theme_accent_hover'] ?? '#8B0000');
    $logo_url = trim($_POST['logo_url'] ?? '/uploads/img/logo_web.webp');

    if (empty($site_name)) {
      $errors[] = 'Site Name cannot be empty.';
    }
    if (!empty($owner_email) && !filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Please provide a valid owner email address.';
    }

    if (empty($errors)) {
      updateSiteSetting('site_name', $site_name, 'general');
      updateSiteSetting('site_tagline', $site_tagline, 'general');
      updateSiteSetting('site_url', $site_url, 'general');
      updateSiteSetting('owner_name', $owner_name, 'general');
      updateSiteSetting('owner_email', $owner_email, 'general');
      updateSiteSetting('owner_address', $owner_address, 'general');
      updateSiteSetting('currency_symbol', $currency_symbol, 'general');
      updateSiteSetting('rating_scale', $rating_scale, 'general');
      updateSiteSetting('wset_mode', $wset_mode, 'general');
      updateSiteSetting('wset_display_format', $wset_display_format, 'general');
      deleteSiteSetting('wset_enabled');
      updateSiteSetting('meta_description', $meta_description, 'general');
      updateSiteSetting('theme_accent_color', $theme_accent_color, 'theme');
      updateSiteSetting('theme_accent_secondary', $theme_accent_secondary, 'theme');
      updateSiteSetting('theme_accent_hover', $theme_accent_hover, 'theme');
      updateSiteSetting('logo_url', $logo_url, 'theme');

      $success_message = 'Settings have been updated successfully.';
    }
  }

  $site_name = getSiteSetting('site_name', 'phpMyCellar');
  $site_tagline = getSiteSetting('site_tagline', 'Fine Wine Cellar & Tasting Notes');
  $site_url = getSiteSetting('site_url', 'http://localhost:8000');
  $owner_name = getOwnerName();
  $owner_email = getOwnerEmail();
  $owner_address = getSiteSetting('owner_address', '');
  $currency_symbol = getCurrencySymbol();
  $rating_scale = getSiteSetting('rating_scale', '20-point');
  $wset_mode = getWsetSATMode();
  $wset_display_format = getWsetSATDisplayFormat();
  $meta_description = getSiteSetting('meta_description', '');
  $theme_accent_color = getSiteSetting('theme_accent_color', '#CD5C5C');
  $theme_accent_secondary = getSiteSetting('theme_accent_secondary', '#B22222');
  $theme_accent_hover = getSiteSetting('theme_accent_hover', '#8B0000');
  $logo_url = getSiteSetting('logo_url', '/uploads/img/logo_web.webp');

  $page_title = 'Site Settings - Administration';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h2>Site Settings &amp; Configuration</h2>
        <p>Configure general site metadata, branding, and default cellar options below.</p>

        <?php if (!empty($errors)): ?>
          <div style="background-color:#ffdddd;border-left:5px solid #f44336;padding:10px 15px;margin-bottom:20px;">
            <ul style="margin:0;padding-left:20px;color:#a00;">
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
          <div style="background-color:#ddffdd;border-left:5px solid #4CAF50;padding:10px 15px;margin-bottom:20px;color:#2e7d32;">
            <?php echo $success_message; ?>
          </div>
        <?php endif; ?>

        <form action="settings.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

          <h3>General Site Settings</h3>

          <div style="margin-bottom:15px;">
            <label for="site_name"><strong>Site Name:</strong></label><br>
            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?>" required style="width:100%;max-width:500px;padding:8px;">
            <br><small style="color:#666;">Displayed in browser title bars, header links, and metadata.</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="site_tagline"><strong>Tagline:</strong></label><br>
            <input type="text" id="site_tagline" name="site_tagline" value="<?php echo htmlspecialchars($site_tagline, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;max-width:500px;padding:8px;">
            <br><small style="color:#666;">Short tagline describing your site (e.g. <em>Fine Wine Cellar &amp; Tasting Notes</em>).</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="site_url"><strong>Base Website URL:</strong></label><br>
            <input type="text" id="site_url" name="site_url" value="<?php echo htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;max-width:500px;padding:8px;">
            <br><small style="color:#666;">Canonical base URL (e.g. <code>https://yourdomain.com</code> or <code>http://localhost:8000</code>).</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="meta_description"><strong>Default Meta Description:</strong></label><br>
            <textarea id="meta_description" name="meta_description" rows="3" style="width:100%;max-width:600px;padding:8px;"><?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <br><small style="color:#666;">Search engine snippet description used across fallback pages.</small>
          </div>

          <hr style="margin:25px 0;">
          <h3>Owner &amp; Contact Details</h3>

          <div style="margin-bottom:15px;">
            <label for="owner_name"><strong>Cellar Master / Owner Name:</strong></label><br>
            <input type="text" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($owner_name, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;max-width:500px;padding:8px;">
            <br><small style="color:#666;">Name used in copyright, structured author metadata, and email signatures.</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="owner_email"><strong>Contact / System Email:</strong></label><br>
            <input type="email" id="owner_email" name="owner_email" value="<?php echo htmlspecialchars($owner_email, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;max-width:500px;padding:8px;">
            <br><small style="color:#666;">Used for notification emails and displayed in the footer contact notice.</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="owner_address"><strong>Postal Address (for Impressum / Footer):</strong></label><br>
            <textarea id="owner_address" name="owner_address" rows="4" style="width:100%;max-width:500px;padding:8px;"><?php echo htmlspecialchars($owner_address, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <br><small style="color:#666;">Optional legal contact address.</small>
          </div>

          <hr style="margin:25px 0;">
          <h3>Formatting &amp; Evaluation Defaults</h3>

          <div style="margin-bottom:15px;">
            <label for="currency_symbol"><strong>Default Currency Symbol:</strong></label><br>
            <input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'); ?>" style="width:80px;padding:8px;">
            <small style="color:#666;">E.g. €, $, £, CHF</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="rating_scale"><strong>Primary Rating Scale:</strong></label><br>
            <select id="rating_scale" name="rating_scale" style="padding:8px;">
              <option value="20-point" <?php echo ($rating_scale === '20-point') ? 'selected' : ''; ?>>20-Point Scale (0 to 20)</option>
              <option value="100-point" <?php echo ($rating_scale === '100-point') ? 'selected' : ''; ?>>100-Point Scale (50 to 100)</option>
            </select>
            <br><small style="color:#666;">Choose between traditional 20-point scoring or international 100-point scoring. Scores are stored separately in the database.</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="wset_mode"><strong>WSET SAT Evaluation (0.0 to 4.0):</strong></label><br>
            <select id="wset_mode" name="wset_mode" style="padding:8px;" onchange="toggleWsetDisplayFormat(this.value)">
              <option value="public" <?php echo ($wset_mode === 'public') ? 'selected' : ''; ?>>Public &mdash; Enter in backend, show publicly on tasting notes</option>
              <option value="logged_in" <?php echo ($wset_mode === 'logged_in') ? 'selected' : ''; ?>>Members Only &mdash; Enter in backend, visible only to logged-in users</option>
              <option value="backend_only" <?php echo ($wset_mode === 'backend_only') ? 'selected' : ''; ?>>Backend Only &mdash; Enter in backend for cellar tracking, hide from public site</option>
              <option value="disabled" <?php echo ($wset_mode === 'disabled') ? 'selected' : ''; ?>>Disabled &mdash; Completely turned off across the site</option>
            </select>
            <br><small style="color:#666;">Controls whether WSET Systematic Approach to Tasting (Balance, Length, Intensity, Complexity) is enabled and who can view the ratings.</small>
          </div>

          <div id="wset_format_container" style="margin-bottom:15px; <?php echo ($wset_mode === 'backend_only' || $wset_mode === 'disabled') ? 'display:none;' : ''; ?>">
            <label for="wset_display_format"><strong>WSET Display Format:</strong></label><br>
            <select id="wset_display_format" name="wset_display_format" style="padding:8px;">
              <option value="standard" <?php echo ($wset_display_format === 'standard') ? 'selected' : ''; ?>>Standard &mdash; Total score &amp; qualitative assessment (e.g. 3.5 / 4.0 &quot;Very Good&quot;)</option>
              <option value="detailed" <?php echo ($wset_display_format === 'detailed') ? 'selected' : ''; ?>>Detailed &mdash; Total score plus BLIC breakdown (Balance, Length, Intensity, Complexity)</option>
            </select>
            <br><small style="color:#666;">Choose whether to show the overall score or additionally show the 4 individual BLIC criteria values on public tasting notes.</small>
          </div>

          <hr style="margin:25px 0;">
          <h3>Theme &amp; Branding</h3>

          <div style="margin-bottom:15px;">
            <label for="theme_accent_color"><strong>Primary Accent Colour (IndianRed):</strong></label><br>
            <input type="color" id="theme_accent_color" name="theme_accent_color" value="<?php echo htmlspecialchars($theme_accent_color, ENT_QUOTES, 'UTF-8'); ?>" style="height:40px;width:80px;vertical-align:middle;">
            <input type="text" value="<?php echo htmlspecialchars($theme_accent_color, ENT_QUOTES, 'UTF-8'); ?>" onchange="document.getElementById('theme_accent_color').value=this.value;" style="width:120px;padding:8px;margin-left:10px;">
            <br><small style="color:#666;">Used for navigation bar, interactive buttons, borders, and pills (default: <code>#CD5C5C</code>).</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="theme_accent_secondary"><strong>Secondary Accent Colour (Firebrick):</strong></label><br>
            <input type="color" id="theme_accent_secondary" name="theme_accent_secondary" value="<?php echo htmlspecialchars($theme_accent_secondary, ENT_QUOTES, 'UTF-8'); ?>" style="height:40px;width:80px;vertical-align:middle;">
            <input type="text" value="<?php echo htmlspecialchars($theme_accent_secondary, ENT_QUOTES, 'UTF-8'); ?>" onchange="document.getElementById('theme_accent_secondary').value=this.value;" style="width:120px;padding:8px;margin-left:10px;">
            <br><small style="color:#666;">Used for text links, vintage titles, stats, active tabs, and rating highlights (default: <code>#B22222</code>).</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="theme_accent_hover"><strong>Accent Hover Colour (DarkRed):</strong></label><br>
            <input type="color" id="theme_accent_hover" name="theme_accent_hover" value="<?php echo htmlspecialchars($theme_accent_hover, ENT_QUOTES, 'UTF-8'); ?>" style="height:40px;width:80px;vertical-align:middle;">
            <input type="text" value="<?php echo htmlspecialchars($theme_accent_hover, ENT_QUOTES, 'UTF-8'); ?>" onchange="document.getElementById('theme_accent_hover').value=this.value;" style="width:120px;padding:8px;margin-left:10px;">
            <br><small style="color:#666;">Used for hover and active states on buttons and menus (default: <code>#8B0000</code>).</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="logo_url"><strong>Logo Path / URL:</strong></label><br>
            <input type="text" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;max-width:500px;padding:8px;">
            <br><small style="color:#666;">Relative path (e.g. <code>/uploads/img/logo_web.webp</code>) or full URL.</small>
          </div>

          <div style="margin-top:30px;">
            <button type="submit" class="btn-action" style="padding:10px 24px;font-size:16px;">Save Settings</button>
          </div>
        </form>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Quick Links</h3>
      <ul>
        <li><a href="manageStaticPages.php">Manage Static Pages</a></li>
        <li><a href="managePrivileges.php">User &amp; Role Privileges</a></li>
        <li><a href="index.php">Backend Dashboard</a></li>
      </ul>
    </div>
  </div>
</div>

<script>
function toggleWsetDisplayFormat(mode) {
  var container = document.getElementById('wset_format_container');
  if (container) {
    container.style.display = (mode === 'backend_only' || mode === 'disabled') ? 'none' : 'block';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
