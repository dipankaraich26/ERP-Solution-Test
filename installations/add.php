<?php
include "../db.php";
include "../includes/dialog.php";

$error = '';
$success = false;

// Fetch customers
$customers = $pdo->query("
    SELECT id, customer_id, company_name, customer_name, contact, email, address1, address2, city, state, pincode
    FROM customers
    WHERE status = 'active'
    ORDER BY company_name, customer_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch internal engineers (employees)
$engineers = $pdo->query("
    SELECT id, emp_id, first_name, last_name, phone, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $installation_date = $_POST['installation_date'] ?? '';
    $installation_time = $_POST['installation_time'] ?? null;
    $engineer_type = $_POST['engineer_type'] ?? 'internal';
    $engineer_id = $engineer_type === 'internal' ? (int)($_POST['engineer_id'] ?? 0) : null;
    $external_engineer_name = $engineer_type === 'external' ? trim($_POST['external_engineer_name'] ?? '') : null;
    $external_engineer_phone = $engineer_type === 'external' ? trim($_POST['external_engineer_phone'] ?? '') : null;
    $external_engineer_company = $engineer_type === 'external' ? trim($_POST['external_engineer_company'] ?? '') : null;
    $site_address = trim($_POST['site_address'] ?? '');
    $site_contact_person = trim($_POST['site_contact_person'] ?? '');
    $site_contact_phone = trim($_POST['site_contact_phone'] ?? '');
    $installation_notes = trim($_POST['installation_notes'] ?? '');
    $status = $_POST['status'] ?? 'scheduled';

    // Get selected products
    $selected_products = $_POST['products'] ?? [];
    $product_quantities = $_POST['product_qty'] ?? [];

    // Validation
    if (!$customer_id) {
        $error = "Please select a customer";
    } elseif (!$installation_date) {
        $error = "Installation date is required";
    } elseif ($engineer_type === 'internal' && !$engineer_id) {
        $error = "Please select an engineer";
    } elseif ($engineer_type === 'external' && !$external_engineer_name) {
        $error = "External engineer name is required";
    } elseif ($invoice_id && empty($selected_products)) {
        $error = "Please select at least one product to install";
    } else {
        try {
            // Add invoice_id column if it doesn't exist (must be outside transaction - DDL causes implicit commit)
            try {
                $pdo->exec("ALTER TABLE installations ADD COLUMN invoice_id INT NULL AFTER customer_id");
            } catch (Exception $e) {
                // Column might already exist
            }

            // Generate installation number
            $maxNo = $pdo->query("
                SELECT COALESCE(MAX(CAST(SUBSTRING(installation_no, 5) AS UNSIGNED)), 0)
                FROM installations WHERE installation_no LIKE 'INS-%'
            ")->fetchColumn();
            $installation_no = 'INS-' . str_pad(((int)$maxNo + 1), 4, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();

            // Insert installation
            $stmt = $pdo->prepare("
                INSERT INTO installations (
                    installation_no, customer_id, invoice_id, installation_date, installation_time,
                    engineer_type, engineer_id, external_engineer_name, external_engineer_phone, external_engineer_company,
                    site_address, site_contact_person, site_contact_phone, installation_notes, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $installation_no,
                $customer_id,
                $invoice_id ?: null,
                $installation_date,
                $installation_time ?: null,
                $engineer_type,
                $engineer_id,
                $external_engineer_name,
                $external_engineer_phone,
                $external_engineer_company,
                $site_address,
                $site_contact_person,
                $site_contact_phone,
                $installation_notes,
                $status
            ]);

            $installation_id = $pdo->lastInsertId();

            // Insert selected products
            if (!empty($selected_products)) {
                $productStmt = $pdo->prepare("
                    INSERT INTO installation_products
                    (installation_id, part_no, product_name, quantity, warranty_months)
                    VALUES (?, ?, ?, ?, 12)
                ");

                foreach ($selected_products as $productKey) {
                    // productKey format: "part_no|part_name"
                    $parts = explode('|', $productKey);
                    $part_no = $parts[0] ?? '';
                    $part_name = $parts[1] ?? '';
                    $qty = isset($product_quantities[$productKey]) ? (int)$product_quantities[$productKey] : 1;

                    if ($qty > 0) {
                        $productStmt->execute([
                            $installation_id,
                            $part_no,
                            $part_name,
                            $qty
                        ]);
                    }
                }
            }

            // Handle file uploads
            if (!empty($_FILES['reports']['name'][0])) {
                $uploadDir = '../uploads/installations/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileCount = count($_FILES['reports']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['reports']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['reports']['tmp_name'][$i];
                        $fileName = $_FILES['reports']['name'][$i];
                        $fileSize = $_FILES['reports']['size'][$i];
                        $fileType = $_FILES['reports']['type'][$i];

                        // Generate unique filename
                        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                        $newFileName = $installation_no . '_' . time() . '_' . $i . '.' . $ext;
                        $filePath = $uploadDir . $newFileName;

                        if (move_uploaded_file($tmpName, $filePath)) {
                            $attachStmt = $pdo->prepare("
                                INSERT INTO installation_attachments
                                (installation_id, file_name, file_path, file_type, file_size, attachment_type, uploaded_by)
                                VALUES (?, ?, ?, ?, ?, 'report', 1)
                            ");
                            $attachStmt->execute([
                                $installation_id,
                                $fileName,
                                'uploads/installations/' . $newFileName,
                                $fileType,
                                $fileSize
                            ]);
                        }
                    }
                }
            }

            $pdo->commit();
            setModal("Success", "Installation $installation_no created successfully");
            header("Location: view.php?id=$installation_id");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>New Installation - Sales</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .form-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .engineer-type-toggle {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .engineer-type-toggle label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        .external-fields, .internal-fields {
            display: none;
        }
        .external-fields.active, .internal-fields.active {
            display: block;
        }
        .customer-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .customer-info.visible {
            display: block;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Invoice Selection Styles */
        .invoice-select-section {
            display: none;
            margin-top: 15px;
        }
        .invoice-select-section.visible {
            display: block;
        }
        .invoice-info {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .invoice-info.visible {
            display: block;
        }

        /* Product Selection Styles */
        .products-section {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .products-section.visible {
            display: block;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }
        .products-table th, .products-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .products-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .products-table tr:hover {
            background: #f5f5f5;
        }
        .products-table input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .products-table input[type="number"] {
            width: 80px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        .product-row.disabled {
            opacity: 0.5;
        }
        .product-row.disabled input[type="number"] {
            background: #eee;
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .no-invoices-msg {
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            color: #856404;
            margin-top: 10px;
        }
        .no-products-msg {
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>New Installation</h1>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="installationForm">
        <!-- Customer & Invoice Section -->
        <div class="form-section">
            <h3>Customer & Invoice Details</h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Select Customer *</label>
                    <select name="customer_id" id="customerSelect" required onchange="onCustomerChange()">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                    data-company="<?= htmlspecialchars($c['company_name']) ?>"
                                    data-name="<?= htmlspecialchars($c['customer_name']) ?>"
                                    data-phone="<?= htmlspecialchars($c['contact']) ?>"
                                    data-email="<?= htmlspecialchars($c['email']) ?>"
                                    data-address="<?= htmlspecialchars(trim($c['address1'] . ' ' . $c['address2'] . ', ' . $c['city'] . ', ' . $c['state'] . ' - ' . $c['pincode'])) ?>">
                                <?= htmlspecialchars($c['customer_id']) ?> - <?= htmlspecialchars($c['company_name'] ?: $c['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="customerInfo" class="customer-info">
                        <strong id="custCompany"></strong><br>
                        <span id="custName"></span><br>
                        <span id="custPhone"></span> | <span id="custEmail"></span><br>
                        <small id="custAddress"></small>
                    </div>
                </div>

                <!-- Invoice Selection (appears after customer is selected) -->
                <div class="form-group full-width invoice-select-section" id="invoiceSection">
                    <label>Select Invoice</label>
                    <select name="invoice_id" id="invoiceSelect" onchange="onInvoiceChange()">
                        <option value="">-- Select Invoice (Optional) --</option>
                    </select>
                    <div id="invoiceInfo" class="invoice-info">
                        <strong>Invoice:</strong> <span id="invNo"></span> |
                        <strong>Date:</strong> <span id="invDate"></span> |
                        <strong>Items:</strong> <span id="invItems"></span>
                    </div>
                    <div id="noInvoicesMsg" class="no-invoices-msg" style="display: none;">
                        No released invoices found for this customer. You can still create an installation manually.
                    </div>
                </div>
            </div>

            <!-- Products Selection (appears after invoice is selected) -->
            <div class="products-section" id="productsSection">
                <h4 style="margin-bottom: 15px; color: #2c3e50;">
                    Select Products to Install
                    <span id="loadingProducts" class="loading-spinner" style="display: none;"></span>
                </h4>
                <p style="margin-bottom: 15px; color: #666; font-size: 0.9em;">
                    Check the products you want to install and specify the number of machines/units for each.
                </p>
                <div id="productsContainer">
                    <div class="no-products-msg">Select an invoice to see available products</div>
                </div>
            </div>
        </div>

        <!-- Installation Details -->
        <div class="form-section">
            <h3>Installation Details</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Installation Date *</label>
                    <input type="date" name="installation_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Installation Time</label>
                    <input type="time" name="installation_time">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Engineer Assignment -->
        <div class="form-section">
            <h3>Engineer Assignment</h3>

            <div class="engineer-type-toggle">
                <label>
                    <input type="radio" name="engineer_type" value="internal" checked onchange="toggleEngineerType()">
                    Internal Employee
                </label>
                <label>
                    <input type="radio" name="engineer_type" value="external" onchange="toggleEngineerType()">
                    External Engineer
                </label>
            </div>

            <div id="internalFields" class="internal-fields active">
                <div class="form-group">
                    <label>Select Engineer *</label>
                    <select name="engineer_id" id="engineerSelect">
                        <option value="">-- Select Engineer --</option>
                        <?php foreach ($engineers as $eng): ?>
                            <option value="<?= $eng['id'] ?>">
                                <?= htmlspecialchars($eng['emp_id']) ?> - <?= htmlspecialchars($eng['first_name'] . ' ' . $eng['last_name']) ?>
                                <?php if ($eng['department']): ?>(<?= htmlspecialchars($eng['department']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="externalFields" class="external-fields">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Engineer Name *</label>
                        <input type="text" name="external_engineer_name" placeholder="Enter engineer name">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="external_engineer_phone" placeholder="Enter phone number">
                    </div>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="external_engineer_company" placeholder="Enter company name (if any)">
                    </div>
                </div>
            </div>
        </div>

        <!-- Site Details -->
        <div class="form-section">
            <h3>Site Details</h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Site Address</label>
                    <textarea name="site_address" id="siteAddress" placeholder="Enter installation site address (if different from customer address)"></textarea>
                </div>
                <div class="form-group">
                    <label>Site Contact Person</label>
                    <input type="text" name="site_contact_person" placeholder="Person to contact at site">
                </div>
                <div class="form-group">
                    <label>Site Contact Phone</label>
                    <input type="text" name="site_contact_phone" placeholder="Contact phone number">
                </div>
            </div>
        </div>

        <!-- Notes & Attachments -->
        <div class="form-section">
            <h3>Notes & Attachments</h3>
            <div class="form-group">
                <label>Installation Notes</label>
                <textarea name="installation_notes" placeholder="Any special instructions or notes for the installation"></textarea>
            </div>
            <div class="form-group">
                <label>Attach Reports/Documents</label>
                <input type="file" name="reports[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <small style="color: #666;">You can upload multiple files (PDF, Word, Images)</small>
            </div>
        </div>

        <!-- Submit -->
        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-success">Create Installation</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Show customer info when selected and load invoices
function onCustomerChange() {
    const select = document.getElementById('customerSelect');
    const infoDiv = document.getElementById('customerInfo');
    const invoiceSection = document.getElementById('invoiceSection');
    const productsSection = document.getElementById('productsSection');
    const option = select.options[select.selectedIndex];

    if (option.value) {
        // Show customer info
        document.getElementById('custCompany').textContent = option.dataset.company || '';
        document.getElementById('custName').textContent = option.dataset.name || '';
        document.getElementById('custPhone').textContent = option.dataset.phone || '';
        document.getElementById('custEmail').textContent = option.dataset.email || '';
        document.getElementById('custAddress').textContent = option.dataset.address || '';
        infoDiv.classList.add('visible');

        // Auto-fill site address from customer address
        const siteAddress = document.getElementById('siteAddress');
        if (!siteAddress.value) {
            siteAddress.value = option.dataset.address || '';
        }

        // Load invoices for this customer
        loadCustomerInvoices(option.value);
        invoiceSection.classList.add('visible');
    } else {
        infoDiv.classList.remove('visible');
        invoiceSection.classList.remove('visible');
        productsSection.classList.remove('visible');
    }
}

// Load invoices for selected customer
function loadCustomerInvoices(customerId) {
    const invoiceSelect = document.getElementById('invoiceSelect');
    const noInvoicesMsg = document.getElementById('noInvoicesMsg');
    const invoiceInfo = document.getElementById('invoiceInfo');
    const productsSection = document.getElementById('productsSection');

    invoiceSelect.innerHTML = '<option value="">Loading...</option>';
    invoiceSelect.disabled = true;
    noInvoicesMsg.style.display = 'none';
    invoiceInfo.classList.remove('visible');
    productsSection.classList.remove('visible');

    fetch(`/api/get_customer_invoices.php?customer_id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            invoiceSelect.disabled = false;

            if (data.success && data.invoices.length > 0) {
                invoiceSelect.innerHTML = '<option value="">-- Select Invoice (Optional) --</option>';
                data.invoices.forEach(inv => {
                    const option = document.createElement('option');
                    option.value = inv.invoice_id;
                    option.dataset.invoiceNo = inv.invoice_no;
                    option.dataset.invoiceDate = inv.invoice_date;
                    option.dataset.soNo = inv.so_no || '';
                    option.dataset.itemCount = inv.item_count || 0;
                    option.dataset.totalValue = inv.total_value ? parseFloat(inv.total_value).toLocaleString('en-IN') : '0';
                    option.textContent = `${inv.invoice_no} (${inv.invoice_date}) - ${inv.item_count} items`;
                    invoiceSelect.appendChild(option);
                });
                noInvoicesMsg.style.display = 'none';
            } else {
                invoiceSelect.innerHTML = '<option value="">-- No Invoices Available --</option>';
                noInvoicesMsg.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading invoices:', error);
            invoiceSelect.innerHTML = '<option value="">-- Error Loading Invoices --</option>';
            invoiceSelect.disabled = false;
        });
}

// When invoice is selected
function onInvoiceChange() {
    const select = document.getElementById('invoiceSelect');
    const invoiceInfo = document.getElementById('invoiceInfo');
    const productsSection = document.getElementById('productsSection');
    const option = select.options[select.selectedIndex];

    if (option.value) {
        // Show invoice info
        document.getElementById('invNo').textContent = option.dataset.invoiceNo;
        document.getElementById('invDate').textContent = option.dataset.invoiceDate;
        document.getElementById('invItems').textContent = option.dataset.itemCount + ' products';
        invoiceInfo.classList.add('visible');

        // Load products for this invoice
        loadInvoiceProducts(option.value);
        productsSection.classList.add('visible');
    } else {
        invoiceInfo.classList.remove('visible');
        productsSection.classList.remove('visible');
    }
}

// Load products for selected invoice
function loadInvoiceProducts(invoiceId) {
    const container = document.getElementById('productsContainer');
    const loading = document.getElementById('loadingProducts');

    loading.style.display = 'inline-block';
    container.innerHTML = '<div class="no-products-msg">Loading products...</div>';

    fetch(`/api/get_invoice_products.php?invoice_id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';

            if (data.success && data.products.length > 0) {
                let html = `
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Install</th>
                                <th>Part No</th>
                                <th>Product Name</th>
                                <th style="width: 100px;">Invoice Qty</th>
                                <th style="width: 150px;">No. of Machines</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                data.products.forEach((product, index) => {
                    const productKey = `${product.part_no}|${product.part_name}`;
                    const escapedKey = escapeHtml(productKey);
                    html += `
                        <tr class="product-row disabled" id="row_${index}">
                            <td>
                                <input type="checkbox" name="products[]" value="${escapedKey}"
                                       id="product_${index}" onchange="toggleProductRow(${index})">
                            </td>
                            <td><strong>${escapeHtml(product.part_no)}</strong></td>
                            <td>${escapeHtml(product.part_name)}</td>
                            <td style="text-align: center;">${product.qty}</td>
                            <td>
                                <input type="number" name="product_qty[${escapedKey}]"
                                       id="qty_${index}" min="1" max="${Math.floor(product.qty)}" value="1"
                                       disabled>
                            </td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <button type="button" class="btn btn-sm btn-primary" onclick="selectAllProducts()">Select All</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAllProducts()">Deselect All</button>
                    </div>
                `;

                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="no-products-msg">No products found in this invoice</div>';
            }
        })
        .catch(error => {
            loading.style.display = 'none';
            console.error('Error loading products:', error);
            container.innerHTML = '<div class="no-products-msg" style="color: #dc3545;">Error loading products</div>';
        });
}

// Toggle product row enabled/disabled
function toggleProductRow(index) {
    const checkbox = document.getElementById(`product_${index}`);
    const qtyInput = document.getElementById(`qty_${index}`);
    const row = document.getElementById(`row_${index}`);

    if (checkbox.checked) {
        qtyInput.disabled = false;
        row.classList.remove('disabled');
    } else {
        qtyInput.disabled = true;
        row.classList.add('disabled');
    }
}

// Select all products
function selectAllProducts() {
    document.querySelectorAll('.product-row input[type="checkbox"]').forEach((cb, index) => {
        cb.checked = true;
        toggleProductRow(index);
    });
}

// Deselect all products
function deselectAllProducts() {
    document.querySelectorAll('.product-row input[type="checkbox"]').forEach((cb, index) => {
        cb.checked = false;
        toggleProductRow(index);
    });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Engineer type toggle
function toggleEngineerType() {
    const type = document.querySelector('input[name="engineer_type"]:checked').value;
    const internalDiv = document.getElementById('internalFields');
    const externalDiv = document.getElementById('externalFields');
    const engineerSelect = document.getElementById('engineerSelect');

    if (type === 'internal') {
        internalDiv.classList.add('active');
        externalDiv.classList.remove('active');
        engineerSelect.required = true;
    } else {
        internalDiv.classList.remove('active');
        externalDiv.classList.add('active');
        engineerSelect.required = false;
    }
}

// Form validation before submit
document.getElementById('installationForm').addEventListener('submit', function(e) {
    const invoiceSelect = document.getElementById('invoiceSelect');
    const selectedProducts = document.querySelectorAll('input[name="products[]"]:checked');

    // If an invoice is selected, at least one product should be selected
    if (invoiceSelect.value && selectedProducts.length === 0) {
        e.preventDefault();
        alert('Please select at least one product to install from the invoice.');
        return false;
    }
});
</script>

</body>
</html>
