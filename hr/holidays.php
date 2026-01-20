<?php
include "../db.php";
include "../includes/dialog.php";

$year = $_GET['year'] ?? date('Y');

// Handle add holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $holiday_date = $_POST['holiday_date'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'Company';

        if ($holiday_date && $name) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO holidays (holiday_date, name, type, year)
                VALUES (?, ?, ?, YEAR(?))
            ");
            $stmt->execute([$holiday_date, $name, $type, $holiday_date]);
            setModal("Success", "Holiday added successfully!");
        }
        header("Location: holidays.php?year=$year");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM holidays WHERE id = ?")->execute([$id]);
        setModal("Success", "Holiday deleted!");
        header("Location: holidays.php?year=$year");
        exit;
    }
}

// Fetch holidays
$stmt = $pdo->prepare("SELECT * FROM holidays WHERE year = ? ORDER BY holiday_date");
$stmt->execute([$year]);
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Holidays - <?= $year ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .holiday-container { max-width: 900px; }

        .year-nav {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        .year-nav h2 { margin: 0; }
        .year-nav a { font-size: 1.5em; color: #3498db; text-decoration: none; }

        .add-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .add-form h3 { margin: 0 0 15px 0; }
        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-row .form-group { margin: 0; }
        .form-row input, .form-row select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .holiday-table { width: 100%; border-collapse: collapse; }
        .holiday-table th, .holiday-table td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .holiday-table th { background: #f5f5f5; }
        .holiday-table tr:hover { background: #fafafa; }

        .type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85em;
        }
        .type-National { background: #e8f5e9; color: #2e7d32; }
        .type-Regional { background: #e3f2fd; color: #1565c0; }
        .type-Company { background: #fff3e0; color: #e65100; }
        .type-Optional { background: #f3e5f5; color: #7b1fa2; }
    </style>
</head>
<body>

<div class="content">
    <div class="holiday-container">

        <div class="year-nav">
            <a href="?year=<?= $year - 1 ?>">&larr;</a>
            <h2>Holidays <?= $year ?></h2>
            <a href="?year=<?= $year + 1 ?>">&rarr;</a>
        </div>

        <p><a href="attendance.php" class="btn btn-secondary">Back to Attendance</a></p>

        <div class="add-form">
            <h3>Add Holiday</h3>
            <form method="post">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="holiday_date" required>
                    </div>
                    <div class="form-group">
                        <label>Holiday Name</label>
                        <input type="text" name="name" required placeholder="e.g., Diwali" style="width: 200px;">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="National">National</option>
                            <option value="Regional">Regional</option>
                            <option value="Company" selected>Company</option>
                            <option value="Optional">Optional</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">Add Holiday</button>
                </div>
            </form>
        </div>

        <table class="holiday-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Holiday</th>
                    <th>Type</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($holidays)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #7f8c8d;">No holidays added for <?= $year ?></td></tr>
                <?php else: ?>
                    <?php foreach ($holidays as $h): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($h['holiday_date'])) ?></td>
                        <td><?= date('l', strtotime($h['holiday_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($h['name']) ?></strong></td>
                        <td><span class="type-badge type-<?= $h['type'] ?>"><?= $h['type'] ?></span></td>
                        <td>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this holiday?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px; color: #7f8c8d;">
            <strong>Total Holidays:</strong> <?= count($holidays) ?>
        </div>
    </div>
</div>

</body>
</html>
