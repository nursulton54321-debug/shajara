<?php
// =============================================
// FILE: admin/login.php
// MAQSAD: Admin panelga kirish (bazadagi parol bilan)
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Sessiyani boshlash
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Agar allaqachon kirilgan bo'lsa, dashboardga o'tkazish
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: ' . SITE_URL . '/admin/index.php');
exit;
    exit;
}

$error = '';

// Login formasi yuborilgan bo'lsa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Bazadan adminni tekshirish
    $sql = "SELECT * FROM adminlar WHERE username = '$username' LIMIT 1";
    $result = db_query($sql);
    
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        
        // Parolni tekshirish (to'g'ridan-to'g'ri solishtirish)
        if ($password === $admin['password']) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            
            // So'nggi kirish vaqtini yangilash
            $now = date('Y-m-d H:i:s');
            db_query("UPDATE adminlar SET last_login = '$now' WHERE id = {$admin['id']}");
            
            header('Location: ' . SITE_URL . '/admin/index.php');
            exit;
        } else {
            $error = '❌ Parol noto\'g\'ri';
        }
    } else {
        $error = '❌ Foydalanuvchi topilmadi';
    }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Oila Shajarasi</title>
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
        
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
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
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header i {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .error-message {
            background: #fef2f2;
            color: #f45656;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid #f45656;
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
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .btn-login i {
            font-size: 18px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .login-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .login-footer a i {
            margin-right: 5px;
        }
        
        .demo-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
            text-align: center;
        }
        
        .demo-info strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-tree"></i>
            <h1>Admin Panel</h1>
            <p>Oila Shajarasi tizimiga kirish</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Foydalanuvchi nomi</label>
                <input type="text" name="username" required placeholder="admin" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Parol</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Kirish
            </button>
        </form>
        <div style="text-align: right; margin-bottom: 15px;">
            <a href="forgot-password.php" style="color: #667eea; text-decoration: none; font-size: 13px;">
            <i class="fas fa-question-circle"></i> Parolni unutdingizmi?
            </a>
        </div>
        
        <div class="login-footer">
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Asosiy sahifaga qaytish</a>
        </div>
        
        <div class="demo-info">
            <i class="fas fa-info-circle"></i> Ma'lumot: Bazadagi username va parol bilan kiring
        </div>
    </div>

    <script>
        // Formani tekshirish
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();
            
            if (username === '' || password === '') {
                e.preventDefault();
                alert('Iltimos, barcha maydonlarni to\'ldiring!');
            }
        });
    </script>
</body>
</html>