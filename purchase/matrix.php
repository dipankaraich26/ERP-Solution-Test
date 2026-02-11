<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Ensure part_supplier_mapping table exists
try {
    $pdo->query("SELECT 1 FROM part_supplier_mapping LIMIT 1");
} catch (Exception $e) {
    echo '<link rel="stylesheet" href="/assets/style.css">';
    include '../includes/sidebar.php';
    echo '<div class="content"><h2>Lead Time vs Value Matrix</h2><p style="color:#dc2626;">Supplier Pricing data not available. Please set up supplier pricing first.</p></div>';
    exit;
}

// Fetch all active parts with their best supplier rate and lead time
$parts = $pdo->query("
    SELECT
        p.part_no,
        p.part_name,
        p.part_id,
        p.uom,
        p.category,
        COALESCE(psm.supplier_rate, p.rate, 0) AS value,
        COALESCE(psm.lead_time_days, 0) AS lead_time,
        psm.supplier_id,
        s.supplier_name,
        COALESCE(i.qty, 0) AS current_stock
    FROM part_master p
    LEFT JOIN (
        SELECT psm1.*
        FROM part_supplier_mapping psm1
        INNER JOIN (
            SELECT part_no, MIN(supplier_rate) AS min_rate
            FROM part_supplier_mapping
            WHERE active = 1 AND supplier_rate > 0
            GROUP BY part_no
        ) psm2 ON psm1.part_no = psm2.part_no AND psm1.supplier_rate = psm2.min_rate
        WHERE psm1.active = 1
        GROUP BY psm1.part_no
    ) psm ON p.part_no = psm.part_no
    LEFT JOIN suppliers s ON psm.supplier_id = s.id
    LEFT JOIN inventory i ON p.part_no = i.part_no
    WHERE p.status = 'active'
    AND COALESCE(psm.supplier_rate, p.rate, 0) > 0
    AND (p.part_id IS NULL OR p.part_id NOT IN ('YID-042','YID-044','YID-046','YID-052','YID-083','YID-091','YID-099'))
    ORDER BY p.part_name
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($parts)) {
    echo '<link rel="stylesheet" href="/assets/style.css">';
    include '../includes/sidebar.php';
    echo '<div class="content"><h2>Lead Time vs Value Matrix</h2><p style="color:#888;">No parts with pricing data found. Please set up supplier pricing first.</p></div>';
    exit;
}

// Calculate thresholds (median)
$values = array_column($parts, 'value');
$leadTimes = array_column($parts, 'lead_time');
sort($values);
sort($leadTimes);

$medianValue = $values[intval(count($values) / 2)];
$medianLeadTime = $leadTimes[intval(count($leadTimes) / 2)];

// Allow custom thresholds via GET
$valueThreshold = isset($_GET['value_threshold']) && $_GET['value_threshold'] !== '' ? (float)$_GET['value_threshold'] : $medianValue;
$leadTimeThreshold = isset($_GET['lt_threshold']) && $_GET['lt_threshold'] !== '' ? (int)$_GET['lt_threshold'] : $medianLeadTime;

