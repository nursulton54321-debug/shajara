<?php
// =============================================
// FILE: api/tree_data.php
// MAQSAD: Kasb va Telefon raqamlarini ham json qilib jo'natish
// =============================================

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

dbConnect();
global $db;

$res = db_query("SELECT s.*, o.ota_id, o.ona_id, o.turmush_ortogi_id FROM shaxslar s LEFT JOIN oilaviy_bogliqlik o ON s.id = o.shaxs_id");

$tree_nodes = [];

while($p = $res->fetch_assoc()) {
    $ism = html_entity_decode($p['ism'], ENT_QUOTES, 'UTF-8');
    $familiya = html_entity_decode($p['familiya'], ENT_QUOTES, 'UTF-8');
    $kasb = html_entity_decode($p['kasbi'] ?? '', ENT_QUOTES, 'UTF-8');
    $telefon = html_entity_decode($p['telefon'] ?? '', ENT_QUOTES, 'UTF-8');
    
    $yili = (!empty($p['tugilgan_sana']) && $p['tugilgan_sana'] != '0000-00-00') ? date('d.m.Y', strtotime($p['tugilgan_sana'])) : '';
    $vafot = (!empty($p['vafot_sana']) && $p['vafot_sana'] != '0000-00-00') ? date('d.m.Y', strtotime($p['vafot_sana'])) : '';
    
    $yillar = '';
    if ($yili && $vafot) { $yillar = "$yili - $vafot"; } 
    elseif ($yili) { $yillar = $yili; } 
    else { $yillar = "Noma'lum"; }

    $yosh = '';
    if ($yili) {
        $dob = new DateTime($p['tugilgan_sana']);
        if (!empty($p['vafot_sana']) && $p['vafot_sana'] != '0000-00-00') {
            $dod = new DateTime($p['vafot_sana']);
            $yosh = $dob->diff($dod)->y;
        } else {
            $yosh = $dob->diff(new DateTime())->y;
        }
    }
    
    $ota = (int)$p['ota_id'];
    $ona = (int)$p['ona_id'];
    $parent_id = ($ota > 0) ? $ota : (($ona > 0) ? $ona : null);
    $spouse_id = (int)$p['turmush_ortogi_id'];
    $photo = $p['foto'] ? 'assets/uploads/' . $p['foto'] : '';

    $tree_nodes[$p['id']] = [
        'id' => $p['id'],
        'name' => trim($ism . ' ' . $familiya),
        'first_name' => trim($ism),
        'last_name' => trim($familiya),
        'gender' => $p['jins'],
        'photo' => $photo,
        'lifespan' => $yillar,
        'age' => $yosh !== '' ? $yosh : '',
        'raw_dob' => $p['tugilgan_sana'] ?: '9999-99-99',
        'parent_id' => $parent_id,
        'spouse_id' => ($spouse_id > 0) ? $spouse_id : null,
        'phone' => $telefon,
        'profession' => $kasb,
        'spouse_name' => '',
        'spouse_first_name' => '',
        'spouse_last_name' => '',
        'spouse_lifespan' => '',
        'spouse_age' => '',
        'spouse_photo' => '',
        'spouse_phone' => '',
        'spouse_profession' => '',
        'is_spouse_attached' => false,
        'primary_spouse_id' => null,
        'child_order' => '',
        'children' => []
    ];
}

foreach(array_keys($tree_nodes) as $id) {
    $sp_id = $tree_nodes[$id]['spouse_id'];
    if ($sp_id && isset($tree_nodes[$sp_id]) && empty($tree_nodes[$id]['is_spouse_attached'])) {
        $tree_nodes[$id]['spouse_name'] = $tree_nodes[$sp_id]['name'];
        $tree_nodes[$id]['spouse_first_name'] = $tree_nodes[$sp_id]['first_name'];
        $tree_nodes[$id]['spouse_last_name'] = $tree_nodes[$sp_id]['last_name'];
        $tree_nodes[$id]['spouse_lifespan'] = $tree_nodes[$sp_id]['lifespan'];
        $tree_nodes[$id]['spouse_age'] = $tree_nodes[$sp_id]['age'];
        $tree_nodes[$id]['spouse_photo'] = $tree_nodes[$sp_id]['photo'];
        $tree_nodes[$id]['spouse_phone'] = $tree_nodes[$sp_id]['phone'];
        $tree_nodes[$id]['spouse_profession'] = $tree_nodes[$sp_id]['profession'];
        
        $tree_nodes[$sp_id]['is_spouse_attached'] = true;
        $tree_nodes[$sp_id]['primary_spouse_id'] = $id;
    }
}

$roots = [];
foreach(array_keys($tree_nodes) as $id) {
    if (!empty($tree_nodes[$id]['is_spouse_attached'])) continue; 

    $p_id = $tree_nodes[$id]['parent_id'];
    if ($p_id && isset($tree_nodes[$p_id])) {
        if (!empty($tree_nodes[$p_id]['is_spouse_attached'])) {
            $p_id = $tree_nodes[$p_id]['primary_spouse_id'];
        }
        $tree_nodes[$p_id]['children'][] = &$tree_nodes[$id];
    } else {
        $roots[] = &$tree_nodes[$id];
    }
}

function sortChildrenByAge(&$nodes, $is_root = false) {
    usort($nodes, function($a, $b) { return strcmp($a['raw_dob'], $b['raw_dob']); });
    $order = 1;
    foreach ($nodes as &$node) {
        if (!$is_root) { $node['child_order'] = $order++; } 
        else { $node['child_order'] = ''; }
        if (!empty($node['children'])) sortChildrenByAge($node['children'], false);
    }
}
sortChildrenByAge($roots, true);

if (count($roots) === 1) { $final_tree = $roots[0]; } 
else { $final_tree = [ "id" => "hidden_root", "name" => "hidden", "children" => $roots ]; }

echo json_encode($final_tree, JSON_UNESCAPED_UNICODE);
exit;
?>