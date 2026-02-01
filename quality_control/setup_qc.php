<?php
/**
 * Quality Control Module - Database Setup
 * Run this file once to create all necessary tables
 */

include "../db.php";

$messages = [];

try {
    // 1. QC Checklists - Master checklist templates
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_checklists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checklist_no VARCHAR(50) NOT NULL UNIQUE,
            checklist_name VARCHAR(255) NOT NULL,
            checklist_type ENUM('Incoming Inspection', 'In-Process', 'Final Inspection', 'Outgoing', 'Supplier Audit', 'Process Audit', 'Product Audit', 'Other') NOT NULL,
            applicable_to VARCHAR(255) DEFAULT NULL COMMENT 'Part number, process, or general',
            revision VARCHAR(20) DEFAULT 'Rev A',
            description TEXT,
            status ENUM('Active', 'Draft', 'Obsolete') DEFAULT 'Active',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_checklists";

    // 2. QC Checklist Items - Items within checklists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_checklist_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checklist_id INT NOT NULL,
            item_no INT NOT NULL,
            check_point VARCHAR(500) NOT NULL,
            specification VARCHAR(255) DEFAULT NULL,
            method VARCHAR(255) DEFAULT NULL COMMENT 'Measurement method or tool',
            acceptance_criteria VARCHAR(255) DEFAULT NULL,
            is_critical TINYINT(1) DEFAULT 0,
            category VARCHAR(100) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            FOREIGN KEY (checklist_id) REFERENCES qc_checklists(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_checklist_items";

    // 3. QC Checklist Records - Filled checklists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_checklist_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            record_no VARCHAR(50) NOT NULL UNIQUE,
            checklist_id INT NOT NULL,
            part_no VARCHAR(100) DEFAULT NULL,
            lot_no VARCHAR(100) DEFAULT NULL,
            po_no VARCHAR(100) DEFAULT NULL,
            supplier_id INT DEFAULT NULL,
            inspection_date DATE NOT NULL,
            inspector_name VARCHAR(100),
            shift VARCHAR(50) DEFAULT NULL,
            sample_size INT DEFAULT NULL,
            accepted_qty INT DEFAULT NULL,
            rejected_qty INT DEFAULT NULL,
            overall_result ENUM('Pass', 'Fail', 'Conditional', 'Pending') DEFAULT 'Pending',
            remarks TEXT,
            status ENUM('Draft', 'Completed', 'Approved', 'Rejected') DEFAULT 'Draft',
            approved_by INT DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_checklist_records";

    // 4. QC Checklist Record Items - Individual check results
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_checklist_record_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            record_id INT NOT NULL,
            checklist_item_id INT NOT NULL,
            measured_value VARCHAR(255) DEFAULT NULL,
            result ENUM('OK', 'NG', 'NA', 'Pending') DEFAULT 'Pending',
            remarks VARCHAR(500) DEFAULT NULL,
            FOREIGN KEY (record_id) REFERENCES qc_checklist_records(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_checklist_record_items";

    // 5. PPAP Submissions - Production Part Approval Process
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_ppap_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ppap_no VARCHAR(50) NOT NULL UNIQUE,
            part_no VARCHAR(100) NOT NULL,
            part_name VARCHAR(255),
            customer_name VARCHAR(255),
            submission_level ENUM('Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5') DEFAULT 'Level 3',
            submission_reason ENUM('Initial Submission', 'Engineering Change', 'Tooling Transfer', 'Correction of Discrepancy', 'Tooling Inactive', 'Sub-supplier Change', 'Material Change', 'Other') NOT NULL,
            submission_date DATE,
            required_date DATE,
            supplier_id INT DEFAULT NULL,
            project_id INT DEFAULT NULL,
            psw_status ENUM('Pending', 'Submitted', 'Approved', 'Rejected', 'Interim Approval') DEFAULT 'Pending',
            overall_status ENUM('Draft', 'In Progress', 'Submitted', 'Approved', 'Rejected', 'Interim') DEFAULT 'Draft',
            customer_decision VARCHAR(100) DEFAULT NULL,
            customer_signature VARCHAR(100) DEFAULT NULL,
            decision_date DATE DEFAULT NULL,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_ppap_submissions";

    // 6. PPAP Elements - 18 standard PPAP elements tracking
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_ppap_elements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ppap_id INT NOT NULL,
            element_no INT NOT NULL,
            element_name VARCHAR(255) NOT NULL,
            required TINYINT(1) DEFAULT 1,
            status ENUM('Not Started', 'In Progress', 'Completed', 'Not Applicable') DEFAULT 'Not Started',
            document_ref VARCHAR(255) DEFAULT NULL,
            completion_date DATE DEFAULT NULL,
            remarks TEXT,
            FOREIGN KEY (ppap_id) REFERENCES qc_ppap_submissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_ppap_elements";

    // 7. Part Submissions - Part approval requests
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_part_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            submission_no VARCHAR(50) NOT NULL UNIQUE,
            part_no VARCHAR(100) NOT NULL,
            part_name VARCHAR(255),
            revision VARCHAR(50) DEFAULT NULL,
            submission_type ENUM('New Part', 'Revision', 'Re-submission', 'Annual Validation') NOT NULL,
            supplier_id INT DEFAULT NULL,
            customer_id INT DEFAULT NULL,
            submission_date DATE NOT NULL,
            required_date DATE,
            sample_qty INT DEFAULT NULL,
            drawing_no VARCHAR(100) DEFAULT NULL,
            specification TEXT,
            test_results TEXT,
            status ENUM('Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Conditional') DEFAULT 'Draft',
            reviewer_id INT DEFAULT NULL,
            review_date DATE DEFAULT NULL,
            review_comments TEXT,
            approval_date DATE DEFAULT NULL,
            approved_by INT DEFAULT NULL,
            validity_date DATE DEFAULT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_part_submissions";

    // 8. Incoming Inspections
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_incoming_inspections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inspection_no VARCHAR(50) NOT NULL UNIQUE,
            grn_no VARCHAR(100) DEFAULT NULL COMMENT 'Goods Receipt Note',
            po_no VARCHAR(100) DEFAULT NULL,
            supplier_id INT DEFAULT NULL,
            inspection_date DATE NOT NULL,
            inspector_id INT DEFAULT NULL,
            inspection_type ENUM('Normal', 'Tightened', 'Reduced', 'Skip Lot') DEFAULT 'Normal',
            total_qty INT DEFAULT 0,
            sample_qty INT DEFAULT 0,
            accepted_qty INT DEFAULT 0,
            rejected_qty INT DEFAULT 0,
            inspection_result ENUM('Accept', 'Reject', 'Conditional Accept', 'Pending') DEFAULT 'Pending',
            disposition ENUM('Accept to Stock', 'Return to Supplier', 'Rework', 'Use As Is', 'Scrap', 'Pending') DEFAULT 'Pending',
            mrb_required TINYINT(1) DEFAULT 0 COMMENT 'Material Review Board',
            ncr_no VARCHAR(50) DEFAULT NULL,
            remarks TEXT,
            status ENUM('Draft', 'In Progress', 'Completed', 'Closed') DEFAULT 'Draft',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_incoming_inspections";

    // 9. Incoming Inspection Items
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_incoming_inspection_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inspection_id INT NOT NULL,
            part_no VARCHAR(100) NOT NULL,
            part_name VARCHAR(255),
            qty_received INT DEFAULT 0,
            qty_inspected INT DEFAULT 0,
            qty_accepted INT DEFAULT 0,
            qty_rejected INT DEFAULT 0,
            defect_type VARCHAR(255) DEFAULT NULL,
            defect_description TEXT,
            result ENUM('Accept', 'Reject', 'Conditional', 'Pending') DEFAULT 'Pending',
            FOREIGN KEY (inspection_id) REFERENCES qc_incoming_inspections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_incoming_inspection_items";

    // 10. Supplier Quality Ratings
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_supplier_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            rating_period VARCHAR(20) NOT NULL COMMENT 'YYYY-MM or YYYY-QN',
            quality_score DECIMAL(5,2) DEFAULT 0 COMMENT 'PPM or defect rate',
            delivery_score DECIMAL(5,2) DEFAULT 0,
            response_score DECIMAL(5,2) DEFAULT 0,
            documentation_score DECIMAL(5,2) DEFAULT 0,
            overall_score DECIMAL(5,2) DEFAULT 0,
            grade ENUM('A', 'B', 'C', 'D', 'F') DEFAULT NULL,
            total_lots_received INT DEFAULT 0,
            lots_accepted INT DEFAULT 0,
            lots_rejected INT DEFAULT 0,
            total_qty_received INT DEFAULT 0,
            qty_rejected INT DEFAULT 0,
            ppm DECIMAL(10,2) DEFAULT 0,
            ncr_count INT DEFAULT 0,
            on_time_delivery_pct DECIMAL(5,2) DEFAULT 0,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_supplier_period (supplier_id, rating_period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_supplier_ratings";

    // 11. Supplier NCRs (Non-Conformance Reports)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_supplier_ncrs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ncr_no VARCHAR(50) NOT NULL UNIQUE,
            supplier_id INT NOT NULL,
            part_no VARCHAR(100),
            po_no VARCHAR(100),
            lot_no VARCHAR(100),
            ncr_date DATE NOT NULL,
            defect_type ENUM('Dimensional', 'Visual', 'Functional', 'Material', 'Packaging', 'Documentation', 'Other') NOT NULL,
            severity ENUM('Critical', 'Major', 'Minor') DEFAULT 'Major',
            qty_affected INT DEFAULT 0,
            description TEXT NOT NULL,
            root_cause TEXT,
            containment_action TEXT,
            corrective_action TEXT,
            preventive_action TEXT,
            supplier_response TEXT,
            supplier_response_date DATE,
            verification_result TEXT,
            verification_date DATE,
            status ENUM('Open', 'Supplier Notified', 'Response Received', 'Verification Pending', 'Closed', 'Escalated') DEFAULT 'Open',
            closure_date DATE,
            cost_impact DECIMAL(12,2) DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_supplier_ncrs";

    // 12. Supplier Audits
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_supplier_audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_no VARCHAR(50) NOT NULL UNIQUE,
            supplier_id INT NOT NULL,
            audit_type ENUM('Initial', 'Periodic', 'Process', 'Product', 'Special', 'Re-audit') NOT NULL,
            audit_date DATE NOT NULL,
            audit_scope TEXT,
            lead_auditor VARCHAR(100),
            audit_team TEXT,
            checklist_used VARCHAR(255),
            total_checkpoints INT DEFAULT 0,
            conforming INT DEFAULT 0,
            minor_nc INT DEFAULT 0,
            major_nc INT DEFAULT 0,
            observations INT DEFAULT 0,
            audit_score DECIMAL(5,2) DEFAULT 0,
            audit_result ENUM('Approved', 'Conditional', 'Not Approved', 'Pending') DEFAULT 'Pending',
            findings_summary TEXT,
            recommendations TEXT,
            next_audit_date DATE,
            status ENUM('Planned', 'In Progress', 'Completed', 'Closed') DEFAULT 'Planned',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_supplier_audits";

    // 13. Supplier Audit Findings
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_supplier_audit_findings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_id INT NOT NULL,
            finding_no INT NOT NULL,
            clause_ref VARCHAR(100),
            finding_type ENUM('Major NC', 'Minor NC', 'Observation', 'Opportunity for Improvement') NOT NULL,
            description TEXT NOT NULL,
            evidence TEXT,
            corrective_action_required TINYINT(1) DEFAULT 1,
            target_date DATE,
            action_taken TEXT,
            verification_status ENUM('Open', 'In Progress', 'Verified', 'Closed') DEFAULT 'Open',
            verification_date DATE,
            FOREIGN KEY (audit_id) REFERENCES qc_supplier_audits(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_supplier_audit_findings";

    // 14. Calibration Records (for measuring instruments)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_calibration_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            instrument_id VARCHAR(100) NOT NULL,
            instrument_name VARCHAR(255) NOT NULL,
            instrument_type VARCHAR(100),
            location VARCHAR(100),
            range_min DECIMAL(15,6) DEFAULT NULL,
            range_max DECIMAL(15,6) DEFAULT NULL,
            unit VARCHAR(50),
            accuracy VARCHAR(100),
            calibration_date DATE NOT NULL,
            next_calibration_date DATE,
            calibration_agency VARCHAR(255),
            certificate_no VARCHAR(100),
            calibration_result ENUM('Pass', 'Fail', 'Limited Use') DEFAULT 'Pass',
            as_found VARCHAR(255),
            as_left VARCHAR(255),
            remarks TEXT,
            status ENUM('Active', 'Due', 'Overdue', 'Out of Service') DEFAULT 'Active',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: qc_calibration_records";

    echo "<h2>Quality Control Module - Database Setup Complete</h2>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    foreach ($messages as $msg) {
        echo "<p style='margin: 5px 0;'>$msg</p>";
    }
    echo "</div>";
    echo "<p><a href='dashboard.php' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>Go to QC Dashboard</a></p>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #721c24;'>Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
