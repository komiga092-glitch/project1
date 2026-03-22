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

$pageTitle = 'Income Management';
$pageDescription = 'Add and manage company income records';

$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$edit_mode = false;
$edit = [
    'income_id' => '',
    'income_type' => '',
    'amount' => '',
    'income_date' => '',
    'description' => '',
    'payment_source' => 'Cash',
    'bank_name' => '',
    'account_number' => ''
];

/* =========================
   EDIT FETCH
========================= */
if (isset($_GET['edit'])) {
    $income_id = (int)($_GET['edit'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM income WHERE company_id = ? AND income_id = ? LIMIT 1");
    $stmt->bind_param("ii", $company_id, $income_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $edit = $row;
        $edit_mode = true;
    }

    $stmt->close();
}

/* =========================
   ADD / UPDATE INCOME
========================= */
if (isset($_POST['save_income'])) {
    $income_id      = (int)($_POST['income_id'] ?? 0);
    $income_type    = trim($_POST['income_type'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $income_date    = trim($_POST['income_date'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $payment_source = trim($_POST['payment_source'] ?? 'Cash');
    $bank_name      = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');

    if ($payment_source !== 'Bank') {
        $bank_name = '';
        $account_number = '';
    }

    if ($income_type === '' || $amount <= 0 || $income_date === '') {
        $msg = 'Please fill required fields correctly.';
        $msgType = 'danger';
    } else {
        if ($income_id > 0) {
            $sql = "UPDATE income
                    SET income_type = ?, amount = ?, income_date = ?, description = ?, payment_source = ?, bank_name = ?, account_number = ?
                    WHERE company_id = ? AND income_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sdsssssii",
                $income_type,
                $amount,
                $income_date,
                $description,
                $payment_source,
                $bank_name,
                $account_number,
                $company_id,
                $income_id
            );

            if ($stmt->execute()) {
                $msg = 'Income record updated successfully.';
                $msgType = 'success';

                $edit_mode = false;
                $edit = [
                    'income_id' => '',
                    'income_type' => '',
                    'amount' => '',
                    'income_date' => '',
                    'description' => '',
                    'payment_source' => 'Cash',
                    'bank_name' => '',
                    'account_number' => ''
                ];
            } else {
                $msg = 'Failed to update income record.';
                $msgType = 'danger';
            }
            $stmt->close();
        } else {
            $sql = "INSERT INTO income
                    (company_id, income_type, amount, income_date, description, payment_source, bank_name, account_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isdsssss",
                $company_id,
                $income_type,
                $amount,
                $income_date,
                $description,
                $payment_source,
                $bank_name,
                $account_number
            );

            if ($stmt->execute()) {
                $msg = 'Income record added successfully.';
                $msgType = 'success';
            } else {
                $msg = 'Failed to add income. Check income table column names.';
                $msgType = 'danger';
            }
            $stmt->close();
        }
    }
}

/* =========================
   DELETE INCOME
========================= */
if (isset($_GET['delete'])) {
    $income_id = (int)($_GET['delete'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM income WHERE company_id = ? AND income_id = ?");
    $stmt->bind_param("ii", $company_id, $income_id);

    if ($stmt->execute()) {
        $msg = 'Income record deleted successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Delete failed.';
        $msgType = 'danger';
    }

    $stmt->close();
}

/* =========================
   FETCH ALL INCOME ROWS
========================= */
$rows = [];
$sql = "SELECT * FROM income WHERE company_id = ? ORDER BY income_id DESC";

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
                <h1>Income Management</h1>
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
                <h3><?= $edit_mode ? 'Edit Income' : 'Add Income' ?></h3>
                <span class="badge badge-primary">Professional Entry</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="income_id" value="<?= e($edit['income_id']) ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Income Type</label>
                            <input
                                type="text"
                                name="income_type"
                                class="form-control"
                                placeholder="Donations / Grants / Membership Fees"
                                value="<?= e($edit['income_type']) ?>"
                                required
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
                            <label class="form-label">Income Date</label>
                            <input
                                type="date"
                                name="income_date"
                                class="form-control"
                                value="<?= e($edit['income_date']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Source</label>
                            <select
                                name="payment_source"
                                class="form-control"
                                id="incomePaymentSource"
                                onchange="toggleIncomeBankFields(this.value)"
                            >
                                <option value="Cash" <?= ($edit['payment_source'] ?? 'Cash') === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Bank" <?= ($edit['payment_source'] ?? '') === 'Bank' ? 'selected' : '' ?>>Bank</option>
                            </select>
                        </div>

                        <div class="form-group" id="incomeBankNameWrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input
                                type="text"
                                name="bank_name"
                                class="form-control"
                                value="<?= e($edit['bank_name']) ?>"
                            >
                        </div>

                        <div class="form-group" id="incomeAccountNoWrap" style="display:none;">
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
                                placeholder="Enter remarks"
                            ><?= e($edit['description']) ?></textarea>
                        </div>

                        <div class="form-group full">
                            <button type="submit" name="save_income" class="btn btn-primary">
                                <?= $edit_mode ? 'Update Income' : 'Save Income' ?>
                            </button>

                            <?php if ($edit_mode): ?>
                                <a href="income.php" class="btn btn-light">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Income Records</h3>
                <span class="badge badge-success"><?= count($rows) ?> Records</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
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
                                    <td><?= e($row['income_id'] ?? '') ?></td>
                                    <td><?= e($row['income_type'] ?? '') ?></td>
                                    <td>Rs. <?= number_format((float)($row['amount'] ?? 0), 2) ?></td>
                                    <td><?= e($row['income_date'] ?? '') ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?= e($row['payment_source'] ?? 'Cash') ?>
                                        </span>
                                    </td>
                                    <td><?= e($row['description'] ?? '') ?></td>
                                    <td>
                                        <a class="btn btn-light" href="?edit=<?= (int)$row['income_id'] ?>">Edit</a>
                                        <a class="btn btn-danger" href="?delete=<?= (int)$row['income_id'] ?>" onclick="return confirm('Delete this income record?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No income records found.</td>
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
function toggleIncomeBankFields(value){
    document.getElementById('incomeBankNameWrap').style.display = value === 'Bank' ? 'block' : 'none';
    document.getElementById('incomeAccountNoWrap').style.display = value === 'Bank' ? 'block' : 'none';
}

toggleIncomeBankFields(document.getElementById('incomePaymentSource').value);
</script>