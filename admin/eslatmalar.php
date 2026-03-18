<?php
// =============================================
// FILE: admin/eslatmalar.php
// MAQSAD: Tug'ilgan kun va boshqa eslatmalarni boshqarish
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

// Filtr parametrlari
$filter_turi = isset($_GET['turi']) ? sanitize($_GET['turi']) : '';
$filter_oy = isset($_GET['oy']) ? (int)$_GET['oy'] : 0;
$filter_yil = isset($_GET['yil']) ? (int)$_GET['yil'] : 0;

// SQL so'rovni tuzish (FOTO ni ham bazadan olamiz)
$sql = "SELECT e.*, s.ism, s.familiya, s.jins, s.tugilgan_sana, s.foto 
        FROM eslatmalar e 
        INNER JOIN shaxslar s ON e.shaxs_id = s.id 
        WHERE 1=1";

if (!empty($filter_turi)) {
    $sql .= " AND e.eslatma_turi = '$filter_turi'";
}

if ($filter_oy > 0) {
    $sql .= " AND MONTH(e.eslatma_sana) = $filter_oy";
}

if ($filter_yil > 0) {
    $sql .= " AND YEAR(e.eslatma_sana) = $filter_yil";
}

// YANGILANGAN SARALASH: Eng yaqin kelayotgan sana birinchi chiqadi
$sql .= " ORDER BY 
            CASE WHEN e.eslatma_sana >= CURDATE() THEN 0 ELSE 1 END,
            CASE WHEN e.eslatma_sana >= CURDATE() THEN e.eslatma_sana END ASC,
            CASE WHEN e.eslatma_sana < CURDATE() THEN e.eslatma_sana END DESC";

$result = db_query($sql);
$eslatmalar = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Tutoq belgisi xatosini tozalash (Ismlar va eslatma matni)
        $row['ism'] = html_entity_decode($row['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $row['familiya'] = html_entity_decode($row['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        $row['eslatma_matni'] = html_entity_decode($row['eslatma_matni'] ?? '', ENT_QUOTES, 'UTF-8');
        $eslatmalar[] = $row;
    }
}

// Eslatma qo'shish yoki tahrirlash
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Yangi eslatma qo'shish
        $shaxs_id = (int)$_POST['shaxs_id'];
        $eslatma_turi = sanitize($_POST['eslatma_turi']);
        $eslatma_sana = sanitize($_POST['eslatma_sana']);
        // XATOLIK TUZATILDI: $conn xatosi o'rniga addslashes ishlatamiz
        $eslatma_matni = addslashes($_POST['eslatma_matni'] ?? ''); 
        $eslatma_berilsin = isset($_POST['eslatma_berilsin']) ? 1 : 0;
        
        $insert_sql = "INSERT INTO eslatmalar (shaxs_id, eslatma_turi, eslatma_sana, eslatma_matni, eslatma_berilsin) 
                       VALUES ($shaxs_id, '$eslatma_turi', '$eslatma_sana', '$eslatma_matni', $eslatma_berilsin)";
        
        if (db_query($insert_sql)) {
            $message = "✅ Eslatma muvaffaqiyatli qo'shildi!";
        } else {
            $error = "❌ Eslatma qo'shishda xatolik!";
        }
    } elseif ($action === 'edit') {
        // Eslatmani tahrirlash
        $id = (int)$_POST['id'];
        $eslatma_turi = sanitize($_POST['eslatma_turi']);
        $eslatma_sana = sanitize($_POST['eslatma_sana']);
        // XATOLIK TUZATILDI: $conn xatosi o'rniga addslashes ishlatamiz
        $eslatma_matni = addslashes($_POST['eslatma_matni'] ?? '');
        $eslatma_berilsin = isset($_POST['eslatma_berilsin']) ? 1 : 0;
        
        $update_sql = "UPDATE eslatmalar 
                       SET eslatma_turi = '$eslatma_turi',
                           eslatma_sana = '$eslatma_sana',
                           eslatma_matni = '$eslatma_matni',
                           eslatma_berilsin = $eslatma_berilsin 
                       WHERE id = $id";
        
        if (db_query($update_sql)) {
            $message = "✅ Eslatma muvaffaqiyatli yangilandi!";
        } else {
            $error = "❌ Yangilashda xatolik!";
        }
    } elseif ($action === 'delete') {
        // Eslatmani o'chirish
        $id = (int)$_POST['id'];
        $delete_sql = "DELETE FROM eslatmalar WHERE id = $id";
        
        if (db_query($delete_sql)) {
            $message = "✅ Eslatma o'chirildi!";
        } else {
            $error = "❌ O'chirishda xatolik!";
        }
    } elseif ($action === 'generate') {
        // Tug'ilgan kunlar uchun avtomatik eslatma yaratish
        $yil = date('Y');
        $generate_sql = "INSERT INTO eslatmalar (shaxs_id, eslatma_turi, eslatma_sana, eslatma_matni, eslatma_berilsin)
                         SELECT id, 'tugilgan_kun', CONCAT('$yil', '-', MONTH(tugilgan_sana), '-', DAY(tugilgan_sana)),
                                CONCAT(ism, ' ', familiya, ' ning tug\'ilgan kuni'), 1
                         FROM shaxslar 
                         WHERE tugilgan_sana IS NOT NULL 
                         AND id NOT IN (SELECT shaxs_id FROM eslatmalar WHERE eslatma_turi = 'tugilgan_kun' AND YEAR(eslatma_sana) = $yil)";
        
        if (db_query($generate_sql)) {
            $message = "✅ Tug'ilgan kun eslatmalari yaratildi!";
        } else {
            $error = "❌ Eslatma yaratishda xatolik!";
        }
    }
    
    // Refresh
    header("Location: eslatmalar.php?" . http_build_query($_GET));
    exit;
}

