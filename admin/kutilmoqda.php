<?php
// =============================================
// FILE: admin/kutilmoqda.php
// MAQSAD: Botdan kelgan arizalarni tasdiqlash va shajaraga qo'shish
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/shajara_functions.php';

// Bot tokeningiz (Rasmlarni yuklab olish uchun kerak)
define('BOT_TOKEN', '8504597068:AAE3X0K1STed1nVaveY8aqguUBlseEjPUqw');

if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Telegram rasmini serverga yuklab olish funksiyasi
function downloadTelegramPhoto($file_id) {
    if (!$file_id) return null;
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getFile?file_id=" . $file_id;
    $res = @file_get_contents($url);
    if ($res) {
        $json = json_decode($res, true);
        if ($json['ok']) {
            $file_path = $json['result']['file_path'];
            $file_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $file_path;
            
            $ext = pathinfo($file_path, PATHINFO_EXTENSION) ?: 'jpg';
            $new_name = 'tg_' . time() . '_' . uniqid() . '.' . $ext;
            $save_path = __DIR__ . '/../assets/uploads/' . $new_name;
            
            if (@file_put_contents($save_path, file_get_contents($file_url))) {
                return $new_name;
            }
        }
    }
    return null;
}

// Ariza tasdiqlanganda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tasdiqlash_id'])) {
    $k_id = (int)$_POST['tasdiqlash_id'];
    $ota_id = !empty($_POST['ota_id']) ? (int)$_POST['ota_id'] : null;
    $ona_id = !empty($_POST['ona_id']) ? (int)$_POST['ona_id'] : null;

    dbConnect();
    $k_res = db_query("SELECT * FROM shaxslar_kutilmoqda WHERE id = $k_id");
    if ($k_res && $k_res->num_rows > 0) {
        $ariza = $k_res->fetch_assoc();
        
        // Rasmni telegramdan tortib olish
        $foto_nomi = null;
        if (!empty($ariza['foto'])) {
            $foto_nomi = downloadTelegramPhoto($ariza['foto']);
        }

        // Asosiy shaxslar jadvaliga yozish
        $yangi_shaxs = [
            'ism' => $ariza['ism'],
            'familiya' => $ariza['familiya'],
            'jins' => $ariza['jins'],
            'tugilgan_sana' => $ariza['tugilgan_sana'],
            'telefon' => $ariza['telefon'],
            'kasbi' => $ariza['kasbi'],
            'foto' => $foto_nomi,
            'added_by_tg_id' => $ariza['added_by_tg_id']
        ];

        $yangi_id = shaxs_qoshish($yangi_shaxs);

        if ($yangi_id) {
            // Ota-onasini bog'lash
            if ($ota_id || $ona_id) {
                ota_ona_qoshish($yangi_id, $ota_id, $ona_id);
            }
            
            // Holatni o'zgartirish
            db_query("UPDATE shaxslar_kutilmoqda SET status = 'tasdiqlangan' WHERE id = $k_id");
            
            // Foydalanuvchiga Telegramdan xabar yuborish
            $tg_id = $ariza['added_by_tg_id'];
            $msg = "✅ Siz qo'shgan <b>{$ariza['ism']} {$ariza['familiya']}</b> admin tomonidan tasdiqlandi va shajaraga qo'shildi!";
            @file_get_contents("https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage?chat_id=$tg_id&text=" . urlencode($msg) . "&parse_mode=HTML");

            $message = "Ariza muvaffaqiyatli tasdiqlandi va shajaraga qo'shildi!";
        } else {
            $error = "Xatolik yuz berdi.";
        }
    }
}

// Ariza bekor qilinganda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bekor_id'])) {
    $k_id = (int)$_POST['bekor_id'];
    db_query("UPDATE shaxslar_kutilmoqda SET status = 'bekor_qilingan' WHERE id = $k_id");
    $message = "Ariza rad etildi.";
}

// Barcha kutilayotgan arizalarni olish
$arizalar = [];
$res = db_query("SELECT * FROM shaxslar_kutilmoqda WHERE status = 'kutilmoqda' ORDER BY created_at DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $arizalar[] = $r;
    }
}

