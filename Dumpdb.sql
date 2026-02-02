-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 02, 2026 at 03:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `yashka_erpsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `acc_account_groups`
--

CREATE TABLE `acc_account_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `group_type` enum('Assets','Liabilities','Income','Expenses','Equity') NOT NULL,
  `nature` enum('Debit','Credit') NOT NULL,
  `is_system` tinyint(1) DEFAULT 0 COMMENT 'System groups cannot be deleted',
  `affects_gross_profit` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_account_groups`
--

INSERT INTO `acc_account_groups` (`id`, `group_name`, `parent_id`, `group_type`, `nature`, `is_system`, `affects_gross_profit`, `description`, `sort_order`, `created_at`) VALUES
(1, 'Capital Account', NULL, 'Equity', 'Credit', 1, 0, NULL, 1, '2026-01-26 16:11:28'),
(2, 'Current Assets', NULL, 'Assets', 'Debit', 1, 0, NULL, 2, '2026-01-26 16:11:28'),
(3, 'Current Liabilities', NULL, 'Liabilities', 'Credit', 1, 0, NULL, 3, '2026-01-26 16:11:28'),
(4, 'Direct Expenses', NULL, 'Expenses', 'Debit', 1, 0, NULL, 4, '2026-01-26 16:11:28'),
(5, 'Direct Incomes', NULL, 'Income', 'Credit', 1, 0, NULL, 5, '2026-01-26 16:11:28'),
(6, 'Fixed Assets', NULL, 'Assets', 'Debit', 1, 0, NULL, 6, '2026-01-26 16:11:28'),
(7, 'Indirect Expenses', NULL, 'Expenses', 'Debit', 1, 0, NULL, 7, '2026-01-26 16:11:28'),
(8, 'Indirect Incomes', NULL, 'Income', 'Credit', 1, 0, NULL, 8, '2026-01-26 16:11:28'),
(9, 'Investments', NULL, 'Assets', 'Debit', 1, 0, NULL, 9, '2026-01-26 16:11:28'),
(10, 'Loans (Liability)', NULL, 'Liabilities', 'Credit', 1, 0, NULL, 10, '2026-01-26 16:11:28'),
(11, 'Loans & Advances (Asset)', NULL, 'Assets', 'Debit', 1, 0, NULL, 11, '2026-01-26 16:11:28'),
(12, 'Suspense Account', NULL, 'Assets', 'Debit', 1, 0, NULL, 12, '2026-01-26 16:11:28'),
(13, 'Branch / Divisions', NULL, 'Liabilities', 'Credit', 1, 0, NULL, 13, '2026-01-26 16:11:28'),
(14, 'Misc. Expenses', NULL, 'Expenses', 'Debit', 1, 0, NULL, 14, '2026-01-26 16:11:28'),
(20, 'Bank Accounts', 2, 'Assets', 'Debit', 1, 0, NULL, 1, '2026-01-26 16:11:28'),
(21, 'Cash-in-Hand', 2, 'Assets', 'Debit', 1, 0, NULL, 2, '2026-01-26 16:11:28'),
(22, 'Sundry Debtors', 2, 'Assets', 'Debit', 1, 0, NULL, 3, '2026-01-26 16:11:28'),
(23, 'Stock-in-Hand', 2, 'Assets', 'Debit', 1, 0, NULL, 4, '2026-01-26 16:11:28'),
(24, 'Deposits (Asset)', 2, 'Assets', 'Debit', 1, 0, NULL, 5, '2026-01-26 16:11:28'),
(30, 'Sundry Creditors', 3, 'Liabilities', 'Credit', 1, 0, NULL, 1, '2026-01-26 16:11:28'),
(31, 'Duties & Taxes', 3, 'Liabilities', 'Credit', 1, 0, NULL, 2, '2026-01-26 16:11:28'),
(32, 'Provisions', 3, 'Liabilities', 'Credit', 1, 0, NULL, 3, '2026-01-26 16:11:28'),
(40, 'GST Payable', 31, 'Liabilities', 'Credit', 1, 0, NULL, 1, '2026-01-26 16:11:28'),
(41, 'TDS Payable', 31, 'Liabilities', 'Credit', 1, 0, NULL, 2, '2026-01-26 16:11:28'),
(42, 'GST Input Credit', 2, 'Assets', 'Debit', 1, 0, NULL, 6, '2026-01-26 16:11:28'),
(50, 'Purchase Accounts', 4, 'Expenses', 'Debit', 1, 0, NULL, 1, '2026-01-26 16:11:28'),
(60, 'Sales Accounts', 5, 'Income', 'Credit', 1, 0, NULL, 1, '2026-01-26 16:11:28'),
(70, 'Administrative Expenses', 7, 'Expenses', 'Debit', 1, 0, NULL, 1, '2026-01-26 16:11:28'),
(71, 'Selling Expenses', 7, 'Expenses', 'Debit', 1, 0, NULL, 2, '2026-01-26 16:11:28'),
(72, 'Financial Expenses', 7, 'Expenses', 'Debit', 1, 0, NULL, 3, '2026-01-26 16:11:28');

-- --------------------------------------------------------

--
-- Table structure for table `acc_bank_accounts`
--

CREATE TABLE `acc_bank_accounts` (
  `id` int(11) NOT NULL,
  `ledger_id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_no` varchar(50) NOT NULL,
  `account_type` enum('Savings','Current','OD','CC','Fixed Deposit') DEFAULT 'Current',
  `ifsc_code` varchar(20) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `branch_address` text DEFAULT NULL,
  `od_limit` decimal(15,2) DEFAULT 0.00,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `last_reconciled_date` date DEFAULT NULL,
  `last_reconciled_balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_bank_reconciliation`
--

CREATE TABLE `acc_bank_reconciliation` (
  `id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `voucher_entry_id` int(11) NOT NULL,
  `bank_date` date DEFAULT NULL COMMENT 'Date as per bank statement',
  `is_reconciled` tinyint(1) DEFAULT 0,
  `reconciled_date` date DEFAULT NULL,
  `reconciled_by` int(11) DEFAULT NULL,
  `bank_reference` varchar(100) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_budgets`
--

CREATE TABLE `acc_budgets` (
  `id` int(11) NOT NULL,
  `budget_name` varchar(100) NOT NULL,
  `financial_year_id` int(11) NOT NULL,
  `ledger_id` int(11) DEFAULT NULL,
  `cost_center_id` int(11) DEFAULT NULL,
  `budget_type` enum('Monthly','Quarterly','Yearly') DEFAULT 'Monthly',
  `period` varchar(20) DEFAULT NULL,
  `budgeted_amount` decimal(15,2) DEFAULT 0.00,
  `actual_amount` decimal(15,2) DEFAULT 0.00,
  `variance` decimal(15,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_cost_centers`
--

CREATE TABLE `acc_cost_centers` (
  `id` int(11) NOT NULL,
  `center_name` varchar(100) NOT NULL,
  `center_code` varchar(20) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_expenses`
--

CREATE TABLE `acc_expenses` (
  `id` int(11) NOT NULL,
  `expense_no` varchar(50) NOT NULL,
  `expense_date` date NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `ledger_id` int(11) NOT NULL,
  `paid_from_ledger_id` int(11) NOT NULL COMMENT 'Bank or Cash account',
  `amount` decimal(15,2) NOT NULL,
  `gst_amount` decimal(15,2) DEFAULT 0.00,
  `tds_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `vendor_name` varchar(150) DEFAULT NULL,
  `vendor_gstin` varchar(20) DEFAULT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `payment_mode` enum('Cash','Bank Transfer','Cheque','UPI','Card') DEFAULT 'Bank Transfer',
  `cheque_no` varchar(50) DEFAULT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('Draft','Approved','Paid','Cancelled') DEFAULT 'Draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_expense_categories`
--

CREATE TABLE `acc_expense_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `ledger_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_financial_years`
--

CREATE TABLE `acc_financial_years` (
  `id` int(11) NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_financial_years`
--

INSERT INTO `acc_financial_years` (`id`, `year_name`, `start_date`, `end_date`, `is_current`, `is_locked`, `created_at`) VALUES
(1, '2025-2026', '2025-04-01', '2026-03-31', 1, 0, '2026-01-26 16:11:28');

-- --------------------------------------------------------

--
-- Table structure for table `acc_gst_rates`
--

CREATE TABLE `acc_gst_rates` (
  `id` int(11) NOT NULL,
  `rate_name` varchar(50) NOT NULL,
  `cgst_rate` decimal(5,2) DEFAULT 0.00,
  `sgst_rate` decimal(5,2) DEFAULT 0.00,
  `igst_rate` decimal(5,2) DEFAULT 0.00,
  `cess_rate` decimal(5,2) DEFAULT 0.00,
  `hsn_code` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_gst_rates`
--

INSERT INTO `acc_gst_rates` (`id`, `rate_name`, `cgst_rate`, `sgst_rate`, `igst_rate`, `cess_rate`, `hsn_code`, `description`, `is_active`) VALUES
(1, 'Exempt', 0.00, 0.00, 0.00, 0.00, NULL, NULL, 1),
(2, 'GST 5%', 2.50, 2.50, 5.00, 0.00, NULL, NULL, 1),
(3, 'GST 12%', 6.00, 6.00, 12.00, 0.00, NULL, NULL, 1),
(4, 'GST 18%', 9.00, 9.00, 18.00, 0.00, NULL, NULL, 1),
(5, 'GST 28%', 14.00, 14.00, 28.00, 0.00, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `acc_gst_returns`
--

CREATE TABLE `acc_gst_returns` (
  `id` int(11) NOT NULL,
  `return_type` enum('GSTR-1','GSTR-2A','GSTR-2B','GSTR-3B','GSTR-9','GSTR-9C') NOT NULL,
  `return_period` varchar(10) NOT NULL COMMENT 'MMYYYY format',
  `financial_year` varchar(10) DEFAULT NULL,
  `filing_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `total_taxable` decimal(15,2) DEFAULT 0.00,
  `total_cgst` decimal(15,2) DEFAULT 0.00,
  `total_sgst` decimal(15,2) DEFAULT 0.00,
  `total_igst` decimal(15,2) DEFAULT 0.00,
  `total_cess` decimal(15,2) DEFAULT 0.00,
  `total_tax` decimal(15,2) DEFAULT 0.00,
  `itc_claimed` decimal(15,2) DEFAULT 0.00,
  `tax_payable` decimal(15,2) DEFAULT 0.00,
  `tax_paid` decimal(15,2) DEFAULT 0.00,
  `status` enum('Draft','Filed','Revised') DEFAULT 'Draft',
  `arn_no` varchar(50) DEFAULT NULL,
  `acknowledgement_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_gst_transactions`
--

CREATE TABLE `acc_gst_transactions` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `transaction_type` enum('Sales','Purchase','Sales Return','Purchase Return') NOT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `party_gstin` varchar(20) DEFAULT NULL,
  `party_name` varchar(150) DEFAULT NULL,
  `place_of_supply` varchar(50) DEFAULT NULL,
  `is_reverse_charge` tinyint(1) DEFAULT 0,
  `taxable_amount` decimal(15,2) DEFAULT 0.00,
  `cgst_amount` decimal(15,2) DEFAULT 0.00,
  `sgst_amount` decimal(15,2) DEFAULT 0.00,
  `igst_amount` decimal(15,2) DEFAULT 0.00,
  `cess_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `gst_rate_id` int(11) DEFAULT NULL,
  `return_period` varchar(10) DEFAULT NULL COMMENT 'MMYYYY format',
  `is_filed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_ledgers`
--

CREATE TABLE `acc_ledgers` (
  `id` int(11) NOT NULL,
  `ledger_code` varchar(20) DEFAULT NULL,
  `ledger_name` varchar(150) NOT NULL,
  `group_id` int(11) NOT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `opening_balance_type` enum('Debit','Credit') DEFAULT 'Debit',
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `balance_type` enum('Debit','Credit') DEFAULT 'Debit',
  `is_bank_account` tinyint(1) DEFAULT 0,
  `is_cash_account` tinyint(1) DEFAULT 0,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `credit_days` int(11) DEFAULT 0,
  `gst_applicable` tinyint(1) DEFAULT 0,
  `gstin` varchar(20) DEFAULT NULL,
  `pan` varchar(20) DEFAULT NULL,
  `tds_applicable` tinyint(1) DEFAULT 0,
  `tds_section` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_ledgers`
--

INSERT INTO `acc_ledgers` (`id`, `ledger_code`, `ledger_name`, `group_id`, `opening_balance`, `opening_balance_type`, `current_balance`, `balance_type`, `is_bank_account`, `is_cash_account`, `bank_name`, `bank_account_no`, `bank_ifsc`, `bank_branch`, `credit_limit`, `credit_days`, `gst_applicable`, `gstin`, `pan`, `tds_applicable`, `tds_section`, `address`, `contact_person`, `phone`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CASH', 'Cash', 21, 0.00, 'Debit', 0.00, 'Debit', 0, 1, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(2, 'CGST-INPUT', 'CGST Input', 42, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(3, 'SGST-INPUT', 'SGST Input', 42, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(4, 'IGST-INPUT', 'IGST Input', 42, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(5, 'CGST-OUTPUT', 'CGST Output', 40, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(6, 'SGST-OUTPUT', 'SGST Output', 40, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(7, 'IGST-OUTPUT', 'IGST Output', 40, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(8, 'TDS-PAYABLE', 'TDS Payable', 41, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(9, 'SALES', 'Sales Account', 60, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(10, 'PURCHASE', 'Purchase Account', 50, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28'),
(11, 'ROUND-OFF', 'Round Off', 7, 0.00, 'Debit', 0.00, 'Debit', 0, 0, NULL, NULL, NULL, NULL, 0.00, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-26 16:11:28', '2026-01-26 16:11:28');

-- --------------------------------------------------------

--
-- Table structure for table `acc_tds_sections`
--

CREATE TABLE `acc_tds_sections` (
  `id` int(11) NOT NULL,
  `section_code` varchar(20) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `individual_rate` decimal(5,2) DEFAULT 0.00,
  `company_rate` decimal(5,2) DEFAULT 0.00,
  `threshold_amount` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_tds_sections`
--

INSERT INTO `acc_tds_sections` (`id`, `section_code`, `section_name`, `description`, `individual_rate`, `company_rate`, `threshold_amount`, `is_active`) VALUES
(1, '194A', 'Interest other than securities', NULL, 10.00, 10.00, 40000.00, 1),
(2, '194C', 'Payment to Contractors', NULL, 1.00, 2.00, 30000.00, 1),
(3, '194H', 'Commission or Brokerage', NULL, 5.00, 5.00, 15000.00, 1),
(4, '194I(a)', 'Rent - Plant & Machinery', NULL, 2.00, 2.00, 240000.00, 1),
(5, '194I(b)', 'Rent - Land & Building', NULL, 10.00, 10.00, 240000.00, 1),
(6, '194J', 'Professional/Technical Services', NULL, 10.00, 10.00, 30000.00, 1),
(7, '194Q', 'Purchase of Goods', NULL, 0.10, 0.10, 5000000.00, 1),
(8, '194R', 'Benefits or Perquisites', NULL, 10.00, 10.00, 20000.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `acc_tds_transactions`
--

CREATE TABLE `acc_tds_transactions` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `deductee_ledger_id` int(11) NOT NULL,
  `deductee_pan` varchar(20) DEFAULT NULL,
  `deductee_name` varchar(150) DEFAULT NULL,
  `tds_section_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `tds_rate` decimal(5,2) NOT NULL,
  `tds_amount` decimal(15,2) NOT NULL,
  `surcharge` decimal(15,2) DEFAULT 0.00,
  `cess` decimal(15,2) DEFAULT 0.00,
  `total_tds` decimal(15,2) NOT NULL,
  `net_amount` decimal(15,2) NOT NULL,
  `challan_no` varchar(50) DEFAULT NULL,
  `challan_date` date DEFAULT NULL,
  `bsr_code` varchar(20) DEFAULT NULL,
  `is_deposited` tinyint(1) DEFAULT 0,
  `deposit_date` date DEFAULT NULL,
  `quarter` varchar(10) DEFAULT NULL COMMENT 'Q1, Q2, Q3, Q4',
  `financial_year` varchar(10) DEFAULT NULL,
  `certificate_no` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_vouchers`
--

CREATE TABLE `acc_vouchers` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `voucher_type_id` int(11) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `reference_date` date DEFAULT NULL,
  `narration` text DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `is_posted` tinyint(1) DEFAULT 1,
  `is_cancelled` tinyint(1) DEFAULT 0,
  `cancelled_reason` varchar(255) DEFAULT NULL,
  `financial_year_id` int(11) DEFAULT NULL,
  `party_ledger_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_voucher_entries`
--

CREATE TABLE `acc_voucher_entries` (
  `id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `ledger_id` int(11) NOT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `narration` varchar(255) DEFAULT NULL,
  `cost_center_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_voucher_types`
--

CREATE TABLE `acc_voucher_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `type_code` varchar(10) NOT NULL,
  `numbering_prefix` varchar(10) DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `affects_stock` tinyint(1) DEFAULT 0,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_voucher_types`
--

INSERT INTO `acc_voucher_types` (`id`, `type_name`, `type_code`, `numbering_prefix`, `is_system`, `affects_stock`, `description`) VALUES
(1, 'Payment', 'PMT', 'PMT/', 1, 0, 'Cash/Bank payments'),
(2, 'Receipt', 'RCT', 'RCT/', 1, 0, 'Cash/Bank receipts'),
(3, 'Contra', 'CNT', 'CNT/', 1, 0, 'Fund transfers between cash/bank'),
(4, 'Journal', 'JRN', 'JRN/', 1, 0, 'Adjustment entries'),
(5, 'Sales', 'SAL', 'SAL/', 1, 0, 'Sales invoices'),
(6, 'Purchase', 'PUR', 'PUR/', 1, 0, 'Purchase invoices'),
(7, 'Credit Note', 'CN', 'CN/', 1, 0, 'Sales returns'),
(8, 'Debit Note', 'DN', 'DN/', 1, 0, 'Purchase returns'),
(9, 'Expense', 'EXP', 'EXP/', 1, 0, 'Expense vouchers');

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `module`, `record_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-18 09:47:11'),
(2, 1, 'login', 'auth', NULL, NULL, '192.168.1.102', '2026-01-18 09:49:50'),
(3, 1, 'logout', 'auth', NULL, NULL, '192.168.1.102', '2026-01-18 10:00:13'),
(4, 1, 'login', 'auth', NULL, NULL, '192.168.1.102', '2026-01-18 10:00:16'),
(5, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-18 10:01:46'),
(6, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-18 10:02:17'),
(7, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-18 10:05:46'),
(8, 1, 'login', 'auth', NULL, NULL, '192.168.1.8', '2026-01-18 14:30:20'),
(9, 2, 'login', 'auth', NULL, NULL, '::1', '2026-01-18 14:32:23'),
(10, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-19 01:37:58'),
(11, 1, 'login', 'auth', NULL, NULL, '192.168.1.101', '2026-01-19 02:59:32'),
(12, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-19 04:51:11'),
(13, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-19 07:21:06'),
(14, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-20 15:48:38'),
(15, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-21 10:18:40'),
(16, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-21 16:22:23'),
(17, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-21 16:22:26'),
(18, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-23 17:42:57'),
(19, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-24 07:42:18'),
(20, NULL, 'login', 'auth', NULL, NULL, '::1', '2026-01-24 07:42:30'),
(21, NULL, 'logout', 'auth', NULL, NULL, '::1', '2026-01-24 08:01:25'),
(22, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-24 08:01:30'),
(23, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-24 08:05:57'),
(24, 4, 'login', 'auth', NULL, NULL, '::1', '2026-01-24 08:06:22'),
(25, 4, 'logout', 'auth', NULL, NULL, '::1', '2026-01-24 08:06:58'),
(26, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-24 08:07:02'),
(27, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-24 15:16:29'),
(28, NULL, 'login', 'auth', NULL, NULL, '::1', '2026-01-24 15:16:35'),
(29, NULL, 'logout', 'auth', NULL, NULL, '::1', '2026-01-24 15:19:39'),
(30, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-24 15:19:43'),
(31, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-25 05:23:26'),
(32, 4, 'login', 'auth', NULL, NULL, '::1', '2026-01-25 05:23:30'),
(33, 4, 'logout', 'auth', NULL, NULL, '::1', '2026-01-25 05:23:52'),
(34, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-25 05:23:56'),
(35, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-26 13:33:43'),
(36, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-28 07:27:48'),
(37, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-28 14:15:01'),
(38, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-29 01:39:17'),
(39, 5, 'login', 'auth', NULL, NULL, '10.212.240.22', '2026-01-29 07:56:50'),
(40, 9, 'login', 'auth', NULL, NULL, '10.212.240.93', '2026-01-29 07:58:41'),
(41, 4, 'login', 'auth', NULL, NULL, '10.212.240.119', '2026-01-29 07:59:00'),
(42, 6, 'login', 'auth', NULL, NULL, '10.212.240.241', '2026-01-29 08:01:27'),
(43, 7, 'login', 'auth', NULL, NULL, '10.212.240.155', '2026-01-29 08:03:20'),
(44, 8, 'login', 'auth', NULL, NULL, '10.212.240.101', '2026-01-29 08:03:42'),
(45, 11, 'login', 'auth', NULL, NULL, '10.212.240.74', '2026-01-29 08:13:45'),
(46, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-29 08:28:01'),
(47, 12, 'login', 'auth', NULL, NULL, '10.212.240.116', '2026-01-29 08:35:05'),
(48, 7, 'login', 'auth', NULL, NULL, '10.212.240.155', '2026-01-29 09:15:43'),
(49, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-29 14:52:38'),
(50, 6, 'login', 'auth', NULL, NULL, '10.212.240.241', '2026-01-30 04:35:08'),
(51, 7, 'login', 'auth', NULL, NULL, '10.212.240.155', '2026-01-30 04:35:40'),
(52, 5, 'login', 'auth', NULL, NULL, '10.212.240.22', '2026-01-30 04:37:10'),
(53, 9, 'login', 'auth', NULL, NULL, '10.212.240.93', '2026-01-30 04:39:32'),
(54, 4, 'login', 'auth', NULL, NULL, '10.212.240.119', '2026-01-30 04:43:49'),
(55, 12, 'login', 'auth', NULL, NULL, '10.212.240.147', '2026-01-30 04:47:31'),
(56, 4, 'logout', 'auth', NULL, NULL, '10.212.240.119', '2026-01-30 04:51:24'),
(57, 7, 'logout', 'auth', NULL, NULL, '10.212.240.155', '2026-01-30 04:51:27'),
(58, 4, 'login', 'auth', NULL, NULL, '10.212.240.119', '2026-01-30 04:51:33'),
(59, 5, 'logout', 'auth', NULL, NULL, '10.212.240.22', '2026-01-30 04:51:34'),
(60, 5, 'login', 'auth', NULL, NULL, '10.212.240.22', '2026-01-30 04:51:39'),
(61, 7, 'login', 'auth', NULL, NULL, '10.212.240.155', '2026-01-30 04:51:42'),
(62, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-30 04:53:13'),
(63, 5, 'login', 'auth', NULL, NULL, '::1', '2026-01-30 04:53:25'),
(64, 5, 'logout', 'auth', NULL, NULL, '10.212.240.22', '2026-01-30 04:53:37'),
(65, 5, 'login', 'auth', NULL, NULL, '10.212.240.22', '2026-01-30 04:54:25'),
(66, 5, 'logout', 'auth', NULL, NULL, '::1', '2026-01-30 04:54:44'),
(67, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-30 04:54:46'),
(68, 8, 'login', 'auth', NULL, NULL, '10.212.240.101', '2026-01-30 05:04:22'),
(69, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-30 06:07:50'),
(70, 5, 'login', 'auth', NULL, NULL, '::1', '2026-01-30 06:08:11'),
(71, 5, 'logout', 'auth', NULL, NULL, '::1', '2026-01-30 06:16:20'),
(72, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-30 06:16:21'),
(73, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-30 06:38:20'),
(74, 5, 'login', 'auth', NULL, NULL, '::1', '2026-01-30 06:38:33'),
(75, 5, 'logout', 'auth', NULL, NULL, '::1', '2026-01-30 06:38:44'),
(76, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-30 06:38:46'),
(77, 11, 'login', 'auth', NULL, NULL, '10.212.240.74', '2026-01-30 10:12:23'),
(78, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-30 12:53:18'),
(79, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-31 02:06:54'),
(80, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-31 04:07:50'),
(81, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-31 04:13:04'),
(82, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-31 04:14:43'),
(83, 5, 'login', 'auth', NULL, NULL, '10.180.182.22', '2026-01-31 04:15:51'),
(84, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-31 04:16:06'),
(85, 6, 'login', 'auth', NULL, NULL, '10.180.182.241', '2026-01-31 04:22:54'),
(86, 6, 'login', 'auth', NULL, NULL, '10.180.182.86', '2026-01-31 04:26:05'),
(87, 5, 'login', 'auth', NULL, NULL, '10.180.182.174', '2026-01-31 04:30:47'),
(88, 4, 'login', 'auth', NULL, NULL, '10.180.182.119', '2026-01-31 04:31:28'),
(89, 12, 'login', 'auth', NULL, NULL, '10.180.182.147', '2026-01-31 04:31:38'),
(90, 7, 'login', 'auth', NULL, NULL, '10.180.182.155', '2026-01-31 04:32:49'),
(91, 8, 'login', 'auth', NULL, NULL, '10.180.182.101', '2026-01-31 05:08:18'),
(92, 8, 'login', 'auth', NULL, NULL, '10.180.182.101', '2026-01-31 05:25:38'),
(93, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-01-31 09:08:33'),
(94, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-31 11:24:00'),
(95, 1, 'login', 'auth', NULL, NULL, '::1', '2026-01-31 13:11:45'),
(96, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-02-01 03:55:28'),
(97, 1, 'login', 'auth', NULL, NULL, '::1', '2026-02-01 03:56:27'),
(98, 1, 'logout', 'auth', NULL, NULL, '::1', '2026-02-01 13:52:57'),
(99, 1, 'login', 'auth', NULL, NULL, '::1', '2026-02-01 13:56:21');

-- --------------------------------------------------------

--
-- Table structure for table `appraisals`
--

CREATE TABLE `appraisals` (
  `id` int(11) NOT NULL,
  `appraisal_no` varchar(30) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `status` enum('Draft','Self Review','Manager Review','HR Review','Completed','Acknowledged') DEFAULT 'Draft',
  `self_review_date` datetime DEFAULT NULL,
  `self_overall_rating` decimal(3,2) DEFAULT NULL,
  `self_strengths` text DEFAULT NULL,
  `self_improvements` text DEFAULT NULL,
  `self_goals` text DEFAULT NULL,
  `self_training_needs` text DEFAULT NULL,
  `manager_review_date` datetime DEFAULT NULL,
  `manager_overall_rating` decimal(3,2) DEFAULT NULL,
  `manager_strengths` text DEFAULT NULL,
  `manager_improvements` text DEFAULT NULL,
  `manager_recommendations` text DEFAULT NULL,
  `promotion_recommendation` enum('No','Maybe','Yes') DEFAULT 'No',
  `salary_increment_recommendation` decimal(5,2) DEFAULT NULL,
  `hr_reviewer_id` int(11) DEFAULT NULL,
  `hr_review_date` datetime DEFAULT NULL,
  `hr_comments` text DEFAULT NULL,
  `final_rating` decimal(3,2) DEFAULT NULL,
  `final_grade` varchar(10) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `employee_comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appraisals`
--

INSERT INTO `appraisals` (`id`, `appraisal_no`, `cycle_id`, `employee_id`, `reviewer_id`, `status`, `self_review_date`, `self_overall_rating`, `self_strengths`, `self_improvements`, `self_goals`, `self_training_needs`, `manager_review_date`, `manager_overall_rating`, `manager_strengths`, `manager_improvements`, `manager_recommendations`, `promotion_recommendation`, `salary_increment_recommendation`, `hr_reviewer_id`, `hr_review_date`, `hr_comments`, `final_rating`, `final_grade`, `acknowledged_at`, `employee_comments`, `created_at`, `updated_at`) VALUES
(1, 'APR-3-0062', 3, 62, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(2, 'APR-3-0063', 3, 63, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(3, 'APR-3-0064', 3, 64, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(4, 'APR-3-0065', 3, 65, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(5, 'APR-3-0066', 3, 66, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(6, 'APR-3-0067', 3, 67, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(7, 'APR-3-0068', 3, 68, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(8, 'APR-3-0069', 3, 69, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(9, 'APR-3-0070', 3, 70, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(10, 'APR-3-0071', 3, 71, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(11, 'APR-3-0072', 3, 72, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(12, 'APR-3-0073', 3, 73, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(13, 'APR-3-0074', 3, 74, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(14, 'APR-3-0075', 3, 75, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(15, 'APR-3-0076', 3, 76, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(16, 'APR-3-0077', 3, 77, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55'),
(17, 'APR-3-0078', 3, 78, NULL, 'Draft', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 02:08:55', '2026-01-31 02:08:55');

-- --------------------------------------------------------

--
-- Table structure for table `appraisal_criteria`
--

CREATE TABLE `appraisal_criteria` (
  `id` int(11) NOT NULL,
  `criteria_name` varchar(150) NOT NULL,
  `criteria_code` varchar(20) DEFAULT NULL,
  `category` enum('Performance','Competency','Goal','Behavior','Development') DEFAULT 'Performance',
  `description` text DEFAULT NULL,
  `weightage` decimal(5,2) DEFAULT 0.00,
  `max_rating` int(11) DEFAULT 5,
  `is_active` tinyint(1) DEFAULT 1,
  `applies_to` varchar(100) DEFAULT 'All',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appraisal_criteria`
--

INSERT INTO `appraisal_criteria` (`id`, `criteria_name`, `criteria_code`, `category`, `description`, `weightage`, `max_rating`, `is_active`, `applies_to`, `sort_order`, `created_at`) VALUES
(1, 'Job Knowledge', 'JK', 'Competency', 'Understanding of job requirements and technical skills', 15.00, 5, 1, 'All', 1, '2026-01-30 04:20:55'),
(2, 'Quality of Work', 'QW', 'Performance', 'Accuracy, thoroughness, and reliability of work output', 15.00, 5, 1, 'All', 2, '2026-01-30 04:20:55'),
(3, 'Productivity', 'PROD', 'Performance', 'Volume of work accomplished and efficiency', 15.00, 5, 1, 'All', 3, '2026-01-30 04:20:55'),
(4, 'Communication Skills', 'COMM', 'Competency', 'Ability to convey information effectively', 10.00, 5, 1, 'All', 4, '2026-01-30 04:20:55'),
(5, 'Teamwork', 'TEAM', 'Behavior', 'Collaboration and cooperation with colleagues', 10.00, 5, 1, 'All', 5, '2026-01-30 04:20:55'),
(6, 'Initiative', 'INIT', 'Behavior', 'Self-motivation and proactive approach', 10.00, 5, 1, 'All', 6, '2026-01-30 04:20:55'),
(7, 'Problem Solving', 'PS', 'Competency', 'Ability to analyze and resolve issues', 10.00, 5, 1, 'All', 7, '2026-01-30 04:20:55'),
(8, 'Attendance & Punctuality', 'ATT', 'Behavior', 'Regularity and timeliness', 5.00, 5, 1, 'All', 8, '2026-01-30 04:20:55'),
(9, 'Adherence to Policies', 'POL', 'Behavior', 'Following company rules and procedures', 5.00, 5, 1, 'All', 9, '2026-01-30 04:20:55'),
(10, 'Professional Development', 'DEV', 'Development', 'Continuous learning and skill improvement', 5.00, 5, 1, 'All', 10, '2026-01-30 04:20:55'),
(11, 'Job Knowledge', 'JK', 'Competency', 'Understanding of job requirements and technical skills', 15.00, 5, 1, 'All', 1, '2026-01-30 04:21:10'),
(12, 'Quality of Work', 'QW', 'Performance', 'Accuracy, thoroughness, and reliability of work output', 15.00, 5, 1, 'All', 2, '2026-01-30 04:21:10'),
(13, 'Productivity', 'PROD', 'Performance', 'Volume of work accomplished and efficiency', 15.00, 5, 1, 'All', 3, '2026-01-30 04:21:10'),
(14, 'Communication Skills', 'COMM', 'Competency', 'Ability to convey information effectively', 10.00, 5, 1, 'All', 4, '2026-01-30 04:21:10'),
(15, 'Teamwork', 'TEAM', 'Behavior', 'Collaboration and cooperation with colleagues', 10.00, 5, 1, 'All', 5, '2026-01-30 04:21:10'),
(16, 'Initiative', 'INIT', 'Behavior', 'Self-motivation and proactive approach', 10.00, 5, 1, 'All', 6, '2026-01-30 04:21:10'),
(17, 'Problem Solving', 'PS', 'Competency', 'Ability to analyze and resolve issues', 10.00, 5, 1, 'All', 7, '2026-01-30 04:21:10'),
(18, 'Attendance & Punctuality', 'ATT', 'Behavior', 'Regularity and timeliness', 5.00, 5, 1, 'All', 8, '2026-01-30 04:21:10'),
(19, 'Adherence to Policies', 'POL', 'Behavior', 'Following company rules and procedures', 5.00, 5, 1, 'All', 9, '2026-01-30 04:21:10'),
(20, 'Professional Development', 'DEV', 'Development', 'Continuous learning and skill improvement', 5.00, 5, 1, 'All', 10, '2026-01-30 04:21:10'),
(21, 'Job Knowledge', 'JK', 'Competency', 'Understanding of job requirements and technical skills', 15.00, 5, 1, 'All', 1, '2026-01-30 04:21:15'),
(22, 'Quality of Work', 'QW', 'Performance', 'Accuracy, thoroughness, and reliability of work output', 15.00, 5, 1, 'All', 2, '2026-01-30 04:21:15'),
(23, 'Productivity', 'PROD', 'Performance', 'Volume of work accomplished and efficiency', 15.00, 5, 1, 'All', 3, '2026-01-30 04:21:15'),
(24, 'Communication Skills', 'COMM', 'Competency', 'Ability to convey information effectively', 10.00, 5, 1, 'All', 4, '2026-01-30 04:21:15'),
(25, 'Teamwork', 'TEAM', 'Behavior', 'Collaboration and cooperation with colleagues', 10.00, 5, 1, 'All', 5, '2026-01-30 04:21:15'),
(26, 'Initiative', 'INIT', 'Behavior', 'Self-motivation and proactive approach', 10.00, 5, 1, 'All', 6, '2026-01-30 04:21:15'),
(27, 'Problem Solving', 'PS', 'Competency', 'Ability to analyze and resolve issues', 10.00, 5, 1, 'All', 7, '2026-01-30 04:21:15'),
(28, 'Attendance & Punctuality', 'ATT', 'Behavior', 'Regularity and timeliness', 5.00, 5, 1, 'All', 8, '2026-01-30 04:21:15'),
(29, 'Adherence to Policies', 'POL', 'Behavior', 'Following company rules and procedures', 5.00, 5, 1, 'All', 9, '2026-01-30 04:21:15'),
(30, 'Professional Development', 'DEV', 'Development', 'Continuous learning and skill improvement', 5.00, 5, 1, 'All', 10, '2026-01-30 04:21:15');

-- --------------------------------------------------------

--
-- Table structure for table `appraisal_cycles`
--

CREATE TABLE `appraisal_cycles` (
  `id` int(11) NOT NULL,
  `cycle_name` varchar(100) NOT NULL,
  `cycle_type` enum('Annual','Half-Yearly','Quarterly','Probation','Special') DEFAULT 'Annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `self_review_deadline` date DEFAULT NULL,
  `manager_review_deadline` date DEFAULT NULL,
  `status` enum('Draft','Active','In Review','Completed','Cancelled') DEFAULT 'Draft',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appraisal_cycles`
--

INSERT INTO `appraisal_cycles` (`id`, `cycle_name`, `cycle_type`, `start_date`, `end_date`, `self_review_deadline`, `manager_review_deadline`, `status`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'FY 2025-26', 'Quarterly', '2026-01-01', '2026-03-31', '2026-12-20', '2026-03-30', 'In Review', '', 1, '2026-01-30 16:24:36', '2026-01-30 16:27:38'),
(3, 'FY 2025-26', 'Quarterly', '2026-01-31', '2026-02-05', '2026-02-08', '2026-02-08', 'Active', 'Job knowledge', 1, '2026-01-31 02:08:34', '2026-01-31 02:09:02');

-- --------------------------------------------------------

--
-- Table structure for table `appraisal_goals`
--

CREATE TABLE `appraisal_goals` (
  `id` int(11) NOT NULL,
  `appraisal_id` int(11) NOT NULL,
  `goal_description` text NOT NULL,
  `target_date` date DEFAULT NULL,
  `weightage` decimal(5,2) DEFAULT 0.00,
  `status` enum('Not Started','In Progress','Completed','Partially Met','Not Met') DEFAULT 'Not Started',
  `self_achievement_percent` int(11) DEFAULT NULL,
  `self_comments` text DEFAULT NULL,
  `manager_achievement_percent` int(11) DEFAULT NULL,
  `manager_comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appraisal_ratings`
--

CREATE TABLE `appraisal_ratings` (
  `id` int(11) NOT NULL,
  `appraisal_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `self_rating` int(11) DEFAULT NULL,
  `self_comments` text DEFAULT NULL,
  `manager_rating` int(11) DEFAULT NULL,
  `manager_comments` text DEFAULT NULL,
  `final_rating` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `working_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('Present','Absent','Half Day','Late','On Leave','Holiday','Week Off') DEFAULT 'Present',
  `leave_type` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `check_in_lat` decimal(10,8) DEFAULT NULL,
  `check_in_lng` decimal(11,8) DEFAULT NULL,
  `check_out_lat` decimal(10,8) DEFAULT NULL,
  `check_out_lng` decimal(11,8) DEFAULT NULL,
  `check_in_distance` decimal(10,2) DEFAULT NULL,
  `check_out_distance` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `check_in`, `check_out`, `working_hours`, `overtime_hours`, `status`, `leave_type`, `remarks`, `created_at`, `updated_at`, `check_in_lat`, `check_in_lng`, `check_out_lat`, `check_out_lng`, `check_in_distance`, `check_out_distance`) VALUES
(1, 62, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 63, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 68, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 74, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 66, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 67, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 72, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 75, '2026-01-29', '10:00:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 09:29:58', NULL, NULL, NULL, NULL, NULL, NULL),
(9, 69, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 73, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 64, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 65, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(13, 70, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(14, 71, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 08:06:02', '2026-01-29 08:06:02', NULL, NULL, NULL, NULL, NULL, NULL),
(15, 77, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:06:52', '2026-01-29 09:06:52', NULL, NULL, NULL, NULL, NULL, NULL),
(16, 78, '2026-01-29', NULL, NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:06:52', '2026-01-29 09:06:52', NULL, NULL, NULL, NULL, NULL, NULL),
(18, 76, '2026-01-29', NULL, NULL, 0.00, 0.00, 'On Leave', NULL, NULL, '2026-01-29 09:06:52', '2026-01-29 09:06:52', NULL, NULL, NULL, NULL, NULL, NULL),
(49, 75, '2026-01-28', '10:35:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:33:42', '2026-01-29 09:33:42', NULL, NULL, NULL, NULL, NULL, NULL),
(50, 75, '2026-01-27', '10:33:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:34:28', '2026-01-29 09:34:28', NULL, NULL, NULL, NULL, NULL, NULL),
(52, 77, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(53, 78, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(54, 66, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(55, 62, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(56, 76, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(57, 63, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(58, 68, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(59, 74, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(60, 67, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(61, 72, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(62, 75, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(63, 69, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(64, 73, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(65, 64, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(66, 65, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(67, 70, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(68, 71, '2026-01-26', NULL, NULL, 0.00, 0.00, 'Holiday', NULL, NULL, '2026-01-29 09:35:22', '2026-01-29 09:35:22', NULL, NULL, NULL, NULL, NULL, NULL),
(86, 75, '2026-01-24', '10:20:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:36:47', '2026-01-29 09:36:47', NULL, NULL, NULL, NULL, NULL, NULL),
(87, 75, '2026-01-01', '09:30:00', '06:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:42:22', '2026-01-29 09:42:22', NULL, NULL, NULL, NULL, NULL, NULL),
(89, 75, '2026-01-02', '10:20:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:42:54', '2026-01-29 09:42:54', NULL, NULL, NULL, NULL, NULL, NULL),
(91, 75, '2026-01-03', NULL, NULL, 0.00, 0.00, 'On Leave', NULL, NULL, '2026-01-29 09:43:24', '2026-01-29 09:45:27', NULL, NULL, NULL, NULL, NULL, NULL),
(92, 75, '2026-01-05', '10:00:00', '06:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:43:51', '2026-01-29 09:43:51', NULL, NULL, NULL, NULL, NULL, NULL),
(94, 75, '2026-01-06', '09:30:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:44:33', '2026-01-29 09:44:33', NULL, NULL, NULL, NULL, NULL, NULL),
(96, 75, '2026-01-07', '09:45:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:45:13', '2026-01-29 09:45:13', NULL, NULL, NULL, NULL, NULL, NULL),
(99, 75, '2026-01-08', '10:00:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:45:55', '2026-01-29 09:45:55', NULL, NULL, NULL, NULL, NULL, NULL),
(101, 75, '2026-01-09', '10:15:00', '06:45:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:46:44', '2026-01-29 09:46:44', NULL, NULL, NULL, NULL, NULL, NULL),
(102, 75, '2026-01-10', '09:45:00', '06:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:47:17', '2026-01-29 09:47:17', NULL, NULL, NULL, NULL, NULL, NULL),
(106, 75, '2026-01-12', NULL, NULL, 0.00, 0.00, 'Absent', NULL, NULL, '2026-01-29 09:47:55', '2026-01-29 09:47:55', NULL, NULL, NULL, NULL, NULL, NULL),
(108, 75, '2026-01-13', '10:00:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:48:24', '2026-01-29 09:48:24', NULL, NULL, NULL, NULL, NULL, NULL),
(110, 75, '2026-01-14', '09:30:00', '05:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:48:54', '2026-01-29 09:48:54', NULL, NULL, NULL, NULL, NULL, NULL),
(111, 75, '2026-01-15', '09:30:00', '06:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:49:17', '2026-01-29 09:49:17', NULL, NULL, NULL, NULL, NULL, NULL),
(112, 75, '2026-01-16', '10:00:00', '06:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:49:37', '2026-01-29 09:49:37', NULL, NULL, NULL, NULL, NULL, NULL),
(114, 75, '2026-01-17', '10:00:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:50:09', '2026-01-29 09:50:09', NULL, NULL, NULL, NULL, NULL, NULL),
(116, 75, '2026-01-19', '10:20:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:50:44', '2026-01-29 09:50:44', NULL, NULL, NULL, NULL, NULL, NULL),
(117, 75, '2026-01-20', '09:50:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:51:18', '2026-01-29 09:51:18', NULL, NULL, NULL, NULL, NULL, NULL),
(118, 75, '2026-01-21', '09:30:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:51:53', '2026-01-29 09:51:53', NULL, NULL, NULL, NULL, NULL, NULL),
(119, 75, '2026-01-22', NULL, NULL, 0.00, 0.00, 'Absent', NULL, NULL, '2026-01-29 09:52:12', '2026-01-29 09:52:12', NULL, NULL, NULL, NULL, NULL, NULL),
(120, 75, '2026-01-23', '09:33:00', '06:30:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:52:43', '2026-01-29 09:52:43', NULL, NULL, NULL, NULL, NULL, NULL),
(122, 75, '2026-01-30', '10:30:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-29 09:53:11', '2026-01-29 09:53:11', NULL, NULL, NULL, NULL, NULL, NULL),
(123, 75, '2026-01-31', '09:30:00', '06:00:00', 0.00, 0.00, 'Absent', NULL, NULL, '2026-01-29 09:53:37', '2026-01-29 09:53:37', NULL, NULL, NULL, NULL, NULL, NULL),
(124, 74, '2026-01-30', '16:24:55', '16:25:01', 0.00, 0.00, 'Half Day', NULL, NULL, '2026-01-30 15:24:55', '2026-01-30 15:25:01', NULL, NULL, NULL, NULL, NULL, NULL),
(125, 74, '2026-01-31', '05:08:19', '12:22:02', 7.23, 0.00, 'Present', NULL, NULL, '2026-01-31 04:08:19', '2026-01-31 11:22:02', 18.57430450, 73.70492450, 18.57430450, 73.70492450, 0.00, 0.00),
(126, 73, '2026-01-31', '05:15:27', NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-31 04:15:27', '2026-01-31 04:15:27', 18.57430450, 73.70492450, NULL, NULL, 0.00, NULL),
(130, 70, '2026-01-31', '09:22:00', NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-31 04:16:39', '2026-01-31 04:16:39', NULL, NULL, NULL, NULL, NULL, NULL),
(132, 70, '2026-01-01', '09:30:00', '20:19:00', 10.82, 0.00, 'Present', NULL, NULL, '2026-01-31 04:21:36', '2026-01-31 04:21:36', NULL, NULL, NULL, NULL, NULL, NULL),
(135, 62, '2026-01-30', NULL, NULL, 0.00, 0.00, 'On Leave', NULL, NULL, '2026-01-31 04:24:23', '2026-01-31 04:24:23', NULL, NULL, NULL, NULL, NULL, NULL),
(136, 65, '2026-01-30', '09:53:00', '07:00:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-31 04:24:23', '2026-01-31 04:24:23', NULL, NULL, NULL, NULL, NULL, NULL),
(141, 65, '2026-01-31', '09:49:00', NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-01-31 04:27:35', '2026-01-31 04:29:44', NULL, NULL, NULL, NULL, NULL, NULL),
(145, 70, '2026-01-30', '09:27:00', '07:57:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-31 04:27:51', '2026-01-31 04:27:51', NULL, NULL, NULL, NULL, NULL, NULL),
(158, 71, '2026-01-24', '10:00:00', '00:04:00', 0.00, 0.00, 'Present', NULL, NULL, '2026-01-31 04:34:41', '2026-01-31 04:34:41', NULL, NULL, NULL, NULL, NULL, NULL),
(161, 69, '2026-01-30', '09:30:00', '10:06:00', 0.60, 0.00, 'Present', NULL, NULL, '2026-01-31 04:36:19', '2026-01-31 04:36:19', NULL, NULL, NULL, NULL, NULL, NULL),
(171, 74, '2026-02-01', '04:55:46', NULL, 0.00, 0.00, 'Present', NULL, NULL, '2026-02-01 03:55:46', '2026-02-01 03:55:46', 18.57418550, 73.70455900, NULL, NULL, 40.73, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_settings`
--

CREATE TABLE `attendance_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_settings`
--

INSERT INTO `attendance_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'office_latitude', '18.57430450', '2026-01-31 03:54:15'),
(2, 'office_longitude', '73.70492450', '2026-01-31 03:54:15'),
(3, 'allowed_radius', '100', '2026-01-30 16:03:06'),
(4, 'location_required', '1', '2026-01-31 03:54:15'),
(5, 'office_name', 'Main Office', '2026-01-30 16:03:06');

-- --------------------------------------------------------

--
-- Table structure for table `bom_items`
--

CREATE TABLE `bom_items` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `component_part_no` varchar(50) NOT NULL,
  `qty` decimal(10,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bom_items`
--

INSERT INTO `bom_items` (`id`, `bom_id`, `component_part_no`, `qty`) VALUES
(1, 1, '22005001', 2.000),
(6, 5, '44005643', 1.000),
(65, 17, '17005066', 1.000),
(66, 17, '12005227', 1.000),
(67, 17, '12005208', 1.000),
(68, 17, '12005049', 1.000),
(69, 17, '17005067', 1.000),
(70, 17, '12005002', 2.000),
(71, 17, '72005208', 1.000),
(72, 17, '12005008', 1.000),
(73, 17, '12005231', 1.000),
(74, 17, '72005168', 1.000),
(75, 17, '17005022', 1.000),
(76, 17, '17005018', 1.000),
(77, 17, '17005023', 1.000),
(78, 17, '17005024', 1.000),
(79, 17, '12005086', 1.000),
(80, 17, '62005137', 1.000),
(81, 17, '12005005', 1.000),
(82, 17, '52005085', 1.000),
(91, 16, '11005089', 3.000),
(92, 16, '99005038', 1.000),
(93, 16, '11005143', 1.000),
(94, 16, '11005144', 2.000),
(95, 18, '46005077', 1.000),
(96, 18, '46005089', 1.000),
(97, 19, '13005001', 1.000),
(99, 20, '13005002', 1.000),
(100, 21, '99005002', 1.000),
(101, 21, '11005099', 1.000),
(102, 21, '11005089', 1.000),
(103, 21, '11005096', 1.000),
(104, 22, '52005024', 1.000),
(105, 22, '17005011', 1.000),
(106, 22, '17005068', 1.000),
(107, 22, '17005066', 1.000),
(108, 23, '46005077', 1.000),
(109, 23, '46005089', 1.000),
(110, 25, '82005255', 1.000),
(111, 25, '82005278', 0.200),
(112, 26, '93005015', 6.000),
(113, 26, '82005343', 1.000),
(114, 26, '92005307', 1.000),
(115, 28, '32005653', 1.000),
(116, 28, '72005014', 2.000),
(117, 28, '72005111', 2.000),
(118, 28, '72005001', 8.000),
(121, 29, '82005431', 200.000),
(122, 29, '82005211', 2.000),
(125, 30, '32005124', 1.000),
(126, 30, '72005044', 2.000),
(127, 31, '32005852', 1.000),
(128, 31, '32005853', 1.000);

-- --------------------------------------------------------

--
-- Table structure for table `bom_master`
--

CREATE TABLE `bom_master` (
  `id` int(11) NOT NULL,
  `bom_no` varchar(50) NOT NULL,
  `parent_part_no` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bom_master`
--

INSERT INTO `bom_master` (`id`, `bom_no`, `parent_part_no`, `description`, `status`, `created_at`) VALUES
(16, 'YID-167', 'YID-167', 'Customer Specifics product', 'active', '2026-01-25 23:06:59'),
(17, '99005038', '99005038', 'Assembly Finished Good', 'active', '2026-01-25 23:22:35'),
(18, '52005085', '52005085', 'VENTIALTOR ASSEMBLY', 'active', '2026-01-25 23:48:47'),
(19, 'YID-044', 'YID-044', 'Trading items', 'active', '2026-01-27 14:15:19'),
(20, 'YID-030', 'YID-030', 'Trading Items', 'active', '2026-01-27 14:15:59'),
(21, 'YID-003', 'YID-003', 'icu ventilator', 'active', '2026-01-28 19:25:33'),
(22, '99005002', '99005002', 'icu ventilator', 'active', '2026-01-28 19:27:10'),
(23, '52005024', '52005024', 'ventilator', 'active', '2026-01-28 19:27:56'),
(25, '83005101', '83005101', '', 'active', '2026-01-29 14:06:02'),
(26, '91005005', '91005005', '', 'active', '2026-01-29 14:06:04'),
(28, '44005001', '44005001', '', 'active', '2026-01-29 14:26:04'),
(29, '83005053', '83005053', 'wiring harness', 'active', '2026-01-30 08:50:46'),
(30, '44005911', '44005911', '', 'active', '2026-01-30 10:22:19'),
(31, '44005733', '44005733', '', 'active', '2026-01-30 10:27:52');

-- --------------------------------------------------------

--
-- Table structure for table `campaign_expenses`
--

CREATE TABLE `campaign_expenses` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `vendor` varchar(150) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_leads`
--

CREATE TABLE `campaign_leads` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `organization` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `interested_catalogs` text DEFAULT NULL,
  `interest_level` enum('Hot','Warm','Cold') DEFAULT 'Warm',
  `follow_up_date` date DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `status` enum('New','Contacted','Interested','Converted','Lost') DEFAULT 'New',
  `converted_to_customer_id` int(11) DEFAULT NULL,
  `order_value` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_types`
--

CREATE TABLE `campaign_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaign_types`
--

INSERT INTO `campaign_types` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'CME', 'Continuing Medical Education', 1),
(2, 'Trade Show', 'Industry trade shows and exhibitions', 1),
(3, 'Product Launch', 'New product launch events', 1),
(4, 'Seminar', 'Educational seminars and workshops', 1),
(5, 'Conference', 'Medical/Industry conferences', 1),
(6, 'Webinar', 'Online webinars and presentations', 1),
(7, 'Demo', 'Product demonstration events', 1),
(8, 'Road Show', 'Multi-location promotional events', 1),
(9, 'Hospital Visit', 'Direct hospital/clinic visits', 1),
(10, 'Digital Campaign', 'Online/Social media campaigns', 1);

-- --------------------------------------------------------

--
-- Table structure for table `catalog_categories`
--

CREATE TABLE `catalog_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `catalog_categories`
--

INSERT INTO `catalog_categories` (`id`, `name`, `description`, `parent_id`, `created_at`) VALUES
(1, 'Medical Equipment', NULL, NULL, '2026-01-18 14:57:12'),
(2, 'Laboratory Instruments', NULL, NULL, '2026-01-18 14:57:12'),
(3, 'Diagnostic Devices', NULL, NULL, '2026-01-18 14:57:12'),
(4, 'Surgical Instruments', NULL, NULL, '2026-01-18 14:57:12'),
(5, 'Consumables', NULL, NULL, '2026-01-18 14:57:12'),
(6, 'Software Solutions', NULL, NULL, '2026-01-18 14:57:12'),
(7, 'Services', NULL, NULL, '2026-01-18 14:57:12');

-- --------------------------------------------------------

--
-- Table structure for table `change_requests`
--

CREATE TABLE `change_requests` (
  `id` int(11) NOT NULL,
  `eco_no` varchar(30) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `change_type` enum('Design Change','Material Change','Process Change','Document Change','Supplier Change','Specification Change','Other') NOT NULL,
  `priority` enum('Critical','High','Medium','Low') DEFAULT 'Medium',
  `reason_for_change` text NOT NULL,
  `description` text DEFAULT NULL,
  `current_state` text DEFAULT NULL,
  `proposed_change` text DEFAULT NULL,
  `impact_quality` text DEFAULT NULL,
  `impact_cost` text DEFAULT NULL,
  `impact_schedule` text DEFAULT NULL,
  `impact_other` text DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `status` enum('Draft','Submitted','Under Review','Approved','Rejected','Implemented','Verified','Closed','Cancelled') DEFAULT 'Draft',
  `requested_by` varchar(100) DEFAULT NULL,
  `requested_user_id` int(11) DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `review_comments` text DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `approval_comments` text DEFAULT NULL,
  `implementation_plan` text DEFAULT NULL,
  `implementation_date` date DEFAULT NULL,
  `implemented_by` varchar(100) DEFAULT NULL,
  `verification_required` tinyint(1) DEFAULT 1,
  `verified_by` varchar(100) DEFAULT NULL,
  `verification_date` date DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `closure_date` date DEFAULT NULL,
  `closure_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `city_name` varchar(100) NOT NULL,
  `state_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `city_name`, `state_id`, `is_active`, `created_at`) VALUES
(1, 'Mumbai', 14, 1, '2026-01-21 13:01:30'),
(2, 'Pune', 14, 1, '2026-01-21 13:01:30'),
(3, 'Nagpur', 14, 1, '2026-01-21 13:01:30'),
(4, 'Nashik', 14, 1, '2026-01-21 13:01:30'),
(5, 'Aurangabad', 14, 1, '2026-01-21 13:01:30'),
(6, 'Thane', 14, 1, '2026-01-21 13:01:30'),
(7, 'Solapur', 14, 1, '2026-01-21 13:01:30'),
(8, 'Kolhapur', 14, 1, '2026-01-21 13:01:30'),
(9, 'Amravati', 14, 1, '2026-01-21 13:01:30'),
(10, 'Navi Mumbai', 14, 1, '2026-01-21 13:01:30'),
(11, 'Sangli', 14, 1, '2026-01-21 13:01:30'),
(12, 'Jalgaon', 14, 1, '2026-01-21 13:01:30'),
(13, 'Akola', 14, 1, '2026-01-21 13:01:30'),
(14, 'Latur', 14, 1, '2026-01-21 13:01:30'),
(15, 'Dhule', 14, 1, '2026-01-21 13:01:30'),
(16, 'Ahmednagar', 14, 1, '2026-01-21 13:01:30'),
(17, 'Chandrapur', 14, 1, '2026-01-21 13:01:30'),
(18, 'Parbhani', 14, 1, '2026-01-21 13:01:30'),
(19, 'Ichalkaranji', 14, 1, '2026-01-21 13:01:30'),
(20, 'Jalna', 14, 1, '2026-01-21 13:01:30'),
(21, 'Ahmedabad', 7, 1, '2026-01-21 13:01:30'),
(22, 'Surat', 7, 1, '2026-01-21 13:01:30'),
(23, 'Vadodara', 7, 1, '2026-01-21 13:01:30'),
(24, 'Rajkot', 7, 1, '2026-01-21 13:01:30'),
(25, 'Bhavnagar', 7, 1, '2026-01-21 13:01:30'),
(26, 'Jamnagar', 7, 1, '2026-01-21 13:01:30'),
(27, 'Junagadh', 7, 1, '2026-01-21 13:01:30'),
(28, 'Gandhinagar', 7, 1, '2026-01-21 13:01:30'),
(29, 'Anand', 7, 1, '2026-01-21 13:01:30'),
(30, 'Nadiad', 7, 1, '2026-01-21 13:01:30'),
(31, 'Morbi', 7, 1, '2026-01-21 13:01:30'),
(32, 'Mehsana', 7, 1, '2026-01-21 13:01:30'),
(33, 'Bharuch', 7, 1, '2026-01-21 13:01:30'),
(34, 'Vapi', 7, 1, '2026-01-21 13:01:30'),
(35, 'Navsari', 7, 1, '2026-01-21 13:01:30'),
(36, 'Veraval', 7, 1, '2026-01-21 13:01:30'),
(37, 'Porbandar', 7, 1, '2026-01-21 13:01:30'),
(38, 'Godhra', 7, 1, '2026-01-21 13:01:30'),
(39, 'Bhuj', 7, 1, '2026-01-21 13:01:30'),
(40, 'Palanpur', 7, 1, '2026-01-21 13:01:30'),
(41, 'Bengaluru', 11, 1, '2026-01-21 13:01:30'),
(42, 'Mysuru', 11, 1, '2026-01-21 13:01:30'),
(43, 'Mangaluru', 11, 1, '2026-01-21 13:01:30'),
(44, 'Hubli', 11, 1, '2026-01-21 13:01:30'),
(45, 'Dharwad', 11, 1, '2026-01-21 13:01:30'),
(46, 'Belgaum', 11, 1, '2026-01-21 13:01:30'),
(47, 'Gulbarga', 11, 1, '2026-01-21 13:01:30'),
(48, 'Davanagere', 11, 1, '2026-01-21 13:01:30'),
(49, 'Bellary', 11, 1, '2026-01-21 13:01:30'),
(50, 'Shimoga', 11, 1, '2026-01-21 13:01:30'),
(51, 'Tumkur', 11, 1, '2026-01-21 13:01:30'),
(52, 'Raichur', 11, 1, '2026-01-21 13:01:30'),
(53, 'Bijapur', 11, 1, '2026-01-21 13:01:30'),
(54, 'Hospet', 11, 1, '2026-01-21 13:01:30'),
(55, 'Hassan', 11, 1, '2026-01-21 13:01:30'),
(56, 'Gadag', 11, 1, '2026-01-21 13:01:30'),
(57, 'Udupi', 11, 1, '2026-01-21 13:01:30'),
(58, 'Robertson Pet', 11, 1, '2026-01-21 13:01:30'),
(59, 'Bhadravati', 11, 1, '2026-01-21 13:01:30'),
(60, 'Chitradurga', 11, 1, '2026-01-21 13:01:30'),
(61, 'Chennai', 23, 1, '2026-01-21 13:01:30'),
(62, 'Coimbatore', 23, 1, '2026-01-21 13:01:30'),
(63, 'Madurai', 23, 1, '2026-01-21 13:01:30'),
(64, 'Tiruchirappalli', 23, 1, '2026-01-21 13:01:30'),
(65, 'Salem', 23, 1, '2026-01-21 13:01:30'),
(66, 'Tirunelveli', 23, 1, '2026-01-21 13:01:30'),
(67, 'Tiruppur', 23, 1, '2026-01-21 13:01:30'),
(68, 'Ranipet', 23, 1, '2026-01-21 13:01:30'),
(69, 'Nagercoil', 23, 1, '2026-01-21 13:01:30'),
(70, 'Thanjavur', 23, 1, '2026-01-21 13:01:30'),
(71, 'Vellore', 23, 1, '2026-01-21 13:01:30'),
(72, 'Kancheepuram', 23, 1, '2026-01-21 13:01:30'),
(73, 'Erode', 23, 1, '2026-01-21 13:01:30'),
(74, 'Tiruvannamalai', 23, 1, '2026-01-21 13:01:30'),
(75, 'Pollachi', 23, 1, '2026-01-21 13:01:30'),
(76, 'Rajapalayam', 23, 1, '2026-01-21 13:01:30'),
(77, 'Sivakasi', 23, 1, '2026-01-21 13:01:30'),
(78, 'Pudukkottai', 23, 1, '2026-01-21 13:01:30'),
(79, 'Neyveli', 23, 1, '2026-01-21 13:01:30'),
(80, 'Nagapattinam', 23, 1, '2026-01-21 13:01:30'),
(81, 'Hyderabad', 24, 1, '2026-01-21 13:01:30'),
(82, 'Warangal', 24, 1, '2026-01-21 13:01:30'),
(83, 'Nizamabad', 24, 1, '2026-01-21 13:01:30'),
(84, 'Khammam', 24, 1, '2026-01-21 13:01:30'),
(85, 'Karimnagar', 24, 1, '2026-01-21 13:01:30'),
(86, 'Ramagundam', 24, 1, '2026-01-21 13:01:30'),
(87, 'Mahbubnagar', 24, 1, '2026-01-21 13:01:30'),
(88, 'Nalgonda', 24, 1, '2026-01-21 13:01:30'),
(89, 'Adilabad', 24, 1, '2026-01-21 13:01:30'),
(90, 'Suryapet', 24, 1, '2026-01-21 13:01:30'),
(91, 'Miryalaguda', 24, 1, '2026-01-21 13:01:30'),
(92, 'Siddipet', 24, 1, '2026-01-21 13:01:30'),
(93, 'Jagtial', 24, 1, '2026-01-21 13:01:30'),
(94, 'Mancherial', 24, 1, '2026-01-21 13:01:30'),
(95, 'Nirmal', 24, 1, '2026-01-21 13:01:30'),
(96, 'Visakhapatnam', 1, 1, '2026-01-21 13:01:30'),
(97, 'Vijayawada', 1, 1, '2026-01-21 13:01:30'),
(98, 'Guntur', 1, 1, '2026-01-21 13:01:30'),
(99, 'Nellore', 1, 1, '2026-01-21 13:01:30'),
(100, 'Kurnool', 1, 1, '2026-01-21 13:01:30'),
(101, 'Rajahmundry', 1, 1, '2026-01-21 13:01:30'),
(102, 'Kakinada', 1, 1, '2026-01-21 13:01:30'),
(103, 'Tirupati', 1, 1, '2026-01-21 13:01:30'),
(104, 'Kadapa', 1, 1, '2026-01-21 13:01:30'),
(105, 'Anantapur', 1, 1, '2026-01-21 13:01:30'),
(106, 'Vizianagaram', 1, 1, '2026-01-21 13:01:30'),
(107, 'Eluru', 1, 1, '2026-01-21 13:01:30'),
(108, 'Ongole', 1, 1, '2026-01-21 13:01:30'),
(109, 'Nandyal', 1, 1, '2026-01-21 13:01:30'),
(110, 'Machilipatnam', 1, 1, '2026-01-21 13:01:30'),
(111, 'Adoni', 1, 1, '2026-01-21 13:01:30'),
(112, 'Tenali', 1, 1, '2026-01-21 13:01:30'),
(113, 'Proddatur', 1, 1, '2026-01-21 13:01:30'),
(114, 'Chittoor', 1, 1, '2026-01-21 13:01:30'),
(115, 'Hindupur', 1, 1, '2026-01-21 13:01:30'),
(116, 'Thiruvananthapuram', 12, 1, '2026-01-21 13:01:30'),
(117, 'Kochi', 12, 1, '2026-01-21 13:01:30'),
(118, 'Kozhikode', 12, 1, '2026-01-21 13:01:30'),
(119, 'Thrissur', 12, 1, '2026-01-21 13:01:30'),
(120, 'Kollam', 12, 1, '2026-01-21 13:01:30'),
(121, 'Palakkad', 12, 1, '2026-01-21 13:01:30'),
(122, 'Alappuzha', 12, 1, '2026-01-21 13:01:30'),
(123, 'Kannur', 12, 1, '2026-01-21 13:01:30'),
(124, 'Kottayam', 12, 1, '2026-01-21 13:01:30'),
(125, 'Kasaragod', 12, 1, '2026-01-21 13:01:30'),
(126, 'Malappuram', 12, 1, '2026-01-21 13:01:30'),
(127, 'Pathanamthitta', 12, 1, '2026-01-21 13:01:30'),
(128, 'Idukki', 12, 1, '2026-01-21 13:01:30'),
(129, 'Wayanad', 12, 1, '2026-01-21 13:01:30'),
(130, 'Ernakulam', 12, 1, '2026-01-21 13:01:30'),
(131, 'New Delhi', 29, 1, '2026-01-21 13:01:30'),
(132, 'North Delhi', 29, 1, '2026-01-21 13:01:30'),
(133, 'South Delhi', 29, 1, '2026-01-21 13:01:30'),
(134, 'East Delhi', 29, 1, '2026-01-21 13:01:30'),
(135, 'West Delhi', 29, 1, '2026-01-21 13:01:30'),
(136, 'Central Delhi', 29, 1, '2026-01-21 13:01:30'),
(137, 'North East Delhi', 29, 1, '2026-01-21 13:01:30'),
(138, 'North West Delhi', 29, 1, '2026-01-21 13:01:30'),
(139, 'South East Delhi', 29, 1, '2026-01-21 13:01:30'),
(140, 'South West Delhi', 29, 1, '2026-01-21 13:01:30'),
(141, 'Shahdara', 29, 1, '2026-01-21 13:01:30'),
(142, 'Lucknow', 26, 1, '2026-01-21 13:01:30'),
(143, 'Kanpur', 26, 1, '2026-01-21 13:01:30'),
(144, 'Ghaziabad', 26, 1, '2026-01-21 13:01:30'),
(145, 'Agra', 26, 1, '2026-01-21 13:01:30'),
(146, 'Varanasi', 26, 1, '2026-01-21 13:01:30'),
(147, 'Meerut', 26, 1, '2026-01-21 13:01:30'),
(148, 'Prayagraj', 26, 1, '2026-01-21 13:01:30'),
(149, 'Bareilly', 26, 1, '2026-01-21 13:01:30'),
(150, 'Aligarh', 26, 1, '2026-01-21 13:01:30'),
(151, 'Moradabad', 26, 1, '2026-01-21 13:01:30'),
(152, 'Saharanpur', 26, 1, '2026-01-21 13:01:30'),
(153, 'Gorakhpur', 26, 1, '2026-01-21 13:01:30'),
(154, 'Noida', 26, 1, '2026-01-21 13:01:30'),
(155, 'Firozabad', 26, 1, '2026-01-21 13:01:30'),
(156, 'Jhansi', 26, 1, '2026-01-21 13:01:30'),
(157, 'Muzaffarnagar', 26, 1, '2026-01-21 13:01:30'),
(158, 'Mathura', 26, 1, '2026-01-21 13:01:30'),
(159, 'Rampur', 26, 1, '2026-01-21 13:01:30'),
(160, 'Shahjahanpur', 26, 1, '2026-01-21 13:01:30'),
(161, 'Farrukhabad', 26, 1, '2026-01-21 13:01:30'),
(162, 'Mau', 26, 1, '2026-01-21 13:01:30'),
(163, 'Hapur', 26, 1, '2026-01-21 13:01:30'),
(164, 'Etawah', 26, 1, '2026-01-21 13:01:30'),
(165, 'Mirzapur', 26, 1, '2026-01-21 13:01:30'),
(166, 'Bulandshahr', 26, 1, '2026-01-21 13:01:30'),
(167, 'Sambhal', 26, 1, '2026-01-21 13:01:30'),
(168, 'Amroha', 26, 1, '2026-01-21 13:01:30'),
(169, 'Hardoi', 26, 1, '2026-01-21 13:01:30'),
(170, 'Fatehpur', 26, 1, '2026-01-21 13:01:30'),
(171, 'Raebareli', 26, 1, '2026-01-21 13:01:30'),
(172, 'Jaipur', 21, 1, '2026-01-21 13:01:30'),
(173, 'Jodhpur', 21, 1, '2026-01-21 13:01:30'),
(174, 'Kota', 21, 1, '2026-01-21 13:01:30'),
(175, 'Bikaner', 21, 1, '2026-01-21 13:01:30'),
(176, 'Ajmer', 21, 1, '2026-01-21 13:01:30'),
(177, 'Udaipur', 21, 1, '2026-01-21 13:01:30'),
(178, 'Bhilwara', 21, 1, '2026-01-21 13:01:30'),
(179, 'Alwar', 21, 1, '2026-01-21 13:01:30'),
(180, 'Bharatpur', 21, 1, '2026-01-21 13:01:30'),
(181, 'Sikar', 21, 1, '2026-01-21 13:01:30'),
(182, 'Pali', 21, 1, '2026-01-21 13:01:30'),
(183, 'Sri Ganganagar', 21, 1, '2026-01-21 13:01:30'),
(184, 'Kishangarh', 21, 1, '2026-01-21 13:01:30'),
(185, 'Baran', 21, 1, '2026-01-21 13:01:30'),
(186, 'Dhaulpur', 21, 1, '2026-01-21 13:01:30'),
(187, 'Tonk', 21, 1, '2026-01-21 13:01:30'),
(188, 'Indore', 13, 1, '2026-01-21 13:01:30'),
(189, 'Bhopal', 13, 1, '2026-01-21 13:01:30'),
(190, 'Jabalpur', 13, 1, '2026-01-21 13:01:30'),
(191, 'Gwalior', 13, 1, '2026-01-21 13:01:30'),
(192, 'Ujjain', 13, 1, '2026-01-21 13:01:30'),
(193, 'Sagar', 13, 1, '2026-01-21 13:01:30'),
(194, 'Dewas', 13, 1, '2026-01-21 13:01:30'),
(195, 'Satna', 13, 1, '2026-01-21 13:01:30'),
(196, 'Ratlam', 13, 1, '2026-01-21 13:01:30'),
(197, 'Rewa', 13, 1, '2026-01-21 13:01:30'),
(198, 'Murwara', 13, 1, '2026-01-21 13:01:30'),
(199, 'Singrauli', 13, 1, '2026-01-21 13:01:30'),
(200, 'Burhanpur', 13, 1, '2026-01-21 13:01:30'),
(201, 'Khandwa', 13, 1, '2026-01-21 13:01:30'),
(202, 'Bhind', 13, 1, '2026-01-21 13:01:30'),
(203, 'Chhindwara', 13, 1, '2026-01-21 13:01:30'),
(204, 'Guna', 13, 1, '2026-01-21 13:01:30'),
(205, 'Shivpuri', 13, 1, '2026-01-21 13:01:30'),
(206, 'Vidisha', 13, 1, '2026-01-21 13:01:30'),
(207, 'Chhatarpur', 13, 1, '2026-01-21 13:01:30'),
(208, 'Kolkata', 28, 1, '2026-01-21 13:01:30'),
(209, 'Howrah', 28, 1, '2026-01-21 13:01:30'),
(210, 'Durgapur', 28, 1, '2026-01-21 13:01:30'),
(211, 'Asansol', 28, 1, '2026-01-21 13:01:30'),
(212, 'Siliguri', 28, 1, '2026-01-21 13:01:30'),
(213, 'Bardhaman', 28, 1, '2026-01-21 13:01:30'),
(214, 'Malda', 28, 1, '2026-01-21 13:01:30'),
(215, 'Baharampur', 28, 1, '2026-01-21 13:01:30'),
(216, 'Habra', 28, 1, '2026-01-21 13:01:30'),
(217, 'Kharagpur', 28, 1, '2026-01-21 13:01:30'),
(218, 'Shantipur', 28, 1, '2026-01-21 13:01:30'),
(219, 'Dankuni', 28, 1, '2026-01-21 13:01:30'),
(220, 'Dhulian', 28, 1, '2026-01-21 13:01:30'),
(221, 'Ranaghat', 28, 1, '2026-01-21 13:01:30'),
(222, 'Haldia', 28, 1, '2026-01-21 13:01:30'),
(223, 'Raiganj', 28, 1, '2026-01-21 13:01:30'),
(224, 'Krishnanagar', 28, 1, '2026-01-21 13:01:30'),
(225, 'Nabadwip', 28, 1, '2026-01-21 13:01:30'),
(226, 'Medinipur', 28, 1, '2026-01-21 13:01:30'),
(227, 'Jalpaiguri', 28, 1, '2026-01-21 13:01:30'),
(228, 'Patna', 4, 1, '2026-01-21 13:01:30'),
(229, 'Gaya', 4, 1, '2026-01-21 13:01:30'),
(230, 'Bhagalpur', 4, 1, '2026-01-21 13:01:30'),
(231, 'Muzaffarpur', 4, 1, '2026-01-21 13:01:30'),
(232, 'Purnia', 4, 1, '2026-01-21 13:01:30'),
(233, 'Darbhanga', 4, 1, '2026-01-21 13:01:30'),
(234, 'Bihar Sharif', 4, 1, '2026-01-21 13:01:30'),
(235, 'Arrah', 4, 1, '2026-01-21 13:01:30'),
(236, 'Begusarai', 4, 1, '2026-01-21 13:01:30'),
(237, 'Katihar', 4, 1, '2026-01-21 13:01:30'),
(238, 'Munger', 4, 1, '2026-01-21 13:01:30'),
(239, 'Chhapra', 4, 1, '2026-01-21 13:01:30'),
(240, 'Samastipur', 4, 1, '2026-01-21 13:01:30'),
(241, 'Hajipur', 4, 1, '2026-01-21 13:01:30'),
(242, 'Sasaram', 4, 1, '2026-01-21 13:01:30'),
(243, 'Dehri', 4, 1, '2026-01-21 13:01:30'),
(244, 'Siwan', 4, 1, '2026-01-21 13:01:30'),
(245, 'Motihari', 4, 1, '2026-01-21 13:01:30'),
(246, 'Nawada', 4, 1, '2026-01-21 13:01:30'),
(247, 'Bagaha', 4, 1, '2026-01-21 13:01:30'),
(248, 'Ludhiana', 20, 1, '2026-01-21 13:01:30'),
(249, 'Amritsar', 20, 1, '2026-01-21 13:01:30'),
(250, 'Jalandhar', 20, 1, '2026-01-21 13:01:30'),
(251, 'Patiala', 20, 1, '2026-01-21 13:01:30'),
(252, 'Bathinda', 20, 1, '2026-01-21 13:01:30'),
(253, 'Mohali', 20, 1, '2026-01-21 13:01:30'),
(254, 'Pathankot', 20, 1, '2026-01-21 13:01:30'),
(255, 'Hoshiarpur', 20, 1, '2026-01-21 13:01:30'),
(256, 'Batala', 20, 1, '2026-01-21 13:01:30'),
(257, 'Moga', 20, 1, '2026-01-21 13:01:30'),
(258, 'Malerkotla', 20, 1, '2026-01-21 13:01:30'),
(259, 'Khanna', 20, 1, '2026-01-21 13:01:30'),
(260, 'Phagwara', 20, 1, '2026-01-21 13:01:30'),
(261, 'Muktsar', 20, 1, '2026-01-21 13:01:30'),
(262, 'Barnala', 20, 1, '2026-01-21 13:01:30'),
(263, 'Rajpura', 20, 1, '2026-01-21 13:01:30'),
(264, 'Firozpur', 20, 1, '2026-01-21 13:01:30'),
(265, 'Kapurthala', 20, 1, '2026-01-21 13:01:30'),
(266, 'Faridabad', 8, 1, '2026-01-21 13:01:30'),
(267, 'Gurgaon', 8, 1, '2026-01-21 13:01:30'),
(268, 'Panipat', 8, 1, '2026-01-21 13:01:30'),
(269, 'Ambala', 8, 1, '2026-01-21 13:01:30'),
(270, 'Yamunanagar', 8, 1, '2026-01-21 13:01:30'),
(271, 'Rohtak', 8, 1, '2026-01-21 13:01:30'),
(272, 'Hisar', 8, 1, '2026-01-21 13:01:30'),
(273, 'Karnal', 8, 1, '2026-01-21 13:01:30'),
(274, 'Sonipat', 8, 1, '2026-01-21 13:01:30'),
(275, 'Panchkula', 8, 1, '2026-01-21 13:01:30'),
(276, 'Bhiwani', 8, 1, '2026-01-21 13:01:30'),
(277, 'Sirsa', 8, 1, '2026-01-21 13:01:30'),
(278, 'Bahadurgarh', 8, 1, '2026-01-21 13:01:30'),
(279, 'Jind', 8, 1, '2026-01-21 13:01:30'),
(280, 'Thanesar', 8, 1, '2026-01-21 13:01:30'),
(281, 'Kaithal', 8, 1, '2026-01-21 13:01:30'),
(282, 'Rewari', 8, 1, '2026-01-21 13:01:30'),
(283, 'Palwal', 8, 1, '2026-01-21 13:01:30'),
(284, 'Bhubaneswar', 19, 1, '2026-01-21 13:01:30'),
(285, 'Cuttack', 19, 1, '2026-01-21 13:01:30'),
(286, 'Rourkela', 19, 1, '2026-01-21 13:01:30'),
(287, 'Brahmapur', 19, 1, '2026-01-21 13:01:30'),
(288, 'Sambalpur', 19, 1, '2026-01-21 13:01:30'),
(289, 'Puri', 19, 1, '2026-01-21 13:01:30'),
(290, 'Balasore', 19, 1, '2026-01-21 13:01:30'),
(291, 'Bhadrak', 19, 1, '2026-01-21 13:01:30'),
(292, 'Baripada', 19, 1, '2026-01-21 13:01:30'),
(293, 'Jharsuguda', 19, 1, '2026-01-21 13:01:30'),
(294, 'Jeypore', 19, 1, '2026-01-21 13:01:30'),
(295, 'Barbil', 19, 1, '2026-01-21 13:01:30'),
(296, 'Rayagada', 19, 1, '2026-01-21 13:01:30'),
(297, 'Paradip', 19, 1, '2026-01-21 13:01:30'),
(298, 'Ranchi', 10, 1, '2026-01-21 13:01:30'),
(299, 'Jamshedpur', 10, 1, '2026-01-21 13:01:30'),
(300, 'Dhanbad', 10, 1, '2026-01-21 13:01:30'),
(301, 'Bokaro Steel City', 10, 1, '2026-01-21 13:01:30'),
(302, 'Deoghar', 10, 1, '2026-01-21 13:01:30'),
(303, 'Hazaribagh', 10, 1, '2026-01-21 13:01:30'),
(304, 'Giridih', 10, 1, '2026-01-21 13:01:30'),
(305, 'Ramgarh', 10, 1, '2026-01-21 13:01:30'),
(306, 'Medininagar', 10, 1, '2026-01-21 13:01:30'),
(307, 'Chirkunda', 10, 1, '2026-01-21 13:01:30'),
(308, 'Guwahati', 3, 1, '2026-01-21 13:01:30'),
(309, 'Silchar', 3, 1, '2026-01-21 13:01:30'),
(310, 'Dibrugarh', 3, 1, '2026-01-21 13:01:30'),
(311, 'Jorhat', 3, 1, '2026-01-21 13:01:30'),
(312, 'Nagaon', 3, 1, '2026-01-21 13:01:30'),
(313, 'Tinsukia', 3, 1, '2026-01-21 13:01:30'),
(314, 'Tezpur', 3, 1, '2026-01-21 13:01:30'),
(315, 'Bongaigaon', 3, 1, '2026-01-21 13:01:30'),
(316, 'Dhubri', 3, 1, '2026-01-21 13:01:30'),
(317, 'Diphu', 3, 1, '2026-01-21 13:01:30'),
(318, 'Raipur', 5, 1, '2026-01-21 13:01:30'),
(319, 'Bhilai', 5, 1, '2026-01-21 13:01:30'),
(320, 'Bilaspur', 5, 1, '2026-01-21 13:01:30'),
(321, 'Korba', 5, 1, '2026-01-21 13:01:30'),
(322, 'Durg', 5, 1, '2026-01-21 13:01:30'),
(323, 'Rajnandgaon', 5, 1, '2026-01-21 13:01:30'),
(324, 'Raigarh', 5, 1, '2026-01-21 13:01:30'),
(325, 'Jagdalpur', 5, 1, '2026-01-21 13:01:30'),
(326, 'Ambikapur', 5, 1, '2026-01-21 13:01:30'),
(327, 'Chirmiri', 5, 1, '2026-01-21 13:01:30'),
(328, 'Dehradun', 27, 1, '2026-01-21 13:01:30'),
(329, 'Haridwar', 27, 1, '2026-01-21 13:01:30'),
(330, 'Roorkee', 27, 1, '2026-01-21 13:01:30'),
(331, 'Haldwani', 27, 1, '2026-01-21 13:01:30'),
(332, 'Rudrapur', 27, 1, '2026-01-21 13:01:30'),
(333, 'Kashipur', 27, 1, '2026-01-21 13:01:30'),
(334, 'Rishikesh', 27, 1, '2026-01-21 13:01:30'),
(335, 'Pithoragarh', 27, 1, '2026-01-21 13:01:30'),
(336, 'Ramnagar', 27, 1, '2026-01-21 13:01:30'),
(337, 'Nainital', 27, 1, '2026-01-21 13:01:30'),
(338, 'Shimla', 9, 1, '2026-01-21 13:01:30'),
(339, 'Solan', 9, 1, '2026-01-21 13:01:30'),
(340, 'Dharamshala', 9, 1, '2026-01-21 13:01:30'),
(341, 'Mandi', 9, 1, '2026-01-21 13:01:30'),
(342, 'Palampur', 9, 1, '2026-01-21 13:01:30'),
(343, 'Baddi', 9, 1, '2026-01-21 13:01:30'),
(344, 'Nahan', 9, 1, '2026-01-21 13:01:30'),
(345, 'Paonta Sahib', 9, 1, '2026-01-21 13:01:30'),
(346, 'Sundernagar', 9, 1, '2026-01-21 13:01:30'),
(347, 'Kullu', 9, 1, '2026-01-21 13:01:30'),
(348, 'Panaji', 6, 1, '2026-01-21 13:01:30'),
(349, 'Margao', 6, 1, '2026-01-21 13:01:30'),
(350, 'Vasco da Gama', 6, 1, '2026-01-21 13:01:30'),
(351, 'Mapusa', 6, 1, '2026-01-21 13:01:30'),
(352, 'Ponda', 6, 1, '2026-01-21 13:01:30'),
(353, 'Bicholim', 6, 1, '2026-01-21 13:01:30'),
(354, 'Curchorem', 6, 1, '2026-01-21 13:01:30'),
(355, 'Sanquelim', 6, 1, '2026-01-21 13:01:30'),
(356, 'Cuncolim', 6, 1, '2026-01-21 13:01:30'),
(357, 'Quepem', 6, 1, '2026-01-21 13:01:30'),
(358, 'Srinagar', 30, 1, '2026-01-21 13:01:30'),
(359, 'Jammu', 30, 1, '2026-01-21 13:01:30'),
(360, 'Anantnag', 30, 1, '2026-01-21 13:01:30'),
(361, 'Baramulla', 30, 1, '2026-01-21 13:01:30'),
(362, 'Sopore', 30, 1, '2026-01-21 13:01:30'),
(363, 'Kathua', 30, 1, '2026-01-21 13:01:30'),
(364, 'Udhampur', 30, 1, '2026-01-21 13:01:30'),
(365, 'Poonch', 30, 1, '2026-01-21 13:01:30'),
(366, 'Rajouri', 30, 1, '2026-01-21 13:01:30'),
(367, 'Kupwara', 30, 1, '2026-01-21 13:01:30'),
(368, 'Chandigarh', 33, 1, '2026-01-21 13:01:30'),
(369, 'Puducherry', 32, 1, '2026-01-21 13:01:30'),
(370, 'Karaikal', 32, 1, '2026-01-21 13:01:30'),
(371, 'Mahe', 32, 1, '2026-01-21 13:01:30'),
(372, 'Yanam', 32, 1, '2026-01-21 13:01:30');

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `company_name` varchar(255) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `gstin` varchar(50) DEFAULT NULL,
  `pan` varchar(50) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(150) DEFAULT NULL,
  `invoice_prefix` varchar(20) DEFAULT 'INV',
  `quote_prefix` varchar(20) DEFAULT 'QT',
  `po_prefix` varchar(20) DEFAULT 'PO',
  `terms_conditions` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `google_review_rating` decimal(2,1) DEFAULT NULL,
  `google_review_count` int(11) DEFAULT 0,
  `google_review_url` varchar(500) DEFAULT NULL,
  `google_place_id` varchar(100) DEFAULT NULL,
  `google_review_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_name`, `logo_path`, `address_line1`, `address_line2`, `city`, `state`, `pincode`, `country`, `phone`, `email`, `website`, `gstin`, `pan`, `bank_name`, `bank_account`, `bank_ifsc`, `bank_branch`, `invoice_prefix`, `quote_prefix`, `po_prefix`, `terms_conditions`, `updated_at`, `google_review_rating`, `google_review_count`, `google_review_url`, `google_place_id`, `google_review_updated_at`) VALUES
(1, 'Yashka Infotronics Pvt Ltd', 'uploads/company/logo_1768959545.png', 'Survey No 162/1 , Parkhi Heights', 'Maan Village', 'Pune', 'Maharashtra', '411057', 'India', '8669234153', 'yashkacontacts@gmail.com', 'yashka.io', '27AABCY0023B1Z0', 'AABCY0023B', 'ICICI', '022405003194', 'ICIC0000224', 'Delhi', 'INV', 'QT', 'PO', 'Njcndf', '2026-01-29 15:44:44', 4.3, 89, 'https://www.google.com/search?sca_esv=292c8ab72c42bfbf&sxsrf=ANbL-n6-6C7ssfd_hEhhCWhrR1swWgJ1ew:1769432127946&si=AL3DRZEsmMGCryMMFSHJ3StBhOdZ2-6yYkXd_doETEE1OR-qOdnWLkZXwvLvoh0PXt6gGonVifE8Y5wOlmkueHgZa-cdJ007gFRaR2bJ6M6M3yQk1qSN0scctyNwpc08bu9MYwEN2mUK4eQ5KIMTFvCrR0AGGf_2zA%3D%3D&q=Yashka+infotronics+Pvt+Ltd+Reviews&sa=X&ved=2ahUKEwjW2cKroKmSAxXXTGwGHUinFWMQ0bkNegQILRAH&biw=1745&bih=828&dpr=1.1&aic=0', NULL, '2026-01-26 18:27:53');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_status_history`
--

CREATE TABLE `complaint_status_history` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_status_history`
--

INSERT INTO `complaint_status_history` (`id`, `complaint_id`, `old_status`, `new_status`, `changed_by`, `remarks`, `changed_at`) VALUES
(1, 1, NULL, 'Open', NULL, 'Complaint registered', '2026-01-18 15:23:59'),
(2, 2, NULL, 'Assigned', NULL, 'Complaint registered', '2026-01-31 04:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `crm_leads`
--

CREATE TABLE `crm_leads` (
  `id` int(11) NOT NULL,
  `lead_no` varchar(50) NOT NULL,
  `customer_type` enum('B2B','B2C') NOT NULL DEFAULT 'B2B',
  `company_name` varchar(200) DEFAULT NULL,
  `contact_person` varchar(150) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `alt_phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `lead_status` enum('cold','warm','hot','converted','lost') DEFAULT 'cold',
  `converted_customer_id` int(11) DEFAULT NULL,
  `lead_source` varchar(100) DEFAULT NULL,
  `market_classification` varchar(50) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `buying_timeline` enum('immediate','1_month','3_months','6_months','1_year','uncertain') DEFAULT 'uncertain',
  `budget_range` varchar(100) DEFAULT NULL,
  `decision_maker` enum('yes','no','influencer') DEFAULT 'no',
  `notes` text DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `last_contact_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_leads`
--

INSERT INTO `crm_leads` (`id`, `lead_no`, `customer_type`, `company_name`, `contact_person`, `designation`, `phone`, `alt_phone`, `email`, `website`, `address1`, `address2`, `city`, `state`, `pincode`, `country`, `lead_status`, `converted_customer_id`, `lead_source`, `market_classification`, `industry`, `buying_timeline`, `budget_range`, `decision_maker`, `notes`, `assigned_to`, `assigned_user_id`, `next_followup_date`, `last_contact_date`, `created_at`, `updated_at`) VALUES
(1, 'LEAD-1', 'B2C', 'Unity Hospital', '7775823557', NULL, '07264800591', NULL, 'aryanaich008@gmail.com', NULL, 'Supriya Heights, Survey No. 162 Maan 1A/1', 'Megapolis Serenity, Hinjewadi Phase 3', NULL, NULL, '411057', 'India', 'hot', 6, 'Referral', NULL, NULL, 'immediate', '50', 'yes', 'assd', 'Dipankar Aich', 3, '2026-01-19', NULL, '2026-01-18 03:08:40', '2026-01-25 05:01:44'),
(2, 'LEAD-2', 'B2B', 'Unity Hospital1', 'asdad', 'Director', '07264800591', NULL, NULL, NULL, 'Supriya Heights, Survey No. 162 Maan 1A/1', 'Megapolis Serenity, Hinjewadi Phase 3', NULL, NULL, '411057', 'India', 'hot', 6, NULL, 'Private Hospitals', 'Hospital', 'uncertain', NULL, 'no', NULL, NULL, NULL, NULL, NULL, '2026-01-18 16:47:41', '2026-01-26 13:15:49'),
(3, 'LEAD-3', 'B2B', 'Advantec Medical Device', 'Animesh Ghose', NULL, '8763591234', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'India', 'hot', 4, 'Referral', 'Private Hospitals', NULL, '1_month', '300000', 'yes', 'hcds', 'Dipankar Aich', 3, '2026-01-22', NULL, '2026-01-21 05:12:43', '2026-01-27 08:38:14'),
(4, 'LEAD-4', 'B2B', 'Akal sirjana services', 'Akal', 'Owner', '7625982199', NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', NULL, NULL, '143001', 'India', 'converted', 28, NULL, NULL, 'Hospital', '1_month', '200000', 'yes', 'He need Moppet 8.5', 'Dipankar', NULL, '2026-01-23', NULL, '2026-01-21 16:40:32', '2026-01-25 07:30:32'),
(5, 'LEAD-5', 'B2B', 'Akal sirjana services', 'Akal', 'Owner', '7625982199', NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', NULL, NULL, '143001', 'India', 'hot', NULL, 'Existing Customer', NULL, 'Hospital', 'immediate', '100000', 'yes', 'gdgdg', 'Dipankar', NULL, '2026-01-23', NULL, '2026-01-23 02:09:41', '2026-01-23 02:10:13'),
(6, 'LEAD-6', 'B2B', 'ABC Hospital', 'Alpesh Doshi', 'Owner', '7020845499', NULL, NULL, NULL, 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', NULL, 'Maharashtra', '413208', 'India', 'converted', NULL, 'Existing Customer', NULL, 'Hospital', '1_month', '10000', 'yes', 'new', NULL, NULL, NULL, NULL, '2026-01-23 03:34:35', '2026-01-25 07:38:30'),
(7, 'LEAD-7', 'B2B', 'Medilife Multispeciality Hospital', 'Dr.Jagdish Jadhav', 'Owner', '9552530205', NULL, NULL, NULL, 'Vijay nagar, Kalewadi, Pune', 'Pune', NULL, 'Maharashtra', '411017', 'India', 'converted', NULL, 'Existing Customer', NULL, 'Hospital', '3_months', '150000', 'yes', 'ndtv', 'Dipankar', NULL, '2026-01-23', NULL, '2026-01-23 03:38:00', '2026-01-25 07:30:44'),
(8, 'LEAD-8', 'B2B', 'veena Healthcare', 'Bubhakarr kumar', NULL, '7310319555', NULL, NULL, NULL, 'Begusarai, , Begusarai', 'Begusarai', NULL, 'Bihar', NULL, 'India', 'hot', NULL, 'Existing Customer', NULL, NULL, 'uncertain', NULL, 'yes', 'dhdhdh', 'Dipankar', NULL, '2026-01-23', NULL, '2026-01-23 03:39:40', '2026-01-23 03:39:40'),
(9, 'LEAD-9', 'B2B', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', NULL, '9874438894', NULL, NULL, NULL, 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', NULL, 'West Bengal', '700010', 'India', 'hot', 12, 'Existing Customer', NULL, NULL, 'uncertain', NULL, 'yes', 'XVS9500 XL ORDER', 'Ms. Kishori Prakash Gadakh', NULL, '2026-01-24', '2026-01-23', '2026-01-23 10:33:45', '2026-01-24 01:27:17'),
(10, 'LEAD-10', 'B2B', 'ABC Hospital', 'Alpesh Doshi', NULL, '7020845499', NULL, NULL, NULL, 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', NULL, NULL, '413208', 'India', 'hot', NULL, 'Existing Customer', 'Private Hospitals', NULL, 'uncertain', NULL, 'yes', NULL, NULL, NULL, '2026-01-23', NULL, '2026-01-23 10:47:14', '2026-01-26 13:16:16'),
(11, 'LEAD-11', 'B2B', 'Akal sirjana services', 'Akal', NULL, '7625982199', NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', NULL, NULL, '143001', 'India', 'converted', NULL, 'Existing Customer', NULL, NULL, 'uncertain', NULL, 'yes', NULL, NULL, NULL, '2026-01-24', NULL, '2026-01-23 10:48:01', '2026-01-25 07:30:04'),
(12, 'LEAD-12', 'B2B', 'LOKENATH MEDICARE', 'Arup Chaterjee', 'Owner', '7602077800', NULL, NULL, NULL, 'Shekhala ,Hooghly, Hoogly, Hooghly', 'Hooghly', 'Hooghly', 'West Bengal', '712706', 'India', 'hot', 52, 'Existing Customer', NULL, 'Hospital', '1_month', '100000', 'yes', 'test', 'Abhinav Chitraranjan Kumar', NULL, '2026-01-25', NULL, '2026-01-24 01:13:21', '2026-01-25 10:47:32'),
(13, 'LEAD-13', 'B2B', 'veena Healthcare', 'Bubhakarr kumar', NULL, '7310319555', NULL, NULL, NULL, 'Begusarai, , Begusarai', 'Begusarai', NULL, NULL, NULL, 'India', 'converted', 55, 'Existing Customer', NULL, NULL, '1_month', '152222', 'yes', 'nottoto', 'Dipankar Aich', 3, '2026-01-29', NULL, '2026-01-24 02:00:53', '2026-01-25 07:29:53'),
(14, 'LEAD-14', 'B2B', 'M/S ANKUR MEDICAL GASSES', 'Ankur', 'Owner', '9839226406', NULL, NULL, NULL, 'L.G. 32, GOYAL PALACE, FAIZABAD ROAD, Lucknow, Lucknow', 'Lucknow', NULL, NULL, '226016', 'India', 'converted', 46, 'Existing Customer', NULL, 'Hospital', 'uncertain', '125000', 'yes', 'Need bubble CPAP  machine with high flow meter', 'Dipankar Aich', 3, '2026-01-30', '2026-01-24', '2026-01-24 06:51:37', '2026-01-25 07:30:14'),
(15, 'LEAD-15', 'B2B', 'FABKON TECHNICAL SYSTEMS', 'Anand baghel', 'Purchase Manager', '6362611388', NULL, NULL, NULL, 'EMPORIUM COMMERCIAL COMPLEX, 16-3-138/46, SHOP NO 26, 3RD FLOOR, OLD BYPASS ROAD KANKANADY, MANGALORE, Dakshina Kannada, Dakshina Kannada', 'Dakshina Kannada', NULL, NULL, '575002', 'India', 'cold', NULL, 'Existing Customer', NULL, 'Hospital', '1_year', '250000', 'no', 'need medical devices', 'Dipankar Aich', 3, '2026-01-31', NULL, '2026-01-24 07:21:08', '2026-01-24 08:01:01'),
(16, 'LEAD-16', 'B2B', 'INDIA SURGICALS', 'India', NULL, '9156315725', NULL, NULL, NULL, 'GAT NO 1324, CHINCHECHA MALA, SONWANE WASTI,CHIKHALI, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '411062', 'India', 'hot', 65, 'Existing Customer', NULL, NULL, 'uncertain', NULL, 'no', NULL, 'Neha Govind Ukale', 4, '2026-01-25', NULL, '2026-01-24 08:05:48', '2026-01-24 12:33:10'),
(17, 'LEAD-17', 'B2B', 'Bablu Barman', 'Bablu Barman', NULL, '9734937949', NULL, NULL, NULL, 'Siliguri , Siliguri , Darjiling', 'Darjiling', 'Darjiling', 'West Bengal', '734001', 'India', 'cold', NULL, 'Existing Customer', NULL, NULL, 'uncertain', NULL, 'no', 'new', 'Dipankar Aich', 3, '2026-01-25', NULL, '2026-01-24 15:19:03', '2026-01-24 15:19:03'),
(18, 'LEAD-18', 'B2B', 'Akal sirjana services', 'Akal', NULL, '7625982199', NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', 'Amritsar', 'Punjab', '143001', 'India', 'converted', 28, 'Existing Customer', NULL, NULL, 'uncertain', NULL, 'no', NULL, NULL, NULL, NULL, '2026-01-25', '2026-01-25 03:18:38', '2026-01-25 07:29:43'),
(19, 'LEAD-19', 'B2B', 'ABC Hospital', 'Alpesh Doshi', 'Owner', '7020845499', NULL, NULL, NULL, 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', NULL, NULL, '413208', 'India', 'hot', 64, 'Existing Customer', NULL, 'Hospital', '1_month', '125000', 'yes', 'Pleasd followup with him he needs some baby warmr', 'Neha Govind Ukale', 4, '2026-01-29', NULL, '2026-01-25 05:07:18', '2026-01-25 07:38:16'),
(20, 'LEAD-20', 'B2B', 'NEW ANURA SURGICAL', 'Anura', NULL, '9021279938', NULL, NULL, NULL, 'SHOP NO.2,S NO.247,PLOT NO.5, MAMASAHEB BORKAR MARG, GANESH NAGAR,CHINCHWAD, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '411033', 'India', 'converted', 66, 'Existing Customer', NULL, NULL, 'immediate', '125000', 'yes', 'need many devices', 'Neha Govind Ukale', 4, '2026-01-29', NULL, '2026-01-25 08:51:17', '2026-01-25 09:15:37'),
(21, 'LEAD-21', 'B2B', 'LOKENATH MEDICARE', 'Arup Chaterjee', NULL, '7602077800', NULL, NULL, NULL, 'Shekhala ,Hooghly, Hoogly, Hooghly', 'Hooghly', NULL, NULL, '712706', 'India', 'converted', 52, 'Existing Customer', 'Corporate Customers', NULL, 'uncertain', NULL, 'no', NULL, NULL, NULL, NULL, NULL, '2026-01-25 09:19:58', '2026-01-26 13:16:59'),
(22, 'LEAD-22', 'B2B', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', 'Owner', '9874438894', NULL, NULL, NULL, 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', NULL, NULL, '700010', 'India', 'hot', 12, 'Existing Customer', 'Private Hospitals', 'Hospital', 'uncertain', NULL, 'yes', 'new model', 'Neha Govind Ukale', 4, '2026-01-27', NULL, '2026-01-26 03:15:26', '2026-01-26 13:16:39'),
(23, 'LEAD-23', 'B2B', 'Akal sirjana services', 'Akal', 'Director', '7625982199', NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', 'Amritsar', 'Punjab', '143001', 'India', 'hot', 28, 'Existing Customer', 'Private Hospitals', 'Hospital', 'immediate', '150000', 'yes', 'potential customers', 'Dipankar Aich', 3, '2026-01-27', NULL, '2026-01-26 16:51:33', '2026-01-26 16:53:28'),
(24, 'LEAD-24', 'B2B', 'Akal sirjana services', 'Akal', 'Owner', '7625982199', NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', NULL, NULL, '143001', 'India', 'hot', 28, 'Existing Customer', 'Export Orders', 'Hospital', 'immediate', '250000', 'yes', 'yes', 'Dipankar Aich', 3, '2026-01-31', NULL, '2026-01-27 08:36:01', '2026-01-27 08:49:03'),
(25, 'LEAD-25', 'B2B', 'ABC Hospital', 'Alpesh Doshi', NULL, '7020845499', NULL, NULL, NULL, 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', NULL, NULL, '413208', 'India', 'hot', 64, 'Existing Customer', 'Private Hospitals', NULL, 'immediate', '150000', 'yes', 'new order', 'Dipankar Aich', 3, '2026-01-28', NULL, '2026-01-27 13:21:22', '2026-01-27 13:22:58'),
(27, 'LEAD-26', 'B2B', 'Medical Hub', 'Shourya Aich', 'CEO', '7775823557', NULL, 'yashkacontacts@gmail.com', NULL, 'Megapolis', 'Mystic', 'Pune', 'Maharashtra', '411057', 'India', 'warm', NULL, 'Trade Show', 'Corporate Customers', 'Hospital', '3_months', '150000', 'yes', 'new leads', 'Dipankar Aich', 3, '2026-01-29', NULL, '2026-01-28 11:09:34', '2026-01-28 11:13:58'),
(28, 'LEAD-27', 'B2B', 'Medtech', 'Aryan', 'Owner', '9527037403', NULL, 'yashkacontacts@gmail.com', NULL, 'Megapolis', 'Mystic', 'Pune', 'Maharashtra', '411057', 'India', 'cold', NULL, 'Referral', 'GEMS or Tenders', 'Hospital', '1_month', '100000', 'yes', 'new ordr', 'Dipankar Aich', 3, '2026-01-28', NULL, '2026-01-28 13:22:40', '2026-01-28 13:22:40'),
(29, 'LEAD-28', 'B2B', 'Medtec', 'aryan', 'CEO', '9527037403', NULL, 'yashkacontacts@gmail.com', NULL, 'Survey No 162/1 , Perkhi Heights', 'Maan Village', 'Pune', 'Maharashtra', '411057', 'India', 'cold', NULL, 'Existing Lead', 'GEMS or Tenders', 'Hospital', '3_months', NULL, 'no', NULL, NULL, NULL, NULL, NULL, '2026-01-28 13:36:00', '2026-01-28 13:36:00'),
(30, 'LEAD-29', 'B2B', 'The medical equipment', 'Sunil Arora', 'CEO', '9820967982', NULL, NULL, NULL, '23 6, 10, Sorabji santuk Ln Navajeevan', 'Sonapur marine lines', NULL, NULL, '400002', 'India', 'hot', 75, 'WhatsApp', 'GEMS or Tenders', 'Multi-Specialty Hospital', '1_month', '500000', 'yes', 'XVS 9500 XL model', 'Ms. Kishori Prakash Gadakh', 5, '2026-01-29', NULL, '2026-01-28 13:48:33', '2026-01-29 07:23:57'),
(32, 'LEAD-31', 'B2B', 'The Precision Surgical', 'Pritam Ghosh', NULL, '9433943047', NULL, 'precisionsurgicals2000@gmail.com', NULL, '79, CIT Road Deb Lane Bus Stop, Deb Lane, Entally, Kolkata, Kolkata, West Bengal', 'Kolkata', 'Kolkata', 'West Bengal', '700014', 'India', 'hot', NULL, 'Existing Customer', 'Private Hospitals', NULL, 'immediate', '2 lacs', 'yes', 'Ventigo 77 order', 'Dipankar Aich', 10, '2026-01-30', NULL, '2026-01-29 08:37:01', '2026-01-29 08:37:01'),
(33, 'LEAD-32', 'B2B', 'The Precision Surgical', 'Pritam Ghosh', NULL, '9433943047', NULL, 'precisionsurgicals2000@gmail.com', NULL, '79, CIT Road Deb Lane Bus Stop, Deb Lane, Entally, Kolkata, Kolkata, West Bengal', 'Kolkata', NULL, NULL, '700014', 'India', 'hot', 5, 'Existing Customer', 'Corporate Customers', NULL, 'immediate', '2 lacs', 'yes', 'ventigo 77 order', 'Ms. Kishori Prakash Gadakh', 5, '2026-01-30', NULL, '2026-01-29 08:39:29', '2026-01-29 09:44:06'),
(34, 'LEAD-33', 'B2B', 'AARON MEDICARE SYSTEMS', 'Arron', 'CEO', '9393770001', NULL, NULL, NULL, '10 - 5 - 20, POST OFFICE LANE, FATHENAGAR, Rangareddy, Hyderabad', 'Hyderabad', 'Adoni', 'Andhra Pradesh', '500018', 'India', 'hot', 14, 'WhatsApp', 'Private Hospitals', 'Multi-Specialty Hospital', 'uncertain', '125222', 'no', 'new', 'Neha Govind Ukale', 4, NULL, '2026-01-29', '2026-01-29 08:45:33', '2026-01-29 15:37:14'),
(35, 'LEAD-34', 'B2B', 'Health Care Surgical', 'Health', NULL, '9609452811', NULL, 'healthcaresurgical.no1@gmail.com', NULL, 'Gopidihi, Nanoor, , Birbhum', 'Birbhum', NULL, NULL, '731215', 'India', 'warm', 51, 'WhatsApp', 'Private Hospitals', NULL, '1_month', '450000', 'yes', 'ahen 5000', 'Sana mahammad Nayakwadi', 7, '2026-01-30', NULL, '2026-01-29 08:47:35', '2026-01-29 08:55:39'),
(36, 'LEAD-35', 'B2B', 'Kapileshwar Ambulance', 'Ashish Borge', NULL, '7276087228', NULL, NULL, NULL, 'Ahilyanagar, Ahilyanagar, Ahilyanagar', 'Ahilyanagar', 'Ahilyanagar', 'Maharashtra', NULL, 'India', 'hot', NULL, NULL, NULL, NULL, 'uncertain', NULL, 'no', NULL, NULL, NULL, NULL, NULL, '2026-01-29 09:25:09', '2026-01-30 02:10:36'),
(37, 'LEAD-36', 'B2B', 'veena Healthcare', 'Bubhakarr kumar', NULL, '7310319555', NULL, NULL, NULL, 'Begusarai, , Begusarai', 'Begusarai', 'Begusarai', 'Bihar', NULL, 'India', 'warm', NULL, NULL, NULL, NULL, 'uncertain', NULL, 'no', NULL, 'Neha Govind Ukale', 4, NULL, NULL, '2026-01-29 09:25:53', '2026-01-29 09:25:53'),
(38, 'LEAD-37', 'B2B', 'SINGULARITY TECH SOLUTION', 'VINAYAK MANE', 'Owner', '8888603102', NULL, NULL, NULL, 'GARGOTI', 'KOLHAPUR', NULL, NULL, '416209', 'India', 'warm', NULL, 'WhatsApp', 'Corporate Customers', 'Medical Equipment Dealer', 'immediate', '150000', 'yes', 'BABY WARMER & MOPPET 8.5', 'Ms. Kishori Prakash Gadakh', 5, '2026-01-30', NULL, '2026-01-29 09:30:21', '2026-01-29 09:31:46'),
(39, 'LEAD-38', 'B2B', 'veena Healthcare', 'Bubhakarr kumar', 'Owner', '7310319555', NULL, NULL, NULL, 'Begusarai, , Begusarai', 'Begusarai', NULL, NULL, NULL, 'India', 'warm', NULL, 'Existing Customer', 'Corporate Customers', 'Medical Equipment Dealer', 'immediate', '350000', 'yes', 'XVS 9500 XL VENTILATOR', 'Ms. Kishori Prakash Gadakh', 5, '2026-01-31', NULL, '2026-01-29 09:36:17', '2026-01-29 09:37:14'),
(40, 'LEAD-39', 'B2B', 'LOKENATH MEDICARE', 'Arup Chaterjee', 'Director', '7602077800', NULL, NULL, NULL, 'Shekhala ,Hooghly, Hoogly, Hooghly', 'Hooghly', 'Hooghly', 'West Bengal', '712706', 'India', 'cold', NULL, 'Exhibition', 'Export Orders', 'Medical College', 'uncertain', NULL, 'no', NULL, 'Neha Govind Ukale', 63, NULL, NULL, '2026-01-29 13:48:58', '2026-01-29 13:48:58'),
(41, 'LEAD-40', 'B2B', 'veena Healthcare', 'Bubhakarr kumar', NULL, '7310319555', NULL, NULL, NULL, 'Begusarai, , Begusarai', 'Begusarai', 'Begusarai', 'Bihar', NULL, 'India', 'hot', NULL, 'Exhibition', 'Export Orders', NULL, 'uncertain', NULL, 'no', NULL, 'Omkar Govind ukhale', 68, NULL, NULL, '2026-01-29 14:29:03', '2026-01-30 02:10:16'),
(42, 'LEAD-41', 'B2B', 'ABC Hospital', 'Alpesh Doshi', NULL, '7020845499', NULL, NULL, NULL, 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', 'Solapur', 'Maharashtra', '413208', 'India', 'warm', NULL, NULL, NULL, NULL, 'uncertain', NULL, 'no', NULL, 'Neha Govind Ukale', 63, NULL, NULL, '2026-01-29 14:31:08', '2026-01-29 14:31:24'),
(43, 'LEAD-42', 'B2B', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', 'CEO', '9874438894', NULL, NULL, NULL, 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700010', 'India', 'converted', 12, 'Existing Customer', 'Export Orders', 'Lab Equipment Supplier', 'uncertain', NULL, 'yes', NULL, 'Ms. Kishori Prakash Gadakh', 70, '2026-01-29', NULL, '2026-01-29 15:47:45', '2026-01-29 15:51:29'),
(44, 'LEAD-43', 'B2B', 'LOKENATH MEDICARE', 'Arup Chaterjee', 'CEO', '7602077800', NULL, NULL, NULL, 'Shekhala ,Hooghly, Hoogly, Hooghly', 'Hooghly', NULL, NULL, '712706', 'India', 'warm', NULL, 'Email Campaign', 'NGO or Others', 'Medical College', 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, NULL, NULL, '2026-01-30 01:28:59', '2026-01-30 01:30:15'),
(45, 'LEAD-44', 'B2B', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', NULL, '9874438894', NULL, NULL, NULL, 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700010', 'India', 'hot', NULL, 'Existing Lead', 'GEMS or Tenders', NULL, 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-30 01:33:08', '2026-01-30 02:10:52'),
(46, 'LEAD-45', 'B2B', 'ABC Hospital', 'Alpesh Doshi', NULL, '7020845499', NULL, NULL, NULL, 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', NULL, NULL, '413208', 'India', 'warm', NULL, NULL, NULL, NULL, 'uncertain', NULL, 'no', NULL, 'Pranjali mahadev Sampate', 65, NULL, NULL, '2026-01-30 01:38:41', '2026-01-30 01:40:13'),
(47, 'LEAD-46', 'B2B', 'NEW ANURA SURGICAL', 'Anura', NULL, '9021279938', NULL, NULL, NULL, 'SHOP NO.2,S NO.247,PLOT NO.5, MAMASAHEB BORKAR MARG, GANESH NAGAR,CHINCHWAD, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '411033', 'India', 'warm', NULL, NULL, NULL, NULL, 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, NULL, NULL, '2026-01-30 02:07:47', '2026-01-30 02:07:51'),
(48, 'LEAD-47', 'B2B', 'M/S ANKUR MEDICAL GASSES', 'Ankur', NULL, '9839226406', NULL, NULL, NULL, 'L.G. 32, GOYAL PALACE, FAIZABAD ROAD, Lucknow, Lucknow', 'Lucknow', 'Lucknow', 'Uttar Pradesh', '226016', 'India', 'warm', NULL, 'Referral', 'Export Orders', NULL, 'uncertain', NULL, 'no', NULL, 'Pranjali mahadev Sampate', 65, NULL, NULL, '2026-01-30 02:11:44', '2026-01-30 02:11:44'),
(49, 'LEAD-48', 'B2B', 'AARON MEDICARE SYSTEMS', 'Arron', NULL, '9393770001', NULL, NULL, NULL, '10 - 5 - 20, POST OFFICE LANE, FATHENAGAR, Rangareddy, Hyderabad', 'Hyderabad', 'Hyderabad', 'Telangana', '500018', 'India', 'warm', NULL, NULL, 'Export Orders', NULL, 'uncertain', NULL, 'no', NULL, 'Pranjali mahadev Sampate', 65, NULL, NULL, '2026-01-30 02:13:13', '2026-01-30 02:36:19'),
(50, 'LEAD-49', 'B2B', 'FABKON TECHNICAL SYSTEMS', 'Anand baghel', NULL, '6362611388', NULL, NULL, NULL, 'EMPORIUM COMMERCIAL COMPLEX, 16-3-138/46, SHOP NO 26, 3RD FLOOR, OLD BYPASS ROAD KANKANADY, MANGALORE, Dakshina Kannada, Dakshina Kannada', 'Dakshina Kannada', 'Dakshina Kannada', 'Karnataka', '575002', 'India', 'converted', NULL, NULL, 'GEMS or Tenders', NULL, 'uncertain', NULL, 'no', NULL, 'Sana mahammad Nayakwadi', 71, NULL, NULL, '2026-01-30 02:31:29', '2026-01-30 02:35:28'),
(51, 'LEAD-50', 'B2B', 'LOKENATH MEDICARE', 'Arup Chaterjee', NULL, '7602077800', NULL, NULL, NULL, 'Shekhala ,Hooghly, Hoogly, Hooghly', 'Hooghly', 'Hooghly', 'West Bengal', '712706', 'India', 'hot', NULL, NULL, 'Export Orders', NULL, 'uncertain', NULL, 'no', NULL, 'Pranjali mahadev Sampate', 65, NULL, NULL, '2026-01-30 02:44:03', '2026-01-30 02:44:55'),
(52, 'LEAD-51', 'B2B', 'SUR ELECTRICAL CO PVT LTD.', 'Chinmay sur', NULL, '9830045531', NULL, 'sepl.sv71@gmail.com', NULL, '3/1, DR SURESH SARKAR ROAD, KOLKATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700014', 'India', 'cold', NULL, 'Existing Customer', 'Corporate Customers', NULL, 'immediate', '350000', 'yes', 'Moppet 6.5 & ventigo 77 with trolly', 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-30 04:41:58', '2026-01-30 04:41:58'),
(53, 'LEAD-52', 'B2B', 'SUR ELECTRICAL CO PVT LTD.', 'Chinmay sur', NULL, '9830045531', NULL, 'sepl.sv71@gmail.com', NULL, '3/1, DR SURESH SARKAR ROAD, KOLKATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700014', 'India', 'cold', NULL, 'Existing Customer', 'Corporate Customers', NULL, 'immediate', '350000', 'yes', 'Ventigo 77 & moppet 6.5', 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-30 04:44:11', '2026-01-30 04:44:11'),
(54, 'LEAD-53', 'B2B', 'Windlio', 'Ravi', 'Owner', '7028041845', NULL, NULL, NULL, 'Aboli Hights, C-2, THREE, Near Ghule Vasti Ganesh Mandir, Pune, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '412307', 'India', 'cold', NULL, 'Cold Call', 'Private Hospitals', 'Surgical Instrument Dealer', '1_month', '45000', 'yes', 'ahen 5000', 'Sana mahammad Nayakwadi', 71, '2026-01-31', NULL, '2026-01-30 04:47:52', '2026-01-30 04:47:52'),
(55, 'LEAD-54', 'B2B', 'Windlio', 'Ravi', 'Owner', '7028041845', NULL, NULL, NULL, 'Aboli Hights, C-2, THREE, Near Ghule Vasti Ganesh Mandir, Pune, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '412307', 'India', 'cold', NULL, 'Cold Call', 'Private Hospitals', 'Surgical Instrument Dealer', '1_month', '45000', 'yes', 'ahen 5000', 'Sana mahammad Nayakwadi', 71, '2026-01-31', NULL, '2026-01-30 04:50:35', '2026-01-30 04:50:35'),
(56, 'LEAD-55', 'B2B', 'NEW ANURA SURGICAL', 'Anura', 'Owner', '9021279938', NULL, NULL, NULL, 'SHOP NO.2,S NO.247,PLOT NO.5, MAMASAHEB BORKAR MARG, GANESH NAGAR,CHINCHWAD, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '411033', 'India', 'cold', NULL, 'Referral', 'Private Hospitals', 'Medical Equipment Dealer', 'uncertain', '4', 'yes', NULL, 'Neha Govind Ukale', 63, NULL, NULL, '2026-01-30 04:51:07', '2026-01-30 04:51:07'),
(57, 'LEAD-56', 'B2B', 'SUR ELECTRICAL CO PVT LTD.', 'Chinmay sur', NULL, '9830045531', NULL, 'sepl.sv71@gmail.com', NULL, '3/1, DR SURESH SARKAR ROAD, KOLKATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700014', 'India', 'cold', NULL, 'Existing Customer', 'Corporate Customers', NULL, 'immediate', '315000', 'yes', 'Ventigo 77 & moppet 6.5', 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-30 04:51:13', '2026-01-30 04:51:13'),
(58, 'LEAD-57', 'B2B', 'NEW ANURA SURGICAL', 'Anura', NULL, '9021279938', NULL, NULL, NULL, 'SHOP NO.2,S NO.247,PLOT NO.5, MAMASAHEB BORKAR MARG, GANESH NAGAR,CHINCHWAD, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '411033', 'India', 'cold', NULL, NULL, NULL, NULL, 'uncertain', NULL, 'no', NULL, 'Neha Govind Ukale', 63, NULL, NULL, '2026-01-30 04:52:00', '2026-01-30 04:52:00'),
(59, 'LEAD-58', 'B2B', 'FABKON TECHNICAL SYSTEMS', 'Anand baghel', NULL, '6362611388', NULL, NULL, NULL, 'EMPORIUM COMMERCIAL COMPLEX, 16-3-138/46, SHOP NO 26, 3RD FLOOR, OLD BYPASS ROAD KANKANADY, MANGALORE, Dakshina Kannada, Dakshina Kannada', 'Dakshina Kannada', 'Dakshina Kannada', 'Karnataka', '575002', 'India', 'cold', NULL, NULL, NULL, 'Multi-Specialty Hospital', 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, NULL, NULL, '2026-01-30 04:52:07', '2026-01-30 04:52:07'),
(60, 'LEAD-59', 'B2B', 'Windlio', 'Ravi', 'Owner', '7028041845', NULL, NULL, NULL, 'Aboli Hights, C-2, THREE, Near Ghule Vasti Ganesh Mandir, Pune, Pune, Pune', 'Pune', 'Pune', 'Maharashtra', '412307', 'India', 'warm', NULL, 'Cold Call', 'Private Hospitals', 'Surgical Instrument Dealer', '1_month', '45000', 'yes', 'ahen 5000', 'Sana mahammad Nayakwadi', 71, '2026-01-31', NULL, '2026-01-30 04:53:27', '2026-01-30 04:58:49'),
(61, 'LEAD-60', 'B2B', 'SUR ELECTRICAL CO PVT LTD.', 'Chinmay sur', NULL, '9830045531', NULL, 'sepl.sv71@gmail.com', NULL, '3/1, DR SURESH SARKAR ROAD, KOLKATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700014', 'India', 'warm', NULL, 'Existing Customer', 'Private Hospitals', NULL, 'immediate', '315000', 'yes', 'ventigo 77 & moppet 6.5', 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-30 04:53:28', '2026-01-30 04:55:17'),
(62, 'LEAD-61', 'B2B', 'AARON MEDICARE SYSTEMS', 'Arron', NULL, '9393770001', NULL, NULL, NULL, '10 - 5 - 20, POST OFFICE LANE, FATHENAGAR, Rangareddy, Hyderabad', 'Hyderabad', 'Hyderabad', 'Telangana', '500018', 'India', 'warm', NULL, NULL, NULL, NULL, 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, NULL, NULL, '2026-01-30 04:53:50', '2026-01-30 04:54:30'),
(63, 'LEAD-62', 'B2B', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', NULL, '9874438894', NULL, NULL, NULL, 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700010', 'India', 'warm', NULL, NULL, 'GEMS or Tenders', NULL, 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, NULL, NULL, '2026-01-30 06:08:47', '2026-01-30 06:11:55'),
(64, 'LEAD-63', 'B2B', 'Medilife Multispeciality Hospital', 'Dr.Jagdish Jadhav', NULL, '9552530205', NULL, NULL, NULL, 'Vijay nagar, Kalewadi, Pune', 'Pune', 'Pune', 'Maharashtra', '411017', 'India', 'cold', NULL, NULL, 'GEMS or Tenders', NULL, 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, NULL, NULL, '2026-01-30 06:11:04', '2026-01-30 06:11:04'),
(65, 'LEAD-64', 'B2B', 'SUR ELECTRICAL CO PVT LTD.', 'Chinmay sur', NULL, '9830045531', NULL, 'sepl.sv71@gmail.com', NULL, '3/1, DR SURESH SARKAR ROAD, KOLKATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700014', 'India', 'cold', NULL, NULL, 'GEMS or Tenders', 'Medical College', 'uncertain', NULL, 'no', NULL, 'Ms. Kishori Prakash Gadakh', 70, NULL, NULL, '2026-01-30 06:16:12', '2026-01-30 06:16:12'),
(66, 'LEAD-65', 'B2B', 'SINGULARITY TECH SOLUTION', 'VINAYAK MANE', NULL, '8888603102', NULL, NULL, NULL, 'GARGOTI', 'KOLHAPUR', 'Kolhapur', 'Maharashtra', '416209', 'India', 'cold', NULL, 'WhatsApp', 'Private Hospitals', NULL, 'immediate', '200000', 'yes', 'ventigo 77', 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-30 06:19:25', '2026-01-30 06:19:25'),
(67, 'LEAD-66', 'B2B', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', NULL, '9874438894', NULL, NULL, NULL, 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', 'West Bengal', '700010', 'India', 'cold', NULL, 'Existing Customer', 'Private Hospitals', NULL, 'immediate', '500000', 'yes', 'HVS 3500 & Nebula 2.1', 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-30 06:24:23', '2026-01-30 06:24:23'),
(69, 'LEAD-67', 'B2B', 'Medway medicare', 'Arjit', 'Director', '8583966187', NULL, 'medwaymedicare2022@gmail.com', NULL, 'Garia ganpati market boral main road', 'near Sbi Bank boral branch', 'Kolkata', 'West Bengal', '700084', 'India', 'cold', NULL, 'Existing Customer', 'Corporate Customers', 'Medical Equipment Dealer', 'uncertain', NULL, 'yes', NULL, 'Ms. Kishori Prakash Gadakh', 70, '2026-01-31', NULL, '2026-01-31 05:15:02', '2026-01-31 05:26:28');

-- --------------------------------------------------------

--
-- Table structure for table `crm_lead_interactions`
--

CREATE TABLE `crm_lead_interactions` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `interaction_type` enum('call','email','meeting','site_visit','demo','quotation_sent','other') NOT NULL,
  `interaction_date` datetime NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `outcome` varchar(255) DEFAULT NULL,
  `next_action` varchar(255) DEFAULT NULL,
  `next_action_date` date DEFAULT NULL,
  `handled_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_lead_interactions`
--

INSERT INTO `crm_lead_interactions` (`id`, `lead_id`, `interaction_type`, `interaction_date`, `subject`, `description`, `outcome`, `next_action`, `next_action_date`, `handled_by`, `created_at`) VALUES
(1, 9, 'call', '2026-01-23 11:35:00', 'xvs order', 'need pi', 'confirm order ', 'PI to be sent ', '2026-01-23', 'Kishori', '2026-01-23 10:36:13'),
(2, 14, 'call', '2026-01-24 07:55:00', 'Bubble CPAP order ', 'He may need within a week ', 'He is taliking with customers', 'Need to call him next week', '2026-01-27', 'Dipankar', '2026-01-24 06:57:00'),
(3, 14, 'demo', '2026-01-24 07:57:00', 'Demo online', 'Today shown demo over video call ', 'He is very impressed', 'He want further demo', '2026-01-24', 'Dipankar', '2026-01-24 06:58:21'),
(4, 18, 'email', '2026-01-25 06:24:00', 'xvs order', 'scdas', NULL, NULL, NULL, NULL, '2026-01-25 05:24:36'),
(5, 34, 'call', '2026-01-29 09:47:00', 'Bidar Requirement', NULL, NULL, NULL, NULL, NULL, '2026-01-29 08:48:02'),
(6, 34, 'call', '2026-01-29 09:48:00', 'Send Me PI', NULL, NULL, NULL, NULL, NULL, '2026-01-29 08:48:12'),
(7, 34, 'call', '2026-01-29 09:48:00', 'PI Sent', NULL, NULL, NULL, NULL, NULL, '2026-01-29 08:48:25');

-- --------------------------------------------------------

--
-- Table structure for table `crm_lead_requirements`
--

CREATE TABLE `crm_lead_requirements` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `part_no` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `estimated_qty` decimal(15,3) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `target_price` decimal(15,2) DEFAULT NULL,
  `our_price` decimal(15,2) DEFAULT NULL,
  `required_by` date DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_lead_requirements`
--

INSERT INTO `crm_lead_requirements` (`id`, `lead_id`, `part_no`, `product_name`, `description`, `estimated_qty`, `unit`, `target_price`, `our_price`, `required_by`, `priority`, `notes`, `created_at`) VALUES
(1, 4, '52001423', 'AVS', 'for ambulence', 1.000, 'pcs', 200000.00, 225000.00, '2026-01-24', 'medium', 'he need by month end ', '2026-01-22 03:03:39'),
(2, 9, 'YID-0124', 'XVS 9500 XL', 'icu Ventilator', 1.000, 'pcs', 350000.00, 450000.00, '2026-01-30', 'medium', 'confirm ordder', '2026-01-23 10:35:22'),
(5, 6, 'YID -043', 'ECG 6 Channel', NULL, NULL, 'NOS', NULL, NULL, NULL, 'medium', NULL, '2026-01-24 08:23:42'),
(6, 14, 'YID -043', 'ECG 6 Channel', '12 channel', 1.000, 'NOS', 1200.00, 2500.00, '2026-01-25', 'medium', 'discussion with doctor', '2026-01-24 08:24:44'),
(7, 17, 'YID -043', 'ECG 6 Channel', NULL, 1.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-24 15:19:03'),
(8, 18, 'YID -043', 'ECG 6 Channel', NULL, 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-25 03:18:38'),
(10, 19, 'YID-008', 'HVS 2500', NULL, 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-25 05:07:18'),
(11, 18, 'YID-006', 'HVS 3500', 'sczd', NULL, 'NOS', NULL, NULL, NULL, 'medium', NULL, '2026-01-25 05:25:39'),
(12, 20, 'YID-044', '12 Channel ECG Machine', NULL, 1.000, 'NOS', 15000.00, NULL, NULL, 'medium', NULL, '2026-01-25 08:51:17'),
(13, 21, 'YID-044', '12 Channel ECG Machine', NULL, 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-25 09:19:58'),
(14, 22, 'YID-167', 'Lio smart-7.5', NULL, 1.000, 'NOS', 1250000.00, NULL, NULL, 'medium', NULL, '2026-01-26 03:15:26'),
(15, 23, 'YID-167', 'Lio smart-7.5', NULL, 0.000, 'NOS', 100000.00, NULL, NULL, 'medium', NULL, '2026-01-26 16:51:33'),
(16, 3, 'YID-044', '12 Channel ECG Machine', NULL, NULL, 'NOS', NULL, NULL, NULL, 'medium', NULL, '2026-01-27 08:15:17'),
(17, 3, 'YID-030', '3 Para Patient Monitor', NULL, NULL, 'NOS', NULL, NULL, NULL, 'medium', NULL, '2026-01-27 08:15:25'),
(18, 24, 'YID-044', '12 Channel ECG Machine', NULL, 0.000, 'NOS', 15000.00, NULL, NULL, 'medium', NULL, '2026-01-27 08:36:01'),
(19, 24, 'YID-008', 'HVS 2500', NULL, 0.000, 'NOS', 250000.00, NULL, NULL, 'medium', NULL, '2026-01-27 08:36:01'),
(20, 25, 'YID-167', 'Lio smart-7.5', NULL, 1.000, 'NOS', 100000.00, NULL, NULL, 'medium', NULL, '2026-01-27 13:21:22'),
(22, 27, 'YID-167', 'Lio smart-7.5', NULL, 1.000, 'NOS', 120000.00, NULL, NULL, 'medium', NULL, '2026-01-28 11:09:34'),
(23, 28, 'YID-167', 'Lio smart-7.5', NULL, 1.000, 'NOS', 120000.00, NULL, NULL, 'medium', NULL, '2026-01-28 13:22:40'),
(24, 29, 'YID-167', 'Lio smart-7.5', NULL, 1.000, 'NOS', 125000.00, NULL, NULL, 'medium', NULL, '2026-01-28 13:36:00'),
(25, 30, 'YID-003', 'XVS 9500', NULL, 1.000, 'NOS', 450000.00, NULL, NULL, 'medium', NULL, '2026-01-28 13:48:33'),
(27, 32, 'YID-012', 'Ventigo 77', NULL, 1.000, 'NOS', 200000.00, NULL, NULL, 'medium', NULL, '2026-01-29 08:37:01'),
(28, 33, 'YID-012', 'Ventigo 77', NULL, 1.000, 'NOS', 200000.00, NULL, NULL, 'medium', NULL, '2026-01-29 08:39:29'),
(29, 34, 'YID-105', 'Moppet 8.5', NULL, 1.000, 'NOS', 140000.00, NULL, NULL, 'medium', NULL, '2026-01-29 08:45:33'),
(30, 35, 'YID-017', 'AHEN 5000', NULL, 1.000, 'NOS', 45000.00, NULL, NULL, 'medium', NULL, '2026-01-29 08:47:35'),
(31, 38, 'YID-027', 'Baby Warmer with Drawer', NULL, 1.000, 'NOS', 22000.00, NULL, NULL, 'medium', NULL, '2026-01-29 09:30:21'),
(32, 38, 'YID-105', 'Moppet 8.5', NULL, 1.000, 'NOS', 150000.00, NULL, NULL, 'medium', NULL, '2026-01-29 09:30:21'),
(33, 39, 'YID-002', 'XVS 9500 XL.', NULL, 1.000, 'NOS', 350000.00, NULL, NULL, 'medium', NULL, '2026-01-29 09:36:17'),
(34, 41, 'YID-044', '12 Channel ECG Machine', NULL, 1.000, 'NOS', 125000.00, NULL, NULL, 'medium', NULL, '2026-01-29 14:29:03'),
(35, 42, 'YID-044', '12 Channel ECG Machine', NULL, 1.000, 'NOS', 25200.00, NULL, NULL, 'medium', NULL, '2026-01-29 14:31:08'),
(36, 43, 'YID-044', '12 Channel ECG Machine', NULL, 1.000, 'NOS', 25000.00, NULL, NULL, 'medium', NULL, '2026-01-29 15:47:45'),
(37, 45, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 01:33:08'),
(38, 48, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 02:11:44'),
(39, 51, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 02:44:03'),
(40, 52, 'YID-012', 'Ventigo 77', 'ICU Ventilator', 1.000, 'NOS', 105000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:41:58'),
(41, 52, 'YID-016', 'Moppet 6.5', 'Transport ventilator', 1.000, 'NOS', 210000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:41:58'),
(42, 53, 'YID-012', 'Ventigo 77', 'ICU Ventilator', 1.000, 'NOS', 210000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:44:11'),
(43, 53, 'YID-016', 'Moppet 6.5', 'transport ventilator', 1.000, 'NOS', 105000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:44:11'),
(44, 54, 'YID-017', 'AHEN 5000', 'Sale Finish Good', 1.000, 'NOS', 45000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:47:52'),
(45, 55, 'YID-017', 'AHEN 5000', 'Sale Finish Good', 1.000, 'NOS', 45000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:50:35'),
(46, 56, 'YID-063', '5 Function Manual ICU Bed', 'Sale Finish Good', 1.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:51:07'),
(47, 57, 'YID-012', 'Ventigo 77', 'ICU Ventilator', 1.000, 'NOS', 210000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:51:13'),
(48, 57, 'YID-016', 'Moppet 6.5', 'transport ventilator', 1.000, 'NOS', 105000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:51:13'),
(49, 58, 'YID-063', '5 Function Manual ICU Bed', 'Sale Finish Good', 1.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:52:00'),
(50, 59, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:52:07'),
(51, 60, 'YID-017', 'AHEN 5000', 'Sale Finish Good', 1.000, 'NOS', 45000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:53:27'),
(52, 61, 'YID-012', 'Ventigo 77', 'ICU Ventilator', 1.000, 'NOS', 210000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:53:28'),
(53, 61, 'YID-016', 'Moppet 6.5', 'Sale Finish Good', 1.000, 'NOS', 105000.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:53:28'),
(54, 62, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 04:53:50'),
(55, 63, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 06:08:47'),
(56, 64, 'YID-030', '3 Para Patient Monitor', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 06:11:04'),
(57, 65, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', 0.000, 'NOS', 0.00, NULL, NULL, 'medium', NULL, '2026-01-30 06:16:12'),
(58, 66, 'YID-012', 'Ventigo 77', 'ICU Ventilator', 1.000, 'NOS', 200000.00, NULL, NULL, 'medium', NULL, '2026-01-30 06:19:25'),
(59, 67, 'YID-068', 'Nebula 2.1', 'Sale Finish Good', 1.000, 'NOS', 225000.00, NULL, NULL, 'medium', NULL, '2026-01-30 06:24:23'),
(60, 67, 'YID-002', 'XVS 9500 XL.', 'ICU Ventilator', 1.000, 'NOS', 310000.00, NULL, NULL, 'medium', NULL, '2026-01-30 06:24:23'),
(63, 69, 'YID-019', 'AHEN 4000', 'Sale Finish Good', 1.000, 'NOS', 285000.00, NULL, NULL, 'medium', NULL, '2026-01-31 05:15:02');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_id` varchar(20) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `secondary_designation` varchar(100) DEFAULT NULL,
  `secondary_contact_name` varchar(150) DEFAULT NULL,
  `customer_type` varchar(10) DEFAULT 'B2B',
  `industry` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_id`, `company_name`, `customer_name`, `contact`, `email`, `address1`, `address2`, `city`, `pincode`, `state`, `gstin`, `status`, `created_at`, `secondary_designation`, `secondary_contact_name`, `customer_type`, `industry`, `customer_phone`, `designation`) VALUES
(2, 'CUST-2', '', 'Aryan Aich', NULL, NULL, NULL, NULL, NULL, NULL, 'Maharashtra', NULL, 'active', '2026-01-10 12:28:44', NULL, NULL, 'B2B', NULL, NULL, NULL),
(3, 'CUST-3', 'Yashka Infotronics Pvt. Ltd.', 'Aryan Aich', '7264800591', NULL, NULL, NULL, NULL, NULL, 'Maharashtra', '125951029419592', 'active', '2026-01-10 12:47:41', NULL, NULL, 'B2B', NULL, NULL, NULL),
(4, 'CUST-4', 'Advantec Medical Device', 'Animesh Ghose', '8763591234', 'yashkacontacts@gmail.com', 'Megapolis', 'Mystic', 'Pune', '411057', 'Maharashtra', 'A001234576PA00988', 'active', '2026-01-10 16:14:07', NULL, NULL, 'B2B', NULL, NULL, NULL),
(5, 'CUST-5', 'The Precision Surgical', 'Pritam Ghosh', '9433943047', 'precisionsurgicals2000@gmail.com', '79, CIT Road Deb Lane Bus Stop, Deb Lane, Entally, Kolkata, Kolkata, West Bengal', 'Kolkata', 'Kolkata', '700014', 'West Bengal', '19AAUFT5555H1ZE', 'active', '2026-01-10 16:17:12', NULL, 'Pritam Ghosh', 'B2B', 'Medical Equipment Dealer', NULL, 'Director'),
(6, 'CUST-6', 'Unity Hospital', 'Aryan', '07264800591', '', 'Supriya Heights, Survey No. 162 Maan 1A/1', 'Megapolis Serenity, Hinjewadi Phase 3', 'Pune', '411057', 'Maharashtra', '', 'active', '2026-01-18 15:39:41', NULL, NULL, 'B2B', NULL, NULL, NULL),
(7, 'CUST-7', 'TENTABS TECH SOLUTIONS PRIVATE LIMITED', 'Raj Kumar', '9819410702', '', 'JR Enclave, No.1161, 1 st floor, HSR Layout, 2nd Sector,Parangipalya Road, Bangalore, Bengaluru Urban, Bangalore', 'Bangalore', 'Bangalore', '560102', 'Karnataka', '29AAGCT7817H1ZW', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(8, 'CUST-8', 'R K Traders', 'Mayank Gupta', '7905913719', '', '32/31, Ghumani Bazaar, Kanpur, Uttar Pradesh, India, Kanpur, Kanpur', 'Kanpur', 'Kanpur', '', 'Uttar Pradesh', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(9, 'CUST-9', 'M/S. MEDITECH SYSTEMS (INDIA)', 'Rajeev ranjan', '8252528780', '', '265, Roy Bahadur Road, Behala, South Twenty Four Parganas, Kolkata', 'Kolkata', 'Kolkata', '700053', 'West Bengal', '19BGUPP8601B1Z3', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(10, 'CUST-10', 'TEJA  BIOMEDICAL  SOLUTIONS', 'Teja', '7095885209', '', 'RAJIVGANDHI  NAGAR,3 RD LANE, 1 ST ROOM, 7-6-847/1, ASST NO  51899, REDDYPALEM ROAD, GUNTUR, Guntur, Guntur', 'Guntur', 'Guntur', '522002', 'Andhra Pradesh', '37BLVPR3813B2ZU', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(11, 'CUST-11', 'FABKON TECHNICAL SYSTEMS', 'Anand baghel', '6362611388', '', 'EMPORIUM COMMERCIAL COMPLEX, 16-3-138/46, SHOP NO 26, 3RD FLOOR, OLD BYPASS ROAD KANKANADY, MANGALORE, Dakshina Kannada, Dakshina Kannada', 'Dakshina Kannada', 'Dakshina Kannada', '575002', 'Karnataka', '29AAWPF7529H1Z2', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(12, 'CUST-12', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', '9874438894', '', 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', '700010', 'West Bengal', '19AHTPG8892J1ZA', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(13, 'CUST-13', 'MetLife Surgicals', 'Shiju Varghese', '8089571837', '', 'Methlehem Arcade, Cherikole P.O. Mavelikara, , Alappuzha, Kerala-, Alappuzha', 'Alappuzha', 'Alappuzha', '690104', 'Kerala', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(14, 'CUST-14', 'AARON MEDICARE SYSTEMS', 'Arron', '9393770001', '', '10 - 5 - 20, POST OFFICE LANE, FATHENAGAR, Rangareddy, Hyderabad', 'Hyderabad', 'Hyderabad', '500018', 'Telangana', '36BTBPP2191N1Z9', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(15, 'CUST-15', 'PRIOM MEDITECH', 'Parijita Chaterjee', '9038430231', '', 'Bidhan Pally, 46/C, 2ND, 37/1A/3 Ibrahimpur Road, Kolkata, Kolkata, Kolkata', 'Kolkata', 'Kolkata', '700032', 'West Bengal', '19DEBPS8990R1Z4', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(16, 'CUST-16', 'Shashikant badekar', 'Shashikant Badekar', '7972821012', '', 'Karad, Karad, Satara', 'Satara', 'Satara', '415110', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(17, 'CUST-17', 'Prime Medical Equipment', 'Vipul das', '9874404751', '', 'Halder Para, Kolkata, Kolkata', 'Kolkata', 'Kolkata', '700061', 'West Bengal', '19APPPD3114CIZA', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(18, 'CUST-18', 'UNITECH MEDICAL SYSTEM', 'Unitech Medical', '7980687774', '', '60/1/A, Ukilabad Road, Berhampore, Murshidabad, Murshidabad', 'Murshidabad', 'Murshidabad', '742101', 'West Bengal', '19AAIFU6895K1Z5', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(19, 'CUST-19', 'Bablu Barman', 'Bablu Barman', '9734937949', '', 'Siliguri , Siliguri , Darjiling', 'Darjiling', 'Darjiling', '734001', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(20, 'CUST-20', 'M/S MEDIPOINT', 'Medipoint', '9837089929', '', 'S2S ASPIRE, SHOP NO.-12, 2nd FLOOR, OPP SHOHRABGATE BUS STAND, MEERUT, Meerut, Meerut', 'Meerut', 'Meerut', '250002', 'Uttar Pradesh', '09BGHPS8211B1zg', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(21, 'CUST-21', 'BOSTON IVY HEALTHCARE SOLUTIONS PVT LTD', 'Rajesh Shetty', '7021952208', '', 'C/O DHL Supply Chain Universal Logistics Parks MCS4, Near Bobare Poultry Farm, Kalyan Sape Road, Talavali, Padgha, Thane', 'Thane', 'Thane', '421302', 'Maharashtra', '27AAFCB5524J1ZM', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(22, 'CUST-22', 'Windlio', 'Ravi', '7028041845', '', 'Aboli Hights, C-2, THREE, Near Ghule Vasti Ganesh Mandir, Pune, Pune, Pune', 'Pune', 'Pune', '412307', 'Maharashtra', '27EWHPB3574F1ZJ', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(23, 'CUST-23', 'Edwin Pereira', 'Edwin Pareira', '9822384231', '', 'Panji, , Panji', 'Panji', 'Panji', '', 'Goa', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(24, 'CUST-24', 'Rakshak Ambulance & Health Care Service', 'Rishabh Prajapti', '9454115323', '', 'ARAZI NO. 115, BHARLAI SHIVPUR, SHIVPUR, Varanasi, , Varanasi', 'Varanasi', 'Varanasi', '221003', 'Uttar Pradesh', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(25, 'CUST-25', 'Veer Bhadreshwar Enterprises', 'Pravin Patil', '9482413062', '', 'SURYA SUSHEELA AASHIRWAD, PLOT NO 85/86, OPP TDB COLONY, GURNALLI, GURNALLI POST AMALAPUR TQ BIDAR, Bidar, Bidar', 'Bidar', 'Bidar', '585403', 'Karnataka', '29DOJPS9248E1Z8', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(26, 'CUST-26', 'Horizon Medical systems', 'Horizon', '7066014102', '', 'Shop no 117 First floor, Arv Royale, Handewadi Rd, Indira Nagar, pune, Pune', 'Pune', 'Pune', '411048', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(27, 'CUST-27', 'saicare surgical agency', 'Saicare', '9090797376', '', 'plot no.-588,Damana, bhubaneshwar, bhubaneshwar', 'bhubaneshwar', 'bhubaneshwar', '', 'Odisha', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(28, 'CUST-28', 'Akal sirjana services', 'Akal', '7625982199', '', 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', 'Amritsar', '143001', 'Punjab', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(29, 'CUST-29', 'Sai Ganesh Ambulance', 'Sai', '8898245920', '', 'Mumbai, Mumbai, Mumbai', 'Mumbai', 'Mumbai', '', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(30, 'CUST-30', 'Medilife Multispeciality Hospital', 'Dr.Jagdish Jadhav', '9552530205', '', 'Vijay nagar, Kalewadi, Pune', 'Pune', 'Pune', '411017', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(31, 'CUST-31', 'ADVANCE MEDQUIPS', 'Vikrant Bhateja', '9896456475', '', 'House No. 432, Sector-13, Hisar, Hisar, Hisar', 'Hisar', 'Hisar', '125001', 'Haryana', '06AHYPB7443D1Z9', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(32, 'CUST-32', 'Aai mahakali ambulance', 'Jitendra  Vaidhya', '9975546436', '', 'Dervan, Chiplun, Ratnagiri', 'Ratnagiri', 'Ratnagiri', '415605', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(33, 'CUST-33', 'Orchid hospital & diagnostics', 'Orchid', '9339881864', '', 'kalachara (Chatimtala), Chanditala, Hooghly, kolkata', 'kolkata', '', '', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(34, 'CUST-34', 'UMRI Hospital', 'UMRI', '9073699003', '', 'near Behala, Kolkata 35/5, Bose Para Rd, Silpara, Purba barisha, , Kolkata', 'Kolkata', 'Kolkata', '700008', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(35, 'CUST-35', 'Dr.Yogesh Karad', 'Yogesh', '9689935737', '', 'Beed, Beed, Beed', 'Beed', 'Beed', '', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(36, 'CUST-36', 'Medical Systems Services', 'Soumitra', '9433085901', '', '463/D, Pasupati Bhattacharjee Road , , Kolkata', 'Kolkata', 'Kolkata', '700041', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(37, 'CUST-37', 'Omkar Clinic', 'Omkar', '6363873484', '', 'chaochan, chaochan, Bijapur(kar)', 'Bijapur(kar)', 'Bijapur(kar)', '586205', 'Karnataka', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(38, 'CUST-38', 'TVS Multispecialty Hospital', 'Dr.TVS Satthapana', '9443167555', '', 'Evergreen Nagar, , Thanjavur', 'Thanjavur', 'Thanjavur', '613001', 'Tamil Nadu', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(39, 'CUST-39', 'Rx Anweshana Pvt ltd', 'RX', '9163658714', '', 'Shekhpura Church Rd, Shekhpura, Midnapore, Medinipur', 'Medinipur', 'Medinipur', '721101', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(40, 'CUST-40', 'M/s NARESH ENTERPRISES', 'Naresh Manik', '9939188629', '', 'HARIHAR BHAWAN, HOLDING NO. 75, GOLMURI MARKET, GOLMURI, JAMSHEDPUR, East Singhbhum, East Singhbhum', 'East Singhbhum', 'East Singhbhum', '831003', 'Jharkhand', '20CMPPS3674D1ZX', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(41, 'CUST-41', 'BHEL', 'Smita Singhal', '8393009955', '', 'haridwar, Ranipur, Haridwar', 'Haridwar', 'Haridwar', '249403', 'Uttarakhand', '05AAACB4146P1ZL', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(42, 'CUST-42', 'Maa Karna Ambulance', 'Paras  sahu', '9907060062', '', 'Bilaspur, Bilaspur, Bilaspur(cgh)', 'Bilaspur(cgh)', 'Bilaspur(cgh)', '', 'Chhattisgarh', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(43, 'CUST-43', 'Heal life hospital', 'Heal', '8788415400', '', 'Mumbai , Mumbai , Mumbai', 'Mumbai', 'Mumbai', '', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(44, 'CUST-44', 'Om Ambulace Service', 'Pavan Yadav', '9822282942', '', 'Katraj, katraj , Pune', 'Pune', 'Pune', '411043', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(45, 'CUST-45', 'Kapileshwar Ambulance', 'Ashish Borge', '7276087228', '', 'Ahilyanagar, Ahilyanagar, Ahilyanagar', 'Ahilyanagar', 'Ahilyanagar', '', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(46, 'CUST-46', 'M/S ANKUR MEDICAL GASSES', 'Ankur', '9839226406', '', 'L.G. 32, GOYAL PALACE, FAIZABAD ROAD, Lucknow, Lucknow', 'Lucknow', 'Lucknow', '226016', 'Uttar Pradesh', '09AFUPM0997B1ZZ', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(47, 'CUST-47', 'Life Health Care System', 'Life', '7061546323', '', 'Model, c/o jasim alam khatta 1362 khesra 2897 2898 2900, Jain Mandir Road, Bhagalpur, Bhagalpur, Bhagalpur', 'Bhagalpur', 'Bhagalpur', '812006', 'Bihar', '10CKMPA7983E1Z9', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(48, 'CUST-48', 'M/S MEDIX ECORAC', 'Imran Mohamad', '9627797777', '', 'SAI DHARAM KANTA BALAJI DHAM, 4, MINI BYPASS ROAD, Bareilly, Bareilly, Bareilly', 'Bareilly', 'Bareilly', '243122', 'Uttar Pradesh', '09ACZPI9621G2ZY', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(49, 'CUST-49', 'Trinity Healthcare', 'Harshal', '9209189855', '', 'C201, Ganesh Nabhangan, Lane B/20, Opp Hotel Murali , , Pune', 'Pune', 'Pune', '411041', 'Maharashtra', '27BJSPJ5336E1ZZ', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(50, 'CUST-50', 'R K & Son\'s Surgical', 'Rk', '9812063854', '', '4 marla colony, medicine market , Fatehabad , Fatehabad', 'Fatehabad', 'Fatehabad', '', 'Haryana', '06AQIPK8203H4ZU', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(51, 'CUST-51', 'Health Care Surgical', 'Health', '9609452811', '', 'Gopidihi, Nanoor, , Birbhum', 'Birbhum', 'Birbhum', '731215', 'West Bengal', '19COOPM5606N1Z6', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(52, 'CUST-52', 'LOKENATH MEDICARE', 'Arup Chaterjee', '7602077800', '', 'Shekhala ,Hooghly, Hoogly, Hooghly', 'Hooghly', 'Hooghly', '712706', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(53, 'CUST-53', 'GLAMATIC PHARMACEUTICALS PRIVATE LIMITED', 'Parabrita Bhowmik', '9830237757', '', '24, ROOM NO. 203, 2ND FLOOR, HEMANTA BASU SARANI, KOLKATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', '700001', 'West Bengal', '19AAICG0241Q1Z9', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(54, 'CUST-54', 'UVW VISINORY WELLNESS PRIVATE LIMITED', 'Ranjyot singh', '8800434241', '', 'PLOT NO 63, POCKET-M, BAWANA, New Delhi, North West Delhi, North West Delhi', 'North West Delhi', 'North West Delhi', '110039', 'Delhi', '07AADCU6555E1Z9', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(55, 'CUST-55', 'veena Healthcare', 'Bubhakarr kumar', '7310319555', '', 'Begusarai, , Begusarai', 'Begusarai', 'Begusarai', '', 'Bihar', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(56, 'CUST-56', 'SUR ELECTRICAL CO PVT LTD.', 'Chinmay sur', '9830045531', 'sepl.sv71@gmail.com', '3/1, DR SURESH SARKAR ROAD, KOLKATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', '700014', 'West Bengal', '19AADCS5472N1ZP', 'active', '2026-01-21 14:09:34', NULL, 'Chinmay sur', 'B2B', '', NULL, 'Director'),
(57, 'CUST-57', 'SI Surgical', 'Sanjay Mukharjee', '6290822892', '', '47/48, NORTH, near HINDUSTAN MARBLE, Nibra, Howrah, West Bengal , Howrah, Howrah', 'Howrah', 'Howrah', '711409', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(58, 'CUST-58', 'Dr. Vinod Rathod', 'Vinod Rathod', '9522202121', '', 'Raipur, , Raipur', 'Raipur', 'Raipur', '491111', 'Chhattisgarh', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(59, 'CUST-59', 'Om Sai Group Ambulance', 'Pawan Yadav', '9993859903', '', 'Near Cims Hospital God Para, , Bilaspur(cgh)', 'Bilaspur(cgh)', 'Bilaspur(cgh)', '495001', 'Chhattisgarh', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(60, 'CUST-60', 'GYNOTECH', 'Nikhil sharma', '9304677347', '', 'C/O HARI KISHUN THAKUR, VILL-RATAN CHAK, TOLA-RATAN CHAK, KHANSAMA, BLOCK-HATHUA, RATAN CHAK, Gopalganj, Gopalganj', 'Gopalganj', 'Gopalganj', '841436', 'Bihar', '10AATFG9355M1ZT', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(61, 'CUST-61', 'Mediprasher India pvt ltd', 'Rakesh pande', '7319854165', '', 'LUCKNOW, , Lucknow', 'Lucknow', 'Lucknow', '226001', 'Uttar Pradesh', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(62, 'CUST-62', 'MEDILIFE ENTERPRISE', 'Medilife', '9933515504', '', 'RISHI ARABINDA PALLY, DURGAPUR, DURGAPUR, Bardhaman, Bardhaman', 'Bardhaman', 'Bardhaman', '713201', 'West Bengal', '19AGRPD6938K1ZO', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(63, 'CUST-63', 'ONENESS ENTERPRISES', 'Manoj Mule', '9552484701', '', 'GALAXY CORNER BUILDING, FLAT NO. 3, 1st FLOOR, S. NO. 12/2,3,4, NEAR KAILAS JIVAN FACTORY, SAI PURAM BUS STOP, DHAYARI, Pune, Pune', 'Pune', 'Pune', '411041', 'Maharashtra', '27BGMPM8256M1ZJ', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(64, 'CUST-64', 'ABC Hospital', 'Alpesh Doshi', '7020845499', '', 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', 'Solapur', '413208', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(65, 'CUST-65', 'INDIA SURGICALS', 'India', '9156315725', '', 'GAT NO 1324, CHINCHECHA MALA, SONWANE WASTI,CHIKHALI, Pune, Pune', 'Pune', 'Pune', '411062', 'Maharashtra', '27AXSPJ1665M1ZR', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(66, 'CUST-66', 'NEW ANURA SURGICAL', 'Anura', '9021279938', '', 'SHOP NO.2,S NO.247,PLOT NO.5, MAMASAHEB BORKAR MARG, GANESH NAGAR,CHINCHWAD, Pune, Pune', 'Pune', 'Pune', '411033', 'Maharashtra', '27AAUPB5003G1ZX', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(67, 'CUST-67', 'GYMNA UNIPHY INDIA PVT.LTD.', 'Saiket Chaterjee', '8013309054', '', '8B, DR MARTIN LUTHAR KING SARANI, WOOD STREET, Kolkata, Kolkata', 'Kolkata', 'Kolkata', '700016', 'West Bengal', '19AAFCG6238E1ZK', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(68, 'CUST-68', 'Shiva Enterprises', 'Sanjeev  kumar', '9910230177', '', 'B-2/120, shiv main mkt, vijay enclave ext, , South West Delhi', 'South West Delhi', 'Amritsar', '110045', 'Punjab', '', 'active', '2026-01-21 14:09:34', 'Owner', 'Akal', 'B2B', '', NULL, NULL),
(69, 'CUST-69', 'Manjurai Life tricks', 'Manjurai', '9748755022', '', 'Maa chandi Apartment, Thakurdas Babu Lane, Mokadampur , Malda', 'Malda', 'Malda', '732103', 'West Bengal', '', 'active', '2026-01-21 14:09:34', NULL, NULL, 'B2B', NULL, NULL, NULL),
(70, 'CUST-70', 'Zippo Medicon OPC Private Ltd', 'Irshard sharma', '7677933619', 'yashkacontacts@gmail.com', 'Near Makka Masjid Chowk, Hindpiri, , Ranchi', 'Ranchi', 'Pune', '834001', 'Maharashtra', '', 'active', '2026-01-21 14:09:34', 'Director', 'asdad', 'B2B', 'Hospital', NULL, NULL),
(71, 'CUST-71', 'Dr. V R Pawar', 'Vinayak  Pawar', '9403156323', 'yashkacontacts@gmail.com', 'Atpadi,  Sangli', 'Sangli', 'Pune', '411057', 'Maharashtra', 'A001234576PA00988', 'active', '2026-01-21 14:09:34', 'Owner', 'Dipankar Aich', 'B2B', 'Hospital', NULL, NULL),
(75, 'CUST-72', 'The medical equipment', 'Sunil Arora', '9820967982', NULL, '23 6, 10, Sorabji santuk Ln Navajeevan', 'Sonapur marine lines', 'Mumbai', '400002', 'Maharashtra', NULL, 'active', '2026-01-28 13:48:54', NULL, NULL, 'B2B', 'Hospital', NULL, 'CEO'),
(76, 'CUST-73', 'SINGULARITY TECH SOLUTION', 'VINAYAK MANE', '8888603102', NULL, 'GARGOTI', 'KOLHAPUR', 'Kolhapur', '416209', 'Maharashtra', NULL, 'active', '2026-01-29 09:31:18', NULL, NULL, 'B2B', 'Medical Equipment Dealer', NULL, 'Owner'),
(77, 'CUST-74', 'G G Tech', 'Ganesh Ghate', '7503773333', '', 'Hadapsar', '', 'Pune', '', 'Maharashtra', '27BMSPG9637A1ZS', 'active', '2026-01-30 04:58:28', NULL, '', 'B2B', 'Medical Equipment Dealer', NULL, 'Owner'),
(78, 'CUST-75', 'Medway medicare', 'Arjit', '08583966187', 'medwaymedicare2022@gmail.com', 'Garia ganpati market boral main road', 'near Sbi Bank boral branch', 'Kolkata', '700084', 'West Bengal', '19EMWPP7760J1ZU', 'active', '2026-01-31 05:24:25', NULL, 'Arjit', 'B2B', 'Medical Equipment Dealer', NULL, 'Director');

-- --------------------------------------------------------

--
-- Table structure for table `customer_documents`
--

CREATE TABLE `customer_documents` (
  `id` int(11) NOT NULL,
  `customer_id` varchar(50) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_po`
--

CREATE TABLE `customer_po` (
  `id` int(11) NOT NULL,
  `po_no` varchar(100) NOT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `linked_quote_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_po`
--

INSERT INTO `customer_po` (`id`, `po_no`, `customer_id`, `po_date`, `attachment_path`, `notes`, `status`, `created_at`, `linked_quote_id`) VALUES
(1, 'PO-002', 'CUST-4', '2026-01-17', 'uploads/customer_po/CPO_PO_002_1768675394.pdf', 'cdcv', 'active', '2026-01-17 18:43:14', 1),
(2, '4646474', 'CUST-5', '2026-01-18', 'uploads/customer_po/CPO_4646474_1768706177.pdf', 'shhwss', 'active', '2026-01-18 03:16:17', 2),
(3, '11144', 'CUST-4', '2026-01-18', 'uploads/customer_po/CPO_11144_1768731459.pdf', 'dvd', 'active', '2026-01-18 10:17:39', 4),
(4, 'de', 'CUST-3', '2026-01-21', 'uploads/customer_po/CPO_de_1768971733.pdf', 'dcdf', 'active', '2026-01-21 05:02:13', 5),
(5, '1234', 'CUST-28', '2026-01-23', 'uploads/customer_po/CPO_1234_1769131575.pdf', 'xx', 'active', '2026-01-23 01:26:15', 6),
(6, 'shdhd', 'CUST-28', '2026-01-23', NULL, '', 'active', '2026-01-23 02:12:10', 6),
(7, 'jhg', 'CUST-28', '2026-01-23', 'uploads/customer_po/CPO_jhg_1769135081.pdf', '', 'active', '2026-01-23 02:24:41', 7),
(8, 'p123', 'CUST-30', '2026-01-23', 'uploads/customer_po/CPO_p123_1769141796.pdf', 'jddh', 'active', '2026-01-23 04:16:36', 9),
(9, 'Po123', 'CUST-64', '2026-01-23', 'uploads/customer_po/CPO_Po123_1769165769.pdf', '', 'active', '2026-01-23 10:56:09', 8),
(10, 'po543', 'CUST-64', '2026-01-24', 'uploads/customer_po/CPO_po543_1769220144.pdf', '', 'active', '2026-01-24 02:02:24', 11),
(11, 'PO-12/2026', 'CUST-46', '2026-01-24', 'uploads/customer_po/CPO_PO_12_2026_1769264666.pdf', '', 'active', '2026-01-24 14:24:26', 20),
(12, 'PO0923', 'CUST-28', '2026-01-24', 'uploads/customer_po/CPO_PO0923_1769272695.pdf', '', 'active', '2026-01-24 16:38:15', 12),
(13, 'po9878', 'CUST-55', '2026-01-24', 'uploads/customer_po/CPO_po9878_1769273084.pdf', '', 'active', '2026-01-24 16:44:44', 13),
(14, 'po9876', 'CUST-55', '2026-01-25', 'uploads/customer_po/CPO_po9876_1769306934.pdf', 'dfd', 'active', '2026-01-25 02:08:54', 18),
(15, 'poi123', 'CUST-28', '2026-01-25', 'uploads/customer_po/CPO_poi123_1769311254.pdf', '', 'active', '2026-01-25 03:20:54', 21),
(16, 'PO123', 'CUST-64', '2026-01-25', 'uploads/customer_po/CPO_PO123_1769317990.pdf', '', 'active', '2026-01-25 05:13:10', 22),
(17, 'po876', 'CUST-66', '2026-01-25', 'uploads/customer_po/CPO_po876_1769332265.pdf', '', 'active', '2026-01-25 09:11:05', 23),
(18, 'po9843', 'CUST-52', '2026-01-25', 'uploads/customer_po/CPO_po9843_1769332949.pdf', '', 'active', '2026-01-25 09:22:29', 24),
(19, 'po987', 'CUST-12', '2026-01-26', 'uploads/customer_po/CPO_po987_1769397495.pdf', 'test', 'active', '2026-01-26 03:18:15', 25),
(20, 'po2341', 'CUST-12', '2026-01-26', 'uploads/customer_po/CPO_po2341_1769406953.pdf', 'PO for 100% payment', 'active', '2026-01-26 05:55:53', 10),
(21, 'Po12', 'CUST-28', '2026-01-26', 'uploads/customer_po/CPO_Po12_1769446456.pdf', '', 'active', '2026-01-26 16:54:16', 26),
(22, 'po099', 'CUST-28', '2026-01-27', 'uploads/customer_po/CPO_po099_1769503863.pdf', '', 'active', '2026-01-27 08:51:03', 30),
(23, 'po12233', 'CUST-64', '2026-01-27', 'uploads/customer_po/CPO_po12233_1769520224.pdf', '', 'active', '2026-01-27 13:23:44', 31),
(24, 'PO-002', 'CUST-72', '2026-01-28', 'uploads/customer_po/CPO_PO_002_1769608307.pdf', '', 'active', '2026-01-28 13:51:47', 33),
(25, '12', 'CUST-5', '2026-01-29', 'uploads/customer_po/CPO_12_1769679951.pdf', '', 'active', '2026-01-29 09:45:51', 52),
(26, 'po342', 'CUST-12', '2026-01-29', 'uploads/customer_po/CPO_po342_1769701782.pdf', '', 'active', '2026-01-29 15:49:42', 76),
(27, 'PO12', 'CUST-11', '2026-01-30', 'uploads/customer_po/CPO_PO12_1769740486.pdf', '', 'active', '2026-01-30 02:34:46', 79),
(28, 'powuwu', 'CUST-52', '2026-02-01', 'uploads/customer_po/CPO_powuwu_1769910905.pdf', 'dd', 'active', '2026-02-01 01:55:05', 80);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `head_id`, `created_at`) VALUES
(1, 'Administration', NULL, NULL, '2026-01-18 14:33:47'),
(2, 'Human Resources', NULL, NULL, '2026-01-18 14:33:47'),
(3, 'Finance', NULL, NULL, '2026-01-18 14:33:47'),
(4, 'Sales', NULL, NULL, '2026-01-18 14:33:47'),
(5, 'Marketing', NULL, NULL, '2026-01-18 14:33:47'),
(6, 'Production', NULL, NULL, '2026-01-18 14:33:47'),
(7, 'Quality Control', NULL, NULL, '2026-01-18 14:33:47'),
(8, 'Warehouse', NULL, NULL, '2026-01-18 14:33:47'),
(9, 'IT', NULL, NULL, '2026-01-18 14:33:47'),
(10, 'Maintenance', NULL, NULL, '2026-01-18 14:33:47'),
(11, 'Fabrication', NULL, NULL, '2026-01-29 17:53:07'),
(12, 'Kronos', NULL, NULL, '2026-01-29 17:53:07'),
(13, 'Accounts', NULL, NULL, '2026-01-29 17:56:03'),
(14, 'Electrical', NULL, NULL, '2026-01-29 17:56:03'),
(15, 'Electronics', NULL, NULL, '2026-01-29 17:56:03'),
(16, 'Engineering', NULL, NULL, '2026-01-29 17:56:03'),
(17, 'Logistics', NULL, NULL, '2026-01-29 18:01:32'),
(18, 'NPD', NULL, NULL, '2026-01-29 18:01:32'),
(19, 'SCM', NULL, NULL, '2026-01-29 18:01:32');

-- --------------------------------------------------------

--
-- Table structure for table `depletion`
--

CREATE TABLE `depletion` (
  `id` int(11) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `adjustment_type` varchar(20) DEFAULT 'depletion',
  `issue_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `depletion`
--

INSERT INTO `depletion` (`id`, `part_no`, `qty`, `issue_date`, `reason`, `status`, `adjustment_type`, `issue_no`) VALUES
(1, '22005001', 4, '2026-01-04', 'Work Order', 'issued', 'depletion', 'W001'),
(2, '32005012', 4, '2026-01-06', 'Work Order', 'issued', 'depletion', 'wo004'),
(3, '95005006', 1, '2026-01-08', 'Work Order', 'issued', 'depletion', 'w005'),
(4, '22005231', 2, '2026-01-08', 'Work Order', 'issued', 'depletion', 'w005'),
(5, '22005458', 1, '2026-01-08', 'Work Order', 'issued', 'depletion', 'w005'),
(6, '62005019', 2, '2026-01-08', 'Work Order', 'issued', 'depletion', 'w005'),
(7, '62005022', 4, '2026-01-08', 'Work Order', 'issued', 'depletion', 'w005'),
(8, '22005519', 2, '2026-01-08', 'Work Order', 'issued', 'depletion', 'w005'),
(9, '95005006', 1, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-1'),
(10, '22005231', 2, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-1'),
(11, '22005458', 1, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-1'),
(12, '62005019', 2, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-1'),
(13, '62005022', 4, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-1'),
(14, '22005519', 2, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-1'),
(15, '62005022', 1, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-2'),
(16, '62005022', 1, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-3'),
(17, '62005022', 1, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-4'),
(18, '62005022', 1, '2026-01-09', 'Work Order', 'issued', 'depletion', 'WO-5'),
(19, '22005231', 1, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-6'),
(20, '22005698', 2, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-6'),
(21, '22005231', 1, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-7'),
(22, '22005698', 2, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-7'),
(23, '22005231', 1, '2026-01-10', 'Test', 'issued', 'depletion', 'ISS-1768044601'),
(24, '22005231', 4, '2026-01-10', 'Test', 'issued', 'depletion', 'ISS-1768044632'),
(25, '22005231', 1, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-8'),
(26, '22005698', 2, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-8'),
(27, '22005231', 2, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-9'),
(28, '22005698', 4, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-9'),
(29, '22005231', 1, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-10'),
(30, '22005698', 2, '2026-01-10', 'Work Order', 'issued', 'depletion', 'WO-10'),
(31, '32005012', 9, '2026-01-10', 'asd', 'issued', 'depletion', 'ISS-1768060530'),
(32, '32005012', 8, '2026-01-10', 'test', 'issued', 'depletion', 'ISS-1768060541'),
(33, '22005001', 2, '2026-01-10', 'test', 'issued', 'depletion', 'ISS-1768063438'),
(34, '95005006', 5, '2026-01-15', 'service', 'issued', 'depletion', 'ISS-1768474768'),
(35, '62005022', 1, '2026-01-18', 'Invoice Release: INV/1/25/26', 'active', 'depletion', 'INV/1/25/26'),
(36, '46005005', 1, '2026-01-18', 'Invoice Release: INV/2/25/26', 'active', 'depletion', 'INV/2/25/26'),
(37, '95005006', 1, '2026-01-18', 'dhdhf', 'issued', 'depletion', 'ISS-1768731926'),
(38, '22005519', 1, '2026-01-21', 'Invoice Release: INV/3/25/26', 'active', 'depletion', 'INV/3/25/26'),
(39, 'YID-044', 1, '2026-01-23', 'Invoice Release: INV/4/25/26', 'active', 'depletion', 'INV/4/25/26'),
(40, 'YID-063', 1, '2026-01-23', 'Invoice Release: INV/4/25/26', 'active', 'depletion', 'INV/4/25/26'),
(41, 'YID-0124', 1, '2026-01-23', 'test', 'issued', 'addition', 'ADJ-1769144523'),
(42, 'YID-044', 2, '2026-01-23', 'test', 'issued', 'addition', 'ADJ-1769165977'),
(43, 'YID-063', 2, '2026-01-23', 'tes', 'issued', 'addition', 'ADJ-1769165999'),
(44, 'YID-044', 1, '2026-01-23', 'Invoice Release: INV/6/25/26', 'active', 'depletion', 'INV/6/25/26'),
(45, 'YID-063', 1, '2026-01-23', 'Invoice Release: INV/6/25/26', 'active', 'depletion', 'INV/6/25/26'),
(46, 'YID-0124', 1, '2026-01-23', 'Invoice Release: INV/5/25/26', 'active', 'depletion', 'INV/5/25/26'),
(47, 'YID -043', 1, '2026-01-24', 'Invoice Release: INV/7/25/26', 'active', 'depletion', 'INV/7/25/26'),
(48, 'YID-063', 1, '2026-01-24', 'Invoice Release: INV/8/25/26', 'active', 'depletion', 'INV/8/25/26'),
(49, 'YID -043', 1, '2026-01-25', 'Invoice Release: INV/9/25/26', 'active', 'depletion', 'INV/9/25/26'),
(50, 'YID-008', 1, '2026-01-25', 'Invoice Release: INV/11/25/26', 'active', 'depletion', 'INV/11/25/26'),
(51, 'YID -043', 1, '2026-01-25', 'Invoice Release: INV/10/25/26', 'active', 'depletion', 'INV/10/25/26'),
(52, 'YID -043', 1, '2026-01-25', 'Invoice Release: INV/12/25/26', 'active', 'depletion', 'INV/12/25/26'),
(53, 'YID -043', 1, '2026-01-25', 'Invoice Release: INV/13/25/26', 'active', 'depletion', 'INV/13/25/26'),
(54, '83005101', 1, '2026-01-29', '..', 'issued', 'addition', 'ADJ-1769677586'),
(55, '32005653', 2, '2026-01-29', '..', 'issued', 'addition', 'ADJ-1769692010'),
(56, 'YID-044', 1, '2026-01-29', 'Invoice Release: INV/15/25/26', 'active', 'depletion', 'INV/15/25/26'),
(57, 'YID-044', 1, '2026-01-30', 'Invoice Release: INV/16/25/26', 'active', 'depletion', 'INV/16/25/26'),
(58, '82005431', 100, '2026-01-30', 'test', 'issued', 'addition', 'ADJ-1769743132'),
(59, '82005211', 50, '2026-01-30', 'test', 'issued', 'addition', 'ADJ-1769743419'),
(60, '82005431', 1, '2026-01-30', 'Work Order Close: WO-43', 'active', 'depletion', 'WO-43'),
(61, '82005211', 4, '2026-01-30', 'Work Order Close: WO-43', 'active', 'depletion', 'WO-43'),
(62, '32005852', 2, '2026-01-30', '..', 'issued', 'addition', 'ADJ-1769749142'),
(63, '32005853', 2, '2026-01-30', '..', 'issued', 'addition', 'ADJ-1769749157'),
(64, '32005852', 1, '2026-01-31', 'Work Order Close: WO-51 [REVERSED]', 'active', 'depletion', 'WO-51'),
(65, '32005853', 1, '2026-01-31', 'Work Order Close: WO-51 [REVERSED]', 'active', 'depletion', 'WO-51'),
(66, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52 [REVERSED]', 'active', 'depletion', 'WO-52'),
(67, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52 [REVERSED]', 'active', 'depletion', 'WO-52'),
(68, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(69, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(70, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(71, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(72, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(73, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(74, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(75, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(76, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(77, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(78, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(79, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(80, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(81, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52', 'active', 'depletion', 'WO-52'),
(82, '32005852', 1, '2026-01-31', 'Work Order Close: WO-52 [REVERSED]', 'active', 'depletion', 'WO-52'),
(83, '32005853', 1, '2026-01-31', 'Work Order Close: WO-52 [REVERSED]', 'active', 'depletion', 'WO-52');

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `type_code` varchar(20) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `requires_expiry` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `type_name`, `type_code`, `category`, `is_mandatory`, `requires_expiry`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'PAN Card', 'PAN', 'Identity', 1, 0, 1, 1, '2026-01-30 04:20:55'),
(2, 'Aadhaar Card', 'AADHAAR', 'Identity', 1, 0, 1, 2, '2026-01-30 04:20:55'),
(3, 'Passport', 'PASSPORT', 'Identity', 0, 1, 1, 3, '2026-01-30 04:20:55'),
(4, 'Driving License', 'DL', 'Identity', 0, 1, 1, 4, '2026-01-30 04:20:55'),
(5, 'Voter ID', 'VOTER', 'Identity', 0, 0, 1, 5, '2026-01-30 04:20:55'),
(6, '10th Marksheet', '10TH', 'Education', 0, 0, 1, 10, '2026-01-30 04:20:55'),
(7, '12th Marksheet', '12TH', 'Education', 0, 0, 1, 11, '2026-01-30 04:20:55'),
(8, 'Degree Certificate', 'DEGREE', 'Education', 0, 0, 1, 12, '2026-01-30 04:20:55'),
(9, 'Post Graduation Certificate', 'PG', 'Education', 0, 0, 1, 13, '2026-01-30 04:20:55'),
(10, 'Professional Certification', 'CERT', 'Education', 0, 1, 1, 14, '2026-01-30 04:20:55'),
(11, 'Experience Letter', 'EXP_LETTER', 'Employment', 0, 0, 1, 20, '2026-01-30 04:20:55'),
(12, 'Relieving Letter', 'RELIEVING', 'Employment', 0, 0, 1, 21, '2026-01-30 04:20:55'),
(13, 'Offer Letter', 'OFFER', 'Employment', 0, 0, 1, 22, '2026-01-30 04:20:55'),
(14, 'Appointment Letter', 'APPOINT', 'Employment', 0, 0, 1, 23, '2026-01-30 04:20:55'),
(15, 'Bank Passbook/Statement', 'BANK', 'Financial', 0, 0, 1, 30, '2026-01-30 04:20:55'),
(16, 'Cancelled Cheque', 'CHEQUE', 'Financial', 0, 0, 1, 31, '2026-01-30 04:20:55'),
(17, 'Address Proof', 'ADDRESS', 'Other', 0, 0, 1, 40, '2026-01-30 04:20:55'),
(18, 'Photo', 'PHOTO', 'Other', 0, 0, 1, 41, '2026-01-30 04:20:55'),
(19, 'Other Document', 'OTHER', 'Other', 0, 0, 1, 99, '2026-01-30 04:20:55');

-- --------------------------------------------------------

--
-- Table structure for table `eco_affected_parts`
--

CREATE TABLE `eco_affected_parts` (
  `id` int(11) NOT NULL,
  `eco_id` int(11) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `part_description` varchar(255) DEFAULT NULL,
  `current_revision` varchar(20) DEFAULT NULL,
  `new_revision` varchar(20) DEFAULT NULL,
  `change_description` text DEFAULT NULL,
  `disposition` enum('Use As Is','Rework','Scrap','Return to Supplier','Other') DEFAULT 'Use As Is',
  `stock_impact` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `eco_approvals`
--

CREATE TABLE `eco_approvals` (
  `id` int(11) NOT NULL,
  `eco_id` int(11) NOT NULL,
  `approver_role` varchar(100) NOT NULL,
  `approver_name` varchar(100) DEFAULT NULL,
  `approver_user_id` int(11) DEFAULT NULL,
  `sequence_order` int(11) DEFAULT 1,
  `status` enum('Pending','Approved','Rejected','Skipped') DEFAULT 'Pending',
  `comments` text DEFAULT NULL,
  `action_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `emp_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT 'Single',
  `blood_group` varchar(5) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Indian',
  `phone` varchar(20) NOT NULL,
  `alt_phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `personal_email` varchar(150) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contract','Intern','Trainee') DEFAULT 'Full-time',
  `date_of_joining` date NOT NULL,
  `date_of_leaving` date DEFAULT NULL,
  `reporting_to` int(11) DEFAULT NULL,
  `work_location` varchar(100) DEFAULT NULL,
  `aadhar_no` varchar(20) DEFAULT NULL,
  `pan_no` varchar(20) DEFAULT NULL,
  `passport_no` varchar(20) DEFAULT NULL,
  `driving_license` varchar(30) DEFAULT NULL,
  `uan_no` varchar(20) DEFAULT NULL,
  `pf_no` varchar(30) DEFAULT NULL,
  `esi_no` varchar(30) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account` varchar(30) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(150) DEFAULT NULL,
  `basic_salary` decimal(12,2) DEFAULT 0.00,
  `hra` decimal(12,2) DEFAULT 0.00,
  `conveyance` decimal(12,2) DEFAULT 0.00,
  `medical_allowance` decimal(12,2) DEFAULT 0.00,
  `special_allowance` decimal(12,2) DEFAULT 0.00,
  `other_allowance` decimal(12,2) DEFAULT 0.00,
  `performance_allowance` decimal(10,2) DEFAULT 0.00,
  `food_allowance` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive','On Leave','Resigned','Terminated') DEFAULT 'Active',
  `photo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `emp_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `marital_status`, `blood_group`, `nationality`, `phone`, `alt_phone`, `email`, `personal_email`, `address_line1`, `address_line2`, `city`, `state`, `pincode`, `country`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `department`, `designation`, `employment_type`, `date_of_joining`, `date_of_leaving`, `reporting_to`, `work_location`, `aadhar_no`, `pan_no`, `passport_no`, `driving_license`, `uan_no`, `pf_no`, `esi_no`, `bank_name`, `bank_account`, `bank_ifsc`, `bank_branch`, `basic_salary`, `hra`, `conveyance`, `medical_allowance`, `special_allowance`, `other_allowance`, `performance_allowance`, `food_allowance`, `status`, `photo_path`, `notes`, `created_at`, `updated_at`) VALUES
(62, 'EMP-0001', 'Rajesh Appasaheb', 'Gondhali', '1972-05-04', 'Male', 'Married', NULL, 'Indian', '8698222858', NULL, 'rajesh.gondhali@yashka.io', 'gondhlirajesh@gmail.com', 'hinjewadi,maan bharne wasti', 'hinjewadi,maan bharne wasti', 'pune', 'maharashtra', '411057', 'India', 'prathemesh gondhali', 'son', '9359437848', 'Logistics', 'Logistic', 'Full-time', '2020-03-13', NULL, 74, 'Head Office', '752900298358', 'AHXPG1722D', NULL, NULL, '100318030795', NULL, NULL, 'ICIC Bank', '0985 0151 2868', ' ICIC0000985', 'Hinjewadi 2 BLUE RIDGE', 27000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-29 18:49:10'),
(63, 'EMP-0002', 'Neha Govind', 'Ukale', '2000-05-03', 'Female', 'Married', NULL, 'Indian', '9834700927', NULL, 'neha.ukale@yashka.io', NULL, NULL, NULL, NULL, NULL, NULL, 'India', NULL, NULL, NULL, 'Sales', 'Sales Executive', 'Full-time', '2021-03-11', NULL, NULL, 'Head Office', '939089039177', 'ANDPU7391J', NULL, NULL, NULL, NULL, NULL, 'HDFC Bank', NULL, NULL, 'Hinjewadi 2 BLUE RIDGE', 65000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-30 04:49:17'),
(64, 'EMP-0003', 'Arvind Uttam', 'pawar', '1986-06-04', 'Male', 'Married', NULL, 'Indian', '9075916359', NULL, 'arvind.pawar@yashka.io', NULL, NULL, NULL, NULL, NULL, NULL, 'India', NULL, NULL, NULL, 'Production', 'Production Assistant', 'Full-time', '2021-04-29', NULL, NULL, 'Head Office', '689730230167', 'BETPP2066R', NULL, NULL, NULL, NULL, NULL, 'HDFC Bank', NULL, NULL, 'Hinjewadi 2 BLUE RIDGE', 27522.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-22 16:59:46'),
(65, 'EMP-0004', 'Pranjali mahadev', 'Sampate', '2000-09-22', 'Female', 'Married', NULL, 'Indian', '7387839917', NULL, 'pranjali.sampate@yashka.io', NULL, NULL, NULL, NULL, NULL, NULL, 'India', NULL, NULL, NULL, 'Production', 'TESTING', 'Full-time', '2023-02-15', NULL, 74, 'Head Office', '809082137599', 'MKSPS7599Q', NULL, NULL, NULL, NULL, NULL, 'HDFC Bank', NULL, NULL, 'Hinjewadi 2 BLUE RIDGE', 32000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-31 04:41:13'),
(66, 'EMP-0005', 'Priyanka Ramesh', 'Gaikwad', '1997-11-01', 'Female', 'Single', 'O+', 'Indian', '9370435178', '9657547130', 'priyanka@yashka.io', 'priyagaykwad78@gmail.com', 'Adhishakti building chapekar chowk', 'Chapekar Chowk Chinchwad', ' Chinchwad', 'Maharastra', '411033', 'India', 'Ramesh Gaikwad', 'Father', '77094095688', NULL, 'Electrical Head', 'Full-time', '2023-09-12', NULL, NULL, 'Head Office', '424317640058', 'CETPG4248D', NULL, NULL, '101379508010', NULL, NULL, 'HDFC Bank', NULL, NULL, 'Hinjewadi 2 BLUE RIDGE', 26500.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-29 09:19:07'),
(67, 'EMP-0006', 'Vikram  Popat', 'Pawar', '1995-06-25', 'Male', 'Married', 'A+', 'Indian', '7709730190', '8329619722', 'vikram.pawar@yashka.io', 'vikrampawar77097@gmail.com', 'hinjewadi maan ', 'hinjewadi maan ', 'Pune', 'Maharashtra', '411057', 'India', 'vaishali pawar', 'wife', '8329619722', 'Electrical', 'Electrical/Electronic Head', 'Full-time', '2021-08-07', NULL, 74, 'Head Office', '724330514664', 'EPQPP7918F', NULL, NULL, '102032197903', NULL, NULL, 'HDFC Bank', '50100459469835    ', 'HDFC0004884', 'Hinjewadi 2 BLUE RIDGE', 15000.00, 2500.00, 0.00, 0.00, 4500.00, 0.00, 0.00, 0.00, 'Active', 'uploads/employees/EMP-0006_1769768337.jpeg', NULL, '2026-01-22 16:59:46', '2026-01-30 10:21:26'),
(68, 'EMP-0007', 'Omkar Govind', 'ukhale', '2003-03-08', 'Male', 'Single', NULL, 'Indian', '8600868096', NULL, 'omkar.ukhale@yashka.io', NULL, NULL, NULL, NULL, NULL, NULL, 'India', NULL, NULL, NULL, 'Account', 'Account Executive', 'Full-time', '2023-10-01', NULL, NULL, 'Head Office', '341269528079', 'APUPU6633C', NULL, NULL, NULL, NULL, NULL, 'HDFC Bank', NULL, NULL, 'Hinjewadi 2 BLUE RIDGE', 15000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-22 16:59:46'),
(69, 'EMP-0008', 'Wasim Ahmad', 'Ahmad', '1988-08-15', 'Male', 'Married', 'O+', 'Indian', '9657496762', '9370667697', 'wasim.ahmad@yashka.io', NULL, 'Maan Hinjewadi ', 'Phase 3 ', 'Pune', 'Maharashtra', '411057', 'India', NULL, NULL, NULL, 'IT', 'Head', 'Full-time', '2021-08-07', NULL, NULL, 'Head Office', '253968958776', 'BDFPA0686K', NULL, NULL, '101339487549', NULL, NULL, 'HDFC Bank', '50100284119892', 'HDFC0009116', 'Akurdi', 24500.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', 'uploads/employees/EMP-0008_1769834722.jpg', NULL, '2026-01-22 16:59:46', '2026-01-31 04:45:22'),
(70, 'EMP-0009', 'Ms. Kishori Prakash', 'Gadakh', '1998-12-05', 'Female', 'Married', 'AB+', 'Indian', '7499180634', '7888033097', 'kishorigadakh@yashka.io', 'kishorigadakh@gmail.com', 'c-101 Milenium Atlas', 'Ram nagar tathwade', NULL, 'Maharashtra', '411033', 'India', 'Ram kardel', 'Spouse', '9823142065', 'Sales', 'Sales Executive', 'Full-time', '2019-08-17', NULL, NULL, 'Head Office', '807757713549', 'EDRPG2232N', NULL, NULL, '102095228482', '102095228482', NULL, 'HDFC Bank', '50100459470520', 'HDFC0004884', 'Hinjewadi 2 BLUE RIDGE', 60000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-30 06:10:34'),
(71, 'EMP-0010', 'Sana mahammad', 'Nayakwadi', '1994-08-07', 'Female', 'Single', 'B+', 'Indian', '7517301231', '9890341436', 'sana.nayakwadi@yashka.io', 'sananayakwadi@gmail.com', 'thaka rnagar,maan', 'gurukrupa socirty', 'pune', 'maharashtra', '411057', 'India', 'sabraj', 'mother', '9890341436', 'Sales', 'Sales executive', 'Full-time', '2023-12-14', NULL, NULL, 'Head Office', '982589514667', 'BLIPN5480M', NULL, NULL, '101384003224', NULL, NULL, 'Bank of baroda', '98630100005768', 'BARBOVJWAKA', 'WAKAD,PUNE', 25000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-22 16:59:46'),
(72, 'EMP-0011', 'Ashwani Chitraranjan', 'Kumar', '1998-02-06', 'Male', 'Married', NULL, 'Indian', '9334106868', NULL, 'ashwani.kumar@yashka.io', 'Ashwanikumarbhoja@gmail.com', NULL, NULL, NULL, NULL, NULL, 'India', NULL, NULL, NULL, 'Field', 'Field Tech.&Application Coordinator', 'Full-time', '2029-08-24', NULL, NULL, 'field', '891799173255', 'GFVPK8400J', NULL, NULL, NULL, NULL, NULL, 'HDFC Bank', NULL, NULL, 'Hinjewadi 2 BLUE RIDGE', 35000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-22 16:59:46'),
(73, 'EMP-0012', 'Abhinav Chitraranjan', 'Kumar', '2001-01-15', 'Male', 'Single', NULL, 'Indian', '9319750060', NULL, 'abhinav.kumar@yashka.io', NULL, NULL, NULL, NULL, NULL, NULL, 'India', NULL, NULL, NULL, 'Production', 'Production Assistant', 'Full-time', '2021-05-05', NULL, NULL, 'Head Office', '529550879486', 'IUVPK2103M', NULL, NULL, NULL, NULL, NULL, 'HDFC Bank', NULL, NULL, 'Hinjewadi 2 BLUE RIDGE', 30000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-22 16:59:46', '2026-01-22 16:59:46'),
(74, 'EMP-0013', 'Dipankar', 'Aich', '1977-01-02', 'Male', 'Married', 'B+', 'Indian', '7775823557', NULL, 'yashkacontacts@gmail.com', 'yashkacontacts@gmail.com', 'Megapolis', 'Mystic', 'Pune', 'Maharashtra', '411057', 'India', '8669234153', NULL, 'Aryan', 'Administration', 'CEO', 'Full-time', '2015-01-01', NULL, NULL, 'Pune', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-24 07:41:09', '2026-01-24 07:41:09'),
(75, 'EMP-0014', 'Shravani Jitendra', 'Jadhav', '2002-05-24', 'Female', 'Married', 'B+', 'Indian', '9067270108', '8805495928', 'shravani@yashka.io', 'shravanijj2002@gmail.com', 'hinjewadi maan thakar nagar', 'hinjewadi maan thakar nagar', 'Pune', 'Maharashtra', '411057', 'India', 'prathemsh bhosale', 'spouse', '7887978741', 'Human Resources', 'hr executive', 'Full-time', '2023-04-01', NULL, 74, 'Head Office', '711223543354', 'DAPPJ6105K', NULL, NULL, '102095486651', NULL, NULL, 'HDFC', '50100422647669', 'HDFC0000801', 'HINJEWADI 2 BLUE RIDGE', 6400.00, 2560.00, 2000.00, 0.00, 0.00, 1040.00, 2000.00, 2000.00, 'Active', 'uploads/employees/EMP-0014_1769769007.jpeg', NULL, '2026-01-29 08:03:06', '2026-01-30 10:57:36'),
(76, 'EMP-0015', 'Sandip', 'jaiwant', '2001-06-20', 'Male', 'Single', 'B+', 'Indian', '7458056124', '8318405011', 'sandeep.jaiwant@yashka.io', 'sandeep94539146@gmail.com', '539kha/995choti jugauli gomti nagar lucknow', '539kha/995choti jugauli gomti nagar lucknow', 'lucknow', 'uttarpradesh', NULL, 'India', 'vijay kumar', 'father', '8318405011', NULL, 'field tech & application coordinator', 'Full-time', '2025-02-26', NULL, 74, 'Field', '5.00599E+11', 'GYLPK3208R', NULL, NULL, NULL, NULL, NULL, 'PNB ', '762000109182956', 'PUNB0076200', 'Niahat ganj lucknow', 30000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-29 08:45:40', '2026-01-29 08:45:40'),
(77, 'EMP-0016', 'Ayan', 'shaikh', '2003-08-25', 'Male', 'Single', 'O+', 'Indian', '8881085150', '9370667697', 'ayaanmd@yashka.io', 'ayansheikh1921836@gmail.com', 'hinjewadi jakat naka', 'hinjewadi jakat naka', 'pune', 'maharashtra', '411057', 'India', 'wasim sheikh', 'brother', '9370667697', NULL, 'Design assistant', 'Full-time', '2023-01-01', NULL, NULL, 'Head Office', '476459375283', 'EWXPA2001Q', NULL, NULL, '102095486646', NULL, NULL, 'HDFC', '50100420825852.00', 'HDFC0000800', 'HINJEWADI 2 BLUE RIDGE', 25000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-29 08:52:15', '2026-01-29 08:52:15'),
(78, 'EMP-0017', 'Irshad', 'Ansari', '1998-01-01', 'Male', 'Married', 'O+', 'Indian', '7388817819', '923657258', 'irshad.ansari@yashka.io', 'nooralamsnsari164@gmail.com', 'sikandar post roari,motipur,ramkola', 'sikandar post roari,motipur,ramkola', 'kushinagar', 'uttarpradesh', '274305', 'India', 'farija khatoon', 'wife', '923657258', NULL, 'welder', 'Full-time', '2023-04-24', NULL, 74, 'Head Office', '387252928559', 'DCGPA9019E', NULL, NULL, '101521035640', NULL, NULL, 'HDFC', '50100610863868.00', 'HDFC0000794', 'HINJEWADI 2 BLUE RIDGE', 27000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Active', NULL, NULL, '2026-01-29 09:00:46', '2026-01-29 09:00:46');

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_skills`
--

CREATE TABLE `employee_skills` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `proficiency_level` enum('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Beginner',
  `years_experience` decimal(4,1) DEFAULT 0.0,
  `last_used_date` date DEFAULT NULL,
  `certified` tinyint(1) DEFAULT 0,
  `certification_name` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `engineering_reviews`
--

CREATE TABLE `engineering_reviews` (
  `id` int(11) NOT NULL,
  `review_no` varchar(30) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `review_type` enum('Concept Review','Preliminary Design Review','Critical Design Review','Production Readiness Review','Post-Production Review','Other') NOT NULL,
  `review_title` varchar(255) NOT NULL,
  `review_date` date NOT NULL,
  `review_location` varchar(255) DEFAULT NULL,
  `review_leader` varchar(100) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
  `outcome` enum('Approved','Approved with Comments','Conditional Approval','Not Approved','Pending') DEFAULT 'Pending',
  `description` text DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `scope` text DEFAULT NULL,
  `participants` text DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `key_decisions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `next_review_date` date DEFAULT NULL,
  `next_review_type` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('National','Regional','Company','Optional') DEFAULT 'Company',
  `year` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `holiday_date`, `name`, `type`, `year`, `created_at`) VALUES
(3, '2026-01-26', 'Republic day', 'National', 2026, '2026-01-29 09:10:50');

-- --------------------------------------------------------

--
-- Table structure for table `india_cities`
--

CREATE TABLE `india_cities` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `city_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `india_cities`
--

INSERT INTO `india_cities` (`id`, `state_id`, `city_name`) VALUES
(1, 1, 'Andaman City'),
(2, 1, 'Port Blair'),
(3, 2, 'Hyderabad'),
(4, 2, 'Visakhapatnam'),
(5, 2, 'Vijayawada'),
(6, 2, 'Tirupati'),
(7, 24, 'Aizawl'),
(8, 4, 'Dispur'),
(9, 4, 'Guwahati'),
(10, 4, 'Silchar'),
(11, 5, 'Patna'),
(12, 5, 'Gaya'),
(13, 5, 'Bhagalpur'),
(14, 5, 'Muzaffarpur'),
(15, 7, 'Raipur'),
(16, 7, 'Bilaspur'),
(17, 7, 'Durg'),
(18, 10, 'Panaji'),
(19, 10, 'Margao'),
(20, 10, 'Vasco da Gama'),
(21, 11, 'Ahmedabad'),
(22, 11, 'Surat'),
(23, 11, 'Vadodara'),
(24, 11, 'Rajkot'),
(25, 12, 'Faridabad'),
(26, 12, 'Gurgaon'),
(27, 12, 'Hisar'),
(28, 13, 'Shimla'),
(29, 13, 'Mandi'),
(30, 13, 'Solan'),
(31, 15, 'Ranchi'),
(32, 15, 'Jamshedpur'),
(33, 15, 'Dhanbad'),
(34, 15, 'Giridih'),
(35, 16, 'Bangalore'),
(36, 16, 'Mysore'),
(37, 16, 'Mangalore'),
(38, 16, 'Belgaum'),
(39, 17, 'Kochi'),
(40, 17, 'Thiruvananthapuram'),
(41, 17, 'Kozhikode'),
(42, 17, 'Thrissur'),
(43, 20, 'Indore'),
(44, 20, 'Bhopal'),
(45, 20, 'Jabalpur'),
(46, 20, 'Gwalior'),
(47, 21, 'Mumbai'),
(48, 21, 'Pune'),
(49, 21, 'Nagpur'),
(50, 21, 'Nashik'),
(51, 22, 'Imphal'),
(52, 23, 'Shillong'),
(53, 23, 'Tura'),
(54, 25, 'Kohima'),
(55, 25, 'Dimapur'),
(56, 26, 'Bhubaneswar'),
(57, 26, 'Cuttack'),
(58, 26, 'Rourkela'),
(59, 26, 'Sambalpur'),
(60, 28, 'Amritsar'),
(61, 28, 'Ludhiana'),
(62, 28, 'Jalandhar'),
(63, 28, 'Patiala'),
(64, 29, 'Jaipur'),
(65, 29, 'Jodhpur'),
(66, 29, 'Kota'),
(67, 29, 'Udaipur'),
(68, 31, 'Chennai'),
(69, 31, 'Coimbatore'),
(70, 31, 'Madurai'),
(71, 31, 'Salem'),
(72, 32, 'Hyderabad'),
(73, 32, 'Warangal'),
(74, 32, 'Khammam'),
(75, 34, 'Agra'),
(76, 34, 'Lucknow'),
(77, 34, 'Kanpur'),
(78, 34, 'Varanasi'),
(79, 35, 'Dehradun'),
(80, 35, 'Haldwani'),
(81, 35, 'Nainital'),
(83, 36, 'Darjeeling'),
(84, 36, 'Howrah'),
(85, 36, 'Durgapur'),
(86, 9, 'New Delhi'),
(87, 9, 'Central Delhi'),
(88, 9, 'East Delhi'),
(89, 27, 'Puducherry'),
(90, 27, 'Karaikal'),
(91, 19, 'Lakshadweep'),
(131, 21, 'Akola'),
(132, 21, 'Jalgaon'),
(133, 36, 'Kolkata');

-- --------------------------------------------------------

--
-- Table structure for table `india_states`
--

CREATE TABLE `india_states` (
  `id` int(11) NOT NULL,
  `state_code` varchar(5) NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `region` enum('North','South','East','West','Central','Northeast') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `india_states`
--

INSERT INTO `india_states` (`id`, `state_code`, `state_name`, `region`) VALUES
(1, 'AN', 'Andaman and Nicobar Islands', 'South'),
(2, 'AP', 'Andhra Pradesh', 'South'),
(3, 'AR', 'Arunachal Pradesh', 'Northeast'),
(4, 'AS', 'Assam', 'Northeast'),
(5, 'BR', 'Bihar', 'East'),
(6, 'CH', 'Chandigarh', 'North'),
(7, 'CG', 'Chhattisgarh', 'Central'),
(8, 'DD', 'Daman and Diu', 'West'),
(9, 'DL', 'Delhi', 'North'),
(10, 'GA', 'Goa', 'West'),
(11, 'GJ', 'Gujarat', 'West'),
(12, 'HR', 'Haryana', 'North'),
(13, 'HP', 'Himachal Pradesh', 'North'),
(14, 'JK', 'Jammu and Kashmir', 'North'),
(15, 'JH', 'Jharkhand', 'East'),
(16, 'KA', 'Karnataka', 'South'),
(17, 'KL', 'Kerala', 'South'),
(18, 'LA', 'Ladakh', 'North'),
(19, 'LD', 'Lakshadweep', 'South'),
(20, 'MP', 'Madhya Pradesh', 'Central'),
(21, 'MH', 'Maharashtra', 'West'),
(22, 'MN', 'Manipur', 'Northeast'),
(23, 'ML', 'Meghalaya', 'Northeast'),
(24, 'MZ', 'Mizoram', 'Northeast'),
(25, 'NL', 'Nagaland', 'Northeast'),
(26, 'OD', 'Odisha', 'East'),
(27, 'PY', 'Puducherry', 'South'),
(28, 'PB', 'Punjab', 'North'),
(29, 'RJ', 'Rajasthan', 'North'),
(30, 'SK', 'Sikkim', 'Northeast'),
(31, 'TN', 'Tamil Nadu', 'South'),
(32, 'TS', 'Telangana', 'South'),
(33, 'TR', 'Tripura', 'Northeast'),
(34, 'UP', 'Uttar Pradesh', 'North'),
(35, 'UK', 'Uttarakhand', 'North'),
(36, 'WB', 'West Bengal', 'East');

-- --------------------------------------------------------

--
-- Table structure for table `installations`
--

CREATE TABLE `installations` (
  `id` int(11) NOT NULL,
  `installation_no` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `installation_date` date NOT NULL,
  `installation_time` time DEFAULT NULL,
  `engineer_type` enum('internal','external') DEFAULT 'internal',
  `engineer_id` int(11) DEFAULT NULL COMMENT 'Reference to employees table if internal',
  `external_engineer_name` varchar(100) DEFAULT NULL COMMENT 'Name if external engineer',
  `external_engineer_phone` varchar(20) DEFAULT NULL,
  `external_engineer_company` varchar(100) DEFAULT NULL,
  `site_address` text DEFAULT NULL,
  `site_contact_person` varchar(100) DEFAULT NULL,
  `site_contact_phone` varchar(20) DEFAULT NULL,
  `product_details` text DEFAULT NULL COMMENT 'Products/parts installed',
  `installation_notes` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled','on_hold') DEFAULT 'scheduled',
  `completion_date` date DEFAULT NULL,
  `customer_signature` tinyint(1) DEFAULT 0,
  `customer_feedback` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL COMMENT '1-5 star rating',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installations`
--

INSERT INTO `installations` (`id`, `installation_no`, `customer_id`, `invoice_id`, `installation_date`, `installation_time`, `engineer_type`, `engineer_id`, `external_engineer_name`, `external_engineer_phone`, `external_engineer_company`, `site_address`, `site_contact_person`, `site_contact_phone`, `product_details`, `installation_notes`, `status`, `completion_date`, `customer_signature`, `customer_feedback`, `rating`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INS-0001', 4, NULL, '2026-01-21', NULL, 'internal', 1, NULL, NULL, NULL, 'sdsj', '', '', NULL, '', 'scheduled', NULL, 0, NULL, NULL, 1, '2026-01-21 11:56:10', '2026-01-21 11:56:10'),
(2, 'INS-0002', 28, 4, '2026-01-25', NULL, 'internal', 73, NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar Amritsar, Amritsar, Punjab - 143001', '', '', NULL, '', 'scheduled', NULL, 0, NULL, NULL, 1, '2026-01-25 12:11:05', '2026-01-25 12:11:05'),
(3, 'INS-0003', 28, 4, '2026-01-25', '19:41:00', 'internal', 64, NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar Amritsar, Amritsar, Punjab - 143001', '', '', NULL, '', 'scheduled', NULL, 0, NULL, NULL, 1, '2026-01-25 12:12:14', '2026-01-25 12:12:14'),
(4, 'INS-0004', 28, 4, '2026-01-25', '19:41:00', 'internal', 64, NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar Amritsar, Amritsar, Punjab - 143001', '', '', NULL, '', 'scheduled', NULL, 0, NULL, NULL, 1, '2026-01-25 12:14:18', '2026-01-25 12:14:18'),
(5, 'INS-0005', 28, 6, '2026-01-25', '18:48:00', 'internal', 73, NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar Amritsar, Amritsar, Punjab - 143001', '', '', NULL, '', 'completed', '2026-01-25', 0, NULL, NULL, 1, '2026-01-25 12:18:40', '2026-01-25 12:32:30'),
(6, 'INS-0006', 28, 6, '2026-01-25', '18:52:00', 'internal', 73, NULL, NULL, NULL, 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar Amritsar, Amritsar, Punjab - 143001', '', '', NULL, '', 'completed', NULL, 0, '', NULL, 1, '2026-01-25 12:22:54', '2026-01-25 12:23:50');

-- --------------------------------------------------------

--
-- Table structure for table `installation_attachments`
--

CREATE TABLE `installation_attachments` (
  `id` int(11) NOT NULL,
  `installation_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `attachment_type` enum('report','photo','document','signature','other') DEFAULT 'document',
  `description` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installation_attachments`
--

INSERT INTO `installation_attachments` (`id`, `installation_id`, `file_name`, `file_path`, `file_type`, `file_size`, `attachment_type`, `description`, `uploaded_by`, `uploaded_at`) VALUES
(1, 3, 'invitition_new.pdf', 'uploads/installations/INS-0003_1769343134_0.pdf', 'application/pdf', 300453, 'report', NULL, 1, '2026-01-25 12:12:14'),
(2, 4, 'invitition_new.pdf', 'uploads/installations/INS-0004_1769343258_0.pdf', 'application/pdf', 300453, 'report', NULL, 1, '2026-01-25 12:14:18'),
(3, 4, 'AMB2.xls', 'uploads/installations/INS-0004_1769343439.xls', 'application/vnd.ms-excel', 64000, 'document', '', 1, '2026-01-25 12:17:19'),
(4, 6, 'WhatsApp Image 2025-02-05 at 16.10.05_0dc22da8.jpg', 'uploads/installations/INS-0006_1769343816.jpg', 'image/jpeg', 145662, 'photo', 'iidie', 1, '2026-01-25 12:23:36');

-- --------------------------------------------------------

--
-- Table structure for table `installation_products`
--

CREATE TABLE `installation_products` (
  `id` int(11) NOT NULL,
  `installation_id` int(11) NOT NULL,
  `part_no` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `warranty_months` int(11) DEFAULT 12,
  `warranty_end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installation_products`
--

INSERT INTO `installation_products` (`id`, `installation_id`, `part_no`, `product_name`, `serial_number`, `quantity`, `warranty_months`, `warranty_end_date`, `notes`) VALUES
(1, 2, 'YID-044', '12 Channel ECG Machine', NULL, 1, 12, NULL, NULL),
(2, 3, 'YID-063', '5 Function Manual ICU Bed', NULL, 1, 12, NULL, NULL),
(3, 4, 'YID-063', '5 Function Manual ICU Bed', NULL, 1, 12, NULL, NULL),
(4, 5, 'YID-063', '5 Function Manual ICU Bed', NULL, 1, 12, NULL, NULL),
(5, 6, 'YID-044', '12 Channel ECG Machine', NULL, 1, 12, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `part_no`, `qty`) VALUES
(1, '22005001', 4),
(2, '22005231', 12),
(3, '32005012', 46),
(4, '22005698', 1),
(9, '95005006', 17),
(11, '22005458', 14),
(12, '62005022', 9),
(13, '22005012', 5),
(15, '62005019', 10),
(17, '22005519', -1),
(18, '44005002', 6),
(19, '46005005', -1),
(22, '50520020', 1),
(31, 'YID-063', 7),
(32, 'YID-044', 4),
(33, 'YID-0124', -1),
(34, '62005151', 2),
(35, 'YID -043', -1),
(42, 'YID-008', 6),
(47, '44005643', 4),
(52, '11005089', 4),
(53, '11005099', 4),
(58, '17005066', 2),
(65, '11005096', 2),
(73, '83005101', 1),
(74, '62005265', 1),
(75, '95005002', 2),
(76, '17005011', 1),
(80, '46005089', 8),
(84, '46005077', 2),
(85, '62005003', 3),
(87, '32005653', 2),
(88, '82005431', 99),
(89, '83005003', 20),
(90, '82005211', 46),
(92, '83005053', 5),
(97, '32005852', -3),
(98, '32005853', -3),
(100, '44005733', 14),
(146, '62005004', 1);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_master`
--

CREATE TABLE `invoice_master` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `so_no` varchar(50) NOT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `released_at` datetime DEFAULT NULL,
  `status` enum('draft','released') DEFAULT 'draft',
  `eway_bill_no` varchar(50) DEFAULT NULL,
  `eway_bill_attachment` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ship_to_company_name` varchar(255) DEFAULT NULL,
  `ship_to_contact_name` varchar(255) DEFAULT NULL,
  `ship_to_address1` varchar(255) DEFAULT NULL,
  `ship_to_address2` varchar(255) DEFAULT NULL,
  `ship_to_city` varchar(100) DEFAULT NULL,
  `ship_to_pincode` varchar(20) DEFAULT NULL,
  `ship_to_state` varchar(100) DEFAULT NULL,
  `ship_to_gstin` varchar(50) DEFAULT NULL,
  `is_igst` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_master`
--

INSERT INTO `invoice_master` (`id`, `invoice_no`, `so_no`, `customer_id`, `invoice_date`, `released_at`, `status`, `eway_bill_no`, `eway_bill_attachment`, `notes`, `created_at`, `updated_at`, `ship_to_company_name`, `ship_to_contact_name`, `ship_to_address1`, `ship_to_address2`, `ship_to_city`, `ship_to_pincode`, `ship_to_state`, `ship_to_gstin`, `is_igst`) VALUES
(1, 'INV/1/25/26', 'SO-12', '4', '2026-01-17', '2026-01-18 00:24:57', 'released', NULL, NULL, NULL, '2026-01-17 18:51:35', '2026-01-17 18:54:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(2, 'INV/2/25/26', 'SO-13', '5', '2026-01-18', '2026-01-18 08:49:11', 'released', NULL, NULL, NULL, '2026-01-18 03:18:52', '2026-01-18 03:19:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(3, 'INV/3/25/26', 'SO-15', '3', '2026-01-21', '2026-01-21 10:33:09', 'released', NULL, NULL, NULL, '2026-01-21 05:02:49', '2026-01-21 05:03:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(4, 'INV/4/25/26', 'SO-16', '28', '2026-01-23', '2026-01-23 07:03:02', 'released', NULL, NULL, NULL, '2026-01-23 01:32:42', '2026-01-23 01:33:02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(5, 'INV/5/25/26', 'SO-18', '30', '2026-01-23', '2026-01-23 16:34:03', 'released', NULL, NULL, NULL, '2026-01-23 05:03:20', '2026-01-23 11:04:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(6, 'INV/6/25/26', 'SO-17', '28', '2026-01-23', '2026-01-23 16:32:21', 'released', NULL, NULL, NULL, '2026-01-23 11:00:38', '2026-01-23 11:02:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(7, 'INV/7/25/26', 'SO-26', '46', '2026-01-24', '2026-01-24 20:30:09', 'released', NULL, NULL, NULL, '2026-01-24 14:58:50', '2026-01-24 15:00:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(8, 'INV/8/25/26', 'SO-27', '28', '2026-01-24', '2026-01-24 22:11:25', 'released', NULL, NULL, NULL, '2026-01-24 16:40:42', '2026-01-24 16:41:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(9, 'INV/9/25/26', 'SO-29', '55', '2026-01-25', '2026-01-25 08:01:09', 'released', '1234567891123456', 'uploads/invoices/EWAY_INV_9_25_26_1769308232.pdf', NULL, '2026-01-25 02:12:15', '2026-01-25 02:31:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(10, 'INV/10/25/26', 'SO-30', '28', '2026-01-25', '2026-01-25 12:46:00', 'released', NULL, NULL, NULL, '2026-01-25 03:24:03', '2026-01-25 07:16:00', 'Akal sirjana services', 'Akal', 'Shop no 3 ,gali no 7 kot baba deep singh, Amritsar , Amritsar', 'Amritsar', 'Amritsar', '143001', 'Punjab', '', 0),
(11, 'INV/11/25/26', 'SO-31', '64', '2026-01-25', '2026-01-25 10:51:46', 'released', '1234567891123456', 'uploads/invoices/EWAY_INV_11_25_26_1769318462.pdf', NULL, '2026-01-25 05:19:48', '2026-01-25 05:21:46', 'ABC Hospital', 'Alpesh Doshi', 'Tembhurni Road ,Bagal Nagar, Kuduvadi, , Solapur', 'Solapur', 'Solapur', '413208', 'Maharashtra', '', 0),
(12, 'INV/12/25/26', 'SO-32', '66', '2026-01-25', '2026-01-25 14:45:37', 'released', NULL, NULL, NULL, '2026-01-25 09:13:16', '2026-01-25 09:15:37', 'NEW ANURA SURGICAL', 'Anura', 'SHOP NO.2,S NO.247,PLOT NO.5, MAMASAHEB BORKAR MARG, GANESH NAGAR,CHINCHWAD, Pune, Pune', 'Pune', 'Pune', '411033', 'Maharashtra', '27AAUPB5003G1ZX', 0),
(13, 'INV/13/25/26', 'SO-33', '52', '2026-01-25', '2026-01-25 15:17:05', 'released', NULL, NULL, NULL, '2026-01-25 09:29:03', '2026-01-25 09:47:05', 'LOKENATH MEDICARE', 'Arup Chaterjee', 'Shekhala ,Hooghly, Hoogly, Hooghly', 'Hooghly', 'Hooghly', '712706', 'West Bengal', '', 0),
(14, 'INV/14/25/26', 'SO-35', '28', '2026-01-29', NULL, 'draft', NULL, NULL, NULL, '2026-01-29 08:19:55', '2026-01-29 08:19:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(15, 'INV/15/25/26', 'SO-41', '12', '2026-01-29', '2026-01-29 21:21:39', 'released', NULL, NULL, NULL, '2026-01-29 15:50:32', '2026-01-29 15:51:39', 'MEDIEQUIP ENTERPRISE', 'Animesh Ghosh', 'B/13A/H/2, CHOUL PATTY ROAD, BELEGHATA, Kolkata, Kolkata', 'Kolkata', 'Kolkata', '700010', 'West Bengal', '19AHTPG8892J1ZA', 0),
(16, 'INV/16/25/26', 'SO-42', '11', '2026-01-30', '2026-01-30 08:05:28', 'released', NULL, NULL, NULL, '2026-01-30 02:35:22', '2026-01-30 02:35:28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance`
--

CREATE TABLE `leave_balance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `opening_balance` decimal(5,1) DEFAULT 0.0,
  `accrued` decimal(5,1) DEFAULT 0.0,
  `taken` decimal(5,1) DEFAULT 0.0,
  `balance` decimal(5,1) DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `allocated` decimal(5,2) DEFAULT 0.00,
  `used` decimal(5,2) DEFAULT 0.00,
  `balance` decimal(5,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`id`, `employee_id`, `leave_type_id`, `year`, `allocated`, `used`, `balance`, `updated_at`) VALUES
(120, 62, 8, 2026, 0.00, 1.00, -1.00, '2026-01-29 18:54:01'),
(121, 63, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(122, 64, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(123, 65, 8, 2026, 0.00, 4.00, -4.00, '2026-01-31 04:40:04'),
(124, 66, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(125, 67, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(126, 68, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(127, 69, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(128, 70, 8, 2026, 0.00, 1.00, -1.00, '2026-01-31 04:38:54'),
(129, 71, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(130, 72, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(131, 73, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(132, 74, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(133, 75, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(134, 76, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(135, 77, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25'),
(136, 78, 8, 2026, 0.00, 0.00, 0.00, '2026-01-29 18:46:25');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `leave_request_no` varchar(20) NOT NULL DEFAULT '',
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL DEFAULT '1970-01-01',
  `end_date` date NOT NULL DEFAULT '1970-01-01',
  `total_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_half_day` tinyint(1) DEFAULT 0,
  `half_day_type` enum('First Half','Second Half') DEFAULT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `days` decimal(4,1) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `approval_remarks` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `leave_request_no`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `is_half_day`, `half_day_type`, `from_date`, `to_date`, `days`, `reason`, `status`, `approved_by`, `approval_date`, `approval_remarks`, `approved_at`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 'LR-2026-0001', 62, 8, '2026-01-30', '2026-01-30', 1.00, 0, NULL, '0000-00-00', '0000-00-00', 0.0, 'ss', 'Approved', NULL, NULL, NULL, NULL, NULL, '2026-01-29 18:54:01', '2026-01-29 18:54:01'),
(2, 'LR-2026-0002', 70, 8, '2026-02-07', '2026-02-07', 1.00, 0, NULL, '0000-00-00', '0000-00-00', 0.0, 'PERSONAL', 'Approved', NULL, NULL, NULL, NULL, NULL, '2026-01-31 04:38:54', '2026-01-31 04:38:54'),
(3, 'LR-2026-0003', 65, 8, '2026-02-05', '2026-02-09', 4.00, 0, NULL, '0000-00-00', '0000-00-00', 0.0, 'PERSONAL REASON', 'Approved', NULL, NULL, NULL, NULL, NULL, '2026-01-31 04:40:04', '2026-01-31 04:40:04');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `leave_type_name` varchar(100) NOT NULL DEFAULT '',
  `leave_code` varchar(20) NOT NULL DEFAULT '',
  `max_days_per_year` int(11) DEFAULT 0,
  `name` varchar(50) NOT NULL,
  `days_per_year` int(11) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 1,
  `requires_approval` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `leave_type_name`, `leave_code`, `max_days_per_year`, `name`, `days_per_year`, `is_paid`, `requires_approval`, `is_active`, `description`, `created_at`) VALUES
(8, 'Work From Home', 'CL', 0, '', 0, 1, 0, 1, 'Work from home - no approval needed', '2026-01-29 18:46:25');

-- --------------------------------------------------------

--
-- Table structure for table `location_marketing_summary`
--

CREATE TABLE `location_marketing_summary` (
  `id` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) DEFAULT NULL,
  `campaigns_count` int(11) DEFAULT 0,
  `total_budget` decimal(12,2) DEFAULT 0.00,
  `total_spent` decimal(12,2) DEFAULT 0.00,
  `total_attendees` int(11) DEFAULT 0,
  `leads_generated` int(11) DEFAULT 0,
  `orders_generated` int(11) DEFAULT 0,
  `revenue_generated` decimal(12,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_campaigns`
--

CREATE TABLE `marketing_campaigns` (
  `id` int(11) NOT NULL,
  `campaign_code` varchar(30) NOT NULL,
  `campaign_name` varchar(255) NOT NULL,
  `campaign_type_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `venue_address` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `target_audience` varchar(255) DEFAULT NULL,
  `expected_attendees` int(11) DEFAULT 0,
  `budget` decimal(12,2) DEFAULT 0.00,
  `actual_cost` decimal(12,2) DEFAULT 0.00,
  `catalog_ids` text DEFAULT NULL,
  `campaign_manager` varchar(150) DEFAULT NULL,
  `team_members` text DEFAULT NULL,
  `status` enum('Planned','Ongoing','Completed','Cancelled','Postponed') DEFAULT 'Planned',
  `actual_attendees` int(11) DEFAULT 0,
  `leads_generated` int(11) DEFAULT 0,
  `enquiries_received` int(11) DEFAULT 0,
  `orders_received` int(11) DEFAULT 0,
  `revenue_generated` decimal(12,2) DEFAULT 0.00,
  `outcome_summary` text DEFAULT NULL,
  `success_rating` enum('Excellent','Good','Average','Poor','Not Rated') DEFAULT 'Not Rated',
  `lessons_learned` text DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_notes` text DEFAULT NULL,
  `photos_path` varchar(255) DEFAULT NULL,
  `report_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_catalogs`
--

CREATE TABLE `marketing_catalogs` (
  `id` int(11) NOT NULL,
  `catalog_code` varchar(30) NOT NULL,
  `catalog_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `model_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `target_audience` varchar(255) DEFAULT NULL,
  `price_range` varchar(100) DEFAULT NULL,
  `brochure_path` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive','Discontinued') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marketing_catalogs`
--

INSERT INTO `marketing_catalogs` (`id`, `catalog_code`, `catalog_name`, `category`, `model_no`, `description`, `specifications`, `features`, `target_audience`, `price_range`, `brochure_path`, `image_path`, `status`, `created_at`, `updated_at`) VALUES
(1, 'CAT-0001', 'HVS Universal', 'Medical Equipment', 'HVS3500XL', 'HVS ventilator with universal mode', 'HVS3500', 'Compressor Based', 'Hospitals', '300000', 'uploads/catalogs/CAT-0001_brochure_1768748434.pdf', 'uploads/catalogs/CAT-0001_1768748434.jpg', 'Active', '2026-01-18 15:00:34', '2026-01-18 15:00:34'),
(2, 'CAT-0002', 'dvdf', 'Medical Equipment', 'dfff', 'sdef', 'ddv', 'sscc', 'Hospitals', '350000', 'uploads/catalogs/CAT-0002_brochure_1768972104.pdf', 'uploads/catalogs/CAT-0002_1768972104.jpg', 'Active', '2026-01-21 05:08:24', '2026-01-21 05:08:24'),
(3, 'CAT-0003', 'HVS 3500 XL', 'Medical Equipment', 'HVS 3500 XL', 'HVS 3500 XL', '1) Compressor Based\r\n2) 10.1\" screen\r\n3) Universal Model\r\n4) Internal Sensors\r\n5) No standby Time\r\n6) 3 Hours Battery Backup\r\n7) Humidifier', '1) Compressor Based\r\n2) 10.1\" screen\r\n3) Universal Model\r\n4) Internal Sensors\r\n5) No standby Time\r\n6) 3 Hours Battery Backup\r\n7) Humidifier', NULL, NULL, 'uploads/catalogs/CAT-0003_brochure_1769673727.pdf', 'uploads/catalogs/CAT-0003_1769673727.jpeg', 'Active', '2026-01-29 08:02:07', '2026-01-29 08:02:07');

-- --------------------------------------------------------

--
-- Table structure for table `milestone_documents`
--

CREATE TABLE `milestone_documents` (
  `id` int(11) NOT NULL,
  `milestone_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `module_group` varchar(50) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `module_key`, `module_name`, `module_group`, `display_order`, `is_active`) VALUES
(1, 'crm', 'CRM / Leads', 'Sales & CRM', 1, 1),
(2, 'customers', 'Customers', 'Sales & CRM', 2, 1),
(3, 'quotes', 'Quotations', 'Sales & CRM', 3, 1),
(4, 'proforma', 'Proforma Invoice', 'Sales & CRM', 4, 1),
(5, 'customer_po', 'Customer PO', 'Sales & CRM', 5, 1),
(6, 'sales_orders', 'Sales Orders', 'Sales & CRM', 6, 1),
(7, 'invoices', 'Invoices', 'Sales & CRM', 7, 1),
(8, 'installations', 'Installations', 'Sales & CRM', 8, 1),
(9, 'suppliers', 'Suppliers', 'Purchase & SCM', 10, 1),
(10, 'purchase', 'Purchase Orders', 'Purchase & SCM', 11, 1),
(11, 'procurement', 'Procurement Planning', 'Purchase & SCM', 12, 1),
(12, 'part_master', 'Part Master', 'Inventory', 20, 1),
(13, 'stock_entry', 'Stock Entries', 'Inventory', 21, 1),
(14, 'depletion', 'Stock Adjustment', 'Inventory', 22, 1),
(15, 'inventory', 'Current Stock', 'Inventory', 23, 1),
(16, 'reports', 'Reports', 'Inventory', 24, 1),
(17, 'bom', 'Bill of Materials', 'Operations', 30, 1),
(18, 'work_orders', 'Work Orders', 'Operations', 31, 1),
(19, 'hr_employees', 'Employees', 'HR', 40, 1),
(20, 'hr_attendance', 'Attendance', 'HR', 41, 1),
(21, 'hr_payroll', 'Payroll', 'HR', 42, 1),
(22, 'marketing_catalogs', 'Catalogs', 'Marketing', 50, 1),
(23, 'marketing_campaigns', 'Campaigns', 'Marketing', 51, 1),
(24, 'marketing_whatsapp', 'WhatsApp', 'Marketing', 52, 1),
(25, 'marketing_analytics', 'Marketing Analytics', 'Marketing', 53, 1),
(26, 'service_complaints', 'Complaints', 'Service', 60, 1),
(27, 'service_technicians', 'Technicians', 'Service', 61, 1),
(28, 'service_analytics', 'Service Analytics', 'Service', 62, 1),
(29, 'tasks', 'Tasks', 'Tasks & Projects', 70, 1),
(30, 'project_management', 'Projects', 'Tasks & Projects', 71, 1),
(31, 'admin_settings', 'Company Settings', 'Admin', 80, 1),
(32, 'admin_users', 'User Management', 'Admin', 81, 1),
(33, 'admin_locations', 'Location Management', 'Admin', 82, 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `open_sales_orders_for_planning`
-- (See below for the actual view)
--
CREATE TABLE `open_sales_orders_for_planning` (
`so_no` varchar(20)
,`part_no` varchar(50)
,`total_demand_qty` decimal(32,0)
,`num_orders` bigint(21)
,`earliest_so_date` date
,`latest_so_date` date
,`current_stock` int(11)
,`part_name` varchar(100)
,`so_list` mediumtext
);

-- --------------------------------------------------------

--
-- Table structure for table `part_id_series`
--

CREATE TABLE `part_id_series` (
  `id` int(11) NOT NULL,
  `part_id` varchar(50) NOT NULL,
  `series_prefix` varchar(50) NOT NULL,
  `current_number` int(11) DEFAULT 0,
  `number_padding` int(11) DEFAULT 4,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_id_series`
--

INSERT INTO `part_id_series` (`id`, `part_id`, `series_prefix`, `current_number`, `number_padding`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'RAW', 'RAW-', 0, 4, 'Raw Materials - Components and materials purchased for manufacturing', 1, '2026-02-01 04:24:39', '2026-02-01 04:24:39'),
(2, 'FG', 'FG-', 0, 4, 'Finished Goods - Final assembled products', 1, '2026-02-01 04:24:39', '2026-02-01 04:24:39'),
(3, 'WIP', 'WIP-', 0, 4, 'Work in Progress - Semi-finished items', 1, '2026-02-01 04:24:39', '2026-02-01 04:24:39'),
(4, 'SUB', 'SUB-', 0, 4, 'Sub-assemblies - Intermediate assembled components', 1, '2026-02-01 04:24:39', '2026-02-01 04:24:39'),
(5, 'PKG', 'PKG-', 0, 4, 'Packaging Materials - Boxes, labels, and packaging items', 1, '2026-02-01 04:24:39', '2026-02-01 04:24:39'),
(6, 'SPA', 'SPA-', 0, 4, 'Spare Parts - Service and replacement parts', 1, '2026-02-01 04:24:39', '2026-02-01 04:24:39'),
(7, 'CON', 'CON-', 1, 4, 'Consumables - Items consumed during manufacturing', 1, '2026-02-01 04:24:39', '2026-02-01 04:48:44'),
(8, 'TOL', 'TOL-', 0, 4, 'Tools - Manufacturing tools and fixtures', 1, '2026-02-01 04:24:39', '2026-02-01 04:24:39'),
(9, '11', '11005', 7, 3, 'Packaging Material', 1, '2026-02-01 04:25:24', '2026-02-01 04:57:19');

-- --------------------------------------------------------

--
-- Table structure for table `part_master`
--

CREATE TABLE `part_master` (
  `id` int(11) NOT NULL,
  `part_name` varchar(100) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `part_id` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uom` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `hsn_code` varchar(20) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `gst` varchar(30) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_master`
--

INSERT INTO `part_master` (`id`, `part_name`, `part_no`, `part_id`, `description`, `uom`, `category`, `hsn_code`, `rate`, `status`, `gst`, `attachment_path`) VALUES
(1, 'Quick Connector', '22005001', '22', 'SS Connector', 'Nos', 'Machining', '', 250.00, 'inactive', '18', 'uploads/parts/22005001_1768407877.pdf'),
(2, 'Assembly Connector', '44005002', '44', 'assembly', 'nos', 'assembly', NULL, 350.00, 'inactive', '18', NULL),
(4, 'Cone', '22005231', '22', 'SS', 'Nos', 'Machining', NULL, 240.00, 'inactive', '18', NULL),
(5, 'Assembly Cone', '44005014', '44', 'Assembly', 'Nos', 'Fabriction', NULL, 350.00, 'inactive', '18', NULL),
(6, 'Cover Base stand', '32005012', '32', 'Cover', 'Nos', 'Machining', NULL, 25.00, 'inactive', '18', NULL),
(7, 'Assembly Compressor', '44005643', '44', 'compressor', 'nos', 'Assembly', NULL, 457.00, 'inactive', '18', NULL),
(8, 'Handle', '22005698', '22', 'MS plate', 'Nos', 'Machining', NULL, 200.00, 'inactive', '18', NULL),
(9, '10.1 inch Screen', '95005006', '95', 'LCD touch Display', 'Nos', 'Brought Out', NULL, 8500.00, 'inactive', '18', NULL),
(10, '6xM5 Male Push fitting', '62005019', '62', 'Pnumatic push fitting', 'Nos', 'Brought Out', NULL, 50.00, 'inactive', '18', NULL),
(11, 'Aeroflex Valve DC 2/2', '62005022', '62', 'Air Valve', 'Nos', 'Brought Out', NULL, 350.00, 'inactive', '18', NULL),
(12, 'FCV Knob', '22005458', '22', 'SS knob', 'Nos', 'Brought Out', NULL, 150.00, 'inactive', '18', NULL),
(13, 'Flow Sensor Cone', '22005012', '22', 'SS cone', 'Nos', 'Machining', NULL, 75.00, 'inactive', '18', NULL),
(14, 'Inlet Hex Fitting', '22005519', '22', 'SS hex fittingn', 'Nos', 'Machining', NULL, 120.00, 'inactive', '18', NULL),
(15, 'Assembly Compressor new', '42005001', '42', 'Air compressor', 'Nos', 'Assembly', NULL, 123.00, 'inactive', '18', NULL),
(16, 'Assembly New', '46005005', '46', 'asem', 'Nos', 'Assembly', NULL, 12.00, 'inactive', '3', NULL),
(17, 'Test', '50520020', '152', 'Test', '235', 'ytest', '18', 125.00, 'inactive', '345232516131342151', 'uploads/parts/50520020_1768068535.pdf'),
(18, 'sdj', '46005555', '46', 'Assembly', 'Nos', 'Assembly', '12', 0.00, 'inactive', '12', NULL),
(19, 'big Bush', '22001111', '22', 'bush', 'Nos', 'Machining', '2311', 12.00, 'inactive', '18', NULL),
(20, 'FLAP DICS FLAP DISC 100MMX16MM', '18005001', '18', 'Consumable', 'NOS', 'Brought Out', '68051010', 25.00, 'active', '18', NULL),
(21, 'Tig Weld .ceramic Nozzel', '18005002', '18', 'Consumable', 'KG', 'Brought Out', '68042220', 10.00, 'active', '12', NULL),
(22, 'Wolfram TIG Rod 2.4 mm', '18005003', '18', 'Consumable', 'MTR', 'Brought Out', '68042220', 10.00, 'active', '18', NULL),
(23, 'Hand gloves', '18005004', '18', 'Consumable', 'NOS', 'Brought Out', '81019990', 125.00, 'active', '18', NULL),
(24, 'Insulation Tape', '18005005', '18', 'Consumable', 'NOS', 'Brought Out', '81019990', 125.00, 'active', '18', NULL),
(25, 'Cable Tile 150X4.6 MM', '18005006', '18', 'Consumable', 'NOS', 'Brought Out', '81019990', 0.00, 'active', '18', NULL),
(26, 'Cotton Apron Full Sleeve', '18005007', '18', 'Consumable', 'NOS', 'Brought Out', '81019990', 0.00, 'active', '18', NULL),
(27, 'Cable Tie 250mm', '18005008', '18', 'Consumable', 'NOS', 'Brought Out', '81019990', 0.00, 'active', '18', NULL),
(28, 'FILLER WIRE 1.6 ER 705', '18005009', '18', 'Consumable', 'NOS', 'Brought Out', '81019990', 0.00, 'active', '18', NULL),
(29, 'EP FOAM 25MM 54X78 INCH', '11005089', '11', 'Packaging', 'NOS', 'Brought Out', '81019990', 24.00, 'active', '18', NULL),
(30, 'Delivery system Nasal Mask Inflow Generator', '12002225', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 0.00, 'active', '18', NULL),
(31, 'HME FILTER', '12005002', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 103.00, 'active', '18', NULL),
(32, 'Medical Arm', '12005003', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 85.00, 'active', '18', NULL),
(33, 'Test Lungs Adult', '12005005', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 65.00, 'active', '18', NULL),
(34, 'Hose Pipe 6mm Duplon oxygen pipe white', '12005008', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 190.00, 'active', '18', NULL),
(35, '1 Inch Prime printed tape', '12005017', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 68.00, 'active', '18', NULL),
(36, 'Masking Tape - 1\"', '12005018', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 0.00, 'active', '18', NULL),
(37, 'Air Circuit Without Trap Neonatal / Pediatric circuit plain without water trap', '12005049', '12', 'Medical Kit', 'NOS', 'Brought Out', '81019990', 60.00, 'active', '18', NULL),
(38, 'BACTERIAL FILTER', '12005051', '12', 'Medical Kit', 'NOS', 'Brought Out', '9019', 45.00, 'active', '18', NULL),
(39, 'Pediatric Test Lungs 50 ml', '12005086', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192090', 950.00, 'active', '18', NULL),
(40, 'Nasal Prong Medium', '12005201', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192091', 200.00, 'active', '18', NULL),
(41, 'Air Circuit Adult Breathing', '12005208', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192092', 200.00, 'active', '18', NULL),
(42, 'swivel mount', '12005210', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 190.00, 'active', '18', NULL),
(43, 'ANTOMICAL MASK', '12005211', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 95.00, 'active', '18', NULL),
(44, 'RESCUICITATOR KIT', '12005212', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 110.00, 'active', '18', NULL),
(45, 'OXYGEN CONVERSION PIPE', '12005213', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 700.00, 'active', '18', NULL),
(46, 'NITROUS CONVERSION PIPE', '12005214', '12', 'Medical Kit', 'NOS', 'Brought Out', '90330000', 450.00, 'active', '18', NULL),
(47, 'nasal mask medium', '12005215', '12', 'Medical Kit', 'NOS', 'Brought Out', '90330000', 450.00, 'active', '18', NULL),
(48, 'SINGLE HEATED WIRE', '12005220', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192010', 200.00, 'active', '18', NULL),
(49, 'Bain Circuit', '12005221', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192010', 600.00, 'active', '18', NULL),
(50, 'Fumist o kit pediatric / nebulizer kit with Tee', '12005222', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 475.00, 'active', '18', NULL),
(51, 'Bubblecpap kit', '12005223', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192010', 150.00, 'active', '18', NULL),
(52, 'AIR PIPE BLACK', '12005224', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192090', 1800.00, 'active', '18', NULL),
(53, 'Cpap bonnet medium', '12005225', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192091', 80.00, 'active', '18', NULL),
(54, 'Adult NIV Mask', '12005227', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192090', 250.00, 'active', '18', NULL),
(55, 'HOSE 6MM PIPE NITROUS BLUE PIPE', '12005228', '12', 'Medical Kit', 'NOS', 'Brought Out', '90192090', 750.00, 'active', '18', NULL),
(56, 'ADULT HIGH FLOW NASAL CANNULA', '12005229', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 500.00, 'active', '18', NULL),
(57, 'ADULT HFNC CIRCUIT', '12005230', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 70.00, 'active', '18', NULL),
(58, 'NEONATLE HME FILTER', '12005231', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 1550.00, 'active', '18', NULL),
(59, 'silicon ambu bag neonatal', '12005232', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 2500.00, 'active', '18', NULL),
(60, 'Silicon ambu bag Child', '12005233', '12', 'Medical Kit', 'NOS', 'Brought Out', '90189041', 65.00, 'active', '18', NULL),
(82, 'Weld nut M6_72005001', '72005001', '72', 'Fastener', 'NOS', 'Brought Out', '84879000', 0.00, 'active', '18', NULL),
(83, 'FLOW SENSOR WINSEN', '93005001', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 5970.46, 'active', '18', NULL),
(84, 'Oxygen Sensor OOM 202', '93005002', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 2400.00, 'active', '18', NULL),
(85, 'Pressure sensor', '93005003', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 600.33, 'active', '18', NULL),
(86, 'LCD 20X4 BLUE', '93005009', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 115.00, 'active', '18', NULL),
(87, 'D Connector Female 9 PIN', '93005010', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 350.00, 'active', '18', NULL),
(88, 'Keypad Metal Domes 12mm 4 Leg', '93005015', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 109.00, 'active', '18', NULL),
(89, 'Silicon Labs CP210x USB To RS232 9Pin D-sub DB9 Serial Converter', '93005018', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 0.00, 'active', '18', NULL),
(90, 'CB 22VTS2 - Heater Wire MR850 4pin 80 Deg Female', '93005019', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 2.00, 'active', '18', NULL),
(91, 'CB 22VTSP4 - Temperature Probe 6Pin 40 Redel', '93005020', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 871.08, 'active', '18', NULL),
(92, 'Etco2 sensor', '93005022', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 1200.00, 'active', '18', NULL),
(93, '400WDC DC High Power constant voltage constant', '93005023', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 1500.00, 'active', '18', NULL),
(94, 'Etco2 module', '93005024', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 1980.00, 'active', '18', NULL),
(95, 'AIR PROBE BABY WARMER', '93005025', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 0.00, 'active', '18', NULL),
(96, 'SKIN PROBE BABY WARMER', '93005026', '93', 'Electronic_F.G', 'NOS', 'Brought Out', '', 25000.00, 'active', '18', NULL),
(97, 'DP to HDMI cable', '95005001', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 0.00, 'active', '18', NULL),
(98, 'CPU', '95005002', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 11000.00, 'active', '18', NULL),
(99, 'HDMI Cable Honeywell 1 Meter', '95005004', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 560.00, 'active', '18', NULL),
(100, '15.6\" Screen', '95005005', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 1250.00, 'active', '18', NULL),
(101, '12.1\" SCREEN', '95005007', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 325.00, 'active', '18', NULL),
(102, 'Cable for Arduino UNO/MEGA (USB A to B)-3mtr', '95005008', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 10500.00, 'active', '18', NULL),
(103, 'Micro 6 Thin vent CPU', '95005010', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 10400.00, 'active', '18', NULL),
(104, 'Waveshare 7inch 1024*600 HDMI, IPS Capacitive Touch Screen LCD (H) With Various', '95005011', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 17750.00, 'active', '18', NULL),
(105, '18.5 Screen', '95005012', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 194.70, 'active', '18', NULL),
(106, 'HDMI 1.4 ADAPTOR CONVERTOR', '95005013', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 4000.00, 'active', '18', NULL),
(107, 'Waveshare 8inch Capacitive Touch Display, 8inch', '95005015', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 3370.76, 'active', '18', NULL),
(108, 'PCAP TOUCH DISPLAY', '95005016', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 22000.00, 'active', '18', NULL),
(109, 'VGA CABLE HP1.5 MTR', '95005017', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 0.00, 'active', '18', NULL),
(110, 'ELECROW ESP32 DISPLAY 7 INCH 800X480', '95005018', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 6194.71, 'active', '18', NULL),
(111, 'CPU Fan', '95005019', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 0.00, 'active', '18', NULL),
(112, 'G1 Tiny PC', '95005020', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 575.00, 'active', '18', NULL),
(113, 'USB V type Cable', '95005021', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 3370.76, 'active', '18', NULL),
(114, 'A/D Board for VGA+HDMI', '95005022', '95', 'IT  F.G', 'NOS', 'Brought Out', '', 0.00, 'active', '18', NULL),
(115, 'HUMIDIFIER BOX', '11005096', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 0.00, 'active', '18', NULL),
(116, 'HVS CABINET BOX 21x21x34', '11005099', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 0.00, 'active', '18', NULL),
(117, 'COMPRESSOR BOX 23x23x18', '11005100', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 300.00, 'active', '18', NULL),
(118, 'XVS COLUMN BOX', '11005118', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 45.00, 'active', '18', NULL),
(119, 'VENTIGO BOX', '11005119', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 424.00, 'active', '18', NULL),
(120, 'WOODEN BOX AHEN 4000', '11005120', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 127.00, 'active', '18', NULL),
(121, 'BUBBLE CPAP BOX', '11005122', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 100.00, 'active', '18', NULL),
(122, 'HVS 2500 BOX', '11005123', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 500.00, 'active', '18', NULL),
(123, 'Wrapping roll', '11005124', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 4000.00, 'active', '18', NULL),
(124, 'Bubble roll', '11005125', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 68.00, 'active', '18', NULL),
(125, 'P.U. foam 3x6', '11005126', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 350.00, 'active', '18', NULL),
(126, 'Murphy/Arya Wooden Box', '11005130', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 135.00, 'active', '18', NULL),
(127, 'AHEN 4500/5000 WOODEN BOX', '11005131', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 600.00, 'active', '18', NULL),
(128, 'VENTIGO /MOPPET BOX CAP', '11005136', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 280.00, 'active', '18', NULL),
(129, 'COMPRESSOR BOX CAP', '11005137', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 150.00, 'active', '18', NULL),
(130, 'HVS BOX CAP 555 X 555 X X 200', '11005138', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 250.00, 'active', '18', NULL),
(131, 'XVS BOX CAP _', '11005139', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 3360.00, 'active', '18', NULL),
(132, 'BUBBLECPAP CAP', '11005140', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 5947.00, 'active', '18', NULL),
(133, 'MOPPET BOX', '11005143', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 32.00, 'active', '18', NULL),
(134, 'MOPPET BOX CAP', '11005144', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 290.00, 'active', '18', NULL),
(135, 'HUMIDIFIER BOX CAP', '11005145', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 750.00, 'active', '18', NULL),
(136, 'DNA BOX', '11005146', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 68.00, 'active', '18', NULL),
(137, 'DNA BOX CAP', '11005147', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 110.00, 'active', '18', NULL),
(138, 'HVS 2500 CAP', '11005148', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 104.00, 'active', '18', NULL),
(139, 'XVS WOODEN BOX', '11005149', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 104.00, 'active', '18', NULL),
(140, 'NEBULA 2.1 WOODEN BOX', '11005150', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 68.00, 'active', '18', NULL),
(141, 'AHEN 5000 NEW WOODEN BOX', '11005151', '11', 'Packaging', 'NOS', 'Manufacturing', '81019990', 0.00, 'active', '18', NULL),
(142, 'DL 2605 HUMIDIFIER STICKER', '17005001', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(143, 'NVS 9000 XL STICKER', '17005002', '17', 'Stickers', 'NOS', 'Printing', '48219090', 15.00, 'active', '18', NULL),
(144, 'HVS 4500 XL STICKER', '17005003', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(145, 'XVS 9500 XL STICKER (YASHKA)', '17005004', '17', 'Stickers', 'NOS', 'Printing', '48219090', 60.00, 'active', '18', NULL),
(146, 'HVS 35 10\" STICKER', '17005006', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(147, 'MOPPET 6.5 STICKER (YASHKA)', '17005007', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(148, 'VENTIGO STICKER', '17005008', '17', 'Stickers', 'NOS', 'Printing', '48219090', 250.00, 'active', '18', NULL),
(149, 'BUBBLECPAP YASHKA STICKER', '17005009', '17', 'Stickers', 'NOS', 'Printing', '48219090', 250.00, 'active', '18', NULL),
(150, 'AHEN 5000 STICKER', '17005011', '17', 'Stickers', 'NOS', 'Printing', '48219090', 375.00, 'active', '18', NULL),
(151, 'AHEN 4000 YASHKA STICKER', '17005012', '17', 'Stickers', 'NOS', 'Printing', '48219090', 250.00, 'active', '18', NULL),
(152, 'AHEN 4500 STICKER (YASHKA)', '17005013', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(153, 'BABY WARMER STICKER', '17005014', '17', 'Stickers', 'NOS', 'Printing', '48219090', 30.00, 'active', '18', NULL),
(154, 'HUMIDIFIER CHAMBER OUTPUT STICKER', '17005015', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(155, 'NEBULIZER PORT STICKER', '17005016', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(156, 'AIR PRESSURE STICKER', '17005017', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(157, 'OXYGEN PRESSURE STICKE R', '17005018', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(158, 'DNA 25 STICKER', '17005019', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(159, 'AIR INLET STICKER', '17005020', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(160, 'VENT FCV STICKER', '17005021', '17', 'Stickers', 'NOS', 'Printing', '48219090', 80.00, 'active', '18', NULL),
(161, 'OXYGEN INLET STICKER', '17005022', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(162, 'PATIENT INLET STICKER', '17005023', '17', 'Stickers', 'NOS', 'Printing', '48219090', 375.00, 'active', '18', NULL),
(163, 'PATIENT OUTLET STICKER', '17005024', '17', 'Stickers', 'NOS', 'Printing', '48219090', 75.00, 'active', '18', NULL),
(164, 'AIR OUTLET', '17005025', '17', 'Stickers', 'NOS', 'Printing', '48219090', 83.33, 'active', '18', NULL),
(165, 'HUMIDIFIER BRACKET STICKER', '17005026', '17', 'Stickers', 'NOS', 'Printing', '48219090', 1250.00, 'active', '18', NULL),
(166, 'B-CPAP BRACKET STICKER', '17005027', '17', 'Stickers', 'NOS', 'Printing', '48219090', 4500.00, 'active', '18', NULL),
(167, 'AIR COMPRESSOR GUAGE STICKER', '17005028', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(168, 'ETCO2 SENSOR STICKER', '17005029', '17', 'Stickers', 'NOS', 'Printing', '48219090', 350.00, 'active', '18', NULL),
(169, 'NITROUS LINE STICKER', '17005030', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(170, 'SCAVENGING GAS STICKER', '17005031', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(171, 'OXYGEN YOKE STICKER', '17005032', '17', 'Stickers', 'NOS', 'Printing', '48219090', 1200.00, 'active', '18', NULL),
(172, 'TURN THE KNOB FOR STICKER', '17005033', '17', 'Stickers', 'NOS', 'Printing', '48219090', 240.00, 'active', '18', NULL),
(173, 'AIR LINE STICKER', '17005034', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(174, 'TO CIRCLE ABSORBER STICKER', '17005035', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(175, 'AIR COMPRESSOR GAS OUTLET STICKER', '17005036', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(176, 'RESERVOIR BAG STICKER', '17005041', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(177, 'OXYGEN LINE STICKER', '17005043', '17', 'Stickers', 'NOS', 'Printing', '48219090', 25.00, 'active', '18', NULL),
(178, 'AIR STICKER', '17005044', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(179, 'NITROUS YOKE STICKER', '17005045', '17', 'Stickers', 'NOS', 'Printing', '48219090', 15.00, 'active', '18', NULL),
(180, 'BELLOW STICKER', '17005046', '17', 'Stickers', 'NOS', 'Printing', '48219090', 15.00, 'active', '18', NULL),
(181, 'FRESH GAS', '17005047', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(182, 'BLENDER STICKER', '17005049', '17', 'Stickers', 'NOS', 'Printing', '48219090', 70.00, 'active', '18', NULL),
(183, 'VENTIGO FIO2 STICKER', '17005050', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(184, 'FLOW CONTROL VALVE STICKER VENT', '17005051', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(185, 'AC VOLTAGE STICKER 220V TO 240V', '17005052', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(186, 'AHEN Sticker (Etc02)', '17005060', '17', 'Stickers', 'NOS', 'Printing', '48219090', 250.00, 'active', '18', NULL),
(187, 'Sticker Aarya', '17005063', '17', 'Stickers', 'NOS', 'Printing', '48219090', 350.00, 'active', '18', NULL),
(188, 'OXYGEN LINE PRESSURE', '17005064', '17', 'Stickers', 'NOS', 'Printing', '48219090', 15.00, 'active', '18', NULL),
(189, 'oxygen high Pressure', '17005065', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(190, 'AC FUSE 10 A STICKER', '17005066', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(191, 'DC 10A FUSE STICKER', '17005067', '17', 'Stickers', 'NOS', 'Printing', '48219090', 15.00, 'active', '18', NULL),
(192, '230-240V AC STICKER', '17005068', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(193, 'Scale Dome sticker', '17005071', '17', 'Stickers', 'NOS', 'Printing', '48219090', 60.00, 'active', '18', NULL),
(194, 'Nitrous High Pressure', '17005072', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(195, 'Nitrous line pressure', '17005074', '17', 'Stickers', 'NOS', 'Printing', '48219090', 12.00, 'active', '18', NULL),
(196, 'Moppet 6.5 Sticker Without logo', '17005075', '17', 'Stickers', 'NOS', 'Printing', '48219090', 250.00, 'active', '18', NULL),
(197, 'AHEN Sticker Without (Etco2)', '17005076', '17', 'Stickers', 'NOS', 'Printing', '48219090', 250.00, 'active', '18', NULL),
(198, 'Murphy Sticker', '17005077', '17', 'Stickers', 'NOS', 'Printing', '48219090', 375.00, 'active', '18', NULL),
(199, 'Murphy Loop Sticker', '17005078', '17', 'Stickers', 'NOS', 'Printing', '48219090', 250.00, 'active', '18', NULL),
(200, 'NEBULA 2.1 ( YASHKA)', '17005079', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(201, 'HME STICKER VENT BACK COVER', '17005081', '17', 'Stickers', 'NOS', 'Printing', '48219090', 30.00, 'active', '18', NULL),
(202, 'HVS 2500 (YASHKA)', '17005083', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(203, 'NEBULA FIO2 STICKER (ROUND)', '17005084', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(204, 'NVC 9000 (YASHKA )', '17005085', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(205, 'HVS YSP 10\"', '17005086', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(206, 'HVS 4500 XL (RENOMED) 12 \"', '17005088', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(207, 'YSP MOPPET 6.5', '17005089', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(208, 'ARYA (YAHSKA)', '17005090', '17', 'Stickers', 'NOS', 'Printing', '48219090', 80.00, 'active', '18', NULL),
(209, 'YBM 3000', '17005091', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(210, 'MURPHY HOSPIKART STICKER', '17005095', '17', 'Stickers', 'NOS', 'Printing', '48219090', 375.00, 'active', '18', NULL),
(211, 'MOPPET 8.5 XL STICKER', '17005096', '17', 'Stickers', 'NOS', 'Printing', '48219090', 75.00, 'active', '18', NULL),
(212, 'ICVS PLUS (VINYL PRINT)', '17005097', '17', 'Stickers', 'NOS', 'Printing', '48219090', 83.33, 'active', '18', NULL),
(213, 'Onyx Label Tapes, Yellow W-9mm (1pc./set) 30m/pc', '17005098', '17', 'Stickers', 'NOS', 'Printing', '48219090', 1250.00, 'active', '18', NULL),
(214, 'Canon ink Ribbons, IC, Black, 5pc/Set (100m/pc)', '17005099', '17', 'Stickers', 'NOS', 'Printing', '48219090', 4500.00, 'active', '18', NULL),
(215, 'MURPHY YSP STICKER', '17005100', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(216, 'YSP VENTIGO VINAYL STICKER', '17005101', '17', 'Stickers', 'NOS', 'Printing', '48219090', 350.00, 'active', '18', NULL),
(217, 'DNA HOSPIKART STICKER', '17005102', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(218, 'WLE10 HVS MAIN STICKER', '17005103', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(219, 'AHEN 4000 MEDILIO', '17005104', '17', 'Stickers', 'NOS', 'Printing', '48219090', 1200.00, 'active', '18', NULL),
(220, 'HUMIDIFIER MEDILIO', '17005105', '17', 'Stickers', 'NOS', 'Printing', '48219090', 240.00, 'active', '18', NULL),
(221, 'Danger Sticker', '17005106', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(222, 'Read Instructions Manual Sticker', '17005107', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(223, 'DANGER BIG', '17005108', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(224, 'WARNING', '17005109', '17', 'Stickers', 'NOS', 'Printing', '48219090', 0.00, 'active', '18', NULL),
(225, 'EXHAVLE VALLE (STICKER) NEBULA', '17005110', '17', 'Stickers', 'NOS', 'Printing', '48219090', 25.00, 'active', '18', NULL),
(226, 'BIG THREAD CONE - HVS', '22005007', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 501.00, 'active', '18', NULL),
(227, 'Big Thread Cone - XVS', '22005013', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 465.00, 'active', '18', NULL),
(228, 'MOPPET 8.5 BIG THREAD CONE NU-T', '22005014', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(229, 'Exhale Valve Spring', '22005028', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 75.00, 'active', '18', NULL),
(230, 'MONITOR MOUNTING BLOCK', '22005068', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 668.00, 'active', '18', NULL),
(231, 'HUMIDIFIER SPRING', '22005146', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 30.00, 'active', '18', NULL),
(232, 'HUMIDIFIER MOUNITNG DISC', '22005147', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 1008.00, 'active', '18', NULL),
(233, 'HVS HANDLE PIPE 20X2 MM THICKNESS', '22005167', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(234, 'HUMIDIFIER BOTTOM DISC', '22005176', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 727.00, 'active', '18', NULL),
(235, 'HUMIDIFIER BOTTOM DISC BUSH', '22005177', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 216.00, 'active', '18', NULL),
(236, 'Flow Valve Adaptor', '22005184', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 186.00, 'active', '18', NULL),
(237, 'Turbine Fitting', '22005199', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 245.00, 'active', '18', NULL),
(238, 'XVS MONITOR RIM MOUNTING BRACKET', '22005237', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 350.00, 'active', '18', NULL),
(239, 'MONITOR BASE DISK', '22005266', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 700.00, 'active', '18', NULL),
(240, 'AHEN 5000/4500 HANDLe', '22005320', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(241, '6.3 MM HP PIPE', '22005361', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 254.24, 'active', '18', NULL),
(242, 'Pipes 73063090', '22005362', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 450.00, 'active', '18', NULL),
(243, 'SALINE HOLDER', '22005387', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 165.00, 'active', '18', NULL),
(244, 'Hex Fitting M11 x 1/4', '22005411', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 220.00, 'active', '18', NULL),
(245, 'BABY WARMER BUSH', '22005432', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(246, 'VENTIGO INLET CONE', '22005434', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 338.00, 'active', '18', NULL),
(247, 'QUICK CONNECTOR', '22005466', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 375.00, 'active', '18', NULL),
(248, 'OUTLET HEX FITTING', '22005468', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 500.00, 'active', '18', NULL),
(249, 'AHEN 4000 SIDE HANDLE', '22005469', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 350.00, 'active', '18', NULL),
(250, 'ANESTHESIA STAND BUSH', '22005491', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 200.00, 'active', '18', NULL),
(251, '1/8 BSP LOCK NUT', '22005497', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 85.00, 'active', '18', NULL),
(252, 'Murphy handle assembly', '22005498', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(253, '20 x 30 SS Square Pipe', '22005507', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 240.00, 'active', '18', NULL),
(254, '50X50 MM SQUARE DIA PIPE', '22005510', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(255, 'SALINE HOLDER BUSH', '22005511', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 385.00, 'active', '18', NULL),
(256, 'PHOTOTHERAPY BUSH', '22005512', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 265.00, 'active', '18', NULL),
(257, '32 mm pipe', '22005520', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 50.00, 'active', '18', NULL),
(258, 'Phototherapy Pipe Bush', '22005521', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 345.00, 'active', '18', NULL),
(259, 'Blender knob', '22005523', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 408.00, 'active', '18', NULL),
(260, 'moppet exhuast and filterblock', '22005524', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 495.00, 'active', '18', NULL),
(261, 'BELLOW FITTING CONE DNA-1', '22005532', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 395.00, 'active', '18', NULL),
(262, 'BELLOW FITTING CONE DNA-2', '22005533', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 395.00, 'active', '18', NULL),
(263, 'BELLOW FITTING CONE DNA-3', '22005534', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 395.00, 'active', '18', NULL),
(264, 'GAUGE ADAPTOR', '22005536', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 2550.00, 'active', '18', NULL),
(265, 'CIRCLE ABOSRVR BASE FITTNG', '22005543', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 175.00, 'active', '18', NULL),
(266, 'PHOTOTHERAPHY SS 222 MM PIPE', '22005546', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 1990.00, 'active', '18', NULL),
(267, 'PHOTOTHERAPHY 243 MM SS PIPE', '22005547', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(268, 'Moppet 8.5 Exhaust valve & Filter cone', '22005548', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 0.00, 'active', '18', NULL),
(269, 'ceramic cylender', '22005549', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 535.00, 'active', '18', NULL),
(270, 'CYLINDER PIPE FITTING', '22005550', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 985.00, 'active', '18', NULL),
(271, 'MOPPET STAND BUSH', '22005554', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 978.00, 'active', '18', NULL),
(272, 'APL KNOB', '22005557', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 520.00, 'active', '18', NULL),
(273, '12.5 ANEASTHESIA Handle pipe', '22005560', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 385.00, 'active', '18', NULL),
(274, 'BAIN CKT FLOW SENSOR CONE', '22005562', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 250.00, 'active', '18', NULL),
(275, 'NEW MONITOR SS PIPE', '22005566', '22', 'Machining F.G', 'NOS', 'Manufacturing', '8487', 320.00, 'active', '18', NULL),
(276, 'AIR FILTER _62005004', '62005004', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 35.00, 'active', '18', NULL),
(277, 'Elbow Male 8XG1/4 \"_62005007', '62005007', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 75.00, 'active', '18', NULL),
(278, 'Elbow Male 8x1/2\"_62005009', '62005009', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90262000', 148.00, 'active', '18', NULL),
(279, 'Pressure Gauge 10 Bar_62005010', '62005010', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 279.00, 'active', '18', NULL),
(280, '8X1/2 MALE PUSH FITTING _62005011', '62005011', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 113.00, 'active', '18', NULL),
(281, 'Push Fitting Male8XG1/4\"_62005012', '62005012', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84813000', 54.00, 'active', '18', NULL),
(282, 'NRV 1/4_62005013', '62005013', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 407.00, 'active', '18', NULL),
(283, 'Union Tee 8X8X8_62005014', '62005014', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 86.00, 'active', '18', NULL),
(284, 'Push Fitting Male4XM5\"_62005016', '62005016', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 55.00, 'active', '18', NULL),
(285, 'DMN Valve_62005020', '62005020', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 3094.00, 'active', '18', NULL),
(286, 'Union 8-12_62005021', '62005021', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84818030', 134.55, 'active', '18', NULL),
(287, 'Pressure Regulator_62005024', '62005024', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84799090', 692.00, 'active', '18', NULL),
(288, 'Water Seperator_62005026', '62005026', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 647.00, 'active', '18', NULL),
(289, 'Push Ftting Male 6 x 1/4_62005028', '62005028', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84799090', 50.00, 'active', '18', NULL),
(290, 'Pressure Gauge 4 Bar_62005033', '62005033', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 279.00, 'active', '18', NULL),
(291, 'Push Fitting Female8XG1/4\"_62005038', '62005038', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 116.00, 'active', '18', NULL),
(292, 'silencer/muffler_62005040', '62005040', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 45.00, 'active', '18', NULL),
(293, 'PU TUBE 8MM TRANSPARENT_62005041', '62005041', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 68.00, 'active', '18', NULL),
(294, 'PU TUBE 6MM TRANSAPARENT _62005057', '62005057', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 71.00, 'active', '18', NULL),
(295, 'FEMALE CONNECTOR 8MM X 1/2\'\'_62005058', '62005058', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8414', 312.00, 'active', '18', NULL),
(296, 'COMPRESSOR PRESSURE SOFT TUBE 380MM_62005059', '62005059', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 177.00, 'active', '18', NULL),
(297, 'Push Fitting Female 8x1/8\" _62005064', '62005064', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '7412', 103.00, 'active', '18', NULL),
(298, 'Hose Nipple Brass1/4\"_62005093', '62005093', '62', 'PNEUMATIC PARTS', 'NOS', 'Brought Out', '90252000', 50.00, 'active', '18', NULL),
(299, 'GAUGE AIR 16KG_62005094', '62005094', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90252000', 100.00, 'active', '18', NULL),
(300, 'GAUGE OXYGEN 16KG_62005095', '62005095', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90252000', 100.00, 'active', '18', NULL),
(301, 'NITROUS GAUGE 16 KG_62005096', '62005096', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8414', 100.00, 'active', '18', NULL),
(302, 'Compressor GA 550 _62005099', '62005099', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '7307', 4900.00, 'active', '18', NULL),
(303, 'SS PLUG BOLT 1/4_62005106', '62005106', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 60.00, 'active', '18', NULL),
(304, 'PUSH FITTING MALE 4 X 1/4\"_62005114', '62005114', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 44.00, 'active', '18', NULL),
(305, 'PU TUBE 4MM TRANSPARENT _62005124', '62005124', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 15.50, 'active', '18', NULL),
(306, 'MALE BRANCH TEE 8 X 1/4\"_62005125', '62005125', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84798999', 147.00, 'active', '18', NULL),
(307, 'PRECISION REGULATOR_62005128', '62005128', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 3941.00, 'active', '18', NULL),
(308, 'QRC COUPLER_62005137', '62005137', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90189041', 145.00, 'active', '18', NULL),
(309, 'Fail Safe Valve _62005145', '62005145', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90181110', 1150.00, 'active', '18', NULL),
(310, 'Circle Absorber_62005147', '62005147', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 10500.00, 'active', '18', NULL),
(311, '0-20LPM 150MM FLOWMETER_62005151', '62005151', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9018', 1450.00, 'active', '18', NULL),
(312, 'Bellow_62005152', '62005152', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9018', 10000.00, 'active', '18', NULL),
(313, 'Tec Bar Twin_62005160', '62005160', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 6500.00, 'active', '18', NULL),
(314, '3 WAY VALVE_62005172', '62005172', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90330000', 2000.00, 'active', '18', NULL),
(315, 'Oxygen Alarm_62005174', '62005174', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90330000', 900.00, 'active', '18', NULL),
(316, 'NRV Anesthesia_62005177', '62005177', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 850.00, 'active', '18', NULL),
(317, '5 TUBE ROTAMETER_62005229', '62005229', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90189041', 15500.00, 'active', '18', NULL),
(318, 'OXYGEN MOX REGULATOR_62005236', '62005236', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 1100.00, 'active', '18', NULL),
(319, 'Sq Knob M 8*30 SALINE HOLDER KNOB_62005241', '62005241', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 40.00, 'active', '18', NULL),
(320, 'WHITE BUSH_62005245', '62005245', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 20.00, 'active', '18', NULL),
(321, 'D CHAIN_62005246', '62005246', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 80.00, 'active', '18', NULL),
(322, 'ELBOW MALE 8 X 1/8\"_62005256', '62005256', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84818090', 72.00, 'active', '18', NULL),
(323, 'Techno Drain Valve_62005263', '62005263', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8414', 1800.00, 'active', '18', NULL),
(324, 'Compressor NRV_62005264', '62005264', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8414', 180.00, 'active', '18', NULL),
(325, 'Pressure Switch Lefoo_62005265', '62005265', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 285.00, 'active', '18', NULL),
(326, '6X4 REDUCER_62005271', '62005271', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8462', 115.00, 'active', '18', NULL),
(327, 'Equal Tee 6 mm_62005274', '62005274', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 100.00, 'active', '18', NULL),
(328, '8 X 4 UNION_62005277', '62005277', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 151.00, 'active', '18', NULL),
(329, 'POCKET HANDLE_62005282', '62005282', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '73072900', 110.00, 'active', '18', NULL),
(330, 'S S 304 MALE CONNECTOR 1/4NPT X 6OD_62005295', '62005295', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 116.00, 'active', '18', NULL),
(331, 'SRE FCV FLOW CONTROL 04 1/2_62005297', '62005297', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 335.00, 'active', '18', NULL),
(332, 'NEBULISER FCV 8MM X 1/4\"_62005299', '62005299', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 360.00, 'active', '18', NULL),
(333, 'ROTAMETER CONE_62005311', '62005311', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '', 60.00, 'active', '18', NULL),
(334, 'ARYA 2 TUBE ROTAMETER_62005319', '62005319', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84818030', 8600.00, 'active', '18', NULL),
(335, 'NRV 163_62005328', '62005328', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 785.00, 'active', '18', NULL),
(336, '8 X 3/8 FEMALE PUSH FITTING_62005332', '62005332', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9206', 196.00, 'active', '18', NULL),
(337, 'Flowmeter 150 mm FSA50/2-20LPM_62005338', '62005338', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9206', 1450.00, 'active', '18', NULL),
(338, 'Turbine_62005353', '62005353', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 8701.60, 'active', '18', NULL),
(339, 'INLINE FCV 8MM_62005355', '62005355', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 484.00, 'active', '18', NULL),
(340, 'Push Connector Female FittingÂ 4*1/4_62005356', '62005356', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 113.00, 'active', '18', NULL),
(341, 'VAPORIZER SEVOFLURANE _62005436', '62005436', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 0.00, 'active', '18', NULL),
(342, 'push fitting male 12 x 1/2\"_62005454', '62005454', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 133.00, 'active', '18', NULL),
(343, 'Push fitting female 12x1/2\"_62005455', '62005455', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90189041', 333.00, 'active', '18', NULL),
(344, 'YOKE STOPPER WITH CHAIN_62005456', '62005456', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90189041', 70.00, 'active', '18', NULL),
(345, 'NITROUS MOX REGULATOR_62005457', '62005457', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90189041', 1100.00, 'active', '18', NULL),
(346, 'OXYGEN YOKE_62005458', '62005458', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90189041', 900.00, 'active', '18', NULL),
(347, 'NITROUS YOKE_62005459', '62005459', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 900.00, 'active', '18', NULL),
(348, 'UNION TEE 12MM_62005460', '62005460', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 144.00, 'active', '18', NULL),
(349, 'VAPORIZER ISOFLURANE_62005461', '62005461', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 44000.00, 'active', '18', NULL),
(350, 'PU TUBE 8 MM BLACK_62005462', '62005462', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 68.00, 'active', '18', NULL),
(351, '8 MM PU TUBE BLUE_62005463', '62005463', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 47.60, 'active', '18', NULL),
(352, 'PU tube 8mm red_62005464', '62005464', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '3917', 68.00, 'active', '18', NULL),
(353, '6MM RED TUBE_62005465', '62005465', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 75.00, 'active', '18', NULL),
(354, 'PU TUBE 6MM BLUE_62005466', '62005466', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 27.30, 'active', '18', NULL),
(355, 'PU TUBE 6 MM BLACK_62005467', '62005467', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9018', 27.30, 'active', '18', NULL),
(356, 'Tecbar single_62005468', '62005468', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 3000.00, 'active', '18', NULL),
(357, 'Male connector 8mm x 1/8\"_62005469', '62005469', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 33.81, 'active', '18', NULL),
(358, 'Tube 4mm RED_62005470', '62005470', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 45.00, 'active', '18', NULL),
(359, 'Tube 4mm Black_62005471', '62005471', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8481', 40.00, 'active', '18', NULL),
(360, 'QRC ADAPTOR Male Plug 1/4\"_62005474', '62005474', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 140.00, 'active', '18', NULL),
(361, 'MALE ELBOW 6X1/4\"_62005478', '62005478', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 61.00, 'active', '18', NULL),
(362, 'Female Elbow BSP 8 x 1/4_62005479', '62005479', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 133.00, 'active', '18', NULL),
(363, 'seal bodok_62005483', '62005483', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90189041', 344.00, 'active', '18', NULL),
(364, 'Equal Tee 04_62005486', '62005486', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 8.00, 'active', '18', NULL),
(365, 'Ceramic Filter_62005490', '62005490', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84219900', 77.00, 'active', '18', NULL),
(366, 'PU TUBE 12 MM TRANSPARENT 62005491', '62005491', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '39173100', 506.00, 'active', '18', NULL),
(367, 'FEMALE ELBOW 1/8 *8 62005492', '62005492', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 95.91, 'active', '18', NULL),
(368, 'MALE ELBOW 12X 1/2 \"62005493', '62005493', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 110.00, 'active', '18', NULL),
(369, '50 mm dial dia Pressure guage 0-250kg o262005521', '62005521', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 153.00, 'active', '18', NULL),
(370, '2 Bar gauge62005522', '62005522', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 300.00, 'active', '18', NULL),
(371, 'On/ Off valve62005523', '62005523', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8481', 293.00, 'active', '18', NULL),
(372, '50 mm dial dia Pressure guage 0-16kg o262005524', '62005524', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 200.00, 'active', '18', NULL),
(373, '50 mm dial dia Pressure guage 0-250 N2O62005525', '62005525', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 280.00, 'active', '18', NULL),
(374, '50 mm dial dia Pressure guage 0-16KG N2O62005526', '62005526', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 300.00, 'active', '18', NULL),
(375, '50 mm dial dia Pressure guage 0-16KG AIR62005527', '62005527', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 280.00, 'active', '18', NULL),
(376, 'Circle Absobrer With Mano62005528', '62005528', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '90181110', 280.00, 'active', '18', NULL),
(377, 'Negative Pressure Gauge 62005529', '62005529', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 10500.00, 'active', '18', NULL),
(378, 'APL Valve 62005530', '62005530', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 0.00, 'active', '18', NULL),
(379, '6 tube Rotameter62005531', '62005531', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '9026', 18500.00, 'active', '18', NULL),
(380, 'AC Valve', '62005535', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 0.00, 'active', '18', NULL),
(381, 'HOSE BRASS NIPPLE 1/8 AIR', '62005536', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '84879000', 0.00, 'active', '18', NULL),
(382, 'ECG 6 Channel', 'YID -043', 'YID', 'ECG machine', 'NOS', 'Finished Good', '1234', 40000.00, 'active', '18', NULL),
(383, 'Portable', 'YID -050', 'YID', 'Sale Finish Good', 'NOS', 'Finished Good', '341', 5500.00, 'active', '18', NULL),
(384, 'Semi fowler bed', 'YID -108', 'YID', 'Medical Bed', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(385, 'XVS 9500 XXL', 'YID-001', 'YID', 'ICU Ventilator', 'Nos', 'Finished Good', '90192090', 400000.00, 'active', '5', NULL),
(386, 'XVS 9500 XL.', 'YID-002', 'YID', 'ICU Ventilator', 'Nos', 'Finished Good', '', 520000.00, 'active', '5', NULL),
(387, 'XVS 9500', 'YID-003', 'YID', 'ICU Ventilator', 'NOS', 'Finished Good', '', 480000.00, 'active', '18', NULL),
(388, 'HVS 3500 XXL', 'YID-004', 'YID', 'ICU Ventilator', 'NOS', 'Finished Good', '', 360000.00, 'active', '18', NULL),
(389, 'HVS 3500 XL', 'YID-005', 'YID', 'ICU Ventilator', 'NOS', 'Finished Good', '', 360000.00, 'active', '18', NULL),
(390, 'HVS 3500', 'YID-006', 'YID', 'ICU Ventilator', 'NOS', 'Finished Good', '', 360000.00, 'active', '18', NULL),
(391, 'HVS 2500 XL', 'YID-007', 'YID', 'ICU Ventilator', 'NOS', 'Finished Good', '', 360000.00, 'active', '18', NULL),
(392, 'HVS 2500', 'YID-008', 'YID', 'ICU Ventilator', 'NOS', 'Finished Good', '', 360000.00, 'active', '18', NULL),
(393, 'NVS 9500 XXL', 'YID-009', '0', 'ICU Ventilator', 'NOS', 'Finished Good', '', 480000.00, 'active', '18', NULL),
(394, 'NVS 9500 XL', 'YID-010', '0', 'ICU Ventilator', 'NOS', 'Finished Good', '', 440000.00, 'active', '18', NULL),
(395, 'CPAP Machine', 'YID-0105', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(396, 'NVS 9500', 'YID-011', '0', 'ICU Ventilator', 'NOS', 'Finished Good', '', 420000.00, 'active', '18', NULL),
(397, 'O2 Concentrator', 'YID-0116', '0', 'Oxygen Generator', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(398, 'Oxygen Concentrator 10 Litre', 'YID-0117', '0', 'Oxygen Generator', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(399, 'O2 Concentrator 5 litre', 'YID-0118', '0', 'Oxygen Generator', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(400, 'WL002 (AHEN 4000)', 'YID-0119', '0', 'Anesthesia Work Station', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(401, 'Ventigo 77', 'YID-012', '0', 'ICU Ventilator', 'Nos', 'Finished Good', '90192090', 200000.00, 'active', '5', NULL),
(402, 'WLE10 (HVS 3500)', 'YID-0120', '0', 'ICU Ventilator', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(403, 'Hospikart (Murphy)', 'YID-0121', '0', 'Anesthesia Work Station', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(404, 'XVS 9500 XL', 'YID-0122', '0', 'ICU Ventilator', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(405, 'EXPORT - NEBULA 2.1', 'YID-0123', '0', 'ICU Ventilator', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(406, 'AHEN 5000 (Export)', 'YID-0124', '0', 'Anesthesia Work Station', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(407, 'Neopurle Bubble CPAP machine', 'YID-0127', '0', 'Bubble CPAP mc', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(408, 'Ventigo 77 Neo', 'YID-013', '0', 'ICU Ventilator', 'NOS', 'Finished Good', '', 260000.00, 'active', '18', NULL),
(409, 'Nebulizer Machine', 'YID-0137', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(410, 'Ventigo 77 XL', 'YID-014', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 280000.00, 'active', '18', NULL),
(411, 'MOPPET 7.5', 'YID-015', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(412, 'Moppet 6.5', 'YID-016', '0', 'Sale Finish Good', 'Nos', 'Finished Good', '', 0.00, 'active', '5', NULL),
(413, 'ABG Gas Analyzer', 'YID-0162', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 75000.00, 'active', '18', NULL),
(414, 'AVS buyback', 'YID-0168', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(415, 'AHEN 5000', 'YID-017', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 520000.00, 'active', '18', NULL),
(416, 'AHEN 4500 Anesthesia Workstation', 'YID-018', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 480000.00, 'active', '18', NULL);
INSERT INTO `part_master` (`id`, `part_name`, `part_no`, `part_id`, `description`, `uom`, `category`, `hsn_code`, `rate`, `status`, `gst`, `attachment_path`) VALUES
(417, 'AHEN 4000', 'YID-019', '0', 'Sale Finish Good', 'Nos', 'Finished Good', '', 440000.00, 'active', '5', NULL),
(418, 'Advanced Boyles machine', 'YID-020', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(419, 'Arya 25-Anesthesia workstation', 'YID-021', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 280000.00, 'active', '18', NULL),
(420, 'Murphy 23 Annesthesia', 'YID-022', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 290000.00, 'active', '18', NULL),
(421, 'Neopurle Bubble CPAP', 'YID-023', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 125000.00, 'active', '18', NULL),
(422, 'Neopurle XL Bubble CPAP', 'YID-024', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 144000.00, 'active', '18', NULL),
(423, 'Baby Warmer with Dual Probe', 'YID-026', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 45000.00, 'active', '18', NULL),
(424, 'Baby Warmer with Drawer', 'YID-027', '0', 'Sale Finish Good', 'Nos', 'Finished Good', '', 24000.00, 'active', '5', NULL),
(425, 'Phototherapy', 'YID-028', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 24000.00, 'active', '18', NULL),
(426, 'Phototherapy Lamp', 'YID-029', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 18000.00, 'active', '18', NULL),
(427, '3 Para Patient Monitor', 'YID-030', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 16000.00, 'active', '18', NULL),
(428, '5 Para Patient Monitor', 'YID-031', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 20000.00, 'active', '18', NULL),
(429, '7 Para Patient Monitor', 'YID-032', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 72000.00, 'active', '18', NULL),
(430, 'Syringe Pump Medovo', 'YID-033', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 16000.00, 'active', '18', NULL),
(431, 'Syringe Pump', 'YID-034', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 18000.00, 'active', '18', NULL),
(432, 'Syringe Pump (Medevo)', 'YID-034.', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 18000.00, 'active', '18', NULL),
(433, 'Infusion Pump Medovo', 'YID-035', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 22000.00, 'active', '18', NULL),
(434, 'Single Dome OT Light x', 'YID-036', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 480000.00, 'active', '18', NULL),
(435, 'Double Dome OT Light', 'YID-037', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 76000.00, 'active', '18', NULL),
(436, 'OT Light Single Dome', 'YID-038', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 50000.00, 'active', '18', NULL),
(437, 'General OT Table', 'YID-039', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 72000.00, 'active', '18', NULL),
(438, 'OT Table', 'YID-040', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 88000.00, 'active', '18', NULL),
(439, 'Electric -Manual OT Table', 'YID-041', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 136000.00, 'active', '18', NULL),
(440, 'ECG Machine 3 channel', 'YID-042', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 24000.00, 'active', '18', NULL),
(441, '12 Channel ECG Machine', 'YID-044', 'YID', 'Sale Finish Good', 'NOS', 'Finished Good', '', 48000.00, 'active', '18', NULL),
(443, 'Defebrilator', 'YID-045', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 120000.00, 'active', '18', NULL),
(444, 'Normal Humidifier x', 'YID-047', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 35000.00, 'active', '18', NULL),
(445, 'Suction machine', 'YID-048', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 16000.00, 'active', '18', NULL),
(446, 'Suction Machine', 'YID-049', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 20000.00, 'active', '18', NULL),
(447, 'NVS 9000 XL', 'YID-057', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 450000.00, 'active', '18', NULL),
(448, 'cautery machine', 'YID-058', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 25000.00, 'active', '18', NULL),
(449, 'Fumigator', 'YID-059', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 13000.00, 'active', '18', NULL),
(450, 'Autoclavable Machine', 'YID-061', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(451, 'Murphy 23 2 Gas', 'YID-062', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 250000.00, 'active', '18', NULL),
(452, '5 Function Manual ICU Bed', 'YID-063', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(453, 'ABG Machine', 'YID-064', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(454, 'Patient Warmer', 'YID-065', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(455, 'HVS 4500 XL', 'YID-066', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(456, 'Nebula 2.1', 'YID-068', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(457, 'Scrub Fabric suit', 'YID-069', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(458, 'DNA-25', 'YID-073', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(459, 'Ventigo Trolly', 'YID-077', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(460, 'Compressor 0.5 HP', 'YID-078', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(461, 'Bed Side Locker', 'YID-080', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(462, 'Food Trolly', 'YID-081', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(463, 'Fogger Machine', 'YID-082', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(464, 'C-Arm Compatible', 'YID-083', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(465, 'X-ray machine', 'YID-084', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(466, 'AVS Pro', 'YID-085', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(467, 'Bubble CPAP Stand', 'YID-086', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(468, 'Infusion Pump TM', 'YID-087', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(469, 'Neopurle', 'YID-095', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(470, 'Murphy 23', 'YID-096', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(471, 'Moppet 8.5 Transport ventilator', 'YID-097', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(472, 'ICVS Plus ICU Ventilator', 'YID-098', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(473, 'Moppet Trolly', 'YID-099', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(474, 'Arya -35 with', 'YID-100', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(475, 'BMC Bipap', 'YID-102', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(476, 'Moppet 8.5', 'YID-105', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '5', NULL),
(477, 'Moppet 6.5 XL', 'YID-108', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(478, 'Transport Incubator', 'YID-109', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(479, 'Plane bed', 'YID-110', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(480, 'Ambitious (Anesthesia Workstation)', 'YID-111', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(481, 'DL 2605', 'YID-113', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(482, 'AED 8000 Defibrillator', 'YID-116', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(483, 'AHEN 4000(Anesthesia Workstation', 'YID-127', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(484, 'DVT Pump', 'YID-163', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 20000.00, 'active', '18', NULL),
(485, 'Hugger Bear Warmer', 'YID-164', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 20000.00, 'active', '18', NULL),
(486, 'Air Bed', 'YID-165', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 15000.00, 'active', '18', NULL),
(487, 'Baby warmer with phototherapy', 'YID-166', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 40000.00, 'active', '18', NULL),
(488, 'Lio smart-7.5', 'YID-167', 'YID', 'Sale Finish Good', 'NOS', 'Finished Good', '90192090', 100000.00, 'active', '5', NULL),
(489, 'Gynae Examination Table', 'YID-60', '0', 'Sale Finish Good', 'NOS', 'Finished Good', '', 0.00, 'active', '18', NULL),
(490, 'BUBBLECPAP MANNUAL', '14005001', '14', 'Mannuals', 'NOS', 'Printing', '90189041', 1.00, 'active', '18', NULL),
(491, 'DL-2605 MANNUAL', '14005002', '14', 'Mannuals', 'NOS', 'Printing', '90189041', 1.00, 'active', '18', NULL),
(492, 'XVS MANUAL', '14005003', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(493, 'HVS 3500 XL MANUL', '14005004', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(494, 'ARYA MANUAL', '14005005', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(495, 'MOPPET 6.5 MANUAL', '14005006', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(496, 'MURPHY MANUAL', '14005007', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(497, 'VENTIGO 77 MANUAL', '14005008', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(498, '2 FLOWMETER BUBBLECPAP MANUAL', '14005009', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(499, 'AHEN 4000 MANUAL', '14005010', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(500, 'MOPPET 8. 5 MANUAL', '14005011', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(501, 'AHEN 5000 MANUAL', '14005012', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(502, 'AHEN 4500 MANUAL', '14005013', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(503, 'BABY WARMER WITH DRAWER MANUAL', '14005015', '14', 'Mannuals', 'Nos', 'Printing', '123', 1.00, 'active', '5', NULL),
(504, 'HVS 2500 MANUAL', '14005016', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(505, 'DNA MANNUL', '14005017', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(506, 'NEBULA 2.1 MANUAL', '14005018', '14', 'Mannuals', 'NOS', 'Printing', '123', 1.00, 'active', '18', NULL),
(507, 'BW ACRYLIC SIDE', '16005025', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(508, 'BW ACRYLIC FRONT', '16005026', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(509, 'HUMIDIFIER BRACKET', '16005031', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(510, 'PHOTOTHERAPY LIGHT ACRYLIC', '16005036', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(511, 'Plastic Handle 150 mm', '16005037', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(512, 'Active exhilation valve DPNAEV Plastic Exhaust Valve', '16005038', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(513, 'Acrylic Front BW New', '16005039', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(514, 'Acrylic Side BW New', '16005040', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(515, 'BW Bed Acrylic', '16005041', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(516, 'Pocket Handle Black', '16005042', '16', 'Plastic', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(517, 'NEW MACHINE MONITOR DISC', '22005568', '22', 'Machining F.G', 'NOS', 'Manufacturing', '123', 1.00, 'active', '18', NULL),
(518, 'MONITOR MOUNTING ARM 2', '22005569', '22', 'Machining F.G', 'NOS', 'Manufacturing', '123', 1.00, 'active', '18', NULL),
(519, 'SMALL NIPPLE 1/4\" SS', '23005014', '23', 'Machining F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(520, 'NEBULIZER NIPPLE', '23005016', '23', 'Machining F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(521, 'BUSH 1/4\" BSP', '23005017', '23', 'Machining F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(522, 'Elbow 6OD Ferrule - HIGH PRESSURE', '23005018', '23', 'Machining F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(523, 'BUSH SS Socket 3/8\"', '23005019', '23', 'Machining F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(524, '6 OD Pipe', '23005020', '23', 'Machining F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(525, '8MMX3/8 SS NEBULIZER NIPPLE', '23005022', '23', 'Machining F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(526, 'ARM BRACKET', '32005010', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(527, 'ARM BRACKET A', '32005011', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(528, 'GUAGE BACK PLATE', '32005023', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(529, 'AVS DC Fan Door', '32005044', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(530, 'AUTO DRAIN VALVE BRACKET', '32005090', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(531, 'Compressor Filter Bracket', '32005097', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(532, 'COLUMN DOOR', '32005102', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(533, 'LP TANK BOTTOM PLATE', '32005109', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(534, 'UPPER PLATE', '32005110', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(535, 'HVS 10 INCH SCREEN BRACKET', '32005124', '32', 'Sheet Metal Laser Cutting & Bending', 'Nos', 'Manufacturing', '8462', 15.00, 'active', '18', NULL),
(536, 'HVS DRIVER BRACKET', '32005125', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(537, 'PRESSURE SENSOR BOX 32005155', '32005155', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(538, 'PRESSURE SENSOR HOUSING CAP_32005156', '32005156', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(539, 'XVS COBRA MONITOR MOUNTING BACK PLATE', '32005179', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(540, 'XVS COBRA FRONT PLATE', '32005193', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(541, 'COBRA MONITOR MOUNTING SIDE PLATE', '32005194', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(542, 'COBRA RIM MOUNITING DISC', '32005237', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(543, 'MONITOR MOUNTING C BRACKET', '32005353', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(544, 'voltage indicator rim', '32005498', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(545, 'MCB BRACKET', '32005544', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(546, 'POWER SOCKET SUPPORT PLATE', '32005553', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(547, 'HUMIDIFIER NEO BACK BODY', '32005564', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(548, 'HUMIDIFIER NEO UPPER HALF RING MS', '32005567', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(549, 'COLUMN BODY', '32005572', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(550, 'HUMIDIFIER NEO FRONT PLATE MS', '32005582', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(551, 'HUMIDIFIER NEO TOP PLATE', '32005583', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(552, 'MIXTURE NOZZEL WASHER', '32005591', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(553, 'COMPRESSOR BRACKET', '32005629', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(554, 'COMPRESSOR DOOR RH', '32005630', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(555, 'COMPRESSOR DOOR LH', '32005631', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(556, 'L BRACKET STAND', '32005632', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(557, 'HVS HANDLE BRACKET', '32005650', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(558, 'COMPRESSOR BACK DOOR CB', '32005653', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(559, 'SMPS BRACKET', '32005656', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(560, 'BASE STAND LEG FRONT U shpe', '32005663', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(561, 'BASE STAND LEG BACK V shape', '32005664', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(562, 'BASE STAND MAIN PART', '32005667', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(563, 'COMPRESSOR BASE & TOP COVER', '32005668', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(564, 'COMPRESSOR CABINET SIDE DOOR PART', '32005669', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(565, 'MONITOR FRONT COVER', '32005670', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(566, 'MONITOR CABINET XVS', '32005671', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(567, 'MONITOR FLENCH CUTOUT', '32005672', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(568, 'HVS 10 INCH PCB TRAY', '32005675', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(569, 'HVS 4500 TOP CABINET COVER', '32005678', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(570, 'HVS CPU FRONT COVER', '32005679', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(571, 'INLET FLOW SENSOR BRACKET', '32005681', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(572, 'BATTERY BRACKET', '32005684', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(573, 'PCB SILDER BRACKET', '32005685', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(574, 'CABINET SLIDER BRACKET', '32005686', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(575, 'HANDLE BRACKET CUTOUT', '32005688', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(576, 'KEYPAD BACK PLATE', '32005689', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(577, 'COLUMN DOOR XVS', '32005696', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(578, 'AVS PCB Tray', '32005698', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(579, 'COMPRESSOR REINFORCEMENT PLATE TOP', '32005702', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(580, 'COLUMN BASE PLATE', '32005703', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(581, 'FRONT COVER CABINET 10 inch', '32005704', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(582, 'HVS 4500 SIDE COVER CABINET', '32005705', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(583, 'BOTTOM COVER HUMIDIFER', '32005707', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(584, 'HVS 4500 PCB TRAY', '32005708', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(585, 'HVS 4500 SCREEN BRACKET', '32005709', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(586, 'SIDE COVER CABINET 10 inch', '32005710', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(587, '12\" SCREEN BRACKET BACK COVER AW +', '32005711', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(588, 'XVS FRONT CABINET COVER', '32005713', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(589, 'TOP COVER CABINET VENT XVS', '32005714', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(590, 'CABINET FRONT COVER', '32005716', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(591, 'XVS VALVE SUPPORT PLATE', '32005718', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(592, 'XVS LP TANK SIDE PLATE', '32005719', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(593, 'XVS PCB TRAY', '32005721', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(594, 'CABINET BACK DOOR', '32005722', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(595, 'AVS Tank Door', '32005726', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(596, 'XVS LP TANK PLATE', '32005763', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(597, 'HVS 2500 BASE STAND', '32005771', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(598, 'HVS 2500 COLUMN TURBINE', '32005772', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(599, 'BACK COVER COLUMN TURBINE', '32005773', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(600, 'HVS 2500 COLUMN PLATE', '32005774', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(601, 'FRONT COVER CABINET (TURBINE)', '32005778', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(602, 'NEBULA TOP SIDE COVER CABINET', '32005787', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(603, 'CABINET BACK DOOR NEBULA', '32005789', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(604, 'INLET FLOW SENSOR BRACKET MOPPET _32005790', '32005790', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(605, 'VENTIGO MAIN COVER BODY', '32005800', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(606, 'VENTIGO FRONT COVER PLATE', '32005801', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(607, 'VENTIGO BACK COVER', '32005802', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(608, 'TURBINE BRACKET_32005803', '32005803', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(609, 'HVS 2500 COLUMN UPPER PLATE', '32005811', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(610, 'FLOW SENSOR BRACKET', '32005812', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(611, 'ICVS BATTERY BRACKET', '32005813', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(612, 'MOPPET FRONT CABINET', '32005832', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(613, 'VENTIGO BATTERY MOUNTING PLATE', '32005842', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(614, 'VENTIGO TOP PLATE', '32005843', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(615, 'PCB MOUNTING PLATE VENTIGO', '32005844', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(616, 'VENTIGO BATTERY BRACKET', '32005845', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(617, 'VALVE BRACKET_32005846', '32005846', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(618, 'CPU PLATE', '32005851', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(619, 'CPU BRACKET', '32005852', '32', 'Sheet Metal Laser Cutting & Bending', 'Nos', 'Manufacturing', '8462', 10.00, 'active', '18', NULL),
(620, 'CPU MOUNTING PLATE', '32005853', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(621, 'VENTIGO DRIVER BOARD', '32005854', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(622, 'PH/BUBBLECPAP pipe support plate', '32005857', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(623, 'BUBBLECPAP PRESSURE GAUGE MOUNTING BRACKET', '32005859', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(624, 'BUBBLECPAP HANDLE BRACKET', '32005860', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(625, 'MONITOR RIM', '32005861', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(626, 'XVS HANDLE', '32005862', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(627, 'HUMIDIFIER BRACKET 1', '32005863', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(628, 'HUMIDIFIER BRACKET 2', '32005864', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(629, 'BUBBLECPAP BUSH SUPPORT PLATE', '32005881', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(630, '139 OD Pipe', '32005892', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(631, 'HP TANKL DISK', '32005893', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(632, 'THERMISTOR BRACKET HUMIDIFIER', '32005898', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(633, 'XVS SS MONITOR MOUNTING RIM', '32005900', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(634, 'MURPHY SUPPORT STAND', '32005903', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(635, 'REGULATOR BRACKET', '32005906', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(636, 'HVS 2500 COLUMN CABINET', '32005913', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(637, 'HVS 2500 COLUMN BACK DOOR', '32005914', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(638, 'COLUMN VENTIGO TROLLY', '32005915', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(639, 'COLUMN MOUNTING PLATE', '32005917', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(640, 'VENTIGO ROLLING PLATE CORNER', '32005919', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(641, 'VENTIGO TROLLY UPPER PLATE', '32005920', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(642, 'AHEN 4500/500 COMPRESSOR CABINET BASE', '32005930', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(643, 'AHEN 4500/5000 COMPRESSOR CABINET TOP AND BACK', '32005931', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(644, 'AHEN 4500/5000 COMPRESSOR PARTITION', '32005932', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(645, 'AHEN 4500/5000 SMALL DRAWER FRONT FRAME', '32005933', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(646, 'AHEN 4500/5000 SMALL DRAWER BACK FRAME', '32005934', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(647, 'AHEN BIG DRAWER FRONT FRAME', '32005935', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(648, 'AHEN BIG DRAWER BACK FRAME', '32005936', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(649, 'AW COMPRESSOR BACK COVER', '32005937', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(650, 'ELECTRICAL PANEL BASE AHEN 4500/5000', '32005938', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(651, 'AHEN ELECTRICAL PANEL SIDE COVER', '32005939', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(652, 'ELECTRICAL PANEL TOP COVER AHEN 4500/5000', '32005940', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(653, 'AHEN 5000 MAIN CABINET BACK & TOP COVER', '32005941', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(654, 'AHEN 4500/5000 LAMP CABINET', '32005947', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(655, 'LAMP CABINET SIDE CUTOUT BUMPING PLATE', '32005948', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(656, 'AHEN 4500/5000 LAMP CABINET TRAY', '32005949', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(657, 'Phototheraphy cabinet', '32005950', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(658, 'AHEN 4500/5000 BASE STAND TOP', '32005955', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(659, 'AHEN 4500/5000 BASE STAND BACK LEG', '32005956', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(660, 'AHEN4500/5000 CABINET BACK COVER', '32005959', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(661, 'AHEN 4500/5000 TRAY BACK COVER', '32005960', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(662, 'BOTTOM PLATE_BELOW', '32005962', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(663, 'FRONT PLATE_BELOW', '32005963', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(664, 'NEW BABY WARMER PLATE', '32005988', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(665, 'AW DOUBLE REGULATOR BRACKET', '32006151', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(666, 'AHEN 4500/5000 TECH BAR PLATE', '32006153', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(667, 'ahen 4000 ss tray', '32006159', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(668, 'ELECTRICAL PANEL SS TRAY AHEN 4500/5000', '32006160', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(669, 'AW VALVE SUPPORT BRACKET', '32006161', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(670, 'ANESTHESIA 4500/5000 BASE STAND FLENGE BACK', '32006177', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(671, 'AHEN BATTERY BRACKET', '32006200', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(672, 'AHEN 4500/5000 STAND FRONT LEG RH', '32006209', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(673, 'AHEN 4500/5000 STAND FRONT LEG LH', '32006210', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(674, 'HP TANK DISC SPACER SS', '32006254', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(675, 'POCKET HANDLE BRACKET', '32006281', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(676, 'BASE BRACKET MIDDLE FLENGE BW', '32006394', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(677, 'BABY WARMER HEATER FRAME TOP', '32006403', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(678, 'BABY WARMER HEATER FRAME DOWN', '32006405', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(679, 'NEW HEATER MOUNTING BOX BW', '32006406', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(680, 'old BABY WARMER PCB FRAME', '32006407', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(681, 'old BABY WARMER PCB CABINET DOOR', '32006408', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(682, 'old BABY WARMER PIPE SUPPORT BRACKET', '32006409', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(683, 'old MONITOR BRACKET BASE BW', '32006411', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(684, 'BED BASE BW', '32006429', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(685, 'ventigo PCB TRAY', '32006432', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(686, 'AHEN 4500 FRONT CABINET', '32006437', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(687, 'aw gauge rim', '32006438', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(688, 'GAUGE BRACKET AW', '32006439', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(689, 'RotamitorCabinet', '32006441', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(690, 'BELLOW FLENGE', '32006443', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(691, 'Rotamitor Cabinet', '32006444', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(692, 'VOLTAGE INDICATOR BRACKET', '32006449', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(693, '25 X 75 SQUARE PIPE BW', '32006451', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(694, '25 X 25 SQUARE PIPE BW', '32006452', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(695, 'Bed Sqaure spport plate -01', '32006453', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(696, 'Bed square support plate -2', '32006454', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(697, 'Rectangular support plate', '32006455', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(698, 'BW LEGS COVER BRACKET', '32006456', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(699, 'OLD BW BASE FRAME FOR DOCUMENTS', '32006457', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(700, 'FAIL SAFE VALVE BRACKET', '32006458', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(701, 'AW NRV BRACKET', '32006459', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(702, 'SALINE HOLDER BRACKET BW', '32006460', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(703, 'AW EXHUAST VALVE BRACKET', '32006461', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(704, 'SUPPORT SIDE PLATE', '32006462', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(705, 'HEATER CABINET REINFORCEMENT PLATE BW', '32006463', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(706, 'AHEN 5000 FRONT CUT OUT', '32006464', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(707, 'AHEN 5000 TEC BAR CUT OUT', '32006465', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(708, '12\" SCREEN BRACKET AHEN 5000', '32006467', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(709, 'BACK PLATE-BELOW', '32006516', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(710, 'AW SINGLE REGULATOR BRACKET', '32006523', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(711, 'TROLLY BASE STAND', '32006541', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(712, 'VENTIGO TROLLY COLUMN DOOR', '32006542', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(713, 'ELECTRICAL PANEL BASE FRONT AHEN', '32006544', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(714, 'AHEN 4000 MAIN BASE PLATE', '32006545', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(715, 'RIGHT SIDE PLATE', '32006546', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(716, 'LEFT SIDE PLATE', '32006547', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(717, 'SUPPORT PLATE COVER', '32006548', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(718, 'BOTTOM BODY SUPPORT', '32006552', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(719, 'AHEN 4000 DRWAER CABINET COVER', '32006553', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(720, 'ahen 4000 CABINET COVER PLATE', '32006554', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(721, 'AHEN 4000 DRAWER CABINET', '32006557', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(722, 'MURPHY FRONT DRAWER COVER', '32006558', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(723, 'AHEN 4000 tray Front COVER _Cabinet_Plate', '32006559', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(724, 'AHEN 4000 PANEL Front_Cage_Plate', '32006560', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(725, 'ahen 4000 tray upper cover', '32006561', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(726, 'AHEN 4000 PCB TRAY BODY', '32006564', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(727, 'ahen 4500/500 SS TRAY', '32006565', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(728, 'AHEN 4000 BACK COVER PLATE', '32006567', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(729, 'AHEN TECH BAR SUPPORT PLATE', '32006568', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(730, 'AHEN 4000 CABINET DOOR', '32006569', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(731, 'AHEN 4000 LAMP CABINET', '32006570', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(732, 'AHEN 4000 LAMP PLATE', '32006571', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(733, 'XVS BATTERY BRACKET', '32006572', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(734, 'old MONITOR BRACKET FLENGE BW', '32006575', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(735, 'MONITOR BRACKET HOLDING PLATE BW', '32006576', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(736, 'BW MAIN FRAME BASE BRACKET', '32006578', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(737, 'BASE BRACKET PLATE BW', '32006579', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(738, 'L BRACKET HP TANK', '32006580', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(739, 'HVS 4500 FRONT CABINET', '32006584', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(740, 'AHEN 5000 YOKE BRACKET', '32006586', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(741, 'ANESTHESIA 4500/5000 BASE STAND FLENGE FRONT', '32006587', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(742, 'AHEN 4500\\5000 PCB TRAY', '32006588', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(743, 'XVS REGULATOR BRACKET', '32006590', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(744, 'CIRCLE ABSORBER FLENGE 4500/5000', '32006592', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(745, 'Ahen 4000 middle cabinet', '32006594', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(746, 'AHEN 4000 YOKE BRACKKET', '32006595', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(747, 'VENTIGO CABINET DOWN FLENCH', '32006596', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(748, 'CORNER PLATE AHEN 4000 LAMP CABINET', '32006597', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(749, 'Auto drain valve locking plate', '32006600', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(750, 'AHEN 4000 DRAWER CABINET Support_Bracket', '32006601', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(751, 'GAUGE SUPPORT PLATE', '32006602', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(752, 'MURPHY GAUGE_RING', '32006603', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(753, 'AHEN 4000 SCREEN BRACKET', '32006604', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(754, 'AHEN 4000 SODALINE PLATE', '32006605', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(755, 'EXHUAST VALVE BRACKET 1', '32006656', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(756, 'ANEASTHESIA FLOW SENSOR BRACKET', '32006658', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(757, 'EXHUAST VALVE 2', '32006659', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(758, 'AHEN 4500/5000 BASE STAND BUSH BRACKET', '32006660', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(759, 'hVS CABINET BACK DOOR', '32006662', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(760, 'MOPPET TOP PLATE BRACKET', '32006664', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(761, 'MOPPET DOWN FLENCH BRACKET', '32006665', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(762, 'MOPPET LH PLATE', '32006666', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(763, 'MOPPET RH PLATE', '32006667', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(764, 'MOPPET SMPS PLATE', '32006671', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(765, 'MOPPET HANDLE PLATE', '32006672', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(766, 'INLET FLOW SENSOR BRACKET 2', '32006674', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(767, 'GAUGE SUPPORT PLATE', '32006896', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(768, 'MURPHY BASE STAND', '32006899', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(769, 'MURPHY FRONT CORNER LEFT PLATE', '32006900', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(770, 'MURPHY FRONT CORNER RIGHT PLATE', '32006901', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(771, 'MURPHY BASE STAND BACK CORNER', '32006902', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(772, 'MURPHY BASE STAND SUPPORT PLATE', '32006903', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL);
INSERT INTO `part_master` (`id`, `part_name`, `part_no`, `part_id`, `description`, `uom`, `category`, `hsn_code`, `rate`, `status`, `gst`, `attachment_path`) VALUES
(773, 'MURPHY DRAWER CABINET SIDE PLATE', '32006904', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(774, 'MURPHY DRAWER CABINET SUPPORT PLATE', '32006907', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(775, 'MURPHY YOKE SUPPORT PLATE', '32006912', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(776, 'MURPHY YOKE BRACKET', '32006913', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(777, 'MURPHY SODALIME BRACKET', '32006919', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(778, 'MURPHY TOP LAMP CABINET', '32006920', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(779, 'MURPHY TOP LAMPCABINET CORNER PLATE', '32006921', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(780, 'MURPHY TOP CABINET UPPER PLATE', '32006922', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(781, 'MURPHY DRAWER SUPPORT PLATE', '32006924', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(782, 'MURPHY TRAY SLIDE', '32006925', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(783, 'MOPPET BACK COVER', '32006929', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(784, 'Moppet battery Bracket_32006930', '32006930', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(785, 'Blender Support plate for tank', '32006935', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(786, 'BLENDER CABINET BASE', '32006936', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(787, 'BLENDER TOP COVER', '32006937', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(788, 'BLENDER DOOR FLENGE', '32006938', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(789, 'BLENDER BACK PLATE', '32006940', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(790, 'PHOTOTHERAPY CABINET', '32006950', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(791, 'PHOTOTHERAPY CABINET SUPPORT PLATE 1', '32006953', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(792, 'PHOTOTHERAPY CABINET SUPPORT PLATE 2', '32006954', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(793, 'PHOTOTHERAPY SMPS DOOR', '32006955', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(794, 'PHOTOTHERAPY MOUNTING PLATE', '32006956', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(795, 'CIRCULAR RING SS', '32006964', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(796, 'SUPPORT PLATE RING SS', '32006969', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(797, 'AHEN NRV AND VALVE BRACKET', '32006974', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(798, 'MOPPET BACK FLENCH 32006979', '32006979', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(799, 'MURPHY TOP FRONT CABINET', '32006981', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(800, 'MURFEY TOP CABINET BACK SIDE', '32006982', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(801, 'Murphy bellow support plate for cabinet', '32006983', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(802, 'MURPHY PANEL FRONT PANEL', '32006984', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(803, 'MURFEY DRAWER CABINET BACK SIDE', '32006985', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(804, 'MURPHY SUPPORT PLATE', '32006986', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(805, 'MURPHY FRONT FLENGE PANEL', '32006987', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(806, 'MURPHY TOP CABINET BACK COVER', '32006988', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(807, 'MURPHY VALVE BRACKET', '32006989', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(808, 'Murphy tray slider bracket', '32006990', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(809, 'MURFEY SS TRAY', '32006993', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(810, 'MURPHY DRAWER', '32006994', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(811, 'MURFEY DRAWER FRONT COVER', '32006995', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(812, 'D CHAIN CONNETION BRACKET', '32006997', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(813, 'MONITOR MOUNTING RIM BRACKET', '32006998', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(814, 'MURPHY TRAY BACK COVER', '32006999', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(815, 'C Shape Support plate-2', '32007000', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(816, 'Rectangular pipe', '32007001', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(817, 'Ahen HP TANK SS BRACKET', '32007003', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(818, 'MURPHY PCB TRAY', '32007005', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(819, 'Phototherapy Square Pipe', '32007008', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(820, 'Phototherapy Bush Cover Cap', '32007009', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(821, 'Phototheraphy smps bracket', '32007010', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(822, 'MURPHY PANEL FRONT PLATE', '32007012', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(823, 'Murphy/DNA Thin vent bracket_32007013', '32007013', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(824, 'AHEN 4500 TOP CABINET BACK SIDE', '32007016', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(825, 'AHEN 4500 Yoke Bracket', '32007018', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(826, 'small drawer plate', '32007023', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(827, 'ventigo battery bracek plate', '32007024', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(828, 'pressure Gauge rim', '32007026', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(829, 'BLENDERE PIPE MOUNTING BRACKET', '32007027', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(830, 'DNA MAIN BODY', '32007028', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(831, 'DNA FRONT CABINET', '32007029', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(832, 'DNA BACK PLATE', '32007030', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(833, 'DNA PCB Plate', '32007031', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(834, 'DNA SIDE SUPPORT PLATE', '32007032', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(835, 'DNA BOTTOM SUPPORT PLATE', '32007033', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(836, 'DNA Door LH', '32007034', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(837, 'DNA Door RH', '32007035', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(838, 'DNA BATTERY SUPPORT BRACKET', '32007037', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(839, 'PRECISION REGULATOR BRACKET', '32007038', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(840, 'DRAWER PLATE', '32007042', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(841, 'NEBULA TURBINE BRACKET', '32007043', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(842, 'NEW Baby Warmer Stand Plate', '32007069', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(843, 'Baby Warmer Drawer Base Cabinet Plate', '32007070', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(844, 'Baby Warmer Drawer Back Plate', '32007071', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(845, 'Baby Warmer Drawer CVabinet', '32007072', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(846, 'Baby Warmer Front Plate', '32007073', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(847, 'Baby Warmer Stand Middle Plate', '32007076', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(848, 'Top Cabinet MBCS', '32007078', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(849, 'Baby Warmer PCB Cabinet 1', '32007079', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(850, 'Baby Warmer PCB Plate', '32007080', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(851, 'Drawer Middle Plate', '32007082', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(852, 'Baby Warmer Heater Cabinet', '32007083', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(853, 'Baby Warmer Heater Down Plate', '32007084', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(854, 'Heater Cabinet Reinforcement PLate', '32007085', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(855, 'Baby Warmer Acrylic Bed Flenge', '32007086', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(856, 'Baby Warmer C', '32007087', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(857, 'Saline Holder Mounting Plate', '32007088', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(858, 'BABY WARMER PLATE', '32007093', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(859, 'BW drawer Slider Plate', '32007094', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(860, 'NEW BW MOUNTING PLATE', '32007095', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(861, 'MONITOR BRACKET FLENGE BW', '32007096', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(862, 'MOUNTING SUPPORT PLATE', '32007097', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(863, 'Phototherapy Base Stand Plate', '32007103', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(864, 'Phototherapy Pipe Base Plate', '32007104', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(865, 'Slider Murphy', '32007105', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(866, 'Battery Reinforcement Plate', '32007106', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(867, 'AHEN 5000 PANNEL FRONT COVER', '32007107', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(868, 'AHEN 4000 Support plate for electrical pannel', '32007109', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(869, 'Reflector', '32007111', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(870, 'MOPPET 8.5 SIDE DOOR LHS', '32007231', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(871, 'MOPPET 8.5 XL SIDE DOOR {RSH}', '32007232', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(872, 'MOPPET 8.5 SIDE RHS', '32007233', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(873, 'MOPPET 8.5 BACK CABINET BODY', '32007235', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(874, 'MOPPET 8 INCH SCREEN BRACKET _32007236', '32007236', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(875, 'MOPPET 8.5 TROLLY BASE', '32007239', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(876, 'MOPPET 8.5 TROLLY PLATE', '32007240', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(877, 'RECHTANGLE PIPE 75X75', '32007241', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(878, '48 OD Trolly circular pipe', '32007242', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(879, 'MOPPET 8.5 TROLLY MOUNITNG PLATE', '32007243', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(880, 'NEW BABY WARMER PART', '32007254', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(881, 'PCB TRAY SILIDER', '32007255', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(882, 'Compressor base stand MBCS', '32007264', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(883, 'Compressor base stand corner flange MBCS', '32007265', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(884, 'Compressor cabinet MBCS', '32007266', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(885, 'Compressor base cabinet MBCS', '32007267', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(886, 'Drawer MBCS', '32007268', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(887, 'Drawer front cover MBCS', '32007269', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(888, 'Base stand mounting leg MBCS back', '32007270', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(889, 'Base stand mounting leg MBCS front', '32007271', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(890, 'Electrical panel side cover MBCS', '32007273', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(891, 'Electrical panel base front plate MBCS', '32007274', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(892, 'Electrical panel bottom mounting cabinet MBCS', '32007275', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(893, 'Electrical panel upper mounting cabinet MBCS', '32007276', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(894, 'Rotameter cabinet', '32007277', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(895, 'NEW DNA FRONT PLATE', '32007279', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(896, 'NEW DNA SIDE FLENGE', '32007280', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(897, 'NEW DNA BACK COVER', '32007281', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(898, 'CIRCLE ABOSORBER', '32007282', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(899, 'CIRCLE ABSORBER PART', '32007283', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(900, 'Electrical tray slider mounting Bracket MBCS', '32007284', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(901, 'Electrical panel back door MBCS', '32007285', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(902, 'Yoke mounting Bracket MBCS', '32007287', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(903, 'Top cabinet back door MBCS', '32007288', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(904, 'Tecbar cabinet MBCS', '32007289', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(905, 'CIRCLE ABSORBER 3', '32007290', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(906, 'Tecbar cabinet back door MBCS', '32007291', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(907, 'Regular mounting Bracket MBCS', '32007292', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(908, 'Electrical panel mounting SS plate MBCS', '32007293', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(909, 'Base stand foot rest corner flange MBCS', '32007294', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(910, 'Gauge bracket MBCS', '32007295', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(911, '52005082', '32007307', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(912, 'NEW MODEL52005083', '32007314', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(913, 'NEW52005083', '32007315', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(914, 'NEW 52005083', '32007319', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(915, 'NEW MODEL 52005083', '32007323', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(916, 'NEW 52005083', '32007327', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(917, 'MODEL 52005083', '32007328', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(918, 'MODEL52005083', '32007329', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(919, 'TURBINE BASE MODEL', '32007330', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(920, '52005083 E SLIDER BRACKET', '32007332', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(921, 'NEW MODEL COMPRESSOR BASE 52005083', '32007335', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(922, 'TURBINE BASE MODEL 1', '32007336', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(923, 'TURBINE BASE MODEL 2', '32007337', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(924, 'TURBINE BASE MODEL 3', '32007338', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(925, 'TURBINE BASE MODEL 4', '32007340', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(926, 'TURBINE BASE MODEL 5', '32007341', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(927, 'NEW MODEL 6', '32007345', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(928, 'NEW MODEL 52005083 7', '32007351', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(929, 'NEW MODEL 52005083 PART 8', '32007352', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(930, 'NEW MODEL BACK COVER 52005083', '32007353', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(931, 'NEW MODEL 52005083 9', '32007354', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(932, 'NEW MODEL 52005083 2', '32007356', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(933, 'NEW MODEL 52005083 1', '32007357', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(934, 'NEW MODEL 52005083 0', '32007358', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(935, 'NEW MODEL PART 52005083', '32007359', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(936, 'NEW MODEL 52005083 TURBINE', '32007360', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(937, 'NEW MODEL 52005083 1 TURBINE', '32007361', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(938, 'NEW MODEL 52005083 2 TURBINE', '32007362', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(939, 'NEW MODEL 52005083 3', '32007363', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(940, 'NEW MODEL 52005083 4', '32007364', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(941, 'NEW MODEL 52005083 5', '32007365', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(942, 'NEW MODEL 52005083 6', '32007366', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(943, 'NEW MODEL 52005083 8', '32007367', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(944, 'NEW MODEL 52005083 10', '32007368', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(945, 'NEW MODEL 52005083', '32007369', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(946, 'moppet 6.5 side plate support plate', '32007391', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(947, 'INVERTOR CABINET', '32007408', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(948, 'INVERTOR PART', '32007409', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(949, 'INVERTOR DOOR', '32007410', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(950, 'INVERTOR DOOR 2', '32007411', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(951, 'ICVS SMPS BRACKET', '32007552', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(952, 'Compressor back door MBCS', '32007586', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(953, 'COMPRESSOR CABINET BACK DOOR_44005001', '44005001', '44', 'Asm_Manf', 'Nos', 'Assembly', '123', 1.00, 'active', '18', 'uploads/parts/44005001_1769748068.pdf'),
(954, 'HVS HANDLE ASSEMBLY_44005033', '44005033', '44', 'Asm_Manf', 'Nos', 'Finished Good', '123', 1.00, 'active', '18', 'uploads/parts/44005033_1769836594.pdf'),
(955, 'VENT CABINET COMPRESSOR', '44005037', '44', 'Asm_Manf', 'Nos', 'Finished Good', '123', 1.00, 'active', '18', 'uploads/parts/44005037_1769836636.pdf'),
(956, 'Monitor Cabinet Assembly', '44005040', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(957, 'HVS TOP CAIBINET ASSEMBLY', '44005046', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(958, 'HVS 4500 cabinet Assembly', '44005047', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(959, 'HVS COLUMN CAIBINET ASSEMBLY', '44005048', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(960, 'XVS TOP CABINET VENTILATOR ASSEMBLY', '44005051', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(961, 'XVS PCB TRAY ASSEMBLY', '44005054', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(962, 'HVS 4500 PCB TRAY ASSEMBLY', '44005055', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(963, 'HVS PCB TRAY ASSEMBLY', '44005056', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(964, 'HVS 2500 BASE STAND ASSEMBLY', '44005064', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(965, 'NEBULA COLUMN ASSEMBLY (MFG)', '44005065', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(966, 'NEBULA BACK COVER ASSEMBLY (MFG)', '44005066', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(967, 'NEBULA TOP CABINET ASSEMBLY (TURBINE)', '44005067', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(968, 'CABINET BACK DOOR ASSEMBLY (MFG', '44005068', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(969, 'NEBULA CABINET BACK DOOR ASSEMBLY (MFG', '44005070', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(970, 'VENTIGO CABINET ASSEMBLY', '44005071', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(971, 'VENTIGO BACK COVER', '44005072', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(972, 'MOPPET 6.5 CABINET ASSEMBLY', '44005079', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(973, 'BUBBLECPAP PIPE ASSEMBLY', '44005081', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(974, 'Bubblecpap handle assembly', '44005082', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(975, 'HUMIDIFIER BRACKET MS', '44005083', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(976, 'HP Tank Assembly 290 mm', '44005090', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(977, 'HVS 4500 TOP CABINET BACK COVER', '44005092', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(978, 'HVS COLUMN BODY BACK COVER', '44005097', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(979, 'HVS 10 INCH SCREEN BRCAKET ASSEMBLY', '44005098', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(980, '10 INCH DRIVER BRACKET ASSEMBLY (MFG)', '44005099', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(981, 'MEDICAL ARM BRACKET ASSEMBLY', '44005102', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(982, 'HVS 2500 COLUMN CABINET ASSEMBLY', '44005105', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(983, 'HVS 2500 COLUMN/ TANK DOOR ASSEMBLY', '44005106', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(984, 'VENTIGO TROLLY ASSEMBLY', '44005110', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(985, 'AHEN 4500/5000 COMPRESSOR CABINET ASSEMBLY', '44005119', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(986, 'AHEN 4500/5000 LAMP CABINET', '44005125', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(987, 'AHEN 4500 CABINET', '44005130', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(988, 'AHEN 4500 COMPRESSOR BACK COVER ASSEMBLY', '44005134', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(989, 'BELLOW BRACKET ASSEMBLY', '44005135', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(990, 'AHEN 4500/5000 PCB TRAY', '44005136', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(991, 'AHEN SMALL DRAWER ASSEMBLY', '44005210', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(992, 'AHEN 4500 /5000 ELECTRICAL PANNEL ASSEMBLY', '44005214', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(993, 'MURPHY ELECTRICAL PANNEL CABINET ASSEMBLY', '44005506', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(994, 'AHEN 4500/5000 TRAY COVER', '44005540', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(995, 'ANEASTHESIA HP TANK', '44005547', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(996, 'AHEN BIGG DRAWER ASSEMBLY', '44005549', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(997, 'AHEN 4500/5000 CABINET BACK COVER', '44005556', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(998, 'AHEN 5000 MAIN CABINET', '44005645', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(999, 'AHEN 5000 SCREEN BRACKET', '44005646', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1000, 'VENTIGO TROLLY BASE STAND ASSEMBLY', '44005692', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1001, 'AHEN 4000 BASE STAND ASSEMBLY', '44005696', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1002, 'AHEN 4000 DRAWER CABINET ASSEMBLY', '44005697', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1003, 'AHEN 4000 DRAWER ASSEMBLY', '44005699', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1004, 'AHEN 4000 ELECTRICAL PANEL ASSEMBLY', '44005702', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1005, 'AHEN 4000 TRAY ASSEMBLY', '44005703', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1006, 'AHEN 4000 TRAY BACK COVER', '44005704', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1007, 'AHEN 4000 CABINET ASSEMBLY', '44005705', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1008, 'AHEN 4000 CABINET BACK COVER', '44005706', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1009, 'AHEN 4000 LAMP CABINET ASSEMBLY', '44005707', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1010, 'Vent Base Stand Compressor', '44005711', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1011, 'AHEN GAUGE RIM ASSEMBLY (ENGG)', '44005717', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1012, 'HIGH PRESSURE LINE ASSEMBLY (ENGG)', '44005731', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1013, 'CPU BRACKET PLATE ASSEMBLY', '44005733', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1014, 'HUMIDIFIER CABINET ASSEMBLY', '44005736', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1015, 'HVS DC FAN COVER ASSEMBLY', '44005742', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1016, 'MOPPET TURBINE PLATE ASSEMBLY', '44005743', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1017, 'MOPPET CPU PLATE ASSEMBLY', '44005750', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1018, 'MOPPET SMPS PLATE ASSEMBLY', '44005751', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1019, 'MOPPET BATTERY MOUNTING PLATE', '44005752', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1020, 'COLUM DOOR XVS', '44005753', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1021, 'XVS LP TANK ASSEMBLY', '44005756', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1022, 'SLIDER ASSEMBLY', '44005762', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1023, 'MURPHY MAIN BASE STAND ASSEMBLY', '44005766', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1024, 'MURPHY TOP CABINET ASSEMBLY', '44005772', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1025, 'Blender Tank assembly', '44005774', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1026, 'Blender CABINET assembly', '44005775', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1027, 'PHOTOTHERAPY CABINET ASSEMBLY', '44005782', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1028, 'MOPPET BACK COVER', '44005791', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1029, 'VENTIGO PCB PLATE', '44005794', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1030, 'MURPHY TOPCABINET MAIN ASSEMBLY', '44005800', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1031, 'MURPHY DRAWER CABINET ASSEMBLY', '44005804', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1032, 'MURPHY BACK COVER CABINET ASSEMBLY', '44005806', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1033, 'MURPHY DRAWER ASSMBLY', '44005809', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1034, 'XVS DC FAN DOOR', '44005810', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1035, 'Cobra Assembly', '44005811', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1036, 'VENTOGO SS NET', '44005812', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1037, 'VENTIGO SMPS PLATE', '44005813', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1038, 'MURPHY TRAY BACK PALTE STUD ASSEMBLY', '44005814', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1039, 'MURPHY PCB TRAY ASSEMBLY', '44005817', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1040, 'Baby warmer bed', '44005818', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1041, 'Phototherapy pipe frame', '44005824', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1042, 'MURPHY LAMP CABINET PLATE ASSEMBLY', '44005825', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1043, 'CIRCULAR RING ASSEMBLY', '44005826', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1044, 'PHOTOTHERAPHY FAN/SMPS PLATE ASSEMBLY', '44005827', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1045, 'VENTIGO BATTERY ASSEMBLY', '44005831', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1046, 'PRESSURE GAUGE RIM ASSEMBLY', '44005832', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1047, 'BLENDER BACK PLATE ASSEMBLY', '44005833', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1048, 'DNA Main Cabinet', '44005836', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1049, 'DNA Back Door', '44005837', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1050, 'NEW BABY WARMER DRAWER SIDE & BACK SUPPORT BRACKET ASSEMBLY', '44005846', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1051, 'Baby Warmer Base Stand Assembly New', '44005847', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1052, 'Drawer Assembly BW', '44005848', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1053, 'NEW MAIN PCB STAND ASSEMBLY BW', '44005851', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1054, 'NEW BABY WARMER BACK PLATE', '44005852', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1055, 'NEW BW HEATER ND LIGHT SUPPORT ASSEMBLY', '44005853', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1056, 'NEW BW MOUNTING SUPPORT PLATE ASSEMBLY', '44005855', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1057, 'phototheraphy pipe assembly', '44005857', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1058, 'NEW PHOTOTHERAPHY BASE STAND', '44005858', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1059, 'Baby Warmer Saline holder Assembly', '44005859', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1060, 'Outdoor Unit Cabinet', '44005885', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1061, 'MOPPET TROLY BASE STAND', '44005888', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1062, 'without compressor bubblecpap pipe assembly', '44005889', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1063, 'MOPPET 8.5 SIDE DOOR (MFG)', '44005899', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1064, 'MOPPET 8.5 SIDE DOOR(MFG)', '44005900', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1065, 'MOPPET 8.5 CABINATE ASSEMBLY(MFG)', '44005902', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1066, 'VOLTAGE INDICATOR RIM ASSEMBLY', '44005903', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1067, 'AUTO DRAIN BRACKET ASSEMBLY', '44005904', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1068, 'XVS MONITOR RIM ASSEMBLY (MFG)', '44005905', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1069, 'LP TANK HVSASSEMBLY (MFG)', '44005907', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1070, 'AW GAUGE ASSEMBLY (MFG)', '44005908', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1071, 'MOPPET 8.5 STAND ASSEMBLY', '44005909', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1072, 'MOPPET TROLLY STAND ASSEMBLY (ENGG)', '44005910', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1073, 'VENTIGO SCREEN BRACKET ASSEMBLY', '44005911', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1074, 'NEW DNA CABINET BACK COVER ASSEMBLY', '44005936', '44', 'Asm_Manf', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1084, 'Compressor Elbow 1/4\"_62005003', '62005003', '62', 'PNEUMATIC PARTS', 'NOS', 'Finished Good', '8414', 6200.00, 'active', '18', NULL),
(1085, 'Weld nut M10_72005002', '72005002', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1086, 'STUD M8X10 MS_72005003', '72005003', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1087, 'Weld Nut M4_72005004', '72005004', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1088, 'STUD M4X30 72005005', '72005005', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1089, 'Nut M6 SS_72005006', '72005006', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1090, 'Washer M8 SS PLAN_72005008', '72005008', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1091, 'Nut M8 SS_72005009', '72005009', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1092, 'Nut M4 SS_72005012', '72005012', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1093, 'STUD M4X25 MS_72005014', '72005014', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1094, 'SCREW BUTTON HEAD M8x40 SS ALLEN_72005017', '72005017', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1095, 'Nut M10 SS_72005019', '72005019', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1096, 'Washer M10 Plan_72005020', '72005020', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1097, 'SCREW CSK M4X6_72005031', '72005031', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1098, 'STUD M3X10 MS_72005032', '72005032', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1099, 'Nut M3 SS_72005033', '72005033', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1100, 'Washer M4 Plan SS_72005034', '72005034', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1101, 'STUD M3X20 MS_72005041', '72005041', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1102, 'STUD M4X20 MS_72005044', '72005044', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1103, 'Wheel W/O Lock 4\"_72005056', '72005056', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1104, 'WHEEL W/O LOCK 3\"_72005057', '72005057', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1105, 'WHEEL LOCK 3\"_72005058', '72005058', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1106, 'Wheel Lock 4\"_72005060', '72005060', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1107, 'Washer M6 Plan SS304_72005061', '72005061', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1108, 'SCREW BUTTON HEAD M4X20_72005064', '72005064', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1109, 'SCREW BUTTON HEAD M5x6_72005065', '72005065', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1110, 'SCREW BUTTON HEAD M5X16_72005067', '72005067', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1111, 'Nut M5 SS_72005072', '72005072', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1112, 'GRUB SCREW M4X8 MS_72005077', '72005077', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1113, 'Plan Washer M12_72005080', '72005080', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1114, 'SCREW SS ALLEN CSK M6X25_72005084', '72005084', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1115, 'SCREW SS ALLEN CSK M4x10_72005086', '72005086', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1116, 'SCREW CSK M4x16_72005087', '72005087', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1117, 'SCREW CSK M4x8_72005088', '72005088', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1118, 'ALEEN SCREW SCK M8x15_72005093', '72005093', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1119, 'SCREW CSK M6X16_72005095', '72005095', '62', 'Fastener', 'NOS', 'Brought Out', '8302', 1.00, 'active', '18', NULL),
(1120, 'Spring Washer M8_72005100', '72005100', '62', 'Fastener', 'NOS', 'Brought Out', '8302', 1.00, 'active', '18', NULL),
(1121, 'Spring Washer M6 SS304_72005101', '72005101', '62', 'Fastener', 'NOS', 'Brought Out', '8302', 1.00, 'active', '18', NULL),
(1122, 'SCREW BUTTON HEAD M10X30_72005107', '72005107', '62', 'Fastener', 'NOS', 'Brought Out', '8302', 1.00, 'active', '18', NULL),
(1123, 'STUD M4X60 MS_72005111', '72005111', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1124, 'SCREW CSK M3X20_72005113', '72005113', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1125, 'SCREW BUTTON HEAD M10X16 SS ALLEN_72005114', '72005114', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1126, 'SCREW BUTTON HEAD M10x25_72005115', '72005115', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1127, 'Screw button Head M5x10_72005120', '72005120', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1128, 'SCREW CSK M6X70_72005145', '72005145', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1129, 'Stud M3 X 35MS_72005150', '72005150', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1130, 'Stud M4 X 35 MS_72005151', '72005151', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1131, 'Allen bolt M8X48_72005152', '72005152', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1132, 'BUTTON HEAD BOLT M8X25_72005162', '72005162', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1133, 'ALLEN BOLT M10x25 SS_72005163', '72005163', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1134, 'WELD NUT M8_72005165', '72005165', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1135, 'OXYGEN FITTING BRASS 3/8_72005168', '72005168', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1136, 'STUD M3X40 72005169', '72005169', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1137, 'BABY WARMER Chair wheel 3/8(Ivery)_72005172', '72005172', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1138, 'Star knob M10*50_72005173', '72005173', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1139, 'STUD M4X40 SS_72005177', '72005177', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1140, 'STUD M8X15 MS_72005178', '72005178', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1141, 'SLIDER BIG 10\"_72005180', '72005180', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1142, 'Weld Nut M12_72005184', '72005184', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1143, 'Screw Button Head M8X30 SS_72005191', '72005191', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1144, 'Stud M6x15 MS_72005192', '72005192', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1145, 'M6X50 CSK SCREW_72005207', '72005207', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1146, 'HOSE CLIP 8MM OXYGEN PIPE CLAMP_72005208', '72005208', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL);
INSERT INTO `part_master` (`id`, `part_name`, `part_no`, `part_id`, `description`, `uom`, `category`, `hsn_code`, `rate`, `status`, `gst`, `attachment_path`) VALUES
(1147, 'SS Fitting Elbow 6mmX6mm_72005209', '72005209', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1148, 'MS SCREW M3X 35_72005210', '72005210', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1149, 'M3 X 16 STAR SCREW_72005298', '72005298', '62', 'Fastener', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1150, 'SSR-25DD 5-60V Solid State Relay', '82005009', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1151, '8 PIN Female RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005012', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1152, 'EMI-20 250VAC 10AÂ ', '82005031', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1153, '1 MM Polycab Cable Blue', '82005034', '82', 'Electrical_C.P', 'MTR', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1154, 'BIG SPIRAL', '82005038', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1155, 'PKB 1P 60 DEGREE 6PINS', '82005081', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1156, '3 Pin White Relimate Straight Male â€“ 2.54mm', '82005088', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1157, '10K - 1W : Precision Potentiometer (RW-1) Single Turn - Shaft:19mm', '82005092', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1158, 'FAN NET', '82005096', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1159, 'MOLEX 3 PIN CONNECTOR Male', '82005097', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1160, 'MOLEX 2 PIN CONNECTOR Female', '82005098', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1161, 'MOLEX 3 PIN CONNECTOR Female', '82005099', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1162, 'VOLTAGE INDICATOR', '82005105', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1163, '8 PIN CIRCULOR F', '82005150', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1164, 'M4 BROWN WASHER', '82005154', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1165, '8 PIN CIRCULOR M', '82005198', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1166, '10 PIN Female RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005211', '82', 'Electrical_C.P', 'Nos', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1167, '2 Pin BLACK Relimate Straight Male â€“ 2.54mm', '82005213', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1168, 'Battery 25.9 5 AH (', '82005214', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1169, '2 Pin White Relimate Straight Male â€“ 2.54mm', '82005215', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1170, '3 PIN TOP 6A', '82005255', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1171, '20 pin Connector', '82005257', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1172, 'Polycab 1.5 Sqmm 3 Core', '82005278', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1173, 'Short Flat Head Metal Push Switch16 MM', '82005307', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1174, '5 MM WHITE SLEEVE', '82005330', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1175, '7 PIN BLACK Relimate Straight Male â€“ 2.54mm', '82005343', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1176, '4 Pin White Relimate Straight Male â€“ 2.54mm', '82005384', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1177, '4 Pin M Right Angle Header', '82005385', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1178, '4 Pin F Right Angle Header', '82005386', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1179, '6 PIN Female RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005387', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1180, '2 PIN MOLEX CONNECTOR', '82005389', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1181, '4 PIN MOLEX CONNECTOR', '82005392', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1182, '6 PIN MALE RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005393', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1183, '10 PIN MALE RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005395', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1184, '2 PIN MALE RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005396', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1185, 'SMALL SPIRAL', '82005421', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1186, '1 MM Polycab Cable GREEN', '82005425', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1187, 'TEFLON BLUE', '82005431', '82', 'Electrical_C.P', 'Mtr', 'Brought Out', '731811', 850.00, 'active', '18', NULL),
(1188, 'TEFLON WHITE', '82005432', '82', 'Electrical_C.P', 'Mtr', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1189, 'TEFLON RED', '82005433', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1190, '4 PIN RELIMATE CONNECTOR Female', '82005440', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1191, '3 PIN RELIMATE CONNECTOR Female', '82005441', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1192, '2 PIN RELIMATE CONNECTOR (Black) Male', '82005442', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1193, '6 Pin White Relimate Straight Male â€“ 2.54mm', '82005445', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1194, '7 PIN RELIMATE CONNECTOR (Black) Female', '82005446', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1195, '2 PIN RELIMATE CONNECTOR Female', '82005447', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1196, 'Big Red Switch', '82005468', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1197, 'MOLEX 4 PIN CONNECTOR Male', '82005469', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1198, 'LOCK CONNECTOR MX3537', '82005479', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1199, '16 A AC SOCKET', '82005494', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1200, 'AC Socket with FUSE Switch', '82005495', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1201, 'Without Lock', '82005496', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1202, '10 A FUSE GLASS 6X30MM Big_', '82005498', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1203, 'Fuse Holder Red', '82005499', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1204, 'Fuse Holder Black', '82005500', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1205, 'TRANSPERNT FUSE 5A', '82005501', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '731811', 1.00, 'active', '18', NULL),
(1206, 'AC FAN 230 V', '82005502', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1207, 'SMPS 24V 14.6 A', '82005504', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1208, 'SMPS 24V 6.5 A', '82005505', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1209, 'Battery 25.6V 6 AH (', '82005506', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 2900.00, 'active', '18', NULL),
(1210, 'Nc switch', '82005509', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1211, 'No Switch', '82005510', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1212, 'Push Button', '82005511', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1213, '2 Position', '82005512', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1214, '3 Position', '82005513', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1215, 'Generic Female Spade', '82005515', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1216, 'M3x10mm Male To Female Nylon Hex Spacer', '82005516', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1217, 'Acrylic Red', '82005517', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1218, 'Acrylic Orange', '82005518', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1219, 'Acrylic Green', '82005519', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1220, 'M3 BROWN WASHER', '82005520', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1221, '0.5 MM WHITE LUG', '82005521', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1222, '1.0 MM WHITE LUG', '82005522', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1223, '1.5 MM WHITE LUG', '82005523', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1224, '1.5 MM BLUE LUG', '82005524', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1225, '0.5 MM BLUE LUG', '82005525', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1226, '1.0 MM BLUE LUG', '82005526', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1227, '1.0 MM RED LUG', '82005528', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1228, '1.5 MM RED LUG', '82005529', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1229, '2 MM BLACK SLEEVE', '82005534', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1230, '1.0 BLACK SLEEVE', '82005535', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1231, '4 MM BLACK SLEEVE', '82005536', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1232, '19 Volt Adapter', '82005537', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1233, '12 Volt Adapter', '82005538', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1234, '0.5 MM RED LUG', '82005540', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1235, 'AEROFLEX DC COIL', '82005544', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1236, 'RING LUG SS 1.5', '82005547', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1237, '1 MM Polycab Cable Brown', '82005550', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1238, 'POT KNOB', '82005559', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1239, 'High Temperature NTC 100K Thermistor with 1 Meter Cable', '82005560', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1240, 'GLCD 20 PIN TARMINAL FEMALE', '82005561', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1241, '2/3 PIN JST FemaleTerminal', '82005562', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1242, '2/3 PIN JST Male Terminal', '82005563', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1243, '2/4 PIN CPU TARMINAL Female', '82005564', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1244, '2/7 PIN RELIMATE TARMINAL', '82005565', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1245, '2/3/4/5/6 PIN TARMINAL CONNECTOR Female', '82005566', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1246, '5 PIN RELIMATE CONNECTOR Female', '82005567', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1247, 'MOLEX 2/3/4 PIN FemaleTerminal', '82005568', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1248, 'MOLEX 2/3/4 PIN Male Terminal', '82005569', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1249, 'Uninsulated Female Spade', '82005571', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1250, 'MCB 10 A', '82005572', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1251, 'FLASHING PIN Female', '82005573', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1252, 'BURG STRIP 40X1 B/S ST F PITC', '82005574', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1253, 'GLUE STICK', '82005575', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1254, 'PKB 1P 60 DEGREE 4 PINS', '82005576', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1255, '5 Pin White Relimate Straight Male â€“ 2.54mm', '82005586', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1256, '10 PIN PUSH CONNECTOR MALE', '82005588', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1257, '2 Pin JST-SM 2518 Male', '82005591', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1258, '2 Pin JST-SM 2517 Female', '82005592', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1259, '3 Pin JST-SM 2518 Male', '82005593', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1260, '3 Pin JST-SM 2517 Female', '82005594', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1261, '2 PIN CPU CONNECTOR Female', '82005601', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1262, '4 PIN CPU CONNECTOR Female', '82005602', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1263, 'S P S T Round Rocker 6 A SWITCH GREEN', '82005604', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1264, '8 PIN MALE FEMALE RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005613', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1265, 'Green 10-24V 8mm LED Metal Indicator Light with 15', '82005614', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1266, 'Socke', '82005623', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1267, 'Acrylic White', '82005624', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1268, 'Line FUSE Holder', '82005640', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1269, 'TRANSPERNT FUSE 8A', '82005641', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1270, 'Rubber Grommet', '82005642', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1271, 'AC Coil', '82005643', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1272, 'TRANSPERNT FUSE BIG 8A', '82005644', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1273, 'MOLEX 2 PIN CONNECTOR Male', '82005645', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1274, 'MOLEX 4 PIN CONNECTOR Female', '82005646', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1275, '10 PIN PUSH CONNECTOR female', '82005647', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1276, '2 PIN Female RIGHT ANGLE XY2500 SERIES CONNECTOR', '82005648', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1277, 'GLCD 20 PIN CONNECTOR Female', '82005649', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1278, '0.5 BLACK SLEEVE', '82005650', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1279, '3 MM BLACK SLEEVE', '82005651', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1280, '1.5 BLACK SLEEVE', '82005652', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1281, '6 PIN RELIMATE CONNECTOR Female', '82005653', '82', 'Electrical_C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1282, 'OXY AND PRE- HARNESS MOPPET', '83005001', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1283, 'FLOW SESNOR HARNESS MOPPET', '83005002', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1284, 'AHEN AC SOCKET WITH FUSE HARNESS', '83005003', '83', 'Asm_WH', 'Nos', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1285, 'ANESTHESIA 19 and 12V cable', '83005004', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1286, 'MOPPET SWITCH HARNESS SET', '83005014', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1287, 'VENTIGO FLOW SESNOR HARNESS', '83005025', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1288, 'AHEN ETCO2 HARNESS', '83005026', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1289, 'AC POWER SUPPLYCABLE SET ASSEMBLY', '83005029', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1290, 'AC COMMONHARNESS', '83005030', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1291, 'COMPRESSOR EXIT HARNESS', '83005031', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1292, 'HUMIDIFIER SMPS TO PCB 24 V HARNESS', '83005032', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1293, 'VOLTAGE INDICATOEHARNESS', '83005034', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1294, 'DRAIN VALVE HARNESS', '83005035', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1295, 'PRESSURE SWITCHHARNESS', '83005036', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1296, 'COMPRESSOR TOSMPS CABLE', '83005037', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1297, 'POWER CORD ASSEMBLY', '83005039', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1298, 'DC COIL HARNESS', '83005040', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1299, 'KEYPAD HARNESS', '83005041', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1300, 'VENT DC SWITCH HARNESS', '83005042', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1301, 'HUMIDIFIER THRMISTOR WIRING HARNESS', '83005043', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1302, 'GLCD HARNESS', '83005044', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1303, 'FLOW SENSOR CABLE HARNESS', '83005045', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1304, '10K POT CABLEHARNESS', '83005046', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1305, 'LED HARNESS', '83005047', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1306, 'TTL HARNESS', '83005048', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1307, 'PRESSURE & OXYGENSENSOR HARNESS', '83005049', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1308, 'VENT DRIVER HARNESS', '83005050', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1309, '10 Pin Power Cable set harness', '83005053', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1310, 'HUMIDIFIER HEATING PROBE HARNESS', '83005054', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1311, '12 V Fan Backdoor Harness', '83005058', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1312, 'ANESTHESIA TTL HARNESS', '83005061', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1313, 'TURBINE POWER CAPACITOR HARNESS', '83005062', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1314, 'TURBINE DRIVER HARNESS', '83005063', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1315, 'TURBINE LED HARNESS', '83005064', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1316, 'TTL HARNESS TURBINE', '83005065', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1317, 'TURBINE HARNESS', '83005067', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1318, 'FLOW SENSOR HARNESS ANESTHESIA', '83005068', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1319, 'DRIVER HARNESS ANESTHESIA', '83005069', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1320, 'PRESSURE & OXYGEN HARNESS ANESTHEISA', '83005070', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1321, 'LED LIGHTS HARNESS AHEN 4000', '83005072', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1322, 'KEYPAD HARNESS ANESTHESIA', '83005076', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1323, 'DC SWITCH HARNESS ANESTHESIA', '83005077', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1324, '12 V & 24 V FAN HARNES', '83005078', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1325, 'BW AC 230 V HEATER WIRE', '83005080', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1326, 'BW MAIN AC SUPPLY', '83005081', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1327, 'XVS MONITOR WIRING HARNESS', '83005083', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1328, 'XVS CABINET TO MONITOR WIRING HARNESS', '83005084', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1329, 'XVS CABINET WIRING HARNESS', '83005085', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1330, 'HUMIDI LED HARNESS', '83005087', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1331, 'HUMIDIFIER HEATING WIRING HARNESS', '83005088', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1332, 'HUMIDIFIEER DC SWITCH WIRING HARNESS', '83005089', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1333, 'HUMIDIFIER KEYPAD HARNESS', '83005090', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1334, 'HUMIDIFIER COMMON HARNESS', '83005091', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1335, 'TURBINE 10 K POT HARNESS', '83005092', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1336, 'TURBINE RESET BUTTON', '83005093', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1337, 'Moppet 12 V supply cable', '83005094', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1338, 'TURBINE DC SWITCH HARNESS', '83005095', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1339, 'TURBINE SMPS MAIN SUPPLY', '83005096', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1340, 'HUMIDIFIER POWER CORD', '83005101', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1341, 'FUSE HOLDER TO BATTER', '83005103', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1342, 'MOPPET BATTERY AND FUSE HOLDER HARNESS', '83005104', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1343, 'MOPPET POWER CORD HARNESS', '83005107', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1344, 'DC FUSE WIRING HARNESS', '83005110', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1345, 'Murphy-Arya TTl Harness', '83005111', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1346, 'MOPPET FAN HARNESS', '83005112', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1347, 'SMPS AC 230 V', '83005113', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1348, 'Green lamp harness', '83005114', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1349, 'MOPPET RELAY HARNESS', '83005115', '83', 'Asm_WH', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1350, 'SMPS LM350-12B24 14.6A BIG', '84005002', '84', 'Electrical F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1351, '8mm Push Button Non-Latching Switch Silver Color Waterproof', '84005008', '84', 'Electrical F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1352, 'LAMP 3 WATT (JAGUAR)', '84005020', '84', 'Electrical F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1353, '12 V FAN DC', '84005024', '84', 'Electrical F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1354, 'Soldering Paste (NP 103 ROHS)', '84005034', '84', 'Brought Out', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1355, 'LED PCB VENT (ASSEMBLE)', '91005003', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1356, 'KEYPAD PCB (ASSEMBLE)', '91005005', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1357, 'HMD CONNECTOR PCB (ASSEMBLY)', '91005009', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1358, 'HUMIDIFIER LED PCB (ASSEMBLY)', '91005010', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1359, 'PRESURE SENSOR PCB (ASSEMBLE)', '91005013', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1360, 'MOPPET Power Supply PCB (ASSEMBLE)', '91005015', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1361, 'INFANT WARMER CONTROLLER PCB', '91005016', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1362, 'PHOTOTHERAPY PCB', '91005017', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1363, 'TURBINE DRIVER', '91005018', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1364, 'Phototherapy Controller (only PCB)', '91005021', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1365, 'SMD VENT MAIN PCB ASSEMBLE', '91005022', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1366, 'PCB HUMIDIFIER (ASSEMBLE)', '91005023', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1367, 'SMD POWER PCB ASSEMBLE', '91005024', '91', 'Asm_PCB', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1368, 'BUZZER 5 V', '92005007', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1369, 'GLCD 128*64 Blue', '92005011', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1370, '0.1uf', '92005023', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1371, '22 PF', '92005024', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1372, '470uf 35V', '92005025', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1373, '10UF 25V TANTULUM \'B\' CASE', '92005026', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1374, 'POT 10 K SMD', '92005027', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1375, '100 K SMD', '92005028', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1376, '10 UH SMD', '92005029', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1377, 'LED_1206 BLUE SMD', '92005031', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1378, 'BC817 SMD', '92005032', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1379, 'SS 54 SMD BIG', '92005033', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1380, '1k SMD', '92005034', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1381, '2k SMD', '92005035', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1382, '3k SMD', '92005037', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1383, '4.7k SMD', '92005039', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1384, 'LM741C SMD', '92005043', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1385, 'MIC29302 SMD', '92005044', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1386, 'Atmega 64 SMD', '92005045', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1387, 'L293DD SMD', '92005046', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1388, 'BURG3(40*1)STFFMALE 11MM 2-54_CONFLY)', '92005061', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1389, '10K POT(BOURNS)', '92005073', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1390, 'PRESURE SENSOR PCB (BLANK)', '92005080', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1391, 'TTL', '92005111', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1392, '16 MHZ CRYSTAL SMD', '92005169', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1393, '24 V RALLY', '92005276', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1394, 'SS 34 SMD', '92005278', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1395, 'VENT LED PCB (BLANK)', '92005303', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1396, 'HMD conn pcb (BLANK)', '92005305', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1397, 'HUMIDIFIER LED PCB (BLANK)', '92005306', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1398, 'KEYPAD PCB 7 PIN (ASSEMBLE)', '92005307', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1399, '470UH', '92005313', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1400, 'ZENER DIODE 4.7V SMD', '92005325', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1401, 'AEB106M2WR44B-F 1000uf 50V', '92005335', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1402, '10k SMD', '92005341', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1403, '100k SMD', '92005342', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1404, '100ohm SMD', '92005343', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1405, '300ohm SMD', '92005344', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1406, 'LED_1206 GREEN SMD', '92005346', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1407, 'LED_1206 ORANGE SMD', '92005347', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1408, 'LED_1206 RED SMD', '92005348', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1409, '0.1UF CAPACITOR', '92005350', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1410, '100 OHM RESISTOR', '92005351', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1411, 'LM2596', '92005353', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1412, 'RES_1206 OR,', '92005360', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1413, 'MOPPET Power Supply (BLANK)', '92005385', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1414, 'ICL7660', '92005388', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1415, 'ORANGE LED_3 MM (DIP)', '92005405', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1416, 'GREEN LED_3 MM (DIP)', '92005406', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1417, 'RED LED_3 MM (DIP)', '92005407', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1418, 'SS 54 SMALL', '92005410', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1419, 'SMD POWER PCB ASSEMBLE', '92005422', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1420, 'VENT MAIN PCB (BLANK)', '92005423', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1421, 'PCB HUMIDIFIER (BLANK)', '92005424', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1422, 'NTC MF52 100K ohm 3950 Thermistor 1%', '92005427', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1423, '1N4007 Diode', '92005432', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1424, 'IN5822 Diode', '92005433', '92', 'Electronic_ C.P', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1425, 'LENOVO 0.5 MTR VGA Cable', '95005003', '95', 'IT  F.G', 'NOS', 'Brought Out', '123', 1.00, 'active', '18', NULL),
(1426, 'MOPPET 8.5 XL', '99005026', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1427, '99005033 DNA HOSPIKART', '99005033', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1428, 'TRAY SLIDER AHEN 4000', '32006156', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(1429, 'AHEN 4000 MAIN CABINET FRONT COVER', '32006566', '32', 'Sheet Metal Laser Cutting & Bending', 'NOS', 'Manufacturing', '8462', 1.00, 'active', '18', NULL),
(1430, 'AHEN 4000', '99005005', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1431, 'AHEN 4500', '99005006', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1432, 'AHEN 5000 XL', '99005007', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1433, 'AHEN WLE10', '99005034', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1434, 'Arya 25', '99005017', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1435, 'BABYWARMER WITH DRAWER', '99005021', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1436, 'BubbleCPAP', '99005025', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1437, 'BubbleCPAp Yashka', '99005028', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1438, 'DL2605', '99005014', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1439, 'DL-2605 WLEO10 STICKER ASSEMBLY', '99005035', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1440, 'DNA 25', '99005018', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1441, 'DNA 25 WITH CIRCLE ABSORBER', '99005037', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1442, 'HVS 2500', '99005003', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1443, 'HVS 3500', '99005002', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1444, 'Moppet 6.5', '99005010', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1445, 'MOPPET TROLLY', '99005029', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1446, 'MURPHY', '99005012', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1447, 'MURPHY YASHKA ASSEMBLY', '99005031', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1448, 'MURPHY YSP STIKCER', '99005030', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1449, 'NEBULA 2.1', '99005019', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1450, 'Nebula 2.1', '99005036', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1451, 'VENTIGO 77', '99005004', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1452, 'WLE10 HVS 3500', '99005032', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1453, 'XVS 9500', '99005001', '99', 'Asm_F.G', 'NOS', 'Finished Good', '123', 1.00, 'active', '18', NULL),
(1454, 'DL 2605 HUMIDIFIER', '52005014', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1455, 'XVS 9500 XL', '52005020', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1456, 'HVS 3500 XL', '52005024', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1457, 'NEBULA 2.1 ASSEMBLY', '52005029', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1458, 'Ventigo 77 1.10.1\" Display 2.Adult,Pediatric 3.Turbine Based 4. Trolly-(Optional)', '52005032', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1459, 'Moppet 6.5', '52005038', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1460, 'BUBBLE CPAP COMPRESSOR BASED WITH INTERNAL YASHKA BLENDER WITH HUMIDIFIER', '52005039', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1461, 'HVS 2500 XL', '52005041', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1462, 'AHEN 4500 Anesthesia Workstation 1. 10\" screen 2.With Single Vaporizer 3. 2 Yoke 4. 5 Tube Rotameter', '52005056', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1463, 'AHEN 5000 1. Integrated Air compressor 1.12\" screen 3.Etco2 monitoring 4.3 Gas connection 5.4 Yoke 6', '52005058', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1464, 'AHEN 4000(Anesthesia Workstation with 10\" screen & .2 Yoke, 3.3 Gas connection system Nitrous ,Air &', '52005062', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1465, 'Arya 25-Anesthesia workstation 1.2 Yoke 2 Gas Rotameter with hypoxia Guard. 3.7 Inch Display with go', '52005066', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1466, 'DNA 25', '52005068', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1467, 'BABY WARMER 125 XL', '52005070', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1468, 'Moppet 8.5 Transport ventilator with trolley,adult pediatrics and neonatal 8 Inch screen', '52005075', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1469, 'MOPPET 8.5 XL', '52005077', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1470, 'ICVS MODEL', '52005078', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1471, 'DNA 25 WITH CIRCLE ABSORBER', '52005081', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1472, 'XVS 9500 XL with Humidifier', '52005084', '52', 'Asm_Prod', 'NOS', 'Assembly', '123', 1.00, 'active', '5', NULL),
(1473, 'EXHUAST VALVE ASSEMBLY (ENGG)', '42005017', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1474, 'WATER SEPERATOR ASSEMBLY (ENGG)', '42005024', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1475, 'HVS LP TANK ASSMEBLY (ENGG)', '42005045', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1476, 'HVS 3500 SCREEN ASSMEBLY (ENGG)', '42005061', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1477, 'VENT FCV ASSENBLY (ENGG)', '42005127', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1478, '10 BAR PRESSURE GAUGE ASSEMBLY', '42005145', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1479, 'VENT REGULATOR ASSEMBLY (ENGG)', '42005176', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1480, 'AIR FILTER ENGG ASSEMBLY', '42005225', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1481, 'HVS COLUMN DOOR ASSEMBLY (ENGG)', '42005247', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1482, 'COMPRESSOR ENGG ASSEMBLY', '42005299', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1483, 'VOLTAGE INDICATOR ASS', '42005318', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1484, 'COBRA MOUNTING BRACKET', '42005340', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1485, 'COMPRESSOR BACK DOOR ASS', '42005344', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1486, 'HUMIDIFIER CABINET  ASSEMBLY ENGG', '42005362', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1487, 'HVS REGULATOR  ASSMEBLY (ENGG)', '42005387', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1488, 'HUMIDIFIER SMPS ASSEMBLY ENGG', '42005392', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1489, 'MOPPET PCB TRAY ASSEMBLLY (ENGG)', '42005406', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1490, 'MOPPET BACK COVER ASSEMBLY', '42005413', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1491, 'EXHAUST VALVE ASSEMBKY (SYS)', '42005432', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1492, 'INLET ASSEMBLY (ENGG)', '42005433', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1493, 'base stand assembly', '42005439', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1494, 'XVS MONITOR ASSEMBLY (ENGG)', '42005441', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1495, 'HVS PCB TRAY ASSEMBLY (SYS)', '42005443', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1496, 'HVS 3500 CABINET ASSEMBLY (ENGG)', '42005445', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1497, 'XVS LP TANK DOOR ASSEMBLY (ENGG)', '42005447', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1498, 'XVS MONITOR ASSEMBLY (ENGG)', '42005452', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1499, 'MURPHY SCAVENGIG GAS ASSEMBLY (ENGG)', '42005455', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1500, 'XVS LP TANK VALVE ASSMBLY (ENGG)', '42005460', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1501, 'XVS PCB TRAY ASSEMBLY (ENGG)', '42005461', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1502, 'XVS CABINET BACK DOOR ASSEMBLY (ENGG)', '42005462', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1503, 'HVS 2500 BASE STAND ASSEMBLY', '42005470', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1504, 'Nebula 2.1 Column Door Assembly', '42005471', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1505, 'NEBULA CABINET ASSEMBLY (ENGG)', '42005475', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1506, 'VENTIGO CABINET ASSEMBLY (ENGG)', '42005480', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1507, 'VENTIGO BACKCOVER ASSEMBLY(ENGG)', '42005481', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1508, 'NEBULA 2.1 BACK DOOR ASSEMBLY', '42005492', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1509, 'VENTIGO BATTERY ASSEMBLY(ENGG)', '42005509', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1510, 'VENTIGO PCB TRAY ASSSEMBLY(ENGG)', '42005512', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1511, 'AHEN CPU ASSEMBLY', '42005514', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1512, 'AHEN 4500 SCREEN ASSEMBLY', '42005515', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1513, 'BUBBLECPA STAND ASSEMBLY ENGG', '42005522', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1514, 'HP TANK ASSEMBLY', '42005536', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1515, 'DC VALVE ASSEMBLY (ENGG)', '42005542', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1516, 'HVS 10 INCH DRIVER BRACKET ASSEMBLY (SYS)', '42005545', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1517, 'HVS 2500 LP TANK DOOR ASSEMBLY ( ENGG )', '42005547', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1518, 'AHEN 5000 LAMP CABINET ASSEMBLY', '42005551', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1519, 'AHEN SMALL DRAWER ASSEMBLY (ENGG)', '42005553', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1520, 'AHEN COMPRESSOR CABINET ASSEMBLY (ENGG)', '42005554', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1521, 'AHEN 4500 MAIN CABINET ASSEMBLY ( ENGG)', '42005555', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1522, 'AHEN 4500/5000 BASE STAND ASSEMBLY (ENGG0', '42005556', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1523, 'AHEN 4500/5000 ELETRICAL PANEL', '42005558', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1524, 'MOX REGULATOR ASSEMBLY (ENGG)', '42005560', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1525, 'AHEN 4500 / 5000 MAIN CABINET BACK COVER ASSEMBLY', '42005561', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1526, 'BELLOW ASSMEBLY(ENGG)', '42005562', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1527, 'ahen 4500/5000 eletrciacl tray assembly', '42005563', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1528, 'MURPHY DOUBLE REGULATOR ASSEMBLY (ENGG)', '42005595', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1529, 'AHEN 4000 DC VALVE ASSEMBLY (ENGG)', '42005597', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1530, 'MURPHY INLET ASSEMBLY (ENGG)', '42005600', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1531, 'AHEN SCAVENGING FLOW ASSEMBLY (ENGG)', '42005601', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1532, 'MURPHY EXHAUST VALVE ASSEMBLY (ENGG)', '42005603', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1533, 'TWIN TECH BAR ASSEMBLY (ENGG)', '42005605', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1534, 'MURPHY NRV ASSEMBLY (ENGG)', '42005606', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1535, 'AHEN 5000/4500 TRAY CABINET COVER ASSEMBLY', '42005619', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1536, '3 WAY VALVE ASSEMBLY AHEN 4500 ASSEMBLY', '42005621', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1537, 'MURPHY MOX REGULATOR ASSEMBLY (ENGG)', '42005622', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1538, 'AHEN  HP TANK ASSEMBLY (ENGG)', '42005623', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1539, 'AHEN BIG DRAWER ASSEMBLY  ENGG)', '42005624', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1540, 'OXYGEN GAUGE ASSEMBLY (ENGG)', '42005638', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1541, 'AHEN 5000 SCREEN ASSEMBLY', '42005642', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1542, 'AHEN 5000 MAIN CABINET ASSEMBLY', '42005643', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1543, 'AHEN SINGLE REGULATOR ASSEMBLY (ENGG)', '42005646', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1544, 'AHEN 4500 / 5000 DC VALVE ASSEMBLY (ENGG)', '42005647', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1545, 'AHEN 4000 MAIN BASE AND STAND ASSEMBLY (ENGG)', '42005654', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1546, 'DRAWER ASSEMBLY', '42005657', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1547, 'AHEN 4000 DRAWER CABINATE ASSEMBLY', '42005658', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1548, 'PCB AND CABINATE ASSEMBLY', '42005659', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1549, 'ANESTHESIA TRAY CABINATE ASSEMBLY', '42005660', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1550, 'FRONT CABINATE PLATE AND SIDE HANDLE ASSEMBLY', '42005661', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1551, 'FAN MOUNTED ASSEMBLY', '42005662', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL);
INSERT INTO `part_master` (`id`, `part_name`, `part_no`, `part_id`, `description`, `uom`, `category`, `hsn_code`, `rate`, `status`, `gst`, `attachment_path`) VALUES
(1552, 'AHEN 4000 CABINET ASSEMBLY (ENGG)', '42005663', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1553, 'AHEN 4000 CABINET BACK COVER ASSEMBLY (ENGG)', '42005664', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1554, 'AHEN 4000 LAMP CABINET ASSEMBLY (ENGG)', '42005665', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1555, 'XVS BATTERY ASSEMBLY (ENGG)', '42005666', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1556, 'AUTO DRAIN VALVE ASSEMBLY ENGG', '42005670', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1557, '16 BAR GAUGE ASSEMBLY', '42005671', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1558, 'AHEN 4000 SCREEN ASSEMBLY', '42005673', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1559, 'WATERSEPERATOR ASSEMBLY COMPRESSOR', '42005694', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1560, 'PRESSURE SWITCH ASSEMBLY ENGG', '42005695', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1561, 'HVS 3500 CABINET BACK COVER ASSEMBLY (SYS)', '42005696', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1562, 'MOPPET 6.5 INLET PLATE ASSEMBLY(ENGG)', '42005697', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1563, 'MAIN  MOPPET CABINET  ASSEMBLY', '42005698', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1564, 'MOPPET THIN VENT PLATE ASSEMBLY (ENGG)', '42005699', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1565, 'MOPPET BATTERY ASSEMBLY (ENGG)', '42005703', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1566, 'NEBULIZER VALVE ASSEMBLY (ENGG)', '42005706', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1567, 'TANK DC VALVE ASSEMBLY (ENGG)', '42005719', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1568, 'MURPHY/ARYA BASE STAND ASSEMBLY (ENGG)', '42005721', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1569, 'BLENDER TANK ASSEMBLY ENGG', '42005734', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1570, 'MEDICAL GAS FLOWMETER ASSEMBLY', '42005735', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1571, 'BLENDER REGULATOR ASSEMBLY', '42005737', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1572, 'BLENDER BACK COVER ASSEMBLY', '42005738', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1573, 'AHEN WATER SEPERATTOR ASSEMBLY', '42005756', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1574, 'MURPHY/ARYA MAIN CABINET ASSEMBLY (ENGG)', '42005764', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1575, 'MURPHY/ARYA ELECTRICAL PANNEL ASSEMBLY (ENGG)', '42005765', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1576, 'MURPHY / ARYA DRAWER CABINET ASSEMBLY (ENGG)', '42005766', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1577, 'MURPHY/ARYA CABINET BACK DOOR ASSEMBLY (ENGG)', '42005767', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1578, 'MURPHY DC VALVE ASSEMBLY (ENGG)', '42005768', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1579, 'MURPHY/ARYA TRAY CABIINET COVER ASSEMBLY (ENGG)', '42005770', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1580, 'MURPHY/ARYA PCB TRAY ASSEMBLY (ENGG)', '42005771', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1581, 'MURPHY / ARYA UPPER LAMP CABINET ASSEMBLY (ENGG)', '42005773', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1582, 'AHEN HP TANKE WATER SEPERATOR ASSEMBLY (ENGG)', '42005774', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1583, 'BLENDER FCV ASSEMBLY', '42005775', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1584, 'BLENDER FCV  KNOB ASSEMBLY', '42005776', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1585, 'BLENDER CABINET ASSEMBLY ENGG', '42005790', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1586, 'DNA ELECTRICAL ASSEMBLY (ENGG)', '42005791', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1587, 'DNA RH INLET ASSEMBLY (ENGG)', '42005792', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1588, 'DNA LH SIDE ASSEMBLY (ENGG)', '42005793', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1589, 'DNA CABINET ASSEMBLY (ENGG)', '42005794', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1590, 'DNA BACK COVER ASSEMBLY (ENGG)', '42005795', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1591, 'PRECISION REGULATOT ASSEMBLY (ENGG)', '42005798', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1592, 'NEW BABY WARMER STAND ASSEMBLY', '42005806', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1593, 'BABY WARMER PCB CABINET ASSEMBLY', '42005808', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1594, 'BABY WARMER BACK DOOR ASSEMBLY', '42005809', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1595, 'BABY WARMER HEATER CABINET ASSEMBLY', '42005810', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1596, 'MOPPET 8.5 TROLLY ASSEMBLY (ENGG)', '42005811', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1597, 'AC Outdoor Unit', '42005826', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1598, 'MOPPET 8.5 XL SIDE DOOR ASSEMBLY (ENGG)', '42005838', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1599, 'MOPPET 8.5 THIN WENT ASSEMBLY', '42005839', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1600, 'MOPPET 8.5 CABINATE ASSEMBLY', '42005840', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1601, 'XVS CABINET ASSEMBLY (ENGG)', '42005842', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1602, 'MOPPET STAND COLUMN ASSEMBLY(ENGG)', '42005845', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1603, 'MOPPET XL INLET PLATE ASSEMBLY', '42005846', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1604, 'BUBBLECPAP BASE STAND ASSEMBLY (ENGG)', '42005848', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1605, 'ICVS CABINET  ASSEMBLY (ENGG)', '42005849', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1606, 'ICVS BACK DOOR ASSEMBLY', '42005850', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1607, 'ICVS COLUMN ASSEMBLY', '42005851', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1608, 'ICVS PCB ASSEMBLY', '42005853', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1609, 'ICVS INLET ASSEMBLY', '42005854', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1610, 'ICVS EXAHUST ASSEMBLY', '42005855', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1611, 'AHEN AUTO DRAIN VALVE ASSEMBLY', '42005862', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1612, 'AHEN 4500 OXYGEN ALARM ASSEMBLY', '42005863', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1613, 'New DNA cabinet Assembly', '42005864', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1614, 'New DNA back cover Assembly', '42005865', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1615, 'AHEN 5000 MOX REGULATOR ASSEMBLY', '42005886', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1616, 'NEBULA PCB TRAY ASSEMBLY', '42005999', '42', 'Asm_Eng', 'NOS', 'Assembly', '123', 1.00, 'active', '18', NULL),
(1617, 'Moppet 7.5 MEDILO Assembly', '99005038', '99', 'Sale Finish Good', 'Nos', 'Finished Good', '90192090', 100000.00, 'active', '5', NULL),
(1618, 'Assembly Moppet 7.5  Model', '52005085', '52', 'Portable ventilator', 'Nos', 'Assembly', '1234', 40000.00, 'active', '5', NULL),
(1619, 'Moppet 7.5 Ventiator Assembly', '46005089', '46', 'ICU Ventilator', 'Nos', 'Assembly', '1234', 40000.00, 'active', '5', NULL),
(1620, 'MOPPET  TROLLY STAND ASSEMBLY', '46005077', '46', 'TROOLY STAND FOR VENTILATOR', 'Nos', 'Assembly', '1234', 5000.00, 'active', '5', NULL),
(1621, 'ECG 12 channel', '13005001', '13', 'Medical Device', 'Nos', 'Brought Out', '90192090', 12500.00, 'active', '5', NULL),
(1622, '3 para patient monitor', '13005002', '13', 'Medical Device', 'Nos', 'Brought Out', '90192090', 9500.00, 'active', '5', NULL),
(1623, 'VENTIGO 77', '46005021', '46', 'Ventigo 77', 'Nos', 'Manufacturing', '00', 60000.00, 'active', '18', 'uploads/parts/46005021_1769836512.pdf'),
(1624, 'HVS COLUMN ASSMEBLY (SYS)', '46005002', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '0', 6998.63, 'active', '18', NULL),
(1625, 'DL -HUMIDIFIER', '46005003', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 10000.00, 'active', '18', NULL),
(1626, 'VENT COMPRESSOR ASSEMBLY (SYS)', '46005009', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 16534.68, 'active', '18', NULL),
(1627, 'HVS 3500 CABINET ASSEMBLY (SYS)', '46005011', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 62483.15, 'active', '18', NULL),
(1628, 'XVS CABINET VENTILATOR ASSEMBLY (SYS)', '46005017', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 68052.16, 'active', '18', NULL),
(1629, 'Nebula column and base stand assembly', '46005019', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 0.00, 'active', '18', NULL),
(1630, 'NEBULA CABINET ASSEMBLY', '46005020', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 0.00, 'active', '18', NULL),
(1631, 'BUBBLE CPAP STAND ASSEMBLY ( SYS)', '46005024', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 966.23, 'active', '18', NULL),
(1632, 'COMPRESSOR ASSEMBLY BUBBLE CPAP', '46005025', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 18046.27, 'active', '18', NULL),
(1633, 'HVS 2500 STAND AND COLUMN ASSEMBLY', '46005027', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 0.00, 'active', '18', NULL),
(1634, 'AHEN 4500 MAIN CABINET ASSEMBLY', '46005029', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 0.00, 'active', '18', NULL),
(1635, 'AHEN 4500 / 5000 COMPRESSOR CABINET ASSEMBLY (SYS)', '46005030', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 26278.60, 'active', '18', NULL),
(1636, 'AHEN 5000 MAIN CABINET ASSEMBLY', '46005045', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 28340.42, 'active', '18', NULL),
(1637, 'AHEN 4000 DRAWER AND ELECTRICAL PANNEL ASSEMBLY (SYS)', '46005050', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 37122.50, 'active', '18', NULL),
(1638, 'AHEN 4000 CABINET ASSEMBLY (SYS)', '46005051', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 107302.56, 'active', '18', NULL),
(1639, 'MOPPET CABINET ASSEMBLY (SYS)', '46005056', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 53378.30, 'active', '18', NULL),
(1640, 'BLENDER AIR & OXYGEN', '46005059', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 18028.54, 'active', '18', NULL),
(1641, 'MURPHY /ARYA CABINET ASSEMBLY (SYS)', '46005063', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 65762.19, 'active', '18', NULL),
(1642, 'ARYA / MURPHY DRAWER CABINET AND ELETRICAL PANNEL ASSEMBLY (SYS)', '46005064', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1643, 'DNA 25 SUB ASSEMBLY', '46005067', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 0.00, 'active', '18', NULL),
(1644, 'BABY WARMER STAND ASSEMBLY', '46005069', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1645, 'BABY WARMER PCB CABINET AND HEATER CABINET ASSMEMBLY', '46005070', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1646, 'MOPPET 8.5 CABINET ASSEMBLY (SUS ASSEMBLY)', '46005076', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1647, 'ICVS CABINET ASSEMBLY', '46005078', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1648, 'ICVS COLUMN ASSEMBLY', '46005079', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1649, 'WITHOUT FLOWMETER BLENDER', '46005080', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1650, 'Ambitious Compressor Assembly', '46005081', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL),
(1651, 'New DNA Assembly', '46005086', '46', 'SYSTEAM ASSEMBLY', 'NOS', 'Assembly', '11', 35020.99, 'active', '18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `part_min_stock`
--

CREATE TABLE `part_min_stock` (
  `id` int(11) NOT NULL,
  `part_no` varchar(255) NOT NULL,
  `min_stock_qty` int(11) DEFAULT 0,
  `reorder_qty` int(11) DEFAULT 10,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_min_stock`
--

INSERT INTO `part_min_stock` (`id`, `part_no`, `min_stock_qty`, `reorder_qty`, `last_updated`) VALUES
(1, '44005643', 1, 0, '2026-01-19 03:04:18'),
(2, '32005653', 11, 0, '2026-01-29 13:05:01'),
(3, '11005089', 1, 2, '2026-01-31 02:42:13');

-- --------------------------------------------------------

--
-- Table structure for table `part_supplier_mapping`
--

CREATE TABLE `part_supplier_mapping` (
  `id` int(11) NOT NULL,
  `part_no` varchar(255) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_sku` varchar(255) DEFAULT NULL,
  `supplier_rate` decimal(12,2) DEFAULT NULL,
  `lead_time_days` int(11) DEFAULT 5,
  `min_order_qty` int(11) DEFAULT 1,
  `is_preferred` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_supplier_mapping`
--

INSERT INTO `part_supplier_mapping` (`id`, `part_no`, `supplier_id`, `supplier_sku`, `supplier_rate`, `lead_time_days`, `min_order_qty`, `is_preferred`, `active`, `created_at`, `updated_at`) VALUES
(1, '22005001', 1, '', 15.00, 5, 1, 0, 1, '2026-01-19 03:13:43', '2026-01-19 03:13:43'),
(2, '22005519', 1, '', 50.00, 5, 1, 0, 1, '2026-01-19 03:14:57', '2026-01-19 03:14:57'),
(3, '44005643', 2, '', 25.00, 5, 1, 0, 1, '2026-01-19 03:15:47', '2026-01-19 03:15:47'),
(4, '22001111', 1, '142', 15.00, 5, 1, 0, 1, '2026-01-21 11:27:10', '2026-01-21 11:27:10'),
(5, '22001111', 2, '150', 16.00, 5, 1, 0, 1, '2026-01-21 11:27:34', '2026-01-21 11:27:34'),
(6, '22005001', 3, '113', 1000.00, 5, 1, 0, 1, '2026-01-21 11:44:25', '2026-01-21 11:44:25'),
(7, 'YID-044', 46, '142', 15000.00, 1, 1, 0, 1, '2026-01-23 01:28:41', '2026-01-23 01:28:41'),
(8, 'YID-063', 24, 'fdd', 12500.00, 1, 1, 0, 1, '2026-01-23 01:29:59', '2026-01-23 01:29:59'),
(9, '62005151', 16, '', 12.00, 5, 1, 0, 1, '2026-01-23 13:35:46', '2026-01-23 13:35:46'),
(11, '11005099', 5, '', 12.00, 5, 1, 0, 1, '2026-01-23 14:06:34', '2026-01-23 14:06:34'),
(12, '32005010', 31, '', 25.00, 5, 1, 0, 1, '2026-01-23 18:45:39', '2026-01-23 18:45:39'),
(13, '16005037', 10, '', 25.00, 5, 1, 0, 1, '2026-01-23 18:48:11', '2026-01-23 18:48:11'),
(14, 'YID -043', 46, '123', 12500.00, 5, 1, 0, 1, '2026-01-24 14:33:26', '2026-01-24 14:33:26'),
(15, 'YID-008', 4, '124', 240000.00, 5, 1, 0, 1, '2026-01-25 05:18:07', '2026-01-25 05:18:07'),
(17, '11005143', 18, '', 15.00, 5, 1, 0, 1, '2026-01-27 07:26:33', '2026-01-27 07:26:33'),
(18, '11005089', 18, '', 24.00, 5, 1, 0, 1, '2026-01-27 07:27:35', '2026-01-27 07:27:35'),
(19, '17005066', 48, '', 4.00, 5, 1, 0, 1, '2026-01-27 07:28:57', '2026-01-27 07:28:57'),
(20, '62005137', 40, '', 250.00, 5, 1, 0, 1, '2026-01-27 07:29:45', '2026-01-27 07:29:45'),
(23, '11005120', 18, '', 25.00, 5, 1, 0, 1, '2026-01-28 03:35:16', '2026-01-28 03:35:16'),
(28, '11005099', 4, NULL, 15.00, 5, 1, 0, 1, '2026-01-28 03:39:18', '2026-01-28 03:39:18'),
(29, '83005030', 7, NULL, 1.00, 5, 1, 0, 1, '2026-01-28 14:02:04', '2026-01-28 14:02:04'),
(30, '83005002', 7, NULL, 1.00, 5, 1, 0, 1, '2026-01-28 14:02:04', '2026-01-28 14:02:04'),
(32, '13005001', 9, '', 1500.00, 5, 1, 0, 1, '2026-01-29 01:34:14', '2026-01-29 01:34:14'),
(33, '17005068', 9, '', 10.00, 5, 1, 0, 1, '2026-01-29 03:03:27', '2026-01-29 03:03:27'),
(34, '17005011', 23, '', 250.00, 5, 1, 0, 1, '2026-01-29 03:04:17', '2026-01-29 03:04:17'),
(35, '62005265', 4, '', 250.00, 5, 1, 0, 1, '2026-01-29 09:11:54', '2026-01-29 09:11:54'),
(38, '95005002', 5, '', 9000.00, 5, 1, 0, 1, '2026-01-29 09:17:45', '2026-01-29 09:17:45'),
(39, '62005003', 4, NULL, 15.00, 5, 1, 0, 1, '2026-01-29 09:41:28', '2026-01-29 09:47:02'),
(40, '62005004', 4, NULL, 35.00, 5, 1, 0, 1, '2026-01-29 09:41:28', '2026-01-29 09:41:28'),
(41, '62005099', 4, NULL, 4900.00, 5, 1, 0, 1, '2026-01-29 09:41:28', '2026-01-29 09:41:28'),
(42, '62005003', 14, NULL, 10.00, 5, 1, 0, 1, '2026-01-29 09:42:07', '2026-01-29 09:47:26'),
(43, '62005004', 14, NULL, 35.00, 5, 1, 0, 1, '2026-01-29 09:42:07', '2026-01-29 09:42:07'),
(44, '62005099', 14, NULL, 4900.00, 5, 1, 0, 1, '2026-01-29 09:42:07', '2026-01-29 09:42:07'),
(45, '22005177', 1, NULL, 216.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(46, '22005013', 1, NULL, 465.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(47, '22005184', 1, NULL, 186.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(48, '22005199', 1, NULL, 245.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(49, '22005237', 1, NULL, 350.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(50, '22005411', 1, NULL, 220.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(51, '22005468', 1, NULL, 500.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(52, '22005562', 1, NULL, 250.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(53, '22005569', 1, NULL, 1.00, 5, 1, 0, 1, '2026-01-29 09:42:38', '2026-01-29 09:42:38'),
(54, '82005506', 45, '', 2900.00, 5, 1, 0, 1, '2026-01-29 12:35:15', '2026-01-29 12:35:38'),
(56, '11005122', 18, '', 100.00, 5, 1, 0, 1, '2026-01-29 13:18:00', '2026-01-29 13:18:00'),
(58, '11005123', 19, '', 650.00, 5, 1, 0, 1, '2026-01-29 13:19:58', '2026-01-29 13:19:58'),
(59, '11005123', 18, '', 500.00, 5, 1, 1, 1, '2026-01-29 13:20:33', '2026-01-29 13:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `payment_terms`
--

CREATE TABLE `payment_terms` (
  `id` int(11) NOT NULL,
  `term_name` varchar(100) NOT NULL,
  `term_description` text DEFAULT NULL,
  `days` int(11) DEFAULT 0 COMMENT 'Payment due in X days',
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_terms`
--

INSERT INTO `payment_terms` (`id`, `term_name`, `term_description`, `days`, `is_default`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, '100% Advance Payment', '100% payment to be made before dispatch of goods', 0, 1, 1, 1, '2026-01-23 11:40:53', '2026-01-30 06:34:48'),
(2, '50% Advance, 50% Before Dispatch', '50% advance with order, remaining 50% before dispatch', 0, 0, 1, 2, '2026-01-23 11:40:53', '2026-01-30 06:34:48'),
(3, '30% Advance, 70% on Delivery', '30% advance payment, 70% on delivery', 0, 0, 1, 3, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(4, 'Net 15 Days', 'Payment due within 15 days from invoice date', 15, 0, 1, 4, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(5, 'Net 30 Days', 'Payment due within 30 days from invoice date', 30, 0, 1, 5, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(6, 'Net 45 Days', 'Payment due within 45 days from invoice date', 45, 0, 1, 6, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(7, 'Net 60 Days', 'Payment due within 60 days from invoice date', 60, 0, 1, 7, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(8, 'Against Delivery', 'Full payment at the time of delivery', 0, 0, 1, 8, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(9, 'LC at Sight', 'Letter of Credit payable at sight', 0, 0, 1, 9, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(10, 'LC 30 Days', 'Letter of Credit with 30 days credit', 30, 0, 1, 10, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(11, 'LC 60 Days', 'Letter of Credit with 60 days credit', 60, 0, 1, 11, '2026-01-23 11:40:53', '2026-01-23 11:40:53'),
(12, 'LC 90 Days', 'Letter of Credit with 90 days credit', 90, 0, 1, 12, '2026-01-23 11:40:53', '2026-01-23 11:40:53');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_month` date NOT NULL,
  `working_days` int(11) DEFAULT 0,
  `days_present` int(11) DEFAULT 0,
  `days_absent` int(11) DEFAULT 0,
  `leaves_taken` int(11) DEFAULT 0,
  `holidays` int(11) DEFAULT 0,
  `basic_salary` decimal(12,2) DEFAULT 0.00,
  `hra` decimal(12,2) DEFAULT 0.00,
  `conveyance` decimal(12,2) DEFAULT 0.00,
  `medical_allowance` decimal(12,2) DEFAULT 0.00,
  `special_allowance` decimal(12,2) DEFAULT 0.00,
  `other_allowance` decimal(12,2) DEFAULT 0.00,
  `performance_allowance` decimal(10,2) DEFAULT 0.00,
  `food_allowance` decimal(10,2) DEFAULT 0.00,
  `overtime_pay` decimal(12,2) DEFAULT 0.00,
  `bonus` decimal(12,2) DEFAULT 0.00,
  `arrears` decimal(12,2) DEFAULT 0.00,
  `gross_earnings` decimal(12,2) DEFAULT 0.00,
  `pf_employee` decimal(12,2) DEFAULT 0.00,
  `pf_employer` decimal(12,2) DEFAULT 0.00,
  `esi_employee` decimal(12,2) DEFAULT 0.00,
  `esi_employer` decimal(12,2) DEFAULT 0.00,
  `professional_tax` decimal(12,2) DEFAULT 0.00,
  `tds` decimal(12,2) DEFAULT 0.00,
  `loan_deduction` decimal(12,2) DEFAULT 0.00,
  `other_deduction` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `net_pay` decimal(12,2) DEFAULT 0.00,
  `status` enum('Draft','Processed','Approved','Paid') DEFAULT 'Draft',
  `payment_date` date DEFAULT NULL,
  `payment_mode` enum('Bank Transfer','Cheque','Cash') DEFAULT 'Bank Transfer',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `employee_id`, `payroll_month`, `working_days`, `days_present`, `days_absent`, `leaves_taken`, `holidays`, `basic_salary`, `hra`, `conveyance`, `medical_allowance`, `special_allowance`, `other_allowance`, `performance_allowance`, `food_allowance`, `overtime_pay`, `bonus`, `arrears`, `gross_earnings`, `pf_employee`, `pf_employer`, `esi_employee`, `esi_employer`, `professional_tax`, `tds`, `loan_deduction`, `other_deduction`, `total_deductions`, `net_pay`, `status`, `payment_date`, `payment_mode`, `transaction_ref`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 63, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:25:04', '2026-01-26 16:25:04'),
(2, 68, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(3, 74, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(4, 66, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(5, 67, '2026-01-01', 27, 0, 0, 0, 0, 15000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 15000.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-30 10:03:54'),
(6, 72, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(7, 69, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(8, 62, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(9, 73, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(10, 64, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(11, 65, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(12, 70, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(13, 71, '2026-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-26 16:33:14', '2026-01-26 16:33:14'),
(14, 75, '2026-01-01', 26, 22, 3, 1, 1, 13538.46, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 13538.46, 1624.62, 1624.62, 101.54, 440.00, 150.00, 0.00, 0.00, 0.00, 1876.16, 11662.30, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:48:24', '2026-01-29 11:48:24'),
(15, 77, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(16, 78, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(17, 66, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(18, 62, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(19, 76, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(20, 63, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(21, 68, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(22, 74, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(23, 67, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(24, 72, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(25, 75, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(26, 69, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(27, 73, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(28, 64, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(29, 65, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(30, 70, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(31, 71, '2025-12-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 11:49:32', '2026-01-29 11:49:32'),
(32, 76, '2026-01-01', 26, 0, 0, 1, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Draft', NULL, 'Bank Transfer', NULL, NULL, '2026-01-29 12:01:40', '2026-01-29 12:01:40');

-- --------------------------------------------------------

--
-- Table structure for table `po_inspection_approvals`
--

CREATE TABLE `po_inspection_approvals` (
  `id` int(11) NOT NULL,
  `po_no` varchar(50) NOT NULL,
  `checklist_id` int(11) DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `approver_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_inspection_approvals`
--

INSERT INTO `po_inspection_approvals` (`id`, `po_no`, `checklist_id`, `requested_by`, `approver_id`, `status`, `remarks`, `requested_at`, `approved_at`) VALUES
(1, 'PO-119', 1, NULL, 74, 'Approved', 'found ok', '2026-01-31 14:23:16', '2026-01-31 19:54:05');

-- --------------------------------------------------------

--
-- Table structure for table `po_inspection_approvers`
--

CREATE TABLE `po_inspection_approvers` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_inspection_approvers`
--

INSERT INTO `po_inspection_approvers` (`id`, `employee_id`, `is_active`, `created_at`) VALUES
(1, 74, 1, '2026-01-31 14:10:03');

-- --------------------------------------------------------

--
-- Table structure for table `po_inspection_checklists`
--

CREATE TABLE `po_inspection_checklists` (
  `id` int(11) NOT NULL,
  `po_no` varchar(50) NOT NULL,
  `checklist_no` varchar(50) NOT NULL,
  `inspector_name` varchar(100) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  `supplier_invoice_no` varchar(100) DEFAULT NULL,
  `status` enum('Draft','Submitted','Approved','Rejected') DEFAULT 'Draft',
  `overall_result` enum('Pass','Fail','Pending','Conditional') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_inspection_checklists`
--

INSERT INTO `po_inspection_checklists` (`id`, `po_no`, `checklist_no`, `inspector_name`, `inspection_date`, `supplier_invoice_no`, `status`, `overall_result`, `remarks`, `created_by`, `created_at`, `submitted_at`) VALUES
(1, 'PO-119', 'IQC000001', 'PRANJALI ', '2026-01-31', '423166', 'Approved', 'Pass', 'ok', NULL, '2026-01-31 14:18:48', '2026-01-31 19:50:08');

-- --------------------------------------------------------

--
-- Table structure for table `po_inspection_checklist_items`
--

CREATE TABLE `po_inspection_checklist_items` (
  `id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `item_no` int(11) NOT NULL,
  `checkpoint` varchar(255) NOT NULL,
  `specification` text DEFAULT NULL,
  `result` enum('Pending','OK','Not OK','NA','Conditional') DEFAULT 'Pending',
  `actual_value` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_inspection_checklist_items`
--

INSERT INTO `po_inspection_checklist_items` (`id`, `checklist_id`, `item_no`, `checkpoint`, `specification`, `result`, `actual_value`, `remarks`) VALUES
(1, 1, 1, 'Purchase Order Match', 'Verify items match PO specifications', 'OK', '', ''),
(2, 1, 2, 'Packing List Verification', 'Check packing list against received items', 'OK', '', ''),
(3, 1, 3, 'Invoice Verification', 'Supplier invoice matches PO and delivery', 'OK', '', ''),
(4, 1, 4, 'Certificate of Conformance', 'COC/Test certificates provided if required', 'OK', '', ''),
(5, 1, 5, 'Quantity Verification', 'Received quantity matches delivery note', 'OK', '', ''),
(6, 1, 6, 'Part Number Verification', 'Part numbers match PO specifications', 'OK', '', ''),
(7, 1, 7, 'Packaging Condition', 'Packaging intact and undamaged', 'OK', '', ''),
(8, 1, 8, 'Labeling Check', 'Items properly labeled with part no, batch, date', 'OK', '', ''),
(9, 1, 9, 'Seal Integrity', 'Seals unbroken (if applicable)', 'OK', '', ''),
(10, 1, 10, 'Visual Inspection', 'No visible damage, rust, or defects', 'OK', '', ''),
(11, 1, 11, 'Dimensional Check', 'Dimensions within specifications', 'OK', '', ''),
(12, 1, 12, 'Color/Finish Check', 'Color and finish as per specification', 'NA', '', ''),
(13, 1, 13, 'Weight Verification', 'Weight within acceptable range', 'NA', '', ''),
(14, 1, 14, 'Material Verification', 'Material grade/type as specified', 'NA', '', ''),
(15, 1, 15, 'Functionality Test', 'Basic functionality verified (if applicable)', 'NA', '', ''),
(16, 1, 16, 'Expiry/Shelf Life', 'Within acceptable shelf life period', 'NA', '', ''),
(17, 1, 17, 'Safety Standards', 'Meets required safety standards', 'NA', '', ''),
(18, 1, 18, 'Regulatory Compliance', 'Complies with applicable regulations', 'NA', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `po_inspection_checkpoint_templates`
--

CREATE TABLE `po_inspection_checkpoint_templates` (
  `id` int(11) NOT NULL,
  `item_no` int(11) NOT NULL,
  `checkpoint` varchar(255) NOT NULL,
  `specification` text DEFAULT NULL,
  `category` varchar(100) DEFAULT 'General',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_inspection_checkpoint_templates`
--

INSERT INTO `po_inspection_checkpoint_templates` (`id`, `item_no`, `checkpoint`, `specification`, `category`, `is_active`, `created_at`) VALUES
(1, 1, 'Purchase Order Match', 'Verify items match PO specifications', 'Documentation', 1, '2026-01-31 14:09:48'),
(2, 2, 'Packing List Verification', 'Check packing list against received items', 'Documentation', 1, '2026-01-31 14:09:48'),
(3, 3, 'Invoice Verification', 'Supplier invoice matches PO and delivery', 'Documentation', 1, '2026-01-31 14:09:48'),
(4, 4, 'Certificate of Conformance', 'COC/Test certificates provided if required', 'Documentation', 1, '2026-01-31 14:09:48'),
(5, 5, 'Quantity Verification', 'Received quantity matches delivery note', 'Quantity', 1, '2026-01-31 14:09:48'),
(6, 6, 'Part Number Verification', 'Part numbers match PO specifications', 'Quantity', 1, '2026-01-31 14:09:48'),
(7, 7, 'Packaging Condition', 'Packaging intact and undamaged', 'Packaging', 1, '2026-01-31 14:09:48'),
(8, 8, 'Labeling Check', 'Items properly labeled with part no, batch, date', 'Packaging', 1, '2026-01-31 14:09:48'),
(9, 9, 'Seal Integrity', 'Seals unbroken (if applicable)', 'Packaging', 1, '2026-01-31 14:09:48'),
(10, 10, 'Visual Inspection', 'No visible damage, rust, or defects', 'Physical', 1, '2026-01-31 14:09:48'),
(11, 11, 'Dimensional Check', 'Dimensions within specifications', 'Physical', 1, '2026-01-31 14:09:48'),
(12, 12, 'Color/Finish Check', 'Color and finish as per specification', 'Physical', 1, '2026-01-31 14:09:48'),
(13, 13, 'Weight Verification', 'Weight within acceptable range', 'Physical', 1, '2026-01-31 14:09:48'),
(14, 14, 'Material Verification', 'Material grade/type as specified', 'Quality', 1, '2026-01-31 14:09:48'),
(15, 15, 'Functionality Test', 'Basic functionality verified (if applicable)', 'Quality', 1, '2026-01-31 14:09:48'),
(16, 16, 'Expiry/Shelf Life', 'Within acceptable shelf life period', 'Quality', 1, '2026-01-31 14:09:48'),
(17, 17, 'Safety Standards', 'Meets required safety standards', 'Compliance', 1, '2026-01-31 14:09:48'),
(18, 18, 'Regulatory Compliance', 'Complies with applicable regulations', 'Compliance', 1, '2026-01-31 14:09:48');

-- --------------------------------------------------------

--
-- Table structure for table `procurement_plans`
--

CREATE TABLE `procurement_plans` (
  `id` int(11) NOT NULL,
  `plan_no` varchar(50) NOT NULL,
  `so_list` text DEFAULT NULL,
  `plan_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('draft','approved','partiallyordered','completed','cancelled') DEFAULT 'draft',
  `total_parts` int(11) DEFAULT NULL,
  `total_items_to_order` int(11) DEFAULT NULL,
  `total_estimated_cost` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procurement_plans`
--

INSERT INTO `procurement_plans` (`id`, `plan_no`, `so_list`, `plan_date`, `status`, `total_parts`, `total_items_to_order`, `total_estimated_cost`, `created_by`, `approved_by`, `approved_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'PP-001', NULL, '2026-01-21 05:04:00', 'partiallyordered', 1, 1, 25.00, 1, 1, '2026-01-23 10:26:34', '', '2026-01-21 05:04:00', '2026-01-23 10:27:05'),
(2, 'PP-002', NULL, '2026-01-24 14:39:51', 'partiallyordered', 1, 2, 25000.00, 1, 1, '2026-01-24 14:40:17', 'urgent requirement', '2026-01-24 14:39:51', '2026-01-24 14:40:25'),
(3, 'PP-003', NULL, '2026-01-24 16:35:50', 'partiallyordered', 2, 4, 55000.00, 1, 1, '2026-01-24 16:35:58', '', '2026-01-24 16:35:50', '2026-01-24 16:36:01'),
(4, 'PP-004', NULL, '2026-01-24 16:39:20', 'partiallyordered', 2, 5, 67500.00, 1, 1, '2026-01-24 16:39:26', '', '2026-01-24 16:39:20', '2026-01-24 16:39:28'),
(5, 'PP-005', NULL, '2026-01-24 16:47:25', 'partiallyordered', 3, 9, 112525.00, 1, 1, '2026-01-24 16:47:32', '', '2026-01-24 16:47:25', '2026-01-24 16:47:35'),
(6, 'PP-006', NULL, '2026-01-25 02:09:43', 'partiallyordered', 1, 1, 12500.00, 1, 1, '2026-01-25 02:09:49', '', '2026-01-25 02:09:43', '2026-01-25 02:11:12'),
(7, 'PP-007', NULL, '2026-01-25 03:09:42', 'partiallyordered', 3, 2, 12525.00, 1, 1, '2026-01-25 03:09:54', '', '2026-01-25 03:09:42', '2026-01-25 03:10:12'),
(8, 'PP-008', NULL, '2026-01-25 03:22:46', 'partiallyordered', 1, 2, 25000.00, 1, 1, '2026-01-25 03:22:51', '', '2026-01-25 03:22:46', '2026-01-25 03:22:54'),
(9, 'PP-009', NULL, '2026-01-25 05:14:58', 'partiallyordered', 2, 1, 12500.00, 1, 1, '2026-01-25 05:15:05', '', '2026-01-25 05:14:58', '2026-01-25 05:15:11'),
(10, 'PP-010', NULL, '2026-01-25 05:18:38', 'partiallyordered', 1, 1, 240000.00, 1, 1, '2026-01-25 05:18:43', '', '2026-01-25 05:18:38', '2026-01-25 05:18:45'),
(11, 'PP-011', NULL, '2026-01-25 09:11:41', 'partiallyordered', 1, 2, 25000.00, 1, 1, '2026-01-25 09:11:49', '', '2026-01-25 09:11:41', '2026-01-25 09:11:53'),
(12, 'PP-012', NULL, '2026-01-25 09:23:00', 'partiallyordered', 1, 2, 25000.00, 1, 1, '2026-01-25 09:23:05', '', '2026-01-25 09:23:00', '2026-01-25 09:23:10'),
(13, 'PP-013', NULL, '2026-01-25 13:47:54', 'partiallyordered', 3, 1, 25.00, 1, 1, '2026-01-25 13:47:59', '', '2026-01-25 13:47:54', '2026-01-25 13:48:04'),
(14, 'PP-014', NULL, '2026-01-26 16:55:40', 'partiallyordered', 3, 0, 0.00, 1, 1, '2026-01-26 16:55:45', '', '2026-01-26 16:55:40', '2026-01-26 16:55:48'),
(15, 'PP-015', NULL, '2026-01-26 17:02:02', 'approved', 1, 1, 14500.00, 1, 1, '2026-01-26 17:02:10', '', '2026-01-26 17:02:02', '2026-01-26 17:02:10'),
(16, 'PP-016', NULL, '2026-01-27 07:59:50', 'approved', 3, 0, 0.00, 1, 1, '2026-01-27 08:00:02', '', '2026-01-27 07:59:50', '2026-01-27 08:00:02'),
(17, 'PP-017', NULL, '2026-01-27 08:06:42', 'draft', 2, 0, 0.00, 1, NULL, NULL, '', '2026-01-27 08:06:42', '2026-01-27 08:06:42'),
(18, 'PP-018', NULL, '2026-01-27 08:52:21', 'draft', 2, 3, 495000.00, 1, NULL, NULL, '', '2026-01-27 08:52:21', '2026-01-27 08:52:21'),
(19, 'PP-019', NULL, '2026-01-27 08:53:09', 'cancelled', 2, 1, 240000.00, 1, NULL, NULL, '', '2026-01-27 08:53:09', '2026-01-27 08:53:23'),
(20, 'PP-020', NULL, '2026-01-27 09:05:46', 'partiallyordered', 2, 1, 240000.00, 1, 1, '2026-01-28 15:50:42', '', '2026-01-27 09:05:46', '2026-01-28 15:50:45'),
(21, 'PP-021', NULL, '2026-01-27 10:12:19', 'partiallyordered', 2, 1, 240000.00, 1, 1, '2026-01-28 15:50:23', '', '2026-01-27 10:12:19', '2026-01-28 15:50:28'),
(22, 'PP-022', NULL, '2026-01-27 10:23:01', 'partiallyordered', 2, 1, 240000.00, 1, 1, '2026-01-28 15:49:01', '', '2026-01-27 10:23:01', '2026-01-28 15:49:05'),
(23, 'PP-023', NULL, '2026-01-27 13:19:54', 'partiallyordered', 2, 1, 240000.00, 1, 1, '2026-01-28 15:50:04', '', '2026-01-27 13:19:54', '2026-01-28 15:50:07'),
(24, 'PP-024', NULL, '2026-01-27 13:20:05', 'partiallyordered', 2, 1, 240000.00, 1, 1, '2026-01-28 15:49:32', '', '2026-01-27 13:20:05', '2026-01-28 15:49:36'),
(25, 'PP-025', NULL, '2026-01-28 15:52:19', 'partiallyordered', 4, 2, 480000.00, 1, 1, '2026-01-28 15:52:25', '', '2026-01-28 15:52:19', '2026-01-28 15:52:28'),
(26, 'PP-026', 'SO-38', '2026-01-29 05:55:29', 'draft', NULL, NULL, 0.00, 1, NULL, NULL, NULL, '2026-01-29 05:55:29', '2026-01-29 05:55:29'),
(27, 'PP-027', 'SO-39', '2026-01-29 06:04:31', 'approved', NULL, NULL, 0.00, 1, 1, '2026-01-29 06:06:12', NULL, '2026-01-29 06:04:31', '2026-01-29 06:06:12'),
(28, 'PP-028', 'SO-37,SO-38,SO-39', '2026-01-29 09:27:48', 'draft', NULL, NULL, 0.00, 1, NULL, NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(29, 'PP-029', 'SO-38,SO-39', '2026-01-29 09:29:00', 'draft', NULL, NULL, 0.00, 1, NULL, NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(30, 'PP-030', 'SO-37', '2026-01-29 09:30:09', 'approved', NULL, NULL, 0.00, 1, 1, '2026-01-29 09:32:26', NULL, '2026-01-29 09:30:09', '2026-01-29 09:32:26'),
(31, 'PP-031', 'SO-37,SO-38', '2026-01-29 09:31:59', 'draft', NULL, NULL, 0.00, 1, NULL, NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(32, 'PP-032', 'SO-40', '2026-01-30 05:30:03', 'draft', NULL, NULL, 0.00, 1, NULL, NULL, NULL, '2026-01-30 05:30:03', '2026-01-30 05:30:03');

-- --------------------------------------------------------

--
-- Table structure for table `procurement_plan_items`
--

CREATE TABLE `procurement_plan_items` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `item_type` enum('main','wo','po') DEFAULT 'main',
  `part_no` varchar(255) NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `required_qty` int(11) DEFAULT 0,
  `recommended_qty` int(11) DEFAULT 0,
  `min_stock_threshold` int(11) DEFAULT 0,
  `supplier_id` int(11) NOT NULL,
  `suggested_rate` decimal(12,2) DEFAULT NULL,
  `line_total` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','ordered','received','skipped') DEFAULT 'pending',
  `created_po_id` int(11) DEFAULT NULL,
  `created_po_line_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procurement_plan_items`
--

INSERT INTO `procurement_plan_items` (`id`, `plan_id`, `item_type`, `part_no`, `current_stock`, `required_qty`, `recommended_qty`, `min_stock_threshold`, `supplier_id`, `suggested_rate`, `line_total`, `status`, `created_po_id`, `created_po_line_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'main', '44005643', 0, 1, 1, 1, 2, 25.00, 25.00, 'ordered', 33, 33, NULL, '2026-01-21 05:04:00', '2026-01-23 10:27:05'),
(2, 2, 'main', 'YID -043', 0, 1, 2, 0, 46, 12500.00, 25000.00, 'ordered', 35, 35, NULL, '2026-01-24 14:39:52', '2026-01-24 14:40:25'),
(3, 3, 'main', 'YID-044', -1, 1, 2, 0, 46, 15000.00, 30000.00, 'ordered', 41, 41, NULL, '2026-01-24 16:35:50', '2026-01-24 16:36:01'),
(4, 3, 'main', 'YID-063', -1, 1, 2, 0, 24, 12500.00, 25000.00, 'ordered', 40, 40, NULL, '2026-01-24 16:35:50', '2026-01-24 16:36:01'),
(5, 4, 'main', 'YID-044', -1, 1, 2, 0, 46, 15000.00, 30000.00, 'ordered', 43, 43, NULL, '2026-01-24 16:39:20', '2026-01-24 16:39:28'),
(6, 4, 'main', 'YID-063', -1, 2, 3, 0, 24, 12500.00, 37500.00, 'ordered', 42, 42, NULL, '2026-01-24 16:39:20', '2026-01-24 16:39:28'),
(7, 5, 'main', '44005643', 0, 1, 1, 1, 2, 25.00, 25.00, 'ordered', 44, 44, NULL, '2026-01-24 16:47:25', '2026-01-24 16:47:35'),
(8, 5, 'main', 'YID-044', 1, 6, 5, 0, 46, 15000.00, 75000.00, 'ordered', 46, 46, NULL, '2026-01-24 16:47:25', '2026-01-24 16:47:35'),
(9, 5, 'main', 'YID-063', 0, 3, 3, 0, 24, 12500.00, 37500.00, 'ordered', 45, 45, NULL, '2026-01-24 16:47:25', '2026-01-24 16:47:35'),
(10, 6, 'main', 'YID -043', 0, 1, 1, 0, 46, 12500.00, 12500.00, 'ordered', 47, 47, NULL, '2026-01-25 02:09:43', '2026-01-25 02:11:12'),
(11, 7, 'main', '44005643', 0, 1, 1, 1, 2, 25.00, 25.00, 'ordered', 48, 48, NULL, '2026-01-25 03:09:42', '2026-01-25 03:10:12'),
(12, 7, 'main', 'YID-044', 6, 3, 0, 0, 46, 15000.00, 0.00, 'ordered', 50, 50, NULL, '2026-01-25 03:09:42', '2026-01-25 03:10:12'),
(13, 7, 'main', 'YID-063', 0, 1, 1, 0, 24, 12500.00, 12500.00, 'ordered', 49, 49, NULL, '2026-01-25 03:09:42', '2026-01-25 03:10:12'),
(14, 8, 'main', 'YID -043', -1, 1, 2, 0, 46, 12500.00, 25000.00, 'ordered', 51, 51, NULL, '2026-01-25 03:22:46', '2026-01-25 03:22:54'),
(15, 9, 'main', 'YID-044', 6, 1, 0, 0, 46, 15000.00, 0.00, 'ordered', 53, 53, NULL, '2026-01-25 05:14:58', '2026-01-25 05:15:11'),
(16, 9, 'main', 'YID-063', 0, 1, 1, 0, 24, 12500.00, 12500.00, 'ordered', 52, 52, NULL, '2026-01-25 05:14:58', '2026-01-25 05:15:11'),
(17, 10, 'main', 'YID-008', 0, 1, 1, 0, 4, 240000.00, 240000.00, 'ordered', 54, 54, NULL, '2026-01-25 05:18:38', '2026-01-25 05:18:45'),
(18, 11, 'main', 'YID -043', -1, 1, 2, 0, 46, 12500.00, 25000.00, 'ordered', 55, 55, NULL, '2026-01-25 09:11:41', '2026-01-25 09:11:53'),
(19, 12, 'main', 'YID -043', -1, 1, 2, 0, 46, 12500.00, 25000.00, 'ordered', 56, 56, NULL, '2026-01-25 09:23:00', '2026-01-25 09:23:10'),
(20, 13, 'main', '44005643', 0, 1, 1, 1, 2, 25.00, 25.00, 'ordered', 57, 57, NULL, '2026-01-25 13:47:54', '2026-01-25 13:48:04'),
(21, 13, 'main', 'YID-044', 6, 6, 0, 0, 46, 15000.00, 0.00, 'ordered', 59, 59, NULL, '2026-01-25 13:47:54', '2026-01-25 13:48:04'),
(22, 13, 'main', 'YID-063', 5, 3, 0, 0, 24, 12500.00, 0.00, 'ordered', 58, 58, NULL, '2026-01-25 13:47:54', '2026-01-25 13:48:04'),
(23, 14, 'main', '44005643', 4, 1, 0, 1, 2, 25.00, 0.00, 'ordered', 60, 60, NULL, '2026-01-26 16:55:40', '2026-01-26 16:55:48'),
(24, 14, 'main', 'YID-044', 8, 6, 0, 0, 46, 15000.00, 0.00, 'ordered', 62, 62, NULL, '2026-01-26 16:55:40', '2026-01-26 16:55:48'),
(25, 14, 'main', 'YID-063', 7, 3, 0, 0, 24, 12500.00, 0.00, 'ordered', 61, 61, NULL, '2026-01-26 16:55:40', '2026-01-26 16:55:48'),
(26, 15, 'main', 'YID-167', 0, 1, 1, 0, 4, 14500.00, 14500.00, 'pending', NULL, NULL, NULL, '2026-01-26 17:02:02', '2026-01-26 17:02:02'),
(27, 16, 'main', '44005643', 4, 1, 0, 1, 2, 25.00, 0.00, 'pending', NULL, NULL, NULL, '2026-01-27 07:59:50', '2026-01-27 07:59:50'),
(28, 16, 'main', 'YID-044', 8, 6, 0, 0, 46, 15000.00, 0.00, 'pending', NULL, NULL, NULL, '2026-01-27 07:59:50', '2026-01-27 07:59:50'),
(29, 16, 'main', 'YID-063', 7, 3, 0, 0, 24, 12500.00, 0.00, 'pending', NULL, NULL, NULL, '2026-01-27 07:59:50', '2026-01-27 07:59:50'),
(30, 17, 'main', 'YID-044', 8, 1, 0, 0, 46, 15000.00, 0.00, 'pending', NULL, NULL, NULL, '2026-01-27 08:06:42', '2026-01-27 08:06:42'),
(31, 17, 'main', 'YID-063', 7, 1, 0, 0, 24, 12500.00, 0.00, 'pending', NULL, NULL, NULL, '2026-01-27 08:06:42', '2026-01-27 08:06:42'),
(32, 18, 'main', 'YID-008', -1, 0, 2, 0, 4, 240000.00, 480000.00, 'pending', NULL, NULL, NULL, '2026-01-27 08:52:21', '2026-01-27 08:52:21'),
(33, 18, 'main', 'YID-044', 8, 0, 1, 0, 46, 15000.00, 15000.00, 'pending', NULL, NULL, NULL, '2026-01-27 08:52:21', '2026-01-27 08:52:21'),
(34, 19, 'main', 'YID-008', -1, 0, 1, 0, 4, 240000.00, 240000.00, 'pending', NULL, NULL, NULL, '2026-01-27 08:53:09', '2026-01-27 08:53:09'),
(35, 19, 'main', 'YID-044', 8, 0, 0, 0, 46, 15000.00, 0.00, 'pending', NULL, NULL, NULL, '2026-01-27 08:53:09', '2026-01-27 08:53:09'),
(36, 20, 'main', 'YID-008', -1, 0, 1, 0, 4, 240000.00, 240000.00, 'ordered', 87, 87, NULL, '2026-01-27 09:05:46', '2026-01-28 15:50:45'),
(37, 20, 'main', 'YID-044', 8, 0, 0, 0, 46, 15000.00, 0.00, 'ordered', 88, 88, NULL, '2026-01-27 09:05:46', '2026-01-28 15:50:45'),
(38, 21, 'main', 'YID-008', -1, 0, 1, 0, 4, 240000.00, 240000.00, 'ordered', 85, 85, NULL, '2026-01-27 10:12:19', '2026-01-28 15:50:28'),
(39, 21, 'main', 'YID-044', 8, 0, 0, 0, 46, 15000.00, 0.00, 'ordered', 86, 86, NULL, '2026-01-27 10:12:19', '2026-01-28 15:50:28'),
(40, 22, 'main', 'YID-008', -1, 0, 1, 0, 4, 240000.00, 240000.00, 'ordered', 79, 79, NULL, '2026-01-27 10:23:01', '2026-01-28 15:49:05'),
(41, 22, 'main', 'YID-044', 8, 0, 0, 0, 46, 15000.00, 0.00, 'ordered', 80, 80, NULL, '2026-01-27 10:23:01', '2026-01-28 15:49:05'),
(42, 23, 'main', 'YID-008', -1, 0, 1, 0, 4, 240000.00, 240000.00, 'ordered', 83, 83, NULL, '2026-01-27 13:19:54', '2026-01-28 15:50:07'),
(43, 23, 'main', 'YID-044', 8, 0, 0, 0, 46, 15000.00, 0.00, 'ordered', 84, 84, NULL, '2026-01-27 13:19:54', '2026-01-28 15:50:07'),
(44, 24, 'main', 'YID-008', -1, 0, 1, 0, 4, 240000.00, 240000.00, 'ordered', 81, 81, NULL, '2026-01-27 13:20:05', '2026-01-28 15:49:36'),
(45, 24, 'main', 'YID-044', 8, 0, 0, 0, 46, 15000.00, 0.00, 'ordered', 82, 82, NULL, '2026-01-27 13:20:05', '2026-01-28 15:49:36'),
(46, 25, 'main', '44005643', 4, 1, 0, 1, 2, 25.00, 0.00, 'ordered', 89, 89, NULL, '2026-01-28 15:52:19', '2026-01-28 15:52:28'),
(47, 25, 'main', 'YID-008', -1, 0, 2, 0, 4, 240000.00, 480000.00, 'ordered', 90, 90, NULL, '2026-01-28 15:52:19', '2026-01-28 15:52:28'),
(48, 25, 'main', 'YID-044', 8, 6, 0, 0, 46, 15000.00, 0.00, 'ordered', 92, 92, NULL, '2026-01-28 15:52:19', '2026-01-28 15:52:28'),
(49, 25, 'main', 'YID-063', 7, 3, 0, 0, 24, 12500.00, 0.00, 'ordered', 91, 91, NULL, '2026-01-28 15:52:19', '2026-01-28 15:52:28');

-- --------------------------------------------------------

--
-- Table structure for table `procurement_plan_po_items`
--

CREATE TABLE `procurement_plan_po_items` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `part_no` varchar(100) NOT NULL,
  `part_name` varchar(255) DEFAULT NULL,
  `part_id` varchar(50) DEFAULT NULL,
  `so_list` varchar(500) DEFAULT NULL,
  `required_qty` decimal(15,3) DEFAULT 0.000,
  `current_stock` decimal(15,3) DEFAULT 0.000,
  `shortage` decimal(15,3) DEFAULT 0.000,
  `ordered_qty` decimal(15,3) DEFAULT 0.000,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','ordered','received','cancelled') DEFAULT 'pending',
  `created_po_id` int(11) DEFAULT NULL,
  `created_po_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procurement_plan_po_items`
--

INSERT INTO `procurement_plan_po_items` (`id`, `plan_id`, `part_no`, `part_name`, `part_id`, `so_list`, `required_qty`, `current_stock`, `shortage`, `ordered_qty`, `supplier_id`, `supplier_name`, `status`, `created_po_id`, `created_po_no`, `created_at`, `updated_at`) VALUES
(1, 27, '11005089', 'EP FOAM 25MM 54X78 INCH', '11', 'SO-39', 3.000, 4.000, 0.000, 0.000, 18, 'GIRISH PACKAGING', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 09:26:39'),
(2, 27, '17005066', 'AC FUSE 10 A STICKER', '17', 'SO-39', 1.000, 2.000, 0.000, 0.000, 48, 'TRIO RADIO & ELECTRONIC CORPORATION', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(3, 27, '12005227', 'Adult NIV Mask', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(4, 27, '12005208', 'Air Circuit Adult Breathing', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(5, 27, '12005049', 'Air Circuit Without Trap Neonatal / Pediatric circuit plain without water trap', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(6, 27, '17005067', 'DC 10A FUSE STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(7, 27, '12005002', 'HME FILTER', '12', 'SO-39', 2.000, 0.000, 2.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(8, 27, '72005208', 'HOSE CLIP 8MM OXYGEN PIPE CLAMP_72005208', '62', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(9, 27, '12005008', 'Hose Pipe 6mm Duplon oxygen pipe white', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(10, 27, '12005231', 'NEONATLE HME FILTER', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(11, 27, '72005168', 'OXYGEN FITTING BRASS 3/8_72005168', '62', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(12, 27, '17005022', 'OXYGEN INLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(13, 27, '17005018', 'OXYGEN PRESSURE STICKE R', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(14, 27, '17005023', 'PATIENT INLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(15, 27, '17005024', 'PATIENT OUTLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(16, 27, '12005086', 'Pediatric Test Lungs 50 ml', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(17, 27, '62005137', 'QRC COUPLER_62005137', '62', 'SO-39', 1.000, 0.000, 1.000, 1.000, 40, 'RAHUL INDUSTRIAL PRODUCTS', 'ordered', 94, 'PO-83', '2026-01-29 06:04:31', '2026-01-29 06:09:10'),
(18, 27, '12005005', 'Test Lungs Adult', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(19, 27, '11005143', 'MOPPET BOX', '11', 'SO-39', 1.000, 0.000, 1.000, 1.000, 18, 'GIRISH PACKAGING', 'ordered', 93, 'PO-82', '2026-01-29 06:04:31', '2026-01-29 06:09:10'),
(20, 27, '11005144', 'MOPPET BOX CAP', '11', 'SO-39', 2.000, 0.000, 2.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 06:04:31', '2026-01-29 06:04:31'),
(281, 28, '11005089', 'EP FOAM 25MM 54X78 INCH', '11', 'SO-37, SO-39', 4.000, 4.000, 0.000, 0.000, 18, 'GIRISH PACKAGING', 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(282, 28, '11005096', 'HUMIDIFIER BOX', '11', 'SO-37', 1.000, 2.000, 0.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(283, 28, '17005068', '230-240V AC STICKER', '17', 'SO-37', 1.000, 0.000, 1.000, 1.000, 9, 'AZAD ENTERPRISE', 'ordered', 101, 'PO-87', '2026-01-29 09:27:48', '2026-01-29 09:28:39'),
(284, 28, '17005066', 'AC FUSE 10 A STICKER', '17', 'SO-37, SO-39', 2.000, 2.000, 0.000, 0.000, 48, 'TRIO RADIO & ELECTRONIC CORPORATION', 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(285, 28, '17005011', 'AHEN 5000 STICKER', '17', 'SO-37', 1.000, 0.000, 1.000, 1.000, 23, 'KEY AUTOMATION', 'ordered', 103, 'PO-89', '2026-01-29 09:27:48', '2026-01-29 09:28:39'),
(286, 28, '11005099', 'HVS CABINET BOX 21x21x34', '11', 'SO-37', 1.000, 4.000, 0.000, 0.000, 5, 'AFZAL COMPUTORS', 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(287, 28, '12005227', 'Adult NIV Mask', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(288, 28, '12005208', 'Air Circuit Adult Breathing', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(289, 28, '12005049', 'Air Circuit Without Trap Neonatal / Pediatric circuit plain without water trap', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(290, 28, '17005067', 'DC 10A FUSE STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(291, 28, '12005002', 'HME FILTER', '12', 'SO-39', 2.000, 0.000, 2.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(292, 28, '72005208', 'HOSE CLIP 8MM OXYGEN PIPE CLAMP_72005208', '62', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(293, 28, '12005008', 'Hose Pipe 6mm Duplon oxygen pipe white', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(294, 28, '12005231', 'NEONATLE HME FILTER', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(295, 28, '72005168', 'OXYGEN FITTING BRASS 3/8_72005168', '62', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(296, 28, '17005022', 'OXYGEN INLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(297, 28, '17005018', 'OXYGEN PRESSURE STICKE R', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(298, 28, '17005023', 'PATIENT INLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(299, 28, '17005024', 'PATIENT OUTLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(300, 28, '12005086', 'Pediatric Test Lungs 50 ml', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(301, 28, '62005137', 'QRC COUPLER_62005137', '62', 'SO-39', 1.000, 0.000, 1.000, 1.000, 40, 'RAHUL INDUSTRIAL PRODUCTS', 'ordered', 104, 'PO-90', '2026-01-29 09:27:49', '2026-01-29 09:28:39'),
(302, 28, '12005005', 'Test Lungs Adult', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(303, 28, '11005143', 'MOPPET BOX', '11', 'SO-39', 1.000, 0.000, 1.000, 1.000, 18, 'GIRISH PACKAGING', 'ordered', 102, 'PO-88', '2026-01-29 09:27:49', '2026-01-29 09:28:39'),
(304, 28, '11005144', 'MOPPET BOX CAP', '11', 'SO-39', 2.000, 0.000, 2.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:27:49', '2026-01-29 09:27:49'),
(421, 29, '11005089', 'EP FOAM 25MM 54X78 INCH', '11', 'SO-39', 3.000, 4.000, 0.000, 0.000, 18, 'GIRISH PACKAGING', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(422, 29, '17005066', 'AC FUSE 10 A STICKER', '17', 'SO-39', 1.000, 2.000, 0.000, 0.000, 48, 'TRIO RADIO & ELECTRONIC CORPORATION', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(423, 29, '12005227', 'Adult NIV Mask', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(424, 29, '12005208', 'Air Circuit Adult Breathing', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(425, 29, '12005049', 'Air Circuit Without Trap Neonatal / Pediatric circuit plain without water trap', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(426, 29, '17005067', 'DC 10A FUSE STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(427, 29, '12005002', 'HME FILTER', '12', 'SO-39', 2.000, 0.000, 2.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(428, 29, '72005208', 'HOSE CLIP 8MM OXYGEN PIPE CLAMP_72005208', '62', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(429, 29, '12005008', 'Hose Pipe 6mm Duplon oxygen pipe white', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(430, 29, '12005231', 'NEONATLE HME FILTER', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(431, 29, '72005168', 'OXYGEN FITTING BRASS 3/8_72005168', '62', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(432, 29, '17005022', 'OXYGEN INLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(433, 29, '17005018', 'OXYGEN PRESSURE STICKE R', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(434, 29, '17005023', 'PATIENT INLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(435, 29, '17005024', 'PATIENT OUTLET STICKER', '17', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(436, 29, '12005086', 'Pediatric Test Lungs 50 ml', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(437, 29, '62005137', 'QRC COUPLER_62005137', '62', 'SO-39', 1.000, 0.000, 1.000, 1.000, 40, 'RAHUL INDUSTRIAL PRODUCTS', 'ordered', 106, 'PO-92', '2026-01-29 09:29:00', '2026-01-29 09:29:23'),
(438, 29, '12005005', 'Test Lungs Adult', '12', 'SO-39', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(439, 29, '11005143', 'MOPPET BOX', '11', 'SO-39', 1.000, 0.000, 1.000, 1.000, 18, 'GIRISH PACKAGING', 'ordered', 105, 'PO-91', '2026-01-29 09:29:00', '2026-01-29 09:29:23'),
(440, 29, '11005144', 'MOPPET BOX CAP', '11', 'SO-39', 2.000, 0.000, 2.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(529, 30, '11005089', 'EP FOAM 25MM 54X78 INCH', '11', 'SO-37', 1.000, 4.000, 0.000, 0.000, 18, 'GIRISH PACKAGING', 'pending', NULL, NULL, '2026-01-29 09:30:09', '2026-01-29 09:30:09'),
(530, 30, '11005096', 'HUMIDIFIER BOX', '11', 'SO-37', 1.000, 2.000, 0.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:30:09', '2026-01-29 09:30:09'),
(531, 30, '17005068', '230-240V AC STICKER', '17', 'SO-37', 1.000, 0.000, 1.000, 1.000, 9, 'AZAD ENTERPRISE', 'ordered', 107, 'PO-93', '2026-01-29 09:30:09', '2026-01-29 09:30:25'),
(532, 30, '17005066', 'AC FUSE 10 A STICKER', '17', 'SO-37', 1.000, 2.000, 0.000, 0.000, 48, 'TRIO RADIO & ELECTRONIC CORPORATION', 'pending', NULL, NULL, '2026-01-29 09:30:09', '2026-01-29 09:30:09'),
(533, 30, '17005011', 'AHEN 5000 STICKER', '17', 'SO-37', 1.000, 1.000, 0.000, 1.000, 23, 'KEY AUTOMATION', 'ordered', 108, 'PO-94', '2026-01-29 09:30:09', '2026-01-29 09:43:45'),
(534, 30, '11005099', 'HVS CABINET BOX 21x21x34', '11', 'SO-37', 1.000, 4.000, 0.000, 0.000, 5, 'AFZAL COMPUTORS', 'pending', NULL, NULL, '2026-01-29 09:30:09', '2026-01-29 09:30:09'),
(617, 31, '11005089', 'EP FOAM 25MM 54X78 INCH', '11', 'SO-37', 1.000, 4.000, 0.000, 0.000, 18, 'GIRISH PACKAGING', 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(618, 31, '11005096', 'HUMIDIFIER BOX', '11', 'SO-37', 1.000, 2.000, 0.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(619, 31, '17005068', '230-240V AC STICKER', '17', 'SO-37', 1.000, 0.000, 1.000, 1.000, 9, 'AZAD ENTERPRISE', 'ordered', 109, 'PO-95', '2026-01-29 09:31:59', '2026-01-29 09:32:06'),
(620, 31, '17005066', 'AC FUSE 10 A STICKER', '17', 'SO-37', 1.000, 2.000, 0.000, 0.000, 48, 'TRIO RADIO & ELECTRONIC CORPORATION', 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(621, 31, '17005011', 'AHEN 5000 STICKER', '17', 'SO-37', 1.000, 0.000, 1.000, 1.000, 23, 'KEY AUTOMATION', 'ordered', 110, 'PO-96', '2026-01-29 09:31:59', '2026-01-29 09:32:06'),
(622, 31, '11005099', 'HVS CABINET BOX 21x21x34', '11', 'SO-37', 1.000, 4.000, 0.000, 0.000, 5, 'AFZAL COMPUTORS', 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(693, 32, 'YID-012', 'Ventigo 77', '0', 'SO-40', 1.000, 0.000, 1.000, 0.000, NULL, 'No Supplier', 'pending', NULL, NULL, '2026-01-30 05:30:03', '2026-01-30 05:30:03');

-- --------------------------------------------------------

--
-- Stand-in structure for view `procurement_plan_summary`
-- (See below for the actual view)
--
CREATE TABLE `procurement_plan_summary` (
`id` int(11)
,`plan_no` varchar(50)
,`plan_date` timestamp
,`status` enum('draft','approved','partiallyordered','completed','cancelled')
,`total_parts` int(11)
,`total_items_to_order` int(11)
,`total_estimated_cost` decimal(15,2)
,`item_count` bigint(21)
,`pending_count` decimal(22,0)
,`ordered_count` decimal(22,0)
,`received_count` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `procurement_plan_wo_items`
--

CREATE TABLE `procurement_plan_wo_items` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `part_no` varchar(100) NOT NULL,
  `part_name` varchar(255) DEFAULT NULL,
  `part_id` varchar(50) DEFAULT NULL,
  `so_list` varchar(500) DEFAULT NULL,
  `required_qty` decimal(15,3) DEFAULT 0.000,
  `current_stock` decimal(15,3) DEFAULT 0.000,
  `shortage` decimal(15,3) DEFAULT 0.000,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `created_wo_id` int(11) DEFAULT NULL,
  `created_wo_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procurement_plan_wo_items`
--

INSERT INTO `procurement_plan_wo_items` (`id`, `plan_id`, `part_no`, `part_name`, `part_id`, `so_list`, `required_qty`, `current_stock`, `shortage`, `status`, `created_wo_id`, `created_wo_no`, `created_at`, `updated_at`) VALUES
(1, 26, 'YID-002', 'XVS 9500 XL.', 'YID', 'SO-38', 1.000, 0.000, 1.000, 'in_progress', 24, 'WO-20', '2026-01-29 05:55:29', '2026-01-29 05:58:40'),
(12, 27, 'YID-167', 'Lio smart-7.5', 'YID', 'SO-39', 1.000, 0.000, 1.000, 'in_progress', 25, 'WO-21', '2026-01-29 06:04:31', '2026-01-29 06:04:44'),
(13, 27, '99005038', 'Moppet 7.5 MEDILO Assembly', '99', 'SO-39', 1.000, 0.000, 1.000, 'in_progress', 26, 'WO-22', '2026-01-29 06:04:31', '2026-01-29 06:04:44'),
(14, 27, '52005085', 'Assembly Moppet 7.5  Model', '52', 'SO-39', 1.000, 0.000, 1.000, 'in_progress', 27, 'WO-23', '2026-01-29 06:04:31', '2026-01-29 06:04:44'),
(15, 27, '46005077', 'MOPPET  TROLLY STAND ASSEMBLY', '46', 'SO-39', 1.000, 0.000, 1.000, 'in_progress', 28, 'WO-24', '2026-01-29 06:04:31', '2026-01-29 06:04:44'),
(16, 27, '46005089', 'Moppet 7.5 Ventiator Assembly', '46', 'SO-39', 1.000, 0.000, 1.000, 'in_progress', 29, 'WO-25', '2026-01-29 06:04:31', '2026-01-29 06:04:44'),
(82, 28, 'YID-003', 'XVS 9500', 'YID', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 34, 'WO-30', '2026-01-29 09:27:48', '2026-01-29 09:29:16'),
(83, 28, '99005002', 'HVS 3500', '99', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 35, 'WO-31', '2026-01-29 09:27:48', '2026-01-29 09:29:16'),
(84, 28, '52005024', 'HVS 3500 XL', '52', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 36, 'WO-32', '2026-01-29 09:27:48', '2026-01-29 09:29:16'),
(85, 28, '46005077', 'MOPPET  TROLLY STAND ASSEMBLY', '46', 'SO-37, SO-39', 2.000, 0.000, 2.000, 'in_progress', 37, 'WO-33', '2026-01-29 09:27:48', '2026-01-29 09:29:16'),
(86, 28, '46005089', 'Moppet 7.5 Ventiator Assembly', '46', 'SO-37, SO-39', 2.000, 0.000, 2.000, 'in_progress', 38, 'WO-34', '2026-01-29 09:27:48', '2026-01-29 09:29:16'),
(87, 28, 'YID-002', 'XVS 9500 XL.', 'YID', 'SO-38', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(88, 28, 'YID-167', 'Lio smart-7.5', 'YID', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(89, 28, '99005038', 'Moppet 7.5 MEDILO Assembly', '99', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(90, 28, '52005085', 'Assembly Moppet 7.5  Model', '52', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:27:48', '2026-01-29 09:27:48'),
(132, 29, 'YID-002', 'XVS 9500 XL.', 'YID', 'SO-38', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(133, 29, 'YID-167', 'Lio smart-7.5', 'YID', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(134, 29, '99005038', 'Moppet 7.5 MEDILO Assembly', '99', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(135, 29, '52005085', 'Assembly Moppet 7.5  Model', '52', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(136, 29, '46005077', 'MOPPET  TROLLY STAND ASSEMBLY', '46', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(137, 29, '46005089', 'Moppet 7.5 Ventiator Assembly', '46', 'SO-39', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:29:00', '2026-01-29 09:29:00'),
(167, 30, 'YID-003', 'XVS 9500', 'YID', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 39, 'WO-35', '2026-01-29 09:30:09', '2026-01-29 09:31:41'),
(168, 30, '99005002', 'HVS 3500', '99', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 40, 'WO-36', '2026-01-29 09:30:09', '2026-01-29 09:31:41'),
(169, 30, '52005024', 'HVS 3500 XL', '52', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 41, 'WO-37', '2026-01-29 09:30:09', '2026-01-29 09:31:41'),
(170, 30, '46005077', 'MOPPET  TROLLY STAND ASSEMBLY', '46', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 42, 'WO-38', '2026-01-29 09:30:09', '2026-01-29 09:31:41'),
(171, 30, '46005089', 'Moppet 7.5 Ventiator Assembly', '46', 'SO-37', 1.000, 0.000, 1.000, 'in_progress', 43, 'WO-39', '2026-01-29 09:30:09', '2026-01-29 09:31:41'),
(208, 31, 'YID-003', 'XVS 9500', 'YID', 'SO-37', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(209, 31, '99005002', 'HVS 3500', '99', 'SO-37', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(210, 31, '52005024', 'HVS 3500 XL', '52', 'SO-37', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(211, 31, '46005077', 'MOPPET  TROLLY STAND ASSEMBLY', '46', 'SO-37', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(212, 31, '46005089', 'Moppet 7.5 Ventiator Assembly', '46', 'SO-37', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59'),
(213, 31, 'YID-002', 'XVS 9500 XL.', 'YID', 'SO-38', 1.000, 0.000, 1.000, 'pending', NULL, NULL, '2026-01-29 09:31:59', '2026-01-29 09:31:59');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_no` varchar(50) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_type` enum('New Product Development','Product Improvement','Cost Reduction','Quality Improvement','Process Improvement','Compliance','Other') DEFAULT 'New Product Development',
  `design_phase` enum('Concept','Preliminary Design','Detailed Design','Prototype','Testing','Production','Released') DEFAULT 'Concept',
  `part_no` varchar(50) DEFAULT NULL,
  `project_manager` varchar(100) DEFAULT NULL,
  `project_engineer` varchar(100) DEFAULT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `status` enum('Planning','In Progress','On Hold','Completed','Cancelled') DEFAULT 'Planning',
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `progress_percentage` int(11) DEFAULT 0,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_no`, `project_name`, `project_type`, `design_phase`, `part_no`, `project_manager`, `project_engineer`, `customer_id`, `description`, `start_date`, `end_date`, `budget`, `status`, `priority`, `progress_percentage`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PROJ-0001', 'XVS Elite', 'New Product Development', 'Concept', NULL, 'Dipankar', 'Ayan', NULL, 'New design', '2026-01-20', '2026-01-23', 12000.00, 'Planning', 'Medium', 0, '1', '2026-01-20 16:52:17', '2026-01-20 16:52:17'),
(2, 'PROJ-9.2233720368548E+18', 'Integrated Circle Absorber', 'New Product Development', 'Concept', NULL, 'Ayan Shaikh', 'Ayan Shaikh', 'CUST-3', 'Integrated Circle Absorber Design', '2026-01-20', '2026-03-31', 50000.00, 'Planning', 'Medium', 20, '4', '2026-01-31 04:52:04', '2026-01-31 16:38:33'),
(3, 'PROJ-0010', 'xvs new', 'New Product Development', 'Concept', NULL, 'Arvind Uttam pawar', 'Arvind Uttam pawar', 'CUST-58', 'ventilator', '2026-01-31', '2026-02-08', 15000.00, 'Planning', 'Medium', 0, '1', '2026-01-31 16:58:28', '2026-01-31 16:58:28');

-- --------------------------------------------------------

--
-- Table structure for table `project_activities`
--

CREATE TABLE `project_activities` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `activity_type` varchar(100) DEFAULT NULL,
  `activity_description` longtext DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','Overdue') DEFAULT 'Pending',
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_documents`
--

CREATE TABLE `project_documents` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `version` varchar(20) DEFAULT '1.0',
  `uploaded_by` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_milestones`
--

CREATE TABLE `project_milestones` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `milestone_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','Delayed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_milestones`
--

INSERT INTO `project_milestones` (`id`, `project_id`, `milestone_name`, `description`, `target_date`, `completion_date`, `status`, `created_at`) VALUES
(1, 2, 'Design finalisation', NULL, '2026-01-31', NULL, 'In Progress', '2026-01-31 16:31:22'),
(2, 2, 'Proto Development', NULL, '2026-02-06', NULL, 'Pending', '2026-01-31 16:31:50'),
(3, 3, 'Design finalisation', NULL, '2026-01-31', NULL, 'In Progress', '2026-01-31 17:11:05');

-- --------------------------------------------------------

--
-- Table structure for table `project_tasks`
--

CREATE TABLE `project_tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `task_start_date` date DEFAULT NULL,
  `task_end_date` date DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','On Hold','Cancelled') DEFAULT 'Pending',
  `remark` longtext DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_tasks`
--

INSERT INTO `project_tasks` (`id`, `project_id`, `task_name`, `task_start_date`, `task_end_date`, `status`, `remark`, `assigned_to`, `created_at`, `updated_at`) VALUES
(1, 2, 'APL Valve Machining Part', '2026-01-31', '2026-02-10', 'Completed', NULL, 'Ayan', '2026-01-31 04:52:47', '2026-01-31 05:07:37'),
(2, 2, 'Gauge - Bipul', '2026-01-31', '2026-02-10', 'Completed', NULL, 'Neha', '2026-01-31 04:53:14', '2026-01-31 05:05:15'),
(3, 2, 'New Design Changes', '2026-02-02', '2026-02-07', 'Pending', NULL, 'Ayan', '2026-01-31 04:53:38', '2026-01-31 04:53:38'),
(4, 2, 'Gauge Position', '2026-01-20', '2026-01-30', 'Completed', NULL, 'Ayan', '2026-01-31 04:54:28', '2026-01-31 04:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_no` varchar(20) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL,
  `rate` decimal(12,2) DEFAULT 0.00,
  `amount` decimal(12,2) DEFAULT 0.00,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `purchase_date` date DEFAULT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `supplier_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_no`, `part_no`, `qty`, `rate`, `amount`, `received_qty`, `purchase_date`, `invoice_no`, `status`, `supplier_id`, `plan_id`) VALUES
(1, 'PO-001', '22005001', 10, 0.00, 0.00, 0, '2026-01-04', NULL, 'closed', 1, NULL),
(2, 'PO-002', '22005231', 10, 0.00, 0.00, 0, '2026-01-05', NULL, 'closed', 1, NULL),
(3, '12', '32005012', 121, 0.00, 0.00, 0, '2026-01-06', NULL, 'closed', 1, NULL),
(4, 'PO005', '32005012', 5, 0.00, 0.00, 0, '2026-01-06', NULL, 'closed', 1, NULL),
(5, 'PO005', '32005012', 5, 0.00, 0.00, 0, '2026-01-06', NULL, 'closed', 1, NULL),
(6, 'PO-006', '22005698', 5, 0.00, 0.00, 0, '2026-01-06', NULL, 'closed', 1, NULL),
(7, 'PO-7', '22005231', 10, 0.00, 0.00, 0, '2026-01-07', NULL, 'closed', 1, NULL),
(8, 'PO-7', '22005698', 10, 0.00, 0.00, 0, '2026-01-07', NULL, 'closed', 1, NULL),
(9, 'PO-8', '22005231', 10, 0.00, 0.00, 0, '2026-01-07', NULL, 'closed', 1, NULL),
(10, 'PO-9', '95005006', 2, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 3, NULL),
(11, 'PO-10', '22005458', 10, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 1, NULL),
(12, 'PO-11', '62005022', 2, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 1, NULL),
(13, 'PO-11', '22005012', 5, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 1, NULL),
(14, 'PO-11', '22005458', 2, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 1, NULL),
(15, 'PO-12', '62005019', 5, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 2, NULL),
(16, 'PO-13', '22005231', 5, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 1, NULL),
(17, 'PO-14', '22005519', 5, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 1, NULL),
(18, 'PO-15', '62005022', 5, 0.00, 0.00, 0, '2026-01-08', NULL, 'closed', 2, NULL),
(19, 'PO-16', '62005022', 5, 0.00, 0.00, 0, '2026-01-09', NULL, 'closed', 2, NULL),
(20, 'PO-17', '95005006', 5, 0.00, 0.00, 0, '2026-01-09', NULL, 'closed', 3, NULL),
(21, 'PO-18', '50520020', 1, 0.00, 0.00, 0, '2026-01-14', NULL, 'closed', 1, NULL),
(22, 'PO-19', '95005006', 5, 0.00, 0.00, 0, '2026-01-15', NULL, 'closed', 2, NULL),
(23, 'PO-19', '22005458', 4, 0.00, 0.00, 0, '2026-01-15', NULL, 'closed', 2, NULL),
(24, 'PO-20', '95005006', 1, 0.00, 0.00, 0, '2026-01-15', NULL, 'closed', 1, NULL),
(25, 'PO-21', '62005022', 6, 0.00, 0.00, 0, '2026-01-18', NULL, 'closed', 1, NULL),
(26, 'PO-22', '62005022', 5, 0.00, 0.00, 0, '2026-01-18', NULL, 'closed', 1, NULL),
(27, 'PO-23', '95005006', 5, 0.00, 0.00, 0, '2026-01-18', NULL, 'closed', 1, NULL),
(28, 'PO-23', '62005019', 4, 0.00, 0.00, 0, '2026-01-18', NULL, 'closed', 1, NULL),
(29, 'PO-24', '95005006', 12, 0.00, 0.00, 0, '2026-01-09', NULL, 'closed', 1, NULL),
(30, 'PO-24', '62005019', 5, 0.00, 0.00, 0, '2026-01-09', NULL, 'closed', 1, NULL),
(31, 'PO-25', 'YID-044', 1, 0.00, 0.00, 0, '2026-01-23', NULL, 'closed', 46, NULL),
(32, 'PO-26', 'YID-063', 1, 0.00, 0.00, 0, '2026-01-23', NULL, 'closed', 24, NULL),
(33, 'PO-27', '44005643', 1, 0.00, 0.00, 0, '2026-01-23', NULL, 'closed', 2, NULL),
(34, 'PO-28', '62005151', 2, 0.00, 0.00, 0, '2026-01-23', NULL, 'closed', 16, NULL),
(35, 'PO-29', 'YID -043', 2, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 46, NULL),
(36, 'PO-30', '11005089', 1, 12.00, 0.00, 0, '2026-01-24', NULL, 'closed', 5, NULL),
(37, 'PO-30', '11005099', 1, 12.00, 0.00, 0, '2026-01-24', NULL, 'closed', 5, NULL),
(38, 'PO-31', '11005089', 1, 12.00, 0.00, 0, '2026-01-24', NULL, 'closed', 5, NULL),
(39, 'PO-31', '11005099', 1, 12.00, 0.00, 0, '2026-01-24', NULL, 'closed', 5, NULL),
(40, 'PO-32', 'YID-063', 2, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 24, NULL),
(41, 'PO-33', 'YID-044', 2, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 46, NULL),
(42, 'PO-34', 'YID-063', 3, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 24, NULL),
(43, 'PO-35', 'YID-044', 2, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 46, NULL),
(44, 'PO-36', '44005643', 1, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 2, NULL),
(45, 'PO-37', 'YID-063', 3, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 24, NULL),
(46, 'PO-38', 'YID-044', 5, 0.00, 0.00, 0, '2026-01-24', NULL, 'closed', 46, NULL),
(47, 'PO-39', 'YID -043', 1, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 46, NULL),
(48, 'PO-40', '44005643', 1, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 2, NULL),
(49, 'PO-41', 'YID-063', 1, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 24, NULL),
(50, 'PO-42', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-25', NULL, 'open', 46, NULL),
(51, 'PO-43', 'YID -043', 2, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 46, NULL),
(52, 'PO-44', 'YID-063', 1, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 24, NULL),
(53, 'PO-45', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-25', NULL, 'open', 46, NULL),
(54, 'PO-46', 'YID-008', 1, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 4, NULL),
(55, 'PO-47', 'YID -043', 2, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 46, NULL),
(56, 'PO-48', 'YID -043', 2, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 46, NULL),
(57, 'PO-49', '44005643', 1, 0.00, 0.00, 0, '2026-01-25', NULL, 'closed', 2, NULL),
(58, 'PO-50', 'YID-063', 0, 0.00, 0.00, 0, '2026-01-25', NULL, 'open', 24, NULL),
(59, 'PO-51', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-25', NULL, 'open', 46, NULL),
(60, 'PO-52', '44005643', 0, 0.00, 0.00, 0, '2026-01-26', NULL, 'open', 2, NULL),
(61, 'PO-53', 'YID-063', 0, 0.00, 0.00, 0, '2026-01-26', NULL, 'open', 24, NULL),
(62, 'PO-54', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-26', NULL, 'open', 46, NULL),
(63, 'PO-55', '11005089', 1, 24.00, 0.00, 0, '2026-01-27', NULL, 'open', 18, NULL),
(64, 'PO-55', '11005143', 1, 15.00, 0.00, 0, '2026-01-27', NULL, 'open', 18, NULL),
(65, 'PO-56', '11005089', 1, 24.00, 0.00, 0, '2026-01-27', NULL, 'open', 18, NULL),
(66, 'PO-56', '11005143', 1, 15.00, 0.00, 0, '2026-01-27', NULL, 'open', 18, NULL),
(67, 'PO-57', '11005096', 1, 25.00, 0.00, 0, '2026-01-28', NULL, 'open', 4, NULL),
(68, 'PO-58', '17005066', 1, 4.00, 0.00, 0, '2026-01-28', NULL, 'open', 48, NULL),
(69, 'PO-59', '11005096', 1, 25.00, 0.00, 0, '2026-01-28', NULL, 'open', 4, NULL),
(70, 'PO-60', '17005066', 1, 4.00, 0.00, 0, '2026-01-28', NULL, 'open', 48, NULL),
(71, 'PO-61', '11005089', 1, 5.00, 0.00, 0, '2026-01-28', NULL, 'open', 4, NULL),
(72, 'PO-61', '11005096', 1, 25.00, 0.00, 0, '2026-01-28', NULL, 'open', 4, NULL),
(73, 'PO-62', '11005099', 1, 12.00, 0.00, 0, '2026-01-28', NULL, 'open', 5, NULL),
(74, 'PO-63', '17005066', 1, 4.00, 0.00, 0, '2026-01-28', NULL, 'closed', 48, NULL),
(75, 'PO-64', '11005089', 1, 5.00, 0.00, 0, '2026-01-28', NULL, 'closed', 4, NULL),
(76, 'PO-65', '11005099', 1, 12.00, 0.00, 0, '2026-01-28', NULL, 'closed', 5, NULL),
(77, 'PO-66', '11005096', 1, 21.00, 0.00, 0, '2026-01-28', NULL, 'closed', 16, NULL),
(78, 'PO-67', '17005066', 1, 4.00, 0.00, 0, '2026-01-28', NULL, 'closed', 48, NULL),
(79, 'PO-68', 'YID-008', 1, 0.00, 0.00, 0, '2026-01-28', NULL, 'closed', 4, NULL),
(80, 'PO-69', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 46, NULL),
(81, 'PO-70', 'YID-008', 1, 0.00, 0.00, 0, '2026-01-28', NULL, 'closed', 4, NULL),
(82, 'PO-71', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 46, NULL),
(83, 'PO-72', 'YID-008', 1, 0.00, 0.00, 0, '2026-01-28', NULL, 'closed', 4, NULL),
(84, 'PO-73', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 46, NULL),
(85, 'PO-74', 'YID-008', 1, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 4, NULL),
(86, 'PO-75', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 46, NULL),
(87, 'PO-76', 'YID-008', 1, 0.00, 0.00, 0, '2026-01-28', NULL, 'closed', 4, NULL),
(88, 'PO-77', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 46, NULL),
(89, 'PO-78', '44005643', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 2, NULL),
(90, 'PO-79', 'YID-008', 2, 0.00, 0.00, 0, '2026-01-28', NULL, 'closed', 4, NULL),
(91, 'PO-80', 'YID-063', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 24, NULL),
(92, 'PO-81', 'YID-044', 0, 0.00, 0.00, 0, '2026-01-28', NULL, 'open', 46, NULL),
(93, 'PO-82', '11005143', 1, 15.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, 27),
(94, 'PO-83', '62005137', 1, 250.00, 0.00, 0, '2026-01-29', NULL, 'open', 40, 27),
(95, 'PO-84', '11005089', 1, 5.00, 0.00, 0, '2026-01-29', NULL, 'closed', 4, NULL),
(96, 'PO-84', '11005096', 1, 25.00, 0.00, 0, '2026-01-29', NULL, 'closed', 4, NULL),
(97, 'PO-84', 'YID-008', 1, 240000.00, 0.00, 0, '2026-01-29', NULL, 'closed', 4, NULL),
(98, 'PO-84', '11005099', 1, 15.00, 0.00, 0, '2026-01-29', NULL, 'closed', 4, NULL),
(99, 'PO-85', '62005265', 1, 250.00, 0.00, 0, '2026-01-29', NULL, 'closed', 4, NULL),
(100, 'PO-86', '95005002', 2, 9000.00, 0.00, 0, '2026-01-29', NULL, 'closed', 5, NULL),
(101, 'PO-87', '17005068', 1, 10.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 9, 28),
(102, 'PO-88', '11005143', 1, 15.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, 28),
(103, 'PO-89', '17005011', 1, 250.00, 0.00, 0, '2026-01-29', NULL, 'open', 23, 28),
(104, 'PO-90', '62005137', 1, 250.00, 0.00, 0, '2026-01-29', NULL, 'open', 40, 28),
(105, 'PO-91', '11005143', 1, 15.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, 29),
(106, 'PO-92', '62005137', 1, 250.00, 0.00, 0, '2026-01-29', NULL, 'open', 40, 29),
(107, 'PO-93', '17005068', 1, 10.00, 0.00, 0, '2026-01-29', NULL, 'open', 9, 30),
(108, 'PO-94', '17005011', 1, 250.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 23, 30),
(109, 'PO-95', '17005068', 1, 10.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 9, 31),
(110, 'PO-96', '17005011', 1, 250.00, 0.00, 0, '2026-01-29', NULL, 'closed', 23, 31),
(111, 'PO-97', '62005004', 1, 35.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 4, NULL),
(112, 'PO-97', '62005003', 1, 1.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 4, NULL),
(113, 'PO-97', '62005099', 1, 4900.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 4, NULL),
(114, 'PO-98', '62005004', 1, 35.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 14, NULL),
(115, 'PO-98', '62005003', 1, 1.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 14, NULL),
(116, 'PO-98', '62005099', 1, 4900.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 14, NULL),
(117, 'PO-99', '62005003', 1, 15.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 4, NULL),
(118, 'PO-100', '62005003', 1, 10.00, 0.00, 0, '2026-01-29', NULL, 'closed', 14, NULL),
(119, 'PO-101', '62005003', 1, 15.00, 0.00, 0, '2026-01-29', NULL, 'closed', 4, NULL),
(120, 'PO-102', '82005506', 10, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(121, 'PO-103', '82005506', 5, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(122, 'PO-104', '82005506', 1, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(123, 'PO-105', '82005506', 1, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(124, 'PO-106', '82005506', 1, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(125, 'PO-107', '82005506', 1, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(126, 'PO-108', '82005506', 1, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(127, 'PO-109', '82005506', 1, 2900.00, 0.00, 0, '2026-01-29', NULL, 'open', 45, NULL),
(128, 'PO-110', '11005089', 1, 24.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(129, 'PO-111', '11005089', 1, 24.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(130, 'PO-111', '11005143', 1, 15.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(131, 'PO-111', '11005120', 1, 25.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(132, 'PO-112', '11005089', 1, 24.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(133, 'PO-113', '11005089', 1, 24.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(134, 'PO-114', '11005120', 1, 25.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(135, 'PO-115', '11005089', 1, 24.00, 0.00, 0, '2026-01-29', NULL, 'open', 18, NULL),
(136, 'PO-116', '11005122', 1, 100.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 18, NULL),
(137, 'PO-117', '11005123', 1, 500.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 18, NULL),
(138, 'PO-118', '11005123', 1, 500.00, 0.00, 0, '2026-01-29', NULL, 'cancelled', 18, NULL),
(139, 'PO-119', '62005004', 1, 35.00, 0.00, 0, '2026-01-31', NULL, 'closed', 4, NULL),
(140, 'PO-119', '62005003', 1, 15.00, 0.00, 0, '2026-01-31', NULL, 'closed', 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `qc_issue_actions`
--

CREATE TABLE `qc_issue_actions` (
  `id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `action_no` int(11) NOT NULL,
  `action_type` enum('Containment','Corrective','Preventive','Verification','Investigation','Other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `assigned_to_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `priority` enum('Critical','High','Medium','Low') DEFAULT 'Medium',
  `start_date` date DEFAULT NULL,
  `target_date` date NOT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','Verified','Overdue','Cancelled') DEFAULT 'Pending',
  `completion_percentage` int(11) DEFAULT 0,
  `verification_required` tinyint(1) DEFAULT 1,
  `verified_by` varchar(100) DEFAULT NULL,
  `verification_date` date DEFAULT NULL,
  `verification_remarks` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `evidence_attached` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qc_issue_attachments`
--

CREATE TABLE `qc_issue_attachments` (
  `id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `action_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qc_issue_categories`
--

CREATE TABLE `qc_issue_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_type` enum('Field','Internal','Both') DEFAULT 'Both',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_issue_categories`
--

INSERT INTO `qc_issue_categories` (`id`, `category_name`, `category_type`, `description`, `is_active`, `sort_order`) VALUES
(1, 'Dimensional Out of Spec', 'Both', NULL, 1, 1),
(2, 'Surface Defect', 'Both', NULL, 1, 2),
(3, 'Functional Failure', 'Both', NULL, 1, 3),
(4, 'Material Defect', 'Both', NULL, 1, 4),
(5, 'Assembly Error', 'Internal', NULL, 1, 5),
(6, 'Packaging Damage', 'Both', NULL, 1, 6),
(7, 'Missing Parts', 'Both', NULL, 1, 7),
(8, 'Wrong Part', 'Both', NULL, 1, 8),
(9, 'Documentation Error', 'Both', NULL, 1, 9),
(10, 'Installation Issue', 'Field', NULL, 1, 10),
(11, 'Performance Issue', 'Field', NULL, 1, 11),
(12, 'Premature Failure', 'Field', NULL, 1, 12),
(13, 'Noise/Vibration', 'Field', NULL, 1, 13),
(14, 'Cosmetic Issue', 'Both', NULL, 1, 14),
(15, 'Process Deviation', 'Internal', NULL, 1, 15),
(16, 'Dimensional Out of Spec', 'Both', NULL, 1, 1),
(17, 'Surface Defect', 'Both', NULL, 1, 2),
(18, 'Functional Failure', 'Both', NULL, 1, 3),
(19, 'Material Defect', 'Both', NULL, 1, 4),
(20, 'Assembly Error', 'Internal', NULL, 1, 5),
(21, 'Packaging Damage', 'Both', NULL, 1, 6),
(22, 'Missing Parts', 'Both', NULL, 1, 7),
(23, 'Wrong Part', 'Both', NULL, 1, 8),
(24, 'Documentation Error', 'Both', NULL, 1, 9),
(25, 'Installation Issue', 'Field', NULL, 1, 10),
(26, 'Performance Issue', 'Field', NULL, 1, 11),
(27, 'Premature Failure', 'Field', NULL, 1, 12),
(28, 'Noise/Vibration', 'Field', NULL, 1, 13),
(29, 'Cosmetic Issue', 'Both', NULL, 1, 14),
(30, 'Process Deviation', 'Internal', NULL, 1, 15),
(31, 'Dimensional Out of Spec', 'Both', NULL, 1, 1),
(32, 'Surface Defect', 'Both', NULL, 1, 2),
(33, 'Functional Failure', 'Both', NULL, 1, 3),
(34, 'Material Defect', 'Both', NULL, 1, 4),
(35, 'Assembly Error', 'Internal', NULL, 1, 5),
(36, 'Packaging Damage', 'Both', NULL, 1, 6),
(37, 'Missing Parts', 'Both', NULL, 1, 7),
(38, 'Wrong Part', 'Both', NULL, 1, 8),
(39, 'Documentation Error', 'Both', NULL, 1, 9),
(40, 'Installation Issue', 'Field', NULL, 1, 10),
(41, 'Performance Issue', 'Field', NULL, 1, 11),
(42, 'Premature Failure', 'Field', NULL, 1, 12),
(43, 'Noise/Vibration', 'Field', NULL, 1, 13),
(44, 'Cosmetic Issue', 'Both', NULL, 1, 14),
(45, 'Process Deviation', 'Internal', NULL, 1, 15),
(46, 'Dimensional Out of Spec', 'Both', NULL, 1, 1),
(47, 'Surface Defect', 'Both', NULL, 1, 2),
(48, 'Functional Failure', 'Both', NULL, 1, 3),
(49, 'Material Defect', 'Both', NULL, 1, 4),
(50, 'Assembly Error', 'Internal', NULL, 1, 5),
(51, 'Packaging Damage', 'Both', NULL, 1, 6),
(52, 'Missing Parts', 'Both', NULL, 1, 7),
(53, 'Wrong Part', 'Both', NULL, 1, 8),
(54, 'Documentation Error', 'Both', NULL, 1, 9),
(55, 'Installation Issue', 'Field', NULL, 1, 10),
(56, 'Performance Issue', 'Field', NULL, 1, 11),
(57, 'Premature Failure', 'Field', NULL, 1, 12),
(58, 'Noise/Vibration', 'Field', NULL, 1, 13),
(59, 'Cosmetic Issue', 'Both', NULL, 1, 14),
(60, 'Process Deviation', 'Internal', NULL, 1, 15);

-- --------------------------------------------------------

--
-- Table structure for table `qc_issue_comments`
--

CREATE TABLE `qc_issue_comments` (
  `id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `comment_type` enum('Update','Status Change','Escalation','Note','Question','Answer') DEFAULT 'Update',
  `comment` text NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qc_quality_issues`
--

CREATE TABLE `qc_quality_issues` (
  `id` int(11) NOT NULL,
  `issue_no` varchar(50) NOT NULL,
  `issue_type` enum('Field Issue','Internal Issue','Customer Complaint','Supplier Issue','Process Issue') NOT NULL,
  `issue_source` enum('Customer','Internal Inspection','Production','Warehouse','Shipping','Installation','Service','Audit','Other') DEFAULT 'Internal Inspection',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` enum('Dimensional','Visual','Functional','Material','Packaging','Documentation','Process','Safety','Other') DEFAULT 'Other',
  `part_no` varchar(100) DEFAULT NULL,
  `lot_no` varchar(100) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `work_order_no` varchar(100) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL COMMENT 'Where issue was found',
  `detection_stage` enum('Incoming','In-Process','Final Inspection','Packing','Shipping','Installation','Field','Customer Use') DEFAULT 'In-Process',
  `qty_affected` int(11) DEFAULT 0,
  `qty_scrapped` int(11) DEFAULT 0,
  `qty_reworked` int(11) DEFAULT 0,
  `priority` enum('Critical','High','Medium','Low') DEFAULT 'Medium',
  `severity` enum('Critical','Major','Minor','Observation') DEFAULT 'Major',
  `cost_impact` decimal(12,2) DEFAULT 0.00,
  `cost_of_quality` decimal(12,2) DEFAULT 0.00 COMMENT 'Total cost including rework, scrap, customer claims',
  `issue_date` date NOT NULL,
  `target_closure_date` date DEFAULT NULL,
  `actual_closure_date` date DEFAULT NULL,
  `reported_by` varchar(100) DEFAULT NULL,
  `reported_by_id` int(11) DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `assigned_to_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('Open','Analysis','Action Required','In Progress','Verification','Closed','Cancelled') DEFAULT 'Open',
  `root_cause` text DEFAULT NULL,
  `root_cause_category` enum('Man','Machine','Method','Material','Measurement','Environment','Other') DEFAULT NULL,
  `why_analysis` text DEFAULT NULL COMMENT '5 Why analysis',
  `containment_action` text DEFAULT NULL,
  `containment_date` date DEFAULT NULL,
  `containment_verified` tinyint(1) DEFAULT 0,
  `parent_issue_id` int(11) DEFAULT NULL,
  `related_ncr_no` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_quality_issues`
--

INSERT INTO `qc_quality_issues` (`id`, `issue_no`, `issue_type`, `issue_source`, `title`, `description`, `category`, `part_no`, `lot_no`, `serial_no`, `work_order_no`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `project_id`, `location`, `detection_stage`, `qty_affected`, `qty_scrapped`, `qty_reworked`, `priority`, `severity`, `cost_impact`, `cost_of_quality`, `issue_date`, `target_closure_date`, `actual_closure_date`, `reported_by`, `reported_by_id`, `assigned_to`, `assigned_to_id`, `department`, `status`, `root_cause`, `root_cause_category`, `why_analysis`, `containment_action`, `containment_date`, `containment_verified`, `parent_issue_id`, `related_ncr_no`, `created_by`, `created_at`, `updated_at`, `closed_by`) VALUES
(1, 'QI-202602-0001', 'Field Issue', 'Service', 'Battery not charging', 'ddd', 'Functional', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Customer Use', 1, 1, 0, 'High', 'Major', 3500.00, 0.00, '2026-02-01', '2026-02-08', NULL, 'Vikram  Popat Pawar', 8, 'Vikram  Popat Pawar', 8, 'Service', 'Open', 'nil', 'Material', 'battery nor charging', 'replacement', NULL, 0, NULL, NULL, 1, '2026-02-01 01:51:36', '2026-02-01 01:51:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `qms_capa`
--

CREATE TABLE `qms_capa` (
  `id` int(11) NOT NULL,
  `capa_no` varchar(50) NOT NULL,
  `capa_type` enum('Corrective','Preventive') NOT NULL,
  `source` enum('NCR','Customer Complaint','Audit Finding','Process Deviation','Risk Assessment','Management Decision','Other') NOT NULL,
  `source_reference` varchar(100) DEFAULT NULL,
  `priority` enum('Critical','High','Medium','Low') DEFAULT 'Medium',
  `problem_description` text NOT NULL,
  `affected_area` varchar(255) DEFAULT NULL,
  `risk_assessment` text DEFAULT NULL,
  `root_cause_analysis` text DEFAULT NULL,
  `root_cause_method` enum('5 Why','Fishbone','Fault Tree','FMEA','Other') DEFAULT '5 Why',
  `proposed_action` text NOT NULL,
  `implementation_plan` text DEFAULT NULL,
  `responsible_person` varchar(100) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `status` enum('Initiated','Investigation','Action Planned','Implementation','Verification','Closed','Cancelled') DEFAULT 'Initiated',
  `effectiveness_criteria` text DEFAULT NULL,
  `effectiveness_result` text DEFAULT NULL,
  `verified_by` varchar(100) DEFAULT NULL,
  `verification_date` date DEFAULT NULL,
  `closure_remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qms_cdsco_adverse_events`
--

CREATE TABLE `qms_cdsco_adverse_events` (
  `id` int(11) NOT NULL,
  `report_no` varchar(50) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `event_date` date NOT NULL,
  `report_date` date NOT NULL,
  `event_type` enum('Death','Life Threatening','Hospitalization','Disability','Intervention Required','Other Serious','Non-Serious') NOT NULL,
  `event_description` text NOT NULL,
  `patient_outcome` enum('Recovered','Recovering','Not Recovered','Fatal','Unknown') DEFAULT 'Unknown',
  `causality_assessment` enum('Certain','Probable','Possible','Unlikely','Unclassified','Pending') DEFAULT 'Pending',
  `corrective_action` text DEFAULT NULL,
  `reported_to_cdsco` enum('Yes','No','Pending') DEFAULT 'Pending',
  `cdsco_acknowledgement` varchar(100) DEFAULT NULL,
  `status` enum('Open','Under Investigation','Closed','Reported') DEFAULT 'Open',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qms_cdsco_licenses`
--

CREATE TABLE `qms_cdsco_licenses` (
  `id` int(11) NOT NULL,
  `license_type` enum('Manufacturing License','Import License','Wholesale License','Retail License','Test License','Loan License') NOT NULL,
  `license_no` varchar(100) DEFAULT NULL,
  `form_type` varchar(50) DEFAULT NULL,
  `facility_name` varchar(255) NOT NULL,
  `facility_address` text DEFAULT NULL,
  `products_covered` text DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Applied','Under Inspection','Approved','Rejected','Expired','Renewal Pending','Suspended') DEFAULT 'Applied',
  `issuing_authority` varchar(255) DEFAULT NULL,
  `inspector_name` varchar(100) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  `inspection_remarks` text DEFAULT NULL,
  `conditions` text DEFAULT NULL,
  `documents_path` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qms_cdsco_products`
--

CREATE TABLE `qms_cdsco_products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_category` enum('Medical Device','Diagnostic','Pharmaceutical','IVD','Implant','Other') NOT NULL,
  `risk_class` enum('Class A','Class B','Class C','Class D') NOT NULL,
  `registration_no` varchar(100) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Draft','Submitted','Under Review','Query Raised','Approved','Rejected','Expired','Renewed') DEFAULT 'Draft',
  `manufacturer` varchar(255) DEFAULT NULL,
  `authorized_agent` varchar(255) DEFAULT NULL,
  `intended_use` text DEFAULT NULL,
  `technical_specs` text DEFAULT NULL,
  `submission_date` date DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `documents_path` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qms_documents`
--

CREATE TABLE `qms_documents` (
  `id` int(11) NOT NULL,
  `doc_no` varchar(50) NOT NULL,
  `doc_type` enum('SOP','Work Instruction','Form','Template','Policy','Manual','Specification','Protocol','Report','Record','External') NOT NULL,
  `title` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `version` varchar(20) NOT NULL DEFAULT '1.0',
  `revision_date` date DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Draft','Under Review','Approved','Effective','Obsolete','Superseded') DEFAULT 'Draft',
  `author` varchar(100) DEFAULT NULL,
  `reviewer` varchar(100) DEFAULT NULL,
  `approver` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `change_description` text DEFAULT NULL,
  `distribution_list` text DEFAULT NULL,
  `training_required` enum('Yes','No') DEFAULT 'No',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qms_documents`
--

INSERT INTO `qms_documents` (`id`, `doc_no`, `doc_type`, `title`, `department`, `category`, `version`, `revision_date`, `effective_date`, `review_date`, `expiry_date`, `status`, `author`, `reviewer`, `approver`, `file_path`, `change_description`, `distribution_list`, `training_required`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'YID/QM/QMS/2022/001', 'Manual', 'Quality Manual', 'Management', '', '006', NULL, '2026-01-01', '2026-01-01', NULL, 'Effective', 'Neha Ukale', 'Dipankar Aich', 'Dipankar Aich', NULL, NULL, NULL, 'No', NULL, '2026-01-31 05:22:30', '2026-01-31 05:22:30');

-- --------------------------------------------------------

--
-- Table structure for table `qms_icmed_audits`
--

CREATE TABLE `qms_icmed_audits` (
  `id` int(11) NOT NULL,
  `certification_id` int(11) DEFAULT NULL,
  `audit_no` varchar(50) NOT NULL,
  `audit_type` enum('Initial','Surveillance','Renewal','Special','Unannounced') NOT NULL,
  `scheduled_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `auditor_name` varchar(100) DEFAULT NULL,
  `audit_team` text DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Postponed','Cancelled') DEFAULT 'Scheduled',
  `checklist_used` varchar(255) DEFAULT NULL,
  `areas_audited` text DEFAULT NULL,
  `major_nc` int(11) DEFAULT 0,
  `minor_nc` int(11) DEFAULT 0,
  `observations` int(11) DEFAULT 0,
  `audit_result` enum('Pass','Conditional Pass','Fail','Pending') DEFAULT 'Pending',
  `corrective_actions_due` date DEFAULT NULL,
  `follow_up_required` enum('Yes','No') DEFAULT 'No',
  `follow_up_date` date DEFAULT NULL,
  `report_path` varchar(500) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qms_icmed_certifications`
--

CREATE TABLE `qms_icmed_certifications` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `icmed_no` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_category` varchar(100) DEFAULT NULL,
  `device_class` enum('Class A','Class B','Class C','Class D') NOT NULL,
  `application_date` date DEFAULT NULL,
  `certification_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Application Submitted','Document Review','Factory Audit Scheduled','Factory Audit Completed','Technical Review','Certified','Suspended','Withdrawn','Renewal Pending','Expired') DEFAULT 'Application Submitted',
  `certification_body` varchar(255) DEFAULT NULL,
  `auditor_name` varchar(100) DEFAULT NULL,
  `audit_date` date DEFAULT NULL,
  `audit_findings` text DEFAULT NULL,
  `nc_count` int(11) DEFAULT 0,
  `certificate_path` varchar(500) DEFAULT NULL,
  `renewal_application_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qms_iso_audits`
--

CREATE TABLE `qms_iso_audits` (
  `id` int(11) NOT NULL,
  `certification_id` int(11) DEFAULT NULL,
  `audit_no` varchar(50) NOT NULL,
  `audit_type` enum('Internal','External','Supplier','Customer','Regulatory') NOT NULL,
  `audit_standard` varchar(100) DEFAULT NULL,
  `audit_scope` text DEFAULT NULL,
  `planned_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `lead_auditor` varchar(100) DEFAULT NULL,
  `audit_team` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('Planned','In Progress','Completed','Cancelled','Postponed') DEFAULT 'Planned',
  `major_nc` int(11) DEFAULT 0,
  `minor_nc` int(11) DEFAULT 0,
  `observations` int(11) DEFAULT 0,
  `opportunities` int(11) DEFAULT 0,
  `audit_report_path` varchar(500) DEFAULT NULL,
  `conclusion` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qms_iso_audits`
--

INSERT INTO `qms_iso_audits` (`id`, `certification_id`, `audit_no`, `audit_type`, `audit_standard`, `audit_scope`, `planned_date`, `actual_date`, `lead_auditor`, `audit_team`, `department`, `status`, `major_nc`, `minor_nc`, `observations`, `opportunities`, `audit_report_path`, `conclusion`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'AUD-2026-0001', 'External', 'ISO 13485', 'Design, Development, manufacture, marketing, sales, Installation and servicing of ICU and NICU ventilators', '2026-02-20', NULL, 'Prathmesh', '', 'All', 'Planned', 0, 0, 0, 0, NULL, NULL, NULL, '2026-01-31 05:18:45', '2026-01-31 05:18:45');

-- --------------------------------------------------------

--
-- Table structure for table `qms_iso_certifications`
--

CREATE TABLE `qms_iso_certifications` (
  `id` int(11) NOT NULL,
  `standard_code` varchar(50) NOT NULL,
  `standard_name` varchar(255) NOT NULL,
  `scope` text DEFAULT NULL,
  `certification_body` varchar(255) DEFAULT NULL,
  `certificate_no` varchar(100) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Planning','Implementation','Audit Scheduled','Certified','Suspended','Withdrawn','Renewal Due') DEFAULT 'Planning',
  `last_audit_date` date DEFAULT NULL,
  `next_audit_date` date DEFAULT NULL,
  `audit_type` enum('Initial','Surveillance','Recertification','Special') DEFAULT 'Initial',
  `findings_count` int(11) DEFAULT 0,
  `major_nc` int(11) DEFAULT 0,
  `minor_nc` int(11) DEFAULT 0,
  `observations` int(11) DEFAULT 0,
  `certificate_path` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qms_iso_certifications`
--

INSERT INTO `qms_iso_certifications` (`id`, `standard_code`, `standard_name`, `scope`, `certification_body`, `certificate_no`, `issue_date`, `expiry_date`, `status`, `last_audit_date`, `next_audit_date`, `audit_type`, `findings_count`, `major_nc`, `minor_nc`, `observations`, `certificate_path`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'ISO 9001:2015', 'Quality Management Systems', NULL, NULL, NULL, NULL, NULL, 'Planning', NULL, NULL, 'Initial', 0, 0, 0, 0, NULL, NULL, '2026-01-25 14:24:23', '2026-01-25 14:24:23'),
(2, 'ISO 13485:2016', 'Medical Devices - Quality Management Systems', NULL, NULL, NULL, NULL, NULL, 'Planning', NULL, NULL, 'Initial', 0, 0, 0, 0, NULL, NULL, '2026-01-25 14:24:23', '2026-01-25 14:24:23'),
(3, 'ISO 14001:2015', 'Environmental Management Systems', NULL, NULL, NULL, NULL, NULL, 'Planning', NULL, NULL, 'Initial', 0, 0, 0, 0, NULL, NULL, '2026-01-25 14:24:23', '2026-01-25 14:24:23'),
(4, 'ISO 45001:2018', 'Occupational Health and Safety', NULL, NULL, NULL, NULL, NULL, 'Planning', NULL, NULL, 'Initial', 0, 0, 0, 0, NULL, NULL, '2026-01-25 14:24:23', '2026-01-25 14:24:23'),
(5, 'ISO 22000:2018', 'Food Safety Management Systems', NULL, NULL, NULL, NULL, NULL, 'Planning', NULL, NULL, 'Initial', 0, 0, 0, 0, NULL, NULL, '2026-01-25 14:24:23', '2026-01-25 14:24:23');

-- --------------------------------------------------------

--
-- Table structure for table `qms_management_review`
--

CREATE TABLE `qms_management_review` (
  `id` int(11) NOT NULL,
  `review_no` varchar(50) NOT NULL,
  `review_date` date NOT NULL,
  `chairman` varchar(100) DEFAULT NULL,
  `attendees` text DEFAULT NULL,
  `agenda` text DEFAULT NULL,
  `previous_actions_status` text DEFAULT NULL,
  `audit_results_summary` text DEFAULT NULL,
  `customer_feedback_summary` text DEFAULT NULL,
  `process_performance` text DEFAULT NULL,
  `nc_capa_summary` text DEFAULT NULL,
  `resource_requirements` text DEFAULT NULL,
  `improvement_opportunities` text DEFAULT NULL,
  `risk_assessment` text DEFAULT NULL,
  `decisions` text DEFAULT NULL,
  `action_items` text DEFAULT NULL,
  `next_review_date` date DEFAULT NULL,
  `minutes_path` varchar(500) DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qms_ncr`
--

CREATE TABLE `qms_ncr` (
  `id` int(11) NOT NULL,
  `ncr_no` varchar(50) NOT NULL,
  `audit_id` int(11) DEFAULT NULL,
  `source` enum('Internal Audit','External Audit','Customer Complaint','Process Deviation','Supplier Issue','Management Review','Other') NOT NULL,
  `nc_type` enum('Major','Minor','Observation','Opportunity') NOT NULL,
  `clause_reference` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `evidence` text DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `immediate_action` text DEFAULT NULL,
  `corrective_action` text DEFAULT NULL,
  `preventive_action` text DEFAULT NULL,
  `responsible_person` varchar(100) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `closure_date` date DEFAULT NULL,
  `status` enum('Open','Action Planned','In Progress','Verification Pending','Closed','Reopened') DEFAULT 'Open',
  `effectiveness_verified` enum('Yes','No','Pending') DEFAULT 'Pending',
  `verified_by` varchar(100) DEFAULT NULL,
  `verification_date` date DEFAULT NULL,
  `verification_remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qms_ncr`
--

INSERT INTO `qms_ncr` (`id`, `ncr_no`, `audit_id`, `source`, `nc_type`, `clause_reference`, `department`, `description`, `evidence`, `root_cause`, `immediate_action`, `corrective_action`, `preventive_action`, `responsible_person`, `target_date`, `closure_date`, `status`, `effectiveness_verified`, `verified_by`, `verification_date`, `verification_remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'NCR-2026-00001', NULL, 'Internal Audit', 'Minor', 'ISO 13485', 'Production', 'Test Rigs are non functional', 'Battery test rig non functional', NULL, 'Test Rig Implementation', NULL, NULL, 'Vikram', '2026-02-02', NULL, 'Open', 'Pending', NULL, NULL, NULL, NULL, '2026-01-31 05:14:34', '2026-01-31 05:14:34');

-- --------------------------------------------------------

--
-- Table structure for table `qms_training`
--

CREATE TABLE `qms_training` (
  `id` int(11) NOT NULL,
  `training_code` varchar(50) NOT NULL,
  `training_title` varchar(255) NOT NULL,
  `training_type` enum('Induction','Procedure','Skill','Regulatory','Safety','Refresher','External') NOT NULL,
  `related_document_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `trainer_name` varchar(100) DEFAULT NULL,
  `training_date` date DEFAULT NULL,
  `duration_hours` decimal(5,2) DEFAULT NULL,
  `employee_ids` text DEFAULT NULL,
  `attendee_count` int(11) DEFAULT 0,
  `status` enum('Planned','Completed','Cancelled') DEFAULT 'Planned',
  `assessment_required` enum('Yes','No') DEFAULT 'No',
  `pass_criteria` varchar(255) DEFAULT NULL,
  `effectiveness_evaluation` text DEFAULT NULL,
  `training_material_path` varchar(500) DEFAULT NULL,
  `attendance_sheet_path` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quote_items`
--

CREATE TABLE `quote_items` (
  `id` int(11) NOT NULL,
  `quote_id` int(11) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `part_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `qty` decimal(15,3) NOT NULL DEFAULT 1.000,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(5,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_percent` decimal(5,2) DEFAULT 0.00,
  `igst_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `lead_time` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quote_items`
--

INSERT INTO `quote_items` (`id`, `quote_id`, `part_no`, `part_name`, `description`, `hsn_code`, `qty`, `unit`, `rate`, `discount`, `taxable_amount`, `cgst_percent`, `cgst_amount`, `sgst_percent`, `sgst_amount`, `igst_percent`, `igst_amount`, `total_amount`, `lead_time`) VALUES
(3, 1, '62005022', 'Aeroflex Valve DC 2/2', NULL, '', 1.000, 'Nos', 350.00, 11.00, 311.50, 9.00, 28.04, 9.00, 28.04, 0.00, 0.00, 367.57, ''),
(4, 2, '46005005', 'Assembly New', NULL, '', 1.000, 'Nos', 12.00, 0.00, 12.00, 1.50, 0.18, 1.50, 0.18, 0.00, 0.00, 12.36, ''),
(5, 3, '22005001', 'Quick Connector', NULL, '', 1.000, 'Nos', 250.00, 0.00, 250.00, 9.00, 22.50, 9.00, 22.50, 0.00, 0.00, 295.00, ''),
(6, 4, '44005643', 'Assembly Compressor', NULL, '', 1.000, 'nos', 457.00, 0.00, 457.00, 9.00, 41.13, 9.00, 41.13, 0.00, 0.00, 539.26, ''),
(8, 5, '22005519', 'Inlet Hex Fitting', NULL, '', 1.000, 'Nos', 120.00, 0.00, 120.00, 9.00, 10.80, 9.00, 10.80, 0.00, 0.00, 141.60, '2'),
(11, 6, 'YID-044', '12 Channel ECG Machine', NULL, '', 1.000, 'NOS', 48000.00, 0.00, 48000.00, 9.00, 4320.00, 9.00, 4320.00, 0.00, 0.00, 56640.00, ''),
(12, 6, 'YID-063', '5 Function Manual ICU Bed', NULL, '', 1.000, 'NOS', 15000.00, 1.00, 14850.00, 9.00, 1336.50, 9.00, 1336.50, 0.00, 0.00, 17523.00, ''),
(13, 7, 'YID-116', 'AED 8000 Defibrillator', NULL, '', 1.000, 'NOS', 0.00, 0.00, 0.00, 9.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0.00, ''),
(17, 9, 'YID-0124', 'AHEN 5000 (Export)', NULL, '', 1.000, 'NOS', 0.00, 0.00, 0.00, 9.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0.00, '5'),
(24, 10, 'YID-002', 'XVS 9500 XL.', NULL, '', 1.000, 'NOS', 520000.00, 30.00, 364000.00, 9.00, 32760.00, 9.00, 32760.00, 0.00, 0.00, 429520.00, '2'),
(27, 12, 'YID-063', '5 Function Manual ICU Bed', NULL, '', 1.000, 'NOS', 0.00, 0.00, 0.00, 9.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0.00, ''),
(28, 11, 'YID-044', '12 Channel ECG Machine', NULL, '', 1.000, 'NOS', 48000.00, 0.00, 48000.00, 9.00, 4320.00, 9.00, 4320.00, 0.00, 0.00, 56640.00, ''),
(29, 8, 'YID-044', '12 Channel ECG Machine', NULL, '', 1.000, 'NOS', 48000.00, 0.00, 48000.00, 9.00, 4320.00, 9.00, 4320.00, 0.00, 0.00, 56640.00, ''),
(30, 8, 'YID-063', '5 Function Manual ICU Bed', NULL, '', 1.000, 'NOS', 0.00, 0.00, 0.00, 9.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0.00, ''),
(32, 13, 'YID-017', 'AHEN 5000', NULL, '', 1.000, 'NOS', 520000.00, 0.00, 520000.00, 9.00, 46800.00, 9.00, 46800.00, 0.00, 0.00, 613600.00, ''),
(38, 15, 'YID -050', 'Portable', NULL, '341', 1.000, 'NOS', 5500.00, 0.00, 5500.00, 9.00, 495.00, 9.00, 495.00, 0.00, 0.00, 6490.00, ''),
(39, 14, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, ''),
(40, 16, 'YID-008', 'HVS 2500', NULL, '', 1.000, 'NOS', 360000.00, 0.00, 360000.00, 9.00, 32400.00, 9.00, 32400.00, 0.00, 0.00, 424800.00, ''),
(46, 19, 'YID-007', 'HVS 2500 XL', NULL, '', 1.000, 'NOS', 360000.00, 0.00, 360000.00, 9.00, 32400.00, 9.00, 32400.00, 0.00, 0.00, 424800.00, '1'),
(48, 20, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, '1'),
(49, 18, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, '1'),
(51, 21, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, ''),
(54, 22, 'YID-008', 'HVS 2500', NULL, '', 1.000, 'NOS', 360000.00, 0.00, 360000.00, 9.00, 32400.00, 9.00, 32400.00, 0.00, 0.00, 424800.00, '1'),
(56, 23, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, ''),
(58, 24, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, ''),
(59, 17, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, '1'),
(61, 25, 'YID-167', 'Lio smart-7.5', NULL, '90192090', 1.000, 'NOS', 100000.00, 0.00, 100000.00, 2.50, 2500.00, 2.50, 2500.00, 0.00, 0.00, 105000.00, '1'),
(63, 26, 'YID-167', 'Lio smart-7.5', NULL, '90192090', 1.000, 'NOS', 100000.00, 0.00, 100000.00, 2.50, 2500.00, 2.50, 2500.00, 0.00, 0.00, 105000.00, '1'),
(68, 27, 'YID-044', '12 Channel ECG Machine', NULL, '', 1.000, 'NOS', 48000.00, 0.00, 48000.00, 9.00, 4320.00, 9.00, 4320.00, 0.00, 0.00, 56640.00, ''),
(69, 27, 'YID-008', 'HVS 2500', NULL, '', 1.000, 'NOS', 360000.00, 0.00, 360000.00, 9.00, 32400.00, 9.00, 32400.00, 0.00, 0.00, 424800.00, ''),
(72, 30, 'YID-044', '12 Channel ECG Machine', NULL, '', 0.000, 'NOS', 48000.00, 0.00, 0.00, 9.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0.00, ''),
(73, 30, 'YID-008', 'HVS 2500', NULL, '', 0.000, 'NOS', 360000.00, 0.00, 0.00, 9.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0.00, ''),
(75, 31, 'YID-167', 'Lio smart-7.5', NULL, '90192090', 1.000, 'NOS', 100000.00, 0.00, 100000.00, 2.50, 2500.00, 2.50, 2500.00, 0.00, 0.00, 105000.00, ''),
(78, 33, 'YID-003', 'XVS 9500', NULL, '', 1.000, 'NOS', 480000.00, 0.00, 480000.00, 9.00, 43200.00, 9.00, 43200.00, 0.00, 0.00, 566400.00, ''),
(80, 34, 'YID-167', 'Lio smart-7.5', NULL, '90192090', 1.000, 'NOS', 100000.00, 0.00, 100000.00, 2.50, 2500.00, 2.50, 2500.00, 0.00, 0.00, 105000.00, ''),
(96, 54, 'YID-017', 'AHEN 5000', NULL, '', 1.000, 'NOS', 520000.00, 0.00, 520000.00, 9.00, 46800.00, 9.00, 46800.00, 0.00, 0.00, 613600.00, ''),
(98, 52, 'YID-012', 'Ventigo 77', NULL, '90192090', 1.000, 'NOS', 200000.00, 0.00, 200000.00, 2.50, 5000.00, 2.50, 5000.00, 0.00, 0.00, 210000.00, ''),
(99, 70, 'YID-027', 'Baby Warmer with Drawer', NULL, '', 1.000, 'NOS', 24000.00, 0.00, 24000.00, 9.00, 2160.00, 9.00, 2160.00, 0.00, 0.00, 28320.00, ''),
(100, 70, 'YID-105', 'Moppet 8.5', NULL, '', 1.000, 'NOS', 0.00, 0.00, 0.00, 2.50, 0.00, 2.50, 0.00, 0.00, 0.00, 0.00, ''),
(102, 72, 'YID -043', 'ECG 6 Channel', NULL, '1234', 1.000, 'NOS', 40000.00, 0.00, 40000.00, 9.00, 3600.00, 9.00, 3600.00, 0.00, 0.00, 47200.00, ''),
(105, 74, 'YID-105', 'Moppet 8.5', NULL, '', 1.000, 'NOS', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(108, 76, 'YID-044', '12 Channel ECG Machine', NULL, '', 1.000, 'NOS', 48000.00, 0.00, 48000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 48000.00, ''),
(110, 75, 'YID-044', '12 Channel ECG Machine', '', '', 1.000, 'NOS', 48000.00, 0.00, 48000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 48000.00, ''),
(111, 73, 'YID-008', 'HVS 2500', '', '', 1.000, 'NOS', 360000.00, 0.00, 360000.00, 9.00, 32400.00, 9.00, 32400.00, 0.00, 0.00, 424800.00, ''),
(112, 77, 'YID-044', '12 Channel ECG Machine', '', '', 0.000, 'NOS', 48000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(115, 79, 'YID-044', '12 Channel ECG Machine', 'Sale Finish Good', '', 1.000, 'NOS', 48000.00, 0.00, 48000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 48000.00, ''),
(116, 78, 'YID-044', '12 Channel ECG Machine', '', '', 0.000, 'NOS', 48000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(118, 80, 'YID-044', '12 Channel ECG Machine', '', '', 0.000, 'NOS', 48000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(119, 81, 'YID-044', '12 Channel ECG Machine', '', '', 0.000, 'NOS', 48000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 18.00, 0.00, 0.00, ''),
(122, 82, 'YID-012', 'Ventigo 77', '', '90192090', 1.000, 'NOS', 200000.00, 0.00, 200000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 200000.00, ''),
(123, 82, 'YID-016', 'Moppet 6.5', '', '', 1.000, 'NOS', 100000.00, 0.00, 100000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100000.00, ''),
(125, 83, 'YID-017', 'AHEN 5000', '', '', 1.000, 'NOS', 520000.00, 0.00, 520000.00, 9.00, 46800.00, 9.00, 46800.00, 0.00, 0.00, 613600.00, ''),
(127, 84, 'YID-019', 'AHEN 4000', '', '', 1.000, 'NOS', 285000.00, 0.00, 285000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 285000.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `quote_master`
--

CREATE TABLE `quote_master` (
  `id` int(11) NOT NULL,
  `quote_no` varchar(50) NOT NULL,
  `pi_no` varchar(50) DEFAULT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `quote_date` date NOT NULL,
  `validity_date` date DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `payment_terms_id` int(11) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected','expired','released') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `released_at` datetime DEFAULT NULL,
  `is_igst` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quote_master`
--

INSERT INTO `quote_master` (`id`, `quote_no`, `pi_no`, `customer_id`, `reference`, `quote_date`, `validity_date`, `terms_conditions`, `notes`, `payment_details`, `payment_terms_id`, `attachment_path`, `status`, `created_at`, `updated_at`, `released_at`, `is_igst`) VALUES
(1, '1/25/26', 'PI/1/25/26', 'CUST-4', 'Dr.  Bala', '2026-01-15', '2026-02-14', 'all', 'all', 'ICIC', NULL, NULL, 'released', '2026-01-15 14:06:46', '2026-01-17 18:07:25', '2026-01-17 23:37:25', 0),
(2, '2/25/26', 'PI/2/25/26', 'CUST-5', 'referehnce from gour  hospital', '2026-01-18', '2026-02-17', 'advance payment', '4 days', '', NULL, NULL, 'released', '2026-01-18 03:14:49', '2026-01-18 03:15:11', '2026-01-18 08:45:11', 0),
(3, '3/25/26', 'PI/4/25/26', 'CUST-2', 'aesdf', '2026-01-18', '2026-02-17', 'asdf', 'asdf', 'asdf', NULL, 'uploads/quotes/QUOTE_3_25_26_1768707358.pdf', 'released', '2026-01-18 03:35:58', '2026-01-18 15:40:58', '2026-01-18 21:10:58', 0),
(4, '4/25/26', 'PI/3/25/26', 'CUST-5', '343', '2026-01-18', '2026-02-17', 'ff', 'dc', 'sdd', NULL, NULL, 'released', '2026-01-18 10:16:19', '2026-01-18 10:16:51', '2026-01-18 15:46:51', 0),
(5, '5/25/26', 'PI/5/25/26', 'CUST-3', 'dr djjj', '2026-01-21', '2026-02-20', 'dd', 'sd', 'sd', NULL, NULL, 'released', '2026-01-21 04:57:10', '2026-01-21 05:01:24', '2026-01-21 10:31:24', 0),
(6, '6/25/26', 'PI/6/25/26', 'CUST-28', 'LEAD-4', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 01:24:21', '2026-01-23 01:25:19', '2026-01-23 06:55:19', 0),
(7, '7/25/26', 'PI/7/25/26', 'CUST-28', 'LEAD-5', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 02:10:55', '2026-01-23 02:11:00', '2026-01-23 07:41:00', 0),
(8, '8/25/26', 'PI/12/25/26', 'CUST-64', 'LEAD-6', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 03:38:29', '2026-01-23 10:52:01', '2026-01-23 16:22:01', 0),
(9, '9/25/26', 'PI/8/25/26', 'CUST-30', 'LEAD-7', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 03:38:52', '2026-01-23 04:15:40', '2026-01-23 09:45:40', 0),
(10, '10/25/26', 'PI/10/25/26', 'CUST-12', 'LEAD-9', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 10:40:46', '2026-01-23 10:45:34', '2026-01-23 16:15:34', 0),
(11, '11/25/26', 'PI/11/25/26', 'CUST-64', 'LEAD-10', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 10:48:53', '2026-01-23 10:50:58', '2026-01-23 16:20:58', 0),
(12, '12/25/26', 'PI/10/25/26', 'CUST-28', 'LEAD-11', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 10:49:17', '2026-01-23 10:49:46', '2026-01-23 16:19:46', 0),
(13, '13/25/26', 'PI/13/25/26', 'CUST-55', 'LEAD-8', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', NULL, NULL, 'released', '2026-01-23 10:52:43', '2026-01-23 10:52:58', '2026-01-23 16:22:58', 0),
(14, '14/25/26', 'PI/16/25/26', 'CUST-6', 'LEAD-2', '2026-01-23', '2026-02-22', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-23 18:00:40', '2026-01-24 00:39:33', '2026-01-24 06:09:33', 0),
(15, '15/25/26', 'PI/15/25/26', 'CUST-6', 'LEAD-1', '2026-01-24', '2026-02-23', 'Njcndf', 'ddd', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-24 00:23:33', '2026-01-24 00:38:31', '2026-01-24 06:08:31', 0),
(16, '16/25/26', NULL, 'CUST-52', 'LEAD-12', '2026-01-24', '2026-02-23', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'draft', '2026-01-24 06:15:46', '2026-01-24 06:15:46', NULL, 0),
(17, '17/25/26', 'PI/23/25/26', 'CUST-52', 'LEAD-12', '2026-01-24', '2026-02-23', 'Njcndf', 'aa', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-24 08:16:41', '2026-01-25 10:47:45', '2026-01-25 16:17:45', 0),
(18, '18/25/26', 'PI/18/25/26', 'CUST-55', 'LEAD-13', '2026-01-24', '2026-02-23', 'Njcndf', 'asss', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-24 12:17:52', '2026-01-24 16:43:53', '2026-01-24 22:13:53', 0),
(19, '19/25/26', 'PI/16/25/26', 'CUST-65', 'LEAD-16', '2026-01-24', '2026-02-23', 'Njcndf', 'test', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-24 12:22:46', '2026-01-24 12:33:20', '2026-01-24 18:03:20', 0),
(20, '20/25/26', 'PI/17/25/26', 'CUST-46', 'LEAD-14', '2026-01-24', '2026-02-23', '1. No Return Policy\r\n2. 6 months Service \r\n3. 1 year Warranty', 'Before Purchase take a demo', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-24 14:17:20', '2026-01-24 14:21:40', '2026-01-24 19:51:40', 0),
(21, '21/25/26', 'PI/19/25/26', 'CUST-28', 'LEAD-18', '2026-01-25', '2026-02-24', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-25 03:18:57', '2026-01-25 03:20:23', '2026-01-25 08:50:23', 0),
(22, '22/25/26', 'PI/20/25/26', 'CUST-64', 'LEAD-19', '2026-01-25', '2026-02-24', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-25 05:09:49', '2026-01-25 05:11:43', '2026-01-25 10:41:43', 0),
(23, '23/25/26', 'PI/21/25/26', 'CUST-66', 'LEAD-20', '2026-01-25', '2026-02-24', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-25 09:02:02', '2026-01-25 09:09:54', '2026-01-25 14:39:54', 0),
(24, '24/25/26', 'PI/22/25/26', 'CUST-52', 'LEAD-21', '2026-01-25', '2026-02-24', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-25 09:20:46', '2026-01-25 09:21:19', '2026-01-25 14:51:19', 0),
(25, '25/25/26', 'PI/24/25/26', 'CUST-12', 'LEAD-22', '2026-01-26', '2026-02-25', 'Njcndf', 'test', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-26 03:16:08', '2026-01-26 03:16:40', '2026-01-26 08:46:40', 0),
(26, '26/25/26', 'PI/25/25/26', 'CUST-28', 'LEAD-23', '2026-01-26', '2026-02-25', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-26 16:53:10', '2026-01-26 16:53:42', '2026-01-26 22:23:42', 0),
(27, '27/25/26', NULL, 'CUST-28', 'LEAD-3', '2026-01-27', '2026-02-26', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-27 08:31:32', '2026-01-27 08:38:10', NULL, 0),
(30, '28/25/26', 'PI/26/25/26', 'CUST-28', 'LEAD-24', '2026-01-27', '2026-02-26', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-27 08:48:46', '2026-01-27 08:49:13', '2026-01-27 14:19:13', 0),
(31, '29/25/26', 'PI/27/25/26', 'CUST-64', 'LEAD-25', '2026-01-27', '2026-02-26', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-27 13:22:43', '2026-01-27 13:23:13', '2026-01-27 18:53:13', 0),
(33, '30/25/26', 'PI/28/25/26', 'CUST-72', 'LEAD-29', '2026-01-28', '2026-02-27', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 133244\r\nIFSC: 342212\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-28 13:49:29', '2026-01-28 13:50:42', '2026-01-28 19:20:42', 0),
(34, '31/25/26', NULL, 'CUST-22', 'LEAD-26', '2026-01-29', '2026-02-28', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-29 08:09:55', '2026-01-29 08:10:26', NULL, 0),
(52, '33/25/26', 'PI/29/25/26', 'CUST-5', 'LEAD-32', '2026-01-29', '2026-02-28', '100 % Advanced Payment\r\n1 We are providing 1 year warranty\r\n2.No return/refund Policy\r\n3.We will not be held responsible for any accidental loss due to theft, robbery, accidental mishap.\r\n4.If goods are damaged, the product replaced within 2 weeks', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-29 09:22:00', '2026-01-29 09:44:33', '2026-01-29 15:14:33', 0),
(54, '34/25/26', NULL, 'CUST-51', 'LEAD-34', '2026-01-29', '2026-02-28', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-29 09:22:36', '2026-01-29 09:35:02', NULL, 0),
(70, '35/25/26', NULL, 'CUST-73', 'LEAD-37', '2026-01-29', '2026-02-28', '100 % Advanced Payment\r\n1 We are providing 1 year warranty\r\n2.No return/refund Policy\r\n3.We will not be held responsible for any accidental loss due to theft, robbery, accidental mishap.\r\n4.If goods are damaged, the product replaced within 2 weeks', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'draft', '2026-01-29 09:56:37', '2026-01-29 09:56:37', NULL, 0),
(72, '37/25/26', NULL, 'CUST-55', 'LEAD-36', '2026-01-29', '2026-02-28', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'draft', '2026-01-29 09:59:35', '2026-01-29 09:59:35', NULL, 0),
(73, '38/25/26', NULL, 'CUST-45', 'LEAD-35', '2026-01-29', '2026-02-28', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-29 10:00:19', '2026-01-30 02:10:36', NULL, 0),
(74, '39/25/26', 'PI/30/25/26', 'CUST-14', 'LEAD-33', '2026-01-29', '2026-02-28', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-29 15:36:42', '2026-01-29 15:37:29', '2026-01-29 21:07:29', 1),
(75, '40/25/26', 'PI/32/25/26', 'CUST-55', 'LEAD-40', '2026-01-29', '2026-02-28', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-29 15:38:24', '2026-01-30 02:10:20', '2026-01-30 07:40:20', 1),
(76, '41/25/26', 'PI/31/25/26', 'CUST-12', 'LEAD-42', '2026-01-29', '2026-02-28', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-29 15:48:34', '2026-01-29 15:49:07', '2026-01-29 21:19:07', 1),
(77, '42/25/26', NULL, 'CUST-12', 'LEAD-44', '2026-01-30', '2026-03-01', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-30 01:33:53', '2026-01-30 02:10:52', NULL, 1),
(78, '43/25/26', NULL, 'CUST-46', 'LEAD-47', '2026-01-30', '2026-03-01', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-30 02:12:03', '2026-01-30 02:36:35', NULL, 1),
(79, '44/25/26', 'PI/33/25/26', 'CUST-11', 'LEAD-49', '2026-01-30', '2026-03-01', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-30 02:32:54', '2026-01-30 02:34:02', '2026-01-30 08:04:02', 1),
(80, '45/25/26', 'PI/34/25/26', 'CUST-52', 'LEAD-50', '2026-01-30', '2026-03-01', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'released', '2026-01-30 02:44:41', '2026-01-30 02:44:55', '2026-01-30 08:14:55', 1),
(81, '46/25/26', NULL, 'CUST-14', 'LEAD-61', '2026-01-30', '2026-03-01', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'draft', '2026-01-30 04:54:36', '2026-01-30 04:54:36', NULL, 1),
(82, '47/25/26', NULL, 'CUST-56', 'LEAD-60', '2026-01-30', '2026-03-01', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-30 04:56:37', '2026-01-30 04:56:56', NULL, 1),
(83, '48/25/26', NULL, 'CUST-22', 'LEAD-59', '2026-01-30', '2026-03-01', '100 % Advanced Payment\r\n1 We are providing 1 year warranty\r\n2.No return/refund Policy\r\n3.We will not be held responsible for any accidental loss due to theft, robbery, accidental mishap.\r\n4.If goods are damaged, the product replaced within 2 weeks', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'accepted', '2026-01-30 04:59:43', '2026-01-30 05:00:00', NULL, 0),
(84, '49/25/26', NULL, 'CUST-30', 'LEAD-67', '2026-01-31', '2026-03-02', 'Njcndf', '', 'Bank: ICICI\r\nAccount: 022405003194\r\nIFSC: ICIC0000224\r\nBranch: Delhi', 1, NULL, 'draft', '2026-01-31 05:15:46', '2026-01-31 05:18:29', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `review_findings`
--

CREATE TABLE `review_findings` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `finding_no` varchar(30) NOT NULL,
  `finding_type` enum('Action Item','Observation','Concern','Risk','Recommendation') DEFAULT 'Action Item',
  `severity` enum('Critical','Major','Minor','Observation') DEFAULT 'Minor',
  `category` varchar(100) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Open','In Progress','Resolved','Verified','Closed','Cancelled') DEFAULT 'Open',
  `resolution` text DEFAULT NULL,
  `resolution_date` date DEFAULT NULL,
  `verified_by` varchar(100) DEFAULT NULL,
  `verification_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role` enum('admin','manager','user','viewer') NOT NULL,
  `module` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(1, 'admin', 'dashboard', 1, 1, 1, 1),
(2, 'admin', 'crm', 1, 1, 1, 1),
(3, 'admin', 'customers', 1, 1, 1, 1),
(4, 'admin', 'quotes', 1, 1, 1, 1),
(5, 'admin', 'proforma', 1, 1, 1, 1),
(6, 'admin', 'sales_orders', 1, 1, 1, 1),
(7, 'admin', 'invoices', 1, 1, 1, 1),
(8, 'admin', 'purchase', 1, 1, 1, 1),
(9, 'admin', 'suppliers', 1, 1, 1, 1),
(10, 'admin', 'inventory', 1, 1, 1, 1),
(11, 'admin', 'part_master', 1, 1, 1, 1),
(12, 'admin', 'bom', 1, 1, 1, 1),
(13, 'admin', 'work_orders', 1, 1, 1, 1),
(14, 'admin', 'reports', 1, 1, 1, 1),
(15, 'admin', 'settings', 1, 1, 1, 1),
(16, 'admin', 'users', 1, 1, 1, 1),
(17, 'manager', 'dashboard', 1, 1, 1, 0),
(18, 'manager', 'crm', 1, 1, 1, 1),
(19, 'manager', 'customers', 1, 1, 1, 0),
(20, 'manager', 'quotes', 1, 1, 1, 1),
(21, 'manager', 'proforma', 1, 1, 1, 0),
(22, 'manager', 'sales_orders', 1, 1, 1, 0),
(23, 'manager', 'invoices', 1, 1, 1, 0),
(24, 'manager', 'purchase', 1, 1, 1, 0),
(25, 'manager', 'suppliers', 1, 1, 1, 0),
(26, 'manager', 'inventory', 1, 1, 1, 0),
(27, 'manager', 'part_master', 1, 1, 1, 0),
(28, 'manager', 'bom', 1, 1, 1, 0),
(29, 'manager', 'work_orders', 1, 1, 1, 0),
(30, 'manager', 'reports', 1, 0, 0, 0),
(31, 'manager', 'settings', 1, 0, 0, 0),
(32, 'user', 'dashboard', 1, 0, 0, 0),
(33, 'user', 'crm', 1, 1, 1, 0),
(34, 'user', 'customers', 1, 1, 0, 0),
(35, 'user', 'quotes', 1, 1, 1, 0),
(36, 'user', 'proforma', 1, 0, 0, 0),
(37, 'user', 'sales_orders', 1, 1, 0, 0),
(38, 'user', 'invoices', 1, 0, 0, 0),
(39, 'user', 'purchase', 1, 1, 0, 0),
(40, 'user', 'suppliers', 1, 0, 0, 0),
(41, 'user', 'inventory', 1, 0, 0, 0),
(42, 'user', 'part_master', 1, 0, 0, 0),
(43, 'user', 'work_orders', 1, 1, 0, 0),
(44, 'user', 'reports', 1, 0, 0, 0),
(45, 'viewer', 'dashboard', 1, 0, 0, 0),
(46, 'viewer', 'customers', 1, 0, 0, 0),
(47, 'viewer', 'quotes', 1, 0, 0, 0),
(48, 'viewer', 'proforma', 1, 0, 0, 0),
(49, 'viewer', 'sales_orders', 1, 0, 0, 0),
(50, 'viewer', 'invoices', 1, 0, 0, 0),
(51, 'viewer', 'inventory', 1, 0, 0, 0),
(52, 'viewer', 'reports', 1, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `salary_advances`
--

CREATE TABLE `salary_advances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Repaid') DEFAULT 'Pending',
  `monthly_deduction` decimal(12,2) DEFAULT 0.00,
  `remaining_amount` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int(11) NOT NULL,
  `customer_po_id` int(11) DEFAULT NULL,
  `linked_quote_id` int(11) DEFAULT NULL,
  `so_no` varchar(20) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL,
  `sales_date` date NOT NULL,
  `status` enum('pending','released','completed') DEFAULT 'pending',
  `customer_id` int(11) NOT NULL,
  `stock_status` varchar(50) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `customer_po_id`, `linked_quote_id`, `so_no`, `part_no`, `qty`, `sales_date`, `status`, `customer_id`, `stock_status`) VALUES
(1, NULL, NULL, 'SO-1', '32005012', 7, '2026-01-10', '', 3, 'pending'),
(2, NULL, NULL, 'SO-1', '22005231', 5, '2026-01-10', '', 3, 'pending'),
(3, NULL, NULL, 'SO-2', '95005006', 2, '2026-01-10', '', 3, 'pending'),
(4, NULL, NULL, 'SO-2', '44005002', 1, '2026-01-10', '', 3, 'pending'),
(5, NULL, NULL, 'SO-3', '95005006', 2, '2026-01-10', '', 3, 'pending'),
(6, NULL, NULL, 'SO-4', '95005006', 1, '2026-01-10', '', 5, 'pending'),
(7, NULL, NULL, 'SO-5', '32005012', 12, '2026-01-10', '', 4, 'pending'),
(8, NULL, NULL, 'SO-5', '22005231', 2, '2026-01-10', '', 4, 'pending'),
(9, NULL, NULL, 'SO-6', '32005012', 16, '2026-01-10', '', 4, 'pending'),
(10, NULL, NULL, 'SO-7', '32005012', 4, '2026-01-10', '', 4, 'pending'),
(11, NULL, NULL, 'SO-7', '22005698', 1, '2026-01-10', '', 4, 'pending'),
(12, NULL, NULL, 'SO-8', '95005006', 1, '2026-01-10', '', 4, 'pending'),
(13, NULL, NULL, 'SO-9', '95005006', 1, '2026-01-10', '', 4, 'pending'),
(14, NULL, NULL, 'SO-10', '46005005', 1, '2026-01-14', '', 5, 'pending'),
(15, NULL, NULL, 'SO-11', '22005231', 4, '2026-01-14', '', 4, 'pending'),
(19, 1, 1, 'SO-12', '62005022', 1, '2026-01-17', 'completed', 4, 'insufficient'),
(20, 2, 2, 'SO-13', '46005005', 1, '2026-01-21', 'completed', 5, 'insufficient'),
(21, 3, 4, 'SO-14', '44005643', 1, '2026-01-18', 'pending', 4, 'insufficient'),
(22, 4, 5, 'SO-15', '22005519', 1, '2026-01-21', 'completed', 3, 'ok'),
(23, 5, 6, 'SO-16', 'YID-044', 1, '2026-01-23', 'completed', 28, 'insufficient'),
(24, 5, 6, 'SO-16', 'YID-063', 1, '2026-01-23', 'completed', 28, 'insufficient'),
(25, 5, 6, 'SO-17', 'YID-044', 1, '2026-01-23', 'completed', 28, 'insufficient'),
(26, 5, 6, 'SO-17', 'YID-063', 1, '2026-01-23', 'completed', 28, 'insufficient'),
(27, 8, 9, 'SO-18', 'YID-0124', 1, '2026-01-23', 'completed', 30, 'insufficient'),
(28, 9, 8, 'SO-19', 'YID-044', 1, '2026-01-23', 'pending', 64, 'insufficient'),
(29, 9, 8, 'SO-19', 'YID-063', 1, '2026-01-23', 'pending', 64, 'insufficient'),
(30, 9, 8, 'SO-20', 'YID-044', 1, '2026-01-23', 'pending', 64, 'insufficient'),
(31, 9, 8, 'SO-20', 'YID-063', 1, '2026-01-23', 'pending', 64, 'insufficient'),
(32, 10, 11, 'SO-21', 'YID-044', 1, '2026-01-24', 'pending', 64, 'insufficient'),
(33, 10, 11, 'SO-22', 'YID-044', 1, '2026-01-24', 'pending', 64, 'insufficient'),
(34, 10, 11, 'SO-23', 'YID-044', 1, '2026-01-24', 'pending', 64, 'insufficient'),
(35, 7, 7, 'SO-24', 'YID-116', 1, '2026-01-24', 'pending', 28, 'insufficient'),
(36, 6, 6, 'SO-25', 'YID-044', 1, '2026-01-24', 'pending', 28, 'insufficient'),
(37, 6, 6, 'SO-25', 'YID-063', 1, '2026-01-24', 'pending', 28, 'insufficient'),
(38, 11, 20, 'SO-26', 'YID -043', 1, '2026-01-24', 'completed', 46, 'insufficient'),
(39, 12, 12, 'SO-27', 'YID-063', 1, '2026-01-24', 'completed', 28, 'insufficient'),
(40, 13, 13, 'SO-28', 'YID-017', 1, '2026-01-24', 'pending', 55, 'insufficient'),
(41, 14, 18, 'SO-29', 'YID -043', 1, '2026-01-25', 'completed', 55, 'insufficient'),
(42, 15, 21, 'SO-30', 'YID -043', 1, '2026-01-25', 'completed', 28, 'insufficient'),
(43, 16, 22, 'SO-31', 'YID-008', 1, '2026-01-25', 'completed', 64, 'insufficient'),
(44, 17, 23, 'SO-32', 'YID -043', 1, '2026-01-25', 'completed', 66, 'insufficient'),
(45, 18, 24, 'SO-33', 'YID -043', 1, '2026-01-25', 'completed', 52, 'insufficient'),
(46, 21, 26, 'SO-34', 'YID-167', 1, '2026-01-26', 'pending', 28, 'insufficient'),
(47, 22, 30, 'SO-35', 'YID-044', 0, '2026-01-27', 'released', 28, 'ok'),
(48, 22, 30, 'SO-35', 'YID-008', 0, '2026-01-27', 'released', 28, 'insufficient'),
(49, 23, 31, 'SO-36', 'YID-167', 1, '2026-01-27', 'pending', 64, 'insufficient'),
(50, 24, 33, 'SO-37', 'YID-003', 1, '2026-01-28', 'pending', 75, 'insufficient'),
(51, 20, 10, 'SO-38', 'YID-002', 1, '2026-01-29', 'pending', 12, 'insufficient'),
(52, 19, 25, 'SO-39', 'YID-167', 1, '2026-01-29', 'pending', 12, 'insufficient'),
(53, 25, 52, 'SO-40', 'YID-012', 1, '2026-01-29', 'pending', 5, 'insufficient'),
(54, 26, 76, 'SO-41', 'YID-044', 1, '2026-01-29', 'completed', 12, 'ok'),
(55, 27, 79, 'SO-42', 'YID-044', 1, '2026-01-30', 'completed', 11, 'ok'),
(56, 28, 80, 'SO-43', 'YID-044', 0, '2026-02-01', 'released', 52, 'ok');

-- --------------------------------------------------------

--
-- Table structure for table `service_complaints`
--

CREATE TABLE `service_complaints` (
  `id` int(11) NOT NULL,
  `complaint_no` varchar(30) NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_status` enum('Under Warranty','Out of Warranty','AMC','Extended Warranty') DEFAULT 'Out of Warranty',
  `issue_category_id` int(11) DEFAULT NULL,
  `complaint_description` text NOT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `assigned_technician_id` int(11) DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `scheduled_visit_date` date DEFAULT NULL,
  `scheduled_visit_time` time DEFAULT NULL,
  `status` enum('Open','Assigned','In Progress','On Hold','Resolved','Closed','Cancelled') DEFAULT 'Open',
  `resolution_date` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `service_charge` decimal(10,2) DEFAULT 0.00,
  `parts_charge` decimal(10,2) DEFAULT 0.00,
  `total_charge` decimal(10,2) DEFAULT 0.00,
  `customer_satisfaction` enum('Very Satisfied','Satisfied','Neutral','Dissatisfied','Very Dissatisfied') DEFAULT NULL,
  `feedback_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `registered_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_complaints`
--

INSERT INTO `service_complaints` (`id`, `complaint_no`, `customer_name`, `customer_phone`, `customer_email`, `customer_address`, `city`, `state_id`, `pincode`, `product_name`, `product_model`, `serial_number`, `purchase_date`, `warranty_status`, `issue_category_id`, `complaint_description`, `priority`, `assigned_technician_id`, `assigned_date`, `scheduled_visit_date`, `scheduled_visit_time`, `status`, `resolution_date`, `resolution_notes`, `parts_replaced`, `service_charge`, `parts_charge`, `total_charge`, `customer_satisfaction`, `feedback_notes`, `internal_notes`, `registered_date`, `created_at`, `updated_at`) VALUES
(1, 'SVC-202601-0001', 'Dipankar Aich', '08669234153', 'yashkacontacts@gmail.com', 'Megapolis\r\nMystic', 'Pune', 21, '411057', 'AVS', '87633', '123', '2026-01-02', 'Under Warranty', 8, 'hi', 'Medium', NULL, NULL, NULL, NULL, 'Open', NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, '2026-01-18 20:53:59', '2026-01-18 15:23:59', '2026-01-18 15:23:59'),
(2, 'SVC-202601-0002', 'Jagruti Hospital', '8347341415', NULL, 'Bhura Complex ', 'Santrampur ', 11, '389260', 'Arya', 'Arya 2500', NULL, '2026-01-27', 'Under Warranty', 1, 'Dc switch change', 'Medium', 1, NULL, '2026-02-02', '10:22:00', 'Assigned', NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, '2026-01-31 10:22:58', '2026-01-31 04:52:58', '2026-01-31 04:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `service_issue_categories`
--

CREATE TABLE `service_issue_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_issue_categories`
--

INSERT INTO `service_issue_categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Hardware Malfunction', 'Physical device or equipment not working properly', 1, '2026-01-18 15:22:31'),
(2, 'Software Issue', 'Software errors, bugs, or crashes', 1, '2026-01-18 15:22:31'),
(3, 'Installation Problem', 'Issues during product installation or setup', 1, '2026-01-18 15:22:31'),
(4, 'Calibration Required', 'Equipment needs calibration or adjustment', 1, '2026-01-18 15:22:31'),
(5, 'Performance Degradation', 'Equipment working slowly or below expected performance', 1, '2026-01-18 15:22:31'),
(6, 'Electrical Issue', 'Power supply, wiring, or electrical problems', 1, '2026-01-18 15:22:31'),
(7, 'Mechanical Failure', 'Moving parts, motors, or mechanical components failure', 1, '2026-01-18 15:22:31'),
(8, 'Display/Screen Issue', 'Monitor, display, or screen related problems', 1, '2026-01-18 15:22:31'),
(9, 'Connectivity Problem', 'Network, Bluetooth, or other connectivity issues', 1, '2026-01-18 15:22:31'),
(10, 'Data Loss/Corruption', 'Data not saving or getting corrupted', 1, '2026-01-18 15:22:31'),
(11, 'User Training', 'Customer needs training on product usage', 1, '2026-01-18 15:22:31'),
(12, 'Preventive Maintenance', 'Scheduled maintenance service', 1, '2026-01-18 15:22:31'),
(13, 'Warranty Claim', 'Product under warranty needs replacement/repair', 1, '2026-01-18 15:22:31'),
(14, 'Spare Parts Required', 'Need to replace specific parts', 1, '2026-01-18 15:22:31'),
(15, 'Other', 'Other issues not categorized above', 1, '2026-01-18 15:22:31');

-- --------------------------------------------------------

--
-- Table structure for table `service_parts`
--

CREATE TABLE `service_parts` (
  `id` int(11) NOT NULL,
  `part_code` varchar(50) NOT NULL,
  `part_name` varchar(200) NOT NULL,
  `compatible_products` text DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `stock_qty` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 5,
  `status` enum('Active','Discontinued') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_technicians`
--

CREATE TABLE `service_technicians` (
  `id` int(11) NOT NULL,
  `tech_code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `assigned_region` varchar(100) DEFAULT NULL,
  `status` enum('Active','On Leave','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_technicians`
--

INSERT INTO `service_technicians` (`id`, `tech_code`, `name`, `phone`, `email`, `specialization`, `assigned_region`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TEC-001', 'Dipankar Aich', '08669234153', 'yashkacontacts@gmail.com', 'electronics', 'North', 'Active', '2026-01-18 15:24:53', '2026-01-18 15:24:53'),
(2, 'TEC-002', 'Wasim Ahamad', '9657496762', 'wasim.ahmad@yashka.io', 'Abhinav Kumar ', 'West', 'Active', '2026-01-31 04:54:10', '2026-01-31 05:00:45'),
(3, 'TEC-003', 'Abhinav kumar', '9319750060', NULL, 'mechanical ', 'West', 'Active', '2026-01-31 04:55:41', '2026-01-31 04:55:41'),
(4, 'TEC-004', 'Ashwni kumar', '9334106868', NULL, NULL, 'East', 'Active', '2026-01-31 04:58:13', '2026-01-31 04:58:13'),
(5, 'TEC-005', 'Sandeep kumar', '7458056124', NULL, NULL, 'Central', 'Active', '2026-01-31 04:58:55', '2026-01-31 04:58:55'),
(6, 'TEC-006', 'vikram pawar', '7709730190', NULL, 'electrical', 'West', 'Active', '2026-01-31 04:59:59', '2026-01-31 04:59:59'),
(7, 'TEC-007', 'vikram pawar', '7709730190', NULL, 'electrical', 'West', 'Active', '2026-01-31 04:59:59', '2026-01-31 04:59:59');

-- --------------------------------------------------------

--
-- Table structure for table `service_visits`
--

CREATE TABLE `service_visits` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_time_start` time DEFAULT NULL,
  `visit_time_end` time DEFAULT NULL,
  `visit_type` enum('Diagnosis','Repair','Parts Replacement','Follow-up','Installation','Training') DEFAULT 'Repair',
  `work_done` text DEFAULT NULL,
  `parts_used` text DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled','Rescheduled') DEFAULT 'Scheduled',
  `customer_signature` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills_master`
--

CREATE TABLE `skills_master` (
  `id` int(11) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skill_categories`
--

CREATE TABLE `skill_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skill_categories`
--

INSERT INTO `skill_categories` (`id`, `category_name`, `description`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Technical Skills', 'Programming, software, and technical competencies', 1, 1, '2026-01-30 04:20:55'),
(2, 'Soft Skills', 'Communication, teamwork, and interpersonal skills', 1, 2, '2026-01-30 04:20:55'),
(3, 'Management Skills', 'Leadership and management competencies', 1, 3, '2026-01-30 04:20:55'),
(4, 'Domain Knowledge', 'Industry and domain-specific knowledge', 1, 4, '2026-01-30 04:20:55'),
(5, 'Tools & Software', 'Proficiency in specific tools and software', 1, 5, '2026-01-30 04:20:55');

-- --------------------------------------------------------

--
-- Table structure for table `so_checklist_items`
--

CREATE TABLE `so_checklist_items` (
  `id` int(11) NOT NULL,
  `category` enum('Machine Performance','Functional Performance','Quality Check','Government Compliance','Other') NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `requires_attachment` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `so_checklist_items`
--

INSERT INTO `so_checklist_items` (`id`, `category`, `item_name`, `description`, `is_mandatory`, `requires_attachment`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Machine Performance', 'Overall Machine Performance Test', 'Verify all machine functions are working as per specifications', 1, 0, 1, 1, '2026-02-01 03:10:49'),
(2, 'Machine Performance', 'Noise Level Check', 'Ensure machine noise is within acceptable limits', 1, 0, 2, 1, '2026-02-01 03:10:49'),
(3, 'Machine Performance', 'Vibration Test', 'Check for abnormal vibrations during operation', 1, 0, 3, 1, '2026-02-01 03:10:49'),
(4, 'Functional Performance', 'Operational Test', 'Complete operational cycle test performed', 1, 0, 4, 1, '2026-02-01 03:10:49'),
(5, 'Functional Performance', 'Safety Features Test', 'All safety interlocks and features verified', 1, 0, 5, 1, '2026-02-01 03:10:49'),
(6, 'Functional Performance', 'Performance Parameters', 'Output/performance parameters meet specifications', 1, 0, 6, 1, '2026-02-01 03:10:49'),
(7, 'Quality Check', 'Visual Inspection', 'No visible defects, scratches, or damage', 1, 0, 7, 1, '2026-02-01 03:10:49'),
(8, 'Quality Check', 'Dimensional Verification', 'Critical dimensions verified against drawings', 1, 0, 8, 1, '2026-02-01 03:10:49'),
(9, 'Quality Check', 'Packaging Inspection', 'Packaging is adequate for safe transportation', 1, 0, 9, 1, '2026-02-01 03:10:49'),
(10, 'Quality Check', 'Documentation Complete', 'All required documents included (manual, warranty, etc.)', 1, 0, 10, 1, '2026-02-01 03:10:49'),
(11, 'Government Compliance', 'BIS Certificate', 'Bureau of Indian Standards certification if applicable', 0, 1, 11, 1, '2026-02-01 03:10:49'),
(12, 'Government Compliance', 'CE Marking', 'CE marking verification for export orders', 0, 1, 12, 1, '2026-02-01 03:10:49'),
(13, 'Government Compliance', 'Test Certificate', 'Factory test certificate attached', 1, 1, 13, 1, '2026-02-01 03:10:49'),
(14, 'Government Compliance', 'Warranty Card', 'Warranty card prepared and included', 1, 0, 14, 1, '2026-02-01 03:10:49');

-- --------------------------------------------------------

--
-- Table structure for table `so_release_attachments`
--

CREATE TABLE `so_release_attachments` (
  `id` int(11) NOT NULL,
  `so_no` varchar(50) NOT NULL,
  `attachment_type` enum('Government Document','Test Report','Quality Certificate','Inspection Report','Other') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `so_release_attachments`
--

INSERT INTO `so_release_attachments` (`id`, `so_no`, `attachment_type`, `file_name`, `original_name`, `file_type`, `file_size`, `file_path`, `description`, `uploaded_by`, `uploaded_at`) VALUES
(1, 'SO-40', 'Test Report', 'SO_SO_40_1769915752_1936.xlsx', 'YID product series (1).xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 17103, 'uploads/so_release/SO_SO_40_1769915752_1936.xlsx', '', 'Administrator', '2026-02-01 03:15:52'),
(2, 'SO-40', 'Inspection Report', 'SO_SO_40_1769915787_5114.xlsx', 'series excel file.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 10762, 'uploads/so_release/SO_SO_40_1769915787_5114.xlsx', '', 'Administrator', '2026-02-01 03:16:27'),
(3, 'SO-40', 'Inspection Report', 'SO_SO_40_1769915811_1822.pdf', 'komal resume (1).pdf', 'application/pdf', 726221, 'uploads/so_release/SO_SO_40_1769915811_1822.pdf', '', 'Administrator', '2026-02-01 03:16:51'),
(4, 'SO-40', 'Government Document', 'SO_SO_40_1769915923_5341.pdf', 'PI172526.pdf', 'application/pdf', 256652, 'uploads/so_release/SO_SO_40_1769915923_5341.pdf', 'ok', 'Administrator', '2026-02-01 03:18:43'),
(5, 'SO-40', 'Government Document', 'SO_SO_40_1769916294_7396.pdf', 'PI172526.pdf', 'application/pdf', 256652, 'uploads/so_release/SO_SO_40_1769916294_7396.pdf', 'ok', 'Administrator', '2026-02-01 03:24:54'),
(6, 'SO-40', 'Government Document', 'SO_SO_40_1769916309_6696.pdf', 'PI172526.pdf', 'application/pdf', 256652, 'uploads/so_release/SO_SO_40_1769916309_6696.pdf', 'ok', 'Administrator', '2026-02-01 03:25:09'),
(7, 'SO-40', 'Quality Certificate', 'SO_SO_40_1769916537_7352.pdf', '202526.pdf', 'application/pdf', 251953, 'uploads/so_release/SO_SO_40_1769916537_7352.pdf', '', 'Administrator', '2026-02-01 03:28:57');

-- --------------------------------------------------------

--
-- Table structure for table `so_release_checklist`
--

CREATE TABLE `so_release_checklist` (
  `id` int(11) NOT NULL,
  `so_no` varchar(50) NOT NULL,
  `machine_performance_ok` tinyint(1) DEFAULT 0,
  `machine_performance_remarks` text DEFAULT NULL,
  `functional_performance_ok` tinyint(1) DEFAULT 0,
  `functional_performance_remarks` text DEFAULT NULL,
  `quality_visual_inspection` tinyint(1) DEFAULT 0,
  `quality_dimensional_check` tinyint(1) DEFAULT 0,
  `quality_safety_check` tinyint(1) DEFAULT 0,
  `quality_packaging_ok` tinyint(1) DEFAULT 0,
  `quality_remarks` text DEFAULT NULL,
  `govt_compliance_checked` tinyint(1) DEFAULT 0,
  `govt_compliance_remarks` text DEFAULT NULL,
  `checklist_completed` tinyint(1) DEFAULT 0,
  `completed_by` int(11) DEFAULT NULL,
  `completed_by_name` varchar(100) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `so_release_checklist`
--

INSERT INTO `so_release_checklist` (`id`, `so_no`, `machine_performance_ok`, `machine_performance_remarks`, `functional_performance_ok`, `functional_performance_remarks`, `quality_visual_inspection`, `quality_dimensional_check`, `quality_safety_check`, `quality_packaging_ok`, `quality_remarks`, `govt_compliance_checked`, `govt_compliance_remarks`, `checklist_completed`, `completed_by`, `completed_by_name`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 'SO-40', 1, 'ok', 1, 'ok', 1, 1, 1, 1, 'ok', 1, 'ok', 1, 1, 'Administrator', '2026-02-01 08:59:36', '2026-02-01 03:29:36', '2026-02-01 03:29:36');

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `state_name` varchar(100) NOT NULL,
  `state_code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`id`, `state_name`, `state_code`, `is_active`, `created_at`) VALUES
(1, 'Andhra Pradesh', 'AP', 1, '2026-01-21 13:01:30'),
(2, 'Arunachal Pradesh', 'AR', 1, '2026-01-21 13:01:30'),
(3, 'Assam', 'AS', 1, '2026-01-21 13:01:30'),
(4, 'Bihar', 'BR', 1, '2026-01-21 13:01:30'),
(5, 'Chhattisgarh', 'CG', 1, '2026-01-21 13:01:30'),
(6, 'Goa', 'GA', 1, '2026-01-21 13:01:30'),
(7, 'Gujarat', 'GJ', 1, '2026-01-21 13:01:30'),
(8, 'Haryana', 'HR', 1, '2026-01-21 13:01:30'),
(9, 'Himachal Pradesh', 'HP', 1, '2026-01-21 13:01:30'),
(10, 'Jharkhand', 'JH', 1, '2026-01-21 13:01:30'),
(11, 'Karnataka', 'KA', 1, '2026-01-21 13:01:30'),
(12, 'Kerala', 'KL', 1, '2026-01-21 13:01:30'),
(13, 'Madhya Pradesh', 'MP', 1, '2026-01-21 13:01:30'),
(14, 'Maharashtra', 'MH', 1, '2026-01-21 13:01:30'),
(15, 'Manipur', 'MN', 1, '2026-01-21 13:01:30'),
(16, 'Meghalaya', 'ML', 1, '2026-01-21 13:01:30'),
(17, 'Mizoram', 'MZ', 1, '2026-01-21 13:01:30'),
(18, 'Nagaland', 'NL', 1, '2026-01-21 13:01:30'),
(19, 'Odisha', 'OD', 1, '2026-01-21 13:01:30'),
(20, 'Punjab', 'PB', 1, '2026-01-21 13:01:30'),
(21, 'Rajasthan', 'RJ', 1, '2026-01-21 13:01:30'),
(22, 'Sikkim', 'SK', 1, '2026-01-21 13:01:30'),
(23, 'Tamil Nadu', 'TN', 1, '2026-01-21 13:01:30'),
(24, 'Telangana', 'TS', 1, '2026-01-21 13:01:30'),
(25, 'Tripura', 'TR', 1, '2026-01-21 13:01:30'),
(26, 'Uttar Pradesh', 'UP', 1, '2026-01-21 13:01:30'),
(27, 'Uttarakhand', 'UK', 1, '2026-01-21 13:01:30'),
(28, 'West Bengal', 'WB', 1, '2026-01-21 13:01:30'),
(29, 'Delhi', 'DL', 1, '2026-01-21 13:01:30'),
(30, 'Jammu and Kashmir', 'JK', 1, '2026-01-21 13:01:30'),
(31, 'Ladakh', 'LA', 1, '2026-01-21 13:01:30'),
(32, 'Puducherry', 'PY', 1, '2026-01-21 13:01:30'),
(33, 'Chandigarh', 'CH', 1, '2026-01-21 13:01:30'),
(34, 'Andaman and Nicobar Islands', 'AN', 1, '2026-01-21 13:01:30'),
(35, 'Dadra and Nagar Haveli and Daman and Diu', 'DN', 1, '2026-01-21 13:01:30'),
(36, 'Lakshadweep', 'LD', 1, '2026-01-21 13:01:30');

-- --------------------------------------------------------

--
-- Table structure for table `stock_entries`
--

CREATE TABLE `stock_entries` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `received_qty` decimal(10,3) NOT NULL,
  `received_date` datetime DEFAULT current_timestamp(),
  `invoice_no` varchar(50) DEFAULT NULL,
  `status` enum('posted','cancelled') DEFAULT 'posted',
  `remarks` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_entries`
--

INSERT INTO `stock_entries` (`id`, `po_id`, `part_no`, `received_qty`, `received_date`, `invoice_no`, `status`, `remarks`) VALUES
(1, 1, '22005001', 10.000, '2026-01-04 19:15:20', '001', 'posted', NULL),
(2, 2, '22005231', 10.000, '2026-01-05 11:33:35', '0041', 'posted', NULL),
(3, 3, '32005012', 121.000, '2026-01-06 08:48:17', '0065', 'posted', NULL),
(4, 6, '22005698', 5.000, '2026-01-06 08:52:49', '133', 'posted', NULL),
(5, 5, '32005012', 5.000, '2026-01-06 08:53:00', '343', 'posted', NULL),
(6, 4, '32005012', 5.000, '2026-01-06 08:53:12', '433', 'posted', NULL),
(7, 7, '22005231', 10.000, '2026-01-07 11:19:17', '123', 'posted', NULL),
(8, 8, '22005698', 10.000, '2026-01-07 11:19:29', '123', 'posted', NULL),
(9, 10, '95005006', 2.000, '2026-01-08 15:24:26', '987', 'posted', NULL),
(10, 9, '22005231', 10.000, '2026-01-08 15:27:44', '5434', 'posted', NULL),
(11, 11, '22005458', 10.000, '2026-01-08 15:44:24', '655', 'posted', NULL),
(12, 12, '62005022', 2.000, '2026-01-08 15:45:41', '766', 'posted', NULL),
(13, 13, '22005012', 5.000, '2026-01-08 15:45:53', '123', 'posted', NULL),
(14, 14, '22005458', 2.000, '2026-01-08 15:47:07', '123', 'posted', NULL),
(15, 15, '62005019', 5.000, '2026-01-08 15:47:55', '755', 'posted', NULL),
(16, 16, '22005231', 5.000, '2026-01-08 15:49:32', '5', 'posted', NULL),
(17, 17, '22005519', 5.000, '2026-01-08 15:52:34', '5', 'posted', NULL),
(18, 18, '62005022', 5.000, '2026-01-08 15:54:45', '12', 'posted', NULL),
(19, 19, '62005022', 5.000, '2026-01-09 08:44:00', '876', 'posted', NULL),
(20, 20, '95005006', 5.000, '2026-01-09 13:09:44', '6575', 'posted', NULL),
(21, 21, '50520020', 1.000, '2026-01-14 22:14:08', '33', 'posted', NULL),
(22, 22, '95005006', 5.000, '2026-01-15 16:03:50', '54', 'posted', NULL),
(23, 23, '22005458', 4.000, '2026-01-15 16:03:50', '54', 'posted', NULL),
(24, 26, '62005022', 5.000, '2026-01-18 00:20:55', '66', 'posted', NULL),
(25, 27, '95005006', 5.000, '2026-01-18 15:14:16', '34343', 'posted', NULL),
(26, 28, '62005019', 4.000, '2026-01-18 15:14:16', '34343', 'posted', NULL),
(27, 29, '95005006', 12.000, '2026-01-18 15:50:01', '232', 'posted', NULL),
(28, 30, '62005019', 5.000, '2026-01-18 15:50:01', '232', 'posted', NULL),
(29, 25, '62005022', 6.000, '2026-01-21 10:34:57', 'dcdc', 'posted', NULL),
(30, 32, 'YID-063', 1.000, '2026-01-23 07:01:53', 'e22', 'posted', NULL),
(31, 31, 'YID-044', 1.000, '2026-01-23 07:02:15', '123', 'posted', NULL),
(32, 34, '62005151', 2.000, '2026-01-24 07:36:30', 'iru', 'posted', NULL),
(33, 35, 'YID -043', 2.000, '2026-01-24 20:27:09', '123', 'posted', NULL),
(34, 43, 'YID-044', 2.000, '2026-01-24 22:09:49', '12', 'posted', NULL),
(35, 42, 'YID-063', 3.000, '2026-01-24 22:10:07', 'dds', 'posted', NULL),
(36, 46, 'YID-044', 5.000, '2026-01-25 07:40:26', '123', 'posted', NULL),
(37, 47, 'YID -043', 1.000, '2026-01-25 07:41:43', 'dd', 'posted', NULL),
(38, 51, 'YID -043', 2.000, '2026-01-25 08:53:31', 'qee', 'posted', NULL),
(39, 52, 'YID-063', 1.000, '2026-01-25 10:46:48', '122', 'posted', NULL),
(40, 54, 'YID-008', 1.000, '2026-01-25 10:49:12', '13132', 'posted', NULL),
(41, 55, 'YID -043', 2.000, '2026-01-25 14:42:33', '123', 'posted', NULL),
(42, 56, 'YID -043', 2.000, '2026-01-25 14:58:06', 'kk', 'posted', NULL),
(43, 49, 'YID-063', 1.000, '2026-01-25 15:02:55', 'adad', 'posted', NULL),
(44, 45, 'YID-063', 3.000, '2026-01-25 15:03:07', 'ffs', 'posted', NULL),
(45, 44, '44005643', 1.000, '2026-01-25 19:19:11', '12', 'posted', NULL),
(46, 41, 'YID-044', 2.000, '2026-01-25 19:19:18', 'd', 'posted', NULL),
(47, 48, '44005643', 1.000, '2026-01-25 19:19:26', 'dd', 'posted', NULL),
(48, 40, 'YID-063', 2.000, '2026-01-25 19:19:32', 'dcdc', 'posted', NULL),
(49, 57, '44005643', 1.000, '2026-01-25 19:20:09', 'dcdc', 'posted', NULL),
(50, 38, '11005089', 1.000, '2026-01-25 19:20:18', '0041', 'posted', NULL),
(51, 39, '11005099', 1.000, '2026-01-25 19:20:18', '0041', 'posted', NULL),
(52, 36, '11005089', 1.000, '2026-01-25 19:20:23', 'dcdc', 'posted', NULL),
(53, 37, '11005099', 1.000, '2026-01-25 19:20:23', 'dcdc', 'posted', NULL),
(54, 33, '44005643', 1.000, '2026-01-25 19:20:29', '12', 'posted', NULL),
(55, 24, '95005006', 1.000, '2026-01-25 19:20:35', '', 'posted', NULL),
(56, 74, '17005066', 1.000, '2026-01-28 19:30:42', '', 'posted', NULL),
(57, 90, 'YID-008', 2.000, '2026-01-28 21:25:29', 'ddd', 'posted', NULL),
(58, 81, 'YID-008', 1.000, '2026-01-28 21:26:15', 'ddd', 'posted', NULL),
(59, 83, 'YID-008', 1.000, '2026-01-28 21:29:20', 'dd', 'posted', NULL),
(60, 87, 'YID-008', 1.000, '2026-01-28 21:29:36', 'ddd', 'posted', NULL),
(61, 79, 'YID-008', 1.000, '2026-01-28 21:29:44', 'dddd', 'posted', NULL),
(62, 78, '17005066', 1.000, '2026-01-28 21:29:54', 'ddd', 'posted', NULL),
(63, 77, '11005096', 1.000, '2026-01-28 21:30:01', 'dd', 'posted', NULL),
(64, 76, '11005099', 1.000, '2026-01-28 21:30:08', 'dd', 'posted', NULL),
(65, 75, '11005089', 1.000, '2026-01-28 21:30:16', 'ddd', 'posted', NULL),
(66, 95, '11005089', 1.000, '2026-01-29 12:46:55', 'ii122', 'posted', NULL),
(67, 96, '11005096', 1.000, '2026-01-29 12:46:55', 'ii122', 'posted', NULL),
(68, 97, 'YID-008', 1.000, '2026-01-29 12:46:55', 'ii122', 'posted', NULL),
(69, 98, '11005099', 1.000, '2026-01-29 12:46:55', 'ii122', 'posted', NULL),
(70, 99, '62005265', 1.000, '2026-01-29 14:44:12', '1', 'posted', NULL),
(71, 100, '95005002', 2.000, '2026-01-29 14:48:49', '1', 'posted', NULL),
(72, 110, '17005011', 1.000, '2026-01-29 15:06:17', '1', 'posted', NULL),
(73, 0, '46005089', 2.000, '2026-01-29 17:04:25', 'WO: WO-34', 'posted', NULL),
(74, 0, '46005089', 2.000, '2026-01-29 17:04:29', 'WO: WO-34', 'posted', NULL),
(75, 0, '46005089', 2.000, '2026-01-29 17:04:43', 'WO: WO-34', 'posted', NULL),
(76, 0, '46005089', 2.000, '2026-01-29 17:05:31', 'WO: WO-34', 'posted', NULL),
(77, 0, '46005077', 2.000, '2026-01-29 17:06:34', 'WO: WO-33', 'posted', NULL),
(78, 119, '62005003', 1.000, '2026-01-29 17:45:17', '', 'posted', NULL),
(79, 118, '62005003', 1.000, '2026-01-29 17:45:28', '1', 'posted', NULL),
(80, 0, '83005003', 20.000, '2026-01-30 08:51:52', 'WO: WO-41', '', NULL),
(81, 0, '83005003', 20.000, '2026-01-30 08:54:44', 'WO: WO-41', 'posted', NULL),
(82, 0, '83005053', 2.000, '2026-01-30 08:58:32', 'WO: WO-43', '', NULL),
(83, 0, '83005053', 2.000, '2026-01-30 09:06:16', 'WO: WO-43', 'posted', NULL),
(84, 0, '83005053', 2.000, '2026-01-30 10:23:34', 'WO: WO-45', 'posted', NULL),
(85, 0, '83005053', 1.000, '2026-01-30 10:36:05', 'WO: WO-44', 'posted', NULL),
(86, 0, '44005733', 1.000, '2026-01-31 10:52:06', 'WO: WO-46', '', NULL),
(87, 0, '44005733', 1.000, '2026-01-31 11:01:23', 'WO: WO-46', 'posted', NULL),
(88, 0, '44005733', 2.000, '2026-01-31 11:02:46', 'WO: WO-47', 'posted', NULL),
(89, 0, '44005733', 1.000, '2026-01-31 11:04:59', 'WO: WO-48', 'posted', NULL),
(90, 0, '44005733', 2.000, '2026-01-31 11:10:42', 'WO: WO-49', 'posted', NULL),
(91, 0, '44005733', 1.000, '2026-01-31 17:30:12', 'WO: WO-51', '', NULL),
(92, 0, '44005733', 1.000, '2026-01-31 17:31:07', 'WO: WO-52', '', NULL),
(93, 0, '44005733', 1.000, '2026-01-31 17:31:34', 'WO: WO-50', 'posted', NULL),
(94, 0, '44005733', 1.000, '2026-01-31 18:14:50', 'WO: WO-52', '', NULL),
(95, 0, '44005733', 1.000, '2026-01-31 18:46:08', 'WO: WO-52', '', NULL),
(96, 0, '44005733', 1.000, '2026-01-31 19:10:55', 'WO: WO-52', 'posted', NULL),
(97, 0, '44005733', 1.000, '2026-01-31 19:11:46', 'WO: WO-52', 'posted', NULL),
(98, 0, '44005733', 1.000, '2026-01-31 19:11:53', 'WO: WO-52', 'posted', NULL),
(99, 0, '44005733', 1.000, '2026-01-31 19:12:25', 'WO: WO-52', 'posted', NULL),
(100, 0, '44005733', 1.000, '2026-01-31 19:12:32', 'WO: WO-52', 'posted', NULL),
(101, 0, '44005733', 1.000, '2026-01-31 19:12:36', 'WO: WO-52', 'posted', NULL),
(102, 0, '44005733', 1.000, '2026-01-31 19:12:51', 'WO: WO-52', 'posted', NULL),
(103, 0, '44005733', 1.000, '2026-01-31 19:13:00', 'WO: WO-52', '', NULL),
(104, 139, '62005004', 1.000, '2026-01-31 19:55:26', '1245', 'posted', NULL),
(105, 140, '62005003', 1.000, '2026-01-31 19:55:26', '1245', 'posted', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(50) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `address1`, `address2`, `city`, `pincode`, `state`, `gstin`, `created_at`) VALUES
(1, 'YS-001', 'Asha Engineering', 'Mr. Avinash', '7775823557', 'Asha@gmail.com', 'Pune', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-04 13:41:21'),
(2, 'YS-002', 'Hospital Device', 'Mr.Ankur Gupta', '8756496754', 'hospital@gmail.com', 'Delhi', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-06 03:49:39'),
(3, 'YS-003', 'Laptec ', 'Nilesh', '98453674321', 'laptec@gmail.com', 'Pune', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-08 09:53:37'),
(4, 'SUP001', 'ADITI ENTERPRISES', 'ADITI ENTER PRISES', '9623201188', 'aaditi.enterprises@yahoo.com', NULL, 'Aaditi Enterprises Plot No. 20, Rainbow Park, Near the Vitthal Mandir , ZP School, Borsheti (BK), Asangaon (E) Tal. Shahaput Dist. Thane 421601 (MS) India', 'Aaditi Enterprises Plot No. 20, Rainbow Park, Near the Vitthal Mandir , ZP School, Borsheti (BK), Asangaon (E) Tal. Shahaput Dist. Thane 421601 (MS) India', 'Mumbai', '421601', 'Maharashtra', '27ABNPM8899H2ZM', '2026-01-22 06:34:52'),
(5, 'SUP002', 'AFZAL COMPUTORS', 'WASIM AHEMED', '9657496762', 'afzalcomputer85@gmail.com', NULL, 'Afzal Computer Maan Road, Rakshey Wasti Maan Village 411057 Pune', 'Afzal Computer Maan Road, Rakshey Wasti Maan Village 411057 Pune', 'PUNE', '411057', 'Gujarat', '24AABCU9603R1ZN', '2026-01-22 06:34:52'),
(6, 'SUP003', 'AKSHY FASTENERS', 'AKSHY FASTENERS', '9823061884', '-', NULL, 'Unit-I, J-64, MIDC Rd, J Block, S Block, MIDC, Bhosari, Pune, Pimpri-Chinchwad, Maharashtra 411026', 'Unit-I, J-64, MIDC Rd, J Block, S Block, MIDC, Bhosari, Pune, Pimpri-Chinchwad, Maharashtra 411026', 'PUNE', '411026', 'Delhi', '27ABYPT9729Q1ZZ', '2026-01-22 06:34:52'),
(7, 'SUP004', 'APS EMBEDDED SYSTEM', 'Akash Shah', '9029145142', 'apsembedded@gmail.com', NULL, 'Neelam Industrial Estate, Gala No 5 Shantilal Modi Cross Road Number 2 Mumbai Maharashtra, India - 400067', 'Neelam Industrial Estate, Gala No 5 Shantilal Modi Cross Road Number 2 Mumbai Maharashtra, India - 400067', 'MUMBAI', '400067', 'Maharashtra', '27CWGPS6694J1ZL', '2026-01-22 06:34:52'),
(8, 'SUP005', 'ASHA ENGINEERING SOLUTIONS', 'Avinash Engg', '8767461601', 'ashaenggsolutions22@gmail.com', NULL, 'ASHA ENGINEERING SOLUTIONS SR.NO.15/7, GULAB INDUSTRIAL COMPLEX, AGAINST SAI HEAT, ANAND NAGAR, BHOSARI,PUNE', 'ASHA ENGINEERING SOLUTIONS SR.NO.15/7, GULAB INDUSTRIAL COMPLEX, AGAINST SAI HEAT, ANAND NAGAR, BHOSARI,PUNE', 'PUNE', '411017', 'Maharashtra', '27BNVPG9301F1ZS', '2026-01-22 06:34:52'),
(9, 'SUP006', 'AZAD ENTERPRISE', 'AZAD ENTERPRISES', '8777253858', 'ae2000ind@gmail.com', NULL, 'AZAD ENTERPRISE B/45/5/H/1, Diamond Harbour Road, Mominpur, Kolkata - 700027, India', 'AZAD ENTERPRISE B/45/5/H/1, Diamond Harbour Road, Mominpur, Kolkata - 700027, India', 'KOLKATA', '700027', 'West Bengal', '19AACPE6689H1ZD', '2026-01-22 06:34:52'),
(10, 'SUP007', 'BABA RAMDEV ENGINEERING', 'HEMLATA HIRALAL', '9371986914', 'BABARAMDEV.ENGINEERING55@GMAIL.COM', NULL, 'BABA RAMDEV ENGINEERING S BLOCK LANE 2 SHOP NO 24 . OPP JAYDEEP BUSSINESS CENTRE INDRAYANI NAGAR BHOSARI PUNE 411026', 'BABA RAMDEV ENGINEERING S BLOCK LANE 2 SHOP NO 24 . OPP JAYDEEP BUSSINESS CENTRE INDRAYANI NAGAR BHOSARI PUNE 411026', 'pune', '411026', 'Maharashtra', '27BKTPM9361G1ZG', '2026-01-22 06:34:52'),
(11, 'SUP008', 'BAMA INSTRUCHEM', 'BAMA SOLUTION', '9405217482', 'bamainstruchem@gmail.com', NULL, 'BAMA INSTRUCHEM OPPOSITE MAHINDRA CIE CHAKAN AMBETHAN ROAD, AMBETHAN TAL. KHED DIST PUNE UDYAM REGISTRATION NUMBER: UDYAM-MH-26-0260262', 'BAMA INSTRUCHEM OPPOSITE MAHINDRA CIE CHAKAN AMBETHAN ROAD, AMBETHAN TAL. KHED DIST PUNE UDYAM REGISTRATION NUMBER: UDYAM-MH-26-0260262', 'pune', '410501', 'Maharashtra', '27AATFB5705A1ZM', '2026-01-22 06:34:52'),
(12, 'SUP009', 'BHAGWATI AUTOMATION & CONTROLS', 'BHAGWATI AUTOMATION', '9810571560', '-', NULL, 'Bhagwati Automation & Controls A-131, Sector-80, Phase-2, NOIDA-201305', 'Bhagwati Automation & Controls A-131, Sector-80, Phase-2, NOIDA-201305', 'NOIDA', '201305', 'Uttar Pradesh', '09ACWPN4427A1ZF', '2026-01-22 06:34:52'),
(13, 'SUP010', 'CAPRO ENTERPRISES', 'CAPRO', '9172880339', '-', NULL, 'Burhani Business Center j 178, MIDC, Bhosari Pune, Maharashtra 411026', 'Burhani Business Center j 178, MIDC, Bhosari Pune, Maharashtra 411026', 'pune', '411026', 'Maharashtra', '27AAKFC7498Q1ZB', '2026-01-22 06:34:52'),
(14, 'SUP011', 'DAS ENGINEERING CORPORATION', 'DAS ENGG', '7414929508', '-', NULL, 'DAS ENGINEERING CORPORATION Registered Office  Sales Office 1: In front of Roopsagar, Ambethan Square, Chakan, Pune - 410501', 'DAS ENGINEERING CORPORATION Registered Office : 4Ward, Near Sales Purchase Office, Malegaon, Tal- Malegaon, District - Washim - 444503.---------------------------------------- Sales Office 1: In front of Roopsagar, Ambethan Square, Chakan, Pune - 410501', 'pune', '410501', 'Maharashtra', '  27BVNPC3861L1', '2026-01-22 06:34:52'),
(15, 'SUP012', 'DL Pneumatics', 'DL DL', '9822794454', 'dl.pneumatic@yahoo.com', NULL, 'DL Pneumatics Sunshine Commercial Prem Co Op Soc Ltd Plot No BGP 124 BG Block Opp JBM Tools MIDC BHOSARI PUNE-411026', 'DL Pneumatics Sunshine Commercial Prem Co Op Soc Ltd Plot No BGP 124 BG Block Opp JBM Tools MIDC BHOSARI PUNE-411026', 'pune', '411026', 'Maharashtra', '27AEDPT5428A1ZN', '2026-01-22 06:34:52'),
(16, 'SUP013', 'ELECTROTRADES', 'VIKAS BIRADAR', '7559108908', '-', NULL, 'Electrotrades Shop no 2 , above rahul motors , sai jyoti apartment behind akurdi  police station, akurdi- 411044, , Pune, Maharashtra', 'Electrotrades Shop no 2 , above rahul motors , sai jyoti apartment behind akurdi  police station, akurdi- 411044, , Pune, Maharashtra', 'pune', '411044', 'Maharashtra', '27BAHPB7906A2Z4', '2026-01-22 06:34:52'),
(17, 'SUP014', 'Framlin Tech', 'Amit Panchal', '9998726569', 'framlintech@gmail.com', NULL, 'Framlin Tech I-227, SUMEL Business Park-6, Nr. Hanumanpura BRTS Bus Stand, Dudheshwar Road, Shahibaug, Ahmedabad - 380 004.', 'Framlin Tech I-227, SUMEL Business Park-6, Nr. Hanumanpura BRTS Bus Stand, Dudheshwar Road, Shahibaug, Ahmedabad - 380 004.', 'Ahmedabad', '380 004', 'GUJRAT', '24ARWPP5239M1ZX', '2026-01-22 06:34:52'),
(18, 'SUP015', 'GIRISH PACKAGING', 'GIRISH PACKAGING', '9146768256', 'girishpackaging2011@gmail.com', NULL, 'GIRISH PACKAGING SHOP NO 3, MILKAT NO 1108, GAT NO 1139, GHOTAVADE PHATA, PIRANGUT, PUNE - 412115', 'GIRISH PACKAGING SHOP NO 3, MILKAT NO 1108, GAT NO 1139, GHOTAVADE PHATA, PIRANGUT, PUNE - 412115', 'PUNE', '412115', 'Maharashtra', '27ALLPB4009N1Z0', '2026-01-22 06:34:52'),
(19, 'SUP016', 'GOUR ELECTRONICS', 'GOUR ELECTRONICS', '9021116190', 'gourcomponents@gmail.com', NULL, 'GOUR ELECTRONICS S. No. 683 Sukhwani Baug C-9, Adinath Nagar, Bhosari Pune', 'GOUR ELECTRONICS S. No. 683 Sukhwani Baug C-9, Adinath Nagar, Bhosari Pune', 'pune', '41103', 'Maharashtra', '27CUXPS9285G1ZE', '2026-01-22 06:34:52'),
(20, 'SUP017', 'HUBTRONICS', 'HUBTRONICS', '8459321114', '-', NULL, 'Punyoday Apartment, Survey No 26 CTS 1352 Aundh Wakad Road Pimpri Chinchwad Maharashtra, India - 411027', 'Punyoday Apartment, Survey No 26 CTS 1352 Aundh Wakad Road Pimpri Chinchwad Maharashtra, India - 411027', 'pune', '411027', 'Maharashtra', '27CXIPS5926C1Z7', '2026-01-22 06:34:52'),
(21, 'SUP018', 'JAII-BHAWANI LASER PRIVATE LIMITED', 'JAY BHAWANI', '7218323319', '-', NULL, 'Dhawade Wasti, SURVEY NO. 18 Philips Road Pimpri Chinchwad Maharashtra, India - 411039', 'Dhawade Wasti, SURVEY NO. 18 Philips Road Pimpri Chinchwad Maharashtra, India - 411039', 'pune', '411039', 'Maharashtra', '27AAGCJ8538H1Z6', '2026-01-22 06:34:52'),
(22, 'SUP019', 'Janani Hospital', 'Raghvendra Shukla', '7979703439', '-', NULL, 'ower house road Binodpur Bihar Bihar, India - 854105', 'ower house road Binodpur Bihar Bihar, India - 854105', 'BihaR', '854105', 'Maharashtra', '', '2026-01-22 06:34:52'),
(23, 'SUP020', 'KEY AUTOMATION', 'Jitendra Kurmi', '9755693029', 'ac.keyautomation@gmail.com', NULL, 'KEY AUTOMATION SEC. NO. 7, PLOT NO. 144A, GATE NO. 2, DIAS-C TOOLS AND COMPONENTS, PCNTDA, BHOSARI Maharashtra - 411026,', 'KEY AUTOMATION SEC. NO. 7, PLOT NO. 144A, GATE NO. 2, DIAS-C TOOLS AND COMPONENTS, PCNTDA, BHOSARI Maharashtra - 411026,', 'pune', '411026,', 'Maharashtra', '27AAUFK6791L1Z9', '2026-01-22 06:34:52'),
(24, 'SUP021', 'LAXMI TRADERS', 'LAXMI TRADERS', '9850023048', '-', NULL, 'LAXMI TRADERS Shop No. 1, Rameshwar Tower, Bajrang Nagar, Pimpri-Bhosari Rd. Pimpri, Pune - 411018', 'LAXMI TRADERS Shop No. 1, Rameshwar Tower, Bajrang Nagar, Pimpri-Bhosari Rd. Pimpri, Pune - 411018', 'pune', '411018', 'Maharashtra', '27AFKPC9422M1Z4', '2026-01-22 06:34:52'),
(25, 'SUP022', 'MACFOS LIMITED', 'ROBU MACFOSE', '8623015800', 'info@robu.in', NULL, 'MACFOS LIMITED  Sumant Building, Dynamic Logistics Trade Park Survey No. 78/1 Dighi, Bhosari Alandi Road Pune 411015 Maharashtra MH India', 'MACFOS LIMITED  Sumant Building, Dynamic Logistics Trade Park Survey No. 78/1 Dighi, Bhosari Alandi Road Pune 411015 Maharashtra MH India', 'pune', '411015', 'Maharashtra', '27AALCM3536H1ZA', '2026-01-22 06:34:52'),
(26, 'SUP023', 'MEDI BANK SYSTEMS', 'MEDIBANK SYSTEAM', '8826969551', '-', NULL, 'B-86,Second Floor, Front Portion, Block-B New Delhi, Delhi - 110064', 'B-86,Second Floor, Front Portion, Block-B New Delhi, Delhi - 110064', 'Delhi', '110064', 'NEW Delhi', '07ABMPC5747P1Z2', '2026-01-22 06:34:52'),
(27, 'SUP024', 'MEDIKOP LIFE SCIENCE', 'MEDIKOP SCIENCE', '9819065789', 'info@medikoplifescience.in', NULL, '391 & 392, PLOT NO: 8 & 9, Athal, Daman Ganga Ind. Est, Silvassa - 396230 U.T.\nGoregaon East\nMaharashtra, India - 396230', '391 & 392, PLOT NO: 8 & 9, Athal, Daman Ganga Ind. Est, Silvassa - 396230 U.T.\nGoregaon East\nMaharashtra, India - 396230', 'Silvassa', '396230', 'Goregaon East', '26ABDFM8623P1ZP', '2026-01-22 06:34:52'),
(28, 'SUP025', 'MEDK SURGICALS', 'MEDK SURGICALS', '7988479335', 'medksurgicals@gmail.com', NULL, '68,Madhu Colony, Behind Madhu Hotel,, Yamunanagar. Yamunanagar Haryana, India - 135001', '68,Madhu Colony, Behind Madhu Hotel,, Yamunanagar. Yamunanagar Haryana, India - 135001', 'Yamunanagar', '135001', 'Haryana', '06ACBFM1539R1Z', '2026-01-22 06:34:52'),
(29, 'SUP026', 'MUKESH METAL', 'MUKESH PIPE', '9619155449', '-', NULL, 'PELWAN BLDG, SHOP NO. 2/PLOT NO.7/ A/B SANT SENA MAHARAJ MARG 2 ND KUMBHARWADA MUMBAI Maharashtra, India - 400004', 'PELWAN BLDG, SHOP NO. 2/PLOT NO.7/ A/B SANT SENA MAHARAJ MARG 2 ND KUMBHARWADA MUMBAI Maharashtra, India - 400004', 'PUNE', '400004', 'Maharashtra', '27AMPPP6964P1ZS', '2026-01-22 06:34:52'),
(30, 'SUP027', 'NIKHIL ENGINEERING WORKS', 'AMOL NIKHIL', '9763973885', '-', NULL, 'GOLD COUNTY, GAT NO-463/2, BL NO-A/608 CHARHOLI Pimpri Chinchwad Maharashtra, India - 412105', 'GOLD COUNTY, GAT NO-463/2, BL NO-A/608 CHARHOLI Pimpri Chinchwad Maharashtra, India - 412105', 'PUNE', '412105', 'Maharashtra', '27BEXPC5221B1ZQ', '2026-01-22 06:34:52'),
(31, 'SUP028', 'NIKHIL ENGINEERING WORKS', 'SUNIL ENGG', '9860085080', 'nikhil.engg143@gmail.com', NULL, 'Plot no: 127/B, Secto-10, PCNTDA Industrial Area, Bhosari, Pune-411026 Pune Maharashtra, India - 411026', 'Plot no: 127/B, Secto-10, PCNTDA Industrial Area, Bhosari, Pune-411026 Pune Maharashtra, India - 411026', 'PUNE', '411026', 'Maharashtra', '27ABEPB4149E1Z2', '2026-01-22 06:34:52'),
(32, 'SUP029', 'NISHA SALES', 'NISHA SALES', '9325299077', 'nishasales@gmail.com', NULL, '495, YASH CLASSIC, OPP RAJESH TRADING COMPANY, DARUWALA PUL, GANESH PETH, PUNE 411002 pune Maharashtra, India - 411002', '495, YASH CLASSIC, OPP RAJESH TRADING COMPANY, DARUWALA PUL, GANESH PETH, PUNE 411002 pune Maharashtra, India - 411002', 'PUNE', '411002', 'Maharashtra', '27AATFN9736F1ZL', '2026-01-22 06:34:52'),
(33, 'SUP030', 'Oneness', 'Oneness Enterprises', '9552484701', '-', NULL, 'S. NO. 12/2,3,4, FLAT NO. 2 & 3 1ST FLOOR, GALAXY CORNER NEAR KAILAS JIVAN FACTORY, SAI PURAM BUS STOP, DHAYARI PUNE - 411041 PUNE Maharashtra, India - 411041', 'S. NO. 12/2,3,4, FLAT NO. 2 & 3 1ST FLOOR, GALAXY CORNER NEAR KAILAS JIVAN FACTORY, SAI PURAM BUS STOP, DHAYARI PUNE - 411041 PUNE Maharashtra, India - 411041 GSTIN : 27BGMPM8256M1ZJ', 'PUNE', '411041', 'Maharashtra', '27BGMPM8256M1ZJ', '2026-01-22 06:34:52'),
(34, 'SUP031', 'PRAKASH ELECTRICALS AND HARDWARE', 'PRAKSH ELECTRICAL', '9765118850', '-', NULL, 'PIRANGUT, 1633 GHOTAWADE PHATA, TAL-MULSHI PUNE Maharashtra, India - 410004', 'PIRANGUT, 1633 GHOTAWADE PHATA, TAL-MULSHI PUNE Maharashtra, India - 410004', 'PUNE', '410004', 'Maharashtra', '27AHCPC1348L1ZH', '2026-01-22 06:34:52'),
(35, 'SUP032', 'PREM ELECTRICALS', 'PREM ELECTRICAL', '9822043418', 'ankuragarwal187@yahoo.in', NULL, 'MIDC, Plot No S 11 Telco Road Pimpri Chinchwad Maharashtra, India - 411026', 'MIDC, Plot No S 11 Telco Road Pimpri Chinchwad Maharashtra, India - 411026', 'PUNE', '411026', 'Maharashtra', '27ATLPA7704M1ZF', '2026-01-22 06:34:52'),
(36, 'SUP033', 'PRIME DISTRIBUTORS', 'PRIME DIRTRIBUTOR', '9373266066', 'prime.bkb@gmail.com', NULL, 'Khira Ind Co Op Society, SR No 71/2/2&3 Plot No 71/1 MIDC Road Pimpri Chinchwad Maharashtra, India - 411026', 'Khira Ind Co Op Society, SR No 71/2/2&3 Plot No 71/1 MIDC Road Pimpri Chinchwad Maharashtra, India - 411026', 'PUNE', '411026', 'Maharashtra', '27AANFP9098C1ZO', '2026-01-22 06:34:52'),
(37, 'SUP034', 'PRIME MEDICAL EQUIPMENT', 'BIPUL DAS', '9874404751', '-', NULL, '2, SHIBRAMPUR LANE, HALDER PARA, KOLKATA-700061 Phone-(033) 9874404751', '2, SHIBRAMPUR LANE, HALDER PARA, KOLKATA-700061 Phone-(033) 9874404751', 'KOLKATA', '700061', 'West Bengal', '19APPPD3114CIZA', '2026-01-22 06:34:52'),
(38, 'SUP035', 'PROBOTS ELECTRONICS INDIA PRIVATE LIMITED', 'PROBOTS', '8618050703', '-', NULL, 'Sankalpa, 530 5th Cross Rd, Vinayaka Layout Bengaluru Karnataka, India - 560072', 'Sankalpa, 530 5th Cross Rd, Vinayaka Layout Bengaluru Karnataka, India - 560072', 'Bengaluru (Bangalore)', '560072', 'Karnataka', '29AANCP8428M1ZH', '2026-01-22 06:34:52'),
(39, 'SUP036', 'QUICK SENSE INDIA', 'VISHAL', '9921387685', '-', NULL, 'BHALEKAR BLDG, GAT NO. 467 Talwade Nigdi Road Pimpri Chinchwad Maharashtra, India - 411062', 'BHALEKAR BLDG, GAT NO. 467 Talwade Nigdi Road Pimpri Chinchwad Maharashtra, India - 411062', 'PUNE', '411062', 'Maharashtra', '27APFPG1361J1Z0', '2026-01-22 06:34:52'),
(40, 'SUP037', 'RAHUL INDUSTRIAL PRODUCTS', 'RAHUL INDUSTRIES', '8788523086', '-', NULL, 'NEAR MAHINDRA LIFESPACE, CTS 5438, SHOP NO. 3, NEAR GANESH MANDIR PIMPRI, PUNE- 411018 Maharashtra, India - 411018', 'NEAR MAHINDRA LIFESPACE, CTS 5438, SHOP NO. 3, NEAR GANESH MANDIR PIMPRI, PUNE- 411018 Maharashtra, India - 411018', 'PUNE', '411018', 'Maharashtra', '27AMZPP6162F1ZD', '2026-01-22 06:34:52'),
(41, 'SUP038', 'RUNNER CASTORS WHEELS PRIVATE LIMITED', 'RUNNER CASTORS', '9911513139', 'runnercasterswheels@gmail.com', NULL, 'GALI NO 8 KADIPUR INDUSTRIAL AREA, KH NO 343 363 KILLA NO 23 BASAI ROAD Gurugram Haryana, India - 122001', 'GALI NO 8 KADIPUR INDUSTRIAL AREA, KH NO 343 363 KILLA NO 23 BASAI ROAD Gurugram Haryana, India - 122001', 'Gurugram', '122001', 'Haryana', '06AAOCR6354B1ZC', '2026-01-22 06:34:52'),
(42, 'SUP039', 'RUSHIKESH ENTERPRISES', 'Rushikesh ENTERPRISES', '7798036631', 'rushikeshenterprises024@gmail.com', NULL, 'Balaji Nagar, Plot No. 56 Daund Taluka Daund Maharashtra, India - 413801', 'Balaji Nagar, Plot No. 56 Daund Taluka Daund Maharashtra, India - 413801', 'PUNE', '413801', 'Maharashtra', '27AUSPP9375P1Z6', '2026-01-22 06:34:52'),
(43, 'SUP040', 'SD PROCESS EQUIPMENT', 'SD PROCESS SD PROCESS', '9833887372', '-', NULL, 'Office No. 53, Shree Manoshi Complex, Plot No. 5 & 6, Sector 3, Opp. Ghansoli Railway Station Ghansoli (East). Navi Mumbai - 400701 Mumbai Maharashtra, India - 400701', 'Office No. 53, Shree Manoshi Complex, Plot No. 5 & 6, Sector 3, Opp. Ghansoli Railway Station Ghansoli (East). Navi Mumbai - 400701 Mumbai Maharashtra, India - 400701', 'Mumbai', '400701', 'Maharashtra', '27AATFS4002M1ZS', '2026-01-22 06:34:52'),
(44, 'SUP041', 'STAR ENTERPRISES', 'HUSAIN HUNED NAGARWALA', '9529437693', 'starenterprises2053@gmail.com', NULL, 'Bhosari Business Center, SHOP NO 13, GROUND FLOOR BHOSARI MIDC ROAD Pimpri Chinchwad Maharashtra, India - 411026', 'Bhosari Business Center, SHOP NO 13, GROUND FLOOR BHOSARI MIDC ROAD Pimpri Chinchwad Maharashtra, India - 411026', 'PUNE', '411026', 'Maharashtra', '27CEBPN8542M1ZZ', '2026-01-22 06:34:52'),
(45, 'SUP042', 'Sulekha Energy Solutions', 'Rohit Kavde', '9511808595', '-', NULL, 'Pesh Industrial Premises, 31 Telco Road Pimpri Chinchwad Maharashtra, India - 411026', 'Pesh Industrial Premises, 31 Telco Road Pimpri Chinchwad Maharashtra, India - 411026', 'PUNE', '411026', 'Maharashtra', '27BSQPK2741F1ZL', '2026-01-22 06:34:52'),
(46, 'SUP043', 'TECHNOMET ENTERPRISES PUNE', 'TECHNOMET ENTERPRISES', '9850083743', 'sales@technometent.co.in', NULL, 'ARAD VINAYAK, S NO 26/1 SHOP NO. 102, 103, 104 Narhe Gaon Road Pune Maharashtra, India - 411041', 'ARAD VINAYAK, S NO 26/1 SHOP NO. 102, 103, 104 Narhe Gaon Road Pune Maharashtra, India - 411041', 'PUNE', '411041', 'Maharashtra', '27AASFT7375F1ZH', '2026-01-22 06:34:52'),
(47, 'SUP044', 'THINVENT TECHNOLOGIES PRIVATE LIMITED', 'THINVENT', '7303695804', '-', NULL, 'Panchayat Building Gangoz Ward,Salvador Do Mundo,Bardez Penha De Franca Goa, India - 403101', 'Panchayat Building Gangoz Ward,Salvador Do Mundo,Bardez Penha De Franca Goa, India - 403101', 'PUNE', '403101', 'Maharashtra', '30AACCT8114F1ZT', '2026-01-22 06:34:52'),
(48, 'SUP045', 'TRIO RADIO & ELECTRONIC CORPORATION', 'TRIO', '7887887854', '-', NULL, '468 PASODYA VITHOBA MANDIR BUDHWAR PETH Maharashtra, India - 411002', '', 'PUNE', '411002', 'Maharashtra', '27AABFT1043R1ZW', '2026-01-22 06:34:52'),
(49, 'SUP046', 'YOGIRAJ HARDWARE AND ELECTRICALS', 'YOGIRAJ HARDWARE', '9371089804', '-', NULL, 'CHANDRAKALA BUILDING, SURVEY NO. 677, SHOP NO.5 LANDEWADI, BHOSARI NEAR SANDIP STORES Maharashtra, India - 411039', 'CHANDRAKALA BUILDING, SURVEY NO. 677, SHOP NO.5 LANDEWADI, BHOSARI NEAR SANDIP STORES Maharashtra, India - 411039', 'PUNE', '411039', 'Maharashtra', '27AGTPB6214E1ZJ', '2026-01-22 06:34:52'),
(50, 'SUP047', 'AYAN BIO Wellness Surgical', 'AYAN BIO', '7507280280', 'wellnesssurgical2@gmail.com', NULL, 'Shop no 16 Ground floor, Bhosale Arcade Nehru Nagar, Bhosari Road, Pimpri, Pune -411018, Pune, Maharashtra, 411018', 'Shop no 16 Ground floor, Bhosale Arcade Nehru Nagar, Bhosari Road, Pimpri, Pune -411018, Pune, Maharashtra, 411018', 'PUNE', '411018', 'Maharashtra', '27BPPPB0536K2ZS', '2026-01-22 06:34:52'),
(51, 'SUP048', 'Dashmesh Sonail Healthcare Pvt. Ltd.', 'DASHMESH', '99206 85504', 'office@dashclarity.com', NULL, '10-12,Rajprabha Indl. Encalve 5, Bldg. No.1, Boidapada, Vasai (E), District Palghar - 401208', '10-12,Rajprabha Indl. Encalve 5, Bldg. No.1, Boidapada, Vasai (E), District Palghar - 401208', 'Vasai (East)', '401208', 'Maharashtra', '27AAACD5094N1Z8', '2026-01-22 06:34:52');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `task_no` varchar(20) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status` enum('Not Started','In Progress','On Hold','Completed','Cancelled') DEFAULT 'Not Started',
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `all_day` tinyint(1) DEFAULT 1,
  `recurrence_type` enum('none','daily','weekly','monthly','yearly') DEFAULT 'none',
  `recurrence_end_date` date DEFAULT NULL,
  `color_code` varchar(7) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `progress_percent` int(11) DEFAULT 0,
  `related_module` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_reference` varchar(100) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `estimated_hours` decimal(8,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `task_no`, `task_name`, `task_description`, `category_id`, `priority`, `status`, `assigned_to`, `assigned_by`, `start_date`, `start_time`, `end_time`, `all_day`, `recurrence_type`, `recurrence_end_date`, `color_code`, `due_date`, `completed_date`, `progress_percent`, `related_module`, `related_id`, `related_reference`, `customer_id`, `project_id`, `estimated_hours`, `actual_hours`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 'TASK-00001', '10 Nos main PCB and Power PCb production', 'jj', 11, 'High', 'In Progress', 67, NULL, '2026-01-23', NULL, NULL, 1, 'none', NULL, NULL, '2026-01-23', NULL, 0, NULL, NULL, '', NULL, NULL, 2.00, NULL, 'hhh', NULL, '2026-01-23 03:16:20', '2026-01-23 03:16:48'),
(4, 'TASK-00002', 'GST filling', '', 9, 'Critical', 'Not Started', 63, NULL, '2026-01-30', NULL, NULL, 1, 'none', NULL, NULL, NULL, NULL, 0, 'finance', NULL, '', NULL, NULL, 2.00, NULL, '', NULL, '2026-01-30 03:44:16', '2026-01-30 03:44:16'),
(5, 'TASK-00003', 'Multipara Bracket Design', '', 2, 'Medium', 'Not Started', 63, NULL, '2026-01-30', '09:00:00', '17:00:00', 1, 'none', NULL, NULL, NULL, NULL, 0, NULL, NULL, '', NULL, NULL, 3.00, NULL, '', NULL, '2026-01-30 05:02:07', '2026-01-30 05:02:07'),
(6, 'TASK-00004', '10 pin power cable harness', '2 qty 10 pin power cable harness', 5, 'Medium', 'Completed', 66, NULL, '2026-01-30', '09:00:00', '17:00:00', 1, 'none', NULL, NULL, '2026-01-30', '2026-01-30', 100, NULL, NULL, '', NULL, NULL, 1.00, NULL, '', NULL, '2026-01-30 05:02:15', '2026-01-30 05:02:34'),
(8, 'TASK-00006', 'SOFTWARE', '', NULL, 'Medium', 'Not Started', NULL, NULL, '2026-01-30', '09:00:00', '17:00:00', 1, 'none', NULL, NULL, NULL, NULL, 0, NULL, NULL, '', NULL, NULL, NULL, NULL, '', NULL, '2026-01-30 05:02:30', '2026-01-30 05:02:30'),
(13, 'TASK-00010', 'ASSEMBLY', 'XVS 9500', 5, 'Medium', 'Completed', 73, NULL, '2026-01-31', '09:00:00', '17:00:00', 1, 'none', NULL, NULL, '2026-01-31', '2026-01-31', 100, NULL, NULL, '', NULL, NULL, NULL, NULL, '', NULL, '2026-01-31 05:00:26', '2026-01-31 05:00:44'),
(14, 'TASK-00011', 'Gauge - Bipul', '', NULL, 'Medium', 'Completed', 77, NULL, '2026-01-31', '09:00:00', '17:00:00', 1, 'none', NULL, NULL, '2026-01-31', '2026-01-31', 100, NULL, NULL, '', NULL, 2, 2.00, NULL, '', NULL, '2026-01-31 05:03:31', '2026-01-31 05:03:39'),
(16, 'TASK-00013', 'part master creation', 'part lst', 7, 'Critical', 'In Progress', 65, NULL, '2026-02-01', '10:00:00', '18:00:00', 0, 'none', NULL, NULL, '2026-02-02', NULL, 35, 'inventory', NULL, '', NULL, NULL, 4.00, NULL, 'dd', NULL, '2026-02-01 02:13:30', '2026-02-01 02:15:31');

-- --------------------------------------------------------

--
-- Table structure for table `task_attachments`
--

CREATE TABLE `task_attachments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_categories`
--

CREATE TABLE `task_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `color_code` varchar(7) DEFAULT '#3498db',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_categories`
--

INSERT INTO `task_categories` (`id`, `category_name`, `category_code`, `color_code`, `description`, `is_active`, `created_at`) VALUES
(1, 'General', 'GEN', '#95a5a6', 'General tasks not specific to any department', 1, '2026-01-21 10:04:52'),
(2, 'Sales', 'SALES', '#e74c3c', 'Sales and CRM related tasks', 1, '2026-01-21 10:04:52'),
(3, 'Marketing', 'MKT', '#f39c12', 'Marketing campaigns and activities', 1, '2026-01-21 10:04:52'),
(4, 'HR', 'HR', '#9b59b6', 'Human resources related tasks', 1, '2026-01-21 10:04:52'),
(5, 'Operations', 'OPS', '#1abc9c', 'Operations and manufacturing tasks', 1, '2026-01-21 10:04:52'),
(6, 'Purchase', 'PUR', '#3498db', 'Purchase and procurement tasks', 1, '2026-01-21 10:04:52'),
(7, 'Inventory', 'INV', '#2ecc71', 'Inventory management tasks', 1, '2026-01-21 10:04:52'),
(8, 'Service', 'SVC', '#e67e22', 'Customer service and support tasks', 1, '2026-01-21 10:04:52'),
(9, 'Finance', 'FIN', '#34495e', 'Finance and accounting tasks', 1, '2026-01-21 10:04:52'),
(10, 'IT', 'IT', '#8e44ad', 'IT and technical tasks', 1, '2026-01-21 10:04:52'),
(11, 'Admin', 'ADMIN', '#7f8c8d', 'Administrative tasks', 1, '2026-01-21 10:04:52');

-- --------------------------------------------------------

--
-- Table structure for table `task_checklist`
--

CREATE TABLE `task_checklist` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `item_text` varchar(255) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_checklist`
--

INSERT INTO `task_checklist` (`id`, `task_id`, `item_text`, `is_completed`, `completed_at`, `completed_by`, `sort_order`, `created_at`) VALUES
(1, 16, 'sss', 0, NULL, NULL, 1, '2026-02-01 02:15:03');

-- --------------------------------------------------------

--
-- Table structure for table `task_comments`
--

CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `commented_by` int(11) DEFAULT NULL,
  `comment_type` enum('comment','status_change','progress_update','assignment') DEFAULT 'comment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_comments`
--

INSERT INTO `task_comments` (`id`, `task_id`, `comment`, `commented_by`, `comment_type`, `created_at`) VALUES
(3, 3, 'Task created and assigned', NULL, 'assignment', '2026-01-23 03:16:20'),
(4, 3, 'Status changed from \'Not Started\' to \'In Progress\'', NULL, 'status_change', '2026-01-23 03:16:48'),
(5, 4, 'Task created and assigned', NULL, 'assignment', '2026-01-30 03:44:16'),
(6, 5, 'Task created and assigned', NULL, 'assignment', '2026-01-30 05:02:07'),
(7, 6, 'Task created and assigned', NULL, 'assignment', '2026-01-30 05:02:15'),
(8, 6, 'Status changed from \'Not Started\' to \'In Progress\'', NULL, 'status_change', '2026-01-30 05:02:24'),
(9, 6, 'Status changed from \'In Progress\' to \'Completed\'', NULL, 'status_change', '2026-01-30 05:02:34'),
(13, 13, 'Task created and assigned', NULL, 'assignment', '2026-01-31 05:00:26'),
(14, 13, 'Status changed from \'Not Started\' to \'In Progress\'', NULL, 'status_change', '2026-01-31 05:00:35'),
(15, 13, 'Status changed from \'In Progress\' to \'On Hold\'', NULL, 'status_change', '2026-01-31 05:00:39'),
(16, 13, 'Status changed from \'On Hold\' to \'Completed\'', NULL, 'status_change', '2026-01-31 05:00:44'),
(17, 14, 'Task created and assigned', NULL, 'assignment', '2026-01-31 05:03:31'),
(18, 14, 'Status changed from \'In Progress\' to \'Completed\'', NULL, 'status_change', '2026-01-31 05:03:39'),
(20, 16, 'Task created and assigned', NULL, 'assignment', '2026-02-01 02:13:30'),
(21, 16, 'Status changed from \'Not Started\' to \'In Progress\'', NULL, 'status_change', '2026-02-01 02:14:26'),
(22, 16, 'Status changed from \'In Progress\' to \'On Hold\'', NULL, 'status_change', '2026-02-01 02:14:29'),
(23, 16, 'Status changed from \'On Hold\' to \'In Progress\'', NULL, 'status_change', '2026-02-01 02:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','user','viewer') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `phone`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$Nc54GszNrswGNFY9.D.Zp.ly9mTAfBDa23RSqMIThohdPH580uEfi', 'Administrator', NULL, NULL, 'admin', 1, '2026-02-01 19:26:21', '2026-01-18 09:43:57', '2026-02-01 13:56:21'),
(2, 'aryan', '$2y$10$6aw/reDxIcZ1kBqqSG.U7uc.n73nZ2Ot1568f5UQ8JCJXN1.5hJU.', 'Aryan Aich', 'aryanaich008@gmail.com', '7264800591', 'manager', 1, '2026-01-18 20:02:23', '2026-01-18 14:31:36', '2026-01-18 14:32:23'),
(4, 'neha', '$2y$10$sSJ1sEHd8vaxSKPg6TL/heT9JD9IoJBL9QWY9Go16x5wNvalKCRsy', 'Neha Govind Ukale', 'neha.ukale@yashka.io', '9834700927', 'manager', 1, '2026-01-31 10:01:28', '2026-01-24 08:04:15', '2026-01-31 04:42:57'),
(5, 'kishori', '$2y$10$CGI.LgOiL1x.1V7II9hhR.jjl9YVP6qCmDx4i6s8aDNdbsHAO7xVC', 'Ms. Kishori Prakash Gadakh', 'kishorigadakh@yashka.io', '7499180634', 'manager', 1, '2026-01-31 10:00:47', '2026-01-29 04:18:38', '2026-01-31 04:30:47'),
(6, 'pranjali', '$2y$10$/kFGBiJigVPPfyn0L9qzJ.sGBgD2B7Q56dtrNtbLw2ZEKvSSUaE1m', 'Pranjali mahadev Sampate', 'pranjali.sampate@yashka.io', '7387839917', 'manager', 1, '2026-01-31 09:56:05', '2026-01-29 04:19:00', '2026-01-31 04:26:05'),
(7, 'sana', '$2y$10$saru/64OcPOblIyqJhU4l.TBb0nd60WUOWTqknF64XIf.XL2zS1QO', 'Sana mahammad Nayakwadi', 'sana.nayakwadi@yashka.io', '7517301231', 'manager', 1, '2026-01-31 10:02:49', '2026-01-29 04:19:31', '2026-01-31 04:32:49'),
(8, 'vikram', '$2y$10$G6Wa3Q3mdQl8zTo0g7Ztf.1VuDrGxCmYWfL4G9IX/V6wEeNskIc/6', 'Vikram  Popat Pawar', 'vikram.pawar@yashka.io', '7709730190', 'manager', 1, '2026-01-31 10:55:38', '2026-01-29 04:19:49', '2026-01-31 05:25:38'),
(9, 'priyanka', '$2y$10$MxnQ0jQX.A3W5jPQwjfGluJBAOQjV5lu19Gr9uskmL3pXp9RWpXHG', 'Priyanka Ramesh Gaikwad', 'priyanka@yashka.io', '9657547130', 'manager', 1, '2026-01-30 10:09:32', '2026-01-29 04:21:27', '2026-01-30 04:39:32'),
(10, 'dipankar', '$2y$10$5p1psNsezXi8CqYG37cZPeqeO7UgJ4dxb7PZaQCHXwgRQh18lshXy', 'Dipankar Aich', 'yashkacontacts@gmail.com', '7775823557', 'user', 1, NULL, '2026-01-29 04:22:53', '2026-01-29 04:22:53'),
(11, 'shravani', '$2y$10$pP8AeBWzj5CtMh9M25tnTuou2BGBRZau87yJ7VfUiPKe4VzCoLj4G', 'shravani jadhav', 'shravani@yashka.io', '9067270108', 'user', 1, '2026-01-30 15:42:23', '2026-01-29 08:04:02', '2026-01-30 10:12:23'),
(12, 'washim', '$2y$10$V/x8PHC5MJ/tkvRlSk.Q0.14afVi27wQe7rj.v7qvVekmF1Ub/oBO', 'Wasim wakeel Shaikh', 'wasim.ahmad@yashka.io', '9370667697', 'user', 1, '2026-01-31 10:01:38', '2026-01-29 08:34:20', '2026-01-31 04:31:38');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES
(34, 7, 'crm', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(35, 7, 'customers', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(36, 7, 'quotes', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(37, 7, 'proforma', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(38, 7, 'customer_po', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(39, 7, 'sales_orders', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(40, 7, 'invoices', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(41, 7, 'installations', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(42, 7, 'suppliers', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(43, 7, 'purchase', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(44, 7, 'procurement', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(45, 7, 'part_master', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(46, 7, 'stock_entry', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(47, 7, 'depletion', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(48, 7, 'inventory', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(49, 7, 'reports', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(50, 7, 'bom', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(51, 7, 'work_orders', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(52, 7, 'hr_employees', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(53, 7, 'hr_attendance', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(54, 7, 'hr_payroll', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(55, 7, 'marketing_catalogs', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(56, 7, 'marketing_campaigns', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(57, 7, 'marketing_whatsapp', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(58, 7, 'marketing_analytics', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(59, 7, 'service_complaints', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(60, 7, 'service_technicians', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(61, 7, 'service_analytics', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(62, 7, 'tasks', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(63, 7, 'project_management', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(64, 7, 'admin_settings', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(65, 7, 'admin_users', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(66, 7, 'admin_locations', 1, 1, 1, 1, '2026-01-30 04:48:39', '2026-01-30 04:48:39'),
(67, 4, 'crm', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(68, 4, 'customers', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(69, 4, 'quotes', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(70, 4, 'proforma', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(71, 4, 'customer_po', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(72, 4, 'sales_orders', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(73, 4, 'invoices', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(74, 4, 'installations', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(75, 4, 'suppliers', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(76, 4, 'purchase', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(77, 4, 'procurement', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(78, 4, 'part_master', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(79, 4, 'stock_entry', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(80, 4, 'depletion', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(81, 4, 'inventory', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(82, 4, 'reports', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(83, 4, 'bom', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(84, 4, 'work_orders', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(85, 4, 'hr_employees', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(86, 4, 'hr_attendance', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(87, 4, 'hr_payroll', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(88, 4, 'marketing_catalogs', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(89, 4, 'marketing_campaigns', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(90, 4, 'marketing_whatsapp', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(91, 4, 'marketing_analytics', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(92, 4, 'service_complaints', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(93, 4, 'service_technicians', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(94, 4, 'service_analytics', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(95, 4, 'tasks', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(96, 4, 'project_management', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(97, 4, 'admin_settings', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(98, 4, 'admin_users', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(99, 4, 'admin_locations', 1, 1, 1, 1, '2026-01-30 04:49:30', '2026-01-30 04:49:30'),
(133, 5, 'crm', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(134, 5, 'customers', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(135, 5, 'quotes', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(136, 5, 'proforma', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(137, 5, 'customer_po', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(138, 5, 'sales_orders', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(139, 5, 'invoices', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(140, 5, 'installations', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(141, 5, 'suppliers', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(142, 5, 'purchase', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(143, 5, 'procurement', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(144, 5, 'part_master', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(145, 5, 'stock_entry', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(146, 5, 'depletion', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(147, 5, 'inventory', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(148, 5, 'reports', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(149, 5, 'bom', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(150, 5, 'work_orders', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(151, 5, 'hr_employees', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(152, 5, 'hr_attendance', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(153, 5, 'hr_payroll', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(154, 5, 'marketing_catalogs', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(155, 5, 'marketing_campaigns', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(156, 5, 'marketing_whatsapp', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(157, 5, 'marketing_analytics', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(158, 5, 'service_complaints', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(159, 5, 'service_technicians', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(160, 5, 'service_analytics', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(161, 5, 'tasks', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(162, 5, 'project_management', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(163, 5, 'admin_settings', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(164, 5, 'admin_users', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(165, 5, 'admin_locations', 1, 1, 1, 1, '2026-01-30 06:09:16', '2026-01-30 06:09:16'),
(166, 12, 'crm', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(167, 12, 'customers', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(168, 12, 'quotes', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(169, 12, 'proforma', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(170, 12, 'customer_po', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(171, 12, 'sales_orders', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(172, 12, 'invoices', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(173, 12, 'installations', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(174, 12, 'suppliers', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(175, 12, 'purchase', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(176, 12, 'procurement', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(177, 12, 'part_master', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(178, 12, 'stock_entry', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(179, 12, 'depletion', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(180, 12, 'inventory', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(181, 12, 'reports', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(182, 12, 'bom', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(183, 12, 'work_orders', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(184, 12, 'hr_employees', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(185, 12, 'hr_attendance', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(186, 12, 'hr_payroll', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(187, 12, 'marketing_catalogs', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(188, 12, 'marketing_campaigns', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(189, 12, 'marketing_whatsapp', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(190, 12, 'marketing_analytics', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(191, 12, 'service_complaints', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(192, 12, 'service_technicians', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(193, 12, 'service_analytics', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(194, 12, 'tasks', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(195, 12, 'project_management', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(196, 12, 'admin_settings', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(197, 12, 'admin_users', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(198, 12, 'admin_locations', 1, 1, 1, 1, '2026-01-31 04:20:26', '2026-01-31 04:20:26'),
(199, 6, 'crm', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(200, 6, 'customers', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(201, 6, 'quotes', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(202, 6, 'proforma', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(203, 6, 'customer_po', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(204, 6, 'sales_orders', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(205, 6, 'invoices', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(206, 6, 'installations', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(207, 6, 'suppliers', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(208, 6, 'purchase', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(209, 6, 'procurement', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(210, 6, 'part_master', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(211, 6, 'stock_entry', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(212, 6, 'depletion', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(213, 6, 'inventory', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(214, 6, 'reports', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(215, 6, 'bom', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(216, 6, 'work_orders', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(217, 6, 'hr_employees', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(218, 6, 'hr_attendance', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(219, 6, 'hr_payroll', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(220, 6, 'marketing_catalogs', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(221, 6, 'marketing_campaigns', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(222, 6, 'marketing_whatsapp', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(223, 6, 'marketing_analytics', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(224, 6, 'service_complaints', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(225, 6, 'service_technicians', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(226, 6, 'service_analytics', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(227, 6, 'tasks', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(228, 6, 'project_management', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(229, 6, 'admin_settings', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(230, 6, 'admin_users', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34'),
(231, 6, 'admin_locations', 1, 1, 1, 1, '2026-01-31 04:21:34', '2026-01-31 04:21:34');

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_log`
--

CREATE TABLE `whatsapp_log` (
  `id` int(11) NOT NULL,
  `recipient_type` enum('customer','lead') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `sent_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `whatsapp_log`
--

INSERT INTO `whatsapp_log` (`id`, `recipient_type`, `recipient_id`, `recipient_name`, `phone`, `message`, `attachment_name`, `sent_at`, `sent_by`) VALUES
(1, 'customer', 3, 'Aryan Aich', '7264800591', 'Hi Aryan Aich,\n\nFollowing up on our previous conversation. I wanted to check if you had any questions or need any additional information.\n\nLooking forward to hearing from you!\n\nThanks', 'Print.pdf', '2026-01-18 22:29:01', NULL),
(2, 'customer', 3, 'Aryan Aich', '7264800591', 'Hi Aryan Aich,\n\nFollowing up on our previous conversation. I wanted to check if you had any questions or need any additional information.\n\nLooking forward to hearing from you!\n\nThanks', 'Print.pdf', '2026-01-18 22:29:14', NULL),
(3, 'customer', 6, '7775823557', '07264800591', 'Hello 7775823557!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards!\n\n📎 File: http://localhost/uploads/whatsapp/wa_696d12a6c75137.72205856.pdf', 'Print.pdf', '2026-01-18 22:34:41', NULL),
(4, 'customer', 6, '7775823557', '07264800591', 'Dear 7775823557,\n\nWe have an exciting special offer just for you!\n\n[Offer Details Here]\n\nThis offer is valid until [Date]. Don\'t miss out!\n\nReply to know more.', '', '2026-01-18 22:38:33', NULL),
(5, 'lead', 1, '7775823557', '07264800591', 'Dear 7775823557,\n\nWe have an exciting special offer just for you!\n\n[Offer Details Here]\n\nThis offer is valid until [Date]. Don\'t miss out!\n\nReply to know more.', '', '2026-01-18 22:39:24', NULL),
(6, 'customer', 6, '7775823557', '07264800591', 'Hello 7775823557!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards!\n\n📎 File: http://192.168.1.7/uploads/whatsapp/wa_696d13c6b01f84.11878321.pdf', 'Ahen 4000.pdf', '2026-01-18 22:40:52', NULL),
(7, 'customer', 6, 'Aryan', '07264800591', 'Hello Aryan!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards!', '', '2026-01-18 22:42:22', NULL),
(8, 'customer', 6, 'Aryan', '07264800591', 'Hello Aryan!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards! \nYashka.io', '', '2026-01-18 22:43:20', NULL),
(9, 'customer', 6, 'Aryan', '07264800591', 'Hello Aryan!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards!\n\nhttp://192.168.1.7/uploads/whatsapp/wa_696d1593d7e6a2.93707765.jpg', 'ahen4000.jpg', '2026-01-18 22:47:11', NULL),
(10, 'customer', 6, 'Aryan', '07264800591', 'Hello Aryan!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards! 1\n\nhttp://localhost/uploads/whatsapp/wa_696d156c570bc6.32142686.pdf', 'Print.pdf', '2026-01-18 22:47:21', NULL),
(11, 'customer', 6, 'Aryan', '07264800591', 'Hello Aryan!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards!\n\nhttp://192.168.1.7/uploads/whatsapp/wa_696d1593d7e6a2.93707765.jpg\n\nhttp://192.168.1.7/uploads/whatsapp/wa_696d1593d7e6a2.93707765.jpg', 'ahen4000.jpg', '2026-01-18 22:48:39', NULL),
(12, 'customer', 6, 'Aryan', '07264800591', 'http://192.168.1.7/uploads/whatsapp/wa_696d1593d7e6a2.93707765.jpg\n\nhttp://192.168.1.7/uploads/whatsapp/wa_696d16178d3779.49647652.jpg', 'ahen4500.jpg', '2026-01-18 22:49:45', NULL),
(13, 'customer', 6, 'Aryan', '07264800591', 'Hello Aryan!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards!\n\nhttp://192.168.1.7/uploads/catalogs/CAT-0001_brochure_1768748434.pdf', 'HVS Universal (CAT-0001)', '2026-01-18 23:00:42', NULL),
(14, 'customer', 6, 'Aryan', '07264800591', 'Dear Aryan,\n\nWe have an exciting special offer just for you!\n\n[Offer Details Here]\n\nThis offer is valid until [Date]. Don\'t miss out!\n\nReply to know more.\n\nhttp://localhost/uploads/catalogs/CAT-0002_brochure_1768972104.pdf', 'dvdf (CAT-0002)', '2026-01-21 10:39:13', NULL),
(15, 'lead', 16, 'India', '9156315725', '1) Compressor Based\n2) 10.1\" screen\n3) Universal Model\n4) Internal Sensors\n5) No standby Time\n6) 3 Hours Battery Backup\n7) Humidifier\n\nhttp://10.212.240.224/uploads/whatsapp/wa_697b145a27ddf2.65285547.jpeg', 'HVS Marketing.jpeg', '2026-01-29 13:33:53', NULL),
(16, 'customer', 36, 'Soumitra', '9433085901', 'Hi sir,\n\nFollowing up on our previous conversation. I wanted to check if you had any questions or need any additional information.\n\nLooking forward to hearing from you!\n\nThanks\n\nhttp://10.180.182.224/uploads/catalogs/CAT-0003_brochure_1769673727.pdf', 'HVS 3500 XL (CAT-0003)', '2026-01-31 10:18:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_templates`
--

CREATE TABLE `whatsapp_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `whatsapp_templates`
--

INSERT INTO `whatsapp_templates` (`id`, `template_name`, `template_content`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Test', 'Hello {name}!\n\nHope you\'re doing well. This is [Your Name] from [Company Name].\n\nJust wanted to reach out and check in with you. Please let me know if there\'s anything I can help you with.\n\nBest regards! 1', '2026-01-18 22:47:12', '2026-01-18 22:47:12', NULL),
(2, 'fallowup', 'Hi sir,\n\nFollowing up on our previous conversation. I wanted to check if you had any questions or need any additional information.\n\nLooking forward to hearing from you!\n\nThanks', '2026-01-31 10:17:53', '2026-01-31 10:17:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `work_orders`
--

CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL,
  `wo_no` varchar(50) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `qty` decimal(10,3) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `status` enum('open','created','released','in_progress','completed','closed','cancelled') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_orders`
--

INSERT INTO `work_orders` (`id`, `wo_no`, `part_no`, `bom_id`, `qty`, `assigned_to`, `plan_id`, `status`, `created_at`) VALUES
(1, 'W001', '44005002', 2, 2.000, NULL, NULL, 'released', '2026-01-04 19:15:40'),
(2, 'wo004', '44005643', 4, 2.000, NULL, NULL, 'released', '2026-01-05 20:14:16'),
(3, 'w1', '44005002', 3, 2.000, NULL, NULL, 'released', '2026-01-07 11:20:16'),
(4, 'w005', '42005001', 8, 1.000, NULL, NULL, 'released', '2026-01-08 15:36:14'),
(5, 'WO-1', '42005001', 8, 1.000, NULL, NULL, 'released', '2026-01-09 08:43:05'),
(6, 'WO-2', '46005005', 9, 1.000, NULL, NULL, 'released', '2026-01-09 08:48:25'),
(7, 'WO-3', '46005005', 9, 1.000, NULL, NULL, 'released', '2026-01-09 08:54:09'),
(8, 'WO-4', '46005005', 9, 1.000, NULL, NULL, 'released', '2026-01-09 09:22:28'),
(9, 'WO-5', '46005005', 9, 1.000, NULL, NULL, 'released', '2026-01-09 11:36:21'),
(10, 'WO-6', '44005002', 6, 1.000, NULL, NULL, 'released', '2026-01-10 13:10:14'),
(11, 'WO-7', '44005002', 6, 1.000, NULL, NULL, 'released', '2026-01-10 13:13:49'),
(12, 'WO-8', '46005005', 6, 1.000, NULL, NULL, 'released', '2026-01-10 17:01:14'),
(13, 'WO-9', '44005002', 6, 2.000, NULL, NULL, 'released', '2026-01-10 17:02:51'),
(14, 'WO-10', '44005002', 6, 1.000, NULL, NULL, 'created', '2026-01-10 17:03:54'),
(15, 'WO-11', '22005001', 6, 1.000, NULL, NULL, 'created', '2026-01-14 22:55:08'),
(16, 'WO-12', '22005001', 6, 1.000, NULL, NULL, 'created', '2026-01-15 16:08:59'),
(17, 'WO-13', '42005001', 6, 1.000, NULL, NULL, 'created', '2026-01-18 15:17:03'),
(18, 'WO-14', '46005555', 11, 1.000, NULL, NULL, 'created', '2026-01-18 15:54:42'),
(19, 'WO-15', '44005002', 6, 2.000, NULL, NULL, 'created', '2026-01-18 20:47:58'),
(20, 'WO-16', '18005001', 16, 1.000, NULL, NULL, 'created', '2026-01-27 13:07:04'),
(21, 'WO-17', 'YID-003', 21, 1.000, 73, NULL, 'created', '2026-01-29 08:35:07'),
(22, 'WO-18', '99005002', 21, 1.000, 73, NULL, 'created', '2026-01-29 08:35:24'),
(23, 'WO-19', '46005089', 21, 1.000, 73, NULL, 'created', '2026-01-29 08:35:52'),
(24, 'WO-20', 'YID-002', 0, 1.000, NULL, 26, 'created', '2026-01-29 11:28:40'),
(25, 'WO-21', 'YID-167', 16, 1.000, NULL, 27, 'created', '2026-01-29 11:34:44'),
(26, 'WO-22', '99005038', 17, 1.000, NULL, 27, 'created', '2026-01-29 11:34:44'),
(27, 'WO-23', '52005085', 18, 1.000, 73, 27, 'created', '2026-01-29 11:34:44'),
(28, 'WO-24', '46005077', 0, 1.000, 73, 27, 'created', '2026-01-29 11:34:44'),
(29, 'WO-25', '46005089', 0, 1.000, NULL, 27, 'created', '2026-01-29 11:34:44'),
(30, 'WO-26', '44005001', 23, 2.000, 64, NULL, 'released', '2026-01-29 12:36:26'),
(31, 'WO-27', '83005001', 23, 1.000, 66, NULL, 'released', '2026-01-29 13:59:24'),
(32, 'WO-28', '83005101', 25, 1.000, NULL, NULL, 'in_progress', '2026-01-29 14:06:38'),
(33, 'WO-29', '83005101', 25, 1.000, 66, NULL, 'closed', '2026-01-29 14:24:36'),
(34, 'WO-30', 'YID-003', 21, 1.000, NULL, 28, 'open', '2026-01-29 14:59:16'),
(35, 'WO-31', '99005002', 22, 1.000, NULL, 28, 'open', '2026-01-29 14:59:16'),
(36, 'WO-32', '52005024', 23, 1.000, NULL, 28, 'open', '2026-01-29 14:59:16'),
(37, 'WO-33', '46005077', 0, 2.000, NULL, 28, 'closed', '2026-01-29 14:59:16'),
(38, 'WO-34', '46005089', 0, 2.000, NULL, 28, 'closed', '2026-01-29 14:59:16'),
(39, 'WO-35', 'YID-003', 21, 1.000, NULL, 30, 'cancelled', '2026-01-29 15:01:41'),
(40, 'WO-36', '99005002', 22, 1.000, NULL, 30, 'open', '2026-01-29 15:01:41'),
(41, 'WO-37', '52005024', 23, 1.000, NULL, 30, 'completed', '2026-01-29 15:01:41'),
(42, 'WO-38', '46005077', 0, 1.000, 78, 30, 'closed', '2026-01-29 15:01:41'),
(43, 'WO-39', '46005089', 0, 1.000, NULL, 30, 'completed', '2026-01-29 15:01:41'),
(44, 'WO-40', '83005101', 25, 1.000, NULL, NULL, 'completed', '2026-01-29 17:16:20'),
(45, 'WO-41', '83005003', 29, 20.000, 66, NULL, 'closed', '2026-01-30 08:51:36'),
(46, 'WO-42', '83005003', 23, 2.000, 76, NULL, 'cancelled', '2026-01-30 08:57:42'),
(47, 'WO-43', '83005053', 29, 2.000, 67, NULL, 'closed', '2026-01-30 08:58:17'),
(48, 'WO-44', '83005053', 29, 1.000, 66, NULL, 'closed', '2026-01-30 10:10:38'),
(49, 'WO-45', '83005053', 29, 2.000, 66, NULL, 'closed', '2026-01-30 10:21:22'),
(50, 'WO-46', '44005733', 31, 1.000, 78, NULL, 'closed', '2026-01-30 10:30:02'),
(51, 'WO-47', '44005733', 31, 2.000, 78, NULL, 'closed', '2026-01-31 11:01:53'),
(52, 'WO-48', '44005733', 31, 1.000, 78, NULL, 'closed', '2026-01-31 11:04:16'),
(53, 'WO-49', '44005733', 31, 2.000, 78, NULL, 'closed', '2026-01-31 11:08:57'),
(54, 'WO-50', '44005733', 31, 1.000, 78, NULL, 'closed', '2026-01-31 17:24:05'),
(55, 'WO-51', '44005733', 31, 1.000, 78, NULL, 'open', '2026-01-31 17:29:44'),
(56, 'WO-52', '44005733', 31, 1.000, 78, NULL, 'completed', '2026-01-31 17:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `work_order_issues`
--

CREATE TABLE `work_order_issues` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `depletion_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_order_issues`
--

INSERT INTO `work_order_issues` (`id`, `work_order_id`, `depletion_id`) VALUES
(1, 1, 1),
(2, 2, 2),
(3, 4, 3),
(4, 4, 4),
(5, 4, 5),
(6, 4, 6),
(7, 4, 7),
(8, 4, 8),
(9, 5, 9),
(10, 5, 10),
(11, 5, 11),
(12, 5, 12),
(13, 5, 13),
(14, 5, 14),
(15, 6, 15),
(16, 7, 16),
(17, 8, 17),
(18, 9, 18),
(19, 10, 19),
(20, 10, 20),
(21, 11, 21),
(22, 11, 22),
(23, 12, 25),
(24, 12, 26),
(25, 13, 27),
(26, 13, 28),
(27, 14, 29),
(28, 14, 30);

-- --------------------------------------------------------

--
-- Table structure for table `wo_approvers`
--

CREATE TABLE `wo_approvers` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `can_approve_wo_closing` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wo_approvers`
--

INSERT INTO `wo_approvers` (`id`, `employee_id`, `can_approve_wo_closing`, `is_active`, `created_at`) VALUES
(1, 74, 1, 1, '2026-01-31 12:47:53'),
(2, 65, 1, 1, '2026-01-31 12:48:03');

-- --------------------------------------------------------

--
-- Table structure for table `wo_closing_approvals`
--

CREATE TABLE `wo_closing_approvals` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `checklist_id` int(11) DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approver_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_quality_checklists`
--

CREATE TABLE `wo_quality_checklists` (
  `id` int(11) NOT NULL,
  `work_order_id` int(11) NOT NULL,
  `checklist_no` varchar(30) NOT NULL,
  `inspector_name` varchar(100) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  `overall_result` enum('Pass','Fail','Pending') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `status` enum('Draft','Submitted','Approved','Rejected') DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wo_quality_checklists`
--

INSERT INTO `wo_quality_checklists` (`id`, `work_order_id`, `checklist_no`, `inspector_name`, `inspection_date`, `overall_result`, `remarks`, `status`, `created_by`, `created_at`, `submitted_at`) VALUES
(1, 56, 'QC-000001', NULL, NULL, 'Pending', NULL, 'Draft', NULL, '2026-01-31 12:45:47', NULL),
(2, 56, 'QC-000002', 'PRANJALI ', '2026-01-31', 'Pass', 'ok', 'Draft', NULL, '2026-01-31 12:45:57', '2026-01-31 19:08:51');

-- --------------------------------------------------------

--
-- Table structure for table `wo_quality_checklist_items`
--

CREATE TABLE `wo_quality_checklist_items` (
  `id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `item_no` int(11) NOT NULL,
  `checkpoint` varchar(255) NOT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `result` enum('OK','Not OK','NA','Pending') DEFAULT 'Pending',
  `actual_value` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wo_quality_checklist_items`
--

INSERT INTO `wo_quality_checklist_items` (`id`, `checklist_id`, `item_no`, `checkpoint`, `specification`, `result`, `actual_value`, `remarks`) VALUES
(1, 1, 1, 'Visual Inspection - Surface Finish', 'No scratches, dents, or damage', 'OK', '', 'PASS'),
(2, 1, 2, 'Visual Inspection - Paint/Coating', 'Uniform coverage, no peeling', 'OK', '', 'PASS'),
(3, 1, 3, 'Visual Inspection - Labeling', 'All labels present and legible', 'OK', '', 'PASS'),
(4, 1, 4, 'Dimensional Check - Overall Size', 'As per drawing specifications', 'OK', '', 'PASS'),
(5, 1, 5, 'Dimensional Check - Critical Dimensions', 'Within tolerance limits', 'OK', '', 'PASS'),
(6, 1, 6, 'Dimensional Check - Hole Positions', 'As per drawing', 'OK', '', 'PASS'),
(7, 1, 7, 'Functional Test - Operation Check', 'Operates as designed', 'Pending', '', ''),
(8, 1, 8, 'Functional Test - Performance', 'Meets performance criteria', 'Pending', '', ''),
(9, 1, 9, 'Functional Test - Noise Level', 'Within acceptable limits', 'Pending', '', ''),
(10, 1, 10, 'Assembly Check - All Parts Present', 'Complete assembly', 'Pending', '', ''),
(11, 1, 11, 'Assembly Check - Fasteners Tightened', 'All bolts/screws secured', 'Pending', '', ''),
(12, 1, 12, 'Assembly Check - Alignment', 'Components properly aligned', 'Pending', '', ''),
(13, 1, 13, 'Safety Check - Sharp Edges', 'No dangerous sharp edges', 'Pending', '', ''),
(14, 1, 14, 'Safety Check - Electrical Safety', 'Proper insulation/grounding', 'Pending', '', ''),
(15, 1, 15, 'Safety Check - Warning Labels', 'Safety labels in place', 'Pending', '', ''),
(16, 1, 16, 'Documentation - Test Records', 'All test records complete', 'Pending', '', ''),
(17, 1, 17, 'Documentation - Traceability', 'Serial/batch numbers recorded', 'Pending', '', ''),
(18, 1, 18, 'Documentation - Certificates', 'Required certificates available', 'Pending', '', ''),
(19, 2, 1, 'Visual Inspection - Surface Finish', 'No scratches, dents, or damage', 'OK', '', ''),
(20, 2, 2, 'Visual Inspection - Paint/Coating', 'Uniform coverage, no peeling', 'OK', '', ''),
(21, 2, 3, 'Visual Inspection - Labeling', 'All labels present and legible', 'OK', '', ''),
(22, 2, 4, 'Dimensional Check - Overall Size', 'As per drawing specifications', 'OK', '', ''),
(23, 2, 5, 'Dimensional Check - Critical Dimensions', 'Within tolerance limits', 'OK', '', ''),
(24, 2, 6, 'Dimensional Check - Hole Positions', 'As per drawing', 'NA', '', ''),
(25, 2, 7, 'Functional Test - Operation Check', 'Operates as designed', 'NA', '', ''),
(26, 2, 8, 'Functional Test - Performance', 'Meets performance criteria', 'OK', '', ''),
(27, 2, 9, 'Functional Test - Noise Level', 'Within acceptable limits', 'OK', '', ''),
(28, 2, 10, 'Assembly Check - All Parts Present', 'Complete assembly', 'OK', '', ''),
(29, 2, 11, 'Assembly Check - Fasteners Tightened', 'All bolts/screws secured', 'NA', '', ''),
(30, 2, 12, 'Assembly Check - Alignment', 'Components properly aligned', 'NA', '', ''),
(31, 2, 13, 'Safety Check - Sharp Edges', 'No dangerous sharp edges', 'NA', '', ''),
(32, 2, 14, 'Safety Check - Electrical Safety', 'Proper insulation/grounding', 'NA', '', ''),
(33, 2, 15, 'Safety Check - Warning Labels', 'Safety labels in place', 'OK', '', ''),
(34, 2, 16, 'Documentation - Test Records', 'All test records complete', 'NA', '', ''),
(35, 2, 17, 'Documentation - Traceability', 'Serial/batch numbers recorded', 'NA', '', ''),
(36, 2, 18, 'Documentation - Certificates', 'Required certificates available', 'NA', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `wo_quality_checkpoint_templates`
--

CREATE TABLE `wo_quality_checkpoint_templates` (
  `id` int(11) NOT NULL,
  `item_no` int(11) NOT NULL,
  `checkpoint` varchar(255) NOT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wo_quality_checkpoint_templates`
--

INSERT INTO `wo_quality_checkpoint_templates` (`id`, `item_no`, `checkpoint`, `specification`, `category`, `is_mandatory`, `is_active`, `created_at`) VALUES
(1, 1, 'Visual Inspection - Surface Finish', 'No scratches, dents, or damage', 'Visual Inspection', 1, 1, '2026-01-31 12:42:17'),
(2, 2, 'Visual Inspection - Paint/Coating', 'Uniform coverage, no peeling', 'Visual Inspection', 1, 1, '2026-01-31 12:42:17'),
(3, 3, 'Visual Inspection - Labeling', 'All labels present and legible', 'Visual Inspection', 1, 1, '2026-01-31 12:42:17'),
(4, 4, 'Dimensional Check - Overall Size', 'As per drawing specifications', 'Dimensional', 1, 1, '2026-01-31 12:42:17'),
(5, 5, 'Dimensional Check - Critical Dimensions', 'Within tolerance limits', 'Dimensional', 1, 1, '2026-01-31 12:42:17'),
(6, 6, 'Dimensional Check - Hole Positions', 'As per drawing', 'Dimensional', 0, 1, '2026-01-31 12:42:17'),
(7, 7, 'Functional Test - Operation Check', 'Operates as designed', 'Functional', 1, 1, '2026-01-31 12:42:17'),
(8, 8, 'Functional Test - Performance', 'Meets performance criteria', 'Functional', 1, 1, '2026-01-31 12:42:17'),
(9, 9, 'Functional Test - Noise Level', 'Within acceptable limits', 'Functional', 0, 1, '2026-01-31 12:42:17'),
(10, 10, 'Assembly Check - All Parts Present', 'Complete assembly', 'Assembly', 1, 1, '2026-01-31 12:42:17'),
(11, 11, 'Assembly Check - Fasteners Tightened', 'All bolts/screws secured', 'Assembly', 1, 1, '2026-01-31 12:42:17'),
(12, 12, 'Assembly Check - Alignment', 'Components properly aligned', 'Assembly', 1, 1, '2026-01-31 12:42:17'),
(13, 13, 'Safety Check - Sharp Edges', 'No dangerous sharp edges', 'Safety', 1, 1, '2026-01-31 12:42:17'),
(14, 14, 'Safety Check - Electrical Safety', 'Proper insulation/grounding', 'Safety', 1, 1, '2026-01-31 12:42:17'),
(15, 15, 'Safety Check - Warning Labels', 'Safety labels in place', 'Safety', 0, 1, '2026-01-31 12:42:17'),
(16, 16, 'Documentation - Test Records', 'All test records complete', 'Documentation', 1, 1, '2026-01-31 12:42:17'),
(17, 17, 'Documentation - Traceability', 'Serial/batch numbers recorded', 'Documentation', 0, 1, '2026-01-31 12:42:17'),
(18, 18, 'Documentation - Certificates', 'Required certificates available', 'Documentation', 0, 1, '2026-01-31 12:42:17');

-- --------------------------------------------------------

--
-- Structure for view `open_sales_orders_for_planning`
--
DROP TABLE IF EXISTS `open_sales_orders_for_planning`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `open_sales_orders_for_planning`  AS SELECT `so`.`so_no` AS `so_no`, `so`.`part_no` AS `part_no`, sum(`so`.`qty`) AS `total_demand_qty`, count(distinct `so`.`so_no`) AS `num_orders`, min(`so`.`sales_date`) AS `earliest_so_date`, max(`so`.`sales_date`) AS `latest_so_date`, coalesce(`i`.`qty`,0) AS `current_stock`, `p`.`part_name` AS `part_name`, group_concat(distinct `so`.`so_no` separator ', ') AS `so_list` FROM ((`sales_orders` `so` join `part_master` `p` on(`so`.`part_no` = `p`.`part_no`)) left join `inventory` `i` on(`so`.`part_no` = `i`.`part_no`)) WHERE `so`.`status` in ('pending','open') GROUP BY `so`.`part_no`, `p`.`part_name`, `i`.`qty` ORDER BY `so`.`part_no` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `procurement_plan_summary`
--
DROP TABLE IF EXISTS `procurement_plan_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `procurement_plan_summary`  AS SELECT `pp`.`id` AS `id`, `pp`.`plan_no` AS `plan_no`, `pp`.`plan_date` AS `plan_date`, `pp`.`status` AS `status`, `pp`.`total_parts` AS `total_parts`, `pp`.`total_items_to_order` AS `total_items_to_order`, `pp`.`total_estimated_cost` AS `total_estimated_cost`, count(`ppi`.`id`) AS `item_count`, sum(case when `ppi`.`status` = 'pending' then 1 else 0 end) AS `pending_count`, sum(case when `ppi`.`status` = 'ordered' then 1 else 0 end) AS `ordered_count`, sum(case when `ppi`.`status` = 'received' then 1 else 0 end) AS `received_count` FROM (`procurement_plans` `pp` left join `procurement_plan_items` `ppi` on(`pp`.`id` = `ppi`.`plan_id`)) GROUP BY `pp`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `acc_account_groups`
--
ALTER TABLE `acc_account_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `acc_bank_accounts`
--
ALTER TABLE `acc_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ledger_id` (`ledger_id`);

--
-- Indexes for table `acc_bank_reconciliation`
--
ALTER TABLE `acc_bank_reconciliation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `voucher_entry_id` (`voucher_entry_id`);

--
-- Indexes for table `acc_budgets`
--
ALTER TABLE `acc_budgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `financial_year_id` (`financial_year_id`),
  ADD KEY `ledger_id` (`ledger_id`),
  ADD KEY `cost_center_id` (`cost_center_id`);

--
-- Indexes for table `acc_cost_centers`
--
ALTER TABLE `acc_cost_centers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_expenses`
--
ALTER TABLE `acc_expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expense_no` (`expense_no`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `ledger_id` (`ledger_id`),
  ADD KEY `paid_from_ledger_id` (`paid_from_ledger_id`),
  ADD KEY `voucher_id` (`voucher_id`);

--
-- Indexes for table `acc_expense_categories`
--
ALTER TABLE `acc_expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ledger_id` (`ledger_id`);

--
-- Indexes for table `acc_financial_years`
--
ALTER TABLE `acc_financial_years`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_gst_rates`
--
ALTER TABLE `acc_gst_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_gst_returns`
--
ALTER TABLE `acc_gst_returns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_gst_transactions`
--
ALTER TABLE `acc_gst_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `gst_rate_id` (`gst_rate_id`);

--
-- Indexes for table `acc_ledgers`
--
ALTER TABLE `acc_ledgers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ledger_code` (`ledger_code`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `acc_tds_sections`
--
ALTER TABLE `acc_tds_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_code` (`section_code`);

--
-- Indexes for table `acc_tds_transactions`
--
ALTER TABLE `acc_tds_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `deductee_ledger_id` (`deductee_ledger_id`),
  ADD KEY `tds_section_id` (`tds_section_id`);

--
-- Indexes for table `acc_vouchers`
--
ALTER TABLE `acc_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `party_ledger_id` (`party_ledger_id`),
  ADD KEY `idx_voucher_date` (`voucher_date`),
  ADD KEY `idx_voucher_type` (`voucher_type_id`);

--
-- Indexes for table `acc_voucher_entries`
--
ALTER TABLE `acc_voucher_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `ledger_id` (`ledger_id`);

--
-- Indexes for table `acc_voucher_types`
--
ALTER TABLE `acc_voucher_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_code` (`type_code`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `appraisals`
--
ALTER TABLE `appraisals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appraisal_no` (`appraisal_no`),
  ADD KEY `idx_cycle` (`cycle_id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_reviewer` (`reviewer_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `appraisal_criteria`
--
ALTER TABLE `appraisal_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `appraisal_cycles`
--
ALTER TABLE `appraisal_cycles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `appraisal_goals`
--
ALTER TABLE `appraisal_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appraisal` (`appraisal_id`);

--
-- Indexes for table `appraisal_ratings`
--
ALTER TABLE `appraisal_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_appraisal_criteria` (`appraisal_id`,`criteria_id`),
  ADD KEY `idx_appraisal` (`appraisal_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_emp_date` (`employee_id`,`attendance_date`),
  ADD KEY `idx_emp_date` (`employee_id`,`attendance_date`),
  ADD KEY `idx_date` (`attendance_date`);

--
-- Indexes for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `bom_items`
--
ALTER TABLE `bom_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bom_master`
--
ALTER TABLE `bom_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bom_no` (`bom_no`);

--
-- Indexes for table `campaign_expenses`
--
ALTER TABLE `campaign_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`);

--
-- Indexes for table `campaign_leads`
--
ALTER TABLE `campaign_leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign` (`campaign_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `campaign_types`
--
ALTER TABLE `campaign_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `catalog_categories`
--
ALTER TABLE `catalog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `change_requests`
--
ALTER TABLE `change_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `eco_no` (`eco_no`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_city_state` (`city_name`,`state_id`),
  ADD KEY `idx_state` (`state_id`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`);

--
-- Indexes for table `crm_leads`
--
ALTER TABLE `crm_leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lead_no` (`lead_no`),
  ADD KEY `idx_lead_status` (`lead_status`),
  ADD KEY `idx_lead_type` (`customer_type`),
  ADD KEY `idx_lead_followup` (`next_followup_date`),
  ADD KEY `idx_lead_timeline` (`buying_timeline`),
  ADD KEY `idx_lead_converted` (`converted_customer_id`),
  ADD KEY `idx_assigned_user` (`assigned_user_id`);

--
-- Indexes for table `crm_lead_interactions`
--
ALTER TABLE `crm_lead_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_int_lead` (`lead_id`),
  ADD KEY `idx_int_date` (`interaction_date`);

--
-- Indexes for table `crm_lead_requirements`
--
ALTER TABLE `crm_lead_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_req_lead` (`lead_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `customer_po`
--
ALTER TABLE `customer_po`
  ADD PRIMARY KEY (`id`),
  ADD KEY `linked_quote_id` (`linked_quote_id`),
  ADD KEY `idx_customer_po_no` (`po_no`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `head_id` (`head_id`);

--
-- Indexes for table `depletion`
--
ALTER TABLE `depletion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `part_no` (`part_no`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name` (`type_name`),
  ADD UNIQUE KEY `type_code` (`type_code`);

--
-- Indexes for table `eco_affected_parts`
--
ALTER TABLE `eco_affected_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eco_id` (`eco_id`);

--
-- Indexes for table `eco_approvals`
--
ALTER TABLE `eco_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eco_id` (`eco_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_id` (`emp_id`),
  ADD KEY `reporting_to` (`reporting_to`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_doc_type` (`document_type`);

--
-- Indexes for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_emp_skill` (`employee_id`,`skill_id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_skill` (`skill_id`);

--
-- Indexes for table `engineering_reviews`
--
ALTER TABLE `engineering_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `review_no` (`review_no`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `holiday_date` (`holiday_date`);

--
-- Indexes for table `india_cities`
--
ALTER TABLE `india_cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_state` (`state_id`);

--
-- Indexes for table `india_states`
--
ALTER TABLE `india_states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `state_code` (`state_code`);

--
-- Indexes for table `installations`
--
ALTER TABLE `installations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `installation_no` (`installation_no`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_engineer` (`engineer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`installation_date`);

--
-- Indexes for table `installation_attachments`
--
ALTER TABLE `installation_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_installation` (`installation_id`);

--
-- Indexes for table `installation_products`
--
ALTER TABLE `installation_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_installation` (`installation_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_inventory_part` (`part_no`);

--
-- Indexes for table `invoice_master`
--
ALTER TABLE `invoice_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `idx_invoice_so` (`so_no`),
  ADD KEY `idx_invoice_customer` (`customer_id`),
  ADD KEY `idx_invoice_date` (`invoice_date`);

--
-- Indexes for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_emp_leave_year` (`employee_id`,`leave_type_id`,`year`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_emp_leave_year` (`employee_id`,`leave_type_id`,`year`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `location_marketing_summary`
--
ALTER TABLE `location_marketing_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_state_period` (`state_id`,`year`,`month`);

--
-- Indexes for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `campaign_code` (`campaign_code`),
  ADD KEY `campaign_type_id` (`campaign_type_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_state` (`state_id`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `marketing_catalogs`
--
ALTER TABLE `marketing_catalogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `catalog_code` (`catalog_code`);

--
-- Indexes for table `milestone_documents`
--
ALTER TABLE `milestone_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_milestone` (`milestone_id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_key` (`module_key`);

--
-- Indexes for table `part_id_series`
--
ALTER TABLE `part_id_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_id` (`part_id`),
  ADD KEY `idx_part_id` (`part_id`),
  ADD KEY `idx_series_prefix` (`series_prefix`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `part_master`
--
ALTER TABLE `part_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_no` (`part_no`);

--
-- Indexes for table `part_min_stock`
--
ALTER TABLE `part_min_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_no` (`part_no`),
  ADD KEY `idx_part_no` (`part_no`);

--
-- Indexes for table `part_supplier_mapping`
--
ALTER TABLE `part_supplier_mapping`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_part_supplier` (`part_no`,`supplier_id`),
  ADD KEY `idx_part_no` (`part_no`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_active_supplier` (`active`,`supplier_id`);

--
-- Indexes for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_emp_month` (`employee_id`,`payroll_month`),
  ADD KEY `idx_emp_month` (`employee_id`,`payroll_month`),
  ADD KEY `idx_month` (`payroll_month`);

--
-- Indexes for table `po_inspection_approvals`
--
ALTER TABLE `po_inspection_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_no` (`po_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `checklist_id` (`checklist_id`),
  ADD KEY `approver_id` (`approver_id`);

--
-- Indexes for table `po_inspection_approvers`
--
ALTER TABLE `po_inspection_approvers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee` (`employee_id`);

--
-- Indexes for table `po_inspection_checklists`
--
ALTER TABLE `po_inspection_checklists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checklist_no` (`checklist_no`),
  ADD KEY `idx_po_no` (`po_no`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `po_inspection_checklist_items`
--
ALTER TABLE `po_inspection_checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checklist_id` (`checklist_id`);

--
-- Indexes for table `po_inspection_checkpoint_templates`
--
ALTER TABLE `po_inspection_checkpoint_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `procurement_plans`
--
ALTER TABLE `procurement_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plan_no` (`plan_no`),
  ADD KEY `idx_plan_no` (`plan_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_plan_date` (`plan_date`);

--
-- Indexes for table `procurement_plan_items`
--
ALTER TABLE `procurement_plan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plan_id` (`plan_id`),
  ADD KEY `idx_part_no` (`part_no`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_plan_status` (`plan_id`,`status`);

--
-- Indexes for table `procurement_plan_po_items`
--
ALTER TABLE `procurement_plan_po_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plan_part` (`plan_id`,`part_no`),
  ADD KEY `idx_plan_id` (`plan_id`),
  ADD KEY `idx_part_no` (`part_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_po_id` (`created_po_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`);

--
-- Indexes for table `procurement_plan_wo_items`
--
ALTER TABLE `procurement_plan_wo_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plan_part` (`plan_id`,`part_no`),
  ADD KEY `idx_plan_id` (`plan_id`),
  ADD KEY `idx_part_no` (`part_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_wo_id` (`created_wo_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_no` (`project_no`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `project_activities`
--
ALTER TABLE `project_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_documents`
--
ALTER TABLE `project_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_milestones`
--
ALTER TABLE `project_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_tasks`
--
ALTER TABLE `project_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `part_no` (`part_no`),
  ADD KEY `fk_supplier` (`supplier_id`);

--
-- Indexes for table `qc_issue_actions`
--
ALTER TABLE `qc_issue_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `issue_id` (`issue_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_target_date` (`target_date`),
  ADD KEY `idx_assigned_to` (`assigned_to_id`);

--
-- Indexes for table `qc_issue_attachments`
--
ALTER TABLE `qc_issue_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `issue_id` (`issue_id`);

--
-- Indexes for table `qc_issue_categories`
--
ALTER TABLE `qc_issue_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `qc_issue_comments`
--
ALTER TABLE `qc_issue_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `issue_id` (`issue_id`);

--
-- Indexes for table `qc_quality_issues`
--
ALTER TABLE `qc_quality_issues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `issue_no` (`issue_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_issue_type` (`issue_type`),
  ADD KEY `idx_issue_date` (`issue_date`),
  ADD KEY `idx_assigned_to` (`assigned_to_id`);

--
-- Indexes for table `qms_capa`
--
ALTER TABLE `qms_capa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_type` (`capa_type`);

--
-- Indexes for table `qms_cdsco_adverse_events`
--
ALTER TABLE `qms_cdsco_adverse_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_event_type` (`event_type`);

--
-- Indexes for table `qms_cdsco_licenses`
--
ALTER TABLE `qms_cdsco_licenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `qms_cdsco_products`
--
ALTER TABLE `qms_cdsco_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `qms_documents`
--
ALTER TABLE `qms_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_doc_type` (`doc_type`);

--
-- Indexes for table `qms_icmed_audits`
--
ALTER TABLE `qms_icmed_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `certification_id` (`certification_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`scheduled_date`);

--
-- Indexes for table `qms_icmed_certifications`
--
ALTER TABLE `qms_icmed_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `qms_iso_audits`
--
ALTER TABLE `qms_iso_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `certification_id` (`certification_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`planned_date`);

--
-- Indexes for table `qms_iso_certifications`
--
ALTER TABLE `qms_iso_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `qms_management_review`
--
ALTER TABLE `qms_management_review`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`review_date`);

--
-- Indexes for table `qms_ncr`
--
ALTER TABLE `qms_ncr`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_id` (`audit_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_nc_type` (`nc_type`);

--
-- Indexes for table `qms_training`
--
ALTER TABLE `qms_training`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_document_id` (`related_document_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`training_date`);

--
-- Indexes for table `quote_items`
--
ALTER TABLE `quote_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quote_items_quote` (`quote_id`);

--
-- Indexes for table `quote_master`
--
ALTER TABLE `quote_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quote_no` (`quote_no`),
  ADD KEY `idx_quote_customer` (`customer_id`),
  ADD KEY `idx_quote_date` (`quote_date`),
  ADD KEY `idx_quote_pi_no` (`pi_no`);

--
-- Indexes for table `review_findings`
--
ALTER TABLE `review_findings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `review_id` (`review_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_module` (`role`,`module`);

--
-- Indexes for table `salary_advances`
--
ALTER TABLE `salary_advances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `part_no` (`part_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_sales_orders_quote` (`linked_quote_id`);

--
-- Indexes for table `service_complaints`
--
ALTER TABLE `service_complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_no` (`complaint_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_registered_date` (`registered_date`),
  ADD KEY `idx_assigned_tech` (`assigned_technician_id`),
  ADD KEY `idx_issue_category` (`issue_category_id`),
  ADD KEY `idx_state` (`state_id`);

--
-- Indexes for table `service_issue_categories`
--
ALTER TABLE `service_issue_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `service_parts`
--
ALTER TABLE `service_parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_code` (`part_code`);

--
-- Indexes for table `service_technicians`
--
ALTER TABLE `service_technicians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tech_code` (`tech_code`);

--
-- Indexes for table `service_visits`
--
ALTER TABLE `service_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `technician_id` (`technician_id`),
  ADD KEY `idx_complaint` (`complaint_id`),
  ADD KEY `idx_visit_date` (`visit_date`);

--
-- Indexes for table `skills_master`
--
ALTER TABLE `skills_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_skill` (`skill_name`,`category_id`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `skill_categories`
--
ALTER TABLE `skill_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `so_checklist_items`
--
ALTER TABLE `so_checklist_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `so_release_attachments`
--
ALTER TABLE `so_release_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_so_no` (`so_no`);

--
-- Indexes for table `so_release_checklist`
--
ALTER TABLE `so_release_checklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_so_no` (`so_no`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `state_name` (`state_name`);

--
-- Indexes for table `stock_entries`
--
ALTER TABLE `stock_entries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_no` (`task_no`),
  ADD KEY `idx_task_status` (`status`),
  ADD KEY `idx_task_priority` (`priority`),
  ADD KEY `idx_task_assigned` (`assigned_to`),
  ADD KEY `idx_task_due_date` (`due_date`),
  ADD KEY `idx_task_category` (`category_id`),
  ADD KEY `idx_task_calendar` (`start_date`,`start_time`,`assigned_to`);

--
-- Indexes for table `task_attachments`
--
ALTER TABLE `task_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_attachments` (`task_id`);

--
-- Indexes for table `task_categories`
--
ALTER TABLE `task_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_code` (`category_code`);

--
-- Indexes for table `task_checklist`
--
ALTER TABLE `task_checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_checklist` (`task_id`);

--
-- Indexes for table `task_comments`
--
ALTER TABLE `task_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_comments` (`task_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_module` (`user_id`,`module`);

--
-- Indexes for table `whatsapp_log`
--
ALTER TABLE `whatsapp_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_template_name` (`template_name`);

--
-- Indexes for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wo_no` (`wo_no`);

--
-- Indexes for table `work_order_issues`
--
ALTER TABLE `work_order_issues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_approvers`
--
ALTER TABLE `wo_approvers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee` (`employee_id`);

--
-- Indexes for table `wo_closing_approvals`
--
ALTER TABLE `wo_closing_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`),
  ADD KEY `checklist_id` (`checklist_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approver_id` (`approver_id`);

--
-- Indexes for table `wo_quality_checklists`
--
ALTER TABLE `wo_quality_checklists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checklist_no` (`checklist_no`),
  ADD KEY `work_order_id` (`work_order_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `wo_quality_checklist_items`
--
ALTER TABLE `wo_quality_checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checklist_id` (`checklist_id`);

--
-- Indexes for table `wo_quality_checkpoint_templates`
--
ALTER TABLE `wo_quality_checkpoint_templates`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `acc_account_groups`
--
ALTER TABLE `acc_account_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `acc_bank_accounts`
--
ALTER TABLE `acc_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_bank_reconciliation`
--
ALTER TABLE `acc_bank_reconciliation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_budgets`
--
ALTER TABLE `acc_budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_cost_centers`
--
ALTER TABLE `acc_cost_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_expenses`
--
ALTER TABLE `acc_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_expense_categories`
--
ALTER TABLE `acc_expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_financial_years`
--
ALTER TABLE `acc_financial_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `acc_gst_rates`
--
ALTER TABLE `acc_gst_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `acc_gst_returns`
--
ALTER TABLE `acc_gst_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_gst_transactions`
--
ALTER TABLE `acc_gst_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_ledgers`
--
ALTER TABLE `acc_ledgers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `acc_tds_sections`
--
ALTER TABLE `acc_tds_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `acc_tds_transactions`
--
ALTER TABLE `acc_tds_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_vouchers`
--
ALTER TABLE `acc_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_voucher_entries`
--
ALTER TABLE `acc_voucher_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_voucher_types`
--
ALTER TABLE `acc_voucher_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `appraisals`
--
ALTER TABLE `appraisals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `appraisal_criteria`
--
ALTER TABLE `appraisal_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `appraisal_cycles`
--
ALTER TABLE `appraisal_cycles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `appraisal_goals`
--
ALTER TABLE `appraisal_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appraisal_ratings`
--
ALTER TABLE `appraisal_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `bom_items`
--
ALTER TABLE `bom_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `bom_master`
--
ALTER TABLE `bom_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `campaign_expenses`
--
ALTER TABLE `campaign_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_leads`
--
ALTER TABLE `campaign_leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_types`
--
ALTER TABLE `campaign_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `catalog_categories`
--
ALTER TABLE `catalog_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `change_requests`
--
ALTER TABLE `change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=373;

--
-- AUTO_INCREMENT for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `crm_leads`
--
ALTER TABLE `crm_leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `crm_lead_interactions`
--
ALTER TABLE `crm_lead_interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `crm_lead_requirements`
--
ALTER TABLE `crm_lead_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `customer_documents`
--
ALTER TABLE `customer_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_po`
--
ALTER TABLE `customer_po`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `depletion`
--
ALTER TABLE `depletion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `eco_affected_parts`
--
ALTER TABLE `eco_affected_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eco_approvals`
--
ALTER TABLE `eco_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_skills`
--
ALTER TABLE `employee_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `engineering_reviews`
--
ALTER TABLE `engineering_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `india_cities`
--
ALTER TABLE `india_cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `india_states`
--
ALTER TABLE `india_states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `installations`
--
ALTER TABLE `installations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `installation_attachments`
--
ALTER TABLE `installation_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `installation_products`
--
ALTER TABLE `installation_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `invoice_master`
--
ALTER TABLE `invoice_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `leave_balance`
--
ALTER TABLE `leave_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `location_marketing_summary`
--
ALTER TABLE `location_marketing_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_catalogs`
--
ALTER TABLE `marketing_catalogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `milestone_documents`
--
ALTER TABLE `milestone_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `part_id_series`
--
ALTER TABLE `part_id_series`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `part_master`
--
ALTER TABLE `part_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1652;

--
-- AUTO_INCREMENT for table `part_min_stock`
--
ALTER TABLE `part_min_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `part_supplier_mapping`
--
ALTER TABLE `part_supplier_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `payment_terms`
--
ALTER TABLE `payment_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `po_inspection_approvals`
--
ALTER TABLE `po_inspection_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `po_inspection_approvers`
--
ALTER TABLE `po_inspection_approvers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `po_inspection_checklists`
--
ALTER TABLE `po_inspection_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `po_inspection_checklist_items`
--
ALTER TABLE `po_inspection_checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `po_inspection_checkpoint_templates`
--
ALTER TABLE `po_inspection_checkpoint_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `procurement_plans`
--
ALTER TABLE `procurement_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `procurement_plan_items`
--
ALTER TABLE `procurement_plan_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `procurement_plan_po_items`
--
ALTER TABLE `procurement_plan_po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=698;

--
-- AUTO_INCREMENT for table `procurement_plan_wo_items`
--
ALTER TABLE `procurement_plan_wo_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `project_activities`
--
ALTER TABLE `project_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_documents`
--
ALTER TABLE `project_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_milestones`
--
ALTER TABLE `project_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `project_tasks`
--
ALTER TABLE `project_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `qc_issue_actions`
--
ALTER TABLE `qc_issue_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qc_issue_attachments`
--
ALTER TABLE `qc_issue_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qc_issue_categories`
--
ALTER TABLE `qc_issue_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `qc_issue_comments`
--
ALTER TABLE `qc_issue_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qc_quality_issues`
--
ALTER TABLE `qc_quality_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qms_capa`
--
ALTER TABLE `qms_capa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qms_cdsco_adverse_events`
--
ALTER TABLE `qms_cdsco_adverse_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qms_cdsco_licenses`
--
ALTER TABLE `qms_cdsco_licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qms_cdsco_products`
--
ALTER TABLE `qms_cdsco_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qms_documents`
--
ALTER TABLE `qms_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qms_icmed_audits`
--
ALTER TABLE `qms_icmed_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qms_icmed_certifications`
--
ALTER TABLE `qms_icmed_certifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qms_iso_audits`
--
ALTER TABLE `qms_iso_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qms_iso_certifications`
--
ALTER TABLE `qms_iso_certifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `qms_management_review`
--
ALTER TABLE `qms_management_review`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qms_ncr`
--
ALTER TABLE `qms_ncr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qms_training`
--
ALTER TABLE `qms_training`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quote_items`
--
ALTER TABLE `quote_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `quote_master`
--
ALTER TABLE `quote_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `review_findings`
--
ALTER TABLE `review_findings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `salary_advances`
--
ALTER TABLE `salary_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `service_complaints`
--
ALTER TABLE `service_complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `service_issue_categories`
--
ALTER TABLE `service_issue_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `service_parts`
--
ALTER TABLE `service_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_technicians`
--
ALTER TABLE `service_technicians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `service_visits`
--
ALTER TABLE `service_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skills_master`
--
ALTER TABLE `skills_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skill_categories`
--
ALTER TABLE `skill_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `so_checklist_items`
--
ALTER TABLE `so_checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `so_release_attachments`
--
ALTER TABLE `so_release_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `so_release_checklist`
--
ALTER TABLE `so_release_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `stock_entries`
--
ALTER TABLE `stock_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `task_attachments`
--
ALTER TABLE `task_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_categories`
--
ALTER TABLE `task_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `task_checklist`
--
ALTER TABLE `task_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `task_comments`
--
ALTER TABLE `task_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- AUTO_INCREMENT for table `whatsapp_log`
--
ALTER TABLE `whatsapp_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `work_orders`
--
ALTER TABLE `work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `work_order_issues`
--
ALTER TABLE `work_order_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `wo_approvers`
--
ALTER TABLE `wo_approvers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wo_closing_approvals`
--
ALTER TABLE `wo_closing_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wo_quality_checklists`
--
ALTER TABLE `wo_quality_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wo_quality_checklist_items`
--
ALTER TABLE `wo_quality_checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `wo_quality_checkpoint_templates`
--
ALTER TABLE `wo_quality_checkpoint_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `acc_account_groups`
--
ALTER TABLE `acc_account_groups`
  ADD CONSTRAINT `acc_account_groups_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `acc_account_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `acc_bank_accounts`
--
ALTER TABLE `acc_bank_accounts`
  ADD CONSTRAINT `acc_bank_accounts_ibfk_1` FOREIGN KEY (`ledger_id`) REFERENCES `acc_ledgers` (`id`);

--
-- Constraints for table `acc_bank_reconciliation`
--
ALTER TABLE `acc_bank_reconciliation`
  ADD CONSTRAINT `acc_bank_reconciliation_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `acc_bank_accounts` (`id`),
  ADD CONSTRAINT `acc_bank_reconciliation_ibfk_2` FOREIGN KEY (`voucher_entry_id`) REFERENCES `acc_voucher_entries` (`id`);

--
-- Constraints for table `acc_budgets`
--
ALTER TABLE `acc_budgets`
  ADD CONSTRAINT `acc_budgets_ibfk_1` FOREIGN KEY (`financial_year_id`) REFERENCES `acc_financial_years` (`id`),
  ADD CONSTRAINT `acc_budgets_ibfk_2` FOREIGN KEY (`ledger_id`) REFERENCES `acc_ledgers` (`id`),
  ADD CONSTRAINT `acc_budgets_ibfk_3` FOREIGN KEY (`cost_center_id`) REFERENCES `acc_cost_centers` (`id`);

--
-- Constraints for table `acc_expenses`
--
ALTER TABLE `acc_expenses`
  ADD CONSTRAINT `acc_expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `acc_expense_categories` (`id`),
  ADD CONSTRAINT `acc_expenses_ibfk_2` FOREIGN KEY (`ledger_id`) REFERENCES `acc_ledgers` (`id`),
  ADD CONSTRAINT `acc_expenses_ibfk_3` FOREIGN KEY (`paid_from_ledger_id`) REFERENCES `acc_ledgers` (`id`),
  ADD CONSTRAINT `acc_expenses_ibfk_4` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`id`);

--
-- Constraints for table `acc_expense_categories`
--
ALTER TABLE `acc_expense_categories`
  ADD CONSTRAINT `acc_expense_categories_ibfk_1` FOREIGN KEY (`ledger_id`) REFERENCES `acc_ledgers` (`id`);

--
-- Constraints for table `acc_gst_transactions`
--
ALTER TABLE `acc_gst_transactions`
  ADD CONSTRAINT `acc_gst_transactions_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`id`),
  ADD CONSTRAINT `acc_gst_transactions_ibfk_2` FOREIGN KEY (`gst_rate_id`) REFERENCES `acc_gst_rates` (`id`);

--
-- Constraints for table `acc_ledgers`
--
ALTER TABLE `acc_ledgers`
  ADD CONSTRAINT `acc_ledgers_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `acc_account_groups` (`id`);

--
-- Constraints for table `acc_tds_transactions`
--
ALTER TABLE `acc_tds_transactions`
  ADD CONSTRAINT `acc_tds_transactions_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`id`),
  ADD CONSTRAINT `acc_tds_transactions_ibfk_2` FOREIGN KEY (`deductee_ledger_id`) REFERENCES `acc_ledgers` (`id`),
  ADD CONSTRAINT `acc_tds_transactions_ibfk_3` FOREIGN KEY (`tds_section_id`) REFERENCES `acc_tds_sections` (`id`);

--
-- Constraints for table `acc_vouchers`
--
ALTER TABLE `acc_vouchers`
  ADD CONSTRAINT `acc_vouchers_ibfk_1` FOREIGN KEY (`voucher_type_id`) REFERENCES `acc_voucher_types` (`id`),
  ADD CONSTRAINT `acc_vouchers_ibfk_2` FOREIGN KEY (`party_ledger_id`) REFERENCES `acc_ledgers` (`id`);

--
-- Constraints for table `acc_voucher_entries`
--
ALTER TABLE `acc_voucher_entries`
  ADD CONSTRAINT `acc_voucher_entries_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acc_voucher_entries_ibfk_2` FOREIGN KEY (`ledger_id`) REFERENCES `acc_ledgers` (`id`);

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `campaign_expenses`
--
ALTER TABLE `campaign_expenses`
  ADD CONSTRAINT `campaign_expenses_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `campaign_leads`
--
ALTER TABLE `campaign_leads`
  ADD CONSTRAINT `campaign_leads_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `catalog_categories`
--
ALTER TABLE `catalog_categories`
  ADD CONSTRAINT `catalog_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `catalog_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `change_requests`
--
ALTER TABLE `change_requests`
  ADD CONSTRAINT `change_requests_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `cities_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  ADD CONSTRAINT `complaint_status_history_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `service_complaints` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_lead_interactions`
--
ALTER TABLE `crm_lead_interactions`
  ADD CONSTRAINT `crm_lead_interactions_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_lead_requirements`
--
ALTER TABLE `crm_lead_requirements`
  ADD CONSTRAINT `crm_lead_requirements_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD CONSTRAINT `customer_documents_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_po`
--
ALTER TABLE `customer_po`
  ADD CONSTRAINT `customer_po_ibfk_1` FOREIGN KEY (`linked_quote_id`) REFERENCES `quote_master` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`head_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `depletion`
--
ALTER TABLE `depletion`
  ADD CONSTRAINT `depletion_ibfk_1` FOREIGN KEY (`part_no`) REFERENCES `part_master` (`part_no`);

--
-- Constraints for table `eco_affected_parts`
--
ALTER TABLE `eco_affected_parts`
  ADD CONSTRAINT `eco_affected_parts_ibfk_1` FOREIGN KEY (`eco_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `eco_approvals`
--
ALTER TABLE `eco_approvals`
  ADD CONSTRAINT `eco_approvals_ibfk_1` FOREIGN KEY (`eco_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`reporting_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `engineering_reviews`
--
ALTER TABLE `engineering_reviews`
  ADD CONSTRAINT `engineering_reviews_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `india_cities`
--
ALTER TABLE `india_cities`
  ADD CONSTRAINT `india_cities_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `india_states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installation_attachments`
--
ALTER TABLE `installation_attachments`
  ADD CONSTRAINT `installation_attachments_ibfk_1` FOREIGN KEY (`installation_id`) REFERENCES `installations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installation_products`
--
ALTER TABLE `installation_products`
  ADD CONSTRAINT `installation_products_ibfk_1` FOREIGN KEY (`installation_id`) REFERENCES `installations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`part_no`) REFERENCES `part_master` (`part_no`);

--
-- Constraints for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD CONSTRAINT `leave_balance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_balance_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`);

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`),
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `location_marketing_summary`
--
ALTER TABLE `location_marketing_summary`
  ADD CONSTRAINT `location_marketing_summary_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `india_states` (`id`);

--
-- Constraints for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD CONSTRAINT `marketing_campaigns_ibfk_1` FOREIGN KEY (`campaign_type_id`) REFERENCES `campaign_types` (`id`),
  ADD CONSTRAINT `marketing_campaigns_ibfk_2` FOREIGN KEY (`state_id`) REFERENCES `india_states` (`id`);

--
-- Constraints for table `milestone_documents`
--
ALTER TABLE `milestone_documents`
  ADD CONSTRAINT `milestone_documents_ibfk_1` FOREIGN KEY (`milestone_id`) REFERENCES `project_milestones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `milestone_documents_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `part_min_stock`
--
ALTER TABLE `part_min_stock`
  ADD CONSTRAINT `part_min_stock_ibfk_1` FOREIGN KEY (`part_no`) REFERENCES `part_master` (`part_no`) ON DELETE CASCADE;

--
-- Constraints for table `part_supplier_mapping`
--
ALTER TABLE `part_supplier_mapping`
  ADD CONSTRAINT `part_supplier_mapping_ibfk_1` FOREIGN KEY (`part_no`) REFERENCES `part_master` (`part_no`) ON DELETE CASCADE,
  ADD CONSTRAINT `part_supplier_mapping_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `po_inspection_approvals`
--
ALTER TABLE `po_inspection_approvals`
  ADD CONSTRAINT `po_inspection_approvals_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `po_inspection_checklists` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `po_inspection_approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `po_inspection_approvers`
--
ALTER TABLE `po_inspection_approvers`
  ADD CONSTRAINT `po_inspection_approvers_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `po_inspection_checklist_items`
--
ALTER TABLE `po_inspection_checklist_items`
  ADD CONSTRAINT `po_inspection_checklist_items_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `po_inspection_checklists` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `procurement_plan_items`
--
ALTER TABLE `procurement_plan_items`
  ADD CONSTRAINT `procurement_plan_items_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `procurement_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `procurement_plan_items_ibfk_2` FOREIGN KEY (`part_no`) REFERENCES `part_master` (`part_no`) ON DELETE CASCADE,
  ADD CONSTRAINT `procurement_plan_items_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL;

--
-- Constraints for table `project_activities`
--
ALTER TABLE `project_activities`
  ADD CONSTRAINT `project_activities_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_documents`
--
ALTER TABLE `project_documents`
  ADD CONSTRAINT `project_documents_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_milestones`
--
ALTER TABLE `project_milestones`
  ADD CONSTRAINT `project_milestones_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_tasks`
--
ALTER TABLE `project_tasks`
  ADD CONSTRAINT `project_tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`part_no`) REFERENCES `part_master` (`part_no`);

--
-- Constraints for table `qc_issue_actions`
--
ALTER TABLE `qc_issue_actions`
  ADD CONSTRAINT `qc_issue_actions_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `qc_quality_issues` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_issue_attachments`
--
ALTER TABLE `qc_issue_attachments`
  ADD CONSTRAINT `qc_issue_attachments_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `qc_quality_issues` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_issue_comments`
--
ALTER TABLE `qc_issue_comments`
  ADD CONSTRAINT `qc_issue_comments_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `qc_quality_issues` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qms_cdsco_adverse_events`
--
ALTER TABLE `qms_cdsco_adverse_events`
  ADD CONSTRAINT `qms_cdsco_adverse_events_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `qms_cdsco_products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qms_icmed_audits`
--
ALTER TABLE `qms_icmed_audits`
  ADD CONSTRAINT `qms_icmed_audits_ibfk_1` FOREIGN KEY (`certification_id`) REFERENCES `qms_icmed_certifications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qms_icmed_certifications`
--
ALTER TABLE `qms_icmed_certifications`
  ADD CONSTRAINT `qms_icmed_certifications_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `qms_cdsco_products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qms_iso_audits`
--
ALTER TABLE `qms_iso_audits`
  ADD CONSTRAINT `qms_iso_audits_ibfk_1` FOREIGN KEY (`certification_id`) REFERENCES `qms_iso_certifications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qms_ncr`
--
ALTER TABLE `qms_ncr`
  ADD CONSTRAINT `qms_ncr_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `qms_iso_audits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qms_training`
--
ALTER TABLE `qms_training`
  ADD CONSTRAINT `qms_training_ibfk_1` FOREIGN KEY (`related_document_id`) REFERENCES `qms_documents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quote_items`
--
ALTER TABLE `quote_items`
  ADD CONSTRAINT `quote_items_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quote_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `review_findings`
--
ALTER TABLE `review_findings`
  ADD CONSTRAINT `review_findings_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `engineering_reviews` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_advances`
--
ALTER TABLE `salary_advances`
  ADD CONSTRAINT `salary_advances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_advances_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `sales_orders_ibfk_1` FOREIGN KEY (`part_no`) REFERENCES `part_master` (`part_no`),
  ADD CONSTRAINT `sales_orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `service_complaints`
--
ALTER TABLE `service_complaints`
  ADD CONSTRAINT `service_complaints_ibfk_1` FOREIGN KEY (`issue_category_id`) REFERENCES `service_issue_categories` (`id`),
  ADD CONSTRAINT `service_complaints_ibfk_2` FOREIGN KEY (`assigned_technician_id`) REFERENCES `service_technicians` (`id`),
  ADD CONSTRAINT `service_complaints_ibfk_3` FOREIGN KEY (`state_id`) REFERENCES `india_states` (`id`);

--
-- Constraints for table `service_visits`
--
ALTER TABLE `service_visits`
  ADD CONSTRAINT `service_visits_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `service_complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_visits_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `service_technicians` (`id`);

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wo_approvers`
--
ALTER TABLE `wo_approvers`
  ADD CONSTRAINT `wo_approvers_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wo_closing_approvals`
--
ALTER TABLE `wo_closing_approvals`
  ADD CONSTRAINT `wo_closing_approvals_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wo_closing_approvals_ibfk_2` FOREIGN KEY (`checklist_id`) REFERENCES `wo_quality_checklists` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `wo_closing_approvals_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `wo_closing_approvals_ibfk_4` FOREIGN KEY (`approver_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wo_quality_checklists`
--
ALTER TABLE `wo_quality_checklists`
  ADD CONSTRAINT `wo_quality_checklists_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wo_quality_checklists_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wo_quality_checklist_items`
--
ALTER TABLE `wo_quality_checklist_items`
  ADD CONSTRAINT `wo_quality_checklist_items_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `wo_quality_checklists` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
