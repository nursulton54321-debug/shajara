<?php
// =============================================
// FILE: admin/qoshish.php
// MAQSAD: Yangi shaxs qo'shish sahifasi (aylana rasm va WebP siqish bilan)
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

$message = '';
$error = '';

// Rasm yuklash papkasi
$upload_dir = __DIR__ . '/../assets/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Barcha shaxslar ro'yxati (ota-ona tanlash uchun)
$shaxslar = shaxslar_roixati();

// Forma yuborilgan bo'lsa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ma'lumotlarni tozalash
    $data = [
        'ism' => $_POST['ism'],
        'familiya' => $_POST['familiya'],
        'otasining_ismi' => $_POST['otasining_ismi'] ?? '',
        'jins' => $_POST['jins'],
        'tugilgan_sana' => $_POST['tugilgan_sana'] ?? null,
        'vafot_sana' => $_POST['vafot_sana'] ?? null,
        'tirik' => isset($_POST['tirik']) ? 1 : 0,
        'tugilgan_joy' => $_POST['tugilgan_joy'] ?? '',
        'kasbi' => $_POST['kasbi'] ?? '',
        'telefon' => $_POST['telefon'] ?? ''
    ];
    
    // Majburiy maydonlarni tekshirish
    if (empty($data['ism']) || empty($data['familiya']) || empty($data['jins'])) {
        $error = 'Ism, familiya va jins majburiy maydonlar!';
    } else {
        // =====================================================
        // YANGILANGAN: RASM YUKLASH VA WEBP SIQISH
        // =====================================================
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $max_size = 10 * 1024 * 1024; // 10 MB gacha ruxsat (funksiya baribir siqadi)
            
            if ($_FILES['foto']['size'] > $max_size) {
                $error = 'Rasm hajmi juda katta!';
            } else {
                // Biz includes/functions.php ga qo'shgan yangi funksiyani chaqiramiz
                // 75% sifat - bu ideal o'lcham va tiniqlik balansi
                $foto_nomi = rasm_yuklash_webp($_FILES['foto'], $upload_dir, 75);
                
                if ($foto_nomi) {
                    $data['foto'] = $foto_nomi; 
                } else {
                    $error = 'Rasmni WebP formatiga o\'tkazishda xatolik yuz berdi!';
                }
            }
        }
        
        if (empty($error)) {
            // Shaxsni qo'shish
            $shaxs_id = shaxs_qoshish($data);
            
            if ($shaxs_id) {
                // Ota-onani bog'lash (agar tanlangan bo'lsa)
                if (!empty($_POST['ota_id']) || !empty($_POST['ona_id'])) {
                    ota_ona_qoshish(
                        $shaxs_id,
                        !empty($_POST['ota_id']) ? $_POST['ota_id'] : null,
                        !empty($_POST['ona_id']) ? $_POST['ona_id'] : null
                    );
                }
                
                // Turmush o'rtog'ini bog'lash (agar tanlangan bo'lsa)
                if (!empty($_POST['turmush_ortogi_id'])) {
                    turmush_ortogi_qoshish($shaxs_id, $_POST['turmush_ortogi_id']);
                }
                
                // Eslatma qo'shish (agar tug'ilgan kun eslatmasi kerak bo'lsa)
                if (isset($_POST['eslatma_berilsin']) && !empty($data['tugilgan_sana'])) {
                    $eslatma_sana = date('Y') . substr($data['tugilgan_sana'], 4);
                    $sql = "INSERT INTO eslatmalar (shaxs_id, eslatma_turi, eslatma_sana, eslatma_matni, eslatma_berilsin) 
                            VALUES ($shaxs_id, 'tugilgan_kun', '$eslatma_sana', '{$data['ism']} {$data['familiya']}ning tug\'ilgan kuni', 1)";
                    db_query($sql);
                }
                
                // Formani tozalash uchun redirect
                header("Location: qoshish.php?success=1");
                exit;
            } else {
                $error = 'Shaxs qo\'shishda xatolik yuz berdi!';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yangi shaxs qo'shish | Admin Panel</title>
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

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        /* ===== AYLANA RASM UCHUN STILLAR ===== */
        .photo-upload-section {
            grid-column: span 2;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px dashed #667eea;
            transition: all 0.3s;
        }

        .photo-upload-section:hover {
            border-color: #48c78e;
            background: #e8f0fe;
        }

        .photo-circle-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        /* Aylana rasm */
        .photo-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: white;
            border: 4px solid #667eea;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
            transition: all 0.3s;
        }

        .photo-circle:hover {
            transform: scale(1.05);
            border-color: #48c78e;
            box-shadow: 0 8px 25px rgba(72,199,142,0.4);
        }

        .photo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .photo-circle .no-photo {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 48px;
        }

        .photo-circle .no-photo i {
            font-size: 48px;
        }

        /* Kamera ikonkasi (hover da ko'rinadi) */
        .photo-circle-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 50%;
        }

        .photo-circle:hover .photo-circle-overlay {
            opacity: 1;
        }

        .photo-circle-overlay i {
            color: white;
            font-size: 32px;
        }

        /* Rasm ma'lumotlari */
        .photo-info {
            text-align: center;
        }

        .photo-info h3 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .photo-info p {
            color: #7f8c8d;
            font-size: 13px;
        }

        /* Yuklash tugmasi (yashirin) */
        .photo-upload-input {
            display: none;
        }

        /* Form grid */
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
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
            .form-group.full-width,
            .photo-upload-section {
                grid-column: span 1;
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
                    <li><a href="qoshish.php" class="active"><i class="fas fa-plus-circle"></i> Yangi qo'shish</a></li>
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
                <h1><i class="fas fa-plus-circle"></i> Yangi shaxs qo'shish</h1>
                <div class="header-actions">
                    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
                </div>
            </div>

            <div class="form-container">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Shaxs muvaffaqiyatli qo'shildi!
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="qoshishForm">
                    <div class="photo-upload-section">
                        <div class="photo-circle-container">
                            <div class="photo-circle" onclick="document.getElementById('foto').click()">
                                <div class="no-photo" id="noPhotoIcon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <img src="" alt="Preview" id="previewImage" style="display: none;">
                                <div class="photo-circle-overlay">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                            
                            <div class="photo-info">
                                <h3><i class="fas fa-camera"></i> Rasm yuklash</h3>
                                <p>Optimallashtirilgan WebP tizimi yoqilgan</p>
                            </div>
                            
                            <input type="file" name="foto" id="foto" class="photo-upload-input" 
                                   accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewCircleImage(this)">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Ism *</label>
                            <input type="text" name="ism" required placeholder="Ismni kiriting" value="<?php echo isset($_POST['ism']) ? htmlspecialchars($_POST['ism']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Familiya *</label>
                            <input type="text" name="familiya" required placeholder="Familiyani kiriting" value="<?php echo isset($_POST['familiya']) ? htmlspecialchars($_POST['familiya']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Otasining ismi</label>
                            <input type="text" name="otasining_ismi" placeholder="Otasining ismi" value="<?php echo isset($_POST['otasining_ismi']) ? htmlspecialchars($_POST['otasining_ismi']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Jins *</label>
                            <select name="jins" required>
                                <option value="">Tanlang...</option>
                                <option value="erkak" <?php echo (isset($_POST['jins']) && $_POST['jins'] == 'erkak') ? 'selected' : ''; ?>>Erkak</option>
                                <option value="ayol" <?php echo (isset($_POST['jins']) && $_POST['jins'] == 'ayol') ? 'selected' : ''; ?>>Ayol</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Tug'ilgan sana</label>
                            <input type="date" name="tugilgan_sana" value="<?php echo isset($_POST['tugilgan_sana']) ? $_POST['tugilgan_sana'] : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-times"></i> Vafot etgan sana</label>
                            <input type="date" name="vafot_sana" value="<?php echo isset($_POST['vafot_sana']) ? $_POST['vafot_sana'] : ''; ?>">
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="tirik" id="tirik" <?php echo (!isset($_POST['tirik']) || $_POST['tirik']) ? 'checked' : ''; ?>>
                                <label for="tirik"><i class="fas fa-heart"></i> Tirik</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Tug'ilgan joy</label>
                            <input type="text" name="tugilgan_joy" placeholder="Tug'ilgan joy" value="<?php echo isset($_POST['tugilgan_joy']) ? htmlspecialchars($_POST['tugilgan_joy']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Kasbi</label>
                            <input type="text" name="kasbi" placeholder="Kasbi" value="<?php echo isset($_POST['kasbi']) ? htmlspecialchars($_POST['kasbi']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Telefon</label>
                            <input type="text" name="telefon" placeholder="+998 XX XXX XX XX" value="<?php echo isset($_POST['telefon']) ? htmlspecialchars($_POST['telefon']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-male"></i> Otasi</label>
                            <select name="ota_id">
                                <option value="">Tanlang...</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <?php if ($shaxs['jins'] == 'erkak'): ?>
                                        <option value="<?php echo $shaxs['id']; ?>">
                                            <?php echo htmlspecialchars($shaxs['ism'] . ' ' . $shaxs['familiya']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-female"></i> Onasi</label>
                            <select name="ona_id">
                                <option value="">Tanlang...</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <?php if ($shaxs['jins'] == 'ayol'): ?>
                                        <option value="<?php echo $shaxs['id']; ?>">
                                            <?php echo htmlspecialchars($shaxs['ism'] . ' ' . $shaxs['familiya']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-heart"></i> Turmush o'rtog'i</label>
                            <select name="turmush_ortogi_id">
                                <option value="">Tanlang...</option>
                                <?php foreach ($shaxslar as $shaxs): ?>
                                    <option value="<?php echo $shaxs['id']; ?>">
                                        <?php echo htmlspecialchars($shaxs['ism'] . ' ' . $shaxs['familiya']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" name="eslatma_berilsin" id="eslatma" checked>
                                <label for="eslatma"><i class="fas fa-bell"></i> Tug'ilgan kun eslatmasini yoqish</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset"><i class="fas fa-undo"></i> Tozalash</button>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Rasmni aylana ichida oldindan ko'rish
        function previewCircleImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const previewImage = document.getElementById('previewImage');
                    const noPhotoIcon = document.getElementById('noPhotoIcon');
                    
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                    noPhotoIcon.style.display = 'none';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Formani tekshirish
        document.getElementById('qoshishForm').addEventListener('submit', function(e) {
            const ism = document.querySelector('input[name="ism"]').value.trim();
            const familiya = document.querySelector('input[name="familiya"]').value.trim();
            const jins = document.querySelector('select[name="jins"]').value;
            
            if (!ism || !familiya || !jins) {
                e.preventDefault();
                alert('Iltimos, barcha majburiy maydonlarni to\'ldiring!');
            }
        });

        // Reset tugmasi bosilganda rasmni tozalash
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            setTimeout(function() {
                const previewImage = document.getElementById('previewImage');
                const noPhotoIcon = document.getElementById('noPhotoIcon');
                const fotoInput = document.getElementById('foto');
                
                previewImage.style.display = 'none';
                previewImage.src = '';
                noPhotoIcon.style.display = 'flex';
                fotoInput.value = '';
            }, 10);
        });

        // Telefon raqam formatini tekshirish
        document.querySelector('input[name="telefon"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9+]/g, '');
            if (value.length > 0 && !value.startsWith('+')) {
                value = '+' + value;
            }
            e.target.value = value;
        });

        // Vafot etgan sana va tirik checkbox ni bog'lash
        const tirikCheckbox = document.getElementById('tirik');
        const vafotSanaInput = document.querySelector('input[name="vafot_sana"]');
        
        tirikCheckbox.addEventListener('change', function() {
            if (this.checked) {
                vafotSanaInput.value = '';
                vafotSanaInput.disabled = true;
            } else {
                vafotSanaInput.disabled = false;
            }
        });

        // Sahifa yuklanganda vafot sana holatini sozlash
        if (tirikCheckbox.checked) {
            vafotSanaInput.disabled = true;
        }
    </script>
</body>
</html>