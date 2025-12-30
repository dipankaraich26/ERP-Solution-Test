<html><head><link rel="stylesheet" href="/erp/assets/style.css"></head></html>

<?php
include "../db.php";
include "../includes/sidebar.php";

/* =========================
   FETCH PARTS
========================= */
$parts = $pdo->query("
    SELECT part_no, part_name
    FROM part_master
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH BOM + ITEMS
========================= */
$boms = $pdo->query("
    SELECT 
        b.id,
        b.bom_no,
        p.part_no AS product_part_no,
        p.part_name AS product_name
    FROM bom_master b
    JOIN part_master p ON p.part_no = b.product_part_no
    ORDER BY b.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>BOM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script>
        function addRow() {
            const table = document.getElementById('bomItems');
            const row = table.insertRow(-1);

            row.innerHTML = `
                <td>
                    <select name="parts[]">
                        <option value="">Select</option>
                        <?php foreach ($parts as $p): ?>
                            <option value="<?= $p['part_no'] ?>">
                                <?= htmlspecialchars($p['part_name']) ?> (<?= $p['part_no'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" name="qty[]" step="0.01" min="0">
                </td>
            `;
        }
    </script>
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
</body>
</html>

<body>

<div class="content">
    <h1>Bill of Materials</h1>

    <!-- =====================
         ADD BOM FORM
    ====================== -->
    <form method="post" action="add.php">
        <h3>Create BOM</h3>

        Product
        <select name="product_part_no" required>
            <option value="">Select Product</option>
            <?php foreach ($parts as $p): ?>
                <option value="<?= $p['part_no'] ?>">
                    <?= htmlspecialchars($p['part_name']) ?> (<?= $p['part_no'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <br><br>

        <table border="1" cellpadding="6" id="bomItems">
            <tr>
                <th>Component Part</th>
                <th>Quantity</th>
            </tr>
            <tr>
                <td>
                    <select name="parts[]">
                        <option value="">Select</option>
                        <?php foreach ($parts as $p): ?>
                            <option value="<?= $p['part_no'] ?>">
                                <?= htmlspecialchars($p['part_name']) ?> (<?= $p['part_no'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" name="qty[]" step="0.01" min="0">
                </td>
            </tr>
        </table>

        <br>
        <button type="button" onclick="addRow()">+ Add Component</button>
        <br><br>

        <button type="submit">Save BOM</button>
    </form>

    <hr>

    <!-- =====================
         BOM LIST
    ====================== -->
    <table border="1" cellpadding="8" width="100%">
        <tr>
            <th>BOM No</th>
            <th>Product</th>
            <th>Components</th>
        </tr>

        <?php foreach ($boms as $b): ?>
        <tr>
            <td><?= $b['bom_no'] ?></td>
            <td>
                <?= htmlspecialchars($b['product_name']) ?><br>
                <small><?= $b['product_part_no'] ?></small>
            </td>
            <td>
                <table border="0" cellpadding="4">
                    <tr>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Qty</th>
                    </tr>
                    <?php
                    $items = $pdo->prepare("
                        SELECT bi.part_no, bi.qty, pm.part_name
                        FROM bom_items bi
                        JOIN part_master pm ON pm.part_no = bi.part_no
                        WHERE bi.bom_id = ?
                    ");
                    $items->execute([$b['id']]);
                    foreach ($items as $i):
                    ?>
                    <tr>
                        <td><?= $i['part_no'] ?></td>
                        <td><?= htmlspecialchars($i['part_name']) ?></td>
                        <td><?= $i['qty'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>
