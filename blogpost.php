<?php
  session_start();

  // Get blog ID
  $blogID=$_GET['id'];

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
      $post = $mysqli->prepare("INSERT INTO x_comments_blogposts (comment_id, blog_id) VALUES (?, ?)");
      $post->bind_param('ii', $last_id, $blogID);
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
  getPost($blogID);
?>

<head>
  <title>Dominik Mueller - <?php echo $blogpost["title"];?></title>
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
      <li><a href="/wines.php" title="Wine database">Wine database</a></li>
      <li><a href="/tnotes.php" title="Fine wine tasting notes">Tasting notes</a></li>
      <li><a class="active" href="/blog.php" title="My wine blog">Stories</a></li>
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
      <h3 style="margin-bottom:0;"><?php echo $blogpost["title"];?></h3>
      <p style="margin-top:0;">Posted on <?php echo date_format(date_create($blogpost["pub_date"]),"l, j F Y");?></p>
    </div>
    <div class="card">
      <section>
	<?php echo $blogpost["content"];?>
      </section>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <h3>Wines from this post</h3>
      <ul style="list-style-type:disc;">
        <?php wines($blogID); ?>
      </ul>
    </div>
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
        <br><input type="text" id="username" value="<?= htmlspecialchars($displayname ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
        <br><br>
        <label for="comment">Your comment:</label>
        <br><textarea name="comment" id="comment" rows="15" cols="35" maxlength="2000" placeholder="..."></textarea>
        <br><br>
        <button type="submit">Post comment</button>
      </form></details>
    </div>
    <?php getComments($blogID) ?>
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

<?php function getPost($id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");

  // Perform query
  $stmt = $mysqli->prepare("select * from blogposts where blog_id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result)
  {
    $GLOBALS['blogpost'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $stmt->close();
  $mysqli -> close();
} ?>

<?php function wines($id)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  $prevWine="";
  // Perform query
  $stmt = $mysqli->prepare("select * from x_blog_wines
                                left join wines on x_blog_wines.wine_id=wines.wine_id
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join (select note_id,wine_id as w_id,tasting_date,flawed_yn,dmpts,status from tnotes) tnotes on x_blog_wines.wine_id=tnotes.w_id
                              where x_blog_wines.blog_id=?
                              order by producers.producer asc, wines_master.name asc, wines.vintage asc, tnotes.tasting_date desc");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  while ($wine = $result->fetch_assoc()) {
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
    // Output
    if ($wine_name!=$prevWine) {
      echo "<li><a href='/wine.php?id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
      $prevWine=$wine_name;
    }
    if ($wine['flawed_yn']=="yes") { $dmpts="flawed"; } elseif ($wine['dmpts']!=null) { $dmpts="DM".$wine["dmpts"]; } else { $dmpts="NR"; }
    if ($wine["note_id"]!=null and $wine["status"]=="published") { echo "<ul><li>Tasted on ".date_format(date_create($wine["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$wine["note_id"]."'>".$dmpts."</a></li></ul>"; }
  }
  if (mysqli_num_rows($result)==0) { echo "<li>No wines were featured in this story.</li>"; }
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
    $stmt = $mysqli->prepare("select * from x_comments_blogposts
                                  left join comments on x_comments_blogposts.comment_id=comments.comment_id
                                  left join users on comments.user_id=users.user_id
                                where x_comments_blogposts.blog_id=?
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
