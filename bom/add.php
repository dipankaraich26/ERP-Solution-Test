<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

/* =========================
   FETCH ACTIVE PARTS
========================= */
$parts = $pdo->query("
    SELECT part_no, part_name
    FROM part_master
    WHERE status='active' AND category = 'ASSEMBLY'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH CHILD PARTS (ALL CATEGORIES)
========================= */
$child_parts = $pdo->query("
    SELECT part_no, part_name
    FROM part_master
    WHERE status='active'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH EXISTING BOM LINKS
========================= */
$boms = $pdo->query("
    SELECT bom_no, parent_part_no
    FROM bom_master
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HANDLE SAVE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (empty($_POST['component_part_no'])) {
        setModal("Failed to add BOM", "Use at least one component");
        header("Location: add.php");
        exit;
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("
            INSERT INTO bom_master (bom_no, parent_part_no, description)
            VALUES (?, ?, ?)
        ")->execute([
            $_POST['bom_no'],
            $_POST['parent_part_no'],
            $_POST['description']
        ]);

        $bom_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO bom_items (bom_id, component_part_no, qty)
            VALUES (?, ?, ?)
        ");

        foreach ($_POST['component_part_no'] as $i => $part) {

            if ($part === $_POST['parent_part_no']) {
                setModal("Failed to add BOM", "Parent part cannot be a component part.");
            header("Location: add.php");
            exit;
            }

            $stmt->execute([
                $bom_id,
                $part,
                $_POST['qty'][$i]
            ]);
        }

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Failed to add BOM", $e->getMessage());
        header("Location: add.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add BOM</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
<h1>Add BOM</h1>

<form method="post">

    <label>BOM No</label><br>
    <input name="bom_no" id="bom_no" readonly required><br><br>


    <!-- PARENT PART -->
    <label>Parent Part</label><br>
    <select name="parent_part_no" id="parent_part_no" required>
        <option value="">Select Part</option>
        <?php foreach ($parts as $p): ?>
            <option value="<?= $p['part_no'] ?>">
                <?= htmlspecialchars($p['part_name']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Description</label><br>
    <textarea name="description"></textarea><br><br>

    <h3>Components</h3>

    <table border="1" cellpadding="8" id="bomTable">
        <tr>
            <th>Component</th>
            <th>Qty</th>
            <th></th>
        </tr>
        <tr>
            <td>
                <select name="component_part_no[]" required>
                    <?php foreach ($child_parts as $p): ?>
                        <option value="<?= $p['part_no'] ?>"><?= htmlspecialchars($p['part_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" step="0.001" name="qty[]" required></td>
            <td><button type="button" onclick="addRow()">➕</button></td>
        </tr>

        <!-- Hidden template row used by addRow() -->
        <tr id="templateRow" style="display:none;">
            <td>
                <select name="component_part_no[]" disabled>
                    <?php foreach ($child_parts as $p): ?>
                        <option value="<?= $p['part_no'] ?>"><?= htmlspecialchars($p['part_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" step="0.001" name="qty[]" disabled></td>
            <td><button type="button" onclick="removeRow(this)">➖</button></td>
        </tr>
    </table>

    <br>
    <button type="submit" class="btn btn-success">Save BOM</button>
</form>
</div>

<script>
document.getElementById('parent_part_no').addEventListener('change', function () {
    const partNo = this.value;
    const bomField = document.getElementById('bom_no');

    if (partNo) {
        bomField.value = 'BOM-' + partNo;
    } else {
        bomField.value = '';
    }
});

function addRow() {
    const tpl = document.getElementById('templateRow');
    if (!tpl) return;
    // Clone the hidden template row and insert it before the template
    const clone = tpl.cloneNode(true);
    clone.removeAttribute('id'); // remove duplicate id
    clone.style.display = ''; // make it visible

    // Clear any values in the cloned inputs/selects
    const sel = clone.querySelector('select[name="component_part_no[]"]');
    const qty = clone.querySelector('input[name="qty[]"]');
    if (sel) sel.selectedIndex = 0;
    if (qty) qty.value = '';

    // Enable the cloned controls (template ones are disabled to avoid validation)
    if (sel) {
        sel.disabled = false;
        sel.required = true;
    }
    if (qty) {
        qty.disabled = false;
        qty.required = true;
    }

    // Insert the new row before the hidden template so template stays at the end
    tpl.parentNode.insertBefore(clone, tpl);
}

function removeRow(btn) {
    const tr = btn.closest('tr');
    if (!tr) return;
    const table = document.getElementById('bomTable');
    // ensure at least one row remains
    const visibleRows = Array.from(table.querySelectorAll('tr')).filter(r => r.style.display !== 'none' && r.querySelector('select[name="component_part_no[]"]'));
    if (visibleRows.length <= 1) {
        // clear values instead of removing last row
        const sel = tr.querySelector('select[name="component_part_no[]"]');
        const qty = tr.querySelector('input[name="qty[]"]');
        if (sel) sel.selectedIndex = 0;
        if (qty) qty.value = '';
        return;
    }
    tr.remove();
}
</script>


</body>
</html>
