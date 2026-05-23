<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Check if user is not logged in
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
  } else {
    // Fetch user details
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

  // Check if a blog ID parameter is provided; if not, redirect to blog.php
  if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    header("Location: /blog.php");
    exit;
  }

  // Get blog ID
  $blogID = $_GET['id'];

  // Include the database configuration file
  global $mysqli, $conn;

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
    // Validate the token before processing the login
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
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
        } else {
          $error = "Failed to post comment. Please try again.";
          $mysqli->rollback();
        }
      } else {
        $error = "Failed to post comment. Please try again.";
        $mysqli->rollback();
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
      <p style="margin-top:0;">Posted on <?php echo date_format(date_create($blogpost["pub_date"]),"l, j F Y");?></p>
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
    <?php renderComments($conn, $blogID, 'blog'); ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

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
                                left join (select note_id,wine_id as w_id,tasting_date,flawed_yn,dmpts,status from tnotes) tnotes on x_blog_wines.wine_id=tnotes.w_id
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
      echo "<li><a href='/wine.php?id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
      $prevWine=$wine_name;
    }
    if ($wine['flawed_yn']=="yes") { $dmpts="flawed"; } elseif ($wine['dmpts']!=null) { $dmpts="DM".$wine["dmpts"]; } else { $dmpts="NR"; }
    if ($wine["note_id"]!=null and $wine["status"]=="published") { echo "<ul><li>Tasted on ".date_format(date_create($wine["tasting_date"]),"d M Y").": <a href='/tnote.php?id=".$wine["note_id"]."'>".$dmpts."</a></li></ul>"; }
  }
  if (mysqli_num_rows($result)==0) { echo "<li>No wines were featured in this story.</li>"; }
  $stmt->close();
  $result -> free_result();
  } ?>
