<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  $page_title = getSiteTitle() . ' - Admin Hub';
  require_once __DIR__ . '/../includes/header.php';

  // Retrieve current user details for the welcome banner
  $user_id = $_SESSION['user_id'] ?? null;
  $displayUsername = 'Cellar Master';
  $displayRole = 'Administrator';

  if ($user_id) {
    try {
      $currentUser = getUserDetails($conn, $user_id);
      if ($currentUser) {
        $displayUsername = !empty($currentUser['displayname']) ? $currentUser['displayname'] : (!empty($currentUser['username']) ? $currentUser['username'] : 'Cellar Master');
        if (!empty($currentUser['role'])) {
          $roles = getAllRoles($conn);
          foreach ($roles as $r) {
            if ($r['role_name'] === $currentUser['role']) {
              $displayRole = $r['display_name'];
              break;
            }
          }
        }
      }
    } catch (Throwable $e) {
      // Gracefully fall back to defaults
    }
  }
  $displayUsername = htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8');
  $displayRole = htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8');

  // Query Key Performance Indicators (KPIs)
  $kpi_bottles = 0;
  $kpi_unique_wines = 0;
  try {
    $res = $mysqli->query("SELECT count(bottle_id) as total_btls, count(distinct wine_id) as total_wines FROM bottles WHERE status='in cellar'");
    if ($res && $row = $res->fetch_assoc()) {
      $kpi_bottles = (int)$row['total_btls'];
      $kpi_unique_wines = (int)$row['total_wines'];
      $res->free_result();
    }
  } catch (Throwable $e) {}

  $current_year = (int)date('Y');
  $kpi_ready = 0;
  try {
    $res = $mysqli->query("SELECT count(bottle_id) as ready_cnt FROM bottles WHERE status='in cellar' AND (drink_from <= $current_year OR drink_from IS NULL OR drink_from = 0)");
    if ($res && $row = $res->fetch_assoc()) {
      $kpi_ready = (int)$row['ready_cnt'];
      $res->free_result();
    }
  } catch (Throwable $e) {}
  $ready_pct = ($kpi_bottles > 0) ? round(($kpi_ready / $kpi_bottles) * 100) : 0;
  $ready_url = hasPrivilege($conn, 'view_cellar_menu') ? '/winemenu.php' : 'browseBottles.php?sort=vintage';

  $kpi_wines = 0;
  try {
    $res = $mysqli->query("SELECT count(*) as cnt FROM wines");
    if ($res && $row = $res->fetch_assoc()) {
      $kpi_wines = (int)$row['cnt'];
      $res->free_result();
    }
  } catch (Throwable $e) {}

  $kpi_masters = 0;
  try {
    $res = $mysqli->query("SELECT count(*) as cnt FROM wines_master");
    if ($res && $row = $res->fetch_assoc()) {
      $kpi_masters = (int)$row['cnt'];
      $res->free_result();
    }
  } catch (Throwable $e) {}

  $kpi_tnotes = 0;
  try {
    $res = $mysqli->query("SELECT count(*) as cnt FROM tnotes");
    if ($res && $row = $res->fetch_assoc()) {
      $kpi_tnotes = (int)$row['cnt'];
      $res->free_result();
    }
  } catch (Throwable $e) {}

  $kpi_orders = 0;
  $canViewOrders = hasPrivilege($conn, 'manage_orders') || hasPrivilege($conn, 'add_order');
  if ($canViewOrders) {
    try {
      $open_orders = getOrders($conn, 'pending delivery');
      $kpi_orders = count($open_orders);
    } catch (Throwable $e) {
      $kpi_orders = 0;
    }
  }

  // Fetch Terroir counts for compact matrix
  $terroir_counts = [
    'producers' => 0,
    'appellations' => 0,
    'vineyards' => 0,
    'regions' => 0,
    'subregions' => 0,
    'countries' => 0,
    'variety' => 0,
  ];
  try {
    foreach (['producers', 'appellations', 'vineyards', 'regions', 'subregions', 'countries', 'variety'] as $tbl) {
      $r = $mysqli->query("SELECT count(*) as c FROM `{$tbl}`");
      if ($r && $row = $r->fetch_assoc()) {
        $terroir_counts[$tbl] = (int)$row['c'];
        $r->free_result();
      }
    }
  } catch (Throwable $e) {}
