<?php
// =============================================
// FILE: admin/boglash.php
// MAQSAD: Shaxslarga ota-ona va turmush o'rtog'ini bog'lash
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

// Barcha shaxslar ro'yxati va ularni tozalash (&#039; xatoligi uchun)
$raw_shaxslar = shaxslar_roixati();
$shaxslar = [];
if ($raw_shaxslar) {
    foreach ($raw_shaxslar as $sh) {
        $sh['ism'] = html_entity_decode($sh['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $sh['familiya'] = html_entity_decode($sh['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        $shaxslar[] = $sh;
    }
}

$message = '';
$error = '';

// Forma yuborilgan bo'lsa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'ota_ona') {
        // Ota-ona bog'lash
        $shaxs_id = (int)$_POST['shaxs_id'];
        $ota_id = !empty($_POST['ota_id']) ? (int)$_POST['ota_id'] : null;
        $ona_id = !empty($_POST['ona_id']) ? (int)$_POST['ona_id'] : null;
        
        if ($shaxs_id <= 0) {
            $error = 'Shaxs tanlanmagan!';
        } else {
            $result = ota_ona_qoshish($shaxs_id, $ota_id, $ona_id);
            if ($result) {
                $message = "Ota-ona muvaffaqiyatli bog'landi!";
            } else {
                $error = "Bog'lashda xatolik yuz berdi!";
            }
        }
    } elseif ($action === 'turmush_ortogi') {
        // Turmush o'rtog'ini bog'lash
        $shaxs1_id = (int)$_POST['shaxs1_id'];
        $shaxs2_id = (int)$_POST['shaxs2_id'];
        
        if ($shaxs1_id <= 0 || $shaxs2_id <= 0) {
            $error = 'Ikkala shaxs ham tanlanishi kerak!';
        } elseif ($shaxs1_id == $shaxs2_id) {
            $error = 'Bir xil shaxsni bog‘lab bo‘lmaydi!';
        } else {
            $result = turmush_ortogi_qoshish($shaxs1_id, $shaxs2_id);
            if ($result) {
                $message = "Turmush o'rtog'i muvaffaqiyatli bog'landi!";
            } else {
                $error = "Bog'lashda xatolik yuz berdi!";
            }
        }
    } elseif ($action === 'farzand') {
        // Farzand qo'shish (ota-onaga farzand bog'lash)
        $ota_id = !empty($_POST['ota_id']) ? (int)$_POST['ota_id'] : null;
        $ona_id = !empty($_POST['ona_id']) ? (int)$_POST['ona_id'] : null;
        $farzand_id = (int)$_POST['farzand_id'];
        
        if ($farzand_id <= 0) {
            $error = 'Farzand tanlanmagan!';
        } elseif (!$ota_id && !$ona_id) {
            $error = 'Kamida ota yoki ona tanlanishi kerak!';
        } else {
            $result = ota_ona_qoshish($farzand_id, $ota_id, $ona_id);
            if ($result) {
                $message = "Farzand muvaffaqiyatli bog'landi!";
            } else {
                $error = "Bog'lashda xatolik yuz berdi!";
            }
        }
    }
}

