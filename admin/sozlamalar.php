<?php
// =============================================
// FILE: admin/sozlamalar.php
// MAQSAD: Admin panel sozlamalari, profil va admin boshqaruvi
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Sessiyani tekshirish
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Admin kirishini tekshirish
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// Joriy admin ma'lumotlarini olish
$admin_id = $_SESSION['admin_id'] ?? 1;
$sql = "SELECT * FROM adminlar WHERE id = $admin_id";
$result = db_query($sql);
$admin = $result->fetch_assoc();

// Barcha adminlarni olish (yangi admin qo'shish uchun)
$sql = "SELECT * FROM adminlar ORDER BY id DESC";
$result = db_query($sql);
$adminlar = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $adminlar[] = $row;
    }
}

$message = '';
$error = '';

// Sozlamalarni saqlash
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'profile') {
        // Profil ma'lumotlarini yangilash
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        
        // Username unikal ekanligini tekshirish (o'zidan boshqa)
        $check_sql = "SELECT id FROM adminlar WHERE username = '$username' AND id != $admin_id";
        $check_result = db_query($check_sql);
        
        if ($check_result && $check_result->num_rows > 0) {
            $error = "Bu foydalanuvchi nomi allaqachon mavjud!";
        } else {
            $update_sql = "UPDATE adminlar SET username = '$username', email = '$email' WHERE id = $admin_id";
            
            if (db_query($update_sql)) {
                $_SESSION['admin_user'] = $username;
                $message = "✅ Profil ma'lumotlari muvaffaqiyatli yangilandi!";
                
                // Admin ma'lumotlarini qayta olish
                $result = db_query("SELECT * FROM adminlar WHERE id = $admin_id");
                $admin = $result->fetch_assoc();
            } else {
                $error = "❌ Yangilashda xatolik yuz berdi!";
            }
        }
    } elseif ($action === 'password') {
        // Parolni yangilash
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Joriy parolni tekshirish (oddiy tekshirish)
        if ($current_password !== $admin['password']) {
            $error = "❌ Joriy parol noto'g'ri!";
        } elseif ($new_password !== $confirm_password) {
            $error = "❌ Yangi parollar bir-biriga mos emas!";
        } elseif (strlen($new_password) < 6) {
            $error = "❌ Parol kamida 6 belgidan iborat bo'lishi kerak!";
        } else {
            $update_sql = "UPDATE adminlar SET password = '$new_password' WHERE id = $admin_id";
            
            if (db_query($update_sql)) {
                $message = "✅ Parol muvaffaqiyatli o'zgartirildi!";
            } else {
                $error = "❌ Parol o'zgartirishda xatolik!";
            }
        }
    } elseif ($action === 'site') {
        // Sayt sozlamalari (config.php faylini yangilash)
        $site_name = sanitize($_POST['site_name']);
        $site_url = sanitize($_POST['site_url']);
        
        // config.php faylini yangilash
        $config_file = __DIR__ . '/../includes/config.php';
        $config_content = file_get_contents($config_file);
        
        // SITE_NAME ni yangilash
        $config_content = preg_replace(
            "/define\('SITE_NAME', '.*'\);/",
            "define('SITE_NAME', '$site_name');",
            $config_content
        );
        
        // SITE_URL ni yangilash
        $config_content = preg_replace(
            "/define\('SITE_URL', '.*'\);/",
            "define('SITE_URL', '$site_url');",
            $config_content
        );
        
        if (file_put_contents($config_file, $config_content)) {
            $message = "✅ Sayt sozlamalari muvaffaqiyatli saqlandi!";
            
            // Constantalarni qayta yuklash (agar define qilingan bo'lsa)
            if (defined('SITE_NAME')) {
                // Qayta define qilib bo'lmaydi, shuning uchun xabar
            }
        } else {
            $error = "❌ Sozlamalarni saqlashda xatolik!";
        }
    } elseif ($action === 'add_admin') {
        // Yangi admin qo'shish
        $new_username = sanitize($_POST['new_username']);
        $new_email = sanitize($_POST['new_email']);
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];
        
        // Tekshirish
        if (empty($new_username) || empty($new_email) || empty($new_password)) {
            $error = "❌ Barcha maydonlarni to'ldiring!";
        } elseif ($new_password !== $confirm_new_password) {
            $error = "❌ Parollar bir-biriga mos emas!";
        } elseif (strlen($new_password) < 6) {
            $error = "❌ Parol kamida 6 belgidan iborat bo'lishi kerak!";
        } else {
            // Username unikal ekanligini tekshirish
            $check_sql = "SELECT id FROM adminlar WHERE username = '$new_username'";
            $check_result = db_query($check_sql);
            
            if ($check_result && $check_result->num_rows > 0) {
                $error = "❌ Bu foydalanuvchi nomi allaqachon mavjud!";
            } else {
                $insert_sql = "INSERT INTO adminlar (username, email, password, created_at) 
                               VALUES ('$new_username', '$new_email', '$new_password', NOW())";
                
                if (db_query($insert_sql)) {
                    $message = "✅ Yangi admin muvaffaqiyatli qo'shildi!";
                    
                    // Adminlar ro'yxatini qayta olish
                    $result = db_query("SELECT * FROM adminlar ORDER BY id DESC");
                    $adminlar = [];
                    while ($row = $result->fetch_assoc()) {
                        $adminlar[] = $row;
                    }
                } else {
                    $error = "❌ Admin qo'shishda xatolik!";
                }
            }
        }
    } elseif ($action === 'delete_admin') {
        // Adminni o'chirish (o'zini o'chirish mumkin emas)
        $delete_id = (int)$_POST['delete_id'];
        
        if ($delete_id == $admin_id) {
            $error = "❌ O'zingizni o'chira olmaysiz!";
        } else {
            $delete_sql = "DELETE FROM adminlar WHERE id = $delete_id";
            
            if (db_query($delete_sql)) {
                $message = "✅ Admin muvaffaqiyatli o'chirildi!";
                
                // Adminlar ro'yxatini qayta olish
                $result = db_query("SELECT * FROM adminlar ORDER BY id DESC");
                $adminlar = [];
                while ($row = $result->fetch_assoc()) {
                    $adminlar[] = $row;
                }
            } else {
                $error = "❌ Admin o'chirishda xatolik!";
            }
        }
    }
}

