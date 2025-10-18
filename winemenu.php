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
    $sort="region";
  } else {
    $sort=$_GET['sort'];
  }
  if ($sort=="region") {
    $sqlOrderBy="order by regions.country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="producer") {
    $sqlOrderBy="order by producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="vintage") {
    $sqlOrderBy="order by vintage desc,regions.country asc,producer asc,region asc,subregion asc,appellation asc,vineyard asc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="variety") {
    $sqlOrderBy="order by grape asc,regions.country asc,producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="tenyearsold") {
    $sqlOrderBy="order by regions.country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="location") {
    $sqlOrderBy="order by cellar_name asc,bin_name asc,regions.country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="style") {
    $sqlOrderBy ="order by wines_master.style asc,regions.country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  }
?>

<head>
  <title>Carte des vins - Wine menu</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="Dominik Mueller">
  <meta name="description" content="On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.">
  <meta name="keywords" content="Dominik Mueller,wine database,wine tasting,tasting notes,fine wine,wine collection,wine cellar">
  <link rel="canonical" href="https://dmueller.com/">
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="/img/cropped-wineglassicon-32x32.webp" sizes="32x32">
  <link rel="icon" href="/img/cropped-wineglassicon-192x192.webp" sizes="192x192">
  <link rel="apple-touch-icon" href="/img/cropped-wineglassicon-180x180.webp">
</head>

<body style="font-family: Georgia, serif;">

<header class="titles">
  <h1 class="site-title">CARTE DES VINS</h1>
</header>

<header class="navigation">
  <input class="mobile-menu" type="checkbox" id="mobile-menu">
  <label class="mobile-icon" for="mobile-menu"><span class="mobile-icon-line"></span></label>

  <nav class="topnav">
    <ul class="top-menu">
      <li><a href="/index.php" title="Back to homepage">Home</a></li>
      <li><a href="/wines.php" title="Wine database">Wine database</a></li>
      <li><a href="/tnotes.php" title="Fine wine tasting notes">Tasting notes</a></li>
      <li><a href="/blog.php" title="My wine blog">Stories</a></li>
      <?php
        if (!isset($_SESSION['user_id'])) {
          echo "<li class='right'><a href='/login.php' title='Login'>Login</a></li>";
        } elseif (isset($_SESSION['user_id'])) {
          echo "<li><a class='active' style='font-style:italic;' href='/winemenu.php' title='Carte des vins'>Carte des vins</a></li>";
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
      <p align="center" style="margin-top:0;margin-bottom:0;"><small>
        Sort by: <a class="filter-nav" href="/winemenu.php?sort=region">Region</a>
        <a class="filter-nav" href="/winemenu.php?sort=producer">Producer</a>
        <a class="filter-nav" href="/winemenu.php?sort=vintage">Vintage</a>
        <a class="filter-nav" href="/winemenu.php?sort=variety">Variety</a>
        <a class="filter-nav" href="/winemenu.php?sort=style">Style</a>
        <a class="filter-nav" href="/winemenu.php?sort=tenyearsold">Ten years old</a>
      </small></p>
    </div>
    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php getBottles($sort,$sqlOrderBy); ?>
      </ul>
    </div>
  </div>
  <div class="column side">
    <div class="card">
      <aside>
        <p>While there are more bottles lying in my cellar, these are all the wines that are ready to be drunk today. <strong>Every wine</strong> can be opened, so please feel free to choose any wine you like! These wines were collected to be shared and enjoyed in the company of friends and family.</p>
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

<?php function getBottles($sort,$sqlOrderBy)
{
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");
  $prevCountry="";
  $prevRegion="";
  $prevProducer="";
  $prevVintage="";
  $prevVariety="";
  $prevLocation="";
  $prevStyle="";
  $prevWine="";
  $prevFormat="";
  // Perform query
  $result = $mysqli -> query(
    "select
      bottles.bottle_id,
      bottles.status,
      bottles.drink_from,
      bottles.drink_through,
      bottles.for_sale,
      bottle_formats.format,
      bottle_formats.format_desc,
      cellars.cellar_name,
      storageBins.bin_id,
      storageBins.bin_name,
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
      sum(count(bottles.bottle_id)) over (partition by wines.wine_id, bottle_formats.format) as numWine,
      count(bottles.bottle_id) as numWineBin
    from bottles
      left join bottle_formats on bottles.format=bottle_formats.format
      left join storageBins on bottles.storage_location=storageBins.bin_id
      left join cellars on storageBins.cellar_id=cellars.cellar_id
      left join wines on bottles.wine_id=wines.wine_id
      left join wines_master on wines.master_id=wines_master.master_id
      left join producers on wines_master.producer_id=producers.producer_id
      left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
      left join regions on wines_master.region_id=regions.region_id
      left join subregions on wines_master.subregion_id=subregions.subregion_id
      left join appellations on wines_master.appellation_id=appellations.appellation_id
      left join (select grape as vgrape, grape_desc from variety) v on wines_master.grape=v.vgrape
    where status='in cellar' and (year(curdate())>=bottles.drink_from or bottles.drink_from is null)"
    .(($sort=="tenyearsold") ? " and vintage is not null and (year(curdate())-vintage=10)" : "")
    ." group by wines.wine_id, bottle_formats.format, storageBins.bin_name "
    .$sqlOrderBy
  );
  if ($sort=="region") {
    echo "<p style='font-family: Georgia, serif;'><small><i>Bottles sorted by country and region. Then by producer, wine and vintage.</i></small></p>";
  } elseif ($sort=="producer") {
    echo "<p><small><i>Bottles sorted by producer, then vintage and wine.</i></small></p>";
  } elseif ($sort=="vintage") {
    echo "<p><small><i>Bottles sorted by vintage, then country, producer and wine.</i></small></p>";
  } elseif ($sort=="variety") {
    echo "<p><small><i>Bottles sorted by grape variety, then country, producer and wine.</i></small></p>";
  } elseif ($sort=="tenyearsold") {
    echo "<p><small><i>Ten-year-old wines sorted by country and region. Then by producer and wine.</i></small></p>";
  } elseif ($sort=="location") {
    echo "<p><small><i>Bottles sorted by storage location. Then by country, region, producer and wine.</i></small></p>";
  } elseif ($sort=="style") {
    echo "<p><small><i>Bottles sorted by style, then by country, region and producer.</i></small></p>";
  }
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
    if($sort=="region" || $sort=="tenyearsold") {
      if($wine["country"]!=$prevCountry) {
        echo ($prevCountry!="") ? "</ul></details></li></ul></li></ul><br>" : "";
        echo "<b>".$wine["country"]."</b><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevRegion="";
      }
      if($wine["region"]!=$prevRegion) {
        $prevWine="";
        echo ($prevRegion!="") ? "</ul></details></li></ul></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><i>".$wine["region"]."</i><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
    } elseif($sort=="producer") {
      if ($wine["producer"]!=$prevProducer) {
        $prevWine="";
        echo ($prevProducer!="") ? "</ul></details></li></ul><br>" : "";
        if ($wine["producer_desc"]!=null) {
          echo "<b>".$wine["producer"]."</b><hr><small>".$wine["producer_desc"]."</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<b>".$wine["producer"]."</b><ul style='list-style-type:none;padding:0;margin:0;'>";
        }
      }
    } elseif($sort=="vintage") {
      if($wine["vintage"]!=$prevVintage) {
        echo ($prevVintage!="") ? "</ul></details></li></ul></li></ul><br>" : "";
        echo "<b>".$wine["vintage"]."</b><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevCountry="";
      }
      if($wine["country"]!=$prevCountry) {
        $prevWine="";
        echo ($prevCountry!="") ? "</ul></details></li></ul></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><i>".$wine["country"]."</i><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
    } elseif($sort=="variety") {
      if($wine["grape"]!=$prevVariety) {
        echo ($prevVariety!="") ? "</ul></details></li></ul></li></ul><br>" : "";
        if($wine["grape_desc"]!=null) {
          echo "<b>".$wine["grape"]."</b><hr><small>".$wine["grape_desc"]."</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<b>".$wine["grape"]."</b><ul style='list-style-type:none;padding:0;margin:0;'>";
        }
        $prevCountry="";
      }
      if($wine["country"]!=$prevCountry) {
        $prevWine="";
        echo ($prevCountry!="") ? "</ul></details></li></ul></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><i>".$wine["country"]."</i><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
    } elseif($sort=="style") {
      if($wine["style"]!=$prevStyle) {
        echo ($prevStyle!="") ? "</ul></details></li></ul></li></ul><br>" : "";
        echo "<b>".$wine["style"]."</b><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevCountry="";
      }
      if($wine["country"]!=$prevCountry) {
        $prevWine="";
        echo ($prevCountry!="") ? "</ul></details></li></ul></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><i>".$wine["country"]."</i><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
    }
    // Print wine
    if($wine["wine_id"]!=$prevWine) {
      echo (($prevWine!="") ? "</ul></details></li>" : "")."<li style='padding-left:".(($sort!="producer") ? "43" : "35")."px;text-indent:-18px;'><details><summary style='list-style:none;'><img style='display:inline-block;vertical-align:middle;' src='/img/".$wine["colour"]."_16px.gif'>".$wine_name." - <small style='color:Gray;'>".$wine["numWine"]." btl".(($wine["numWine"]>1) ? "s." : ".")."</small></summary>";
      echo (($wine["wine_desc"]!=null) ? "<div class='winemenu_wine_desc'><hr><small>".$wine["wine_desc"]."</small></div>" : "");
      echo "<ul style='list-style-type:none;padding:0;margin-top:2px;margin-bottom:8px;'>";
    }
    // Print bottles
    if($wine["wine_id"]!=$prevWine || $wine["cellar_name"].$wine["bin_name"]!=$prevLocation || $wine["format"]!=$prevFormat) {
      echo "<li style='padding-left:19px;padding-top:1px;margin:0;line-height:10px;'><small style='color:Gray;'>".$wine["format"]." - ".$wine["bin_name"]." - ".$wine["numWineBin"]." btl".(($wine["numWineBin"]>1) ? "s." : ".")."</small></li>";
    }
    // Set previous values
    $prevCountry=$wine["country"];
    $prevRegion=$wine["region"];
    $prevProducer=$wine["producer"];
    $prevVintage=$wine["vintage"];
    $prevVariety=$wine["grape"];
    $prevLocation=$wine["cellar_name"].$wine["bin_name"];
    $prevStyle=$wine["style"];
    $prevWine=$wine["wine_id"];
    $prevFormat=$wine["format"];
  }
  if($sort=="region" || $sort=="tenyearsold" || $sort=="vintage" || $sort=="variety" || $sort=="style") {
    echo "</ul></details></ul></details></li></ul></details>";
  } elseif($sort=="producer" || $sort=="location") {
    echo "</ul></details></li></ul>";
  }
  $result -> free_result();
  $mysqli -> close();
} ?>
