<?php
// =============================================
// FILE: includes/config.php
// MAQSAD: Ma'lumotlar bazasi va sayt sozlamalari
// =============================================

// =============================================
// MUHIT SOZLAMALARI
// =============================================
define('APP_ENV', getenv('APP_ENV') ?: 'local');

// Xatoliklarni ko'rsatish
if (APP_ENV !== 'production') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
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
define('DB_SSL', getenv('DB_SSL') ?: 'false');

// =============================================
// SAYT SOZLAMALARI
// =============================================
$siteUrl = getenv('SITE_URL') ?: 'http://localhost/shajara2/';
$siteUrl = rtrim($siteUrl, '/') . '/';

define('SITE_URL', $siteUrl);
define('SITE_NAME', 'Oila Shajarasi');

// =============================================
// BAZAGA ULANISH FUNKSIYASI (MYSQLI)
// =============================================
function dbConnect() {
    $conn = mysqli_init();

    // SSL kerak bo'lsa
    if (DB_SSL === 'true') {
        mysqli_ssl_set($conn, null, null, null, null, null);
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    }

    $connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

    if (!$connected) {
        if (APP_ENV === 'production') {
            die("❌ Serverda vaqtinchalik xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");
        } else {
            die("❌ Bazaga ulanishda xatolik: " . mysqli_connect_error());
        }
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
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];

        // SSL kerak bo'lsa
        if (DB_SSL === 'true') {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (APP_ENV === 'production') {
            die("PDO ulanishda server xatoligi yuz berdi.");
        } else {
            die("PDO ulanish xatolik: " . $e->getMessage());
        }
    }
}

// =============================================
// SESSIYANI BOSHLASH
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>