<?php
// =============================================
// FILE: bot/bot.php
// MAQSAD: Qidiruv, PIN-kod, Moderatsiya, Backup, Tug'ilgan kunlar va Foydalanuvchilar boshqaruvi
// DEBUG VERSIYA: Render loglarda aniq xatoni ushlash uchun
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/keyboards.php';

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
define('ADMIN_TG_ID', getenv('ADMIN_TG_ID') ?: '');

// =============================================
// DEBUG FUNKSIYALAR
// =============================================
function botLog($message, $context = null) {
    if ($context !== null) {
        if (!is_string($context)) {
            $context = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log('[BOT DEBUG] ' . $message . ' | ' . $context);
    } else {
        error_log('[BOT DEBUG] ' . $message);
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    botLog('PHP Error', [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line
    ]);
    return false;
});

set_exception_handler(function ($e) {
    botLog('Uncaught Exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(200);
    echo 'OK';
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        botLog('Shutdown Fatal Error', $error);
    }
});

// =============================================
// TELEGRAMGA SO'ROV YUBORISH
// =============================================
function sendTelegram($method, $data) {
    if (BOT_TOKEN === '') {
        botLog('BOT_TOKEN bo‘sh');
        return false;
    }

    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 30
        ]
    ];

    $context = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);

    if ($res === false) {
        botLog('sendTelegram failed', [
            'method' => $method,
            'data' => $data
        ]);
        return false;
    }

    $decoded = json_decode($res, true);
    if (!is_array($decoded)) {
        botLog('sendTelegram invalid json', [
            'method' => $method,
            'raw' => $res
        ]);
        return false;
    }

    return $decoded;
}

