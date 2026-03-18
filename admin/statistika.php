<?php
// =============================================
// FILE: admin/statistika.php
// MAQSAD: Oila haqida batafsil statistika va diagrammalar
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shajara_functions.php';

// Sessiyani tekshirish
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Admin kirishini tekshirish
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// ==========================================
// INTERAKTIV FILTRLAR VA KESHLASH (CACHING)
// ==========================================

// Keshni qo'lda tozalash
if (isset($_GET['refresh'])) {
    unset($_SESSION['tree_depth_cache']);
    unset($_SESSION['tree_depth_time']);
    header("Location: statistika.php");
    exit;
}

$f_jins = isset($_GET['f_jins']) ? sanitize($_GET['f_jins']) : '';
$f_holat = isset($_GET['f_holat']) ? $_GET['f_holat'] : '';

$base_where = "1=1";
if ($f_jins !== '') {
    $base_where .= " AND jins = '$f_jins'";
}
if ($f_holat !== '') {
    $base_where .= " AND tirik = " . (int)$f_holat;
}

// Asosiy statistikalarni filtr bilan hisoblash
$sql_stats = "SELECT 
    COUNT(*) as jami,
    SUM(CASE WHEN tirik = 1 THEN 1 ELSE 0 END) as tirik,
    SUM(CASE WHEN tirik = 0 THEN 1 ELSE 0 END) as vafot,
    SUM(CASE WHEN jins = 'erkak' THEN 1 ELSE 0 END) as erkak,
    SUM(CASE WHEN jins = 'ayol' THEN 1 ELSE 0 END) as ayol
    FROM shaxslar WHERE $base_where";
$res_stats = db_query($sql_stats);
$stats_filtered = $res_stats->fetch_assoc();

$stats = [
    'jami' => $stats_filtered['jami'] ?? 0,
    'tirik' => $stats_filtered['tirik'] ?? 0,
    'vafot' => $stats_filtered['vafot'] ?? 0,
    'jins' => [
        'erkak' => $stats_filtered['erkak'] ?? 0,
        'ayol' => $stats_filtered['ayol'] ?? 0
    ]
];

// Yosh guruhlari bo'yicha statistika
$yosh_guruhlari = [
    '0-10' => 0, '11-20' => 0, '21-30' => 0, '31-40' => 0,
    '41-50' => 0, '51-60' => 0, '61-70' => 0, '71+' => 0
];

$sql = "SELECT tugilgan_sana, tirik FROM shaxslar WHERE tugilgan_sana IS NOT NULL AND $base_where";
$result = db_query($sql);
$yosh_malumotlari = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yosh = yosh_hisoblash($row['tugilgan_sana']);
        if (is_numeric($yosh)) {
            $yosh_malumotlari[] = $yosh;
            if ($yosh <= 10) $yosh_guruhlari['0-10']++;
            elseif ($yosh <= 20) $yosh_guruhlari['11-20']++;
            elseif ($yosh <= 30) $yosh_guruhlari['21-30']++;
            elseif ($yosh <= 40) $yosh_guruhlari['31-40']++;
            elseif ($yosh <= 50) $yosh_guruhlari['41-50']++;
            elseif ($yosh <= 60) $yosh_guruhlari['51-60']++;
            elseif ($yosh <= 70) $yosh_guruhlari['61-70']++;
            else $yosh_guruhlari['71+']++;
        }
    }
}

// Tug'ilgan oylar bo'yicha statistika
$oylar = ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];
$oylar_soni = array_fill(0, 12, 0);
$sql = "SELECT MONTH(tugilgan_sana) as oy, COUNT(*) as soni FROM shaxslar WHERE tugilgan_sana IS NOT NULL AND $base_where GROUP BY oy";
$result = db_query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $oylar_soni[$row['oy'] - 1] = $row['soni'];
    }
}

// Davrlar (o'n yilliklar) bo'yicha demografik o'sish diagrammasi
$davrlar = [];
$davrlar_soni = [];
// Yilning o'zini qoldirish uchun '-yillar' ni olib tashladik
$sql = "SELECT CONCAT(FLOOR(YEAR(tugilgan_sana)/10)*10) as davr, COUNT(*) as soni 
        FROM shaxslar 
        WHERE tugilgan_sana IS NOT NULL AND $base_where 
        GROUP BY FLOOR(YEAR(tugilgan_sana)/10)*10 
        ORDER BY FLOOR(YEAR(tugilgan_sana)/10)*10 ASC";
$result = db_query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $davrlar[] = $row['davr'];
        $davrlar_soni[] = $row['soni'];
    }
}

