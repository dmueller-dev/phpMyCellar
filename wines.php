<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';
?>

<?php
  if (empty($_GET['sort'])) {
    $sort="region";
  } else {
    $sort=$_GET['sort'];
  }

  if ($sort=="region" || $sort=="tenyearsold" || $sort=="twentyplus") {
    $sqlOrderBy="order by country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc";
  } elseif ($sort=="producer") {
    $sqlOrderBy="order by producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc";
  } elseif ($sort=="vintage") {
    $sqlOrderBy="order by vintage desc,country asc,producer asc,region asc,subregion asc,appellation asc,vineyard asc";
  } elseif ($sort=="variety") {
    $sqlOrderBy="order by grape asc,country asc,producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc";
  }
?>

<?php
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
        const url = new URL(window.location.href);
        if (searchValue) {
          url.searchParams.set('q', searchValue);
        } else {
          url.searchParams.delete('q');
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

  require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <h3 style="margin-bottom:10px; margin-top:0;">Browse all wines</h3>

      <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 15px;">
        <label for="searchBox" style="font-size: small;">Search:</label>
        <input type="text" id="searchBox" onkeyup="triggerSearch()"
          value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') : ''; ?>"
          placeholder="e.g. 2015 Bordeaux"
          style="font-family: Georgia, serif; padding: 2px; width: 200px; max-width: 100%;">
      </div>

      <p style="margin-top:0;"><small>
        Sort by:
        <?php $urlParams = !empty($_GET['q']) ? "&q=" . urlencode($_GET['q']) : ""; ?>
        <a class="filter-nav" href="/wines.php?sort=region<?php echo $urlParams; ?>">Region</a>
        <a class="filter-nav" href="/wines.php?sort=producer<?php echo $urlParams; ?>">Producer</a>
        <a class="filter-nav" href="/wines.php?sort=vintage<?php echo $urlParams; ?>">Vintage</a>
        <a class="filter-nav" href="/wines.php?sort=variety<?php echo $urlParams; ?>">Variety</a>
        <a class="filter-nav" href="/wines.php?sort=tenyearsold<?php echo $urlParams; ?>">Aged 10 years</a>
        <a class="filter-nav" href="/wines.php?sort=twentyplus<?php echo $urlParams; ?>">Aged 20+ years</a>
        <a class="filter-nav" href="/wines.php"><b>Reset</b></a>
      </small></p>
    </div>
    <div class="card">
      <ul style="list-style-type:none;padding:0;margin:0;">
        <?php renderWines(1000,$sort,$sqlOrderBy); ?>
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

<?php require_once 'includes/footer.php'; ?>

<?php function renderWines($num,$sort,$sqlOrderBy)
{
  global $mysqli, $conn;
  $prevCountry="";
  $prevRegion="";
  $prevProducer="";
  $prevVintage="";

  // Base WHERE condition
  $sqlWhere = " WHERE 1=1 ";

  // Logic for ten-year-old and mature wines
  if ($sort == "tenyearsold") {
    $sqlWhere .= " AND wines.vintage is not null and year(curdate()) - wines.vintage = 10";
  } elseif ($sort == "twentyplus") {
    $sqlWhere .= " AND wines.vintage is not null and year(curdate()) - wines.vintage >= 20";
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
          wines_master.style LIKE '%$word%'
        )";
      }
    }
    if (!empty($conditions)) {
      $sqlWhere .= " AND " . implode(' AND ', $conditions) . " ";
    }
  }

  // Perform query
  $result = $mysqli -> query("select
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
                                left join (select grape as vgrape, grape_desc from variety) v on wines_master.grape=v.vgrape
                                " . $sqlWhere . " " . $sqlOrderBy . " limit 0," . $num);

  // Display message if no results found
  if ($result->num_rows == 0) {
    // Determine what to display based on whether they searched or just filtered
    $feedbackMsg = !empty($_GET['q']) 
        ? "your search for '<strong>" . htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') . "</strong>'"
        : "your current filter selection";

    echo "<div class='card' style='text-align:center; padding:30px 20px;'>
      <p>I'm afraid no wines currently match {$feedbackMsg}.</p>
      <p><small>Try adjusting your search terms or removing some filters to explore the database.</small></p>
    </div>";

    return; // Exit the function early so we don't print empty lists or sort text
  }

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
  } elseif ($sort=="twentyplus") {
    echo "<p><small><i>Mature wines (20+ years) organised by country and region. Then by producer and wine.</i></small></p>";
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
    if($sort=="region" || $sort=="tenyearsold" || $sort=="twentyplus") {
      if($wine["country"]!=$prevCountry) {
        echo ($prevCountry!="") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details><summary><b>".$wine["country"]."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevRegion="";
      }
      if($wine["region"]!=$prevRegion) {
        echo ($prevRegion!="") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:5px;'><details><summary><i>".$wine["region"]."</i></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
      }
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/wine.php?id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
    } elseif($sort=="producer") {
      if ($wine["producer"]!=$prevProducer) {
        echo ($prevProducer!="") ? "</ul></details><br>" : "";
        if ($wine["producer_desc"]!=null) {
          echo "<details><summary><b>".$wine["producer"]."</b></summary><hr><small>".$wine["producer_desc"]."</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details><summary><b>".$wine["producer"]."</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        }
      }
      echo "<li style='padding-left:35px;text-indent:-18px;'><a href='/wine.php?id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
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
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/wine.php?id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
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
      echo "<li style='padding-left:43px;text-indent:-18px;'><a href='/wine.php?id=".$wine["wine_id"]."'>".$wine_name."</a></li>";
    }
    // Set previous values
    $prevCountry=$wine["country"];
    $prevRegion=$wine["region"];
    $prevProducer=$wine["producer"];
    $prevVintage=$wine["vintage"];
    $prevVariety=$wine["grape"];
  }
  if($sort=="region" || $sort=="tenyearsold" || $sort=="twentyplus" || $sort=="vintage" || $sort=="variety") {
    echo "</ul></details></li></ul></details>";
  } elseif($sort=="producer") {
    echo "</ul></details>";
  }
  $result -> free_result();
  } ?>
