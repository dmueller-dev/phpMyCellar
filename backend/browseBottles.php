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
  if ($sort=="region" || $sort=="tenyearsold") {
    $sqlOrderBy="order by country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,bottle_id asc";
  } elseif ($sort=="producer") {
    $sqlOrderBy="order by producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc,bottle_id asc";
  } elseif ($sort=="vintage") {
    $sqlOrderBy="order by vintage desc,country asc,producer asc,region asc,subregion asc,appellation asc,vintage desc,bottle_id asc";
  } elseif ($sort=="variety") {
    $sqlOrderBy="order by grape asc,country asc,producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc,bottle_id asc";
  } elseif ($sort=="location") {
    $sqlOrderBy="order by cellar_name asc,bin_name asc,country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,bottle_id asc";
  }

  // Include header
  $page_title = 'Dominik Mueller - Browse all wines';

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
        const favouriteCheckbox = document.getElementById('favouriteToggle');
        const includeConsumedCheckbox = document.getElementById('includeConsumedToggle');
        const url = new URL(window.location.href);

        if (searchValue) {
          url.searchParams.set('q', searchValue);
        } else {
          url.searchParams.delete('q');
        }

        if (favouriteCheckbox && favouriteCheckbox.checked) {
          url.searchParams.set('favourite', 'yes');
        } else {
          url.searchParams.delete('favourite');
        }

        if (includeConsumedCheckbox && includeConsumedCheckbox.checked) {
          url.searchParams.set('include_consumed', 'yes');
        } else {
          url.searchParams.delete('include_consumed');
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

  require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3 style="margin-bottom:0;">Browse all bottles</h3>

      <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; margin-top: 10px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 5px;">
          <label for="searchBox" style="font-size: small;">Search:</label>
          <input type="text" id="searchBox" onkeyup="triggerSearch()"
            value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') : ''; ?>"
            placeholder="e.g. 2015 Bordeaux"
            style="font-family: Georgia, serif; padding: 2px; width: 200px; max-width: 100%;">
        </div>
        <div style="display: flex; align-items: center; gap: 5px;">
          <input type="checkbox" id="favouriteToggle" onchange="updateFilters()"
            <?php echo (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') ? 'checked' : ''; ?>
            style="cursor: pointer; margin: 0; vertical-align: middle;">
          <label for="favouriteToggle" style="font-size: small; cursor: pointer; user-select: none;">Favourites only</label>
        </div>
        <div style="display: flex; align-items: center; gap: 5px;">
          <input type="checkbox" id="includeConsumedToggle" onchange="updateFilters()"
            <?php echo (isset($_GET['include_consumed']) && $_GET['include_consumed'] == 'yes') ? 'checked' : ''; ?>
            style="cursor: pointer; margin: 0; vertical-align: middle;">
          <label for="includeConsumedToggle" style="font-size: small; cursor: pointer; user-select: none;">Show consumed bottles</label>
        </div>
      </div>

      <p style="margin-top:0;"><small>
        Sort by: 
        <?php 
          $urlParams = "";
          if (!empty($_GET['q'])) {
            $urlParams .= "&q=" . urlencode($_GET['q']);
          }
          if (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') {
            $urlParams .= "&favourite=yes";
          }
          if (isset($_GET['include_consumed']) && $_GET['include_consumed'] == 'yes') {
            $urlParams .= "&include_consumed=yes";
          }
        ?>
        <a class="filter-nav" href="/backend/browseBottles.php?sort=region<?php echo $urlParams; ?>">Region</a>
        <a class="filter-nav" href="/backend/browseBottles.php?sort=producer<?php echo $urlParams; ?>">Producer</a>
        <a class="filter-nav" href="/backend/browseBottles.php?sort=vintage<?php echo $urlParams; ?>">Vintage</a>
        <a class="filter-nav" href="/backend/browseBottles.php?sort=variety<?php echo $urlParams; ?>">Variety</a>
        <a class="filter-nav" href="/backend/browseBottles.php?sort=tenyearsold<?php echo $urlParams; ?>">Ten years old</a>
        <a class="filter-nav" href="/backend/browseBottles.php?sort=location<?php echo $urlParams; ?>">Storage location</a>
        <a class="filter-nav" href="/backend/browseBottles.php"><b>Reset</b></a>
      </small></p>
    </div>
    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php renderBottles(10000,$sort,$sqlOrderBy); ?>
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

<?php function renderBottles($num,$sort,$sqlOrderBy)
{
  global $mysqli, $conn;
  $prevCountry="";
  $prevRegion="";
  $prevProducer="";
  $prevVintage="";
  $prevLocation="";
  $is_admin = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? 'read') === 'admin';

  // Base WHERE condition
  $sqlWhere = " WHERE 1=1 ";

  // Logic for ten-year-old and mature wines
  if ($sort == "tenyearsold") {
    $sqlWhere .= " AND wines.vintage is not null and year(curdate()) - wines.vintage = 10 ";
  }

  // Consumed bottles filter logic
  if ($sort == "location" || !isset($_GET['include_consumed']) || $_GET['include_consumed'] !== 'yes') {
    $sqlWhere .= " AND bottles.status = 'in cellar' ";
  }

  // Favourites filter constraint
  if (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') {
    $sqlWhere .= " AND EXISTS (SELECT 1 FROM tnotes WHERE tnotes.wine_id = wines.wine_id AND tnotes.favourite = 'yes' AND tnotes.status = 'published') ";
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
          wines_master.style LIKE '%$word%' OR
          bottles.bottle_id LIKE '%$word%'
        )";
      }
    }
    if (!empty($conditions)) {
      $sqlWhere .= " AND " . implode(' AND ', $conditions) . " ";
    }
  }

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
      storageBins.bin_name,
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
      left join (select grape as vgrape, grape_desc from variety) v on wines_master.grape=v.vgrape " .
    $sqlWhere . " " . $sqlOrderBy . " limit 0," . $num
  );

  if ($result->num_rows == 0) {
    // Determine what to display based on whether they searched or just filtered
    $feedbackMsg = !empty($_GET['q']) 
        ? "your search for '<strong>" . htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') . "</strong>'"
        : "your current filter selection";

    echo "<div class='card' style='text-align:center; padding:30px 20px;'>
      <p>I'm afraid no bottles currently match {$feedbackMsg}.</p>
      <p><small>Try adjusting your search terms or removing some filters to explore the database.</small></p>
    </div>";

    return; // Exit the function early so we don't print empty lists or sort text
  }
  if ($sort=="region") {
    echo "<p><small><i>Bottles sorted by country and region. Then by producer, wine and vintage.</i></small></p>";
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
  }
  while ($wine = $result->fetch_assoc()) {
    // Vintage NV?
    if ($wine["vintage"]==null) { $wine["vintage"]="NV"; }
    // Get wine name
    $wine_name = getWineName($wine['nameconvention'], $wine['vintage'], $wine['name'], $wine['producer'], $wine['grape'], $wine['vineyard']);

    // Generate shortcut link if user is admin
    $blind_taste_link = "";
    if ($is_admin) {
      $blind_taste_link = " <a href='/backend/blindTasting.php?bottle_id=" . $wine["bottle_id"] . "' title='Write blind tasting note' style='font-size:0.85em; text-decoration:none; margin-left:6px; color:indianred;'>[+ note]</a>";
    }

    // Output
    if($sort=="region" || $sort=="tenyearsold") {
      if($wine["country"]!=$prevCountry) {
        echo ($prevCountry!="") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details><summary><b>".$wine["country"]."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevRegion="";
      }
      if($wine["region"]!=$prevRegion) {
        echo ($prevRegion!="") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><details><summary><i>".$wine["region"]."</i></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/backend/editBottle.php?bottle_id=".$wine["bottle_id"]."'>".$wine["bottle_id"]."</a> - ".$wine_name." - <small style='color:Gray;'>".$wine["format"]."</small> - ".(($wine["bin_name"]!=null) ? "<small style='color:Gray;'><i>".$wine["cellar_name"]."/".$wine["bin_name"] : "<small style='color:LightCoral;'><i>".$wine["status"])."</i></small>" . $blind_taste_link . "</li>";
    } elseif($sort=="producer") {
      if ($wine["producer"]!=$prevProducer) {
        echo ($prevProducer!="") ? "</ul></details><br>" : "";
        if ($wine["producer_desc"]!=null) {
          echo "<details><summary><b>".htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8')."</b></summary><hr><small>".$wine["producer_desc"]."</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details><summary><b>".htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8')."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        }
      }
      echo "<li style='padding-left:35px;text-indent:-18px;'><a href='/backend/editBottle.php?bottle_id=".$wine["bottle_id"]."'>".$wine["bottle_id"]."</a> - ".$wine_name." - <small style='color:Gray;'>".$wine["format"]."</small> - ".(($wine["bin_name"]!=null) ? "<small style='color:Gray;'><i>".$wine["cellar_name"]."/".$wine["bin_name"] : "<small style='color:LightCoral;'><i>".$wine["status"])."</i></small>" . $blind_taste_link . "</li>";
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
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/backend/editBottle.php?bottle_id=".$wine["bottle_id"]."'>".$wine["bottle_id"]."</a> - ".$wine_name." - <small style='color:Gray;'>".$wine["format"]."</small> - ".(($wine["bin_name"]!=null) ? "<small style='color:Gray;'><i>".$wine["cellar_name"]."/".$wine["bin_name"] : "<small style='color:LightCoral;'><i>".$wine["status"])."</i></small>" . $blind_taste_link . "</li>";
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
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/backend/editBottle.php?bottle_id=".$wine["bottle_id"]."'>".$wine["bottle_id"]."</a> - ".$wine_name." - <small style='color:Gray;'>".$wine["format"]."</small> - ".(($wine["bin_name"]!=null) ? "<small style='color:Gray;'><i>".$wine["cellar_name"]."/".$wine["bin_name"] : "<small style='color:LightCoral;'><i>".$wine["status"])."</i></small>" . $blind_taste_link . "</li>";
    } elseif($sort=="location") {
      if($wine["cellar_name"].$wine["bin_name"]!=$prevLocation) {
        echo ($prevLocation!="") ? "</ul></details><br>" : "";
        echo "<details><summary><b>".$wine["cellar_name"]." / ".$wine["bin_name"]."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
      echo "<li style='padding-left:35px;text-indent:-18px;'><a href='/backend/editBottle.php?bottle_id=".$wine["bottle_id"]."'>".$wine["bottle_id"]."</a> - ".$wine_name." - <small style='color:Gray;'>".$wine["format"]."</small>" . $blind_taste_link . "</li>";
    }
    // Set previous values
    $prevCountry=$wine["country"];
    $prevRegion=$wine["region"];
    $prevProducer=$wine["producer"];
    $prevVintage=$wine["vintage"];
    $prevVariety=$wine["grape"];
    $prevLocation=$wine["cellar_name"].$wine["bin_name"];
  }
  if($sort=="region" || $sort=="tenyearsold" || $sort=="vintage" || $sort=="variety") {
    echo "</ul></details></li></ul></details>";
  } elseif($sort=="producer" || $sort=="location") {
    echo "</ul></details>";
  }
  $result -> free_result();
  } ?>
