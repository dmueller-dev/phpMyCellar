<?php
  session_start();

  // Get tasting note ID
  $wineID=$_GET['id'];

  // Include the database configuration file
  require 'db_connect.php';
  $mysqli->query("SET NAMES utf8");

  // Check if user is logged in
  if (isset($_SESSION['user_id'])) {
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
    $mysqli->close();
  }
?>

<!DOCTYPE html>
<html lang="en-GB">

<?php
  getWine($wineID);
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
?>

<head>
  <title>Dominik Mueller - <?php echo $wine_name;?></title>
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
    <?php getBlog($wineID); ?>
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
        echo "<label for='username'>Your name:</label>";
        echo "<br><input type='text' id='username' value='".htmlspecialchars($displayname, ENT_QUOTES, 'UTF-8')."' disabled readonly>";
        echo "<br><br>";
        echo "<label for='comment'>Your comment:</label>";
        echo "<br><textarea name='comment' id='comment' rows='15' cols='35' maxlength='2000' placeholder='...'></textarea>";
        echo "<br><br>";
        echo "<button type='submit'>Post comment</button>";
        echo "</form></details>";
        echo "</div>";
        getComments($wineID);
      }
    ?>
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

<?php function getWine($wineID)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");

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
                              where status='published' and wines.wine_id=?
                              order by tasting_date desc, note_id desc");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($tasting_note = $result->fetch_assoc()) {
    // DM points?
    if ($tasting_note['flawed_yn']=="yes") { $dmpts="flawed"; } elseif ($tasting_note['dmpts']!=null) { $dmpts="DM".$tasting_note["dmpts"]; } else { $dmpts="NR"; }
    // Output
    echo "<li>Tasted by ".$tasting_note["displayname"]." on ".date_format(date_create($tasting_note["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$tasting_note['note_id']."'>".$dmpts."</a></li>";
  }
  if (mysqli_num_rows($result)==0) { echo "<li>No tasting notes for this wine, yet.</li>"; }
  $stmt->close();
  $result -> free_result();
  $mysqli -> close();
} ?>

<?php function getBlog($id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  // Perform query
  $stmt = $mysqli->prepare("select * from x_blog_wines
                              left join blogposts on x_blog_wines.blog_id=blogposts.blog_id
                            where x_blog_wines.wine_id=?
                            order by blogposts.pub_date desc");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  if (mysqli_num_rows($result)!=0) {
    echo "<div class='card'><h3>Appears in these blog stories:</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($blog = $result->fetch_assoc()) {
      echo "<li>".date_format(date_create($blog["pub_date"]),"d M Y").": <a href='/blogpost.php?id=".$blog['blog_id']."'>".$blog["title"]."</a></li>";
    }
    echo "</ul></p></div>";
  }
  $stmt->close();
  $result -> free_result();
  $mysqli -> close();
} ?>

<?php function otherVintages($id, $master_id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
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
  $mysqli -> close();
} ?>

<?php
  function getComments($id)
  {
    require "db_connect.php";
    $mysqli->query("SET NAMES utf8");
    // Perform query
    $stmt = $mysqli->prepare("select * from x_comments_wines
                                left join comments on x_comments_wines.comment_id=comments.comment_id
                                left join users on comments.user_id=users.user_id
                              where x_comments_wines.wine_id=?
                              order by comments.pub_time desc");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (mysqli_num_rows($result)!=0) {
      while ($comments = $result->fetch_assoc()) {
        echo "<div class='card'><p style='font-size:small;'><b>".$comments["displayname"]."</b>, ".date_format(date_create($comments["pub_time"]),"l, j F Y H:i:s").":</p><hr><p style='font-size:small;'>".$comments["content"]."</p></div>";
      }
    }
    $stmt->close();
    $result -> free_result();
    $mysqli -> close();
  }
?>
