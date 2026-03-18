<?php
// =============================================
// FILE: admin/tahrirlash.php
// MAQSAD: Shaxs ma'lumotlarini tahrirlash (Galereya va Timeline qismi bilan)
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

// ID ni tekshirish
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: shaxslar.php');
    exit;
}

// ============================================
// AJAX YORDAMIDA GALEREYA RASMINI O'CHIRISH
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gallery_image'])) {
    $img_id = (int)$_POST['image_id'];
    $sql_check = "SELECT fayl FROM shaxs_galereya WHERE id = $img_id AND shaxs_id = $id";
    $res_check = db_query($sql_check);
    if ($res_check && $res_check->num_rows > 0) {
        $row = $res_check->fetch_assoc();
        $file_path = __DIR__ . '/../assets/uploads/' . $row['fayl'];
        if (file_exists($file_path)) unlink($file_path);
        db_query("DELETE FROM shaxs_galereya WHERE id = $img_id");
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// ============================================
// AJAX YORDAMIDA VOQEANI O'CHIRISH
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_voqea'])) {
    $v_id = (int)$_POST['voqea_id'];
    db_query("DELETE FROM shaxs_voqealar WHERE id = $v_id AND shaxs_id = $id");
    echo json_encode(['success' => true]);
    exit;
}

// Shaxs ma'lumotlarini olish
$shaxs = shaxs_olish($id);
if (!$shaxs) {
    header('Location: shaxslar.php?error=notfound');
    exit;
}

