<?php
require_once __DIR__ . '/config/db.php';
if (!empty($_SESSION['user_id'])) {
    if (!empty($_SESSION['company_id'])) {
        header('Location: dashboard.php');
    } else {
        header('Location: select_company.php');
    }
    exit;
}
header('Location: login.php');
exit;
