<?php
include "../db.php";
include "../includes/dialog.php";

if (!isset($_GET['id'])) {
    setModal("Error", "BOM ID is required.");
    header("Location: index.php");
    exit;
}

$sourceId = (int)$_GET['id'];

// Fetch the source BOM
$sourceBom = $pdo->prepare("SELECT * FROM bom_master WHERE id = ?");
$sourceBom->execute([$sourceId]);
$sourceBom = $sourceBom->fetch(PDO::FETCH_ASSOC);

if (!$sourceBom) {
    setModal("Error", "Source BOM not found.");
    header("Location: index.php");
    exit;
}

// Fetch the source BOM items
$sourceItems = $pdo->prepare("SELECT * FROM bom_items WHERE bom_id = ?");
$sourceItems->execute([$sourceId]);
$sourceItems = $sourceItems->fetchAll(PDO::FETCH_ASSOC);

// Fetch all parts for parent selection
$parts = $pdo->query("SELECT part_no, description FROM part_master ORDER BY part_no")->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newParentPartNo = trim($_POST['parent_part_no'] ?? '');
    $newDescription = trim($_POST['description'] ?? '');

    if (empty($newParentPartNo)) {
        $error = "Parent Part No is required.";
    } else {
        try {
            $pdo->beginTransaction();

            // Generate new BOM number
            // Format: BOM-YYYYMM-XXXX
            $yearMonth = date('Ym');
            $lastBom = $pdo->query("SELECT bom_no FROM bom_master ORDER BY id DESC LIMIT 1")->fetchColumn();

            if ($lastBom && preg_match('/BOM-(\d{6})-(\d+)/', $lastBom, $matches)) {
                $lastYearMonth = $matches[1];
                $lastSeq = (int)$matches[2];

                if ($lastYearMonth === $yearMonth) {
                    $newSeq = $lastSeq + 1;
                } else {
                    $newSeq = 1;
                }
            } else {
                $newSeq = 1;
            }

            $newBomNo = 'BOM-' . $yearMonth . '-' . str_pad($newSeq, 4, '0', STR_PAD_LEFT);

            // Create new BOM record (as Draft/Inactive)
            $insertBom = $pdo->prepare("
                INSERT INTO bom_master (bom_no, parent_part_no, description, status)
                VALUES (?, ?, ?, 'inactive')
            ");
            $insertBom->execute([
                $newBomNo,
                $newParentPartNo,
                $newDescription
            ]);

            $newBomId = $pdo->lastInsertId();

            // Copy all BOM items
            $insertItem = $pdo->prepare("
                INSERT INTO bom_items (bom_id, component_part_no, qty, rate)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($sourceItems as $item) {
                $insertItem->execute([
                    $newBomId,
                    $item['component_part_no'],
                    $item['qty'],
                    $item['rate']
                ]);
            }

            $pdo->commit();

            setModal("Success", "BOM duplicated successfully! New BOM: $newBomNo");
            header("Location: edit.php?id=" . $newBomId);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to duplicate BOM: " . $e->getMessage();
        }
    }
}

// Default values for form
$defaultParentPartNo = $sourceBom['parent_part_no'];
$defaultDescription = $sourceBom['description'] . ' (Copy of ' . $sourceBom['bom_no'] . ')';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicate BOM - <?= htmlspecialchars($sourceBom['bom_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .duplicate-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .source-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .source-info h4 {
            margin-top: 0;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .source-info table {
            width: 100%;
        }
        .source-info td {
            padding: 5px 0;
        }
        .source-info td:first-child {
            font-weight: bold;
            width: 150px;
            color: #6c757d;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .items-preview {
            margin-top: 20px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        .items-preview h4 {
            margin-top: 0;
            color: #495057;
        }
        .items-preview table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-preview th,
        .items-preview td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .items-preview th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .select2-container {
            width: 100% !important;
        }
        .select2-container .select2-selection--single {
            height: 42px;
            padding: 5px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
    </style>
</head>
<body>
    <?php include "../includes/navbar.php"; ?>

    <div class="duplicate-container">
        <h2>Duplicate BOM</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="source-info">
            <h4>Source BOM Information</h4>
            <table>
                <tr>
                    <td>BOM No:</td>
                    <td><?= htmlspecialchars($sourceBom['bom_no']) ?></td>
                </tr>
                <tr>
                    <td>Parent Part No:</td>
                    <td><?= htmlspecialchars($sourceBom['parent_part_no']) ?></td>
                </tr>
                <tr>
                    <td>Description:</td>
                    <td><?= htmlspecialchars($sourceBom['description']) ?></td>
                </tr>
                <tr>
                    <td>Status:</td>
                    <td><?= htmlspecialchars(ucfirst($sourceBom['status'])) ?></td>
                </tr>
                <tr>
                    <td>Items Count:</td>
                    <td><?= count($sourceItems) ?> components</td>
                </tr>
            </table>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="parent_part_no">New Parent Part No *</label>
                <select name="parent_part_no" id="parent_part_no" required>
                    <option value="">-- Select Parent Part --</option>
                    <?php foreach ($parts as $part): ?>
                        <option value="<?= htmlspecialchars($part['part_no']) ?>"
                                <?= ($part['part_no'] === $defaultParentPartNo) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($part['part_no']) ?> - <?= htmlspecialchars($part['description']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #6c757d;">Select a different parent part or keep the same one</small>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea name="description" id="description" required><?= htmlspecialchars($_POST['description'] ?? $defaultDescription) ?></textarea>
            </div>

            <div class="items-preview">
                <h4>Components to Copy (<?= count($sourceItems) ?> items)</h4>
                <?php if (count($sourceItems) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Component Part No</th>
                                <th>Quantity</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sourceItems as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($item['component_part_no']) ?></td>
                                    <td><?= htmlspecialchars($item['qty']) ?></td>
                                    <td><?= number_format($item['rate'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No components in source BOM.</p>
                <?php endif; ?>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Create Duplicate BOM</button>
                <a href="view.php?id=<?= $sourceId ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#parent_part_no').select2({
                placeholder: '-- Select Parent Part --',
                allowClear: true
            });
        });
    </script>
</body>
</html>
