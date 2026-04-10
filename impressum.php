<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);

  $page_title = 'Dominik Mueller - Impressum / Imprint';
  
  require_once 'includes/header.php';
?>

<div class="card">
  <section>
    <h3>Impressum / Imprint</h3>
    <p>
      Diese Webseite wird privat genutzt.<br><br>
      <b>Impressum Angaben gem. §5 TMG:</b><br><br>
      Inhaltlich verantwortlich ist:<br>
      <address>
        Dominik Mueller<br>
        Muehlstr. 24<br>
        76532 Baden-Baden<br>
        GERMANY<br><br>
        E-Mail: DM (at) DMUELLER (dot) COM
      </address>
    </p>
  </section>
</div>

<?php require_once 'includes/footer.php'; ?>
