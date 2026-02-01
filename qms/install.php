<?php
/**
 * QMS (Quality Management System) Module Installation
 * Creates all necessary database tables for CDSCO, ISO, and ICMED compliance tracking
 */
include "../db.php";

echo "<h2>QMS Module Installation</h2>";
echo "<pre>";

try {
    // ============================================
    // CDSCO TABLES (Central Drugs Standard Control Organisation)
    // ============================================

    // CDSCO Product Registrations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_cdsco_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_code VARCHAR(50) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_category ENUM('Medical Device', 'Diagnostic', 'Pharmaceutical', 'IVD', 'Implant', 'Other') NOT NULL,
            risk_class ENUM('Class A', 'Class B', 'Class C', 'Class D') NOT NULL,
            registration_no VARCHAR(100),
            registration_date DATE,
            expiry_date DATE,
            status ENUM('Draft', 'Submitted', 'Under Review', 'Query Raised', 'Approved', 'Rejected', 'Expired', 'Renewed') DEFAULT 'Draft',
            manufacturer VARCHAR(255),
            authorized_agent VARCHAR(255),
            intended_use TEXT,
            technical_specs TEXT,
            submission_date DATE,
            approval_date DATE,
            remarks TEXT,
            documents_path VARCHAR(500),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_expiry (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_cdsco_products\n";

    // CDSCO Licenses (Manufacturing, Import, etc.)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_cdsco_licenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_type ENUM('Manufacturing License', 'Import License', 'Wholesale License', 'Retail License', 'Test License', 'Loan License') NOT NULL,
            license_no VARCHAR(100),
            form_type VARCHAR(50),
            facility_name VARCHAR(255) NOT NULL,
            facility_address TEXT,
            products_covered TEXT,
            issue_date DATE,
            expiry_date DATE,
            status ENUM('Applied', 'Under Inspection', 'Approved', 'Rejected', 'Expired', 'Renewal Pending', 'Suspended') DEFAULT 'Applied',
            issuing_authority VARCHAR(255),
            inspector_name VARCHAR(100),
            inspection_date DATE,
            inspection_remarks TEXT,
            conditions TEXT,
            documents_path VARCHAR(500),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_expiry (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_cdsco_licenses\n";

    // CDSCO Adverse Events
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_cdsco_adverse_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_no VARCHAR(50) NOT NULL,
            product_id INT,
            event_date DATE NOT NULL,
            report_date DATE NOT NULL,
            event_type ENUM('Death', 'Life Threatening', 'Hospitalization', 'Disability', 'Intervention Required', 'Other Serious', 'Non-Serious') NOT NULL,
            event_description TEXT NOT NULL,
            patient_outcome ENUM('Recovered', 'Recovering', 'Not Recovered', 'Fatal', 'Unknown') DEFAULT 'Unknown',
            causality_assessment ENUM('Certain', 'Probable', 'Possible', 'Unlikely', 'Unclassified', 'Pending') DEFAULT 'Pending',
            corrective_action TEXT,
            reported_to_cdsco ENUM('Yes', 'No', 'Pending') DEFAULT 'Pending',
            cdsco_acknowledgement VARCHAR(100),
            status ENUM('Open', 'Under Investigation', 'Closed', 'Reported') DEFAULT 'Open',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES qms_cdsco_products(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_cdsco_adverse_events\n";

    // ============================================
    // ISO TABLES (International Standards)
    // ============================================

    // ISO Certifications
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_iso_certifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            standard_code VARCHAR(50) NOT NULL,
            standard_name VARCHAR(255) NOT NULL,
            scope TEXT,
            certification_body VARCHAR(255),
            certificate_no VARCHAR(100),
            issue_date DATE,
            expiry_date DATE,
            status ENUM('Planning', 'Implementation', 'Audit Scheduled', 'Certified', 'Suspended', 'Withdrawn', 'Renewal Due') DEFAULT 'Planning',
            last_audit_date DATE,
            next_audit_date DATE,
            audit_type ENUM('Initial', 'Surveillance', 'Recertification', 'Special') DEFAULT 'Initial',
            findings_count INT DEFAULT 0,
            major_nc INT DEFAULT 0,
            minor_nc INT DEFAULT 0,
            observations INT DEFAULT 0,
            certificate_path VARCHAR(500),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_expiry (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_iso_certifications\n";

    // ISO Audits
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_iso_audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            certification_id INT,
            audit_no VARCHAR(50) NOT NULL,
            audit_type ENUM('Internal', 'External', 'Supplier', 'Customer', 'Regulatory') NOT NULL,
            audit_standard VARCHAR(100),
            audit_scope TEXT,
            planned_date DATE,
            actual_date DATE,
            lead_auditor VARCHAR(100),
            audit_team TEXT,
            department VARCHAR(100),
            status ENUM('Planned', 'In Progress', 'Completed', 'Cancelled', 'Postponed') DEFAULT 'Planned',
            major_nc INT DEFAULT 0,
            minor_nc INT DEFAULT 0,
            observations INT DEFAULT 0,
            opportunities INT DEFAULT 0,
            audit_report_path VARCHAR(500),
            conclusion TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (certification_id) REFERENCES qms_iso_certifications(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_date (planned_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_iso_audits\n";

    // Non-Conformance Reports (NCR)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_ncr (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ncr_no VARCHAR(50) NOT NULL,
            audit_id INT,
            source ENUM('Internal Audit', 'External Audit', 'Customer Complaint', 'Process Deviation', 'Supplier Issue', 'Management Review', 'Other') NOT NULL,
            nc_type ENUM('Major', 'Minor', 'Observation', 'Opportunity') NOT NULL,
            clause_reference VARCHAR(100),
            department VARCHAR(100),
            description TEXT NOT NULL,
            evidence TEXT,
            root_cause TEXT,
            immediate_action TEXT,
            corrective_action TEXT,
            preventive_action TEXT,
            responsible_person VARCHAR(100),
            target_date DATE,
            closure_date DATE,
            status ENUM('Open', 'Action Planned', 'In Progress', 'Verification Pending', 'Closed', 'Reopened') DEFAULT 'Open',
            effectiveness_verified ENUM('Yes', 'No', 'Pending') DEFAULT 'Pending',
            verified_by VARCHAR(100),
            verification_date DATE,
            verification_remarks TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (audit_id) REFERENCES qms_iso_audits(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_nc_type (nc_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_ncr\n";

    // CAPA (Corrective and Preventive Actions)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_capa (
            id INT AUTO_INCREMENT PRIMARY KEY,
            capa_no VARCHAR(50) NOT NULL,
            capa_type ENUM('Corrective', 'Preventive') NOT NULL,
            source ENUM('NCR', 'Customer Complaint', 'Audit Finding', 'Process Deviation', 'Risk Assessment', 'Management Decision', 'Other') NOT NULL,
            source_reference VARCHAR(100),
            priority ENUM('Critical', 'High', 'Medium', 'Low') DEFAULT 'Medium',
            problem_description TEXT NOT NULL,
            affected_area VARCHAR(255),
            risk_assessment TEXT,
            root_cause_analysis TEXT,
            root_cause_method ENUM('5 Why', 'Fishbone', 'Fault Tree', 'FMEA', 'Other') DEFAULT '5 Why',
            proposed_action TEXT NOT NULL,
            implementation_plan TEXT,
            responsible_person VARCHAR(100),
            target_date DATE,
            actual_completion_date DATE,
            status ENUM('Initiated', 'Investigation', 'Action Planned', 'Implementation', 'Verification', 'Closed', 'Cancelled') DEFAULT 'Initiated',
            effectiveness_criteria TEXT,
            effectiveness_result TEXT,
            verified_by VARCHAR(100),
            verification_date DATE,
            closure_remarks TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_type (capa_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_capa\n";

    // Document Control
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_no VARCHAR(50) NOT NULL,
            doc_type ENUM('SOP', 'Work Instruction', 'Form', 'Template', 'Policy', 'Manual', 'Specification', 'Protocol', 'Report', 'Record', 'External') NOT NULL,
            title VARCHAR(255) NOT NULL,
            department VARCHAR(100),
            category VARCHAR(100),
            version VARCHAR(20) NOT NULL DEFAULT '1.0',
            revision_date DATE,
            effective_date DATE,
            review_date DATE,
            expiry_date DATE,
            status ENUM('Draft', 'Under Review', 'Approved', 'Effective', 'Obsolete', 'Superseded') DEFAULT 'Draft',
            author VARCHAR(100),
            reviewer VARCHAR(100),
            approver VARCHAR(100),
            file_path VARCHAR(500),
            change_description TEXT,
            distribution_list TEXT,
            training_required ENUM('Yes', 'No') DEFAULT 'No',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_doc_type (doc_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_documents\n";

    // ============================================
    // ICMED TABLES (Indian Certification for Medical Devices)
    // ============================================

    // ICMED Certifications
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_icmed_certifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            icmed_no VARCHAR(100),
            product_name VARCHAR(255) NOT NULL,
            product_category VARCHAR(100),
            device_class ENUM('Class A', 'Class B', 'Class C', 'Class D') NOT NULL,
            application_date DATE,
            certification_date DATE,
            expiry_date DATE,
            status ENUM('Application Submitted', 'Document Review', 'Factory Audit Scheduled', 'Factory Audit Completed', 'Technical Review', 'Certified', 'Suspended', 'Withdrawn', 'Renewal Pending', 'Expired') DEFAULT 'Application Submitted',
            certification_body VARCHAR(255),
            auditor_name VARCHAR(100),
            audit_date DATE,
            audit_findings TEXT,
            nc_count INT DEFAULT 0,
            certificate_path VARCHAR(500),
            renewal_application_date DATE,
            remarks TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES qms_cdsco_products(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_expiry (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_icmed_certifications\n";

    // ICMED Factory Audits
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_icmed_audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            certification_id INT,
            audit_no VARCHAR(50) NOT NULL,
            audit_type ENUM('Initial', 'Surveillance', 'Renewal', 'Special', 'Unannounced') NOT NULL,
            scheduled_date DATE,
            actual_date DATE,
            auditor_name VARCHAR(100),
            audit_team TEXT,
            status ENUM('Scheduled', 'In Progress', 'Completed', 'Postponed', 'Cancelled') DEFAULT 'Scheduled',
            checklist_used VARCHAR(255),
            areas_audited TEXT,
            major_nc INT DEFAULT 0,
            minor_nc INT DEFAULT 0,
            observations INT DEFAULT 0,
            audit_result ENUM('Pass', 'Conditional Pass', 'Fail', 'Pending') DEFAULT 'Pending',
            corrective_actions_due DATE,
            follow_up_required ENUM('Yes', 'No') DEFAULT 'No',
            follow_up_date DATE,
            report_path VARCHAR(500),
            remarks TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (certification_id) REFERENCES qms_icmed_certifications(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_date (scheduled_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_icmed_audits\n";

    // ============================================
    // COMMON QMS TABLES
    // ============================================

    // Training Records (for QMS compliance)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_training (
            id INT AUTO_INCREMENT PRIMARY KEY,
            training_code VARCHAR(50) NOT NULL,
            training_title VARCHAR(255) NOT NULL,
            training_type ENUM('Induction', 'Procedure', 'Skill', 'Regulatory', 'Safety', 'Refresher', 'External') NOT NULL,
            related_document_id INT,
            department VARCHAR(100),
            trainer_name VARCHAR(100),
            training_date DATE,
            duration_hours DECIMAL(5,2),
            employee_ids TEXT,
            attendee_count INT DEFAULT 0,
            status ENUM('Planned', 'Completed', 'Cancelled') DEFAULT 'Planned',
            assessment_required ENUM('Yes', 'No') DEFAULT 'No',
            pass_criteria VARCHAR(255),
            effectiveness_evaluation TEXT,
            training_material_path VARCHAR(500),
            attendance_sheet_path VARCHAR(500),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (related_document_id) REFERENCES qms_documents(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_date (training_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_training\n";

    // Management Review Records
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qms_management_review (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_no VARCHAR(50) NOT NULL,
            review_date DATE NOT NULL,
            chairman VARCHAR(100),
            attendees TEXT,
            agenda TEXT,
            previous_actions_status TEXT,
            audit_results_summary TEXT,
            customer_feedback_summary TEXT,
            process_performance TEXT,
            nc_capa_summary TEXT,
            resource_requirements TEXT,
            improvement_opportunities TEXT,
            risk_assessment TEXT,
            decisions TEXT,
            action_items TEXT,
            next_review_date DATE,
            minutes_path VARCHAR(500),
            status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_date (review_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: qms_management_review\n";

    // Insert default ISO standards
    $pdo->exec("
        INSERT IGNORE INTO qms_iso_certifications (id, standard_code, standard_name, status) VALUES
        (1, 'ISO 9001:2015', 'Quality Management Systems', 'Planning'),
        (2, 'ISO 13485:2016', 'Medical Devices - Quality Management Systems', 'Planning'),
        (3, 'ISO 14001:2015', 'Environmental Management Systems', 'Planning'),
        (4, 'ISO 45001:2018', 'Occupational Health and Safety', 'Planning'),
        (5, 'ISO 22000:2018', 'Food Safety Management Systems', 'Planning')
    ");
    echo "Inserted default ISO standards\n";

    echo "\n</pre>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724; margin: 0 0 10px 0;'>QMS Module Installation Complete!</h3>";
    echo "<p style='color: #155724; margin: 0;'>All database tables have been created successfully.</p>";
    echo "</div>";

    echo "<div style='margin-top: 20px;'>";
    echo "<a href='dashboard.php' style='display: inline-block; padding: 12px 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Go to QMS Dashboard</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "\n</pre>";
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px;'>";
    echo "<h3 style='color: #721c24;'>Installation Error</h3>";
    echo "<p style='color: #721c24;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
