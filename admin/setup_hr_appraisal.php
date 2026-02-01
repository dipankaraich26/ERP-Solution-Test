<?php
/**
 * Setup HR Appraisal System, Skill Metrics, and Employee Documents
 */

include "../db.php";

$messages = [];
$errors = [];

// Run setup when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {

    try {
        // 1. Employee Documents Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                document_type VARCHAR(100) NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                document_number VARCHAR(100),
                file_path VARCHAR(500),
                issue_date DATE,
                expiry_date DATE,
                verified TINYINT(1) DEFAULT 0,
                verified_by INT,
                verified_at DATETIME,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee (employee_id),
                INDEX idx_doc_type (document_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created employee_documents table";

        // 2. Document Types Master
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type_name VARCHAR(100) NOT NULL UNIQUE,
                type_code VARCHAR(20) NOT NULL UNIQUE,
                category VARCHAR(50),
                is_mandatory TINYINT(1) DEFAULT 0,
                requires_expiry TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created document_types table";

        // Insert default document types
        $docTypes = [
            ['PAN Card', 'PAN', 'Identity', 1, 0, 1],
            ['Aadhaar Card', 'AADHAAR', 'Identity', 1, 0, 2],
            ['Passport', 'PASSPORT', 'Identity', 0, 1, 3],
            ['Driving License', 'DL', 'Identity', 0, 1, 4],
            ['Voter ID', 'VOTER', 'Identity', 0, 0, 5],
            ['10th Marksheet', '10TH', 'Education', 0, 0, 10],
            ['12th Marksheet', '12TH', 'Education', 0, 0, 11],
            ['Degree Certificate', 'DEGREE', 'Education', 0, 0, 12],
            ['Post Graduation Certificate', 'PG', 'Education', 0, 0, 13],
            ['Professional Certification', 'CERT', 'Education', 0, 1, 14],
            ['Experience Letter', 'EXP_LETTER', 'Employment', 0, 0, 20],
            ['Relieving Letter', 'RELIEVING', 'Employment', 0, 0, 21],
            ['Offer Letter', 'OFFER', 'Employment', 0, 0, 22],
            ['Appointment Letter', 'APPOINT', 'Employment', 0, 0, 23],
            ['Bank Passbook/Statement', 'BANK', 'Financial', 0, 0, 30],
            ['Cancelled Cheque', 'CHEQUE', 'Financial', 0, 0, 31],
            ['Address Proof', 'ADDRESS', 'Other', 0, 0, 40],
            ['Photo', 'PHOTO', 'Other', 0, 0, 41],
            ['Other Document', 'OTHER', 'Other', 0, 0, 99]
        ];

        $insertDoc = $pdo->prepare("
            INSERT IGNORE INTO document_types (type_name, type_code, category, is_mandatory, requires_expiry, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($docTypes as $dt) {
            $insertDoc->execute($dt);
        }
        $messages[] = "Inserted default document types";

        // 3. Skill Categories
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS skill_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                is_active TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created skill_categories table";

        // 4. Skills Master
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS skills_master (
                id INT AUTO_INCREMENT PRIMARY KEY,
                skill_name VARCHAR(100) NOT NULL,
                category_id INT,
                description TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_skill (skill_name, category_id),
                INDEX idx_category (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created skills_master table";

        // 5. Employee Skills
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_skills (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                skill_id INT NOT NULL,
                proficiency_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Beginner',
                years_experience DECIMAL(4,1) DEFAULT 0,
                last_used_date DATE,
                certified TINYINT(1) DEFAULT 0,
                certification_name VARCHAR(200),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_emp_skill (employee_id, skill_id),
                INDEX idx_employee (employee_id),
                INDEX idx_skill (skill_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created employee_skills table";

        // Insert default skill categories and skills
        $categories = [
            ['Technical Skills', 'Programming, software, and technical competencies', 1],
            ['Soft Skills', 'Communication, teamwork, and interpersonal skills', 2],
            ['Management Skills', 'Leadership and management competencies', 3],
            ['Domain Knowledge', 'Industry and domain-specific knowledge', 4],
            ['Tools & Software', 'Proficiency in specific tools and software', 5]
        ];

        $insertCat = $pdo->prepare("INSERT IGNORE INTO skill_categories (category_name, description, sort_order) VALUES (?, ?, ?)");
        foreach ($categories as $cat) {
            $insertCat->execute($cat);
        }
        $messages[] = "Inserted default skill categories";

        // 6. Appraisal Cycles
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS appraisal_cycles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cycle_name VARCHAR(100) NOT NULL,
                cycle_type ENUM('Annual', 'Half-Yearly', 'Quarterly', 'Probation', 'Special') DEFAULT 'Annual',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                self_review_deadline DATE,
                manager_review_deadline DATE,
                status ENUM('Draft', 'Active', 'In Review', 'Completed', 'Cancelled') DEFAULT 'Draft',
                description TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created appraisal_cycles table";

        // 7. Appraisal Criteria/Competencies
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS appraisal_criteria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                criteria_name VARCHAR(150) NOT NULL,
                criteria_code VARCHAR(20),
                category ENUM('Performance', 'Competency', 'Goal', 'Behavior', 'Development') DEFAULT 'Performance',
                description TEXT,
                weightage DECIMAL(5,2) DEFAULT 0,
                max_rating INT DEFAULT 5,
                is_active TINYINT(1) DEFAULT 1,
                applies_to VARCHAR(100) DEFAULT 'All',
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created appraisal_criteria table";

        // Insert default appraisal criteria
        $criteria = [
            ['Job Knowledge', 'JK', 'Competency', 'Understanding of job requirements and technical skills', 15, 5, 1],
            ['Quality of Work', 'QW', 'Performance', 'Accuracy, thoroughness, and reliability of work output', 15, 5, 2],
            ['Productivity', 'PROD', 'Performance', 'Volume of work accomplished and efficiency', 15, 5, 3],
            ['Communication Skills', 'COMM', 'Competency', 'Ability to convey information effectively', 10, 5, 4],
            ['Teamwork', 'TEAM', 'Behavior', 'Collaboration and cooperation with colleagues', 10, 5, 5],
            ['Initiative', 'INIT', 'Behavior', 'Self-motivation and proactive approach', 10, 5, 6],
            ['Problem Solving', 'PS', 'Competency', 'Ability to analyze and resolve issues', 10, 5, 7],
            ['Attendance & Punctuality', 'ATT', 'Behavior', 'Regularity and timeliness', 5, 5, 8],
            ['Adherence to Policies', 'POL', 'Behavior', 'Following company rules and procedures', 5, 5, 9],
            ['Professional Development', 'DEV', 'Development', 'Continuous learning and skill improvement', 5, 5, 10]
        ];

        $insertCrit = $pdo->prepare("
            INSERT IGNORE INTO appraisal_criteria
            (criteria_name, criteria_code, category, description, weightage, max_rating, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($criteria as $c) {
            $insertCrit->execute($c);
        }
        $messages[] = "Inserted default appraisal criteria";

        // 8. Main Appraisals Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS appraisals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                appraisal_no VARCHAR(30) NOT NULL UNIQUE,
                cycle_id INT NOT NULL,
                employee_id INT NOT NULL,
                reviewer_id INT,
                status ENUM('Draft', 'Self Review', 'Manager Review', 'HR Review', 'Completed', 'Acknowledged') DEFAULT 'Draft',

                -- Self Review
                self_review_date DATETIME,
                self_overall_rating DECIMAL(3,2),
                self_strengths TEXT,
                self_improvements TEXT,
                self_goals TEXT,
                self_training_needs TEXT,

                -- Manager Review
                manager_review_date DATETIME,
                manager_overall_rating DECIMAL(3,2),
                manager_strengths TEXT,
                manager_improvements TEXT,
                manager_recommendations TEXT,
                promotion_recommendation ENUM('No', 'Maybe', 'Yes') DEFAULT 'No',
                salary_increment_recommendation DECIMAL(5,2),

                -- HR Review
                hr_reviewer_id INT,
                hr_review_date DATETIME,
                hr_comments TEXT,
                final_rating DECIMAL(3,2),
                final_grade VARCHAR(10),

                -- Acknowledgement
                acknowledged_at DATETIME,
                employee_comments TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_cycle (cycle_id),
                INDEX idx_employee (employee_id),
                INDEX idx_reviewer (reviewer_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created appraisals table";

        // 9. Appraisal Ratings (individual criteria ratings)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS appraisal_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                appraisal_id INT NOT NULL,
                criteria_id INT NOT NULL,
                self_rating INT,
                self_comments TEXT,
                manager_rating INT,
                manager_comments TEXT,
                final_rating INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_appraisal_criteria (appraisal_id, criteria_id),
                INDEX idx_appraisal (appraisal_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created appraisal_ratings table";

        // 10. Appraisal Goals
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS appraisal_goals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                appraisal_id INT NOT NULL,
                goal_description TEXT NOT NULL,
                target_date DATE,
                weightage DECIMAL(5,2) DEFAULT 0,
                status ENUM('Not Started', 'In Progress', 'Completed', 'Partially Met', 'Not Met') DEFAULT 'Not Started',
                self_achievement_percent INT,
                self_comments TEXT,
                manager_achievement_percent INT,
                manager_comments TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_appraisal (appraisal_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created appraisal_goals table";

        // Create uploads directory for employee documents
        $uploadDir = "../uploads/employee_docs/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            $messages[] = "Created uploads directory for employee documents";
        }

    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Check existing tables
$tables = [
    'employee_documents' => false,
    'document_types' => false,
    'skill_categories' => false,
    'skills_master' => false,
    'employee_skills' => false,
    'appraisal_cycles' => false,
    'appraisal_criteria' => false,
    'appraisals' => false,
    'appraisal_ratings' => false,
    'appraisal_goals' => false
];

foreach ($tables as $table => $exists) {
    $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
    $tables[$table] = (bool)$check;
}

$allTablesExist = !in_array(false, $tables);

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="content">
<style>
        .setup-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .status-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-card h3 {
            margin-top: 0;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .table-status {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        .table-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .table-item.exists {
            background: #d4edda;
        }
        .table-item.missing {
            background: #fff3cd;
        }
        .status-icon {
            width: 20px;
            margin-right: 10px;
            font-weight: bold;
        }
        .messages {
            margin: 20px 0;
        }
        .message-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .message-success {
            background: #d4edda;
            color: #155724;
        }
        .message-error {
            background: #f8d7da;
            color: #721c24;
        }
        .feature-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        .feature-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .feature-card h4 {
            margin: 0 0 10px 0;
        }
        .feature-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9em;
        }
    </style>

    <div class="setup-container">
        <h1>HR Appraisal System Setup</h1>

        <?php if (!empty($messages)): ?>
        <div class="messages">
            <?php foreach ($messages as $msg): ?>
            <div class="message-item message-success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="messages">
            <?php foreach ($errors as $err): ?>
            <div class="message-item message-error"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Features Overview -->
        <div class="feature-list">
            <div class="feature-card">
                <h4>Employee Documents</h4>
                <p>PAN, Aadhaar, Certificates, Experience Letters</p>
            </div>
            <div class="feature-card">
                <h4>Skill Metrics</h4>
                <p>Track skills, proficiency levels, certifications</p>
            </div>
            <div class="feature-card">
                <h4>Appraisal System</h4>
                <p>Self-review, Manager review, Goals tracking</p>
            </div>
        </div>

        <!-- Table Status -->
        <div class="status-card" style="margin-top: 30px;">
            <h3>Database Tables Status</h3>
            <div class="table-status">
                <?php foreach ($tables as $table => $exists): ?>
                <div class="table-item <?= $exists ? 'exists' : 'missing' ?>">
                    <span class="status-icon"><?= $exists ? '✓' : '✗' ?></span>
                    <span><?= $table ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Setup Button -->
        <?php if (!$allTablesExist): ?>
        <div class="status-card">
            <h3>Run Setup</h3>
            <p>Click the button below to create all required tables and insert default data.</p>
            <form method="post">
                <button type="submit" name="run_setup" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1em;">
                    Run Setup
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="status-card" style="background: #d4edda;">
            <h3 style="color: #155724; border-color: #28a745;">Setup Complete</h3>
            <p style="color: #155724;">All tables are ready. You can now use the HR Appraisal features.</p>
            <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="../hr/employee_documents.php" class="btn btn-primary">Employee Documents</a>
                <a href="../hr/skills.php" class="btn btn-primary">Skills Management</a>
                <a href="../hr/appraisal_cycles.php" class="btn btn-primary">Appraisal Cycles</a>
                <a href="../hr/appraisals.php" class="btn btn-primary">Appraisals</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Links -->
        <div class="status-card">
            <h3>Module Overview</h3>
            <table style="width: 100%;">
                <tr>
                    <th style="text-align: left; padding: 10px;">Module</th>
                    <th style="text-align: left; padding: 10px;">Description</th>
                </tr>
                <tr>
                    <td style="padding: 10px;"><strong>Employee Documents</strong></td>
                    <td style="padding: 10px;">Upload and manage identity proofs, educational certificates, employment documents</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px;"><strong>Skills Management</strong></td>
                    <td style="padding: 10px;">Track employee skills, proficiency levels, certifications, and experience</td>
                </tr>
                <tr>
                    <td style="padding: 10px;"><strong>Appraisal Cycles</strong></td>
                    <td style="padding: 10px;">Create and manage annual/quarterly appraisal periods</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px;"><strong>Appraisal Process</strong></td>
                    <td style="padding: 10px;">Self-review → Manager review → HR review → Acknowledgement</td>
                </tr>
                <tr>
                    <td style="padding: 10px;"><strong>Appraisal Criteria</strong></td>
                    <td style="padding: 10px;">Configurable criteria with weightage for fair evaluation</td>
                </tr>
            </table>
        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>
