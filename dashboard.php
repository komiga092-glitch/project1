<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($company_id <= 0) {
    header("Location: select_company.php");
    exit;
}

$pageTitle = 'Dashboard';
$pageDescription = 'Professional overview of your selected company';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$accessOk = false;
$accessSql = "
    SELECT c.company_id, c.company_name, c.registration_no, c.address, c.phone, c.email,
           cua.role_in_company, cua.access_status
    FROM company_user_access cua
    INNER JOIN companies c ON c.company_id = cua.company_id
    WHERE cua.user_id = ?
      AND cua.company_id = ?
      AND cua.access_status = 'Active'
    LIMIT 1
";

if ($stmt = $conn->prepare($accessSql)) {
    $stmt->bind_param("ii", $user_id, $company_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $company = $res->fetch_assoc();
    $stmt->close();

    if ($company) {
        $accessOk = true;
        $_SESSION['company_name'] = $company['company_name'];
        $_SESSION['company_registration_no'] = $company['registration_no'];
        $_SESSION['company_email'] = $company['email'];
        $_SESSION['company_phone'] = $company['phone'];
        $_SESSION['role'] = ucfirst($company['role_in_company']);
    }
}

if (!$accessOk) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

function getSingleValue($conn, $sql, $company_id) {
    $value = 0;
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $stmt->bind_result($result);
        $stmt->fetch();
        $value = $result ?? 0;
        $stmt->close();
    }
    return (float)$value;
}

$totalIncome = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM income WHERE company_id = ?", $company_id);
$totalExpense = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id = ?", $company_id);
$totalAssets = getSingleValue($conn, "SELECT COALESCE(SUM(CASE WHEN current_value IS NOT NULL AND current_value > 0 THEN current_value ELSE cost_value END),0) FROM assets WHERE company_id = ?", $company_id);
$totalLiabilities = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM liabilities WHERE company_id = ?", $company_id);

$totalCashIn = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_account WHERE company_id = ? AND transaction_type = 'Cash In'", $company_id);
$totalCashOut = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_account WHERE company_id = ? AND transaction_type = 'Cash Out'", $company_id);

$totalBankCredit = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM bank_account WHERE company_id = ? AND transaction_type = 'Credit'", $company_id);
$totalBankDebit = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM bank_account WHERE company_id = ? AND transaction_type = 'Debit'", $company_id);

$cashBalance = $totalCashIn - $totalCashOut;
$bankBalance = $totalBankCredit - $totalBankDebit;
$netIncomePosition = $totalIncome - $totalExpense;
$netWorthEstimate = $totalAssets - $totalLiabilities;

$recentTransactions = [];
$sqlRecent = "
    SELECT 'Cash' AS source_name, transaction_date AS txn_date, description, transaction_type, amount
    FROM cash_account
    WHERE company_id = ?
    UNION ALL
    SELECT 'Bank' AS source_name, transaction_date AS txn_date, description, transaction_type, amount
    FROM bank_account
    WHERE company_id = ?
    ORDER BY txn_date DESC
    LIMIT 10
";

if ($stmt = $conn->prepare($sqlRecent)) {
    $stmt->bind_param("ii", $company_id, $company_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
    $stmt->close();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-area">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="page-heading">
                <h1>Dashboard</h1>
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
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Total Income</div>
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($totalIncome, 2) ?></div>
                <div class="stat-note">Company income total</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Total Expenses</div>
                    <div class="stat-icon">💸</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($totalExpense, 2) ?></div>
                <div class="stat-note">Company expense total</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Total Assets</div>
                    <div class="stat-icon">📦</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($totalAssets, 2) ?></div>
                <div class="stat-note">Current asset valuation</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Liabilities</div>
                    <div class="stat-icon">📉</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($totalLiabilities, 2) ?></div>
                <div class="stat-note">Outstanding liabilities</div>
            </div>
        </div>

        <div class="grid grid-4 mt-24">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Cash Balance</div>
                    <div class="stat-icon">💵</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($cashBalance, 2) ?></div>
                <div class="stat-note">Cash In minus Cash Out</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Bank Balance</div>
                    <div class="stat-icon">🏦</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($bankBalance, 2) ?></div>
                <div class="stat-note">Credit minus Debit</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Income Position</div>
                    <div class="stat-icon">📊</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($netIncomePosition, 2) ?></div>
                <div class="stat-note">Income minus Expenses</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-title">Net Worth</div>
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-value">Rs. <?= number_format($netWorthEstimate, 2) ?></div>
                <div class="stat-note">Assets minus Liabilities</div>
            </div>
        </div>

        <div class="grid grid-2 mt-24">
            <div class="card">
                <div class="card-header">
                    <h3>Company Information</h3>
                    <span class="badge badge-primary"><?= e($_SESSION['role'] ?? 'User') ?></span>
                </div>
                <div class="card-body">
                    <p><strong>Company Name:</strong> <?= e($company['company_name'] ?? '-') ?></p>
                    <p><strong>Registration No:</strong> <?= e($company['registration_no'] ?? '-') ?></p>
                    <p><strong>Email:</strong> <?= e($company['email'] ?? '-') ?></p>
                    <p><strong>Phone:</strong> <?= e($company['phone'] ?? '-') ?></p>
                    <p><strong>Address:</strong> <?= e($company['address'] ?? '-') ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                    <span class="badge badge-warning">Fast Access</span>
                </div>
                <div class="card-body">
                    <div class="grid grid-2">
                        <a href="income.php" class="btn btn-primary">+ Add Income</a>
                        <a href="expenses.php" class="btn btn-outline">+ Add Expense</a>
                        <a href="assets.php" class="btn btn-light">+ Add Asset</a>
                        <a href="liabilities.php" class="btn btn-primary">+ Add Liability</a>
                        <a href="income_expenditure_report.php" class="btn btn-outline">Income Report</a>
                        <a href="assets_liabilities_report.php" class="btn btn-light">Asset Report</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Recent Cash / Bank Transactions</h3>
                <span class="badge badge-success"><?= count($recentTransactions) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Source</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentTransactions): ?>
                                <?php foreach ($recentTransactions as $row): ?>
                                    <?php
                                    $type = $row['transaction_type'] ?? '';
                                    $badgeClass = 'badge-primary';

                                    if ($type === 'Cash In' || $type === 'Credit') {
                                        $badgeClass = 'badge-success';
                                    } elseif ($type === 'Cash Out' || $type === 'Debit') {
                                        $badgeClass = 'badge-danger';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= e($row['txn_date']) ?></td>
                                        <td><?= e($row['source_name']) ?></td>
                                        <td><?= e($row['description']) ?></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= e($type) ?></span></td>
                                        <td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No recent transactions found for this company.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>