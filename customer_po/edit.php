<?php
include "../db.php";

$errors = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch existing PO
$stmt = $pdo->prepare("SELECT * FROM customer_po WHERE id = ?");
$stmt->execute([$id]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    header("Location: index.php");
    exit;
}

// Fetch current customer details
$currentCustomer = null;
if ($po['customer_id']) {
    $stmt = $pdo->prepare("SELECT customer_id, company_name, customer_name, city, contact FROM customers WHERE customer_id = ?");
    $stmt->execute([$po['customer_id']]);
    $currentCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch customers for search
$customers = $pdo->query("
    SELECT customer_id, company_name, customer_name, city, contact
    FROM customers
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch released PIs that are not already linked to other POs (excluding current PO's link)
$stmtPis = $pdo->prepare("
    SELECT id, pi_no, quote_no, customer_id
    FROM quote_master
    WHERE status = 'released' AND (
        id NOT IN (SELECT linked_quote_id FROM customer_po WHERE linked_quote_id IS NOT NULL AND id != ?)
        OR id = ?
    )
    ORDER BY released_at DESC
");
$stmtPis->execute([$id, $po['linked_quote_id']]);
$pis = $stmtPis->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_no = trim($_POST['po_no'] ?? '');
    $customer_id = trim($_POST['customer_id'] ?? '');
    $po_date = trim($_POST['po_date'] ?? '');
    $linked_quote_id = !empty($_POST['linked_quote_id']) ? (int)$_POST['linked_quote_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    // Validation
    if ($po_no === '') {
        $errors[] = "PO Number is required";
    }

    if ($customer_id === '') {
        $errors[] = "Customer is required";
    }

    if (!$linked_quote_id) {
        $errors[] = "Linking to a Proforma Invoice is mandatory";
    }

    // Handle file upload
    $attachmentPath = $po['attachment_path'];
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = "../uploads/customer_po/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowedExts)) {
            $errors[] = "File type not allowed. Allowed: PDF, JPG, PNG";
        } else {
            $fileName = "CPO_" . preg_replace('/[^a-zA-Z0-9]/', '_', $po_no) . "_" . time() . "." . $ext;
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
                $attachmentPath = "uploads/customer_po/" . $fileName;
            } else {
                $errors[] = "Failed to upload attachment";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE customer_po
            SET po_no = ?, customer_id = ?, po_date = ?, linked_quote_id = ?,
                attachment_path = ?, notes = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $po_no,
            $customer_id ?: null,
            $po_date ?: null,
            $linked_quote_id,
            $attachmentPath,
            $notes,
            $status,
            $id
        ]);

        header("Location: view.php?id=" . $id);
        exit;
    }
}

// Convert data to JSON for JavaScript
$customersJson = json_encode($customers);
$currentCustomerJson = json_encode($currentCustomer);

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Customer PO - <?= htmlspecialchars($po['po_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-section {
            max-width: 700px;
            padding: 20px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; }

        /* Customer Search Styles */
        .search-container {
            position: relative;
            width: 100%;
        }
        .customer-search-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .search-results.active {
            display: block;
        }
        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .search-result-item:hover {
            background: #f0f7ff;
        }
        .customer-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        .customer-details {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        .no-results {
            padding: 15px;
            text-align: center;
            color: #666;
        }
        .search-hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        .selected-customer {
            padding: 12px;
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .selected-customer.active {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .selected-customer-info {
            flex: 1;
        }
        .selected-customer-name {
            font-weight: bold;
            color: #2e7d32;
        }
        .selected-customer-detail {
            font-size: 12px;
            color: #555;
        }
        .clear-selection {
            background: #f44336;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .clear-selection:hover {
            background: #d32f2f;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Edit Customer PO - <?= htmlspecialchars($po['po_no']) ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-section" id="poForm">

        <div class="form-group">
            <label>Customer PO Number *</label>
            <input type="text" name="po_no" value="<?= htmlspecialchars($po['po_no']) ?>" required>
        </div>

        <div class="form-group">
            <label>Customer *</label>
            <div class="search-container">
                <input type="text" class="customer-search-input" id="customer_search"
                       placeholder="Search by dealer name or owner name..."
                       onkeyup="searchCustomers(this)"
                       onfocus="showCustomerResults()"
                       autocomplete="off">
                <div class="search-results" id="customer_results"></div>
            </div>
            <input type="hidden" name="customer_id" id="customer_id" required>
            <p class="search-hint">Type to search by company/dealer name or owner name</p>

            <div class="selected-customer" id="selected_customer">
                <div class="selected-customer-info">
                    <div class="selected-customer-name" id="selected_customer_name"></div>
                    <div class="selected-customer-detail" id="selected_customer_detail"></div>
                </div>
                <button type="button" class="clear-selection" onclick="clearCustomerSelection()">Clear</button>
            </div>
        </div>

        <div class="form-group">
            <label>PO Date</label>
            <input type="date" name="po_date" value="<?= htmlspecialchars($po['po_date'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Link to Proforma Invoice (Mandatory) *</label>
            <select name="linked_quote_id" id="pi_select" required>
                <option value="">-- Select a Proforma Invoice --</option>
                <?php foreach ($pis as $pi): ?>
                    <option value="<?= $pi['id'] ?>"
                            data-customer-id="<?= htmlspecialchars($pi['customer_id']) ?>"
                            <?= $pi['id'] == $po['linked_quote_id'] ? 'selected' : '' ?>
                            style="<?= $pi['customer_id'] === $po['customer_id'] || $pi['id'] == $po['linked_quote_id'] ? '' : 'display: none;' ?>">
                        <?= htmlspecialchars($pi['pi_no']) ?> (Quote: <?= htmlspecialchars($pi['quote_no']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #666;">Select a Proforma Invoice - Linking is mandatory. Only available PIs (not linked to other POs) are shown.</small>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active" <?= $po['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $po['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $po['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <div class="form-group">
            <label>Attachment (PDF/Image)</label>
            <?php if ($po['attachment_path']): ?>
                <p><a href="../<?= htmlspecialchars($po['attachment_path']) ?>" target="_blank">Current attachment</a></p>
            <?php endif; ?>
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
            <small style="color: #666;">Upload new file to replace existing. Allowed: PDF, JPG, PNG</small>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes"><?= htmlspecialchars($po['notes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">Update Customer PO</button>
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
// Customer data from PHP
const allCustomers = <?= $customersJson ?>;
const currentCustomer = <?= $currentCustomerJson ?>;

// Initialize with current customer if exists
document.addEventListener('DOMContentLoaded', function() {
    if (currentCustomer) {
        selectCustomer(
            currentCustomer.customer_id,
            currentCustomer.company_name,
            currentCustomer.customer_name || '',
            currentCustomer.city || '',
            currentCustomer.contact || ''
        );
    }
});

function searchCustomers(input) {
    if (input.readOnly) return;

    const query = input.value.trim().toLowerCase();
    const resultsDiv = document.getElementById('customer_results');

    if (!query) {
        showAllCustomers();
        return;
    }

    // Search by company_name (dealer) or customer_name (owner)
    const results = allCustomers.filter(customer => {
        const dealerMatch = customer.company_name && customer.company_name.toLowerCase().includes(query);
        const ownerMatch = customer.customer_name && customer.customer_name.toLowerCase().includes(query);
        const cityMatch = customer.city && customer.city.toLowerCase().includes(query);
        const phoneMatch = customer.contact && customer.contact.includes(query);
        return dealerMatch || ownerMatch || cityMatch || phoneMatch;
    });

    if (results.length === 0) {
        resultsDiv.innerHTML = '<div class="no-results">No customers found</div>';
    } else {
        resultsDiv.innerHTML = results.map(customer => `
            <div class="search-result-item" onclick="selectCustomer('${customer.customer_id}', '${escapeHtml(customer.company_name)}', '${escapeHtml(customer.customer_name || '')}', '${escapeHtml(customer.city || '')}', '${escapeHtml(customer.contact || '')}')">
                <div class="customer-name">${escapeHtml(customer.company_name)}</div>
                <div class="customer-details">
                    Owner: ${escapeHtml(customer.customer_name || 'N/A')}
                    ${customer.city ? ' | ' + escapeHtml(customer.city) : ''}
                    ${customer.contact ? ' | ' + escapeHtml(customer.contact) : ''}
                </div>
            </div>
        `).join('');
    }

    resultsDiv.classList.add('active');
}

function showAllCustomers() {
    const resultsDiv = document.getElementById('customer_results');

    if (allCustomers.length === 0) {
        resultsDiv.innerHTML = '<div class="no-results">No customers available</div>';
    } else {
        resultsDiv.innerHTML = allCustomers.slice(0, 20).map(customer => `
            <div class="search-result-item" onclick="selectCustomer('${customer.customer_id}', '${escapeHtml(customer.company_name)}', '${escapeHtml(customer.customer_name || '')}', '${escapeHtml(customer.city || '')}', '${escapeHtml(customer.contact || '')}')">
                <div class="customer-name">${escapeHtml(customer.company_name)}</div>
                <div class="customer-details">
                    Owner: ${escapeHtml(customer.customer_name || 'N/A')}
                    ${customer.city ? ' | ' + escapeHtml(customer.city) : ''}
                </div>
            </div>
        `).join('');

        if (allCustomers.length > 20) {
            resultsDiv.innerHTML += '<div class="no-results">Type to search more...</div>';
        }
    }

    resultsDiv.classList.add('active');
}

function showCustomerResults() {
    const input = document.getElementById('customer_search');
    if (input.readOnly) return;

    if (input.value.trim()) {
        searchCustomers(input);
    } else {
        showAllCustomers();
    }
}

function selectCustomer(customerId, companyName, ownerName, city, contact) {
    const hiddenInput = document.getElementById('customer_id');
    const searchInput = document.getElementById('customer_search');
    const resultsDiv = document.getElementById('customer_results');
    const selectedDiv = document.getElementById('selected_customer');
    const selectedName = document.getElementById('selected_customer_name');
    const selectedDetail = document.getElementById('selected_customer_detail');

    // Set values
    hiddenInput.value = customerId;
    searchInput.value = companyName;
    searchInput.readOnly = true;
    searchInput.style.background = '#e8f5e9';

    // Show selected customer info
    selectedName.textContent = companyName;
    let detailText = 'Owner: ' + (ownerName || 'N/A');
    if (city) detailText += ' | ' + city;
    if (contact) detailText += ' | ' + contact;
    selectedDetail.textContent = detailText;
    selectedDiv.classList.add('active');

    // Hide results
    resultsDiv.classList.remove('active');

    // Filter PIs for this customer
    filterPIsForCustomer(customerId);
}

function clearCustomerSelection() {
    const hiddenInput = document.getElementById('customer_id');
    const searchInput = document.getElementById('customer_search');
    const selectedDiv = document.getElementById('selected_customer');
    const piSelect = document.getElementById('pi_select');

    // Clear values
    hiddenInput.value = '';
    searchInput.value = '';
    searchInput.readOnly = false;
    searchInput.style.background = '';
    selectedDiv.classList.remove('active');

    // Reset PI dropdown
    piSelect.value = '';
    const piOptions = piSelect.querySelectorAll('option[data-customer-id]');
    piOptions.forEach(option => {
        option.style.display = 'none';
    });

    searchInput.focus();
}

function filterPIsForCustomer(customerId) {
    const piSelect = document.getElementById('pi_select');
    const piOptions = piSelect.querySelectorAll('option');
    const currentValue = piSelect.value;

    piOptions.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else if (option.getAttribute('data-customer-id') === customerId) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
            // Deselect if current selection doesn't match new customer
            if (option.value === currentValue) {
                piSelect.value = '';
            }
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/'/g, "\\'");
}

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container')) {
        document.getElementById('customer_results').classList.remove('active');
    }
});

// Form validation
document.getElementById('poForm').addEventListener('submit', function(e) {
    const customerId = document.getElementById('customer_id').value;
    if (!customerId) {
        e.preventDefault();
        alert('Please select a customer');
        return false;
    }
});
</script>

</body>
</html>
