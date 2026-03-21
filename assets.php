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

$pageTitle = 'Assets Management';
$pageDescription = 'Add and manage company assets';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['add_asset'])) {
    $asset_name      = trim($_POST['asset_name'] ?? '');
    $asset_type      = trim($_POST['asset_type'] ?? '');
    $purchase_date   = trim($_POST['purchase_date'] ?? '');
    $cost_value      = (float)($_POST['cost_value'] ?? 0);
    $current_value   = (float)($_POST['current_value'] ?? 0);
    $description     = trim($_POST['description'] ?? '');
    $payment_source  = trim($_POST['payment_source'] ?? 'Cash');
    $bank_name       = trim($_POST['bank_name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');

    if ($asset_name === '' || $purchase_date === '' || $cost_value <= 0) {
        $msg = 'Please fill all required asset fields correctly.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO assets
            (company_id, asset_name, asset_type, purchase_date, cost_value, current_value, description, payment_source, bank_name, account_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "isssddssss",
            $company_id,
            $asset_name,
            $asset_type,
            $purchase_date,
            $cost_value,
            $current_value,
            $description,
            $payment_source,
            $bank_name,
            $account_number
        );

        if ($stmt->execute()) {
            $msg = 'Asset added successfully.';
            $msgType = 'success';
        } else {
            $msg = 'Failed to add asset.';
            $msgType = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $asset_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM assets WHERE company_id = ? AND asset_id = ?");
    $stmt->bind_param("ii", $company_id, $asset_id);
    if ($stmt->execute()) {
        $msg = 'Asset deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$rows = [];
$stmt = $conn->prepare("SELECT * FROM assets WHERE company_id = ? ORDER BY asset_id DESC");
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
                <h1>Assets Management</h1>
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
                <h3>Add Asset</h3>
                <span class="badge badge-primary">Professional Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Asset Name</label>
                            <input type="text" name="asset_name" class="form-control" placeholder="Laptop / Vehicle / Building" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Asset Type</label>
                            <input type="text" name="asset_type" class="form-control" placeholder="Equipment / Furniture / Property">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cost Value</label>
                            <input type="number" step="0.01" name="cost_value" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Current Value</label>
                            <input type="number" step="0.01" name="current_value" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Source</label>
                            <select name="payment_source" class="form-control" onchange="toggleAssetBankFields(this.value)">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank</option>
                            </select>
                        </div>

                        <div class="form-group" id="assetBankNameWrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>

                        <div class="form-group" id="assetAccountNoWrap" style="display:none;">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_number" class="form-control">
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" placeholder="Enter asset details"></textarea>
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="add_asset" class="btn btn-primary">Save Asset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Asset Records</h3>
                <span class="badge badge-success"><?= count($rows) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Asset Name</th>
                                <th>Type</th>
                                <th>Purchase Date</th>
                                <th>Cost Value</th>
                                <th>Current Value</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['asset_id']) ?></td>
                                    <td><?= e($row['asset_name']) ?></td>
                                    <td><?= e($row['asset_type']) ?></td>
                                    <td><?= e($row['purchase_date']) ?></td>
                                    <td>Rs. <?= number_format((float)$row['cost_value'], 2) ?></td>
                                    <td>Rs. <?= number_format((float)$row['current_value'], 2) ?></td>
                                    <td><span class="badge badge-primary"><?= e($row['payment_source']) ?></span></td>
                                    <td>
                                        <a class="btn btn-danger" href="?delete=<?= (int)$row['asset_id'] ?>" onclick="return confirm('Delete this asset record?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8">No assets found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleAssetBankFields(value){
    document.getElementById('assetBankNameWrap').style.display = value === 'Bank' ? 'block' : 'none';
    document.getElementById('assetAccountNoWrap').style.display = value === 'Bank' ? 'block' : 'none';
}
</script>