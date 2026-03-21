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

$pageTitle = 'Expense Management';
$pageDescription = 'Add and manage company expenses';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['add_expense'])) {
    $expense_type   = trim($_POST['expense_type'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $expense_date   = trim($_POST['expense_date'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $payment_source = trim($_POST['payment_source'] ?? 'Cash');
    $bank_name      = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');

    if ($expense_type === '' || $amount <= 0 || $expense_date === '') {
        $msg = 'Please fill required fields correctly.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO expenses (company_id, expense_type, amount, expense_date, description, payment_source, bank_name, account_number)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsssss", $company_id, $expense_type, $amount, $expense_date, $description, $payment_source, $bank_name, $account_number);
        if ($stmt->execute()) {
            $msg = 'Expense added successfully.';
            $msgType = 'success';
        } else {
            $msg = 'Failed to add expense.';
            $msgType = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $expense_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE company_id = ? AND expense_id = ?");
    $stmt->bind_param("ii", $company_id, $expense_id);
    if ($stmt->execute()) {
        $msg = 'Expense deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$rows = [];
$stmt = $conn->prepare("SELECT * FROM expenses WHERE company_id = ? ORDER BY expense_id DESC");
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
                <h1>Expense Management</h1>
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
                <h3>Add Expense</h3>
                <span class="badge badge-warning">Expense Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Expense Type</label>
                            <input type="text" name="expense_type" class="form-control" placeholder="Office / Salary / Travel / Utility" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Expense Date</label>
                            <input type="date" name="expense_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Source</label>
                            <select name="payment_source" class="form-control" onchange="toggleExpenseBankFields(this.value)">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank</option>
                            </select>
                        </div>

                        <div class="form-group" id="expenseBankNameWrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>

                        <div class="form-group" id="expenseAccountNoWrap" style="display:none;">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_number" class="form-control">
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"></textarea>
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="add_expense" class="btn btn-primary">Save Expense</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Expense Records</h3>
                <span class="badge badge-danger"><?= count($rows) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Expense Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Payment</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['expense_id']) ?></td>
                                    <td><?= e($row['expense_type']) ?></td>
                                    <td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
                                    <td><?= e($row['expense_date']) ?></td>
                                    <td><span class="badge badge-warning"><?= e($row['payment_source']) ?></span></td>
                                    <td><?= e($row['description']) ?></td>
                                    <td>
                                        <a class="btn btn-danger" href="?delete=<?= (int)$row['expense_id'] ?>" onclick="return confirm('Delete this expense record?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No expense records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleExpenseBankFields(value){
    document.getElementById('expenseBankNameWrap').style.display = value === 'Bank' ? 'block' : 'none';
    document.getElementById('expenseAccountNoWrap').style.display = value === 'Bank' ? 'block' : 'none';
}
</script>