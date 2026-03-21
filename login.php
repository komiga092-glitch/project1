<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['company_id'])) {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = 'Login';
$pageDescription = 'Login to your professional accounting and audit system';

$message = '';
$messageType = '';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$pendingInviteToken = trim($_GET['token'] ?? $_SESSION['pending_invite_token'] ?? '');
if ($pendingInviteToken !== '') {
    $_SESSION['pending_invite_token'] = $pendingInviteToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $message = 'Please enter email and password.';
        $messageType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, full_name, email, password, status, created_at
            FROM app_users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $message = 'Invalid email or password.';
            $messageType = 'danger';
        } elseif (($user['status'] ?? '') !== 'Active') {
            $message = 'Your account is inactive. Please contact administrator.';
            $messageType = 'danger';
        } elseif (!password_verify($password, $user['password'])) {
            $message = 'Invalid email or password.';
            $messageType = 'danger';
        } else {
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['account_status'] = $user['status'];
            $_SESSION['created_at'] = $user['created_at'];

            /* Invite token flow first */
            if (!empty($_SESSION['pending_invite_token'])) {
                $token = $_SESSION['pending_invite_token'];
                header("Location: auditor_accept_invite.php?token=" . urlencode($token));
                exit;
            }

            /* Normal company access flow */
            $stmt = $conn->prepare("
                SELECT 
                    c.company_id,
                    c.company_name,
                    c.registration_no,
                    c.email AS company_email,
                    c.phone AS company_phone,
                    c.address AS company_address,
                    cua.role_in_company
                FROM company_user_access cua
                INNER JOIN companies c ON c.company_id = cua.company_id
                WHERE cua.user_id = ?
                  AND cua.access_status = 'Active'
                ORDER BY c.company_name ASC
            ");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            $res = $stmt->get_result();

            $companies = [];
            while ($row = $res->fetch_assoc()) {
                $companies[] = $row;
            }
            $stmt->close();

            if (count($companies) === 1) {
                $company = $companies[0];

                $_SESSION['company_id'] = (int)$company['company_id'];
                $_SESSION['company_name'] = $company['company_name'];
                $_SESSION['company_registration_no'] = $company['registration_no'];
                $_SESSION['company_email'] = $company['company_email'];
                $_SESSION['company_phone'] = $company['company_phone'];
                $_SESSION['company_address'] = $company['company_address'];
                $_SESSION['role'] = ucfirst($company['role_in_company']);

                header("Location: dashboard.php");
                exit;
            }

            if (count($companies) > 1) {
                header("Location: company_switch.php");
                exit;
            }

            $_SESSION['role'] = 'User';
            header("Location: add_company.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TrustLedger Pro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-left">
        <div class="auth-brand">
            <div class="logo">📘</div>
            <h1>Professional NGO Accounting & Audit System</h1>
            <p>
                Secure login for accountant and auditor workflows with multi-company
                access, invite-token support, and premium maroon responsive interface.
            </p>

            <div class="auth-points">
                <div class="auth-point">✅ app_users table compatible</div>
                <div class="auth-point">✅ auditor invite token flow supported</div>
                <div class="auth-point">✅ one dashboard with role-based view</div>
                <div class="auth-point">✅ phone and laptop responsive design</div>
            </div>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-card">
            <h2>Welcome Back</h2>
            <p>Login to continue to your company workspace.</p>

            <?php if (!empty($_SESSION['pending_invite_token'])): ?>
                <div class="alert alert-warning">
                    Auditor invite detected. Please login with the invited account to continue.
                </div>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Email Address</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter your email"
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
                                id="loginPassword"
                                class="form-control"
                                placeholder="Enter your password"
                                required
                            >
                            <button type="button" class="btn btn-light" data-toggle-password="loginPassword">Show</button>
                        </div>
                    </div>

                    <div class="form-group full">
                        <button type="submit" class="btn btn-primary" style="width:100%;">Login Now</button>
                    </div>
                </div>
            </form>

            <div class="auth-footer">
                Don’t have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>