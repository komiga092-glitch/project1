<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
        $checkSql = "SELECT user_id FROM app_users WHERE email = ? LIMIT 1";
        if ($stmt = $conn->prepare($checkSql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $message = 'This email is already registered.';
                $messageType = 'danger';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertSql = "
                    INSERT INTO app_users (full_name, email, password, status)
                    VALUES (?, ?, ?, 'Active')
                ";

                if ($insertStmt = $conn->prepare($insertSql)) {
                    $insertStmt->bind_param("sss", $full_name, $email, $hashedPassword);

                    if ($insertStmt->execute()) {
                        $message = 'Registration successful. Please login and select your company access.';
                        $messageType = 'success';
                    } else {
                        $message = 'Registration failed. Please try again.';
                        $messageType = 'danger';
                    }
                    $insertStmt->close();
                } else {
                    $message = 'Registration query failed.';
                    $messageType = 'danger';
                }
            }
            $stmt->close();
        } else {
            $message = 'Database error.';
            $messageType = 'danger';
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
                Register a new user in the system. Company role and access will be
                controlled separately through the company_user_access table.
            </p>

            <div class="auth-points">
                <div class="auth-point">✅ app_users exact schema</div>
                <div class="auth-point">✅ active status by default</div>
                <div class="auth-point">✅ secure hashed password</div>
                <div class="auth-point">✅ ready for company assignment</div>
            </div>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-card">
            <h2>Create Account</h2>
            <p>Fill your details to register.</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Password</label>
                        <div style="display:flex; gap:10px;">
                            <input type="password" name="password" id="registerPassword" class="form-control" placeholder="Enter password" required>
                            <button type="button" class="btn btn-light" data-toggle-password="registerPassword">Show</button>
                        </div>
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Confirm Password</label>
                        <div style="display:flex; gap:10px;">
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm password" required>
                            <button type="button" class="btn btn-light" data-toggle-password="confirmPassword">Show</button>
                        </div>
                    </div>

                    <div class="form-group full">
                        <button type="submit" class="btn btn-primary" style="width:100%;">Register Now</button>
                    </div>
                </div>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>