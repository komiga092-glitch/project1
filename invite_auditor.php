<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));
$user_id = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

if (!in_array($role, ['accountant', 'organization'])) {
    header("Location: dashboard.php");
    exit;
}

if ($company_id <= 0) {
    header("Location: company_switch.php");
    exit;
}

$pageTitle = 'Invite Auditor';
$pageDescription = 'Invite an auditor to access this company audit dashboard';

$msg = '';
$msgType = 'success';
$generatedInviteLink = '';

$stmt = $conn->prepare("
    SELECT company_id, company_name, registration_no, email, phone, address
    FROM companies
    WHERE company_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
$company = $res->fetch_assoc();
$stmt->close();

if (!$company) {
    header("Location: dashboard.php");
    exit;
}

$company_name = $company['company_name'] ?? '';

if (isset($_POST['create_invite'])) {
    $auditor_name  = trim($_POST['auditor_name'] ?? '');
    $auditor_email = trim($_POST['auditor_email'] ?? '');
    $expires_days  = (int)($_POST['expires_days'] ?? 7);

    if ($auditor_name === '' || $auditor_email === '') {
        $msg = 'Please enter auditor name and email.';
        $msgType = 'danger';
    } elseif (!filter_var($auditor_email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid auditor email address.';
        $msgType = 'danger';
    } elseif ($expires_days < 1 || $expires_days > 30) {
        $msg = 'Expiry days must be between 1 and 30.';
        $msgType = 'danger';
    } else {
        $check = $conn->prepare("
            SELECT invite_id
            FROM auditor_invites
            WHERE company_id = ?
              AND auditor_email = ?
              AND status = 'Pending'
            LIMIT 1
        ");
        $check->bind_param("is", $company_id, $auditor_email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = 'A pending invite already exists for this auditor email.';
            $msgType = 'warning';
        } else {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . $expires_days . ' days'));

            $stmt = $conn->prepare("
                INSERT INTO auditor_invites
                (company_id, company_name, auditor_name, auditor_email, invite_token, status, expires_at, invited_by)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)
            ");
            $stmt->bind_param(
                "isssssi",
                $company_id,
                $company_name,
                $auditor_name,
                $auditor_email,
                $token,
                $expires_at,
                $user_id
            );

            if ($stmt->execute()) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $generatedInviteLink = $scheme . '://' . $host . $basePath . '/auditor_accept_invite.php?token=' . urlencode($token);

                $msg = 'Auditor invite created successfully.';
                $msgType = 'success';
            } else {
                $msg = 'Failed to create auditor invite.';
                $msgType = 'danger';
            }
            $stmt->close();
        }
        $check->close();
    }
}

if (isset($_GET['cancel'])) {
    $invite_id = (int)($_GET['cancel'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE auditor_invites
        SET status = 'Cancelled'
        WHERE invite_id = ?
          AND company_id = ?
          AND invited_by = ?
          AND status = 'Pending'
    ");
    $stmt->bind_param("iii", $invite_id, $company_id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msg = 'Invite cancelled successfully.';
        $msgType = 'success';
    } else {
        $msg = 'Unable to cancel invite.';
        $msgType = 'danger';
    }
    $stmt->close();
}

$invites = [];
$stmt = $conn->prepare("
    SELECT invite_id, company_name, auditor_name, auditor_email, invite_token, status, expires_at, accepted_by, accepted_at, created_at
    FROM auditor_invites
    WHERE company_id = ?
    ORDER BY invite_id DESC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $invites[] = $row;
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
                <h1>Invite Auditor</h1>
                <p><?= e($pageDescription) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="company-pill"><?= e($_SESSION['company_name'] ?? 'Company') ?></div>
            <div class="role-pill"><?= e($_SESSION['role'] ?? 'Accountant') ?></div>
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
            <div class="card">
                <div class="card-header">
                    <h3>Create Auditor Invite</h3>
                    <span class="badge badge-primary">Secure Access Link</span>
                </div>
                <div class="card-body">
                    <form method="POST" autocomplete="off">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Auditor Name</label>
                                <input type="text" name="auditor_name" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Auditor Email</label>
                                <input type="email" name="auditor_email" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Invite Expiry (Days)</label>
                                <select name="expires_days" class="form-control" required>
                                    <option value="3">3 Days</option>
                                    <option value="5">5 Days</option>
                                    <option value="7" selected>7 Days</option>
                                    <option value="10">10 Days</option>
                                    <option value="15">15 Days</option>
                                    <option value="30">30 Days</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Role Access</label>
                                <input type="text" class="form-control" value="Auditor" readonly>
                            </div>

                            <div class="form-group full">
                                <button type="submit" name="create_invite" class="btn btn-primary">Generate Invite Link</button>
                            </div>
                        </div>
                    </form>

                    <?php if ($generatedInviteLink !== ''): ?>
                        <div class="mt-24">
                            <label class="form-label">Generated Invite Link</label>
                            <input type="text" id="inviteLinkBox" class="form-control" value="<?= e($generatedInviteLink) ?>" readonly>
                            <div class="mt-16">
                                <button type="button" class="btn btn-primary" onclick="copyInviteLink()">Copy Link</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Company</h3>
                    <span class="badge badge-warning">Current</span>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?= e($company['company_name'] ?? '-') ?></p>
                    <p><strong>Registration No:</strong> <?= e($company['registration_no'] ?? '-') ?></p>
                    <p><strong>Email:</strong> <?= e($company['email'] ?? '-') ?></p>
                    <p><strong>Phone:</strong> <?= e($company['phone'] ?? '-') ?></p>
                    <p><strong>Address:</strong> <?= e($company['address'] ?? '-') ?></p>
                </div>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header">
                <h3>Auditor Invite History</h3>
                <span class="badge badge-primary"><?= count($invites) ?> Invites</span>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Auditor Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Expires At</th>
                                <th>Accepted At</th>
                                <th>Created At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invites): ?>
                                <?php foreach ($invites as $invite): ?>
                                    <?php
                                    $status = $invite['status'] ?? 'Pending';
                                    $badgeClass = 'badge-primary';
                                    if ($status === 'Accepted') $badgeClass = 'badge-success';
                                    if ($status === 'Cancelled' || $status === 'Expired') $badgeClass = 'badge-danger';
                                    if ($status === 'Pending') $badgeClass = 'badge-warning';
                                    ?>
                                    <tr>
                                        <td><?= e($invite['invite_id']) ?></td>
                                        <td><?= e($invite['auditor_name']) ?></td>
                                        <td><?= e($invite['auditor_email']) ?></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= e($status) ?></span></td>
                                        <td><?= e($invite['expires_at'] ?? '-') ?></td>
                                        <td><?= e($invite['accepted_at'] ?? '-') ?></td>
                                        <td><?= e($invite['created_at'] ?? '-') ?></td>
                                        <td>
                                            <?php if (($invite['status'] ?? '') === 'Pending'): ?>
                                                <a href="?cancel=<?= (int)$invite['invite_id'] ?>" class="btn btn-danger" onclick="return confirm('Cancel this invite?')">Cancel</a>
                                            <?php else: ?>
                                                <span class="text-muted">No action</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">No auditor invites found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

<script>
function copyInviteLink() {
    const box = document.getElementById('inviteLinkBox');
    if (!box) return;
    box.select();
    box.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(box.value).then(function () {
        alert('Invite link copied successfully.');
    }).catch(function () {
        document.execCommand('copy');
        alert('Invite link copied successfully.');
    });
}
</script>