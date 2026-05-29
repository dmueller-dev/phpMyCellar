<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Check if user is not logged in
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
  }

  // Check if a tasting note ID parameter is provided; if not, redirect to tnotes.php
  if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    header("Location: /tnotes.php");
    exit;
  }

  // Get tasting note ID
  $noteID = $_GET['id'];

  // Include the database configuration file
  global $mysqli, $conn;

  // Fetch the tasting note and ensure it exists before proceeding
  getNote($noteID);
  if (empty($tasting_note)) {
    header("Location: /tnotes.php");
    exit;
  }

  // Generate the token for the form
  $csrf_token = generateCSRFToken();

  // Fetch user details
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
        $post = $mysqli->prepare("INSERT INTO x_comments_tnotes (comment_id, note_id) VALUES (?, ?)");
        $post->bind_param('ii', $last_id, $noteID);
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

  // Page title and header
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
	<h3>Tasting note</h3>
        <?php
          if($tasting_note["img"]!=null) {
            echo "<img class='".$tasting_note["img_class"]."' src='/img/".$tasting_note["img"]."' alt='".$wine_name."'>";
          }
          echo $tasting_note["tasting_note"];
          if($tasting_note["drinkwindow_min"]!=null && $tasting_note["drinkwindow_max"]!=null) {
            echo " <p>Drink from " . $tasting_note["drinkwindow_min"] . " through " . $tasting_note["drinkwindow_max"] . ".</p>";
          } elseif ($tasting_note["drinkwindow_max"]!=null) {
            echo " <p>Drink through " . $tasting_note["drinkwindow_max"] . ".</p>";
          }
          echo "<p><i>Tasted by ".$tasting_note["displayname"]." on ".date_format(date_create($tasting_note["tasting_date"]),"l, j F Y").".</i></p>";
        ?>
      </section>
    </div>
    <?php renderBlogReferences($conn, $noteID, 'tnote', 'Referenced in these stories:'); ?>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Ratings</h3>
      <p>
        <table>
          <tr><td>Flawed?</td><td style="width:5px;"></td><td><?php echo $tasting_note["flawed_yn"];?></td></tr>
          <tr>
            <td><?php echo htmlspecialchars($tasting_note["initials"] ?? 'DM', ENT_QUOTES, 'UTF-8'); ?>:</td>
            <td style="width:5px;"></td>
            <td>
              <?php
                if($tasting_note["dmpts"]==null) {
                  echo "not rated";
                } else {
                  echo "<div class='tooltip'>".$tasting_note["dmpts"]."<span class='tooltiptext'>".$tasting_note["dmpts_desc"]."</span></div> / 20 (&quot;".$tasting_note["dmpts_class"]."&quot;)";
                }
              ?>
            </td>
          </tr>
          <tr>
            <td>Stars:</td>
            <td style="width:5px;"></td>
            <td>
              <?php
                if($tasting_note["starpts"]==null) {
                  echo "not rated";
                } else {
                  echo "<div class='tooltip'>".$tasting_note["starpts"]."<span class='tooltiptext'>".$tasting_note["starpts_desc"]."</span></div> / 5";
                }
              ?>
            </td>
          </tr>
        </table>
      </p>
      <?php if ($tasting_note["blind"]=="blind") { echo "<p><em>Tasted blind.</em></p>"; } ?>
      <p><a href="https://dmueller.com/blogpost.php?id=26" title="How I rate wines">Find out more about how I rate wines.</a></p>
    </div>
    <div class="card">
    <h3>Wine details</h3>
    <p>
      <table>
        <tr>
          <td>Vintage:</td>
          <td>
            <?php
              if ($tasting_note["vintage_desc"]==null) {
                echo $tasting_note["vintage"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["vintage"]."<span class='tooltiptext'>".$tasting_note["vintage_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Colour:</td><td><?php echo $tasting_note["colour"];?></td></tr>
        <tr><td>Style:</td><td><?php echo $tasting_note["style"];?></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Assemblage:</td><td><?php echo $tasting_note["cuvee_yn"];?></td></tr>
        <tr>
          <td>Grape variety:</td>
          <td>
            <?php
              if ($tasting_note["grape_desc"]==null) {
                echo $tasting_note["grape"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["grape"]."<span class='tooltiptext'>".$tasting_note["grape_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr><td colspan="2"><font style="font-size:12px;">For <i>assemblages</i>, the main grape variety is shown.</font></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Producer:</td><td><?php echo "<a href='/producers.php?id=".$tasting_note["producer_id"]."'>".$tasting_note["producer"]."</a>";?></td></tr>
        <tr><td>Country:</td><td><?php echo $tasting_note["country"];?></td></tr>
        <tr>
          <td>Region:</td>
          <td>
            <?php
              if ($tasting_note["region_desc"]==null) {
                echo $tasting_note["region"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["region"]."<span class='tooltiptext'>".$tasting_note["region_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr><td>Subregion:</td><td><?php if ($tasting_note["subregion"]==null){echo "n/a";}else{echo $tasting_note["subregion"];}?></td></tr>
        <tr>
          <td>Appellation:</td>
          <td>
            <?php
              if ($tasting_note["appellation"]==null) {
                echo "n/a";
              } elseif ($tasting_note["appellation_desc"]==null) {
                echo $tasting_note["appellation"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["appellation"]."<span class='tooltiptext'>".$tasting_note["appellation_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr>
          <td>Vineyard:</td>
          <td>
            <?php
              if ($tasting_note["vineyard"]==null) {
                echo "n/a";
              } elseif ($tasting_note["vineyard_desc"]==null) {
                echo $tasting_note["vineyard"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["vineyard"]."<span class='tooltiptext'>".$tasting_note["vineyard_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td colspan="2"><?php echo "<a href='/wine.php?id=".$tasting_note["wine_id"]."'>More details on this wine.</a>";?></td></tr>
        <tr><td colspan="2"><?php echo ($tasting_note["ct_id"]!==null) ? "<a href='https://www.cellartracker.com/wine.asp?iWine=".$tasting_note["ct_id"]."' target='_blanc'>View this wine on CellarTracker.</a>" : "";?></td></tr>
      </table>
    </p>
    </div>
    <?php moreNotes($noteID, $tasting_note["wine_id"], $wine_name); ?>
    <?php if ($tasting_note["vintage"]!="NV") { otherVintages($tasting_note["wine_id"], $tasting_note["master_id"]); } ?>
    <div class="card">
      <details><summary><h3 style="display:inline;margin:0;">Post a comment</h3></summary>
      <?php
        if ($error!="") {
          echo "<div style='color:red;'>$error</div>";
        } elseif ($success!="") {
          echo "<div style='color:green;'>$success</div>";
        }
      ?>
      <form method="post" autocomplete="off" accept-charset="UTF-8" style="margin-bottom:10px;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <label for="username">Your name:</label>
        <br><input type="text" id="username" value="<?= htmlspecialchars($displayname ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <br><br>
        <label for="comment">Your comment:</label>
        <br><textarea name="comment" id="comment" rows="15" cols="35" maxlength="2000" placeholder="..."></textarea>
        <br><br>
        <button type="submit">Post comment</button>
      </form></details>
    </div>
    <?php renderComments($conn, $noteID, 'tnote'); ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php function getNote($noteID)
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
                                   left join variety on wines_master.grape=variety.grape
				   left join dmpts on tnotes.dmpts=dmpts.pts
				   left join wsetpts on tnotes.wsetpts=wsetpts.pts
				   left join starpts on tnotes.starpts=starpts.pts
				   left join (select vintage as vint, region_id as rvid, vintage_desc from x_vintage_region) xvr on wines.vintage=xvr.vint and wines_master.region_id=xvr.rvid
                                 where note_id=?");
  $stmt->bind_param("i", $noteID);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result)
  {
    $GLOBALS['tasting_note'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $stmt->close();
} ?>

<?php function moreNotes($id, $wine_id, $wine_name)
{
  global $mysqli, $conn;
    // Perform query
  $stmt = $mysqli->prepare("select tnotes.*, users.initials from tnotes
                              left join users on tnotes.user_id=users.user_id
                              where tnotes.status='published' and tnotes.note_id<>? and tnotes.wine_id=?
                              order by tnotes.tasting_date desc");
  $stmt->bind_param("ii", $id, $wine_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if (mysqli_num_rows($result)!=0) {
    echo "<div class='card'><h3>More tasting notes on this wine:</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($moreNotes = $result->fetch_assoc()) {
      if ($moreNotes['flawed_yn']=="yes") {
        $dmpts="flawed";
      } elseif ($moreNotes['dmpts']!=null) {
        $initials = !empty($moreNotes['initials']) ? $moreNotes['initials'] : 'DM';
        $dmpts=$initials.$moreNotes["dmpts"];
      } else {
        $dmpts="NR";
      }
      echo "<li>".date_format(date_create($moreNotes["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$moreNotes["note_id"]."'>".$wine_name."</a> (".$dmpts.")</li>";
    }
    echo "</ul></p></div>";
  }
  $stmt->close();
  $result -> free_result();
} ?>

<?php function otherVintages($wine_id, $master_id)
{
  global $mysqli, $conn;
    // Perform query
  $stmt = $mysqli->prepare("select wines.*, wines_master.*, tnotes.*, producers.*, vineyards.*, users.initials from wines
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join tnotes on wines.wine_id=tnotes.wine_id
                                left join users on tnotes.user_id=users.user_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                              where tnotes.status='published' and wines.wine_id<>? and wines.master_id=?
                              order by tnotes.tasting_date desc");
  $stmt->bind_param("ii", $wine_id, $master_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if (mysqli_num_rows($result)!=0) {
    echo "<div class='card'><h3>Tasting notes on other vintages of this wine:</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($otherVintages = $result->fetch_assoc()) {
      // Get wine name
      if ($otherVintages["nameconvention"]=="vintage_name") {
        $otherWine=$otherVintages["vintage"]." ".$otherVintages["name"];
      } elseif ($otherVintages["nameconvention"]=="vintage_producer") {
        $otherWine=$otherVintages["vintage"]." ".$otherVintages["producer"];
      } elseif ($otherVintages["nameconvention"]=="vintage_producer_grape_name") {
        $otherWine=$otherVintages["vintage"]." ".$otherVintages["producer"]." ".$otherVintages["grape"]." ".$otherVintages["name"];
      } elseif ($otherVintages["nameconvention"]=="vintage_producer_vineyard_grape_name") {
        $otherWine=$otherVintages["vintage"]." ".$otherVintages["producer"]." ".$otherVintages["vineyard"]." ".$otherVintages["grape"]." ".$otherVintages["name"];
      } elseif ($otherVintages["nameconvention"]=="vintage_producer_vineyard_name") {
        $otherWine=$otherVintages["vintage"]." ".$otherVintages["producer"]." ".$otherVintages["vineyard"]." ".$otherVintages["name"];
      // ...else vintage_producer_name as default:
      } else {
        $otherWine=$otherVintages["vintage"]." ".$otherVintages["producer"]." ".$otherVintages["name"];
      }
      if ($otherVintages['flawed_yn']=="yes") {
        $dmpts="flawed";
      } elseif ($otherVintages['dmpts']!=null) {
        $initials = !empty($otherVintages['initials']) ? $otherVintages['initials'] : 'DM';
        $dmpts=$initials.$otherVintages["dmpts"];
      } else {
        $dmpts="NR";
      }
      if ($otherVintages["tasting_date"]!=null) {
        echo "<li>".date_format(date_create($otherVintages["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$otherVintages["note_id"]."'>".$otherWine."</a> (".$dmpts.")</li>";
      }
    }
    echo "</ul></p></div>";
  }
  $stmt->close();
  $result -> free_result();
} ?>
