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
    $r = $_SESSION['role'] ?? '';
    if ($r == 'admin' || $r == 'superadmin') {
        header("Location: /admin/dashboard.php"); exit;
    } else if ($r == 'sotuvchi') {
        header("Location: /sotuvchi/dashboard.php"); exit;
    } else if ($r == 'menejer') {
        header("Location: /menejer/index.php"); exit;
    } else if ($r == 'mijoz') {
        header("Location: /mijoz/index.php"); exit;
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
            } else if ($_SESSION['role'] == 'menejer') {
                header("Location: /menejer/index.php");
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
    body {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        font-family: 'Segoe UI', -apple-system, sans-serif;
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 20px; position: relative; overflow: hidden;
    }
    .circle-1 { position: fixed; top: -80px; left: -80px; width: 300px; height: 300px; background: #6366f1; border-radius: 50%; filter: blur(100px); opacity: 0.35; z-index: 0; }
    .circle-2 { position: fixed; bottom: -80px; right: -80px; width: 350px; height: 350px; background: #10b981; border-radius: 50%; filter: blur(110px); opacity: 0.25; z-index: 0; }
    .glass-card {
        background: rgba(255,255,255,0.06);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 24px;
        padding: 40px 36px;
        width: 100%; max-width: 420px;
        box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        color: white; text-align: center; z-index: 1; position: relative;
    }
    .logo-box {
        width: 76px; height: 76px;
        background: rgba(255,255,255,0.1);
        border: 2px solid rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 18px;
        box-shadow: 0 8px 24px rgba(99,102,241,0.4);
    }
    .logo-box i { font-size: 30px; color: #fff; }
    .brand-text { font-weight: 900; font-size: 28px; letter-spacing: 1px; margin-bottom: 4px; color: #fff; text-transform: uppercase; }
    .brand-sub { color: #94a3b8; font-size: 14px; margin-bottom: 28px; }
    .input-group { position: relative; margin-bottom: 16px; }
    .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; z-index: 2; }
    .form-control {
        background: rgba(255,255,255,0.07);
        border: 1.5px solid rgba(255,255,255,0.15);
        border-radius: 14px; color: #fff;
        height: 54px; width: 100%;
        padding: 0 16px 0 50px;
        font-size: 15px; transition: .25s;
    }
    .form-control:focus {
        background: rgba(255,255,255,0.12);
        border-color: #6366f1; color: #fff;
        outline: none;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
    }
    .form-control::placeholder { color: #475569; }
    .btn-login {
        background: linear-gradient(135deg,#6366f1,#4f46e5);
        border: none; border-radius: 14px;
        height: 54px; width: 100%;
        font-weight: 800; font-size: 16px; color: #fff;
        margin-top: 8px; cursor: pointer;
        box-shadow: 0 6px 20px rgba(99,102,241,0.4);
        transition: .2s; letter-spacing: .3px;
    }
    .btn-login:active { transform: scale(.97); }
    .alert-glass {
        background: rgba(239,68,68,0.15);
        border: 1px solid rgba(239,68,68,0.3);
        color: #fca5a5; border-radius: 12px;
        font-weight: 600; padding: 12px 16px;
        margin-bottom: 18px; font-size: 14px;
        display: flex; align-items: center; gap: 8px;
    }
    .reg-link { margin-top: 20px; color: #64748b; font-size: 14px; }
    .reg-link a { color: #818cf8; font-weight: 700; text-decoration: none; }

    /* 📱 Mobil */
    @media(max-width:480px){
        body{ padding: 16px; align-items: flex-end; }
        .glass-card{
            border-radius: 24px 24px 0 0;
            padding: 32px 22px env(safe-area-inset-bottom, 28px);
            max-width: 100%;
        }
        .circle-1,.circle-2{ opacity:.2; }
        .brand-text{ font-size:24px; }
        .form-control{ height:52px; font-size:16px; }
        .btn-login{ height:52px; font-size:15px; }
    }
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
        <div class="alert-glass">
            <i class="fas fa-exclamation-triangle"></i> <?= $xato ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="input-group">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="login" class="form-control" placeholder="Login yoki telefon" autocomplete="username" required>
        </div>
        <div class="input-group" style="margin-bottom:4px">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="parol" class="form-control" placeholder="Parol" autocomplete="current-password" required>
        </div>
        <button type="submit" name="kirish" class="btn-login">
            Kirish &nbsp; <i class="fas fa-arrow-right"></i>
        </button>
    </form>
    <div class="reg-link">
        Hisob yo'qmi? <a href="/auth/register.php">Ro'yxatdan o'ting</a>
    </div>
</div>

</body>
</html>