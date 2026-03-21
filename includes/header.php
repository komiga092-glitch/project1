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

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* project base path */
$base_url = '/project1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | TrustLedger Pro</title>
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css">
</head>
<body>
<div class="app-shell">