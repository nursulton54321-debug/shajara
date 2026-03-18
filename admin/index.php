<?php
// =============================================
// FILE: admin/index.php
// MAQSAD: Admin panel - asosiy boshqaruv sahifasi
// =============================================

// Asosiy fayllarni ulash
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shajara_functions.php';

// Sessiyani boshlash
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Admin kirishini tekshirish
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// Statistikani olish
$stats = oila_statistikasi();

// So'nggi qo'shilganlar (10 ta)
$sql = "SELECT s.*, 
        (SELECT CONCAT(ism, ' ', familiya) FROM shaxslar WHERE id = ob.ota_id) as ota_ismi,
        (SELECT CONCAT(ism, ' ', familiya) FROM shaxslar WHERE id = ob.ona_id) as ona_ismi,
        (SELECT CONCAT(ism, ' ', familiya) FROM shaxslar WHERE id = ob.turmush_ortogi_id) as turmush_ortogi_ismi
        FROM shaxslar s
        LEFT JOIN oilaviy_bogliqlik ob ON s.id = ob.shaxs_id
        ORDER BY s.created_at DESC LIMIT 10";
$result = db_query($sql);
$songi_shaxslar = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // --- TUTUQ BELGISI MUAMMOSI YECHIMI QO'SHILDI ---
        $row['ism'] = html_entity_decode($row['ism'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['familiya'] = html_entity_decode($row['familiya'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['otasining_ismi'] = html_entity_decode($row['otasining_ismi'] ?: '-', ENT_QUOTES, 'UTF-8');
        $row['ota_ismi'] = html_entity_decode($row['ota_ismi'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['ona_ismi'] = html_entity_decode($row['ona_ismi'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['turmush_ortogi_ismi'] = html_entity_decode($row['turmush_ortogi_ismi'] ?: '', ENT_QUOTES, 'UTF-8');
        // ------------------------------------------------
        
        $songi_shaxslar[] = $row;
    }
}

// Bugungi tug'ilganlar
$bugun = date('m-d');
$sql = "SELECT s.*,
        (SELECT CONCAT(ism, ' ', familiya) FROM shaxslar WHERE id = ob.turmush_ortogi_id) as turmush_ortogi_ismi
        FROM shaxslar s
        LEFT JOIN oilaviy_bogliqlik ob ON s.id = ob.shaxs_id
        WHERE DATE_FORMAT(s.tugilgan_sana, '%m-%d') = '$bugun' 
        ORDER BY s.tugilgan_sana";
$result = db_query($sql);
$bugun_tugilganlar = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // --- TUTUQ BELGISI MUAMMOSI YECHIMI QO'SHILDI ---
        $row['ism'] = html_entity_decode($row['ism'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['familiya'] = html_entity_decode($row['familiya'] ?: '', ENT_QUOTES, 'UTF-8');
        $row['otasining_ismi'] = html_entity_decode($row['otasining_ismi'] ?: '-', ENT_QUOTES, 'UTF-8');
        $row['turmush_ortogi_ismi'] = html_entity_decode($row['turmush_ortogi_ismi'] ?: '', ENT_QUOTES, 'UTF-8');
        // ------------------------------------------------
        
        $bugun_tugilganlar[] = $row;
    }
}

// Eslatmalar soni
$sql = "SELECT COUNT(*) as soni FROM eslatmalar WHERE eslatma_sana >= CURDATE() AND eslatma_berilsin = 1";
$result = db_query($sql);
$eslatmalar_soni = $result->fetch_assoc()['soni'] ?? 0;
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Oila Shajarasi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Admin panel uchun qo'shimcha stillar */
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
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
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
            font-size: 18px;
        }

        .sidebar-menu .badge {
            background: #48c78e;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: auto;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Header */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-left h1 {
            color: #2c3e50;
            font-size: 28px;
        }

        .header-left p {
            color: #7f8c8d;
            margin-top: 5px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notifications {
            position: relative;
        }

        .notifications .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f45656;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .notifications i {
            font-size: 24px;
            color: #7f8c8d;
            cursor: pointer;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .user-details {
            line-height: 1.4;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .user-role {
            font-size: 13px;
            color: #7f8c8d;
        }

        .logout-btn {
            padding: 10px 20px;
            background: #f45656;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #d43f3f;
            transform: translateY(-2px);
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-info h3 {
            color: #7f8c8d;
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 10px;
        }

        .stat-info .number {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { background: #667eea20; color: #667eea; }
        .stat-icon.green { background: #48c78e20; color: #48c78e; }
        .stat-icon.purple { background: #764ba220; color: #764ba2; }
        .stat-icon.red { background: #f4565620; color: #f45656; }

        /* Action Buttons */
        .action-buttons {
            margin-bottom: 30px;
        }

        .btn-add {
            background: #48c78e;
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(72, 199, 142, 0.3);
        }

        .btn-add:hover {
            background: #3aa87a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(72, 199, 142, 0.4);
        }

        .btn-secondary {
            background: #667eea;
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            margin-left: 15px;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Table Container */
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
        }

        .table-header h2 {
            color: #2c3e50;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .view-all {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 10px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
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

        /* Tartib raqam */
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

        /* Rasm uchun stil - PEOPLE ICONS */
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
        }

        .action-icons a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .action-icons a.delete:hover {
            background: #f45656;
        }

        /* Birthday List */
        .birthday-list {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .birthday-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .birthday-header i {
            color: #f5b042;
            font-size: 24px;
        }

        .birthday-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .birthday-item:last-child {
            border-bottom: none;
        }

        .birthday-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            overflow: hidden;
        }

        .birthday-avatar.erkak {
            background: #4299e1;
        }

        .birthday-avatar.ayol {
            background: #ed64a6;
        }

        .birthday-avatar i {
            font-size: 24px;
            color: white;
        }

        .birthday-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .birthday-info {
            flex: 1;
        }

        .birthday-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .birthday-date {
            font-size: 13px;
            color: #7f8c8d;
        }

        .birthday-age {
            background: #f0f0f0;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #666;
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
        }

        .modal-content::-webkit-scrollbar {
            width: 4px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }

        .modal-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .close-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        .modal-body {
            padding: 20px;
        }

        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 16px;
            overflow: hidden;
            border: 3px solid #667eea;
            box-shadow: 0 4px 10px rgba(102,126,234,0.2);
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
            margin-bottom: 16px;
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
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .info-label i {
            width: 16px;
            color: #667eea;
            font-size: 13px;
        }

        .info-value {
            flex: 1;
            color: #2d3748;
            font-weight: 500;
            font-size: 13px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
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
            transition: all 0.2s;
        }

        .modal-btn.edit {
            background: linear-gradient(135deg, #48c78e, #3aa87a);
            color: white;
        }

        .modal-btn.edit:hover {
            background: #3aa87a;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(72,199,142,0.3);
        }

        .modal-btn.close {
            background: #e2e8f0;
            color: #4a5568;
        }

        .modal-btn.close:hover {
            background: #cbd5e0;
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
            .admin-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
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
                    <li><a href="index.php" class="active"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="shaxslar.php"><i class="fas fa-users"></i> Shaxslar</a></li>
                    <li><a href="qoshish.php"><i class="fas fa-plus-circle"></i> Yangi qo'shish</a></li>
                    <li><a href="boglash.php"><i class="fas fa-link"></i> Ota-ona bog'lash</a></li>
                    <li><a href="eslatmalar.php"><i class="fas fa-bell"></i> Eslatmalar 
                        <?php if ($eslatmalar_soni > 0): ?>
                            <span class="badge"><?php echo $eslatmalar_soni; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="statistika.php"><i class="fas fa-chart-bar"></i> Statistika</a></li>
                    <li><a href="sozlamalar.php"><i class="fas fa-cog"></i> Sozlamalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="admin-header">
                <div class="header-left">
                    <h1>Xush kelibsiz, Admin!</h1>
                    <p><?php echo date('d F Y, l'); ?></p>
                </div>
                <div class="header-right">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($eslatmalar_soni > 0): ?>
                            <span class="badge"><?php echo $eslatmalar_soni; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="user-details">
                            <div class="user-name">Administrator</div>
                            <div class="user-role">Super Admin</div>
                        </div>
                    </div>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
                </div>
            </div>

            <div class="quick-stats">
                <div class="stat-box">
                    <div class="stat-info">
                        <h3>Jami shaxslar</h3>
                        <div class="number"><?php echo $stats['jami'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-info">
                        <h3>Tiriklar</h3>
                        <div class="number"><?php echo $stats['tirik'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-info">
                        <h3>Erkaklar</h3>
                        <div class="number"><?php echo $stats['jins']['erkak'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-male"></i> 
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-info">
                        <h3>Ayollar</h3>
                        <div class="number"><?php echo $stats['jins']['ayol'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-female"></i> 
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <a href="qoshish.php" class="btn-add">
                    <i class="fas fa-plus"></i> Yangi shaxs qo'shish
                </a>
                <a href="boglash.php" class="btn-secondary">
                    <i class="fas fa-link"></i> Ota-ona bog'lash
                </a>
            </div>

            <div class="dashboard-grid">
                <div class="table-container">
                    <div class="table-header">
                        <h2><i class="fas fa-clock"></i> So'nggi qo'shilganlar</h2>
                        <a href="shaxslar.php" class="view-all">Barchasini ko'rish →</a>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>№</th> 
                                <th>Rasm</th>
                                <th>Ism</th>
                                <th>Familiya</th>
                                <th>Jins</th>
                                <th>Holati</th>
                                <th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($songi_shaxslar)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                    Hozircha shaxslar yo'q
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php 
                                $tartib = 1;
                                foreach ($songi_shaxslar as $shaxs): 
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
                                    <td><?php echo $shaxs['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $shaxs['tirik'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <i class="fas fa-<?php echo $shaxs['tirik'] ? 'heart' : 'skull'; ?>"></i>
                                            <?php echo $shaxs['tirik'] ? 'Tirik' : 'Vafot etgan'; ?>
                                        </span>
                                    </td>
                                    <td class="action-icons">
                                        <a href="javascript:void(0);" onclick="shaxsMalumot(<?php echo $shaxs['id']; ?>)" title="Ko'rish">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="tahrirlash.php?id=<?php echo $shaxs['id']; ?>" title="Tahrirlash">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);" onclick="ochirish(<?php echo $shaxs['id']; ?>)" class="delete" title="O'chirish">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="birthday-list">
                    <div class="birthday-header">
                        <i class="fas fa-birthday-cake"></i>
                        <h2>Bugungi tug'ilganlar</h2>
                    </div>
                    
                    <?php if (empty($bugun_tugilganlar)): ?>
                    <div style="text-align: center; padding: 30px; color: #999;">
                        <i class="fas fa-calendar" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                        Bugun tug'ilganlar yo'q
                    </div>
                    <?php else: ?>
                        <?php foreach ($bugun_tugilganlar as $shaxs): 
                            $yosh = yosh_hisoblash($shaxs['tugilgan_sana']);
                        ?>
                        <div class="birthday-item" onclick="shaxsMalumot(<?php echo $shaxs['id']; ?>)" style="cursor: pointer;"> 
                            <div class="birthday-avatar <?php echo $shaxs['jins']; ?>">
                                <?php if (!empty($shaxs['foto'])): ?>
                                    <img src="../assets/uploads/<?php echo $shaxs['foto']; ?>" alt="<?php echo htmlspecialchars($shaxs['ism']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-<?php echo $shaxs['jins'] == 'erkak' ? 'male' : 'female'; ?>"></i> 
                                <?php endif; ?>
                            </div>
                            <div class="birthday-info">
                                <div class="birthday-name">
                                    <?php echo htmlspecialchars($shaxs['ism'] . ' ' . $shaxs['familiya']); ?>
                                </div>
                                <div class="birthday-date">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('d.m.Y', strtotime($shaxs['tugilgan_sana'])); ?>
                                </div>
                            </div>
                            <div class="birthday-age">
                                <?php echo $yosh; ?> yosh
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div style="margin-top: 20px; text-align: center;">
                        <a href="eslatmalar.php" class="view-all">Barcha eslatmalarni ko'rish →</a>
                    </div>
                </div>
            </div>

            <div style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-line" style="color: #667eea;"></i>
                    Qisqacha statistika
                </h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                    <div>
                        <div style="color: #7f8c8d; font-size: 14px;">O'rtacha yosh</div>
                        <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $stats['ortacha_yosh'] ?? 0; ?></div>
                    </div>
                    <div>
                        <div style="color: #7f8c8d; font-size: 14px;">Avlodlar soni</div>
                        <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $stats['avlodlar'] ?? 1; ?></div>
                    </div>
                    <div>
                        <div style="color: #7f8c8d; font-size: 14px;">Vafot etganlar</div>
                        <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $stats['vafot'] ?? 0; ?></div>
                    </div>
                    <div>
                        <div style="color: #7f8c8d; font-size: 14px;">Eslatmalar</div>
                        <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $eslatmalar_soni; ?></div>
                    </div>
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

    <script>
        // Shaxs ma'lumotlarini modal oynada ko'rish
        function shaxsMalumot(id) {
            fetch(`../api/shaxs.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const s = data.data;
                        const modal = document.getElementById('shaxsModal');
                        const body = document.getElementById('shaxsModalBody');
                        
                        document.getElementById('modalShaxsNomi').textContent = `${s.ism} ${s.familiya}`;
                        
                        const yosh = s.yosh ? s.yosh + ' yosh' : 'Noma\'lum';
                        const tugilganSana = s.tugilgan_sana ? new Date(s.tugilgan_sana).toLocaleDateString('uz-UZ') : '-';
                        
                        const holatBadge = s.tirik == 1 
                            ? '<span class="badge badge-success"><i class="fas fa-heart"></i> Tirik</span>' 
                            : '<span class="badge badge-danger"><i class="fas fa-skull"></i> Vafot etgan</span>';
                        
                        const jinsIkon = s.jins === 'erkak' ? 'fa-male' : 'fa-female';
                        const jinsMatn = s.jins === 'erkak' ? 'Erkak' : 'Ayol';
                        
                        let fotoHtml = '';
                        if (s.foto) {
                            fotoHtml = `<div class="profile-photo"><img src="../assets/uploads/${s.foto}" alt="${s.ism}"></div>`;
                        } else {
                            fotoHtml = `
                                <div class="profile-photo">
                                    <div class="no-photo ${s.jins}">
                                        <i class="fas ${jinsIkon}"></i>
                                    </div>
                                </div>
                            `;
                        }
                        
                        body.innerHTML = `
                            ${fotoHtml}
                            <div class="info-card">
                                <div class="info-row"><span class="info-label"><i class="fas fa-id-card"></i> ID:</span><span class="info-value">#${s.id}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-user"></i> Familiya:</span><span class="info-value">${s.familiya || '-'}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-user"></i> Ism:</span><span class="info-value">${s.ism || '-'}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-user"></i> Otasining ismi:</span><span class="info-value">${s.otasining_ismi || '-'}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas ${jinsIkon}"></i> Jinsi:</span><span class="info-value">${jinsMatn}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-calendar"></i> Tug'ilgan sana:</span><span class="info-value">${tugilganSana}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-birthday-cake"></i> Yosh:</span><span class="info-value">${yosh}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-heartbeat"></i> Holati:</span><span class="info-value">${holatBadge}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-briefcase"></i> Kasbi:</span><span class="info-value">${s.kasbi || '-'}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-phone"></i> Telefon:</span><span class="info-value">${s.telefon || '-'}</span></div>
                                <div class="info-row"><span class="info-label"><i class="fas fa-map-marker-alt"></i> Tug'ilgan joy:</span><span class="info-value">${s.tugilgan_joy || '-'}</span></div>
                            </div>
                            <div class="modal-actions">
                                <button onclick="window.location.href='tahrirlash.php?id=${s.id}'" class="modal-btn edit"><i class="fas fa-edit"></i> Tahrirlash</button>
                                <button onclick="closeShaxsModal()" class="modal-btn close"><i class="fas fa-times"></i> Yopish</button>
                            </div>
                        `;
                        
                        modal.style.display = 'flex';
                    } else {
                        alert('Ma\'lumotlarni yuklashda xatolik');
                    }
                })
                .catch(error => {
                    console.error('Xatolik:', error);
                    alert('Xatolik yuz berdi');
                });
        }

        function closeShaxsModal() {
            document.getElementById('shaxsModal').style.display = 'none';
        }

        // O'chirish funksiyasi
        function ochirish(id) {
            if (confirm('Haqiqatan ham bu shaxsni o\'chirmoqchimisiz?\nBu amalni ortga qaytarib bo\'lmaydi!')) {
                fetch(`../api/shaxs.php?id=${id}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Shaxs muvaffaqiyatli o\'chirildi');
                        location.reload();
                    } else {
                        if (data.farzandlar_soni > 0) {
                            const forceDelete = confirm(`Bu shaxsning ${data.farzandlar_soni} ta farzandi bor.\n\nFarzandlari bilan birga o\'chirishni xohlaysizmi?\n\n⚠️ OGOHLANTIRISH: Bu amalni ortga qaytarib bo\'lmaydi!`);
                            
                            if (forceDelete) {
                                fetch(`../api/shaxs.php?id=${id}&force=1`, {
                                    method: 'DELETE'
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        alert('Shaxs va uning farzandlari o\'chirildi');
                                        location.reload();
                                    } else {
                                        alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                                    }
                                });
                            }
                        } else {
                            alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                        }
                    }
                })
                .catch(error => {
                    console.error('Xatolik:', error);
                    alert('Xatolik yuz berdi');
                });
            }
        }

        // Modalni yopish uchun tashqariga bosish
        window.onclick = function(event) {
            const modal = document.getElementById('shaxsModal');
            if (event.target == modal) {
                closeShaxsModal();
            }
        }
    </script>
</body>
</html>