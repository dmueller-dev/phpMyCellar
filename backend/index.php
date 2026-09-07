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
  ];
  try {
    foreach (['producers', 'appellations', 'vineyards', 'regions', 'subregions', 'countries'] as $tbl) {
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
    <p class="admin-hero-sub">Cellar administration, cuv&eacute;e catalog &amp; inventory control center</p>
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
    <div class="kpi-label">Wine Catalog</div>
    <div class="kpi-sub">
      <span><?php echo number_format($kpi_masters); ?> masters / cuv&eacute;es</span>
      <span>Catalog &rarr;</span>
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
    <a href="addTastingNote.php" class="btn-quick-action" title="Write a standard tasting note">
      <span>&#9997;&#65039;</span> Write Note
    </a>
    <a href="addTastingNote.php?mode=blind" class="btn-quick-action btn-secondary" title="Conduct a blind tasting and take notes">
      <span>&#128065;&#65039;</span> Blind Tasting
    </a>
  <?php endif; ?>

  <?php if (hasPrivilege($conn, 'add_bottle')): ?>
    <a href="addBottle.php" class="btn-quick-action" title="Add a new physical bottle to cellar inventory">
      <span>&#127870;</span> Add Bottle
    </a>
  <?php endif; ?>

  <?php if (hasPrivilege($conn, 'add_wine')): ?>
    <a href="addWine.php" class="btn-quick-action" title="Add a new harvest vintage to the catalog">
      <span>&#127815;</span> Add Wine Vintage
    </a>
  <?php endif; ?>

  <?php if (hasPrivilege($conn, 'add_order')): ?>
    <a href="addOrder.php" class="btn-quick-action btn-secondary" title="Create a new wine purchase order">
      <span>&#128230;</span> New Order
    </a>
  <?php endif; ?>
</div>

<div class="row">
  <div class="column main">
    <div class="admin-domains-grid">

      <!-- Cellar & Inventory Management -->
      <?php if (hasPrivilege($conn, 'browse_bottles') || hasPrivilege($conn, 'add_bottle') || hasPrivilege($conn, 'edit_bottle') || hasPrivilege($conn, 'add_order') || hasPrivilege($conn, 'manage_orders')): ?>
        <div class="admin-domain-card">
          <h3><span>&#127870;</span> Cellar &amp; Inventory</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'browse_bottles')): ?>
              <li>
                <a href="browseBottles.php" title="Browse physical bottles in cellar">Browse all bottles</a>
                <a href="browseBottles.php" class="link-action">Browse</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_bottle')): ?>
              <li>
                <a href="addBottle.php" title="Add a new bottle of wine to storage">Add bottle to inventory</a>
                <a href="addBottle.php" class="link-action">+ Add</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_bottle')): ?>
              <li>
                <a href="editBottle.php" title="Edit bottles and drinking windows">Edit bottle details</a>
                <a href="editBottle.php" class="link-action">Edit</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_order')): ?>
              <li>
                <a href="addOrder.php" title="Create a new wine order">Create purchase order</a>
                <a href="addOrder.php" class="link-action">+ Order</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_orders')): ?>
              <li>
                <a href="manageOrders.php" title="Manage pending orders and accept delivery">Manage open orders</a>
                <a href="manageOrders.php" class="link-action">Orders</a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Wines & Masters Catalog -->
      <?php if (hasPrivilege($conn, 'browse_wines') || hasPrivilege($conn, 'add_wine') || hasPrivilege($conn, 'edit_wine') || hasPrivilege($conn, 'add_wine_master') || hasPrivilege($conn, 'edit_wine_master')): ?>
        <div class="admin-domain-card">
          <h3><span>&#127991;&#65039;</span> Wine Catalog &amp; Masters</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'browse_wines')): ?>
              <li>
                <a href="browseWines.php" title="Browse wine database">Browse wine catalog</a>
                <a href="browseWines.php" class="link-action">Browse</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_wine_master')): ?>
              <li>
                <a href="addWineMaster.php" title="Add a master cuv&eacute;e profile">Add wine master profile</a>
                <a href="addWineMaster.php" class="link-action">+ Master</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_wine')): ?>
              <li>
                <a href="addWine.php" title="Add a specific harvest vintage">Add wine harvest vintage</a>
                <a href="addWine.php" class="link-action">+ Vintage</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_wine_master')): ?>
              <li>
                <a href="editWineMaster.php" title="Edit master profiles">Edit wine master</a>
                <a href="editWineMaster.php" class="link-action">Edit</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_wine')): ?>
              <li>
                <a href="editWine.php" title="Edit harvest vintages and tasting parameters">Edit wine vintages</a>
                <a href="editWine.php" class="link-action">Edit</a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Tasting Notes & Editorial -->
      <?php if (hasPrivilege($conn, 'add_tasting_note') || hasPrivilege($conn, 'edit_tasting_note') || hasPrivilege($conn, 'edit_all_tasting_notes') || hasPrivilege($conn, 'add_blogpost') || hasPrivilege($conn, 'edit_blogpost') || hasPrivilege($conn, 'edit_all_blogposts')): ?>
        <div class="admin-domain-card">
          <h3><span>&#128221;</span> Tasting Notes &amp; Editorial</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'add_tasting_note')): ?>
              <li>
                <a href="addTastingNote.php" title="Write a standard tasting note">Write tasting note</a>
                <a href="addTastingNote.php" class="link-action">+ Note</a>
              </li>
              <li>
                <a href="addTastingNote.php?mode=blind" title="Write a blind tasting note">Write blind tasting note</a>
                <a href="addTastingNote.php?mode=blind" class="link-action">+ Blind</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_tasting_note') || hasPrivilege($conn, 'edit_all_tasting_notes')): ?>
              <li>
                <a href="editTastingNote.php" title="Edit tasting notes">Edit tasting notes</a>
                <a href="editTastingNote.php" class="link-action">Edit</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_blogpost')): ?>
              <li>
                <a href="addBlogpost.php" title="Write a new cellar story">Write new story</a>
                <a href="addBlogpost.php" class="link-action">+ Story</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_blogpost') || hasPrivilege($conn, 'edit_all_blogposts')): ?>
              <li>
                <a href="editBlogpost.php" title="Edit published stories">Edit stories</a>
                <a href="editBlogpost.php" class="link-action">Edit</a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Terroir & Geography Matrix -->
      <?php if (hasPrivilege($conn, 'manage_producers') || hasPrivilege($conn, 'manage_countries') || hasPrivilege($conn, 'manage_regions') || hasPrivilege($conn, 'manage_subregions') || hasPrivilege($conn, 'manage_appellations') || hasPrivilege($conn, 'manage_vineyards')): ?>
        <div class="admin-domain-card">
          <h3><span>&#127757;</span> Terroir &amp; Geography</h3>
          <table class="admin-terroir-table">
            <tbody>
              <?php if (hasPrivilege($conn, 'manage_producers')): ?>
                <tr>
                  <td class="admin-terroir-name">Producers</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['producers']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="addProducer.php" class="terroir-btn terroir-btn-add" title="Add producer">+ Add</a>
                    <a href="editProducer.php" class="terroir-btn terroir-btn-edit" title="Edit producer">Edit</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_appellations')): ?>
                <tr>
                  <td class="admin-terroir-name">Appellations</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['appellations']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="addAppellation.php" class="terroir-btn terroir-btn-add" title="Add appellation">+ Add</a>
                    <a href="editAppellation.php" class="terroir-btn terroir-btn-edit" title="Edit appellation">Edit</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_vineyards')): ?>
                <tr>
                  <td class="admin-terroir-name">Vineyards</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['vineyards']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="addVineyard.php" class="terroir-btn terroir-btn-add" title="Add vineyard">+ Add</a>
                    <a href="editVineyard.php" class="terroir-btn terroir-btn-edit" title="Edit vineyard">Edit</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_regions')): ?>
                <tr>
                  <td class="admin-terroir-name">Regions</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['regions']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="addRegion.php" class="terroir-btn terroir-btn-add" title="Add region">+ Add</a>
                    <a href="editRegion.php" class="terroir-btn terroir-btn-edit" title="Edit region">Edit</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_subregions')): ?>
                <tr>
                  <td class="admin-terroir-name">Subregions</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['subregions']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="addSubregion.php" class="terroir-btn terroir-btn-add" title="Add subregion">+ Add</a>
                    <a href="editSubregion.php" class="terroir-btn terroir-btn-edit" title="Edit subregion">Edit</a>
                  </td>
                </tr>
              <?php endif; ?>
              <?php if (hasPrivilege($conn, 'manage_countries')): ?>
                <tr>
                  <td class="admin-terroir-name">Countries</td>
                  <td class="admin-terroir-count"><?php echo number_format($terroir_counts['countries']); ?> recorded</td>
                  <td class="admin-terroir-actions">
                    <a href="addCountry.php" class="terroir-btn terroir-btn-add" title="Add country">+ Add</a>
                    <a href="editCountry.php" class="terroir-btn terroir-btn-edit" title="Edit country">Edit</a>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Site Settings & User Administration -->
      <?php if (hasPrivilege($conn, 'manage_privileges') || hasPrivilege($conn, 'manage_users')): ?>
        <div class="admin-domain-card">
          <h3><span>&#9881;&#65039;</span> System &amp; Settings</h3>
          <ul class="admin-domain-links">
            <?php if (hasPrivilege($conn, 'manage_privileges')): ?>
              <li>
                <a href="settings.php" title="Branding, accent colors, scoring system">Site settings &amp; branding</a>
                <a href="settings.php" class="link-action">Settings</a>
              </li>
              <li>
                <a href="manageStaticPages.php" title="Custom static text, sidebar notices, about content">Static content pages</a>
                <a href="manageStaticPages.php" class="link-action">Pages</a>
              </li>
              <li>
                <a href="managePrivileges.php" title="Configure 31 RBAC privileges and roles">User &amp; role privileges</a>
                <a href="managePrivileges.php" class="link-action">Roles</a>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_users')): ?>
              <li>
                <a href="addUser.php" title="Create a new user account">Add new user</a>
                <a href="addUser.php" class="link-action">+ User</a>
              </li>
              <li>
                <a href="editUser.php" title="Edit accounts and reset passwords">Edit user accounts</a>
                <a href="editUser.php" class="link-action">Edit</a>
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
        <a href="browseBottles.php" style="font-size:0.8rem; font-weight:normal; text-decoration:none;" title="View all physical bottles">Browse &rarr;</a>
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
        <li><span>Wine Catalog</span> <strong><?php echo number_format($kpi_wines); ?> vintages</strong></li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Render the cellar storage breakdown widget using portable SQL grouping
 */
function renderCellarStorageWidget()
{
  global $mysqli;

  $rows = [];
  $cellarTotals = [];

  try {
    $result = $mysqli->query(
      "SELECT
        COALESCE(cellars.cellar_name, 'Unassigned Cellar') AS cellar_name,
        COALESCE(storageBins.bin_name, 'Unassigned Bin') AS bin_name,
        COUNT(bottles.bottle_id) AS btls
      FROM bottles
        LEFT JOIN storageBins ON bottles.storage_location = storageBins.bin_id
        LEFT JOIN cellars ON storageBins.cellar_id = cellars.cellar_id
      WHERE bottles.status = 'in cellar'
      GROUP BY cellars.cellar_name, storageBins.bin_name
      ORDER BY cellars.cellar_name ASC, storageBins.bin_name ASC"
    );

    if ($result) {
      while ($r = $result->fetch_assoc()) {
        $cName = $r['cellar_name'];
        $rows[] = $r;
        if (!isset($cellarTotals[$cName])) {
          $cellarTotals[$cName] = 0;
        }
        $cellarTotals[$cName] += (int)$r['btls'];
      }
      $result->free_result();
    }
  } catch (Throwable $e) {
    // Graceful fallback on database error
  }

  if (empty($rows)) {
    echo "<p style='color:#777; font-size:0.88rem; margin:0;'>No bottles currently placed in storage bins.</p>";
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
      echo "<div class='admin-storage-cellar'>";
      echo "<div class='admin-storage-cellar-title'>";
      echo "<span>" . htmlspecialchars($cName, ENT_QUOTES, 'UTF-8') . "</span>";
      echo "<span class='admin-storage-badge'>" . (int)$cTotal . " btls</span>";
      echo "</div>";
      echo "<div class='admin-storage-bins'>";
    }
    $binName = htmlspecialchars($storedBtls['bin_name'], ENT_QUOTES, 'UTF-8');
    $btlCount = (int)$storedBtls['btls'];
    echo "<div class='admin-storage-bin-row'>";
    echo "<a href='browseBottles.php?sort=location' title='View bottles in " . $binName . "'>Bin " . $binName . "</a>";
    echo "<span style='color:#666; font-size:0.82rem;'>" . $btlCount . " btl" . ($btlCount === 1 ? '' : 's') . "</span>";
    echo "</div>";
  }

  if ($inCellarBlock) {
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

  echo "<div style='margin-top:14px; padding-top:10px; border-top:1px solid #eee; display:flex; justify-content:space-between; font-weight:bold; font-size:0.9rem;'>";
  echo "<span>Total in Cellar</span>";
  echo "<span>" . $totalInCellar . " bottles</span>";
  echo "</div>";
}
?>
