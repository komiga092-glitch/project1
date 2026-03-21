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

$pageTitle = 'Employees';
$pageDescription = 'Manage employee records for the selected company';

$msg = '';
$msgType = 'success';
$edit_mode = false;

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$edit = [
    'employee_id' => '',
    'employee_name' => '',
    'nic' => '',
    'phone' => '',
    'email' => '',
    'position' => '',
    'basic_salary' => '',
    'join_date' => ''
];

if (isset($_GET['edit'])) {
    $employee_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM employees WHERE company_id = ? AND employee_id = ? LIMIT 1");
    $stmt->bind_param("ii", $company_id, $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $edit = $row;
        $edit_mode = true;
    }
    $stmt->close();
}

if (isset($_POST['save_employee'])) {
    $employee_name = trim($_POST['employee_name'] ?? '');
    $nic          = trim($_POST['nic'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $position     = trim($_POST['position'] ?? '');
    $basic_salary = (float)($_POST['basic_salary'] ?? 0);
    $join_date    = trim($_POST['join_date'] ?? '');

    if ($employee_name === '' || $basic_salary < 0) {
        $msg = 'Please enter employee name and valid basic salary.';
        $msgType = 'danger';
    } else {
        if (!empty($_POST['employee_id'])) {
            $employee_id = (int)$_POST['employee_id'];

            $stmt = $conn->prepare("UPDATE employees
                SET employee_name = ?, nic = ?, phone = ?, email = ?, position = ?, basic_salary = ?, join_date = ?
                WHERE company_id = ? AND employee_id = ?");
            $stmt->bind_param(
                "sssssdsii",
                $employee_name,
                $nic,
                $phone,
                $email,
                $position,
                $basic_salary,
                $join_date,
                $company_id,
                $employee_id
            );

            if ($stmt->execute()) {
                $msg = 'Employee updated successfully.';
                $msgType = 'success';
            } else {
                $msg = 'Failed to update employee.';
                $msgType = 'danger';
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO employees
                (company_id, employee_name, nic, phone, email, position, basic_salary, join_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "isssssds",
                $company_id,
                $employee_name,
                $nic,
                $phone,
                $email,
                $position,
                $basic_salary,
                $join_date
            );

            if ($stmt->execute()) {
                $msg = 'Employee added successfully.';
                $msgType = 'success';
            } else {
                $msg = 'Failed to add employee.';
                $msgType = 'danger';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['delete'])) {
    $employee_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM employees WHERE company_id = ? AND employee_id = ?");
    $stmt->bind_param("ii", $company_id, $employee_id);
    if ($stmt->execute()) {
        $msg = 'Employee deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$rows = [];
$stmt = $conn->prepare("SELECT * FROM employees WHERE company_id = ? ORDER BY employee_id DESC");
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
                <h1>Employees</h1>
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
                <h3><?= $edit_mode ? 'Edit Employee' : 'Add Employee' ?></h3>
                <span class="badge badge-primary">Employee Master</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="employee_id" value="<?= e($edit['employee_id']) ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Employee Name</label>
                            <input type="text" name="employee_name" class="form-control" value="<?= e($edit['employee_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">NIC</label>
                            <input type="text" name="nic" class="form-control" value="<?= e($edit['nic']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($edit['phone']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($edit['email']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" value="<?= e($edit['position']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" step="0.01" name="basic_salary" class="form-control" value="<?= e($edit['basic_salary']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Join Date</label>
                            <input type="date" name="join_date" class="form-control" value="<?= e($edit['join_date']) ?>">
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="save_employee" class="btn btn-primary">
                                <?= $edit_mode ? 'Update Employee' : 'Save Employee' ?>
                            </button>
                            <?php if ($edit_mode): ?>
                                <a href="employees.php" class="btn btn-light">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Employee List</h3>
                <span class="badge badge-success"><?= count($rows) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>NIC</th>
                                <th>Phone</th>
                                <th>Position</th>
                                <th>Salary</th>
                                <th>Join Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($rows): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= e($row['employee_id']) ?></td>
                                        <td><?= e($row['employee_name']) ?></td>
                                        <td><?= e($row['nic']) ?></td>
                                        <td><?= e($row['phone']) ?></td>
                                        <td><?= e($row['position']) ?></td>
                                        <td>Rs. <?= number_format((float)$row['basic_salary'], 2) ?></td>
                                        <td><?= e($row['join_date']) ?></td>
                                        <td>
                                            <a href="?edit=<?= (int)$row['employee_id'] ?>" class="btn btn-light">Edit</a>
                                            <a href="?delete=<?= (int)$row['employee_id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this employee?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8">No employees found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>