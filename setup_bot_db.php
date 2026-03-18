<?php
// =============================================
// FILE: setup_bot_db.php
// MAQSAD: Barcha jadvallarni bazada yaratish
// =============================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

try {
    if (function_exists('dbConnect')) {
        dbConnect();
    }

    // 1. ASOSIY SHAXSLAR JADVALI
    db_query("CREATE TABLE IF NOT EXISTS shaxslar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ism VARCHAR(100) NOT NULL,
        familiya VARCHAR(100) NOT NULL,
        otasining_ismi VARCHAR(100),
        jins ENUM('erkak', 'ayol') NOT NULL,
        tugilgan_sana DATE,
        vafot_sana DATE,
        tirik TINYINT(1) DEFAULT 1,
        tugilgan_joy VARCHAR(255),
        kasbi VARCHAR(255),
        telefon VARCHAR(50),
        bio TEXT,
        foto VARCHAR(255),
        added_by_tg_id BIGINT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 2. OILAVIY BOG'LIQLIK JADVALI
    db_query("CREATE TABLE IF NOT EXISTS oilaviy_bogliqlik (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shaxs_id INT NOT NULL,
        ota_id INT,
        ona_id INT,
        turmush_ortogi_id INT,
        FOREIGN KEY (shaxs_id) REFERENCES shaxslar(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 3. BOT FOYDALANUVCHILARI JADVALI
    db_query("CREATE TABLE IF NOT EXISTS bot_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tg_id BIGINT NOT NULL UNIQUE,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        step VARCHAR(100) DEFAULT 'none',
        temp_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 4. KUTILAYOTGAN SHAXSLAR JADVALI
    db_query("CREATE TABLE IF NOT EXISTS shaxslar_kutilmoqda (
        id INT AUTO_INCREMENT PRIMARY KEY,
        added_by_tg_id BIGINT NOT NULL,
        ism VARCHAR(100) NOT NULL,
        familiya VARCHAR(100) NOT NULL,
        otasining_ismi VARCHAR(100),
        jins ENUM('erkak', 'ayol') NOT NULL,
        tugilgan_sana DATE,
        vafot_sana DATE,
        tirik TINYINT(1) DEFAULT 1,
        tugilgan_joy VARCHAR(255),
        kasbi VARCHAR(255),
        telefon VARCHAR(50),
        bio TEXT,
        foto VARCHAR(255),
        ota_id INT,
        ona_id INT,
        turmush_ortogi_id INT,
        status ENUM('kutilmoqda', 'tasdiqlangan', 'rad_etilgan') DEFAULT 'kutilmoqda',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 5. SOZLAMALAR JADVALI
    db_query("CREATE TABLE IF NOT EXISTS sozlamalar (
        kalit VARCHAR(50) PRIMARY KEY,
        qiymat VARCHAR(255)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    db_query("INSERT IGNORE INTO sozlamalar (kalit, qiymat) VALUES ('sayt_pin', '2026')");

    // 6. SHAXS VOQEALARI JADVALI
    db_query("CREATE TABLE IF NOT EXISTS shaxs_voqealar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shaxs_id INT NOT NULL,
        sana DATE,
        sarlavha VARCHAR(255),
        matn TEXT,
        icon VARCHAR(50) DEFAULT 'fa-star',
        color VARCHAR(20) DEFAULT '#667eea',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (shaxs_id) REFERENCES shaxslar(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 7. KUTILAYOTGAN VOQEALAR JADVALI
    db_query("CREATE TABLE IF NOT EXISTS shaxs_voqealar_kutilmoqda (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shaxs_id INT NOT NULL,
        voqea_id INT,
        harakat ENUM('add', 'edit', 'delete') DEFAULT 'add',
        sana DATE,
        sarlavha VARCHAR(255),
        matn TEXT,
        yuboruvchi_ism VARCHAR(100),
        yuboruvchi_tel VARCHAR(50),
        status ENUM('kutilmoqda', 'tasdiqlandi', 'rad_etildi') DEFAULT 'kutilmoqda',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 8. ADMINLAR JADVALI
    db_query("CREATE TABLE IF NOT EXISTS adminlar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        last_login DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Default admin (username: admin, parol: admin123)
    db_query("INSERT IGNORE INTO adminlar (username, password) VALUES ('admin', 'admin123')");

    echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>";
    echo "<h2 style='color:#48c78e;'>✅ Barcha jadvallar muvaffaqiyatli yaratildi!</h2>";
    echo "<p style='color:#2c3e50;'>Endi <a href='/'>Bosh sahifaga</a> o'tishingiz mumkin.</p>";
    echo "<p style='color:#e74c3c;'><b>Xavfsizlik uchun setup_bot_db.php faylini o'chiring!</b></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h2 style='color:#f45656; text-align:center; font-family:sans-serif;'>Xatolik: " . $e->getMessage() . "</h2>";
}
?>