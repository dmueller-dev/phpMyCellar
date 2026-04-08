<?php
  session_start();

  // Get tasting note ID
  $noteID=$_GET['id'];

  // Include the database configuration file
  require 'db_connect.php';
  $mysqli->query("SET NAMES utf8");

  // Check if user is not logged in
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
  } else {
    // --- FETCH USER DETAILS ---
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
    $mysqli->close();
  }
?>

<!DOCTYPE html>
<html lang="en-GB">

<?php
  getNote($noteID);
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
?>

<head>
  <title>Dominik Mueller - <?php echo $wine_name;?></title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <meta name="description" content="On this website, I share my wine cellar with a community of fellow fine wine enthusiasts."
  <meta name="keywords" content="Dominik Mueller,wine database,fine wine,wine collection,wine cellar">
  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">
</head>

<body>

<header>
  <a href="https://dmueller.com" title="Dominik Mueller - Fine wine tasting notes">
    <img src="/img/logo_web.webp" class="logo" alt="Dominik Mueller - Fine wine tasting notes">
  </a>
</header>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <li><a href="/index.php" title="Back to homepage">Home</a></li>
      <li><a href="/wines.php" title="Wine database">Wine database</a></li>
      <li><a class="active" href="/tnotes.php" title="Fine wine tasting notes">Tasting notes</a></li>
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
    <?php getBlog($noteID); ?>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Ratings</h3>
      <p>
        <table>
          <tr><td>Flawed?</td><td style="width:5px;"></td><td><?php echo $tasting_note["flawed_yn"];?></td></tr>
          <tr>
            <td>DM:</td>
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
        <label for="username">Your name:</label>
        <br><input type="text" id="username" value="<?= htmlspecialchars($displayname, ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <br><br>
        <label for="comment">Your comment:</label>
        <br><textarea name="comment" id="comment" rows="15" cols="35" maxlength="2000" placeholder="..."></textarea>
        <br><br>
        <button type="submit">Post comment</button>
      </form></details>
    </div>
    <?php getComments($noteID) ?>
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

<?php function getNote($noteID)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");

  // Perform query
  if ($result = $mysqli -> query("select * from tnotes
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
                                 where note_id=".$noteID))
  {
    $GLOBALS['tasting_note'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $mysqli -> close();
} ?>

<?php function getBlog($id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  // Perform query
  $result = $mysqli -> query("select * from x_blog_tnotes
                                left join blogposts on x_blog_tnotes.blog_id=blogposts.blog_id
                              where x_blog_tnotes.note_id=".$id."
                              order by blogposts.pub_date desc");
  if (mysqli_num_rows($result)!=0) {
    echo "<div class='card'><h3>Referenced in these stories:</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($blog = $result->fetch_assoc()) {
      echo "<li>".date_format(date_create($blog["pub_date"]),"d M Y").": <a href='/blogpost.php?id=".$blog['blog_id']."'>".$blog["title"]."</a></li>";
    }
    echo "</ul></p></div>";
  }
  $result -> free_result();
  $mysqli -> close();
} ?>

<?php function moreNotes($id, $wine_id, $wine_name)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  // Perform query
  $result = $mysqli -> query("select * from tnotes 
                              where tnotes.status='published' and tnotes.note_id<>".$id." and tnotes.wine_id=".$wine_id."
                              order by tnotes.tasting_date desc");
  if (mysqli_num_rows($result)!=0) {
    echo "<div class='card'><h3>More tasting notes on this wine:</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($moreNotes = $result->fetch_assoc()) {
      if ($moreNotes['flawed_yn']=="yes") {
        $dmpts="flawed";
      } elseif ($moreNotes['dmpts']!=null) {
        $dmpts="DM".$moreNotes["dmpts"];
      } else {
        $dmpts="NR";
      }
      echo "<li>".date_format(date_create($moreNotes["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$moreNotes["note_id"]."'>".$wine_name."</a> (".$dmpts.")</li>";
    }
    echo "</ul></p></div>";
  }
  $result -> free_result();
  $mysqli -> close();
} ?>

<?php function otherVintages($wine_id, $master_id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  // Perform query
  $result = $mysqli -> query("select * from wines
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join tnotes on wines.wine_id=tnotes.wine_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                              where tnotes.status='published' and wines.wine_id<>".$wine_id." and wines.master_id=".$master_id."
                              order by tnotes.tasting_date desc");
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
        $dmpts="DM".$otherVintages["dmpts"];
      } else {
        $dmpts="NR";
      }
      if ($otherVintages["tasting_date"]!=null) {
        echo "<li>".date_format(date_create($otherVintages["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$otherVintages["note_id"]."'>".$otherWine."</a> (".$dmpts.")</li>";
      }
    }
    echo "</ul></p></div>";
  }
  $result -> free_result();
  $mysqli -> close();
} ?>

<?php
  function getComments($id)
  {
    require "db_connect.php";
    $mysqli->query("SET NAMES utf8");
    // Perform query
    $result = $mysqli -> query("select * from x_comments_tnotes
                                  left join comments on x_comments_tnotes.comment_id=comments.comment_id
                                  left join users on comments.user_id=users.user_id
                                where x_comments_tnotes.note_id=".$id."
                                order by comments.pub_time desc");
    if (mysqli_num_rows($result)!=0) {
      while ($comments = $result->fetch_assoc()) {
        echo "<div class='card'><p style='font-size:small;'><b>".$comments["displayname"]."</b>, ".date_format(date_create($comments["pub_time"]),"l, j F Y H:i:s").":</p><hr><p style='font-size:small;'>".$comments["content"]."</p></div>";
      }
    }
    $result -> free_result();
    $mysqli -> close();
  }
?>
