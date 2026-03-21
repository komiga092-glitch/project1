<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireRoles($conn, ['organization','accountant']);
header('Content-Type: application/json');
$employeeId = (int)($_GET['employee_id'] ?? 0);
$companyId = currentCompanyId();
$stmt = $conn->prepare('SELECT employee_id, employee_name, basic_salary FROM employees WHERE employee_id=? AND company_id=? LIMIT 1');
$stmt->bind_param('ii', $employeeId, $companyId);
$stmt->execute();
echo json_encode($stmt->get_result()->fetch_assoc() ?: []);
