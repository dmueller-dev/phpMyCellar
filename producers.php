<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="en-GB">

<?php
  $producerID=$_GET['id'];
  getProducer($producerID);
?>

<head>
  <title>Dominik Mueller - <?php echo $producer["producer"];?></title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <meta name="description" content="On this website, I share my wine cellar with a community of fellow fine wine enthusiasts."
  <meta name="keywords" content="Dominik Mueller,wine database,wine tasting,tasting notes,fine wine,wine collection,wine cellar">
  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">
</head>

<body>

<header class="titles">
  <h1 class="site-title">Dominik Mueller</h1>
  <h2 class="sub-title">Wine is my hobby. Fine wine tasting notes and experiences.</h2>
</header>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <li><a href="/index.php" title="Back to homepage">Home</a></li>
      <li><a class="active" href="/wines.php" title="Wine database">Wine database</a></li>
      <li><a href="/tnotes.php" title="Fine wine tasting notes">Tasting notes</a></li>
      <li><a href="/blog.php" title="My wine blog">Stories</a></li>
      <?php
        if (!isset($_SESSION['user_id'])) {
          echo "<li class='right'><a href='/login.php' title='Login'>Login</a></li>";
        } elseif (isset($_SESSION['user_id'])) {
          echo "<li><a style='font-style:italic;' href='/winemenu.php' title='Carte des vins'>Carte des vins</a></li>";
          echo "<li class='right'><a href='/logout.php' title='Logout'>Logout</a></li>";
          echo "<li class='right'><a href='/accountSettings.php' title='My account'>My account</a></li>";
        }
      ?>
    </ul>
  </nav>
</header>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3><?php echo $producer["producer"];?></h3>
    </div>
    <div class="card">
      <section>
	<h3>Description</h3>
        <p>Based in <?php echo $producer["region"].", ".$producer["country"]."."; ?></p>
        <?php
          if($producer["producer_desc"]!=null) {
            echo $producer["producer_desc"];
          }
        ?>
      </section>
    </div>
    <div class="card">
      <h3>Wines I've tasted</h3>
      <p>
        <ul style="list-style-type:none;padding:0;margin:0;">
          <?php
            if (!isset($_SESSION['user_id'])) {
              echo "<p>Please log in to see my tasting notes.</p>";
            } elseif (isset($_SESSION['user_id'])) {
              latestNotes($producer["producer_id"]);
            }
          ?>
        </ul>
      </p>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Address</h3>
      <address>
        <p>
          <?php
            if($producer["address"]!=null) {
              echo $producer["address"];
            } else {
              echo "Not available.";
            } 
          ?>
        </p>
      </address>
    </div>
  </div>
</div>

<div class="footer">
  <footer>
    <p style="float:right;margin-top:0;">
      <a href="/privacy.php" title="Privacy policy">Privacy policy</a>
      <br><a href="/impressum.php" title="Impressum / Imprint">Impressum / Imprint</a>
    </p>
    <address>
      Contact details:<br>
      Dominik Mueller<br>
      Muehlstr. 24<br>
      76532 Baden-Baden<br>
      GERMANY<br><br>
      E-Mail: <a href="mailto:dm@dmueller.com" title="Contact me by email">dm@dmueller.com</a>
    </address>
    <p align="center">
      <small><u>Cookie notice:</u><br>This website uses session cookies for members logging in only. Aside from that,<br>the
      website uses <strong>no</strong> cookies. Refer to the <a href="/privacy.php" alt="Privacy policy">privacy policy</a>
      for details. Have fun!</small>
    </p>
  </footer>
</div>

</body>

</html>

<?php function getProducer($producerID)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");

  // Perform query
  if ($result = $mysqli -> query("select * from producers
                                    left join regions on producers.region_id=regions.region_id
                                  where producer_id=".$producerID))
  {
    $GLOBALS['producer'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $mysqli -> close();
} ?>

<?php function latestNotes($id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  // Perform query
  $result = $mysqli -> query("select * from tnotes
                                left join users on tnotes.user_id=users.user_id
                                left join wines on tnotes.wine_id=wines.wine_id
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join regions on wines_master.region_id=regions.region_id
                                left join countries on regions.country=countries.country
                                left join subregions on wines_master.subregion_id=subregions.subregion_id
                                left join appellations on wines_master.appellation_id=appellations.appellation_id
                              where status='published' and producers.producer_id=".$id."
                              order by tasting_date desc, note_id desc");
  while ($tasting_note = $result->fetch_assoc()) {
    // Vintage NV?
    if ($tasting_note["vintage"]==null) { $tasting_note["vintage"]="NV"; }
    // Get wine name
    if ($tasting_note["nameconvention"]=="vintage_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["name"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer_grape_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["grape"]." ".$tasting_note["name"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer_vineyard_grape_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["vineyard"]." ".$tasting_note["grape"]." ".$tasting_note["name"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer_vineyard_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["vineyard"]." ".$tasting_note["name"];
    // ...else vintage_producer_name as default:
    } else {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["name"];
    }
    // DM points?
    if ($tasting_note['flawed_yn']=="yes") {
      $dmpts="flawed";
    } elseif ($tasting_note['dmpts']!=null) {
      $dmpts="DM".$tasting_note["dmpts"];
    } else {
      $dmpts="NR";
    }
    // Output
    echo "<li>".date_format(date_create($tasting_note["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$tasting_note['note_id']."'>".$wine_name."</a> (".$dmpts.")</li>";
  }
  if (mysqli_num_rows($result)==0) { echo "<li>I haven't tasted any of this producer's wines, yet.</li>"; }
  $result -> free_result();
  $mysqli -> close();
} ?>