<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Dominik Mueller - Browse all stories</title>
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
      <h3>Browse all blog posts and stories</h3>
    </div>
    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php getNotes(1000); ?>
      </ul>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <p>
        In this section, I don't usually write about individual wines, but about larger tastings of several wines (e.g. horizontals,
        verticals, etc.) or personal experiences on wine trips and at restaurants.
      </p>
      <p>
        If a report refers to a specific wine, this is usually linked so that you can quickly find more information about the wine in
        question on my site.
      </p>
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

<?php function getNotes($num)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  $prevYear="";
  // Perform query
  $result = $mysqli -> query("select * from blogposts order by pub_date desc, blog_id desc limit 0,".$num);
  // Output
  while ($blogs = $result->fetch_assoc()) {
    if (date_format(date_create($blogs["pub_date"]),"Y")!=$prevYear) {
      echo "<br><li><b>".date_format(date_create($blogs["pub_date"]),"Y")."</b></li>";
    }
    echo "<li>".date_format(date_create($blogs["pub_date"]),"d M Y").": <a href='/blogpost.php?id=".$blogs['blog_id']."'>".$blogs["title"]."</a></li>";
    $prevYear=date_format(date_create($blogs["pub_date"]),"Y");
  }
  $result -> free_result();
  $mysqli -> close();
} ?>
