<?php
// =============================================
// FILE: admin/logout.php
// MAQSAD: Admin paneldan chiqish
// =============================================

require_once __DIR__ . '/../includes/config.php';

// Sessiyani boshlash (agar boshlanmagan bo'lsa)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sessiyani tozalash
$_SESSION = array();

// Sessiyani yo'q qilish
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Login sahifasiga o'tish
header('Location: login.php?logout=1');
exit;
?>