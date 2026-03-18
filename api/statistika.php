<?php
// =============================================
// FILE: api/statistika.php
// MAQSAD: Oila statistikasi va diagrammalar uchun ma'lumotlar
// =============================================

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shajara_functions.php';

header('Content-Type: application/json; charset=utf-8');

// Faqat GET so'rovlariga ruxsat
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_chiqar([
        'success' => false,
        'message' => 'Faqat GET so\'rovlariga ruxsat'
    ], 405);
}

// Asosiy statistika
$stats = oila_statistikasi();

// Yosh guruhlari bo'yicha statistika
$yosh_guruhlari = [
    '0-10' => 0,
    '11-20' => 0,
    '21-30' => 0,
    '31-40' => 0,
    '41-50' => 0,
    '51-60' => 0,
    '61+' => 0
];

$sql = "SELECT tugilgan_sana FROM shaxslar WHERE tirik = 1 AND tugilgan_sana IS NOT NULL";
$result = db_query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yosh = yosh_hisoblash($row['tugilgan_sana']);
        if (is_numeric($yosh)) {
            if ($yosh <= 10) $yosh_guruhlari['0-10']++;
            elseif ($yosh <= 20) $yosh_guruhlari['11-20']++;
            elseif ($yosh <= 30) $yosh_guruhlari['21-30']++;
            elseif ($yosh <= 40) $yosh_guruhlari['31-40']++;
            elseif ($yosh <= 50) $yosh_guruhlari['41-50']++;
            elseif ($yosh <= 60) $yosh_guruhlari['51-60']++;
            else $yosh_guruhlari['61+']++;
        }
    }
}

// Oiladagi eng keng tarqalgan ismlar
$eng_ismlar = [];
$sql = "SELECT ism, COUNT(*) as soni FROM shaxslar GROUP BY ism ORDER BY soni DESC LIMIT 10";
$result = db_query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $eng_ismlar[] = $row;
    }
}

// Oiladagi eng keng tarqalgan familiyalar
$eng_familiyalar = [];
$sql = "SELECT familiya, COUNT(*) as soni FROM shaxslar GROUP BY familiya ORDER BY soni DESC LIMIT 10";
$result = db_query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $eng_familiyalar[] = $row;
    }
}

// Tug'ilgan oylar bo'yicha statistika
$tugilgan_oylar = array_fill(1, 12, 0);
$sql = "SELECT MONTH(tugilgan_sana) as oy, COUNT(*) as soni FROM shaxslar WHERE tugilgan_sana IS NOT NULL GROUP BY oy";
$result = db_query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tugilgan_oylar[$row['oy']] = (int)$row['soni'];
    }
}

// So'nggi qo'shilganlar
$songi_qoshilganlar = [];
$sql = "SELECT id, ism, familiya, created_at FROM shaxslar ORDER BY created_at DESC LIMIT 10";
$result = db_query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $songi_qoshilganlar[] = $row;
    }
}

json_chiqar([
    'success' => true,
    'data' => [
        'umumiy' => $stats,
        'yosh_guruhlari' => $yosh_guruhlari,
        'eng_ismlar' => $eng_ismlar,
        'eng_familiyalar' => $eng_familiyalar,
        'tugilgan_oylar' => $tugilgan_oylar,
        'songi_qoshilganlar' => $songi_qoshilganlar,
        'diagrammalar' => [
            'jins' => [
                'labels' => ['Erkak', 'Ayol'],
                'data' => [$stats['jins']['erkak'] ?? 0, $stats['jins']['ayol'] ?? 0]
            ],
            'yosh' => [
                'labels' => array_keys($yosh_guruhlari),
                'data' => array_values($yosh_guruhlari)
            ],
            'oylar' => [
                'labels' => ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyun', 'Iyul', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'],
                'data' => array_values($tugilgan_oylar)
            ]
        ]
    ]
]);
?>