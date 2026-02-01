<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: employees.php");
    exit;
}

// Fetch employee
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    header("Location: employees.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_joining_input = $_POST['date_of_joining'] ?? '';
    $date_of_birth_input = $_POST['date_of_birth'] ?? '';

    if ($first_name === '') $errors[] = "First name is required";
    if ($phone === '') $errors[] = "Phone number is required";
    if ($date_of_joining_input === '') $errors[] = "Date of joining is required";

    // Check for duplicate phone number (exclude current employee)
    if (!empty($phone) && $phone !== $emp['phone']) {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE phone = ? AND id != ? AND status != 'Inactive'");
        $stmt->execute([$phone, $id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $errors[] = "An employee with phone number '$phone' already exists: " . $existing['first_name'] . " " . $existing['last_name'] . " (ID: EMP-" . str_pad($existing['id'], 4, '0', STR_PAD_LEFT) . ")";
        }
    }

    // Convert DD-MM-YYYY to YYYY-MM-DD for database
    $date_of_joining = '';
    if (!empty($date_of_joining_input)) {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date_of_joining_input, $matches)) {
            $date_of_joining = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } else {
            $errors[] = "Date of joining must be in DD-MM-YYYY format";
        }
    }

    $date_of_birth = '';
    if (!empty($date_of_birth_input)) {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date_of_birth_input, $matches)) {
            $date_of_birth = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } else {
            $errors[] = "Date of birth must be in DD-MM-YYYY format";
        }
    }

    if (empty($errors)) {
        // Handle photo upload
        $photo_path = $emp['photo_path'];
        if (!empty($_FILES['photo']['name'])) {
            $uploadDir = "../uploads/employees/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $fileName = $emp['emp_id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName)) {
                    // Delete old photo
                    if ($photo_path && file_exists("../" . $photo_path)) {
                        unlink("../" . $photo_path);
                    }
                    $photo_path = 'uploads/employees/' . $fileName;
                }
            }
        }

        $stmt = $pdo->prepare("
            UPDATE employees SET
                first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, marital_status = ?, blood_group = ?,
                phone = ?, alt_phone = ?, email = ?, personal_email = ?,
                address_line1 = ?, address_line2 = ?, city = ?, state = ?, pincode = ?, country = ?,
                emergency_contact_name = ?, emergency_contact_relation = ?, emergency_contact_phone = ?,
                department = ?, designation = ?, employment_type = ?, date_of_joining = ?, date_of_leaving = ?,
                reporting_to = ?, work_location = ?,
                aadhar_no = ?, pan_no = ?, uan_no = ?, pf_no = ?, esi_no = ?,
                bank_name = ?, bank_account = ?, bank_ifsc = ?, bank_branch = ?,
 \
                photo_path = ?, notes = ?, status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $first_name,
            $last_name,
            !empty($date_of_birth) ? $date_of_birth : null,
            $_POST['gender'] ?? 'Male',
            $_POST['marital_status'] ?? 'Single',
            $_POST['blood_group'] ?: null,
            $phone,
            $_POST['alt_phone'] ?: null,
            $_POST['email'] ?: null,
            $_POST['personal_email'] ?: null,
            $_POST['address_line1'] ?: null,
            $_POST['address_line2'] ?: null,
            $_POST['city'] ?: null,
            $_POST['state'] ?: null,
            $_POST['pincode'] ?: null,
            $_POST['country'] ?: 'India',
            $_POST['emergency_contact_name'] ?: null,
            $_POST['emergency_contact_relation'] ?: null,
            $_POST['emergency_contact_phone'] ?: null,
            $_POST['department'] ?: null,
            $_POST['designation'] ?: null,
            $_POST['employment_type'] ?? 'Full-time',
            $date_of_joining,
            $_POST['date_of_leaving'] ?: null,
            $_POST['reporting_to'] ?: null,
            $_POST['work_location'] ?: null,
            $_POST['aadhar_no'] ?: null,
            $_POST['pan_no'] ?: null,
            $_POST['uan_no'] ?: null,
            $_POST['pf_no'] ?: null,
            $_POST['esi_no'] ?: null,
            $_POST['bank_name'] ?: null,
            $_POST['bank_account'] ?: null,
            $_POST['bank_ifsc'] ?: null,
            $_POST['bank_branch'] ?: null,
            $_POST['basic_salary'] ?: 0,
            $_POST['hra'] ?: 0,
            $_POST['conveyance'] ?: 0,
            $_POST['medical_allowance'] ?: 0,
            $_POST['special_allowance'] ?: 0,
            $_POST['other_allowance'] ?: 0,
            $_POST['performance_allowance'] ?: 0,
            $_POST['food_allowance'] ?: 0,
            $photo_path,
            $_POST['notes'] ?: null,
            $_POST['status'] ?? 'Active',
            $id
        ]);

        setModal("Success", "Employee updated successfully!");
        header("Location: employee_view.php?id=$id");
        exit;
    }
}

