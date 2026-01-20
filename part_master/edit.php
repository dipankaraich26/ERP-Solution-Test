<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

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
        $stmt = $pdo->prepare("
            UPDATE part_master
            SET part_name=?, part_id=?, description=?, uom=?, category=?, rate=?, hsn_code=?, gst=?, attachment_path=?
            WHERE part_no=?
        ");
        $stmt->execute([
            $_POST['part_name'],
            $_POST['part_id'],
            $_POST['description'],
            $_POST['uom'],
            $_POST['category'],
            $_POST['rate'],
            $_POST['hsn_code'],
            $_POST['gst'],
            $attachmentPath,
            $part_no
        ]);

        header("Location: list.php");
        exit;
    }
}
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
        Part Name <input name="part_name" value="<?= htmlspecialchars($part['part_name']) ?>" required><br><br>
        Part ID <input name="part_id" value="<?= htmlspecialchars($part['part_id']) ?>"><br><br>
        Description <input name="description" value="<?= htmlspecialchars($part['description']) ?>"><br><br>
        UOM <input name="uom" value="<?= htmlspecialchars($part['uom']) ?>"><br><br>
        Category
        <select name="category" required>
            <option value="">-- Select Category --</option>
            <option value="Assembly" <?= $part['category'] === 'Assembly' ? 'selected' : '' ?>>Assembly</option>
            <option value="Machining" <?= $part['category'] === 'Machining' ? 'selected' : '' ?>>Machining</option>
            <option value="Brought Out" <?= $part['category'] === 'Brought Out' ? 'selected' : '' ?>>Brought Out</option>
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
