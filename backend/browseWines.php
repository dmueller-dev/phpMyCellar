<?php
  // Define a constant to protect included files from direct access
  if (!defined('INCLUDED_VIA_APP')) {
    define('INCLUDED_VIA_APP', true);
  }

  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/../includes/init.php';

  // Get sort logic
  if (empty($_GET['sort'])) {
    $sort="region";
  } else {
    $sort=$_GET['sort'];
  }
  if ($sort=="region") {
    $sqlOrderBy="order by country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc";
  } elseif ($sort=="producer") {
    $sqlOrderBy="order by producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc";
  } elseif ($sort=="vintage") {
    $sqlOrderBy="order by vintage desc,country asc,producer asc,region asc,subregion asc,appellation asc,vineyard asc";
  } elseif ($sort=="variety") {
    $sqlOrderBy="order by grape asc,country asc,producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc";
  } elseif ($sort=="tenyearsold") {
    $sqlOrderBy="where vintage is not null and year(curdate())-vintage=10 order by country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc";
  }

  // Get header
  $page_title = 'Dominik Mueller - Browse all wines';
  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3 style="margin-bottom:0;">Browse all wines</h3>
      <p style="margin-top:0;"><small>
        Sort by: <a class="filter-nav" href="/backend/browseWines.php?sort=region">Region</a>
        <a class="filter-nav" href="/backend/browseWines.php?sort=producer">Producer</a>
        <a class="filter-nav" href="/backend/browseWines.php?sort=vintage">Vintage</a>
        <a class="filter-nav" href="/backend/browseWines.php?sort=variety">Variety</a>
        <a class="filter-nav" href="/backend/browseWines.php?sort=tenyearsold">Ten years old</a>
        <a class="filter-nav" href="/backend/browseWines.php"><b>Reset</b></a>
      </small></p>
    </div>
    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php renderWines(10000,$sort,$sqlOrderBy); ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php function renderWines($num,$sort,$sqlOrderBy)
{
  global $mysqli, $conn;
    $prevCountry="";
  $prevRegion="";
  $prevProducer="";
  $prevVintage="";
  // Perform query
  $result = $mysqli -> query(
    "select
      wines.wine_id,
      wines.vintage,
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
      v.grape_desc
    from wines
      left join wines_master on wines.master_id=wines_master.master_id
      left join producers on wines_master.producer_id=producers.producer_id
      left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
      left join regions on wines_master.region_id=regions.region_id
      left join subregions on wines_master.subregion_id=subregions.subregion_id
      left join appellations on wines_master.appellation_id=appellations.appellation_id
      left join (select grape as vgrape, grape_desc from variety) v on wines_master.grape=v.vgrape " .
    $sqlOrderBy." limit 0,".$num
  );
  if ($sort=="region") {
    echo "<p><small><i>Wines sorted by country and region. Then by producer, wine and vintage.</i></small></p>";
  } elseif ($sort=="producer") {
    echo "<p><small><i>Wines sorted by producer, then vintage and wine.</i></small></p>";
  } elseif ($sort=="vintage") {
    echo "<p><small><i>Wines sorted by vintage, then country, producer and wine.</i></small></p>";
  } elseif ($sort=="variety") {
    echo "<p><small><i>Wines sorted by grape variety, then country, producer and wine.</i></small></p>";
  } elseif ($sort=="tenyearsold") {
    echo "<p><small><i>Ten-year-old wines sorted by country and region. Then by producer and wine.</i></small></p>";
  }
  while ($wine = $result->fetch_assoc()) {
    // Vintage NV?
    if ($wine["vintage"]==null) { $wine["vintage"]="NV"; }
    // Get wine name
    $wine_name = getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']);
    // Output
    if($sort=="region" || $sort=="tenyearsold") {
      if($wine["country"]!=$prevCountry) {
        echo ($prevCountry!="") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details><summary><b>".$wine["country"]."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevRegion="";
      }
      if($wine["region"]!=$prevRegion) {
        echo ($prevRegion!="") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><details><summary><i>".$wine["region"]."</i></summary><small><a href='/backend/editRegion.php?region_id=".$wine["region_id"]."'>Edit region.</a></small><br><hr><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/backend/editWine.php?wine_id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
    } elseif($sort=="producer") {
      if ($wine["producer"]!=$prevProducer) {
        echo ($prevProducer!="") ? "</ul></details><br>" : "";
        if ($wine["producer_desc"]!=null) {
          echo "<details><summary><b>".htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8')."</b></summary><small><a href='/backend/editProducer.php?producer_id=".$wine["producer_id"]."'>Edit producer.</a></small><br><hr><small>".$wine["producer_desc"]."</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details><summary><b>".htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8')."</b></summary><small><a href='/backend/editProducer.php?producer_id=".$wine["producer_id"]."'>Edit producer.</a></small><br><hr><ul style='list-style-type:none;padding:0;margin:0;'>";
        }
      }
      echo "<li style='padding-left:35px;text-indent:-18px;'><a href='/backend/editWine.php?wine_id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
    } elseif($sort=="vintage") {
      if($wine["vintage"]!=$prevVintage) {
        echo ($prevVintage!="") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details><summary><b>".$wine["vintage"]."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevCountry="";
      }
      if($wine["country"]!=$prevCountry) {
        echo ($prevCountry!="") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><details><summary><i>".$wine["country"]."</i></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/backend/editWine.php?wine_id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
    } elseif($sort=="variety") {
      if($wine["grape"]!=$prevVariety) {
        echo ($prevVariety!="") ? "</ul></details></li></ul></details><br>" : "";
        if($wine["grape_desc"]!=null) {
          echo "<details><summary><b>".$wine["grape"]."</b></summary><hr><small>".$wine["grape_desc"]."</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details><summary><b>".$wine["grape"]."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        }
        $prevCountry="";
      }
      if($wine["country"]!=$prevCountry) {
        echo ($prevCountry!="") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><details><summary><i>".$wine["country"]."</i></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/backend/editWine.php?wine_id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
    }
    // Set previous values
    $prevCountry=$wine["country"];
    $prevRegion=$wine["region"];
    $prevProducer=$wine["producer"];
    $prevVintage=$wine["vintage"];
    $prevVariety=$wine["grape"];
  }
  if($sort=="region" || $sort=="tenyearsold" || $sort=="vintage" || $sort=="variety") {
    echo "</ul></details></li></ul></details>";
  } elseif($sort=="producer") {
    echo "</ul></details>";
  }
  $result -> free_result();
  } ?>