function tozalash_html($str) {
    if (empty($str)) return $str;
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$shaxs['ism'] = tozalash_html($shaxs['ism']);
$shaxs['familiya'] = tozalash_html($shaxs['familiya']);
$shaxs['otasining_ismi'] = tozalash_html($shaxs['otasining_ismi'] ?? '');
$shaxs['tugilgan_joy'] = tozalash_html($shaxs['tugilgan_joy'] ?? '');
$shaxs['kasbi'] = tozalash_html($shaxs['kasbi'] ?? '');
$shaxs['telefon'] = tozalash_html($shaxs['telefon'] ?? '');
$shaxs['bio'] = tozalash_html($shaxs['bio'] ?? '');

$ota_ona = ota_ona_olish($id);
$turmush_ortogi = turmush_ortogi_olish($id);
$shaxslar = shaxslar_roixati();

if (is_array($shaxslar)) {
    foreach ($shaxslar as &$s) {
        $s['ism'] = tozalash_html($s['ism']);
        $s['familiya'] = tozalash_html($s['familiya']);
    }
    unset($s);
}

$upload_dir = __DIR__ . '/../assets/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_gallery_image']) && !isset($_POST['delete_voqea'])) {
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
        'telefon' => $_POST['telefon'] ?? '',
        'bio' => $_POST['bio'] ?? '',
        'ota_id' => !empty($_POST['ota_id']) ? (int)$_POST['ota_id'] : null,
        'ona_id' => !empty($_POST['ona_id']) ? (int)$_POST['ona_id'] : null,
        'turmush_ortogi_id' => !empty($_POST['turmush_ortogi_id']) ? (int)$_POST['turmush_ortogi_id'] : null
    ];
    
    if (empty($data['ism']) || empty($data['familiya']) || empty($data['jins'])) {
        $error = 'Ism, familiya va jins majburiy maydonlar!';
    } else {
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; 
            
            if (!in_array($_FILES['foto']['type'], $allowed_types)) {
                $error = 'Faqat JPG, PNG, GIF va WEBP formatlari ruxsat etiladi!';
            } elseif ($_FILES['foto']['size'] > $max_size) {
                $error = 'Rasm hajmi 5MB dan kichik bo\'lishi kerak!';
            } else {
                if (!empty($shaxs['foto']) && file_exists($upload_dir . $shaxs['foto'])) {
                    unlink($upload_dir . $shaxs['foto']);
                }
                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $foto_nomi = time() . '_' . uniqid() . '.' . $ext;
                $upload_path = $upload_dir . $foto_nomi;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                    $data['foto'] = $foto_nomi;
                } else {
                    $error = 'Rasm yuklashda xatolik!';
                }
            }
        } elseif (isset($_POST['remove_photo']) && $_POST['remove_photo'] == 1) {
            if (!empty($shaxs['foto']) && file_exists($upload_dir . $shaxs['foto'])) {
                unlink($upload_dir . $shaxs['foto']);
            }
            $data['foto'] = null;
        }
        
        if (empty($error)) {
            $update_data = [
                'ism' => $data['ism'],
                'familiya' => $data['familiya'],
                'otasining_ismi' => $data['otasining_ismi'],
                'jins' => $data['jins'],
                'tugilgan_sana' => $data['tugilgan_sana'],
                'vafot_sana' => $data['vafot_sana'],
                'tirik' => $data['tirik'],
                'tugilgan_joy' => $data['tugilgan_joy'],
                'kasbi' => $data['kasbi'],
                'telefon' => $data['telefon'],
                'bio' => $data['bio']
            ];
            
            if (isset($data['foto'])) $update_data['foto'] = $data['foto'];
            
            $result = shaxs_yangilash($id, $update_data);
            
            if ($result) {
                if (isset($data['ota_id']) || isset($data['ona_id'])) {
                    ota_ona_qoshish($id, $data['ota_id'] ?? null, $data['ona_id'] ?? null);
                }
                
                if (isset($data['turmush_ortogi_id']) && !empty($data['turmush_ortogi_id'])) {
                    turmush_ortogi_qoshish($id, $data['turmush_ortogi_id']);
                } elseif (isset($data['turmush_ortogi_id']) && empty($data['turmush_ortogi_id'])) {
                    turmush_ortogi_ochirish($id);
                }
                
                // Galereyani saqlash
                if (isset($_FILES['galereya_rasmlar']) && !empty($_FILES['galereya_rasmlar']['name'][0])) {
                    $total_files = count($_FILES['galereya_rasmlar']['name']);
                    for ($i = 0; $i < $total_files; $i++) {
                        $tmp_name = $_FILES['galereya_rasmlar']['tmp_name'][$i];
                        $original_name = $_FILES['galereya_rasmlar']['name'][$i];
                        $error_code = $_FILES['galereya_rasmlar']['error'][$i];
                        
                        if ($error_code == 0) {
                            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                            $yangi_nom = 'arxiv_' . time() . '_' . uniqid() . '.' . $ext;
                            $destination = $upload_dir . $yangi_nom;
                            
                            if (move_uploaded_file($tmp_name, $destination)) {
                                db_query("INSERT INTO shaxs_galereya (shaxs_id, fayl) VALUES ($id, '$yangi_nom')");
                            }
                        }
                    }
                }

                // ===========================================
                // YANGI VOQEALARNI (TIMELINE) SAQLASH
                // ===========================================
                if (isset($_POST['yangi_voqea_sarlavha']) && is_array($_POST['yangi_voqea_sarlavha'])) {
                    foreach ($_POST['yangi_voqea_sarlavha'] as $k => $sarlavha) {
                        $sarlavha = trim($sarlavha);
                        $sana = trim($_POST['yangi_voqea_sana'][$k] ?? '');
                        if (!empty($sarlavha) && !empty($sana)) {
                            // Xavfsiz saqlash uchun qochish (escape)
                            $s = addslashes($sarlavha);
                            $sn = addslashes($sana);
                            $m = addslashes(trim($_POST['yangi_voqea_matn'][$k] ?? ''));
                            db_query("INSERT INTO shaxs_voqealar (shaxs_id, sana, sarlavha, matn) VALUES ($id, '$sn', '$s', '$m')");
                        }
                    }
                }
                // ===========================================

                $message = "Shaxs ma'lumotlari muvaffaqiyatli yangilandi!";
                
                $shaxs = shaxs_olish($id);
                $shaxs['ism'] = tozalash_html($shaxs['ism']);
                $shaxs['familiya'] = tozalash_html($shaxs['familiya']);
                $shaxs['otasining_ismi'] = tozalash_html($shaxs['otasining_ismi'] ?? '');
                $shaxs['tugilgan_joy'] = tozalash_html($shaxs['tugilgan_joy'] ?? '');
                $shaxs['kasbi'] = tozalash_html($shaxs['kasbi'] ?? '');
                $shaxs['telefon'] = tozalash_html($shaxs['telefon'] ?? '');
                $shaxs['bio'] = tozalash_html($shaxs['bio'] ?? '');

                $ota_ona = ota_ona_olish($id);
                $turmush_ortogi = turmush_ortogi_olish($id);
            } else {
                $error = 'Yangilashda xatolik yuz berdi!';
            }
        }
    }
}

