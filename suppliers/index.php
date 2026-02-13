<?php
// TEMPORARY: Enable error display to debug 500 errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('suppliers');
include "../includes/dialog.php";

$error = '';

/* =========================
   GENERATE NEXT SUPPLIER ID
========================= */
$max = $pdo->query("
    SELECT MAX(CAST(SUBSTRING(supplier_code, 4) AS UNSIGNED))
    FROM suppliers
    WHERE supplier_code LIKE 'SUP%'
")->fetchColumn();

$next = $max ? ((int)$max + 1) : 1;
$supplier_code = 'SUP' . str_pad($next, 3, '0', STR_PAD_LEFT);

/* =========================
   HANDLE ADD SUPPLIER
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = $_POST['supplier_code']; // This will be the auto-generated code
    $name = $_POST['supplier_name'];
    $contact = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address1 = $_POST['address1'];
    $address2 = $_POST['address2'];
    $city = $_POST['city'];
    $pincode = $_POST['pincode'];
    $state = $_POST['state'];
    $gstin = $_POST['gstin'];

    try {
        $pdo->prepare("
            INSERT INTO suppliers
            (supplier_code, supplier_name, contact_person, phone, email, address1, address2, city, pincode, state, gstin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$code, $name, $contact, $phone, $email, $address1, $address2, $city, $pincode, $state, $gstin]);

        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $error = "Supplier code must be unique";
    }
}

/* =========================
   SUPPLIER LIST WITH PAGINATION AND SEARCH
========================= */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause for search
$whereClause = "";
$searchParams = [];
if (!empty($search)) {
    $whereClause = " WHERE supplier_code LIKE ? OR supplier_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ? OR state LIKE ?";
    $searchTerm = "%$search%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Get total count with search
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers" . $whereClause);
if (!empty($searchParams)) {
    $countStmt->execute($searchParams);
} else {
    $countStmt->execute();
}
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get suppliers with search and pagination
$stmt = $pdo->prepare("
    SELECT * FROM suppliers
    $whereClause
    ORDER BY supplier_code DESC
    LIMIT ? OFFSET ?
");

// Bind search parameters
$paramIndex = 1;
if (!empty($searchParams)) {
    foreach ($searchParams as $value) {
        $stmt->bindValue($paramIndex++, $value, PDO::PARAM_STR);
    }
}

// Bind LIMIT and OFFSET as integers
$stmt->bindValue($paramIndex++, $per_page, PDO::PARAM_INT);
$stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);

$stmt->execute();
$suppliers = $stmt;

include "../includes/sidebar.php";
showModal();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Suppliers</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Suppliers</h1>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <a href="import.php" class="btn btn-primary">Import from Excel</a>
            <a href="download_template.php" class="btn btn-secondary">Download Template</a>
        </div>
        <div>
            <form method="get" style="display: flex; gap: 10px;" id="searchForm">
                <input type="text"
                       name="search"
                       id="searchInput"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search suppliers..."
                       style="padding: 8px 15px; border: 1px solid #ddd; border-radius: 4px; width: 300px;">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ADD SUPPLIER FORM -->
    <form method="post" class="form-grid" style="max-width: 600px; margin-bottom: 30px;">
        <label>Supplier ID</label>
        <input name="supplier_code" value="<?= htmlspecialchars($supplier_code) ?>" readonly style="background: #f0f0f0; cursor: not-allowed;">

        <label>Name</label>
        <input name="supplier_name" required>

        <label>Contact Person</label>
        <input name="contact_person">

        <label>Phone</label>
        <input name="phone">

        <label>Email</label>
        <input name="email" type="email">

        <label>Address Line 1</label>
        <input name="address1">

        <label>Address Line 2</label>
        <input name="address2">

        <label>State</label>
        <select name="state" id="state_select_add" onchange="loadCitiesAdd(this.value)">
            <option value="">-- Select State --</option>
            <?php
            try {
                $states = $pdo->query("SELECT id, state_name FROM states WHERE is_active = 1 ORDER BY state_name")->fetchAll();
                foreach ($states as $state) {
                    echo '<option value="' . htmlspecialchars($state['state_name']) . '">' . htmlspecialchars($state['state_name']) . '</option>';
                }
            } catch (PDOException $e) {
                // Table might not exist
            }
            ?>
        </select>

        <label>City</label>
        <select id="city_select_add" name="city">
            <option value="">-- Select City --</option>
        </select>

        <label>Pincode</label>
        <input name="pincode" maxlength="10">

        <label>GSTIN</label>
        <input name="gstin" placeholder="15-character GSTIN">

        <div></div>
        <button type="submit" class="btn btn-primary">Add Supplier</button>
    </form>

    <script>
    function loadCitiesAdd(stateName) {
        const citySelect = document.getElementById('city_select_add');
        citySelect.innerHTML = '<option value="">Loading...</option>';

        if (!stateName) {
            citySelect.innerHTML = '<option value="">-- Select City --</option>';
            return;
        }

        fetch('../api/get_cities.php?state=' + encodeURIComponent(stateName))
            .then(response => response.json())
            .then(data => {
                citySelect.innerHTML = '<option value="">-- Select City --</option>';
                const cities = data.cities || [];
                if (Array.isArray(cities) && cities.length > 0) {
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.city_name;
                        option.textContent = city.city_name;
                        citySelect.appendChild(option);
                    });
                } else {
                    citySelect.innerHTML = '<option value="">-- No cities found --</option>';
                }
            })
            .catch(error => {
                console.error('Error loading cities:', error);
                citySelect.innerHTML = '<option value="">-- Error loading cities --</option>';
            });
    }
    </script>

    <hr>

    <!-- SUPPLIER TABLE -->
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Address 1</th>
            <th>Address 2</th>
            <th>City</th>
            <th>Pincode</th>
            <th>State</th>
            <th>GSTIN</th>
            <th>Action</th>
        </tr>

        <?php while ($s = $suppliers->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($s['supplier_code']) ?></td>
            <td><?= htmlspecialchars($s['supplier_name']) ?></td>
            <td><?= htmlspecialchars($s['contact_person']) ?></td>
            <td><?= htmlspecialchars($s['phone']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td><?= htmlspecialchars($s['address1'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['address2'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['city'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['pincode'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['state'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['gstin'] ?? '') ?></td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="edit.php?id=<?= $s['id'] ?>">Edit</a>
                <a class="btn btn-danger" href="delete.php?id=<?= $s['id'] ?>"
                   onclick="return confirm('Delete supplier?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total suppliers)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
