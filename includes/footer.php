<?php
  // Prevent direct access to this file
  if (!defined('INCLUDED_VIA_APP')) {
    die('Direct access not permitted');
  }

  $owner_name = function_exists('getOwnerName') ? getOwnerName() : 'Cellar Master';
  $owner_email = function_exists('getOwnerEmail') ? getOwnerEmail() : 'cellar@example.com';
  $owner_address = function_exists('getSiteSetting') ? getSiteSetting('owner_address', '') : '';
?>

<div class="footer">
  <footer>
    <p style="float:right;margin-top:0;">
      <a href="/privacy.php" title="Privacy policy">Privacy policy</a>
      <br><a href="/impressum.php" title="Impressum / Imprint">Impressum / Imprint</a>
    </p>
    <address>
      Contact details:<br>
      <?php echo htmlspecialchars($owner_name, ENT_QUOTES, 'UTF-8'); ?><br>
      <?php if (!empty($owner_address)): ?>
        <div class="footer-address"><?php echo $owner_address; ?></div>
      <?php endif; ?>
      E-Mail: <a href="mailto:<?php echo htmlspecialchars($owner_email, ENT_QUOTES, 'UTF-8'); ?>" title="Contact cellar owner by email"><?php echo htmlspecialchars($owner_email, ENT_QUOTES, 'UTF-8'); ?></a>
    </address>
    <p style="text-align:center;">
      <small><u>Cookie notice:</u><br>This website uses session cookies for members logging in only. Aside from that,<br>the
      website uses <strong>no</strong> cookies. Refer to the <a href="/privacy.php" title="Privacy policy">privacy policy</a>
      for details. Have fun!</small>
    </p>
  </footer>
</div>

</body>
</html>
