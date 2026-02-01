<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: employees.php");
    exit;
}

// Fetch employee
$stmt = $pdo->prepare("
    SELECT e.*, CONCAT(m.first_name, ' ', m.last_name) as manager_name, m.emp_id as manager_emp_id
    FROM employees e
    LEFT JOIN employees m ON e.reporting_to = m.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    header("Location: employees.php");
    exit;
}

// Calculate gross salary
$grossSalary = $emp['basic_salary'] + $emp['hra'] + $emp['conveyance'] +
               $emp['medical_allowance'] + $emp['special_allowance'] + $emp['other_allowance'] +
               ($emp['performance_allowance'] ?? 0) + ($emp['food_allowance'] ?? 0);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee - <?= htmlspecialchars($emp['emp_id']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .emp-view { max-width: 1000px; }

        .emp-header {
            display: flex;
            gap: 25px;
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .emp-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.2);
        }
        .emp-photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            border: 4px solid rgba(255,255,255,0.3);
        }
        .emp-header-info h1 { margin: 0 0 5px 0; }
        .emp-header-info p { margin: 5px 0; opacity: 0.9; }
        .emp-header-info .emp-id { font-size: 1.2em; opacity: 0.8; }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        .status-Active { background: #27ae60; }
        .status-Inactive { background: #e74c3c; }
        .status-On-Leave { background: #f39c12; }

        .info-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .info-section h3 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .info-item label {
            display: block;
            color: #7f8c8d;
            font-size: 0.85em;
            margin-bottom: 5px;
        }
        .info-item .value {
            font-weight: 500;
            color: #2c3e50;
        }

        .salary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .salary-table td {
            padding: 10px 20px;
            border-bottom: 1px solid #eee;
        }
        .salary-table tr:last-child td { border-bottom: none; }
        .salary-table .label { color: #7f8c8d; }
        .salary-table .amount { text-align: right; font-weight: 500; }
        .salary-table .total { background: #f8f9fa; font-weight: bold; }
        .salary-table .total .amount { color: #27ae60; font-size: 1.2em; }

        .action-buttons { margin-bottom: 20px; }
        .action-buttons .btn { margin-right: 10px; }

        @media print {
            .sidebar, .action-buttons { display: none !important; }
            .content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="emp-view">

        <div class="action-buttons">
            <a href="employees.php" class="btn btn-secondary">Back to List</a>
            <a href="employee_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <button onclick="window.print()" class="btn btn-secondary">Print</button>
        </div>

        <!-- Header -->
        <div class="emp-header">
            <?php if ($emp['photo_path']): ?>
                <img src="../<?= htmlspecialchars($emp['photo_path']) ?>" class="emp-photo" alt="">
            <?php else: ?>
                <div class="emp-photo-placeholder">
                    <?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="emp-header-info">
                <p class="emp-id"><?= htmlspecialchars($emp['emp_id']) ?></p>
                <h1><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h1>
                <p><?= htmlspecialchars($emp['designation'] ?? 'No Designation') ?> | <?= htmlspecialchars($emp['department'] ?? 'No Department') ?></p>
                <p><?= htmlspecialchars($emp['employment_type']) ?></p>
                <span class="status-badge status-<?= str_replace(' ', '-', $emp['status']) ?>">
                    <?= $emp['status'] ?>
                </span>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="info-section">
            <h3>Personal Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Date of Birth</label>
                    <div class="value"><?= $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '-' ?></div>
                </div>
                <div class="info-item">
                    <label>Gender</label>
                    <div class="value"><?= htmlspecialchars($emp['gender'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Marital Status</label>
                    <div class="value"><?= htmlspecialchars($emp['marital_status'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Blood Group</label>
                    <div class="value"><?= htmlspecialchars($emp['blood_group'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Nationality</label>
                    <div class="value"><?= htmlspecialchars($emp['nationality'] ?? 'Indian') ?></div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="info-section">
            <h3>Contact Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Phone</label>
                    <div class="value"><?= htmlspecialchars($emp['phone']) ?></div>
                </div>
                <div class="info-item">
                    <label>Alternate Phone</label>
                    <div class="value"><?= htmlspecialchars($emp['alt_phone'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Official Email</label>
                    <div class="value"><?= htmlspecialchars($emp['email'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Personal Email</label>
                    <div class="value"><?= htmlspecialchars($emp['personal_email'] ?? '-') ?></div>
                </div>
            </div>
        </div>

        <!-- Address -->
        <div class="info-section">
            <h3>Address</h3>
            <div class="info-grid">
                <div class="info-item" style="grid-column: 1 / -1;">
                    <label>Full Address</label>
                    <div class="value">
                        <?php
                        $address = array_filter([
                            $emp['address_line1'],
                            $emp['address_line2'],
                            $emp['city'],
                            $emp['state'],
                            $emp['pincode'],
                            $emp['country']
                        ]);
                        echo htmlspecialchars(implode(', ', $address) ?: '-');
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div class="info-section">
            <h3>Emergency Contact</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Contact Name</label>
                    <div class="value"><?= htmlspecialchars($emp['emergency_contact_name'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Relation</label>
                    <div class="value"><?= htmlspecialchars($emp['emergency_contact_relation'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Phone</label>
                    <div class="value"><?= htmlspecialchars($emp['emergency_contact_phone'] ?? '-') ?></div>
                </div>
            </div>
        </div>

        <!-- Employment Details -->
        <div class="info-section">
            <h3>Employment Details</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Department</label>
                    <div class="value"><?= htmlspecialchars($emp['department'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Designation</label>
                    <div class="value"><?= htmlspecialchars($emp['designation'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Employment Type</label>
                    <div class="value"><?= htmlspecialchars($emp['employment_type']) ?></div>
                </div>
                <div class="info-item">
                    <label>Date of Joining</label>
                    <div class="value"><?= date('d-m-Y', strtotime($emp['date_of_joining'])) ?></div>
                </div>
                <div class="info-item">
                    <label>Reporting To</label>
                    <div class="value">
                        <?= $emp['manager_name'] ? htmlspecialchars($emp['manager_name'] . ' (' . $emp['manager_emp_id'] . ')') : '-' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Work Location</label>
                    <div class="value"><?= htmlspecialchars($emp['work_location'] ?? '-') ?></div>
                </div>
            </div>
        </div>

        <!-- ID Documents -->
        <div class="info-section">
            <h3>ID Documents</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Aadhar Number</label>
                    <div class="value"><?= htmlspecialchars($emp['aadhar_no'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>PAN Number</label>
                    <div class="value"><?= htmlspecialchars($emp['pan_no'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>UAN Number</label>
                    <div class="value"><?= htmlspecialchars($emp['uan_no'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>PF Number</label>
                    <div class="value"><?= htmlspecialchars($emp['pf_no'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>ESI Number</label>
                    <div class="value"><?= htmlspecialchars($emp['esi_no'] ?? '-') ?></div>
                </div>
            </div>
        </div>

        <!-- Bank Details -->
        <div class="info-section">
            <h3>Bank Details</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Bank Name</label>
                    <div class="value"><?= htmlspecialchars($emp['bank_name'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Account Number</label>
                    <div class="value"><?= htmlspecialchars($emp['bank_account'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>IFSC Code</label>
                    <div class="value"><?= htmlspecialchars($emp['bank_ifsc'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Branch</label>
                    <div class="value"><?= htmlspecialchars($emp['bank_branch'] ?? '-') ?></div>
                </div>
            </div>
        </div>

        <!-- Salary Structure -->
        <div class="info-section">
            <h3>Salary Structure (Monthly)</h3>
            <table class="salary-table">
                <tr>
                    <td class="label">Basic Salary</td>
                    <td class="amount"><?= number_format($emp['basic_salary'], 2) ?></td>
                </tr>
                <tr>
                    <td class="label">HRA</td>
                    <td class="amount"><?= number_format($emp['hra'], 2) ?></td>
                </tr>
                <tr>
                    <td class="label">Conveyance</td>
                    <td class="amount"><?= number_format($emp['conveyance'], 2) ?></td>
                </tr>
                <tr>
                    <td class="label">Medical Allowance</td>
                    <td class="amount"><?= number_format($emp['medical_allowance'], 2) ?></td>
                </tr>
                <tr>
                    <td class="label">Special Allowance</td>
                    <td class="amount"><?= number_format($emp['special_allowance'], 2) ?></td>
                </tr>
                <tr>
                    <td class="label">Other Allowance</td>
                    <td class="amount"><?= number_format($emp['other_allowance'], 2) ?></td>
                </tr>
                <tr>
                    <td class="label">Performance Allowance</td>
                    <td class="amount"><?= number_format($emp['performance_allowance'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td class="label">Food Allowance</td>
                    <td class="amount"><?= number_format($emp['food_allowance'] ?? 0, 2) ?></td>
                </tr>
                <tr class="total">
                    <td class="label">Gross Salary</td>
                    <td class="amount"><?= number_format($grossSalary, 2) ?></td>
                </tr>
            </table>
        </div>

        <?php if ($emp['notes']): ?>
        <div class="info-section">
            <h3>Notes</h3>
            <div style="padding: 20px;">
                <?= nl2br(htmlspecialchars($emp['notes'])) ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
