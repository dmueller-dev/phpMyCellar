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

  $db_pages = getAllStaticPages();
  $indexed_db_pages = [];
  foreach ($db_pages as $dp) {
    $indexed_db_pages[$dp['page_key']] = $dp;
  }

  // Group pages for presentation
  $groups = [
    'Homepage Content Cards' => [],
    'Full Informational Pages' => [],
    'Section Sidebars & Notices' => [],
    'Other Content' => []
  ];

  // Process known keys first to guarantee consistent ordering
  $processed_keys = [];
  foreach ($known_metadata as $key => $meta) {
    $db_item = $indexed_db_pages[$key] ?? null;
    $item = [
      'page_key' => $key,
      'page_title' => $db_item['page_title'] ?? $meta['title'],
      'last_updated' => $db_item['last_updated'] ?? null,
      'location' => $meta['location'],
      'url' => $meta['url'],
      'description' => $meta['description']
    ];
    $groups[$meta['group']][] = $item;
    $processed_keys[$key] = true;
  }

  // Any additional custom keys in DB
  foreach ($indexed_db_pages as $key => $dp) {
    if (!isset($processed_keys[$key])) {
      $groups['Other Content'][] = [
        'page_key' => $key,
        'page_title' => $dp['page_title'],
        'last_updated' => $dp['last_updated'] ?? null,
        'location' => 'Custom Content Block',
        'url' => '',
        'description' => $dp['meta_description'] ?? 'Custom static content block.'
      ];
    }
  }

  $page_title = 'Manage Static Content & Sidebars - Administration';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <section>
        <h2>Manage Static Content &amp; Sidebars</h2>
        <p>Edit introductory texts, front-page cards, full legal pages, and page sidebars across the site. Each item shows exactly where it appears on the live website.</p>

        <?php foreach ($groups as $group_name => $items): ?>
          <?php if (empty($items)) continue; ?>
          <div style="margin-top:30px;">
            <h3 style="margin-bottom:10px;padding-bottom:5px;border-bottom:2px solid var(--primary-accent, #CD5C5C);color:var(--primary-accent, #CD5C5C);">
              <?php echo htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            
            <table style="width:100%;border-collapse:collapse;margin-top:10px;">
              <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #cbd5e1;text-align:left;font-size:0.88em;color:#475569;">
                  <th style="padding:10px 8px;width:30%;">Content Block</th>
                  <th style="padding:10px 8px;width:32%;">Location &amp; Live Preview</th>
                  <th style="padding:10px 8px;width:23%;">Description</th>
                  <th style="padding:10px 8px;width:15%;text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:12px 8px;vertical-align:top;">
                      <strong><?php echo htmlspecialchars($item['page_title'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                      <code style="font-size:0.82em;background:#f1f5f9;padding:2px 5px;border-radius:3px;color:#0f172a;"><?php echo htmlspecialchars($item['page_key'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php if (!empty($item['last_updated'])): ?>
                        <div style="font-size:0.75em;color:#94a3b8;margin-top:4px;">Updated: <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($item['last_updated'])), ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php endif; ?>
                    </td>
                    <td style="padding:12px 8px;vertical-align:top;font-size:0.9em;">
                      <span style="color:#334155;font-weight:500;"><?php echo htmlspecialchars($item['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php if (!empty($item['url'])): ?>
                        <br><a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="font-size:0.85em;color:var(--primary-accent, #CD5C5C);text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:2px;">
                          View live page &#8599;
                        </a>
                      <?php endif; ?>
                    </td>
                    <td style="padding:12px 8px;vertical-align:top;font-size:0.85em;color:#64748b;line-height:1.4;">
                      <?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td style="padding:12px 8px;vertical-align:top;text-align:right;">
                      <a href="editStaticPage.php?key=<?php echo urlencode($item['page_key']); ?>" class="btn-action" style="display:inline-block;padding:5px 12px;font-size:0.88em;white-space:nowrap;">Edit Content</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
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
    <div class="card">
      <h3>Content Guidance</h3>
      <p style="font-size:0.9em;color:#475569;line-height:1.4;">
        Static content blocks allow you to customize site copy without editing source code.
      </p>
      <p style="font-size:0.9em;color:#475569;line-height:1.4;">
        You can also use dynamic placeholders like <code>{{owner_name}}</code> or <code>{{owner_address}}</code> inside any text block to automatically stay synced with your Site Settings.
      </p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