// O'rtacha yosh (tiriklar uchun)
$sql = "SELECT AVG(YEAR(CURDATE()) - YEAR(tugilgan_sana)) as ortacha FROM shaxslar WHERE tirik = 1 AND tugilgan_sana IS NOT NULL AND $base_where";
$result = db_query($sql);
$ortacha_yosh = round($result->fetch_assoc()['ortacha'] ?? 0);

// Eng keksa va eng yosh
$sql = "SELECT id, ism, familiya, tugilgan_sana, YEAR(CURDATE()) - YEAR(tugilgan_sana) as yosh 
        FROM shaxslar WHERE tirik = 1 AND tugilgan_sana IS NOT NULL AND $base_where
        ORDER BY tugilgan_sana ASC LIMIT 1";
$result = db_query($sql);
$eng_keksa = $result->fetch_assoc();
if ($eng_keksa) {
    $eng_keksa['ism'] = html_entity_decode($eng_keksa['ism'], ENT_QUOTES, 'UTF-8');
    $eng_keksa['familiya'] = html_entity_decode($eng_keksa['familiya'], ENT_QUOTES, 'UTF-8');
}

$sql = "SELECT id, ism, familiya, tugilgan_sana, YEAR(CURDATE()) - YEAR(tugilgan_sana) as yosh 
        FROM shaxslar WHERE tirik = 1 AND tugilgan_sana IS NOT NULL AND $base_where
        ORDER BY tugilgan_sana DESC LIMIT 1";
$result = db_query($sql);
$eng_yosh = $result->fetch_assoc();
if ($eng_yosh) {
    $eng_yosh['ism'] = html_entity_decode($eng_yosh['ism'], ENT_QUOTES, 'UTF-8');
    $eng_yosh['familiya'] = html_entity_decode($eng_yosh['familiya'], ENT_QUOTES, 'UTF-8');
}

// KESHLASH (Caching): Oila daraxti chuqurligini faqat bir marta hisoblaymiz (kesh 1 soat)
if (!isset($_SESSION['tree_depth_cache']) || (time() - $_SESSION['tree_depth_time'] > 3600)) {
    $sql = "WITH RECURSIVE avlodlar AS (
                SELECT id, 1 as daraja FROM shaxslar 
                WHERE id NOT IN (SELECT ota_id FROM oilaviy_bogliqlik WHERE ota_id IS NOT NULL)
                UNION ALL
                SELECT s.id, a.daraja + 1
                FROM shaxslar s
                INNER JOIN oilaviy_bogliqlik ob ON s.id = ob.shaxs_id
                INNER JOIN avlodlar a ON ob.ota_id = a.id OR ob.ona_id = a.id
            )
            SELECT MAX(daraja) as maks FROM avlodlar";
    $result = db_query($sql);
    $_SESSION['tree_depth_cache'] = $result->fetch_assoc()['maks'] ?? 1;
    $_SESSION['tree_depth_time'] = time();
}
$avlodlar_soni = $_SESSION['tree_depth_cache'];

// Eslatmalar statistikasi
$sql = "SELECT 
            COUNT(*) as jami,
            SUM(CASE WHEN eslatma_turi = 'tugilgan_kun' THEN 1 ELSE 0 END) as tugilgan_kun,
            SUM(CASE WHEN eslatma_sana >= CURDATE() THEN 1 ELSE 0 END) as kelgusi
        FROM eslatmalar WHERE eslatma_berilsin = 1";
$result = db_query($sql);
$eslatma_stats = $result->fetch_assoc();

// O'lim statistikasi
$sql = "SELECT 
            COUNT(*) as jami_vafot,
            AVG(YEAR(vafot_sana) - YEAR(tugilgan_sana)) as ortacha_umr
        FROM shaxslar WHERE tirik = 0 AND tugilgan_sana IS NOT NULL AND vafot_sana IS NOT NULL AND $base_where";
$result = db_query($sql);
$olum_stats = $result->fetch_assoc();

// YOSH KATTALAR (eng keksa 10 kishi - FOTO VA JINS HAM OLINADI)
$sql = "SELECT id, ism, familiya, jins, foto, tugilgan_sana, YEAR(CURDATE()) - YEAR(tugilgan_sana) as yosh 
        FROM shaxslar WHERE tirik = 1 AND tugilgan_sana IS NOT NULL AND $base_where
        ORDER BY tugilgan_sana ASC LIMIT 10";
$result = db_query($sql);
$yoshi_kattalar = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['ism'] = html_entity_decode($row['ism'], ENT_QUOTES, 'UTF-8');
        $row['familiya'] = html_entity_decode($row['familiya'], ENT_QUOTES, 'UTF-8');
        $yoshi_kattalar[] = $row;
    }
}

