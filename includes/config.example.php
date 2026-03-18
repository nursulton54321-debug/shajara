<?php
// Bu faylni nusxalab config.php deb saqlang
// va qiymatlarni to'ldiring

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'shajara_db');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/shajara2/');
define('SITE_NAME', 'Oila Shajarasi');

function dbConnect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("❌ Bazaga ulanishda xatolik: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function dbPDO() {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    } catch(PDOException $e) {
        die("PDO ulanish xatolik: " . $e->getMessage());
    }
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
```

---

