<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRoles($conn, ['auditor','organization']);
$companyId = currentCompanyId();
$pageTitle = 'Auditor Dashboard';
include __DIR__ . '/../includes/header.php'; include __DIR__ . '/../includes/sidebar.php';
$income = (float)$conn->query("SELECT COALESCE(SUM(amount),0) total FROM income WHERE company_id=$companyId")->fetch_assoc()['total'];
$expense = (float)$conn->query("SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE company_id=$companyId")->fetch_assoc()['total'];
?>
<div class="cards"><div class="card stat"><h3>Total Income</h3><div class="amount">Rs. <?= number_format($income,2) ?></div></div><div class="card stat"><h3>Total Expense</h3><div class="amount">Rs. <?= number_format($expense,2) ?></div></div></div>
<div class="card"><p>Auditor can review company data, add notes, and prepare audit reports.</p></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
