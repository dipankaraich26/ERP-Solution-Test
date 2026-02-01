<?php
include "../db.php";
include "../includes/dialog.php";

$part_no = $_GET['part_no'] ?? null;
$errors = [];

if (!$part_no) {
    setModal("Failed to edit part", "Part not specified");
    header("Location: list.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no=?");
$stmt->execute([$part_no]);
$part = $stmt->fetch();

if (!$part) {
    setModal("Failed to edit part", "Part not found");
    header("Location: list.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $attachmentPath = $part['attachment_path']; // Keep existing attachment by default

    // Handle new attachment upload
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = "../uploads/parts/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors[] = "Only PDF files are allowed";
        } else {
            $fileName = $part_no . "_" . time() . ".pdf";
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
                // Delete old attachment if exists
                if (!empty($part['attachment_path']) && file_exists("../" . $part['attachment_path'])) {
                    unlink("../" . $part['attachment_path']);
                }
                $attachmentPath = "uploads/parts/" . $fileName;
            } else {
                $errors[] = "Failed to upload attachment";
            }
        }
    }

    if (empty($errors)) {
        // Sanitize input
        $part_name = trim($_POST['part_name'] ?? '');
        $part_id_val = trim($_POST['part_id'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $uom = trim($_POST['uom'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $rate = $_POST['rate'] ?? 0;
        $hsn_code = trim($_POST['hsn_code'] ?? '');
        $gst = $_POST['gst'] ?? 0;

        $stmt = $pdo->prepare("
            UPDATE part_master
            SET part_name=?, part_id=?, description=?, uom=?, category=?, rate=?, hsn_code=?, gst=?, attachment_path=?
            WHERE part_no=?
        ");
        $stmt->execute([
            $part_name,
            $part_id_val,
            $description,
            $uom,
            $category,
            $rate,
            $hsn_code,
            $gst,
            $attachmentPath,
            $part_no
        ]);

        setModal("Success", "Part updated successfully!");
        header("Location: list.php");
        exit;
    }
}

// Include sidebar AFTER all redirects
include "../includes/sidebar.php";
showModal();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Part</title>
    <link rel="stylesheet" href="/assets/style.css">
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

<div class="content">
    <h1>Edit Part</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        Part Name <input type="text" name="part_name" value="<?= htmlspecialchars($part['part_name'] ?? '') ?>" required><br><br>
        Part ID <input type="text" name="part_id" value="<?= htmlspecialchars($part['part_id'] ?? '') ?>"><br><br>
        Description <input type="text" name="description" value="<?= htmlspecialchars($part['description'] ?? '') ?>"><br><br>
        UOM (Unit of Measure)
        <select name="uom" required>
            <option value="">-- Select UOM --</option>
            <?php
            $uomOptions = ['Nos' => 'Nos (Numbers)', 'Mtr' => 'Mtr (Meter)', 'Kg' => 'Kg (Kilogram)', 'Gm' => 'Gm (Gram)', 'Ltr' => 'Ltr (Litre)', 'Ml' => 'Ml (Millilitre)', 'Pcs' => 'Pcs (Pieces)', 'Set' => 'Set', 'Box' => 'Box', 'Roll' => 'Roll', 'Pair' => 'Pair', 'Ft' => 'Ft (Feet)', 'Sqm' => 'Sqm (Square Meter)', 'Sqft' => 'Sqft (Square Feet)'];
            $currentUom = $part['uom'] ?? '';
            foreach ($uomOptions as $value => $label):
            ?>
            <option value="<?= $value ?>" <?= $currentUom === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select><br><br>
        Category
        <select name="category" required>
            <option value="">-- Select Category --</option>
            <option value="Assembly" <?= $part['category'] === 'Assembly' ? 'selected' : '' ?>>Assembly</option>
            <option value="Brought Out" <?= $part['category'] === 'Brought Out' ? 'selected' : '' ?>>Brought Out</option>
            <option value="Finished Good" <?= $part['category'] === 'Finished Good' ? 'selected' : '' ?>>Finished Good</option>
            <option value="Manufacturing" <?= $part['category'] === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
            <option value="Printing" <?= $part['category'] === 'Printing' ? 'selected' : '' ?>>Printing</option>
        </select><br><br>
        Rate <input name="rate" type="number" step="0.01" value="<?= htmlspecialchars($part['rate']) ?>"><br><br>
        HSN Code <input name="hsn_code" value="<?= htmlspecialchars($part['hsn_code'] ?? '') ?>"><br><br>
        GST (%) <input name="gst" type="number" step="0.01" min="0" value="<?= htmlspecialchars($part['gst'] ?? '') ?>"><br><br>

        Attachment (PDF)
        <?php if (!empty($part['attachment_path'])): ?>
            <br><small>Current: <a href="../<?= htmlspecialchars($part['attachment_path']) ?>" target="_blank">View PDF</a></small>
        <?php endif; ?>
        <input type="file" name="attachment" accept="application/pdf"><br><br>

        <button type="submit">Update</button>
    </form>
</div>

</body>
</html>