// Get managers for dropdown (excluding current employee)
$managers = $pdo->prepare("SELECT id, emp_id, first_name, last_name FROM employees WHERE status = 'Active' AND id != ? ORDER BY first_name");
$managers->execute([$id]);
$managers = $managers->fetchAll();

// Get departments - with fallback list
try {
    $departments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $departments = [];
}

// Get states for dropdown
$states = [];
try {
    $states = $pdo->query("SELECT id, state_name FROM states WHERE is_active = 1 ORDER BY state_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist
}

// Fallback department list if table is empty or doesn't exist
if (empty($departments)) {
    $departments = [
        'Administration',
        'Accounts',
        'Assembly',
        'Design',
        'Electrical',
        'Electronics',
        'Engineering',
        'Fabrication',
        'Finance',
        'HR',
        'IT',
        'Kronos',
        'Maintenance',
        'Manufacturing',
        'Marketing',
        'Operations',
        'Production',
        'Purchase',
        'Quality',
        'R&D',
        'Sales',
        'Service',
        'Store',
        'Testing',
        'Welding'
    ];
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Employee - <?= htmlspecialchars($emp['emp_id']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 1000px; }
        .form-section {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group.full-width { grid-column: 1 / -1; }

        .error-box {
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
            padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }

        .current-photo {
            width: 80px; height: 80px; border-radius: 50%;
            object-fit: cover; border: 2px solid #ddd;
        }
        .photo-section { display: flex; align-items: center; gap: 15px; }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Employee: <?= htmlspecialchars($emp['emp_id']) ?></h1>
        <p>
            <a href="employees.php" class="btn btn-secondary">Back to List</a>
            <a href="employee_view.php?id=<?= $id ?>" class="btn btn-secondary">View Profile</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

            <!-- Personal Information -->
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($emp['first_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required value="<?= htmlspecialchars($emp['last_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="text" name="date_of_birth" placeholder="DD-MM-YYYY" pattern="\d{2}-\d{2}-\d{4}" value="<?= $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '' ?>">
                        <small style="color: #666;">Format: DD-MM-YYYY (e.g., 15-06-1990)</small>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="Male" <?= $emp['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $emp['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= $emp['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marital Status</label>
                        <select name="marital_status">
                            <option value="Single" <?= $emp['marital_status'] === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= $emp['marital_status'] === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Divorced" <?= $emp['marital_status'] === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                            <option value="Widowed" <?= $emp['marital_status'] === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group</label>
                        <select name="blood_group">
                            <option value="">-- Select --</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= $emp['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Photo</label>
                        <div class="photo-section">
                            <?php if ($emp['photo_path']): ?>
                                <img src="../<?= htmlspecialchars($emp['photo_path']) ?>" class="current-photo">
                            <?php endif; ?>
                            <input type="file" name="photo" accept=".jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <h3>Contact Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="text" name="phone" required value="<?= htmlspecialchars($emp['phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Alternate Phone</label>
                        <input type="text" name="alt_phone" value="<?= htmlspecialchars($emp['alt_phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Official Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($emp['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Personal Email</label>
                        <input type="email" name="personal_email" value="<?= htmlspecialchars($emp['personal_email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3>Address</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Address Line 1</label>
                        <input type="text" name="address_line1" value="<?= htmlspecialchars($emp['address_line1'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Address Line 2</label>
                        <input type="text" name="address_line2" value="<?= htmlspecialchars($emp['address_line2'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <select name="state" id="stateSelect" onchange="loadCities(this.value)">
                            <option value="">-- Select State --</option>
                            <?php foreach ($states as $st): ?>
                            <option value="<?= htmlspecialchars($st['state_name']) ?>"
                                    data-id="<?= $st['id'] ?>"
                                    <?= ($emp['state'] ?? '') === $st['state_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($st['state_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <select name="city" id="citySelect">
                            <option value="">-- Select City --</option>
                            <?php if (!empty($emp['city'])): ?>
                            <option value="<?= htmlspecialchars($emp['city']) ?>" selected><?= htmlspecialchars($emp['city']) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" value="<?= htmlspecialchars($emp['pincode'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" value="<?= htmlspecialchars($emp['country'] ?? 'India') ?>">
                    </div>
                </div>
            </div>

            <script>
            // Load cities on page load if state is already selected
            document.addEventListener('DOMContentLoaded', function() {
                const stateSelect = document.getElementById('stateSelect');
                if (stateSelect.value) {
                    loadCities(stateSelect.value, '<?= addslashes($emp['city'] ?? '') ?>');
                }
            });

            function loadCities(stateName, selectedCity = '') {
                const citySelect = document.getElementById('citySelect');
                citySelect.innerHTML = '<option value="">Loading...</option>';

                if (!stateName) {
                    citySelect.innerHTML = '<option value="">-- Select City --</option>';
                    return;
                }

                fetch('../api/get_cities.php?state=' + encodeURIComponent(stateName))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        let options = '<option value="">-- Select City --</option>';
                        const cities = data.cities || [];

                        if (Array.isArray(cities) && cities.length > 0) {
                            cities.forEach(city => {
                                const cityName = city.city_name || city;
                                const isSelected = (selectedCity && cityName === selectedCity) ? 'selected' : '';
                                options += '<option value="' + cityName + '" ' + isSelected + '>' + cityName + '</option>';
                            });
                        } else {
                            // If no cities found in database, allow manual entry
                            if (selectedCity) {
                                options += '<option value="' + selectedCity + '" selected>' + selectedCity + '</option>';
                            }
                            options += '<option value="">-- No cities found --</option>';
                        }

                        citySelect.innerHTML = options;
                    })
                    .catch(error => {
                        console.error('Error loading cities:', error);
                        // On error, still allow the current city to be kept
                        let options = '<option value="">-- Select City --</option>';
                        if (selectedCity) {
                            options = '<option value="' + selectedCity + '" selected>' + selectedCity + '</option>' + options;
                        }
                        citySelect.innerHTML = options;
                    });
            }
            </script>

            <!-- Emergency Contact -->
            <div class="form-section">
                <h3>Emergency Contact</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Contact Name</label>
                        <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars($emp['emergency_contact_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Relation</label>
                        <input type="text" name="emergency_contact_relation" value="<?= htmlspecialchars($emp['emergency_contact_relation'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" value="<?= htmlspecialchars($emp['emergency_contact_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Employment Details -->
            <div class="form-section">
                <h3>Employment Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department">
                            <option value="">-- Select --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= $emp['department'] === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" value="<?= htmlspecialchars($emp['designation'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Employment Type</label>
                        <select name="employment_type">
                            <?php foreach (['Full-time','Part-time','Contract','Intern','Trainee'] as $t): ?>
                                <option value="<?= $t ?>" <?= $emp['employment_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Joining *</label>
                        <input type="text" name="date_of_joining" placeholder="DD-MM-YYYY" pattern="\d{2}-\d{2}-\d{4}" required value="<?= date('d-m-Y', strtotime($emp['date_of_joining'])) ?>">
                        <small style="color: #666;">Format: DD-MM-YYYY (e.g., 15-01-2020)</small>
                    </div>
                    <div class="form-group">
                        <label>Date of Leaving</label>
                        <input type="date" name="date_of_leaving" value="<?= $emp['date_of_leaving'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Reporting To</label>
                        <select name="reporting_to">
                            <option value="">-- Select Manager --</option>
                            <?php foreach ($managers as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $emp['reporting_to'] == $m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name'] . ' (' . $m['emp_id'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Work Location</label>
                        <input type="text" name="work_location" value="<?= htmlspecialchars($emp['work_location'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['Active','Inactive','On Leave','Resigned','Terminated'] as $s): ?>
                                <option value="<?= $s ?>" <?= $emp['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ID Documents -->
            <div class="form-section">
                <h3>ID Documents</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Aadhar Number</label>
                        <input type="text" name="aadhar_no" value="<?= htmlspecialchars($emp['aadhar_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>PAN Number</label>
                        <input type="text" name="pan_no" value="<?= htmlspecialchars($emp['pan_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>UAN Number</label>
                        <input type="text" name="uan_no" value="<?= htmlspecialchars($emp['uan_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>PF Number</label>
                        <input type="text" name="pf_no" value="<?= htmlspecialchars($emp['pf_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>ESI Number</label>
                        <input type="text" name="esi_no" value="<?= htmlspecialchars($emp['esi_no'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="form-section">
                <h3>Bank Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?= htmlspecialchars($emp['bank_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="bank_account" value="<?= htmlspecialchars($emp['bank_account'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="bank_ifsc" value="<?= htmlspecialchars($emp['bank_ifsc'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Branch</label>
                        <input type="text" name="bank_branch" value="<?= htmlspecialchars($emp['bank_branch'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Salary Details -->
            <div class="form-section">
                <h3>Salary Details (Monthly)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Basic Salary</label>
                        <input type="number" name="basic_salary" id="basic_salary" step="0.01" value="<?= $emp['basic_salary'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                    <div class="form-group">
                        <label>HRA</label>
                        <input type="number" name="hra" id="hra" step="0.01" value="<?= $emp['hra'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                    <div class="form-group">
                        <label>Conveyance</label>
                        <input type="number" name="conveyance" id="conveyance" step="0.01" value="<?= $emp['conveyance'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                    <div class="form-group">
                        <label>Medical Allowance</label>
                        <input type="number" name="medical_allowance" id="medical_allowance" step="0.01" value="<?= $emp['medical_allowance'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                    <div class="form-group">
                        <label>Special Allowance</label>
                        <input type="number" name="special_allowance" id="special_allowance" step="0.01" value="<?= $emp['special_allowance'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                    <div class="form-group">
                        <label>Other Allowance</label>
                        <input type="number" name="other_allowance" id="other_allowance" step="0.01" value="<?= $emp['other_allowance'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                    <div class="form-group">
                        <label>Performance Allowance</label>
                        <input type="number" name="performance_allowance" id="performance_allowance" step="0.01" value="<?= $emp['performance_allowance'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                    <div class="form-group">
                        <label>Food Allowance</label>
                        <input type="number" name="food_allowance" id="food_allowance" step="0.01" value="<?= $emp['food_allowance'] ?? 0 ?>" onchange="calculateSalaryTotals()" onkeyup="calculateSalaryTotals()">
                    </div>
                </div>
                <!-- Salary Totals -->
                <div style="margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white;">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center;">
                        <div>
                            <div style="font-size: 0.85em; opacity: 0.9;">Total Allowances</div>
                            <div id="totalAllowances" style="font-size: 1.4em; font-weight: bold;">₹0.00</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85em; opacity: 0.9;">Basic Salary</div>
                            <div id="basicDisplay" style="font-size: 1.4em; font-weight: bold;">₹0.00</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85em; opacity: 0.9;">Total Gross Salary</div>
                            <div id="totalGross" style="font-size: 1.4em; font-weight: bold;">₹0.00</div>
                        </div>
                    </div>
                </div>
                <script>
                function calculateSalaryTotals() {
                    const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
                    const hra = parseFloat(document.getElementById('hra').value) || 0;
                    const conveyance = parseFloat(document.getElementById('conveyance').value) || 0;
                    const medical = parseFloat(document.getElementById('medical_allowance').value) || 0;
                    const special = parseFloat(document.getElementById('special_allowance').value) || 0;
                    const other = parseFloat(document.getElementById('other_allowance').value) || 0;
                    const performance = parseFloat(document.getElementById('performance_allowance').value) || 0;
                    const food = parseFloat(document.getElementById('food_allowance').value) || 0;

                    const totalAllowances = hra + conveyance + medical + special + other + performance + food;
                    const totalGross = basic + totalAllowances;

                    document.getElementById('totalAllowances').textContent = '₹' + totalAllowances.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    document.getElementById('basicDisplay').textContent = '₹' + basic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    document.getElementById('totalGross').textContent = '₹' + totalGross.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                // Calculate on page load
                document.addEventListener('DOMContentLoaded', calculateSalaryTotals);
                </script>
            </div>

            <!-- Notes -->
            <div class="form-section">
                <h3>Additional Notes</h3>
                <div class="form-group">
                    <textarea name="notes"><?= htmlspecialchars($emp['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1.1em;">
                Update Employee
            </button>
            <a href="employee_view.php?id=<?= $id ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>

        </form>
    </div>
</div>

</body>
</html>
