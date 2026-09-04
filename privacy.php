<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  $page = getStaticPage('privacy');
  $page_title = ($page['page_title'] ?? 'Privacy Policy') . ' - ' . getSiteTitle();
  $meta_desc = $page['meta_description'] ?? ('Privacy policy for ' . getSiteTitle());
  $meta_keywords = buildKeywordsList([
    'privacy policy', 'data protection', 'GDPR', 'privacy', 'cookies',
    getOwnerName(), getSiteTitle()
  ]);

  require_once 'includes/header.php';
?>

<div class="card">
  <section>
    <?php if (!empty($page['page_content'])): ?>
      <?php echo function_exists('interpolateSiteSettings') ? interpolateSiteSettings($page['page_content']) : $page['page_content']; ?>
    <?php else: ?>
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
        <br><br>You have the right to contact the cellar master regarding any questions about your data or this privacy policy.
        <br><br>For more information, please contact using the contact details below.
      </p>
    <?php endif; ?>
  </section>
</div>

<?php require_once 'includes/footer.php'; ?>
