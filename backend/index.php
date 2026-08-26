<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';
?>

<?php
  $page_title = 'Dominik Mueller - Wine database backend';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <?php if (hasPrivilege($conn, 'add_tasting_note') || hasPrivilege($conn, 'edit_tasting_note') || hasPrivilege($conn, 'edit_all_tasting_notes')): ?>
          <h3>Tasting notes</h3>
          <ul>
            <?php if (hasPrivilege($conn, 'add_tasting_note')): ?>
              <li><a href="addTastingNote.php" title="Add a new tasting note">New tasting note</a>
                | <a href="blindTasting.php" title="Add a new tasting note">New <strong>blind</strong> tasting note</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_tasting_note') || hasPrivilege($conn, 'edit_all_tasting_notes')): ?>
              <li><a href="editTastingNote.php" title="Edit tasting notes">Edit tasting note</a></li>
            <?php endif; ?>
          </ul>
          <hr>
        <?php endif; ?>

        <?php if (hasPrivilege($conn, 'browse_bottles') || hasPrivilege($conn, 'add_bottle') || hasPrivilege($conn, 'edit_bottle') || hasPrivilege($conn, 'add_order') || hasPrivilege($conn, 'manage_orders')): ?>
          <h3>Cellar management</h3>
          <ul>
            <?php if (hasPrivilege($conn, 'browse_bottles')): ?>
              <li><a href="browseBottles.php" title="Browse all bottles">Browse all bottles</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_bottle')): ?>
              <li><a href="addBottle.php" title="Add a new bottle of wine">Add new bottle of wine</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_bottle')): ?>
              <li><a href="editBottle.php" title="Edit bottles">Edit bottle</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_order')): ?>
              <li><a href="addOrder.php" title="Create a new wine order">Create new order</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_orders')): ?>
              <li><a href="manageOrders.php" title="Manage open orders and accept delivery">Manage open orders</a></li>
            <?php endif; ?>
          </ul>
          <hr>
        <?php endif; ?>

        <?php if (hasPrivilege($conn, 'browse_wines') || hasPrivilege($conn, 'add_wine') || hasPrivilege($conn, 'edit_wine') || hasPrivilege($conn, 'add_wine_master') || hasPrivilege($conn, 'edit_wine_master')): ?>
          <h3>Wines</h3>
          <ul>
            <?php if (hasPrivilege($conn, 'browse_wines')): ?>
              <li><a href="browseWines.php" title="Browse all wines">Browse all wines</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'add_wine_master') || hasPrivilege($conn, 'add_wine')): ?>
              <li>
                <?php if (hasPrivilege($conn, 'add_wine_master')): ?><a href="addWineMaster.php" title="Add a new wine master">Add new <strong>master</strong></a><?php endif; ?>
                <?php if (hasPrivilege($conn, 'add_wine_master') && hasPrivilege($conn, 'add_wine')): ?> | <?php endif; ?>
                <?php if (hasPrivilege($conn, 'add_wine')): ?><a href="addWine.php" title="Add a new wine">Add new wine</a><?php endif; ?>
              </li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_wine_master')): ?>
              <li><a href="editWineMaster.php" title="Edit wine masters">Edit wine master</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_wine')): ?>
              <li><a href="editWine.php" title="Edit wines">Edit wines</a></li>
            <?php endif; ?>
          </ul>
          <hr>
        <?php endif; ?>

        <?php if (hasPrivilege($conn, 'manage_producers') || hasPrivilege($conn, 'manage_countries') || hasPrivilege($conn, 'manage_regions') || hasPrivilege($conn, 'manage_subregions') || hasPrivilege($conn, 'manage_appellations') || hasPrivilege($conn, 'manage_vineyards')): ?>
          <?php if (hasPrivilege($conn, 'manage_producers')): ?>
            <h3>Producers</h3>
            <ul>
              <li><a href="addProducer.php" title="Add a new producer">Add new producer</a></li>
              <li><a href="editProducer.php" title="Edit producers">Edit producer</a></li>
            </ul>
          <?php endif; ?>
          <?php if (hasPrivilege($conn, 'manage_countries')): ?>
            <h3>Countries</h3>
            <ul>
              <li><a href="addCountry.php" title="Add a new country">Add new country</a></li>
              <li><a href="editCountry.php" title="Edit countries">Edit country</a></li>
            </ul>
          <?php endif; ?>
          <?php if (hasPrivilege($conn, 'manage_regions')): ?>
            <h3>Regions</h3>
            <ul>
              <li><a href="addRegion.php" title="Add a new region">Add new region</a></li>
              <li><a href="editRegion.php" title="Edit regions">Edit region</a></li>
            </ul>
          <?php endif; ?>
          <?php if (hasPrivilege($conn, 'manage_subregions')): ?>
            <h3>Subregions</h3>
            <ul>
              <li><a href="addSubregion.php" title="Add a new subregion">Add new subregion</a></li>
              <li><a href="editSubregion.php" title="Edit subregions">Edit subregion</a></li>
            </ul>
          <?php endif; ?>
          <?php if (hasPrivilege($conn, 'manage_appellations')): ?>
            <h3>Appellations</h3>
            <ul>
              <li><a href="addAppellation.php" title="Add a new appellation">Add new appellation</a></li>
              <li><a href="editAppellation.php" title="Edit appellations">Edit appellation</a></li>
            </ul>
          <?php endif; ?>
          <?php if (hasPrivilege($conn, 'manage_vineyards')): ?>
            <h3>Vineyards</h3>
            <ul>
              <li><a href="addVineyard.php" title="Add a new vineyard">Add new vineyard</a></li>
              <li><a href="editVineyard.php" title="Edit vineyards">Edit vineyard</a></li>
            </ul>
          <?php endif; ?>
          <hr>
        <?php endif; ?>

        <?php if (hasPrivilege($conn, 'add_blogpost') || hasPrivilege($conn, 'edit_blogpost') || hasPrivilege($conn, 'edit_all_blogposts')): ?>
          <h3>Blog stories</h3>
          <ul>
            <?php if (hasPrivilege($conn, 'add_blogpost')): ?>
              <li><a href="addBlogpost.php" title="Add new story">Write new story</a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'edit_blogpost') || hasPrivilege($conn, 'edit_all_blogposts')): ?>
              <li><a href="editBlogpost.php" title="Edit stories">Edit story</a></li>
            <?php endif; ?>
          </ul>
          <hr>
        <?php endif; ?>

        <?php if (hasPrivilege($conn, 'manage_privileges') || hasPrivilege($conn, 'manage_users')): ?>
          <h3>User & Role Management</h3>
          <ul>
            <?php if (hasPrivilege($conn, 'manage_privileges')): ?>
              <li><a href="managePrivileges.php" title="User and role privileges management"><strong>User & role privileges</strong></a></li>
            <?php endif; ?>
            <?php if (hasPrivilege($conn, 'manage_users')): ?>
              <li><a href="addUser.php" title="Add a new user">Add new user</a></li>
              <li><a href="editUser.php" title="Edit a user and reset password">Edit user</a></li>
            <?php endif; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Bottles in cellar</h3>
      <p><?php getNumberOfBottlesInStorage(); ?></p>
      <p><a href="browseBottles.php">View all bottles...</a></p>
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

