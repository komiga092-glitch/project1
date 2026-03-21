<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Select Company';
$pageDescription = 'Choose the company you want to access';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$msgType = 'success';

if (isset($_POST['select_company'])) {
    $selected_company_id = (int)($_POST['company_id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT c.company_id, c.company_name, c.registration_no, c.email, c.phone, cua.role_in_company
        FROM company_user_access cua
        INNER JOIN companies c ON c.company_id = cua.company_id
        WHERE cua.user_id = ?
          AND cua.company_id = ?
          AND cua.access_status = 'Active'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $user_id, $selected_company_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $_SESSION['company_id'] = (int)$row['company_id'];
        $_SESSION['company_name'] = $row['company_name'];
        $_SESSION['company_registration_no'] = $row['registration_no'];
        $_SESSION['company_email'] = $row['email'];
        $_SESSION['company_phone'] = $row['phone'];
        $_SESSION['role'] = ucfirst($row['role_in_company']);

        header("Location: dashboard.php");
        exit;
    } else {
        $msg = 'Invalid company access.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$companies = [];
$stmt = $conn->prepare("
    SELECT c.company_id, c.company_name, c.registration_no, c.email, c.phone, cua.role_in_company
    FROM company_user_access cua
    INNER JOIN companies c ON c.company_id = cua.company_id
    WHERE cua.user_id = ?
      AND cua.access_status = 'Active'
    ORDER BY c.company_name ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $companies[] = $row;
}
$stmt->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-area">
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="page-heading">
                <h1>Select Company</h1>
                <p><?= e($pageDescription) ?></p>
            </div>
        </div>

        <div class="topbar-right">
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

        <div class="grid grid-2">
            <?php if ($companies): ?>
                <?php foreach ($companies as $company): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><?= e($company['company_name']) ?></h3>
                            <span class="badge badge-primary"><?= e(ucfirst($company['role_in_company'])) ?></span>
                        </div>
                        <div class="card-body">
                            <p><strong>Registration No:</strong> <?= e($company['registration_no']) ?></p>
                            <p><strong>Email:</strong> <?= e($company['email']) ?></p>
                            <p><strong>Phone:</strong> <?= e($company['phone']) ?></p>

                            <form method="POST" class="mt-20">
                                <input type="hidden" name="company_id" value="<?= (int)$company['company_id'] ?>">
                                <button type="submit" name="select_company" class="btn btn-primary">
                                    Open Company Dashboard
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p>No active company access found for your account.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

<?php include 'includes/footer.php'; ?>