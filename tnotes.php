<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Check if user is not logged in
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
  }
?>

<?php
  if (empty($_GET['sort'])) {
    $sort="date";
  } else {
    $sort=$_GET['sort'];
  }

  if ($sort=="date" || $sort=="tenyears" || $sort=="twentyplus") {
    $sqlOrderBy="order by tnotes.tasting_date desc, wines.wine_id asc, tnotes.note_id desc";
  } elseif ($sort=="rating") {
    $sqlOrderBy="order by tnotes.flawed_yn asc, tnotes.dmpts desc, producers.producer asc, wines.vintage desc, wines_master.name asc, tnotes.tasting_date desc, tnotes.note_id desc";
  } elseif ($sort=="stars") {
    $sqlOrderBy="order by tnotes.flawed_yn asc, tnotes.starpts desc, tnotes.dmpts desc, producers.producer asc, wines.vintage desc, wines_master.name asc, tnotes.tasting_date desc, tnotes.note_id desc";
  } elseif ($sort=="region") {
    $sqlOrderBy="order by regions.country asc, regions.region asc, producers.producer asc, wines.vintage desc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, tnotes.tasting_date desc, tnotes.note_id desc";
  } elseif ($sort=="producer") {
    $sqlOrderBy="order by producers.producer asc, wines.vintage desc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, regions.region asc, tnotes.tasting_date desc, tnotes.note_id desc";
  } elseif ($sort=="vintage") {
    $sqlOrderBy="order by wines.vintage desc, regions.country asc, producers.producer asc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, tnotes.tasting_date desc, tnotes.note_id desc";
  } elseif ($sort=="variety") {
    $sqlOrderBy="order by wines_master.grape asc, regions.country asc, producers.producer asc, wines.vintage desc, wines_master.name asc, vineyards.vineyard asc, appellations.appellation asc, tnotes.tasting_date desc, tnotes.note_id desc";
  }
?>

<?php
  $page_title = 'Dominik Mueller - Browse all wines';
  $meta_desc = 'On this website, I share my wine cellar with a community of fellow fine wine enthusiasts.';

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
      <h3 style="margin-bottom:10px; margin-top:0;">Browse all tasting notes</h3>

      <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
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
            style="cursor: pointer; vertical-align: middle;">
          <label for="favouriteToggle" style="font-size: small; cursor: pointer; user-select: none;">Only show Favourites</label>
        </div>
      </div>

      <p style="margin-top:0;"><small>
        Sort by:
        <?php 
          $urlParams = !empty($_GET['q']) ? "&q=" . urlencode($_GET['q']) : ""; 
          if (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') {
            $urlParams .= "&favourite=yes";
          }
        ?>
        <a class="filter-nav" href="/tnotes.php?sort=date<?php echo $urlParams; ?>">Tasting date</a>
        <a class="filter-nav" href="/tnotes.php?sort=rating<?php echo $urlParams; ?>">DM points</a>
        <a class="filter-nav" href="/tnotes.php?sort=stars<?php echo $urlParams; ?>">Stars</a>
        <a class="filter-nav" href="/tnotes.php?sort=region<?php echo $urlParams; ?>">Region</a>
        <a class="filter-nav" href="/tnotes.php?sort=producer<?php echo $urlParams; ?>">Producer</a>
        <a class="filter-nav" href="/tnotes.php?sort=vintage<?php echo $urlParams; ?>">Vintage</a>
        <a class="filter-nav" href="/tnotes.php?sort=variety<?php echo $urlParams; ?>">Variety</a>
        <a class="filter-nav" href="/tnotes.php?sort=tenyears<?php echo $urlParams; ?>">Aged 10 years</a>
        <a class="filter-nav" href="/tnotes.php?sort=twentyplus<?php echo $urlParams; ?>">Aged 20+ years</a>
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

<?php require_once 'includes/footer.php'; ?>

