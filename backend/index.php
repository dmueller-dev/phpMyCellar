<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Dominik Mueller - Wine database backend</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="https://dmueller.com/styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">
</head>

<body>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <li><a class="active" href="index.php" title="Backend Home">Index</a></li>
      <li><a href="browseWines.php" title="Show all wines">Wines</a></li>
      <li><a href="browseBottles.php" title="Show all bottles">Bottles</a></li>
      <li><a href="winemenu.php" title="Show wine menu">Wine menu</a></li>
      <li class="right"><a href="https://dmueller.com" title="Frontend">Go to website</a></li>
    </ul>
  </nav>
</header>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h3>Tasting notes</h3>
        <ul>
	  <li><a href="addTastingNote.php" title="Add a new tasting note">New tasting note</a>
            | <a href="blindTasting.php" title="Add a new tasting note">New <strong>blind</strong> tasting note</a></li>
          <li><a href="editTastingNote.php" title="Edit tasting notes">Edit tasting note</a></li>
        </ul>
        <hr>
        <h3>Cellar management</h3>
        <ul>
          <li><a href="addBottle.php" title="Add a new bottle of wine">Add new bottle of wine</a></li>
          <li><a href="editBottle.php" title="Edit bottles">Edit bottle</a></li>
        </ul>
        <hr>
        <h3>Wines</h3>
        <ul>
          <li><a href="addWineMaster.php" title="Add a new wine master">Add new <strong>master</strong></a>
             | <a href="addWine.php" title="Add a new wine">Add new wine</a></li>
          <li><a href="editWineMaster.php" title="Edit wine masters">Edit wine master</a></li>
          <li><a href="editWine.php" title="Edit wines">Edit wines</a></li>
        </ul>
        <hr>
        <h3>Producers</h3>
        <ul>
          <li><a href="addProducer.php" title="Add a new producer">Add new producer</a></li>
          <li><a href="editProducer.php" title="Edit producers">Edit producer</a></li>
        </ul>
        <h3>Countries</h3>
        <ul>
          <li><a href="addCountry.php" title="Add a new country">Add new country</a></li>
          <li><a href="editCountry.php" title="Edit countries">Edit country</a></li>
        </ul>
        <h3>Regions</h3>
        <ul>
          <li><a href="addRegion.php" title="Add a new region">Add new region</a></li>
          <li><a href="editRegion.php" title="Edit regions">Edit region</a></li>
        </ul>
        <h3>Subregions</h3>
        <ul>
          <li><a href="addSubregion.php" title="Add a new subregion">Add new subregion</a></li>
          <li><a href="editSubregion.php" title="Edit subregions">Edit subregion</a></li>
        </ul>
        <h3>Appellations</h3>
        <ul>
          <li><a href="addAppellation.php" title="Add a new appellation">Add new appellation</a></li>
          <li><a href="editAppellation.php" title="Edit appellations">Edit appellation</a></li>
        </ul>
        <h3>Vineyards</h3>
        <ul>
          <li><a href="addVineyard.php" title="Add a new vineyard">Add new vineyard</a></li>
          <li><a href="editVineyard.php" title="Edit vineyards">Edit vineyard</a></li>
        </ul>
        <hr>
        <h3>User management</h3>
        <ul>
          <li><a href="addUser.php" title="Add a new user">Add new user</a></li>
        </ul>
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

<div class="footer">
  <footer>
    <p style="float:right;margin-top:0;"><a href="/impressum.php" title="Impressum / Imprint">Impressum / Imprint</a></p>
    <address>
      Contact details:<br>
      Dominik Mueller<br>
      Muehlstr. 24<br>
      76532 Baden-Baden<br>
      GERMANY<br><br>
      E-Mail: <a href="mailto:dm@dmueller.com" title="Contact me by email">dm@dmueller.com</a>
    </address>
    <p align="center"><small>This website uses <strong>no</strong> cookies. Have fun!</small></p>
  </footer>
</div>

</body>

</html>

<?php function getNumberOfBottlesInStorage()
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  // Perform query
  $result = $mysqli -> query("select
                                storageBins.bin_name,
                                count(bottles.bottle_id) as btls
                              from bottles
                              left join storageBins on bottles.storage_location=storageBins.bin_id
                              where status='in cellar'
                              group by storageBins.bin_name
                              order by storageBins.bin_name");
  echo "<table>";
  while ($storedBtls = $result->fetch_assoc()) {
    echo "<tr><td><b>".$storedBtls["bin_name"]."</b></td><td>".$storedBtls["btls"]."</td></tr>";
  }
  $result -> free_result();
  $result = $mysqli -> query("select count(bottle_id) as num from bottles where status='in cellar'");
  $totalBtls = $result->fetch_assoc();
  echo "<tr><td><b>Total number</b></td><td>".$totalBtls["num"]."</td></tr>";
  echo "</table>";
  $result -> free_result();
  $mysqli -> close();
} ?>