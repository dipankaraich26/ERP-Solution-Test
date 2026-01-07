<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>

<?php
require '../db.php';
require '../includes/sidebar.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 🔹 Sanitize + normalize input
    $part_no   = strtoupper(trim($_POST['part_no'] ?? ''));
    $part_name = trim($_POST['part_name'] ?? '');
    $part_id = trim($_POST['part_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $uom = trim($_POST['uom'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $rate = trim($_POST['rate'] ?? '');
    $gst       = trim($_POST['gst'] ?? '');

    // 🔹 Validation
    if ($part_no === '') {
        $errors[] = "Part No is required";
    }

    if ($part_name === '') {
        $errors[] = "Part Name is required";
    }

    if ($part_id === '') {
        $errors[] = "Part ID is required";
    }

    if ($description === '') {
        $errors[] = "Part Description is required";
    }

    if ($uom === '') {
        $errors[] = "Part UOM is required";
    }

    if ($category === '') {
        $errors[] = "Part Category is required";
    }

    if ($rate === '' || !is_numeric($rate) || $rate < 0) {
        $errors[] = "Rate must be a valid number";
    }

    if ($gst === '' || !is_numeric($gst) || $gst < 0) {
        $errors[] = "GST must be a valid number";
    }

    // 🔹 Uniqueness check (ONLY if no validation errors)
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM part_master 
            WHERE part_no = ?
        ");
        $stmt->execute([$part_no]);

        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Part No must be unique";
        }
    }

    // 🔹 Insert
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO part_master 
            (part_no, part_name, part_id, description, uom, category, rate, gst, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

    $stmt->execute([
        $part_no,
        $part_name,
        $part_id,
        $description,
        $uom,
        $category,
        $rate,
        $gst
    ]);

    $success = true;
}

}
?>



<!DOCTYPE html>
<html>
<head>
    <title>Add Part</title>
    <link rel="stylesheet" href="/erp/assets/style.css">
</head>
<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">

    <h2>Add Part</h2>

    
    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    
    <?php if ($success): ?>
        <div class="alert success">
            Part added successfully.
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Part No</label>
        <input type="text" name="part_no" required>
        <br><br>
        <label>Part Name</label>
        <input type="text" name="part_name" required>
        <br><br>
        <label>Part ID</label>
        <input type="text" name="part_id" required>
        <br><br>
        <label>Description</label>
        <input type="text" name="description" required>
        <br><br>
        <label>UOM</label>
        <input type="text" name="uom" required>
        <br><br>
        <label>Category</label>
        <input type="text" name="category" required>
        <br><br>
        <label>Rate</label>
        <input type="number" name="rate" step="0.01" min="0" required>
        <br><br>
        <label>GST (%)</label>
        <input type="number" name="gst" step="0.01" min="0" required>
        <br><br>
        <button type="submit">Add Part</button>
    </form>

</div>

</body>
</html>
