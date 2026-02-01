<?php
include "../db.php";
include "../includes/dialog.php";

// Ensure all required columns exist in customers table
$columnsToAdd = [
    "ALTER TABLE customers ADD COLUMN designation VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN secondary_designation VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN secondary_contact_name VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN customer_type VARCHAR(10) DEFAULT 'B2B'",
    "ALTER TABLE customers ADD COLUMN industry VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN customer_phone VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN contact VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN email VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN address1 VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN address2 VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN city VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN state VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN pincode VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE customers MODIFY COLUMN gstin VARCHAR(20) DEFAULT NULL"
];
foreach ($columnsToAdd as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) {}
}

/* --- Generate next Customer ID --- */
$max = $pdo->query("
    SELECT MAX(CAST(SUBSTRING(customer_id,6) AS UNSIGNED))
    FROM customers
    WHERE customer_id LIKE 'CUST-%'
")->fetchColumn();

$next = $max ? ((int)$max + 1) : 1;
$customer_id = 'CUST-' . $next;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $contact = $_POST['contact'];

    // Check if customer with same phone number already exists
    if (!empty($contact)) {
        $existingCustomer = $pdo->prepare("SELECT customer_id, customer_name FROM customers WHERE contact = ?");
        $existingCustomer->execute([$contact]);
        $existing = $existingCustomer->fetch();

        if ($existing) {
            setModal("Duplicate Customer", "A customer with phone number '$contact' already exists: " . $existing['customer_name'] . " (" . $existing['customer_id'] . ")");
            header("Location: add.php");
            exit;
        }
    }

    $pdo->prepare("
        INSERT INTO customers
        (customer_id, company_name, designation, customer_name, contact, email, address1, address2, city, pincode, state, gstin, secondary_designation, secondary_contact_name, customer_type, industry)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $customer_id,
        $_POST['company_name'],
        $_POST['designation'] ?? null,
        $_POST['customer_name'],
        $_POST['contact'],
        $_POST['email'],
        $_POST['address1'],
        $_POST['address2'],
        $_POST['city'],
        $_POST['pincode'],
        $_POST['state'],
        $_POST['gstin'],
        $_POST['secondary_designation'] ?? null,
        $_POST['secondary_contact_name'] ?? null,
        $_POST['customer_type'] ?? 'B2B',
        $_POST['industry'] ?? null
    ]);

    header("Location: index.php");
    exit;
}

// Include sidebar AFTER all redirects
include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Customer</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .form-section {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .form-section h3 {
            margin: 0 0 18px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
            color: #2c3e50;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section h3 .icon {
            font-size: 1.2em;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
        }
        .form-group label .required {
            color: #dc3545;
            margin-left: 2px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            color: #6c757d;
            font-size: 0.8em;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.6em;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: transform 0.1s, box-shadow 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .customer-type-toggle {
            display: flex;
            gap: 10px;
        }
        .customer-type-toggle label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .customer-type-toggle label:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .customer-type-toggle input[type="radio"] {
            width: auto;
            margin: 0;
        }
        .customer-type-toggle input[type="radio"]:checked + span {
            color: #667eea;
            font-weight: 600;
        }
        .customer-type-toggle label:has(input:checked) {
            border-color: #667eea;
            background: #f0f4ff;
        }
        #lookup_result {
            margin-top: 8px;
        }

        /* Dark mode support */
        body.dark .form-section {
            background: #2c3e50;
            border-color: #3d566e;
        }
        body.dark .form-section h3 {
            color: #ecf0f1;
        }
        body.dark .form-group label {
            color: #bdc3c7;
        }
        body.dark .form-group input,
        body.dark .form-group select {
            background: #34495e;
            border-color: #4a6278;
            color: #ecf0f1;
        }
        body.dark .customer-type-toggle label {
            border-color: #4a6278;
            color: #ecf0f1;
        }
        body.dark .customer-type-toggle label:hover,
        body.dark .customer-type-toggle label:has(input:checked) {
            background: #3d566e;
        }
    </style>
</head>

<body>

<script>
function loadCities(stateName) {
    const citySelect = document.getElementById('city_select');

    if (!stateName) {
        citySelect.innerHTML = '<option value="">-- Select City --</option>';
        return;
    }

    citySelect.innerHTML = '<option value="">Loading cities...</option>';

    // Try to fetch cities from API
    fetch(`/api/get_cities.php?state=${encodeURIComponent(stateName)}`)
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">-- Select City --</option>';

            if (data.success && data.cities && data.cities.length > 0) {
                data.cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.city_name;
                    option.textContent = city.city_name;
                    citySelect.appendChild(option);
                });
            } else {
                // Try alternate API endpoint for india_cities table
                fetchCitiesFromIndiaTable(stateName);
            }
        })
        .catch(error => {
            console.error('Error loading cities:', error);
            fetchCitiesFromIndiaTable(stateName);
        });
}

