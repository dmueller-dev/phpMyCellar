<?php
  session_start();
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
?>

<?php
  $producerID=$_GET['id'];
  getProducer($producerID);
?>

<?php
  $page_title = "Dominik Mueller - " . $producer["producer"] . "";
  
  require_once 'includes/header.php';
?>

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

<?php require_once 'includes/footer.php'; ?>

<?php function getProducer($producerID)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");

  // Perform query
  $stmt = $mysqli->prepare("select * from producers
                              left join regions on producers.region_id=regions.region_id
                              where producer_id=?");
  $stmt->bind_param("i", $producerID);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result)
  {
    $GLOBALS['producer'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $stmt->close();
  $mysqli -> close();
} ?>

<?php function latestNotes($id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  // Perform query
  $stmt = $mysqli->prepare("select * from tnotes
                                left join users on tnotes.user_id=users.user_id
                                left join wines on tnotes.wine_id=wines.wine_id
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join regions on wines_master.region_id=regions.region_id
                                left join countries on regions.country=countries.country
                                left join subregions on wines_master.subregion_id=subregions.subregion_id
                                left join appellations on wines_master.appellation_id=appellations.appellation_id
                              where status='published' and producers.producer_id=?
                              order by tasting_date desc, note_id desc");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
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
  $stmt->close();
  $result -> free_result();
  $mysqli -> close();
} ?>
