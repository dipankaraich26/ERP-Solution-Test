<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: audits.php");
    exit;
}

// Get audit details
try {
    $stmt = $pdo->prepare("
        SELECT a.*, s.name as supplier_name, s.contact_person, s.email, s.phone
        FROM qc_supplier_audits a
        LEFT JOIN suppliers s ON a.supplier_id = s.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $audit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$audit) {
        header("Location: audits.php");
        exit;
    }

    // Get findings
    $findings_stmt = $pdo->prepare("
        SELECT * FROM qc_supplier_audit_findings
        WHERE audit_id = ?
        ORDER BY severity DESC, id
    ");
    $findings_stmt->execute([$id]);
    $findings = $findings_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading audit: " . $e->getMessage();
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Audit - <?= htmlspecialchars($audit['audit_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .audit-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .audit-card h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item label {
            display: block;
            color: #666;
            font-size: 0.85em;
            margin-bottom: 5px;
        }
        .info-item .value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.05em;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .status-planned { background: #cce5ff; color: #004085; }
        .status-in-progress { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-closed { background: #e2e3e5; color: #383d41; }

        .result-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .result-approved { background: #d4edda; color: #155724; }
        .result-conditional { background: #fff3cd; color: #856404; }
        .result-not-approved { background: #f8d7da; color: #721c24; }
        .result-pending { background: #e2e3e5; color: #383d41; }

        .score-display {
            font-size: 2.5em;
            font-weight: 700;
            color: #667eea;
        }
        .score-label { color: #666; font-size: 0.9em; }

        .findings-table {
            width: 100%;
            border-collapse: collapse;
        }
        .findings-table th, .findings-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .findings-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .severity-critical { color: #721c24; font-weight: 600; }
        .severity-major { color: #856404; font-weight: 600; }
        .severity-minor { color: #004085; }
        .severity-observation { color: #383d41; }

        body.dark .audit-card { background: #2c3e50; }
        body.dark .audit-card h3, body.dark .info-item .value { color: #ecf0f1; }
        body.dark .findings-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="audit-header">
        <div>
            <h1 style="margin: 0 0 10px 0; color: #2c3e50;"><?= htmlspecialchars($audit['audit_no']) ?></h1>
            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $audit['status'])) ?>">
                <?= $audit['status'] ?>
            </span>
            <?php if ($audit['audit_result'] && $audit['audit_result'] !== 'Pending'): ?>
                <span class="result-badge result-<?= strtolower(str_replace(' ', '-', $audit['audit_result'])) ?>" style="margin-left: 10px;">
                    <?= $audit['audit_result'] ?>
                </span>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="audits.php" class="btn btn-secondary">Back to List</a>
            <?php if ($audit['status'] !== 'Closed'): ?>
                <a href="audit_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div>
            <!-- Audit Details -->
            <div class="audit-card">
                <h3>Audit Details</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Supplier</label>
                        <div class="value"><?= htmlspecialchars($audit['supplier_name'] ?: 'Unknown') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Audit Type</label>
                        <div class="value"><?= htmlspecialchars($audit['audit_type']) ?></div>
                    </div>
                    <div class="info-item">
                        <label>Audit Date</label>
                        <div class="value"><?= date('d M Y', strtotime($audit['audit_date'])) ?></div>
                    </div>
                    <div class="info-item">
                        <label>Lead Auditor</label>
                        <div class="value"><?= htmlspecialchars($audit['lead_auditor'] ?: '-') ?></div>
                    </div>
                    <?php if ($audit['audit_team']): ?>
                        <div class="info-item">
                            <label>Audit Team</label>
                            <div class="value"><?= htmlspecialchars($audit['audit_team']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($audit['next_audit_date']): ?>
                        <div class="info-item">
                            <label>Next Audit Date</label>
                            <div class="value"><?= date('d M Y', strtotime($audit['next_audit_date'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($audit['audit_scope']): ?>
                    <div style="margin-top: 20px;">
                        <label style="color: #666; font-size: 0.85em;">Audit Scope</label>
                        <p style="margin: 8px 0 0; color: #2c3e50;"><?= nl2br(htmlspecialchars($audit['audit_scope'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Findings -->
            <div class="audit-card">
                <h3>Audit Findings (<?= count($findings) ?>)</h3>
                <?php if (empty($findings)): ?>
                    <p style="color: #666; text-align: center; padding: 30px;">No findings recorded for this audit.</p>
                    <?php if ($audit['status'] === 'Completed'): ?>
                        <div style="text-align: center;">
                            <a href="audit_findings.php?id=<?= $id ?>" class="btn btn-primary">+ Add Findings</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <table class="findings-table">
                        <thead>
                            <tr>
                                <th>Finding</th>
                                <th>Severity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($findings as $f): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($f['finding_title']) ?></strong>
                                        <?php if ($f['finding_description']): ?>
                                            <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                                                <?= htmlspecialchars(substr($f['finding_description'], 0, 100)) ?>...
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="severity-<?= strtolower($f['severity']) ?>"><?= $f['severity'] ?></td>
                                    <td><?= $f['status'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <!-- Score Card -->
            <div class="audit-card" style="text-align: center;">
                <h3>Audit Score</h3>
                <?php if ($audit['audit_score'] > 0): ?>
                    <div class="score-display"><?= number_format($audit['audit_score'], 1) ?>%</div>
                    <div class="score-label">
                        <?php
                        if ($audit['audit_score'] >= 90) echo 'Excellent';
                        elseif ($audit['audit_score'] >= 80) echo 'Good';
                        elseif ($audit['audit_score'] >= 70) echo 'Acceptable';
                        elseif ($audit['audit_score'] >= 60) echo 'Needs Improvement';
                        else echo 'Poor';
                        ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666;">Score not yet recorded</p>
                <?php endif; ?>
            </div>

            <!-- Supplier Contact -->
            <?php if ($audit['supplier_name']): ?>
                <div class="audit-card">
                    <h3>Supplier Contact</h3>
                    <div class="info-item" style="margin-bottom: 15px;">
                        <label>Contact Person</label>
                        <div class="value"><?= htmlspecialchars($audit['contact_person'] ?: '-') ?></div>
                    </div>
                    <?php if ($audit['email']): ?>
                        <div class="info-item" style="margin-bottom: 15px;">
                            <label>Email</label>
                            <div class="value"><?= htmlspecialchars($audit['email']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($audit['phone']): ?>
                        <div class="info-item">
                            <label>Phone</label>
                            <div class="value"><?= htmlspecialchars($audit['phone']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if ($audit['notes']): ?>
                <div class="audit-card">
                    <h3>Notes</h3>
                    <p style="color: #2c3e50; margin: 0;"><?= nl2br(htmlspecialchars($audit['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
