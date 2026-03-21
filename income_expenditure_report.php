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

$pageTitle = 'Income & Expenditure Report';
$pageDescription = 'Professional financial statement for selected company';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$from_date = trim($_GET['from_date'] ?? date('Y-m-01'));
$to_date   = trim($_GET['to_date'] ?? date('Y-m-d'));

$company = null;
$stmt = $conn->prepare("SELECT company_id, company_name, registration_no, email, phone FROM companies WHERE company_id = ? LIMIT 1");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
$company = $res->fetch_assoc();
$stmt->close();

$income_rows = [];
$stmt = $conn->prepare("
    SELECT income_id, income_type, amount, income_date, description, payment_source, bank_name, account_number
    FROM income
    WHERE company_id = ? AND income_date BETWEEN ? AND ?
    ORDER BY income_date ASC, income_id ASC
");
$stmt->bind_param("iss", $company_id, $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $income_rows[] = $row;
$stmt->close();

$expense_rows = [];
$stmt = $conn->prepare("
    SELECT expense_id, expense_type, amount, expense_date, description, payment_source, bank_name, account_number
    FROM expenses
    WHERE company_id = ? AND expense_date BETWEEN ? AND ?
    ORDER BY expense_date ASC, expense_id ASC
");
$stmt->bind_param("iss", $company_id, $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $expense_rows[] = $row;
$stmt->close();

$total_income = 0;
$income_by_type = [];
foreach ($income_rows as $row) {
    $amount = (float)$row['amount'];
    $type = trim($row['income_type'] ?? '') ?: 'Other Income';
    $total_income += $amount;
    if (!isset($income_by_type[$type])) $income_by_type[$type] = 0;
    $income_by_type[$type] += $amount;
}

$total_expense = 0;
$expense_by_type = [];
foreach ($expense_rows as $row) {
    $amount = (float)$row['amount'];
    $type = trim($row['expense_type'] ?? '') ?: 'Other Expense';
    $total_expense += $amount;
    if (!isset($expense_by_type[$type])) $expense_by_type[$type] = 0;
    $expense_by_type[$type] += $amount;
}

$surplus_deficit = $total_income - $total_expense;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-area">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="page-heading">
                <h1>Income & Expenditure Report</h1>
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
        <div class="card">
            <div class="card-header">
                <h3>Report Filters</h3>
                <div class="flex items-center gap-12">
                    <button onclick="window.print()" class="btn btn-primary">Print Report</button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="<?= e($from_date) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="<?= e($to_date) ?>" required>
                        </div>

                        <div class="form-group full">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24" id="printArea">
            <div class="card-body">
                <div style="text-align:center; margin-bottom:24px;">
                    <h2 style="color:var(--primary-dark); margin-bottom:8px;">Income & Expenditure Statement</h2>
                    <h3 style="margin-bottom:6px;"><?= e($company['company_name'] ?? 'Company') ?></h3>
                    <div class="text-muted">Registration No: <?= e($company['registration_no'] ?? '-') ?></div>
                    <div class="text-muted">Period: <?= e($from_date) ?> to <?= e($to_date) ?></div>
                </div>

                <div class="grid grid-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-title">Total Income</div>
                            <div class="stat-icon">💰</div>
                        </div>
                        <div class="stat-value">Rs. <?= number_format($total_income, 2) ?></div>
                        <div class="stat-note">All income within selected period</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-title">Total Expenditure</div>
                            <div class="stat-icon">💸</div>
                        </div>
                        <div class="stat-value">Rs. <?= number_format($total_expense, 2) ?></div>
                        <div class="stat-note">All expenses within selected period</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-title"><?= $surplus_deficit >= 0 ? 'Surplus' : 'Deficit' ?></div>
                            <div class="stat-icon">📊</div>
                        </div>
                        <div class="stat-value">Rs. <?= number_format(abs($surplus_deficit), 2) ?></div>
                        <div class="stat-note"><?= $surplus_deficit >= 0 ? 'Positive financial result' : 'Negative financial result' ?></div>
                    </div>
                </div>

                <div class="grid grid-2 mt-24">
                    <div class="card">
                        <div class="card-header">
                            <h3>Income Summary</h3>
                            <span class="badge badge-success"><?= count($income_rows) ?> Entries</span>
                        </div>
                        <div class="card-body">
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Income Type</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($income_by_type): ?>
                                            <?php foreach ($income_by_type as $type => $amount): ?>
                                                <tr>
                                                    <td><?= e($type) ?></td>
                                                    <td>Rs. <?= number_format($amount, 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <th>Total Income</th>
                                                <th>Rs. <?= number_format($total_income, 2) ?></th>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="2">No income records found for this period.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Expenditure Summary</h3>
                            <span class="badge badge-danger"><?= count($expense_rows) ?> Entries</span>
                        </div>
                        <div class="card-body">
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Expense Type</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($expense_by_type): ?>
                                            <?php foreach ($expense_by_type as $type => $amount): ?>
                                                <tr>
                                                    <td><?= e($type) ?></td>
                                                    <td>Rs. <?= number_format($amount, 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <th>Total Expenditure</th>
                                                <th>Rs. <?= number_format($total_expense, 2) ?></th>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="2">No expense records found for this period.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-24">
                    <div class="card-header">
                        <h3>Detailed Transactions</h3>
                        <span class="badge badge-primary">Statement View</span>
                    </div>
                    <div class="card-body">
                        <h4 style="margin-bottom:12px; color:var(--primary-dark);">Income Details</h4>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Payment</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($income_rows): ?>
                                        <?php foreach ($income_rows as $row): ?>
                                            <tr>
                                                <td><?= e($row['income_date']) ?></td>
                                                <td><?= e($row['income_type']) ?></td>
                                                <td><?= e($row['description']) ?></td>
                                                <td><?= e($row['payment_source']) ?></td>
                                                <td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5">No income details found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <h4 style="margin:24px 0 12px; color:var(--primary-dark);">Expense Details</h4>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Payment</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($expense_rows): ?>
                                        <?php foreach ($expense_rows as $row): ?>
                                            <tr>
                                                <td><?= e($row['expense_date']) ?></td>
                                                <td><?= e($row['expense_type']) ?></td>
                                                <td><?= e($row['description']) ?></td>
                                                <td><?= e($row['payment_source']) ?></td>
                                                <td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5">No expense details found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-2 mt-24">
                            <div>
                                <p><strong>Prepared By:</strong> <?= e($_SESSION['full_name'] ?? 'User') ?></p>
                                <p class="text-muted">Date: <?= date('Y-m-d') ?></p>
                            </div>
                            <div style="text-align:right;">
                                <p><strong>Checked / Approved By:</strong> ____________________</p>
                                <p class="text-muted">Auditor Signature / Stamp</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>