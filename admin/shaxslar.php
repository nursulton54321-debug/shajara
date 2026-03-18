<?php
// =============================================
// FILE: admin/shaxslar.php
// MAQSAD: Barcha shaxslar ro'yxati
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

// Filtr va qidiruv parametrlari
$search = isset($_GET['search']) ? $_GET['search'] : '';
$jins_filter = isset($_GET['jins']) ? $_GET['jins'] : '';
$tirik_filter = isset($_GET['tirik']) ? $_GET['tirik'] : '';

// Sort parametrlari - DEFAULT YOSH BO'YICHA KICHIKDAN KATTA GA
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'tugilgan_sana';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc'; // asc = kichikdan kattaga (yosh)

// Yosh bo'yicha tartiblash (tug'ilgan sana bo'yicha)
if ($sort == 'yosh') {
    $sort = 'tugilgan_sana';
}

// SQL so'rov
$sql = "SELECT s.*, 
        (SELECT CONCAT(ism, ' ', familiya) FROM shaxslar WHERE id = ob.ota_id) as ota_ismi,
        (SELECT CONCAT(ism, ' ', familiya) FROM shaxslar WHERE id = ob.ona_id) as ona_ismi,
        (SELECT CONCAT(ism, ' ', familiya) FROM shaxslar WHERE id = ob.turmush_ortogi_id) as turmush_ortogi_ismi
        FROM shaxslar s
        LEFT JOIN oilaviy_bogliqlik ob ON s.id = ob.shaxs_id
        WHERE 1=1";

// Qidiruv
if (!empty($search)) {
    $sql .= " AND (s.ism LIKE '%$search%' 
                   OR s.familiya LIKE '%$search%' 
                   OR s.otasining_ismi LIKE '%$search%')";
}

// Jins filtri
if (!empty($jins_filter)) {
    $sql .= " AND s.jins = '$jins_filter'";
}

// Tiriklik filtri
if ($tirik_filter !== '') {
    $sql .= " AND s.tirik = $tirik_filter";
}

// Sortirovka
$sql .= " ORDER BY s.$sort $order";