// AJAX so'rovlar uchun (shaxs ma'lumotlarini olish)
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id > 0) {
        $shaxs = shaxs_olish($id);
        if ($shaxs) {
            $shaxs['ism'] = html_entity_decode($shaxs['ism'] ?? '', ENT_QUOTES, 'UTF-8');
            $shaxs['familiya'] = html_entity_decode($shaxs['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        }

        $ota_ona = ota_ona_olish($id);
        $turmush_ortogi = turmush_ortogi_olish($id);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'shaxs' => $shaxs,
                'ota_id' => $ota_ona['ota_id'] ?? null,
                'ona_id' => $ota_ona['ona_id'] ?? null,
                'turmush_ortogi_id' => $turmush_ortogi
            ]
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ota-ona bog'lash | Admin Panel</title>
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
        }

        .tab-btn {
            padding: 12px 25px;
            border: none;
            background: #f0f0f0;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            flex: 1;
            justify-content: center;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
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

        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
            cursor: pointer;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-group select:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 13px;
            color: #666;
            border-left: 3px solid #667eea;
        }

        .info-box i {
            color: #667eea;
            margin-right: 5px;
        }

        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
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

        /* Selected info */
        .selected-info {
            background: #e8f0fe;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .selected-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .selected-details {
            flex: 1;
        }

        .selected-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .selected-meta {
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
            .tabs {
                flex-direction: column;
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
                    <li><a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="shaxslar.php"><i class="fas fa-users"></i> Shaxslar</a></li>
                    <li><a href="qoshish.php"><i class="fas fa-plus-circle"></i> Yangi qo'shish</a></li>
                    <li><a href="boglash.php" class="active"><i class="fas fa-link"></i> Ota-ona bog'lash</a></li>
                    <li><a href="eslatmalar.php"><i class="fas fa-bell"></i> Eslatmalar</a></li>
                    <li><a href="statistika.php"><i class="fas fa-chart-bar"></i> Statistika</a></li>
                    <li><a href="sozlamalar.php"><i class="fas fa-cog"></i> Sozlamalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-link"></i> Ota-ona va turmush o'rtog'ini bog'lash</h1>
                <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
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

            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('ota-ona')">
                    <i class="fas fa-users"></i> Ota-ona bog'lash
                </button>
                <button class="tab-btn" onclick="showTab('turmush')">
                    <i class="fas fa-heart"></i> Turmush o'rtog'i bog'lash
                </button>
                <button class="tab-btn" onclick="showTab('farzand')">
                    <i class="fas fa-child"></i> Farzand bog'lash
                </button>
            </div>

            <div id="ota-ona-form" class="form-container active">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="ota_ona">
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label><i class="fas fa-user"></i> Shaxsni tanlang *</label>
                            <select name="shaxs_id" id="shaxsSelect" required onchange="shaxsMalumot(this.value, 'ota-ona')">
                                <option value="">-- Shaxsni tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <option value="<?php echo $shaxs['id']; ?>">
                                        <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        (<?php echo $shaxs['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="ota-ona-info" class="selected-info" style="display: none;">
                            <div class="selected-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="selected-details">
                                <div class="selected-name" id="selectedName"></div>
                                <div class="selected-meta" id="selectedMeta"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-male"></i> Otasi</label>
                            <select name="ota_id" id="otaSelect">
                                <option value="">-- Otasini tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <?php if ($shaxs['jins'] == 'erkak'): ?>
                                        <option value="<?php echo $shaxs['id']; ?>">
                                            <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-female"></i> Onasi</label>
                            <select name="ona_id" id="onaSelect">
                                <option value="">-- Onasini tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <?php if ($shaxs['jins'] == 'ayol'): ?>
                                        <option value="<?php echo $shaxs['id']; ?>">
                                            <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="info-box full-width">
                            <i class="fas fa-info-circle"></i>
                            <strong>Eslatma:</strong> Agar ota yoki ona noma'lum bo'lsa, bo'sh qoldiring.
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-link"></i> Bog'lash</button>
                    </div>
                </form>
            </div>

            <div id="turmush-form" class="form-container">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="turmush_ortogi">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> 1-shaxs *</label>
                            <select name="shaxs1_id" required onchange="shaxsMalumot(this.value, 'turmush1')">
                                <option value="">-- 1-shaxsni tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <option value="<?php echo $shaxs['id']; ?>">
                                        <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        (<?php echo $shaxs['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> 2-shaxs *</label>
                            <select name="shaxs2_id" required onchange="shaxsMalumot(this.value, 'turmush2')">
                                <option value="">-- 2-shaxsni tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <option value="<?php echo $shaxs['id']; ?>">
                                        <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        (<?php echo $shaxs['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="info-box full-width">
                            <i class="fas fa-info-circle"></i>
                            <strong>Eslatma:</strong> Turmush o'rtog'i bog'lashda ikkala shaxs ham bir-biriga bog'lanadi.
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-heart"></i> Bog'lash</button>
                    </div>
                </form>
            </div>

            <div id="farzand-form" class="form-container">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="farzand">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-male"></i> Otasi</label>
                            <select name="ota_id" id="farzandOtaSelect">
                                <option value="">-- Otasini tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <?php if ($shaxs['jins'] == 'erkak'): ?>
                                        <option value="<?php echo $shaxs['id']; ?>">
                                            <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-female"></i> Onasi</label>
                            <select name="ona_id" id="farzandOnaSelect">
                                <option value="">-- Onasini tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <?php if ($shaxs['jins'] == 'ayol'): ?>
                                        <option value="<?php echo $shaxs['id']; ?>">
                                            <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label><i class="fas fa-child"></i> Farzand *</label>
                            <select name="farzand_id" required>
                                <option value="">-- Farzandni tanlang --</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <option value="<?php echo $shaxs['id']; ?>">
                                        <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?>
                                        (<?php echo $shaxs['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="info-box full-width">
                            <i class="fas fa-info-circle"></i>
                            <strong>Eslatma:</strong> Kamida ota yoki onadan birini tanlash kerak.
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-child"></i> Farzand qilish</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tablarni almashtirish
        function showTab(tabName) {
            // Tab buttonlarini yangilash
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            // Formalarni yashirish/ko'rsatish
            document.querySelectorAll('.form-container').forEach(form => form.classList.remove('active'));
            document.getElementById(tabName + '-form').classList.add('active');
        }

        // Shaxs ma'lumotlarini olish (AJAX)
        function shaxsMalumot(id, type) {
            if (!id) return;
            
            fetch(`boglash.php?ajax=1&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (type === 'ota-ona') {
                            // Ota-ona formasi uchun
                            document.getElementById('ota-ona-info').style.display = 'flex';
                            document.getElementById('selectedName').textContent = 
                                data.data.shaxs.ism + ' ' + data.data.shaxs.familiya;
                            document.getElementById('selectedMeta').textContent = 
                                (data.data.shaxs.jins === 'erkak' ? 'Erkak' : 'Ayol') + 
                                (data.data.shaxs.tugilgan_sana ? ', ' + data.data.shaxs.tugilgan_sana : '');
                            
                            // Ota va onani oldindan tanlash
                            if (data.data.ota_id) {
                                document.getElementById('otaSelect').value = data.data.ota_id;
                            } else {
                                document.getElementById('otaSelect').value = '';
                            }
                            if (data.data.ona_id) {
                                document.getElementById('onaSelect').value = data.data.ona_id;
                            } else {
                                document.getElementById('onaSelect').value = '';
                            }
                        }
                    }
                })
                .catch(error => console.error('Xatolik:', error));
        }

        // Formalarni tozalash
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('reset', function() {
                setTimeout(() => {
                    // Ota-ona formasi uchun info panelni yashirish
                    if (this.querySelector('input[name="action"]')?.value === 'ota_ona') {
                        document.getElementById('ota-ona-info').style.display = 'none';
                    }
                }, 10);
            });
        });

        // Sahifa yuklanganda
        document.addEventListener('DOMContentLoaded', function() {
            // URL parametrlariga qarab tabni ochish
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                // To'g'ri tugmani topish va click event chaqirish o'rniga to'g'ridan to'g'ri funksiyani ishlatamiz
                const tabBtns = document.querySelectorAll('.tab-btn');
                tabBtns.forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.form-container').forEach(form => form.classList.remove('active'));
                
                if(tab === 'turmush') {
                    tabBtns[1].classList.add('active');
                    document.getElementById('turmush-form').classList.add('active');
                } else if(tab === 'farzand') {
                    tabBtns[2].classList.add('active');
                    document.getElementById('farzand-form').classList.add('active');
                } else {
                    tabBtns[0].classList.add('active');
                    document.getElementById('ota-ona-form').classList.add('active');
                }
            }
        });
    </script>
</body>
</html>