// Categorize parts into 4 quadrants
$quadrants = [
    'HV_HLT' => ['label' => 'Strategic', 'desc' => 'High Value · High Lead Time', 'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fca5a5', 'items' => []],
    'HV_LLT' => ['label' => 'Leverage', 'desc' => 'High Value · Low Lead Time', 'color' => '#2563eb', 'bg' => '#eff6ff', 'border' => '#93c5fd', 'items' => []],
    'LV_HLT' => ['label' => 'Bottleneck', 'desc' => 'Low Value · High Lead Time', 'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fcd34d', 'items' => []],
    'LV_LLT' => ['label' => 'Non-Critical', 'desc' => 'Low Value · Low Lead Time', 'color' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#86efac', 'items' => []],
];

foreach ($parts as $part) {
    $isHighValue = $part['value'] >= $valueThreshold;
    $isHighLT = $part['lead_time'] >= $leadTimeThreshold;

    if ($isHighValue && $isHighLT) {
        $quadrants['HV_HLT']['items'][] = $part;
    } elseif ($isHighValue && !$isHighLT) {
        $quadrants['HV_LLT']['items'][] = $part;
    } elseif (!$isHighValue && $isHighLT) {
        $quadrants['LV_HLT']['items'][] = $part;
    } else {
        $quadrants['LV_LLT']['items'][] = $part;
    }
}

// Stats
$totalParts = count($parts);
$avgValue = $totalParts > 0 ? array_sum(array_column($parts, 'value')) / $totalParts : 0;
$avgLeadTime = $totalParts > 0 ? array_sum(array_column($parts, 'lead_time')) / $totalParts : 0;
$maxValue = max(array_column($parts, 'value'));
$maxLeadTime = max(array_column($parts, 'lead_time'));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lead Time vs Value Matrix</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .matrix-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .quadrant {
            border-radius: 10px;
            padding: 18px;
            border: 2px solid;
            min-height: 200px;
        }
        .quadrant-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .quadrant-title {
            font-size: 1.15em;
            font-weight: 700;
        }
        .quadrant-count {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            color: white;
        }
        .quadrant-desc {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 10px;
        }
        .part-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 8px;
            border-radius: 5px;
            font-size: 0.85em;
            cursor: pointer;
            transition: background 0.15s;
        }
        .part-row:hover {
            background: rgba(0,0,0,0.05);
        }
        .part-row:nth-child(odd) {
            background: rgba(255,255,255,0.5);
        }
        .part-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }
        .part-info .part-no {
            font-weight: 600;
            white-space: nowrap;
            color: #1e40af;
            font-size: 0.85em;
        }
        .part-info .part-name {
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .part-metrics {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
            font-size: 0.85em;
        }
        .metric {
            text-align: right;
            white-space: nowrap;
        }
        .metric-label {
            font-size: 0.75em;
            color: #888;
        }
        .threshold-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            padding: 12px 18px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .threshold-form label {
            font-size: 0.85em;
            color: #555;
            font-weight: 500;
        }
        .threshold-form input {
            width: 120px;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: 0.9em;
        }
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            padding: 12px 15px;
            background: #f3f4f6;
            border-radius: 8px;
        }
        .stat-card .label { font-size: 0.8em; color: #666; }
        .stat-card .value { font-size: 1.5em; font-weight: 700; }
        .axis-label {
            text-align: center;
            font-weight: 600;
            font-size: 0.9em;
            color: #555;
            padding: 4px 0;
        }
        .more-link {
            display: block;
            text-align: center;
            padding: 6px;
            font-size: 0.8em;
            color: #6366f1;
            cursor: pointer;
        }
        .part-list-full { display: none; }
        .part-list-full.show { display: block; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">Lead Time vs Value Matrix</h2>
        <a href="/purchase/dashboard.php" class="btn btn-secondary" style="font-size: 0.9em;">← Back to Dashboard</a>
    </div>

    <!-- Stats -->
    <div class="stat-cards">
        <div class="stat-card">
            <div class="label">Total Parts</div>
            <div class="value" style="color: #2563eb;"><?= $totalParts ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Avg. Value (₹)</div>
            <div class="value" style="color: #7c3aed;"><?= number_format($avgValue, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Avg. Lead Time</div>
            <div class="value" style="color: #d97706;"><?= round($avgLeadTime, 1) ?> days</div>
        </div>
        <div class="stat-card" style="background: <?= $quadrants['HV_HLT']['bg'] ?>;">
            <div class="label">Strategic</div>
            <div class="value" style="color: <?= $quadrants['HV_HLT']['color'] ?>;"><?= count($quadrants['HV_HLT']['items']) ?></div>
        </div>
        <div class="stat-card" style="background: <?= $quadrants['HV_LLT']['bg'] ?>;">
            <div class="label">Leverage</div>
            <div class="value" style="color: <?= $quadrants['HV_LLT']['color'] ?>;"><?= count($quadrants['HV_LLT']['items']) ?></div>
        </div>
        <div class="stat-card" style="background: <?= $quadrants['LV_HLT']['bg'] ?>;">
            <div class="label">Bottleneck</div>
            <div class="value" style="color: <?= $quadrants['LV_HLT']['color'] ?>;"><?= count($quadrants['LV_HLT']['items']) ?></div>
        </div>
        <div class="stat-card" style="background: <?= $quadrants['LV_LLT']['bg'] ?>;">
            <div class="label">Non-Critical</div>
            <div class="value" style="color: <?= $quadrants['LV_LLT']['color'] ?>;"><?= count($quadrants['LV_LLT']['items']) ?></div>
        </div>
    </div>

    <!-- Threshold Controls -->
    <form method="get" class="threshold-form">
        <div>
            <label>Value Threshold (₹)</label><br>
            <input type="number" name="value_threshold" step="0.01" value="<?= $valueThreshold ?>" placeholder="<?= $medianValue ?>">
        </div>
        <div>
            <label>Lead Time Threshold (days)</label><br>
            <input type="number" name="lt_threshold" step="1" value="<?= $leadTimeThreshold ?>" placeholder="<?= $medianLeadTime ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 6px 16px;">Apply</button>
        <a href="matrix.php" class="btn btn-secondary" style="padding: 6px 16px; font-size: 0.9em;">Reset to Median</a>
    </form>

    <!-- Axis Labels -->
    <div style="text-align: center; margin-bottom: 5px;">
        <span class="axis-label" style="color: #dc2626;">← High Lead Time</span>
        <span style="margin: 0 30px; color: #ccc;">|</span>
        <span class="axis-label" style="color: #16a34a;">Low Lead Time →</span>
    </div>

    <!-- Matrix Grid -->
    <div style="display: flex; gap: 0;">
        <!-- Y-axis label -->
        <div style="writing-mode: vertical-lr; transform: rotate(180deg); display: flex; align-items: center; justify-content: center; padding: 0 8px; font-weight: 600; font-size: 0.85em;">
            <span style="color: #dc2626;">High Value ↑</span>
            <span style="margin: 15px 0; color: #ccc;">—</span>
            <span style="color: #16a34a;">Low Value ↓</span>
        </div>

        <div class="matrix-grid" style="flex: 1;">
            <!-- Top-Left: High Value + High Lead Time (Strategic) -->
            <div class="quadrant" style="background: <?= $quadrants['HV_HLT']['bg'] ?>; border-color: <?= $quadrants['HV_HLT']['border'] ?>;">
                <div class="quadrant-header">
                    <div>
                        <div class="quadrant-title" style="color: <?= $quadrants['HV_HLT']['color'] ?>;">Strategic</div>
                        <div class="quadrant-desc">High Value · High Lead Time</div>
                    </div>
                    <span class="quadrant-count" style="background: <?= $quadrants['HV_HLT']['color'] ?>;"><?= count($quadrants['HV_HLT']['items']) ?></span>
                </div>
                <div class="quadrant-tip" style="font-size: 0.78em; color: #991b1b; background: #fee2e2; padding: 5px 8px; border-radius: 4px; margin-bottom: 8px;">
                    Build partnerships, ensure supply security, maintain safety stock
                </div>
                <?php renderQuadrantItems($quadrants['HV_HLT']['items'], 'HV_HLT'); ?>
            </div>

            <!-- Top-Right: High Value + Low Lead Time (Leverage) -->
            <div class="quadrant" style="background: <?= $quadrants['HV_LLT']['bg'] ?>; border-color: <?= $quadrants['HV_LLT']['border'] ?>;">
                <div class="quadrant-header">
                    <div>
                        <div class="quadrant-title" style="color: <?= $quadrants['HV_LLT']['color'] ?>;">Leverage</div>
                        <div class="quadrant-desc">High Value · Low Lead Time</div>
                    </div>
                    <span class="quadrant-count" style="background: <?= $quadrants['HV_LLT']['color'] ?>;"><?= count($quadrants['HV_LLT']['items']) ?></span>
                </div>
                <div class="quadrant-tip" style="font-size: 0.78em; color: #1e40af; background: #dbeafe; padding: 5px 8px; border-radius: 4px; margin-bottom: 8px;">
                    Negotiate aggressively, use competitive bidding, consolidate volumes
                </div>
                <?php renderQuadrantItems($quadrants['HV_LLT']['items'], 'HV_LLT'); ?>
            </div>

            <!-- Bottom-Left: Low Value + High Lead Time (Bottleneck) -->
            <div class="quadrant" style="background: <?= $quadrants['LV_HLT']['bg'] ?>; border-color: <?= $quadrants['LV_HLT']['border'] ?>;">
                <div class="quadrant-header">
                    <div>
                        <div class="quadrant-title" style="color: <?= $quadrants['LV_HLT']['color'] ?>;">Bottleneck</div>
                        <div class="quadrant-desc">Low Value · High Lead Time</div>
                    </div>
                    <span class="quadrant-count" style="background: <?= $quadrants['LV_HLT']['color'] ?>;"><?= count($quadrants['LV_HLT']['items']) ?></span>
                </div>
                <div class="quadrant-tip" style="font-size: 0.78em; color: #92400e; background: #fef3c7; padding: 5px 8px; border-radius: 4px; margin-bottom: 8px;">
                    Secure supply, find alternatives, keep buffer stock
                </div>
                <?php renderQuadrantItems($quadrants['LV_HLT']['items'], 'LV_HLT'); ?>
            </div>

            <!-- Bottom-Right: Low Value + Low Lead Time (Non-Critical) -->
            <div class="quadrant" style="background: <?= $quadrants['LV_LLT']['bg'] ?>; border-color: <?= $quadrants['LV_LLT']['border'] ?>;">
                <div class="quadrant-header">
                    <div>
                        <div class="quadrant-title" style="color: <?= $quadrants['LV_LLT']['color'] ?>;">Non-Critical</div>
                        <div class="quadrant-desc">Low Value · Low Lead Time</div>
                    </div>
                    <span class="quadrant-count" style="background: <?= $quadrants['LV_LLT']['color'] ?>;"><?= count($quadrants['LV_LLT']['items']) ?></span>
                </div>
                <div class="quadrant-tip" style="font-size: 0.78em; color: #166534; background: #dcfce7; padding: 5px 8px; border-radius: 4px; margin-bottom: 8px;">
                    Simplify ordering, automate, reduce admin effort
                </div>
                <?php renderQuadrantItems($quadrants['LV_LLT']['items'], 'LV_LLT'); ?>
            </div>
        </div>
    </div>

    <!-- Full Parts Table -->
    <div style="margin-top: 30px;">
        <h3>All Parts Detail</h3>
        <div style="margin-bottom: 10px;">
            <input type="text" id="tableSearch" placeholder="Search parts..." style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; width: 300px; font-size: 0.9em;">
            <select id="quadrantFilter" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9em; margin-left: 8px;">
                <option value="all">All Quadrants</option>
                <option value="HV_HLT">Strategic (HV/HLT)</option>
                <option value="HV_LLT">Leverage (HV/LLT)</option>
                <option value="LV_HLT">Bottleneck (LV/HLT)</option>
                <option value="LV_LLT">Non-Critical (LV/LLT)</option>
            </select>
        </div>
        <div style="overflow-x: auto;">
            <table id="partsTable">
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>YID</th>
                        <th>Category</th>
                        <th>Value (₹)</th>
                        <th>Lead Time (days)</th>
                        <th>Supplier</th>
                        <th>Stock</th>
                        <th>Quadrant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($quadrants as $qKey => $q) {
                        foreach ($q['items'] as $item) {
                            $qLabel = $q['label'];
                            $qColor = $q['color'];
                            echo '<tr data-quadrant="' . $qKey . '">';
                            echo '<td><a href="/part_master/view.php?part_no=' . urlencode($item['part_no']) . '" style="color:#2563eb; text-decoration:none; font-weight:500;">' . htmlspecialchars($item['part_no']) . '</a></td>';
                            echo '<td>' . htmlspecialchars($item['part_name']) . '</td>';
                            echo '<td><strong>' . htmlspecialchars($item['part_id'] ?? '-') . '</strong></td>';
                            echo '<td>' . htmlspecialchars($item['category'] ?? '-') . '</td>';
                            echo '<td style="text-align:right; font-weight:600;">₹ ' . number_format($item['value'], 2) . '</td>';
                            echo '<td style="text-align:center; font-weight:600;">' . $item['lead_time'] . '</td>';
                            echo '<td>' . htmlspecialchars($item['supplier_name'] ?? '-') . '</td>';
                            echo '<td style="text-align:center;">' . $item['current_stock'] . '</td>';
                            echo '<td><span style="display:inline-block; padding:3px 8px; background:' . $qColor . '20; color:' . $qColor . '; border-radius:12px; font-size:0.8em; font-weight:600;">' . $qLabel . '</span></td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Table search
    var searchInput = document.getElementById('tableSearch');
    var filterSelect = document.getElementById('quadrantFilter');
    var table = document.getElementById('partsTable');

    function filterTable() {
        var search = searchInput.value.toLowerCase();
        var quadrant = filterSelect.value;
        var rows = table.querySelectorAll('tbody tr');

        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            var rowQuadrant = row.getAttribute('data-quadrant');
            var matchSearch = !search || text.indexOf(search) !== -1;
            var matchQuadrant = quadrant === 'all' || rowQuadrant === quadrant;
            row.style.display = (matchSearch && matchQuadrant) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterTable);
    filterSelect.addEventListener('change', filterTable);

    // Toggle show all items in quadrant
    document.querySelectorAll('.more-link').forEach(function(link) {
        link.addEventListener('click', function() {
            var target = this.getAttribute('data-target');
            var el = document.getElementById(target);
            if (el) {
                el.classList.toggle('show');
                this.textContent = el.classList.contains('show') ? 'Show less' : this.getAttribute('data-text');
            }
        });
    });
});
</script>

</body>
</html>

<?php
function renderQuadrantItems($items, $qKey) {
    if (empty($items)) {
        echo '<div style="text-align:center; color:#999; padding:20px; font-size:0.9em;">No parts in this quadrant</div>';
        return;
    }

    // Sort by value descending
    usort($items, function($a, $b) { return $b['value'] <=> $a['value']; });

    $show = 5;
    $total = count($items);
    $visible = array_slice($items, 0, $show);
    $hidden = array_slice($items, $show);

    foreach ($visible as $item) {
        renderPartRow($item);
    }

    if (!empty($hidden)) {
        echo '<div class="part-list-full" id="more-' . $qKey . '">';
        foreach ($hidden as $item) {
            renderPartRow($item);
        }
        echo '</div>';
        echo '<a class="more-link" data-target="more-' . $qKey . '" data-text="Show all ' . $total . ' items">Show all ' . $total . ' items</a>';
    }
}

function renderPartRow($item) {
    echo '<div class="part-row">';
    echo '  <div class="part-info">';
    echo '    <span class="part-no">' . htmlspecialchars($item['part_no']) . '</span>';
    echo '    <span class="part-name">' . htmlspecialchars($item['part_name']) . '</span>';
    echo '  </div>';
    echo '  <div class="part-metrics">';
    echo '    <div class="metric"><div style="font-weight:600;">₹' . number_format($item['value'], 0) . '</div><div class="metric-label">Value</div></div>';
    echo '    <div class="metric"><div style="font-weight:600;">' . $item['lead_time'] . 'd</div><div class="metric-label">Lead</div></div>';
    echo '  </div>';
    echo '</div>';
}
?>
