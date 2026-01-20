<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic info
    $customer_type = $_POST['customer_type'] ?? 'B2B';
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Address
    $address1 = trim($_POST['address1'] ?? '');
    $address2 = trim($_POST['address2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $country = trim($_POST['country'] ?? 'India');

    // Lead details
    $lead_status = $_POST['lead_status'] ?? 'cold';
    $lead_source = trim($_POST['lead_source'] ?? '');
    $industry = trim($_POST['industry'] ?? '');

    // Buying intent
    $buying_timeline = $_POST['buying_timeline'] ?? 'uncertain';
    $budget_range = trim($_POST['budget_range'] ?? '');
    $decision_maker = $_POST['decision_maker'] ?? 'no';

    // Follow-up & notes
    $next_followup_date = $_POST['next_followup_date'] ?? '';
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if ($contact_person === '') {
        $errors[] = "Contact person name is required";
    }
    if ($phone === '') {
        $errors[] = "Contact number is required";
    }
    if ($customer_type === 'B2B' && $company_name === '') {
        $errors[] = "Company name is required for B2B leads";
    }

    if (empty($errors)) {
        // Generate lead number
        $maxNo = $pdo->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(lead_no, 6) AS UNSIGNED)), 0)
            FROM crm_leads WHERE lead_no LIKE 'LEAD-%'
        ")->fetchColumn();
        $lead_no = 'LEAD-' . ($maxNo + 1);

        $stmt = $pdo->prepare("
            INSERT INTO crm_leads (
                lead_no, customer_type, company_name, contact_person, designation,
                phone, email,
                address1, address2, city, state, pincode, country,
                lead_status, lead_source, industry,
                buying_timeline, budget_range, decision_maker,
                next_followup_date, assigned_to, notes
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
        ");

        $stmt->execute([
            $lead_no, $customer_type, $company_name ?: null, $contact_person, $designation ?: null,
            $phone, $email ?: null,
            $address1 ?: null, $address2 ?: null, $city ?: null, $state ?: null, $pincode ?: null, $country,
            $lead_status, $lead_source ?: null, $industry ?: null,
            $buying_timeline, $budget_range ?: null, $decision_maker,
            $next_followup_date ?: null, $assigned_to ?: null, $notes ?: null
        ]);

        $newId = $pdo->lastInsertId();

        setModal("Success", "Lead $lead_no created successfully!");
        header("Location: view.php?id=$newId");
        exit;
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Lead - CRM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 900px;
        }
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group small { color: #666; font-size: 0.85em; }

        .radio-group {
            display: flex;
            gap: 20px;
            padding: 10px 0;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            cursor: pointer;
        }
        .radio-group input[type="radio"] { width: auto; }

        .status-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .status-options label {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: normal;
        }
        .status-options input[type="radio"] { display: none; }
        .status-options input[type="radio"]:checked + span { font-weight: bold; }
        .status-options label:has(input:checked) { border-color: #3498db; background: #ebf5fb; }
        .status-options .status-cold { border-color: #bdc3c7; }
        .status-options .status-warm { border-color: #f39c12; }
        .status-options .status-hot { border-color: #e74c3c; }
        .status-options label.status-cold:has(input:checked) { background: #ecf0f1; }
        .status-options label.status-warm:has(input:checked) { background: #fef5e7; }
        .status-options label.status-hot:has(input:checked) { background: #fdedec; }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Add New Lead</h1>

        <p><a href="index.php" class="btn btn-secondary">Back to Leads</a></p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">

            <!-- Customer Type -->
            <div class="form-section">
                <h3>Customer Type</h3>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="customer_type" value="B2B" checked>
                        <strong>B2B</strong> (Business to Business)
                    </label>
                    <label>
                        <input type="radio" name="customer_type" value="B2C">
                        <strong>B2C</strong> (Business to Consumer)
                    </label>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>

                <!-- Import Customer Section -->
                <div style="background: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
                    <strong>Import Existing Customer Data</strong>
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                        <select id="customer_select" style="flex: 1; padding: 8px;">
                            <option value="">-- Select Existing Customer --</option>
                            <?php
                            $customers = $pdo->query("SELECT id, customer_id, customer_name, company_name, contact FROM customers ORDER BY customer_name")->fetchAll();
                            foreach ($customers as $cust) {
                                $displayName = $cust['customer_name'];
                                if ($cust['company_name']) {
                                    $displayName .= ' (' . $cust['company_name'] . ')';
                                }
                                $displayName .= ' - ' . $cust['customer_id'];
                                echo '<option value="' . $cust['id'] . '">' . htmlspecialchars($displayName) . '</option>';
                            }
                            ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="importCustomerData()">Import Data</button>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name <span id="companyRequired">*</span></label>
                        <input type="text" name="company_name" id="company_name"
                               placeholder="Company / Organization name">
                    </div>
                    <div class="form-group">
                        <label>Contact Person *</label>
                        <input type="text" name="contact_person" id="contact_person" required
                               placeholder="Primary contact name">
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" id="designation"
                               placeholder="e.g., Manager, Director, Owner">
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <input type="text" name="industry" id="industry"
                               placeholder="e.g., Manufacturing, Healthcare, IT">
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="phone" id="phone" required placeholder="Primary phone number">
                        <div id="phone_lookup_result"></div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email" placeholder="email@example.com">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3>Address</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 1</label>
                        <input type="text" name="address1" id="address1" placeholder="Street address, building">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 2</label>
                        <input type="text" name="address2" id="address2" placeholder="Area, landmark">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" id="city">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" name="state" id="state">
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" id="pincode">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" id="country" value="India">
                    </div>
                </div>
            </div>

            <!-- Lead Classification -->
            <div class="form-section">
                <h3>Lead Classification</h3>

                <div class="form-group">
                    <label>Lead Status</label>
                    <div class="status-options">
                        <label class="status-cold">
                            <input type="radio" name="lead_status" value="cold" checked>
                            <span>Cold</span>
                        </label>
                        <label class="status-warm">
                            <input type="radio" name="lead_status" value="warm">
                            <span>Warm</span>
                        </label>
                        <label class="status-hot">
                            <input type="radio" name="lead_status" value="hot">
                            <span>Hot</span>
                        </label>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>Lead Source</label>
                        <select name="lead_source">
                            <option value="">-- Select Source --</option>
                            <option value="Website">Website</option>
                            <option value="Referral">Referral</option>
                            <option value="Cold Call">Cold Call</option>
                            <option value="Trade Show">Trade Show</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Email Campaign">Email Campaign</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Buying Intent -->
            <div class="form-section">
                <h3>Buying Intent</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Buying Timeline</label>
                        <select name="buying_timeline">
                            <option value="uncertain">Uncertain</option>
                            <option value="immediate">Immediate</option>
                            <option value="1_month">Within 1 Month</option>
                            <option value="3_months">Within 3 Months</option>
                            <option value="6_months">Within 6 Months</option>
                            <option value="1_year">Within 1 Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Budget Range</label>
                        <input type="text" name="budget_range" placeholder="e.g., 1-5 Lakhs">
                    </div>
                    <div class="form-group">
                        <label>Decision Maker?</label>
                        <select name="decision_maker">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                            <option value="influencer">Influencer</option>
                        </select>
                        <small>Is this contact the decision maker for purchases?</small>
                    </div>
                </div>
            </div>

            <!-- Follow-up & Assignment -->
            <div class="form-section">
                <h3>Follow-up & Assignment</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Next Follow-up Date</label>
                        <input type="date" name="next_followup_date">
                    </div>
                    <div class="form-group">
                        <label>Assigned To</label>
                        <input type="text" name="assigned_to" placeholder="Sales person name">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Any initial notes about this lead..."></textarea>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1.1em;">
                    Create Lead
                </button>
                <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
            </div>

        </form>
    </div>
</div>

<script>
// Toggle company name requirement based on customer type
document.querySelectorAll('input[name="customer_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const companyField = document.getElementById('company_name');
        const requiredSpan = document.getElementById('companyRequired');
        if (this.value === 'B2B') {
            companyField.required = true;
            requiredSpan.style.display = 'inline';
        } else {
            companyField.required = false;
            requiredSpan.style.display = 'none';
        }
    });
});

// Import customer data
function importCustomerData() {
    const customerId = document.getElementById('customer_select').value;

    if (!customerId) {
        alert('Please select a customer first');
        return;
    }

    fetch(`/api/get_customer_by_id.php?id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data) {
                // Set customer type based on company name
                if (data.company_name) {
                    document.querySelector('input[name="customer_type"][value="B2B"]').checked = true;
                    document.querySelector('input[name="customer_type"][value="B2B"]').dispatchEvent(new Event('change'));
                } else {
                    document.querySelector('input[name="customer_type"][value="B2C"]').checked = true;
                    document.querySelector('input[name="customer_type"][value="B2C"]').dispatchEvent(new Event('change'));
                }

                // Fill basic info
                document.getElementById('company_name').value = data.company_name || '';
                document.getElementById('contact_person').value = data.customer_name || '';
                document.getElementById('phone').value = data.contact || '';
                document.getElementById('email').value = data.email || '';

                // Fill address
                document.getElementById('address1').value = data.address1 || '';
                document.getElementById('address2').value = data.address2 || '';
                document.getElementById('city').value = data.city || '';
                document.getElementById('state').value = data.state || '';
                document.getElementById('pincode').value = data.pincode || '';

                alert('Customer data imported successfully!');
            }
        })
        .catch(error => {
            console.error('Error importing customer data:', error);
            alert('Error loading customer data');
        });
}

// Check for phone number duplicate
function checkPhoneDuplicate() {
    const phone = document.getElementById('phone').value;
    const lookupResult = document.getElementById('phone_lookup_result');

    if (!phone || phone.length < 10) {
        lookupResult.innerHTML = '';
        return;
    }

    fetch(`/api/check_customer.php?contact=${encodeURIComponent(phone)}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                lookupResult.innerHTML = `
                    <div style="background: #fff3cd; padding: 8px; border-radius: 4px; margin-top: 5px; border-left: 3px solid #ffc107; font-size: 0.9em;">
                        <strong>⚠ Warning:</strong> Customer exists with this phone number:<br>
                        <strong>${data.customer_name}</strong> (${data.customer_id})
                    </div>
                `;
            } else {
                lookupResult.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Error checking phone:', error);
            lookupResult.innerHTML = '';
        });
}

// Add phone number check on input
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');
    let timeout;
    phoneInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(checkPhoneDuplicate, 500);
    });
});
</script>

</body>
</html>
