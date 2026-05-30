<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mijoz') {
    header("Location: /auth/login.php"); exit;
}
require_once "../config/dokon_db.php";

// Migration
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar MEDIUMTEXT DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL");

$uid  = (int)$_SESSION['user_id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$uid LIMIT 1"));
if (!$user) { header("Location: /auth/logout.php"); exit; }

// Statistika
$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as cnt, COALESCE(SUM(total_price),0) as total
     FROM sales WHERE user_id=$uid AND source='mijoz'"
));
$totalOrders = (int)($stats['cnt']   ?? 0);
$totalSpent  = (float)($stats['total'] ?? 0);
$avgOrder    = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;

// So'nggi buyurtmalar
$ordRes = mysqli_query($conn,
    "SELECT id, sale_date, total_price, status, sale_type, note
     FROM sales WHERE user_id=$uid AND source='mijoz'
     ORDER BY id DESC LIMIT 25"
);
$orders = [];
if ($ordRes) while ($r = mysqli_fetch_assoc($ordRes)) $orders[] = $r;

$st   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site = $st['store_name'] ?? 'SMART POS';

function statusInfo($s) {
    switch($s) {
        case 'yangi':         return ['⏳ Kutilmoqda',    '#f59e0b','rgba(245,158,11,.18)'];
        case 'qabul_qilindi': return ['📦 Tayyorlanmoqda','#10b981','rgba(16,185,129,.18)'];
        case 'rad_etildi':    return ['❌ Bekor qilindi', '#ef4444','rgba(239,68,68,.18)'];
        case 'tolangan':      return ['✅ Yakunlandi',    '#6366f1','rgba(99,102,241,.18)'];
        default:              return [$s,                 '#94a3b8','rgba(148,163,184,.12)'];
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Profil | <?= htmlspecialchars($site) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{
  --bg:#0a0f1e; --glass:rgba(255,255,255,.07); --glass2:rgba(255,255,255,.12);
  --border:rgba(255,255,255,.11); --primary:#6366f1; --primary-d:#4f46e5;
  --green:#10b981; --red:#ef4444; --text:#f1f5f9; --muted:#94a3b8;
}
body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;color:var(--text);overflow-x:hidden;position:relative;}

/* BG blobs */
.blob{position:fixed;border-radius:50%;filter:blur(120px);pointer-events:none;z-index:0;}
.b1{width:420px;height:420px;background:#6366f1;opacity:.18;top:-120px;left:-120px;}
.b2{width:360px;height:360px;background:#10b981;opacity:.13;bottom:-80px;right:-100px;}
.b3{width:260px;height:260px;background:#ec4899;opacity:.09;top:45%;left:55%;transform:translate(-50%,-50%);}

/* TOPBAR */
.topbar{
  position:sticky;top:0;z-index:100;
  background:rgba(10,15,30,.88);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-bottom:1px solid var(--border);
  padding:0 16px;height:56px;
  display:flex;align-items:center;justify-content:space-between;
}
.back-btn{
  display:flex;align-items:center;gap:6px;
  background:var(--glass);border:1px solid var(--border);
  border-radius:10px;padding:8px 14px;
  font-size:13px;font-weight:700;color:var(--muted);
  text-decoration:none;transition:.15s;
}
.back-btn:hover{background:var(--glass2);color:var(--text);}
.topbar-title{font-size:15px;font-weight:800;}
.logout-a{
  background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);
  border-radius:10px;padding:8px 14px;
  font-size:13px;font-weight:700;color:#fca5a5;
  text-decoration:none;transition:.15s;
}
.logout-a:hover{background:rgba(239,68,68,.22);}

/* PAGE */
.page{max-width:480px;margin:0 auto;padding:20px 16px 50px;position:relative;z-index:1;}

/* HERO */
.hero{
  background:var(--glass);
  backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
  border:1px solid var(--border);
  border-radius:24px;padding:30px 20px 24px;
  text-align:center;margin-bottom:14px;
  box-shadow:0 8px 32px rgba(0,0,0,.35);
}
.av-wrap{position:relative;display:inline-block;margin-bottom:16px;}
.avatar{
  width:90px;height:90px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  display:flex;align-items:center;justify-content:center;
  font-size:36px;font-weight:900;color:#fff;
  border:3px solid rgba(255,255,255,.18);
  box-shadow:0 0 0 7px rgba(99,102,241,.2),0 8px 28px rgba(99,102,241,.35);
  overflow:hidden;cursor:pointer;transition:.2s;
}
.avatar:hover{transform:scale(1.04);}
.avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.av-cam{
  position:absolute;bottom:2px;right:2px;
  width:26px;height:26px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  border:2.5px solid rgba(10,15,30,.9);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;cursor:pointer;
  box-shadow:0 2px 8px rgba(99,102,241,.4);
}
.hero-name{font-size:21px;font-weight:900;margin-bottom:3px;}
.hero-user{font-size:13px;color:var(--muted);margin-bottom:12px;}
.hero-phone{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.28);
  border-radius:20px;padding:5px 14px;
  font-size:12px;font-weight:700;color:#a5b4fc;
}
#avatarInput{display:none;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
.scard{
  background:var(--glass);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  border:1px solid var(--border);
  border-radius:16px;padding:14px 8px;text-align:center;
  box-shadow:0 4px 16px rgba(0,0,0,.2);
}
.s-ico{font-size:22px;margin-bottom:6px;}
.s-val{font-size:17px;font-weight:900;line-height:1;margin-bottom:3px;}
.s-lbl{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}

/* TABS */
.tabs{
  display:flex;background:var(--glass);
  border:1px solid var(--border);border-radius:14px;padding:4px;
  margin-bottom:14px;
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
}
.tab{
  flex:1;padding:10px 6px;border-radius:10px;border:none;
  background:transparent;color:var(--muted);
  font-size:12px;font-weight:700;cursor:pointer;transition:.18s;
  font-family:'Inter',sans-serif;
}
.tab.active{
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  color:#fff;box-shadow:0 4px 14px rgba(99,102,241,.4);
}

/* TAB CONTENT */
.tc{display:none;}
.tc.active{display:block;}

/* GLASS CARD */
.gc{
  background:var(--glass);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  border:1px solid var(--border);
  border-radius:20px;padding:20px;margin-bottom:12px;
}
.gc-title{font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;}

/* FORM */
.fg{margin-bottom:12px;}
.fl{display:block;font-size:11px;font-weight:700;color:var(--muted);margin-bottom:5px;letter-spacing:.3px;text-transform:uppercase;}
.fi{
  width:100%;height:50px;
  background:rgba(255,255,255,.06);
  border:1.5px solid var(--border);border-radius:12px;
  color:var(--text);font-size:14px;font-weight:600;
  padding:0 16px;transition:.2s;
  font-family:'Inter',sans-serif;
}
.fi:focus{outline:none;border-color:var(--primary);background:rgba(99,102,241,.1);box-shadow:0 0 0 3px rgba(99,102,241,.18);}
.fi::placeholder{color:#334155;}

/* SAVE BTN */
.btn-save{
  width:100%;height:52px;border-radius:14px;border:none;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  color:#fff;font-size:15px;font-weight:800;
  cursor:pointer;transition:.15s;
  box-shadow:0 6px 22px rgba(99,102,241,.4);
  display:flex;align-items:center;justify-content:center;gap:8px;
  font-family:'Inter',sans-serif;
}
.btn-save:active{transform:scale(.97);}
.btn-save:disabled{opacity:.55;pointer-events:none;}

/* ALERT */
.al{padding:12px 16px;border-radius:12px;font-size:13px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.al-ok{background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.28);color:#6ee7b7;}
.al-err{background:rgba(239,68,68,.14);border:1px solid rgba(239,68,68,.28);color:#fca5a5;}

/* ORDER LIST */
.ord-item{
  background:rgba(255,255,255,.05);border:1px solid var(--border);
  border-radius:14px;padding:14px;margin-bottom:8px;
  transition:.15s;
}
.ord-item:last-child{margin-bottom:0;}
.oi-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;}
.oi-id{font-size:14px;font-weight:800;color:var(--primary);}
.oi-status{font-size:11px;font-weight:800;padding:4px 10px;border-radius:20px;}
.oi-date{font-size:11px;color:var(--muted);margin-bottom:3px;}
.oi-total{font-size:16px;font-weight:900;margin-top:7px;}
.empty-box{text-align:center;padding:50px 20px;color:var(--muted);}
.empty-box p{font-size:14px;font-weight:700;color:var(--text);margin:10px 0 6px;}
.empty-box small{font-size:12px;}
.shop-now{
  display:inline-block;margin-top:16px;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  color:#fff;padding:12px 24px;border-radius:12px;
  font-weight:800;text-decoration:none;font-size:14px;
  box-shadow:0 6px 18px rgba(99,102,241,.35);
}

/* Avatar upload overlay */
.av-loading{
  position:absolute;inset:0;border-radius:50%;
  background:rgba(0,0,0,.55);
  display:none;align-items:center;justify-content:center;
  font-size:22px;
}
.av-loading.show{display:flex;}

@media(max-width:480px){
  .topbar{padding:0 12px;}
  .page{padding:14px 12px 50px;}
  .stats{gap:8px;}
  .s-val{font-size:15px;}
  .scard{padding:12px 6px;}
}
</style>
</head>
<body>

<div class="blob b1"></div>
<div class="blob b2"></div>
<div class="blob b3"></div>

<!-- TOPBAR -->
<div class="topbar">
  <a href="/mijoz/index.php" class="back-btn">← Orqaga</a>
  <span class="topbar-title">👤 Profilim</span>
  <a href="/auth/logout.php" class="logout-a" onclick="return confirm('Tizimdan chiqasizmi?')">Chiqish ⏻</a>
</div>

<div class="page">

  <!-- HERO -->
  <div class="hero">
    <div class="av-wrap">
      <div class="avatar" onclick="document.getElementById('avatarInput').click()" id="avatarEl">
        <?php if(!empty($user['avatar'])): ?>
          <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
        <?php else: ?>
          <span id="avInit"><?= mb_strtoupper(mb_substr($user['name']??'U',0,1)) ?></span>
        <?php endif; ?>
        <div class="av-loading" id="avLoad">⏳</div>
      </div>
      <div class="av-cam">📷</div>
    </div>
    <div class="hero-name" id="heroName"><?= htmlspecialchars($user['name'] ?? '') ?></div>
    <div class="hero-user">@<?= htmlspecialchars($user['username'] ?? '') ?></div>
    <?php if(!empty($user['phone'])): ?>
    <div class="hero-phone" id="heroPhone">📱 <?= htmlspecialchars($user['phone']) ?></div>
    <?php else: ?>
    <div class="hero-phone" id="heroPhone" style="display:none">📱</div>
    <?php endif; ?>
    <input type="file" id="avatarInput" accept="image/*" onchange="handleAvatar(this)">
  </div>

  <!-- STATS -->
  <div class="stats">
    <div class="scard">
      <div class="s-ico">🛍️</div>
      <div class="s-val"><?= $totalOrders ?></div>
      <div class="s-lbl">Buyurtma</div>
    </div>
    <div class="scard">
      <div class="s-ico">💰</div>
      <div class="s-val"><?= $totalOrders > 0 ? number_format($totalSpent/1000000,1).'M' : '0' ?></div>
      <div class="s-lbl">UZS sarflandi</div>
    </div>
    <div class="scard">
      <div class="s-ico">📊</div>
      <div class="s-val"><?= $avgOrder > 0 ? number_format($avgOrder/1000,0).'K' : '—' ?></div>
      <div class="s-lbl">O'rtacha</div>
    </div>
  </div>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab active" onclick="swTab('info',this)">✏️ Ma'lumotlar</button>
    <button class="tab" onclick="swTab('orders',this)">📋 Buyurtmalar <?= $totalOrders > 0 ? "($totalOrders)" : '' ?></button>
  </div>

  <!-- TAB: MA'LUMOTLAR -->
  <div class="tc active" id="tc-info">

    <div id="alertBox"></div>

    <div class="gc">
      <div class="gc-title">👤 Shaxsiy ma'lumotlar</div>
      <div class="fg">
        <label class="fl">To'liq ism</label>
        <input type="text" class="fi" id="inp-name"
               value="<?= htmlspecialchars($user['name'] ?? '') ?>"
               placeholder="Ismingiz">
      </div>
      <div class="fg" style="margin-bottom:0">
        <label class="fl">Telefon raqam</label>
        <input type="tel" class="fi" id="inp-phone"
               value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
               placeholder="+998 XX XXX XX XX">
      </div>
    </div>

    <div class="gc">
      <div class="gc-title">🔒 Parolni o'zgartirish</div>
      <div class="fg">
        <label class="fl">Joriy parol</label>
        <input type="password" class="fi" id="inp-old" placeholder="Hozirgi parolingiz">
      </div>
      <div class="fg">
        <label class="fl">Yangi parol</label>
        <input type="password" class="fi" id="inp-new" placeholder="Yangi parol (kamida 6 belgi)">
      </div>
      <div class="fg" style="margin-bottom:0">
        <label class="fl">Yangi parolni tasdiqlang</label>
        <input type="password" class="fi" id="inp-conf" placeholder="Yana kiriting">
      </div>
    </div>

    <button class="btn-save" id="saveBtn" onclick="saveProfile()">
      💾 Saqlash
    </button>
  </div>

  <!-- TAB: BUYURTMALAR -->
  <div class="tc" id="tc-orders">
    <?php if(empty($orders)): ?>
      <div class="empty-box">
        <div style="font-size:52px">📭</div>
        <p>Hali buyurtma yo'q</p>
        <small>Birinchi xaridingizni qiling!</small>
        <a href="/mijoz/index.php" class="shop-now">🛍️ Xarid qilish</a>
      </div>
    <?php else: ?>
      <div class="gc" style="padding:0;overflow:hidden;">
        <?php foreach($orders as $i => $o):
          [$stLabel,$stColor,$stBg] = statusInfo($o['status']);
          $ico = $o['sale_type'] === 'yetkazish' ? '🚚' : '🏪';
          try { $dt = new DateTime($o['sale_date']); $ds = $dt->format('d.m.Y  H:i'); }
          catch(Exception $e) { $ds = $o['sale_date']; }
        ?>
        <div class="ord-item" style="border-radius:<?= $i===0?'20px 20px':'0' ?> 0 0;<?= $i===count($orders)-1?'border-radius:0 0 20px 20px;border-bottom:none':'border-bottom:1px solid var(--border)' ?>;border-left:3px solid <?= $stColor ?>">
          <div class="oi-top">
            <span class="oi-id"><?= $ico ?> #<?= str_pad($o['id'],4,'0',STR_PAD_LEFT) ?></span>
            <span class="oi-status" style="color:<?= $stColor ?>;background:<?= $stBg ?>"><?= $stLabel ?></span>
          </div>
          <div class="oi-date">📅 <?= $ds ?></div>
          <?php if($o['note']): ?>
          <div class="oi-date" style="margin-top:2px">📝 <?= htmlspecialchars(mb_substr($o['note'],0,60)) ?></div>
          <?php endif; ?>
          <div class="oi-total"><?= number_format($o['total_price'],0,'.',' ') ?> <small style="font-size:11px;color:var(--muted);font-weight:700">UZS</small></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /page -->

<script>
function swTab(name, btn) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tc').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tc-' + name).classList.add('active');
}

function showAlert(msg, type = 'ok') {
  const box = document.getElementById('alertBox');
  box.innerHTML = `<div class="al al-${type}">${type==='ok'?'✅':'❌'} ${msg}</div>`;
  if (type === 'ok') setTimeout(() => box.innerHTML = '', 4000);
}

async function saveProfile() {
  const btn  = document.getElementById('saveBtn');
  const name = document.getElementById('inp-name').value.trim();
  const phone= document.getElementById('inp-phone').value.trim();
  const old  = document.getElementById('inp-old').value;
  const nw   = document.getElementById('inp-new').value;
  const conf = document.getElementById('inp-conf').value;

  if (!name) { showAlert('Ismni kiriting!', 'err'); return; }
  if (nw) {
    if (nw.length < 6)  { showAlert('Yangi parol kamida 6 belgi!', 'err'); return; }
    if (nw !== conf)    { showAlert('Parollar mos kelmadi!', 'err'); return; }
    if (!old)           { showAlert('Joriy parolni kiriting!', 'err'); return; }
  }

  btn.disabled = true;
  btn.innerHTML = '⏳ Saqlanmoqda...';

  const fd = new FormData();
  fd.append('name',     name);
  fd.append('phone',    phone);
  fd.append('old_pass', old);
  fd.append('new_pass', nw);

  const res = await fetch('/mijoz/api.php?action=update_profile', { method:'POST', body:fd });
  const d   = await res.json();

  if (d.status === 'ok') {
    showAlert("Ma'lumotlar muvaffaqiyatli saqlandi! ✨");
    document.getElementById('inp-old').value = '';
    document.getElementById('inp-new').value = '';
    document.getElementById('inp-conf').value = '';
    document.getElementById('heroName').textContent = name;
    const ph = document.getElementById('heroPhone');
    if (phone) { ph.textContent = '📱 ' + phone; ph.style.display = ''; }
  } else {
    showAlert(d.message || 'Xatolik yuz berdi!', 'err');
  }

  btn.disabled = false;
  btn.innerHTML = '💾 Saqlash';
}

function handleAvatar(input) {
  const file = input.files[0];
  if (!file) return;

  const avEl = document.getElementById('avatarEl');
  const load = document.getElementById('avLoad');
  load.classList.add('show');

  const reader = new FileReader();
  reader.onload = e => {
    const img = new Image();
    img.onload = () => {
      // Canvas: max 200x200, JPEG 80%
      const canvas = document.createElement('canvas');
      const MAX = 200;
      let w = img.width, h = img.height;
      if (w > h) { if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; } }
      else       { if (h > MAX) { w = Math.round(w * MAX / h); h = MAX; } }
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      const b64 = canvas.toDataURL('image/jpeg', 0.82);

      const fd = new FormData();
      fd.append('avatar', b64);
      fetch('/mijoz/api.php?action=upload_avatar', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
          load.classList.remove('show');
          if (d.status === 'ok') {
            avEl.innerHTML = `<img src="${b64}" alt="Avatar"><div class="av-loading" id="avLoad"></div>`;
            showAlert('Avatar yangilandi! 🎉');
          } else {
            showAlert('Avatar yuklanmadi: ' + (d.message || ''), 'err');
          }
        });
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}
</script>
</body>
</html>