?>

<div class="admin-hero">
  <div class="admin-hero-content">
    <h1>Admin Hub</h1>
    <p class="admin-hero-sub">Cellar administration, cuv&eacute;e catalogue &amp; inventory control centre</p>
  </div>
  <div class="admin-user-badge">
    <span>&#128100; <strong><?php echo $displayUsername; ?></strong> (<?php echo $displayRole; ?>)</span>
    <span>&bull;</span>
    <span><?php echo date('M j, Y'); ?></span>
  </div>
</div>

<!-- At-a-Glance KPI Cards -->
<div class="admin-kpi-grid">
  <a href="browseBottles.php" class="admin-kpi-card" title="Browse physical bottles in cellar">
    <div class="kpi-top">
      <span class="kpi-val"><?php echo number_format($kpi_bottles); ?></span>
      <span class="kpi-icon">&#127870;</span>
    </div>
    <div class="kpi-label">Bottles in Cellar</div>
    <div class="kpi-sub">
      <span><?php echo number_format($kpi_unique_wines); ?> unique cuv&eacute;es</span>
      <span>Browse &rarr;</span>
    </div>
  </a>

  <a href="<?php echo $ready_url; ?>" class="admin-kpi-card kpi-accent-green" title="View wines currently ready to drink">
    <div class="kpi-top">
      <span class="kpi-val"><?php echo number_format($kpi_ready); ?></span>
      <span class="kpi-icon">&#127863;</span>
    </div>
    <div class="kpi-label">Ready to Drink</div>
    <div class="kpi-sub">
      <span class="kpi-badge"><?php echo $ready_pct; ?>% of cellar</span>
      <span>Carte des vins &rarr;</span>
    </div>
  </a>

  <a href="browseWines.php" class="admin-kpi-card kpi-accent-gold" title="Browse all catalogued wines and masters">
    <div class="kpi-top">
      <span class="kpi-val"><?php echo number_format($kpi_wines); ?></span>
      <span class="kpi-icon">&#127991;&#65039;</span>
    </div>
    <div class="kpi-label">Wine Catalogue</div>
    <div class="kpi-sub">
      <span><?php echo number_format($kpi_masters); ?> masters / cuv&eacute;es</span>
      <span>Catalogue &rarr;</span>
    </div>
  </a>

  <a href="/tnotes.php" class="admin-kpi-card kpi-accent-blue" title="Browse tasting notes">
    <div class="kpi-top">
      <span class="kpi-val"><?php echo number_format($kpi_tnotes); ?></span>
      <span class="kpi-icon">&#128221;</span>
    </div>
    <div class="kpi-label">Tasting Notes</div>
    <div class="kpi-sub">
      <span>Published reviews</span>
      <span>Notes &rarr;</span>
    </div>
  </a>

  <?php if ($canViewOrders): ?>
    <a href="manageOrders.php" class="admin-kpi-card kpi-accent-purple" title="View pending wine orders">
      <div class="kpi-top">
        <span class="kpi-val"><?php echo number_format($kpi_orders); ?></span>
        <span class="kpi-icon">&#128230;</span>
      </div>
      <div class="kpi-label">Pending Orders</div>
      <div class="kpi-sub">
        <span>Awaiting delivery</span>
        <span>Orders &rarr;</span>
      </div>
    </a>
  <?php endif; ?>
</div>

