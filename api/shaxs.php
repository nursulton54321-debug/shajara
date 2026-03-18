<?php
// =============================================
// FILE: api/shaxs.php
// MAQSAD: Shaxs ma'lumotlarini olish, qidirish, O'CHIRISH, GALEREYA va TIMELINE (Vaqt o'qi + Maxsus voqealar)
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shajara_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (function_exists('dbConnect')) {
        dbConnect();
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // ==========================================
    // 1. O'CHIRISH
    // ==========================================
    if ($method === 'POST' || $method === 'DELETE') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        $force = isset($_POST['force']) ? (int)$_POST['force'] : (isset($_GET['force']) ? (int)$_GET['force'] : 0);

        if ($id <= 0) throw new Exception("O'chirish uchun noto'g'ri ID berildi.", 400);

        $farzandlar = farzandlar_olish($id);
        $farzandlar_soni = is_array($farzandlar) ? count($farzandlar) : 0;

        if ($farzandlar_soni > 0 && !$force) {
            echo json_encode(['success' => false, 'message' => 'Ushbu shaxsning farzandlari bor. Avval ularni o\'chiring yoki majburiy o\'chirishni tanlang.', 'farzandlar_soni' => $farzandlar_soni]);
            exit;
        }

        db_query("DELETE FROM oilaviy_bogliqlik WHERE ota_id = $id OR ona_id = $id OR shaxs_id = $id OR turmush_ortogi_id = $id");
        $result = db_query("DELETE FROM shaxslar WHERE id = $id");

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Shaxs muvaffaqiyatli o\'chirildi']);
        } else {
            throw new Exception("Shaxsni o'chirishda xatolik yuz berdi.");
        }
        exit;
    }


    // ==========================================
    // 2. MA'LUMOTLARNI OLISH
    // ==========================================
    if ($method !== 'GET') throw new Exception("Faqat GET yoki POST so'rovlariga ruxsat", 405);

    function tozalash_html($str) {
        if (empty($str)) return $str;
        $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    if (isset($_GET['eng_katta'])) {
        $sql = "SELECT id, ism, familiya FROM shaxslar WHERE tugilgan_sana IS NOT NULL AND tugilgan_sana != '0000-00-00' ORDER BY tugilgan_sana ASC LIMIT 1";
        $result = db_query($sql);
        if ($result && $result->num_rows > 0) {
            $shaxs = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => ['id' => (int)$shaxs['id'], 'ism' => tozalash_html($shaxs['ism']), 'familiya' => tozalash_html($shaxs['familiya'])]], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Shaxs topilmadi']);
        }
        exit;
    }

    if (isset($_GET['q'])) {
        $q = trim($_GET['q']);
        if (strlen($q) < 2) { echo json_encode(['success' => false, 'message' => 'Qidiruv so\'zi juda qisqa']); exit; }
        
        $q = sanitize($q);
        $sql = "SELECT id, ism, familiya, jins, tugilgan_sana, foto FROM shaxslar WHERE ism LIKE '%$q%' OR familiya LIKE '%$q%' OR otasining_ismi LIKE '%$q%' ORDER BY tugilgan_sana DESC LIMIT 20";
        $result = db_query($sql);
        $shaxslar = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $shaxslar[] = ['id' => (int)$row['id'], 'ism' => tozalash_html($row['ism']), 'familiya' => tozalash_html($row['familiya']), 'jins' => $row['jins'], 'tugilgan_sana' => $row['tugilgan_sana'], 'foto' => $row['foto']];
            }
        }
        echo json_encode(['success' => true, 'data' => $shaxslar, 'count' => count($shaxslar)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($_GET['id'])) {
        $sql = "SELECT id, ism, familiya, jins, tugilgan_sana, tirik, foto FROM shaxslar ORDER BY tugilgan_sana DESC";
        $result = db_query($sql);
        $shaxslar = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $shaxslar[] = ['id' => (int)$row['id'], 'ism' => tozalash_html($row['ism']), 'familiya' => tozalash_html($row['familiya']), 'jins' => $row['jins'], 'tugilgan_sana' => $row['tugilgan_sana'], 'tirik' => (bool)$row['tirik'], 'foto' => $row['foto']];
            }
        }
        echo json_encode(['success' => true, 'data' => $shaxslar, 'count' => count($shaxslar)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── ID BO'YICHA BITTA SHAXSNI OLISH VA TIMELINE HISOB-KITOBI ──
    $id = (int)$_GET['id'];
    if ($id <= 0) throw new Exception('Noto\'g\'ri ID');

    $sql = "SELECT * FROM shaxslar WHERE id = $id";
    $result = db_query($sql);
    if (!$result || $result->num_rows === 0) throw new Exception('Shaxs topilmadi', 404);
    
    $shaxs = $result->fetch_assoc();
    $ota_ona = ota_ona_olish($id);
    $turmush_ortogi_id = turmush_ortogi_olish($id);
    
    $yosh = null;
    if (!empty($shaxs['tugilgan_sana']) && $shaxs['tugilgan_sana'] != '0000-00-00') {
        $tugilgan = new DateTime($shaxs['tugilgan_sana']);
        $bugun = new DateTime();
        $yosh = $bugun->diff($tugilgan)->y;
    }
    
    $response = [
        'id' => (int)$shaxs['id'],
        'ism' => tozalash_html($shaxs['ism']),
        'familiya' => tozalash_html($shaxs['familiya']),
        'otasining_ismi' => tozalash_html($shaxs['otasining_ismi']),
        'jins' => $shaxs['jins'],
        'tugilgan_sana' => $shaxs['tugilgan_sana'] != '0000-00-00' ? $shaxs['tugilgan_sana'] : null,
        'vafot_sana' => $shaxs['vafot_sana'] != '0000-00-00' ? $shaxs['vafot_sana'] : null,
        'tirik' => (bool)$shaxs['tirik'],
        'yosh' => $yosh,
        'kasbi' => tozalash_html($shaxs['kasbi']),
        'telefon' => tozalash_html($shaxs['telefon']),
        'tugilgan_joy' => tozalash_html($shaxs['tugilgan_joy']),
        'foto' => $shaxs['foto'] ?: null,
        'ota_id' => $ota_ona['ota_id'] ? (int)$ota_ona['ota_id'] : null,
        'ona_id' => $ota_ona['ona_id'] ? (int)$ota_ona['ona_id'] : null,
        'turmush_ortogi_id' => $turmush_ortogi_id ? (int)$turmush_ortogi_id : null
    ];
    
    if ($response['ota_id']) {
        $ota_sql = "SELECT ism, familiya FROM shaxslar WHERE id = {$response['ota_id']}";
        $ota_result = db_query($ota_sql);
        if ($ota_result && $ota_result->num_rows > 0) {
            $ota = $ota_result->fetch_assoc();
            $response['ota_ismi'] = tozalash_html($ota['ism']) . ' ' . tozalash_html($ota['familiya']);
        }
    }
    
    if ($response['ona_id']) {
        $ona_sql = "SELECT ism, familiya FROM shaxslar WHERE id = {$response['ona_id']}";
        $ona_result = db_query($ona_sql);
        if ($ona_result && $ona_result->num_rows > 0) {
            $ona = $ona_result->fetch_assoc();
            $response['ona_ismi'] = tozalash_html($ona['ism']) . ' ' . tozalash_html($ona['familiya']);
        }
    }
    
    if ($response['turmush_ortogi_id']) {
        $to_sql = "SELECT ism, familiya FROM shaxslar WHERE id = {$response['turmush_ortogi_id']}";
        $to_result = db_query($to_sql);
        if ($to_result && $to_result->num_rows > 0) {
            $to = $to_result->fetch_assoc();
            $response['turmush_ortogi_ismi'] = tozalash_html($to['ism']) . ' ' . tozalash_html($to['familiya']);
        }
    }
    
    $farzandlar = farzandlar_olish($id);
    $response['farzandlar_soni'] = is_array($farzandlar) ? count($farzandlar) : 0;

    // GALEREYA
    $galereya = [];
    try {
        $gal_sql = "SELECT fayl FROM shaxs_galereya WHERE shaxs_id = $id";
        $gal_res = db_query($gal_sql);
        if ($gal_res) {
            while ($g_row = $gal_res->fetch_assoc()) {
                $galereya[] = $g_row['fayl'];
            }
        }
    } catch (Exception $e) {}
    $response['galereya'] = $galereya;


    // ==========================================
    // TIMELINE (VAQT O'QI) YARATISH
    // ==========================================
    $timeline = [];

    // 1. Tug'ilgan sana
    if (!empty($shaxs['tugilgan_sana']) && $shaxs['tugilgan_sana'] != '0000-00-00') {
        $timeline[] = [
            'is_custom' => false,
            'sana' => $shaxs['tugilgan_sana'],
            'yil' => substr($shaxs['tugilgan_sana'], 0, 4),
            'sarlavha' => 'Tug\'ilgan',
            'matn' => $shaxs['tugilgan_joy'] ? tozalash_html($shaxs['tugilgan_joy']) . 'da dunyoga kelgan' : '',
            'icon' => 'fa-baby',
            'color' => '#48c78e'
        ];
    }

    // 2. Farzandlarining tug'ilishi
    if (is_array($farzandlar) && count($farzandlar) > 0) {
        usort($farzandlar, function($a, $b) {
            $da = !empty($a['tugilgan_sana']) && $a['tugilgan_sana'] != '0000-00-00' ? $a['tugilgan_sana'] : '9999-12-31';
            $db = !empty($b['tugilgan_sana']) && $b['tugilgan_sana'] != '0000-00-00' ? $b['tugilgan_sana'] : '9999-12-31';
            return strcmp($da, $db);
        });

        $f_tartib = 1;
        foreach ($farzandlar as $f) {
            if (!empty($f['tugilgan_sana']) && $f['tugilgan_sana'] != '0000-00-00') {
                $f_ism = tozalash_html($f['ism']) . ' ' . tozalash_html($f['familiya']);
                $timeline[] = [
                    'is_custom' => false,
                    'sana' => $f['tugilgan_sana'],
                    'yil' => substr($f['tugilgan_sana'], 0, 4),
                    'sarlavha' => $f_tartib . '-farzandi tug\'ildi',
                    'matn' => $f_ism . ' dunyoga keldi',
                    'icon' => 'fa-child',
                    'color' => '#f5b042'
                ];
            }
            $f_tartib++;
        }
    }

    // 3. Maxsus qo'shilgan voqealar (To'y, O'qish...)
    try {
        $v_sql = "SELECT * FROM shaxs_voqealar WHERE shaxs_id = $id";
        $v_res = db_query($v_sql);
        if ($v_res) {
            while ($vr = $v_res->fetch_assoc()) {
                // Toza, buzilmagan ma'lumotlarni yuboramiz. Tugmalarni JS da chizamiz.
                $timeline[] = [
                    'is_custom' => true,
                    'voqea_id' => $vr['id'],
                    'sana' => $vr['sana'],
                    'yil' => substr($vr['sana'], 0, 4),
                    'sarlavha' => tozalash_html($vr['sarlavha']),
                    'matn' => tozalash_html($vr['matn']),
                    'icon' => $vr['icon'] ?: 'fa-star', 
                    'color' => $vr['color'] ?: '#9b59b6' 
                ];
            }
        }
    } catch (Exception $e) {}

    // 4. Vafot etgan sana
    if (!empty($shaxs['vafot_sana']) && $shaxs['vafot_sana'] != '0000-00-00') {
        $timeline[] = [
            'is_custom' => false,
            'sana' => $shaxs['vafot_sana'],
            'yil' => substr($shaxs['vafot_sana'], 0, 4),
            'sarlavha' => 'Vafot etgan',
            'matn' => '',
            'icon' => 'fa-dove',
            'color' => '#f45656'
        ];
    }

    // Barcha eventlarni sanasiga qarab aniq xronologik tartiblash
    usort($timeline, function($a, $b) {
        return strcmp($a['sana'], $b['sana']);
    });

    $response['timeline'] = $timeline;
    // ==========================================
    
    echo json_encode(['success' => true, 'data' => $response], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>