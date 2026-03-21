<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Change Password';
$pageDescription = 'Update your account password securely';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$msgType = 'success';

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $msg = 'All password fields are required.';
        $msgType = 'danger';
    } elseif (strlen($new_password) < 6) {
        $msg = 'New password must be at least 6 characters.';
        $msgType = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $msg = 'New password and confirm password do not match.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT password FROM app_users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($db_password);
        $stmt->fetch();
        $stmt->close();

        if (!$db_password || !password_verify($current_password, $db_password)) {
            $msg = 'Current password is incorrect.';
            $msgType = 'danger';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE app_users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed, $user_id);

            if ($stmt->execute()) {
                $msg = 'Password changed successfully.';
                $msgType = 'success';
            } else {
                $msg = 'Failed to change password.';
                $msgType = 'danger';
            }
            $stmt->close();
        }
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
                <h1>Change Password</h1>
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

        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <h3>Update Password</h3>
                    <span class="badge badge-primary">Secure Access</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label class="form-label">Current Password</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                                    <button type="button" class="btn btn-light" data-toggle-password="current_password">Show</button>
                                </div>
                            </div>

                            <div class="form-group full">
                                <label class="form-label">New Password</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                                    <button type="button" class="btn btn-light" data-toggle-password="new_password">Show</button>
                                </div>
                            </div>

                            <div class="form-group full">
                                <label class="form-label">Confirm New Password</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                    <button type="button" class="btn btn-light" data-toggle-password="confirm_password">Show</button>
                                </div>
                            </div>

                            <div class="form-group full">
                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Password Rules</h3>
                    <span class="badge badge-warning">Important</span>
                </div>
                <div class="card-body">
                    <div class="grid">
                        <div class="stat-card">
                            <div class="stat-top">
                                <div class="stat-title">Minimum Length</div>
                                <div class="stat-icon">🔐</div>
                            </div>
                            <div class="stat-value">6+</div>
                            <div class="stat-note">Use at least 6 characters</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-top">
                                <div class="stat-title">Recommendation</div>
                                <div class="stat-icon">🛡️</div>
                            </div>
                            <div class="stat-value" style="font-size:20px;">Strong Password</div>
                            <div class="stat-note">Use letters, numbers, and symbols</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-top">
                                <div class="stat-title">Security</div>
                                <div class="stat-icon">✅</div>
                            </div>
                            <div class="stat-value" style="font-size:20px;">Protected</div>
                            <div class="stat-note">Passwords are stored hashed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>