<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = 'Add Company';
$pageDescription = 'Create a company and assign it to your account';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $registration_no = trim($_POST['registration_no'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role_in_company = strtolower(trim($_POST['role_in_company'] ?? 'accountant'));

    if ($company_name === '' || $registration_no === '') {
        $msg = 'Company name and registration number are required.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO companies (company_name, registration_no, email, phone, address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $company_name, $registration_no, $email, $phone, $address);

        if ($stmt->execute()) {
            $company_id = $stmt->insert_id;
            $stmt->close();

            $stmt2 = $conn->prepare("
                INSERT INTO company_user_access (company_id, user_id, role_in_company, access_status)
                VALUES (?, ?, ?, 'Active')
            ");
            $stmt2->bind_param("iis", $company_id, $user_id, $role_in_company);

            if ($stmt2->execute()) {
                $_SESSION['company_id'] = $company_id;
                $_SESSION['company_name'] = $company_name;
                $_SESSION['company_registration_no'] = $registration_no;
                $_SESSION['company_email'] = $email;
                $_SESSION['company_phone'] = $phone;
                $_SESSION['role'] = ucfirst($role_in_company);

                header("Location: dashboard.php");
                exit;
            } else {
                $msg = 'Company created, but access mapping failed.';
                $msgType = 'danger';
            }
            $stmt2->close();
        } else {
            $msg = 'Failed to create company.';
            $msgType = 'danger';
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
                <h1>Add Company</h1>
                <p><?= e($pageDescription) ?></p>
            </div>
        </div>
    </div>

    <div class="content">
        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= e($msgType) ?>"><?= e($msg) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Create Company</h3>
                <span class="badge badge-primary">Professional Setup</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Registration No</label>
                            <input type="text" name="registration_no" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Your Role in Company</label>
                            <select name="role_in_company" class="form-control" required>
                                <option value="accountant">Accountant</option>
                                <option value="auditor">Auditor</option>
                                <option value="organization">Organization</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <button type="submit" class="btn btn-primary">Create Company</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>