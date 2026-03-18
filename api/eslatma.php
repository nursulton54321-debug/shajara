<?php
// =============================================
// FILE: api/eslatma.php
// MAQSAD: Tug'ilgan kun va boshqa eslatmalar
// =============================================

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

function eslatma_select_base() {
    return "
        SELECT 
            e.*,
            s.ism,
            s.familiya,
            s.telefon,
            s.telegram_id,
            s.jins,
            s.foto,
            s.tugilgan_sana
        FROM eslatmalar e
        INNER JOIN shaxslar s ON e.shaxs_id = s.id
    ";
}

switch ($method) {
    case 'GET':

        // Bugungi eslatmalar
        if (isset($_GET['bugun']) && $_GET['bugun'] == '1') {
            $bugun = date('Y-m-d');

            $sql = eslatma_select_base() . "
                WHERE e.eslatma_sana = '$bugun'
                  AND e.eslatma_berilsin = 1
                ORDER BY e.eslatma_turi, e.eslatma_sana ASC
            ";

            $result = db_query($sql);
            $eslatmalar = [];

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $eslatmalar[] = $row;
                }
            }

            json_chiqar([
                'success' => true,
                'data' => $eslatmalar,
                'count' => count($eslatmalar)
            ]);
        }

        // Kelgusi eslatmalar (30 kun)
        elseif (isset($_GET['kelgusi'])) {
            $bugun = date('Y-m-d');
            $keyin = date('Y-m-d', strtotime('+30 days'));

            $sql = eslatma_select_base() . "
                , DATEDIFF(e.eslatma_sana, CURDATE()) as necha_kundan_keyin
                WHERE e.eslatma_sana BETWEEN '$bugun' AND '$keyin'
                  AND e.eslatma_berilsin = 1
                ORDER BY e.eslatma_sana ASC, e.eslatma_turi ASC
            ";

            $result = db_query($sql);
            $eslatmalar = [];

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $eslatmalar[] = $row;
                }
            }

            json_chiqar([
                'success' => true,
                'data' => $eslatmalar,
                'count' => count($eslatmalar)
            ]);
        }

        // Shaxsning barcha eslatmalari
        elseif (isset($_GET['shaxs_id'])) {
            $shaxs_id = (int)$_GET['shaxs_id'];

            $sql = eslatma_select_base() . "
                WHERE e.shaxs_id = $shaxs_id
                ORDER BY e.eslatma_sana ASC
            ";

            $result = db_query($sql);
            $eslatmalar = [];

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $eslatmalar[] = $row;
                }
            }

            json_chiqar([
                'success' => true,
                'data' => $eslatmalar,
                'count' => count($eslatmalar)
            ]);
        }

        // Hammasi
        else {
            $sql = eslatma_select_base() . "
                ORDER BY e.eslatma_sana DESC
                LIMIT 200
            ";

            $result = db_query($sql);
            $eslatmalar = [];

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $eslatmalar[] = $row;
                }
            }

            json_chiqar([
                'success' => true,
                'data' => $eslatmalar,
                'count' => count($eslatmalar)
            ]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            $data = $_POST;
        }

        if (empty($data['shaxs_id']) || empty($data['eslatma_turi']) || empty($data['eslatma_sana'])) {
            json_chiqar([
                'success' => false,
                'message' => 'Shaxs ID, eslatma turi va sana majburiy'
            ], 400);
        }

        $shaxs_id = (int)$data['shaxs_id'];
        $eslatma_turi = sanitize($data['eslatma_turi']);
        $eslatma_sana = sanitize($data['eslatma_sana']);
        $eslatma_matni = isset($data['eslatma_matni']) ? sanitize($data['eslatma_matni']) : '';
        $eslatma_berilsin = isset($data['eslatma_berilsin']) ? (int)$data['eslatma_berilsin'] : 1;

        if (!sana_tekshir($eslatma_sana)) {
            json_chiqar([
                'success' => false,
                'message' => 'Sana formati noto\'g\'ri'
            ], 400);
        }

        $sql = "
            INSERT INTO eslatmalar (shaxs_id, eslatma_turi, eslatma_sana, eslatma_matni, eslatma_berilsin)
            VALUES ($shaxs_id, '$eslatma_turi', '$eslatma_sana', '$eslatma_matni', $eslatma_berilsin)
        ";

        if (db_query($sql)) {
            $id = db_connect()->insert_id;

            json_chiqar([
                'success' => true,
                'message' => 'Eslatma qo\'shildi',
                'id' => $id
            ], 201);
        } else {
            json_chiqar([
                'success' => false,
                'message' => 'Eslatma qo\'shishda xatolik'
            ], 500);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            json_chiqar([
                'success' => false,
                'message' => 'Eslatma ID si kerak'
            ], 400);
        }

        $id = (int)$_GET['id'];
        $sql = "DELETE FROM eslatmalar WHERE id = $id";

        if (db_query($sql)) {
            json_chiqar([
                'success' => true,
                'message' => 'Eslatma o\'chirildi'
            ]);
        } else {
            json_chiqar([
                'success' => false,
                'message' => 'O\'chirishda xatolik'
            ], 500);
        }
        break;

    default:
        json_chiqar([
            'success' => false,
            'message' => 'Ruxsat etilmagan metod'
        ], 405);
}
?>