<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // DB connection
    global $mysqli, $conn;
    
    // Username and password variables
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Perform query using prepared statement to prevent SQL injection
    $stmt = $mysqli->prepare("SELECT user_id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // Validate user
    if ($result->num_rows == 1) {
      $user = $result->fetch_assoc();
      $hashedPassword = $user['password'];

      // Verify the password
        if (password_verify($password, $hashedPassword)) {
            $_SESSION['user_id'] = $user['user_id'];
            $stmt->close();
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } else {
      $error = "Invalid username or password";
    }
    $stmt->close();
  }
?>

<?php
  $page_title = 'Dominik Mueller - Wine is my hobby';
  $meta_desc = 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.';
  
  require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3>Login</h3>
      <?php
        if (isset($error)) {
          echo "<p>$error</p>";
          echo "<p>If you do not yet have an account, please contact me using the details below.</p>";
        } else {
          echo "<p>Please log in to access all parts of the website. Thank you.</p>";
          echo "<p>If you do not yet have an account, please contact me using the details below.</p>";
        }
      ?>
    </div>
    <div class="card">
      <form method="post">
        Username: <input type="text" name="username"><br>
        Password: <input type="password" name="password"><br>
        <button type="submit">Login</button>
      </form>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <h3>Get in touch</h3>
      <p>I control access to some parts of this website. My tasting notes and blog posts are reserved for members only - as is
      my <em>carte des vins</em>, an interactive account of the wines in my personal cellar, which are ready to be drunk. 
      Registered users can also leave comments on my notes and enjoy a safe and well-behaved place to discuss their favourite
      topic, wine. User accounts are free, I don't want to make money from this site, but usually only friends and family have
      access. If you'd like to introduce yourself and connect with me, you may do so using my details below. I'm always happy
      to hear and learn from other wine enthusiasts.</p>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php function latestNotes($num)
{
  global $mysqli, $conn;
    // Perform query
  $result = $mysqli -> query("select * from tnotes
                                left join users on tnotes.user_id=users.user_id
                                left join wines on tnotes.wine_id=wines.wine_id
                                left join wines_master on wines_master.master_id=wines.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join regions on wines_master.region_id=regions.region_id
                                left join countries on regions.country=countries.country
                                left join subregions on wines_master.subregion_id=subregions.subregion_id
                                left join appellations on wines_master.appellation_id=appellations.appellation_id
                              where status='published'
                              order by tasting_date desc, note_id desc limit 0,".$num);
  while ($tasting_note = $result->fetch_assoc()) {
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
    // Print tasting notes
    echo "<li>".date_format(date_create($tasting_note["tasting_date"]),"d M y").": <a href='/tnote.php?id=".$tasting_note['note_id']."'>".$wine_name."</a></li>";
  }
  $result -> free_result();
  } ?>

<?php function latestBlogs($num)
{
  global $mysqli, $conn;
    // Perform query
  $result = $mysqli -> query("select * from blogposts order by pub_date desc limit 0,".$num);
  while ($blog = $result->fetch_assoc()) {
    echo "<li>".date_format(date_create($blog["pub_date"]),"d M y").": <a href='/blogpost.php?id=".$blog['blog_id']."'>".$blog["title"]."</a></li>";
  }
  $result -> free_result();
  } ?>
