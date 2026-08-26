<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Check if user has permission to view stories
  if (!hasPrivilege($conn, 'view_stories')) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
  }

  $is_single = isset($_GET['id']) && trim($_GET['id']) !== '';

  if ($is_single) {
    // Include the database configuration file
    global $mysqli, $conn;

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

    // Get blog ID
    $blogID = $_GET['id'];

    // Fetch the blog post and ensure it exists before proceeding
    getPost($blogID);
    if (empty($blogpost)) {
      header("Location: /blog.php");
      exit;
    }

    // Generate the token for the comments form
    $csrf_token = generateCSRFToken();

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
          $post = $mysqli->prepare("INSERT INTO x_comments_blogposts (comment_id, blog_id) VALUES (?, ?)");
          $post->bind_param('ii', $last_id, $blogID);
          if ($post->execute()) {
            $success = "Thank you! Comment posted successfully.";
            $mysqli->commit();
            $mysqli->autocommit(TRUE);
            
            // Auto-subscribe the commenter and notify existing subscribers
            autoSubscribe($mysqli, $user_id, $blogID, 'blog');
            createNotificationsForComment($mysqli, $user_id, $blogID, 'blog', $last_id);
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

    // Page title and header
    $page_title = "Dominik Mueller - " . $blogpost["title"] . "";
    require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3 style="margin-bottom:0;"><?php echo $blogpost["title"];?></h3>
      <p style="margin-top:0;">Posted on <?php echo date_format(date_create($blogpost["pub_date"]),"l, j F Y");?><?php if (!empty($blogpost['edit_date']) && strtotime($blogpost['edit_date']) > strtotime($blogpost['pub_date'])): ?> (Last edited: <?php echo date_format(date_create($blogpost['edit_date']),"l, j F Y");?>)<?php endif; ?></p>
    </div>
    <div class="card">
      <section>
	<?php echo $blogpost["content"];?>
      </section>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <h3>Wines from this post</h3>
      <ul style="list-style-type:disc;">
        <?php wines($blogID); ?>
      </ul>
    </div>
    <?php if (hasPrivilege($conn, 'view_comments')): ?>
      <?php
        $is_subbed = isset($_SESSION['user_id']) ? isSubscribed($conn, $_SESSION['user_id'], $blogID, 'blog') : false;
      ?>
      <div class="card" id="comments" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div><h3 style="margin:0;">Discussion</h3></div>
        <?php if (isset($_SESSION['user_id'])): ?>
          <div class="subscription-container" style="margin:0;">
            <?php if ($is_subbed): ?>
              <button id="btn-sub-toggle" class="btn-subscription subscribed" onclick="toggleSubscriptionAjax(<?= $blogID ?>, 'blog')">🔕 Unsubscribe</button>
            <?php else: ?>
              <button id="btn-sub-toggle" class="btn-subscription" onclick="toggleSubscriptionAjax(<?= $blogID ?>, 'blog')">🔔 Subscribe to discussion</button>
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

      <?php renderComments($conn, $blogID, 'blog'); ?>

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
    // Browse all blog posts list view
    $page_title = 'Dominik Mueller - Browse all stories';
    require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3>Browse all blog posts and stories</h3>
    </div>
    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php getNotes(1000); ?>
      </ul>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <p>
        In this section, I don't usually write about individual wines, but about larger tastings of several wines (e.g. horizontals,
        verticals, etc.) or personal experiences on wine trips and at restaurants.
      </p>
      <p>
        If a report refers to a specific wine, this is usually linked so that you can quickly find more information about the wine in
        question on my site.
      </p>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<?php
  }
?>

<?php function getPost($id)
{
  global $mysqli, $conn;
  
  // Perform query
  $stmt = $mysqli->prepare("select * from blogposts where blog_id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result)
  {
    $GLOBALS['blogpost'] = $result -> fetch_assoc();
    // Free result set
    $result -> free_result();
  }
  $stmt->close();
} ?>

<?php function wines($id)
{
  global $mysqli, $conn;
  $prevWine="";
  // Perform query
  $stmt = $mysqli->prepare("select * from x_blog_wines
                                left join wines on x_blog_wines.wine_id=wines.wine_id
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join (select note_id,wine_id as w_id,tasting_date,flawed_yn,dmpts,status,users.initials from tnotes left join users on tnotes.user_id=users.user_id) tnotes on x_blog_wines.wine_id=tnotes.w_id
                              where x_blog_wines.blog_id=?
                              order by producers.producer asc, wines_master.name asc, wines.vintage asc, tnotes.tasting_date desc");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  while ($wine = $result->fetch_assoc()) {
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
    // Output
    if ($wine_name!=$prevWine) {
      echo "<li><a href='/wines.php?id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
      $prevWine=$wine_name;
    }
    if ($wine['flawed_yn']=="yes") { $dmpts="flawed"; } elseif ($wine['dmpts']!=null) { $initials = !empty($wine['initials']) ? $wine['initials'] : 'DM'; $dmpts=$initials.$wine["dmpts"]; } else { $dmpts="NR"; }
    if ($wine["note_id"]!=null and $wine["status"]=="published") { echo "<ul><li>Tasted on ".date_format(date_create($wine["tasting_date"]),"d M Y").": <a href='/tnotes.php?id=".$wine["note_id"]."'>".$dmpts."</a></li></ul>"; }
  }
  if (mysqli_num_rows($result)==0) { echo "<li>No wines were featured in this story.</li>"; }
  $stmt->close();
  $result -> free_result();
} ?>

<?php function getNotes($num)
{
  global $mysqli, $conn;
  $prevYear="";
  // Perform query
  $result = $mysqli -> query("select blogposts.*, coalesce(c.comment_count, 0) as comment_count from blogposts left join (select blog_id as c_blog_id, count(comment_id) as comment_count from x_comments_blogposts group by blog_id) c on blogposts.blog_id=c.c_blog_id where status='published' order by pub_date desc, blog_id desc limit 0,".$num);
  // Output
  while ($blogs = $result->fetch_assoc()) {
    $comment_badge = getCommentBadgeHtml($blogs["comment_count"] ?? 0, "/blog.php?id=" . $blogs["blog_id"] . "#comments");
    if (date_format(date_create($blogs["pub_date"]),"Y")!=$prevYear) {
      echo "<br><li><b>".date_format(date_create($blogs["pub_date"]),"Y")."</b></li>";
    }
    echo "<li>".date_format(date_create($blogs["pub_date"]),"d M Y").": <a href='/blog.php?id=".$blogs['blog_id']."'>".$blogs["title"]."</a>" . $comment_badge . "</li>";
    $prevYear=date_format(date_create($blogs["pub_date"]),"Y");
  }
  $result -> free_result();
} ?>