$result = db_query($sql);
$shaxslar = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // MUAMMO YECHIMI: Barcha ismlardagi &#039; va shunga o'xshash belgilarni normal holatga qaytarish
        $row['ism'] = html_entity_decode($row['ism'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['familiya'] = html_entity_decode($row['familiya'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['otasining_ismi'] = html_entity_decode($row['otasining_ismi'] ?: '-', ENT_QUOTES, 'UTF-8');
        $row['ota_ismi'] = html_entity_decode($row['ota_ismi'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['ona_ismi'] = html_entity_decode($row['ona_ismi'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['turmush_ortogi_ismi'] = html_entity_decode($row['turmush_ortogi_ismi'] ?: '', ENT_QUOTES, 'UTF-8');
        
        $shaxslar[] = $row;
    }
}

$total = count($shaxslar);
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shaxslar ro'yxati | Admin Panel</title>
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

        .sidebar-header h2 {
            font-size: 24px;
            margin-top: 10px;
        }

        .sidebar-header i {
            font-size: 48px;
            color: #48c78e;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #48c78e;
        }

        .sidebar-menu i {
            width: 25px;
            margin-right: 15px;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

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

        .page-header h1 {
            color: #2c3e50;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .add-btn {
            padding: 12px 25px;
            background: #48c78e;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-weight: 600;
        }

        .add-btn:hover {
            background: #3aa87a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(72, 199, 142, 0.3);
        }

        .back-btn {
            padding: 12px 25px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #5a67d8;
            transform: translateX(-5px);
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-title {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 500;
        }

        .filter-group label i {
            width: 16px;
            color: #667eea;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .btn-filter {
            padding: 12px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-filter:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .btn-reset {
            padding: 12px 25px;
            background: #f45656;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-reset:hover {
            background: #d43f3f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244,86,86,0.3);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            color: #2c3e50;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-info {
            color: #7f8c8d;
            font-size: 14px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1300px;
        }

        th {
            text-align: left;
            padding: 15px 10px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        th:hover {
            background: #e9ecef;
        }

        th a {
            color: #2c3e50;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        th a:hover {
            color: #667eea;
        }

        th i {
            font-size: 12px;
            color: #667eea;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #ecf0f1;
            color: #34495e;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .tartib-raqam {
            font-weight: 600;
            color: #667eea;
            background: #f0f0f0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 14px;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            overflow: hidden;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .avatar-circle.erkak {
            background: #4299e1;
        }

        .avatar-circle.ayol {
            background: #ed64a6;
        }

        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-circle i {
            font-size: 20px;
            color: white;
        }

        .jins-erkak {
            color: #4299e1;
            font-weight: 600;
            background: #4299e110;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
        }

        .jins-ayol {
            color: #ed64a6;
            font-weight: 600;
            background: #ed64a610;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success {
            background: #48c78e20;
            color: #48c78e;
        }

        .badge-danger {
            background: #f4565620;
            color: #f45656;
        }

        .badge-warning {
            background: #f5b04220;
            color: #f5b042;
        }

        .action-icons {
            display: flex;
            gap: 8px;
        }

        .action-icons a {
            color: #667eea;
            text-decoration: none;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.3s;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .action-icons a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .action-icons a.delete:hover {
            background: #f45656;
        }

        .vafot-yoshi {
            font-size: 11px;
            color: #999;
            display: block;
        }

        /* Modal stillari */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 420px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        .close-btn {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .close-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 20px;
        }

        /* Confirm Modal */
        .confirm-modal .modal-content {
            max-width: 380px;
        }

        .confirm-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .confirm-icon i {
            font-size: 64px;
            color: #f45656;
        }

        .confirm-title {
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .confirm-message {
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .confirm-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            min-width: 120px;
            justify-content: center;
        }

        .confirm-btn.yes {
            background: #f45656;
            color: white;
        }

        .confirm-btn.yes:hover {
            background: #d43f3f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244,86,86,0.3);
        }

        .confirm-btn.no {
            background: #e2e8f0;
            color: #4a5568;
        }

        .confirm-btn.no:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }

        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 16px;
            overflow: hidden;
            border: 3px solid #667eea;
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-photo .no-photo {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .profile-photo .no-photo.erkak {
            background: #4299e1;
            color: white;
        }

        .profile-photo .no-photo.ayol {
            background: #ed64a6;
            color: white;
        }

        .info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 100px;
            color: #718096;
        }

        .info-value {
            flex: 1;
            color: #2d3748;
            font-weight: 500;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .modal-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .modal-btn.edit {
            background: linear-gradient(135deg, #48c78e, #3aa87a);
            color: white;
        }

        /* Yopish tugmasining aylanib ketish xatosi "close" o'rniga "modal-close-btn" nomlash bilan hal bo'ldi */
        .modal-btn.modal-close-btn {
            background: #e2e8f0;
            color: #4a5568;
            transition: all 0.2s ease;
        }
        .modal-btn.modal-close-btn:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }

        /* --- YANGI QO'SHILGAN PREMIUM DIZAYN STILLARI --- */
        .rel-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 500;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #495057;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            white-space: nowrap;
        }
        .rel-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
            border-color: #dee2e6;
        }
        .rel-badge.spouse {
            background: #fff0f6;
            color: #c2255c;
            border-color: #fcc2d7;
        }
        .rel-badge.spouse i {
            margin-right: 6px;
            font-size: 11px;
            color: #f06595;
        }
        .parents-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }
        .rel-badge.parent {
            justify-content: flex-start;
        }
        .parent-label {
            font-weight: 700;
            margin-right: 8px;
            padding-right: 8px;
            border-right: 1px solid rgba(0,0,0,0.1);
        }
        .parent-label.ota { color: #228be6; }
        .parent-label.ona { color: #e64980; }
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
                    <li><a href="shaxslar.php" class="active"><i class="fas fa-users"></i> Shaxslar</a></li>
                    <li><a href="qoshish.php"><i class="fas fa-plus-circle"></i> Yangi qo'shish</a></li>
                    <li><a href="boglash.php"><i class="fas fa-link"></i> Ota-ona bog'lash</a></li>
                    <li><a href="eslatmalar.php"><i class="fas fa-bell"></i> Eslatmalar</a></li>
                    <li><a href="statistika.php"><i class="fas fa-chart-bar"></i> Statistika</a></li>
                    <li><a href="sozlamalar.php"><i class="fas fa-cog"></i> Sozlamalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-users"></i> Shaxslar ro'yxati</h1>
                <div class="header-actions">
                    <a href="qoshish.php" class="add-btn"><i class="fas fa-plus"></i> Yangi qo'shish</a>
                    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
                </div>
            </div>

            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i> Filtrlar
                </div>
                
                <form method="GET" action="shaxslar.php" id="filterForm">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Qidirish</label>
                            <input type="text" name="search" id="searchInput" placeholder="Ism, familiya..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-venus-mars"></i> Jinsi</label>
                            <select name="jins" id="jinsSelect">
                                <option value="">Barcha</option>
                                <option value="erkak" <?php echo $jins_filter == 'erkak' ? 'selected' : ''; ?>>Erkak</option>
                                <option value="ayol" <?php echo $jins_filter == 'ayol' ? 'selected' : ''; ?>>Ayol</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-heart"></i> Holati</label>
                            <select name="tirik" id="tirikSelect">
                                <option value="">Barcha</option>
                                <option value="1" <?php echo $tirik_filter === '1' ? 'selected' : ''; ?>>Tirik</option>
                                <option value="0" <?php echo $tirik_filter === '0' ? 'selected' : ''; ?>>Vafot etgan</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrlash</button>
                            <a href="shaxslar.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Tozalash</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Shaxslar</h2>
                    <div class="table-info">
                        Jami: <strong><?php echo $total; ?></strong> ta
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Rasm</th>
                                <th><a href="?sort=ism&order=<?php echo $sort == 'ism' && $order == 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&jins=<?php echo urlencode($jins_filter); ?>&tirik=<?php echo urlencode($tirik_filter); ?>">Ism <?php if($sort=='ism'): ?><i class="fas fa-sort-<?php echo $order; ?>"></i><?php endif; ?></a></th>
                                <th><a href="?sort=familiya&order=<?php echo $sort == 'familiya' && $order == 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&jins=<?php echo urlencode($jins_filter); ?>&tirik=<?php echo urlencode($tirik_filter); ?>">Familiya <?php if($sort=='familiya'): ?><i class="fas fa-sort-<?php echo $order; ?>"></i><?php endif; ?></a></th>
                                <th>Otasining ismi</th>
                                <th><a href="?sort=jins&order=<?php echo $sort == 'jins' && $order == 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&jins=<?php echo urlencode($jins_filter); ?>&tirik=<?php echo urlencode($tirik_filter); ?>">Jinsi <?php if($sort=='jins'): ?><i class="fas fa-sort-<?php echo $order; ?>"></i><?php endif; ?></a></th>
                                <th><a href="?sort=tugilgan_sana&order=<?php echo $sort == 'tugilgan_sana' && $order == 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&jins=<?php echo urlencode($jins_filter); ?>&tirik=<?php echo urlencode($tirik_filter); ?>">Tug'ilgan sana <?php if($sort=='tugilgan_sana'): ?><i class="fas fa-sort-<?php echo $order; ?>"></i><?php endif; ?></a></th>
                                <th><a href="?sort=yosh&order=<?php echo $sort == 'yosh' && $order == 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&jins=<?php echo urlencode($jins_filter); ?>&tirik=<?php echo urlencode($tirik_filter); ?>">Yoshi <?php if($sort=='yosh'): ?><i class="fas fa-sort-<?php echo $order; ?>"></i><?php endif; ?></a></th>
                                <th><a href="?sort=tirik&order=<?php echo $sort == 'tirik' && $order == 'asc' ? 'desc' : 'asc'; ?>&search=<?php echo urlencode($search); ?>&jins=<?php echo urlencode($jins_filter); ?>&tirik=<?php echo urlencode($tirik_filter); ?>">Holati <?php if($sort=='tirik'): ?><i class="fas fa-sort-<?php echo $order; ?>"></i><?php endif; ?></a></th>
                                <th>Turmush o'rtog'i</th>
                                <th>Ota-ona</th>
                                <th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shaxslar)): ?>
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 50px; color: #999;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                    Hech qanday shaxs topilmadi
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $tartib = 1; foreach ($shaxslar as $shaxs): 
                                    $yosh = yosh_hisoblash($shaxs['tugilgan_sana']);
                                    $vafot_yoshi = '';
                                    if (!$shaxs['tirik'] && !empty($shaxs['tugilgan_sana']) && !empty($shaxs['vafot_sana'])) {
                                        $tugilgan = new DateTime($shaxs['tugilgan_sana']);
                                        $vafot = new DateTime($shaxs['vafot_sana']);
                                        $vafot_yoshi = $vafot->diff($tugilgan)->y;
                                    }
                                ?>
                                <tr>
                                    <td><div class="tartib-raqam"><?php echo $tartib++; ?></div></td>
                                    <td>
                                        <div class="avatar-circle <?php echo $shaxs['jins']; ?>">
                                            <?php if (!empty($shaxs['foto'])): ?>
                                                <img src="../assets/uploads/<?php echo $shaxs['foto']; ?>" alt="<?php echo htmlspecialchars($shaxs['ism']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-<?php echo $shaxs['jins'] == 'erkak' ? 'male' : 'female'; ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($shaxs['ism']); ?></td>
                                    <td><?php echo htmlspecialchars($shaxs['familiya']); ?></td>
                                    <td><?php echo htmlspecialchars($shaxs['otasining_ismi']); ?></td>
                                    <td><span class="jins-<?php echo $shaxs['jins']; ?>"><?php echo $shaxs['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?></span></td>
                                    <td><?php echo $shaxs['tugilgan_sana'] ? date('d.m.Y', strtotime($shaxs['tugilgan_sana'])) : '-'; ?></td>
                                    <td>
                                        <?php if (is_numeric($yosh)): ?>
                                            <?php echo $yosh; ?> yosh
                                            <?php if (!empty($vafot_yoshi)): ?>
                                                <span class="vafot-yoshi">(vafot: <?php echo $vafot_yoshi; ?> yosh)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php echo $yosh; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo $shaxs['tirik'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $shaxs['tirik'] ? 'Tirik' : 'Vafot etgan'; ?></span></td>
                                    
                                    <td>
                                        <?php if (!empty($shaxs['turmush_ortogi_ismi'])): ?>
                                            <span class="rel-badge spouse"><i class="fas fa-ring"></i> <?php echo htmlspecialchars($shaxs['turmush_ortogi_ismi']); ?></span>
                                        <?php else: ?>
                                            <span style="color:#adb5bd;">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($shaxs['ota_ismi']) || !empty($shaxs['ona_ismi'])): ?>
                                            <div class="parents-stack">
                                                <?php if (!empty($shaxs['ota_ismi'])): ?>
                                                    <span class="rel-badge parent"><span class="parent-label ota">Ota</span> <?php echo htmlspecialchars($shaxs['ota_ismi']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($shaxs['ona_ismi'])): ?>
                                                    <span class="rel-badge parent"><span class="parent-label ona">Ona</span> <?php echo htmlspecialchars($shaxs['ona_ismi']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#adb5bd;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="action-icons">
                                        <a href="javascript:void(0);" onclick="shaxsMalumot(<?php echo $shaxs['id']; ?>)"><i class="fas fa-eye"></i></a>
                                        <a href="tahrirlash.php?id=<?php echo $shaxs['id']; ?>"><i class="fas fa-edit"></i></a>
                                        <a href="javascript:void(0);" onclick="ochirishModal(<?php echo $shaxs['id']; ?>)" class="delete"><i class="fas fa-trash"></i></a>
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

    <div id="shaxsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-circle"></i> <span id="modalShaxsNomi"></span></h2>
                <button class="close-btn" onclick="closeShaxsModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="shaxsModalBody">
                <div class="loading">Yuklanmoqda...</div>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f45656, #d43f3f);">
                <h2><i class="fas fa-exclamation-triangle"></i> O'chirish</h2>
                <button class="close-btn" onclick="closeDeleteModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="confirm-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div class="confirm-title">Haqiqatan ham bu shaxsni o'chirmoqchimisiz?</div>
                <div class="confirm-message" id="deleteMessage">Bu amalni ortga qaytarib bo'lmaydi!</div>
                <div class="confirm-actions">
                    <button class="confirm-btn no" onclick="closeDeleteModal()">Bekor qilish</button>
                    <button class="confirm-btn yes" id="confirmDeleteBtn">O'chirish</button>
                </div>
            </div>
        </div>
    </div>

    <div id="forceDeleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f45656, #d43f3f);">
                <h2><i class="fas fa-exclamation-triangle"></i> Ogohlantirish</h2>
                <button class="close-btn" onclick="closeForceDeleteModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="confirm-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="confirm-title" id="forceDeleteTitle">Bu shaxsning farzandlari bor!</div>
                <div class="confirm-message" id="forceDeleteMessage">Farzandlari bilan birga o'chirilsinmi?</div>
                <div class="confirm-actions">
                    <button class="confirm-btn no" onclick="closeForceDeleteModal()">Bekor qilish</button>
                    <button class="confirm-btn yes" id="confirmForceDeleteBtn">Ha, o'chirish</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // QIDIRISH - yozish davom etsin va avtomatik qidirsin
        let searchTimeout;
        let deleteId = null;
        let forceDeleteId = null;
        
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 800);
        });

        // Jins va holat o'zgarganda
        document.getElementById('jinsSelect').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('tirikSelect').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // O'chirish modalini ochish
        function ochirishModal(id) {
            deleteId = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteId = null;
        }

        // Force delete modalini ochish
        function forceDeleteModal(id, farzandlarSoni) {
            forceDeleteId = id;
            document.getElementById('forceDeleteTitle').textContent = `Bu shaxsning ${farzandlarSoni} ta farzandi bor!`;
            document.getElementById('forceDeleteModal').style.display = 'flex';
        }

        function closeForceDeleteModal() {
            document.getElementById('forceDeleteModal').style.display = 'none';
            forceDeleteId = null;
        }

        // O'chirishni POST metodi orqali yuborish
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (deleteId) {
                let formData = new FormData();
                formData.append('id', deleteId);
                
                fetch(`../api/shaxs.php?id=${deleteId}`, { 
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Shaxs muvaffaqiyatli o\'chirildi');
                        location.reload();
                    } else if (data.farzandlar_soni > 0) {
                        closeDeleteModal();
                        forceDeleteModal(deleteId, data.farzandlar_soni);
                    } else {
                        alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                        closeDeleteModal();
                    }
                })
                .catch(error => {
                    console.error('Xatolik:', error);
                    alert('Xatolik yuz berdi');
                    closeDeleteModal();
                });
            }
        });

        // Force delete ni POST orqali yuborish
        document.getElementById('confirmForceDeleteBtn').addEventListener('click', function() {
            if (forceDeleteId) {
                let forceData = new FormData();
                forceData.append('id', forceDeleteId);
                forceData.append('force', 1);

                fetch(`../api/shaxs.php?id=${forceDeleteId}&force=1`, { 
                    method: 'POST',
                    body: forceData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Shaxs va uning farzandlari o\'chirildi');
                        location.reload();
                    } else {
                        alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                        closeForceDeleteModal();
                    }
                })
                .catch(error => {
                    console.error('Xatolik:', error);
                    alert('Xatolik yuz berdi');
                    closeForceDeleteModal();
                });
            }
        });

        // Modal funksiyalari
        function shaxsMalumot(id) {
            fetch(`../api/shaxs.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const s = data.data;
                        
                        // Ism va familiyalardagi html belgilarni tozalash uchun
                        const ism = document.createElement('textarea'); ism.innerHTML = s.ism;
                        const fam = document.createElement('textarea'); fam.innerHTML = s.familiya;
                        
                        document.getElementById('modalShaxsNomi').textContent = `${ism.value} ${fam.value}`;
                        
                        const jinsIkon = s.jins === 'erkak' ? 'fa-male' : 'fa-female';
                        
                        let fotoHtml = s.foto ? 
                            `<div class="profile-photo"><img src="../assets/uploads/${s.foto}"></div>` :
                            `<div class="profile-photo"><div class="no-photo ${s.jins}"><i class="fas ${jinsIkon}"></i></div></div>`;
                        
                        const otaIsmi = document.createElement('textarea'); otaIsmi.innerHTML = (s.otasining_ismi || '-');
                        const kasb = document.createElement('textarea'); kasb.innerHTML = (s.kasbi || '-');
                        
                        document.getElementById('shaxsModalBody').innerHTML = `
                            ${fotoHtml}
                            <div class="info-card">
                                <div class="info-row"><span class="info-label">ID:</span><span class="info-value">#${s.id}</span></div>
                                <div class="info-row"><span class="info-label">Familiya:</span><span class="info-value">${fam.value}</span></div>
                                <div class="info-row"><span class="info-label">Ism:</span><span class="info-value">${ism.value}</span></div>
                                <div class="info-row"><span class="info-label">Otasining ismi:</span><span class="info-value">${otaIsmi.value}</span></div>
                                <div class="info-row"><span class="info-label">Jinsi:</span><span class="info-value">${s.jins == 'erkak' ? 'Erkak' : 'Ayol'}</span></div>
                                <div class="info-row"><span class="info-label">Tug'ilgan sana:</span><span class="info-value">${s.tugilgan_sana || '-'}</span></div>
                                <div class="info-row"><span class="info-label">Yosh:</span><span class="info-value">${s.yosh || '-'}</span></div>
                                <div class="info-row"><span class="info-label">Holati:</span><span class="info-value">${s.tirik ? 'Tirik' : 'Vafot etgan'}</span></div>
                                <div class="info-row"><span class="info-label">Kasbi:</span><span class="info-value">${kasb.value}</span></div>
                                <div class="info-row"><span class="info-label">Telefon:</span><span class="info-value">${s.telefon || '-'}</span></div>
                            </div>
                            <div class="modal-actions">
                                <button onclick="window.location.href='tahrirlash.php?id=${s.id}'" class="modal-btn edit">Tahrirlash</button>
                                <button onclick="closeShaxsModal()" class="modal-btn modal-close-btn">Yopish</button>
                            </div>
                        `;
                        document.getElementById('shaxsModal').style.display = 'flex';
                    }
                });
        }

        function closeShaxsModal() {
            document.getElementById('shaxsModal').style.display = 'none';
        }

        // Modalni yopish uchun tashqariga bosish
        window.onclick = function(event) {
            const shaxsModal = document.getElementById('shaxsModal');
            const deleteModal = document.getElementById('deleteModal');
            const forceDeleteModal = document.getElementById('forceDeleteModal');
            
            if (event.target == shaxsModal) {
                closeShaxsModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
            if (event.target == forceDeleteModal) {
                closeForceDeleteModal();
            }
        }
    </script>
</body>
</html>