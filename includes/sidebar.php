<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$role = strtolower(trim($_SESSION['role'] ?? ''));

function isActive($fileName){
    global $currentPage;
    return $currentPage === $fileName ? 'active' : '';
}
?>

<div class="overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-badge">📊</div>
        <h2>TrustLedger Pro</h2>
        <p>Maroon Professional Finance Suite</p>
    </div>

    <div class="sidebar-menu">
        <div class="menu-label">Main</div>

        <a href="dashboard.php" class="<?= isActive('dashboard.php') ?>">
            <span class="icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <a href="select_company.php" class="<?= isActive('select_company.php') ?>">
            <span class="icon">🏢</span>
            <span>Select Company</span>
        </a>

        <div class="menu-label">Transactions</div>

        <a href="income.php" class="<?= isActive('income.php') ?>">
            <span class="icon">💰</span>
            <span>Income</span>
        </a>

        <a href="expenses.php" class="<?= isActive('expenses.php') ?>">
            <span class="icon">💸</span>
            <span>Expenses</span>
        </a>

        <a href="cash_account.php" class="<?= isActive('cash_account.php') ?>">
            <span class="icon">💵</span>
            <span>Cash Account</span>
        </a>

        <a href="bank_account.php" class="<?= isActive('bank_account.php') ?>">
            <span class="icon">🏦</span>
            <span>Bank Account</span>
        </a>

        <div class="menu-label">Statements</div>

        <a href="assets.php" class="<?= isActive('assets.php') ?>">
            <span class="icon">📦</span>
            <span>Assets</span>
        </a>

        <a href="liabilities.php" class="<?= isActive('liabilities.php') ?>">
            <span class="icon">📉</span>
            <span>Liabilities</span>
        </a>

        <div class="menu-label">HR & Payroll</div>

        <a href="employees.php" class="<?= isActive('employees.php') ?>">
            <span class="icon">👥</span>
            <span>Employees</span>
        </a>

        <a href="salaries.php" class="<?= isActive('salaries.php') ?>">
            <span class="icon">🧾</span>
            <span>Salaries</span>
        </a>

        <div class="menu-label">Reports</div>

     <a href="income_expenditure_report.php" class="<?= isActive('income_expenditure_report.php') ?>">
    <span class="icon">📄</span>
    <span>Income & Expenditure</span>
</a>

<a href="assets_liabilities_report.php" class="<?= isActive('assets_liabilities_report.php') ?>">
    <span class="icon">📑</span>
    <span>Assets & Liabilities</span>
</a>

        <?php if ($role === 'auditor'): ?>
            <div class="menu-label">Audit</div>

            <a href="audit_notes.php" class="<?= isActive('audit_notes.php') ?>">
                <span class="icon">📝</span>
                <span>Audit Notes</span>
            </a>

            <a href="audit_reports.php" class="<?= isActive('audit_reports.php') ?>">
                <span class="icon">✅</span>
                <span>Audit Reports</span>
            </a>
        <?php endif; ?>

        <div class="menu-label">Account</div>

        <a href="profile.php" class="<?= isActive('profile.php') ?>">
            <span class="icon">🙍</span>
            <span>My Profile</span>
        </a>

        <a href="change_password.php" class="<?= isActive('change_password.php') ?>">
            <span class="icon">🔐</span>
            <span>Change Password</span>
        </a>

        <a href="logout.php">
            <span class="icon">🚪</span>
            <span>Logout</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div><strong><?= htmlspecialchars($_SESSION['company_name'] ?? 'No Company', ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div>Responsive for phone & laptop</div>
    </div>
</aside>