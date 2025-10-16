<?php
  session_start();

  // Check if user is not logged in
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
  }
?>

<!DOCTYPE html>
<html lang="en-GB">

<?php
  if (empty($_GET['sort'])) {
    $sort="date";
  } else {
    $sort=$_GET['sort'];
  }
  if ($sort=="date") {
    $sqlOrderBy="where status='published' order by tasting_date desc, note_id desc";
  } elseif ($sort=="rating") {
    $sqlOrderBy="where status='published' order by flawed_yn asc, dmpts desc, tasting_date desc, note_id desc";
  } elseif ($sort=="stars") {
    $sqlOrderBy="where status='published' order by flawed_yn asc, starpts desc, dmpts desc, producer asc, tasting_date desc, note_id desc";
  } elseif ($sort=="region") {
    $sqlOrderBy="where status='published' order by country asc,region asc,dmpts desc,producer asc,vintage desc,subregion asc,appellation asc,vineyard asc,tasting_date desc";
  } elseif ($sort=="producer") {
    $sqlOrderBy="where status='published' order by producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,tasting_date desc";
  } elseif ($sort=="vintage") {
    $sqlOrderBy="where status='published' order by vintage desc,dmpts desc,tasting_date desc,country asc,producer asc,region asc,subregion asc,appellation asc,vineyard asc";
  } elseif ($sort=="variety") {
    $sqlOrderBy="where status='published' order by grape asc,dmpts desc,producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,tasting_date desc";
  } elseif ($sort=="tenyears") {
    $sqlOrderBy="where status='published' and vintage is not null and year(tasting_date)-vintage=10 order by tasting_date desc, note_id desc";
  }
?>

<head>
  <title>Dominik Mueller - Browse all wines</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <meta name="description" content="On this website, I share my wine cellar with a community of fellow fine wine enthusiasts."
  <meta name="keywords" content="Dominik Mueller,wine database,wine tasting,tasting notes,fine wine,wine collection,wine cellar">
  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">
</head>

<body>

<header class="titles">
  <h1 class="site-title">Dominik Mueller</h1>
  <h2 class="sub-title">Wine is my hobby. Fine wine tasting notes and experiences.</h2>
</header>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <li><a href="/index.php" title="Back to homepage">Home</a></li>
      <li><a href="/wines.php" title="Wine database">Wine database</a></li>
      <li><a class="active" href="/tnotes.php" title="Fine wine tasting notes">Tasting notes</a></li>
      <li><a href="/blog.php" title="My wine blog">Stories</a></li>
      <?php
        if (!isset($_SESSION['user_id'])) {
          echo "<li class='right'><a href='/login.php' title='Login'>Login</a></li>";
        } elseif (isset($_SESSION['user_id'])) {
          echo "<li><a style='font-style:italic;' href='/winemenu.php' title='Carte des vins'>Carte des vins</a></li>";
          echo "<li class='right'><a href='/logout.php' title='Logout'>Logout</a></li>";
          echo "<li class='right'><a href='/accountSettings.php' title='My account'>My account</a></li>";
        }
      ?>
    </ul>
  </nav>
</header>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3 style="margin-bottom:0;">Browse all tasting notes</h3>
      <p style="margin-top:0;"><small>
        Sort by:
        <a class="filter-nav" href="/tnotes.php?sort=date">Tasting date</a>
        <a class="filter-nav" href="/tnotes.php?sort=rating">DM points</a>
        <a class="filter-nav" href="/tnotes.php?sort=stars">Stars</a>
        <a class="filter-nav" href="/tnotes.php?sort=region">Region</a>
        <a class="filter-nav" href="/tnotes.php?sort=producer">Producer</a>
        <a class="filter-nav" href="/tnotes.php?sort=vintage">Vintage</a>
	<a class="filter-nav" href="/tnotes.php?sort=variety">Variety</a>
        <a class="filter-nav" href="/tnotes.php?sort=tenyears">Ten years on</a>
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
      <p>
        This is a chronological list of all the tasting notes I have published. Keep in mind that this is not my main job, but a hobby.
        However, I usually manage to write at least one new note per week.
      </p>
      <aside>
        <h4>How I rate wines</h4>
        <p>I use two rating scales in my notes:</p>
        <p>
          Firstly, there is my personal <strong>20-point DM scale</strong>. On this scale, I rate the <em>absolute</em> quality of wines.
          My ratings may appear low relative to those of most popular wine reviewers at first glance, but I make use of the entire range
          from 0 to 20 points:
        </p>
        <table>
          <tr>
            <td style="width:70px">20</td>
            <td>one-of-a-kind</td>
          </tr>
          <tr>
            <td>17-19</td>
            <td>grand vin</td>
          </tr>
          <tr>
            <td>13-16</td>
            <td>excellent</td>
          </tr>
          <tr>
            <td>9-12</td>
            <td>very good</td>
          </tr>
          <tr>
            <td>5-8</td>
            <td>good</td>
          </tr>
          <tr>
            <td>3-4</td>
            <td>passable</td>
          </tr>
          <tr>
            <td>1-2</td>
            <td>subpar</td>
          </tr>
          <tr>
            <td>0</td>
            <td>poor</td>
          </tr>
        </table>
        <p>As you can see, &quot;good&quot; wines start at 5 points already!</p>
        <p>
          Secondly, I also find <em>relative</em> ratings extremely valuable. For Burgundy, for example, it is logical that a <em>village</em>
          wine should generally receive fewer points than a <em>grand cru</em> wine on both the 20-point and the 100-point scale, although
          that particular village wine may still be an excellent wine within its category (type, character, and price segment). I was inspired
          by Jasper Morris from Inside Burgundy to use his <strong>star scale</strong> on which a maximum of 5 stars can be achieved. With this
          scale I take into account the quality of a wine <em>relative to its peer group</em> in order to allow well-made wines to stand out.
        </p>
        <p><a href="https://dmueller.com/blogpost.php?id=26" title="How I rate wines">Find out more about how I rate wines.</a></p>
      </aside>
    </div>
  </div>