function getNameById($id) {
    if (!$id || $id == "NULL" || $id == "") return "Bog'lanmagan";
    $res = db_query("SELECT ism, familiya FROM shaxslar WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) return $row['ism'] . " " . $row['familiya'];
    return "Noma'lum";
}

function getEmojiProgress($percent) {
    $filled = round($percent / 10);
    $empty = 10 - $filled;
    if ($filled < 0) $filled = 0;
    if ($empty < 0) $empty = 0;
    return str_repeat('🟩', $filled) . str_repeat('⬜', $empty);
}

function downloadTelegramPhoto($file_id) {
    if (!$file_id || $file_id == 'NULL') return '';
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getFile?file_id=" . $file_id;
    $res = @file_get_contents($url);
    if ($res) {
        $json = json_decode($res, true);
        if (isset($json['result']['file_path'])) {
            $fp = $json['result']['file_path'];
            $dl = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $fp;
            $ext = pathinfo($fp, PATHINFO_EXTENSION) ?: 'jpg';
            $nn = 'tg_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            if (@file_put_contents($uploadDir . $nn, file_get_contents($dl))) return $nn;
        }
    }
    return '';
}

// =============================================
// BAZAGA ULANISH
// =============================================
dbConnect();
global $db;

// Sozlamalar jadvali va PIN-kod bazasi ishonchli yaratilishi
$chk_sozlama = db_query("SHOW TABLES LIKE 'sozlamalar'");
if ($chk_sozlama && $chk_sozlama->num_rows == 0) {
    db_query("CREATE TABLE sozlamalar (kalit VARCHAR(50) PRIMARY KEY, qiymat VARCHAR(255))");
    db_query("INSERT IGNORE INTO sozlamalar (kalit, qiymat) VALUES ('sayt_pin', '2026')");
}

function getSitePin() {
    $res = db_query("SELECT qiymat FROM sozlamalar WHERE kalit = 'sayt_pin'");
    if ($res && $res->num_rows > 0) return $res->fetch_assoc()['qiymat'];
    return '2026';
}

$chk_add = db_query("SHOW COLUMNS FROM shaxslar LIKE 'added_by_tg_id'");
if ($chk_add && $chk_add->num_rows == 0) db_query("ALTER TABLE shaxslar ADD COLUMN added_by_tg_id BIGINT NULL AFTER foto");

$chk_vaf = db_query("SHOW COLUMNS FROM shaxslar LIKE 'vafot_sana'");
if ($chk_vaf && $chk_vaf->num_rows == 0) db_query("ALTER TABLE shaxslar ADD COLUMN vafot_sana DATE NULL AFTER tugilgan_sana");

function setStep($tg_id, $step, $temp_arr) {
    $json_safe = addslashes(json_encode($temp_arr, JSON_UNESCAPED_UNICODE));
    db_query("UPDATE bot_users SET step = '$step', temp_data = '$json_safe' WHERE tg_id = $tg_id");
}

function startQuiz($chat_id) {
    global $db;
    $sql = "SELECT s.id, s.ism, s.familiya, s.jins, o.ota_id, o.ona_id, o.turmush_ortogi_id 
            FROM shaxslar s LEFT JOIN oilaviy_bogliqlik o ON s.id = o.shaxs_id";
    $res = db_query($sql);
    if (!$res) {
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ Bazaga ulanishda xatolik yuz berdi."
        ]);
        return;
    }

    $shaxslar = [];
    while ($r = $res->fetch_assoc()) {
        $r['ism'] = html_entity_decode($r['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $r['familiya'] = html_entity_decode($r['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        $shaxslar[$r['id']] = $r;
    }

    $relations = [];
    foreach ($shaxslar as $id => $p) {
        $nameA = trim($p['ism'] . " " . $p['familiya']);
        $jinsA = $p['jins'];

        if (!empty($p['ota_id']) && isset($shaxslar[$p['ota_id']])) {
            $p2 = $shaxslar[$p['ota_id']];
            $nameB = trim($p2['ism'] . " " . $p2['familiya']);
            $relations[] = ['a' => $nameA, 'b' => $nameB, 'ans' => ($jinsA == 'erkak' ? "O'g'li" : "Qizi")];
            $relations[] = ['a' => $nameB, 'b' => $nameA, 'ans' => "Otasi"];

            foreach ($shaxslar as $id3 => $p3) {
                if (!empty($p3['ota_id']) && $p3['ota_id'] == $p['ota_id'] && $id != $id3) {
                    $nameC = trim($p3['ism'] . " " . $p3['familiya']);
                    $relations[] = ['a' => $nameA, 'b' => $nameC, 'ans' => ($jinsA == 'erkak' ? "Akasi (yoki Ukasi)" : "Opasi (yoki Singlisi)")];
                }
            }

            if (!empty($p2['ota_id']) && isset($shaxslar[$p2['ota_id']])) {
                $p3 = $shaxslar[$p2['ota_id']];
                $nameC = trim($p3['ism'] . " " . $p3['familiya']);
                $relations[] = ['a' => $nameA, 'b' => $nameC, 'ans' => ($jinsA == 'erkak' ? "Nevarasi (o'g'il)" : "Nevarasi (qiz)")];
                $relations[] = ['a' => $nameC, 'b' => $nameA, 'ans' => ($p3['jins'] == 'erkak' ? "Bobosi" : "Buvisi")];
            }
        }

        if (!empty($p['ona_id']) && isset($shaxslar[$p['ona_id']])) {
            $p2 = $shaxslar[$p['ona_id']];
            $nameB = trim($p2['ism'] . " " . $p2['familiya']);
            $relations[] = ['a' => $nameA, 'b' => $nameB, 'ans' => ($jinsA == 'erkak' ? "O'g'li" : "Qizi")];
            $relations[] = ['a' => $nameB, 'b' => $nameA, 'ans' => "Onasi"];
        }

        if (!empty($p['turmush_ortogi_id']) && isset($shaxslar[$p['turmush_ortogi_id']])) {
            $p2 = $shaxslar[$p['turmush_ortogi_id']];
            $nameB = trim($p2['ism'] . " " . $p2['familiya']);
            $relations[] = ['a' => $nameA, 'b' => $nameB, 'ans' => ($jinsA == 'erkak' ? "Eri" : "Ayoli")];
        }
    }

    if (empty($relations)) {
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ O'yin o'ynash uchun bazada kamida 2 ta bog'langan kishi bo'lishi kerak!"
        ]);
        return;
    }

    $relations = array_values(array_map("unserialize", array_unique(array_map("serialize", $relations))));
    $rand_idx = array_rand($relations);
    $q = $relations[$rand_idx];
    $correct = $q['ans'];

    $pool = ["O'g'li", "Qizi", "Otasi", "Onasi", "Bobosi", "Buvisi", "Nevarasi (o'g'il)", "Nevarasi (qiz)", "Akasi (yoki Ukasi)", "Opasi (yoki Singlisi)", "Eri", "Ayoli", "Tog'asi", "Amakisi", "Ammasi", "Xolasi"];
    $pool = array_values(array_diff($pool, [$correct]));
    shuffle($pool);

    $options = [
        ['text' => $correct, 'cb' => "quiz_1"],
        ['text' => $pool[0], 'cb' => "quiz_0"],
        ['text' => $pool[1], 'cb' => "quiz_0"],
        ['text' => $pool[2], 'cb' => "quiz_0"]
    ];
    shuffle($options);

    $inline = [];
    foreach ($options as $opt) {
        $inline[] = [['text' => $opt['text'], 'callback_data' => $opt['cb']]];
    }

    $msg = "🎮 <b>OILA VIKTORINASI</b>\n\n❓ <b>{$q['a']}</b>\n<b>{$q['b']}</b> ning kimi bo'ladi?\n\n<i>To'g'ri variantni tanlang: 👇</i>";
    sendTelegram('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $msg,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => $inline])
    ]);
}

