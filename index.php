<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';
?>

<?php
  $page_title = 'Dominik Mueller - Wine is my hobby';
  $meta_desc = 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.';
  
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
        <h3>Welcome</h3>
        <img class="inline left" src="/img/me2026.jpg" alt="Dominik Mueller">
        <p>
          Welcome to my personal wine notebook! Here, I share wines I have tasted and tell you about my wine and food experiences. I am
          not a professional. Wine is my hobby. The French would call me an <em>amateur des vins</em>. With this website, I hope to connect
          with like-minded people interested in all kinds of wines.
        </p>
        <p>
         The site is organized around a wine database, including information on producers, plus my tasting notes with ratings and usually
         photos. There is also a blog where I occasionally write about other wine experiences, such as travel reports or themed tastings.
       </p>
       <p>
         I programmed the website myself, old-school in a text editor, so not everything may be perfect yet. But there is one big advantage:
         I manage almost entirely without cookies! Only when you log in will an anonymous session cookie keep your session active. There are
         no third-party services or visitor counters. You are incognito here, if you want. Everything is free of charge. I hope you enjoy it!
       </p>
       <img src="/img/dmsig.bmp" width="300" height="370" style="margin:5px 10px 0px 0px;max-width:15%;height:auto;">
       <p>
         <em>Dominik Mueller</em>
       </p>
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <ul style="list-style-type:none;padding:0;margin:0;">
          <?php latestComments(5); ?>
        </ul>
      <?php else: ?>
        <p>You must be <a href="/login.php">logged in</a> to view and post comments.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Get in touch</h3>
      <p>
        I control access to some parts of this website. My tasting notes and blog posts are reserved for members only - as is
        my <em>carte des vins</em>, an interactive account of the wines in my personal cellar, which are ready to be drunk. 
        Registered users can also leave comments on my notes and enjoy a safe and well-behaved place to discuss their favourite
        topic, wine. User accounts are free, I don't want to make money from this site, but usually only friends and family have
        access. If you'd like to introduce yourself and connect with me, you may do so using my details below. I'm always happy
        to hear and learn from other wine enthusiasts.
      </p>
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
  $result = $mysqli -> query("select * from blogposts where status='published' order by pub_date desc limit 0,".$num);
  while ($blog = $result->fetch_assoc()) {
    echo "<li>".date_format(date_create($blog["pub_date"]),"d M y").": <a href='/blogpost.php?id=".$blog['blog_id']."'>".$blog["title"]."</a></li>";
  }
  $result -> free_result();
  } ?>
