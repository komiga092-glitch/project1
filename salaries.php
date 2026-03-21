<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
if ($company_id <= 0) {
    header("Location: select_company.php");
    exit;
}

$pageTitle = 'Salaries';
$pageDescription = 'Manage payroll for employees';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$employees = [];
$stmt = $conn->prepare("SELECT employee_id, employee_name, basic_salary, position FROM employees WHERE company_id = ? ORDER BY employee_name ASC");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $employees[] = $row;
$stmt->close();

if (isset($_POST['add_salary'])) {
    $employee_id     = (int)($_POST['employee_id'] ?? 0);
    $salary_month    = trim($_POST['salary_month'] ?? '');
    $basic_salary    = (float)($_POST['basic_salary'] ?? 0);
    $allowance       = (float)($_POST['allowance'] ?? 0);
    $deduction       = (float)($_POST['deduction'] ?? 0);
    $payment_date    = trim($_POST['payment_date'] ?? '');
    $payment_source  = trim($_POST['payment_source'] ?? 'Cash');
    $bank_name       = trim($_POST['bank_name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');

    $gross_salary  = $basic_salary + $allowance;
    $epf_employee  = round($basic_salary * 0.08, 2);
    $epf_employer  = round($basic_salary * 0.12, 2);
    $etf_employer  = round($basic_salary * 0.03, 2);
    $net_salary    = $gross_salary - $deduction - $epf_employee;

    if ($employee_id <= 0 || $salary_month === '' || $basic_salary <= 0 || $payment_date === '') {
        $msg = 'Please fill all required payroll fields.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO salaries
            (company_id, employee_id, salary_month, basic_salary, allowance, deduction, gross_salary, epf_employee, epf_employer, etf_employer, net_salary, payment_date, payment_source, bank_name, account_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iisddddddddssss",
            $company_id,
            $employee_id,
            $salary_month,
            $basic_salary,
            $allowance,
            $deduction,
            $gross_salary,
            $epf_employee,
            $epf_employer,
            $etf_employer,
            $net_salary,
            $payment_date,
            $payment_source,
            $bank_name,
            $account_number
        );

        if ($stmt->execute()) {
            $msg = 'Salary record added successfully.';
            $msgType = 'success';
        } else {
            $msg = 'Failed to add salary record.';
            $msgType = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $salary_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM salaries WHERE company_id = ? AND salary_id = ?");
    $stmt->bind_param("ii", $company_id, $salary_id);
    if ($stmt->execute()) {
        $msg = 'Salary record deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$rows = [];
$stmt = $conn->prepare("
    SELECT s.*, e.employee_name, e.position
    FROM salaries s
    LEFT JOIN employees e ON e.employee_id = s.employee_id
    WHERE s.company_id = ?
    ORDER BY s.salary_id DESC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-area">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="page-heading">
                <h1>Salaries</h1>
                <p><?= e($pageDescription) ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <div class="company-pill"><?= e($_SESSION['company_name'] ?? 'Company') ?></div>
            <div class="role-pill"><?= e($_SESSION['role'] ?? 'User') ?></div>
            <div class="user-chip">
                <div class="avatar"><?= e(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))) ?></div>
                <div class="meta">
                    <strong><?= e($_SESSION['full_name'] ?? 'User') ?></strong>
                    <span><?= e($_SESSION['email'] ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= e($msgType) ?>"><?= e($msg) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Add Salary</h3>
                <span class="badge badge-primary">Payroll Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Employee</label>
                            <select name="employee_id" id="employee_id" class="form-control" required onchange="fillBasicSalary()">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= (int)$emp['employee_id'] ?>" data-salary="<?= e($emp['basic_salary']) ?>">
                                        <?= e($emp['employee_name']) ?><?= $emp['position'] ? ' - ' . e($emp['position']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Salary Month</label>
                            <input type="text" name="salary_month" class="form-control" placeholder="2026-03" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" step="0.01" name="basic_salary" id="basic_salary" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Allowance</label>
                            <input type="number" step="0.01" name="allowance" class="form-control" value="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Deduction</label>
                            <input type="number" step="0.01" name="deduction" class="form-control" value="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Source</label>
                            <select name="payment_source" class="form-control" onchange="toggleSalaryBankFields(this.value)">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank</option>
                            </select>
                        </div>

                        <div class="form-group" id="salaryBankNameWrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>

                        <div class="form-group" id="salaryAccountNoWrap" style="display:none;">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_number" class="form-control">
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="add_salary" class="btn btn-primary">Save Salary</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Salary Records</h3>
                <span class="badge badge-success"><?= count($rows) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Month</th>
                                <th>Basic</th>
                                <th>Allowance</th>
                                <th>Deduction</th>
                                <th>EPF (8%)</th>
                                <th>Net Salary</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($rows): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= e($row['salary_id']) ?></td>
                                        <td><?= e($row['employee_name'] ?? 'Employee #' . $row['employee_id']) ?></td>
                                        <td><?= e($row['salary_month']) ?></td>
                                        <td>Rs. <?= number_format((float)$row['basic_salary'], 2) ?></td>
                                        <td>Rs. <?= number_format((float)$row['allowance'], 2) ?></td>
                                        <td>Rs. <?= number_format((float)$row['deduction'], 2) ?></td>
                                        <td>Rs. <?= number_format((float)$row['epf_employee'], 2) ?></td>
                                        <td>Rs. <?= number_format((float)$row['net_salary'], 2) ?></td>
                                        <td><span class="badge badge-primary"><?= e($row['payment_source']) ?></span></td>
                                        <td>
                                            <a href="?delete=<?= (int)$row['salary_id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this salary record?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10">No salary records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>

<script>
function fillBasicSalary() {
    const emp = document.getElementById('employee_id');
    const selected = emp.options[emp.selectedIndex];
    const salary = selected.getAttribute('data-salary') || '';
    document.getElementById('basic_salary').value = salary;
}
function toggleSalaryBankFields(value){
    document.getElementById('salaryBankNameWrap').style.display = value === 'Bank' ? 'block' : 'none';
    document.getElementById('salaryAccountNoWrap').style.display = value === 'Bank' ? 'block' : 'none';
}
</script>