<?php function getNumberOfBottlesInStorage()
{
  global $mysqli, $conn;
    // Perform query
  $result = $mysqli -> query(
    "select
      src.cellar_name,
      src.bin_name,
      sum(src.btls) over (partition by src.cellar_name) as btls_cellar,
      src.btls
    from
    (
      select
        cellars.cellar_name,
        storageBins.bin_name,
        count(bottles.bottle_id) as btls
      from bottles
        left join storageBins on bottles.storage_location=storageBins.bin_id
        left join cellars on storageBins.cellar_id=cellars.cellar_id
      where status='in cellar'
      group by storageBins.bin_name
    ) src
    order by cellar_name, bin_name"
  );
  echo "<table style='border-collapse:collapse;'>";
  $prevCellar="";
  while ($storedBtls = $result->fetch_assoc()) {
    if ($storedBtls["cellar_name"] != $prevCellar) {
      if ($prevCellar != "") { echo "<tr style='height:15px;'><td colspan='2'></td></tr>"; }
      $prevCellar = $storedBtls["cellar_name"];
      echo "<tr style='border-bottom:1px solid;'><td><b>".$storedBtls["cellar_name"]."</b></td><td style='text-indent:5px;'>".$storedBtls["btls_cellar"]."</td></tr>";
    }
    echo "<tr style='text-indent:15px;'><td><b>".$storedBtls["bin_name"]."</b></td><td>".$storedBtls["btls"]."</td></tr>";
  }
  $result -> free_result();
  $result = $mysqli -> query("select count(bottle_id) as num from bottles where status='in cellar'");
  $totalBtls = $result->fetch_assoc();
  echo "<tr style='height:15px;'><td colspan='2'></td></tr>";
  echo "<tr><td><b>Total number</b></td><td style='text-indent:5px;'>".$totalBtls["num"]."</td></tr>";
  echo "</table>";
  $result -> free_result();
  } ?>
