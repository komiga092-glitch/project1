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

$pageTitle = 'Liability Management';
$pageDescription = 'Add and manage company liabilities';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['add_liability'])) {
    $liability_name = trim($_POST['liability_name'] ?? '');
    $liability_type = trim($_POST['liability_type'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $liability_date = trim($_POST['liability_date'] ?? '');
    $due_date       = trim($_POST['due_date'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $payment_source = trim($_POST['payment_source'] ?? 'Cash');
    $bank_name      = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');

    if ($liability_name === '' || $amount <= 0 || $liability_date === '') {
        $msg = 'Please fill required fields correctly.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO liabilities
            (company_id, liability_name, liability_type, amount, liability_date, description, due_date, payment_source, bank_name, account_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdssssss", $company_id, $liability_name, $liability_type, $amount, $liability_date, $description, $due_date, $payment_source, $bank_name, $account_number);

        if ($stmt->execute()) {
            $msg = 'Liability added successfully.';
            $msgType = 'success';
        } else {
            $msg = 'Failed to add liability.';
            $msgType = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $liability_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM liabilities WHERE company_id = ? AND liability_id = ?");
    $stmt->bind_param("ii", $company_id, $liability_id);
    if ($stmt->execute()) {
        $msg = 'Liability deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$rows = [];
$stmt = $conn->prepare("SELECT * FROM liabilities WHERE company_id = ? ORDER BY liability_id DESC");
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
                <h1>Liability Management</h1>
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
                <h3>Add Liability</h3>
                <span class="badge badge-primary">Liability Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Liability Name</label>
                            <input type="text" name="liability_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Liability Type</label>
                            <input type="text" name="liability_type" class="form-control" placeholder="Loan / Payable / Advance">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Liability Date</label>
                            <input type="date" name="liability_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Source</label>
                            <select name="payment_source" class="form-control" onchange="toggleLiabilityBankFields(this.value)">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank</option>
                            </select>
                        </div>

                        <div class="form-group" id="liabilityBankNameWrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>

                        <div class="form-group" id="liabilityAccountNoWrap" style="display:none;">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_number" class="form-control">
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"></textarea>
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="add_liability" class="btn btn-primary">Save Liability</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Liability Records</h3>
                <span class="badge badge-warning"><?= count($rows) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Liability Date</th>
                                <th>Due Date</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['liability_id']) ?></td>
                                    <td><?= e($row['liability_name']) ?></td>
                                    <td><?= e($row['liability_type']) ?></td>
                                    <td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
                                    <td><?= e($row['liability_date']) ?></td>
                                    <td><?= e($row['due_date']) ?></td>
                                    <td><span class="badge badge-primary"><?= e($row['payment_source']) ?></span></td>
                                    <td>
                                        <a class="btn btn-danger" href="?delete=<?= (int)$row['liability_id'] ?>" onclick="return confirm('Delete this liability record?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8">No liabilities found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleLiabilityBankFields(value){
    document.getElementById('liabilityBankNameWrap').style.display = value === 'Bank' ? 'block' : 'none';
    document.getElementById('liabilityAccountNoWrap').style.display = value === 'Bank' ? 'block' : 'none';
}
</script>