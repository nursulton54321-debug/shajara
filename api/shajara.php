<?php
// =============================================
// FILE: api/shajara.php — YAKUNIY TUZATILGAN
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shajara_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
ini_set('display_errors', 0);
error_reporting(0);

function toMalumot($to_id) {
    if (!$to_id) return null;
    $to_id = (int)$to_id;

    $r = db_query("SELECT id, ism, familiya, jins, tugilgan_sana, vafot_sana, tirik, foto
                   FROM shaxslar
                   WHERE id = $to_id
                   LIMIT 1");

    if (!$r || $r->num_rows === 0) return null;

    $row = $r->fetch_assoc();

    return [
        'id'            => (int)$row['id'],
        'ism'           => $row['ism'] ?? '',
        'familiya'      => $row['familiya'] ?? '',
        'jins'          => $row['jins'] ?? 'erkak',
        'tugilgan_sana' => (!empty($row['tugilgan_sana']) && $row['tugilgan_sana'] !== '0000-00-00') ? $row['tugilgan_sana'] : null,
        'vafot_sana'    => (!empty($row['vafot_sana']) && $row['vafot_sana'] !== '0000-00-00') ? $row['vafot_sana'] : null,
        'tirik'         => (bool)$row['tirik'],
        'foto'          => $row['foto'] ?: null,
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Faqat GET');
    }

    // Qidiruv
    if (isset($_GET['qidiruv'])) {
        $q = trim($_GET['qidiruv']);
        if (strlen($q) < 1) {
            echo json_encode(['success'=>false,'message'=>'Juda qisqa'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $q = sanitize($q);
        $r = db_query("SELECT id, ism, familiya, jins, tugilgan_sana, vafot_sana, foto
                       FROM shaxslar
                       WHERE ism LIKE '%$q%' OR familiya LIKE '%$q%'
                       ORDER BY tugilgan_sana ASC
                       LIMIT 20");

        $list = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $list[] = $row;
            }
        }

        echo json_encode(['success'=>true,'data'=>$list], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Barcha shaxslar
    if (isset($_GET['barcha'])) {
        $r = db_query("SELECT id, ism, familiya, jins, tugilgan_sana, vafot_sana, tirik, foto
                       FROM shaxslar
                       ORDER BY tugilgan_sana ASC, ism ASC");

        $nodes = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $nodes[$row['id']] = [
                    'id'                => (int)$row['id'],
                    'ism'               => $row['ism'],
                    'familiya'          => $row['familiya'],
                    'jins'              => $row['jins'],
                    'tugilgan_sana'     => (!empty($row['tugilgan_sana']) && $row['tugilgan_sana'] !== '0000-00-00') ? $row['tugilgan_sana'] : null,
                    'vafot_sana'        => (!empty($row['vafot_sana']) && $row['vafot_sana'] !== '0000-00-00') ? $row['vafot_sana'] : null,
                    'tirik'             => (bool)$row['tirik'],
                    'foto'              => $row['foto'] ?: null,
                    'turmush_ortogi_id' => null,
                    'turmush_ortogi'    => null,
                    'children'          => [],
                ];
            }
        }

        $r2 = db_query("SELECT shaxs_id, ota_id, ona_id, turmush_ortogi_id FROM oilaviy_bogliqlik");
        $parentOf  = [];
        $spouseMap = [];
        $assigned  = [];

        if ($r2) {
            while ($row = $r2->fetch_assoc()) {
                $sid = (int)$row['shaxs_id'];
                $oid = $row['ota_id'] ? (int)$row['ota_id'] : null;
                $nid = $row['ona_id'] ? (int)$row['ona_id'] : null;
                $tid = $row['turmush_ortogi_id'] ? (int)$row['turmush_ortogi_id'] : null;

                if ($oid || $nid) {
                    $parentOf[$sid] = ['ota'=>$oid,'ona'=>$nid];
                }

                if ($tid && isset($nodes[$sid]) && isset($nodes[$tid])) {
                    if (!$nodes[$sid]['turmush_ortogi_id']) {
                        $nodes[$sid]['turmush_ortogi_id'] = $tid;
                        $nodes[$sid]['turmush_ortogi'] = toMalumot($tid);
                    }
                    if (!$nodes[$tid]['turmush_ortogi_id']) {
                        $nodes[$tid]['turmush_ortogi_id'] = $sid;
                        $nodes[$tid]['turmush_ortogi'] = toMalumot($sid);
                    }

                    $k = min($sid,$tid).'_'.max($sid,$tid);
                    if (!isset($spouseMap[$k])) {
                        $spouseMap[$k] = ['id1'=>min($sid,$tid),'id2'=>max($sid,$tid)];
                    }
                }
            }
        }

        // Farzandlarni ota bo'yicha, bo'lmasa ona bo'yicha bog'lash
        foreach ($parentOf as $sid => $po) {
            if (!isset($nodes[$sid]) || isset($assigned[$sid])) continue;

            $pid = $po['ota'] ?? $po['ona'];
            if ($pid && isset($nodes[$pid])) {
                $nodes[$pid]['children'][] = &$nodes[$sid];
                $assigned[$sid] = true;
            }
        }

        // Rootlarni topish
        $roots = [];
        foreach ($nodes as $id => &$node) {
            if (!isset($assigned[$id])) {
                $roots[] = &$node;
            }
        }
        unset($node);

        if (empty($roots)) {
            echo json_encode(['success'=>false,'message'=>'Shaxslar topilmadi'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (count($roots) === 1) {
            $daraxt = $roots[0];
        } else {
            usort($roots, function($a, $b){
                $ca = count($a['children'] ?? []);
                $cb = count($b['children'] ?? []);
                if ($ca === $cb) {
                    $da = $a['tugilgan_sana'] ?? '9999-12-31';
                    $db = $b['tugilgan_sana'] ?? '9999-12-31';
                    return strcmp($da, $db);
                }
                return $cb - $ca;
            });
            $daraxt = $roots[0];
        }

        echo json_encode([
            'success' => true,
            'data'    => [
                'daraxt'  => $daraxt,
                'spouses' => array_values($spouseMap)
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Bitta shaxs
    if (!isset($_GET['id'])) {
        throw new Exception('ID kerak');
    }

    $id = (int)$_GET['id'];
    $max_depth = isset($_GET['chuqurlik']) ? (int)$_GET['chuqurlik'] : 10;

    if (!shaxs_olish($id)) {
        throw new Exception('Shaxs topilmadi');
    }

    $visited = [];

    function buildTree($shaxs_id, $depth, $max_depth) {
        global $visited;

        if ($depth > $max_depth || isset($visited[$shaxs_id])) return null;
        $visited[$shaxs_id] = true;

        $s = shaxs_olish($shaxs_id);
        if (!$s) return null;

        $to_id = turmush_ortogi_olish($shaxs_id);

        $node = [
            'id'                => (int)$s['id'],
            'ism'               => $s['ism'],
            'familiya'          => $s['familiya'],
            'jins'              => $s['jins'],
            'tugilgan_sana'     => (!empty($s['tugilgan_sana']) && $s['tugilgan_sana'] !== '0000-00-00') ? $s['tugilgan_sana'] : null,
            'vafot_sana'        => (!empty($s['vafot_sana']) && $s['vafot_sana'] !== '0000-00-00') ? $s['vafot_sana'] : null,
            'tirik'             => (bool)$s['tirik'],
            'foto'              => $s['foto'] ?: null,
            'turmush_ortogi_id' => $to_id ? (int)$to_id : null,
            'turmush_ortogi'    => $to_id ? toMalumot($to_id) : null,
            'children'          => [],
        ];

        $r = db_query("SELECT s.id
                       FROM shaxslar s
                       INNER JOIN oilaviy_bogliqlik ob ON s.id = ob.shaxs_id
                       WHERE ob.ota_id = $shaxs_id OR ob.ona_id = $shaxs_id
                       ORDER BY s.tugilgan_sana ASC, s.ism ASC");

        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $child = buildTree((int)$row['id'], $depth+1, $max_depth);
                if ($child) $node['children'][] = $child;
            }
        }

        return $node;
    }

    $daraxt = buildTree($id, 0, $max_depth);

    $spouseMap2 = [];
    $r = db_query("SELECT shaxs_id, turmush_ortogi_id
                   FROM oilaviy_bogliqlik
                   WHERE turmush_ortogi_id IS NOT NULL");

    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $s1 = (int)$row['shaxs_id'];
            $s2 = (int)$row['turmush_ortogi_id'];
            $k  = min($s1,$s2).'_'.max($s1,$s2);
            if (!isset($spouseMap2[$k])) {
                $spouseMap2[$k] = ['id1'=>min($s1,$s2),'id2'=>max($s1,$s2)];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'daraxt'  => $daraxt,
            'spouses' => array_values($spouseMap2)
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>