<!-- Quick Action Shortcuts Bar -->
<div class="admin-actions-bar">
  <?php if (hasPrivilege($conn, 'add_tasting_note')): ?>
    <a href="manageTastingNotes.php" class="btn-quick-action" title="Write a standard tasting note">
      <span>&#9997;&#65039;</span> Write Note
    </a>
    <a href="manageTastingNotes.php?mode=blind" class="btn-quick-action btn-secondary" title="Conduct a blind tasting and take notes">
      <span>&#128065;&#65039;</span> Blind Tasting
    </a>
  <?php endif; ?>

  <?php if (hasPrivilege($conn, 'add_bottle')): ?>
    <a href="manageBottles.php?mode=add" class="btn-quick-action" title="Add a new physical bottle to cellar inventory">
      <span>&#127870;</span> Add Bottle
    </a>
  <?php endif; ?>

  <?php if (hasPrivilege($conn, 'add_wine')): ?>
    <a href="manageWines.php" class="btn-quick-action" title="Add a new harvest vintage to the catalogue">
      <span>&#127815;</span> Add Wine Vintage
    </a>
  <?php endif; ?>

  <?php if (hasPrivilege($conn, 'add_order')): ?>
    <a href="manageOrders.php?action=create" class="btn-quick-action btn-secondary" title="Create a new wine purchase order">
      <span>&#128230;</span> New Order
    </a>
  <?php endif; ?>
</div>