function fetchCitiesFromIndiaTable(stateName) {
    const citySelect = document.getElementById('city_select');

    fetch(`/api/get_india_cities.php?state=${encodeURIComponent(stateName)}`)
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">-- Select City --</option>';

            if (Array.isArray(data) && data.length > 0) {
                data.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.city_name;
                    option.textContent = city.city_name;
                    citySelect.appendChild(option);
                });
            } else {
                citySelect.innerHTML = '<option value="">-- No cities available --</option>';
            }
        })
        .catch(error => {
            console.error('Error loading cities from india table:', error);
            citySelect.innerHTML = '<option value="">-- Error loading cities --</option>';
        });
}

function checkExistingCustomer() {
    const contact = document.querySelector('input[name="contact"]').value;
    const lookupResult = document.getElementById('lookup_result');

    if (!contact || contact.length < 10) {
        lookupResult.innerHTML = '';
        return;
    }

    fetch(`/api/check_customer.php?contact=${encodeURIComponent(contact)}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                lookupResult.innerHTML = `
                    <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107;">
                        <strong>Customer Found:</strong> ${data.customer_name} (${data.customer_id})<br>
                        <button type="button" class="btn btn-secondary" onclick="loadExistingCustomer('${contact}')" style="margin-top: 5px;">Load Existing Data</button>
                    </div>
                `;
            } else {
                lookupResult.innerHTML = '<div style="color: #28a745; padding: 5px;">✓ No duplicate found</div>';
            }
        })
        .catch(error => {
            console.error('Error checking customer:', error);
            lookupResult.innerHTML = '';
        });
}

function loadExistingCustomer(contact) {
    fetch(`/api/get_customer.php?contact=${encodeURIComponent(contact)}`)
        .then(response => response.json())
        .then(data => {
            if (data) {
                // Only fill name fields, NOT address fields
                document.querySelector('input[name="company_name"]').value = data.company_name || '';
                document.querySelector('input[name="customer_name"]').value = data.customer_name || '';
                document.querySelector('input[name="email"]').value = data.email || '';
                // Address fields are NOT auto-filled - user must enter manually
            }
        })
        .catch(error => console.error('Error loading customer data:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.querySelector('select[name="state"]');
    stateSelect.addEventListener('change', function() {
        loadCities(this.value);
    });

    // Add debounce to contact field
    const contactInput = document.querySelector('input[name="contact"]');
    let timeout;
    contactInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(checkExistingCustomer, 500);
    });
});
</script>

<div class="content">
    <div class="form-container">
        <div class="page-header">
            <h1>Add New Customer</h1>
            <a href="index.php" class="btn btn-secondary">Back to Customers</a>
        </div>

        <form method="post">
            <!-- Customer Type Section -->
            <div class="form-section">
                <h3><span class="icon">🏢</span> Customer Classification</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Customer ID</label>
                        <input value="<?= htmlspecialchars($customer_id) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Customer Type</label>
                        <div class="customer-type-toggle">
                            <label>
                                <input type="radio" name="customer_type" value="B2B" checked>
                                <span>B2B (Business)</span>
                            </label>
                            <label>
                                <input type="radio" name="customer_type" value="B2C">
                                <span>B2C (Consumer)</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Industry / Sector</label>
                        <select name="industry">
                            <option value="">-- Select Industry --</option>
                            <option value="Multi-Specialty Hospital">Multi-Specialty Hospital</option>
                            <option value="Super-Specialty Hospital">Super-Specialty Hospital</option>
                            <option value="Medical College">Medical College</option>
                            <option value="Nursing Home">Nursing Home</option>
                            <option value="Eye Hospital">Eye Hospital</option>
                            <option value="Medical Equipment Dealer">Medical Equipment Dealer</option>
                            <option value="Hospital Supply Chain">Hospital Supply Chain</option>
                            <option value="Lab Equipment Supplier">Lab Equipment Supplier</option>
                            <option value="Surgical Instrument Dealer">Surgical Instrument Dealer</option>
                            <option value="Medical Device Manufacturing">Medical Device Manufacturing</option>
                            <option value="Medical E-commerce">Medical E-commerce</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="form-section">
                <h3><span class="icon">👤</span> Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name <span class="required">*</span></label>
                        <input name="company_name" required placeholder="Enter company name">
                    </div>
                    <div class="form-group">
                        <label>Customer Name <span class="required">*</span></label>
                        <input name="customer_name" required placeholder="Primary contact person">
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation">
                            <option value="">-- Select Designation --</option>
                            <optgroup label="Title">
                                <option value="Dr.">Dr.</option>
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Prof.">Prof.</option>
                            </optgroup>
                            <optgroup label="Position">
                                <option value="Chairman">Chairman</option>
                                <option value="Managing Director">Managing Director</option>
                                <option value="Director">Director</option>
                                <option value="Owner">Owner</option>
                                <option value="Partner">Partner</option>
                                <option value="CEO">CEO</option>
                                <option value="CFO">CFO</option>
                                <option value="COO">COO</option>
                                <option value="General Manager">General Manager</option>
                                <option value="Manager">Manager</option>
                                <option value="Purchase Manager">Purchase Manager</option>
                                <option value="Accountant">Accountant</option>
                                <option value="Administrator">Administrator</option>
                            </optgroup>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact (Phone)</label>
                        <input name="contact" placeholder="10-digit phone number" maxlength="15">
                        <div id="lookup_result"></div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input name="email" type="email" placeholder="email@example.com">
                    </div>
                </div>
            </div>

            <!-- Secondary Contact -->
            <div class="form-section">
                <h3><span class="icon">👥</span> Secondary Contact (Optional)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Contact Person Name</label>
                        <input name="secondary_contact_name" placeholder="Secondary contact person name">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3><span class="icon">📍</span> Address</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Address Line 1</label>
                        <input name="address1" placeholder="Street address, building name">
                    </div>
                    <div class="form-group full-width">
                        <label>Address Line 2</label>
                        <input name="address2" placeholder="Area, landmark">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <select name="state">
                            <option value="">-- Select State --</option>
                            <?php
                            $states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();
                            foreach ($states as $state) {
                                echo '<option value="' . htmlspecialchars($state['state_name']) . '">' . htmlspecialchars($state['state_name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <select id="city_select" name="city">
                            <option value="">-- Select State First --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input name="pincode" maxlength="10" placeholder="6-digit pincode">
                    </div>
                    <div class="form-group">
                        <label>GSTIN</label>
                        <input name="gstin" placeholder="15-character GSTIN" maxlength="15">
                        <small>Format: 22AAAAA0000A1Z5</small>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="btn-group">
                <button type="submit" class="btn-primary">Add Customer</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
