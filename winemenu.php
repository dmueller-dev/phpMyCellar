<?php
  session_start();

  // Check if user is not logged in
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
  }
?>

<?php
  if (empty($_GET['sort'])) {
    $sort="region";
  } else {
    $sort=$_GET['sort'];
  }
  if ($sort=="region" || $sort=="tenyearsold" || $sort=="twentyplus" || $sort=="rand") {
    $sqlOrderBy="order by regions.country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="producer") {
    $sqlOrderBy="order by producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="vintage") {
    $sqlOrderBy="order by vintage desc,regions.country asc,producer asc,region asc,subregion asc,appellation asc,vineyard asc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="variety") {
    $sqlOrderBy="order by grape asc,regions.country asc,producer asc,vintage desc,region asc,subregion asc,appellation asc,vineyard asc,name asc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="location") {
    $sqlOrderBy="order by cellar_name asc,bin_name asc,regions.country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  } elseif ($sort=="style") {
    $sqlOrderBy="order by wines_master.style asc,regions.country asc,region asc,producer asc,subregion asc,appellation asc,vineyard asc,name asc,vintage desc,storageBins.bin_name asc,bottle_formats.format asc,bottle_id asc";
  }
?>

<?php
  $page_title = 'Carte des vins - Wine menu';
  $meta_desc = 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.';
  $extra_head = '<script> // Automatically update cellar filter on new selection by user\n    function updateCellarFilter() {\n      const cellarValue = document.getElementById(\'cellarToggle\').value;\n      const url = new URL(window.location.href);\n      \n      // Set or remove the cellar parameter\n      if (cellarValue) {\n        url.searchParams.set(\'cellar\', cellarValue);\n      } else {\n        url.searchParams.delete(\'cellar\');\n      }\n      \n      // Redirect to the new URL\n      window.location.href = url.toString();\n    }\n  </script>';
  require_once 'includes/header.php';
?>

<div class="row">
  <div class="column main">
    <div class="card">
      <p align="center" style="margin-top:0;margin-bottom:0;"><small>
        Filter by cellar: 
        <select id="cellarToggle" onchange="updateCellarFilter()" style="font-family: Georgia, serif; padding: 2px;">
          <option value="">All cellars</option>
          <?php
            require "db_connect.php";
            $cellarRes = $mysqli->query("SELECT cellar_id, cellar_name FROM cellars ORDER BY cellar_name ASC");
            while($c = $cellarRes->fetch_assoc()) {
              $selected = (isset($_GET['cellar']) && $_GET['cellar'] == $c['cellar_id']) ? 'selected' : '';
              echo "<option value='{$c['cellar_id']}' $selected>{$c['cellar_name']}</option>";
            }
          ?>
        </select>
      </small></p>
    </div>
    <div class="card">
      <p align="center" style="margin-top:0;margin-bottom:0;"><small>
        Sort by:
        <?php 
          $cellarParam = !empty($_GET['cellar']) ? "&cellar=" . urlencode($_GET['cellar']) : ""; 
        ?>
        <a class="filter-nav" href="/winemenu.php?sort=region<?php echo $cellarParam; ?>">Region</a>
        <a class="filter-nav" href="/winemenu.php?sort=producer<?php echo $cellarParam; ?>">Producer</a>
        <a class="filter-nav" href="/winemenu.php?sort=vintage<?php echo $cellarParam; ?>">Vintage</a>
        <a class="filter-nav" href="/winemenu.php?sort=variety<?php echo $cellarParam; ?>">Variety</a>
        <a class="filter-nav" href="/winemenu.php?sort=style<?php echo $cellarParam; ?>">Style</a>
        <a class="filter-nav" href="/winemenu.php?sort=tenyearsold<?php echo $cellarParam; ?>">Aged 10 years</a>
        <a class="filter-nav" href="/winemenu.php?sort=twentyplus<?php echo $cellarParam; ?>">Aged 20+ years</a>
        <a class="filter-nav" href="/winemenu.php?sort=rand<?php echo $cellarParam; ?>">Random</a>
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

<?php require_once 'includes/footer.php'; ?>

