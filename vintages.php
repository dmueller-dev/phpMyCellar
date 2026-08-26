<?php
  // Define a constant to protect included files from direct access
  define('INCLUDED_VIA_APP', true);
  // Include the initialization file (handles sessions and database connection)
  require_once __DIR__ . '/includes/init.php';

  // Accessible if user has view_tnotes privilege
  if (!hasPrivilege($conn, 'view_tnotes')) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
  }

  global $mysqli, $conn;

  // Determine view mode
  $selected_vintage = null;
  $vintage_error = null;
  $vintage_summary = null;

  if (isset($_GET['vintage']) && trim($_GET['vintage']) !== '') {
    $raw_vintage = trim($_GET['vintage']);
    $validated_vintage = filter_var($raw_vintage, FILTER_VALIDATE_INT);

    if ($validated_vintage === false || $validated_vintage < 1750 || $validated_vintage > 2100) {
      $vintage_error = "Invalid vintage year provided: '" . htmlspecialchars($raw_vintage, ENT_QUOTES, 'UTF-8') . "'. Please choose a valid vintage year from the chart.";
    } else {
      $selected_vintage = $validated_vintage;
      $vintage_summary = getVintageSummary($conn, $selected_vintage);
      if (!$vintage_summary || (int)$vintage_summary['total_notes'] === 0) {
        $vintage_error = "No tasting notes or vintage data found for vintage " . htmlspecialchars((string)$selected_vintage, ENT_QUOTES, 'UTF-8') . ".";
      }
    }
  }

  // Set Page Title
  if ($selected_vintage && !$vintage_error) {
    $page_title = "Dominik Mueller - Vintage Report " . $selected_vintage;
  } else {
    $page_title = "Dominik Mueller - Vintage Chart & Reports";
  }

  require_once 'includes/header.php';
?>

