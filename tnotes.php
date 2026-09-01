<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Check if user has permission to view tasting notes
  if (!hasPrivilege($conn, 'view_tnotes')) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
  }

  $is_single = isset($_GET['id']) && trim($_GET['id']) !== '';

  if ($is_single) {
    // Get tasting note ID
    $noteID = $_GET['id'];

    // Include the database configuration file
    global $mysqli, $conn;

    // Fetch the tasting note and ensure it exists before proceeding
    getNote($noteID);
    if (empty($tasting_note)) {
      header("Location: /tnotes.php");
      exit;
    }

    // Generate the token for the form
    $csrf_token = generateCSRFToken();

    // Fetch user details if logged in
    $user_id = $_SESSION['user_id'] ?? null;
    $username = '';
    $displayname = '';
    if ($user_id) {
      $stmt = $mysqli->prepare("SELECT username, displayname FROM users WHERE user_id = ?");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $stmt->bind_result($username, $displayname);
      if (!$stmt->fetch()) {
        $stmt->close();
        die("<h2>User not found.</h2>");
      }
      $stmt->close();
    }

    // Initialize error and success messages
    $error = "";
    $success = "";

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!hasPrivilege($conn, 'post_comments')) {
        $error = "You do not have permission to post comments.";
      } elseif (!$user_id) {
        $error = "You must be logged in to post comments.";
      } elseif (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Security check failed. Please refresh the page and try again.";
      } else {
        $comment = $_POST['comment'] ?? '';
        $mysqli->autocommit(FALSE);
        $post = $mysqli->prepare("INSERT INTO comments (user_id, content) VALUES (?, ?)");
        $post->bind_param('is', $user_id, $comment);
        if ($post->execute()) {
          $last_id = $mysqli->insert_id;
          $post->close();
          $post = $mysqli->prepare("INSERT INTO x_comments_tnotes (comment_id, note_id) VALUES (?, ?)");
          $post->bind_param('ii', $last_id, $noteID);
          if ($post->execute()) {
            $success = "Thank you! Comment posted successfully.";
            $mysqli->commit();
            $mysqli->autocommit(TRUE);
            
            // Auto-subscribe the commenter and notify existing subscribers
            autoSubscribe($mysqli, $user_id, $noteID, 'tnote');
            createNotificationsForComment($mysqli, $user_id, $noteID, 'tnote', $last_id);
          } else {
            $error = "Failed to post comment. Please try again.";
            $mysqli->rollback();
            $mysqli->autocommit(TRUE);
          }
        } else {
          $error = "Failed to post comment. Please try again.";
          $mysqli->rollback();
          $mysqli->autocommit(TRUE);
        }
        $post->close();
      }
    }

    // Vintage NV?
    if ($tasting_note["vintage"]==null) { $tasting_note["vintage"]="NV"; }
    // Get wine name
    if ($tasting_note["nameconvention"]=="vintage_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["name"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer_grape_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["grape"]." ".$tasting_note["name"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer_vineyard_grape_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["vineyard"]." ".$tasting_note["grape"]." ".$tasting_note["name"];
    } elseif ($tasting_note["nameconvention"]=="vintage_producer_vineyard_name") {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["vineyard"]." ".$tasting_note["name"];
    // ...else vintage_producer_name as default:
    } else {
      $wine_name=$tasting_note["vintage"]." ".$tasting_note["producer"]." ".$tasting_note["name"];
    }

    // Page title and SEO metadata
    $owner_name = getOwnerName();
    $page_title = getSiteTitle() . " - " . $wine_name . " (Tasting Note)";
    $meta_keywords = generateTastingNoteKeywords($tasting_note);
    $meta_desc = generateTastingNoteDescription($tasting_note, $wine_name);
    $canonical_url = getAbsoluteUrl('/tnotes.php?id=' . $noteID);
    $og_type = 'article';
    $og_image = !empty($tasting_note['img']) ? getAbsoluteUrl('/uploads/img/' . $tasting_note['img']) : null;
    $article_meta = [
      'published_time' => !empty($tasting_note['tasting_date']) ? $tasting_note['tasting_date'] : null,
      'author' => !empty($tasting_note['displayname']) ? $tasting_note['displayname'] : $owner_name
    ];
    $json_ld = generateTastingNoteJsonLd($tasting_note, $wine_name, $canonical_url);

    require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3><?php echo $wine_name;?></h3>
    </div>
    <div class="card">
      <section>
	<h3>Tasting note</h3>
        <?php
          if($tasting_note["img"]!=null) {
            echo "<img class='".$tasting_note["img_class"]."' src='/uploads/img/".$tasting_note["img"]."' alt='".$wine_name."'>";
          }
          echo $tasting_note["tasting_note"];
          if($tasting_note["drinkwindow_min"]!=null && $tasting_note["drinkwindow_max"]!=null) {
            echo " <p>Drink from " . $tasting_note["drinkwindow_min"] . " through " . $tasting_note["drinkwindow_max"] . ".</p>";
          } elseif ($tasting_note["drinkwindow_max"]!=null) {
            echo " <p>Drink through " . $tasting_note["drinkwindow_max"] . ".</p>";
          }
          echo "<p><i>Tasted by ".$tasting_note["displayname"]." on ".date_format(date_create($tasting_note["tasting_date"]),"l, j F Y").".</i></p>";
        ?>
      </section>
    </div>
    <?php renderBlogReferences($conn, $noteID, 'tnote', 'Referenced in these stories:'); ?>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Ratings</h3>
      <p>
        <table>
          <tr><td>Flawed?</td><td style="width:5px;"></td><td><?php echo $tasting_note["flawed_yn"];?></td></tr>
          <?php 
            $scale = getRatingScale();
            $wset_enabled = isWsetSATEnabled();
            $score_initials = htmlspecialchars($tasting_note["initials"] ?? 'DM', ENT_QUOTES, 'UTF-8');
            $active_score = ($scale === '100-point') ? ($tasting_note['pts_100'] ?? null) : ($tasting_note['pts_20'] ?? $tasting_note['dmpts'] ?? null);
            $max_score = getRatingScaleMax($scale);
            $desc_field = ($scale === '100-point') ? 'scale_100_desc' : 'scale_20_desc';
            $class_field = ($scale === '100-point') ? 'scale_100_class' : 'scale_20_class';
            $score_desc = $tasting_note[$desc_field] ?? $tasting_note['pts_desc'] ?? $tasting_note['dmpts_desc'] ?? '';
            $score_class = $tasting_note[$class_field] ?? $tasting_note['pts_class'] ?? $tasting_note['dmpts_class'] ?? '';
          ?>
          <tr>
            <td><?php echo $score_initials; ?>:</td>
            <td style="width:5px;"></td>
            <td>
              <?php
                if ($active_score === null || $active_score === '') {
                  echo "not rated";
                } elseif (!empty($score_desc)) {
                  echo "<div class='tooltip'>" . htmlspecialchars($active_score, ENT_QUOTES, 'UTF-8') . "<span class='tooltiptext'>" . htmlspecialchars($score_desc, ENT_QUOTES, 'UTF-8') . "</span></div> / " . $max_score . (!empty($score_class) ? " (&quot;" . htmlspecialchars($score_class, ENT_QUOTES, 'UTF-8') . "&quot;)" : "");
                } else {
                  echo htmlspecialchars($active_score, ENT_QUOTES, 'UTF-8') . " / " . $max_score . (!empty($score_class) ? " (&quot;" . htmlspecialchars($score_class, ENT_QUOTES, 'UTF-8') . "&quot;)" : "");
                }
              ?>
            </td>
          </tr>
          <?php if ($wset_enabled && !empty($tasting_note['wsetpts'])): ?>
            <tr>
              <td>WSET SAT:</td>
              <td style="width:5px;"></td>
              <td>
                <?php
                  $wset_pts = $tasting_note['wsetpts'];
                  $wset_desc = $tasting_note['wset_desc'] ?? '';
                  if (!empty($wset_desc)) {
                    echo "<div class='tooltip'>" . htmlspecialchars($wset_pts, ENT_QUOTES, 'UTF-8') . "<span class='tooltiptext'>" . htmlspecialchars($wset_desc, ENT_QUOTES, 'UTF-8') . "</span></div> / 4.0";
                  } else {
                    echo htmlspecialchars($wset_pts, ENT_QUOTES, 'UTF-8') . " / 4.0";
                  }
                ?>
              </td>
            </tr>
          <?php endif; ?>
          <tr>
            <td>Favourite:</td>
            <td style="width:5px;"></td>
            <td>
              <?php
                if(isset($tasting_note["favourite"]) && $tasting_note["favourite"] == 'yes') {
                  echo "<span style='color:#e25555; font-size:0.9em;'>❤️ Yes</span>";
                } else {
                  echo "No";
                }
              ?>
            </td>
          </tr>
        </table>
      </p>
      <?php if ($tasting_note["blind"]=="blind") { echo "<p><em>Tasted blind.</em></p>"; } ?>
      <p><a href="/blog.php?id=26" title="How I rate wines">Find out more about how I rate wines.</a></p>
    </div>
    <div class="card">
    <h3>Wine details</h3>
    <p>
      <table>
        <tr>
          <td>Vintage:</td>
          <td>
            <?php
              if ($tasting_note["vintage_desc"]==null) {
                echo $tasting_note["vintage"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["vintage"]."<span class='tooltiptext'>".$tasting_note["vintage_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Colour:</td><td><?php echo $tasting_note["colour"];?></td></tr>
        <tr><td>Style:</td><td><?php echo $tasting_note["style"];?></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Assemblage:</td><td><?php echo $tasting_note["cuvee_yn"];?></td></tr>
        <tr>
          <td>Grape variety:</td>
          <td>
            <?php
              if ($tasting_note["grape_desc"]==null) {
                echo $tasting_note["grape"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["grape"]."<span class='tooltiptext'>".$tasting_note["grape_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr><td colspan="2"><font style="font-size:12px;">For <i>assemblages</i>, the main grape variety is shown.</font></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Producer:</td><td><?php echo "<a href='/producers.php?id=".$tasting_note["producer_id"]."'>".$tasting_note["producer"]."</a>";?></td></tr>
        <tr><td>Country:</td><td><?php echo $tasting_note["country"];?></td></tr>
        <tr>
          <td>Region:</td>
          <td>
            <?php
              if ($tasting_note["region_desc"]==null) {
                echo $tasting_note["region"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["region"]."<span class='tooltiptext'>".$tasting_note["region_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr><td>Subregion:</td><td><?php if ($tasting_note["subregion"]==null){echo "n/a";}else{echo $tasting_note["subregion"];}?></td></tr>
        <tr>
          <td>Appellation:</td>
          <td>
            <?php
              if ($tasting_note["appellation"]==null) {
                echo "n/a";
              } elseif ($tasting_note["appellation_desc"]==null) {
                echo $tasting_note["appellation"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["appellation"]."<span class='tooltiptext'>".$tasting_note["appellation_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr>
          <td>Vineyard:</td>
          <td>
            <?php
              if ($tasting_note["vineyard"]==null) {
                echo "n/a";
              } elseif ($tasting_note["vineyard_desc"]==null) {
                echo $tasting_note["vineyard"];
              } else {
                echo "<div class='tooltip'>".$tasting_note["vineyard"]."<span class='tooltiptext'>".$tasting_note["vineyard_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td colspan="2"><?php echo "<a href='/wines.php?id=".$tasting_note["wine_id"]."'>More details on this wine.</a>";?></td></tr>
        <tr><td colspan="2"><?php echo ($tasting_note["ct_id"]!==null) ? "<a href='https://www.cellartracker.com/wine.asp?iWine=".$tasting_note["ct_id"]."' target='_blank' rel='noopener noreferrer'>View this wine on CellarTracker.</a>" : "";?></td></tr>
        <tr><td colspan="2"><a href="<?php echo getWineSearcherUrl($wine_name); ?>" target="_blank" rel="noopener noreferrer">Find this wine on Wine-Searcher.</a></td></tr>
      </table>
    </p>
    </div>
    <?php moreNotes($noteID, $tasting_note["wine_id"], $wine_name); ?>
    <?php if ($tasting_note["vintage"]!="NV") { otherVintages($tasting_note["wine_id"], $tasting_note["master_id"]); } ?>
    <?php if (hasPrivilege($conn, 'view_comments')): ?>
      <?php
        $is_subbed = isset($_SESSION['user_id']) ? isSubscribed($conn, $_SESSION['user_id'], $noteID, 'tnote') : false;
      ?>
      <div class="card" id="comments" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div><h3 style="margin:0;">Discussion</h3></div>
        <?php if (isset($_SESSION['user_id'])): ?>
          <div class="subscription-container" style="margin:0;">
            <?php if ($is_subbed): ?>
              <button id="btn-sub-toggle" class="btn-subscription subscribed" onclick="toggleSubscriptionAjax(<?= $noteID ?>, 'tnote')">🔕 Unsubscribe</button>
            <?php else: ?>
              <button id="btn-sub-toggle" class="btn-subscription" onclick="toggleSubscriptionAjax(<?= $noteID ?>, 'tnote')">🔔 Subscribe to discussion</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (hasPrivilege($conn, 'post_comments')): ?>
        <div class="card">
          <details><summary><h3 style="display:inline;margin:0;">Post a comment</h3></summary>
          <?php
            if ($error!="") {
              echo "<div style='color:red;'>$error</div>";
            } elseif ($success!="") {
              echo "<div style='color:green;'>$success</div>";
            }
          ?>
          <form method="post" autocomplete="off" accept-charset="UTF-8" style="margin-bottom:10px;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <label for="username">Your name:</label>
            <br><input type="text" id="username" value="<?= htmlspecialchars($displayname ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
            <br><br>
            <label for="comment">Your comment:</label>
            <br><textarea name="comment" id="comment" rows="15" cols="35" maxlength="2000" placeholder="..."></textarea>
            <br><br>
            <button type="submit">Post comment</button>
          </form></details>
        </div>
      <?php elseif (!isset($_SESSION['user_id'])): ?>
        <div class="card">
          <p style="margin:0; font-size:0.95em; color:#666;">Please <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">log in</a> to post a comment or join the discussion.</p>
        </div>
      <?php endif; ?>

      <?php renderComments($conn, $noteID, 'tnote'); ?>

      <?php if (isset($_SESSION['user_id'])): ?>
        <script>
        function toggleSubscriptionAjax(id, type) {
          const btn = document.getElementById('btn-sub-toggle');
          if (!btn) return;
          
          btn.disabled = true;
          
          const formData = new FormData();
          formData.append('id', id);
          formData.append('type', type);
          formData.append('csrf_token', '<?= $csrf_token ?>');
          
          const xhr = new XMLHttpRequest();
          xhr.open('POST', '/toggle_subscription.php', true);
          xhr.onload = function() {
            btn.disabled = false;
            if (xhr.status === 200) {
              try {
                const res = JSON.parse(xhr.responseText);
                if (res.status === 'success') {
                  if (res.action === 'subscribed') {
                    btn.textContent = '🔕 Unsubscribe';
                    btn.className = 'btn-subscription subscribed';
                  } else {
                    btn.textContent = '🔔 Subscribe to discussion';
                    btn.className = 'btn-subscription';
                  }
                } else {
                  alert(res.message);
                }
              } catch (e) {
                alert('An error occurred. Please try again.');
              }
            } else {
              alert('An error occurred. Please try again.');
            }
          };
          xhr.send(formData);
        }
        </script>
      <?php endif; ?>
    <?php else: ?>
      <div class="card" id="comments">
        <h3>Discussion</h3>
        <p>
          <strong>Join the discussion:</strong> <?php if (!isset($_SESSION['user_id'])): ?>Only logged-in members can read and write comments. Please <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">log in</a> to participate.<?php else: ?>Only authorized members can read and write comments.<?php endif; ?>
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<?php
  } else {
    // Browse all tasting notes list view
    if (empty($_GET['sort'])) {
      $sort="date";
    } else {
      $sort=$_GET['sort'];
    }

    if ($sort=="date" || $sort=="tenyears" || $sort=="twentyplus") {
      $sqlOrderBy="order by tnotes.tasting_date desc, wines.wine_id asc, tnotes.note_id desc";
    } elseif ($sort=="rating") {
      $active_scale = getRatingScale();
      if ($active_scale === '100-point') {
        $sqlOrderBy="order by tnotes.flawed_yn asc, tnotes.pts_100 desc, producers.producer asc, wines.vintage desc, wines_master.name asc, tnotes.tasting_date desc, tnotes.note_id desc";
      } else {
        $sqlOrderBy="order by tnotes.flawed_yn asc, COALESCE(tnotes.pts_20, tnotes.dmpts) desc, producers.producer asc, wines.vintage desc, wines_master.name asc, tnotes.tasting_date desc, tnotes.note_id desc";
      }
    } elseif ($sort=="region") {
      $sqlOrderBy="order by regions.country asc, regions.region asc, producers.producer asc, wines.vintage desc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, tnotes.tasting_date desc, tnotes.note_id desc";
    } elseif ($sort=="producer") {
      $sqlOrderBy="order by producers.producer asc, wines.vintage desc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, regions.region asc, tnotes.tasting_date desc, tnotes.note_id desc";
    } elseif ($sort=="vintage") {
      $sqlOrderBy="order by wines.vintage desc, regions.country asc, producers.producer asc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, tnotes.tasting_date desc, tnotes.note_id desc";
    } elseif ($sort=="variety") {
      $sqlOrderBy="order by wines_master.grape asc, regions.country asc, producers.producer asc, wines.vintage desc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, tnotes.tasting_date desc, tnotes.note_id desc";
    }

    $search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $is_fav = (isset($_GET['favourite']) && $_GET['favourite'] === 'yes');
    $site_title = getSiteTitle();
    $owner_name = getOwnerName();
    if (!empty($search_query)) {
      $page_title = $site_title . ' - Tasting Notes: Search "' . htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') . '"';
      $meta_desc = 'Search tasting notes and ratings for "' . htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') . '" on ' . $site_title . ' fine wine notebook.';
      $meta_keywords = buildKeywordsList([$search_query, 'wine tasting notes', 'wine reviews', 'wine ratings', $owner_name]);
    } elseif ($is_fav) {
      $page_title = $site_title . ' - Favourite Tasting Notes';
      $meta_desc = 'Explore ' . $site_title . ' favourite fine wine tasting notes and top-rated cellar highlights.';
      $meta_keywords = $owner_name . ', favourite wines, top rated wines, fine wine reviews, wine cellar highlights';
    } else {
      $sortLabels = [
        'date' => 'by Tasting Date',
        'rating' => 'by Rating',
        'region' => 'by Region',
        'producer' => 'by Producer',
        'vintage' => 'by Vintage',
        'variety' => 'by Grape Variety',
        'tenyears' => 'Aged 10 Years',
        'twentyplus' => 'Aged 20+ Years'
      ];
      $sortSuffix = isset($sortLabels[$sort]) ? ' (' . $sortLabels[$sort] . ')' : '';
      $page_title = $site_title . ' - Browse all tasting notes' . $sortSuffix;
      $meta_desc = 'Browse fine wine tasting notes and independent wine ratings by ' . $owner_name . ' across wine regions worldwide.';
      $meta_keywords = 'wine tasting notes, wine reviews, wine ratings, fine wine reviews, wine tasting notebook, ' . $owner_name;
    }
    $canonical_url = getAbsoluteUrl('/tnotes.php');
    $og_type = 'website';
    $json_ld = [
      '@context' => 'https://schema.org',
      '@type' => 'CollectionPage',
      'name' => $page_title,
      'description' => $meta_desc,
      'url' => $canonical_url,
      'publisher' => [
        '@type' => 'Organization',
        'name' => $site_title,
        'url' => getSiteUrl()
      ]
    ];

    $extra_head = <<<HTML
      <script>
        document.addEventListener("DOMContentLoaded", function() {
          if (sessionStorage.getItem('returnFocusToSearch') === 'true') {
            const searchBox = document.getElementById('searchBox');
            if (searchBox) {
              searchBox.focus();
              const len = searchBox.value.length;
              searchBox.setSelectionRange(len, len);
            }
            sessionStorage.removeItem('returnFocusToSearch');
          }
        });

        let searchTimeout;
        function updateFilters(triggeredBySearch = false) {
          const searchValue = document.getElementById('searchBox').value;
          const favouriteCheckbox = document.getElementById('favouriteToggle');
          const url = new URL(window.location.href);
          if (searchValue) {
            url.searchParams.set('q', searchValue);
          } else {
            url.searchParams.delete('q');
          }
          if (favouriteCheckbox && favouriteCheckbox.checked) {
            url.searchParams.set('favourite', 'yes');
          } else {
            url.searchParams.delete('favourite');
          }
          if (triggeredBySearch) {
            sessionStorage.setItem('returnFocusToSearch', 'true');
          }
          window.location.href = url.toString();
        }

        function triggerSearch() {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => updateFilters(true), 600);
        }
      </script>
    HTML;

    require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3 style="margin-bottom:10px; margin-top:0;">Browse all tasting notes</h3>

      <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 5px;">
          <label for="searchBox" style="font-size: small;">Search:</label>
          <input type="text" id="searchBox" onkeyup="triggerSearch()"
            value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') : ''; ?>"
            placeholder="e.g. 2015 Bordeaux"
            style="font-family: Georgia, serif; padding: 2px; width: 200px; max-width: 100%;">
        </div>
        <div style="display: flex; align-items: center; gap: 5px;">
          <input type="checkbox" id="favouriteToggle" onchange="updateFilters()"
            <?php echo (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') ? 'checked' : ''; ?>
            style="cursor: pointer; vertical-align: middle;">
          <label for="favouriteToggle" style="font-size: small; cursor: pointer; user-select: none;">Only show Favourites</label>
        </div>
      </div>

      <p style="margin-top:0;"><small>
        Sort by:
        <?php 
          $urlParams = !empty($_GET['q']) ? "&q=" . urlencode($_GET['q']) : ""; 
          if (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') {
            $urlParams .= "&favourite=yes";
          }
        ?>
        <a class="filter-nav" href="/tnotes.php?sort=date<?php echo $urlParams; ?>">Tasting date</a>
        <a class="filter-nav" href="/tnotes.php?sort=rating<?php echo $urlParams; ?>">DM points</a>
        <a class="filter-nav" href="/tnotes.php?sort=region<?php echo $urlParams; ?>">Region</a>
        <a class="filter-nav" href="/tnotes.php?sort=producer<?php echo $urlParams; ?>">Producer</a>
        <a class="filter-nav" href="/tnotes.php?sort=vintage<?php echo $urlParams; ?>">Vintage</a>
        <a class="filter-nav" href="/tnotes.php?sort=variety<?php echo $urlParams; ?>">Variety</a>
        <a class="filter-nav" href="/tnotes.php?sort=tenyears<?php echo $urlParams; ?>">Aged 10 years</a>
        <a class="filter-nav" href="/tnotes.php?sort=twentyplus<?php echo $urlParams; ?>">Aged 20+ years</a>
        <a class="filter-nav" href="/tnotes.php"><b>Reset</b></a>
      </small></p>
    </div>

    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php getNotes(1000,$sort,$sqlOrderBy); ?>
      </ul>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <aside>
        <?php 
          $active_scale = getRatingScale();
          $default_sidebar_html = '<p>This is a list of all the tasting notes I have published. Keep in mind that this is not my main job, but a hobby. However, I usually manage to write at least one new note per week.</p>';
          if ($active_scale === '100-point') {
            $default_sidebar_html .= '<h4>How I rate wines</h4><p>I rate wines on a <strong>100-point scale</strong>. On this scale, I rate the <em>absolute</em> quality of wines:</p><table><tr><td style="width:70px">98-100</td><td>extraordinary / classic</td></tr><tr><td>95-97</td><td>extraordinary</td></tr><tr><td>90-94</td><td>outstanding</td></tr><tr><td>85-89</td><td>very good</td></tr><tr><td>80-84</td><td>good</td></tr><tr><td>75-79</td><td>acceptable / mediocre</td></tr><tr><td>70-74</td><td>below average</td></tr><tr><td>&lt; 70</td><td>poor / faulty</td></tr></table><p><a href="/blog.php?id=26" title="How I rate wines">Find out more about how I rate wines.</a></p>';
          } else {
            $default_sidebar_html .= '<h4>How I rate wines</h4><p>I rate wines on my personal <strong>20-point DM scale</strong>. On this scale, I rate the <em>absolute</em> quality of wines. My ratings may appear low relative to those of most popular wine reviewers at first glance, but I make use of the entire range from 0 to 20 points:</p><table><tr><td style="width:70px">20</td><td>one-of-a-kind</td></tr><tr><td>17-19</td><td>grand vin</td></tr><tr><td>13-16</td><td>excellent</td></tr><tr><td>9-12</td><td>very good</td></tr><tr><td>5-8</td><td>good</td></tr><tr><td>3-4</td><td>passable</td></tr><tr><td>1-2</td><td>subpar</td></tr><tr><td>0</td><td>poor</td></tr></table><p>As you can see, &quot;good&quot; wines start at 5 points already!</p><p><a href="/blog.php?id=26" title="How I rate wines">Find out more about how I rate wines.</a></p>';
          }
          echo getStaticPageContent('tnotes_sidebar', $default_sidebar_html);
        ?>
      </aside>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<?php
  }
?>

<?php function getNote($noteID)
{
  global $mysqli, $conn;

  // Perform query
  $stmt = $mysqli->prepare("select tnotes.*, users.initials, users.displayname,
                                   wines.vintage, wines.master_id,
                                   wines_master.*,
                                   producers.producer, producers.producer_desc,
                                   vineyards.vineyard,
                                   regions.region, regions.country,
                                   subregions.subregion,
                                   appellations.appellation,
                                   variety.grape_desc,
                                   scale_20.pts_desc as scale_20_desc, scale_20.pts_class as scale_20_class,
                                   scale_100.pts_desc as scale_100_desc, scale_100.pts_class as scale_100_class,
                                   wsetpts.wset_desc,
                                   xvr.vintage_desc
                            from tnotes
                            left join users on tnotes.user_id=users.user_id
                            left join wines on tnotes.wine_id=wines.wine_id
                            left join wines_master on wines.master_id=wines_master.master_id
                            left join producers on wines_master.producer_id=producers.producer_id
                            left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                            left join regions on wines_master.region_id=regions.region_id
                            left join countries on regions.country=countries.country
                            left join subregions on wines_master.subregion_id=subregions.subregion_id
                            left join appellations on wines_master.appellation_id=appellations.appellation_id
                            left join variety on wines_master.grape=variety.grape
                            left join scale_20 on (tnotes.pts_20=scale_20.pts or (tnotes.pts_20 is null and tnotes.dmpts=scale_20.pts))
                            left join scale_100 on (tnotes.pts_100 >= scale_100.min_pts and tnotes.pts_100 <= scale_100.max_pts)
                            left join wsetpts on tnotes.wsetpts=wsetpts.pts
                            left join (select vintage as vint, region_id as rvid, vintage_desc from x_vintage_region) xvr on wines.vintage=xvr.vint and wines_master.region_id=xvr.rvid
                            where note_id=?");
  $stmt->bind_param("i", $noteID);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result)
  {
    $GLOBALS['tasting_note'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $stmt->close();
} ?>

<?php function moreNotes($id, $wine_id, $wine_name)
{
  global $mysqli, $conn;
  $scale = getRatingScale();
  // Perform query
  $stmt = $mysqli->prepare("select tnotes.*, users.initials from tnotes
                              left join users on tnotes.user_id=users.user_id
                              where tnotes.status='published' and tnotes.note_id<>? and tnotes.wine_id=?
                              order by tnotes.tasting_date desc");
  $stmt->bind_param("ii", $id, $wine_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if (mysqli_num_rows($result)!=0) {
    echo "<div class='card'><h3>More tasting notes on this wine:</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($moreNotes = $result->fetch_assoc()) {
      $rating_badge = formatNoteRatingBadge($moreNotes, $scale, true);
      echo "<li>".date_format(date_create($moreNotes["tasting_date"]),"d M Y").": <a href='/tnotes.php?id=".$moreNotes["note_id"]."'>".$wine_name."</a> (".$rating_badge.")</li>";
    }
    echo "</ul></p></div>";
  }
  $stmt->close();
  $result -> free_result();
} ?>

<?php function otherVintages($wine_id, $master_id)
{
  global $mysqli, $conn;
  $scale = getRatingScale();
  // Perform query
  $stmt = $mysqli->prepare("select wines.*, wines_master.*, tnotes.*, producers.*, vineyards.*, users.initials from wines
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join tnotes on wines.wine_id=tnotes.wine_id
                                left join users on tnotes.user_id=users.user_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                              where tnotes.status='published' and wines.wine_id<>? and wines.master_id=?
                              order by tnotes.tasting_date desc");
  $stmt->bind_param("ii", $wine_id, $master_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if (mysqli_num_rows($result)!=0) {
    echo "<div class='card'><h3>Tasting notes on other vintages of this wine:</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($otherVintages = $result->fetch_assoc()) {
      $otherWine = getWineName($otherVintages["nameconvention"], $otherVintages["vintage"], $otherVintages["name"], $otherVintages["producer"], $otherVintages["grape"], $otherVintages["vineyard"]);
      $rating_badge = formatNoteRatingBadge($otherVintages, $scale, true);
      if ($otherVintages["tasting_date"]!=null) {
        echo "<li>".date_format(date_create($otherVintages["tasting_date"]),"d M Y").": <a href='/tnotes.php?id=".$otherVintages["note_id"]."'>".$otherWine."</a> (".$rating_badge.")</li>";
      }
    }
    echo "</ul></p></div>";
  }
  $stmt->close();
  $result -> free_result();
} ?>

<?php function getNotes($num,$sort,$sqlOrderBy)
{
  global $mysqli, $conn;
  $prevYear="";
  $prevRating="";

  // Base WHERE condition
  $sqlWhere = " WHERE tnotes.status='published' ";

  // Favourites filter
  if (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') {
    $sqlWhere .= " AND tnotes.favourite = 'yes' ";
  }

  // Ten-year-old wines
  if ($sort == "tenyears") {
    $sqlWhere .= " AND wines.vintage is not null and year(tnotes.tasting_date) - wines.vintage = 10 ";
  } elseif ($sort == "twentyplus") {
    $sqlWhere .= " AND wines.vintage is not null and year(tnotes.tasting_date) - wines.vintage >= 20 ";
  }

  // Fuzzy search constraint
  if (!empty($_GET['q'])) {
    $q = $mysqli->real_escape_string($_GET['q']);
    $words = explode(' ', $q);
    $conditions = [];
    foreach($words as $word) {
      $word = trim($word);
      if (!empty($word)) {
        $conditions[] = "(
          producers.producer LIKE '%$word%' OR
          wines_master.name LIKE '%$word%' OR
          regions.region LIKE '%$word%' OR
          regions.country LIKE '%$word%' OR
          appellations.appellation LIKE '%$word%' OR
          v.grape_desc LIKE '%$word%' OR
          wines_master.grape LIKE '%$word%' OR
          wines.vintage LIKE '%$word%' OR
          vineyards.vineyard LIKE '%$word%' OR
          wines_master.style LIKE '%$word%'
        )";
      }
    }
    if (!empty($conditions)) {
      $sqlWhere .= " AND " . implode(' AND ', $conditions) . " ";
    }
  }

  // Perform query
  $result = $mysqli -> query("select tnotes.*, users.initials, users.displayname,
                                     wines.vintage, wines.master_id,
                                     wines_master.*,
                                     producers.producer, producers.producer_desc,
                                     vineyards.vineyard,
                                     regions.region, regions.country,
                                     subregions.subregion,
                                     appellations.appellation,
                                     v.grape_desc,
                                     s20.pts_desc as scale_20_desc, s20.pts_class as scale_20_class,
                                     s100.pts_desc as scale_100_desc, s100.pts_class as scale_100_class,
                                     c.comment_count
                              from tnotes
                              left join users on tnotes.user_id=users.user_id
                              left join wines on tnotes.wine_id=wines.wine_id
                              left join wines_master on wines.master_id=wines_master.master_id
                              left join producers on wines_master.producer_id=producers.producer_id
                              left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                              left join regions on wines_master.region_id=regions.region_id
                              left join subregions on wines_master.subregion_id=subregions.subregion_id
                              left join appellations on wines_master.appellation_id=appellations.appellation_id
                              left join (select grape as vgrape, grape_desc from variety) v on wines_master.grape=v.vgrape
                              left join scale_20 s20 on (tnotes.pts_20=s20.pts or (tnotes.pts_20 is null and tnotes.dmpts=s20.pts))
                              left join scale_100 s100 on (tnotes.pts_100 >= s100.min_pts and tnotes.pts_100 <= s100.max_pts)
                              left join (select note_id as c_note_id, count(comment_id) as comment_count from x_comments_tnotes group by note_id) c on tnotes.note_id=c.c_note_id
                              " . $sqlWhere . " " . $sqlOrderBy . " limit 0," . $num);

  // Display message if no results found
  if ($result->num_rows == 0) {
    $feedbackMsg = !empty($_GET['q'])
        ? "your search for '<strong>" . htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') . "</strong>'"
        : "your current filter selection";

    echo "<div class='card' style='text-align:center; padding:30px 20px;'>
      <p>I'm afraid no tasting notes currently match {$feedbackMsg}.</p>
      <p><small>Try checking your spelling or using fewer keywords.</small></p>
    </div>";
 
    return; // Exit the function early
  }

  $active_scale = getRatingScale();

  if ($sort=="date") {
    echo "<p><small><i>Tasting notes arranged chronologically by tasting date, grouped by year.</i></small></p>";
  } elseif ($sort=="rating") {
    if ($active_scale === '100-point') {
      echo "<p><small><i>Tasting notes arranged by score (100-point scale), then by producer and vintage.</i></small></p>";
    } else {
      echo "<p><small><i>Tasting notes arranged by DM score (20-point scale), then by producer and vintage.</i></small></p>";
    }
  } elseif ($sort=="region") {
    echo "<p><small><i>Tasting notes arranged by country and region, then by producer, cuvée, and vintage.</i></small></p>";
  } elseif ($sort=="producer") {
    echo "<p><small><i>Tasting notes organised by producer, then by cuvée and vintage.</i></small></p>";
  } elseif ($sort=="vintage") {
    echo "<p><small><i>Tasting notes arranged by vintage, then by country, producer, and cuvée.</i></small></p>";
  } elseif ($sort=="variety") {
    echo "<p><small><i>Tasting notes grouped by grape variety, then by country, producer, and cuvée. For <em>assemblages</em>, only the primary grape variety is shown.</i></small></p>";
  } elseif ($sort=="tenyears") {
    echo "<p><small><i>&quot;Ten years on&quot; tasting notes, arranged chronologically by tasting date.</i></small></p>";
  } elseif ($sort=="twentyplus") {
    echo "<p><small><i>Tasting notes for mature wines (20+ years), arranged chronologically by tasting date.</i></small></p>";
  }
  $rows = [];
  while ($r = $result->fetch_assoc()) {
    if ($r["vintage"] === null) {
      $r["vintage"] = "NV";
    }

    if ($r['flawed_yn'] == "yes") {
      $r['rating_label'] = "flawed";
      $r['rating_desc'] = "I mark bottles as flawed when there is a fault outside of the control of the winemaker. This might happen if a wine is corked or it is spoilt because of bad storage conditions. I don't rate these wines.";
    } elseif ($active_scale === '100-point' && $r['pts_100'] !== null) {
      $initials = !empty($r["initials"]) ? $r["initials"] : 'DM';
      $r['rating_label'] = $initials . $r["pts_100"];
      $r['rating_desc'] = $r['scale_100_desc'] ?? '';
    } elseif ($active_scale !== '100-point' && ($r['pts_20'] !== null || $r['dmpts'] !== null)) {
      $initials = !empty($r["initials"]) ? $r["initials"] : 'DM';
      $pts = $r['pts_20'] ?? $r['dmpts'];
      $r['rating_label'] = $initials . $pts;
      $r['rating_desc'] = $r['scale_20_desc'] ?? '';
    } else {
      $r['rating_label'] = "NR";
      $r['rating_desc'] = "Sometimes I don't give a rating to a wine. In that case you'll read &quot;not rated&quot; or the abbreviation &quot;NR&quot; in the tasting note. I might not give a rating if I tasted a wine only quickly without taking notes - at a winery, for example. Sometimes I might still want to note down an indication for a possible rating. In that case, I'll write a provisional score in the tasting note, but I'll always make that clear in the written note.";
    }

    $rows[] = $r;
  }
  $result->free_result();

  // Group consecutive rows with the same wine_id within the active hierarchy section
  $grouped_notes = [];
  $current_group = null;
  $current_section_key = null;

  foreach ($rows as $row) {
    if ($sort == "date" || $sort == "twentyplus") {
      $section_key = date_format(date_create($row["tasting_date"]), "Y");
    } elseif ($sort == "tenyears") {
      $section_key = $row["vintage"] . "-" . date_format(date_create($row["tasting_date"]), "y");
    } elseif ($sort == "rating") {
      $section_key = $row["rating_label"];
    } elseif ($sort == "region") {
      $section_key = ($row['country'] ?? '') . '|||' . ($row['region'] ?? '');
    } elseif ($sort == "producer") {
      $section_key = $row['producer'] ?? '';
    } elseif ($sort == "vintage") {
      $section_key = $row['vintage'] ?? '';
    } elseif ($sort == "variety") {
      $section_key = $row['grape'] ?? '';
    } else {
      $section_key = '';
    }

    $wine_id = $row['wine_id'] ?? $row['note_id'];

    if (
      $current_group !== null &&
      $current_section_key === $section_key &&
      $current_group['wine_id'] === $wine_id
    ) {
      $current_group['notes'][] = $row;
    } else {
      if ($current_group !== null) {
        $grouped_notes[] = $current_group;
      }
      $current_section_key = $section_key;
      $current_group = [
        'section_key' => $section_key,
        'wine_id' => $wine_id,
        'row' => $row,
        'notes' => [$row]
      ];
    }
  }
  if ($current_group !== null) {
    $grouped_notes[] = $current_group;
  }

  $prevYear = "";
  $prevTenYears = "";
  $prevRating = "";
  $prevCountry = "";
  $prevRegion = "";
  $prevProducer = "";
  $prevVintage = "";
  $prevVariety = "";

  foreach ($grouped_notes as $group) {
    $wine = $group['row'];

    // Output hierarchy headers
    if ($sort == "date" || $sort == "twentyplus") {
      $curYear = date_format(date_create($wine["tasting_date"]), "Y");
      if ($curYear != $prevYear) {
        echo ($prevYear != "") ? "</ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($curYear, ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "tenyears") {
      $curTenYears = $wine["vintage"] . "-" . date_format(date_create($wine["tasting_date"]), "y");
      if ($curTenYears != $prevTenYears) {
        echo ($prevTenYears != "") ? "</ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($curTenYears, ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "rating") {
      if ($wine["rating_label"] != $prevRating) {
        echo ($prevRating != "") ? "</ul></details><br>" : "";
        if ($wine["rating_label"] != "flawed" && $wine["rating_label"] != "NR") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["rating_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["rating_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } elseif ($wine["rating_label"] == "NR") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["rating_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>Sometimes I don't give a rating to a wine. In that case you'll read &quot;not rated&quot; or the abbreviation &quot;NR&quot; in the tasting note. I might not give a rating if I tasted a wine only quickly without taking notes - at a winery, for example. Sometimes I might still want to note down an indication for a possible rating. In that case, I'll write a provisional score in the tasting note, but I'll always make that clear in the written note.</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } elseif ($wine["rating_label"] == "flawed") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["rating_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>I mark bottles as flawed when there is a fault outside of the control of the winemaker. This might happen if a wine is corked or it is spoilt because of bad storage conditions. I don't rate these wines.</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
      }
    } elseif ($sort == "region") {
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevRegion = "";
      }
      if ($wine["region"] != $prevRegion) {
        echo ($prevRegion != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details open><summary><i>" . htmlspecialchars($wine["region"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "producer") {
      if ($wine["producer"] != $prevProducer) {
        echo ($prevProducer != "") ? "</ul></details><br>" : "";
        if (!empty($wine["producer_desc"])) {
          echo "<details open><summary><b>" . htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["producer_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } else {
          echo "<details open><summary><b>" . htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
      }
    } elseif ($sort == "vintage") {
      if ($wine["vintage"] != $prevVintage) {
        echo ($prevVintage != "") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($wine["vintage"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevCountry = "";
      }
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details open><summary><i>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "variety") {
      if ($wine["grape"] != $prevVariety) {
        echo ($prevVariety != "") ? "</ul></details></li></ul></details><br>" : "";
        if (!empty($wine["grape_desc"])) {
          echo "<details open><summary><b>" . htmlspecialchars($wine["grape"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["grape_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details open><summary><b>" . htmlspecialchars($wine["grape"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
        $prevCountry = "";
      }
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details open><summary><i>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    }

    $wine_name = getWineName($wine["nameconvention"], $wine["vintage"], $wine["name"], $wine["producer"], $wine["grape"], $wine["vineyard"]);
    $item_padding = ($sort == "region" || $sort == "vintage" || $sort == "variety") ? "padding-left:43px;text-indent:-18px;" : "padding-left:35px;text-indent:-18px;";

    echo "<li class='wine-item' style='{$item_padding}margin-bottom:8px;'>";
    echo "<span class='wine-title'>" . htmlspecialchars($wine_name, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "<div class='vintage-chip-group'>";

    foreach ($group['notes'] as $n) {
      $t_date = date_format(date_create($n["tasting_date"]), "d M Y");
      $rating_label = $n['rating_label'];
      $comment_count = (int)($n['comment_count'] ?? 0);
      $is_fav = (isset($n['favourite']) && $n['favourite'] == 'yes');

      echo "<div class='vintage-chip rating-chip'>";
      echo "<a class='chip-link' href='/tnotes.php?id=" . $n["note_id"] . "' title='Tasted on " . $t_date . "'>";
      if ($is_fav) {
        echo "<span class='chip-fav'>❤️</span>";
      }
      echo "<span class='chip-rating'>" . htmlspecialchars($rating_label, ENT_QUOTES, 'UTF-8') . "</span>";
      echo "<span class='chip-sep'>·</span>";
      echo "<span class='chip-date'>" . htmlspecialchars($t_date, ENT_QUOTES, 'UTF-8') . "</span>";
      echo "</a>";

      if ($comment_count > 0) {
        $c_title = $comment_count === 1 ? '1 comment' : $comment_count . ' comments';
        echo "<a class='chip-badge' href='/tnotes.php?id=" . $n["note_id"] . "#comments' title='" . $c_title . "' aria-label='" . $c_title . "'>";
        echo '<svg class="comment-icon" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
        echo "<span class='comment-count'>" . $comment_count . "</span>";
        echo "</a>";
      }

      echo "</div>";
    }

    echo "</div>";
    echo "</li>";

    // Set previous values
    $prevYear = date_format(date_create($wine["tasting_date"]), "Y");
    $prevTenYears = $wine["vintage"] . "-" . date_format(date_create($wine["tasting_date"]), "y");
    $prevRating = $wine["rating_label"];
    $prevCountry = $wine["country"];
    $prevRegion = $wine["region"];
    $prevProducer = $wine["producer"];
    $prevVintage = $wine["vintage"];
    $prevVariety = $wine["grape"];
  }

  if ($sort == "region" || $sort == "vintage" || $sort == "variety") {
    echo "</ul></details></li></ul></details>";
  } elseif ($sort == "date" || $sort == "twentyplus" || $sort == "tenyears" || $sort == "rating" || $sort == "producer") {
    echo "</ul></details>";
  }
} ?>
