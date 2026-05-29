<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Generate the token for the comments form
  $csrf_token = generateCSRFToken();

  // Check if a wine ID parameter is provided; if not, redirect to wines.php
  if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    header("Location: /wines.php");
    exit;
  }

  // Get wine ID
  $wineID = $_GET['id'];

  // Include the database configuration file
  global $mysqli, $conn;

  // Fetch the wine and ensure it exists before proceeding
  getWine($wineID);
  if (empty($wine)) {
    header("Location: /wines.php");
    exit;
  }

  // Check if user is logged in
  if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("SELECT username, displayname FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $displayname);
    if (!$stmt->fetch()) {
      $stmt->close();
      die("<h2>User not found.</h2>");
    }
    $stmt->close();
  }

  // Initialize error and success messages
  $error = "";
  $success = "";

  // Process form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate the token before processing the login
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      $error = "Security check failed. Please refresh the page and try again.";
    } else {
      $comment = $_POST['comment'] ?? '';
      $mysqli->autocommit(FALSE);
      $post = $mysqli->prepare("INSERT INTO comments (user_id, content) VALUES (?, ?)");
      $post->bind_param('is', $user_id, $comment);
      if ($post->execute()) {
        $last_id = $mysqli->insert_id;
        $post->close();
        $post = $mysqli->prepare("INSERT INTO x_comments_wines (comment_id, wine_id) VALUES (?, ?)");
        $post->bind_param('ii', $last_id, $wineID);
        if ($post->execute()) {
          $success = "Thank you! Comment posted successfully.";
          $mysqli->commit();
        } else {
          $error = "Failed to post comment. Please try again.";
          $mysqli->rollback();
        }
      } else {
        $error = "Failed to post comment. Please try again.";
        $mysqli->rollback();
      }
      $post->close();
    }
  }

  // Vintage NV?
  if ($wine["vintage"]==null) { $wine["vintage"]="NV"; }
  // Get wine name
  if ($wine["nameconvention"]=="vintage_name") {
    $wine_name=$wine["vintage"]." ".$wine["name"];
  } elseif ($wine["nameconvention"]=="vintage_producer") {
    $wine_name=$wine["vintage"]." ".$wine["producer"];
  } elseif ($wine["nameconvention"]=="vintage_producer_grape_name") {
    $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["grape"]." ".$wine["name"];
  } elseif ($wine["nameconvention"]=="vintage_producer_vineyard_grape_name") {
    $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["vineyard"]." ".$wine["grape"]." ".$wine["name"];
  } elseif ($wine["nameconvention"]=="vintage_producer_vineyard_name") {
    $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["vineyard"]." ".$wine["name"];
  // ...else vintage_producer_name as default:
  } else {
    $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["name"];
  }

  // Page header
  $page_title = "Dominik Mueller - " . $wine_name . "";

  require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3><?php echo $wine_name;?></h3>
    </div>
    <div class="card">
      <section>
        <h3>Description</h3>
        <p>
          <?php if ($wine["wine_desc"]==null) {
            echo "Sorry, no description on this wine yet.";
          } else {
            echo $wine["wine_desc"];
          } ?>
        </p>
      </section>
    </div>
    <div class="card">
      <h3>Tasting notes on this wine</h3>
      <p><ul style="list-style-type:none;padding:0;margin:0;">
        <?php
          if (!isset($_SESSION['user_id'])) {
            echo "<p>Please log in to see my tasting notes.</p>";
          } elseif (isset($_SESSION['user_id'])) {
            latestNotes($wine["wine_id"]);
          }
        ?>
      </ul></p>
    </div>
    <?php renderBlogReferences($conn, $wineID, 'wine', 'Appears in these blog stories:'); ?>
    <?php
      if ($wine["region_desc"]!=null) {
        echo "<div class='card'><section><h3>About ".$wine["region"]."</h3>".$wine["region_desc"]."</section></div>";
      }
    ?>
    <?php
      if ($wine["vintage_desc"]!=null) {
        echo "<div class='card'><section><h3>The ".$wine["vintage"]." vintage in ".$wine["region"]."</h3>".$wine["vintage_desc"]."</section></div>";
      }
    ?>
    <?php
      if ($wine["grape_desc"]!=null) {
        echo "<div class='card'><section><h3>The ".$wine["grape"]." grape variety</h3>".$wine["grape_desc"]."</section></div>";
      }
    ?>
  </div>
  <div class="column side">
    <div class="card">
    <h3>Wine details</h3>
    <p>
      <table>
        <tr>
          <td>Vintage:</td>
          <td>
            <?php
              if ($wine["vintage_desc"]==null) {
                echo $wine["vintage"];
              } else {
                echo "<div class='tooltip'>".$wine["vintage"]."<span class='tooltiptext'>".$wine["vintage_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Colour:</td><td><?php echo $wine["colour"];?></td></tr>
        <tr><td>Style:</td><td><?php echo $wine["style"];?></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Assemblage:</td><td><?php echo $wine["cuvee_yn"];?></td></tr>
        <tr>
          <td>Grape variety:</td>
          <td>
            <?php
              if ($wine["grape_desc"]==null) {
                echo $wine["grape"];
              } else {
                echo "<div class='tooltip'>".$wine["grape"]."<span class='tooltiptext'>".$wine["grape_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr><td colspan="2"><font style="font-size:12px;">For <i>assemblages</i>, the main grape variety is shown.</font></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Producer:</td><td><?php echo "<a href='/producers.php?id=".$wine["producer_id"]."'>".$wine["producer"]."</a>";?></td></tr>
        <tr><td>Country:</td><td><?php echo $wine["country"];?></td></tr>
        <tr><td>Region:</td><td><?php echo $wine["region"];?></td></tr>
        <tr><td>Subregion:</td><td><?php if ($wine["subregion"]==null){echo "n/a";}else{echo $wine["subregion"];}?></td></tr>
        <tr>
          <td>Appellation:</td>
          <td>
            <?php
              if ($wine["appellation"]==null) {
                echo "n/a";
              } elseif ($wine["appellation_desc"]==null) {
                echo $wine["appellation"];
              } else {
                echo "<div class='tooltip'>".$wine["appellation"]."<span class='tooltiptext'>".$wine["appellation_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr>
          <td>Vineyard:</td>
          <td>
            <?php
              if ($wine["vineyard"]==null) {
                echo "n/a";
              } elseif ($wine["vineyard_desc"]==null) {
                echo $wine["vineyard"];
              } else {
                echo "<div class='tooltip'>".$wine["vineyard"]."<span class='tooltiptext'>".$wine["vineyard_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td colspan="2"><?php echo ($wine["ct_id"]!==null) ? "<a href='https://www.cellartracker.com/wine.asp?iWine=".$wine["ct_id"]."' target='_blanc'>View this wine on CellarTracker.</a>" : "";?></td></tr>
      </table>
    </p>
    </div>
    <?php
      if ($wine["producer_desc"]!=null) {
        echo "<div class='card'><aside><h3>About ".$wine["producer"]."</h3>".$wine["producer_desc"]."</aside></div>";
      }
      if ($wine["appellation_desc"]!=null) {
        echo "<div class='card'><aside><h3>The appellation: ".$wine["appellation"]."</h3>".$wine["appellation_desc"]."</aside></div>";
      }
      if ($wine["vintage"]!="NV") {
        otherVintages($wineID, $wine["master_id"]);
      }
    ?>
    <?php
      // Check if user is logged in
      if (isset($_SESSION['user_id'])) {
        echo "<div class='card'><details><summary><h3 style='display:inline;margin:0;'>Post a comment</h3></summary>";
        if ($error!="") {
          echo "<div style='color:red;'>$error</div>";
        } elseif ($success!="") {
          echo "<div style='color:green;'>$success</div>";
        }
        echo "<form method='post' autocomplete='off' accept-charset='UTF-8' style='margin-bottom:10px;'>";
        echo "<input type='hidden' name='csrf_token' value='" . $csrf_token . "'>";
        echo "<label for='username'>Your name:</label>";
        echo "<br><input type='text' id='username' value='".htmlspecialchars($displayname, ENT_QUOTES, 'UTF-8')."' disabled readonly>";
        echo "<br><br>";
        echo "<label for='comment'>Your comment:</label>";
        echo "<br><textarea name='comment' id='comment' rows='15' cols='35' maxlength='2000' placeholder='...'></textarea>";
        echo "<br><br>";
        echo "<button type='submit'>Post comment</button>";
        echo "</form></details>";
        echo "</div>";
        renderComments($conn, $wineID, 'wine');
      }
    ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php function getWine($wineID)
{
  global $mysqli, $conn;

  // Perform query
  $stmt = $mysqli->prepare("select * from wines
                            left join wines_master on wines.master_id=wines_master.master_id
                            left join producers on wines_master.producer_id=producers.producer_id
                            left join regions on wines_master.region_id=regions.region_id
                            left join subregions on wines_master.subregion_id=subregions.subregion_id
                            left join appellations on wines_master.appellation_id=appellations.appellation_id
                            left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                            left join variety on wines_master.grape=variety.grape
                            left join (select vintage as vint, region_id as rvid, vintage_desc from x_vintage_region) xvr on wines.vintage=xvr.vint and wines_master.region_id=xvr.rvid
                          where wine_id=?");
  $stmt->bind_param("i", $wineID);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result)
  {
    $GLOBALS['wine'] = $result -> fetch_assoc();
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
                              where status='published' and wines.wine_id=?
                              order by tasting_date desc, note_id desc");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($tasting_note = $result->fetch_assoc()) {
    // DM points?
    if ($tasting_note['flawed_yn']=="yes") { $dmpts="flawed"; } elseif ($tasting_note['dmpts']!=null) { $initials = !empty($tasting_note['initials']) ? $tasting_note['initials'] : 'DM'; $dmpts = $initials . $tasting_note["dmpts"]; } else { $dmpts="NR"; }
    // Output
    echo "<li>Tasted by ".$tasting_note["displayname"]." on ".date_format(date_create($tasting_note["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$tasting_note['note_id']."'>".$dmpts."</a></li>";
  }
  if (mysqli_num_rows($result)==0) { echo "<li>No tasting notes for this wine, yet.</li>"; }
  $stmt->close();
  $result -> free_result();
}
?>

<?php function otherVintages($id, $master_id)
{
  global $mysqli, $conn;
    // Perform query
  $stmt = $mysqli->prepare("select * from wines
                              left join wines_master on wines.master_id=wines_master.master_id
                            where wines.wine_id<>? and wines.master_id=?
                            order by wines.vintage desc");
  $stmt->bind_param("ii", $id, $master_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if (mysqli_num_rows($result)!=0) {
    $count=1;
    echo "<div class='card'><h3>Other vintages of this wine:</h3><p>";
    while ($otherWines = $result->fetch_assoc()) {
      if ($count>1) {
        echo ", ";
      }
      echo "<a href='/wine.php?id=".$otherWines["wine_id"]."'>".$otherWines["vintage"]."</a>";
      $count=$count+1;
    }
    echo "</p></div>";
  }
  $stmt->close();
  $result -> free_result();
} ?>
