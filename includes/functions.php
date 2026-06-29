<?php
// Prevent direct access to this file
if (!defined('INCLUDED_VIA_APP')) {
  die('Direct access not permitted');
}

// Function to get all countries
function getCountries($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select country from countries order by country asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all regions
function getRegions($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select region_id, region, country from regions order by country asc, region asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all subregions
function getSubregions($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select subregions.subregion_id, subregions.subregion, subregions.region_id, regions.region, regions.country
          from subregions
          left join regions on subregions.region_id=regions.region_id
          order by regions.country asc, regions.region asc, subregions.subregion asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all appellations
function getAppellations($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select appellations.appellation_id, appellations.appellation, appellations.region_id, regions.region, regions.country
          from appellations
          left join regions on appellations.region_id=regions.region_id
          order by regions.country asc, regions.region asc, appellations.appellation asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all vineyards
function getVineyards($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select vineyards.vineyard_id, vineyards.vineyard, vineyards.region_id, regions.region, regions.country, appellations.appellation
          from vineyards
          left join regions on vineyards.region_id=regions.region_id
          left join appellations on vineyards.appellation_id=appellations.appellation_id
          order by regions.country asc, regions.region asc, appellations.appellation asc, vineyards.vineyard asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all producers
function getProducers($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select producers.producer_id, producers.producer, producers.region_id, regions.region, regions.country
          from producers
          left join regions on producers.region_id=regions.region_id
          order by regions.country asc, regions.region asc, producers.producer asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all wines
function getWines($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select wines.wine_id, wines.master_id, wines.vintage, wines_master.nameconvention, wines_master.name, wines_master.grape, producers.producer, regions.country, regions.region, vineyards.vineyard
          from wines
          left join wines_master on wines.master_id=wines_master.master_id
          left join producers on wines_master.producer_id=producers.producer_id
          left join regions on wines_master.region_id=regions.region_id
          left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
          order by regions.country asc, regions.region asc, producers.producer asc, wines.vintage desc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all wine masters
function getWineMasters($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select wines_master.master_id, wines_master.nameconvention, wines_master.name, wines_master.grape, producers.producer, regions.country, regions.region, vineyards.vineyard
          from wines_master
          left join producers on wines_master.producer_id=producers.producer_id
          left join regions on wines_master.region_id=regions.region_id
          left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
          order by regions.country asc, regions.region asc, producers.producer asc, wines_master.name asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all vintages
function getVintages($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select vintage from vintages order by vintage desc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all grape varieties
function getVarieties($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select grape from variety order by grape asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all colours
function getColours($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select colour from colours order by colour asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all wine styles
function getStyles($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select style from styles order by style asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all bottles currently in the cellar
function getBottlesInCellar($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select
		bottles.bottle_id,
		wines.wine_id,
		wines_master.nameconvention,
		wines.vintage,
		producers.producer,
		wines_master.grape,
		wines_master.name,
		vineyards.vineyard
	from bottles
	left join wines on bottles.wine_id=wines.wine_id
	left join wines_master on wines.master_id=wines_master.master_id
	left join producers on wines_master.producer_id=producers.producer_id
	left join variety on wines_master.grape=variety.grape
	left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
  where status='in cellar'
	order by bottles.bottle_id asc, producers.producer asc, wines_master.name asc, wines.vintage desc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all bottles
function getBottles($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select
		bottles.bottle_id,
    cellars.cellar_name,
    storageBins.bin_name,
		wines.wine_id,
		wines_master.nameconvention,
		wines.vintage,
		producers.producer,
		wines_master.grape,
		wines_master.name,
		vineyards.vineyard
	from bottles
	left join wines on bottles.wine_id=wines.wine_id
	left join wines_master on wines.master_id=wines_master.master_id
	left join producers on wines_master.producer_id=producers.producer_id
	left join variety on wines_master.grape=variety.grape
	left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
  left join storageBins on bottles.storage_location=storageBins.bin_id
  left join cellars on storageBins.cellar_id=cellars.cellar_id
	order by cellars.cellar_name asc, storageBins.bin_name asc, producers.producer asc, wines_master.name asc, wines.vintage desc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all name conventions from table displayoptions
function getNameConventions($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select nameconvention from displayoptions order by nameconvention asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get bottle formats
function getFormats($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select format, format_desc from bottle_formats";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get stores
function getStores($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select store_id, store_name, country from stores order by country asc, store_name asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get storage locations
function getStorageLocations($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select storageBins.bin_id, storageBins.bin_name, cellars.cellar_name
    from storageBins
    left join cellars on storageBins.cellar_id=cellars.cellar_id
    order by cellars.cellar_name asc, storageBins.bin_name asc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get all tasting notes
function getTastingNotes($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select tnotes.note_id, tnotes.user_id, tnotes.status, tnotes.tasting_date, tnotes.dmpts, tnotes.blind,
    wines.wine_id, wines.master_id, wines.vintage, wines_master.nameconvention, wines_master.name,
    wines_master.grape, producers.producer, regions.country, regions.region, vineyards.vineyard,
    users.initials
          from tnotes
          left join users on tnotes.user_id=users.user_id
          left join wines on tnotes.wine_id=wines.wine_id
          left join wines_master on wines.master_id=wines_master.master_id
          left join producers on wines_master.producer_id=producers.producer_id
          left join regions on wines_master.region_id=regions.region_id
          left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
    order by tnotes.tasting_date desc, regions.country asc, producers.producer asc, wines_master.grape asc,
      vineyards.vineyard asc, wines_master.name asc, wines.vintage desc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function for wine names
function getWineName($nameconvention,$vintage,$name,$producer,$grape,$vineyard) {
  // Non-vintage?
  if ($vintage=="") { $vintage="NV"; }
  // Get wine name
  if ($nameconvention=="vintage_name") {
    $wine_name=$vintage." ".$name;
  } elseif ($nameconvention=="vintage_producer") {
    $wine_name=$vintage." ".$producer;
  } elseif ($nameconvention=="vintage_producer_grape_name") {
    $wine_name=$vintage." ".$producer." ".$grape." ".$name;
  } elseif ($nameconvention=="vintage_producer_vineyard_grape_name") {
    $wine_name=$vintage." ".$producer." ".$vineyard." ".$grape." ".$name;
  } elseif ($nameconvention=="vintage_producer_vineyard_name") {
    $wine_name=$vintage." ".$producer." ".$vineyard." ".$name;
  // ...else vintage_producer_name as default:
  } else {
    $wine_name=$vintage." ".$producer." ".$name;
  }
  // Return wine name
  return $wine_name;
}

// Function to generate Wine-Searcher search URL from a full wine name
function getWineSearcherUrl($wine_name) {
  return "https://www.wine-searcher.com/find/" . urlencode($wine_name);
}

// Function to get country details
function getCountryDetails($conn, $country) {
  $sql = "select * from countries where country = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $country);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get region details
function getRegionDetails($conn, $region_id) {
  $sql = "select * from regions where region_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $region_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get subregion details
function getSubregionDetails($conn, $subregion_id) {
  $sql = "select * from subregions where subregion_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $subregion_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get appellation details
function getAppellationDetails($conn, $appellation_id) {
  $sql = "select * from appellations where appellation_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $appellation_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get vineyard details
function getVineyardDetails($conn, $vineyard_id) {
  $sql = "select * from vineyards where vineyard_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $vineyard_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get producer details
function getProducerDetails($conn, $producer_id) {
  $sql = "select * from producers where producer_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $producer_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get wine details
function getMasterDetails($conn, $master_id) {
  $sql = "select * from wines_master where master_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $master_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get wine details
function getWineDetails($conn, $wine_id) {
  $sql = "select * from wines where wine_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $wine_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get bottle details
function getBottleDetails($conn, $bottle_id) {
  $sql = "select
		bottles.bottle_id,
		wines.wine_id,
		wines_master.nameconvention,
		wines.vintage,
		producers.producer,
		wines_master.grape,
		wines_master.name,
		vineyards.vineyard,
    bottles.format,
    bottles.storage_location,
    bottles.purchased_from,
    bottles.purchase_date,
    bottles.purchase_price,
    bottles.arrival_date,
    bottles.status,
    bottles.drink_from,
    bottles.drink_through,
    bottles.consumption_date,
    bottles.consumption_note,
    bottles.for_sale,
    bottles.note_id
	from bottles
	left join wines on bottles.wine_id=wines.wine_id
	left join wines_master on wines.master_id=wines_master.master_id
	left join producers on wines_master.producer_id=producers.producer_id
	left join variety on wines_master.grape=variety.grape
	left join vineyards on wines_master.vineyard_id=vineyards.vineyard_id
  where bottle_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $bottle_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to get tasting note details
function getNoteDetails($conn, $note_id) {
  $sql = "select * from tnotes where note_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $note_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to check if country exists
function checkCountryExists($conn, $country) {
  $sql = "select 1 from countries where country = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $country);
  $stmt->execute();
  return $stmt->get_result()->num_rows > 0;
}

// Function to update country
function updateCountry($conn, $country, $country_desc) {
  if (!checkCountryExists($conn, $country)) {
    return false;
  }
  $sql = "update countries set country_desc = ? where country = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $country_desc, $country);
  return $stmt->execute();
}

// Function to update region
function updateRegion($conn, $region_id, $region, $country, $region_desc) {
  if (!checkCountryExists($conn, $country)) {
    return false;
  }
  $sql = "update regions set region = ?, country = ?, region_desc = ? where region_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sssi", $region, $country, $region_desc, $region_id);
  return $stmt->execute();
}

// Function to update subregion
function updateSubregion($conn, $subregion_id, $region_id, $subregion, $subregion_desc) {
  $sql = "update subregions set subregion = ?, region_id = ?, subregion_desc = ? where subregion_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sisi", $subregion, $region_id, $subregion_desc, $subregion_id);
  return $stmt->execute();
}

// Function to update appellation
function updateAppellation($conn, $appellation_id, $region_id, $appellation, $appellation_desc) {
  $sql = "update appellations set appellation = ?, region_id = ?, appellation_desc = ? where appellation_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sisi", $appellation, $region_id, $appellation_desc, $appellation_id);
  return $stmt->execute();
}

// Function to update vineyard
function updateVineyard($conn, $vineyard_id, $appellation_id = null, $region_id, $vineyard, $vineyard_desc) {
  if ($appellation_id=="") { $appellation_id=null; }
  $sql = "update vineyards set vineyard = ?, region_id = ?, appellation_id = ?, vineyard_desc = ? where vineyard_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("siisi", $vineyard, $region_id, $appellation_id, $vineyard_desc, $vineyard_id);
  return $stmt->execute();
}

function updateProducer($conn, $producer_id, $region_id, $producer, $address, $producer_desc) {
  $sql = "update producers set producer = ?, region_id = ?, address = ?, producer_desc = ? where producer_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sissi", $producer, $region_id, $address, $producer_desc, $producer_id);
  return $stmt->execute();
}

function updateWine($conn, $wine_id, $master_id, $vintage, $wine_desc, $ct_id) {
  if ($vintage==0) { $vintage=null; }
  if (empty($ct_id) || $ct_id=="") { $ct_id=null; }
  $sql = "update wines set master_id = ?, vintage = ?, wine_desc = ?, ct_id = ? where wine_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iisii", $master_id, $vintage, $wine_desc, $ct_id, $wine_id);
  return $stmt->execute();
}

function updateMaster($conn, $master_id, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id, $name, $cuvee_yn, $variety, $colour, $style, $nameconvention) {
  if ($subregion_id=="") { $subregion_id=null; }
  if ($appellation_id=="") { $appellation_id=null; }
  if ($vineyard_id=="") { $vineyard_id=null; }
  $sql = "update wines_master set producer_id = ?, region_id = ?, subregion_id = ?, appellation_id = ?, vineyard_id = ?, name = ?, cuvee_yn = ?, grape = ?, colour = ?, style = ?, nameconvention = ? where master_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iiiiissssssi", $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id, $name, $cuvee_yn, $variety, $colour, $style, $nameconvention, $master_id);
  return $stmt->execute();
}

function updateBottle($conn, $bottle_id, $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date = null, $consumption_note = null, $for_sale, $note_id = null) {

  if (empty($bin_id) || $bin_id=="") { $bin_id=null; }
  if (empty($purchase_price) || $purchase_price=="") { $purchase_price=null; }
  if (empty($arrival_date) || $arrival_date=="") { $arrival_date=null; }
  if (empty($drink_from) || $drink_from=="") { $drink_from=null; }
  if (empty($drink_through) || $drink_through=="") { $drink_through=null; }
  if (empty($consumption_date) || $consumption_date=="") { $consumption_date=null; }
  if (empty($consumption_note) || $consumption_note=="") { $consumption_note=null; }
  if (empty($note_id) || $note_id=="") { $note_id=null; }

  $sql = "update bottles set wine_id = ?, format = ?, storage_location = ?, purchased_from = ?, purchase_date = ?, purchase_price = ?, arrival_date = ?, status = ?, drink_from = ?, drink_through = ?, consumption_date = ?, consumption_note = ?, for_sale = ?, note_id = ? where bottle_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("isiisdssiisssii", $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id, $bottle_id);
  return $stmt->execute();
}

// Mark bottle as consumed
function markBottleAsConsumed($conn, $bottle_id, $consumption_date, $note_id = null) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  if (empty($note_id) || $note_id === "") { $note_id = null; }

  if ($note_id !== null) {
    $sql = "UPDATE bottles SET status = 'consumed', consumption_date = ?, storage_location = NULL, note_id = ? WHERE bottle_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      return false;
    }
    $stmt->bind_param("sii", $consumption_date, $note_id, $bottle_id);
  } else {
    $sql = "UPDATE bottles SET status = 'consumed', consumption_date = ?, storage_location = NULL WHERE bottle_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      return false;
    }
    $stmt->bind_param("si", $consumption_date, $bottle_id);
  }
  return $stmt->execute();
}

function updateTastingNote($conn, $note_id, $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $dmpts = null, $wset_balance = null, $wset_length = null, $wset_intensity = null, $wset_complexity = null, $wsetpts = null, $starpts = null, $drink_from = null, $drink_through = null, $blind, $status, $img = null, $img_class = null, $favourite = 'no') {

  if (empty($dmpts) || $dmpts=="") { $dmpts=null; }
  if (empty($wset_balance) || $wset_balance=="") { $wset_balance=null; }
  if (empty($wset_length) || $wset_length=="") { $wset_length=null; }
  if (empty($wset_intensity) || $wset_intensity=="") { $wset_intensity=null; }
  if (empty($wset_complexity) || $wset_complexity=="") { $wset_complexity=null; }
  if (empty($wsetpts) || $wsetpts=="") { $wsetpts=null; }
  if (empty($starpts) || $starpts=="") { $starpts=null; }
  if (empty($drink_from) || $drink_from=="") { $drink_from=null; }
  if (empty($drink_through) || $drink_through=="") { $drink_through=null; }
  if (empty($img) || $img=="") { $img=null; }
  if (empty($img_class) || $img_class=="") { $img_class=null; }

  $sql = "update tnotes set wine_id = ?, tasting_date = ?, user_id = ?, tasting_note = ?, flawed_yn = ?, dmpts = ?, wset_balance = ?, wset_length = ?, wset_intensity = ?, wset_complexity = ?, wsetpts = ?, starpts = ?, drinkwindow_min = ?, drinkwindow_max = ?, status = ?, blind = ?, img = ?, img_class = ?, favourite = ? where note_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("isissidddddiiisssssi", $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $dmpts, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $starpts, $drink_from, $drink_through, $status, $blind, $img, $img_class, $favourite, $note_id);
  return $stmt->execute();
}

// Function to insert new country
function insertCountry($conn, $country, $country_desc = null) {
  if ($country_desc === null) {
    // SQL without country_desc
    $sql = "INSERT INTO countries (country) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $country);
  } else {
    // SQL with country_desc
    if ($country_desc=='' || empty($country_desc)) { $country_desc = null; }
    $sql = "INSERT INTO countries (country, country_desc) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $country, $country_desc);
  }
  return $stmt->execute();
}

// Function to insert new region
function insertRegion($conn, $region, $country, $region_desc = null) {
  if (!checkCountryExists($conn, $country)) {
    return false;
  }
  if ($region_desc === null) {
    // SQL without region_desc
    $sql = "INSERT INTO regions (region, country) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $region, $country);
  } else {
    // SQL with region_desc
    if ($region_desc=='' || empty($region_desc)) { $region_desc = null; }
    $sql = "INSERT INTO regions (region, country, region_desc) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $region, $country, $region_desc);
  }
  return $stmt->execute();
}

// Function to insert new subregion
function insertSubregion($conn, $subregion, $region_id, $subregion_desc = null) {
  if ($subregion_desc === null) {
    // SQL without subregion_desc
    $sql = "INSERT INTO subregions (subregion, region_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $subregion, $region_id);
  } else {
    // SQL with subregion_desc
    if ($subregion_desc=='' || empty($subregion_desc)) { $subregion_desc = null; }
    $sql = "INSERT INTO subregions (subregion, region_id, subregion_desc) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $subregion, $region_id, $subregion_desc);
  }
  return $stmt->execute();
}

// Function to insert new appellation
function insertAppellation($conn, $appellation, $region_id, $appellation_desc = null) {
  if ($appellation_desc === null) {
    // SQL without appellation_desc
    $sql = "INSERT INTO appellations (appellation, region_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $appellation, $region_id);
  } else {
    // SQL with appellation_desc
    if ($appellation_desc=='' || empty($appellation_desc)) { $appellation_desc = null; }
    $sql = "INSERT INTO appellations (appellation, region_id, appellation_desc) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $appellation, $region_id, $appellation_desc);
  }
  return $stmt->execute();
}

// Function to insert new vineyard
function insertVineyard($conn, $vineyard, $region_id, $appellation_id = null, $vineyard_desc = null) {
  if ($vineyard_desc === null) {
    if ($appellation_id=='' || empty($appellation_id)) { $appellation_id = null; }
    // SQL without vineyard_desc
    $sql = "INSERT INTO vineyards (vineyard, region_id, appellation_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $vineyard, $region_id, $appellation_id);
  } else {
    // SQL with vineyard_desc
    if ($appellation_id=='' || empty($appellation_id)) { $appellation_id = null; }
    if ($vineyard_desc=='' || empty($vineyard_desc)) { $vineyard_desc = null; }
    $sql = "INSERT INTO vineyards (vineyard, region_id, appellation_id, vineyard_desc) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siis", $vineyard, $region_id, $appellation_id, $vineyard_desc);
  }
  return $stmt->execute();
}

// Function to insert new producer
function insertProducer($conn, $producer, $region_id, $address = null, $producer_desc = null) {
  if ($address=='' || empty($address)) { $address = null; }
  if ($producer_desc=='' || empty($producer_desc)) { $producer_desc = null; }
  $sql = "INSERT INTO producers (producer, region_id, address, producer_desc) VALUES (?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("siss", $producer, $region_id, $address, $producer_desc);
  return $stmt->execute();
}

// Function to insert new wine master
function insertWineMaster($conn, $name, $nameconvention, $producer_id, $region_id, $subregion_id = null, $appellation_id = null, $vineyard_id = null, $grape, $cuvee_yn, $colour, $style) {
  if ($subregion_id=='' || empty($subregion_id)) { $subregion_id = null; }
  if ($appellation_id=='' || empty($appellation_id)) { $appellation_id = null; }
  if ($vineyard_id=='' || empty($vineyard_id)) { $vineyard_id = null; }
  $sql = "INSERT INTO wines_master (name, nameconvention, producer_id, region_id, subregion_id, appellation_id, vineyard_id, grape, cuvee_yn, colour, style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ssiiiiissss", $name, $nameconvention, $producer_id, $region_id, $subregion_id, $appellation_id, $vineyard_id, $grape, $cuvee_yn, $colour, $style);
  return $stmt->execute();
}

// Function to insert new wine
function insertWine($conn, $master_id, $ct_id = null, $vintage = null, $wine_desc = null) {
  if ($ct_id=='' || empty($ct_id)) { $ct_id = null; }
  if ($vintage==0 || $vintage=='' || empty($vintage)) { $vintage = null; }
  if ($wine_desc=='' || empty($wine_desc)) { $wine_desc = null; }
  $sql = "INSERT INTO wines (master_id, ct_id, vintage, wine_desc) VALUES (?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iiis", $master_id, $ct_id, $vintage, $wine_desc);
  return $stmt->execute();
}

// Function to insert new bottle
function insertBottle($conn, $wine_id, $format, $bin_id = null, $store_id, $purchase_date, $purchase_price = null, $arrival_date = null, $status, $drink_from = null, $drink_through = null, $consumption_date = null, $consumption_note = null, $for_sale, $note_id = null) {
  if ($bin_id=='' || empty($bin_id)) { $bin_id = null; }
  if ($purchase_price=='' || empty($purchase_price)) { $purchase_price = null; }
  if ($arrival_date=='' || empty($arrival_date)) { $arrival_date = null; }
  if ($drink_from=='' || empty($drink_from)) { $drink_from = null; }
  if ($drink_through=='' || empty($drink_through)) { $drink_through = null; }
  if ($consumption_date=='' || empty($consumption_date)) { $consumption_date = null; }
  if ($consumption_note=='' || empty($consumption_note)) { $consumption_note = null; }
  if ($note_id=='' || empty($note_id)) { $note_id = null; }
  $sql = "INSERT INTO bottles (wine_id, format, storage_location, purchased_from, purchase_date, purchase_price, arrival_date, status, drink_from, drink_through, consumption_date, consumption_note, for_sale, note_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("isiisdssiisssi", $wine_id, $format, $bin_id, $store_id, $purchase_date, $purchase_price, $arrival_date, $status, $drink_from, $drink_through, $consumption_date, $consumption_note, $for_sale, $note_id);
  return $stmt->execute();
}

// Function to insert a new tasting note
function insertTastingNote($conn, $bottle_id = null, $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $dmpts, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $starpts, $drinkwindow_min = null, $drinkwindow_max = null, $status, $blind, $img = null, $img_class = null, $favourite = 'no') {
  // Check optional inputs
  if ($drinkwindow_min=="") { $drinkwindow_min=null; }
  if ($drinkwindow_max=="") { $drinkwindow_max=null; }
  if ($img=="") { $img=null; $img_class=null; }
    
  $sql = "INSERT INTO tnotes (wine_id, user_id, tasting_date, tasting_note, flawed_yn, dmpts, wset_balance, wset_length, wset_intensity, wset_complexity, wsetpts, starpts, drinkwindow_min, drinkwindow_max, status, blind, img, img_class, favourite) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
  $stmt = $conn->prepare($sql);
    
  // Create an array of parameters
  $params = [$wine_id, $user_id, $tasting_date, $tasting_note, $flawed_yn, $dmpts, $wset_balance, $wset_length, $wset_intensity, $wset_complexity, $wsetpts, $starpts, $drinkwindow_min, $drinkwindow_max, $status, $blind, $img, $img_class, $favourite];
    
  // Create a types string
  $types = '';
  foreach ($params as $param) {
      if (is_null($param)) {
          $types .= 's'; // Treat null as string
      } elseif (is_int($param)) {
          $types .= 'i';
      } elseif (is_string($param)) {
          $types .= 's';
      } elseif (is_double($param)) {
          $types .= 'd';
      }
  }
    
  // Bind parameters dynamically
  $stmt->bind_param($types, ...$params);
    
  return $stmt->execute();
}

// Function to generate CSRF token
function generateCSRFToken() {
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

// Function to validate CSRF token
function validateCSRFToken($token) {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to sanitize input
function sanitizeInput($input) {
  return strip_tags(trim($input),'<a><b><br><em><h1><h2><h3><h4><i><img><p><small><strong><u><ul><ol><li>');
}

// Function to validate and sanitize a redirect URL to prevent Open Redirect vulnerabilities
function getSafeRedirectUrl($url) {
  if (empty($url)) {
    return 'index.php';
  }
  
  // Parse the URL
  $parts = parse_url($url);
  
  // To be safe, we only allow local redirects (no host, no scheme)
  if (isset($parts['host']) || isset($parts['scheme'])) {
    return 'index.php';
  }
  
  // Ensure it doesn't start with // or \ to prevent protocol-relative redirects
  if (preg_match('/^\/\//', $url) || preg_match('/^\\\\/', $url)) {
    return 'index.php';
  }
  
  // Ensure it doesn't contain a colon (prevent javascript: scheme, etc.)
  if (strpos($url, ':') !== false) {
    return 'index.php';
  }
  
  return $url;
}

// Function to validate country input
function validateCountryInput($country, $country_desc) {
  $errors = [];
    
  if (empty($country) || strlen($country) > 100) {
    $errors[] = "Country name is required and must be less than 100 characters.";
  }
    
  if (!is_string($country) || $country == "") {
    $errors[] = "Invalid country string.";
  }
    
  if (strlen($country_desc) > 1500) {
    $errors[] = "Country description must be less than 1500 characters.";
  }
    
  return $errors;
}

// Function to validate region input
function validateRegionInput($region, $country, $region_desc) {
  $errors = [];
    
  if (empty($region) || strlen($region) > 100) {
    $errors[] = "Region name is required and must be less than 100 characters.";
  }
    
  if (!is_string($country) || $country == "") {
    $errors[] = "Invalid country string.";
  }
    
  if (strlen($region_desc) > 1500) {
    $errors[] = "Region description must be less than 1500 characters.";
  }
    
  return $errors;
}

// Function to validate subregion input
function validateSubregionInput($subregion, $region_id, $subregion_desc) {
  $errors = [];
    
  if (empty($subregion) || strlen($subregion) > 100) {
    $errors[] = "Subregion name is required and must be less than 100 characters.";
  }
    
  if (!is_numeric($region_id) || $region_id == "") {
    $errors[] = "Invalid region ID.";
  }
    
  if (strlen($subregion_desc) > 1500) {
    $errors[] = "Subregion description must be less than 1500 characters.";
  }
    
  return $errors;
}

// Function to validate appellation input
function validateAppellationInput($appellation, $region_id, $appellation_desc) {
  $errors = [];
    
  if (empty($appellation) || strlen($appellation) > 100) {
    $errors[] = "Appellation name is required and must be less than 100 characters.";
  }
    
  if (!is_numeric($region_id) || $region_id == "") {
    $errors[] = "Invalid region ID.";
  }
    
  if (strlen($appellation_desc) > 1500) {
    $errors[] = "Appellation description must be less than 1500 characters.";
  }
    
  return $errors;
}

// Function to validate vineyard input
function validateVineyardInput($vineyard, $appellation_id, $region_id, $vineyard_desc) {
  $errors = [];
    
  if (empty($vineyard) || strlen($vineyard) > 100) {
    $errors[] = "Vineyard name is required and must be less than 100 characters.";
  }
    
  if (!is_numeric($region_id) || $region_id == "") {
    $errors[] = "Invalid region ID.";
  }
    
  if ($appellation_id !== "" && !empty($appellation_id) && !is_numeric($appellation_id)) {
    $errors[] = "Invalid appellation ID.";
  }
    
  if (strlen($vineyard_desc) > 1500) {
    $errors[] = "Vineyard description must be less than 1500 characters.";
  }
    
  return $errors;
}

// Function to validate subregion input
function validateProducerInput($producer, $region_id, $address, $producer_desc) {
  $errors = [];
    
  if (empty($producer) || strlen($producer) > 100) {
    $errors[] = "Producer name is required and must be less than 100 characters.";
  }
    
  if (!is_numeric($region_id) || $region_id == "") {
    $errors[] = "Invalid region ID.";
  }
    
  if (strlen($address) > 200) {
    $errors[] = "Producer address must be less than 200 characters.";
  }

  if (strlen($producer_desc) > 1500) {
    $errors[] = "Producer description must be less than 1500 characters.";
  }
    
  return $errors;
}

// Function to validate wine input
function validateWineInput($wine_id, $master_id, $vintage, $wine_desc, $ct_id) {
  $errors = [];

  if (!is_numeric($master_id) || $master_id == "") {
    $errors[] = "Invalid master ID.";
  }
    
  if (!is_numeric($wine_id) || $wine_id == "") {
    $errors[] = "Invalid wine ID.";
  }

  if ($vintage != null && $vintage != "") {
    if (!is_numeric($vintage) || $vintage < 1750 || $vintage > 2100) {
      $errors[] = "Invalid vintage.";
    }
  }

  if ($ct_id != null && $ct_id != "") {
    if (!is_numeric($ct_id) || $ct_id < 0) {
      $errors[] = "Invalid CellarTracker ID.";
    }
  }

  if (strlen($wine_desc) > 2000) {
    $errors[] = "Wine description must be less than 2000 characters.";
  }
    
  return $errors;
}

// Function to validate master input
function validateMasterInput($conn, $master_id, $producer_id, $region_id, $subregion_id = null, $appellation_id = null, $vineyard_id = null) {
  $errors = [];
    
  if (!is_numeric($master_id) || $master_id == "") {
    $errors[] = "Invalid master ID.";
  }
    
  if (!is_numeric($producer_id) || $producer_id == "") {
    $errors[] = "Invalid producer ID.";
  }

  if (!is_numeric($region_id) || $region_id == "") {
    $errors[] = "Invalid region ID.";
  }

  if ($subregion_id != null && $subregion_id != "") {
    if (!is_numeric($subregion_id)) {
      $errors[] = "Invalid subregion ID.";
    } else {
      $sql = "select region_id from subregions where subregion_id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $subregion_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $check = $result->fetch_assoc();
      if ($check && $check['region_id'] != $region_id) {
        $errors[] = "Subregion not in selected region. Check IDs.";
      }
      $stmt->close();
    }
  }

  if ($appellation_id != null && $appellation_id != "") {
    if (!is_numeric($appellation_id)) {
      $errors[] = "Invalid appellation ID.";
    } else {
      $sql = "select region_id from appellations where appellation_id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $appellation_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $check = $result->fetch_assoc();
      if ($check && $check['region_id'] != $region_id) {
        $errors[] = "Appellation not in selected region. Check IDs.";
      }
      $stmt->close();
    }
  }

  if ($vineyard_id != null && $vineyard_id != "") {
    if (!is_numeric($vineyard_id)) {
      $errors[] = "Invalid vineyard ID.";
    } else {
      $sql = "select region_id, appellation_id from vineyards where vineyard_id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $vineyard_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $check = $result->fetch_assoc();
      if (($check && $check['region_id'] != $region_id) || ($appellation_id != null && $appellation_id != "" && $check['appellation_id'] != $appellation_id)) {
        $errors[] = "Vineyard not in selected region or appellation. Check IDs.";
      }
      $stmt->close();
    }
  }
    
  return $errors;
}

// Function to validate bottle input
function validateBottleInput($bottle_id, $wine_id, $format, $bin_id = null, $store_id, $purchase_date, $purchase_price = null, $arrival_date = null, $status, $drink_from = null, $drink_through = null, $consumption_date = null, $consumption_note = null, $for_sale, $note_id = null) {
  $errors = [];

  if ($bin_id=="") { $bin_id=null; }
  if ($purchase_price=="") { $purchase_price=null; }
  if ($arrival_date=="") { $arrival_date=null; }
  if ($drink_from=="") { $drink_from=null; }
  if ($drink_through=="") { $drink_through=null; }
  if ($consumption_date=="") { $consumption_date=null; }
  if ($consumption_note=="") { $consumption_note=null; }
  if ($note_id=="") { $note_id=null; }

  if (!is_numeric($bottle_id) || $bottle_id == "") {
    $errors[] = "Invalid bottle ID.";
  }

  if (!is_numeric($wine_id) || $wine_id == "") {
    $errors[] = "Invalid wine ID.";
  }
    
  if (empty($format) || strlen($format) > 7) {
    $errors[] = "Bottle format is required and must be less than 7 characters.";
  }

  if ($bin_id != null && $bin_id != "") {
    if (!is_numeric($bin_id) || $bin_id == "") {
      $errors[] = "Invalid bin ID.";
    }
  }

  if (!is_numeric($store_id) || $store_id == "") {
    $errors[] = "Invalid store ID.";
  }

  if ($drink_from != "" && $drink_from != null && !is_numeric($drink_from)) {
    $errors[] = "Drink from must be numeric.";
  } elseif ($drink_through != "" && $drink_through != null && !is_numeric($drink_through)) {
    $errors[] = "Drink through must be numeric.";
  } elseif ($drink_from!="" && $drink_through!="" && $drink_from>$drink_through) {
    $errors[] = "Start of drinking window must be before end of drinking window.";
  }

  if (strlen($consumption_note) > 1500) {
    $errors[] = "Consumption note must be less than 1500 characters.";
  }

  if ($note_id != null && $note_id != "") {
    if (!is_numeric($note_id) || $note_id == "") {
      $errors[] = "Invalid tasting note ID.";
    }
  }
    
  return $errors;
}

// Function to validate image input
function validateImageInput($img, $img_class) {
  $errors = [];
    
  if (!empty($img) && empty($img_class)) {
    $errors[] = "Image class is required when posting an image.";
  }
    
  if (!is_string($img)) {
    $errors[] = "Invalid image file.";
  }
    
  return $errors;
}

// Function to validate drinking window input
function validateDrinkDatesInput($drink_from, $drink_through) {
  $errors = [];

  if ($drink_from!="" && !is_numeric($drink_from)) {
    $errors[] = "Drink from must be numeric.";
  } elseif ($drink_through!="" && !is_numeric($drink_through)) {
    $errors[] = "Drink through must be numeric.";
  } elseif ($drink_from!="" && $drink_through!="" && $drink_from>$drink_through) {
    $errors[] = "Start of drinking window must be before end of drinking window.";
  }
    
  return $errors;
}

// Function to validate tasting note input
function validateNoteInput($note_id, $wine_id, $tasting_date, $user_id, $tasting_note, $flawed_yn, $dmpts = null, $wset_balance = null, $wset_length = null, $wset_intensity = null, $wset_complexity = null, $wsetpts = null, $starpts = null, $drink_from = null, $drink_through = null, $blind, $status, $img = null, $img_class = null, $favourite = 'no') {
  $errors = [];

  if ($dmpts=="") { $dmpts=null; }
  if ($wset_balance=="") { $wset_balance=null; }
  if ($wset_length=="") { $wset_length=null; }
  if ($wset_intensity=="") { $wset_intensity=null; }
  if ($wset_complexity=="") { $wset_complexity=null; }
  if ($wsetpts=="") { $wsetpts=null; }
  if ($starpts=="") { $starpts=null; }
  if ($drink_from=="") { $drink_from=null; }
  if ($drink_through=="") { $drink_through=null; }
  if ($img=="") { $img=null; }
  if ($img_class=="") { $img_class=null; }

  if ($note_id != null && $note_id != "") {
    if (!is_numeric($note_id) || $note_id == "") {
      $errors[] = "Invalid tasting note ID.";
    }
  }

  if (!is_string($status)) {
    $errors[] = "Status must be a string.";
  }

  if (!is_numeric($wine_id) || $wine_id == "") {
    $errors[] = "Invalid wine ID.";
  }

  if (!is_numeric($user_id) || $user_id == "") {
    $errors[] = "Invalid user ID.";
  }

  if (strlen($tasting_note) > 2000) {
    $errors[] = "Tasting note must be less than 2000 characters.";
  }

  if ($favourite !== 'yes' && $favourite !== 'no') {
    $errors[] = "Favourite must be either 'yes' or 'no'.";
  }
    
  return $errors;
}

// Function to render comments for a specific entity (blog, tnote, wine)
function renderComments($conn, $id, $type) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $table_prefix = "";
  $id_column = "";
  if ($type == 'blog') {
    $table_prefix = "x_comments_blogposts";
    $id_column = "blog_id";
  } elseif ($type == 'tnote') {
    $table_prefix = "x_comments_tnotes";
    $id_column = "note_id";
  } elseif ($type == 'wine') {
    $table_prefix = "x_comments_wines";
    $id_column = "wine_id";
  } else {
    throw new Exception("Invalid comment type");
  }

  $sql = "select * from $table_prefix
            left join comments on $table_prefix.comment_id=comments.comment_id
            left join users on comments.user_id=users.user_id
          where $table_prefix.$id_column=?
          order by comments.pub_time desc";
  
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result && mysqli_num_rows($result) != 0) {
    while ($comments = $result->fetch_assoc()) {
      echo "<div class='card' id='comment-".$comments["comment_id"]."'><p style='font-size:small;'><b>".$comments["displayname"]."</b>, ".date_format(date_create($comments["pub_time"]),"l, j F Y H:i:s").":</p><hr><p style='font-size:small;'>".$comments["content"]."</p></div>";
    }
  }
  $stmt->close();
  if ($result) {
    $result->free_result();
  }
}

// Function to render blog references for an entity (tnote, wine)
function renderBlogReferences($conn, $id, $type, $title) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $table_prefix = "";
  $id_column = "";
  if ($type == 'tnote') {
    $table_prefix = "x_blog_tnotes";
    $id_column = "note_id";
  } elseif ($type == 'wine') {
    $table_prefix = "x_blog_wines";
    $id_column = "wine_id";
  } else {
    throw new Exception("Invalid blog reference type");
  }

  $sql = "select * from $table_prefix
            left join blogposts on $table_prefix.blog_id=blogposts.blog_id
          where $table_prefix.$id_column=? and blogposts.status='published'
          order by blogposts.pub_date desc";
  
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result && mysqli_num_rows($result) != 0) {
    echo "<div class='card'><h3>$title</h3><p><ul style='list-style-type:none;padding:0;margin:0;'>";
    while ($blog = $result->fetch_assoc()) {
      echo "<li>".date_format(date_create($blog["pub_date"]),"d M Y").": <a href='/blogpost.php?id=".$blog['blog_id']."'>".$blog["title"]."</a></li>";
    }
    echo "</ul></p></div>";
  }
  $stmt->close();
  if ($result) {
    $result->free_result();
  }
}

// Function to validate blogpost input
function validateBlogpostInput($title, $content, $status) {
  $errors = [];

  if (empty($title) || strlen($title) > 255) {
    $errors[] = "Title is required and must be less than 255 characters.";
  }

  if (empty($content)) {
    $errors[] = "Content is required.";
  }

  if (!is_string($status)) {
    $errors[] = "Status must be a string.";
  }

  return $errors;
}

// Function to insert a new blogpost
function insertBlogpost($conn, $user_id, $pub_date, $title, $content, $status) {
  $sql = "INSERT INTO blogposts (user_id, pub_date, title, content, status) VALUES (?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("issss", $user_id, $pub_date, $title, $content, $status);
  return $stmt->execute();
}

// Function to get all blogposts
function getBlogposts($conn) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }

  $sql = "select blogposts.blog_id, blogposts.user_id, blogposts.pub_date, blogposts.title, blogposts.status, users.displayname 
          from blogposts 
          left join users on blogposts.user_id=users.user_id
          order by blogposts.pub_date desc, blogposts.blog_id desc";
  $result = $conn->query($sql);

  if ($result === false) {
    throw new Exception("Query failed: " . $conn->error);
  }

  if (method_exists($result, 'fetch_all')) {
    return $result->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }
}

// Function to get blogpost details
function getBlogpostDetails($conn, $blog_id) {
  $sql = "select * from blogposts where blog_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $blog_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

// Function to update a blogpost
function updateBlogpost($conn, $blog_id, $pub_date, $title, $content, $status) {
  $sql = "UPDATE blogposts SET pub_date = ?, title = ?, content = ?, status = ? WHERE blog_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ssssi", $pub_date, $title, $content, $status, $blog_id);
  return $stmt->execute();
}

// Check if user is subscribed to an item (wine or tnote)
function isSubscribed($conn, $user_id, $item_id, $item_type) {
  if (!($conn instanceof mysqli)) {
    return false;
  }
  try {
    $stmt = $conn->prepare("SELECT 1 FROM subscriptions WHERE user_id = ? AND item_id = ? AND item_type = ?");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param("iis", $user_id, $item_id, $item_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscribed = ($result && $result->num_rows > 0);
    $stmt->close();
    return $subscribed;
  } catch (Throwable $e) {
    return false;
  }
}

// Toggle a user's subscription to an item (returns 'subscribed' or 'unsubscribed')
function toggleSubscription($conn, $user_id, $item_id, $item_type) {
  if (!($conn instanceof mysqli)) {
    return 'unsubscribed';
  }
  try {
    if (isSubscribed($conn, $user_id, $item_id, $item_type)) {
      $stmt = $conn->prepare("DELETE FROM subscriptions WHERE user_id = ? AND item_id = ? AND item_type = ?");
      if (!$stmt) {
        return 'unsubscribed';
      }
      $stmt->bind_param("iis", $user_id, $item_id, $item_type);
      $stmt->execute();
      $stmt->close();
      return 'unsubscribed';
    } else {
      $stmt = $conn->prepare("INSERT IGNORE INTO subscriptions (user_id, item_id, item_type) VALUES (?, ?, ?)");
      if (!$stmt) {
        return 'unsubscribed';
      }
      $stmt->bind_param("iis", $user_id, $item_id, $item_type);
      $stmt->execute();
      $stmt->close();
      return 'subscribed';
    }
  } catch (Throwable $e) {
    return 'unsubscribed';
  }
}

// Silently auto-subscribe a user when they comment
function autoSubscribe($conn, $user_id, $item_id, $item_type) {
  if (!($conn instanceof mysqli)) {
    return;
  }
  try {
    $stmt = $conn->prepare("INSERT IGNORE INTO subscriptions (user_id, item_id, item_type) VALUES (?, ?, ?)");
    if (!$stmt) {
      return;
    }
    $stmt->bind_param("iis", $user_id, $item_id, $item_type);
    $stmt->execute();
    $stmt->close();
  } catch (Throwable $e) {
    // Fail silently
  }
}

// Get the count of unread notifications for a user (for the header badge)
function getUnreadNotificationCount($conn, $user_id) {
  if (!($conn instanceof mysqli)) {
    return 0;
  }
  try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    if (!$stmt) {
      return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
  } catch (Throwable $e) {
    return 0;
  }
}

// Create notification records for all subscribers of an item, and email them if opted-in
function createNotificationsForComment($conn, $sender_id, $item_id, $item_type, $comment_id) {
  if (!($conn instanceof mysqli)) {
    return;
  }

  try {
    // 1. Get all other subscribed users
    $stmt = $conn->prepare("SELECT user_id FROM subscriptions WHERE item_id = ? AND item_type = ? AND user_id <> ?");
    if (!$stmt) {
      return;
    }
    $stmt->bind_param("isi", $item_id, $item_type, $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscribers = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $subscribers[] = $row['user_id'];
      }
    }
    $stmt->close();

    if (empty($subscribers)) {
      return;
    }

    // 2. Fetch sender display name
    $stmt = $conn->prepare("SELECT displayname FROM users WHERE user_id = ?");
    if (!$stmt) {
      return;
    }
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $stmt->bind_result($sender_name);
    $stmt->fetch();
    $stmt->close();

    // 3. Fetch item name and build direct link
    $item_name = "";
    $item_url = "";
    if ($item_type === 'wine') {
      $stmt = $conn->prepare("SELECT wines.vintage, wines_master.name, producers.producer 
                              FROM wines 
                              LEFT JOIN wines_master ON wines.master_id = wines_master.master_id 
                              LEFT JOIN producers ON wines_master.producer_id = producers.producer_id 
                              WHERE wines.wine_id = ?");
      if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->bind_result($vintage, $w_name, $producer);
        if ($stmt->fetch()) {
          $vintage = $vintage ?? 'NV';
          $item_name = trim("$vintage $producer $w_name");
        } else {
          $item_name = "Wine #" . $item_id;
        }
        $stmt->close();
      } else {
        $item_name = "Wine #" . $item_id;
      }
      $item_url = "https://dmueller.com/wine.php?id=" . $item_id . "#comment-" . $comment_id;
    } elseif ($item_type === 'tnote') {
      $stmt = $conn->prepare("SELECT wines.vintage, wines_master.name, producers.producer 
                              FROM tnotes 
                              LEFT JOIN wines ON tnotes.wine_id = wines.wine_id 
                              LEFT JOIN wines_master ON wines.master_id = wines_master.master_id 
                              LEFT JOIN producers ON wines_master.producer_id = producers.producer_id 
                              WHERE tnotes.note_id = ?");
      if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->bind_result($vintage, $w_name, $producer);
        if ($stmt->fetch()) {
          $vintage = $vintage ?? 'NV';
          $item_name = "Tasting note on " . trim("$vintage $producer $w_name");
        } else {
          $item_name = "Tasting Note #" . $item_id;
        }
        $stmt->close();
      } else {
        $item_name = "Tasting Note #" . $item_id;
      }
      $item_url = "https://dmueller.com/tnote.php?id=" . $item_id . "#comment-" . $comment_id;
    } elseif ($item_type === 'blog') {
      $stmt = $conn->prepare("SELECT title FROM blogposts WHERE blog_id = ?");
      if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->bind_result($b_title);
        if ($stmt->fetch()) {
          $item_name = "Story: " . trim($b_title);
        } else {
          $item_name = "Story #" . $item_id;
        }
        $stmt->close();
      } else {
        $item_name = "Story #" . $item_id;
      }
      $item_url = "https://dmueller.com/blogpost.php?id=" . $item_id . "#comment-" . $comment_id;
    }

    // 4. Fetch comment snippet
    $stmt = $conn->prepare("SELECT content FROM comments WHERE comment_id = ?");
    if (!$stmt) {
      return;
    }
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $stmt->bind_result($comment_content);
    $stmt->fetch();
    $stmt->close();
    
    $comment_snippet = mb_strimwidth(strip_tags($comment_content), 0, 150, "...");

    // 5. Create notifications and send emails
    foreach ($subscribers as $recipient_id) {
      // Insert notification record
      $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, sender_id, item_id, item_type, comment_id) VALUES (?, ?, ?, ?, ?)");
      if ($stmt_notif) {
        $stmt_notif->bind_param("iiisi", $recipient_id, $sender_id, $item_id, $item_type, $comment_id);
        $stmt_notif->execute();
        $stmt_notif->close();
      }

      // Fetch recipient settings
      $stmt_pref = $conn->prepare("SELECT email, displayname, email_notifications FROM users WHERE user_id = ?");
      if ($stmt_pref) {
        $stmt_pref->bind_param("i", $recipient_id);
        $stmt_pref->execute();
        $stmt_pref->bind_result($recipient_email, $recipient_name, $email_notifications);
        if ($stmt_pref->fetch() && $email_notifications == 1) {
          sendNotificationEmail($recipient_email, $recipient_name, $item_name, $item_url, $sender_name, $comment_snippet);
        }
        $stmt_pref->close();
      }
    }
  } catch (Throwable $e) {
    // Fail silently
  }
}

// Mail helper function using standard PHP mail() with dm@dmueller.com sender
function sendNotificationEmail($to_email, $displayname, $item_name, $item_url, $comment_author, $comment_snippet) {
  $subject = "[Dominik Mueller Fine Wine] New comment on \"" . $item_name . "\"";
  
  $message = "Hello " . $displayname . ",\n\n" .
             $comment_author . " has posted a new comment on \"" . $item_name . "\", which you are following.\n\n" .
             "---\n" .
             "\"" . $comment_snippet . "\"\n" .
             "---\n\n" .
             "You can read the full comment and reply here:\n" .
             $item_url . "\n\n" .
             "To manage your subscriptions or unsubscribe from this discussion, visit your Account Settings:\n" .
             "https://dmueller.com/accountSettings.php\n\n" .
             "Best regards,\n" .
             "Dominik Mueller";
  
  $headers = "From: dm@dmueller.com\r\n" .
             "Reply-To: dm@dmueller.com\r\n" .
             "X-Mailer: PHP/" . phpversion();
  
  @mail($to_email, $subject, $message, $headers);
}

// Function to insert new order
function insertOrder($conn, $store_id, $order_date, $shipping_paid, $status = 'pending delivery') {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  $sql = "INSERT INTO orders (store_id, order_date, shipping_paid, status) VALUES (?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param("isds", $store_id, $order_date, $shipping_paid, $status);
  if ($stmt->execute()) {
    $order_id = $conn->insert_id;
    $stmt->close();
    return $order_id;
  }
  $stmt->close();
  return false;
}

// Function to insert order line item
function insertOrderItem($conn, $order_id, $wine_id, $format, $quantity, $total_price) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  $sql = "INSERT INTO order_items (order_id, wine_id, format, quantity, total_price) VALUES (?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param("iisid", $order_id, $wine_id, $format, $quantity, $total_price);
  $res = $stmt->execute();
  $stmt->close();
  return $res;
}

// Function to insert order document record
function insertOrderDocument($conn, $order_id, $file_path, $file_name) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  $sql = "INSERT INTO order_documents (order_id, file_path, file_name) VALUES (?, ?, ?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param("iss", $order_id, $file_path, $file_name);
  $res = $stmt->execute();
  $stmt->close();
  return $res;
}

// Function to get orders
function getOrders($conn, $status = null) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  $sql = "SELECT orders.order_id, orders.store_id, orders.order_date, orders.shipping_paid, orders.status, orders.created_at, stores.store_name, stores.country 
          FROM orders 
          LEFT JOIN stores ON orders.store_id = stores.store_id";
  if ($status !== null) {
    $sql .= " WHERE orders.status = ?";
  }
  $sql .= " ORDER BY orders.order_date DESC, orders.order_id DESC";
  
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return array();
  }
  if ($status !== null) {
    $stmt->bind_param("s", $status);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $rows = array();
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $stmt->close();
  return $rows;
}

// Function to get order items
function getOrderItems($conn, $order_id) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  $sql = "SELECT order_items.item_id, order_items.wine_id, order_items.format, order_items.quantity, order_items.total_price,
                 wines.vintage, wines_master.name, wines_master.nameconvention, producers.producer, regions.country, regions.region, vineyards.vineyard
          FROM order_items
          LEFT JOIN wines ON order_items.wine_id = wines.wine_id
          LEFT JOIN wines_master ON wines.master_id = wines_master.master_id
          LEFT JOIN producers ON wines_master.producer_id = producers.producer_id
          LEFT JOIN regions ON wines_master.region_id = regions.region_id
          LEFT JOIN vineyards ON wines_master.vineyard_id = vineyards.vineyard_id
          WHERE order_items.order_id = ?
          ORDER BY regions.country ASC, regions.region ASC, producers.producer ASC, wines.vintage DESC";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return array();
  }
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $rows = array();
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $stmt->close();
  return $rows;
}

// Function to get order documents
function getOrderDocuments($conn, $order_id) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  $sql = "SELECT document_id, file_path, file_name, uploaded_at FROM order_documents WHERE order_id = ? ORDER BY uploaded_at ASC";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return array();
  }
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $rows = array();
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $stmt->close();
  return $rows;
}

// Function to get pending bottles of an order
function getPendingOrderBottles($conn, $order_id) {
  if (!($conn instanceof mysqli)) {
    throw new Exception("Invalid database connection");
  }
  $sql = "SELECT bottles.bottle_id, bottles.wine_id, bottles.format, bottles.purchase_price, 
                 wines.vintage, wines_master.name, wines_master.nameconvention, producers.producer, regions.country, regions.region, vineyards.vineyard
          FROM bottles
          LEFT JOIN wines ON bottles.wine_id = wines.wine_id
          LEFT JOIN wines_master ON wines.master_id = wines_master.master_id
          LEFT JOIN producers ON wines_master.producer_id = producers.producer_id
          LEFT JOIN regions ON wines_master.region_id = regions.region_id
          LEFT JOIN vineyards ON wines_master.vineyard_id = vineyards.vineyard_id
          WHERE bottles.order_id = ? AND bottles.status = 'pending delivery'
          ORDER BY bottles.wine_id ASC, bottles.bottle_id ASC";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return array();
  }
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $rows = array();
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $stmt->close();
  return $rows;
}

// Function to insert newly generated order bottles (for_sale defaults to 'no')
function insertOrderBottle($conn, $wine_id, $format, $store_id, $purchase_date, $purchase_price, $status, $order_id) {
  $sql = "INSERT INTO bottles (wine_id, format, storage_location, purchased_from, purchase_date, purchase_price, arrival_date, status, drink_from, drink_through, consumption_date, consumption_note, for_sale, note_id, order_id) 
          VALUES (?, ?, NULL, ?, ?, ?, NULL, ?, NULL, NULL, NULL, NULL, 'no', NULL, ?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param("isisdsi", $wine_id, $format, $store_id, $purchase_date, $purchase_price, $status, $order_id);
  $res = $stmt->execute();
  $stmt->close();
  return $res;
}

?>