<div class="row">
  <div class="column main">
    <div class="admin-domains-grid">

      <!-- Cellar & Inventory Management -->
      <?php if (hasPrivilege($conn, 'browse_bottles') || hasPrivilege($conn, 'add_bottle') || hasPrivilege($conn, 'edit_bottle') || hasPrivilege($conn, 'add_order') || hasPrivilege($conn, 'manage_orders') || hasPrivilege($conn, 'manage_storage_bins') || hasPrivilege($conn, 'manage_stores') || hasPrivilege($conn, 'manage_formats')): ?>
        <div class="admin-domain-card">
          <h3><span>&#127870;</span> Cellar &amp; Inventory</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'browse_bottles')): ?>
              <li>
                <a href="browseBottles.php" title="Browse physical bottles in cellar">
                  <span>Browse all bottles</span>
                  <span class="link-action">Browse &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_bottle') || hasPrivilege($conn, 'edit_bottle')): ?>
              <li>
                <a href="manageBottles.php" title="Add, edit, and manage bottle inventory and drinking windows">
                  <span>Manage bottle inventory</span>
                  <span class="link-action">Manage &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_storage_bins') || hasPrivilege($conn, 'browse_bottles')): ?>
              <li>
                <a href="manageStorageBins.php" title="Configure cellars, storage bins, and maximum capacities">
                  <span>Cellar storage bins</span>
                  <span class="link-action">Bins &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_orders') || hasPrivilege($conn, 'add_order')): ?>
              <li>
                <a href="manageOrders.php" title="Create purchase orders and accept deliveries">
                  <span>Purchase orders &amp; delivery</span>
                  <span class="link-action">Orders &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_stores') || hasPrivilege($conn, 'manage_orders')): ?>
              <li>
                <a href="manageStores.php" title="Manage wine merchants, retailers, and order sources">
                  <span>Wine merchants &amp; stores</span>
                  <span class="link-action">Stores &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_formats') || hasPrivilege($conn, 'add_bottle')): ?>
              <li>
                <a href="manageFormats.php" title="Configure bottle formats and standard liquid volumes">
                  <span>Bottle formats &amp; sizes</span>
                  <span class="link-action">Formats &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Wines & Masters Catalogue -->
      <?php if (hasPrivilege($conn, 'browse_wines') || hasPrivilege($conn, 'add_wine') || hasPrivilege($conn, 'edit_wine') || hasPrivilege($conn, 'add_wine_master') || hasPrivilege($conn, 'edit_wine_master') || hasPrivilege($conn, 'manage_varieties') || hasPrivilege($conn, 'manage_vintages') || hasPrivilege($conn, 'manage_wine_attributes')): ?>
        <div class="admin-domain-card">
          <h3><span>&#127991;&#65039;</span> Wine Catalogue &amp; Masters</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'browse_wines')): ?>
              <li>
                <a href="browseWines.php" title="Browse all catalogued wines and masters">
                  <span>Browse wine catalogue</span>
                  <span class="link-action">Browse &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_wine_master') || hasPrivilege($conn, 'edit_wine_master')): ?>
              <li>
                <a href="manageWineMasters.php" title="Add, edit, or configure master cuv&eacute;e profiles and grape c&eacute;pages">
                  <span>Wine master profiles</span>
                  <span class="link-action">Masters &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_wine') || hasPrivilege($conn, 'edit_wine')): ?>
              <li>
                <a href="manageWines.php" title="Add, edit, or configure specific harvest vintages and tasting parameters">
                  <span>Wine harvest vintages</span>
                  <span class="link-action">Vintages &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_varieties') || hasPrivilege($conn, 'edit_wine_master')): ?>
              <li>
                <a href="manageVarieties.php" title="Manage grape varieties and c&eacute;page classifications">
                  <span>Grape varieties &amp; c&eacute;pages</span>
                  <span class="link-action">Varieties &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_vintages') || hasPrivilege($conn, 'edit_wine')): ?>
              <li>
                <a href="manageVintages.php" title="Manage harvest vintage years and regional character reports">
                  <span>Vintage reports &amp; charts</span>
                  <span class="link-action">Reports &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_wine_attributes') || hasPrivilege($conn, 'edit_wine_master')): ?>
              <li>
                <a href="manageWineAttributes.php" title="Manage wine classifications, styles, colours, and display options">
                  <span>Wine styles &amp; attributes</span>
                  <span class="link-action">Attributes &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Tasting Notes & Editorial -->
      <?php if (hasPrivilege($conn, 'add_tasting_note') || hasPrivilege($conn, 'edit_tasting_note') || hasPrivilege($conn, 'edit_all_tasting_notes') || hasPrivilege($conn, 'add_blogpost') || hasPrivilege($conn, 'edit_blogpost') || hasPrivilege($conn, 'edit_all_blogposts') || hasPrivilege($conn, 'moderate_comments')): ?>
        <div class="admin-domain-card">
          <h3><span>&#128221;</span> Tasting Notes &amp; Editorial</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'add_tasting_note') || hasPrivilege($conn, 'edit_tasting_note') || hasPrivilege($conn, 'edit_all_tasting_notes')): ?>
              <li>
                <a href="manageTastingNotes.php" title="Write, edit, and review standard tasting notes">
                  <span>Tasting notes journal</span>
                  <span class="link-action">Journal &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_tasting_note')): ?>
              <li>
                <a href="manageTastingNotes.php?mode=blind" title="Conduct a blind tasting assessment">
                  <span>Blind tasting session</span>
                  <span class="link-action">Blind &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_blogpost') || hasPrivilege($conn, 'edit_blogpost') || hasPrivilege($conn, 'edit_all_blogposts')): ?>
              <li>
                <a href="manageBlogposts.php" title="Compose, publish, and edit cellar stories and news articles">
                  <span>Cellar stories &amp; articles</span>
                  <span class="link-action">Stories &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'moderate_comments') || hasPrivilege($conn, 'manage_privileges')): ?>
              <li>
                <a href="manageComments.php" title="Moderate public tasting note comments and review approvals">
                  <span>Reader comments &amp; moderation</span>
                  <span class="link-action">Comments &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Terroir & Geography Matrix -->
      <?php if (hasPrivilege($conn, 'manage_producers') || hasPrivilege($conn, 'manage_countries') || hasPrivilege($conn, 'manage_regions') || hasPrivilege($conn, 'manage_subregions') || hasPrivilege($conn, 'manage_appellations') || hasPrivilege($conn, 'manage_vineyards') || hasPrivilege($conn, 'manage_varieties')): ?>
        <div class="admin-domain-card">
          <h3><span>&#127757;</span> Terroir &amp; Geography</h3>
          <table class="admin-terroir-table">
            <tbody>
              <?php if (hasPrivilege($conn, 'manage_producers')): ?>
                <tr>
                  <td class="admin-terroir-name">Producers</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['producers']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="manageProducers.php" class="terroir-btn terroir-btn-manage" title="Add and manage wine producers">Manage &rarr;</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_varieties') || hasPrivilege($conn, 'edit_wine_master')): ?>
                <tr>
                  <td class="admin-terroir-name">Grape Varieties</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['variety']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="manageVarieties.php" class="terroir-btn terroir-btn-manage" title="Add and manage grape varieties">Manage &rarr;</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_appellations')): ?>
                <tr>
                  <td class="admin-terroir-name">Appellations</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['appellations']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="manageAppellations.php" class="terroir-btn terroir-btn-manage" title="Add and manage appellations">Manage &rarr;</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_vineyards')): ?>
                <tr>
                  <td class="admin-terroir-name">Vineyards</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['vineyards']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="manageVineyards.php" class="terroir-btn terroir-btn-manage" title="Add and manage vineyards">Manage &rarr;</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_regions')): ?>
                <tr>
                  <td class="admin-terroir-name">Regions</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['regions']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="manageRegions.php" class="terroir-btn terroir-btn-manage" title="Add and manage regions">Manage &rarr;</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_subregions')): ?>
                <tr>
                  <td class="admin-terroir-name">Subregions</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['subregions']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="manageSubregions.php" class="terroir-btn terroir-btn-manage" title="Add and manage subregions">Manage &rarr;</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_countries')): ?>
                <tr>
                  <td class="admin-terroir-name">Countries</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['countries']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="manageCountries.php" class="terroir-btn terroir-btn-manage" title="Add and manage countries">Manage &rarr;</a>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Site Settings & User Administration -->
      <?php if (hasPrivilege($conn, 'manage_privileges') || hasPrivilege($conn, 'manage_users') || hasPrivilege($conn, 'manage_rating_scales') || hasPrivilege($conn, 'manage_static_pages')): ?>
        <div class="admin-domain-card">
          <h3><span>&#9881;&#65039;</span> System &amp; Settings</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'manage_privileges')): ?>
              <li>
                <a href="settings.php" title="Branding, accent colours, scoring system">
                  <span>Site settings &amp; branding</span>
                  <span class="link-action">Settings &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_static_pages') || hasPrivilege($conn, 'manage_privileges')): ?>
              <li>
                <a href="manageStaticPages.php" title="Custom static text, sidebar notices, about content">
                  <span>Static content pages</span>
                  <span class="link-action">Pages &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_privileges')): ?>
              <li>
                <a href="managePrivileges.php" title="Configure role permissions and user privilege overrides">
                  <span>User &amp; role privileges</span>
                  <span class="link-action">Roles &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_rating_scales') || hasPrivilege($conn, 'manage_privileges')): ?>
              <li>
                <a href="manageRatingScales.php" title="Configure 20-point, 100-point, and WSET qualitative score descriptors">
                  <span>Rating scales &amp; assessment</span>
                  <span class="link-action">Scales &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_users')): ?>
              <li>
                <a href="manageUsers.php" title="Add, edit, and manage user accounts and passwords">
                  <span>User accounts &amp; security</span>
                  <span class="link-action">Users &rarr;</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Sidebar: Cellar Storage Breakdown & System Overview -->
  <div class="column side">
    <div class="admin-storage-widget">
      <h3>
        <span>&#128452;&#65039; Storage Locations</span>
        <span>
          <?php if (hasPrivilege($conn, 'manage_storage_bins') || hasPrivilege($conn, 'browse_bottles')): ?>
            <a href="manageStorageBins.php" style="font-size:0.8rem; font-weight:normal; text-decoration:none; margin-right:8px;" title="Manage storage bins and capacities">Manage &rarr;</a>
          <?php endif; ?>
          <a href="browseBottles.php" style="font-size:0.8rem; font-weight:normal; text-decoration:none;" title="View all physical bottles">Browse &rarr;</a>
        </span>
      </h3>
      <?php renderCellarStorageWidget(); ?>
    </div>

    <div class="admin-system-card">
      <h3>&#9432; System Status</h3>
      <ul class="system-status-list">
        <li><span>Software</span> <strong>phpMyCellar</strong></li>
        <li><span>PHP Runtime</span> <strong>v<?php echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; ?></strong></li>
        <li><span>Database</span> <strong>Connected (MySQL)</strong></li>
        <li><span>Total Inventory</span> <strong><?php echo number_format($kpi_bottles); ?> bottles</strong></li>
        <li><span>Wine Catalogue</span> <strong><?php echo number_format($kpi_wines); ?> vintages</strong></li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Render the cellar storage breakdown widget with optional capacity and percentage usage indicators.
 */
