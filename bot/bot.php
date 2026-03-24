<?php
// =============================================
// FILE: bot/bot.php
// MAQSAD: Qidiruv, PIN-kod, Moderatsiya, Backup, Tug'ilgan kunlar va Foydalanuvchilar boshqaruvi
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/keyboards.php';

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
define('ADMIN_TG_ID', getenv('ADMIN_TG_ID') ?: '');

function sendTelegram($method, $data) {
    if (BOT_TOKEN === '') {
        return ['ok' => false, 'description' => 'BOT_TOKEN topilmadi'];
    }

    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $res = curl_exec($ch);

    if ($res === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'description' => $err];
    }

    curl_close($ch);
    return json_decode($res, true);
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

    if (BOT_TOKEN === '') {
        return '';
    }

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

            if (@file_put_contents($uploadDir . $nn, file_get_contents($dl))) {
                return $nn;
            }
        }
    }

    return '';
}

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
            FROM shaxslar s
            LEFT JOIN oilaviy_bogliqlik o ON s.id = o.shaxs_id";
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

$rawInput = file_get_contents('php://input');
$update = json_decode($rawInput, true);

// Telegram webhook test uchun bo'sh kirishda 500 bermasin
if (!$update || !is_array($update)) {
    http_response_code(200);
    echo 'OK';
    exit;
}

if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];
    $message_id = $callback['message']['message_id'];

    $user_res = db_query("SELECT * FROM bot_users WHERE tg_id = $chat_id");
    $user = ($user_res && $user_res->num_rows > 0) ? $user_res->fetch_assoc() : null;
    $temp = $user ? json_decode($user['temp_data'], true) : [];

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
        $curr = $currRes ? $currRes->fetch_assoc() : null;

        if ($curr && $curr['status'] == 'rejected') {
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
        $sDataRes = db_query("SELECT * FROM shaxslar WHERE id = $shaxs_id");
        $s_data = $sDataRes ? $sDataRes->fetch_assoc() : null;
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

    // Qolgan callback logikalarini buzmaslik uchun hozircha shu yerda qoldiramiz.
    http_response_code(200);
    echo 'OK';
    exit;
}

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';

    $user_res = db_query("SELECT * FROM bot_users WHERE tg_id = $chat_id");
    if ($user_res && $user_res->num_rows > 0) {
        $user = $user_res->fetch_assoc();
        $status = $user['status'];
        $step = $user['step'];
        $temp = json_decode($user['temp_data'] ?? '{}', true) ?: [];
    } else {
        $status = ($chat_id == ADMIN_TG_ID) ? 'approved' : 'pending';
        db_query("INSERT INTO bot_users (tg_id, status, step, temp_data) VALUES ($chat_id, '$status', 'none', '{}')");
        if ($status == 'pending') {
            $name = addslashes($update['message']['from']['first_name'] ?? 'Foydalanuvchi');
            $admin_kb = json_encode(['inline_keyboard' => [[['text' => "✅ Ruxsat berish", 'callback_data' => "appruser_$chat_id"], ['text' => "❌ Rad etish", 'callback_data' => "rejuser_$chat_id"]]]]);
            sendTelegram('sendMessage', [
                'chat_id' => ADMIN_TG_ID,
                'text' => "👤 <b>Yangi shaxs ruxsat so'ramoqda:</b>\n<a href='tg://user?id=$chat_id'>$name</a>",
                'parse_mode' => 'HTML',
                'reply_markup' => $admin_kb
            ]);
            sendTelegram('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "⏳ <b>Kuting...</b> Ruxsat berilgandan so'ng foydalana olasiz.",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['remove_keyboard' => true])
            ]);
            exit;
        }
        $step = 'none';
        $temp = [];
    }

    if ($status == 'pending' && $chat_id != ADMIN_TG_ID) {
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⏳ Iltimos, admin ruxsat berishini kuting."
        ]);
        exit;
    }

    if ($status == 'rejected' && $chat_id != ADMIN_TG_ID) {
        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Sizga huquq berilmagan."
        ]);
        exit;
    }

    if ($text == "/start" || strpos($text, 'Bekor qilish') !== false) {
        $pin = getSitePin();
        $welcome = "🌟 <b>«OILAMIZ SHAJARASIGA XUSH KELIBSIZ!»</b> 🌟\n\n";
        $welcome .= "📖 <b>BU BOT NIMA UCHUN?</b>\n";
        $welcome .= "<i>Ota-bobolarimiz va qarindoshlarimizning yagona raqamli shajara daraxtini yaratamiz!</i>\n\n";
        $welcome .= "🔐 <b>Maxfiy PIN-kod:</b> <code>$pin</code>\n";
        $welcome .= "<i>(Bu kod saytga kirish uchun kerak bo'ladi)</i>\n\n";
        $welcome .= "👇 <b>Quyidagi menyulardan birini tanlang:</b>";

        sendTelegram('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $welcome,
            'parse_mode' => 'HTML',
            'reply_markup' => btn_main_menu($chat_id)
        ]);
        exit;
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

http_response_code(200);
echo 'OK';
?>
