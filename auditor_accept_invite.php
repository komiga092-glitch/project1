<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once 'config/db.php';

$pageTitle = 'Accept Auditor Invite';
$pageDescription = 'Validate and accept auditor access invitation';

$msg = '';
$msgType = 'danger';

$token = trim($_GET['token'] ?? $_POST['token'] ?? $_SESSION['pending_invite_token'] ?? '');

if ($token === '') {
    $msg = 'Invalid invite link. Missing token.';
}

$invite = null;

if ($token !== '') {
    $stmt = $conn->prepare("
        SELECT 
            invite_id,
            company_id,
            company_name,
            auditor_name,
            auditor_email,
            invite_token,
            status,
            expires_at,
            invited_by,
            accepted_by,
            accepted_at,
            created_at
        FROM auditor_invites
        WHERE invite_token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $invite = $res->fetch_assoc();
    $stmt->close();

    if (!$invite) {
        $msg = 'Invite not found or invalid.';
        $msgType = 'danger';
    } elseif (($invite['status'] ?? '') === 'Cancelled') {
        $msg = 'This invite has been cancelled.';
        $msgType = 'danger';
    } elseif (($invite['status'] ?? '') === 'Accepted') {
        $msg = 'This invite has already been accepted.';
        $msgType = 'warning';
    } elseif (!empty($invite['expires_at']) && strtotime($invite['expires_at']) < time()) {
        $expireStmt = $conn->prepare("
            UPDATE auditor_invites
            SET status = 'Expired'
            WHERE invite_id = ?
              AND status = 'Pending'
        ");
        $expireStmt->bind_param("i", $invite['invite_id']);
        $expireStmt->execute();
        $expireStmt->close();

        $invite['status'] = 'Expired';
        $msg = 'This invite link has expired.';
        $msgType = 'danger';
    } elseif (($invite['status'] ?? '') !== 'Pending') {
        $msg = 'This invite is not active.';
        $msgType = 'danger';
    }
}

/* not logged in -> login page */
if ($invite && ($invite['status'] ?? '') === 'Pending' && !isset($_SESSION['user_id'])) {
    $_SESSION['pending_invite_token'] = $token;
    header("Location: login.php?token=" . urlencode($token));
    exit;
}

/* logged in + valid pending invite */
if ($invite && ($invite['status'] ?? '') === 'Pending' && isset($_SESSION['user_id'])) {
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $user_email = trim($_SESSION['email'] ?? '');

    /* wrong logged-in account -> auto logout and force login with invited email */
    if ($user_email !== '' && strcasecmp($user_email, (string)$invite['auditor_email']) !== 0) {
        $savedToken = $token;

        session_unset();
        session_destroy();

        session_start();
        $_SESSION['pending_invite_token'] = $savedToken;

        header("Location: login.php?token=" . urlencode($savedToken));
        exit;
    }

    $company_id = (int)$invite['company_id'];

    /* check or create auditor company access */
    $existing_access_id = 0;
    $check = $conn->prepare("
        SELECT access_id
        FROM company_user_access
        WHERE company_id = ?
          AND user_id = ?
          AND role_in_company = 'auditor'
        LIMIT 1
    ");
    $check->bind_param("ii", $company_id, $user_id);
    $check->execute();
    $check->bind_result($existing_access_id);
    $check->fetch();
    $check->close();

    if ((int)$existing_access_id > 0) {
        $activate = $conn->prepare("
            UPDATE company_user_access
            SET access_status = 'Active'
            WHERE access_id = ?
        ");
        $activate->bind_param("i", $existing_access_id);
        $activate->execute();
        $activate->close();
    } else {
        $insert = $conn->prepare("
            INSERT INTO company_user_access
            (company_id, user_id, role_in_company, access_status)
            VALUES (?, ?, 'auditor', 'Active')
        ");
        $insert->bind_param("ii", $company_id, $user_id);

        if (!$insert->execute()) {
            $msg = 'Failed to assign auditor access for this company.';
            $msgType = 'danger';
        }
        $insert->close();
    }

    if ($msg === '') {
        $update = $conn->prepare("
            UPDATE auditor_invites
            SET status = 'Accepted',
                accepted_by = ?,
                accepted_at = NOW()
            WHERE invite_id = ?
              AND status = 'Pending'
        ");
        $update->bind_param("ii", $user_id, $invite['invite_id']);
        $update->execute();
        $update->close();

        /* load company details */
        $companyStmt = $conn->prepare("
            SELECT company_id, company_name, registration_no, email, phone, address
            FROM companies
            WHERE company_id = ?
            LIMIT 1
        ");
        $companyStmt->bind_param("i", $company_id);
        $companyStmt->execute();
        $companyRes = $companyStmt->get_result();
        $company = $companyRes->fetch_assoc();
        $companyStmt->close();

        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_name'] = $_SESSION['full_name'] ?? '';
        $_SESSION['email'] = $user_email;
        $_SESSION['company_id'] = $company_id;
        $_SESSION['company_name'] = $company['company_name'] ?? ($invite['company_name'] ?? '');
        $_SESSION['company_registration_no'] = $company['registration_no'] ?? '';
        $_SESSION['company_email'] = $company['email'] ?? '';
        $_SESSION['company_phone'] = $company['phone'] ?? '';
        $_SESSION['company_address'] = $company['address'] ?? '';
        $_SESSION['role'] = 'Auditor';

        unset($_SESSION['pending_invite_token']);

        header("Location: dashboard.php");
        exit;
    }
}

include 'includes/header.php';
?>

<div class="app-shell">
    <div class="main-area" style="margin-left:0; width:100%;">
        <div class="topbar">
            <div class="topbar-left">
                <div class="page-heading">
                    <h1>Auditor Invite</h1>
                    <p><?= e($pageDescription) ?></p>
                </div>
            </div>

            <div class="topbar-right">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="role-pill"><?= e($_SESSION['role'] ?? 'User') ?></div>
                    <div class="user-chip">
                        <div class="avatar"><?= e(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))) ?></div>
                        <div class="meta">
                            <strong><?= e($_SESSION['full_name'] ?? 'User') ?></strong>
                            <span><?= e($_SESSION['email'] ?? '') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <?php if ($msg !== ''): ?>
                <div class="alert alert-<?= e($msgType) ?>"><?= e($msg) ?></div>
            <?php endif; ?>

            <?php if ($invite): ?>
                <?php
                $status = $invite['status'] ?? 'Unknown';
                $badgeClass = 'badge-primary';
                if ($status === 'Accepted') $badgeClass = 'badge-success';
                if ($status === 'Pending') $badgeClass = 'badge-warning';
                if ($status === 'Cancelled' || $status === 'Expired') $badgeClass = 'badge-danger';
                ?>
                <div class="grid grid-2">
                    <div class="card">
                        <div class="card-header">
                            <h3>Invite Details</h3>
                            <span class="badge <?= $badgeClass ?>"><?= e($status) ?></span>
                        </div>
                        <div class="card-body">
                            <p><strong>Auditor Name:</strong> <?= e($invite['auditor_name'] ?? '-') ?></p>
                            <p><strong>Auditor Email:</strong> <?= e($invite['auditor_email'] ?? '-') ?></p>
                            <p><strong>Invited By:</strong> <?= e($invite['invited_by'] ?? '-') ?></p>
                            <p><strong>Created At:</strong> <?= e($invite['created_at'] ?? '-') ?></p>
                            <p><strong>Expires At:</strong> <?= e($invite['expires_at'] ?? '-') ?></p>
                            <p><strong>Accepted By:</strong> <?= e($invite['accepted_by'] ?? '-') ?></p>
                            <p><strong>Accepted At:</strong> <?= e($invite['accepted_at'] ?? '-') ?></p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Company Details</h3>
                            <span class="badge badge-primary">Audit Access</span>
                        </div>
                        <div class="card-body">
                            <p><strong>Company Name:</strong> <?= e($invite['company_name'] ?? '-') ?></p>
                            <p><strong>Access Role:</strong> Auditor</p>
                            <p><strong>Invite Token:</strong></p>
                            <input type="text" class="form-control" value="<?= e($invite['invite_token'] ?? '') ?>" readonly>
                        </div>
                    </div>
                </div>

                <?php if (($invite['status'] ?? '') === 'Pending' && !isset($_SESSION['user_id'])): ?>
                    <div class="card mt-24">
                        <div class="card-header">
                            <h3>Login Required</h3>
                            <span class="badge badge-warning">Next Step</span>
                        </div>
                        <div class="card-body">
                            <p style="margin-bottom:16px;">
                                To accept this auditor invite, login or register with the invited auditor email.
                            </p>
                            <a href="login.php?token=<?= urlencode($token) ?>" class="btn btn-primary">Login</a>
                            <a href="register.php?token=<?= urlencode($token) ?>" class="btn btn-outline">Register</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (($invite['status'] ?? '') === 'Accepted'): ?>
                    <div class="card mt-24">
                        <div class="card-header">
                            <h3>Invite Already Accepted</h3>
                            <span class="badge badge-success">Completed</span>
                        </div>
                        <div class="card-body">
                            <p style="margin-bottom:16px;">
                                This auditor invite has already been accepted.
                            </p>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="dashboard.php" class="btn btn-primary">Open Dashboard</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary">Login</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array(($invite['status'] ?? ''), ['Cancelled', 'Expired'])): ?>
                    <div class="card mt-24">
                        <div class="card-header">
                            <h3>Invite Not Usable</h3>
                            <span class="badge badge-danger">Closed</span>
                        </div>
                        <div class="card-body">
                            <p>
                                This invite can no longer be used. Please ask the accountant to send a new invite.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p>Invite information could not be loaded.</p>
                        <a href="login.php" class="btn btn-primary mt-16">Go to Login</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>