<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  $site_title = getSiteTitle();
  $site_tagline = getSiteTagline();
  $page_title = $site_title . ' - ' . $site_tagline;
  $meta_desc = getSiteSetting('meta_description', 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.');
  
  $welcome_page = getStaticPage('welcome');
  $welcome_title = $welcome_page['page_title'] ?? 'Welcome';
  $welcome_content = $welcome_page['page_content'] ?? '<p>Welcome to my wine notebook! Here, I share wines I have tasted and tell you about my wine and food experiences.</p>';

  $get_in_touch_page = getStaticPage('get_in_touch');
  $get_in_touch_title = $get_in_touch_page['page_title'] ?? 'Get in touch';
  $get_in_touch_content = $get_in_touch_page['page_content'] ?? '<p>Access to certain areas of this website is restricted. While my tasting notes and blog posts are open to everyone, my carte des vins is reserved for members. Feel free to reach out using the contact details below.</p>';

  require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3>New tasting notes</h3>
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php latestNotes(5); ?>
      </ul>
      <p><i><a href="/tnotes.php" title="All tasting notes">Browse all tasting notes...</a></i></p>
    </div>
    <div class="card">
      <section>
        <h3><?php echo htmlspecialchars($welcome_title, ENT_QUOTES, 'UTF-8'); ?></h3>
        <?php echo function_exists('interpolateSiteSettings') ? interpolateSiteSettings($welcome_content) : $welcome_content; ?>
      </section>
    </div>
  </div>

  <div class="column side">
    <div class="card">
      <h3>Latest stories</h3>
      <p>While this website is dedicated to the wines I've tasted, you will find other wine-related articles and stories on my blog:</p>
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php latestBlogs(5); ?>
      </ul>
      <p><i><a href="/blog.php" title="All wine stories">Read all...</a></i></p>
    </div>

    <div class="card">
      <h3>Recent comments</h3>
      <?php if (hasPrivilege($conn, 'view_comments')): ?>
        <ul style="list-style-type:none;padding:0;margin:0;">
          <?php latestComments(5); ?>
        </ul>
      <?php else: ?>
        <p>You must be <a href="/login.php">logged in</a> to view and post comments.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3><?php echo htmlspecialchars($get_in_touch_title, ENT_QUOTES, 'UTF-8'); ?></h3>
      <?php echo function_exists('interpolateSiteSettings') ? interpolateSiteSettings($get_in_touch_content) : $get_in_touch_content; ?>
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
    echo "<li>".date_format(date_create($tasting_note["tasting_date"]),"d M y").": <a href='/tnotes.php?id=".$tasting_note['note_id']."'>".$wine_name."</a></li>";
  }
  $result -> free_result();
  } ?>

<?php function latestBlogs($num)
{
  global $mysqli, $conn;
    // Perform query
  $result = $mysqli -> query("select * from blogposts where status='published' order by pub_date desc limit 0,".$num);
  while ($blog = $result->fetch_assoc()) {
    echo "<li>".date_format(date_create($blog["pub_date"]),"d M y").": <a href='/blog.php?id=".$blog['blog_id']."'>".$blog["title"]."</a></li>";
  }
  $result -> free_result();
  } ?>
