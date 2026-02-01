<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_no = trim($_POST['doc_no'] ?? '');
    $doc_type = trim($_POST['doc_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $effective_date = trim($_POST['effective_date'] ?? '');
    $review_date = trim($_POST['review_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Draft');
    $author = trim($_POST['author'] ?? '');
    $reviewer = trim($_POST['reviewer'] ?? '');
    $approver = trim($_POST['approver'] ?? '');

    if (empty($doc_no) || empty($doc_type) || empty($title)) {
        $error = "Document number, type, and title are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qms_documents
                (doc_no, doc_type, title, department, category, version, effective_date, review_date, status, author, reviewer, approver)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $doc_no,
                $doc_type,
                $title,
                $department,
                $category,
                $version,
                $effective_date ?: null,
                $review_date ?: null,
                $status,
                $author,
                $reviewer,
                $approver
            ]);

            header("Location: documents.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding document: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Document - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        .required::after {
            content: ' *';
            color: red;
        }
        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Add Document</h1>
        <a href="documents.php" class="btn btn-secondary">← Back to Documents</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Document Numbering Convention:</strong><br>
        SOP-[Dept]-[Number] for SOPs | WI-[Dept]-[Number] for Work Instructions |
        FRM-[Dept]-[Number] for Forms | POL-[Number] for Policies
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Document Number</label>
                    <input type="text" name="doc_no" value="<?= htmlspecialchars($_POST['doc_no'] ?? '') ?>" required
                           placeholder="e.g., SOP-QA-001">
                </div>
                <div class="form-group">
                    <label class="required">Document Type</label>
                    <select name="doc_type" required>
                        <option value="">Select Type</option>
                        <option value="SOP">SOP (Standard Operating Procedure)</option>
                        <option value="Work Instruction">Work Instruction</option>
                        <option value="Form">Form / Template</option>
                        <option value="Policy">Policy</option>
                        <option value="Manual">Manual</option>
                        <option value="Specification">Specification</option>
                        <option value="Protocol">Protocol</option>
                        <option value="Report">Report</option>
                        <option value="Record">Record</option>
                        <option value="External">External Document</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="required">Document Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required
                       placeholder="Enter document title">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">Select Department</option>
                        <option value="Quality Assurance">Quality Assurance</option>
                        <option value="Quality Control">Quality Control</option>
                        <option value="Production">Production</option>
                        <option value="R&D">R&D</option>
                        <option value="Regulatory Affairs">Regulatory Affairs</option>
                        <option value="Engineering">Engineering</option>
                        <option value="Warehouse">Warehouse</option>
                        <option value="HR">Human Resources</option>
                        <option value="Purchase">Purchase</option>
                        <option value="Sales">Sales</option>
                        <option value="IT">IT</option>
                        <option value="Management">Management</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"
                           placeholder="e.g., Equipment, Process, Training">
                </div>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label>Version</label>
                    <input type="text" name="version" value="<?= htmlspecialchars($_POST['version'] ?? '1.0') ?>"
                           placeholder="e.g., 1.0">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Draft">Draft</option>
                        <option value="Under Review">Under Review</option>
                        <option value="Approved">Approved</option>
                        <option value="Effective">Effective</option>
                        <option value="Obsolete">Obsolete</option>
                        <option value="Superseded">Superseded</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Effective Date</label>
                    <input type="date" name="effective_date" value="<?= htmlspecialchars($_POST['effective_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Review Date</label>
                    <input type="date" name="review_date" value="<?= htmlspecialchars($_POST['review_date'] ?? '') ?>">
                </div>
                <div class="form-group"></div>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" name="author" value="<?= htmlspecialchars($_POST['author'] ?? '') ?>"
                           placeholder="Document author">
                </div>
                <div class="form-group">
                    <label>Reviewer</label>
                    <input type="text" name="reviewer" value="<?= htmlspecialchars($_POST['reviewer'] ?? '') ?>"
                           placeholder="Document reviewer">
                </div>
                <div class="form-group">
                    <label>Approver</label>
                    <input type="text" name="approver" value="<?= htmlspecialchars($_POST['approver'] ?? '') ?>"
                           placeholder="Document approver">
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Add Document</button>
                <a href="documents.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
