<?php
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireCompany(): void {
    if (empty($_SESSION['company_id'])) {
        header('Location: select_company.php');
        exit;
    }
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentCompanyId(): int {
    return (int)($_SESSION['company_id'] ?? 0);
}

function currentRole(): string {
    return (string)($_SESSION['role_in_company'] ?? '');
}

function userHasCompanyRole(mysqli $conn, int $userId, int $companyId, array $roles): bool {
    if (!$userId || !$companyId || !$roles) return false;
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types = 'ii' . str_repeat('s', count($roles));
    $sql = "SELECT 1 FROM company_user_access WHERE user_id=? AND company_id=? AND access_status='Active' AND role_in_company IN ($placeholders) LIMIT 1";
    $stmt = $conn->prepare($sql);
    $params = [$types, $userId, $companyId, ...$roles];
    $refs = [];
    foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
    $stmt->bind_param(...$refs);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool)$result->fetch_row();
}

function requireRoles(mysqli $conn, array $roles): void {
    requireLogin();
    requireCompany();
    if (!userHasCompanyRole($conn, currentUserId(), currentCompanyId(), $roles)) {
        http_response_code(403);
        die('Access denied');
    }
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function companyName(mysqli $conn, int $companyId): string {
    $stmt = $conn->prepare('SELECT company_name FROM companies WHERE company_id = ? LIMIT 1');
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['company_name'] ?? '';
}
