<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  global $conn;

  $pages = getAllStaticPages();

  $page_title = 'Manage Static Pages - Administration';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h2>Manage Static Content Pages</h2>
        <p>Edit introductory texts, imprint/impressum, privacy policy, and other informational pages.</p>

        <table style="width:100%;border-collapse:collapse;margin-top:20px;">
          <thead>
            <tr style="border-bottom:2px solid #7B1113;text-align:left;">
              <th style="padding:10px 8px;">Page Key</th>
              <th style="padding:10px 8px;">Page Title</th>
              <th style="padding:10px 8px;">Last Updated</th>
              <th style="padding:10px 8px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pages)): ?>
              <tr>
                <td colspan="4" style="padding:15px 8px;text-align:center;color:#777;">No static pages found. Run migration or seed script.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($pages as $p): ?>
                <tr style="border-bottom:1px solid #ddd;">
                  <td style="padding:10px 8px;"><code><?php echo htmlspecialchars($p['page_key'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td style="padding:10px 8px;"><strong><?php echo htmlspecialchars($p['page_title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                  <td style="padding:10px 8px;font-size:0.9em;color:#555;"><?php echo htmlspecialchars($p['last_updated'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td style="padding:10px 8px;text-align:right;">
                    <a href="editStaticPage.php?key=<?php echo urlencode($p['page_key']); ?>" style="display:inline-block;padding:4px 10px;background-color:#7B1113;color:#fff;text-decoration:none;border-radius:3px;font-size:0.9em;">Edit</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Quick Links</h3>
      <ul>
        <li><a href="settings.php">Site Settings</a></li>
        <li><a href="managePrivileges.php">User &amp; Role Privileges</a></li>
        <li><a href="index.php">Backend Dashboard</a></li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
