<?php
/**
 * Setup script for customer documents table
 */
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';

$messages = [];
$errors = [];

// Create customer_documents table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customer_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id VARCHAR(50) NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT DEFAULT 0,
            uploaded_by INT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_id (customer_id),
            FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'customer_documents' created or already exists.";
} catch (PDOException $e) {
    $errors[] = "Failed to create customer_documents table: " . $e->getMessage();
}

// Create uploads directory for customer documents
$uploadDir = dirname(__DIR__) . '/uploads/customer_documents';
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        $messages[] = "Upload directory created: /uploads/customer_documents";
    } else {
        $errors[] = "Failed to create upload directory: /uploads/customer_documents";
    }
} else {
    $messages[] = "Upload directory already exists: /uploads/customer_documents";
}

// Create .htaccess to prevent direct PHP execution in uploads folder
$htaccessPath = $uploadDir . '/.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessContent = "# Prevent PHP execution\nphp_flag engine off\n\n# Deny access to sensitive files\n<FilesMatch \"\\.(php|php5|phtml)$\">\n    Require all denied\n</FilesMatch>";
    file_put_contents($htaccessPath, $htaccessContent);
    $messages[] = "Security .htaccess file created in uploads directory.";
}
?>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Setup Customer Documents</h1>

    <p style="margin-bottom: 20px;">
        <a href="settings.php" class="btn btn-secondary">Back to Admin</a>
    </p>

    <?php if (!empty($errors)): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Errors:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Setup Complete:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3>Customer Documents Feature</h3>
        <p>This feature allows you to attach documents to customer records, such as:</p>
        <ul>
            <li>Dealer Agreements</li>
            <li>GST Certificates</li>
            <li>PAN Card</li>
            <li>Trade License</li>
            <li>Other Business Documents</li>
        </ul>

        <h4 style="margin-top: 20px;">How to Use:</h4>
        <ol>
            <li>Go to Customers → Edit Customer</li>
            <li>Scroll down to the "Documents & Attachments" section</li>
            <li>Select document type, upload file, and add remarks if needed</li>
            <li>Click "Upload Document" to save</li>
        </ol>

        <p style="margin-top: 20px;">
            <a href="/customers/index.php" class="btn btn-primary">Go to Customers</a>
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
