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

$pageTitle = 'Assets & Liabilities Report';
$pageDescription = 'Professional statement of assets and liabilities';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$from_date = trim($_GET['from_date'] ?? date('Y-01-01'));
$to_date   = trim($_GET['to_date'] ?? date('Y-m-d'));

$company = null;
$stmt = $conn->prepare("SELECT company_id, company_name, registration_no, email, phone FROM companies WHERE company_id = ? LIMIT 1");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
$company = $res->fetch_assoc();
$stmt->close();

$asset_rows = [];
$stmt = $conn->prepare("
    SELECT asset_id, asset_name, asset_type, purchase_date, cost_value, current_value, description, payment_source, bank_name, account_number
    FROM assets
    WHERE company_id = ? AND purchase_date BETWEEN ? AND ?
    ORDER BY purchase_date ASC, asset_id ASC
");
$stmt->bind_param("iss", $company_id, $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $asset_rows[] = $row;
$stmt->close();

$liability_rows = [];
$stmt = $conn->prepare("
    SELECT liability_id, liability_name, liability_type, amount, liability_date, due_date, description, payment_source, bank_name, account_number
    FROM liabilities
    WHERE company_id = ? AND liability_date BETWEEN ? AND ?
    ORDER BY liability_date ASC, liability_id ASC
");
$stmt->bind_param("iss", $company_id, $from_date, $to_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $liability_rows[] = $row;
$stmt->close();

$total_asset_cost = 0;
$total_asset_current = 0;
$assets_by_type = [];
foreach ($asset_rows as $row) {
    $cost = (float)$row['cost_value'];
    $current = (float)$row['current_value'];
    $type = trim($row['asset_type'] ?? '') ?: 'Other Asset';
    $total_asset_cost += $cost;
    $total_asset_current += $current;
    if (!isset($assets_by_type[$type])) $assets_by_type[$type] = 0;
    $assets_by_type[$type] += $current > 0 ? $current : $cost;
}

$total_liabilities = 0;
$liabilities_by_type = [];
foreach ($liability_rows as $row) {
    $amount = (float)$row['amount'];
    $type = trim($row['liability_type'] ?? '') ?: 'Other Liability';
    $total_liabilities += $amount;
    if (!isset($liabilities_by_type[$type])) $liabilities_by_type[$type] = 0;
    $liabilities_by_type[$type] += $amount;
}

$net_assets = $total_asset_current - $total_liabilities;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-area">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="page-heading">
                <h1>Assets & Liabilities Report</h1>
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
                <button onclick="window.print()" class="btn btn-primary">Print Report</button>
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

        <div class="card mt-24">
            <div class="card-body">
                <div style="text-align:center; margin-bottom:24px;">
                    <h2 style="color:var(--primary-dark); margin-bottom:8px;">Assets & Liabilities Statement</h2>
                    <h3 style="margin-bottom:6px;"><?= e($company['company_name'] ?? 'Company') ?></h3>
                    <div class="text-muted">Registration No: <?= e($company['registration_no'] ?? '-') ?></div>
                    <div class="text-muted">Period: <?= e($from_date) ?> to <?= e($to_date) ?></div>
                </div>

                <div class="grid grid-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-title">Total Asset Value</div>
                            <div class="stat-icon">📦</div>
                        </div>
                        <div class="stat-value">Rs. <?= number_format($total_asset_current, 2) ?></div>
                        <div class="stat-note">Current value of company assets</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-title">Total Liabilities</div>
                            <div class="stat-icon">📉</div>
                        </div>
                        <div class="stat-value">Rs. <?= number_format($total_liabilities, 2) ?></div>
                        <div class="stat-note">Outstanding liabilities</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-title">Net Assets</div>
                            <div class="stat-icon">📊</div>
                        </div>
                        <div class="stat-value">Rs. <?= number_format($net_assets, 2) ?></div>
                        <div class="stat-note">Assets minus liabilities</div>
                    </div>
                </div>

                <div class="grid grid-2 mt-24">
                    <div class="card">
                        <div class="card-header">
                            <h3>Asset Summary</h3>
                            <span class="badge badge-success"><?= count($asset_rows) ?> Entries</span>
                        </div>
                        <div class="card-body">
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Asset Type</th>
                                            <th>Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($assets_by_type): ?>
                                            <?php foreach ($assets_by_type as $type => $amount): ?>
                                                <tr>
                                                    <td><?= e($type) ?></td>
                                                    <td>Rs. <?= number_format($amount, 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <th>Total Assets</th>
                                                <th>Rs. <?= number_format($total_asset_current, 2) ?></th>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="2">No asset records found for this period.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Liability Summary</h3>
                            <span class="badge badge-warning"><?= count($liability_rows) ?> Entries</span>
                        </div>
                        <div class="card-body">
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Liability Type</th>
                                            <th>Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($liabilities_by_type): ?>
                                            <?php foreach ($liabilities_by_type as $type => $amount): ?>
                                                <tr>
                                                    <td><?= e($type) ?></td>
                                                    <td>Rs. <?= number_format($amount, 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <th>Total Liabilities</th>
                                                <th>Rs. <?= number_format($total_liabilities, 2) ?></th>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="2">No liability records found for this period.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-24">
                    <div class="card-header">
                        <h3>Detailed Statement</h3>
                        <span class="badge badge-primary">Professional View</span>
                    </div>
                    <div class="card-body">
                        <h4 style="margin-bottom:12px; color:var(--primary-dark);">Asset Details</h4>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Asset Name</th>
                                        <th>Type</th>
                                        <th>Cost Value</th>
                                        <th>Current Value</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($asset_rows): ?>
                                        <?php foreach ($asset_rows as $row): ?>
                                            <tr>
                                                <td><?= e($row['purchase_date']) ?></td>
                                                <td><?= e($row['asset_name']) ?></td>
                                                <td><?= e($row['asset_type']) ?></td>
                                                <td>Rs. <?= number_format((float)$row['cost_value'], 2) ?></td>
                                                <td>Rs. <?= number_format((float)$row['current_value'], 2) ?></td>
                                                <td><?= e($row['payment_source']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6">No asset details found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <h4 style="margin:24px 0 12px; color:var(--primary-dark);">Liability Details</h4>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Liability Name</th>
                                        <th>Type</th>
                                        <th>Due Date</th>
                                        <th>Payment</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($liability_rows): ?>
                                        <?php foreach ($liability_rows as $row): ?>
                                            <tr>
                                                <td><?= e($row['liability_date']) ?></td>
                                                <td><?= e($row['liability_name']) ?></td>
                                                <td><?= e($row['liability_type']) ?></td>
                                                <td><?= e($row['due_date']) ?></td>
                                                <td><?= e($row['payment_source']) ?></td>
                                                <td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6">No liability details found.</td></tr>
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