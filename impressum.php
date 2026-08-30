<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  $page = getStaticPage('impressum');
  $page_title = ($page['page_title'] ?? 'Impressum / Imprint') . ' - ' . getSiteTitle();
  $meta_desc = $page['meta_description'] ?? ('Impressum and legal notice for ' . getSiteTitle());
  $owner_name = getOwnerName();
  $owner_email = getOwnerEmail();
  $owner_address = getSiteSetting('owner_address', '');

  require_once 'includes/header.php';
?>

<div class="card">
  <section>
    <?php if (!empty($page['page_content'])): ?>
      <?php echo function_exists('interpolateSiteSettings') ? interpolateSiteSettings($page['page_content']) : $page['page_content']; ?>
    <?php else: ?>
      <h3>Impressum / Imprint</h3>
      <p>
        Diese Webseite wird privat genutzt.<br><br>
        <b>Impressum Angaben gem. §5 TMG:</b><br><br>
        Inhaltlich verantwortlich ist:<br>
        <address>
          <?php echo htmlspecialchars($owner_name, ENT_QUOTES, 'UTF-8'); ?><br>
          <?php if (!empty($owner_address)): ?>
            <div class="impressum-address"><?php echo $owner_address; ?></div>
          <?php endif; ?>
          E-Mail: <a href="mailto:<?php echo htmlspecialchars($owner_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($owner_email, ENT_QUOTES, 'UTF-8'); ?></a>
        </address>
      </p>
    <?php endif; ?>
  </section>
</div>

<?php require_once 'includes/footer.php'; ?>
