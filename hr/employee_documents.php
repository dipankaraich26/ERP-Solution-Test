<?php
/**
 * Employee Documents Management
 * Upload and manage employee identity, education, and employment documents
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Check if tables exist
$tableExists = $pdo->query("SHOW TABLES LIKE 'employee_documents'")->fetch();
if (!$tableExists) {
    setModal("Setup Required", "Please run the HR Appraisal setup first.");
    header("Location: /admin/setup_hr_appraisal.php");
    exit;
}

$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$message = '';
$error = '';

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $emp_id = intval($_POST['employee_id']);
    $doc_type = trim($_POST['document_type']);
    $doc_name = trim($_POST['document_name']);
    $doc_number = trim($_POST['document_number']);
    $issue_date = $_POST['issue_date'] ?: null;
    $expiry_date = $_POST['expiry_date'] ?: null;
    $notes = trim($_POST['notes']);

    $filePath = null;

    // Handle file upload
    if (!empty($_FILES['document_file']['name'])) {
        $uploadDir = "../uploads/employee_docs/" . $emp_id . "/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['document_file']['name']);
        $targetPath = $uploadDir . $fileName;

        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes)) {
            $error = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
        } elseif ($_FILES['document_file']['size'] > 5 * 1024 * 1024) {
            $error = "File size exceeds 5MB limit";
        } elseif (move_uploaded_file($_FILES['document_file']['tmp_name'], $targetPath)) {
            $filePath = "uploads/employee_docs/" . $emp_id . "/" . $fileName;
        } else {
            $error = "Failed to upload file";
        }
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO employee_documents
                (employee_id, document_type, document_name, document_number, file_path, issue_date, expiry_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$emp_id, $doc_type, $doc_name, $doc_number, $filePath, $issue_date, $expiry_date, $notes]);
            $message = "Document uploaded successfully!";
            $employee_id = $emp_id; // Stay on same employee
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $docId = intval($_GET['delete']);

    // Get file path before deleting
    $doc = $pdo->prepare("SELECT file_path, employee_id FROM employee_documents WHERE id = ?");
    $doc->execute([$docId]);
    $docData = $doc->fetch(PDO::FETCH_ASSOC);

    if ($docData) {
        // Delete file if exists
        if ($docData['file_path'] && file_exists("../" . $docData['file_path'])) {
            unlink("../" . $docData['file_path']);
        }

        $pdo->prepare("DELETE FROM employee_documents WHERE id = ?")->execute([$docId]);
        $message = "Document deleted successfully";
        $employee_id = $docData['employee_id'];
    }
}

// Handle document verification
if (isset($_GET['verify']) && is_numeric($_GET['verify'])) {
    $docId = intval($_GET['verify']);
    $pdo->prepare("
        UPDATE employee_documents
        SET verified = 1, verified_by = ?, verified_at = NOW()
        WHERE id = ?
    ")->execute([$_SESSION['user_id'] ?? 1, $docId]);
    $message = "Document verified successfully";

    // Get employee_id to stay on page
    $doc = $pdo->prepare("SELECT employee_id FROM employee_documents WHERE id = ?");
    $doc->execute([$docId]);
    $employee_id = $doc->fetchColumn();
}

// Fetch employees for dropdown
$employees = $pdo->query("
    SELECT id, emp_id, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch document types
$docTypes = $pdo->query("
    SELECT * FROM document_types WHERE is_active = 1 ORDER BY sort_order, type_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch documents for selected employee
$documents = [];
$selectedEmployee = null;
if ($employee_id) {
    $empStmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $empStmt->execute([$employee_id]);
    $selectedEmployee = $empStmt->fetch(PDO::FETCH_ASSOC);

    $docStmt = $pdo->prepare("
        SELECT ed.*, dt.category, dt.requires_expiry,
               v.first_name as verifier_first, v.last_name as verifier_last
        FROM employee_documents ed
        LEFT JOIN document_types dt ON ed.document_type = dt.type_name
        LEFT JOIN employees v ON ed.verified_by = v.id
        WHERE ed.employee_id = ?
        ORDER BY dt.sort_order, ed.created_at DESC
    ");
    $docStmt->execute([$employee_id]);
    $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Group document types by category
$docTypesByCategory = [];
foreach ($docTypes as $dt) {
    $cat = $dt['category'] ?: 'Other';
    if (!isset($docTypesByCategory[$cat])) {
        $docTypesByCategory[$cat] = [];
    }
    $docTypesByCategory[$cat][] = $dt;
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Documents</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .doc-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
        }
        .employee-selector {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .employee-selector h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .employee-search {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .employee-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .employee-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .employee-item:hover {
            background: #f0f7ff;
        }
        .employee-item.selected {
            background: #e3f2fd;
            border-left: 3px solid #3498db;
        }
        .employee-item .emp-name {
            font-weight: 500;
        }
        .employee-item .emp-dept {
            font-size: 0.85em;
            color: #666;
        }
        .doc-count {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        .documents-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        .doc-category {
            margin-bottom: 25px;
        }
        .doc-category h4 {
            background: #f8f9fa;
            padding: 10px 15px;
            margin: 0 0 15px 0;
            border-left: 4px solid #3498db;
        }
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }
        .doc-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            position: relative;
        }
        .doc-card.verified {
            border-color: #28a745;
            background: #f8fff9;
        }
        .doc-card.expiring {
            border-color: #ffc107;
            background: #fffdf5;
        }
        .doc-card.expired {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .doc-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .doc-type-badge {
            background: #6c757d;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
        }
        .doc-type-badge.Identity { background: #007bff; }
        .doc-type-badge.Education { background: #28a745; }
        .doc-type-badge.Employment { background: #17a2b8; }
        .doc-type-badge.Financial { background: #ffc107; color: #333; }
        .doc-name {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        .doc-number {
            color: #666;
            font-family: monospace;
        }
        .doc-dates {
            font-size: 0.85em;
            color: #666;
            margin-top: 10px;
        }
        .doc-actions {
            margin-top: 10px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .doc-actions a, .doc-actions button {
            padding: 4px 8px;
            font-size: 0.85em;
        }
        .verified-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
        }
        .upload-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .no-employee {
            text-align: center;
            padding: 60px;
            color: #666;
        }
        .no-employee h3 {
            margin-bottom: 10px;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        @media (max-width: 900px) {
            .doc-container {
                grid-template-columns: 1fr;
            }
            .employee-selector {
                position: static;
            }
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Employee Documents</h1>

    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="doc-container">
        <!-- Employee Selector -->
        <div class="employee-selector">
            <h3>Select Employee</h3>
            <input type="text" class="employee-search" placeholder="Search employees..." id="empSearch" onkeyup="filterEmployees()">
            <div class="employee-list" id="employeeList">
                <?php foreach ($employees as $emp):
                    // Get document count
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_documents WHERE employee_id = ?");
                    $countStmt->execute([$emp['id']]);
                    $docCount = $countStmt->fetchColumn();
                ?>
                <div class="employee-item <?= $employee_id == $emp['id'] ? 'selected' : '' ?>"
                     onclick="window.location='?employee_id=<?= $emp['id'] ?>'"
                     data-name="<?= strtolower($emp['first_name'] . ' ' . $emp['last_name']) ?>">
                    <div>
                        <div class="emp-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                        <div class="emp-dept"><?= htmlspecialchars($emp['emp_id']) ?> | <?= htmlspecialchars($emp['department'] ?: 'No Dept') ?></div>
                    </div>
                    <?php if ($docCount > 0): ?>
                    <span class="doc-count"><?= $docCount ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="documents-section">
            <?php if ($selectedEmployee): ?>
            <div class="doc-header">
                <div>
                    <h2 style="margin: 0;">
                        <?= htmlspecialchars($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?>
                    </h2>
                    <small style="color: #666;">
                        <?= htmlspecialchars($selectedEmployee['emp_id']) ?> |
                        <?= htmlspecialchars($selectedEmployee['department'] ?: 'No Department') ?>
                    </small>
                </div>
                <button class="btn btn-primary" onclick="toggleUploadForm()">+ Add Document</button>
            </div>

            <!-- Upload Form (Hidden by default) -->
            <div class="upload-form" id="uploadForm" style="display: none;">
                <h4 style="margin-top: 0;">Upload New Document</h4>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="employee_id" value="<?= $employee_id ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Document Type *</label>
                            <select name="document_type" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach ($docTypesByCategory as $cat => $types): ?>
                                <optgroup label="<?= htmlspecialchars($cat) ?>">
                                    <?php foreach ($types as $dt): ?>
                                    <option value="<?= htmlspecialchars($dt['type_name']) ?>"
                                            data-requires-expiry="<?= $dt['requires_expiry'] ?>">
                                        <?= htmlspecialchars($dt['type_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Document Name/Title *</label>
                            <input type="text" name="document_name" required placeholder="e.g., PAN Card">
                        </div>
                        <div class="form-group">
                            <label>Document Number</label>
                            <input type="text" name="document_number" placeholder="e.g., ABCDE1234F">
                        </div>
                        <div class="form-group">
                            <label>Upload File (Max 5MB)</label>
                            <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                        <div class="form-group">
                            <label>Issue Date</label>
                            <input type="date" name="issue_date">
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date">
                        </div>
                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <button type="submit" name="upload_document" class="btn btn-success">Upload Document</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleUploadForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Documents List -->
            <?php if (empty($documents)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h3>No Documents</h3>
                <p>Click "Add Document" to upload employee documents.</p>
            </div>
            <?php else:
                // Group documents by category
                $docsByCategory = [];
                foreach ($documents as $doc) {
                    $cat = $doc['category'] ?: 'Other';
                    if (!isset($docsByCategory[$cat])) {
                        $docsByCategory[$cat] = [];
                    }
                    $docsByCategory[$cat][] = $doc;
                }
            ?>
                <?php foreach ($docsByCategory as $category => $catDocs): ?>
                <div class="doc-category">
                    <h4><?= htmlspecialchars($category) ?> Documents (<?= count($catDocs) ?>)</h4>
                    <div class="doc-grid">
                        <?php foreach ($catDocs as $doc):
                            $isExpired = $doc['expiry_date'] && strtotime($doc['expiry_date']) < time();
                            $isExpiring = $doc['expiry_date'] && !$isExpired &&
                                          strtotime($doc['expiry_date']) < strtotime('+30 days');
                            $cardClass = $doc['verified'] ? 'verified' : '';
                            if ($isExpired) $cardClass = 'expired';
                            elseif ($isExpiring) $cardClass = 'expiring';
                        ?>
                        <div class="doc-card <?= $cardClass ?>">
                            <?php if ($doc['verified']): ?>
                            <span class="verified-badge">Verified</span>
                            <?php endif; ?>

                            <div class="doc-card-header">
                                <span class="doc-type-badge <?= htmlspecialchars($category) ?>">
                                    <?= htmlspecialchars($doc['document_type']) ?>
                                </span>
                            </div>

                            <div class="doc-name"><?= htmlspecialchars($doc['document_name']) ?></div>

                            <?php if ($doc['document_number']): ?>
                            <div class="doc-number"><?= htmlspecialchars($doc['document_number']) ?></div>
                            <?php endif; ?>

                            <div class="doc-dates">
                                <?php if ($doc['issue_date']): ?>
                                <div>Issued: <?= date('d M Y', strtotime($doc['issue_date'])) ?></div>
                                <?php endif; ?>
                                <?php if ($doc['expiry_date']): ?>
                                <div style="<?= $isExpired ? 'color: #dc3545; font-weight: bold;' : ($isExpiring ? 'color: #ffc107;' : '') ?>">
                                    <?= $isExpired ? 'Expired' : 'Expires' ?>: <?= date('d M Y', strtotime($doc['expiry_date'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($doc['notes']): ?>
                            <div style="font-size: 0.85em; color: #666; margin-top: 5px; font-style: italic;">
                                <?= htmlspecialchars(substr($doc['notes'], 0, 50)) ?><?= strlen($doc['notes']) > 50 ? '...' : '' ?>
                            </div>
                            <?php endif; ?>

                            <div class="doc-actions">
                                <?php if ($doc['file_path']): ?>
                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-primary">View</a>
                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" download class="btn btn-secondary">Download</a>
                                <?php endif; ?>
                                <?php if (!$doc['verified']): ?>
                                <a href="?employee_id=<?= $employee_id ?>&verify=<?= $doc['id'] ?>"
                                   class="btn btn-success"
                                   onclick="return confirm('Verify this document?')">Verify</a>
                                <?php endif; ?>
                                <a href="?employee_id=<?= $employee_id ?>&delete=<?= $doc['id'] ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('Delete this document?')">Delete</a>
                            </div>

                            <?php if ($doc['verified'] && $doc['verifier_first']): ?>
                            <div style="font-size: 0.75em; color: #28a745; margin-top: 8px;">
                                Verified by <?= htmlspecialchars($doc['verifier_first'] . ' ' . $doc['verifier_last']) ?>
                                on <?= date('d M Y', strtotime($doc['verified_at'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php else: ?>
            <div class="no-employee">
                <h3>Select an Employee</h3>
                <p>Choose an employee from the list to view and manage their documents.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterEmployees() {
    const search = document.getElementById('empSearch').value.toLowerCase();
    const items = document.querySelectorAll('.employee-item');

    items.forEach(item => {
        const name = item.getAttribute('data-name');
        item.style.display = name.includes(search) ? 'flex' : 'none';
    });
}

function toggleUploadForm() {
    const form = document.getElementById('uploadForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>