<?php function getBottles($sort,$sqlOrderBy)
{
  // Establish database connection
  require "db_connect.php";
  $mysqli->query("SET NAMES utf8");

  // Handle the cellar filter
  $cellarMsg = "all cellars"; // Variable to print selected cellar name;
                              // showing all cellars by default
  $cellarConstraint = ""; // Variable for WHERE string in SQL query
  if (!empty($_GET['cellar'])) {
    $cellarId = $mysqli->real_escape_string($_GET['cellar']);
    $cellarResult = $mysqli->query("SELECT cellar_name FROM cellars WHERE cellar_id = '$cellarId'");
    if ($cellarRow = $cellarResult->fetch_assoc()) {
        $cellarMsg = $cellarRow['cellar_name'];
    }
    $cellarConstraint = " AND cellars.cellar_id = '$cellarId' ";
  }

  // Initialise loop variables
  $prevCountry = "";
  $prevRegion = "";
  $prevProducer = "";
  $prevVintage = "";
  $prevVariety = "";
  $prevLocation = "";
  $prevStyle = "";
  $prevWine = "";
  $prevFormat = "";
  $prevMasterId = "";

  // Random wine?
  $randomWineConstraint = " ";
  if ($sort=="rand") {
    $randomCellarJoins = "";
    $randomCellarWhere = "";
    if (!empty($_GET['cellar'])) {
      $randomCellarJoins = " left join storageBins sb on b2.storage_location=sb.bin_id left join cellars c on sb.cellar_id=c.cellar_id ";
      $randomCellarWhere = " and c.cellar_id = '$cellarId' ";
    }

    $randomWineConstraint = " inner join (
      select b2.wine_id
      from bottles b2
      $randomCellarJoins
      where b2.status = 'in cellar'
        and (year(curdate()) >= b2.drink_from or b2.drink_from is null)
        $randomCellarWhere
      order by rand()
      limit 1
    ) as random_wine on bottles.wine_id = random_wine.wine_id ";
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
    from bottles"
    .$randomWineConstraint
    ."left join bottle_formats on bottles.format=bottle_formats.format
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
    .$cellarConstraint
    .(
      ($sort=="tenyearsold") ? " and vintage is not null and (year(curdate())-vintage=10)" :
      (
        ($sort=="twentyplus") ? " and vintage is not null and (year(curdate())-vintage>=20)" : ""
      )
    )
    ." group by wines.wine_id, bottle_formats.format, storageBins.bin_name "
    .$sqlOrderBy
  );

  // Display message if no bottle found
  if ($result->num_rows == 0) {
    echo "<div class='card' style='text-align:center; padding:20px;'>
      <p>No bottles currently match your selection in <strong>$cellarMsg</strong>.<br>
      <small>Please adjust your filters or explore another cellar to view the collection.
      </small></p></div>";
    return; // Exit function so the rest of the code doesn't run
  }

  // Dynamic sort message
  $selectionText = ($cellarMsg == "all cellars") ? "across all cellars" : "within $cellarMsg";
  if ($sort == "region") {
      echo "<p><small><i>Wines $selectionText, arranged by country and region, then by producer and vintage.</i></small></p>";
  } elseif ($sort == "producer") {
      echo "<p><small><i>Wines $selectionText, organised by producer, followed by vintage and cuvée.</i></small></p>";
  } elseif ($sort == "vintage") {
      echo "<p><small><i>Wines $selectionText, presented by vintage, then by country and producer.</i></small></p>";
  } elseif ($sort == "variety") {
      echo "<p><small><i>Wines $selectionText, grouped by grape variety, then by country and producer.</i></small></p>";
  } elseif ($sort == "tenyearsold") {
      echo "<p><small><i>A selection of ten-year-old wines $selectionText, arranged by region and producer.</i></small></p>";
  } elseif ($sort == "twentyplus") {
      echo "<p><small><i>A selection of mature wines (20+ years) $selectionText, organised by region and vintage.</i></small></p>";
  } elseif ($sort == "location") {
      echo "<p><small><i>Current inventory $selectionText, sorted by specific bin location.</i></small></p>";
  } elseif ($sort == "style") {
      echo "<p><small><i>Wines $selectionText, categorised by style, then by country and region.</i></small></p>";
  } elseif ($sort == "rand") {
      echo "<p><small><i>A featured recommendation $selectionText. Please refresh for another suggestion.</i></small></p>";
  }

  // Print wine menu
  while ($wine = $result->fetch_assoc()) {

    // Vintage NV?
    if ($wine["vintage"]==null) {
      $wine["vintage"]="NV";
    }

    // Get wine name
    if ($wine["nameconvention"] == "vintage_name") {
      $base_name = $wine["name"];
    } elseif ($wine["nameconvention"] == "vintage_producer") {
      $base_name = $wine["producer"];
    } elseif ($wine["nameconvention"] == "vintage_producer_grape_name") {
      $base_name = $wine["producer"] . " " . $wine["grape"] . " " . $wine["name"];
    } elseif ($wine["nameconvention"] == "vintage_producer_vineyard_grape_name") {
      $base_name = $wine["producer"] . " " . $wine["vineyard"] . " " . $wine["grape"] . " " . $wine["name"];
    } elseif ($wine["nameconvention"] == "vintage_producer_vineyard_name") {
      $base_name = $wine["producer"] . " " . $wine["vineyard"] . " " . $wine["name"];
    // ...else default to vintage_producer_name:
    } else {
      $base_name = $wine["producer"] . " " . $wine["name"];
    }

    // Determine if we show the full name or a placeholder
    if ($wine["master_id"] == $prevMasterId && $sort != "rand") {
      $display_name = $wine["vintage"];
    } else {
      $display_name = $wine["vintage"] . " " . $base_name;
    }

    // Print headers depending on sort algorithm
    if($sort=="region" || $sort=="tenyearsold" || $sort=="twentyplus" || $sort=="rand") {
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
      echo (($prevWine!="") ? "</ul></details></li>" : "") . "<li style='padding-left:" . (($sort!="producer") ? "43" : "35") .
        "px;text-indent:-18px;'><details><summary style='list-style:none;'><img style='display:inline-block;vertical-align:middle;' src='/img/" .
        $wine["colour"] . "_16px.gif'>" . $display_name . "<small style='color:Gray;'> - " . $wine["numWine"] . " btl" . 
        (($wine["numWine"]>1) ? "s." : ".") . "</small></summary>";
      echo (($wine["wine_desc"]!=null) ? "<div class='winemenu_wine_desc'><hr><small>" . $wine["wine_desc"] . "</small></div>" : "");
      echo "<ul style='list-style-type:none;padding:0;margin-top:2px;margin-bottom:8px;'>";
    }

    // Print bottles
    if($wine["wine_id"]!=$prevWine || $wine["cellar_name"].$wine["bin_name"]!=$prevLocation || $wine["format"]!=$prevFormat) {
      echo "<li style='padding-left:19px;padding-top:1px;margin:0;line-height:10px;'><small style='color:Gray;'>" .
        $wine["format"] . " - " . $wine["cellar_name"] . " / " . $wine["bin_name"] . " - " . $wine["numWineBin"] . " btl" .
        (($wine["numWineBin"]>1) ? "s." : ".") . "</small></li>";
    }

    // Set previous values
    $prevCountry = $wine["country"];
    $prevRegion = $wine["region"];
    $prevProducer = $wine["producer"];
    $prevVintage = $wine["vintage"];
    $prevVariety = $wine["grape"];
    $prevLocation = $wine["cellar_name"] . $wine["bin_name"];
    $prevStyle = $wine["style"];
    $prevWine = $wine["wine_id"];
    $prevFormat = $wine["format"];
    $prevMasterId = $wine["master_id"];
  }
  if($sort=="region" || $sort=="tenyearsold" || $sort=="twentyplus" || $sort=="vintage" || $sort=="variety" || $sort=="style" || $sort=="rand") {
    echo "</ul></details></ul></details></li></ul></details>";
  } elseif($sort=="producer" || $sort=="location") {
    echo "</ul></details></li></ul>";
  }

  // Disconnect from database
  $result -> free_result();
  $mysqli -> close();
} ?>
