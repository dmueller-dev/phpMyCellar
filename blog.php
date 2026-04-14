<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';
?>

<?php
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

<?php function getNotes($num)
{
  global $mysqli, $conn;
    $prevYear="";
  // Perform query
  $result = $mysqli -> query("select * from blogposts where status='published' order by pub_date desc, blog_id desc limit 0,".$num);
  // Output
  while ($blogs = $result->fetch_assoc()) {
    if (date_format(date_create($blogs["pub_date"]),"Y")!=$prevYear) {
      echo "<br><li><b>".date_format(date_create($blogs["pub_date"]),"Y")."</b></li>";
    }
    echo "<li>".date_format(date_create($blogs["pub_date"]),"d M Y").": <a href='/blogpost.php?id=".$blogs['blog_id']."'>".$blogs["title"]."</a></li>";
    $prevYear=date_format(date_create($blogs["pub_date"]),"Y");
  }
  $result -> free_result();
  } ?>
