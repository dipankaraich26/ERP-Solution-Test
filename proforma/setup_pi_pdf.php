<?php
include "../db.php";

try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM quote_master LIKE 'pi_pdf_file'")->fetch();

    if (!$check) {
        // Add pi_pdf_file column to quote_master
        $pdo->exec("ALTER TABLE quote_master ADD COLUMN pi_pdf_file VARCHAR(500) NULL AFTER pi_attachment");
        echo "✓ Successfully added pi_pdf_file column to quote_master table<br>";
    } else {
        echo "✓ pi_pdf_file column already exists in quote_master table<br>";
    }

    // Create upload directory
    $uploadDir = '../uploads/proforma_pdf';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "✓ Created upload directory: $uploadDir<br>";
    } else {
        echo "✓ Upload directory already exists: $uploadDir<br>";
    }

    echo "<br><strong>Setup completed successfully!</strong><br>";
    echo "You can now upload Proforma Invoice PDFs in the view page.<br>";
    echo "<a href='index.php'>Back to Proforma Invoices</a>";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
