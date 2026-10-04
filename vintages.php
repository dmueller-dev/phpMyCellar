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
    $page_title = getSiteTitle() . " - Vintage Report " . $selected_vintage;
  } else {
    $page_title = getSiteTitle() . " - Vintage Chart & Reports";
  }

  require_once 'includes/header.php';
?>

<div class="row">
  <?php if ($selected_vintage && !$vintage_error): ?>
    <?php
      // Single Vintage Report
      $active_scale = getRatingScale();
      $max_score = getRatingScaleMax($active_scale);
      $threshold = getTopWineScoreThreshold($active_scale);
      $region_stats = getVintageRegionStats($conn, $selected_vintage);
      $country_stats = getVintageCountryStats($conn, $selected_vintage);
      $top_wines = getVintageTopWines($conn, $selected_vintage);
      $adjacent = getAdjacentVintages($conn, $selected_vintage);
      $all_vintages_list = getAllVintagesSummary($conn);
      $avg_display = $vintage_summary['avg_score'] ?? $vintage_summary['avg_dmpts'] ?? null;
      $max_display = $vintage_summary['max_score'] ?? $vintage_summary['max_dmpts'] ?? null;
    ?>

    <!-- Top Centre Back Button -->
    <div class="vintage-nav-center">
      <a href="/vintages.php" class="vintage-nav-btn">← Back to all vintages</a>
    </div>

    <!-- Main Content Area -->
    <div class="column main">
      <!-- Vintage Header Card -->
      <div class="card">
        <h3 style="margin-top:0; margin-bottom: 5px;">Vintage report: <?php echo $selected_vintage; ?></h3>
        <p style="margin-top:0;">Detailed analysis of published tasting notes for the <?php echo $selected_vintage; ?> vintage.</p>

        <div class="vintage-stats-grid">
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo '<span title="Total number of tasting notes (including flawed wines and notes without a rating">'.(int)$vintage_summary['total_notes'].'</span>'; ?></div>
            <div class="stat-lbl">Tasting Notes</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo ($avg_display !== null) ? $avg_display : '<span title="Not enough tasting notes to calculate an average">n/a</span>'; ?></div>
            <div class="stat-lbl">Avg Rating</div>
          </div>
          <div class="vintage-stat-box">
            <div class="stat-val"><?php echo ($max_display !== null) ? $max_display : 'n/a'; ?></div>
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

        <?php if ($avg_display === null): ?>
          <p style="margin-top: 15px; margin-bottom: 0;">
            <em>Not enough tasting notes to calculate an average rating (at least 5 <b>rated</b> tasting notes required<?php echo ((int)$vintage_summary['rated_notes_count'] > 0) ? ', currently ' . (int)$vintage_summary['rated_notes_count'] : ''; ?>).</em>
          </p>
        <?php endif; ?>
      </div>

      <!-- Regional Averages & Expandable Descriptions -->
      <div class="card">
        <h3 style="margin-top:0;">Average ratings by country &amp; region</h3>
        <p style="margin-top:0; margin-bottom:15px;">Average points (out of <?php echo $max_score; ?>, to one decimal place). Click on an entry to reveal vintage descriptions where available.</p>

        <?php if (empty($region_stats)): ?>
          <p><i>No regional statistics available for this vintage.</i></p>
        <?php else: ?>
          <?php foreach ($region_stats as $r): ?>
            <?php
              $label = htmlspecialchars($r['country_region_colour'], ENT_QUOTES, 'UTF-8');
              $score_val = $r['avg_score'] ?? $r['avg_dmpts'] ?? null;
              $avg = ($score_val !== null) ? number_format((float)$score_val, 1) : 'NR';
              $count_label = $r['note_count'] . ' note' . ($r['note_count'] > 1 ? 's' : '');
              $has_desc = !empty($r['vintage_desc']);
            ?>
            <div class="vintage-region-item">
              <?php if ($has_desc): ?>
                <details class="vintage-region-detail">
                  <summary>
                    <div>
                      <strong><?php echo $label; ?></strong>
                      <span style="color: #666; font-size: 0.9em; margin-left: 6px;">(<?php echo $count_label; ?>)</span>
                      <span style="font-size: 0.85em; color: var(--primary-accent, indianred); margin-left: 6px;">📖 details</span>
                    </div>
                    <div class="vintage-score-badge"><?php echo $avg; ?> / <?php echo $max_score; ?></div>
                  </summary>
                  <div class="vintage-desc-box">
                    <?php echo $r['vintage_desc']; ?>
                  </div>
                </details>
              <?php else: ?>
                <div class="vintage-region-plain">
                  <div>
                    <strong><?php echo $label; ?></strong>
                    <span style="color: #666; font-size: 0.9em; margin-left: 6px;">(<?php echo $count_label; ?>)</span>
                  </div>
                  <div class="vintage-score-badge"><?php echo $avg; ?> / <?php echo $max_score; ?></div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Top Wines of Vintage -->
      <div class="card">
        <h3 style="margin-top:0;">Top wines of the <?php echo $selected_vintage; ?> vintage</h3>
        <p style="margin-top:0; margin-bottom:15px;">Ranked from best to worst. Showing wines rated <?php echo $threshold; ?> and higher.</p>

        <?php if (empty($top_wines)): ?>
          <p><i>No wines rated <?php echo $threshold; ?> or higher for this vintage.</i></p>
        <?php else: ?>
          <ul style="list-style-type:none; padding:0; margin:0;">
            <?php foreach ($top_wines as $wine): ?>
              <?php
                // Format wine name
                $wine_name = getWineName($wine['nameconvention'] ?? 'vintage_producer_name', $wine['vintage'] ?? 'NV', $wine['name'] ?? '', $wine['producer'] ?? '', $wine['grape'] ?? '', $wine['vineyard'] ?? '');
                $score_text = formatNoteRatingBadge($wine, $active_scale, true);
                $fav_icon = ($wine['favourite'] === 'yes') ? "<span style='color:#e25555; margin-left:4px;'>❤️</span>" : "";
                $t_dt = !empty($wine['tasting_date']) ? date_create($wine['tasting_date']) : false;
                $tasted_date = ($t_dt !== false) ? date_format($t_dt, "d M Y") : '';
              ?>
              <li class="vintage-top-wine-card">
                <div>
                  <a href="/tnotes.php?id=<?php echo (int)$wine['note_id']; ?>" style="font-weight: bold;">
                    <?php echo htmlspecialchars($wine_name, ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                  <?php echo $fav_icon; ?>
                  <div class="vintage-top-wine-meta">
                    <?php echo htmlspecialchars($wine['region'] . ', ' . $wine['country'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($wine['grape'])): ?> &bull; <?php echo htmlspecialchars($wine['grape'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <?php if (!empty($tasted_date)): ?> &bull; Tasted <?php echo $tasted_date; ?><?php endif; ?>
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
        <p style="margin-top:0; margin-bottom:10px;">Distribution of published notes across countries for <?php echo $selected_vintage; ?>.</p>

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
                <td style="width: 20%; text-align: right; color: #475569;">
                  <strong><?php echo number_format((float)$c['country_percentage'], 1); ?>%</strong>
                  <br><span style="font-size: 12px; color: #666;"><?php echo $c['country_notes_count']; ?> note<?php echo $c['country_notes_count'] > 1 ? 's' : ''; ?></span>
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
            <span style="color:#777; font-size: 14px;">← Older</span>
          <?php endif; ?>

          <span style="font-weight:bold; font-size: 18px;"><?php echo $selected_vintage; ?></span>

          <?php if (!empty($adjacent['next_vintage'])): ?>
            <a class="filter-nav" href="/vintages.php?vintage=<?php echo (int)$adjacent['next_vintage']; ?>"><?php echo (int)$adjacent['next_vintage']; ?> →</a>
          <?php else: ?>
            <span style="color:#777; font-size: 14px;">Newer →</span>
          <?php endif; ?>
        </div>

        <div style="margin-top: 10px;">
          <label for="vintageSelect" style="display:block; margin-bottom: 5px;">Jump to vintage:</label>
          <select id="vintageSelect" onchange="if(this.value) window.location.href='/vintages.php?vintage=' + this.value;" style="width: 100%; padding: 6px; font-family: Georgia, serif; font-size: 14px;">
            <option value="">-- Select a vintage --</option>
            <?php foreach ($all_vintages_list as $v_item): ?>
              <?php $v_item_avg = $v_item['avg_score'] ?? $v_item['avg_dmpts'] ?? null; ?>
              <option value="<?php echo (int)$v_item['vintage']; ?>" <?php echo ((int)$v_item['vintage'] === $selected_vintage) ? 'selected' : ''; ?>>
                <?php echo (int)$v_item['vintage']; ?> (<?php echo $v_item['note_count']; ?> note<?php echo (int)$v_item['note_count'] === 1 ? '' : 's'; ?><?php echo ($v_item_avg !== null) ? ', avg ' . $v_item_avg : ', avg n/a'; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Rating Scale Info -->
      <div class="card">
        <h3 style="margin-top:0;">My rating scale</h3>
        <?php if ($active_scale === '100-point'): ?>
          <p>
            Wines are evaluated using the 100-point scale:
          </p>
          <table>
            <tr><td style="width:70px">98-100</td><td>extraordinary / classic</td></tr>
            <tr><td>95-97</td><td>extraordinary</td></tr>
            <tr><td>90-94</td><td>outstanding</td></tr>
            <tr><td>85-89</td><td>very good</td></tr>
            <tr><td>80-84</td><td>good</td></tr>
            <tr><td>75-79</td><td>acceptable / mediocre</td></tr>
            <tr><td>70-74</td><td>below average</td></tr>
            <tr><td>&lt; 70</td><td>poor / faulty</td></tr>
          </table>
        <?php else: ?>
          <p>
            Wines are evaluated using the 20-point scale:
          </p>
          <table>
            <tr><td style="width:70px">20</td><td>one-of-a-kind</td></tr>
            <tr><td>17-19</td><td>grand vin</td></tr>
            <tr><td>13-16</td><td>excellent</td></tr>
            <tr><td>9-12</td><td>very good</td></tr>
            <tr><td>5-8</td><td>good</td></tr>
            <tr><td>3-4</td><td>passable</td></tr>
            <tr><td>1-2</td><td>subpar</td></tr>
            <tr><td>0</td><td>poor</td></tr>
          </table>
        <?php endif; ?>
        <p style="margin-top:10px;"><a href="/blog.php">Explore tasting notes &amp; stories &rarr;</a></p>
      </div>
    </div>

    <!-- Bottom Centre Back Button -->
    <div class="vintage-nav-center bottom">
      <a href="/vintages.php" class="vintage-nav-btn">← Back to all vintages</a>
    </div>

  <?php elseif ($vintage_error): ?>
    <!-- Error State for Invalid Vintage -->
    <div class="column main" style="width: 100%;">
      <div class="vintage-nav-center">
        <a href="/vintages.php" class="vintage-nav-btn">← Back to all vintages</a>
      </div>

      <div class="card" style="text-align:center; padding: 40px 20px;">
        <h3 style="color: darkred; margin-top:0;">Vintage Not Found</h3>
        <p><?php echo htmlspecialchars($vintage_error, ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Please select an available vintage from the chart overview.</p>
        <div style="margin-top: 20px;">
          <a href="/vintages.php" class="vintage-nav-btn">View Vintage Chart</a>
        </div>
      </div>

      <div class="vintage-nav-center bottom">
        <a href="/vintages.php" class="vintage-nav-btn">← Back to all vintages</a>
      </div>
    </div>

  <?php else: ?>
    <?php
      // All Vintages Chart Overview
      $active_scale = getRatingScale();
      $max_score = getRatingScaleMax($active_scale);
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

        $v_score_val = $v_data['avg_score'] ?? $v_data['avg_dmpts'] ?? null;
        if ($v_score_val !== null) {
          $avg_val = (float)$v_score_val;
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
        <p style="margin-top:0;">Explore wines by vintage year. Click any vintage to view regional performance, top rated wines, and country breakdowns.</p>
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
                    $v_score_val = $v['avg_score'] ?? $v['avg_dmpts'] ?? null;
                    $v_avg = ($v_score_val !== null) ? number_format((float)$v_score_val, 1) : null;
                  ?>
                  <!-- Link to vintages.php?vintage=NNNN -->
                  <a href="/vintages.php?vintage=<?php echo $v_year; ?>" class="vintage-tile" title="<?php echo ($v_avg !== null) ? 'View ' . $v_year . ' vintage report (avg ' . $v_avg . ')' : 'View ' . $v_year . ' vintage report (not enough tasting notes to calculate an average)'; ?>">
                    <span class="vintage-year"><?php echo $v_year; ?></span>
                    <span class="vintage-meta">
                      <span><?php echo $v_count; ?> note<?php echo $v_count > 1 ? 's' : ''; ?></span>
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
          <p style="margin-top:15px;">
            Top performing vintage (min. 5 notes): <a href="/vintages.php?vintage=<?php echo $highest_vintage; ?>"><b><?php echo $highest_vintage; ?></b></a> (avg <?php echo number_format($highest_vintage_score, 1); ?> / <?php echo $max_score; ?>).
          </p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3 style="margin-top:0;">Direct vintage lookup</h3>
        <p>Select a vintage to jump directly to its report:</p>
        <select onchange="if(this.value) window.location.href='/vintages.php?vintage=' + this.value;" style="width: 100%; padding: 6px; font-family: Georgia, serif; font-size: 14px;">
          <option value="">-- Choose a vintage --</option>
          <?php foreach ($all_vintages as $v_item): ?>
            <?php $v_item_avg = $v_item['avg_score'] ?? $v_item['avg_dmpts'] ?? null; ?>
            <option value="<?php echo (int)$v_item['vintage']; ?>">
              <?php echo (int)$v_item['vintage']; ?> (<?php echo $v_item['note_count']; ?> note<?php echo (int)$v_item['note_count'] === 1 ? '' : 's'; ?><?php echo ($v_item_avg !== null) ? ', avg ' . $v_item_avg : ', avg n/a'; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="card">
        <aside>
          <?php 
            echo getStaticPageContent(
              'vintages_sidebar',
              '<h3 style="margin-top:0;">About vintage reports</h3><p>Vintage scores reflect my personal tasting notes on the wines reviewed. They are updated dynamically as new tasting notes are posted.</p><p><a href="/tnotes.php">Browse all tasting notes &rarr;</a></p>'
            ); 
          ?>
        </aside>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
