<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['company_id'])) {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = 'Register';
$pageDescription = 'Create your professional accounting and audit system account';

$message = '';
$messageType = '';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* Preserve invite token flow */
$pendingInviteToken = trim($_GET['token'] ?? $_SESSION['pending_invite_token'] ?? '');
if ($pendingInviteToken !== '') {
    $_SESSION['pending_invite_token'] = $pendingInviteToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $message = 'Please fill all required fields.';
        $messageType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = 'Password and confirm password do not match.';
        $messageType = 'danger';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'danger';
    } else {
        $checkStmt = $conn->prepare("
            SELECT user_id
            FROM app_users
            WHERE email = ?
            LIMIT 1
        ");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $message = 'This email is already registered.';
            $messageType = 'danger';
            $checkStmt->close();
        } else {
            $checkStmt->close();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $conn->prepare("
                INSERT INTO app_users (full_name, email, password, status)
                VALUES (?, ?, ?, 'Active')
            ");
            $insertStmt->bind_param("sss", $full_name, $email, $hashedPassword);

            if ($insertStmt->execute()) {
                $new_user_id = (int)$insertStmt->insert_id;
                $insertStmt->close();

                /* Auto login after successful registration */
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['account_status'] = 'Active';
                $_SESSION['role'] = 'User';

                /* If this registration came from auditor invite flow */
                if (!empty($_SESSION['pending_invite_token'])) {
                    $token = $_SESSION['pending_invite_token'];
                    header("Location: auditor_accept_invite.php?token=" . urlencode($token));
                    exit;
                }

                /* Normal register flow */
                header("Location: add_company.php");
                exit;
            } else {
                $message = 'Registration failed. Please try again.';
                $messageType = 'danger';
                $insertStmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | TrustLedger Pro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-left">
        <div class="auth-brand">
            <div class="logo">🏢</div>
            <h1>Create Your Account</h1>
            <p>
                Register a secure account for accountant or auditor workflows with
                multi-company support and invite-token onboarding.
            </p>

            <div class="auth-points">
                <div class="auth-point">✅ app_users exact schema compatible</div>
                <div class="auth-point">✅ invite-token flow supported</div>
                <div class="auth-point">✅ password securely hashed</div>
                <div class="auth-point">✅ ready for company add or invite acceptance</div>
            </div>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-card">
            <h2>Create Account</h2>
            <p>Fill the details below to create your account.</p>

            <?php if (!empty($_SESSION['pending_invite_token'])): ?>
                <div class="alert alert-warning">
                    Auditor invite detected. Register with the invited email to continue.
                </div>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Full Name</label>
                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            placeholder="Enter full name"
                            value="<?= e($_POST['full_name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Email Address</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter email address"
                            value="<?= e($_POST['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Password</label>
                        <div style="display:flex; gap:10px;">
                            <input
                                type="password"
                                name="password"
                                id="registerPassword"
                                class="form-control"
                                placeholder="Enter password"
                                required
                            >
                            <button type="button" class="btn btn-light" data-toggle-password="registerPassword">Show</button>
                        </div>
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Confirm Password</label>
                        <div style="display:flex; gap:10px;">
                            <input
                                type="password"
                                name="confirm_password"
                                id="confirmPassword"
                                class="form-control"
                                placeholder="Confirm password"
                                required
                            >
                            <button type="button" class="btn btn-light" data-toggle-password="confirmPassword">Show</button>
                        </div>
                    </div>

                    <div class="form-group full">
                        <button type="submit" class="btn btn-primary" style="width:100%;">Register Now</button>
                    </div>
                </div>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php<?= !empty($_SESSION['pending_invite_token']) ? '?token=' . urlencode($_SESSION['pending_invite_token']) : '' ?>">Login here</a>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>