// Barcha shaxslar (select formalar uchun va tozalangan)
$raw_shaxslar = shaxslar_roixati();
$shaxslar = [];
if($raw_shaxslar){
    foreach($raw_shaxslar as $sh){
        $sh['ism'] = html_entity_decode($sh['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $sh['familiya'] = html_entity_decode($sh['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        $shaxslar[] = $sh;
    }
}

// Eslatma turlari
$eslatma_turlari = [
    'tugilgan_kun' => '🎂 Tug\'ilgan kun',
    'vafot_kuni' => '⚰️ Vafot kuni',
    'toy' => '💍 To\'y',
    'boshqa' => '📌 Boshqa'
];

// Statistika
$stats_sql = "SELECT 
                COUNT(*) as jami,
                SUM(CASE WHEN eslatma_berilsin = 1 THEN 1 ELSE 0 END) as aktiv,
                SUM(CASE WHEN eslatma_sana >= CURDATE() THEN 1 ELSE 0 END) as kelgusi,
                SUM(CASE WHEN MONTH(eslatma_sana) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as shu_oy
              FROM eslatmalar";
$stats_result = db_query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eslatmalar boshqaruvi | Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 { font-size: 24px; margin-top: 10px; }
        .sidebar-header i { font-size: 48px; color: #48c78e; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu ul { list-style: none; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: #ecf0f1; text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); border-left-color: #48c78e; }
        .sidebar-menu i { width: 25px; margin-right: 15px; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Header */
        .page-header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 { color: #2c3e50; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .header-actions { display: flex; gap: 15px; flex-wrap: wrap; }

        .add-btn { padding: 12px 25px; background: #48c78e; color: white; border: none; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .add-btn:hover { background: #3aa87a; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(72,199,142,0.3); }
        .generate-btn { padding: 12px 25px; background: #f5b042; color: white; border: none; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .generate-btn:hover { background: #e09d30; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(245,176,66,0.3); }
        .back-btn { padding: 12px 25px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .back-btn:hover { background: #5a67d8; transform: translateX(-5px); }

        /* Xabarlar */
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; animation: slideDown 0.3s ease; }
        .alert-success { background: #48c78e20; color: #48c78e; border-left: 4px solid #48c78e; }
        .alert-error { background: #f4565620; color: #f45656; border-left: 4px solid #f45656; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
        .stat-info h3 { color: #7f8c8d; font-size: 14px; font-weight: 400; margin-bottom: 5px; }
        .stat-info .number { font-size: 28px; font-weight: 700; color: #2c3e50; }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-icon.blue { background: #667eea20; color: #667eea; }
        .stat-icon.green { background: #48c78e20; color: #48c78e; }
        .stat-icon.orange { background: #f5b04220; color: #f5b042; }
        .stat-icon.purple { background: #764ba220; color: #764ba2; }

        /* Filter Section */
        .filter-section { background: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filter-title { color: #2c3e50; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 13px; color: #7f8c8d; font-weight: 500; }
        .filter-group label i { width: 16px; color: #667eea; }
        .filter-group select { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; transition: all 0.3s; background: white; cursor: pointer; }
        .filter-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .filter-actions { display: flex; gap: 10px; align-items: flex-end; }
        .filter-actions button, .filter-actions a { padding: 12px 20px; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.3s; }
        .btn-filter { background: #667eea; color: white; }
        .btn-filter:hover { background: #5a67d8; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.3); }
        .btn-reset { background: #f45656; color: white; }
        .btn-reset:hover { background: #d43f3f; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(244,86,86,0.3); }

        /* Table Container */
        .table-container { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .table-header h2 { color: #2c3e50; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .table-info { color: #7f8c8d; font-size: 14px; background: #f8f9fa; padding: 8px 15px; border-radius: 20px; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { text-align: left; padding: 15px 10px; background: #f8f9fa; color: #2c3e50; font-weight: 600; font-size: 14px; }
        td { padding: 15px 10px; border-bottom: 1px solid #ecf0f1; color: #34495e; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }

        /* Avatar Circle */
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; overflow: hidden; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .avatar-circle.erkak { background: #4299e1; }
        .avatar-circle.ayol { background: #ed64a6; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-circle i { font-size: 20px; color: white; }

        /* Tartib raqam */
        .tartib-raqam { font-weight: 600; color: #667eea; background: #f0f0f0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 14px; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .badge-success { background: #48c78e20; color: #48c78e; }
        .badge-warning { background: #f5b04220; color: #f5b042; }
        .badge-info { background: #667eea20; color: #667eea; }
        .badge-danger { background: #f4565620; color: #f45656; }

        /* YAXSHILANGAN MATN USTUNI */
        .eslatma-matni-box {
            background: #f8fafc;
            color: #334155;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
            border-left: 3px solid #667eea;
            max-width: 400px;
            word-wrap: break-word;
            font-weight: 500;
        }

        .matn-yuq {
            color: #adb5bd;
            font-style: italic;
            font-size: 13px;
        }

        /* Kun qoldi ranglari */
        .kun-qoldi { display: block; font-size: 11px; margin-top: 3px; font-weight: 600; animation: fadeIn 0.3s ease; }
        .kun-qoldi.juda-yakin { color: #f45656; animation: pulse 1.5s infinite; }
        .kun-qoldi.yakin { color: #f5b042; }
        .kun-qoldi.orta { color: #48c78e; }
        .kun-qoldi.uzoq { color: #667eea; }
        .kun-qoldi.bugun { color: #f45656; font-weight: 700; animation: pulse 1s infinite; }
        .kun-qoldi.otgan { color: #999; text-decoration: line-through; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .action-icons { display: flex; gap: 8px; }
        .action-icons button { width: 32px; height: 32px; border: none; background: #f8f9fa; border-radius: 8px; cursor: pointer; color: #667eea; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; }
        .action-icons button:hover { background: #667eea; color: white; transform: translateY(-2px); }
        .action-icons button.delete:hover { background: #f45656; }

        /* Modal stillari */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; animation: modalFadeIn 0.3s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .modal-header { padding: 16px 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .modal-header h2 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .close-btn { width: 32px; height: 32px; background: rgba(255,255,255,0.2); border: none; border-radius: 8px; color: white; font-size: 18px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
        .close-btn:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
        .modal-body { padding: 20px; }

        .confirm-modal .modal-content { max-width: 380px; }
        .confirm-icon { text-align: center; margin-bottom: 20px; }
        .confirm-icon i { font-size: 64px; color: #f5b042; }
        .confirm-title { text-align: center; font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 10px; }
        .confirm-message { text-align: center; color: #7f8c8d; margin-bottom: 25px; font-size: 14px; }
        .confirm-actions { display: flex; gap: 10px; justify-content: center; }
        .confirm-btn { padding: 12px 25px; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; min-width: 120px; justify-content: center; }
        .confirm-btn.yes { background: #48c78e; color: white; }
        .confirm-btn.yes:hover { background: #3aa87a; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(72,199,142,0.3); }
        .confirm-btn.no { background: #e2e8f0; color: #4a5568; }
        .confirm-btn.no:hover { background: #cbd5e0; transform: translateY(-2px); }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 500; }
        .form-group label i { width: 20px; color: #667eea; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; transition: all 0.3s; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }

        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, #48c78e, #3aa87a); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(72,199,142,0.3); }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { flex-direction: column; }
            .btn-filter, .btn-reset { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
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
                    <li><a href="eslatmalar.php" class="active"><i class="fas fa-bell"></i> Eslatmalar</a></li>
                    <li><a href="statistika.php"><i class="fas fa-chart-bar"></i> Statistika</a></li>
                    <li><a href="sozlamalar.php"><i class="fas fa-cog"></i> Sozlamalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-bell"></i> Eslatmalar boshqaruvi</h1>
                <div class="header-actions">
                    <button onclick="openAddModal()" class="add-btn">
                        <i class="fas fa-plus"></i> Yangi eslatma
                    </button>
                    <button onclick="generateReminders()" class="generate-btn">
                        <i class="fas fa-sync"></i> Avtomatik yaratish
                    </button>
                    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Jami eslatmalar</h3>
                        <div class="number"><?php echo $stats['jami'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-bell"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Aktiv eslatmalar</h3>
                        <div class="number"><?php echo $stats['aktiv'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Kelgusi 30 kun</h3>
                        <div class="number"><?php echo $stats['kelgusi'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon orange">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Shu oy</h3>
                        <div class="number"><?php echo $stats['shu_oy'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i> Filtrlar
                </div>
                <form method="GET" action="" id="filterForm">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-tag"></i> Eslatma turi</label>
                            <select name="turi" id="turiSelect">
                                <option value="">Barcha turlar</option>
                                <?php foreach ($eslatma_turlari as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $filter_turi == $key ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Oy</label>
                            <select name="oy" id="oySelect">
                                <option value="">Barcha oylar</option>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $filter_oy == $i ? 'selected' : ''; ?>>
                                        <?php 
                                        $oy_nomi = ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];
                                        echo $oy_nomi[$i-1]; 
                                        ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Yil</label>
                            <select name="yil" id="yilSelect">
                                <option value="">Barcha yillar</option>
                                <?php for($i = date('Y') - 1; $i <= date('Y') + 2; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $filter_yil == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-filter"></i> Filtrlash
                            </button>
                            <a href="eslatmalar.php" class="btn-reset">
                                <i class="fas fa-undo-alt"></i> Tozalash
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Eslatmalar ro'yxati</h2>
                    <div class="table-info">
                        Jami: <strong><?php echo count($eslatmalar); ?></strong> ta
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Rasm</th>
                                <th>Shaxs</th>
                                <th>Eslatma turi</th>
                                <th>Sana</th>
                                <th>Matn</th>
                                <th>Holati</th>
                                <th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($eslatmalar)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 50px; color: #999;">
                                    <i class="fas fa-bell-slash" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                    Hech qanday eslatma topilmadi
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php 
                                $tartib = 1;
                                foreach ($eslatmalar as $eslatma): 
                                    $sana = strtotime($eslatma['eslatma_sana']);
                                    $bugun = time();
                                    $farq = ceil(($sana - $bugun) / 86400);
                                    
                                    $kun_qoldi_class = '';
                                    $kun_qoldi_text = '';
                                    $sana_rangi = '';
                                    
                                    if ($farq > 0 && $farq <= 7) {
                                        $kun_qoldi_class = 'juda-yakin';
                                        $kun_qoldi_text = '🔥 ' . $farq . ' kun qoldi';
                                        $sana_rangi = '#f45656';
                                    } elseif ($farq > 7 && $farq <= 14) {
                                        $kun_qoldi_class = 'yakin';
                                        $kun_qoldi_text = '⚡ ' . $farq . ' kun qoldi';
                                        $sana_rangi = '#f5b042';
                                    } elseif ($farq > 14 && $farq <= 30) {
                                        $kun_qoldi_class = 'orta';
                                        $kun_qoldi_text = '📅 ' . $farq . ' kun qoldi';
                                        $sana_rangi = '#48c78e';
                                    } elseif ($farq > 30) {
                                        $kun_qoldi_class = 'uzoq';
                                        $kun_qoldi_text = '📆 ' . $farq . ' kun qoldi';
                                        $sana_rangi = '#667eea';
                                    } elseif ($farq == 0) {
                                        $kun_qoldi_class = 'bugun';
                                        $kun_qoldi_text = '🎉 BUGUN!';
                                        $sana_rangi = '#f45656';
                                    } elseif ($farq < 0) {
                                        $kun_qoldi_class = 'otgan';
                                        $kun_qoldi_text = '⏰ ' . abs($farq) . ' kun oldin';
                                        $sana_rangi = '#999';
                                    }
                                    
                                    if ($farq < 0 && $eslatma['eslatma_turi'] == 'tugilgan_kun') {
                                        $keyingi_yil = date('Y', strtotime('+1 year')) . substr($eslatma['eslatma_sana'], 4);
                                        $keyingi_sana = strtotime($keyingi_yil);
                                        $keyingi_farq = ceil(($keyingi_sana - $bugun) / 86400);
                                        
                                        if ($keyingi_farq > 0 && $keyingi_farq <= 7) {
                                            $kun_qoldi_class = 'juda-yakin';
                                            $kun_qoldi_text = '➡️ ' . $keyingi_farq . ' kun qoldi';
                                            $sana_rangi = '#f45656';
                                        } elseif ($keyingi_farq > 7 && $keyingi_farq <= 14) {
                                            $kun_qoldi_class = 'yakin';
                                            $kun_qoldi_text = '➡️ ' . $keyingi_farq . ' kun qoldi';
                                            $sana_rangi = '#f5b042';
                                        } elseif ($keyingi_farq > 14 && $keyingi_farq <= 30) {
                                            $kun_qoldi_class = 'orta';
                                            $kun_qoldi_text = '➡️ ' . $keyingi_farq . ' kun qoldi';
                                            $sana_rangi = '#48c78e';
                                        } elseif ($keyingi_farq > 30) {
                                            $kun_qoldi_class = 'uzoq';
                                            $kun_qoldi_text = '➡️ ' . $keyingi_farq . ' kun qoldi';
                                            $sana_rangi = '#667eea';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><div class="tartib-raqam"><?php echo $tartib++; ?></div></td>
                                    
                                    <td>
                                        <div class="avatar-circle <?php echo $eslatma['jins']; ?>">
                                            <?php if (!empty($eslatma['foto'])): ?>
                                                <img src="../assets/uploads/<?php echo $eslatma['foto']; ?>" alt="">
                                            <?php else: ?>
                                                <i class="fas fa-<?php echo $eslatma['jins'] == 'erkak' ? 'male' : 'female'; ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <strong><?php echo $eslatma['ism'] . ' ' . $eslatma['familiya']; ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $eslatma_turlari[$eslatma['eslatma_turi']] ?? $eslatma['eslatma_turi']; ?>
                                        </span>
                                    </td>
                                    <td style="position: relative;">
                                        <div style="font-weight: 500;"><?php echo date('d.m.Y', strtotime($eslatma['eslatma_sana'])); ?></div>
                                        <?php if (!empty($kun_qoldi_text)): ?>
                                            <div class="kun-qoldi <?php echo $kun_qoldi_class; ?>" style="color: <?php echo $sana_rangi; ?>;">
                                                <?php echo $kun_qoldi_text; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php $text = trim($eslatma['eslatma_matni'] ?? ''); ?>
                                        <?php if (!empty($text) && $text !== '-'): ?>
                                            <div class="eslatma-matni-box">
                                                <?php echo htmlspecialchars($text); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="matn-yuq"><i class="fas fa-comment-slash"></i> Matn kiritilmagan</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <span class="badge <?php echo $eslatma['eslatma_berilsin'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $eslatma['eslatma_berilsin'] ? 'Aktiv' : 'O\'chirilgan'; ?>
                                        </span>
                                    </td>
                                    <td class="action-icons">
                                        <button onclick='editEslatma(<?php echo json_encode($eslatma, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Tahrirlash">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteEslatma(<?php echo $eslatma['id']; ?>)" class="delete" title="O'chirish">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="eslatmaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-plus"></i> Yangi eslatma qo'shish</h2>
                <button class="close-btn" onclick="closeModal('eslatmaModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="eslatmaForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="eslatmaId" value="">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Shaxs</label>
                        <select name="shaxs_id" id="shaxsSelect" required>
                            <option value="">-- Shaxsni tanlang --</option>
                            <?php foreach ($shaxslar as $shaxs): ?>
                                <option value="<?php echo $shaxs['id']; ?>">
                                    <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Eslatma turi</label>
                        <select name="eslatma_turi" id="eslatmaTuri" required>
                            <?php foreach ($eslatma_turlari as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Sana</label>
                        <input type="date" name="eslatma_sana" id="eslatmaSana" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Eslatma matni</label>
                        <textarea name="eslatma_matni" id="eslatmaMatni" rows="3" placeholder="Eslatma matni..."></textarea>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="eslatma_berilsin" id="eslatmaBerilsin" checked>
                            <label for="eslatmaBerilsin">Eslatma berilsin</label>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Saqlash
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5b042, #e09d30);">
                <h2><i class="fas fa-question-circle"></i> <span id="confirmTitle">Tasdiqlash</span></h2>
                <button class="close-btn" onclick="closeModal('confirmModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="confirm-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="confirm-title" id="confirmMessage">Siz rostdan ham bu amalni bajarishni xohlaysizmi?</div>
                <div class="confirm-actions">
                    <button class="confirm-btn no" onclick="closeModal('confirmModal')">Bekor qilish</button>
                    <button class="confirm-btn yes" id="confirmActionBtn">Ha, davom et</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global o'zgaruvchilar
        let currentAction = null;
        let currentId = null;

        // Filtrlarni avtomatik submit qilish
        document.getElementById('turiSelect').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('oySelect').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('yilSelect').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // Yangi eslatma qo'shish modalini ochish
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Yangi eslatma qo\'shish';
            document.getElementById('formAction').value = 'add';
            document.getElementById('eslatmaId').value = '';
            document.getElementById('eslatmaForm').reset();
            document.getElementById('eslatmaBerilsin').checked = true;
            openModal('eslatmaModal');
        }

        // Eslatmani tahrirlash
        function editEslatma(data) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Eslatmani tahrirlash';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('eslatmaId').value = data.id;
            document.getElementById('shaxsSelect').value = data.shaxs_id;
            document.getElementById('eslatmaTuri').value = data.eslatma_turi;
            document.getElementById('eslatmaSana').value = data.eslatma_sana;
            
            const txt = document.createElement('textarea');
            txt.innerHTML = data.eslatma_matni || '';
            document.getElementById('eslatmaMatni').value = txt.value;
            
            document.getElementById('eslatmaBerilsin').checked = data.eslatma_berilsin == 1;
            openModal('eslatmaModal');
        }

        // Eslatmani o'chirish
        function deleteEslatma(id) {
            currentAction = 'delete';
            currentId = id;
            document.getElementById('confirmTitle').textContent = 'Eslatmani o\'chirish';
            document.getElementById('confirmMessage').textContent = 'Siz rostdan ham bu eslatmani o\'chirmoqchimisiz? Bu amalni ortga qaytarib bo\'lmaydi!';
            document.getElementById('confirmActionBtn').onclick = confirmDelete;
            openModal('confirmModal');
        }

        // O'chirishni tasdiqlash
        function confirmDelete() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.name = 'id';
            idInput.value = currentId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Avtomatik yaratish
        function generateReminders() {
            currentAction = 'generate';
            document.getElementById('confirmTitle').textContent = 'Avtomatik yaratish';
            document.getElementById('confirmMessage').textContent = 'Barcha tug\'ilgan kunlar uchun eslatma yaratilsinmi?';
            document.getElementById('confirmActionBtn').onclick = confirmGenerate;
            openModal('confirmModal');
        }

        // Yaratishni tasdiqlash
        function confirmGenerate() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'generate';
            
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Modalni ochish
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        // Modalni yopish
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Tashqariga bosish bilan yopish
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>