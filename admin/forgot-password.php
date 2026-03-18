<?php
// =============================================
// FILE: admin/forgot-password.php
// MAQSAD: Email orqali parolni tiklash
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Sessiyani boshlash
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Agar allaqachon kirilgan bo'lsa
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';
$step = isset($_GET['step']) ? $_GET['step'] : '1'; // 1 - email kiritish, 2 - kod kiritish, 3 - yangi parol

// Email orqali tiklash
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1-QADAM: Email kiritish
    if ($step === '1' && isset($_POST['send_code'])) {
        $email = sanitize($_POST['email']);
        
        // Email mavjudligini tekshirish
        $sql = "SELECT * FROM adminlar WHERE email = '$email'";
        $result = db_query($sql);
        
        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // 6 xonali tasdiqlash kodi yaratish
            $code = rand(100000, 999999);
            
            // Kodni sessiyada saqlash (vaqtinchalik)
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_time'] = time(); // Kodning amal qilish muddati (10 daqiqa)
            
            // Emailga kodni yuborish (PHPMailer kerak)
            // Hozircha oddiy qilib ko'rsatamiz
            $message = "✅ Tasdiqlash kodi yuborildi: $code (Haqiqiy loyihada emailga yuboriladi)";
            
            // 2-qadamga o'tish
            header("Location: forgot-password.php?step=2");
            exit;
        } else {
            $error = "❌ Bu email tizimda topilmadi!";
        }
    }
    
    // 2-QADAM: Kodni tekshirish
    elseif ($step === '2' && isset($_POST['verify_code'])) {
        $entered_code = $_POST['code'];
        
        // Kodni tekshirish
        if (isset($_SESSION['reset_code']) && $_SESSION['reset_code'] == $entered_code) {
            // Kod amal qilish muddatini tekshirish (10 daqiqa)
            if (time() - $_SESSION['reset_time'] < 600) {
                header("Location: forgot-password.php?step=3");
                exit;
            } else {
                $error = "❌ Kodning amal qilish muddati tugagan! Qaytadan urinib ko'ring.";
                session_destroy();
            }
        } else {
            $error = "❌ Noto'g'ri kod!";
        }
    }
    
    // 3-QADAM: Yangi parolni saqlash
    elseif ($step === '3' && isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "❌ Parollar mos kelmadi!";
        } elseif (strlen($new_password) < 6) {
            $error = "❌ Parol kamida 6 belgidan iborat bo'lishi kerak!";
        } else {
            $email = $_SESSION['reset_email'];
            
            // Parolni yangilash
            $sql = "UPDATE adminlar SET password = '$new_password' WHERE email = '$email'";
            
            if (db_query($sql)) {
                $message = "✅ Parol muvaffaqiyatli o'zgartirildi! Endi login qilishingiz mumkin.";
                
                // Sessiyani tozalash
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_code']);
                unset($_SESSION['reset_time']);
                
                // Login sahifasiga yo'naltirish
                header("refresh:3;url=login.php");
            } else {
                $error = "❌ Xatolik yuz berdi!";
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
    <title>Parolni tiklash | Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header i {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .reset-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .reset-header p {
            color: #666;
            font-size: 14px;
        }
        
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            transform: translateY(-50%);
            z-index: 1;
        }
        
        .step {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #999;
            position: relative;
            z-index: 2;
            background: white;
        }
        
        .step.active {
            border-color: #667eea;
            color: #667eea;
            background: white;
        }
        
        .step.completed {
            background: #48c78e;
            border-color: #48c78e;
            color: white;
        }
        
        .step-label {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
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
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group label i {
            color: #667eea;
            width: 20px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-key"></i>
            <h1>Parolni tiklash</h1>
            <p>Email orqali parolingizni tiklang</p>
        </div>
        
        <!-- Qadamlar -->
        <div class="steps">
            <div class="step <?php echo $step >= '1' ? 'active' : ''; ?> <?php echo $step > '1' ? 'completed' : ''; ?>">1</div>
            <div class="step <?php echo $step >= '2' ? 'active' : ''; ?> <?php echo $step > '2' ? 'completed' : ''; ?>">2</div>
            <div class="step <?php echo $step >= '3' ? 'active' : ''; ?>">3</div>
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
        
        <!-- 1-QADAM: Email kiritish -->
        <?php if ($step === '1'): ?>
        <form method="POST" action="">
            <div class="info-box">
                <i class="fas fa-info-circle"></i> Ro'yxatdan o'tgan email manzilingizni kiriting. Tasdiqlash kodi yuboriladi.
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email manzil</label>
                <input type="email" name="email" required placeholder="admin@example.com">
            </div>
            
            <button type="submit" name="send_code" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Kodni yuborish
            </button>
        </form>
        <?php endif; ?>
        
        <!-- 2-QADAM: Kodni kiritish -->
        <?php if ($step === '2'): ?>
        <form method="POST" action="">
            <div class="info-box">
                <i class="fas fa-info-circle"></i> Emailingizga yuborilgan 6 xonali kodni kiriting.
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-key"></i> Tasdiqlash kodi</label>
                <input type="text" name="code" required placeholder="123456" maxlength="6" pattern="[0-9]{6}">
            </div>
            
            <button type="submit" name="verify_code" class="btn-submit">
                <i class="fas fa-check-circle"></i> Kodni tekshirish
            </button>
        </form>
        <?php endif; ?>
        
        <!-- 3-QADAM: Yangi parol -->
        <?php if ($step === '3'): ?>
        <form method="POST" action="">
            <div class="info-box">
                <i class="fas fa-info-circle"></i> Yangi parolni kiriting (kamida 6 belgi).
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Yangi parol</label>
                <input type="password" name="new_password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Yangi parolni takrorlang</label>
                <input type="password" name="confirm_password" required minlength="6">
            </div>
            
            <button type="submit" name="reset_password" class="btn-submit">
                <i class="fas fa-save"></i> Parolni saqlash
            </button>
        </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Login sahifasiga qaytish</a>
        </div>
    </div>
    
    <script>
        // Kod maydoniga faqat raqam kiritish
        document.querySelector('input[name="code"]')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>
</body>
</html>