<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);
$role       = strtolower(trim($_SESSION['role'] ?? ''));

if ($company_id <= 0) {
    header("Location: company_switch.php");
    exit;
}

$pageTitle = 'Dashboard';
$pageDescription = 'Professional overview of your selected company';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
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

/* company validation */
$company = null;
$stmt = $conn->prepare("
    SELECT c.company_id, c.company_name, c.registration_no, c.address, c.phone, c.email,
           cua.role_in_company, cua.access_status
    FROM company_user_access cua
    INNER JOIN companies c ON c.company_id = cua.company_id
    WHERE cua.user_id = ?
      AND cua.company_id = ?
      AND cua.access_status = 'Active'
    LIMIT 1
");
$stmt->bind_param("ii", $user_id, $company_id);
$stmt->execute();
$res = $stmt->get_result();
$company = $res->fetch_assoc();
$stmt->close();

if (!$company) {
    header("Location: company_switch.php");
    exit;
}

$_SESSION['company_name'] = $company['company_name'];
$_SESSION['company_registration_no'] = $company['registration_no'];
$_SESSION['company_email'] = $company['email'];
$_SESSION['company_phone'] = $company['phone'];
$_SESSION['role'] = ucfirst($company['role_in_company']);
$role = strtolower(trim($company['role_in_company'] ?? ''));

/* accountant side data */
$totalIncome       = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM income WHERE company_id = ?", $company_id);
$totalExpense      = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id = ?", $company_id);
$totalAssets       = getSingleValue($conn, "SELECT COALESCE(SUM(CASE WHEN current_value IS NOT NULL AND current_value > 0 THEN current_value ELSE cost_value END),0) FROM assets WHERE company_id = ?", $company_id);
$totalLiabilities  = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM liabilities WHERE company_id = ?", $company_id);
$totalCashIn       = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_account WHERE company_id = ? AND transaction_type = 'Cash In'", $company_id);
$totalCashOut      = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_account WHERE company_id = ? AND transaction_type = 'Cash Out'", $company_id);
$totalBankCredit   = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM bank_account WHERE company_id = ? AND transaction_type = 'Credit'", $company_id);
$totalBankDebit    = getSingleValue($conn, "SELECT COALESCE(SUM(amount),0) FROM bank_account WHERE company_id = ? AND transaction_type = 'Debit'", $company_id);

$cashBalance       = $totalCashIn - $totalCashOut;
$bankBalance       = $totalBankCredit - $totalBankDebit;
$netIncomePosition = $totalIncome - $totalExpense;
$netWorthEstimate  = $totalAssets - $totalLiabilities;

/* auditor side data */
$totalAssignments = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM audit_assignments
    WHERE company_id = ?
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stmt->bind_result($totalAssignments);
$stmt->fetch();
$stmt->close();

$totalAuditNotes = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM audit_notes
    WHERE company_id = ? AND auditor_id = ?
");
$stmt->bind_param("ii", $company_id, $user_id);
$stmt->execute();
$stmt->bind_result($totalAuditNotes);
$stmt->fetch();
$stmt->close();

$totalAuditReports = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM audit_reports
    WHERE company_id = ? AND auditor_id = ?
");
$stmt->bind_param("ii", $company_id, $user_id);
$stmt->execute();
$stmt->bind_result($totalAuditReports);
$stmt->fetch();
$stmt->close();

/* recent transactions only for accountant/org */
$recentTransactions = [];
if (in_array($role, ['accountant', 'organization'])) {
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
            <div class="role-pill"><?= e(ucfirst($role)) ?></div>
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

        <!-- common company info -->
        <div class="card">
            <div class="card-header">
                <h3>Company Information</h3>
                <span class="badge badge-primary"><?= e(ucfirst($role)) ?></span>
            </div>
            <div class="card-body">
                <div class="grid grid-2">
                    <div>
                        <p><strong>Company Name:</strong> <?= e($company['company_name'] ?? '-') ?></p>
                        <p><strong>Registration No:</strong> <?= e($company['registration_no'] ?? '-') ?></p>
                        <p><strong>Email:</strong> <?= e($company['email'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p><strong>Phone:</strong> <?= e($company['phone'] ?? '-') ?></p>
                        <p><strong>Address:</strong> <?= e($company['address'] ?? '-') ?></p>
                        <p><strong>Your Role:</strong> <?= e(ucfirst($role)) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (in_array($role, ['accountant', 'organization'])): ?>
            <!-- accountant dashboard view -->
            <div class="grid grid-4 mt-24">
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
                    <div class="stat-note">Cash In - Cash Out</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-title">Bank Balance</div>
                        <div class="stat-icon">🏦</div>
                    </div>
                    <div class="stat-value">Rs. <?= number_format($bankBalance, 2) ?></div>
                    <div class="stat-note">Credit - Debit</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-title">Income Position</div>
                        <div class="stat-icon">📊</div>
                    </div>
                    <div class="stat-value">Rs. <?= number_format($netIncomePosition, 2) ?></div>
                    <div class="stat-note">Income - Expenses</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-title">Net Worth</div>
                        <div class="stat-icon">✅</div>
                    </div>
                    <div class="stat-value">Rs. <?= number_format($netWorthEstimate, 2) ?></div>
                    <div class="stat-note">Assets - Liabilities</div>
                </div>
            </div>

            <div class="card mt-24">
                <div class="card-header">
                    <h3>Quick Accountant Actions</h3>
                    <span class="badge badge-warning">Accountant View</span>
                </div>
                <div class="card-body">
                    <div class="grid grid-2">
                        <a href="income.php" class="btn btn-primary">+ Add Income</a>
                        <a href="expenses.php" class="btn btn-outline">+ Add Expense</a>
                        <a href="assets.php" class="btn btn-light">+ Add Asset</a>
                        <a href="liabilities.php" class="btn btn-primary">+ Add Liability</a>
                        <a href="invite_auditor.php" class="btn btn-outline">Invite Auditor</a>
                        <a href="income_expenditure_report.php" class="btn btn-light">View Reports</a>
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
                                        if ($type === 'Cash In' || $type === 'Credit') $badgeClass = 'badge-success';
                                        if ($type === 'Cash Out' || $type === 'Debit') $badgeClass = 'badge-danger';
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
                                    <tr><td colspan="5">No recent transactions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array($role, ['auditor', 'organization'])): ?>
            <!-- auditor dashboard view -->
            <div class="grid grid-3 mt-24">
                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-title">Audit Assignments</div>
                        <div class="stat-icon">📋</div>
                    </div>
                    <div class="stat-value"><?= (int)$totalAssignments ?></div>
                    <div class="stat-note">Assigned company audits</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-title">Audit Notes</div>
                        <div class="stat-icon">📝</div>
                    </div>
                    <div class="stat-value"><?= (int)$totalAuditNotes ?></div>
                    <div class="stat-note">Created by you</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-title">Audit Reports</div>
                        <div class="stat-icon">✅</div>
                    </div>
                    <div class="stat-value"><?= (int)$totalAuditReports ?></div>
                    <div class="stat-note">Finalized reports</div>
                </div>
            </div>

            <div class="card mt-24">
                <div class="card-header">
                    <h3>Quick Audit Actions</h3>
                    <span class="badge badge-primary">Auditor View</span>
                </div>
                <div class="card-body">
                    <div class="grid grid-2">
                        <a href="audit_notes.php" class="btn btn-primary">Open Audit Notes</a>
                        <a href="audit_reports.php" class="btn btn-outline">Open Audit Reports</a>
                        <a href="assets_liabilities_report.php" class="btn btn-light">Review Statements</a>
                        <a href="income_expenditure_report.php" class="btn btn-primary">Audit Financial Report</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

<?php include 'includes/footer.php'; ?>