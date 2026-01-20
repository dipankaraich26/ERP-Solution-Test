<?php
include "../db.php";
include "../includes/dialog.php";

// Handle State Operations (BEFORE any output)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_state':
                    $pdo->prepare("INSERT INTO india_states (state_code, state_name, region) VALUES (?, ?, ?)")
                        ->execute([$_POST['state_code'], $_POST['state_name'], $_POST['region']]);
                    setModal("Success", "State added successfully");
                    break;

                case 'edit_state':
                    $pdo->prepare("UPDATE india_states SET state_code=?, state_name=?, region=? WHERE id=?")
                        ->execute([$_POST['state_code'], $_POST['state_name'], $_POST['region'], $_POST['state_id']]);
                    setModal("Success", "State updated successfully");
                    break;

                case 'delete_state':
                    $pdo->prepare("DELETE FROM india_states WHERE id=?")->execute([$_POST['state_id']]);
                    setModal("Success", "State deleted successfully");
                    break;

                case 'add_city':
                    $pdo->prepare("INSERT INTO india_cities (state_id, city_name) VALUES (?, ?)")
                        ->execute([$_POST['state_id'], $_POST['city_name']]);
                    setModal("Success", "City added successfully");
                    break;

                case 'delete_city':
                    $pdo->prepare("DELETE FROM india_cities WHERE id=?")->execute([$_POST['city_id']]);
                    setModal("Success", "City deleted successfully");
                    break;
            }
            header("Location: locations.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch all states
$states = $pdo->query("SELECT * FROM india_states ORDER BY state_name")->fetchAll();

// Fetch all cities with state names
$cities = $pdo->query("
    SELECT c.*, s.state_name
    FROM india_cities c
    JOIN india_states s ON c.state_id = s.id
    ORDER BY s.state_name, c.city_name
")->fetchAll();

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Location Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            align-items: center;
            max-width: 600px;
        }
        .form-grid label {
            font-weight: 500;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .action-btns {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-small {
            padding: 10px 14px;
            font-size: 13px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .btn-small:hover {
            opacity: 0.85;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        #stateFilter {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 14px;
            cursor: pointer;
        }
        .filter-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>

<body>

<div class="content">
    <h1>City Management</h1>

    <?php if (isset($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ADD CITY FORM -->
    <div class="form-section">
        <h2>Add New City</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add_city">

            <label>State:</label>
            <select name="state_id" required>
                <option value="">-- Select State --</option>
                <?php foreach ($states as $state): ?>
                    <option value="<?= $state['id'] ?>"><?= htmlspecialchars($state['state_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>City Name:</label>
            <input name="city_name" required placeholder="e.g., Mumbai">

            <div></div>
            <button type="submit" class="btn btn-primary">Add City</button>
        </form>
    </div>

    <!-- CITIES LIST -->
    <div>
        <h2>All Cities (<span id="cityCount"><?= count($cities) ?></span>)</h2>

        <div class="filter-section">
            <label><strong>Filter by State:</strong></label>
            <select id="stateFilter" onchange="filterCities()">
                <option value="">-- All States --</option>
                <?php foreach ($states as $state): ?>
                    <option value="<?= htmlspecialchars($state['state_name']) ?>"><?= htmlspecialchars($state['state_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <table id="citiesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>State</th>
                    <th>City Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cities as $city): ?>
                <tr class="city-row" data-state="<?= htmlspecialchars($city['state_name']) ?>">
                    <td><?= $city['id'] ?></td>
                    <td><?= htmlspecialchars($city['state_name']) ?></td>
                    <td><?= htmlspecialchars($city['city_name']) ?></td>
                    <td class="action-btns">
                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this city?')">
                            <input type="hidden" name="action" value="delete_city">
                            <input type="hidden" name="city_id" value="<?= $city['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Filter cities by state
function filterCities() {
    const selectedState = document.getElementById('stateFilter').value;
    const rows = document.querySelectorAll('.city-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowState = row.getAttribute('data-state');
        if (selectedState === '' || rowState === selectedState) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('cityCount').textContent = visibleCount;
}
</script>

</body>
</html>