// BARCHA FARZANDLI SHAXSLAR
$sql = "SELECT 
            o.id as ota_id, o.ism as ota_ism, o.familiya as ota_familiya, o.jins as ota_jins, o.foto as ota_foto,
            onA.id as ona_id, onA.ism as ona_ism, onA.familiya as ona_familiya, onA.jins as ona_jins, onA.foto as ona_foto,
            COUNT(DISTINCT ob.shaxs_id) as farzandlar_soni
        FROM oilaviy_bogliqlik ob
        LEFT JOIN shaxslar o ON ob.ota_id = o.id
        LEFT JOIN shaxslar onA ON ob.ona_id = onA.id
        WHERE ob.ota_id IS NOT NULL OR ob.ona_id IS NOT NULL
        GROUP BY ob.ota_id, ob.ona_id
        HAVING farzandlar_soni > 0
        ORDER BY farzandlar_soni DESC
        LIMIT 10";
$result = db_query($sql);
$er_xotin_farzand = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['ota_ism'] = html_entity_decode($row['ota_ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $row['ota_familiya'] = html_entity_decode($row['ota_familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        $row['ona_ism'] = html_entity_decode($row['ona_ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $row['ona_familiya'] = html_entity_decode($row['ona_familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        $er_xotin_farzand[] = $row;
    }
}

// Yosh farqi
$eng_katta_yosh = !empty($yosh_malumotlari) ? max($yosh_malumotlari) : 0;
$eng_kichik_yosh = !empty($yosh_malumotlari) ? min($yosh_malumotlari) : 0;
$yosh_farqi = $eng_katta_yosh - $eng_kichik_yosh;

// Kelgusi tug'ilgan kunlar
$sql = "SELECT 
            s.id, s.ism, s.familiya, s.jins, s.foto, s.tugilgan_sana,
            DATEDIFF(
                CONCAT(YEAR(CURDATE()), '-', MONTH(s.tugilgan_sana), '-', DAY(s.tugilgan_sana)),
                CURDATE()
            ) as kun_qoldi
        FROM shaxslar s
        WHERE s.tirik = 1 AND $base_where
            AND CONCAT(YEAR(CURDATE()), '-', MONTH(s.tugilgan_sana), '-', DAY(s.tugilgan_sana)) >= CURDATE()
        ORDER BY kun_qoldi ASC
        LIMIT 5";
$result = db_query($sql);
$kelgusi_tugilganlar = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['ism'] = html_entity_decode($row['ism'], ENT_QUOTES, 'UTF-8');
        $row['familiya'] = html_entity_decode($row['familiya'], ENT_QUOTES, 'UTF-8');
        $kelgusi_tugilganlar[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistika | Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .admin-container { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 280px; background: linear-gradient(135deg, #2c3e50, #1a252f); color: white; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; margin-top: 10px; }
        .sidebar-header i { font-size: 48px; color: #48c78e; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu ul { list-style: none; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: #ecf0f1; text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); border-left-color: #48c78e; }
        .sidebar-menu i { width: 25px; margin-right: 15px; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 280px; padding: 30px; max-width: calc(100% - 280px); }

        /* Header */
        .page-header { background: white; padding: 20px 30px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { color: #2c3e50; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        
        .header-actions { display: flex; gap: 10px; }
        .btn-action { padding: 10px 20px; border-radius: 8px; color: white; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .bg-blue { background: #667eea; } .bg-blue:hover { background: #5a67d8; }
        .bg-green { background: #48c78e; } .bg-green:hover { background: #3aa87a; }
        .bg-orange { background: #f5b042; } .bg-orange:hover { background: #e09d30; }

        /* Filter Panel */
        .top-filter-panel { background: white; border-radius: 15px; padding: 15px 30px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
        .filter-form { display: flex; gap: 15px; align-items: center; margin: 0; }
        .filter-form select { padding: 8px 15px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; font-family: inherit; color: #4a5568; cursor: pointer; }
        
        /* 1. TAVSIYA: Tozalash tugmasi yangi dizayni */
        .btn-clear-filter {
            padding: 8px 16px;
            background: #fff1f1;
            color: #e63946;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            border: 1px solid #ffe4e6;
        }
        .btn-clear-filter:hover {
            background: #e63946;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.2);
        }
        .btn-clear-filter i { transition: transform 0.4s ease; }
        .btn-clear-filter:hover i { transform: rotate(180deg); }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 12px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 18px 10px; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.03); position: relative; overflow: hidden;}
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(102,126,234,0.12); }
        .stat-icon { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 12px; transition: transform 0.3s ease; }
        .stat-card:hover .stat-icon { transform: scale(1.1) rotate(5deg); }
        .stat-icon.blue { background: #667eea20; color: #667eea; }
        .stat-icon.green { background: #48c78e20; color: #48c78e; }
        .stat-icon.orange { background: #f5b04220; color: #f5b042; }
        .stat-icon.purple { background: #764ba220; color: #764ba2; }
        .stat-info h3 { color: #7f8c8d; font-size: 11px; font-weight: 600; margin-bottom: 5px; line-height: 1.2; }
        .stat-info .number { font-size: 24px; font-weight: 800; color: #2c3e50; line-height: 1; }
        .stat-card .trend { margin-top: 8px; font-size: 11px; display: flex; align-items: center; justify-content: center; gap: 4px; color: #95a5a6; font-weight: 500; }

        /* Chart Container */
        .charts-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .chart-container { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: all 0.3s ease;}
        .chart-container:hover { box-shadow: 0 10px 30px rgba(102,126,234,0.15); transform: translateY(-3px); }
        .chart-container h3 { color: #2c3e50; margin-bottom: 20px; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .chart-container h3 i { color: #667eea; }
        .chart-container:hover h3 i { animation: pulse 1s infinite; }
        .chart-container canvas { width: 100%; height: 260px; max-height: 260px; }

        /* Lists Grid */
        .lists-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .list-container { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: all 0.3s ease;}
        .list-container:hover { box-shadow: 0 10px 30px rgba(102,126,234,0.15); transform: translateY(-3px); }
        .list-container h3 { color: #2c3e50; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .list-container:hover h3 i { animation: bounce 1s infinite; }
        .list-items { list-style: none; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; transition: background 0.2s, padding-left 0.3s; border-radius: 8px;}
        .list-item:hover { background: #f8fafc; padding-left: 8px; }
        .list-item:last-child { border-bottom: none; }
        
        .item-name { font-weight: 500; color: #2c3e50; font-size: 13.5px; display: flex; align-items: center; gap: 5px; }
        .item-name i { color: #cbd5e1; font-size: 12px; }
        
        .stat-link { color: #2c3e50; text-decoration: none; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .stat-link:hover { color: #667eea; }
        
        .item-count { background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 12px; transition: all 0.3s; }
        .list-item:hover .item-count { background: #667eea; color: white; transform: scale(1.05); }

        .er-xotin { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .er-xotin .er { color: #3b82f6; text-decoration: none;}
        .er-xotin .er:hover { text-decoration: underline; }
        .er-xotin .xotin { color: #ec4899; text-decoration: none;}
        .er-xotin .xotin:hover { text-decoration: underline; }
        .plus-icon { color: #94a3b8; font-size: 10px; }

        /* Mini Avatar - 4. TAVSIYA: Rasmlar chiqishi uchun */
        .mini-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-right: 5px;}
        .mini-avatar-placeholder { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-right: 5px;}
        .mini-avatar-placeholder.erkak { background: #3b82f6; }
        .mini-avatar-placeholder.ayol { background: #ec4899; }

        /* Summary Cards */
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .summary-card { background: #f8fafc; border-radius: 16px; padding: 20px; text-align: center; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .summary-card:hover { background: #fff; transform: translateY(-5px); box-shadow: 0 10px 25px rgba(102,126,234,0.1); border-color: #cbd5e1; }
        .summary-card h4 { color: #64748b; font-size: 14px; margin-bottom: 10px; font-weight: 600; }
        .summary-card h4 i { font-size: 20px; display: block; margin-bottom: 8px; color: #94a3b8; transition: transform 0.3s; }
        .summary-card:hover h4 i { transform: scale(1.2); color: #667eea; }
        .summary-value { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 5px; }
        .summary-label { color: #94a3b8; font-size: 12px; }

        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }

        /* PDF Export uchun */
        #exportArea { position: relative; width: 100%; }

        @media (max-width: 1400px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
            .charts-row { grid-template-columns: repeat(2, 1fr); }
            .lists-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; max-width: 100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-row, .lists-grid, .summary-grid { grid-template-columns: 1fr; }
            .top-filter-panel { flex-direction: column; gap: 15px; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar no-print">
            <div class="sidebar-header">
                <i class="fas fa-tree"></i>
                <h2>Admin Panel</h2>
                <p>Oila Shajarasi</p>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li><a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="shaxslar.php"><i class="fas fa-users"></i> Shaxslar</a></li>
                    <li><a href="qoshish.php"><i class="fas fa-plus-circle"></i> Yangi qo'shish</a></li>
                    <li><a href="boglash.php"><i class="fas fa-link"></i> Ota-ona bog'lash</a></li>
                    <li><a href="eslatmalar.php"><i class="fas fa-bell"></i> Eslatmalar</a></li>
                    <li><a href="statistika.php" class="active"><i class="fas fa-chart-bar"></i> Statistika</a></li>
                    <li><a href="sozlamalar.php"><i class="fas fa-cog"></i> Sozlamalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div id="exportArea">
                <div class="page-header no-print">
                    <h1><i class="fas fa-chart-line"></i> Oilaviy statistika</h1>
                    <div class="header-actions">
                        <a href="?refresh=1" class="btn-action bg-orange"><i class="fas fa-sync-alt"></i> Keshni yangilash</a>
                        <button onclick="exportToPDF()" class="btn-action bg-green"><i class="fas fa-file-pdf"></i> PDF Yuklash</button>
                        <a href="index.php" class="btn-action bg-blue"><i class="fas fa-arrow-left"></i> Dashboard</a>
                    </div>
                </div>

                <div class="top-filter-panel no-print">
                    <div style="font-weight: 600; color: #475569;"><i class="fas fa-filter" style="color: #667eea;"></i> Statistikani filtrlash:</div>
                    <form method="GET" class="filter-form">
                        <select name="f_jins" onchange="this.form.submit()">
                            <option value="">Barcha jinslar</option>
                            <option value="erkak" <?php echo $f_jins == 'erkak' ? 'selected' : ''; ?>>Faqat Erkaklar</option>
                            <option value="ayol" <?php echo $f_jins == 'ayol' ? 'selected' : ''; ?>>Faqat Ayollar</option>
                        </select>
                        <select name="f_holat" onchange="this.form.submit()">
                            <option value="">Barcha holat</option>
                            <option value="1" <?php echo $f_holat === '1' ? 'selected' : ''; ?>>Faqat Tiriklar</option>
                            <option value="0" <?php echo $f_holat === '0' ? 'selected' : ''; ?>>Faqat Vafot etganlar</option>
                        </select>
                        <?php if($f_jins !== '' || $f_holat !== ''): ?>
                            <a href="statistika.php" class="btn-clear-filter">
                                <i class="fas fa-redo-alt"></i> Tozalash
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <h3>Jami shaxslar</h3>
                            <div class="number"><?php echo $stats['jami']; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-arrow-up" style="color: #48c78e;"></i> Oila a'zolari</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-heart"></i></div>
                        <div class="stat-info">
                            <h3>Tiriklar</h3>
                            <div class="number"><?php echo $stats['tirik']; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-check" style="color: #48c78e;"></i> Hozir tirik</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fas fa-dove"></i></div>
                        <div class="stat-info">
                            <h3>Vafot etganlar</h3>
                            <div class="number"><?php echo $stats['vafot']; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-clock" style="color: #7f8c8d;"></i> Xotira</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-venus-mars"></i></div>
                        <div class="stat-info">
                            <h3>Erkak / Ayol</h3>
                            <div class="number" style="font-size:20px; margin-top:4px;"><?php echo $stats['jins']['erkak']; ?> / <?php echo $stats['jins']['ayol']; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-balance-scale"></i> Nisbat: <?php echo round(max($stats['jins']['erkak'], 1) / max($stats['jins']['ayol'], 1), 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-calendar"></i></div>
                        <div class="stat-info">
                            <h3>O'rtacha yosh</h3>
                            <div class="number"><?php echo $ortacha_yosh; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-chart-line"></i> Umumiy yosh</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-sitemap"></i></div>
                        <div class="stat-info">
                            <h3>Avlodlar soni</h3>
                            <div class="number"><?php echo $avlodlar_soni; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-tree"></i> Daraxt asosi</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fas fa-bell"></i></div>
                        <div class="stat-info">
                            <h3>Faol eslatmalar</h3>
                            <div class="number"><?php echo $eslatma_stats['jami'] ?? 0; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-check-circle" style="color: #48c78e;"></i> Barchasi</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3>Kelgusi tadbirlar</h3>
                            <div class="number"><?php echo $eslatma_stats['kelgusi'] ?? 0; ?></div>
                        </div>
                        <div class="trend"><i class="fas fa-hourglass-half"></i> 30 kun ichida</div>
                    </div>
                </div>

                <div class="charts-row">
                    <div class="chart-container">
                        <h3><i class="fas fa-chart-pie"></i> Jins bo'yicha taqsimot</h3>
                        <canvas id="jinsChart"></canvas>
                        <div style="text-align: center; margin-top: 15px; display: flex; justify-content: center; gap: 30px; font-size: 14px;">
                            <div><span style="color: #3b82f6; font-weight: 700;">Erkak:</span> <?php echo $stats['jins']['erkak'] ?? 0; ?></div>
                            <div><span style="color: #ec4899; font-weight: 700;">Ayol:</span> <?php echo $stats['jins']['ayol'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <h3><i class="fas fa-chart-bar"></i> Tug'ilgan oylar bo'yicha</h3>
                        <canvas id="oylarChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3><i class="fas fa-chart-line"></i> Davrlar bo'yicha o'sish</h3>
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <div class="lists-grid">
                    <div class="list-container">
                        <h3><i class="fas fa-crown" style="color:#f5b042;"></i> Yoshi kattalar</h3>
                        <ul class="list-items">
                            <?php if (!empty($yoshi_kattalar)): ?>
                                <?php foreach ($yoshi_kattalar as $item): ?>
                                <li class="list-item">
                                    <a href="shaxslar.php?search=<?php echo urlencode($item['ism']); ?>" class="stat-link">
                                        <?php if (!empty($item['foto'])): ?>
                                            <img src="../assets/uploads/<?php echo htmlspecialchars($item['foto']); ?>" class="mini-avatar" alt="">
                                        <?php else: ?>
                                            <div class="mini-avatar-placeholder <?php echo $item['jins']; ?>">
                                                <i class="fas fa-<?php echo $item['jins'] == 'erkak' ? 'male' : 'female'; ?>"></i>
                                            </div>
                                        <?php endif; ?>
                                        <?php echo $item['ism'] . ' ' . $item['familiya']; ?>
                                    </a>
                                    <span class="item-count"><?php echo $item['yosh']; ?> yosh</span>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-item">Ma'lumot yo'q</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="list-container">
                        <h3><i class="fas fa-users" style="color:#667eea;"></i> Farzandli shaxslar</h3>
                        <ul class="list-items">
                            <?php if (!empty($er_xotin_farzand)): ?>
                                <?php foreach ($er_xotin_farzand as $item): ?>
                                <li class="list-item">
                                    <div class="er-xotin">
                                        <?php if ($item['ota_id'] && $item['ona_id']): ?>
                                            <?php if (!empty($item['ota_foto'])): ?>
                                                <img src="../assets/uploads/<?php echo htmlspecialchars($item['ota_foto']); ?>" class="mini-avatar" style="width:24px; height:24px;" alt="">
                                            <?php else: ?>
                                                <div class="mini-avatar-placeholder erkak" style="width:24px; height:24px; font-size:10px;"><i class="fas fa-male"></i></div>
                                            <?php endif; ?>
                                            <a href="shaxslar.php?search=<?php echo urlencode($item['ota_ism']); ?>" class="stat-link er"><?php echo $item['ota_ism']; ?></a>
                                            
                                            <i class="fas fa-plus plus-icon"></i>
                                            
                                            <?php if (!empty($item['ona_foto'])): ?>
                                                <img src="../assets/uploads/<?php echo htmlspecialchars($item['ona_foto']); ?>" class="mini-avatar" style="width:24px; height:24px;" alt="">
                                            <?php else: ?>
                                                <div class="mini-avatar-placeholder ayol" style="width:24px; height:24px; font-size:10px;"><i class="fas fa-female"></i></div>
                                            <?php endif; ?>
                                            <a href="shaxslar.php?search=<?php echo urlencode($item['ona_ism']); ?>" class="stat-link xotin"><?php echo $item['ona_ism']; ?></a>
                                            
                                        <?php elseif ($item['ota_id']): ?>
                                            <?php if (!empty($item['ota_foto'])): ?>
                                                <img src="../assets/uploads/<?php echo htmlspecialchars($item['ota_foto']); ?>" class="mini-avatar" style="width:24px; height:24px;" alt="">
                                            <?php else: ?>
                                                <div class="mini-avatar-placeholder erkak" style="width:24px; height:24px; font-size:10px;"><i class="fas fa-male"></i></div>
                                            <?php endif; ?>
                                            <a href="shaxslar.php?search=<?php echo urlencode($item['ota_ism']); ?>" class="stat-link er"><?php echo $item['ota_ism'] . ' ' . $item['ota_familiya']; ?></a>
                                            
                                        <?php elseif ($item['ona_id']): ?>
                                            <?php if (!empty($item['ona_foto'])): ?>
                                                <img src="../assets/uploads/<?php echo htmlspecialchars($item['ona_foto']); ?>" class="mini-avatar" style="width:24px; height:24px;" alt="">
                                            <?php else: ?>
                                                <div class="mini-avatar-placeholder ayol" style="width:24px; height:24px; font-size:10px;"><i class="fas fa-female"></i></div>
                                            <?php endif; ?>
                                            <a href="shaxslar.php?search=<?php echo urlencode($item['ona_ism']); ?>" class="stat-link xotin"><?php echo $item['ona_ism'] . ' ' . $item['ona_familiya']; ?></a>
                                        <?php endif; ?>
                                    </div>
                                    <span class="item-count" style="background:#e0f2fe; color:#0f172a; border-color:#cbd5e1;"><?php echo $item['farzandlar_soni']; ?> ta</span>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-item">Ma'lumot yo'q</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="list-container">
                        <h3><i class="fas fa-birthday-cake" style="color:#ec4899;"></i> Kelgusi tug'ilgan kunlar</h3>
                        <ul class="list-items">
                            <?php if (!empty($kelgusi_tugilganlar)): ?>
                                <?php foreach ($kelgusi_tugilganlar as $item): ?>
                                <li class="list-item">
                                    <a href="shaxslar.php?search=<?php echo urlencode($item['ism']); ?>" class="stat-link">
                                        <?php if (!empty($item['foto'])): ?>
                                            <img src="../assets/uploads/<?php echo htmlspecialchars($item['foto']); ?>" class="mini-avatar" alt="">
                                        <?php else: ?>
                                            <div class="mini-avatar-placeholder <?php echo $item['jins']; ?>">
                                                <i class="fas fa-<?php echo $item['jins'] == 'erkak' ? 'male' : 'female'; ?>"></i>
                                            </div>
                                        <?php endif; ?>
                                        <?php echo $item['ism'] . ' ' . $item['familiya']; ?>
                                    </a>
                                    <span class="item-count" style="background:#fef3c7; color:#b45309; border-color:#fde68a;"><?php echo $item['kun_qoldi']; ?> kun</span>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-item">Ma'lumot yo'q</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-card">
                        <h4><i class="fas fa-user-tie"></i> Eng keksa</h4>
                        <?php if ($eng_keksa): ?>
                            <div class="summary-value"><?php echo $eng_keksa['yosh']; ?></div>
                            <div class="summary-label"><?php echo $eng_keksa['ism'] . ' ' . $eng_keksa['familiya']; ?></div>
                        <?php else: ?>
                            <div class="summary-value">-</div>
                        <?php endif; ?>
                    </div>
                    <div class="summary-card">
                        <h4><i class="fas fa-child"></i> Eng yosh</h4>
                        <?php if ($eng_yosh): ?>
                            <div class="summary-value"><?php echo $eng_yosh['yosh']; ?></div>
                            <div class="summary-label"><?php echo $eng_yosh['ism'] . ' ' . $eng_yosh['familiya']; ?></div>
                        <?php else: ?>
                            <div class="summary-value">-</div>
                        <?php endif; ?>
                    </div>
                    <div class="summary-card">
                        <h4><i class="fas fa-heartbeat"></i> O'rtacha umr</h4>
                        <div class="summary-value"><?php echo round($olum_stats['ortacha_umr'] ?? 0); ?></div>
                        <div class="summary-label">yosh (vafot etganlar)</div>
                    </div>
                    <div class="summary-card">
                        <h4><i class="fas fa-arrows-alt-v"></i> Yosh farqi</h4>
                        <div class="summary-value"><?php echo $yosh_farqi; ?></div>
                        <div class="summary-label">eng katta farq</div>
                    </div>
                </div>
            </div> </div>
    </div>

    <script>
        // PDF Export Funksiyasi (Sig'may qolish muammosi to'liq hal qilingan holati)
        function exportToPDF() {
            const element = document.getElementById('exportArea');
            
            // Vaqtinchalik o'lcham beramiz, shunda hamma elementlar PDF ga to'liq tushadi (kengligi 1200px qilib olinadi)
            const originalWidth = element.style.width;
            element.style.width = '1200px'; 
            
            const opt = {
                margin:       10,
                filename:     'Shajara_Statistikasi.pdf',
                image:        { type: 'jpeg', quality: 1 },
                html2canvas:  { scale: 2, useCORS: true, windowWidth: 1200 }, 
                jsPDF:        { unit: 'mm', format: 'a3', orientation: 'landscape' } 
            };
            
            // Print qilish paytida keraksiz knopkalarni yashiramiz
            document.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');
            
            html2pdf().set(opt).from(element).save().then(() => {
                // PDF saqlangach joyiga qaytaramiz
                document.querySelectorAll('.no-print').forEach(el => el.style.display = '');
                element.style.width = originalWidth; // O'lchamni joyiga qaytarish
            });
        }

        // ChartJS Plugin: Barcha diagrammalarda raqam va foizlarni grafik ustiga yozuvchi plagin
        const chartValuePlugin = {
            id: 'chartValuePlugin',
            afterDatasetsDraw(chart) {
                const {ctx} = chart;
                const dataset = chart.data.datasets[0];
                if (!dataset) return;

                const total = (dataset.data || []).reduce((a, b) => a + Number(b || 0), 0);

                chart.getDatasetMeta(0).data.forEach((element, index) => {
                    const value = Number(dataset.data[index] || 0);
                    if (!value && chart.config.type !== 'line') return; // Line bo'lmasa 0 ni chizmaymiz

                    ctx.save();
                    ctx.font = 'bold 12px Segoe UI';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    if (chart.config.type === 'bar') {
                        // Bar chart ustida yozuv (Top sig'maslik muammosini grace yechadi)
                        ctx.fillStyle = '#475569';
                        ctx.fillText(String(value), element.x, element.y - 10);
                    } else if (chart.config.type === 'doughnut') {
                        // Doughnut ichida foiz bilan yozuv
                        const percent = total ? Math.round((value / total) * 100) : 0;
                        const pos = element.tooltipPosition();
                        ctx.fillStyle = '#ffffff';
                        ctx.shadowColor = 'rgba(0,0,0,0.5)';
                        ctx.shadowBlur = 4;
                        ctx.fillText(value + ' (' + percent + '%)', pos.x, pos.y);
                    }
                    // Eslatma: Line chart (Trend) uchun raqam maxsus "lineLabelWithConnector" plagini orqali chiziladi.
                    ctx.restore();
                });
            }
        };
        Chart.register(chartValuePlugin);

        // 1. Jins diagrammasi
        const jinsCtx = document.getElementById('jinsChart').getContext('2d');
        new Chart(jinsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Erkak', 'Ayol'],
                datasets: [{
                    data: [<?php echo $stats['jins']['erkak'] ?? 0; ?>, <?php echo $stats['jins']['ayol'] ?? 0; ?>],
                    backgroundColor: ['#3b82f6', '#ec4899'],
                    borderColor: ['#fff', '#fff'],
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                cutout: '55%',
                animation: { animateScale: true, animateRotate: true }
            }
        });

        // 2. Oylar diagrammasi (Grace qo'shildi - yuqori sig'maslik uchun)
        const oylarCtx = document.getElementById('oylarChart').getContext('2d');
        new Chart(oylarCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($oylar); ?>,
                datasets: [{
                    label: "Tug'ilganlar",
                    data: <?php echo json_encode($oylar_soni); ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(context) { return `${context.raw} ta`; } } }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grace: '15%', // Yuqoridan 15% bo'shliq qo'shadi (raqam sig'ishi uchun)
                        ticks: { stepSize: 1, precision: 0 }, 
                        grid: { color: 'rgba(0,0,0,0.05)' } 
                    },
                    x: { grid: { display: false } }
                },
                animation: { duration: 1500, easing: 'easeInOutQuart' }
            }
        });

        // 3. Davrlar o'sish diagrammasi (Line Chart) - YILLAR VA QIZIL CHIZIQ QO'SHILDI
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($davrlar); ?>, // X o'qidagi yozuvlar ('1990', '2000' kabi qilib phpda o'zgartirdik)
                datasets: [{
                    label: "O'sish",
                    data: <?php echo json_encode($davrlar_soni); ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.15)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#8b5cf6',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(context) { return `${context.raw} ta`; } } },
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grace: '25%', // Yuqoridan ko'proq bo'shliq raqamlar sig'ishi uchun
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { stepSize: 1, precision: 0 }
                    },
                    x: { 
                        grid: { 
                            display: true, 
                            drawOnChartArea: false, // O'rtadagi katakchalarni yashiramiz, faqat pastki yozuv uchun chiziqlar qoladi
                        },
                        ticks: {
                            maxRotation: 0, // Yillar yozuvini tekis joylashtirish
                            font: { size: 12, weight: 'bold' }
                        }
                    }
                },
                animation: { duration: 1500, easing: 'easeInOutQuart' }
            },
            // Line Chart uchun maxsus plagin: Raqam va Qizil Chiziq pastga qarab
            plugins: [{
                id: 'lineLabelWithRedConnector',
                afterDatasetsDraw(chart) {
                    if (chart.config.type !== 'line') return;
                    const {ctx, chartArea: {bottom}} = chart;
                    const dataset = chart.data.datasets[0];
                    if (!dataset) return;

                    chart.getDatasetMeta(0).data.forEach((element, index) => {
                        const value = Number(dataset.data[index]);
                        
                        ctx.save();
                        // 1. Qizil chiziq pastga tushishi (Nuqtadan pastki o'qgacha)
                        ctx.beginPath();
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = '#ef4444'; // Qizil rang
                        ctx.moveTo(element.x, element.y);
                        ctx.lineTo(element.x, bottom); // Pastgacha chizish
                        ctx.stroke();

                        // 2. Raqamni chizish
                        ctx.font = 'bold 13px Segoe UI';
                        ctx.fillStyle = '#8b5cf6';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(String(value), element.x, element.y - 18);
                        ctx.restore();
                    });
                }
            }]
        });
    </script>
</body>
</html>