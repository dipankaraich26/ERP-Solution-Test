<?php
/**
 * Accounting & Finance Module - Database Setup
 * Run this file once to create all necessary tables
 * Based on Tally-like double-entry accounting system
 */

include "../db.php";

$messages = [];

try {
    // 1. Account Groups (Chart of Accounts hierarchy)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_account_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_name VARCHAR(100) NOT NULL,
            parent_id INT DEFAULT NULL,
            group_type ENUM('Assets', 'Liabilities', 'Income', 'Expenses', 'Equity') NOT NULL,
            nature ENUM('Debit', 'Credit') NOT NULL,
            is_system TINYINT(1) DEFAULT 0 COMMENT 'System groups cannot be deleted',
            affects_gross_profit TINYINT(1) DEFAULT 0,
            description TEXT,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES acc_account_groups(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_account_groups";

    // 2. Ledgers (Individual accounts)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_ledgers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ledger_code VARCHAR(20) UNIQUE,
            ledger_name VARCHAR(150) NOT NULL,
            group_id INT NOT NULL,
            opening_balance DECIMAL(15,2) DEFAULT 0,
            opening_balance_type ENUM('Debit', 'Credit') DEFAULT 'Debit',
            current_balance DECIMAL(15,2) DEFAULT 0,
            balance_type ENUM('Debit', 'Credit') DEFAULT 'Debit',
            is_bank_account TINYINT(1) DEFAULT 0,
            is_cash_account TINYINT(1) DEFAULT 0,
            bank_name VARCHAR(100) DEFAULT NULL,
            bank_account_no VARCHAR(50) DEFAULT NULL,
            bank_ifsc VARCHAR(20) DEFAULT NULL,
            bank_branch VARCHAR(100) DEFAULT NULL,
            credit_limit DECIMAL(15,2) DEFAULT 0,
            credit_days INT DEFAULT 0,
            gst_applicable TINYINT(1) DEFAULT 0,
            gstin VARCHAR(20) DEFAULT NULL,
            pan VARCHAR(20) DEFAULT NULL,
            tds_applicable TINYINT(1) DEFAULT 0,
            tds_section VARCHAR(20) DEFAULT NULL,
            address TEXT,
            contact_person VARCHAR(100),
            phone VARCHAR(20),
            email VARCHAR(100),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES acc_account_groups(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_ledgers";

    // 3. Financial Years
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_financial_years (
            id INT AUTO_INCREMENT PRIMARY KEY,
            year_name VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_current TINYINT(1) DEFAULT 0,
            is_locked TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_financial_years";

    // 4. Voucher Types
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_voucher_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_name VARCHAR(50) NOT NULL,
            type_code VARCHAR(10) NOT NULL UNIQUE,
            numbering_prefix VARCHAR(10) DEFAULT NULL,
            is_system TINYINT(1) DEFAULT 0,
            affects_stock TINYINT(1) DEFAULT 0,
            description VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_voucher_types";

    // 5. Vouchers (Main transactions table)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_vouchers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_no VARCHAR(50) NOT NULL,
            voucher_type_id INT NOT NULL,
            voucher_date DATE NOT NULL,
            reference_no VARCHAR(100) DEFAULT NULL,
            reference_date DATE DEFAULT NULL,
            narration TEXT,
            total_amount DECIMAL(15,2) DEFAULT 0,
            is_posted TINYINT(1) DEFAULT 1,
            is_cancelled TINYINT(1) DEFAULT 0,
            cancelled_reason VARCHAR(255) DEFAULT NULL,
            financial_year_id INT,
            party_ledger_id INT DEFAULT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (voucher_type_id) REFERENCES acc_voucher_types(id),
            FOREIGN KEY (party_ledger_id) REFERENCES acc_ledgers(id),
            INDEX idx_voucher_date (voucher_date),
            INDEX idx_voucher_type (voucher_type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_vouchers";

    // 6. Voucher Entries (Debit/Credit line items)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_voucher_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_id INT NOT NULL,
            ledger_id INT NOT NULL,
            debit_amount DECIMAL(15,2) DEFAULT 0,
            credit_amount DECIMAL(15,2) DEFAULT 0,
            narration VARCHAR(255) DEFAULT NULL,
            cost_center_id INT DEFAULT NULL,
            FOREIGN KEY (voucher_id) REFERENCES acc_vouchers(id) ON DELETE CASCADE,
            FOREIGN KEY (ledger_id) REFERENCES acc_ledgers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_voucher_entries";

    // 7. Bank Accounts (Extended details)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_bank_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ledger_id INT NOT NULL UNIQUE,
            bank_name VARCHAR(100) NOT NULL,
            account_no VARCHAR(50) NOT NULL,
            account_type ENUM('Savings', 'Current', 'OD', 'CC', 'Fixed Deposit') DEFAULT 'Current',
            ifsc_code VARCHAR(20),
            branch_name VARCHAR(100),
            branch_address TEXT,
            od_limit DECIMAL(15,2) DEFAULT 0,
            interest_rate DECIMAL(5,2) DEFAULT 0,
            last_reconciled_date DATE DEFAULT NULL,
            last_reconciled_balance DECIMAL(15,2) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (ledger_id) REFERENCES acc_ledgers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_bank_accounts";

    // 8. Bank Reconciliation
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_bank_reconciliation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bank_account_id INT NOT NULL,
            voucher_entry_id INT NOT NULL,
            bank_date DATE DEFAULT NULL COMMENT 'Date as per bank statement',
            is_reconciled TINYINT(1) DEFAULT 0,
            reconciled_date DATE DEFAULT NULL,
            reconciled_by INT DEFAULT NULL,
            bank_reference VARCHAR(100) DEFAULT NULL,
            remarks VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (bank_account_id) REFERENCES acc_bank_accounts(id),
            FOREIGN KEY (voucher_entry_id) REFERENCES acc_voucher_entries(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_bank_reconciliation";

    // 9. Expense Categories
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_expense_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            parent_id INT DEFAULT NULL,
            ledger_id INT DEFAULT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (ledger_id) REFERENCES acc_ledgers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_expense_categories";

    // 10. Expenses
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            expense_no VARCHAR(50) NOT NULL UNIQUE,
            expense_date DATE NOT NULL,
            category_id INT,
            ledger_id INT NOT NULL,
            paid_from_ledger_id INT NOT NULL COMMENT 'Bank or Cash account',
            amount DECIMAL(15,2) NOT NULL,
            gst_amount DECIMAL(15,2) DEFAULT 0,
            tds_amount DECIMAL(15,2) DEFAULT 0,
            net_amount DECIMAL(15,2) NOT NULL,
            vendor_name VARCHAR(150),
            vendor_gstin VARCHAR(20),
            invoice_no VARCHAR(100),
            invoice_date DATE,
            description TEXT,
            payment_mode ENUM('Cash', 'Bank Transfer', 'Cheque', 'UPI', 'Card') DEFAULT 'Bank Transfer',
            cheque_no VARCHAR(50),
            voucher_id INT DEFAULT NULL,
            attachment VARCHAR(255),
            status ENUM('Draft', 'Approved', 'Paid', 'Cancelled') DEFAULT 'Draft',
            approved_by INT,
            approved_at DATETIME,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES acc_expense_categories(id),
            FOREIGN KEY (ledger_id) REFERENCES acc_ledgers(id),
            FOREIGN KEY (paid_from_ledger_id) REFERENCES acc_ledgers(id),
            FOREIGN KEY (voucher_id) REFERENCES acc_vouchers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_expenses";

    // 11. GST Configuration
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_gst_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rate_name VARCHAR(50) NOT NULL,
            cgst_rate DECIMAL(5,2) DEFAULT 0,
            sgst_rate DECIMAL(5,2) DEFAULT 0,
            igst_rate DECIMAL(5,2) DEFAULT 0,
            cess_rate DECIMAL(5,2) DEFAULT 0,
            hsn_code VARCHAR(20),
            description VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_gst_rates";

    // 12. GST Transactions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_gst_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_id INT,
            transaction_type ENUM('Sales', 'Purchase', 'Sales Return', 'Purchase Return') NOT NULL,
            invoice_no VARCHAR(100),
            invoice_date DATE,
            party_gstin VARCHAR(20),
            party_name VARCHAR(150),
            place_of_supply VARCHAR(50),
            is_reverse_charge TINYINT(1) DEFAULT 0,
            taxable_amount DECIMAL(15,2) DEFAULT 0,
            cgst_amount DECIMAL(15,2) DEFAULT 0,
            sgst_amount DECIMAL(15,2) DEFAULT 0,
            igst_amount DECIMAL(15,2) DEFAULT 0,
            cess_amount DECIMAL(15,2) DEFAULT 0,
            total_amount DECIMAL(15,2) DEFAULT 0,
            gst_rate_id INT,
            return_period VARCHAR(10) COMMENT 'MMYYYY format',
            is_filed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (voucher_id) REFERENCES acc_vouchers(id),
            FOREIGN KEY (gst_rate_id) REFERENCES acc_gst_rates(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_gst_transactions";

    // 13. GST Returns
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_gst_returns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            return_type ENUM('GSTR-1', 'GSTR-2A', 'GSTR-2B', 'GSTR-3B', 'GSTR-9', 'GSTR-9C') NOT NULL,
            return_period VARCHAR(10) NOT NULL COMMENT 'MMYYYY format',
            financial_year VARCHAR(10),
            filing_date DATE,
            due_date DATE,
            total_taxable DECIMAL(15,2) DEFAULT 0,
            total_cgst DECIMAL(15,2) DEFAULT 0,
            total_sgst DECIMAL(15,2) DEFAULT 0,
            total_igst DECIMAL(15,2) DEFAULT 0,
            total_cess DECIMAL(15,2) DEFAULT 0,
            total_tax DECIMAL(15,2) DEFAULT 0,
            itc_claimed DECIMAL(15,2) DEFAULT 0,
            tax_payable DECIMAL(15,2) DEFAULT 0,
            tax_paid DECIMAL(15,2) DEFAULT 0,
            status ENUM('Draft', 'Filed', 'Revised') DEFAULT 'Draft',
            arn_no VARCHAR(50),
            acknowledgement_date DATE,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_gst_returns";

    // 14. TDS Sections
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_tds_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_code VARCHAR(20) NOT NULL UNIQUE,
            section_name VARCHAR(100) NOT NULL,
            description TEXT,
            individual_rate DECIMAL(5,2) DEFAULT 0,
            company_rate DECIMAL(5,2) DEFAULT 0,
            threshold_amount DECIMAL(15,2) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_tds_sections";

    // 15. TDS Transactions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_tds_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_id INT,
            deductee_ledger_id INT NOT NULL,
            deductee_pan VARCHAR(20),
            deductee_name VARCHAR(150),
            tds_section_id INT NOT NULL,
            transaction_date DATE NOT NULL,
            gross_amount DECIMAL(15,2) NOT NULL,
            tds_rate DECIMAL(5,2) NOT NULL,
            tds_amount DECIMAL(15,2) NOT NULL,
            surcharge DECIMAL(15,2) DEFAULT 0,
            cess DECIMAL(15,2) DEFAULT 0,
            total_tds DECIMAL(15,2) NOT NULL,
            net_amount DECIMAL(15,2) NOT NULL,
            challan_no VARCHAR(50),
            challan_date DATE,
            bsr_code VARCHAR(20),
            is_deposited TINYINT(1) DEFAULT 0,
            deposit_date DATE,
            quarter VARCHAR(10) COMMENT 'Q1, Q2, Q3, Q4',
            financial_year VARCHAR(10),
            certificate_no VARCHAR(50),
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (voucher_id) REFERENCES acc_vouchers(id),
            FOREIGN KEY (deductee_ledger_id) REFERENCES acc_ledgers(id),
            FOREIGN KEY (tds_section_id) REFERENCES acc_tds_sections(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_tds_transactions";

    // 16. Cost Centers (for department-wise tracking)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_cost_centers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            center_name VARCHAR(100) NOT NULL,
            center_code VARCHAR(20),
            parent_id INT DEFAULT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_cost_centers";

    // 17. Budgets
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acc_budgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            budget_name VARCHAR(100) NOT NULL,
            financial_year_id INT NOT NULL,
            ledger_id INT,
            cost_center_id INT,
            budget_type ENUM('Monthly', 'Quarterly', 'Yearly') DEFAULT 'Monthly',
            period VARCHAR(20),
            budgeted_amount DECIMAL(15,2) DEFAULT 0,
            actual_amount DECIMAL(15,2) DEFAULT 0,
            variance DECIMAL(15,2) DEFAULT 0,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (financial_year_id) REFERENCES acc_financial_years(id),
            FOREIGN KEY (ledger_id) REFERENCES acc_ledgers(id),
            FOREIGN KEY (cost_center_id) REFERENCES acc_cost_centers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Created table: acc_budgets";

    // Insert default Account Groups (Tally-like structure)
    $pdo->exec("
        INSERT IGNORE INTO acc_account_groups (id, group_name, parent_id, group_type, nature, is_system, sort_order) VALUES
        -- Primary Groups
        (1, 'Capital Account', NULL, 'Equity', 'Credit', 1, 1),
        (2, 'Current Assets', NULL, 'Assets', 'Debit', 1, 2),
        (3, 'Current Liabilities', NULL, 'Liabilities', 'Credit', 1, 3),
        (4, 'Direct Expenses', NULL, 'Expenses', 'Debit', 1, 4),
        (5, 'Direct Incomes', NULL, 'Income', 'Credit', 1, 5),
        (6, 'Fixed Assets', NULL, 'Assets', 'Debit', 1, 6),
        (7, 'Indirect Expenses', NULL, 'Expenses', 'Debit', 1, 7),
        (8, 'Indirect Incomes', NULL, 'Income', 'Credit', 1, 8),
        (9, 'Investments', NULL, 'Assets', 'Debit', 1, 9),
        (10, 'Loans (Liability)', NULL, 'Liabilities', 'Credit', 1, 10),
        (11, 'Loans & Advances (Asset)', NULL, 'Assets', 'Debit', 1, 11),
        (12, 'Suspense Account', NULL, 'Assets', 'Debit', 1, 12),
        (13, 'Branch / Divisions', NULL, 'Liabilities', 'Credit', 1, 13),
        (14, 'Misc. Expenses', NULL, 'Expenses', 'Debit', 1, 14),

        -- Sub Groups under Current Assets
        (20, 'Bank Accounts', 2, 'Assets', 'Debit', 1, 1),
        (21, 'Cash-in-Hand', 2, 'Assets', 'Debit', 1, 2),
        (22, 'Sundry Debtors', 2, 'Assets', 'Debit', 1, 3),
        (23, 'Stock-in-Hand', 2, 'Assets', 'Debit', 1, 4),
        (24, 'Deposits (Asset)', 2, 'Assets', 'Debit', 1, 5),

        -- Sub Groups under Current Liabilities
        (30, 'Sundry Creditors', 3, 'Liabilities', 'Credit', 1, 1),
        (31, 'Duties & Taxes', 3, 'Liabilities', 'Credit', 1, 2),
        (32, 'Provisions', 3, 'Liabilities', 'Credit', 1, 3),

        -- Sub Groups under Duties & Taxes
        (40, 'GST Payable', 31, 'Liabilities', 'Credit', 1, 1),
        (41, 'TDS Payable', 31, 'Liabilities', 'Credit', 1, 2),
        (42, 'GST Input Credit', 2, 'Assets', 'Debit', 1, 6),

        -- Sub Groups under Direct Expenses
        (50, 'Purchase Accounts', 4, 'Expenses', 'Debit', 1, 1),

        -- Sub Groups under Direct Income
        (60, 'Sales Accounts', 5, 'Income', 'Credit', 1, 1),

        -- Sub Groups under Indirect Expenses
        (70, 'Administrative Expenses', 7, 'Expenses', 'Debit', 1, 1),
        (71, 'Selling Expenses', 7, 'Expenses', 'Debit', 1, 2),
        (72, 'Financial Expenses', 7, 'Expenses', 'Debit', 1, 3)
    ");
    $messages[] = "✅ Inserted default Account Groups";

    // Insert default Voucher Types
    $pdo->exec("
        INSERT IGNORE INTO acc_voucher_types (id, type_name, type_code, numbering_prefix, is_system, description) VALUES
        (1, 'Payment', 'PMT', 'PMT/', 1, 'Cash/Bank payments'),
        (2, 'Receipt', 'RCT', 'RCT/', 1, 'Cash/Bank receipts'),
        (3, 'Contra', 'CNT', 'CNT/', 1, 'Fund transfers between cash/bank'),
        (4, 'Journal', 'JRN', 'JRN/', 1, 'Adjustment entries'),
        (5, 'Sales', 'SAL', 'SAL/', 1, 'Sales invoices'),
        (6, 'Purchase', 'PUR', 'PUR/', 1, 'Purchase invoices'),
        (7, 'Credit Note', 'CN', 'CN/', 1, 'Sales returns'),
        (8, 'Debit Note', 'DN', 'DN/', 1, 'Purchase returns'),
        (9, 'Expense', 'EXP', 'EXP/', 1, 'Expense vouchers')
    ");
    $messages[] = "✅ Inserted default Voucher Types";

    // Insert default TDS Sections
    $pdo->exec("
        INSERT IGNORE INTO acc_tds_sections (section_code, section_name, individual_rate, company_rate, threshold_amount) VALUES
        ('194A', 'Interest other than securities', 10.00, 10.00, 40000),
        ('194C', 'Payment to Contractors', 1.00, 2.00, 30000),
        ('194H', 'Commission or Brokerage', 5.00, 5.00, 15000),
        ('194I(a)', 'Rent - Plant & Machinery', 2.00, 2.00, 240000),
        ('194I(b)', 'Rent - Land & Building', 10.00, 10.00, 240000),
        ('194J', 'Professional/Technical Services', 10.00, 10.00, 30000),
        ('194Q', 'Purchase of Goods', 0.10, 0.10, 5000000),
        ('194R', 'Benefits or Perquisites', 10.00, 10.00, 20000)
    ");
    $messages[] = "✅ Inserted default TDS Sections";

    // Insert default GST Rates
    $pdo->exec("
        INSERT IGNORE INTO acc_gst_rates (rate_name, cgst_rate, sgst_rate, igst_rate) VALUES
        ('Exempt', 0, 0, 0),
        ('GST 5%', 2.5, 2.5, 5),
        ('GST 12%', 6, 6, 12),
        ('GST 18%', 9, 9, 18),
        ('GST 28%', 14, 14, 28)
    ");
    $messages[] = "✅ Inserted default GST Rates";

    // Insert default Financial Year
    $currentYear = date('Y');
    $startMonth = date('n') >= 4 ? $currentYear : $currentYear - 1;
    $fyStart = "$startMonth-04-01";
    $fyEnd = ($startMonth + 1) . "-03-31";
    $fyName = "$startMonth-" . ($startMonth + 1);

    $pdo->exec("
        INSERT IGNORE INTO acc_financial_years (year_name, start_date, end_date, is_current) VALUES
        ('$fyName', '$fyStart', '$fyEnd', 1)
    ");
    $messages[] = "✅ Created current Financial Year: $fyName";

    // Insert default Ledgers
    $pdo->exec("
        INSERT IGNORE INTO acc_ledgers (id, ledger_code, ledger_name, group_id, is_cash_account, is_bank_account) VALUES
        (1, 'CASH', 'Cash', 21, 1, 0),
        (2, 'CGST-INPUT', 'CGST Input', 42, 0, 0),
        (3, 'SGST-INPUT', 'SGST Input', 42, 0, 0),
        (4, 'IGST-INPUT', 'IGST Input', 42, 0, 0),
        (5, 'CGST-OUTPUT', 'CGST Output', 40, 0, 0),
        (6, 'SGST-OUTPUT', 'SGST Output', 40, 0, 0),
        (7, 'IGST-OUTPUT', 'IGST Output', 40, 0, 0),
        (8, 'TDS-PAYABLE', 'TDS Payable', 41, 0, 0),
        (9, 'SALES', 'Sales Account', 60, 0, 0),
        (10, 'PURCHASE', 'Purchase Account', 50, 0, 0),
        (11, 'ROUND-OFF', 'Round Off', 7, 0, 0)
    ");
    $messages[] = "✅ Inserted default Ledgers";

    echo "<!DOCTYPE html><html><head><title>Accounts Module Setup</title>";
    echo "<link rel='stylesheet' href='../assets/style.css'></head><body style='padding: 40px;'>";
    echo "<h2>Accounting & Finance Module - Database Setup Complete</h2>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    foreach ($messages as $msg) {
        echo "<p style='margin: 5px 0;'>$msg</p>";
    }
    echo "</div>";
    echo "<h3>Module Features:</h3>";
    echo "<ul>
        <li>Chart of Accounts (Tally-like hierarchy)</li>
        <li>Double-entry Voucher System (Payment, Receipt, Journal, Contra)</li>
        <li>Bank Reconciliation</li>
        <li>Expense Management</li>
        <li>GST Compliance (GSTR-1, GSTR-3B)</li>
        <li>TDS Management</li>
        <li>Financial Reports (P&L, Balance Sheet, Trial Balance)</li>
        <li>Cost Center Tracking</li>
        <li>Budget Management</li>
    </ul>";
    echo "<p><a href='dashboard.php' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>Go to Accounts Dashboard</a></p>";
    echo "</body></html>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #721c24;'>Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
