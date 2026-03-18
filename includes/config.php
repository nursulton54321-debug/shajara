<?php
// =============================================
// FILE: includes/config.php
// MAQSAD: Ma'lumotlar bazasi va sayt sozlamalari
// =============================================

// Xatoliklarni ko'rsatish (faqat local da)
if (getenv('APP_ENV') !== 'production') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}

// =============================================
// BAZA SOZLAMALARI
// =============================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'shajara_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_SSL',  getenv('DB_SSL')  ?: 'false');

// =============================================
// SAYT SOZLAMALARI
// =============================================
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/shajara2/');
define('SITE_NAME', 'Oila Shajarasi');

// =============================================
// BAZAGA ULANISH FUNKSIYASI
// =============================================
function dbConnect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    // SSL kerak bo'lsa (Aiven uchun)
    if (DB_SSL === 'true') {
        mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    }

    if ($conn->connect_error) {
        die("❌ Bazaga ulanishda xatolik: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    // Global $db o'zgaruvchini saqlash
    global $db;
    $db = $conn;

    return $conn;
}

// =============================================
// PDO ULANISH FUNKSIYASI
// =============================================
function dbPDO() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];

        // SSL kerak bo'lsa (Aiven uchun)
        if (DB_SSL === 'true') {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("PDO ulanish xatolik: " . $e->getMessage());
    }
}

// =============================================
// SESSIYANI BOSHLASH
// =============================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>