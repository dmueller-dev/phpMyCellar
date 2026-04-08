<!DOCTYPE html>
<html lang="en-GB">

<head>
  <title>Dominik Mueller - Privacy policy</title>
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

<div class="card">
  <section>
    <h3>Privacy policy</h3>
    <p>
      This website uses a session cookie only when a user logs in. The session cookie is essential for authentication and
      ensures that you remain logged in during your session. The cookie does not store any personal information; it only
      stores a numerical user ID, not even the username. The cookie is automatically deleted when you close your browser.
      <br><br><strong>No</strong> cookies are used for users who are not registered, logged-in members.
      <br><br>I do <strong>not</strong> use any third-party cookies or share your information with third parties.
      <br><br>For registered members, only minimal personal information is required and stored on the server. This is
      the full name and email address of the member to allow transparent communications between members.
      <br><br>All personal information is securely stored in a database hosted on the web server. Sensitive information is
      encrypted. Secure connections, such as HTTPS and FTPS, are used at all times.
      <br><br>You have the right to contact me regarding any questions about your data or this privacy policy.
      <br><br>For more information, please contact me using the contact details below.
    </p>
  </section>
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
