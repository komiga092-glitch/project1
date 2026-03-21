<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

if (!isset($pageDescription)) {
    $pageDescription = 'Professional Accounting & Audit Management System';
}

$currentUserName = $_SESSION['full_name'] ?? 'User';
$currentUserRole = $_SESSION['role'] ?? 'Guest';
$currentCompany  = $_SESSION['company_name'] ?? 'No Company';
$currentPage     = basename($_SERVER['PHP_SELF']);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$avatarLetter = strtoupper(substr($currentUserName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | TrustLedger Pro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">