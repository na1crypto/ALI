<?php
session_start();
// Bazaga ulanish faylini ulaymiz
require_once __DIR__ . '/../config/dokon_db.php';

// 🔴 DO'KON NOMINI BAZADAN OLISH (Settings)
$st_query = mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1");
$st = ($st_query && mysqli_num_rows($st_query) > 0) ? mysqli_fetch_assoc($st_query) : null;
$site_name = $st['store_name'] ?? "SMART POS";

$xato = "";

// Agar foydalanuvchi allaqachon tizimga kirgan bo'lsa, avtomat yo'naltirish
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin') {
        header("Location: /admin/dashboard.php");
        exit;
    } else if ($_SESSION['role'] == 'sotuvchi') {
        header("Location: /sotuvchi/dashboard.php");
        exit;
    }
}

if (isset($_POST['kirish'])) {
    // Inputlarni tozalash
    $login = mysqli_real_escape_string($conn, trim($_POST['login']));
    $parol = mysqli_real_escape_string($conn, trim($_POST['parol']));

    // Bazadan foydalanuvchini qidirish
    $sql = "SELECT * FROM users WHERE username = '$login' LIMIT 1";
    $res = mysqli_query($conn, $sql);

    if ($res && mysqli_num_rows($res) == 1) {
        $user = mysqli_fetch_assoc($res);

        // Parolni tekshirish (password_hash va oddiy matn — ikkalasini ham qo'llab-quvvatlaydi)
        $parol_togri = false;
        if (password_verify($parol, $user['password'])) {
            // Yangi usul: password_hash bilan saqlangan
            $parol_togri = true;
        } elseif ($parol === $user['password']) {
            // Eski usul: oddiy matn — kirdi, lekin avtomatik hash ga o'tkazamiz
            $yangi_hash = password_hash($parol, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password='$yangi_hash' WHERE id=" . (int)$user['id']);
            $parol_togri = true;
        }
        if ($parol_togri) {
            
            // Sessiyaga hamma ma'lumotni yozamiz
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = strtolower(trim($user['role'])); // 'admin', 'superadmin' yoki 'sotuvchi'

            // Ruliga qarab yo'naltirish
            if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin') {
                header("Location: /admin/dashboard.php");
                exit;
            } else if ($_SESSION['role'] == 'sotuvchi') {
                header("Location: /sotuvchi/dashboard.php");
                exit;
            } else if ($_SESSION['role'] == 'mijoz') {
                header("Location: /mijoz/index.php");
                exit;
            } else {
                $xato = "❌ Sizga rol biriktirilmagan!";
            }
        } else {
            $xato = "❌ Parol noto‘g‘ri!";
        }
    } else {
        $xato = "❌ Bunday login topilmadi!";
    }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kirish | <?= htmlspecialchars($site_name) ?></title>
  <style>
    body {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        font-family: 'Plus Jakarta Sans', sans-serif;
        height: 100vh; display: flex; align-items: center; justify-content: center;
        margin: 0; position: relative; overflow: hidden;
    }
    
    /* Orqa fondagi chiroyli doiralar (Glow effekt) */
    .circle-1 { position: absolute; top: 10%; left: 15%; width: 300px; height: 300px; background: #6366f1; border-radius: 50%; filter: blur(100px); opacity: 0.4; z-index: 0; }
    .circle-2 { position: absolute; bottom: 10%; right: 15%; width: 400px; height: 400px; background: #10b981; border-radius: 50%; filter: blur(120px); opacity: 0.3; z-index: 0; }
    
    .glass-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        padding: 45px 40px;
        width: 100%; max-width: 420px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        color: white;
        text-align: center;
        z-index: 1;
    }
    
    /* 🔴 LOGOTIP UCHUN DIZAYN */
    .logo-box {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.1);
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px auto;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        overflow: hidden;
    }
    .logo-box i { font-size: 34px; color: #ffffff; }
    .logo-box img { width: 100%; height: 100%; object-fit: cover; } /* Rasm qo'yilsa moslashadi */

    .form-control {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 14px; color: white; height: 55px; padding-left: 55px;
        font-size: 15px; transition: 0.3s;
    }
    .form-control:focus {
        background: rgba(255, 255, 255, 0.1); border-color: #6366f1; color: white; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
    }
    .form-control::placeholder { color: #64748b; }
    
    .input-group { position: relative; margin-bottom: 25px; }
    .input-icon { position: absolute; left: 20px; top: 18px; color: #94a3b8; z-index: 10; font-size: 18px; }
    
    .btn-login {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        border: none; border-radius: 14px; height: 55px; font-weight: 700; font-size: 16px; transition: 0.3s; color: white; width: 100%;
        margin-top: 10px; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }
    .btn-login:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(99, 102, 241, 0.5); color: white; }
    
    .brand-text { font-weight: 800; font-size: 30px; letter-spacing: 1px; margin-bottom: 5px; color: #ffffff; text-transform: uppercase;}
    .brand-sub { color: #94a3b8; font-size: 15px; margin-bottom: 35px; font-weight: 400; }
    
    .alert-glass { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 12px; font-weight: 600; }
  </style>
</head>
<body>

<div class="circle-1"></div>
<div class="circle-2"></div>

<div class="glass-card">
    
    <div class="logo-box">
        <i class="fas fa-store"></i>
    </div>

    <div class="brand-text"><?= htmlspecialchars($site_name) ?></div>
    <div class="brand-sub">Tizimga kirish</div>

    <?php if ($xato): ?>
        <div class="alert alert-glass text-sm p-3 mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?= $xato; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="input-group">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="login" class="form-control" placeholder="Loginni kiriting" autocomplete="off" required>
        </div>
        <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="parol" class="form-control" placeholder="Parolni kiriting" required>
        </div>
        <button type="submit" name="kirish" class="btn btn-login">Kirish <i class="fas fa-arrow-right ml-2"></i></button>
    </form>
    <p style="margin-top:22px; color:#64748b; font-size:14px; text-align:center;">
        Hisobingiz yo'qmi?
        <a href="/auth/register.php" style="color:#818cf8; font-weight:700; text-decoration:none;">Ro'yxatdan o'ting</a>
    </p>
</div>

</body>
</html>