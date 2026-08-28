<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  $is_single = isset($_GET['id']) && trim($_GET['id']) !== '';

  if ($is_single) {
    // Generate the token for the comments form
    $csrf_token = generateCSRFToken();

    // Get wine ID
    $wineID = $_GET['id'];

    // Include the database configuration file
    global $mysqli, $conn;

    // Fetch the wine and ensure it exists before proceeding
    getWine($wineID);
    if (empty($wine)) {
      header("Location: /wines.php");
      exit;
    }

    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
      $user_id = $_SESSION['user_id'];
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
      } elseif (!isset($_SESSION['user_id'])) {
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
          $post = $mysqli->prepare("INSERT INTO x_comments_wines (comment_id, wine_id) VALUES (?, ?)");
          $post->bind_param('ii', $last_id, $wineID);
          if ($post->execute()) {
            $success = "Thank you! Comment posted successfully.";
            $mysqli->commit();
            $mysqli->autocommit(TRUE);
            
            // Auto-subscribe the commenter and notify existing subscribers
            autoSubscribe($mysqli, $user_id, $wineID, 'wine');
            createNotificationsForComment($mysqli, $user_id, $wineID, 'wine', $last_id);
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
      }
    }

    // Vintage NV?
    if ($wine["vintage"]==null) { $wine["vintage"]="NV"; }
    // Get wine name
    if ($wine["nameconvention"]=="vintage_name") {
      $wine_name=$wine["vintage"]." ".$wine["name"];
    } elseif ($wine["nameconvention"]=="vintage_producer") {
      $wine_name=$wine["vintage"]." ".$wine["producer"];
    } elseif ($wine["nameconvention"]=="vintage_producer_grape_name") {
      $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["grape"]." ".$wine["name"];
    } elseif ($wine["nameconvention"]=="vintage_producer_vineyard_grape_name") {
      $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["vineyard"]." ".$wine["grape"]." ".$wine["name"];
    } elseif ($wine["nameconvention"]=="vintage_producer_vineyard_name") {
      $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["vineyard"]." ".$wine["name"];
    // ...else vintage_producer_name as default:
    } else {
      $wine_name=$wine["vintage"]." ".$wine["producer"]." ".$wine["name"];
    }

    // Page header & SEO metadata
    $page_title = getSiteTitle() . " - " . $wine_name;
    $meta_keywords = generateWineKeywords($wine);
    $meta_desc = generateWineDescription($wine, $wine_name);
    $canonical_url = getAbsoluteUrl('/wines.php?id=' . $wineID);
    $og_type = 'product';
    $json_ld = generateWineJsonLd($wine, $wine_name, $canonical_url);

    require_once 'includes/header.php';
    $has_contribution_rights = hasPrivilege($conn, 'add_tasting_note');
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3><?php echo $wine_name;?></h3>
    </div>
    <div class="card">
      <section>
        <h3>Description</h3>
        <p>
          <?php if ($wine["wine_desc"]==null) {
            echo "Sorry, no description on this wine yet.";
          } else {
            echo $wine["wine_desc"];
          } ?>
        </p>
      </section>
    </div>
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h3 style="margin: 0;">Tasting notes on this wine</h3>
        <?php if ($has_contribution_rights): ?>
          <a class="filter-nav" href="/backend/addTastingNote.php?wine_id=<?php echo $wine['wine_id']; ?>" title="Add a tasting note" style="margin: 0;">+ Add tasting note</a>
        <?php endif; ?>
      </div>
      <p><ul style="list-style-type:none;padding:0;margin:0;">
        <?php
          if (hasPrivilege($conn, 'view_tnotes')) {
            latestNotes($wine["wine_id"]);
          } else {
            echo "<p>Please <a href='/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']) . "'>log in</a> to see tasting notes.</p>";
          }
        ?>
      </ul></p>
    </div>
    <?php renderBlogReferences($conn, $wineID, 'wine', 'Appears in these blog stories:'); ?>
    <?php
      if ($wine["region_desc"]!=null) {
        echo "<div class='card'><section><h3>About ".$wine["region"]."</h3>".$wine["region_desc"]."</section></div>";
      }
    ?>
    <?php
      if ($wine["vintage_desc"]!=null) {
        echo "<div class='card'><section><h3>The ".$wine["vintage"]." vintage in ".$wine["region"]."</h3>".$wine["vintage_desc"]."</section></div>";
      }
    ?>
    <?php
      if ($wine["grape_desc"]!=null) {
        echo "<div class='card'><section><h3>The ".$wine["grape"]." grape variety</h3>".$wine["grape_desc"]."</section></div>";
      }
    ?>
  </div>
  <div class="column side">
    <div class="card">
    <h3>Wine details</h3>
    <p>
      <table>
        <tr>
          <td>Vintage:</td>
          <td>
            <?php
              if ($wine["vintage_desc"]==null) {
                echo $wine["vintage"];
              } else {
                echo "<div class='tooltip'>".$wine["vintage"]."<span class='tooltiptext'>".$wine["vintage_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Colour:</td><td><?php echo $wine["colour"];?></td></tr>
        <tr><td>Style:</td><td><?php echo $wine["style"];?></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Assemblage:</td><td><?php echo $wine["cuvee_yn"];?></td></tr>
        <tr>
          <td>Grape variety:</td>
          <td>
            <?php
              if ($wine["grape_desc"]==null) {
                echo $wine["grape"];
              } else {
                echo "<div class='tooltip'>".$wine["grape"]."<span class='tooltiptext'>".$wine["grape_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr><td colspan="2"><font style="font-size:12px;">For <i>assemblages</i>, the main grape variety is shown.</font></td></tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td>Producer:</td><td><?php echo "<a href='/producers.php?id=".$wine["producer_id"]."'>".$wine["producer"]."</a>";?></td></tr>
        <tr><td>Country:</td><td><?php echo $wine["country"];?></td></tr>
        <tr><td>Region:</td><td><?php echo $wine["region"];?></td></tr>
        <tr><td>Subregion:</td><td><?php if ($wine["subregion"]==null){echo "n/a";}else{echo $wine["subregion"];}?></td></tr>
        <tr>
          <td>Appellation:</td>
          <td>
            <?php
              if ($wine["appellation"]==null) {
                echo "n/a";
              } elseif ($wine["appellation_desc"]==null) {
                echo $wine["appellation"];
              } else {
                echo "<div class='tooltip'>".$wine["appellation"]."<span class='tooltiptext'>".$wine["appellation_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr>
          <td>Vineyard:</td>
          <td>
            <?php
              if ($wine["vineyard"]==null) {
                echo "n/a";
              } elseif ($wine["vineyard_desc"]==null) {
                echo $wine["vineyard"];
              } else {
                echo "<div class='tooltip'>".$wine["vineyard"]."<span class='tooltiptext'>".$wine["vineyard_desc"]."</span></div>";
              }
            ?>
          </td>
        </tr>
        <tr style="height:10px;"><td></td></tr>
        <tr><td colspan="2"><?php echo ($wine["ct_id"]!==null) ? "<a href='https://www.cellartracker.com/wine.asp?iWine=".$wine["ct_id"]."' target='_blank' rel='noopener noreferrer'>View this wine on CellarTracker.</a>" : "";?></td></tr>
        <tr><td colspan="2"><a href="<?php echo getWineSearcherUrl($wine_name); ?>" target="_blank" rel="noopener noreferrer">Find this wine on Wine-Searcher.</a></td></tr>
      </table>
    </p>
    </div>
    <?php
      if ($wine["producer_desc"]!=null) {
        echo "<div class='card'><aside><h3>About ".$wine["producer"]."</h3>".$wine["producer_desc"]."</aside></div>";
      }
      if ($wine["appellation_desc"]!=null) {
        echo "<div class='card'><aside><h3>The appellation: ".$wine["appellation"]."</h3>".$wine["appellation_desc"]."</aside></div>";
      }
      if ($wine["vintage"]!="NV") {
        otherVintages($wineID, $wine["master_id"]);
      }
    ?>
    <?php if (hasPrivilege($conn, 'view_comments')): ?>
      <?php
        $is_subbed = isset($_SESSION['user_id']) ? isSubscribed($conn, $_SESSION['user_id'], $wineID, 'wine') : false;
      ?>
      <div class="card" id="comments" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div><h3 style="margin:0;">Discussion</h3></div>
        <?php if (isset($_SESSION['user_id'])): ?>
          <div class="subscription-container" style="margin:0;">
            <?php if ($is_subbed): ?>
              <button id="btn-sub-toggle" class="btn-subscription subscribed" onclick="toggleSubscriptionAjax(<?= $wineID ?>, 'wine')">🔕 Unsubscribe</button>
            <?php else: ?>
              <button id="btn-sub-toggle" class="btn-subscription" onclick="toggleSubscriptionAjax(<?= $wineID ?>, 'wine')">🔔 Subscribe to discussion</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (hasPrivilege($conn, 'post_comments')): ?>
        <div class="card"><details><summary><h3 style="display:inline;margin:0;">Post a comment</h3></summary>
        <?php
          if ($error!="") {
            echo "<div style='color:red;'>$error</div>";
          } elseif ($success!="") {
            echo "<div style='color:green;'>$success</div>";
          }
        ?>
        <form method="post" autocomplete="off" accept-charset="UTF-8" style="margin-bottom:10px;">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
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

      <?php renderComments($conn, $wineID, 'wine'); ?>

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
    // Browse all wines list view
    if (empty($_GET['sort'])) {
      $sort="region";
    } else {
      $sort=$_GET['sort'];
    }

    if ($sort=="region" || $sort=="tenyearsold" || $sort=="twentyplus") {
      $sqlOrderBy="order by country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc";
    } elseif ($sort=="producer") {
      $sqlOrderBy="order by producer asc,region asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc";
    } elseif ($sort=="vintage") {
      $sqlOrderBy="order by vintage desc,country asc,producer asc,region asc,subregion asc,appellation asc,vineyard asc,name asc";
    } elseif ($sort=="variety") {
      $sqlOrderBy="order by grape asc,country asc,producer asc,region asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc";
    }

    $search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $site_title = getSiteTitle();
    $owner_name = getOwnerName();
    if (!empty($search_query)) {
      $page_title = $site_title . ' - Wine Database: Search "' . htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') . '"';
      $meta_desc = 'Search results for "' . htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') . '" in ' . $site_title . ' fine wine database.';
      $meta_keywords = buildKeywordsList([$search_query, 'wine database', 'fine wine', 'wine tasting notes', 'wine search', $owner_name]);
    } else {
      $sortLabels = [
        'region' => 'by Region',
        'producer' => 'by Producer',
        'vintage' => 'by Vintage',
        'variety' => 'by Grape Variety',
        'tenyearsold' => 'Aged 10 Years',
        'twentyplus' => 'Aged 20+ Years'
      ];
      $sortSuffix = isset($sortLabels[$sort]) ? ' (' . $sortLabels[$sort] . ')' : '';
      $page_title = $site_title . ' - Browse all wines' . $sortSuffix;
      $meta_desc = 'Explore ' . $site_title . ' fine wine database. Browse wines by region, producer, vintage, and grape variety with tasting notes and vintage details.';
      $meta_keywords = $owner_name . ', ' . $site_title . ', wine database, fine wine, browse wines, wine collection, wine tastings, wine producers, wine vintages, wine regions';
    }
    $canonical_url = getAbsoluteUrl('/wines.php');
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
          const url = new URL(window.location.href);
          if (searchValue) {
            url.searchParams.set('q', searchValue);
          } else {
            url.searchParams.delete('q');
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
      <h3 style="margin-bottom:10px; margin-top:0;">Browse all wines</h3>

      <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 15px;">
        <label for="searchBox" style="font-size: small;">Search:</label>
        <input type="text" id="searchBox" onkeyup="triggerSearch()"
          value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') : ''; ?>"
          placeholder="e.g. 2015 Bordeaux"
          style="font-family: Georgia, serif; padding: 2px; width: 200px; max-width: 100%;">
      </div>

      <p style="margin-top:0;"><small>
        Sort by:
        <?php $urlParams = !empty($_GET['q']) ? "&q=" . urlencode($_GET['q']) : ""; ?>
        <a class="filter-nav" href="/wines.php?sort=region<?php echo $urlParams; ?>">Region</a>
        <a class="filter-nav" href="/wines.php?sort=producer<?php echo $urlParams; ?>">Producer</a>
        <a class="filter-nav" href="/wines.php?sort=vintage<?php echo $urlParams; ?>">Vintage</a>
        <a class="filter-nav" href="/wines.php?sort=variety<?php echo $urlParams; ?>">Variety</a>
        <a class="filter-nav" href="/wines.php?sort=tenyearsold<?php echo $urlParams; ?>">Aged 10 years</a>
        <a class="filter-nav" href="/wines.php?sort=twentyplus<?php echo $urlParams; ?>">Aged 20+ years</a>
        <a class="filter-nav" href="/wines.php"><b>Reset</b></a>
      </small></p>
    </div>
    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php renderWines(1000,$sort,$sqlOrderBy); ?>
      </ul>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <aside>
        <p>This is not a list of my wine cellar. These are wines I've written about in a tasting note or in a story on my blog.</p>
      </aside>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<?php
  }
?>

<?php function getWine($wineID)
{
  global $mysqli, $conn;

  // Perform query
  $stmt = $mysqli->prepare("select * from wines
                            left join wines_master on wines.master_id=wines_master.master_id
                            left join producers on wines_master.producer_id=producers.producer_id
                            left join regions on wines_master.region_id=regions.region_id
                            left join subregions on wines_master.subregion_id=subregions.subregion_id
                            left join appellations on wines_master.appellation_id=appellations.appellation_id
                            left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                            left join variety on wines_master.grape=variety.grape
                            left join (select vintage as vint, region_id as rvid, vintage_desc from x_vintage_region) xvr on wines.vintage=xvr.vint and wines_master.region_id=xvr.rvid
                          where wine_id=?");
  $stmt->bind_param("i", $wineID);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result)
  {
    $GLOBALS['wine'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $stmt->close();
} ?>

<?php function latestNotes($id)
{
  global $mysqli, $conn;
    // Perform query
  $stmt = $mysqli->prepare("select * from tnotes
                                left join users on tnotes.user_id=users.user_id
                                left join wines on tnotes.wine_id=wines.wine_id
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join regions on wines_master.region_id=regions.region_id
                                left join countries on regions.country=countries.country
                                left join subregions on wines_master.subregion_id=subregions.subregion_id
                                left join appellations on wines_master.appellation_id=appellations.appellation_id
                              where status='published' and wines.wine_id=?
                              order by tasting_date desc, note_id desc");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($tasting_note = $result->fetch_assoc()) {
    // DM points?
    if ($tasting_note['flawed_yn']=="yes") { $dmpts="flawed"; } elseif ($tasting_note['dmpts']!=null) { $initials = !empty($tasting_note['initials']) ? $tasting_note['initials'] : 'DM'; $dmpts = $initials . $tasting_note["dmpts"]; } else { $dmpts="NR"; }
    // Output
    echo "<li>Tasted by ".$tasting_note["displayname"]." on ".date_format(date_create($tasting_note["tasting_date"]),"d M Y").": <a href='/tnotes.php?id=".$tasting_note['note_id']."'>".$dmpts."</a></li>";
  }
  if (mysqli_num_rows($result)==0) { echo "<li>No tasting notes for this wine, yet.</li>"; }
  $stmt->close();
  $result -> free_result();
}
?>

<?php function otherVintages($id, $master_id)
{
  global $mysqli, $conn;
    // Perform query
  $stmt = $mysqli->prepare("select * from wines
                              left join wines_master on wines.master_id=wines_master.master_id
                            where wines.wine_id<>? and wines.master_id=?
                            order by wines.vintage desc");
  $stmt->bind_param("ii", $id, $master_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if (mysqli_num_rows($result)!=0) {
    $count=1;
    echo "<div class='card'><h3>Other vintages of this wine:</h3><p>";
    while ($otherWines = $result->fetch_assoc()) {
      if ($count>1) {
        echo ", ";
      }
      echo "<a href='/wines.php?id=".$otherWines["wine_id"]."'>".$otherWines["vintage"]."</a>";
      $count=$count+1;
    }
    echo "</p></div>";
  }
  $stmt->close();
  $result -> free_result();
} ?>

<?php function renderWines($num,$sort,$sqlOrderBy)
{
  global $mysqli, $conn;
  $has_contribution_rights = hasPrivilege($conn, 'add_tasting_note');

  // Base WHERE condition
  $sqlWhere = " WHERE 1=1 ";

  // Logic for ten-year-old and mature wines
  if ($sort == "tenyearsold") {
    $sqlWhere .= " AND wines.vintage is not null and year(curdate()) - wines.vintage = 10";
  } elseif ($sort == "twentyplus") {
    $sqlWhere .= " AND wines.vintage is not null and year(curdate()) - wines.vintage >= 20";
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
  $result = $mysqli -> query("select
                                wines.wine_id,
                                wines.vintage,
                                wines.wine_desc,
                                wines_master.master_id,
                                wines_master.name,
                                wines_master.nameconvention,
                                wines_master.producer_id,
                                wines_master.region_id,
                                wines_master.subregion_id,
                                wines_master.appellation_id,
                                wines_master.vineyard_id,
                                wines_master.grape,
                                wines_master.colour,
                                wines_master.style,
                                producers.producer,
                                producers.producer_desc,
                                vineyards.vineyard,
                                regions.region,
                                regions.country,
                                subregions.subregion,
                                appellations.appellation,
                                v.grape_desc,
                                coalesce(c.comment_count, 0) as comment_count
                              from wines
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join regions on wines_master.region_id=regions.region_id
                                left join subregions on wines_master.subregion_id=subregions.subregion_id
                                left join appellations on wines_master.appellation_id=appellations.appellation_id
                                left join (select grape as vgrape, grape_desc from variety) v on wines_master.grape=v.vgrape
                                left join (select wine_id as c_wine_id, count(comment_id) as comment_count from x_comments_wines group by wine_id) c on wines.wine_id=c.c_wine_id
                                " . $sqlWhere . " " . $sqlOrderBy . " limit 0," . $num);

  // Display message if no results found
  if (!$result || $result->num_rows == 0) {
    // Determine what to display based on whether they searched or just filtered
    $feedbackMsg = !empty($_GET['q']) 
        ? "your search for '<strong>" . htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') . "</strong>'"
        : "your current filter selection";

    echo "<div class='card' style='text-align:center; padding:30px 20px;'>
      <p>I'm afraid no wines currently match {$feedbackMsg}.</p>
      <p><small>Try adjusting your search terms or removing some filters to explore the database.</small></p>
    </div>";

    return; // Exit the function early so we don't print empty lists or sort text
  }

  if ($sort=="region") {
    echo "<p><small><i>Wines arranged by country and region, then by producer, cuvée, and vintage.</i></small></p>";
  } elseif ($sort=="producer") {
    echo "<p><small><i>Wines organised by producer, then by cuvée and vintage.</i></small></p>";
  } elseif ($sort=="vintage") {
    echo "<p><small><i>Wines arranged by vintage, then by country, producer, and cuvée.</i></small></p>";
  } elseif ($sort=="variety") {
    echo "<p><small><i>Wines grouped by grape variety, then by country, producer, cuvée, and vintage. For <em>assemblages</em>, only the primary grape variety is shown.</i></small></p>";
  } elseif ($sort=="tenyearsold") {
    echo "<p><small><i>A selection of ten-year-old wines, arranged by country and region, then by producer and cuvée.</i></small></p>";
  } elseif ($sort=="twentyplus") {
    echo "<p><small><i>A selection of mature wines (20+ years), arranged by country and region, then by producer, cuvée, and vintage.</i></small></p>";
  }

  // Fetch all rows and normalize non-vintage values
  $rows = [];
  while ($row = $result->fetch_assoc()) {
    if ($row["vintage"] === null) {
      $row["vintage"] = "NV";
    }
    $rows[] = $row;
  }
  $result->free_result();

  // Group consecutive rows with the same master_id within the active hierarchy section
  $grouped_wines = [];
  $current_group = null;
  $current_section_key = null;

  foreach ($rows as $row) {
    if ($sort == "region" || $sort == "tenyearsold" || $sort == "twentyplus") {
      $section_key = ($row['country'] ?? '') . '|||' . ($row['region'] ?? '');
    } elseif ($sort == "producer") {
      $section_key = $row['producer'] ?? '';
    } elseif ($sort == "vintage") {
      $section_key = ($row['vintage'] ?? '') . '|||' . ($row['country'] ?? '');
    } elseif ($sort == "variety") {
      $section_key = ($row['grape'] ?? '') . '|||' . ($row['country'] ?? '');
    } else {
      $section_key = '';
    }

    $master_id = $row['master_id'] ?? $row['wine_id'];

    if (
      $current_group !== null &&
      $current_section_key === $section_key &&
      $current_group['master_id'] === $master_id
    ) {
      $current_group['vintages'][] = $row;
    } else {
      if ($current_group !== null) {
        $grouped_wines[] = $current_group;
      }
      $current_section_key = $section_key;
      $current_group = [
        'section_key' => $section_key,
        'master_id' => $master_id,
        'row' => $row,
        'vintages' => [$row]
      ];
    }
  }
  if ($current_group !== null) {
    $grouped_wines[] = $current_group;
  }

  $prevCountry = "";
  $prevRegion = "";
  $prevProducer = "";
  $prevVintage = "";
  $prevVariety = "";

  foreach ($grouped_wines as $group) {
    $wine = $group['row'];

    // Hierarchy headers
    if ($sort == "region" || $sort == "tenyearsold" || $sort == "twentyplus") {
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details><summary><b>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevRegion = "";
      }
      if ($wine["region"] != $prevRegion) {
        echo ($prevRegion != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details><summary><i>" . htmlspecialchars($wine["region"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "producer") {
      if ($wine["producer"] != $prevProducer) {
        echo ($prevProducer != "") ? "</ul></details><br>" : "";
        if (!empty($wine["producer_desc"])) {
          echo "<details><summary><b>" . htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["producer_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details><summary><b>" . htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
      }
    } elseif ($sort == "vintage") {
      if ($wine["vintage"] != $prevVintage) {
        echo ($prevVintage != "") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details><summary><b>" . htmlspecialchars($wine["vintage"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevCountry = "";
      }
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details><summary><i>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "variety") {
      if ($wine["grape"] != $prevVariety) {
        echo ($prevVariety != "") ? "</ul></details></li></ul></details><br>" : "";
        if (!empty($wine["grape_desc"])) {
          echo "<details><summary><b>" . htmlspecialchars($wine["grape"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["grape_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details><summary><b>" . htmlspecialchars($wine["grape"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
        $prevCountry = "";
      }
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details><summary><i>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    }

    $item_padding = ($sort == "producer") ? "padding-left:35px;text-indent:-18px;" : "padding-left:43px;text-indent:-18px;";

    $first_v = $group['vintages'][0];
    $base_name = getMasterWineName($first_v["nameconvention"], $first_v["name"], $first_v["producer"], $first_v["grape"], $first_v["vineyard"]);

    echo "<li class='wine-item' style='{$item_padding}margin-bottom:8px;'>";
    echo "<span class='wine-title'>" . htmlspecialchars($base_name, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "<div class='vintage-chip-group'>";

    foreach ($group['vintages'] as $v) {
      $desc_title = !empty($v['wine_desc']) ? strip_tags($v['wine_desc']) : '';
      $comment_count = (int)($v['comment_count'] ?? 0);
      $v_display = htmlspecialchars($v['vintage'], ENT_QUOTES, 'UTF-8');

      echo "<div class='vintage-chip'>";
      echo "<a class='chip-link' href='/wines.php?id=" . $v["wine_id"] . "'" . (!empty($desc_title) ? " title='" . htmlspecialchars($desc_title, ENT_QUOTES, 'UTF-8') . "'" : "") . ">";
      echo "<span class='chip-year'>" . $v_display . "</span>";
      echo "</a>";

      if ($comment_count > 0) {
        $c_title = $comment_count === 1 ? '1 comment' : $comment_count . ' comments';
        echo "<a class='chip-badge' href='/wines.php?id=" . $v["wine_id"] . "#comments' title='" . $c_title . "' aria-label='" . $c_title . "'>";
        echo '<svg class="comment-icon" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
        echo "<span class='comment-count'>" . $comment_count . "</span>";
        echo "</a>";
      }

      if ($has_contribution_rights) {
        echo "<a class='chip-action' href='/backend/addTastingNote.php?wine_id=" . $v["wine_id"] . "' title='Add tasting note for " . $v_display . "'>+</a>";
      }

      echo "</div>";
    }

    echo "</div>";
    echo "</li>";

    // Set previous values
    $prevCountry = $wine["country"];
    $prevRegion = $wine["region"];
    $prevProducer = $wine["producer"];
    $prevVintage = $wine["vintage"];
    $prevVariety = $wine["grape"];
  }

  if ($sort == "region" || $sort == "tenyearsold" || $sort == "twentyplus" || $sort == "vintage" || $sort == "variety") {
    echo "</ul></details></li></ul></details>";
  } elseif ($sort == "producer") {
    echo "</ul></details>";
  }
} ?>
