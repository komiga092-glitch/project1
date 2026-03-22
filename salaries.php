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

$pageTitle = 'Salary Management';
$pageDescription = 'Manage employee salary records';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* =========================
   FETCH EMPLOYEES
========================= */
$employees = [];
$stmt = $conn->prepare("
    SELECT employee_id, employee_name, position, basic_salary
    FROM employees
    WHERE company_id = ?
    ORDER BY employee_name ASC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $employees[] = $row;
}
$stmt->close();

/* =========================
   EDIT DEFAULTS
========================= */
$edit_mode = false;
$edit = [
    'salary_id' => '',
    'employee_id' => '',
    'salary_month' => '',
    'basic_salary' => '',
    'allowance' => '0',
    'deduction' => '0',
    'payment_date' => '',
    'payment_source' => 'Cash',
    'bank_name' => '',
    'account_number' => ''
];

/* =========================
   EDIT FETCH
========================= */
if (isset($_GET['edit'])) {
    $salary_id = (int)($_GET['edit'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM salaries WHERE company_id = ? AND salary_id = ? LIMIT 1");
    $stmt->bind_param("ii", $company_id, $salary_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $edit = $row;
        $edit_mode = true;
    }

    $stmt->close();
}

/* =========================
   ADD / UPDATE SALARY
========================= */
if (isset($_POST['save_salary'])) {
    $salary_id       = (int)($_POST['salary_id'] ?? 0);
    $employee_id     = (int)($_POST['employee_id'] ?? 0);
    $salary_month    = trim($_POST['salary_month'] ?? '');
    $basic_salary    = (float)($_POST['basic_salary'] ?? 0);
    $allowance       = (float)($_POST['allowance'] ?? 0);
    $deduction       = (float)($_POST['deduction'] ?? 0);
    $payment_date    = trim($_POST['payment_date'] ?? '');
    $payment_source  = trim($_POST['payment_source'] ?? 'Cash');
    $bank_name       = trim($_POST['bank_name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');

    if ($payment_source !== 'Bank') {
        $bank_name = '';
        $account_number = '';
    }

    $gross_salary = $basic_salary + $allowance;
    $epf_employee = $basic_salary * 0.08;
    $epf_employer = $basic_salary * 0.12;
    $etf_employer = $basic_salary * 0.03;
    $net_salary   = $gross_salary - $epf_employee - $deduction;

    if ($employee_id <= 0 || $salary_month === '' || $basic_salary <= 0 || $payment_date === '') {
        $msg = 'Please fill required fields correctly.';
        $msgType = 'danger';
    } else {
        if ($salary_id > 0) {
            $stmt = $conn->prepare("
                UPDATE salaries
                SET employee_id = ?, salary_month = ?, basic_salary = ?, allowance = ?, deduction = ?,
                    gross_salary = ?, epf_employee = ?, epf_employer = ?, etf_employer = ?, net_salary = ?,
                    payment_date = ?, payment_source = ?, bank_name = ?, account_number = ?
                WHERE company_id = ? AND salary_id = ?
            ");

            $stmt->bind_param(
                "isddddddddssssii",
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
                $account_number,
                $company_id,
                $salary_id
            );

            if ($stmt->execute()) {
                $msg = 'Salary record updated successfully.';
                $msgType = 'success';

                $edit_mode = false;
                $edit = [
                    'salary_id' => '',
                    'employee_id' => '',
                    'salary_month' => '',
                    'basic_salary' => '',
                    'allowance' => '0',
                    'deduction' => '0',
                    'payment_date' => '',
                    'payment_source' => 'Cash',
                    'bank_name' => '',
                    'account_number' => ''
                ];
            } else {
                $msg = 'Failed to update salary record.';
                $msgType = 'danger';
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO salaries
                (company_id, employee_id, salary_month, basic_salary, allowance, deduction,
                 gross_salary, epf_employee, epf_employer, etf_employer, net_salary,
                 payment_date, payment_source, bank_name, account_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

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
}

/* =========================
   DELETE SALARY
========================= */
if (isset($_GET['delete'])) {
    $salary_id = (int)($_GET['delete'] ?? 0);

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

/* =========================
   FETCH SALARY RECORDS
========================= */
$rows = [];
$stmt = $conn->prepare("
    SELECT s.*, e.employee_name, e.position
    FROM salaries s
    LEFT JOIN employees e ON s.employee_id = e.employee_id
    WHERE s.company_id = ?
    ORDER BY s.salary_id DESC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-area">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="page-heading">
                <h1>Salary Management</h1>
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
                <h3><?= $edit_mode ? 'Edit Salary' : 'Add Salary' ?></h3>
                <span class="badge badge-primary">Professional Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="salary_id" value="<?= e($edit['salary_id']) ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Employee</label>
                            <select name="employee_id" id="employee_id" class="form-control" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option
                                        value="<?= (int)$emp['employee_id'] ?>"
                                        data-salary="<?= e($emp['basic_salary']) ?>"
                                        <?= (string)$edit['employee_id'] === (string)$emp['employee_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($emp['employee_name']) ?><?= !empty($emp['position']) ? ' - ' . e($emp['position']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Salary Month</label>
                            <input
                                type="text"
                                name="salary_month"
                                class="form-control"
                                placeholder="2026-03"
                                value="<?= e($edit['salary_month']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Basic Salary</label>
                            <input
                                type="number"
                                step="0.01"
                                name="basic_salary"
                                id="basic_salary"
                                class="form-control"
                                value="<?= e($edit['basic_salary']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Allowance</label>
                            <input
                                type="number"
                                step="0.01"
                                name="allowance"
                                class="form-control"
                                value="<?= e($edit['allowance']) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Deduction</label>
                            <input
                                type="number"
                                step="0.01"
                                name="deduction"
                                class="form-control"
                                value="<?= e($edit['deduction']) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Date</label>
                            <input
                                type="date"
                                name="payment_date"
                                class="form-control"
                                value="<?= e($edit['payment_date']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Source</label>
                            <select
                                name="payment_source"
                                class="form-control"
                                id="salaryPaymentSource"
                                onchange="toggleSalaryBankFields(this.value)"
                            >
                                <option value="Cash" <?= ($edit['payment_source'] ?? 'Cash') === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Bank" <?= ($edit['payment_source'] ?? '') === 'Bank' ? 'selected' : '' ?>>Bank</option>
                            </select>
                        </div>

                        <div class="form-group" id="salaryBankNameWrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input
                                type="text"
                                name="bank_name"
                                class="form-control"
                                value="<?= e($edit['bank_name']) ?>"
                            >
                        </div>

                        <div class="form-group" id="salaryAccountNoWrap" style="display:none;">
                            <label class="form-label">Account Number</label>
                            <input
                                type="text"
                                name="account_number"
                                class="form-control"
                                value="<?= e($edit['account_number']) ?>"
                            >
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="save_salary" class="btn btn-primary">
                                <?= $edit_mode ? 'Update Salary' : 'Save Salary' ?>
                            </button>

                            <?php if ($edit_mode): ?>
                                <a href="salaries.php" class="btn btn-light">Cancel</a>
                            <?php endif; ?>
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
                                <th>Net Salary</th>
                                <th>Payment Date</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['salary_id'] ?? '') ?></td>
                                    <td><?= e($row['employee_name'] ?? 'Unknown Employee') ?></td>
                                    <td><?= e($row['salary_month'] ?? '') ?></td>
                                    <td>Rs. <?= number_format((float)($row['basic_salary'] ?? 0), 2) ?></td>
                                    <td>Rs. <?= number_format((float)($row['allowance'] ?? 0), 2) ?></td>
                                    <td>Rs. <?= number_format((float)($row['deduction'] ?? 0), 2) ?></td>
                                    <td>Rs. <?= number_format((float)($row['net_salary'] ?? 0), 2) ?></td>
                                    <td><?= e($row['payment_date'] ?? '') ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?= e($row['payment_source'] ?? 'Cash') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= (int)$row['salary_id'] ?>" class="btn btn-light">Edit</a>
                                        <a href="?delete=<?= (int)$row['salary_id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this salary record?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">No salary records found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleSalaryBankFields(value){
    document.getElementById('salaryBankNameWrap').style.display = value === 'Bank' ? 'block' : 'none';
    document.getElementById('salaryAccountNoWrap').style.display = value === 'Bank' ? 'block' : 'none';
}

document.getElementById('employee_id').addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    const basicSalary = selected.getAttribute('data-salary') || '';
    const basicSalaryInput = document.getElementById('basic_salary');

    if (basicSalaryInput.value === '') {
        basicSalaryInput.value = basicSalary;
    }
});

toggleSalaryBankFields(document.getElementById('salaryPaymentSource').value);
</script>