</div>

<div class="footer">
  <footer>
    <p style="float:right;margin-top:0;">
      <a href="/privacy.php" title="Privacy policy">Privacy policy</a>
      <br><a href="/impressum.php" title="Impressum / Imprint">Impressum / Imprint</a>
    </p>
    <address>
      Contact details:<br>
      Dominik Mueller<br>
      Muehlstr. 24<br>
      76532 Baden-Baden<br>
      GERMANY<br><br>
      E-Mail: <a href="mailto:dm@dmueller.com" title="Contact me by email">dm@dmueller.com</a>
    </address>
    <p align="center">
      <small><u>Cookie notice:</u><br>This website uses session cookies for members logging in only. Aside from that,<br>the
      website uses <strong>no</strong> cookies. Refer to the <a href="/privacy.php" alt="Privacy policy">privacy policy</a>
      for details. Have fun!</small>
    </p>
  </footer>
</div>

</body>

</html>

<?php function getNotes($num,$sort,$sqlOrderBy)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  $prevYear="";
  $prevRating="";
  // Perform query
  $result = $mysqli -> query("select * from tnotes
                                left join wines on tnotes.wine_id=wines.wine_id
                                left join wines_master on wines.master_id=wines_master.master_id
                                left join producers on wines_master.producer_id=producers.producer_id
                                left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
                                left join regions on wines_master.region_id=regions.region_id
                                left join subregions on wines_master.subregion_id=subregions.subregion_id
                                left join appellations on wines_master.appellation_id=appellations.appellation_id
				left join (select grape as vgrape, grape_desc from variety) v on wines_master.grape=v.vgrape
                                left join (select pts as dpts, dmpts_desc from dmpts) d on tnotes.dmpts=d.dpts
                                left join (select pts as spts, starpts_desc from starpts) s on tnotes.starpts=s.spts
                              ".$sqlOrderBy." limit 0,".$num);
  if ($sort=="date") {
    echo "<p><small><i>Tasting notes sorted chronologically by tasting date.</i></small></p>";
  } elseif ($sort=="rating") {
    echo "<p><small><i>Tasting notes sorted by DM points (20-point absolute scale).</i></small></p>";
  } elseif ($sort=="stars") {
    echo "<p><small><i>Tasting notes sorted by stars (5-star relative scale), then DM points and producer.</i></small></p>";
  } elseif ($sort=="region") {
    echo "<p><small><i>Tasting notes sorted by country and region. Then by DM points, producer and wine.</i></small></p>";
  } elseif ($sort=="producer") {
    echo "<p><small><i>Tasting notes sorted by producer, then vintage and wine.</i></small></p>";
  } elseif ($sort=="vintage") {
    echo "<p><small><i>Tasting notes sorted by vintage, then DM points and tasting date.</i></small></p>";
  } elseif ($sort=="variety") {
    echo "<p><small><i>Tasting notes sorted by grape variety, then DM points, producer and tasting date. For <em>assemblages</em>, only the main grape variety is shown.</i></small></p>";
  } elseif ($sort=="tenyears") {
    echo "<p><small><i>&quot;Ten years on&quot; tasting notes sorted chronologically by tasting date.</i></small></p>";
  }
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
    // Rating?
    if ($tasting_note['flawed_yn']=="yes") {
      $dmpts="flawed";
      $stars="flawed";
    } elseif ($tasting_note['dmpts']!=null) {
      $dmpts="DM".$tasting_note["dmpts"];
      if ($tasting_note["starpts"]!=1) {
        $stars=$tasting_note["starpts"]." stars";
      } else {
        $stars=$tasting_note["starpts"]." star";
      }
    } else {
      $dmpts="NR";
      $stars="NR";
    }
    // Output
    if ($sort=="date") {
      if (date_format(date_create($tasting_note["tasting_date"]),"Y")!=$prevYear) {echo "<br><li><b>".date_format(date_create($tasting_note["tasting_date"]),"Y")."</b></li>";}
    } elseif ($sort=="rating") {
      if ($dmpts!=$prevRating) {
        if ($dmpts!="flawed" and $dmpts!="NR") {
          echo "<br><li><details><summary><b>".$dmpts."</b></summary><hr><small>".$tasting_note["dmpts_desc"]."</small><br><br></details></li>";
        } elseif ($dmpts=="NR") {
          echo "<br><li><details><summary><b>".$dmpts."</b></summary><hr><small>Sometimes I don't give a rating to a wine. In that case you'll read &quot;not rated&quot; or the abbreviation
            &quot;NR&quot; in the tasting note. I might not give a rating if I tasted a wine only quickly without taking notes - at a winery, for example. Sometimes I might still want to note
            down an indication for a possible rating. In that case, I'll write a provisional score in the tasting note, but I'll always make that clear in the written note.</small><br><br></details></li>";
        } elseif ($dmpts=="flawed") {
          echo "<br><li><details><summary><b>".$dmpts."</b></summary><hr><small>I mark bottles as flawed when there is a fault outside of the control of the winemaker. This might happen if
            a wine is corked or it is spoilt because of bad storage conditions. I don't rate these wines.</small><br><br></details></li>";
        }
      }
    } elseif ($sort=="stars") {
      if ($stars!=$prevStars) {
        if ($stars!="flawed" and $stars!="NR") {
          echo "<br><li><details><summary><b>".$stars."</b></summary><hr><small>".$tasting_note["starpts_desc"]."</small><br><br></details></li>";
        } elseif ($stars=="NR") {
          echo "<br><li><details><summary><b>".$stars."</b></summary><hr><small>Sometimes I don't give a rating to a wine. In that case you'll read &quot;not rated&quot; or the abbreviation
            &quot;NR&quot; in the tasting note. I might not give a rating if I tasted a wine only quickly without taking notes - at a winery, for example. Sometimes I might still want to note
            down an indication for a possible rating. In that case, I'll write a provisional score in the tasting note, but I'll always make that clear in the written note.</small><br><br></details></li>";
        } elseif ($stars=="flawed") {
          echo "<br><li><details><summary><b>".$stars."</b></summary><hr><small>I mark bottles as flawed when there is a fault outside of the control of the winemaker. This might happen if
            a wine is corked or it is spoilt because of bad storage conditions. I don't rate these wines.</small><br><br></details></li>";
        }
      }
    } elseif($sort=="region") {
      if($tasting_note["country"]!=$prevCountry){echo "<br><li><b>".$tasting_note["country"]."</b></li>";}
      if($tasting_note["region"]!=$prevRegion){echo "<li><i>".$tasting_note["region"]."</i></li>";}
    } elseif($sort=="producer") {
      if ($tasting_note["producer"]!=$prevProducer) {
        if ($tasting_note["producer_desc"]!=null) {
          echo "<br><li><details><summary><b>".$tasting_note["producer"]."</b></summary><hr><small>".$tasting_note["producer_desc"]."</small></details></li>";
        } else {
          echo "<br><li><b>".$tasting_note["producer"]."</b></li>";
        }
      }
    } elseif($sort=="vintage") {
      if($tasting_note["vintage"]!=$prevVintage) {echo "<br><li><b>".$tasting_note["vintage"]."</b></li>";}
    } elseif($sort=="variety") {
      if ($tasting_note["grape"]!=$prevVariety) {
        if ($tasting_note["grape_desc"]!=null) {
          echo "<br><li><details><summary><b>".$tasting_note["grape"]."</b></summary><hr><small>".$tasting_note["grape_desc"]."</small></details></li>";
        } else {
          echo "<br><li><b>".$tasting_note["grape"]."</b></li>";
        }
      }
    } elseif($sort=="tenyears") {
      if($tasting_note["vintage"]!=$prevVintage) {echo "<br><li><b>".$tasting_note["vintage"]."-".date_format(date_create($tasting_note["tasting_date"]),"y")."</b></li>";}
    }
    echo "<li>";
    if ($sort=="date" or $sort=="rating" or $sort=="tenyears") {
      echo date_format(date_create($tasting_note["tasting_date"]),"d M Y").": ";
    }
    if ($tasting_note["starpts"]==5) {
      echo "<img src='/img/5stars_16px.gif'>";
    }
    echo "<a href='/tnote.php?id=".$tasting_note['note_id']."'>".$wine_name."</a> (".$dmpts.")</li>";
    // Set previous values
    $prevYear=date_format(date_create($tasting_note["tasting_date"]),"Y");
    $prevRating=$dmpts;
    $prevStars=$stars;
    $prevCountry=$tasting_note["country"];
    $prevRegion=$tasting_note["region"];
    $prevProducer=$tasting_note["producer"];
    $prevVintage=$tasting_note["vintage"];
    $prevVariety=$tasting_note["grape"];
  }
  $result -> free_result();
  $mysqli -> close();
} ?>