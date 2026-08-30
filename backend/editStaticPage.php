<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  global $conn;

  // Metadata mapping for all known static content keys
  $known_metadata = [
    // Full Informational Pages
    'impressum' => [
      'group' => 'Full Informational Pages',
      'title' => 'Impressum / Imprint',
      'location' => 'Imprint / Legal Notice Page',
      'url' => '/impressum.php',
      'description' => 'Mandatory legal notice, operator details, postal address, and contact information.'
    ],
    'privacy' => [
      'group' => 'Full Informational Pages',
      'title' => 'Privacy Policy',
      'location' => 'Privacy Policy Page',
      'url' => '/privacy.php',
      'description' => 'Data privacy declaration, session cookie details, and user data rights.'
    ],

    // Homepage Cards
    'welcome' => [
      'group' => 'Homepage Content Cards',
      'title' => 'Homepage: Welcome Introduction',
      'location' => 'Homepage — Main Welcome Card (Left)',
      'url' => '/index.php',
      'description' => 'Welcome message and introduction to the cellar notebook on the homepage.'
    ],
    'get_in_touch' => [
      'group' => 'Homepage Content Cards',
      'title' => 'Homepage: Get in Touch Card',
      'location' => 'Homepage — Get in Touch Card (Right)',
      'url' => '/index.php',
      'description' => 'Contact invitation and cellar notebook access request details on the homepage.'
    ],

    // Section Sidebars & Notices
    'tnotes_sidebar' => [
      'group' => 'Section Sidebars & Notices',
      'title' => 'Tasting Notes: Sidebar Introduction',
      'location' => 'Tasting Notes Index — Right Sidebar',
      'url' => '/tnotes.php',
      'description' => 'Introductory text explaining the tasting notes list and tasting frequency.'
    ],
    'wines_sidebar' => [
      'group' => 'Section Sidebars & Notices',
      'title' => 'Wine Database: Sidebar Notice',
      'location' => 'Wine Database Index — Right Sidebar',
      'url' => '/wines.php',
      'description' => 'Notice clarifying that listed wines are bottles reviewed across notes and stories.'
    ],
    'winemenu_sidebar' => [
      'group' => 'Section Sidebars & Notices',
      'title' => 'Wine Menu: Cellar Invitation Sidebar',
      'location' => 'Wine Menu / Cellar — Right Sidebar',
      'url' => '/winemenu.php',
      'description' => 'Invitation welcoming guests to select and open any ready cellar bottle.'
    ],
    'vintages_sidebar' => [
      'group' => 'Section Sidebars & Notices',
      'title' => 'Vintage Reports: Sidebar Overview & Note',
      'location' => 'Vintage Reports Index — Right Sidebar',
      'url' => '/vintages.php',
      'description' => 'Explanatory note on how vintage scores and reports are dynamically compiled.'
    ],
    'blog_sidebar' => [
      'group' => 'Section Sidebars & Notices',
      'title' => 'Stories: Sidebar Introduction',
      'location' => 'Stories / Blog Index — Right Sidebar',
      'url' => '/blog.php',
      'description' => 'Introduction to stories, tasting retrospectives, and wine trips.'
    ],
  ];

  $errors = [];
  $success_message = '';
  $page_key = trim($_GET['key'] ?? ($_POST['page_key'] ?? ''));

  if (empty($page_key)) {
    header('Location: manageStaticPages.php');
    exit;
  }

  $current_meta = $known_metadata[$page_key] ?? [
    'group' => 'Content Block',
    'title' => ucfirst(str_replace('_', ' ', $page_key)),
    'location' => 'Custom Block',
    'url' => '',
    'description' => 'Custom static content block.'
  ];

  // Handle form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die('CSRF token validation failed');
    }

    $page_title_input = sanitizeInput($_POST['page_title'] ?? '');
    $page_content_input = $_POST['page_content'] ?? '';
    $meta_desc_input = sanitizeInput($_POST['meta_description'] ?? '');

    if (empty($page_title_input)) {
      $errors[] = 'Page title cannot be empty.';
    }

    if (empty($errors)) {
      if (saveStaticPage($page_key, $page_title_input, $page_content_input, $meta_desc_input)) {
        $success_message = 'Static content updated successfully.';
      } else {
        $errors[] = 'Failed to update static content in the database.';
      }
    }
  }

  $page_data = getStaticPage($page_key);
  if (!$page_data && empty($success_message)) {
    // If not in DB yet, create a skeleton
    $page_data = [
      'page_key' => $page_key,
      'page_title' => $current_meta['title'] ?? ucfirst(str_replace('_', ' ', $page_key)),
      'page_content' => '',
      'meta_description' => $current_meta['description'] ?? ''
    ];
  }

  $page_title = 'Edit Content: ' . htmlspecialchars($page_data['page_title'] ?? $page_key, ENT_QUOTES, 'UTF-8');
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <p><a href="manageStaticPages.php">&larr; Back to all static content &amp; sidebars</a></p>
        
        <h2>Edit: <?php echo htmlspecialchars($page_data['page_title'] ?? $page_key, ENT_QUOTES, 'UTF-8'); ?></h2>

        <!-- Context & Location Information Box -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid var(--primary-accent, #CD5C5C);padding:12px 16px;border-radius:4px;margin-bottom:20px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
            <div>
              <div style="font-size:0.85em;text-transform:uppercase;letter-spacing:0.5px;color:#64748b;font-weight:600;">Where this appears:</div>
              <div style="font-size:1.05em;font-weight:600;color:#0f172a;margin-top:2px;">
                <?php echo htmlspecialchars($current_meta['location'], ENT_QUOTES, 'UTF-8'); ?>
              </div>
              <div style="font-size:0.88em;color:#475569;margin-top:4px;">
                <?php echo htmlspecialchars($current_meta['description'], ENT_QUOTES, 'UTF-8'); ?>
              </div>
            </div>
            <?php if (!empty($current_meta['url'])): ?>
              <div>
                <a href="<?php echo htmlspecialchars($current_meta['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn-action" style="display:inline-block;padding:6px 12px;font-size:0.85em;text-decoration:none;">
                  View Live Page &#8599;
                </a>
              </div>
            <?php endif; ?>
          </div>
          <div style="margin-top:8px;font-size:0.82em;color:#94a3b8;">
            Unique Identifier: <code><?php echo htmlspecialchars($page_key, ENT_QUOTES, 'UTF-8'); ?></code>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div style="background-color:#ffdddd;border-left:5px solid #f44336;padding:10px 15px;margin-bottom:20px;">
            <ul style="margin:0;padding-left:20px;color:#a00;">
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
          <div style="background-color:#ddffdd;border-left:5px solid #4CAF50;padding:10px 15px;margin-bottom:20px;color:#2e7d32;">
            <?php echo $success_message; ?>
          </div>
        <?php endif; ?>

        <form action="editStaticPage.php?key=<?php echo urlencode($page_key); ?>" method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
          <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($page_key, ENT_QUOTES, 'UTF-8'); ?>">

          <div style="margin-bottom:15px;">
            <label for="page_title"><strong>Admin Display Title:</strong></label><br>
            <input type="text" id="page_title" name="page_title" value="<?php echo htmlspecialchars($page_data['page_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required style="width:100%;max-width:550px;padding:8px;">
          </div>

          <div style="margin-bottom:15px;">
            <label for="meta_description"><strong>Meta Description / Note:</strong></label><br>
            <input type="text" id="meta_description" name="meta_description" value="<?php echo htmlspecialchars($page_data['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;max-width:650px;padding:8px;">
            <br><small style="color:#666;">Used for SEO meta descriptions on full pages or as reference notes.</small>
          </div>

          <div style="margin-bottom:15px;">
            <label for="page_content"><strong>Content (HTML / WYSIWYG):</strong></label><br>
            <textarea id="page_content" name="page_content" rows="18" cols="60" style="width:100%;padding:8px;font-family:Georgia,serif;"><?php echo htmlspecialchars($page_data['page_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            <br><small style="color:#666;">The WYSIWYG editor is enabled on this field for formatted editing.</small>
          </div>

          <div style="margin-top:20px;">
            <button type="submit" class="btn-action" style="padding:10px 24px;font-size:16px;">Save Content</button>
            <a href="manageStaticPages.php" style="margin-left:15px;color:#555;text-decoration:none;">Cancel</a>
          </div>
        </form>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3 style="margin-top:0;">Switch Content Block</h3>
      <p style="font-size:0.85em;color:#64748b;margin-bottom:10px;">Quickly jump to edit another section:</p>

      <div style="font-size:0.85em;font-weight:bold;color:#475569;margin-top:8px;">Homepage:</div>
      <ul style="margin:4px 0 10px 18px;padding:0;font-size:0.88em;">
        <li><a href="editStaticPage.php?key=welcome" style="<?= $page_key === 'welcome' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Welcome Introduction</a></li>
        <li><a href="editStaticPage.php?key=get_in_touch" style="<?= $page_key === 'get_in_touch' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Get in Touch Card</a></li>
      </ul>

      <div style="font-size:0.85em;font-weight:bold;color:#475569;margin-top:8px;">Sidebars &amp; Notices:</div>
      <ul style="margin:4px 0 10px 18px;padding:0;font-size:0.88em;">
        <li><a href="editStaticPage.php?key=tnotes_sidebar" style="<?= $page_key === 'tnotes_sidebar' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Tasting Notes Sidebar</a></li>
        <li><a href="editStaticPage.php?key=wines_sidebar" style="<?= $page_key === 'wines_sidebar' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Wine Database Sidebar</a></li>
        <li><a href="editStaticPage.php?key=winemenu_sidebar" style="<?= $page_key === 'winemenu_sidebar' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Wine Menu Sidebar</a></li>
        <li><a href="editStaticPage.php?key=vintages_sidebar" style="<?= $page_key === 'vintages_sidebar' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Vintage Reports Sidebar</a></li>
        <li><a href="editStaticPage.php?key=blog_sidebar" style="<?= $page_key === 'blog_sidebar' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Stories Sidebar</a></li>
      </ul>

      <div style="font-size:0.85em;font-weight:bold;color:#475569;margin-top:8px;">Full Pages:</div>
      <ul style="margin:4px 0 10px 18px;padding:0;font-size:0.88em;">
        <li><a href="editStaticPage.php?key=impressum" style="<?= $page_key === 'impressum' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Impressum / Imprint</a></li>
        <li><a href="editStaticPage.php?key=privacy" style="<?= $page_key === 'privacy' ? 'font-weight:bold;color:var(--primary-accent, #CD5C5C);' : '' ?>">Privacy Policy</a></li>
      </ul>
    </div>

    <div class="card">
      <h3 style="margin-top:0;">Dynamic Site Variables</h3>
      <p><small style="color:#555;">Insert any of these tags to automatically pull live data from <a href="settings.php">Site Settings</a>:</small></p>
      <ul style="font-size:0.88em;color:#334155;padding-left:18px;margin-bottom:0;">
        <li><code>{{owner_name}}</code>: Owner Name</li>
        <li><code>{{owner_address}}</code>: Postal Address</li>
        <li><code>{{owner_email}}</code>: Contact Email</li>
        <li><code>{{site_name}}</code>: Site Title</li>
        <li><code>{{site_tagline}}</code>: Site Tagline</li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