// BAZADAN JORIY SHAXS GALEREYASINI OLIB KELISH
$galereya_rasmlar = [];
try {
    $g_res = db_query("SELECT * FROM shaxs_galereya WHERE shaxs_id = $id ORDER BY id DESC");
    if ($g_res) { while ($g_row = $g_res->fetch_assoc()) { $galereya_rasmlar[] = $g_row; } }
} catch (Exception $e) {}

// BAZADAN JORIY SHAXS VOQEALARINI OLIB KELISH
$voqealar_ruyxati = [];
try {
    $v_res = db_query("SELECT * FROM shaxs_voqealar WHERE shaxs_id = $id ORDER BY sana ASC");
    if ($v_res) { while ($v_row = $v_res->fetch_assoc()) { $voqealar_ruyxati[] = $v_row; } }
} catch (Exception $e) {}

?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shaxsni tahrirlash | Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .admin-container { display: flex; min-height: 100vh; }

        .sidebar { width: 280px; background: linear-gradient(135deg, #2c3e50, #1a252f); color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; margin-top: 10px; }
        .sidebar-header i { font-size: 48px; color: #48c78e; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu ul { list-style: none; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: #ecf0f1; text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); border-left-color: #48c78e; }
        .sidebar-menu i { width: 25px; margin-right: 15px; }

        .main-content { flex: 1; margin-left: 280px; padding: 30px; }

        .page-header { background: white; padding: 20px 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { color: #2c3e50; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .header-actions { display: flex; gap: 15px; }
        .back-btn { padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .back-btn:hover { background: #5a67d8; transform: translateX(-5px); }

        .form-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #48c78e20; color: #48c78e; border-left: 4px solid #48c78e; }
        .alert-error { background: #f4565620; color: #f45656; border-left: 4px solid #f45656; }

        .photo-upload-section { grid-column: span 2; background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 30px; border-radius: 15px; margin-bottom: 30px; border: 2px dashed #667eea; transition: all 0.3s; }
        .photo-upload-section:hover { border-color: #48c78e; background: #e8f0fe; }
        .photo-circle-container { display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .photo-circle { width: 150px; height: 150px; border-radius: 50%; background: white; border: 4px solid #667eea; overflow: hidden; cursor: pointer; position: relative; box-shadow: 0 5px 15px rgba(102,126,234,0.3); transition: all 0.3s; }
        .photo-circle:hover { transform: scale(1.05); border-color: #48c78e; box-shadow: 0 8px 25px rgba(72,199,142,0.4); }
        .photo-circle img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .photo-circle .no-photo { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-size: 48px; }
        .photo-circle-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; border-radius: 50%; }
        .photo-circle:hover .photo-circle-overlay { opacity: 1; }
        .photo-circle-overlay i { color: white; font-size: 32px; }
        .photo-info { text-align: center; }
        .photo-info h3 { color: #2c3e50; margin-bottom: 5px; font-size: 16px; }
        .photo-info p { color: #7f8c8d; font-size: 13px; }
        .photo-upload-input { display: none; }
        .photo-actions { margin-top: 10px; display: flex; gap: 10px; justify-content: center; }
        .remove-photo-label { display: inline-flex; align-items: center; gap: 5px; padding: 8px 15px; background: #f8f9fa; border-radius: 20px; cursor: pointer; font-size: 13px; color: #f45656; border: 1px solid #f45656; transition: all 0.3s; }
        .remove-photo-label:hover { background: #f45656; color: white; }
        .remove-photo-label input[type="checkbox"] { display: none; }

        .gallery-upload-section, .timeline-upload-section { grid-column: span 2; margin-top: 15px; padding: 25px; border-radius: 12px; background: #fff; border: 1px solid #e0e0e0; }
        .gallery-title { font-size: 18px; color: #2c3e50; font-weight: 600; margin-bottom: 15px; display:flex; align-items:center; gap:8px; }
        .gallery-upload-box { border: 2px dashed #cbd5e1; padding: 30px; text-align: center; border-radius: 12px; background: #f8fafc; cursor: pointer; transition: all 0.3s; margin-bottom:20px; }
        .gallery-upload-box:hover { border-color: #667eea; background: #eff6ff; }
        .gallery-upload-box i { font-size: 36px; color: #94a3b8; margin-bottom: 10px; }
        .gallery-upload-box p { color: #475569; font-size: 14px; font-weight: 500; }
        
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; }
        .gallery-item { position: relative; width: 100%; aspect-ratio: 1; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 2px solid transparent; transition: all 0.3s; }
        .gallery-item:hover { border-color: #667eea; transform: scale(1.02); }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-item .delete-btn { position: absolute; top: 5px; right: 5px; background: rgba(244, 86, 86, 0.9); color: white; border: none; width: 28px; height: 28px; border-radius: 50%; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; }
        .gallery-item:hover .delete-btn { opacity: 1; }
        .gallery-item .delete-btn:hover { background: #d43f3f; transform: scale(1.1); }

        .voqea-item { background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; border-left: 4px solid #667eea; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;}
        .voqea-item:hover { transform: translateX(3px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .voqea-item strong { color: #667eea; background: rgba(102,126,234,0.1); padding: 2px 8px; border-radius: 4px; font-size: 13px; margin-right: 8px;}
        .voqea-item .sarlavha { font-weight: 700; color: #2c3e50; font-size: 15px;}
        .voqea-item .matn { font-size: 13px; color: #64748b; margin-top: 4px; }

        .info-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #667eea; }
        .info-box h3 { color: #2c3e50; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .info-item { display: flex; flex-direction: column; gap: 5px; }
        .info-item .label { font-size: 12px; color: #7f8c8d; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #2c3e50; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 500; }
        .form-group label i { color: #667eea; width: 20px; margin-right: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; transition: all 0.3s; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .form-group input:read-only { background: #f8f9fa; cursor: not-allowed; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .checkbox-group label { margin-bottom: 0; cursor: pointer; }

        .form-actions { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 15px; justify-content: flex-end; flex-wrap: wrap; position: sticky; bottom: 0; background: #f5f7fa; padding-bottom: 20px; z-index: 100;}
        .btn-submit { padding: 14px 30px; background: linear-gradient(135deg, #48c78e, #3aa87a); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(72, 199, 142, 0.3); }
        .btn-reset { padding: 14px 30px; background: #95a5a6; color: white; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s; }
        .btn-reset:hover { background: #7f8c8d; }
        .btn-delete { padding: 14px 30px; background: #f45656; color: white; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s; margin-right: auto; }
        .btn-delete:hover { background: #d43f3f; transform: translateY(-2px); }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width, .photo-upload-section, .gallery-upload-section, .timeline-upload-section { grid-column: span 1; }
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
                    <li><a href="eslatmalar.php"><i class="fas fa-bell"></i> Eslatmalar</a></li>
                    <li><a href="statistika.php"><i class="fas fa-chart-bar"></i> Statistika</a></li>
                    <li><a href="sozlamalar.php"><i class="fas fa-cog"></i> Sozlamalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-edit"></i> Shaxsni tahrirlash: <?php echo $shaxs['ism'] . ' ' . $shaxs['familiya']; ?></h1>
                <div class="header-actions">
                    <a href="shaxslar.php" class="back-btn"><i class="fas fa-arrow-left"></i> Ro'yxatga qaytish</a>
                </div>
            </div>

            <div class="form-container">
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

                <div class="info-box">
                    <h3><i class="fas fa-info-circle"></i> Shaxs haqida qisqacha</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">ID</span>
                            <span class="value">#<?php echo $shaxs['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Yosh</span>
                            <span class="value"><?php 
                                $yosh = yosh_hisoblash($shaxs['tugilgan_sana']);
                                echo is_numeric($yosh) ? $yosh . ' yosh' : $yosh;
                            ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Qo'shilgan sana</span>
                            <span class="value"><?php echo date('d.m.Y H:i', strtotime($shaxs['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">So'nggi yangilanish</span>
                            <span class="value"><?php echo date('d.m.Y H:i', strtotime($shaxs['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" enctype="multipart/form-data" id="tahrirlashForm">
                    <div class="photo-upload-section">
                        <div class="photo-circle-container">
                            <div class="photo-circle" onclick="document.getElementById('foto').click()">
                                <?php if (!empty($shaxs['foto'])): ?>
                                    <img src="../assets/uploads/<?php echo $shaxs['foto']; ?>" alt="Shaxs rasmi" id="profileImage">
                                <?php else: ?>
                                    <div class="no-photo" id="noPhotoIcon">
                                        <i class="fas fa-<?php echo $shaxs['jins'] == 'erkak' ? 'male' : 'female'; ?>"></i>
                                    </div>
                                <?php endif; ?>
                                <img src="" alt="Preview" id="previewImage" style="display: none;">
                                <div class="photo-circle-overlay">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                            
                            <div class="photo-info">
                                <h3><i class="fas fa-camera"></i> Asosiy Profil Rasmi</h3>
                                <p>JPG, PNG, WEBP (max 5MB)</p>
                            </div>
                            
                            <input type="file" name="foto" id="foto" class="photo-upload-input" 
                                   accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewCircleImage(this)">
                            
                            <?php if (!empty($shaxs['foto'])): ?>
                            <div class="photo-actions">
                                <label class="remove-photo-label">
                                    <input type="checkbox" name="remove_photo" value="1" onchange="toggleRemovePhoto(this)">
                                    <i class="fas fa-trash"></i> Rasmni o'chirish
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Ism *</label>
                            <input type="text" name="ism" required placeholder="Ismni kiriting" 
                                   value="<?php echo $shaxs['ism']; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Familiya *</label>
                            <input type="text" name="familiya" required placeholder="Familiyani kiriting" 
                                   value="<?php echo $shaxs['familiya']; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Otasining ismi</label>
                            <input type="text" name="otasining_ismi" placeholder="Otasining ismi" 
                                   value="<?php echo $shaxs['otasining_ismi']; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Jins *</label>
                            <select name="jins" required>
                                <option value="">Tanlang...</option>
                                <option value="erkak" <?php echo $shaxs['jins'] == 'erkak' ? 'selected' : ''; ?>>Erkak</option>
                                <option value="ayol" <?php echo $shaxs['jins'] == 'ayol' ? 'selected' : ''; ?>>Ayol</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Tug'ilgan sana</label>
                            <input type="date" name="tugilgan_sana" value="<?php echo $shaxs['tugilgan_sana']; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-times"></i> Vafot etgan sana</label>
                            <input type="date" name="vafot_sana" value="<?php echo $shaxs['vafot_sana']; ?>">
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group" style="margin-top: 30px;">
                                <input type="checkbox" name="tirik" id="tirik" <?php echo $shaxs['tirik'] ? 'checked' : ''; ?>>
                                <label for="tirik"><i class="fas fa-heart"></i> Tirik</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Tug'ilgan joy</label>
                            <input type="text" name="tugilgan_joy" placeholder="Tug'ilgan joy" 
                                   value="<?php echo $shaxs['tugilgan_joy']; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Kasbi</label>
                            <input type="text" name="kasbi" placeholder="Kasbi" 
                                   value="<?php echo $shaxs['kasbi']; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Telefon</label>
                            <input type="text" name="telefon" placeholder="+998 XX XXX XX XX" 
                                   value="<?php echo $shaxs['telefon']; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-male"></i> Otasi</label>
                            <select name="ota_id">
                                <option value="">-- Tanlang --</option>
                                <?php foreach ($shaxslar as $s): ?>
                                    <?php if ($s['jins'] == 'erkak' && $s['id'] != $id): ?>
                                        <option value="<?php echo $s['id']; ?>"
                                            <?php echo ($ota_ona['ota_id'] ?? '') == $s['id'] ? 'selected' : ''; ?>>
                                            <?php echo $s['ism'] . ' ' . $s['familiya']; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-female"></i> Onasi</label>
                            <select name="ona_id">
                                <option value="">-- Tanlang --</option>
                                <?php foreach ($shaxslar as $s): ?>
                                    <?php if ($s['jins'] == 'ayol' && $s['id'] != $id): ?>
                                        <option value="<?php echo $s['id']; ?>"
                                            <?php echo ($ota_ona['ona_id'] ?? '') == $s['id'] ? 'selected' : ''; ?>>
                                            <?php echo $s['ism'] . ' ' . $s['familiya']; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label><i class="fas fa-heart"></i> Turmush o'rtog'i</label>
                            <select name="turmush_ortogi_id">
                                <option value="">-- Tanlang --</option>
                                <?php foreach ($shaxslar as $s): ?>
                                    <?php if ($s['id'] != $id): ?>
                                        <option value="<?php echo $s['id']; ?>"
                                            <?php echo ($turmush_ortogi ?? '') == $s['id'] ? 'selected' : ''; ?>>
                                            <?php echo $s['ism'] . ' ' . $s['familiya']; ?>
                                            (<?php echo $s['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label><i class="fas fa-align-left"></i> Qisqacha ma'lumot</label>
                            <textarea name="bio" rows="4" placeholder="Shaxs haqida qo'shimcha ma'lumot..."><?php echo $shaxs['bio']; ?></textarea>
                        </div>
                        
                        <div class="timeline-upload-section">
                            <div class="gallery-title">
                                <i class="fas fa-stream" style="color:#f5b042;"></i> Hayot yo'li (Maxsus voqealar)
                            </div>
                            <p style="color:#7f8c8d; font-size:13px; margin-bottom:20px;">Shaxsning hayotidagi eng muhim voqealarni (To'y, Universitet, Ko'chib o'tish) saqlab qo'yishingiz mumkin.</p>
                            
                            <div id="voqealar-list">
                                <?php if (!empty($voqealar_ruyxati)): ?>
                                    <?php foreach ($voqealar_ruyxati as $v): ?>
                                        <div class="voqea-item" id="voqea_<?php echo $v['id']; ?>">
                                            <div>
                                                <strong><?php echo date('d.m.Y', strtotime($v['sana'])); ?></strong> 
                                                <span class="sarlavha"><?php echo htmlspecialchars($v['sarlavha']); ?></span>
                                                <?php if($v['matn']): ?>
                                                    <div class="matn"><?php echo htmlspecialchars($v['matn']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn-delete" style="padding: 8px 12px; margin: 0; background:#f4565620; color:#f45656;" onclick="deleteVoqea(<?php echo $v['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="color: #94a3b8; font-size: 14px; text-align:center; padding:15px; border:1px dashed #cbd5e1; border-radius:10px; margin-bottom:15px;">Hozircha maxsus voqealar qo'shilmagan.</p>
                                <?php endif; ?>
                            </div>

                            <div id="new-voqealar-container"></div>

                            <div style="text-align:center; margin-top:15px;">
                                <button type="button" class="btn-reset" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;" onclick="addVoqeaRow()">
                                    <i class="fas fa-plus"></i> Yangi voqea qo'shish
                                </button>
                            </div>
                        </div>

                        <div class="gallery-upload-section">
                            <div class="gallery-title">
                                <i class="fas fa-images" style="color:#667eea;"></i> Media Galereya (Eski rasmlar, hujjatlar)
                            </div>
                            
                            <div class="gallery-upload-box" onclick="document.getElementById('galereya_rasmlar').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Yangi rasmlar qo'shish uchun bosing (Bir nechta tanlash mumkin)</p>
                            </div>
                            <input type="file" name="galereya_rasmlar[]" id="galereya_rasmlar" multiple accept="image/*" style="display:none;" onchange="updateGalleryLabel(this)">
                            <div id="gallery-status" style="text-align:center; color:#48c78e; font-weight:600; margin-bottom:15px; font-size:13px;"></div>

                            <div class="gallery-grid" id="existing_gallery">
                                <?php if (!empty($galereya_rasmlar)): ?>
                                    <?php foreach ($galereya_rasmlar as $r): ?>
                                        <div class="gallery-item" id="gal_item_<?php echo $r['id']; ?>">
                                            <img src="../assets/uploads/<?php echo $r['fayl']; ?>" alt="Galereya rasmi">
                                            <button type="button" class="delete-btn" onclick="deleteGalleryImage(<?php echo $r['id']; ?>)" title="O'chirish">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="grid-column: 1 / -1; text-align: center; color: #94a3b8; font-size: 14px; padding: 20px;">
                                        Ushbu shaxsda hali qo'shimcha rasmlar yo'q.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="button" onclick="ochirish(<?php echo $id; ?>)" class="btn-delete">
                            <i class="fas fa-trash"></i> O'chirish
                        </button>
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
                    const profileImage = document.getElementById('profileImage');
                    const noPhotoIcon = document.getElementById('noPhotoIcon');
                    
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                    
                    if (profileImage) profileImage.style.display = 'none';
                    if (noPhotoIcon) noPhotoIcon.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function toggleRemovePhoto(checkbox) {
            const photoCircle = document.querySelector('.photo-circle');
            if (checkbox.checked) {
                photoCircle.style.opacity = '0.5';
                photoCircle.style.borderColor = '#f45656';
            } else {
                photoCircle.style.opacity = '1';
                photoCircle.style.borderColor = '#667eea';
            }
        }

        function updateGalleryLabel(input) {
            const statusDiv = document.getElementById('gallery-status');
            if (input.files && input.files.length > 0) {
                statusDiv.innerHTML = `<i class="fas fa-check"></i> ${input.files.length} ta rasm tanlandi. "Saqlash" tugmasini bosing!`;
            } else {
                statusDiv.innerHTML = '';
            }
        }

        // Galereya rasmini o'chirish (AJAX)
        function deleteGalleryImage(imgId) {
            if (confirm("Bu rasmni rostdan ham o'chirasizmi?")) {
                const formData = new FormData();
                formData.append('delete_gallery_image', '1');
                formData.append('image_id', imgId);

                fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const item = document.getElementById('gal_item_' + imgId);
                        item.style.transform = 'scale(0)';
                        setTimeout(() => item.remove(), 300);
                    } else {
                        alert("O'chirishda xatolik yuz berdi!");
                    }
                }).catch(console.error);
            }
        }

        // TIMELINE: Voqeani o'chirish (AJAX)
        function deleteVoqea(vId) {
            if (confirm("Bu voqeani rostdan ham o'chirasizmi?")) {
                const formData = new FormData();
                formData.append('delete_voqea', '1');
                formData.append('voqea_id', vId);

                fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const item = document.getElementById('voqea_' + vId);
                        item.style.transform = 'translateX(20px)';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 300);
                    } else {
                        alert("O'chirishda xatolik yuz berdi!");
                    }
                }).catch(console.error);
            }
        }

        // TIMELINE: Yangi voqea qatori qo'shish
        function addVoqeaRow() {
            const container = document.getElementById('new-voqealar-container');
            const row = document.createElement('div');
            row.style.cssText = "background: #f8fafc; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 10px; margin-bottom: 15px; position:relative; animation: modalIn 0.3s ease;";
            row.innerHTML = `
                <button type="button" onclick="this.parentElement.remove()" style="position:absolute; top:10px; right:10px; background:none; border:none; color:#f45656; cursor:pointer; font-size:16px;" title="O'chirish"><i class="fas fa-times-circle"></i></button>
                <div style="display:flex; gap:15px; margin-bottom:10px;">
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Sana *</label>
                        <input type="date" name="yangi_voqea_sana[]" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; outline:none; font-family:inherit;">
                    </div>
                    <div style="flex:2;">
                        <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Sarlavha (Masalan: To'y) *</label>
                        <input type="text" name="yangi_voqea_sarlavha[]" required placeholder="Nima voqea bo'ldi?" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; outline:none; font-family:inherit;">
                    </div>
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Qisqacha izoh (Ixtiyoriy)</label>
                    <input type="text" name="yangi_voqea_matn[]" placeholder="Batafsil ma'lumot kiriting..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; outline:none; font-family:inherit;">
                </div>
            `;
            container.appendChild(row);
        }

        // Form tozalash
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            setTimeout(function() {
                const previewImage = document.getElementById('previewImage');
                const profileImage = document.getElementById('profileImage');
                const noPhotoIcon = document.getElementById('noPhotoIcon');
                const fotoInput = document.getElementById('foto');
                const removeCheckbox = document.querySelector('input[name="remove_photo"]');
                const galleryStatus = document.getElementById('gallery-status');
                const newVoqealarContainer = document.getElementById('new-voqealar-container');
                
                previewImage.style.display = 'none';
                previewImage.src = '';
                fotoInput.value = '';
                galleryStatus.innerHTML = '';
                newVoqealarContainer.innerHTML = ''; // Yangi voqealarni ham tozalash
                
                if (profileImage) profileImage.style.display = 'block';
                else if (noPhotoIcon) noPhotoIcon.style.display = 'flex';
                
                if (removeCheckbox) removeCheckbox.checked = false;
                
                const photoCircle = document.querySelector('.photo-circle');
                photoCircle.style.opacity = '1';
                photoCircle.style.borderColor = '#667eea';
            }, 10);
        });

        function ochirish(id) {
            if (confirm('Haqiqatan ham bu shaxsni o\'chirmoqchimisiz?\nBu amalni ortga qaytarib bo\'lmaydi!')) {
                fetch(`../api/shaxs.php?id=${id}`, { method: 'DELETE' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Shaxs muvaffaqiyatli o\'chirildi');
                        window.location.href = 'shaxslar.php';
                    } else {
                        if (data.farzandlar_soni > 0) {
                            const forceDelete = confirm(`Bu shaxsning ${data.farzandlar_soni} ta farzandi bor.\n\nFarzandlari bilan birga o\'chirishni xohlaysizmi?\n\n⚠️ OGOHLANTIRISH: Bu amalni ortga qaytarib bo\'lmaydi!`);
                            if (forceDelete) {
                                fetch(`../api/shaxs.php?id=${id}&force=1`, { method: 'DELETE' })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        alert('Shaxs va uning farzandlari o\'chirildi');
                                        window.location.href = 'shaxslar.php';
                                    } else {
                                        alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                                    }
                                });
                            }
                        } else {
                            alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                        }
                    }
                }).catch(error => { console.error('Xatolik:', error); alert('Xatolik yuz berdi'); });
            }
        }

        document.querySelector('input[name="telefon"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9+]/g, '');
            if (value.length > 0 && !value.startsWith('+')) value = '+' + value;
            e.target.value = value;
        });

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

        if (tirikCheckbox.checked) vafotSanaInput.disabled = true;
    </script>
</body>
</html>