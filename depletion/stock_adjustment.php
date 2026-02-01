<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

// Get all parts from part_master (for addition, we need all parts, not just those with stock)
$all_parts = $pdo->query("
    SELECT p.part_no, p.part_name, COALESCE(i.qty, 0) as qty
    FROM part_master p
    LEFT JOIN inventory i ON i.part_no = p.part_no
    WHERE p.status = 'active'
    ORDER BY p.part_name
")->fetchAll();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $part = $_POST['part_no'];
    $qty = (int)$_POST['qty'];
    $reason = $_POST['reason'];
    $adjustment_type = $_POST['adjustment_type'];

    if ($qty <= 0) {
        setModal("Error", "Quantity must be greater than 0");
        header("Location: stock_adjustment.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check current stock
        $stmt = $pdo->prepare("SELECT qty FROM inventory WHERE part_no = ?");
        $stmt->execute([$part]);
        $current_stock = $stmt->fetchColumn();

        if ($adjustment_type === 'depletion') {
            // Depletion - reduce stock
            if ($current_stock === false || $current_stock < $qty) {
                $pdo->rollBack();
                setModal("Failed", "Insufficient stock. Available: " . ($current_stock ?: 0));
                header("Location: stock_adjustment.php");
                exit;
            }

            // Update inventory
            $pdo->prepare("
                UPDATE inventory SET qty = qty - ?
                WHERE part_no = ?
            ")->execute([$qty, $part]);

            // Record in depletion table
            $pdo->prepare("
                INSERT INTO depletion (part_no, qty, issue_date, reason, status, issue_no, adjustment_type)
                VALUES (?, ?, CURDATE(), ?, 'issued', CONCAT('ADJ-', UNIX_TIMESTAMP()), 'depletion')
            ")->execute([$part, $qty, $reason]);

            $pdo->commit();
            setModal("Success", "Stock depleted successfully. Reduced $qty units.");

        } else {
            // Addition - increase stock
            if ($current_stock === false) {
                // Part not in inventory, insert new record
                $pdo->prepare("
                    INSERT INTO inventory (part_no, qty)
                    VALUES (?, ?)
                ")->execute([$part, $qty]);
            } else {
                // Update existing inventory
                $pdo->prepare("
                    UPDATE inventory SET qty = qty + ?
                    WHERE part_no = ?
                ")->execute([$qty, $part]);
            }

            // Record in depletion table (as addition)
            $pdo->prepare("
                INSERT INTO depletion (part_no, qty, issue_date, reason, status, issue_no, adjustment_type)
                VALUES (?, ?, CURDATE(), ?, 'issued', CONCAT('ADJ-', UNIX_TIMESTAMP()), 'addition')
            ")->execute([$part, $qty, $reason]);

            $pdo->commit();
            setModal("Success", "Stock added successfully. Added $qty units.");
        }

        header("Location: stock_adjustment.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Error", $e->getMessage());
        header("Location: stock_adjustment.php");
        exit;
    }
}

// Pagination for adjustment records
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$total_count = $pdo->query("SELECT COUNT(*) FROM depletion")->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$adjustments_stmt = $pdo->prepare("
    SELECT d.id, d.issue_no, p.part_name, d.part_no, d.qty, d.issue_date, d.reason, d.status,
           COALESCE(d.adjustment_type, 'depletion') as adjustment_type
    FROM depletion d
    JOIN part_master p ON p.part_no = d.part_no
    ORDER BY d.id DESC
    LIMIT :limit OFFSET :offset
");
$adjustments_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$adjustments_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$adjustments_stmt->execute();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stock Adjustment</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .adjustment-type-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .adjustment-type-selector label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .adjustment-type-selector input[type="radio"] {
            width: 18px;
            height: 18px;
        }
        .adjustment-type-selector label:has(input:checked) {
            border-color: #3498db;
            background: #ebf5fb;
        }
        .adjustment-type-selector label.depletion-label:has(input:checked) {
            border-color: #e74c3c;
            background: #fdedec;
        }
        .adjustment-type-selector label.addition-label:has(input:checked) {
            border-color: #27ae60;
            background: #eafaf1;
        }
        .type-depletion {
            color: #e74c3c;
            font-weight: bold;
        }
        .type-addition {
            color: #27ae60;
            font-weight: bold;
        }
        .stock-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
        }
        .stock-info.visible {
            display: block;
        }
        body.dark .stock-info {
            background: #2c3e50;
        }
        body.dark .adjustment-type-selector label {
            border-color: #4a5568;
            color: #ecf0f1;
        }
        body.dark .adjustment-type-selector label:has(input:checked) {
            background: #2c3e50;
        }
    </style>
</head>
<body>

<?php include "../includes/sidebar.php"; ?>

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
        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "Dark Mode";
        }
    });
}

// Part stock data
const partStock = {
    <?php foreach ($all_parts as $p): ?>
    "<?= $p['part_no'] ?>": <?= (int)$p['qty'] ?>,
    <?php endforeach; ?>
};

// Part data for search
const allParts = [
    <?php foreach ($all_parts as $p): ?>
    {
        part_no: "<?= htmlspecialchars($p['part_no']) ?>",
        part_name: "<?= htmlspecialchars($p['part_name']) ?>",
        qty: <?= (int)$p['qty'] ?>
    },
    <?php endforeach; ?>
];

function updateStockInfo() {
    const partSelect = document.getElementById('part_select');
    const stockInfo = document.getElementById('stock_info');
    const stockQty = document.getElementById('stock_qty');
    const selectedPart = partSelect.value;

    if (selectedPart && partStock[selectedPart] !== undefined) {
        stockQty.textContent = partStock[selectedPart];
        stockInfo.classList.add('visible');
    } else {
        stockInfo.classList.remove('visible');
    }
}

function updateButtonStyle() {
    const submitBtn = document.getElementById('submit_btn');
    const depletionRadio = document.getElementById('type_depletion');

    if (depletionRadio.checked) {
        submitBtn.className = 'btn btn-danger';
        submitBtn.textContent = 'Deplete Stock';
    } else {
        submitBtn.className = 'btn btn-primary';
        submitBtn.textContent = 'Add Stock';
    }
}

// Part search functionality
function searchParts(query) {
    const dropdown = document.getElementById('part_dropdown');
    const partSelect = document.getElementById('part_select');

    if (!query.trim()) {
        dropdown.style.display = 'none';
        return;
    }

    const lowerQuery = query.toLowerCase();
    const filtered = allParts.filter(part =>
        part.part_no.toLowerCase().includes(lowerQuery) ||
        part.part_name.toLowerCase().includes(lowerQuery)
    );

    if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding: 12px; color: #666; text-align: center;">No parts found</div>';
        dropdown.style.display = 'block';
        return;
    }

    dropdown.innerHTML = filtered.map(part => `
        <div class="part-option" data-part-no="${part.part_no}"
             style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">
            <div style="font-weight: bold; color: #2c3e50;">${part.part_no}</div>
            <div style="font-size: 0.9em; color: #666; margin-top: 4px;">${part.part_name}</div>
            <div style="font-size: 0.85em; color: #999; margin-top: 4px;">Stock: <strong>${part.qty}</strong> units</div>
        </div>
    `).join('');

    dropdown.style.display = 'block';

    // Add click handlers
    document.querySelectorAll('.part-option').forEach(option => {
        option.addEventListener('click', function() {
            const partNo = this.dataset.partNo;
            partSelect.value = partNo;

            // Update search box with selected part
            const selectedPart = allParts.find(p => p.part_no === partNo);
            if (selectedPart) {
                document.getElementById('part_search').value = selectedPart.part_no + ' — ' + selectedPart.part_name;
            }

            dropdown.style.display = 'none';
            updateStockInfo();
        });

        // Hover effect
        option.addEventListener('mouseover', function() {
            this.style.background = '#f0f0f0';
        });
        option.addEventListener('mouseout', function() {
            this.style.background = 'white';
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('part_search');
    const dropdown = document.getElementById('part_dropdown');

    // Search input handler
    searchInput.addEventListener('input', function() {
        searchParts(this.value);
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target !== searchInput && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Show dropdown on focus if there's text
    searchInput.addEventListener('focus', function() {
        if (this.value.trim()) {
            searchParts(this.value);
        }
    });

    updateStockInfo();
    updateButtonStyle();
});
</script>

<div class="content">
    <h1>Stock Adjustment</h1>

    <a href="index.php" class="btn btn-secondary">Back to Depletion</a>
    <br><br>

    <form method="post" class="form-grid" style="max-width: 500px;">

        <label>Adjustment Type</label>
        <div class="adjustment-type-selector">
            <label class="depletion-label">
                <input type="radio" name="adjustment_type" id="type_depletion" value="depletion" checked onchange="updateButtonStyle()">
                <span>Depletion (Reduce)</span>
            </label>
            <label class="addition-label">
                <input type="radio" name="adjustment_type" id="type_addition" value="addition" onchange="updateButtonStyle()">
                <span>Addition (Increase)</span>
            </label>
        </div>

        <label>Select Part</label>
        <div style="position: relative;">
            <input type="text" id="part_search" placeholder="Search by Part No or Part Name..."
                   style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
            <div id="part_dropdown" style="position: absolute; width: 100%; background: white; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; max-height: 250px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <!-- Search results will appear here -->
            </div>
        </div>
        <select name="part_no" id="part_select" required style="display: none;">
            <option value="">-- Select Part --</option>
            <?php foreach ($all_parts as $p): ?>
                <option value="<?= htmlspecialchars($p['part_no']) ?>">
                    <?= htmlspecialchars($p['part_no']) ?> — <?= htmlspecialchars($p['part_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div></div>
        <div id="stock_info" class="stock-info">
            Current Stock: <strong id="stock_qty">0</strong> units
        </div>

        <label>Quantity</label>
        <input type="number" name="qty" min="1" required placeholder="Enter quantity">

        <label>Reason</label>
        <input type="text" name="reason" required placeholder="Reason for adjustment">

        <div></div>
        <button type="submit" id="submit_btn" class="btn btn-danger">Deplete Stock</button>
    </form>

    <hr>

    <h2>Adjustment History</h2>
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Adjustment No</th>
            <th>Type</th>
            <th>Part No</th>
            <th>Part Name</th>
            <th>Qty</th>
            <th>Date</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php while ($d = $adjustments_stmt->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($d['issue_no']) ?></td>
            <td>
                <?php if ($d['adjustment_type'] === 'addition'): ?>
                    <span class="type-addition">+ Addition</span>
                <?php else: ?>
                    <span class="type-depletion">- Depletion</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($d['part_no']) ?></td>
            <td><?= htmlspecialchars($d['part_name']) ?></td>
            <td><?= htmlspecialchars($d['qty']) ?></td>
            <td><?= htmlspecialchars($d['issue_date']) ?></td>
            <td><?= htmlspecialchars($d['reason']) ?></td>
            <td><?= htmlspecialchars($d['status']) ?></td>
            <td>
                <?php if ($d['status'] === 'issued'): ?>
                    <a class="btn btn-secondary" href="edit.php?id=<?= $d['id'] ?>">Edit</a>
                    | <a class="btn btn-danger" href="cancel.php?id=<?= $d['id'] ?>" onclick="return confirm('Cancel this adjustment?')">Cancel</a>
                <?php endif; ?>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total records)
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