<?php function getNotes($num,$sort,$sqlOrderBy)
{
  global $mysqli, $conn;
  $prevYear="";
  $prevRating="";

  // Base WHERE condition
  $sqlWhere = " WHERE tnotes.status='published' ";

  // Favourites filter
  if (isset($_GET['favourite']) && $_GET['favourite'] == 'yes') {
    $sqlWhere .= " AND tnotes.favourite = 'yes' ";
  }

  // Ten-year-old wines
  if ($sort == "tenyears") {
    $sqlWhere .= " AND wines.vintage is not null and year(tnotes.tasting_date) - wines.vintage = 10 ";
  } elseif ($sort == "twentyplus") {
    $sqlWhere .= " AND wines.vintage is not null and year(tnotes.tasting_date) - wines.vintage >= 20 ";
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
  $result = $mysqli -> query("select * from tnotes
                                left join users on tnotes.user_id=users.user_id
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
                                left join (select note_id as c_note_id, count(comment_id) as comment_count from x_comments_tnotes group by note_id) c on tnotes.note_id=c.c_note_id
                                " . $sqlWhere . " " . $sqlOrderBy . " limit 0," . $num);

  // Display message if no results found
  if ($result->num_rows == 0) {
    $feedbackMsg = !empty($_GET['q'])
        ? "your search for '<strong>" . htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') . "</strong>'"
        : "your current filter selection";

    echo "<div class='card' style='text-align:center; padding:30px 20px;'>
      <p>I'm afraid no tasting notes currently match {$feedbackMsg}.</p>
      <p><small>Try checking your spelling or using fewer keywords.</small></p>
    </div>";
 
    return; // Exit the function early
  }

  if ($sort=="date") {
    echo "<p><small><i>Tasting notes arranged chronologically by tasting date, grouped by year.</i></small></p>";
  } elseif ($sort=="rating") {
    echo "<p><small><i>Tasting notes arranged by DM score (20-point scale), then by producer and vintage.</i></small></p>";
  } elseif ($sort=="stars") {
    echo "<p><small><i>Tasting notes arranged by star rating (5-star scale), then by score and producer.</i></small></p>";
  } elseif ($sort=="region") {
    echo "<p><small><i>Tasting notes arranged by country and region, then by producer, cuvée, and vintage.</i></small></p>";
  } elseif ($sort=="producer") {
    echo "<p><small><i>Tasting notes organised by producer, then by cuvée and vintage.</i></small></p>";
  } elseif ($sort=="vintage") {
    echo "<p><small><i>Tasting notes arranged by vintage, then by country, producer, and cuvée.</i></small></p>";
  } elseif ($sort=="variety") {
    echo "<p><small><i>Tasting notes grouped by grape variety, then by country, producer, and cuvée. For <em>assemblages</em>, only the primary grape variety is shown.</i></small></p>";
  } elseif ($sort=="tenyears") {
    echo "<p><small><i>&quot;Ten years on&quot; tasting notes, arranged chronologically by tasting date.</i></small></p>";
  } elseif ($sort=="twentyplus") {
    echo "<p><small><i>Tasting notes for mature wines (20+ years), arranged chronologically by tasting date.</i></small></p>";
  }
  $rows = [];
  while ($r = $result->fetch_assoc()) {
    if ($r["vintage"] === null) {
      $r["vintage"] = "NV";
    }

    if ($r['flawed_yn'] == "yes") {
      $r['dmpts_label'] = "flawed";
      $r['stars_label'] = "flawed";
    } elseif ($r['dmpts'] !== null) {
      $initials = !empty($r["initials"]) ? $r["initials"] : 'DM';
      $r['dmpts_label'] = $initials . $r["dmpts"];
      $r['stars_label'] = ($r["starpts"] != 1) ? $r["starpts"] . " stars" : $r["starpts"] . " star";
    } else {
      $r['dmpts_label'] = "NR";
      $r['stars_label'] = "NR";
    }

    $rows[] = $r;
  }
  $result->free_result();

  // Group consecutive rows with the same wine_id within the active hierarchy section
  $grouped_notes = [];
  $current_group = null;
  $current_section_key = null;

  foreach ($rows as $row) {
    if ($sort == "date" || $sort == "twentyplus") {
      $section_key = date_format(date_create($row["tasting_date"]), "Y");
    } elseif ($sort == "tenyears") {
      $section_key = $row["vintage"] . "-" . date_format(date_create($row["tasting_date"]), "y");
    } elseif ($sort == "rating") {
      $section_key = $row["dmpts_label"];
    } elseif ($sort == "stars") {
      $section_key = $row["stars_label"];
    } elseif ($sort == "region") {
      $section_key = ($row['country'] ?? '') . '|||' . ($row['region'] ?? '');
    } elseif ($sort == "producer") {
      $section_key = $row['producer'] ?? '';
    } elseif ($sort == "vintage") {
      $section_key = $row['vintage'] ?? '';
    } elseif ($sort == "variety") {
      $section_key = $row['grape'] ?? '';
    } else {
      $section_key = '';
    }

    $wine_id = $row['wine_id'] ?? $row['note_id'];

    if (
      $current_group !== null &&
      $current_section_key === $section_key &&
      $current_group['wine_id'] === $wine_id
    ) {
      $current_group['notes'][] = $row;
    } else {
      if ($current_group !== null) {
        $grouped_notes[] = $current_group;
      }
      $current_section_key = $section_key;
      $current_group = [
        'section_key' => $section_key,
        'wine_id' => $wine_id,
        'row' => $row,
        'notes' => [$row]
      ];
    }
  }
  if ($current_group !== null) {
    $grouped_notes[] = $current_group;
  }

  $prevYear = "";
  $prevTenYears = "";
  $prevRating = "";
  $prevStars = "";
  $prevCountry = "";
  $prevRegion = "";
  $prevProducer = "";
  $prevVintage = "";
  $prevVariety = "";

  foreach ($grouped_notes as $group) {
    $wine = $group['row'];

    // Output hierarchy headers
    if ($sort == "date" || $sort == "twentyplus") {
      $curYear = date_format(date_create($wine["tasting_date"]), "Y");
      if ($curYear != $prevYear) {
        echo ($prevYear != "") ? "</ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($curYear, ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "tenyears") {
      $curTenYears = $wine["vintage"] . "-" . date_format(date_create($wine["tasting_date"]), "y");
      if ($curTenYears != $prevTenYears) {
        echo ($prevTenYears != "") ? "</ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($curTenYears, ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "rating") {
      if ($wine["dmpts_label"] != $prevRating) {
        echo ($prevRating != "") ? "</ul></details><br>" : "";
        if ($wine["dmpts_label"] != "flawed" && $wine["dmpts_label"] != "NR") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["dmpts_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["dmpts_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } elseif ($wine["dmpts_label"] == "NR") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["dmpts_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>Sometimes I don't give a rating to a wine. In that case you'll read &quot;not rated&quot; or the abbreviation &quot;NR&quot; in the tasting note. I might not give a rating if I tasted a wine only quickly without taking notes - at a winery, for example. Sometimes I might still want to note down an indication for a possible rating. In that case, I'll write a provisional score in the tasting note, but I'll always make that clear in the written note.</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } elseif ($wine["dmpts_label"] == "flawed") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["dmpts_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>I mark bottles as flawed when there is a fault outside of the control of the winemaker. This might happen if a wine is corked or it is spoilt because of bad storage conditions. I don't rate these wines.</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
      }
    } elseif ($sort == "stars") {
      if ($wine["stars_label"] != $prevStars) {
        echo ($prevStars != "") ? "</ul></details><br>" : "";
        if ($wine["stars_label"] != "flawed" && $wine["stars_label"] != "NR") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["stars_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["starpts_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } elseif ($wine["stars_label"] == "NR") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["stars_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>Sometimes I don't give a rating to a wine. In that case you'll read &quot;not rated&quot; or the abbreviation &quot;NR&quot; in the tasting note. I might not give a rating if I tasted a wine only quickly without taking notes - at a winery, for example. Sometimes I might still want to note down an indication for a possible rating. In that case, I'll write a provisional score in the tasting note, but I'll always make that clear in the written note.</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } elseif ($wine["stars_label"] == "flawed") {
          echo "<details open><summary><b>" . htmlspecialchars($wine["stars_label"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>I mark bottles as flawed when there is a fault outside of the control of the winemaker. This might happen if a wine is corked or it is spoilt because of bad storage conditions. I don't rate these wines.</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
      }
    } elseif ($sort == "region") {
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevRegion = "";
      }
      if ($wine["region"] != $prevRegion) {
        echo ($prevRegion != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details open><summary><i>" . htmlspecialchars($wine["region"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "producer") {
      if ($wine["producer"] != $prevProducer) {
        echo ($prevProducer != "") ? "</ul></details><br>" : "";
        if (!empty($wine["producer_desc"])) {
          echo "<details open><summary><b>" . htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["producer_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        } else {
          echo "<details open><summary><b>" . htmlspecialchars($wine["producer"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
      }
    } elseif ($sort == "vintage") {
      if ($wine["vintage"] != $prevVintage) {
        echo ($prevVintage != "") ? "</ul></details></li></ul></details><br>" : "";
        echo "<details open><summary><b>" . htmlspecialchars($wine["vintage"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:0;'>";
        $prevCountry = "";
      }
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details open><summary><i>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    } elseif ($sort == "variety") {
      if ($wine["grape"] != $prevVariety) {
        echo ($prevVariety != "") ? "</ul></details></li></ul></details><br>" : "";
        if (!empty($wine["grape_desc"])) {
          echo "<details open><summary><b>" . htmlspecialchars($wine["grape"], ENT_QUOTES, 'UTF-8') . "</b></summary><hr><small>" . $wine["grape_desc"] . "</small><ul style='list-style-type:none;padding:0;margin:0;'>";
        } else {
          echo "<details open><summary><b>" . htmlspecialchars($wine["grape"], ENT_QUOTES, 'UTF-8') . "</b></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
        }
        $prevCountry = "";
      }
      if ($wine["country"] != $prevCountry) {
        echo ($prevCountry != "") ? "</ul></details></li>" : "";
        echo "<li style='text-indent:10px;margin-top:6px;'><details open><summary><i>" . htmlspecialchars($wine["country"], ENT_QUOTES, 'UTF-8') . "</i></summary><ul style='list-style-type:none;padding:0;margin:10px 0 0 0;'>";
      }
    }

    $wine_name = getWineName($wine["nameconvention"], $wine["vintage"], $wine["name"], $wine["producer"], $wine["grape"], $wine["vineyard"]);
    $item_padding = ($sort == "region" || $sort == "vintage" || $sort == "variety") ? "padding-left:43px;text-indent:-18px;" : "padding-left:35px;text-indent:-18px;";

    echo "<li class='wine-item' style='{$item_padding}margin-bottom:8px;'>";
    echo "<span class='wine-title'>" . htmlspecialchars($wine_name, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "<div class='vintage-chip-group'>";

    foreach ($group['notes'] as $n) {
      $t_date = date_format(date_create($n["tasting_date"]), "d M Y");
      $rating_label = $n['dmpts_label'];
      $comment_count = (int)($n['comment_count'] ?? 0);
      $is_fav = (isset($n['favourite']) && $n['favourite'] == 'yes');

      echo "<div class='vintage-chip rating-chip'>";
      echo "<a class='chip-link' href='/tnote.php?id=" . $n["note_id"] . "' title='Tasted on " . $t_date . "'>";
      if ($is_fav) {
        echo "<span class='chip-fav'>❤️</span>";
      }
      echo "<span class='chip-rating'>" . htmlspecialchars($rating_label, ENT_QUOTES, 'UTF-8') . "</span>";
      echo "<span class='chip-sep'>·</span>";
      echo "<span class='chip-date'>" . htmlspecialchars($t_date, ENT_QUOTES, 'UTF-8') . "</span>";
      echo "</a>";

      if ($comment_count > 0) {
        $c_title = $comment_count === 1 ? '1 comment' : $comment_count . ' comments';
        echo "<a class='chip-badge' href='/tnote.php?id=" . $n["note_id"] . "#comments' title='" . $c_title . "' aria-label='" . $c_title . "'>";
        echo '<svg class="comment-icon" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
        echo "<span class='comment-count'>" . $comment_count . "</span>";
        echo "</a>";
      }

      echo "</div>";
    }

    echo "</div>";
    echo "</li>";

    // Set previous values
    $prevYear = date_format(date_create($wine["tasting_date"]), "Y");
    $prevTenYears = $wine["vintage"] . "-" . date_format(date_create($wine["tasting_date"]), "y");
    $prevRating = $wine["dmpts_label"];
    $prevStars = $wine["stars_label"];
    $prevCountry = $wine["country"];
    $prevRegion = $wine["region"];
    $prevProducer = $wine["producer"];
    $prevVintage = $wine["vintage"];
    $prevVariety = $wine["grape"];
  }

  if ($sort == "region" || $sort == "vintage" || $sort == "variety") {
    echo "</ul></details></li></ul></details>";
  } elseif ($sort == "date" || $sort == "twentyplus" || $sort == "tenyears" || $sort == "rating" || $sort == "stars" || $sort == "producer") {
    echo "</ul></details>";
  }
} ?>
