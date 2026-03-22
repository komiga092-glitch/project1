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

$pageTitle = 'Liabilities Management';
$pageDescription = 'Add and manage company liability records';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$edit_mode = false;
$edit = [
    'liability_id' => '',
    'liability_name' => '',
    'liability_type' => '',
    'amount' => '',
    'liability_date' => '',
    'description' => '',
    'due_date' => '',
    'payment_source' => 'Cash',
    'bank_name' => '',
    'account_number' => ''
];

/* =========================
   EDIT FETCH
========================= */
if (isset($_GET['edit'])) {
    $liability_id = (int)($_GET['edit'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM liabilities WHERE company_id = ? AND liability_id = ? LIMIT 1");
    $stmt->bind_param("ii", $company_id, $liability_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $edit = $row;
        $edit_mode = true;
    }

    $stmt->close();
}

/* =========================
   ADD / UPDATE LIABILITY
========================= */
if (isset($_POST['save_liability'])) {
    $liability_id   = (int)($_POST['liability_id'] ?? 0);
    $liability_name = trim($_POST['liability_name'] ?? '');
    $liability_type = trim($_POST['liability_type'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $liability_date = trim($_POST['liability_date'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $due_date       = trim($_POST['due_date'] ?? '');
    $payment_source = trim($_POST['payment_source'] ?? 'Cash');
    $bank_name      = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');

    if ($payment_source !== 'Bank') {
        $bank_name = '';
        $account_number = '';
    }

    if ($liability_name === '' || $liability_date === '' || $amount <= 0) {
        $msg = 'Please fill required fields correctly.';
        $msgType = 'danger';
    } else {
        $due_date_db = ($due_date === '') ? null : $due_date;

        if ($liability_id > 0) {
            $sql = "UPDATE liabilities
                    SET liability_name = ?, liability_type = ?, amount = ?, liability_date = ?, description = ?, due_date = ?, payment_source = ?, bank_name = ?, account_number = ?
                    WHERE company_id = ? AND liability_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssdssssssii",
                $liability_name,
                $liability_type,
                $amount,
                $liability_date,
                $description,
                $due_date_db,
                $payment_source,
                $bank_name,
                $account_number,
                $company_id,
                $liability_id
            );

            if ($stmt->execute()) {
                $msg = 'Liability record updated successfully.';
                $msgType = 'success';

                $edit_mode = false;
                $edit = [
                    'liability_id' => '',
                    'liability_name' => '',
                    'liability_type' => '',
                    'amount' => '',
                    'liability_date' => '',
                    'description' => '',
                    'due_date' => '',
                    'payment_source' => 'Cash',
                    'bank_name' => '',
                    'account_number' => ''
                ];
            } else {
                $msg = 'Failed to update liability record.';
                $msgType = 'danger';
            }

            $stmt->close();
        } else {
            $sql = "INSERT INTO liabilities
                    (company_id, liability_name, liability_type, amount, liability_date, description, due_date, payment_source, bank_name, account_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "issdssssss",
                $company_id,
                $liability_name,
                $liability_type,
                $amount,
                $liability_date,
                $description,
                $due_date_db,
                $payment_source,
                $bank_name,
                $account_number
            );

            if ($stmt->execute()) {
                $msg = 'Liability record added successfully.';
                $msgType = 'success';
            } else {
                $msg = 'Failed to add liability record.';
                $msgType = 'danger';
            }

            $stmt->close();
        }
    }
}

/* =========================
   DELETE LIABILITY
========================= */
if (isset($_GET['delete'])) {
    $liability_id = (int)($_GET['delete'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM liabilities WHERE company_id = ? AND liability_id = ?");
    $stmt->bind_param("ii", $company_id, $liability_id);

    if ($stmt->execute()) {
        $msg = 'Liability record deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }

    $stmt->close();
}

/* =========================
   FETCH ALL LIABILITY ROWS
========================= */
$rows = [];
$sql = "SELECT * FROM liabilities WHERE company_id = ? ORDER BY liability_id DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
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
                <h1>Liabilities Management</h1>
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
                <h3><?= $edit_mode ? 'Edit Liability' : 'Add Liability' ?></h3>
                <span class="badge badge-primary">Professional Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="liability_id" value="<?= e($edit['liability_id']) ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Liability Name</label>
                            <input
                                type="text"
                                name="liability_name"
                                class="form-control"
                                placeholder="Loan / Payable / Advance"
                                value="<?= e($edit['liability_name']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Liability Type</label>
                            <input
                                type="text"
                                name="liability_type"
                                class="form-control"
                                placeholder="Short Term / Long Term / Other"
                                value="<?= e($edit['liability_type']) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                name="amount"
                                class="form-control"
                                value="<?= e($edit['amount']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Liability Date</label>
                            <input
                                type="date"
                                name="liability_date"
                                class="form-control"
                                value="<?= e($edit['liability_date']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Due Date</label>
                            <input
                                type="date"
                                name="due_date"
                                class="form-control"
                                value="<?= e($edit['due_date']) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Source</label>
                            <select
                                name="payment_source"
                                class="form-control"
                                id="liabilityPaymentSource"
                                onchange="toggleLiabilityBankFields(this.value)"
                            >
                                <option value="Cash" <?= ($edit['payment_source'] ?? 'Cash') === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Bank" <?= ($edit['payment_source'] ?? '') === 'Bank' ? 'selected' : '' ?>>Bank</option>
                            </select>
                        </div>

                        <div class="form-group" id="liabilityBankNameWrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input
                                type="text"
                                name="bank_name"
                                class="form-control"
                                value="<?= e($edit['bank_name']) ?>"
                            >
                        </div>

                        <div class="form-group" id="liabilityAccountNoWrap" style="display:none;">
                            <label class="form-label">Account Number</label>
                            <input
                                type="text"
                                name="account_number"
                                class="form-control"
                                value="<?= e($edit['account_number']) ?>"
                            >
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Description</label>
                            <textarea
                                name="description"
                                class="form-control"
                                placeholder="Enter liability details"
                            ><?= e($edit['description']) ?></textarea>
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="save_liability" class="btn btn-primary">
                                <?= $edit_mode ? 'Update Liability' : 'Save Liability' ?>
                            </button>

                            <?php if ($edit_mode): ?>
                                <a href="liabilities.php" class="btn btn-light">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Liability Records</h3>
                <span class="badge badge-success"><?= count($rows) ?> Records</span>
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
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= e($row['liability_id'] ?? '') ?></td>
                                    <td><?= e($row['liability_name'] ?? '') ?></td>
                                    <td><?= e($row['liability_type'] ?? '') ?></td>
                                    <td>Rs. <?= number_format((float)($row['amount'] ?? 0), 2) ?></td>
                                    <td><?= e($row['liability_date'] ?? '') ?></td>
                                    <td><?= e($row['due_date'] ?? '') ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?= e($row['payment_source'] ?? 'Cash') ?>
                                        </span>
                                    </td>
                                    <td><?= e($row['description'] ?? '') ?></td>
                                    <td>
                                        <a class="btn btn-light" href="?edit=<?= (int)$row['liability_id'] ?>">Edit</a>
                                        <a class="btn btn-danger" href="?delete=<?= (int)$row['liability_id'] ?>" onclick="return confirm('Delete this liability record?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No liability records found.</td>
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
function toggleLiabilityBankFields(value){
    document.getElementById('liabilityBankNameWrap').style.display = value === 'Bank' ? 'block' : 'none';
    document.getElementById('liabilityAccountNoWrap').style.display = value === 'Bank' ? 'block' : 'none';
}

toggleLiabilityBankFields(document.getElementById('liabilityPaymentSource').value);
</script>