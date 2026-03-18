<?php
// =============================================
// FILE: api/voqea_taklif.php
// MAQSAD: Saytdan kelgan voqea taklifi (Qo'shish/Tahrirlash/O'chirish)ni Adminga jo'natish
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (function_exists('dbConnect')) {
        dbConnect();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Faqat POST so'rovlar qabul qilinadi.");
    }

    // Ma'lumotlarni qabul qilish
    $shaxs_id = isset($_POST['shaxs_id']) ? (int)$_POST['shaxs_id'] : 0;
    $harakat = isset($_POST['harakat']) ? $_POST['harakat'] : 'add'; // 'add', 'edit', 'delete'
    $voqea_id = (isset($_POST['voqea_id']) && $_POST['voqea_id'] !== '') ? (int)$_POST['voqea_id'] : "NULL";
    
    $yuboruvchi_ism = isset($_POST['yuboruvchi_ism']) ? addslashes(trim($_POST['yuboruvchi_ism'])) : '';
    $yuboruvchi_tel = isset($_POST['yuboruvchi_tel']) ? addslashes(trim($_POST['yuboruvchi_tel'])) : '';
    
    $sarlavha = isset($_POST['sarlavha']) ? addslashes(trim($_POST['sarlavha'])) : '';
    $sana = isset($_POST['sana']) ? addslashes(trim($_POST['sana'])) : '';
    $matn = isset($_POST['matn']) ? addslashes(trim($_POST['matn'])) : '';

    // Tekshiruv
    if (!$shaxs_id || empty($yuboruvchi_ism) || empty($yuboruvchi_tel)) {
        throw new Exception("Yuboruvchi ma'lumotlari to'ldirilishi shart!");
    }
    if ($harakat !== 'delete' && (empty($sarlavha) || empty($sana) || empty($matn))) {
        throw new Exception("Voqea tafsilotlari to'liq kiritilishi shart!");
    }
    if ($harakat !== 'add' && $voqea_id === "NULL") {
        throw new Exception("Tahrirlash yoki o'chirish uchun voqea ID si topilmadi!");
    }

    // Kutish jadvaliga yozish
    $sql = "INSERT INTO shaxs_voqealar_kutilmoqda 
            (shaxs_id, harakat, voqea_id, yuboruvchi_ism, yuboruvchi_tel, sana, sarlavha, matn, status) 
            VALUES 
            ($shaxs_id, '$harakat', $voqea_id, '$yuboruvchi_ism', '$yuboruvchi_tel', '$sana', '$sarlavha', '$matn', 'kutilmoqda')";
    
    if (!db_query($sql)) {
        throw new Exception("Bazaga yozishda xatolik yuz berdi.");
    }

    $voqea_id_res = db_query("SELECT LAST_INSERT_ID() as id");
    $yangi_ariza_id = $voqea_id_res->fetch_assoc()['id'];

    // Shaxs ismini aniqlash
    $shaxs_res = db_query("SELECT ism, familiya FROM shaxslar WHERE id = $shaxs_id");
    $shaxs_ism = "Noma'lum shaxs";
    if ($shaxs_res && $shaxs_res->num_rows > 0) {
        $sh_row = $shaxs_res->fetch_assoc();
        $shaxs_ism = html_entity_decode($sh_row['ism'] . ' ' . $sh_row['familiya'], ENT_QUOTES, 'UTF-8');
    }

    // ===============================================
    // TELEGRAM BOT ORQALI ADMINGA XABAR YUBORISH
    // ===============================================
    define('BOT_TOKEN', '8504597068:AAE3X0K1STed1nVaveY8aqguUBlseEjPUqw'); 
    define('ADMIN_TG_ID', '139619338'); 

    if ($harakat == 'add') {
        $msg = "🔔 <b>YANGI VOQEA QO'SHISH TAKLIFI!</b>\n\n";
    } elseif ($harakat == 'edit') {
        $msg = "✏️ <b>VOQEANI TAHRIRLASH TAKLIFI!</b>\n\n";
    } elseif ($harakat == 'delete') {
        $msg = "🗑 <b>VOQEANI O'CHIRISH TAKLIFI!</b>\n\n";
    }

    $msg .= "👤 <b>KIMGA:</b> $shaxs_ism\n";
    $msg .= "📅 <b>SANA:</b> " . ($sana ? date('d.m.Y', strtotime($sana)) : "-") . "\n";
    $msg .= "📌 <b>SARLAVHA:</b> $sarlavha\n";
    if ($harakat !== 'delete') $msg .= "📝 <b>MATN:</b> $matn\n";
    $msg .= "\n🕵️‍♂️ <b>YUBORUVCHI:</b> $yuboruvchi_ism\n";
    $msg .= "📞 <b>TELEFON:</b> $yuboruvchi_tel\n\n";
    $msg .= "<i>Buni tasdiqlaysizmi?</i>";

    $inline_keyboard = [
        'inline_keyboard' => [
            [
                ['text' => "✅ Tasdiqlash", 'callback_data' => "apprevent_$yangi_ariza_id"],
                ['text' => "❌ Rad etish", 'callback_data' => "rejevent_$yangi_ariza_id"]
            ]
        ]
    ];

    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $post_fields = [
        'chat_id' => ADMIN_TG_ID,
        'text' => $msg,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode($inline_keyboard)
    ];

    $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch); curl_close($ch);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>