<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

// Fetch all customers
$customers = $pdo->query("
    SELECT id, customer_id, customer_name, company_name, contact, 'customer' as source
    FROM customers
    WHERE contact IS NOT NULL AND contact != ''
    ORDER BY customer_name
")->fetchAll();

// Fetch all CRM leads
$leads = $pdo->query("
    SELECT id, lead_no, contact_person, company_name, phone as contact, 'lead' as source
    FROM crm_leads
    WHERE phone IS NOT NULL AND phone != ''
    ORDER BY contact_person
")->fetchAll();

// Handle message log saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_message') {
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_log (recipient_type, recipient_id, recipient_name, phone, message, attachment_name, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_POST['recipient_type'],
        $_POST['recipient_id'],
        $_POST['recipient_name'],
        $_POST['phone'],
        $_POST['message'],
        $_POST['attachment_name'] ?? null
    ]);

    echo json_encode(['success' => true]);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Marketing</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .whatsapp-container {
            max-width: 800px;
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
            border-bottom: 2px solid #25D366;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 15px;
        }
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
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-group small {
            color: #666;
            font-size: 0.85em;
        }

        .source-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .source-tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
        }
        .source-tab.active {
            border-color: #25D366;
            background: #e8f5e9;
            font-weight: bold;
        }
        .source-tab:hover {
            border-color: #25D366;
        }

        .contact-info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .contact-info.visible {
            display: block;
        }
        .contact-info strong {
            color: #25D366;
        }

        .whatsapp-btn {
            background: #25D366;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .whatsapp-btn:hover {
            background: #128C7E;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }
        .whatsapp-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .file-preview {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .file-preview.visible {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .file-preview .remove-file {
            color: #e74c3c;
            cursor: pointer;
            font-weight: bold;
        }

        .instructions {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .instructions h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        .instructions ol {
            margin: 0;
            padding-left: 20px;
        }
        .instructions li {
            margin-bottom: 5px;
            color: #856404;
        }

        .templates-section {
            margin-top: 20px;
        }
        .template-btn {
            padding: 8px 15px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 0.9em;
        }
        .template-btn:hover {
            background: #f5f5f5;
            border-color: #25D366;
        }

        .wa-icon {
            width: 24px;
            height: 24px;
        }

        .file-link {
            background: #e8f5e9;
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .file-link.visible {
            display: block;
        }
        .file-link a {
            color: #25D366;
            text-decoration: none;
            font-weight: bold;
            word-break: break-all;
        }
        .file-link a:hover {
            text-decoration: underline;
        }
        .copy-link {
            padding: 5px 10px;
            background: white;
            border: 1px solid #25D366;
            border-radius: 4px;
            color: #25D366;
            cursor: pointer;
            font-size: 0.85em;
            margin-left: 10px;
        }
        .copy-link:hover {
            background: #e8f5e9;
        }
        .upload-status {
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .upload-status.visible {
            display: block;
        }
        .upload-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .upload-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal.visible {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            margin: 0;
            color: #25D366;
        }
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        .close-modal:hover {
            color: #333;
        }
        .template-list {
            list-style: none;
            padding: 0;
        }
        .template-item {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .template-item-content {
            flex: 1;
        }
        .template-item-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .template-item-text {
            color: #666;
            font-size: 0.9em;
            white-space: pre-wrap;
            max-height: 60px;
            overflow: hidden;
        }
        .template-item-actions {
            display: flex;
            gap: 5px;
        }
        .template-item-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
        }
        .use-template-btn {
            background: #25D366;
            color: white;
        }
        .use-template-btn:hover {
            background: #128C7E;
        }
        .delete-template-btn {
            background: #e74c3c;
            color: white;
        }
        .delete-template-btn:hover {
            background: #c0392b;
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
        }
        .catalog-card {
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .catalog-card:hover {
            border-color: #25D366;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.2);
            transform: translateY(-2px);
        }
        .catalog-card.selected {
            border-color: #25D366;
            background: #e8f5e9;
        }
        .catalog-card-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .catalog-card-code {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }
        .catalog-card-model {
            font-size: 0.8em;
            color: #999;
        }
        .catalog-attachment-btn {
            background: #25D366;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.2s;
        }
        .catalog-attachment-btn:hover {
            background: #128C7E;
        }
        .catalog-attachment-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .catalog-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ddd;
        }
    </style>
</head>

<body>

<div class="content">
    <div class="whatsapp-container">
        <h1 style="color: #25D366;">WhatsApp Marketing</h1>

        <div class="instructions">
            <h4>How to Use:</h4>
            <ol>
                <li>Make sure <strong>WhatsApp Desktop</strong> or <strong>WhatsApp Web</strong> is open and logged in</li>
                <li>Select a customer or lead from the list below</li>
                <li>Write your message (you can use templates)</li>
                <li>Optionally attach a file - it will be automatically uploaded and a shareable link will be included in the message</li>
                <li>Click "Send via WhatsApp" - it will open WhatsApp with the message ready (including the file link)</li>
                <li>Press Enter in WhatsApp to send</li>
            </ol>
        </div>

        <form id="whatsappForm">
            <!-- Select Source -->
            <div class="form-section">
                <h3>Select Recipient</h3>

                <div class="source-tabs">
                    <div class="source-tab active" data-source="customer" onclick="switchSource('customer')">
                        Customers
                    </div>
                    <div class="source-tab" data-source="lead" onclick="switchSource('lead')">
                        CRM Leads
                    </div>
                </div>

                <div class="form-group" id="customerSelect">
                    <label>Select Customer</label>
                    <select id="customer_dropdown" onchange="selectRecipient('customer')">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                    data-name="<?= htmlspecialchars($c['customer_name']) ?>"
                                    data-company="<?= htmlspecialchars($c['company_name'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($c['contact']) ?>"
                                    data-id="<?= htmlspecialchars($c['customer_id']) ?>">
                                <?= htmlspecialchars($c['customer_name']) ?>
                                <?= $c['company_name'] ? ' (' . htmlspecialchars($c['company_name']) . ')' : '' ?>
                                - <?= htmlspecialchars($c['contact']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="leadSelect" style="display: none;">
                    <label>Select CRM Lead</label>
                    <select id="lead_dropdown" onchange="selectRecipient('lead')">
                        <option value="">-- Select Lead --</option>
                        <?php foreach ($leads as $l): ?>
                            <option value="<?= $l['id'] ?>"
                                    data-name="<?= htmlspecialchars($l['contact_person']) ?>"
                                    data-company="<?= htmlspecialchars($l['company_name'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($l['contact']) ?>"
                                    data-id="<?= htmlspecialchars($l['lead_no']) ?>">
                                <?= htmlspecialchars($l['contact_person']) ?>
                                <?= $l['company_name'] ? ' (' . htmlspecialchars($l['company_name']) . ')' : '' ?>
                                - <?= htmlspecialchars($l['contact']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="contact-info" id="contactInfo">
                    <strong>Selected:</strong> <span id="selectedName">-</span><br>
                    <strong>Company:</strong> <span id="selectedCompany">-</span><br>
                    <strong>Phone:</strong> <span id="selectedPhone">-</span>
                </div>
            </div>

            <!-- Message -->
            <div class="form-section">
                <h3>Message</h3>

                <div class="form-group">
                    <label>Your Message</label>
                    <textarea id="message" placeholder="Type your message here..."></textarea>
                    <small>Tip: Use {name} to insert recipient's name</small>
                </div>

                <div class="templates-section">
                    <label>Quick Templates:</label><br>
                    <button type="button" class="template-btn" onclick="useTemplate('greeting')">Greeting</button>
                    <button type="button" class="template-btn" onclick="useTemplate('followup')">Follow-up</button>
                    <button type="button" class="template-btn" onclick="useTemplate('offer')">Special Offer</button>
                    <button type="button" class="template-btn" onclick="useTemplate('meeting')">Meeting Request</button>
                    <div id="customTemplates" style="display: inline;"></div>
                </div>

                <div style="margin-top: 15px;">
                    <button type="button" class="template-btn" style="background: #e8f5e9; border-color: #25D366;" onclick="saveCustomTemplate()">💾 Save Current as Template</button>
                    <button type="button" class="template-btn" style="background: #fff3cd; border-color: #ffc107;" onclick="manageTemplates()">⚙️ Manage Templates</button>
                </div>
            </div>

            <!-- Attachment -->
            <div class="form-section">
                <h3>Attachment (Optional)</h3>

                <div style="margin-bottom: 15px;">
                    <button type="button" class="template-btn" style="background: #3498db; border-color: #3498db; color: white;" onclick="openCatalogSelector()">📋 Select from Catalog</button>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span style="color: #666;">or</span>
                </div>

                <div class="form-group">
                    <label>Upload File</label>
                    <input type="file" id="attachment" onchange="previewFile()">
                    <small>Supported: Images, PDF, Word, Excel, Video, Audio (Max 10MB)</small>
                </div>

                <div id="uploadStatus" class="upload-status"></div>

                <div class="file-preview" id="filePreview">
                    <span id="fileName"></span>
                    <span class="remove-file" onclick="removeFile()">X Remove</span>
                </div>

                <div class="file-link" id="fileLink">
                    <strong>Shareable Link:</strong><br>
                    <a id="fileLinkUrl" href="#" target="_blank"></a>
                    <button type="button" class="copy-link" onclick="copyFileLink()">Copy Link</button>
                </div>
            </div>

            <!-- Send Button -->
            <div style="text-align: center; margin-top: 20px;">
                <button type="button" class="whatsapp-btn" id="sendBtn" onclick="sendWhatsApp()" disabled>
                    <svg class="wa-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    Send via WhatsApp
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Catalog Selector Modal -->
<div id="catalogModal" class="modal">
    <div class="modal-content">
        <div class="catalog-modal-header">
            <h3>Select Catalog to Attach</h3>
            <span class="close-modal" onclick="closeCatalogModal()">&times;</span>
        </div>
        <div id="catalogGrid" class="catalog-grid"></div>
        <div style="text-align: center; margin-top: 15px;">
            <button type="button" class="catalog-attachment-btn" id="attachCatalogBtn" onclick="attachSelectedCatalog()" disabled>
                Attach Selected Catalog
            </button>
        </div>
    </div>
</div>

<!-- Template Management Modal -->
<div id="templateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Manage Templates</h3>
            <span class="close-modal" onclick="closeTemplateModal()">&times;</span>
        </div>
        <div id="templateList"></div>
    </div>
</div>

<script>
let currentSource = 'customer';
let selectedRecipient = null;
let customTemplatesList = [];
let catalogsList = [];
let selectedCatalogId = null;

// Load custom templates and catalogs on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCustomTemplates();
    loadCatalogs();
});

const templates = {
    greeting: `Hello {name}!

Hope you're doing well. This is [Your Name] from [Company Name].

Just wanted to reach out and check in with you. Please let me know if there's anything I can help you with.

Best regards!`,

    followup: `Hi {name},

Following up on our previous conversation. I wanted to check if you had any questions or need any additional information.

Looking forward to hearing from you!

Thanks`,

    offer: `Dear {name},

We have an exciting special offer just for you!

[Offer Details Here]

This offer is valid until [Date]. Don't miss out!

Reply to know more.`,

    meeting: `Hi {name},

I hope this message finds you well. I would like to schedule a meeting to discuss [Topic].

Would you be available for a quick call this week? Please let me know your convenient time.

Thank you!`
};

function switchSource(source) {
    currentSource = source;

    document.querySelectorAll('.source-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelector(`.source-tab[data-source="${source}"]`).classList.add('active');

    if (source === 'customer') {
        document.getElementById('customerSelect').style.display = 'block';
        document.getElementById('leadSelect').style.display = 'none';
        document.getElementById('customer_dropdown').value = '';
    } else {
        document.getElementById('customerSelect').style.display = 'none';
        document.getElementById('leadSelect').style.display = 'block';
        document.getElementById('lead_dropdown').value = '';
    }

    // Reset selection
    selectedRecipient = null;
    document.getElementById('contactInfo').classList.remove('visible');
    document.getElementById('sendBtn').disabled = true;
}

function selectRecipient(source) {
    const dropdown = source === 'customer' ? document.getElementById('customer_dropdown') : document.getElementById('lead_dropdown');
    const selected = dropdown.options[dropdown.selectedIndex];

    if (!dropdown.value) {
        selectedRecipient = null;
        document.getElementById('contactInfo').classList.remove('visible');
        document.getElementById('sendBtn').disabled = true;
        return;
    }

    selectedRecipient = {
        type: source,
        id: dropdown.value,
        name: selected.dataset.name,
        company: selected.dataset.company,
        phone: selected.dataset.phone,
        refId: selected.dataset.id
    };

    document.getElementById('selectedName').textContent = selectedRecipient.name;
    document.getElementById('selectedCompany').textContent = selectedRecipient.company || '-';
    document.getElementById('selectedPhone').textContent = selectedRecipient.phone;
    document.getElementById('contactInfo').classList.add('visible');
    document.getElementById('sendBtn').disabled = false;
}

function useTemplate(type) {
    const template = templates[type];
    document.getElementById('message').value = template;
}

function previewFile() {
    const fileInput = document.getElementById('attachment');
    const preview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const uploadStatus = document.getElementById('uploadStatus');
    const fileLink = document.getElementById('fileLink');

    if (fileInput.files.length > 0) {
        fileName.textContent = fileInput.files[0].name;
        preview.classList.add('visible');
        uploadStatus.classList.remove('visible');
        fileLink.classList.remove('visible');
        uploadFile(fileInput.files[0]);
    } else {
        preview.classList.remove('visible');
        fileLink.classList.remove('visible');
        uploadStatus.classList.remove('visible');
    }
}

function removeFile() {
    document.getElementById('attachment').value = '';
    document.getElementById('filePreview').classList.remove('visible');
    document.getElementById('fileLink').classList.remove('visible');
    document.getElementById('uploadStatus').classList.remove('visible');
    window.uploadedFileUrl = null;
}

function uploadFile(file) {
    const uploadStatus = document.getElementById('uploadStatus');
    const fileLink = document.getElementById('fileLink');

    // Validate file size
    if (file.size > 10 * 1024 * 1024) {
        uploadStatus.textContent = 'Error: File too large. Maximum size is 10MB';
        uploadStatus.classList.add('visible', 'error');
        removeFile();
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    uploadStatus.textContent = 'Uploading...';
    uploadStatus.classList.add('visible', 'success');
    uploadStatus.classList.remove('error');

    fetch('/api/upload_whatsapp_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.uploadedFileUrl = data.url;
            uploadStatus.textContent = 'File uploaded successfully!';
            uploadStatus.classList.add('success');
            uploadStatus.classList.remove('error');

            // Show file link
            const fileLinkUrl = document.getElementById('fileLinkUrl');
            const fullUrl = window.location.origin + data.url;
            fileLinkUrl.href = fullUrl;
            fileLinkUrl.textContent = fullUrl;
            fileLink.classList.add('visible');
        } else {
            throw new Error(data.error || 'Upload failed');
        }
    })
    .catch(error => {
        uploadStatus.textContent = 'Error: ' + error.message;
        uploadStatus.classList.add('visible', 'error');
        uploadStatus.classList.remove('success');
        removeFile();
    });
}

function copyFileLink() {
    const fileLinkUrl = document.getElementById('fileLinkUrl');
    const url = fileLinkUrl.href;

    navigator.clipboard.writeText(url).then(() => {
        alert('Link copied to clipboard!');
    }).catch(err => {
        alert('Could not copy link: ' + err);
    });
}

function formatPhoneNumber(phone) {
    // Remove all non-numeric characters
    let cleaned = phone.replace(/\D/g, '');

    // If starts with 0, replace with 91 (India code)
    if (cleaned.startsWith('0')) {
        cleaned = '91' + cleaned.substring(1);
    }

    // If doesn't start with country code, add 91
    if (cleaned.length === 10) {
        cleaned = '91' + cleaned;
    }

    return cleaned;
}

function sendWhatsApp() {
    if (!selectedRecipient) {
        alert('Please select a recipient');
        return;
    }

    let message = document.getElementById('message').value;

    if (!message.trim()) {
        alert('Please enter a message');
        return;
    }

    // Replace {name} placeholder
    message = message.replace(/{name}/g, selectedRecipient.name);

    // Add file link to message if uploaded
    let attachmentName = '';
    if (window.uploadedFileUrl) {
        const fullUrl = window.location.origin + window.uploadedFileUrl;
        // Add file link on a new line with proper formatting for WhatsApp
        message += '\n\n' + fullUrl;
        const fileNameSpan = document.getElementById('fileName');
        attachmentName = fileNameSpan.textContent.split('/').pop();
    }

    // Format phone number
    const phone = formatPhoneNumber(selectedRecipient.phone);

    // Create WhatsApp URL
    const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;

    // Log the message
    fetch('whatsapp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'log_message',
            recipient_type: selectedRecipient.type,
            recipient_id: selectedRecipient.id,
            recipient_name: selectedRecipient.name,
            phone: selectedRecipient.phone,
            message: message,
            attachment_name: attachmentName
        })
    });

    // Open WhatsApp
    window.open(waUrl, '_blank');
}

// Load custom templates
function loadCustomTemplates() {
    fetch('/api/whatsapp_templates.php?action=list')
        .then(response => response.json())
        .then(data => {
            customTemplatesList = data;
            renderCustomTemplateButtons();
        })
        .catch(error => {
            console.error('Error loading templates:', error);
        });
}

// Render custom template buttons
function renderCustomTemplateButtons() {
    const container = document.getElementById('customTemplates');
    container.innerHTML = '';

    customTemplatesList.forEach(template => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'template-btn';
        btn.textContent = template.template_name;
        btn.onclick = () => useCustomTemplate(template.id);
        container.appendChild(btn);
    });
}

// Use custom template
function useCustomTemplate(templateId) {
    const template = customTemplatesList.find(t => t.id === templateId);
    if (template) {
        document.getElementById('message').value = template.template_content;
    }
}

// Save current message as template
function saveCustomTemplate() {
    const message = document.getElementById('message').value.trim();

    if (!message) {
        alert('Please enter a message first');
        return;
    }

    const templateName = prompt('Enter a name for this template:');
    if (!templateName) {
        return;
    }

    fetch('/api/whatsapp_templates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'save',
            name: templateName.trim(),
            content: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Template saved successfully!');
            loadCustomTemplates();
        } else {
            alert('Error: ' + (data.error || 'Failed to save template'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

// Load catalogs from database
function loadCatalogs() {
    fetch('/api/get_catalogs.php')
        .then(response => response.json())
        .then(data => {
            catalogsList = data;
        })
        .catch(error => {
            console.error('Error loading catalogs:', error);
        });
}

// Open catalog selector modal
function openCatalogSelector() {
    selectedCatalogId = null;
    document.getElementById('attachCatalogBtn').disabled = true;
    const modal = document.getElementById('catalogModal');
    modal.classList.add('visible');
    renderCatalogGrid();
}

// Close catalog modal
function closeCatalogModal() {
    const modal = document.getElementById('catalogModal');
    modal.classList.remove('visible');
}

// Render catalog grid
function renderCatalogGrid() {
    const grid = document.getElementById('catalogGrid');

    if (catalogsList.length === 0) {
        grid.innerHTML = '<p style="color: #999; text-align: center; grid-column: 1/-1;">No catalogs available</p>';
        return;
    }

    grid.innerHTML = '';
    catalogsList.forEach(catalog => {
        const card = document.createElement('div');
        card.className = 'catalog-card';
        card.onclick = () => selectCatalog(catalog.id, card);
        card.innerHTML = `
            <div class="catalog-card-name">${escapeHtml(catalog.catalog_name)}</div>
            <div class="catalog-card-code">Code: ${escapeHtml(catalog.catalog_code)}</div>
            <div class="catalog-card-model">Model: ${escapeHtml(catalog.model_no || 'N/A')}</div>
        `;
        grid.appendChild(card);
    });
}

// Select catalog card
function selectCatalog(catalogId, cardElement) {
    // Remove previous selection
    document.querySelectorAll('.catalog-card').forEach(card => {
        card.classList.remove('selected');
    });

    // Add selection to clicked card
    cardElement.classList.add('selected');
    selectedCatalogId = catalogId;
    document.getElementById('attachCatalogBtn').disabled = false;
}

// Attach selected catalog
function attachSelectedCatalog() {
    if (!selectedCatalogId) {
        alert('Please select a catalog');
        return;
    }

    const catalog = catalogsList.find(c => c.id === selectedCatalogId);
    if (!catalog || !catalog.brochure_path) {
        alert('Catalog file not found');
        return;
    }

    // Store the catalog file path
    window.uploadedFileUrl = '/' + catalog.brochure_path;

    // Update UI to show file is attached
    const fileName = document.getElementById('fileName');
    fileName.textContent = catalog.catalog_name + ' (' + catalog.catalog_code + ')';
    document.getElementById('filePreview').classList.add('visible');

    // Show upload status
    const uploadStatus = document.getElementById('uploadStatus');
    uploadStatus.textContent = 'Catalog attached: ' + catalog.catalog_name;
    uploadStatus.classList.add('visible', 'success');

    // Show file link
    const fileLinkUrl = document.getElementById('fileLinkUrl');
    const fullUrl = window.location.origin + '/' + catalog.brochure_path;
    fileLinkUrl.href = fullUrl;
    fileLinkUrl.textContent = fullUrl;
    document.getElementById('fileLink').classList.add('visible');

    // Close modal
    closeCatalogModal();
}

// Manage templates modal
function manageTemplates() {
    const modal = document.getElementById('templateModal');
    modal.classList.add('visible');
    renderTemplateList();
}

function closeTemplateModal() {
    const modal = document.getElementById('templateModal');
    modal.classList.remove('visible');
}

function renderTemplateList() {
    const container = document.getElementById('templateList');

    if (customTemplatesList.length === 0) {
        container.innerHTML = '<p style="color: #999; text-align: center;">No custom templates saved yet</p>';
        return;
    }

    let html = '<ul class="template-list">';
    customTemplatesList.forEach(template => {
        html += `
            <li class="template-item">
                <div class="template-item-content">
                    <div class="template-item-name">${escapeHtml(template.template_name)}</div>
                    <div class="template-item-text">${escapeHtml(template.template_content)}</div>
                </div>
                <div class="template-item-actions">
                    <button class="use-template-btn" onclick="useCustomTemplateAndClose(${template.id})">Use</button>
                    <button class="delete-template-btn" onclick="deleteTemplate(${template.id})">Delete</button>
                </div>
            </li>
        `;
    });
    html += '</ul>';
    container.innerHTML = html;
}

function useCustomTemplateAndClose(templateId) {
    useCustomTemplate(templateId);
    closeTemplateModal();
}

function deleteTemplate(templateId) {
    if (!confirm('Are you sure you want to delete this template?')) {
        return;
    }

    fetch('/api/whatsapp_templates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'delete',
            id: templateId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCustomTemplates();
            renderTemplateList();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete template'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('templateModal');
    if (event.target === modal) {
        closeTemplateModal();
    }
}
</script>

</body>
</html>