// Ota va Onalarni tanlash uchun barcha shaxslarni olish
$shaxslar = shaxslar_roixati();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Yangi arizalar | Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .admin-container { display: flex; min-height: 100vh; }
        
        .sidebar { width: 280px; background: linear-gradient(135deg, #2c3e50, #1a252f); color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu ul { list-style: none; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: #ecf0f1; text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); border-left-color: #48c78e; }
        .sidebar-menu i { width: 25px; margin-right: 15px; }

        .main-content { flex: 1; margin-left: 280px; padding: 30px; }
        .page-header { background: white; padding: 20px 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; }
        .alert-success { background: #48c78e20; color: #48c78e; border-left: 4px solid #48c78e; }
        .alert-error { background: #f4565620; color: #f45656; border-left: 4px solid #f45656; }

        .ariza-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; gap: 20px; border: 1px solid #e2e8f0; border-left: 5px solid #f5b042; }
        .ariza-img { width: 100px; height: 100px; border-radius: 12px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 30px; color: #94a3b8; overflow: hidden; flex-shrink: 0; }
        .ariza-img img { width: 100%; height: 100%; object-fit: cover; }
        .ariza-info { flex: 1; }
        .ariza-info h3 { color: #1e293b; font-size: 18px; margin-bottom: 8px; }
        .ariza-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; font-size: 14px; color: #475569; margin-bottom: 15px; }
        
        .ariza-actions { background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; }
        .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; }
        .btn-success { background: #48c78e; color: white; }
        .btn-success:hover { background: #3aa87a; }
        .btn-danger { background: #f45656; color: white; }
        .btn-danger:hover { background: #d43f3f; }
        
        .empty-state { text-align: center; padding: 50px; background: white; border-radius: 15px; color: #94a3b8; }
        .empty-state i { font-size: 50px; margin-bottom: 15px; color: #cbd5e1; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-tree"></i><h2>Admin Panel</h2>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="kutilmoqda.php" class="active"><i class="fas fa-clock"></i> Yangi arizalar (<?php echo count($arizalar); ?>)</a></li>
                    <li><a href="shaxslar.php"><i class="fas fa-users"></i> Shaxslar</a></li>
                    <li><a href="qoshish.php"><i class="fas fa-plus-circle"></i> Yangi qo'shish</a></li>
                    <li><a href="eslatmalar.php"><i class="fas fa-bell"></i> Eslatmalar</a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Chiqish</a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-clock" style="color:#f5b042;"></i> Botdan kelgan yangi arizalar</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (empty($arizalar)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Hozircha yangi arizalar yo'q</h3>
                    <p>Bot orqali kiritilgan ma'lumotlar shu yerda ko'rinadi.</p>
                </div>
            <?php else: ?>
                <?php foreach ($arizalar as $a): ?>
                    <div class="ariza-card">
                        <div class="ariza-img">
                            <?php if ($a['foto']): ?>
                                <i class="fas fa-image" title="Telegram rasm tasdiqlangach yuklanadi"></i>
                            <?php else: ?>
                                <i class="fas fa-<?php echo $a['jins'] == 'erkak' ? 'male' : 'female'; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ariza-info">
                            <h3><?php echo htmlspecialchars($a['ism'] . ' ' . $a['familiya']); ?></h3>
                            <div class="ariza-details">
                                <div><i class="fas fa-venus-mars"></i> <?php echo $a['jins'] == 'erkak' ? 'Erkak' : 'Ayol'; ?></div>
                                <div><i class="fas fa-calendar"></i> <?php echo $a['tugilgan_sana'] ?: 'Kiritilmagan'; ?></div>
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($a['telefon']) ?: 'Kiritilmagan'; ?></div>
                                <div><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($a['kasbi']) ?: 'Kiritilmagan'; ?></div>
                            </div>
                            
                            <form method="POST" class="ariza-actions">
                                <input type="hidden" name="tasdiqlash_id" value="<?php echo $a['id']; ?>">
                                <div style="display:flex; gap:15px; margin-bottom:15px;">
                                    <div class="form-group" style="flex:1;">
                                        <label>Shajaradagi otasi:</label>
                                        <select name="ota_id">
                                            <option value="">-- Otasini tanlang --</option>
                                            <?php foreach ($shaxslar as $s) if ($s['jins'] == 'erkak') echo "<option value='{$s['id']}'>{$s['ism']} {$s['familiya']}</option>"; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" style="flex:1;">
                                        <label>Shajaradagi onasi:</label>
                                        <select name="ona_id">
                                            <option value="">-- Onasini tanlang --</option>
                                            <?php foreach ($shaxslar as $s) if ($s['jins'] == 'ayol') echo "<option value='{$s['id']}'>{$s['ism']} {$s['familiya']}</option>"; ?>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex; gap:10px;">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Tasdiqlash va Qo'shish</button>
                                    <button type="submit" formaction="" formmethod="POST" name="bekor_id" value="<?php echo $a['id']; ?>" class="btn btn-danger"><i class="fas fa-times"></i> Rad etish</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>