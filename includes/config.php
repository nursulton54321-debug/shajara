<?php

// ======================================
// ENV o'zgaruvchilar
// ======================================

define('DB_HOST', getenv('DB_HOST'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_SSL', getenv('DB_SSL'));

// ======================================
// Global DB
// ======================================

$db = null;

function dbConnect()
{
    global $db;

    if ($db) {
        return $db;
    }

    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $name = DB_NAME;
    $port = DB_PORT;

    mysqli_report(MYSQLI_REPORT_OFF);

    $db = mysqli_init();

    // SSL (Aiven uchun majburiy)
    mysqli_ssl_set($db, NULL, NULL, NULL, NULL, NULL);

    $connected = $db->real_connect(
        $host,
        $user,
        $pass,
        $name,
        $port,
        NULL,
        MYSQLI_CLIENT_SSL
    );

    if (!$connected) {
        error_log("DB CONNECT ERROR: " . mysqli_connect_error());
        throw new Exception("Database ulanish xatosi");
    }

    $db->set_charset("utf8mb4");

    return $db;
}

function db_query($sql)
{
    global $db;

    if (!$db) {
        dbConnect();
    }

    $result = $db->query($sql);

    if (!$result) {
        error_log("SQL ERROR: " . $db->error . " | Query: " . $sql);
    }

    return $result;
}
