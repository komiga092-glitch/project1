<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'My Companies';
$pageDescription = 'Switch between your assigned companies';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$msgType = 'success';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* Handle company selection */
if (isset($_POST['select_company'])) {
    $selected_company_id = (int)($_POST['company_id'] ?? 0);

    if ($selected_company_id <= 0) {
        $msg = 'Invalid company selection.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("
            SELECT 
                c.company_id,
                c.company_name,
                c.registration_no,
                c.email AS company_email,
                c.phone AS company_phone,
                c.address AS company_address,
                cua.role_in_company,
                cua.access_status
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
        $company = $res->fetch_assoc();
        $stmt->close();

        if ($company) {
            $_SESSION['company_id'] = (int)$company['company_id'];
            $_SESSION['company_name'] = $company['company_name'];
            $_SESSION['company_registration_no'] = $company['registration_no'];
            $_SESSION['company_email'] = $company['company_email'];
            $_SESSION['company_phone'] = $company['company_phone'];
            $_SESSION['company_address'] = $company['company_address'];
            $_SESSION['role'] = ucfirst($company['role_in_company']);

            header("Location: dashboard.php");
            exit;
        } else {
            $msg = 'You do not have active access to the selected company.';
            $msgType = 'danger';
        }
    }
}

/* Load all active companies */
$companies = [];
$stmt = $conn->prepare("
    SELECT 
        c.company_id,
        c.company_name,
        c.registration_no,
        c.email,
        c.phone,
        c.address,
        cua.role_in_company,
        cua.access_status
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
                <h1>My Companies</h1>
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

        <?php if (!empty($companies)): ?>
            <div class="grid grid-2">
                <?php foreach ($companies as $company): ?>
                    <?php
                    $isCurrent = ((int)($_SESSION['company_id'] ?? 0) === (int)$company['company_id']);
                    $roleText = ucfirst($company['role_in_company'] ?? 'User');
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><?= e($company['company_name']) ?></h3>
                            <div class="flex items-center gap-12">
                                <span class="badge badge-primary"><?= e($roleText) ?></span>
                                <?php if ($isCurrent): ?>
                                    <span class="badge badge-success">Current</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="grid" style="gap:10px;">
                                <p><strong>Registration No:</strong> <?= e($company['registration_no'] ?? '-') ?></p>
                                <p><strong>Email:</strong> <?= e($company['email'] ?? '-') ?></p>
                                <p><strong>Phone:</strong> <?= e($company['phone'] ?? '-') ?></p>
                                <p><strong>Address:</strong> <?= e($company['address'] ?? '-') ?></p>
                                <p><strong>Access Status:</strong> <?= e($company['access_status'] ?? '-') ?></p>
                            </div>

                            <form method="POST" class="mt-20">
                                <input type="hidden" name="company_id" value="<?= (int)$company['company_id'] ?>">
                                <button type="submit" name="select_company" class="btn btn-primary">
                                    <?= $isCurrent ? 'Open Current Dashboard' : 'Switch to This Company' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3>No Active Companies</h3>
                    <span class="badge badge-warning">Setup Needed</span>
                </div>
                <div class="card-body">
                    <p style="margin-bottom:16px;">
                        You do not have any active company access yet. Create a company or wait until access is assigned to your account.
                    </p>
                    <a href="add_company.php" class="btn btn-primary">Add Company</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php include 'includes/footer.php'; ?>