// Aktiv eslatmalar soni
$sql = "SELECT COUNT(*) as soni FROM eslatmalar WHERE eslatma_berilsin = 1 AND eslatma_sana >= CURDATE()";
$result = db_query($sql);
$active_reminders = $result->fetch_assoc()['soni'] ?? 0;

// Oxirgi kirishlar
$sql = "SELECT last_login FROM adminlar WHERE id = $admin_id";
$result = db_query($sql);
$last_login = $result->fetch_assoc()['last_login'];
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sozlamalar | Admin Panel</title>
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
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #5a67d8;
            transform: translateX(-5px);
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: #f0f0f0;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            flex: 1;
            justify-content: center;
            min-width: 120px;
        }

        .tab-btn i {
            font-size: 16px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background: #e0e0e0;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            margin-bottom: 30px;
        }

        .form-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #48c78e20;
            color: #48c78e;
            border-left: 4px solid #48c78e;
        }

        .alert-error {
            background: #f4565620;
            color: #f45656;
            border-left: 4px solid #f45656;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-group label i {
            color: #667eea;
            width: 20px;
            margin-right: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-group input:read-only {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }

        .info-box h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-item .label {
            font-size: 12px;
            color: #7f8c8d;
        }

        .info-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Adminlar jadvali */
        .admins-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .admins-table th {
            text-align: left;
            padding: 12px 10px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .admins-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #ecf0f1;
            color: #34495e;
        }

        .admins-table tr:hover {
            background: #f8f9fa;
        }

        .badge-admin {
            background: #667eea20;
            color: #667eea;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .delete-admin-btn {
            color: #f45656;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .delete-admin-btn:hover {
            background: #f4565620;
        }

        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .btn-submit {
            padding: 14px 30px;
            background: linear-gradient(135deg, #48c78e, #3aa87a);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(72, 199, 142, 0.3);
        }

        .btn-reset {
            padding: 14px 30px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-reset:hover {
            background: #7f8c8d;
        }

        .btn-add {
            padding: 14px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }

        .danger-zone {
            margin-top: 40px;
            padding: 20px;
            border: 2px solid #f45656;
            border-radius: 15px;
            background: #fef2f2;
        }

        .danger-zone h3 {
            color: #f45656;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-danger {
            padding: 12px 25px;
            background: #f45656;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-danger:hover {
            background: #d43f3f;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
            .tabs {
                flex-direction: column;
            }
            .tab-btn {
                width: 100%;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn-submit, .btn-reset {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
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
                    <li><a href="eslatmalar.php"><i class="fas fa-bell"></i> Eslatmalar</a></li>
                    <li><a href="statistika.php"><i class="fas fa-chart-bar"></i> Statistika</a></li>
                    <li><a href="sozlamalar.php" class="active"><i class="fas fa-cog"></i> Sozlamalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-cog"></i> Sozlamalar</h1>
                <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <!-- Xabarlar -->
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

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('profile')">
                    <i class="fas fa-user"></i> Profil
                </button>
                <button class="tab-btn" onclick="showTab('password')">
                    <i class="fas fa-lock"></i> Parol
                </button>
                <button class="tab-btn" onclick="showTab('admins')">
                    <i class="fas fa-users-cog"></i> Adminlar
                </button>
                <button class="tab-btn" onclick="showTab('site')">
                    <i class="fas fa-globe"></i> Sayt
                </button>
                <button class="tab-btn" onclick="showTab('system')">
                    <i class="fas fa-info-circle"></i> Tizim
                </button>
            </div>

            <!-- Profil formasi -->
            <div id="profile-form" class="form-container active">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="profile">
                    
                    <div class="info-box">
                        <h3><i class="fas fa-info-circle"></i> Profil ma'lumotlari</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="label">Admin ID</span>
                                <span class="value">#<?php echo $admin['id']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Oxirgi kirish</span>
                                <span class="value"><?php echo $last_login ? date('d.m.Y H:i', strtotime($last_login)) : 'Birinchi marta'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Ro'yxatdan o'tgan</span>
                                <span class="value"><?php echo date('d.m.Y', strtotime($admin['created_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Aktiv eslatmalar</span>
                                <span class="value"><?php echo $active_reminders; ?> ta</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Foydalanuvchi nomi</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Saqlash</button>
                    </div>
                </form>
            </div>

            <!-- Parol formasi -->
            <div id="password-form" class="form-container">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="password">
                    
                    <div class="info-box">
                        <h3><i class="fas fa-lock"></i> Parolni o'zgartirish</h3>
                        <p style="color: #666; font-size: 14px;">Xavfsizlik uchun kuchli parol tanlang (kamida 6 belgi).</p>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Joriy parol</label>
                        <input type="password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Yangi parol</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Yangi parolni takrorlang</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> O'zgartirish</button>
                    </div>
                </form>
            </div>

            <!-- Adminlar formasi (yangi) -->
            <div id="admins-form" class="form-container">
                <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-plus" style="color: #667eea;"></i> Yangi admin qo'shish
                </h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_admin">
                    
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Foydalanuvchi nomi</label>
                            <input type="text" name="new_username" required placeholder="Username">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="new_email" required placeholder="email@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Parol</label>
                            <input type="password" name="new_password" required minlength="6" placeholder="••••••">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Parolni takrorlang</label>
                            <input type="password" name="confirm_new_password" required minlength="6" placeholder="••••••">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-add"><i class="fas fa-user-plus"></i> Admin qo'shish</button>
                    </div>
                </form>
                
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #ecf0f1;">
                
                <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-users" style="color: #667eea;"></i> Mavjud adminlar
                </h3>
                
                <table class="admins-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foydalanuvchi nomi</th>
                            <th>Email</th>
                            <th>Ro'yxatdan o'tgan</th>
                            <th>Oxirgi kirish</th>
                            <th>Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminlar as $a): ?>
                        <tr>
                            <td>#<?php echo $a['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($a['username']); ?>
                                <?php if ($a['id'] == $admin_id): ?>
                                    <span class="badge-admin">(Siz)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($a['email']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($a['created_at'])); ?></td>
                            <td><?php echo $a['last_login'] ? date('d.m.Y H:i', strtotime($a['last_login'])) : '-'; ?></td>
                            <td>
                                <?php if ($a['id'] != $admin_id): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Haqiqatan ham bu adminni o\'chirmoqchimisiz?');">
                                    <input type="hidden" name="action" value="delete_admin">
                                    <input type="hidden" name="delete_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="delete-admin-btn" title="O'chirish">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sayt sozlamalari formasi -->
            <div id="site-form" class="form-container">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="site">
                    
                    <div class="info-box">
                        <h3><i class="fas fa-globe"></i> Sayt sozlamalari</h3>
                        <p style="color: #666; font-size: 14px;">Bu sozlamalar config.php faylida saqlanadi.</p>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tree"></i> Sayt nomi</label>
                        <input type="text" name="site_name" value="<?php echo SITE_NAME; ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-link"></i> Sayt URL</label>
                        <input type="url" name="site_url" value="<?php echo SITE_URL; ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Saqlash</button>
                    </div>
                </form>
            </div>

            <!-- Tizim ma'lumotlari -->
            <div id="system-form" class="form-container">
                <div class="info-box">
                    <h3><i class="fas fa-server"></i> Tizim ma'lumotlari</h3>
                    
                    <div class="info-grid" style="margin-top: 15px;">
                        <div class="info-item">
                            <span class="label">PHP versiyasi</span>
                            <span class="value"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">MySQL versiyasi</span>
                            <span class="value"><?php 
                                $conn = dbConnect();
                                echo $conn->server_info;
                            ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Loyiha versiyasi</span>
                            <span class="value">1.0.0</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Server</span>
                            <span class="value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Noma\'lum'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Maxsus papka</span>
                            <span class="value"><?php echo __DIR__; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Sessiya nomi</span>
                            <span class="value"><?php echo session_name(); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tablarni almashtirish
        function showTab(tabName) {
            // Tab buttonlarini yangilash
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Formalarni yashirish/ko'rsatish
            document.querySelectorAll('.form-container').forEach(form => form.classList.remove('active'));
            
            if (tabName === 'profile') {
                document.getElementById('profile-form').classList.add('active');
            } else if (tabName === 'password') {
                document.getElementById('password-form').classList.add('active');
            } else if (tabName === 'admins') {
                document.getElementById('admins-form').classList.add('active');
            } else if (tabName === 'site') {
                document.getElementById('site-form').classList.add('active');
            } else if (tabName === 'system') {
                document.getElementById('system-form').classList.add('active');
            }
        }

        // Parolni tekshirish
        document.querySelector('#password-form form')?.addEventListener('submit', function(e) {
            const newPass = document.querySelector('input[name="new_password"]').value;
            const confirmPass = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Yangi parollar bir-biriga mos emas!');
            }
        });

        // Yangi admin qo'shishda parollarni tekshirish
        document.querySelector('#admins-form form')?.addEventListener('submit', function(e) {
            const newPass = document.querySelector('input[name="new_password"]').value;
            const confirmPass = document.querySelector('input[name="confirm_new_password"]').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Parollar bir-biriga mos emas!');
            }
        });
    </script>
</body>
</html>