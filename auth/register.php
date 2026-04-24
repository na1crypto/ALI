<?php
session_start();
require_once __DIR__ . '/../config/dokon_db.php';

// Allaqachon kirgan bo'lsa
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'mijoz') header("Location: /mijoz/index.php");
    else header("Location: /admin/dashboard.php");
    exit;
}

// role ustuniga 'mijoz' qiymatini qo'shish (bir martalik migration)
@mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','sotuvchi','mijoz') NOT NULL DEFAULT 'sotuvchi'");

$st    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site  = $st['store_name'] ?? "ELEVEN";
$xato  = "";
$muvaffaqiyat = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ism      = trim($_POST['ism'] ?? '');
    $tel      = trim($_POST['tel'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $parol    = $_POST['parol'] ?? '';
    $parol2   = $_POST['parol2'] ?? '';

    // Validatsiya
    if (!$ism || !$tel || !$username || !$parol) {
        $xato = "Barcha maydonlarni to'ldiring!";
    } elseif (strlen($parol) < 4) {
        $xato = "Parol kamida 4 ta belgidan iborat bo'lishi kerak!";
    } elseif ($parol !== $parol2) {
        $xato = "Parollar bir-biriga mos kelmadi!";
    } else {
        $u = mysqli_real_escape_string($conn, $username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$u'");
        if (mysqli_num_rows($check) > 0) {
            $xato = "Bu login band! Boshqa login tanlang.";
        } else {
            $n  = mysqli_real_escape_string($conn, $ism);
            $t  = mysqli_real_escape_string($conn, $tel);
            $ph = password_hash($parol, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, username, password, role) VALUES ('$n','$u','$ph','mijoz') /* TiDB */";
            if (mysqli_query($conn, $sql)) {
                $uid = mysqli_insert_id($conn);
                $_SESSION['user_id'] = $uid;
                $_SESSION['name']    = $ism;
                $_SESSION['role']    = 'mijoz';
                $_SESSION['phone']   = $t;
                header("Location: /mijoz/index.php");
                exit;
            } else {
                $xato = "Xatolik yuz berdi: " . mysqli_error($conn);
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
<title>Ro'yxatdan o'tish | <?= htmlspecialchars($site) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
  min-height:100vh;
  background:linear-gradient(135deg,#0f172a 0%,#1e293b 60%,#0f172a 100%);
  font-family:'Segoe UI',sans-serif;
  display:flex; align-items:center; justify-content:center;
  padding:20px;
}
.circle-1{position:fixed;top:-60px;left:-60px;width:350px;height:350px;background:#6366f1;border-radius:50%;filter:blur(120px);opacity:0.3;pointer-events:none;}
.circle-2{position:fixed;bottom:-80px;right:-60px;width:400px;height:400px;background:#10b981;border-radius:50%;filter:blur(130px);opacity:0.25;pointer-events:none;}

.card{
  background:rgba(255,255,255,0.06);
  backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
  border:1px solid rgba(255,255,255,0.12);
  border-radius:24px;
  padding:36px 32px;
  width:100%; max-width:440px;
  color:#fff;
  position:relative; z-index:1;
}
.logo{
  width:64px; height:64px;
  background:linear-gradient(135deg,#6366f1,#4f46e5);
  border-radius:18px;
  display:flex; align-items:center; justify-content:center;
  font-size:28px; margin:0 auto 16px;
  box-shadow:0 8px 24px rgba(99,102,241,0.4);
}
h1{text-align:center;font-size:22px;font-weight:800;margin-bottom:4px;}
.sub{text-align:center;color:#94a3b8;font-size:14px;margin-bottom:28px;}

.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.field{margin-bottom:16px;}
label{display:block;font-size:12px;font-weight:700;color:#94a3b8;margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px;}
.inp{
  width:100%; padding:13px 16px;
  background:rgba(255,255,255,0.06);
  border:1px solid rgba(255,255,255,0.12);
  border-radius:12px; color:#fff;
  font-size:14px; transition:.25s;
}
.inp:focus{outline:none;border-color:#6366f1;background:rgba(255,255,255,0.09);box-shadow:0 0 0 3px rgba(99,102,241,0.18);}
.inp::placeholder{color:#475569;}

.inp-wrap{position:relative;}
.inp-wrap .inp{padding-right:44px;}
.eye-btn{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;font-size:16px;padding:4px;}

.btn{
  width:100%; padding:14px;
  background:linear-gradient(135deg,#6366f1,#4f46e5);
  border:none; border-radius:14px;
  color:#fff; font-size:16px; font-weight:700;
  cursor:pointer; margin-top:8px;
  box-shadow:0 4px 15px rgba(99,102,241,0.35);
  transition:.25s;
}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,0.45);}
.btn:active{transform:none;}

.alert{
  padding:12px 16px; border-radius:12px;
  font-size:14px; font-weight:600;
  display:flex; align-items:center; gap:10px;
  margin-bottom:20px;
}
.alert-err{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}
.alert-ok{background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;}

.login-link{text-align:center;margin-top:20px;color:#64748b;font-size:14px;}
.login-link a{color:#818cf8;font-weight:600;text-decoration:none;}
.login-link a:hover{color:#a5b4fc;}

@media(max-width:480px){
  .card{padding:28px 20px;}
  .row2{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="circle-1"></div>
<div class="circle-2"></div>

<div class="card">
  <div class="logo">🛍️</div>
  <h1><?= htmlspecialchars($site) ?></h1>
  <p class="sub">Yangi hisob yaratish</p>

  <?php if($xato): ?>
  <div class="alert alert-err">⚠️ <?= htmlspecialchars($xato) ?></div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="row2">
      <div class="field">
        <label>Ismingiz</label>
        <input class="inp" type="text" name="ism" placeholder="Sardor" value="<?= htmlspecialchars($_POST['ism']??'') ?>" required>
      </div>
      <div class="field">
        <label>Telefon</label>
        <input class="inp" type="tel" name="tel" placeholder="+998 90 123 45 67" value="<?= htmlspecialchars($_POST['tel']??'') ?>">
      </div>
    </div>

    <div class="field">
      <label>Login (foydalanuvchi nomi)</label>
      <input class="inp" type="text" name="username" placeholder="sardor_shop" value="<?= htmlspecialchars($_POST['username']??'') ?>" required autocomplete="off">
    </div>

    <div class="row2">
      <div class="field">
        <label>Parol</label>
        <div class="inp-wrap">
          <input class="inp" type="password" name="parol" id="p1" placeholder="••••••••" required>
          <button type="button" class="eye-btn" onclick="toggleEye('p1','e1')"><span id="e1">👁</span></button>
        </div>
      </div>
      <div class="field">
        <label>Parolni takrorlang</label>
        <div class="inp-wrap">
          <input class="inp" type="password" name="parol2" id="p2" placeholder="••••••••" required>
          <button type="button" class="eye-btn" onclick="toggleEye('p2','e2')"><span id="e2">👁</span></button>
        </div>
      </div>
    </div>

    <button type="submit" class="btn">Ro'yxatdan o'tish →</button>
  </form>

  <p class="login-link">Hisobingiz bormi? <a href="/auth/login.php">Kirish</a></p>
</div>

<script>
function toggleEye(id, eid) {
  const inp = document.getElementById(id);
  const ico = document.getElementById(eid);
  if (inp.type === 'password') { inp.type = 'text'; ico.textContent = '🙈'; }
  else { inp.type = 'password'; ico.textContent = '👁'; }
}
</script>
</body>
</html>
