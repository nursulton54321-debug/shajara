<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/shajara_functions.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    dbConnect();
    $db_status = "ok";
    
    // Sozlamalar jadvalini avtomatik yaratish va standart PIN-kodni o'rnatish (birinchi marta ishlaganda)
    db_query("CREATE TABLE IF NOT EXISTS sozlamalar (kalit VARCHAR(50) PRIMARY KEY, qiymat VARCHAR(255))");
    db_query("INSERT IGNORE INTO sozlamalar (kalit, qiymat) VALUES ('sayt_pin', '2026')");

} catch (Exception $e) {
    $db_status = "error";
    $db_error = $e->getMessage();
}

// ---------------------------------------------------------
// PIN-KOD HIMOYASI TIZIMI
// ---------------------------------------------------------
$pin_res = db_query("SELECT qiymat FROM sozlamalar WHERE kalit = 'sayt_pin'");
$real_pin = ($pin_res && $pin_res->num_rows > 0) ? $pin_res->fetch_assoc()['qiymat'] : '2026';

if (isset($_POST['enter_pin'])) {
    if ($_POST['pin_code'] === $real_pin) {
        $_SESSION['oila_pin_verified'] = true;
        header("Location: index.php");
        exit;
    } else {
        $pin_error = "PIN-kod noto'g'ri! Iltimos, qayta urinib ko'ring.";
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['oila_pin_verified']);
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['oila_pin_verified']) || $_SESSION['oila_pin_verified'] !== true) {
    // PIN-KOD KIRITISH EKRANI (LOCK SCREEN)
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kirish | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        body { 
            display: flex; align-items: center; justify-content: center; min-height: 100vh; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); overflow: hidden;
        }
        .lock-card {
            background: rgba(255, 255, 255, 0.95); padding: 40px 30px; border-radius: 20px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); text-align: center; width: 90%; max-width: 400px;
            backdrop-filter: blur(10px);
        }
        .lock-icon {
            width: 80px; height: 80px; background: #667eea20; color: #667eea; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 20px;
        }
        .lock-card h2 { color: #2c3e50; margin-bottom: 10px; font-size: 24px; }
        .lock-card p { color: #7f8c8d; margin-bottom: 25px; font-size: 14.5px; line-height: 1.5; }
        .pin-input {
            width: 100%; padding: 15px; font-size: 28px; text-align: center; letter-spacing: 12px;
            border: 2px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; outline: none; 
            transition: border 0.3s; font-weight: bold; color: #2c3e50; background: #f8f9fa;
        }
        .pin-input:focus { border-color: #667eea; background: #fff; }
        .pin-btn {
            width: 100%; padding: 15px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; 
            border: none; border-radius: 12px; font-size: 16px; font-weight: bold; cursor: pointer; 
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .pin-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
        .pin-error {
            color: #e74c3c; font-size: 13.5px; margin-bottom: 15px; background: #fdf0f0; 
            padding: 10px; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 8px;
        }
        .bot-link { margin-top: 20px; display: block; color: #667eea; text-decoration: none; font-size: 14px; font-weight: 600; }
        .bot-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="lock-card">
        <div class="lock-icon"><i class="fas fa-shield-alt"></i></div>
        <h2>Maxfiy Shajara</h2>
        <p>Oila shajarasiga kirish faqat ruxsat etilgan a'zolar uchun. Iltimos, <b>PIN-kodni</b> kiriting.</p>
        
        <?php if (isset($pin_error)): ?>
            <div class="pin-error"><i class="fas fa-exclamation-circle"></i> <?php echo $pin_error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="password" name="pin_code" class="pin-input" placeholder="••••" required autofocus autocomplete="off">
            <button type="submit" name="enter_pin" class="pin-btn"><i class="fas fa-unlock"></i> Shajarani ochish</button>
        </form>
        <a href="https://t.me/mening_shajarambot" target="_blank" class="bot-link"><i class="fab fa-telegram"></i> Telegram bot orqali PIN-kod olish</a>
    </div>
</body>
</html>
<?php
    exit;
}
// ---------------------------------------------------------
// ASOSIY SAHIFA KODI (PIN VERIFIED)
// ---------------------------------------------------------

$stats = [];
try {
    $stats = oila_statistikasi();

    $r = db_query("SELECT ism,familiya,YEAR(CURDATE())-YEAR(tugilgan_sana) as yosh
                   FROM shaxslar
                   WHERE tirik=1 AND tugilgan_sana IS NOT NULL
                   ORDER BY tugilgan_sana ASC LIMIT 1");
    $stats['eng_keksa'] = $r ? $r->fetch_assoc() : null;

    $r = db_query("SELECT ism,familiya,YEAR(CURDATE())-YEAR(tugilgan_sana) as yosh
                   FROM shaxslar
                   WHERE tirik=1 AND tugilgan_sana IS NOT NULL
                   ORDER BY tugilgan_sana DESC LIMIT 1");
    $stats['eng_yosh'] = $r ? $r->fetch_assoc() : null;

    $r = db_query("SELECT COUNT(*) as soni FROM shaxslar WHERE MONTH(tugilgan_sana)=MONTH(CURDATE())");
    $stats['shu_oy_tugilgan'] = $r ? (int)($r->fetch_assoc()['soni'] ?? 0) : 0;

    if (!isset($stats['vafot'])) {
        $r = db_query("SELECT COUNT(*) as soni FROM shaxslar WHERE tirik=0");
        $stats['vafot'] = $r ? (int)($r->fetch_assoc()['soni'] ?? 0) : 0;
    }

    if (!isset($stats['jami'])) $stats['jami'] = 0;
    if (!isset($stats['tirik'])) $stats['tirik'] = 0;
    if (!isset($stats['jins'])) $stats['jins'] = ['erkak'=>0,'ayol'=>0];
    if (!isset($stats['ortacha_yosh'])) $stats['ortacha_yosh'] = 0;
    if (!isset($stats['avlodlar'])) $stats['avlodlar'] = 1;

    if (!empty($stats['eng_keksa'])) {
        $stats['eng_keksa']['ism'] = html_entity_decode($stats['eng_keksa']['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $stats['eng_keksa']['familiya'] = html_entity_decode($stats['eng_keksa']['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
    }
    if (!empty($stats['eng_yosh'])) {
        $stats['eng_yosh']['ism'] = html_entity_decode($stats['eng_yosh']['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $stats['eng_yosh']['familiya'] = html_entity_decode($stats['eng_yosh']['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
    }

} catch (Exception $e) {
    $stats = [
        'jami' => 0, 'tirik' => 0, 'vafot' => 0,
        'jins' => ['erkak'=>0,'ayol'=>0], 'ortacha_yosh' => 0,
        'shu_oy_tugilgan' => 0, 'avlodlar' => 1,
        'eng_keksa' => null, 'eng_yosh' => null
    ];
}

$shaxslar = shaxslar_roixati();
if (is_array($shaxslar)) {
    foreach ($shaxslar as &$sh) {
        $sh['ism'] = html_entity_decode($sh['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $sh['familiya'] = html_entity_decode($sh['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
    }
    unset($sh);

    usort($shaxslar, function($a, $b){
        $da = $a['tugilgan_sana'] ?? '9999-12-31';
        $db = $b['tugilgan_sana'] ?? '9999-12-31';
        if ($da === $db) {
            $na = trim(($a['ism'] ?? '') . ' ' . ($a['familiya'] ?? ''));
            $nb = trim(($b['ism'] ?? '') . ' ' . ($b['familiya'] ?? ''));
            return strcasecmp($na, $nb);
        }
        return strcmp($da, $db);
    });
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Oila Shajarasi | <?php echo SITE_NAME; ?></title>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/shajara.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>

    <style>
        body { min-height: 100vh; overflow-x: hidden; }
        .container { min-height: 100vh; }

        .event-form-group { margin-bottom: 15px; text-align: left; }
        .event-form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--text-main); }
        .event-form-group input, 
        .event-form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit;
        }
        .event-btn {
            width: 100%; padding: 12px; background: linear-gradient(135deg, #48c78e, #2ecc71);
            color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s;
        }
        .event-btn:hover { opacity: 0.9; }
        .add-event-btn-wrapper { margin-top: 20px; text-align: center; }
        .add-event-btn {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none;
            padding: 10px 20px; border-radius: 20px; cursor: pointer; font-weight: bold;
            display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s;
        }
        .add-event-btn:hover { transform: scale(1.05); }
    </style>
</head>
<body>
<div class="container" id="pageExportArea">

    <header class="header">
        <div class="header-content">
            <h1><i class="fas fa-tree icon-float"></i> <?php echo SITE_NAME; ?></h1>

            <div class="search-wrapper">
                <div class="search-box">
                    <input type="text" id="qidiruv" placeholder="Ism yoki familiya..." autocomplete="off">
                    <button onclick="qidiruvQilish()" title="Qidirish"><i class="fas fa-search icon-pulse"></i></button>
                </div>
                <div id="qidiruvNatijalar"></div>
            </div>

            <div class="header-buttons">
                <button onclick="toggleTheme()" class="btn-theme" title="Dark / Light">
                    <i class="fas fa-moon icon-float"></i>
                </button>
                <button onclick="exportTreeToPDF()" class="btn-pdf" title="PDF eksport">
                    <i class="fas fa-file-pdf icon-pulse"></i>
                </button>
                <button onclick="openModal('statModal')" class="btn-stat" title="Statistika">
                    <i class="fas fa-chart-bar icon-pulse"></i>
                </button>
                <button onclick="openModal('eslatmaModal')" class="btn-reminder" title="Eslatmalar">
                    <i class="fas fa-bell icon-wiggle"></i>
                </button>
                <button onclick="window.location.href='admin/index.php'" class="btn-admin" title="Admin panel">
                    <i class="fas fa-user-shield icon-pulse"></i>
                </button>
                <button onclick="window.location.href='?logout=1'" class="btn-theme" style="color:#f45656;" title="Qulflash (Chiqish)">
                    <i class="fas fa-lock icon-pulse"></i>
                </button>
            </div>
        </div>
    </header>

    <div class="top-accent"></div>

    <?php if (($db_status ?? '') === 'error'): ?>
    <div style="background:#f4565618;color:#f45656;padding:14px 18px;border-radius:10px;margin-bottom:12px;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($db_error ?? ''); ?>
    </div>
    <?php endif; ?>

    <div class="quick-stats">
        <div class="quick-stat-card">
            <div class="quick-stat-icon qs-blue"><i class="fas fa-users icon-pulse"></i></div>
            <div>
                <div class="quick-stat-value"><?php echo (int)($stats['jami'] ?? 0); ?></div>
                <div class="quick-stat-label">Jami a'zolar</div>
            </div>
        </div>

        <div class="quick-stat-card">
            <div class="quick-stat-icon qs-green"><i class="fas fa-heart icon-pulse"></i></div>
            <div>
                <div class="quick-stat-value"><?php echo (int)($stats['tirik'] ?? 0); ?></div>
                <div class="quick-stat-label">Tiriklar</div>
            </div>
        </div>

        <div class="quick-stat-card">
            <div class="quick-stat-icon qs-red"><i class="fas fa-ribbon icon-wiggle"></i></div>
            <div>
                <div class="quick-stat-value"><?php echo (int)($stats['vafot'] ?? 0); ?></div>
                <div class="quick-stat-label">Vafot etganlar</div>
            </div>
        </div>

        <div class="quick-stat-card">
            <div class="quick-stat-icon qs-orange"><i class="fas fa-cake-candles icon-wiggle"></i></div>
            <div>
                <div class="quick-stat-value"><?php echo (int)($stats['shu_oy_tugilgan'] ?? 0); ?></div>
                <div class="quick-stat-label">Shu oy tug'ilgan</div>
            </div>
        </div>

        <div class="quick-stat-card">
            <div class="quick-stat-icon qs-purple"><i class="fas fa-layer-group icon-float"></i></div>
            <div>
                <div class="quick-stat-value"><?php echo (int)($stats['avlodlar'] ?? 1); ?></div>
                <div class="quick-stat-label">Avlodlar</div>
            </div>
        </div>

        <div class="quick-stat-card">
            <div class="quick-stat-icon qs-teal"><i class="fas fa-chart-line icon-pulse"></i></div>
            <div>
                <div class="quick-stat-value"><?php echo round((float)($stats['ortacha_yosh'] ?? 0)); ?></div>
                <div class="quick-stat-label">O'rtacha yosh</div>
            </div>
        </div>
    </div>

    <div class="tree-container">
        <div class="tree-toolbar">
            <h2><i class="fas fa-sitemap icon-float"></i> Shajara daraxti</h2>

            <div class="tree-controls">
                <button onclick="zoomIn()" title="Kattalashtirish"><i class="fas fa-search-plus icon-pulse"></i></button>
                <button onclick="zoomOut()" title="Kichiklashtirish"><i class="fas fa-search-minus icon-pulse"></i></button>
                <button onclick="resetZoom()" title="Asl holatga qaytarish"><i class="fas fa-rotate-left icon-spin-soft"></i></button>

                <button id="vafotFilterBtn" class="wide-btn" onclick="toggleVafotFilter()" title="Vafot etganlarni xiralashtirish">
                    <i class="fas fa-ribbon icon-wiggle"></i> Vafot etganlar
                </button>

                <select id="shaxsSelect" onchange="shajaraYukla(this.value)">
                    <option value="">— Barcha shaxslar —</option>
                    <?php foreach ($shaxslar as $sh): ?>
                        <option value="<?php echo (int)$sh['id']; ?>">
                            <?php echo htmlspecialchars($sh['ism'] . ' ' . $sh['familiya'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="shajaraDiagram">
            <div class="loading"><i class="fas fa-spinner fa-spin"></i>&nbsp;Yuklanmoqda...</div>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Oila Shajarasi — <?php echo SITE_NAME; ?></p>
    </footer>

</div>

<div id="nodeTooltip" class="node-tooltip"></div>

<div id="statModal" class="umodal" onclick="backdropClose(event,'statModal')">
    <div class="umodal-box">
        <button class="umodal-x" onclick="closeModal('statModal')"><i class="fas fa-times"></i></button>
        <div class="umodal-h"><i class="fas fa-chart-pie icon-pulse" style="color:#48c78e;"></i> Statistika</div>

        <div class="stats-top">
            <div class="st-card" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                <div class="sv"><?php echo (int)($stats['jami'] ?? 0); ?></div>
                <div class="sl">Jami a'zo</div>
            </div>
            <div class="st-card" style="background:linear-gradient(135deg,#48c78e,#2ecc71);">
                <div class="sv"><?php echo (int)($stats['tirik'] ?? 0); ?></div>
                <div class="sl">Tirik</div>
            </div>
            <div class="st-card" style="background:linear-gradient(135deg,#f48fb1,#d16b87);">
                <div class="sv"><?php echo (int)($stats['vafot'] ?? 0); ?></div>
                <div class="sl">Vafot etgan</div>
            </div>
            <div class="st-card" style="background:linear-gradient(135deg,#4299e1,#2980b9);">
                <div class="sv"><?php echo (int)($stats['jins']['erkak'] ?? 0) . '/' . (int)($stats['jins']['ayol'] ?? 0); ?></div>
                <div class="sl">Erkak / Ayol</div>
            </div>
        </div>

        <div class="charts-row">
            <div class="ch-box">
                <h4><i class="fas fa-circle-half-stroke icon-spin-soft"></i>&nbsp; Hayot holati</h4>
                <canvas id="chartHolat"></canvas>
            </div>
            <div class="ch-box">
                <h4><i class="fas fa-venus-mars icon-float"></i>&nbsp; Jins taqsimoti</h4>
                <canvas id="chartJins"></canvas>
            </div>
            <div class="ch-box">
                <h4><i class="fas fa-layer-group icon-pulse"></i>&nbsp; Avlodlar</h4>
                <canvas id="chartAvlod"></canvas>
            </div>
        </div>

        <?php if (!empty($stats['eng_keksa']) || !empty($stats['eng_yosh'])): ?>
        <div class="keksa-row">
            <?php if (!empty($stats['eng_keksa'])): ?>
            <div class="keksa-card">
                <div class="kl">👴 Eng keksa</div>
                <div class="kn"><?php echo htmlspecialchars($stats['eng_keksa']['ism'] . ' ' . $stats['eng_keksa']['familiya']); ?></div>
                <div class="ky" style="color:#667eea;"><?php echo htmlspecialchars($stats['eng_keksa']['yosh'] ?? ''); ?> yosh</div>
            </div>
            <?php endif; ?>
            <?php if (!empty($stats['eng_yosh'])): ?>
            <div class="keksa-card">
                <div class="kl">👶 Eng yosh</div>
                <div class="kn"><?php echo htmlspecialchars($stats['eng_yosh']['ism'] . ' ' . $stats['eng_yosh']['familiya']); ?></div>
                <div class="ky" style="color:#48c78e;"><?php echo htmlspecialchars($stats['eng_yosh']['yosh'] ?? ''); ?> yosh</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="eslatmaModal" class="umodal" onclick="backdropClose(event,'eslatmaModal')">
    <div class="umodal-box narrow">
        <button class="umodal-x" onclick="closeModal('eslatmaModal')"><i class="fas fa-times"></i></button>
        <div class="umodal-h"><i class="fas fa-birthday-cake icon-wiggle" style="color:#f5b042;"></i> Tug'ilgan kunlar</div>
        <div class="eslatma-filters">
            <button class="eslatma-filter-btn active" data-filter="all" onclick="setEslatmaFilter('all', this)">Hammasi</button>
            <button class="eslatma-filter-btn" data-filter="today" onclick="setEslatmaFilter('today', this)">Bugun</button>
            <button class="eslatma-filter-btn" data-filter="week" onclick="setEslatmaFilter('week', this)">Hafta</button>
            <button class="eslatma-filter-btn" data-filter="month" onclick="setEslatmaFilter('month', this)">Oy</button>
        </div>
        <div id="eslatmalarList">
            <p style="color:#aaa;text-align:center;padding:24px;"><i class="fas fa-spinner fa-spin"></i> Yuklanmoqda...</p>
        </div>
    </div>
</div>

<div id="shaxsModal" class="shaxsmodal" onclick="shaxsBackdrop(event)">
    <div class="shaxsmodal-box">
        <button class="umodal-x" onclick="closeShaxsModal()"><i class="fas fa-times"></i></button>
        <h2 id="shaxsModalTitle" style="color:var(--text-main);font-size:19px;margin-bottom:18px;padding-right:28px;"></h2>
        <div id="shaxsModalBody"></div>
        
        <div class="add-event-btn-wrapper">
            <button class="add-event-btn" onclick="openEventModal('add')"><i class="fas fa-plus"></i> Voqea qo'shish</button>
        </div>
    </div>
</div>

<div id="addEventModal" class="umodal" onclick="backdropClose(event,'addEventModal')">
    <div class="umodal-box narrow">
        <button class="umodal-x" onclick="closeModal('addEventModal')"><i class="fas fa-times"></i></button>
        <div class="umodal-h" id="eventModalTitle"><i class="fas fa-calendar-plus" style="color:#667eea;"></i> Voqea taklif qilish</div>
        
        <form id="addEventForm" onsubmit="submitEventForm(event)" style="padding: 10px;">
            <input type="hidden" id="event_shaxs_id" name="shaxs_id">
            <input type="hidden" id="event_harakat" name="harakat" value="add">
            <input type="hidden" id="event_voqea_id" name="voqea_id" value="">
            
            <div class="event-form-group">
                <label>Sizning ismingiz (Kim yuboryapti?)</label>
                <input type="text" id="event_yuboruvchi_ism" name="yuboruvchi_ism" placeholder="Masalan: Nurislom" required>
            </div>
            
            <div class="event-form-group">
                <label>Telefon raqamingiz</label>
                <input type="tel" id="event_yuboruvchi_tel" name="yuboruvchi_tel" placeholder="+998 90 123 45 67" required>
            </div>
            
            <div class="event-form-group" id="group_sarlavha">
                <label>Voqea sarlavhasi</label>
                <input type="text" id="event_sarlavha" name="sarlavha" placeholder="Masalan: Universitetga kirdi" required>
            </div>
            
            <div class="event-form-group" id="group_sana">
                <label>Voqea sanasi</label>
                <input type="date" id="event_sana" name="sana" required>
            </div>
            
            <div class="event-form-group" id="group_matn">
                <label>Qisqacha ta'rif</label>
                <textarea id="event_matn" name="matn" rows="3" placeholder="Voqea haqida batafsil..." required></textarea>
            </div>
            
            <button type="submit" class="event-btn" id="submitEventBtn">
                <i class="fas fa-paper-plane"></i> Adminga yuborish
            </button>
        </form>
    </div>
</div>

<script>
    let tg;
    try {
        tg = window.Telegram.WebApp;
        tg.expand(); 
        tg.ready();
    } catch(e) {}

    window.PHP_STATS = {
        jami: <?php echo (int)($stats['jami'] ?? 0); ?>,
        tirik: <?php echo (int)($stats['tirik'] ?? 0); ?>,
        vafot: <?php echo (int)($stats['vafot'] ?? 0); ?>,
        erkak: <?php echo (int)($stats['jins']['erkak'] ?? 0); ?>,
        ayol: <?php echo (int)($stats['jins']['ayol'] ?? 0); ?>,
        avlodlar: <?php echo (int)($stats['avlodlar'] ?? 1); ?>
    };

    function openEventModal(harakat = 'add', voqea_id = '', sana = '', sarlavha = '', matn = '') {
        let shaxsId = document.getElementById('shaxsModal').getAttribute('data-shaxs-id');
        
        if(!shaxsId) {
            alert("Shaxs aniqlanmadi.");
            return;
        }
        
        let form = document.getElementById('addEventForm');
        form.reset();
        
        document.getElementById('event_shaxs_id').value = shaxsId;
        document.getElementById('event_harakat').value = harakat;
        document.getElementById('event_voqea_id').value = voqea_id;
        
        let modalTitle = document.getElementById('eventModalTitle');
        let btn = document.getElementById('submitEventBtn');
        
        let sarlavhaInput = document.getElementById('event_sarlavha');
        let sanaInput = document.getElementById('event_sana');
        let matnInput = document.getElementById('event_matn');

        sarlavhaInput.required = true; sanaInput.required = true; matnInput.required = true;
        sarlavhaInput.readOnly = false; sanaInput.readOnly = false; matnInput.readOnly = false;
        
        if (harakat === 'add') {
            modalTitle.innerHTML = '<i class="fas fa-calendar-plus" style="color:#667eea;"></i> Voqea qo\'shish taklifi';
            btn.style.background = 'linear-gradient(135deg, #48c78e, #2ecc71)';
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Adminga yuborish';
        } 
        else if (harakat === 'edit') {
            modalTitle.innerHTML = '<i class="fas fa-edit" style="color:#f5b042;"></i> Voqeani tahrirlash taklifi';
            btn.style.background = 'linear-gradient(135deg, #f5b042, #e67e22)';
            btn.innerHTML = '<i class="fas fa-edit"></i> Tahrirni tasdiqlatish';
            sarlavhaInput.value = sarlavha;
            sanaInput.value = sana;
            matnInput.value = matn;
        } 
        else if (harakat === 'delete') {
            modalTitle.innerHTML = '<i class="fas fa-trash" style="color:#f45656;"></i> Voqeani o\'chirish taklifi';
            btn.style.background = 'linear-gradient(135deg, #f45656, #c0392b)';
            btn.innerHTML = '<i class="fas fa-trash"></i> O\'chirishni so\'rash';
            sarlavhaInput.value = sarlavha;
            sanaInput.value = sana;
            matnInput.value = matn;
            
            sarlavhaInput.readOnly = true;
            sanaInput.readOnly = true;
            matnInput.readOnly = true;
        }
        
        closeShaxsModal();
        openModal('addEventModal');
    }

    window.openAddEventModal = function() {
        openEventModal('add');
    };

    function submitEventForm(e) {
        e.preventDefault();
        let btn = document.getElementById('submitEventBtn');
        let oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yuborilmoqda...';
        
        let formData = new FormData(document.getElementById('addEventForm'));
        
        fetch('api/voqea_taklif.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Muvaffaqiyatli! Arizangiz admin tasdiqlashi uchun Telegramga yuborildi.");
                closeModal('addEventModal');
            } else {
                alert("Xatolik: " + (data.message || "Noma'lum xato"));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Tizim bilan aloqada xatolik yuz berdi.");
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        });
    }

    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function backdropClose(e, id) { if (e.target.id === id) closeModal(id); }
</script>

<script src="assets/js/tree.js?v=<?php echo time(); ?>"></script>
<script>
    const originalShaxsMalumot = window.shaxsMalumot;
    if (typeof originalShaxsMalumot === 'function') {
        window.shaxsMalumot = function(id) {
            document.getElementById('shaxsModal').setAttribute('data-shaxs-id', id);
            originalShaxsMalumot(id);
        };
    }
</script>

</body>
</html>