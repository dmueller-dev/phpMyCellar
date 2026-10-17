<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  /**
   * Unified Static Content & Sidebar Management Dashboard.
   *
   * Manages site copy, legal notices, homepage welcome blocks, and section sidebars.
   * Supports directory overview and direct in-page content editing.
   * Required privilege: 'manage_static_pages'
   */
  if (!hasPrivilege($conn, 'manage_static_pages') && !hasPrivilege($conn, 'manage_privileges')) {
    header("Location: /backend/index.php");
    exit();
  }

  global $conn;

  $errors = [];
  $success_message = '';

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

  $page_key = trim($_GET['key'] ?? ($_POST['page_key'] ?? ''));

  // Handle Form Submission for editing static page
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
      die('CSRF token validation failed');
    }

    $page_title_input = sanitizeInput($_POST['page_title'] ?? '');
    $page_content_input = $_POST['page_content'] ?? '';
    $meta_desc_input = sanitizeInput($_POST['meta_description'] ?? '');

    if (empty($page_title_input)) {
      $errors[] = 'Content block title cannot be empty.';
    }

    if (empty($errors)) {
      if (saveStaticPage($page_key, $page_title_input, $page_content_input, $meta_desc_input)) {
        $success_message = 'Static content updated successfully.';
      } else {
        $errors[] = 'Failed to update static content in database.';
      }
    }
  }

  // Load all pages from DB
  $db_pages = getAllStaticPages();
  $indexed_db_pages = [];
  foreach ($db_pages as $dp) {
    $indexed_db_pages[$dp['page_key']] = $dp;
  }

  $page_title = 'Manage Static Content & Sidebars - Cellar Administration';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main" style="width: 100%; max-width: 1200px; margin: 0 auto;">
    
    <div style="margin-bottom: 16px;">
      <a href="index.php" style="text-decoration: none; color: var(--secondary-accent, firebrick); font-size: 0.95rem;">
        &larr; Return to Admin Hub
      </a>
    </div>

    <div class="card">
      <div class="admin-card-header">
        <h2 style="margin: 0; color: var(--secondary-accent, firebrick);">
          &#128196; Static Content &amp; Sidebars
        </h2>
        <div style="display: flex; gap: 8px;">
          <a href="settings.php" class="btn-action btn-secondary" style="text-decoration: none; font-size: 0.88rem; padding: 6px 14px;">
            Site Settings &rarr;
          </a>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div style="background-color: #ffdddd; border-left: 5px solid #f44336; padding: 12px 16px; margin-bottom: 20px;">
          <strong style="color: #a00;">Please correct the following errors:</strong>
          <ul style="margin: 8px 0 0; padding-left: 20px; color: #a00;">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div style="background-color: #ddffdd; border-left: 5px solid #4CAF50; padding: 12px 16px; margin-bottom: 20px; color: #2e7d32;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($page_key)): ?>
        <!-- EDITOR VIEW -->
        <?php
          $current_meta = $known_metadata[$page_key] ?? [
            'group' => 'Custom Content',
            'title' => ucfirst(str_replace('_', ' ', $page_key)),
            'location' => 'Custom Block',
            'url' => '',
            'description' => 'Custom static content block.'
          ];
          $page_data = getStaticPage($page_key);
          if (!$page_data) {
            $page_data = [
              'page_key' => $page_key,
              'page_title' => $current_meta['title'] ?? ucfirst(str_replace('_', ' ', $page_key)),
              'page_content' => '',
              'meta_description' => $current_meta['description'] ?? ''
            ];
          }
        ?>
        <div style="margin-bottom: 15px;">
          <a href="manageStaticPages.php" style="text-decoration: none; color: #0d6efd; font-size: 0.95rem;">
            &larr; View all content blocks &amp; sidebars
          </a>
        </div>

        <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
          <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            &#9998; Editing: <?php echo htmlspecialchars($page_data['page_title'] ?? $page_key, ENT_QUOTES, 'UTF-8'); ?>
          </h3>

          <!-- Location Context Box -->
          <div style="background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid var(--secondary-accent, firebrick); padding: 14px 18px; border-radius: 6px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
              <div>
                <div style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: bold;">
                  Target Location:
                </div>
                <div style="font-size: 1.05rem; font-weight: bold; color: #0f172a; margin-top: 2px;">
                  <?php echo htmlspecialchars($current_meta['location'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div style="font-size: 0.88rem; color: #475569; margin-top: 4px;">
                  <?php echo htmlspecialchars($current_meta['description'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
              </div>
              <?php if (!empty($current_meta['url'])): ?>
                <div>
                  <a href="<?php echo htmlspecialchars($current_meta['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn-action" style="font-size: 0.85rem; padding: 6px 14px; text-decoration: none;">
                    View Live Page &#8599;
                  </a>
                </div>
              <?php endif; ?>
            </div>
            <div style="margin-top: 8px; font-size: 0.8rem; color: #94a3b8;">
              Unique Key: <code><?php echo htmlspecialchars($page_key, ENT_QUOTES, 'UTF-8'); ?></code>
            </div>
          </div>

          <form action="manageStaticPages.php?key=<?php echo urlencode($page_key); ?>" method="POST" accept-charset="UTF-8">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($page_key, ENT_QUOTES, 'UTF-8'); ?>">

            <div style="margin-bottom: 16px;">
              <label for="page_title" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Admin Display Title: <span style="color: red;">*</span>
              </label>
              <input type="text" id="page_title" name="page_title" value="<?php echo htmlspecialchars($page_data['page_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required style="width: 100%; max-width: 600px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
            </div>

            <div style="margin-bottom: 16px;">
              <label for="meta_description" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Meta Description / Reference Note:
              </label>
              <input type="text" id="meta_description" name="meta_description" value="<?php echo htmlspecialchars($page_data['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; max-width: 700px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 44px;">
              <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">Used for search engine descriptions or administrative context notes.</div>
            </div>

            <div style="margin-bottom: 20px;">
              <label for="page_content" style="display: block; font-weight: bold; margin-bottom: 6px; font-size: 0.9rem;">
                Content Body (HTML / Rich Text):
              </label>
              <textarea id="page_content" name="page_content" rows="16" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 0.92rem;"><?php echo htmlspecialchars($page_data['page_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
              <div style="font-size: 0.78rem; color: #777; margin-top: 4px;">WYSIWYG editor supported. Dynamic placeholders: <code>{{owner_name}}</code>, <code>{{owner_address}}</code>.</div>
            </div>

            <div style="display: flex; gap: 10px;">
              <button type="submit" class="btn-action" style="padding: 12px 24px; font-size: 1rem; cursor: pointer; min-height: 44px;">
                Save Content
              </button>
              <a href="manageStaticPages.php" class="btn-action btn-secondary" style="padding: 12px 20px; text-decoration: none; text-align: center; font-size: 0.95rem; min-height: 44px; display: inline-flex; align-items: center;">
                Cancel
              </a>
            </div>
          </form>
        </div>

      <?php else: ?>
        <!-- DIRECTORY VIEW -->
        <p style="color: #555; margin-top: 0; font-size: 0.95rem;">
          Configure introductory texts, front-page cards, legal notices, and page sidebars across the cellar notebook.
        </p>

        <?php 
          $groups = [
            'Homepage Content Cards' => [],
            'Full Informational Pages' => [],
            'Section Sidebars & Notices' => [],
            'Other Content' => []
          ];

          $processed_keys = [];
          foreach ($known_metadata as $key => $meta) {
            $db_item = $indexed_db_pages[$key] ?? null;
            $groups[$meta['group']][] = [
              'page_key' => $key,
              'page_title' => $db_item['page_title'] ?? $meta['title'],
              'last_updated' => $db_item['last_updated'] ?? null,
              'location' => $meta['location'],
              'url' => $meta['url'],
              'description' => $meta['description']
            ];
            $processed_keys[$key] = true;
          }

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
        ?>

        <?php foreach ($groups as $group_name => $items): ?>
          <?php if (empty($items)) continue; ?>
          <div style="margin-top: 28px;">
            <h3 style="margin-bottom: 8px; padding-bottom: 6px; border-bottom: 2px solid var(--secondary-accent, firebrick); color: var(--secondary-accent, firebrick); font-size: 1.15rem;">
              <?php echo htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8'); ?>
            </h3>

            <div class="admin-table-container">
              <table>
                <thead>
                  <tr>
                    <th style="width: 28%;">Content Block</th>
                    <th style="width: 30%;">Live Location</th>
                    <th>Description</th>
                    <th style="text-align: right; width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $item): ?>
                    <tr>
                      <td>
                        <strong><?php echo htmlspecialchars($item['page_title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div style="font-size: 0.78rem; color: #777; margin-top: 2px;">
                          <code><?php echo htmlspecialchars($item['page_key'], ENT_QUOTES, 'UTF-8'); ?></code>
                          <?php if (!empty($item['last_updated'])): ?>
                            &bull; Updated: <?php echo date('M j, Y', strtotime($item['last_updated'])); ?>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <span style="color: #333; font-weight: 500; font-size: 0.9rem;"><?php echo htmlspecialchars($item['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($item['url'])): ?>
                          <div>
                            <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="font-size: 0.8rem; color: #0d6efd; text-decoration: none;">
                              View live &#8599;
                            </a>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td style="font-size: 0.85rem; color: #666; line-height: 1.4;">
                        <?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <a href="manageStaticPages.php?key=<?php echo urlencode($item['page_key']); ?>" class="btn-action" style="font-size: 0.82rem; padding: 5px 12px; text-decoration: none;">
                          Edit Content
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
