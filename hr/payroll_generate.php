<?php
include "../db.php";
include "../includes/dialog.php";

$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthName = date('F Y', strtotime($monthStart));

// Get working days in month (excluding Sundays)
$workingDays = 0;
$current = strtotime($monthStart);
$end = strtotime($monthEnd);
while ($current <= $end) {
    if (date('w', $current) != 0) { // Not Sunday
        $workingDays++;
    }
    $current = strtotime('+1 day', $current);
}

// Subtract holidays
$holidayCount = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date BETWEEN ? AND ? AND DAYOFWEEK(holiday_date) != 1");
$holidayCount->execute([$monthStart, $monthEnd]);
$holidays = $holidayCount->fetchColumn();
$workingDays -= $holidays;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeIds = $_POST['employees'] ?? [];

    if (empty($employeeIds)) {
        setModal("Error", "Please select at least one employee");
    } else {
        $generated = 0;
        $skipped = 0;

        foreach ($employeeIds as $empId) {
            // Check if already exists
            $check = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND payroll_month = ?");
            $check->execute([$empId, $monthStart]);
            if ($check->fetch()) {
                $skipped++;
                continue;
            }

            // Get employee details
            $emp = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
            $emp->execute([$empId]);
            $emp = $emp->fetch(PDO::FETCH_ASSOC);

            if (!$emp) continue;

            // Get attendance summary
            $attStmt = $pdo->prepare("
                SELECT
                    COUNT(CASE WHEN status = 'Present' OR status = 'Late' THEN 1 END) as present,
                    COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_days,
                    COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
                    COUNT(CASE WHEN status = 'On Leave' THEN 1 END) as leaves
                FROM attendance
                WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
            ");
            $attStmt->execute([$empId, $monthStart, $monthEnd]);
            $att = $attStmt->fetch(PDO::FETCH_ASSOC);

            $daysPresent = ($att['present'] ?? 0) + (($att['half_days'] ?? 0) * 0.5);
            $daysAbsent = $att['absent'] ?? 0;
            $leavesTaken = $att['leaves'] ?? 0;

            // Calculate salary proportionally
            $salaryRatio = $workingDays > 0 ? $daysPresent / $workingDays : 0;

            $basic = round($emp['basic_salary'] * $salaryRatio, 2);
            $hra = round($emp['hra'] * $salaryRatio, 2);
            $conveyance = round($emp['conveyance'] * $salaryRatio, 2);
            $medical = round($emp['medical_allowance'] * $salaryRatio, 2);
            $special = round($emp['special_allowance'] * $salaryRatio, 2);
            $other = round($emp['other_allowance'] * $salaryRatio, 2);

            $grossEarnings = $basic + $hra + $conveyance + $medical + $special + $other;

            // Calculate deductions
            $pfEmployee = round($basic * 0.12, 2); // 12% of basic
            $pfEmployer = round($basic * 0.12, 2);
            $esiEmployee = $grossEarnings <= 21000 ? round($grossEarnings * 0.0075, 2) : 0; // 0.75% if gross <= 21000
            $esiEmployer = $grossEarnings <= 21000 ? round($grossEarnings * 0.0325, 2) : 0; // 3.25%

            // Professional Tax (simplified - varies by state)
            $professionalTax = $grossEarnings > 15000 ? 200 : ($grossEarnings > 10000 ? 150 : 0);

            $totalDeductions = $pfEmployee + $esiEmployee + $professionalTax;
            $netPay = $grossEarnings - $totalDeductions;

            // Insert payroll record
            $stmt = $pdo->prepare("
                INSERT INTO payroll (
                    employee_id, payroll_month, working_days, days_present, days_absent, leaves_taken, holidays,
                    basic_salary, hra, conveyance, medical_allowance, special_allowance, other_allowance,
                    gross_earnings, pf_employee, pf_employer, esi_employee, esi_employer, professional_tax,
                    total_deductions, net_pay, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')
            ");
            $stmt->execute([
                $empId, $monthStart, $workingDays, $daysPresent, $daysAbsent, $leavesTaken, $holidays,
                $basic, $hra, $conveyance, $medical, $special, $other,
                $grossEarnings, $pfEmployee, $pfEmployer, $esiEmployee, $esiEmployer, $professionalTax,
                $totalDeductions, $netPay
            ]);

            $generated++;
        }

        setModal("Success", "Payroll generated for $generated employees. Skipped $skipped (already exists).");
        header("Location: payroll.php?month=$selectedMonth");
        exit;
    }
}

// Get active employees without payroll for this month
$employees = $pdo->prepare("
    SELECT e.id, e.emp_id, e.first_name, e.last_name, e.department, e.designation,
           e.basic_salary, e.hra, e.conveyance, e.medical_allowance, e.special_allowance, e.other_allowance,
           (SELECT COUNT(*) FROM payroll p WHERE p.employee_id = e.id AND p.payroll_month = ?) as has_payroll
    FROM employees e
    WHERE e.status = 'Active'
    ORDER BY e.department, e.first_name
");
$employees->execute([$monthStart]);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generate Payroll - <?= $monthName ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .gen-container { max-width: 1000px; }

        .month-info {
            background: #e3f2fd;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .month-info strong { color: #1565c0; }

        .emp-table { width: 100%; border-collapse: collapse; }
        .emp-table th, .emp-table td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .emp-table th { background: #f5f5f5; }
        .emp-table tr:hover { background: #fafafa; }
        .emp-table .number { text-align: right; }

        .already-generated {
            color: #27ae60;
            font-weight: bold;
        }

        .select-all-row {
            background: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="gen-container">
        <h1>Generate Payroll: <?= $monthName ?></h1>
        <p><a href="payroll.php?month=<?= $selectedMonth ?>" class="btn btn-secondary">Back to Payroll</a></p>

        <div class="month-info">
            <strong>Working Days:</strong> <?= $workingDays ?> days
            (Excluding <?= $holidays ?> holidays and Sundays)
        </div>

        <form method="post">
            <div class="select-all-row">
                <label>
                    <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                    <strong>Select All Pending</strong>
                </label>
            </div>

            <table class="emp-table">
                <thead>
                    <tr>
                        <th width="40"></th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="number">Basic</th>
                        <th class="number">Gross (Full Month)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp):
                        $gross = $emp['basic_salary'] + $emp['hra'] + $emp['conveyance'] +
                                 $emp['medical_allowance'] + $emp['special_allowance'] + $emp['other_allowance'];
                        $hasPayroll = $emp['has_payroll'] > 0;
                    ?>
                    <tr>
                        <td>
                            <?php if (!$hasPayroll): ?>
                                <input type="checkbox" name="employees[]" value="<?= $emp['id'] ?>" class="emp-check">
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong><br>
                            <small style="color: #7f8c8d;"><?= htmlspecialchars($emp['emp_id']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($emp['department'] ?? '-') ?></td>
                        <td class="number"><?= number_format($emp['basic_salary'], 2) ?></td>
                        <td class="number"><?= number_format($gross, 2) ?></td>
                        <td>
                            <?php if ($hasPayroll): ?>
                                <span class="already-generated">Already Generated</span>
                            <?php else: ?>
                                <span style="color: #f39c12;">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="padding: 12px 30px;">
                    Generate Payroll for Selected
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAll(checkbox) {
    document.querySelectorAll('.emp-check').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}
</script>

</body>
</html>
