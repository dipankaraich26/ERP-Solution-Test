<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

// Get suppliers
try {
    $suppliers = $pdo->query("SELECT id, supplier_name as name FROM suppliers ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $suppliers = [];
}

// Get users for inspector dropdown
try {
    $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// Get parts
try {
    $parts = $pdo->query("SELECT part_no, description FROM part_master WHERE status = 'active' ORDER BY part_no")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $parts = [];
}

// Get checklists for incoming inspection
try {
    $checklists = $pdo->query("SELECT id, checklist_no, checklist_name FROM qc_checklists WHERE status = 'Active' AND checklist_type = 'Incoming Inspection' ORDER BY checklist_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $checklists = [];
}

$inspection_types = ['Normal', 'Tightened', 'Reduced', 'Skip Lot'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grn_no = trim($_POST['grn_no'] ?? '');
    $po_no = trim($_POST['po_no'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $inspection_date = trim($_POST['inspection_date'] ?? date('Y-m-d'));
    $inspector_id = !empty($_POST['inspector_id']) ? (int)$_POST['inspector_id'] : null;
    $inspection_type = trim($_POST['inspection_type'] ?? 'Normal');
    $total_qty = (int)($_POST['total_qty'] ?? 0);
    $sample_qty = (int)($_POST['sample_qty'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    // Get items
    $item_parts = $_POST['item_part_no'] ?? [];
    $item_names = $_POST['item_part_name'] ?? [];
    $item_qty_received = $_POST['item_qty_received'] ?? [];
    $item_qty_inspected = $_POST['item_qty_inspected'] ?? [];
    $item_qty_accepted = $_POST['item_qty_accepted'] ?? [];
    $item_qty_rejected = $_POST['item_qty_rejected'] ?? [];
    $item_defect_type = $_POST['item_defect_type'] ?? [];
    $item_result = $_POST['item_result'] ?? [];

    // Validation
    if (empty($inspection_date)) $errors[] = "Inspection date is required";

    // Check if at least one item is provided
    $valid_items = array_filter($item_parts, function($p) { return !empty(trim($p)); });
    if (empty($valid_items)) $errors[] = "At least one inspection item is required";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate inspection number
            $year = date('Y');
            $month = date('m');
            $prefix = 'INS';
            $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(inspection_no, 13) AS UNSIGNED)) FROM qc_incoming_inspections WHERE inspection_no LIKE '$prefix-$year$month-%'")->fetchColumn();
            $inspection_no = $prefix . '-' . $year . $month . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            // Calculate totals
            $total_accepted = 0;
            $total_rejected = 0;
            $has_reject = false;

            foreach ($item_parts as $idx => $part) {
                if (!empty(trim($part))) {
                    $total_accepted += (int)($item_qty_accepted[$idx] ?? 0);
                    $total_rejected += (int)($item_qty_rejected[$idx] ?? 0);
                    if (($item_result[$idx] ?? '') === 'Reject') $has_reject = true;
                }
            }

            // Determine overall result
            $overall_result = 'Pending';
            if ($total_rejected > 0 || $has_reject) {
                $overall_result = 'Reject';
            } elseif ($total_accepted > 0) {
                $overall_result = 'Accept';
            }

            // Insert inspection
            $stmt = $pdo->prepare("
                INSERT INTO qc_incoming_inspections (
                    inspection_no, grn_no, po_no, supplier_id, inspection_date,
                    inspector_id, inspection_type, total_qty, sample_qty,
                    accepted_qty, rejected_qty, inspection_result, remarks, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)
            ");
            $stmt->execute([
                $inspection_no,
                $grn_no ?: null,
                $po_no ?: null,
                $supplier_id,
                $inspection_date,
                $inspector_id,
                $inspection_type,
                $total_qty,
                $sample_qty,
                $total_accepted,
                $total_rejected,
                $overall_result,
                $remarks ?: null,
                $_SESSION['user_id'] ?? null
            ]);
            $inspection_id = $pdo->lastInsertId();

            // Insert items
            $item_stmt = $pdo->prepare("
                INSERT INTO qc_incoming_inspection_items (
                    inspection_id, part_no, part_name, qty_received, qty_inspected,
                    qty_accepted, qty_rejected, defect_type, result
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($item_parts as $idx => $part) {
                if (!empty(trim($part))) {
                    $item_stmt->execute([
                        $inspection_id,
                        trim($part),
                        $item_names[$idx] ?? null,
                        (int)($item_qty_received[$idx] ?? 0),
                        (int)($item_qty_inspected[$idx] ?? 0),
                        (int)($item_qty_accepted[$idx] ?? 0),
                        (int)($item_qty_rejected[$idx] ?? 0),
                        $item_defect_type[$idx] ?? null,
                        $item_result[$idx] ?? 'Pending'
                    ]);
                }
            }

            $pdo->commit();
            setModal("Success", "Incoming Inspection '$inspection_no' recorded successfully.");
            header("Location: inspection_view.php?id=$inspection_id");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>New Incoming Inspection - QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child { border-bottom: none; }
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #667eea;
            font-size: 1.1em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #495057;
        }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-group.full-width { grid-column: 1 / -1; }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            background: #f8f9fa;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            font-size: 0.9em;
        }
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .items-table input, .items-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .items-table input[type="number"] { width: 70px; }

        .remove-row {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .add-row-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 15px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .result-accept { background: #d4edda !important; }
        .result-reject { background: #f8d7da !important; }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .items-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

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
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "Light Mode" : "Dark Mode";
    });
}
</script>

<div class="content">
    <div class="page-header">
        <div>
            <h1>New Incoming Inspection</h1>
            <p style="color: #666; margin: 5px 0 0;">Record material/parts receiving inspection</p>
        </div>
        <a href="inspections.php" class="btn btn-secondary">Back to Inspections</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 10px 0 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="post">
            <!-- Inspection Details -->
            <div class="form-section">
                <h3>Inspection Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Inspection Date <span class="required">*</span></label>
                        <input type="date" name="inspection_date" value="<?= htmlspecialchars($_POST['inspection_date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id">
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($_POST['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>PO Number</label>
                        <input type="text" name="po_no" value="<?= htmlspecialchars($_POST['po_no'] ?? '') ?>" placeholder="Purchase Order Number">
                    </div>

                    <div class="form-group">
                        <label>GRN Number</label>
                        <input type="text" name="grn_no" value="<?= htmlspecialchars($_POST['grn_no'] ?? '') ?>" placeholder="Goods Receipt Note Number">
                    </div>

                    <div class="form-group">
                        <label>Inspector</label>
                        <select name="inspector_id">
                            <option value="">-- Select Inspector --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($_POST['inspector_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Inspection Type</label>
                        <select name="inspection_type">
                            <?php foreach ($inspection_types as $t): ?>
                                <option value="<?= $t ?>" <?= ($_POST['inspection_type'] ?? 'Normal') === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Total Quantity</label>
                        <input type="number" name="total_qty" value="<?= (int)($_POST['total_qty'] ?? 0) ?>" min="0">
                    </div>

                    <div class="form-group">
                        <label>Sample Quantity</label>
                        <input type="number" name="sample_qty" value="<?= (int)($_POST['sample_qty'] ?? 0) ?>" min="0">
                    </div>

                    <div class="form-group full-width">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="2" placeholder="General remarks..."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Inspection Items -->
            <div class="form-section">
                <h3>Inspection Items</h3>
                <p style="color: #666; margin-bottom: 15px;">Enter the parts/materials inspected and results.</p>

                <div style="overflow-x: auto;">
                    <table class="items-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Part No <span class="required">*</span></th>
                                <th style="width: 18%;">Description</th>
                                <th style="width: 8%;">Rcvd</th>
                                <th style="width: 8%;">Inspected</th>
                                <th style="width: 8%;">Accepted</th>
                                <th style="width: 8%;">Rejected</th>
                                <th style="width: 12%;">Defect Type</th>
                                <th style="width: 10%;">Result</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td>
                                    <input type="text" name="item_part_no[]" list="partsList" placeholder="Part #">
                                </td>
                                <td><input type="text" name="item_part_name[]" placeholder="Description"></td>
                                <td><input type="number" name="item_qty_received[]" value="0" min="0"></td>
                                <td><input type="number" name="item_qty_inspected[]" value="0" min="0"></td>
                                <td><input type="number" name="item_qty_accepted[]" value="0" min="0" onchange="updateResult(this)"></td>
                                <td><input type="number" name="item_qty_rejected[]" value="0" min="0" onchange="updateResult(this)"></td>
                                <td>
                                    <select name="item_defect_type[]">
                                        <option value="">--</option>
                                        <option value="Dimensional">Dimensional</option>
                                        <option value="Visual">Visual</option>
                                        <option value="Functional">Functional</option>
                                        <option value="Material">Material</option>
                                        <option value="Packaging">Packaging</option>
                                        <option value="Documentation">Documentation</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="item_result[]" class="result-select">
                                        <option value="Pending">Pending</option>
                                        <option value="Accept">Accept</option>
                                        <option value="Reject">Reject</option>
                                        <option value="Conditional">Conditional</option>
                                    </select>
                                </td>
                                <td><button type="button" class="remove-row" onclick="removeRow(this)">X</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <datalist id="partsList">
                    <?php foreach ($parts as $p): ?>
                        <option value="<?= htmlspecialchars($p['part_no']) ?>"><?= htmlspecialchars($p['description']) ?></option>
                    <?php endforeach; ?>
                </datalist>

                <button type="button" class="add-row-btn" onclick="addRow()">+ Add Item</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Inspection</button>
                <a href="inspections.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function addRow() {
    const tbody = document.getElementById('itemsBody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" name="item_part_no[]" list="partsList" placeholder="Part #"></td>
        <td><input type="text" name="item_part_name[]" placeholder="Description"></td>
        <td><input type="number" name="item_qty_received[]" value="0" min="0"></td>
        <td><input type="number" name="item_qty_inspected[]" value="0" min="0"></td>
        <td><input type="number" name="item_qty_accepted[]" value="0" min="0" onchange="updateResult(this)"></td>
        <td><input type="number" name="item_qty_rejected[]" value="0" min="0" onchange="updateResult(this)"></td>
        <td>
            <select name="item_defect_type[]">
                <option value="">--</option>
                <option value="Dimensional">Dimensional</option>
                <option value="Visual">Visual</option>
                <option value="Functional">Functional</option>
                <option value="Material">Material</option>
                <option value="Packaging">Packaging</option>
                <option value="Documentation">Documentation</option>
                <option value="Other">Other</option>
            </select>
        </td>
        <td>
            <select name="item_result[]" class="result-select">
                <option value="Pending">Pending</option>
                <option value="Accept">Accept</option>
                <option value="Reject">Reject</option>
                <option value="Conditional">Conditional</option>
            </select>
        </td>
        <td><button type="button" class="remove-row" onclick="removeRow(this)">X</button></td>
    `;
    tbody.appendChild(newRow);
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
    }
}

function updateResult(input) {
    const row = input.closest('tr');
    const accepted = parseInt(row.querySelector('input[name="item_qty_accepted[]"]').value) || 0;
    const rejected = parseInt(row.querySelector('input[name="item_qty_rejected[]"]').value) || 0;
    const resultSelect = row.querySelector('select[name="item_result[]"]');

    if (rejected > 0) {
        resultSelect.value = 'Reject';
        resultSelect.className = 'result-select result-reject';
    } else if (accepted > 0) {
        resultSelect.value = 'Accept';
        resultSelect.className = 'result-select result-accept';
    }
}
</script>

</body>
</html>
