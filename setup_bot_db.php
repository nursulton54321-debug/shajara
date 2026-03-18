<?php
// =============================================
// FILE: setup_bot_db.php
// MAQSAD: Bot ishlashi uchun kerakli jadvallarni bazada yaratish
// =============================================

require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    // Bazaga ulanish
    if (function_exists('dbConnect')) {
        dbConnect();
    }

    // 1. Bot foydalanuvchilari jadvali (Kimlar botdan foydalanyapti?)
    $sql_users = "CREATE TABLE IF NOT EXISTS bot_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tg_id BIGINT NOT NULL UNIQUE,
        ism VARCHAR(255) NOT NULL,
        telefon VARCHAR(50),
        holat ENUM('kutmoqda', 'tasdiqlangan', 'bloklangan') DEFAULT 'kutmoqda',
        rol ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    db_query($sql_users);

    // 2. Kutilayotgan shaxslar jadvali (Moderatsiya zali)
    $sql_kutilmoqda = "CREATE TABLE IF NOT EXISTS shaxslar_kutilmoqda (
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
        status ENUM('kutilmoqda', 'tasdiqlangan', 'bekor_qilingan') DEFAULT 'kutilmoqda',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    db_query($sql_kutilmoqda);

    // 3. Asosiy shaxslar jadvaliga 'added_by_tg_id' ustunini qo'shish 
    // (Toki shaxsiy kabinetda kim nima kiritganini ajrata olaylik)
    $check_col = db_query("SHOW COLUMNS FROM shaxslar LIKE 'added_by_tg_id'");
    if ($check_col->num_rows == 0) {
        db_query("ALTER TABLE shaxslar ADD COLUMN added_by_tg_id BIGINT NULL AFTER updated_at");
    }

    echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>";
    echo "<h2 style='color:#48c78e;'>Tabriklayman! Bot uchun barcha jadvallar bazada muvaffaqiyatli yaratildi! 🎉</h2>";
    echo "<p style='color:#2c3e50;'>Endi bu sahifani yopib, xavfsizlik uchun <b>setup_bot_db.php</b> faylini o'chirib tashlashingiz mumkin.</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h2 style='color:#f45656; text-align:center; font-family:sans-serif;'>Xatolik yuz berdi: " . $e->getMessage() . "</h2>";
}
?>