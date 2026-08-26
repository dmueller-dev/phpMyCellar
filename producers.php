<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Check if a producer ID parameter is provided; if not, redirect to wines.php?sort=producer
  if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    header("Location: /wines.php?sort=producer");
    exit;
  }

  // Get producer ID
  $producerID = $_GET['id'];

  // Fetch the producer and ensure it exists before proceeding
  getProducer($producerID);
  if (empty($producer)) {
    header("Location: /wines.php?sort=producer");
    exit;
  }

  // Associated producer data for richer SEO keywords & schema
  $associated_data = getProducerAssociatedData($conn, $producerID);

  // Page title and SEO metadata
  $page_title = "Dominik Mueller - " . $producer["producer"];
  $meta_keywords = generateProducerKeywords($producer, $associated_data);
  $meta_desc = generateProducerDescription($producer);
  $canonical_url = getAbsoluteUrl('/producers.php?id=' . $producerID);
  $og_type = 'profile';
  $json_ld = generateProducerJsonLd($producer, $canonical_url);

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
            if (hasPrivilege($conn, 'view_tnotes')) {
              latestNotes($producer["producer_id"]);
            } else {
              echo "<p>Please <a href='/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']) . "'>log in</a> to see my tasting notes.</p>";
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
  global $mysqli, $conn;
  
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
  } ?>

<?php function latestNotes($id)
{
  global $mysqli, $conn;
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
      $initials = !empty($tasting_note['initials']) ? $tasting_note['initials'] : 'DM';
      $dmpts=$initials.$tasting_note["dmpts"];
    } else {
      $dmpts="NR";
    }
    // Output
    echo "<li>".date_format(date_create($tasting_note["tasting_date"]),"d M Y").": <a href='/tnotes.php?id=".$tasting_note['note_id']."'>".$wine_name."</a> (".$dmpts.")</li>";
  }
  if (mysqli_num_rows($result)==0) { echo "<li>I haven't tasted any of this producer's wines, yet.</li>"; }
  $stmt->close();
  $result -> free_result();
  } ?>
