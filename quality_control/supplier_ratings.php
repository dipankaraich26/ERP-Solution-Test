<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get current period
$current_period = date('Y-m');
$period_filter = isset($_GET['period']) ? $_GET['period'] : $current_period;

// Get supplier ratings for the period
try {
    $sql = "
        SELECT r.*, s.supplier_name as supplier_name
        FROM qc_supplier_ratings r
        INNER JOIN suppliers s ON r.supplier_id = s.id
        WHERE r.rating_period = ?
        ORDER BY r.overall_score DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$period_filter]);
    $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ratings = [];
}

// Get available periods
try {
    $periods = $pdo->query("SELECT DISTINCT rating_period FROM qc_supplier_ratings ORDER BY rating_period DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $periods = [];
}

// Get suppliers for manual rating
try {
    $suppliers = $pdo->query("SELECT id, supplier_name as name FROM suppliers ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $suppliers = [];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier Ratings - QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .filter-section {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .rating-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 20px;
            align-items: center;
        }

        .supplier-info h3 { margin: 0 0 5px 0; color: #2c3e50; }

        .grade-badge {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            font-weight: bold;
            color: white;
        }
        .grade-a { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .grade-b { background: linear-gradient(135deg, #3498db, #5dade2); }
        .grade-c { background: linear-gradient(135deg, #f39c12, #f1c40f); }
        .grade-d { background: linear-gradient(135deg, #e67e22, #d35400); }
        .grade-f { background: linear-gradient(135deg, #e74c3c, #c0392b); }

        .scores-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .score-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .score-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        .score-label {
            font-size: 0.8em;
            color: #666;
            margin-top: 3px;
        }

        .overall-score {
            text-align: center;
        }
        .overall-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        .overall-label {
            font-size: 0.9em;
            color: #666;
        }

        .metrics-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .metric-item {
            text-align: center;
            font-size: 0.85em;
        }
        .metric-value { font-weight: bold; color: #2c3e50; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
        }

        .grade-legend {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        .legend-badge {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.8em;
        }

        body.dark .rating-card { background: #2c3e50; }
        body.dark .supplier-info h3 { color: #ecf0f1; }
        body.dark .filter-section { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "Light Mode";
    }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "Light Mode" : "Dark Mode";
    });
}
</script>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Supplier Quality Ratings</h1>
            <p style="color: #666; margin: 5px 0 0;">Supplier performance scores and grades</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="rating_add.php" class="btn btn-primary">+ Add Rating</a>
            <a href="rating_calculate.php" class="btn btn-secondary">Calculate Ratings</a>
        </div>
    </div>

    <!-- Grade Legend -->
    <div class="grade-legend">
        <div class="legend-item"><span class="legend-badge grade-a">A</span> 90-100: Excellent</div>
        <div class="legend-item"><span class="legend-badge grade-b">B</span> 80-89: Good</div>
        <div class="legend-item"><span class="legend-badge grade-c">C</span> 70-79: Acceptable</div>
        <div class="legend-item"><span class="legend-badge grade-d">D</span> 60-69: Poor</div>
        <div class="legend-item"><span class="legend-badge grade-f">F</span> &lt;60: Unacceptable</div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center;">
            <label style="font-weight: 600;">Period:</label>
            <select name="period" onchange="this.form.submit()">
                <option value="<?= $current_period ?>" <?= $period_filter === $current_period ? 'selected' : '' ?>>
                    <?= date('F Y', strtotime($current_period . '-01')) ?> (Current)
                </option>
                <?php foreach ($periods as $p): ?>
                    <?php if ($p !== $current_period): ?>
                        <option value="<?= $p ?>" <?= $period_filter === $p ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($p . '-01')) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= count($ratings) ?> supplier<?= count($ratings) != 1 ? 's' : '' ?> rated
        </div>
    </div>

    <!-- Ratings List -->
    <?php if (empty($ratings)): ?>
        <div class="empty-state">
            <h3>No Ratings for This Period</h3>
            <p>No supplier ratings recorded for <?= date('F Y', strtotime($period_filter . '-01')) ?>.</p>
            <a href="rating_add.php" class="btn btn-primary" style="margin-top: 15px;">+ Add Rating</a>
        </div>
    <?php else: ?>
        <?php foreach ($ratings as $rating):
            $grade = $rating['grade'] ?: 'C';
        ?>
            <div class="rating-card">
                <div class="supplier-info">
                    <div class="grade-badge grade-<?= strtolower($grade) ?>"><?= $grade ?></div>
                    <h3 style="margin-top: 10px;"><?= htmlspecialchars($rating['supplier_name']) ?></h3>
                </div>

                <div class="scores-grid">
                    <div class="score-item">
                        <div class="score-value"><?= number_format($rating['quality_score'], 1) ?></div>
                        <div class="score-label">Quality</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?= number_format($rating['delivery_score'], 1) ?></div>
                        <div class="score-label">Delivery</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?= number_format($rating['response_score'], 1) ?></div>
                        <div class="score-label">Response</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?= number_format($rating['documentation_score'], 1) ?></div>
                        <div class="score-label">Docs</div>
                    </div>
                </div>

                <div class="overall-score">
                    <div class="overall-value"><?= number_format($rating['overall_score'], 1) ?></div>
                    <div class="overall-label">Overall Score</div>
                </div>

                <div class="metrics-row" style="grid-column: 1 / -1;">
                    <div class="metric-item">
                        <div class="metric-value"><?= $rating['total_lots_received'] ?></div>
                        <div>Lots Received</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value" style="color: #27ae60;"><?= $rating['lots_accepted'] ?></div>
                        <div>Lots Accepted</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value" style="color: #e74c3c;"><?= $rating['lots_rejected'] ?></div>
                        <div>Lots Rejected</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?= number_format($rating['ppm'], 0) ?></div>
                        <div>PPM</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?= $rating['ncr_count'] ?></div>
                        <div>NCRs</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?= number_format($rating['on_time_delivery_pct'], 1) ?>%</div>
                        <div>OTD</div>
                    </div>
                    <div class="metric-item">
                        <a href="supplier_rating_view.php?id=<?= $rating['id'] ?>" class="btn btn-sm btn-secondary">Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
