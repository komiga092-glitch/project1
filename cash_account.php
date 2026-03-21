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

$pageTitle = 'Cash Account';
$pageDescription = 'Manage company cash ledger transactions';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['add_cash'])) {
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $transaction_type = trim($_POST['transaction_type'] ?? '');
    $amount           = (float)($_POST['amount'] ?? 0);

    if ($transaction_date === '' || $description === '' || $amount <= 0 || !in_array($transaction_type, ['Cash In', 'Cash Out'])) {
        $msg = 'Please fill all required fields correctly.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO cash_account (company_id, transaction_date, description, transaction_type, amount)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssd", $company_id, $transaction_date, $description, $transaction_type, $amount);

        if ($stmt->execute()) {
            $msg = 'Cash transaction added successfully.';
            $msgType = 'success';
        } else {
            $msg = 'Failed to add cash transaction.';
            $msgType = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $cash_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM cash_account WHERE company_id = ? AND cash_id = ?");
    $stmt->bind_param("ii", $company_id, $cash_id);
    if ($stmt->execute()) {
        $msg = 'Cash transaction deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$rows = [];
$stmt = $conn->prepare("SELECT * FROM cash_account WHERE company_id = ? ORDER BY transaction_date DESC, cash_id DESC");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

$totalIn = 0;
$totalOut = 0;
foreach ($rows as $r) {
    if (($r['transaction_type'] ?? '') === 'Cash In') {
        $totalIn += (float)$r['amount'];
    } else {
        $totalOut += (float)$r['amount'];
    }
}
$balance = $totalIn - $totalOut;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-area">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="page-heading">
                <h1>Cash Account</h1>
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

        <div class="grid grid-3">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Total Cash In</div>
                    <div class="stat-icon">⬇️</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($totalIn, 2) ?></div>
                <div class="stat-note">All cash inflow records</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Total Cash Out</div>
                    <div class="stat-icon">⬆️</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($totalOut, 2) ?></div>
                <div class="stat-note">All cash outflow records</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Cash Balance</div>
                    <div class="stat-icon">💵</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($balance, 2) ?></div>
                <div class="stat-note">Cash In - Cash Out</div>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Add Cash Transaction</h3>
                <span class="badge badge-primary">Cash Ledger Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Transaction Date</label>
                            <input type="date" name="transaction_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Transaction Type</label>
                            <select name="transaction_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="Cash In">Cash In</option>
                                <option value="Cash Out">Cash Out</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" placeholder="Enter transaction description" required></textarea>
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="add_cash" class="btn btn-primary">Save Cash Entry</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Cash Ledger Records</h3>
                <span class="badge badge-success"><?= count($rows) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['cash_id']) ?></td>
                                    <td><?= e($row['transaction_date']) ?></td>
                                    <td><?= e($row['description']) ?></td>
                                    <td>
                                        <?php $cls = ($row['transaction_type'] === 'Cash In') ? 'badge-success' : 'badge-danger'; ?>
                                        <span class="badge <?= $cls ?>"><?= e($row['transaction_type']) ?></span>
                                    </td>
                                    <td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
                                    <td>
                                        <a class="btn btn-danger" href="?delete=<?= (int)$row['cash_id'] ?>" onclick="return confirm('Delete this cash entry?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No cash transactions found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>