// =============================================
// UPDATE O‘QISH
// =============================================
$rawInput = file_get_contents('php://input');
$update = json_decode($rawInput, true);

if (!$update || !is_array($update)) {
    http_response_code(200);
    echo 'OK';
    exit;
}

try {

if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];
    $message_id = $callback['message']['message_id'];

    $user_res = db_query("SELECT * FROM bot_users WHERE tg_id = $chat_id");
    $user = ($user_res && $user_res->num_rows > 0) ? $user_res->fetch_assoc() : null;
    $temp = $user ? json_decode($user['temp_data'], true) : [];

    // FOYDALANUVCHIGA RUXSAT BERISH
    if (strpos($data, 'appruser_') === 0 && $chat_id == ADMIN_TG_ID) {
        $u_id = (int)str_replace('appruser_', '', $data);
        db_query("UPDATE bot_users SET status = 'approved' WHERE tg_id = $u_id");
        $pin = getSitePin();
        sendTelegram('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "✅ Foydalanuvchiga ruxsat berildi!"
        ]);
        sendTelegram('sendMessage', [
            'chat_id' => $u_id,
            'text' => "🎉 *Tabriklaymiz!* Admin sizga shajaradan foydalanishga ruxsat berdi.\n\n🔐 *Saytga kirish uchun PIN-kod:* `$pin`\n\nMarhamat, bot menyularidan foydalaning 👇",
            'parse_mode' => 'Markdown',
            'reply_markup' => btn_main_menu($u_id)
        ]);
        exit;
    }

    if (strpos($data, 'rejuser_') === 0 && $chat_id == ADMIN_TG_ID) {
        $u_id = (int)str_replace('rejuser_', '', $data);
        db_query("UPDATE bot_users SET status = 'rejected' WHERE tg_id = $u_id");
        sendTelegram('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "❌ Foydalanuvchi rad etildi."
        ]);
        sendTelegram('sendMessage', [
            'chat_id' => $u_id,
            'text' => "❌ Kechirasiz, admin sizning so'rovingizni rad etdi."
        ]);
        exit;
    }

    if (strpos($data, 'quiz_') === 0) {
        if ($data == 'quiz_1') {
            $msg = "🎉 <b>BARAKALLA!</b>\n\n✅ To'g'ri topdingiz! Siz shajarani a'lo darajada bilasiz 👏";
            $kb = json_encode(['inline_keyboard' => [[['text' => "🔄 Yana savol berish", 'callback_data' => "quiz_next"]]]]);
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $msg,
                'parse_mode' => 'HTML',
                'reply_markup' => $kb
            ]);
        } elseif ($data == 'quiz_0') {
            sendTelegram('answerCallbackQuery', [
                'callback_query_id' => $callback['id'],
                'text' => "❌ Noto'g'ri javob! Yana o'ylab ko'ring.",
                'show_alert' => true
            ]);
        } elseif ($data == 'quiz_next') {
            sendTelegram('deleteMessage', [
                'chat_id' => $chat_id,
                'message_id' => $message_id
            ]);
            startQuiz($chat_id);
        }
        exit;
    }

    // FOYDALANUVCHILARNI BOSHQARISH (WARN/BAN/UNBAN)
    if (strpos($data, 'u_warn_') === 0 && $chat_id == ADMIN_TG_ID) {
        $target_id = str_replace('u_warn_', '', $data);
        setStep($chat_id, 'admin_input_warn', ['target_id' => $target_id]);
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⚠️ <b>ID: $target_id</b> ga yuboriladigan ogohlantirish matnini yozing:",
            'parse_mode' => 'HTML',
            'reply_markup' => btn_cancel()
        ]);
        exit;
    }

    if (strpos($data, 'u_ban_') === 0 && $chat_id == ADMIN_TG_ID) {
        $target_id = str_replace('u_ban_', '', $data);
        $currRes = db_query("SELECT status FROM bot_users WHERE tg_id = $target_id");
        $curr = ($currRes && $currRes->num_rows > 0) ? $currRes->fetch_assoc() : ['status' => null];

        if ($curr['status'] == 'rejected') {
            db_query("UPDATE bot_users SET status = 'approved' WHERE tg_id = $target_id");
            sendTelegram('sendMessage', [
                'chat_id' => $target_id,
                'text' => "✅ Botdan foydalanish huquqingiz tiklandi."
            ]);
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "✅ Foydalanuvchi (ID: $target_id) bandan chiqarildi."
            ]);
        } else {
            db_query("UPDATE bot_users SET status = 'rejected' WHERE tg_id = $target_id");
            sendTelegram('sendMessage', [
                'chat_id' => $target_id,
                'text' => "🚫 <b>Sizning botdan foydalanish huquqingiz admin tomonidan bekor qilindi.</b>",
                'parse_mode' => 'HTML'
            ]);
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "✅ Foydalanuvchi (ID: $target_id) ban qilindi."
            ]);
        }
        exit;
    }

    if ($data == 'back_to_list') {
        sendTelegram('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
        exit;
    }

    if (strpos($data, 'manage_') === 0) {
        $shaxs_id = (int)str_replace('manage_', '', $data);
        $sRes = db_query("SELECT * FROM shaxslar WHERE id = $shaxs_id");
        $s_data = ($sRes && $sRes->num_rows > 0) ? $sRes->fetch_assoc() : null;
        if ($s_data) {
            $tugilgan = !empty($s_data['tugilgan_sana']) ? date('d.m.Y', strtotime($s_data['tugilgan_sana'])) : 'Kiritilmagan';
            $msg = "⚙️ <b>SHAXSNI BOSHQARISH</b>\n\n👤 <b>F.I.SH:</b> {$s_data['familiya']} {$s_data['ism']} {$s_data['otasining_ismi']}\n📅 <b>Tug'ilgan:</b> {$tugilgan}\n\nNimani amalga oshiramiz?";
            $kb = json_encode(['inline_keyboard' => [
                [['text' => "✏️ Ismni tahrirlash", 'callback_data' => "edit_ism_" . $shaxs_id], ['text' => "✏️ Familiyani tahrirlash", 'callback_data' => "edit_familiya_" . $shaxs_id]],
                [['text' => "✏️ Sanani tahrirlash", 'callback_data' => "edit_tugilgan_sana_" . $shaxs_id]],
                [['text' => "👨‍👦 Ota biriktirish", 'callback_data' => "asklink_ota_" . $shaxs_id], ['text' => "👩‍👦 Ona biriktirish", 'callback_data' => "asklink_ona_" . $shaxs_id]],
                [['text' => "💍 Juft biriktirish", 'callback_data' => "asklink_juft_" . $shaxs_id]],
                [['text' => "🗑 Butunlay o'chirish", 'callback_data' => "del_main_" . $shaxs_id]],
                [['text' => "🔙 Orqaga", 'callback_data' => "back_to_list"]]
            ]]);
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $msg,
                'parse_mode' => 'HTML',
                'reply_markup' => $kb
            ]);
        }
        exit;
    }

    if (strpos($data, 'edit_') === 0 && strpos($data, 'edit_ariza_') === false) {
        $parts = explode('_', $data);
        if (count($parts) >= 3) {
            $shaxs_id = array_pop($parts);
            array_shift($parts);
            $field = implode('_', $parts);
            setStep($chat_id, "editmain_{$field}_{$shaxs_id}", []);
            $field_name = ($field == 'ism') ? 'Yangi ismni' : (($field == 'familiya') ? 'Yangi familiyani' : 'Yangi sanani (kk.oo.yyyy)');
            sendTelegram('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✏️ <b>$field_name kiriting:</b>",
                'parse_mode' => 'HTML',
                'reply_markup' => btn_cancel()
            ]);
        }
        exit;
    }

    if (strpos($data, 'asklink_') === 0) {
        $parts = explode('_', $data);
        $tur = $parts[1];
        $shaxs_id = $parts[2];
        setStep($chat_id, "dolink_{$tur}_{$shaxs_id}", []);
        $rol = $tur == 'ota' ? 'Otasining' : ($tur == 'ona' ? 'Onasining' : 'Turmush o\'rtog\'ining');
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🔍 <b>$rol ismi qanday?</b>\n<i>(Baza ichidan qidirish uchun 3 ta harf yozib yuboring)</i>",
            'parse_mode' => 'HTML',
            'reply_markup' => btn_cancel()
        ]);
        exit;
    }

    if (strpos($data, 'setlink_') === 0) {
        $parts = explode('_', $data);
        $tur = $parts[1];
        $shaxs_id = $parts[2];
        $target_id = $parts[3];
        $col = $tur == 'ota' ? 'ota_id' : ($tur == 'ona' ? 'ona_id' : 'turmush_ortogi_id');
        $chk = db_query("SELECT id FROM oilaviy_bogliqlik WHERE shaxs_id = $shaxs_id");
        if ($chk && $chk->num_rows > 0) db_query("UPDATE oilaviy_bogliqlik SET $col = $target_id WHERE shaxs_id = $shaxs_id");
        else db_query("INSERT INTO oilaviy_bogliqlik (shaxs_id, $col) VALUES ($shaxs_id, $target_id)");
        sendTelegram('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "✅ Muvaffaqiyatli biriktirildi!"
        ]);
        setStep($chat_id, 'none', []);
        exit;
    }

    if (strpos($data, 'del_main_') === 0) {
        $shaxs_id = (int)str_replace('del_main_', '', $data);
        db_query("DELETE FROM shaxslar WHERE id = $shaxs_id");
        db_query("DELETE FROM oilaviy_bogliqlik WHERE shaxs_id = $shaxs_id");
        sendTelegram('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => "Shaxs butunlay o'chirildi!",
            'show_alert' => true
        ]);
        sendTelegram('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
        exit;
    }

    if ($user && $user['status'] != 'approved' && $chat_id != ADMIN_TG_ID) {
        sendTelegram('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => "Sizda huquq yo'q!",
            'show_alert' => true
        ]);
        exit;
    }

    if (strpos($data, 'approve_') === 0 && $chat_id == ADMIN_TG_ID) {
        $ariza_id = (int)str_replace('approve_', '', $data);
        $arizaRes = db_query("SELECT * FROM shaxslar_kutilmoqda WHERE id = $ariza_id AND status = 'kutilmoqda'");
        $ariza = ($arizaRes && $arizaRes->num_rows > 0) ? $arizaRes->fetch_assoc() : null;
        if ($ariza) {
            $i = addslashes($ariza['ism']);
            $f = addslashes($ariza['familiya']);
            $oi = addslashes($ariza['otasining_ismi'] ?? '');
            $j = addslashes($ariza['jins']);
            $s = addslashes($ariza['tugilgan_sana']);
            $t = addslashes($ariza['telefon'] ?? '');
            $k = addslashes($ariza['kasbi'] ?? '');
            $added_by = $ariza['added_by_tg_id'];
            $p = downloadTelegramPhoto($ariza['foto']);

            $o = ($ariza['ota_id'] && $ariza['ota_id'] != 'NULL' && $ariza['ota_id'] != 0) ? (int)$ariza['ota_id'] : "NULL";
            $on = ($ariza['ona_id'] && $ariza['ona_id'] != 'NULL' && $ariza['ona_id'] != 0) ? (int)$ariza['ona_id'] : "NULL";
            $tur = ($ariza['turmush_ortogi_id'] && $ariza['turmush_ortogi_id'] != 'NULL' && $ariza['turmush_ortogi_id'] != 0) ? (int)$ariza['turmush_ortogi_id'] : "NULL";

            if (db_query("INSERT INTO shaxslar (ism, familiya, otasining_ismi, jins, tugilgan_sana, telefon, kasbi, foto, added_by_tg_id, created_at) VALUES ('$i', '$f', '$oi', '$j', '$s', '$t', '$k', '$p', '$added_by', NOW())")) {
                $y_res = db_query("SELECT LAST_INSERT_ID() as id");
                $yangi_id = ($y_res && $y_res->num_rows > 0) ? $y_res->fetch_assoc()['id'] : 0;
                if ($o != "NULL" || $on != "NULL" || $tur != "NULL") db_query("INSERT INTO oilaviy_bogliqlik (shaxs_id, ota_id, ona_id, turmush_ortogi_id) VALUES ($yangi_id, $o, $on, $tur)");
                db_query("UPDATE shaxslar_kutilmoqda SET status = 'tasdiqlangan' WHERE id = $ariza_id");
                sendTelegram('editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => $message_id,
                    'text' => "✅ $i $f shajaraga qo'shildi!"
                ]);
                sendTelegram('sendMessage', [
                    'chat_id' => $added_by,
                    'text' => "🎉 Xushxabar! Siz yuborgan <b>$i $f</b> admin tomonidan tasdiqlandi!",
                    'parse_mode' => 'HTML'
                ]);
            }
        } else {
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "⚠️ Bu ariza ko'rib chiqilgan."
            ]);
        }
        exit;
    }

    if (strpos($data, 'reject_') === 0) {
        if ($data == 'reject_person') {
            setStep($chat_id, 'none', []);
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "❌ Bekor qilindi."
            ]);
            sendTelegram('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "Bosh menyu:",
                'reply_markup' => btn_main_menu($chat_id)
            ]);
        } else {
            $ariza_id = (int)str_replace('reject_', '', $data);
            db_query("UPDATE shaxslar_kutilmoqda SET status = 'rad_etilgan' WHERE id = $ariza_id");
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "❌ Ariza rad etildi."
            ]);
        }
        exit;
    }

    if (strpos($data, 'apprevent_') === 0 && $chat_id == ADMIN_TG_ID) {
        $v_id = (int)str_replace('apprevent_', '', $data);
        $vRes = db_query("SELECT * FROM shaxs_voqealar_kutilmoqda WHERE id = $v_id AND status = 'kutilmoqda'");
        $v_data = ($vRes && $vRes->num_rows > 0) ? $vRes->fetch_assoc() : null;

        if ($v_data) {
            $sid = $v_data['shaxs_id'];
            $harakat = $v_data['harakat'];
            $target_vid = $v_data['voqea_id'];
            $sana = addslashes($v_data['sana']);
            $sarlavha = addslashes($v_data['sarlavha']);
            $matn = addslashes($v_data['matn']);

            if ($harakat == 'add') {
                db_query("INSERT INTO shaxs_voqealar (shaxs_id, sana, sarlavha, matn, icon, color) VALUES ($sid, '$sana', '$sarlavha', '$matn', 'fa-star', '#667eea')");
                $harakat_txt = "qo'shildi";
            } elseif ($harakat == 'edit') {
                db_query("UPDATE shaxs_voqealar SET sana='$sana', sarlavha='$sarlavha', matn='$matn' WHERE id=$target_vid");
                $harakat_txt = "tahrirlandi";
            } elseif ($harakat == 'delete') {
                db_query("DELETE FROM shaxs_voqealar WHERE id=$target_vid");
                $harakat_txt = "o'chirildi";
            }

            db_query("UPDATE shaxs_voqealar_kutilmoqda SET status = 'tasdiqlandi' WHERE id = $v_id");

            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "✅ Voqea muvaffaqiyatli $harakat_txt va Timeline yangilandi!"
            ]);
        } else {
            sendTelegram('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "⚠️ Bu ariza allaqachon ko'rib chiqilgan yoki topilmadi."
            ]);
        }
        exit;
    }

    if (strpos($data, 'rejevent_') === 0 && $chat_id == ADMIN_TG_ID) {
        $v_id = (int)str_replace('rejevent_', '', $data);
        db_query("UPDATE shaxs_voqealar_kutilmoqda SET status = 'rad_etildi' WHERE id = $v_id");
        sendTelegram('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "❌ Voqea amaliyoti rad etildi."
        ]);
        exit;
    }

    if (strpos($data, 'del_ariza_') === 0) {
        $del_id = (int)str_replace('del_ariza_', '', $data);
        db_query("DELETE FROM shaxslar_kutilmoqda WHERE id = $del_id AND status = 'kutilmoqda'");
        sendTelegram('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "🗑 Arizangiz o'chirildi."
        ]);
        exit;
    }

    if (strpos($data, 'edit_ariza_') === 0) {
        $del_id = (int)str_replace('edit_ariza_', '', $data);
        db_query("DELETE FROM shaxslar_kutilmoqda WHERE id = $del_id AND status = 'kutilmoqda'");
        sendTelegram('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "✏️ Tahrirlash uchun eski ariza bekor qilindi.\nQaytadan kiriting."
        ]);
        setStep($chat_id, 'add_ism', []);
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✍️ <b>1. Ismni kiriting:</b>\n<i>(Masalan: Nurislom)</i>",
            'parse_mode' => 'HTML',
            'reply_markup' => btn_cancel()
        ]);
        exit;
    }

    if (strpos($data, 'set_ota_') === 0 || $data === 'skip_ota') {
        $temp['ota_id'] = ($data === 'skip_ota') ? null : (int)str_replace('set_ota_', '', $data);
        setStep($chat_id, 'ask_ona', $temp);
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👩‍👦 <b>10. Onasining ismi qanday?</b>\n<i>(Baza ichidan qidirish uchun 3 ta harf yozib yuboring)</i>",
            'parse_mode' => 'HTML',
            'reply_markup' => btn_skip_cancel()
        ]);
    } elseif (strpos($data, 'set_ona_') === 0 || $data === 'skip_ona') {
        $temp['ona_id'] = ($data === 'skip_ona') ? null : (int)str_replace('set_ona_', '', $data);
        setStep($chat_id, 'ask_turmush', $temp);
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "💍 <b>11. Turmush o'rtog'ining ismi qanday?</b>\n<i>(Bor bo'lsa, 3 ta harf yozing. Yo'q bo'lsa o'tkazib yuboring)</i>",
            'parse_mode' => 'HTML',
            'reply_markup' => btn_skip_cancel()
        ]);
    } elseif (strpos($data, 'set_turmush_') === 0 || $data === 'skip_turmush') {
        $temp['turmush_ortogi_id'] = ($data === 'skip_turmush') ? null : (int)str_replace('set_turmush_', '', $data);
        setStep($chat_id, 'confirm', $temp);
        $msg = "📋 <b>TEKSHIRISH:</b>\n\n👤 <b>F.I.SH:</b> {$temp['familiya']} {$temp['ism']} " . ($temp['otasining_ismi'] ?? '') . "\n🚻 <b>Jins:</b> " . ($temp['jins'] == 'erkak' ? 'Erkak' : 'Ayol') . "\n📅 <b>Sana:</b> " . date('d.m.Y', strtotime($temp['sana'])) . "\n👨‍👦 <b>Ota:</b> " . getNameById($temp['ota_id']) . "\n👩‍👦 <b>Ona:</b> " . getNameById($temp['ona_id']) . "\n💍 <b>Jufti:</b> " . getNameById($temp['turmush_ortogi_id']) . "\n\nMa'lumotlar to'g'rimi?";
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "Klaviatura yopildi 👇",
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => btn_confirm_person()
        ]);
    } elseif ($data == 'save_person') {
        $i = addslashes($temp['ism']);
        $f = addslashes($temp['familiya']);
        $oi = addslashes($temp['otasining_ismi'] ?? '');
        $j = addslashes($temp['jins']);
        $s = addslashes($temp['sana']);
        $t = addslashes($temp['telefon'] ?? '');
        $k = addslashes($temp['kasb'] ?? '');
        $p = addslashes($temp['foto'] ?? '');
        $ota = $temp['ota_id'] ?: "NULL";
        $ona = $temp['ona_id'] ?: "NULL";
        $tur = $temp['turmush_ortogi_id'] ?: "NULL";

        db_query("INSERT INTO shaxslar_kutilmoqda (added_by_tg_id, ism, familiya, otasining_ismi, jins, tugilgan_sana, telefon, kasbi, foto, ota_id, ona_id, turmush_ortogi_id, status) VALUES ($chat_id, '$i', '$f', '$oi', '$j', '$s', '$t', '$k', '$p', $ota, $ona, $tur, 'kutilmoqda')");
        $newRes = db_query("SELECT LAST_INSERT_ID() as id");
        $new_id = ($newRes && $newRes->num_rows > 0) ? $newRes->fetch_assoc()['id'] : 0;

        sendTelegram('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "✅ Adminga yuborildi!"
        ]);
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "Yangi amalni tanlang:",
            'reply_markup' => btn_main_menu($chat_id)
        ]);
        sendTelegram('sendMessage', [
            'chat_id' => ADMIN_TG_ID,
            'text' => "🆕 <b>Yangi shaxs:</b>\n$f $i $oi\nTasdiqlaysizmi?",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "✅ Tasdiqlash", 'callback_data' => "approve_$new_id"], ['text' => "❌ Rad etish", 'callback_data' => "reject_$new_id"]]]])
        ]);
        setStep($chat_id, 'none', []);
    }
    exit;
}

http_response_code(200);
echo 'OK';

} catch (Throwable $e) {
    botLog('Caught Throwable', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(200);
    echo 'OK';
    exit;
}
?>
