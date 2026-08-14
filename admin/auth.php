<?php
if (!defined('ADMIN_APP')) {
    http_response_code(403);
    exit;
}

function loadAdminConfig(): ?array {
    $path = __DIR__ . '/config.php';
    if (!file_exists($path)) {
        return null;
    }
    $cfg = require $path;
    if (!is_array($cfg)
        || empty($cfg['admin_username'])
        || empty($cfg['admin_password_hash'])
    ) {
        return null;
    }
    return $cfg;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