<div class="row">
  <?php if ($selected_vintage && !$vintage_error): ?>
    <?php
      // Single Vintage Report
      $region_stats = getVintageRegionStats($conn, $selected_vintage);
      $country_stats = getVintageCountryStats($conn, $selected_vintage);
      $top_wines = getVintageTopWines($conn, $selected_vintage);
      $adjacent = getAdjacentVintages($conn, $selected_vintage);
      $all_vintages_list = getAllVintagesSummary($conn);
    ?>

    <!-- Top Centre Back Button -->
    <div class="vintage-nav-center">
      <a href="/vintages.php" class="btn-action" style="padding: 8px 18px; font-size: 14px; display: inline-block;">← Back to all vintages</a>
    </div>

    <!-- Main Content Area -->
    <div class="column main">
      <!-- Vintage Header Card -->
      <div class="card">
        <h3 style="margin-top:0; margin-bottom: 5px;">Vintage report: <?php echo $selected_vintage; ?></h3>
        <p style="margin-top:0; color: #64748b;"><small>Detailed analysis of published tasting notes for the <?php echo $selected_vintage; ?> vintage.</small></p>

        <div class="vintage-stats-grid">
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo '<span title="Total number of tasting notes (including flawed wines and notes without a rating">'.(int)$vintage_summary['total_notes'].'</span>'; ?></div>
            <div class="stat-lbl">Tasting Notes</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo ($vintage_summary['avg_dmpts'] !== null) ? $vintage_summary['avg_dmpts'] : '<span title="Not enough tasting notes to calculate an average">n/a</span>'; ?></div>
            <div class="stat-lbl">Avg DM Rating</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo ($vintage_summary['max_dmpts'] !== null) ? $vintage_summary['max_dmpts'] : 'n/a'; ?></div>
            <div class="stat-lbl">Top Rating</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo (int)$vintage_summary['country_count']; ?></div>
            <div class="stat-lbl">Countries</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo (int)$vintage_summary['region_count']; ?></div>
            <div class="stat-lbl">Regions</div>
          </div>
        </div>

        <?php if ($vintage_summary['avg_dmpts'] === null): ?>
          <p style="margin-top: 15px; margin-bottom: 0; color: #64748b; font-size: 13px;">
            <em>Not enough tasting notes to calculate an average rating (at least 5 <b>rated</b> tasting notes required<?php echo ((int)$vintage_summary['rated_notes_count'] > 0) ? ', currently ' . (int)$vintage_summary['rated_notes_count'] : ''; ?>).</em>
          </p>
        <?php endif; ?>
      </div>

      <!-- Regional Averages & Expandable Descriptions -->
      <div class="card">
        <h3 style="margin-top:0;">Average ratings by country &amp; region</h3>
        <p style="margin-top:0; margin-bottom:15px;"><small>Average DM points (out of 20, to one decimal place). Click on an entry to reveal vintage descriptions where available.</small></p>

        <?php if (empty($region_stats)): ?>
          <p><i>No regional statistics available for this vintage.</i></p>
        <?php else: ?>
          <?php foreach ($region_stats as $r): ?>
            <?php
              $label = htmlspecialchars($r['country_region_colour'], ENT_QUOTES, 'UTF-8');
              $avg = ($r['avg_dmpts'] !== null) ? number_format((float)$r['avg_dmpts'], 1) : 'NR';
              $count_label = $r['note_count'] . ' note' . ($r['note_count'] > 1 ? 's' : '');
              $has_desc = !empty($r['vintage_desc']);
            ?>
            <div class="vintage-region-item">
              <?php if ($has_desc): ?>
                <details class="vintage-region-detail">
                  <summary>
                    <div>
                      <strong><?php echo $label; ?></strong>
                      <small style="color: #64748b; margin-left: 6px;">(<?php echo $count_label; ?>)</small>
                      <span style="font-size: 11px; color: indianred; margin-left: 6px;">📖 details</span>
                    </div>
                    <div class="vintage-score-badge"><?php echo $avg; ?> / 20</div>
                  </summary>
                  <div class="vintage-desc-box">
                    <?php echo $r['vintage_desc']; ?>
                  </div>
                </details>
              <?php else: ?>
                <div class="vintage-region-plain">
                  <div>
                    <strong><?php echo $label; ?></strong>
                    <small style="color: #64748b; margin-left: 6px;">(<?php echo $count_label; ?>)</small>
                  </div>
                  <div class="vintage-score-badge"><?php echo $avg; ?> / 20</div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Top Wines of Vintage DM8+ -->
      <div class="card">
        <h3 style="margin-top:0;">Top wines of the <?php echo $selected_vintage; ?> vintage</h3>
        <p style="margin-top:0; margin-bottom:15px;"><small>Ranked from best to worst. Showing wines rated DM8 and higher.</small></p>

        <?php if (empty($top_wines)): ?>
          <p><i>No wines rated DM8 or higher for this vintage.</i></p>
        <?php else: ?>
          <ul style="list-style-type:none; padding:0; margin:0;">
            <?php foreach ($top_wines as $wine): ?>
              <?php
                // Format wine name
                $w_vintage = $wine['vintage'] ?? 'NV';
                $nameconvention = $wine['nameconvention'] ?? 'vintage_producer_name';
                if ($nameconvention == "vintage_name") {
                  $wine_name = $w_vintage . " " . $wine["name"];
                } elseif ($nameconvention == "vintage_producer") {
                  $wine_name = $w_vintage . " " . $wine["producer"];
                } elseif ($nameconvention == "vintage_producer_grape_name") {
                  $wine_name = $w_vintage . " " . $wine["producer"] . " " . $wine["grape"] . " " . $wine["name"];
                } elseif ($nameconvention == "vintage_producer_vineyard_grape_name") {
                  $wine_name = $w_vintage . " " . $wine["producer"] . " " . $wine["vineyard"] . " " . $wine["grape"] . " " . $wine["name"];
                } elseif ($nameconvention == "vintage_producer_vineyard_name") {
                  $wine_name = $w_vintage . " " . $wine["producer"] . " " . $wine["vineyard"] . " " . $wine["name"];
                } else {
                  $wine_name = $w_vintage . " " . $wine["producer"] . " " . $wine["name"];
                }

                $initials = !empty($wine['initials']) ? $wine['initials'] : 'DM';
                $score_text = $initials . $wine['dmpts'];
                $fav_icon = ($wine['favourite'] === 'yes') ? "<span style='color:#e25555; margin-left:4px;'>❤️</span>" : "";
                $tasted_date = !empty($wine['tasting_date']) ? date_format(date_create($wine['tasting_date']), "d M Y") : '';
              ?>
              <li class="vintage-top-wine-card">
                <div>
                  <a href="/tnotes.php?id=<?php echo (int)$wine['note_id']; ?>" style="font-weight: bold;">
                    <?php echo htmlspecialchars($wine_name, ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                  <?php echo $fav_icon; ?>
                  <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                    <?php echo htmlspecialchars($wine['region'] . ', ' . $wine['country'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($wine['grape'])): ?> &bull; <?php echo htmlspecialchars($wine['grape'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <?php if (!empty($tasted_date)): ?> &bull; <small>Tasted <?php echo $tasted_date; ?></small><?php endif; ?>
                  </div>
                </div>
                <div style="text-align: right; min-width: 90px;">
                  <span class="vintage-score-badge"><?php echo $score_text; ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- Side Column -->
    <div class="column side">
      <!-- % of Notes per Country -->
      <div class="card">
        <h3 style="margin-top:0;">Tasting notes by country</h3>
        <p style="margin-top:0; margin-bottom:10px;"><small>Distribution of published notes across countries for <?php echo $selected_vintage; ?>.</small></p>

        <?php if (empty($country_stats)): ?>
          <p><i>No country data available.</i></p>
        <?php else: ?>
          <table class="vintage-country-table">
            <?php foreach ($country_stats as $c): ?>
              <tr>
                <td style="width: 35%; font-weight: bold;">
                  <?php echo htmlspecialchars($c['country'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td style="width: 45%;">
                  <div class="vintage-bar-wrapper">
                    <div class="vintage-bar-fill" style="width: <?php echo min(100, max(5, (float)$c['country_percentage'])); ?>%;"></div>
                  </div>
                </td>
                <td style="width: 20%; text-align: right; font-size: 12px; color: #475569;">
                  <strong><?php echo number_format((float)$c['country_percentage'], 1); ?>%</strong>
                  <br><small style="color: #94a3b8;"><?php echo $c['country_notes_count']; ?> note<?php echo $c['country_notes_count'] > 1 ? 's' : ''; ?></small>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <!-- Adjacent Vintages Switcher -->
      <div class="card">
        <h3 style="margin-top:0;">Switch vintage</h3>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
          <?php if (!empty($adjacent['prev_vintage'])): ?>
            <a class="filter-nav" href="/vintages.php?vintage=<?php echo (int)$adjacent['prev_vintage']; ?>">← <?php echo (int)$adjacent['prev_vintage']; ?></a>
          <?php else: ?>
            <span style="color:#94a3b8; font-size:small;">← Older</span>
          <?php endif; ?>

          <span style="font-weight:bold; font-size: 16px;"><?php echo $selected_vintage; ?></span>

          <?php if (!empty($adjacent['next_vintage'])): ?>
            <a class="filter-nav" href="/vintages.php?vintage=<?php echo (int)$adjacent['next_vintage']; ?>"><?php echo (int)$adjacent['next_vintage']; ?> →</a>
          <?php else: ?>
            <span style="color:#94a3b8; font-size:small;">Newer →</span>
          <?php endif; ?>
        </div>

        <div style="margin-top: 10px;">
          <label for="vintageSelect" style="font-size: small; display:block; margin-bottom: 5px;">Jump to vintage:</label>
          <select id="vintageSelect" onchange="if(this.value) window.location.href='/vintages.php?vintage=' + this.value;" style="width: 100%; padding: 5px; font-family: Georgia, serif;">
            <option value="">-- Select a vintage --</option>
            <?php foreach ($all_vintages_list as $v_item): ?>
              <option value="<?php echo (int)$v_item['vintage']; ?>" <?php echo ((int)$v_item['vintage'] === $selected_vintage) ? 'selected' : ''; ?>>
                <?php echo (int)$v_item['vintage']; ?> (<?php echo $v_item['note_count']; ?> note<?php echo (int)$v_item['note_count'] === 1 ? '' : 's'; ?><?php echo ($v_item['avg_dmpts'] !== null) ? ', avg ' . $v_item['avg_dmpts'] : ', avg n/a'; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Rating Scale Info -->
      <div class="card">
        <h3 style="margin-top:0;">My rating scale</h3>
        <p style="font-size: small; line-height: 1.4;">
          Wines are evaluated using the 20-point scale:
        </p>
        <ul style="font-size: small; padding-left: 18px; margin: 0;">
          <li><b>20</b>: one-of-a-kind</li>
          <li><b>17-19</b>: grand vin</li>
          <li><b>13-16</b>: excellent</li>
          <li><b>9-12</b>: very good</li>
          <li><b>5-8</b>: good</li>
          <li><b>3-4</b>: passable</li>
          <li><b>1-2</b>: subpar</li>
          <li><b>0</b>: poor</li>
        </ul>
        <p style="margin-top:10px;"><small><a href="https://dmueller.com/blog.php?id=26">Learn more about how I rate wines &rarr;</a></small></p>
      </div>
    </div>

    <!-- Bottom Centre Back Button -->
    <div class="vintage-nav-center bottom">
      <a href="/vintages.php" class="btn-action" style="padding: 8px 18px; font-size: 14px; display: inline-block;">← Back to all vintages</a>
    </div>

  <?php elseif ($vintage_error): ?>
    <!-- Error State for Invalid Vintage -->
    <div class="column main" style="width: 100%;">
      <div class="vintage-nav-center">
        <a href="/vintages.php" class="btn-action" style="padding: 8px 18px; font-size: 14px; display: inline-block;">← Back to all vintages</a>
      </div>

      <div class="card" style="text-align:center; padding: 40px 20px;">
        <h3 style="color: darkred; margin-top:0;">Vintage Not Found</h3>
        <p><?php echo htmlspecialchars($vintage_error, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><small>Please select an available vintage from the chart overview.</small></p>
        <div style="margin-top: 20px;">
          <a href="/vintages.php" class="btn-action" style="padding: 8px 18px; font-size: 14px; display: inline-block;">View Vintage Chart</a>
        </div>
      </div>

      <div class="vintage-nav-center bottom">
        <a href="/vintages.php" class="btn-action" style="padding: 8px 18px; font-size: 14px; display: inline-block;">← Back to all vintages</a>
      </div>
    </div>

  <?php else: ?>
    <?php
      // All Vintages Chart Overview
      $all_vintages = getAllVintagesSummary($conn);

      // Group vintages by decade
      $decades = [];
      $total_all_notes = 0;
      $weighted_score_sum = 0;
      $weighted_notes_count = 0;
      $highest_vintage = null;
      $highest_vintage_score = 0;

      foreach ($all_vintages as $v_data) {
        $v_num = (int)$v_data['vintage'];
        $n_count = (int)$v_data['note_count'];
        $total_all_notes += $n_count;

        if ($v_data['avg_dmpts'] !== null) {
          $avg_val = (float)$v_data['avg_dmpts'];
          $weighted_score_sum += ($avg_val * $n_count);
          $weighted_notes_count += $n_count;

          if ($avg_val > $highest_vintage_score && $n_count >= 5) {
            $highest_vintage_score = $avg_val;
            $highest_vintage = $v_num;
          }
        }

        $decade_key = floor($v_num / 10) * 10;
        if ($decade_key >= 1970) {
          $decade_label = $decade_key . "s";
        } else {
          $decade_label = "1960s & Older";
          $decade_key = 1960;
        }

        if (!isset($decades[$decade_label])) {
          $decades[$decade_label] = [];
        }
        $decades[$decade_label][] = $v_data;
      }

      $overall_avg = ($weighted_notes_count > 0) ? round($weighted_score_sum / $weighted_notes_count, 1) : null;
    ?>

    <!-- Main Column: Vintage Chart -->
    <div class="column main">
      <div class="card">
        <h3 style="margin-top:0; margin-bottom:5px;">Vintage chart &amp; reports</h3>
        <p style="margin-top:0; color:#475569;"><small>Explore wines by vintage year. Click any vintage to view regional performance, top rated wines, and country breakdowns.</small></p>
      </div>

      <?php if (empty($all_vintages)): ?>
        <div class="card" style="text-align:center; padding:30px;">
          <p>No published tasting notes with vintage information are available yet.</p>
        </div>
      <?php else: ?>
        <div class="card">
          <?php foreach ($decades as $decade_title => $vintages_in_decade): ?>
            <div class="vintage-grid-decade">
              <div class="vintage-decade-heading"><?php echo htmlspecialchars($decade_title, ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="vintage-chart-grid">
                <?php foreach ($vintages_in_decade as $v): ?>
                  <?php
                    $v_year = (int)$v['vintage'];
                    $v_count = (int)$v['note_count'];
                    $v_avg = ($v['avg_dmpts'] !== null) ? number_format((float)$v['avg_dmpts'], 1) : null;
                  ?>
                  <!-- Link to vintages.php?vintage=NNNN -->
                  <a href="/vintages.php?vintage=<?php echo $v_year; ?>" class="vintage-tile" title="<?php echo ($v_avg !== null) ? 'View ' . $v_year . ' vintage report (avg ' . $v_avg . ')' : 'View ' . $v_year . ' vintage report (not enough tasting notes to calculate an average)'; ?>">
                    <span class="vintage-year"><?php echo $v_year; ?></span>
                    <span class="vintage-meta">
                      <small><?php echo $v_count; ?> note<?php echo $v_count > 1 ? 's' : ''; ?></small>
                      <?php if ($v_avg !== null): ?>
                        <span class="vintage-score-pill"><?php echo $v_avg; ?></span>
                      <?php else: ?>
                        <span class="vintage-score-pill vintage-score-pill-na" title="Not enough tasting notes to calculate an average">n/a</span>
                      <?php endif; ?>
                    </span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Side Column: Summary & Quick Search -->
    <div class="column side">
      <div class="card">
        <h3 style="margin-top:0;">Overview</h3>
        <p>This vintage chart compiles tasting notes from my personal notebook across all recorded vintages.</p>

        <div class="vintage-stats-grid">
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo count($all_vintages); ?></div>
            <div class="stat-lbl">Vintages</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo $total_all_notes; ?></div>
            <div class="stat-lbl">Total Notes</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo ($overall_avg !== null) ? $overall_avg : 'n/a'; ?></div>
            <div class="stat-lbl">Overall Avg</div>
          </div>
        </div>

        <?php if ($highest_vintage): ?>
          <p style="font-size:small; margin-top:15px;">
            Top performing vintage (min. 5 notes): <a href="/vintages.php?vintage=<?php echo $highest_vintage; ?>"><b><?php echo $highest_vintage; ?></b></a> (avg <?php echo number_format($highest_vintage_score, 1); ?> / 20).
          </p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3 style="margin-top:0;">Direct vintage lookup</h3>
        <p style="font-size:small;">Select a vintage to jump directly to its report:</p>
        <select onchange="if(this.value) window.location.href='/vintages.php?vintage=' + this.value;" style="width: 100%; padding: 6px; font-family: Georgia, serif;">
          <option value="">-- Choose a vintage --</option>
          <?php foreach ($all_vintages as $v_item): ?>
            <option value="<?php echo (int)$v_item['vintage']; ?>">
              <?php echo (int)$v_item['vintage']; ?> (<?php echo $v_item['note_count']; ?> note<?php echo (int)$v_item['note_count'] === 1 ? '' : 's'; ?><?php echo ($v_item['avg_dmpts'] !== null) ? ', avg ' . $v_item['avg_dmpts'] : ', avg n/a'; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="card">
        <aside>
          <h3 style="margin-top:0;">About vintage reports</h3>
          <p style="font-size:small; line-height:1.4;">
            Vintage scores reflect my personal tasting notes on the wines reviewed. They are updated dynamically as new tasting notes are posted.
          </p>
          <p style="font-size:small;"><a href="/tnotes.php">Browse all tasting notes &rarr;</a></p>
        </aside>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