function renderCellarStorageWidget()
{
  global $mysqli, $conn;

  $showUsagePct = (getSiteSetting('show_storage_usage_pct', '1') === '1');
  $hasCapacity = hasStorageBinsMaxCapacityColumn($mysqli);

  $rows = [];
  $cellarTotals = [];
  $cellarCapacities = [];

  $capSelect = $hasCapacity ? "sb.max_capacity," : "NULL AS max_capacity,";

  try {
    $result = $mysqli->query(
      "SELECT
        sb.bin_id,
        sb.bin_name,
        COALESCE(c.cellar_name, 'Unassigned Cellar') AS cellar_name,
        " . $capSelect . "
        COUNT(CASE WHEN bottles.status = 'in cellar' THEN 1 END) AS btls
      FROM storageBins sb
        LEFT JOIN cellars c ON sb.cellar_id = c.cellar_id
        LEFT JOIN bottles ON sb.bin_id = bottles.storage_location
      GROUP BY sb.bin_id, sb.bin_name, c.cellar_name" . ($hasCapacity ? ", sb.max_capacity" : "") . "
      ORDER BY c.cellar_name ASC, sb.bin_name ASC"
    );

    if ($result) {
      while ($r = $result->fetch_assoc()) {
        $cName = $r['cellar_name'];
        $rows[] = $r;
        if (!isset($cellarTotals[$cName])) {
          $cellarTotals[$cName] = 0;
          $cellarCapacities[$cName] = 0;
        }
        $cellarTotals[$cName] += (int)$r['btls'];
        if ($r['max_capacity'] !== null) {
          $cellarCapacities[$cName] += (int)$r['max_capacity'];
        }
      }
      $result->free_result();
    }
  } catch (Throwable $e) {
    // Graceful fallback on database error
  }

  // Check for any unassigned bottles
  $unassignedCount = 0;
  try {
    $uRes = $mysqli->query("SELECT count(bottle_id) as cnt FROM bottles WHERE status='in cellar' AND (storage_location IS NULL OR storage_location = 0)");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
      $unassignedCount = (int)$uRow['cnt'];
      $uRes->free_result();
    }
  } catch (Throwable $e) {}

  if (empty($rows) && $unassignedCount === 0) {
    echo "<p style='color:#777; font-size:0.88rem; margin:0;'>No storage bins currently configured.</p>";
    return;
  }

  $prevCellar = null;
  $inCellarBlock = false;

  foreach ($rows as $storedBtls) {
    $cName = $storedBtls['cellar_name'];
    if ($cName !== $prevCellar) {
      if ($inCellarBlock) {
        echo "</div></div>"; // Close previous cellar block
      }
      $prevCellar = $cName;
      $inCellarBlock = true;
      $cTotal = $cellarTotals[$cName] ?? (int)$storedBtls['btls'];
      $cCap = $cellarCapacities[$cName] ?? 0;

      $badgeText = (int)$cTotal . " btls";
      if ($showUsagePct && $cCap > 0) {
        $cPct = round(($cTotal / $cCap) * 100);
        $badgeText = (int)$cTotal . " / " . (int)$cCap . " (" . $cPct . "%)";
      }

      echo "<div class='admin-storage-cellar'>";
      echo "<div class='admin-storage-cellar-title'>";
      echo "<span>" . htmlspecialchars($cName, ENT_QUOTES, 'UTF-8') . "</span>";
      echo "<span class='admin-storage-badge'>" . htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') . "</span>";
      echo "</div>";
      echo "<div class='admin-storage-bins'>";
    }
    $binName = htmlspecialchars($storedBtls['bin_name'], ENT_QUOTES, 'UTF-8');
    $btlCount = (int)$storedBtls['btls'];
    $maxCap = ($storedBtls['max_capacity'] !== null) ? (int)$storedBtls['max_capacity'] : null;

    echo "<div class='admin-storage-bin-row'>";
    echo "<a href='browseBottles.php?sort=location' title='View bottles in " . $binName . "'>Bin " . $binName . "</a>";

    if ($maxCap !== null && $maxCap > 0) {
      if ($showUsagePct) {
        $pct = round(($btlCount / $maxCap) * 100);
        $barClass = 'storage-bar-normal';
        $pctColor = '#2e7d32';
        if ($pct >= 100) {
          $barClass = 'storage-bar-full';
          $pctColor = '#d32f2f';
        } elseif ($pct >= 80) {
          $barClass = 'storage-bar-warning';
          $pctColor = '#e65100';
        }
        echo "<div style='display:flex; align-items:center; gap:6px;'>";
        echo "<span style='color:#666; font-size:0.82rem;'>" . $btlCount . " / " . $maxCap . "</span>";
        echo "<div style='width:36px; height:6px; background:#eee; border-radius:3px; overflow:hidden;' title='" . $pct . "% utilised'>";
        echo "<div class='" . $barClass . "' style='width:" . min(100, $pct) . "%; height:100%;'></div>";
        echo "</div>";
        echo "<span style='font-size:0.75rem; font-weight:600; color:" . $pctColor . "; min-width:28px; text-align:right;'>" . $pct . "%</span>";
        echo "</div>";
      } else {
        echo "<span style='color:#666; font-size:0.82rem;'>" . $btlCount . " / " . $maxCap . " btls</span>";
      }
    } else {
      echo "<span style='color:#666; font-size:0.82rem;'>" . $btlCount . " btl" . ($btlCount === 1 ? '' : 's') . "</span>";
    }

    echo "</div>";
  }

  if ($inCellarBlock) {
    echo "</div></div>";
  }

  // Display unassigned bottles section if any exist
  if ($unassignedCount > 0) {
    echo "<div class='admin-storage-cellar'>";
    echo "<div class='admin-storage-cellar-title' style='color:#856404;'>";
    echo "<span>&#9888;&#65039; Unassigned Storage</span>";
    echo "<span class='admin-storage-badge' style='background:#fff3cd; color:#856404;'>" . $unassignedCount . " btls</span>";
    echo "</div>";
    echo "<div class='admin-storage-bins'>";
    echo "<div class='admin-storage-bin-row'>";
    echo "<a href='browseBottles.php?sort=location' style='color:#856404; font-style:italic;' title='View unassigned bottles'>Awaiting bin assignment</a>";
    echo "<span style='color:#856404; font-size:0.82rem;'>" . $unassignedCount . " btl" . ($unassignedCount === 1 ? '' : 's') . "</span>";
    echo "</div>";
    echo "</div></div>";
  }

  $totalInCellar = 0;
  try {
    $totRes = $mysqli->query("SELECT count(bottle_id) as num FROM bottles WHERE status='in cellar'");
    if ($totRes && $totalBtls = $totRes->fetch_assoc()) {
      $totalInCellar = (int)$totalBtls['num'];
      $totRes->free_result();
    }
  } catch (Throwable $e) {}

  $totalCappedCapacity = array_sum($cellarCapacities);
  $totalSummary = $totalInCellar . " bottles";
  if ($showUsagePct && $totalCappedCapacity > 0) {
    $totPct = round(($totalInCellar / $totalCappedCapacity) * 100);
    $totalSummary = $totalInCellar . " / " . $totalCappedCapacity . " (" . $totPct . "%)";
  }

  echo "<div style='margin-top:14px; padding-top:10px; border-top:1px solid #eee; display:flex; justify-content:space-between; font-weight:bold; font-size:0.9rem;'>";
  echo "<span>Total in Cellar</span>";
  echo "<span>" . htmlspecialchars($totalSummary, ENT_QUOTES, 'UTF-8') . "</span>";
  echo "</div>";
}
?>
