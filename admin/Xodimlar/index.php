<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__, 2);
require_once $root . "/middleware/admin_check.php";
require_once $root . "/config/dokon_db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: /admin/dashboard.php"); exit;
}

$st        = mysqli_fetch_assoc(mysqli_query($conn,"SELECT store_name FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? 'SMART POS';
$active_menu = 'xodimlar';

// Migrations
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS salary DECIMAL(14,2) DEFAULT 0");
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL");
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL");
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar MEDIUMTEXT DEFAULT NULL");
mysqli_query($conn,"CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT PRIMARY KEY, perm_sell TINYINT DEFAULT 1,
    perm_discount TINYINT DEFAULT 0, max_discount INT DEFAULT 0,
    perm_reports TINYINT DEFAULT 0, perm_add_prod TINYINT DEFAULT 0,
    perm_edit_prod TINYINT DEFAULT 0, perm_del_prod TINYINT DEFAULT 0,
    perm_finance TINYINT DEFAULT 0, perm_orders TINYINT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
mysqli_query($conn,"CREATE TABLE IF NOT EXISTS salary_payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL, note VARCHAR(255) DEFAULT '',
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, paid_by INT DEFAULT NULL
)");

// Xodimlar ro'yxati
$users_res = mysqli_query($conn,"SELECT * FROM users ORDER BY FIELD(role,'superadmin','admin','menejer','sotuvchi','mijoz'), id ASC");
$users = [];
if ($users_res) while($u = mysqli_fetch_assoc($users_res)) $users[] = $u;

// Statistika
$st2 = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as cnt, COALESCE(SUM(salary),0) as total_salary, SUM(status='active') as active_cnt, SUM(status='inactive') as inactive_cnt FROM users WHERE role NOT IN('mijoz')"));

// Joriy oy to'lovlari
$thisMonth = date('Y-m');
$month_paid = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) as total FROM salary_payments WHERE DATE_FORMAT(paid_at,'%Y-%m')='$thisMonth'"));

function roleBadge($role) {
    $map = [
        'superadmin' => ['👑','#7C3AED','#F3F0FF','#DDD6FE','Boshqaruvchi'],
        'admin'      => ['🛡️','#2563EB','#EFF6FF','#BFDBFE','Admin'],
        'menejer'    => ['📋','#EA580C','#FFF7ED','#FED7AA','Menejer'],
        'sotuvchi'   => ['🧾','#059669','#ECFDF5','#A7F3D0','Sotuvchi'],
        'mijoz'      => ['👤','#64748B','#F8FAFC','#E2E8F0','Mijoz'],
    ];
    return $map[$role] ?? ['👤','#64748B','#F8FAFC','#E2E8F0',$role];
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xodimlar | <?= htmlspecialchars($site_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
<style>
:root{--primary:#4318FF;--primary-l:#EEF2FF;--green:#059669;--red:#EF4444;--orange:#EA580C;--muted:#64748B;--border:#E2E8F0;--bg:#F8FAFC;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:#0F172A;}
.bento-main{margin-left:90px;padding:28px 32px;min-height:100vh;transition:margin-left .4s;}
@media(max-width:992px){.bento-main{margin-left:0;padding:16px;}}

/* PAGE HEADER */
.page-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
.page-title{font-size:26px;font-weight:900;letter-spacing:-.5px;}
.page-title span{color:var(--primary);}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px;}
.scard{background:#fff;border:1px solid var(--border);border-radius:16px;padding:16px 18px;display:flex;align-items:center;gap:14px;}
.scard-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.scard-val{font-size:22px;font-weight:900;line-height:1;margin-bottom:2px;}
.scard-lbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}

/* TOOLBAR */
.toolbar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;}
.search-wrap{position:relative;flex:1;min-width:200px;}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8;}
.search-inp{width:100%;height:40px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:0 12px 0 36px;font-size:14px;font-weight:500;}
.search-inp:focus{outline:none;border-color:var(--primary);}
.filter-sel{height:40px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:0 12px;font-size:13px;font-weight:600;color:#475569;cursor:pointer;}
.btn-add{height:40px;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:0 20px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;white-space:nowrap;box-shadow:0 4px 12px rgba(67,24,255,.25);}
.btn-add:hover{background:#3912E8;}

/* CARDS GRID */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}

/* EMPLOYEE CARD */
.emp-card{background:#fff;border:1.5px solid var(--border);border-radius:20px;padding:20px;transition:.18s;position:relative;overflow:hidden;}
.emp-card:hover{border-color:var(--primary);box-shadow:0 8px 24px rgba(67,24,255,.1);transform:translateY(-2px);}
.emp-card.inactive{opacity:.7;border-style:dashed;}

/* Card accent line */
.emp-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:20px 20px 0 0;}

/* Avatar */
.emp-avatar{width:54px;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:900;color:#fff;flex-shrink:0;overflow:hidden;}
.emp-avatar img{width:100%;height:100%;object-fit:cover;border-radius:14px;}

.emp-top{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;}
.emp-info{flex:1;min-width:0;}
.emp-name{font-size:15px;font-weight:800;color:#0F172A;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
.emp-user{font-size:11px;color:var(--muted);font-weight:600;font-family:monospace;margin-top:1px;}
.role-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-top:4px;}

/* Status dot */
.status-dot{position:absolute;top:14px;right:14px;width:10px;height:10px;border-radius:50%;}
.status-dot.active{background:#10B981;box-shadow:0 0 0 3px rgba(16,185,129,.2);}
.status-dot.inactive{background:#CBD5E1;}

/* Stats row in card */
.emp-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;}
.emp-stat{background:#F8FAFC;border-radius:10px;padding:8px 10px;}
.emp-stat-val{font-size:13px;font-weight:800;color:#0F172A;}
.emp-stat-lbl{font-size:10px;color:var(--muted);font-weight:600;margin-top:1px;}

/* Permission pills */
.perm-pills{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px;min-height:24px;}
.perm-pill{padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;}
.perm-on{background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;}
.perm-off{background:#F8FAFC;color:#CBD5E1;border:1px solid #F1F5F9;text-decoration:line-through;}

/* Card actions */
.emp-actions{display:flex;gap:6px;}
.act-btn{flex:1;height:34px;border:1.5px solid var(--border);border-radius:9px;background:#fff;font-size:12px;font-weight:700;color:#475569;cursor:pointer;transition:.15s;display:flex;align-items:center;justify-content:center;gap:5px;}
.act-btn:hover{background:var(--primary-l);border-color:var(--primary);color:var(--primary);}
.act-btn.danger:hover{background:#FEF2F2;border-color:var(--red);color:var(--red);}
.act-btn.success:hover{background:#ECFDF5;border-color:var(--green);color:var(--green);}

/* ═══ MODALS ═══ */
.modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9990;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);}
.modal-bg.open{display:flex;}
.modal-box{background:#fff;border-radius:24px;width:100%;max-width:560px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.2);}
.modal-head{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.modal-head h4{font-size:17px;font-weight:800;color:#0F172A;margin:0;}
.modal-close{background:#F1F5F9;border:none;border-radius:9px;width:32px;height:32px;font-size:16px;cursor:pointer;color:var(--muted);display:flex;align-items:center;justify-content:center;}
.modal-close:hover{background:var(--border);}
.modal-body{flex:1;overflow-y:auto;padding:20px 24px;}
.modal-foot{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;flex-shrink:0;}

/* TABS */
.tabs{display:flex;background:#F8FAFC;border:1.5px solid var(--border);border-radius:12px;padding:4px;margin-bottom:20px;}
.tab-btn{flex:1;padding:8px 6px;border-radius:8px;border:none;background:transparent;font-size:12px;font-weight:700;color:var(--muted);cursor:pointer;transition:.15s;font-family:'Inter',sans-serif;}
.tab-btn.active{background:#fff;color:var(--primary);box-shadow:0 2px 8px rgba(0,0,0,.08);}
.tab-pane{display:none;}
.tab-pane.active{display:block;}

/* FORM */
.fgroup{margin-bottom:14px;}
.flabel{display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;}
.finput{width:100%;height:44px;background:#F8FAFC;border:1.5px solid var(--border);border-radius:11px;padding:0 14px;font-size:14px;font-weight:600;color:#0F172A;transition:.18s;font-family:'Inter',sans-serif;}
.finput:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(67,24,255,.1);}
.finput::placeholder{color:#94A3B8;font-weight:500;}
.finput-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}

/* TOGGLE SWITCH */
.toggle-list{display:flex;flex-direction:column;gap:10px;}
.toggle-item{display:flex;align-items:center;justify-content:space-between;background:#F8FAFC;border:1.5px solid var(--border);border-radius:12px;padding:12px 14px;transition:.15s;}
.toggle-item:hover{border-color:#CBD5E1;}
.toggle-item.active{background:#F0FEFF;border-color:#A7F3D0;}
.toggle-left{display:flex;align-items:center;gap:10px;}
.toggle-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.toggle-name{font-size:13px;font-weight:700;color:#0F172A;}
.toggle-desc{font-size:11px;color:var(--muted);margin-top:1px;}
/* The switch */
.switch{position:relative;width:44px;height:24px;flex-shrink:0;}
.switch input{opacity:0;width:0;height:0;}
.slider{position:absolute;inset:0;background:#CBD5E1;border-radius:24px;cursor:pointer;transition:.25s;}
.slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 4px rgba(0,0,0,.2);}
input:checked+.slider{background:var(--primary);}
input:checked+.slider:before{transform:translateX(20px);}

/* Max discount input */
.discount-row{display:flex;align-items:center;gap:8px;margin-top:8px;padding:10px 14px;background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:10px;}
.discount-row label{font-size:12px;font-weight:700;color:#EA580C;flex:1;}
.discount-inp{width:70px;height:32px;border:1.5px solid #FED7AA;border-radius:8px;background:#fff;text-align:center;font-size:14px;font-weight:800;color:#EA580C;padding:0 8px;}

/* SALARY SECTION */
.salary-current{background:linear-gradient(135deg,#4318FF,#7C3AED);border-radius:16px;padding:20px;color:#fff;text-align:center;margin-bottom:16px;}
.salary-current .amount{font-size:32px;font-weight:900;letter-spacing:-1px;}
.salary-current .label{font-size:12px;opacity:.8;margin-top:4px;}
.month-status{display:flex;align-items:center;justify-content:space-between;background:#F0FDF4;border:1.5px solid #A7F3D0;border-radius:12px;padding:12px 16px;margin-bottom:16px;}
.pay-history{background:#F8FAFC;border-radius:12px;overflow:hidden;}
.pay-row{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border);}
.pay-row:last-child{border-bottom:none;}
.pay-row-date{font-size:12px;color:var(--muted);font-weight:600;}
.pay-row-amt{font-size:14px;font-weight:800;color:var(--green);}
.pay-row-note{font-size:11px;color:var(--muted);}

/* BTNs */
.btn-primary{background:var(--primary);color:#fff;border:none;border-radius:11px;height:44px;padding:0 22px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s;font-family:'Inter',sans-serif;}
.btn-primary:hover{background:#3912E8;}
.btn-primary:disabled{opacity:.55;pointer-events:none;}
.btn-sec{background:#F1F5F9;color:#475569;border:none;border-radius:11px;height:44px;padding:0 18px;font-size:14px;font-weight:600;cursor:pointer;transition:.15s;font-family:'Inter',sans-serif;}
.btn-sec:hover{background:var(--border);}
.btn-danger{background:#FEF2F2;color:var(--red);border:1px solid #FECACA;border-radius:11px;height:44px;padding:0 18px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s;font-family:'Inter',sans-serif;}
.btn-danger:hover{background:var(--red);color:#fff;}
.btn-success{background:#ECFDF5;color:var(--green);border:1px solid #A7F3D0;border-radius:11px;height:44px;padding:0 18px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;}
.btn-success:hover{background:var(--green);color:#fff;}

/* TOAST */
.toast-box{position:fixed;bottom:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;}
.xtoast{background:#0F172A;color:#fff;padding:12px 18px;border-radius:12px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.25);animation:tIn .3s ease;display:flex;align-items:center;gap:8px;}
.xtoast.ok{background:#065F46;}
.xtoast.err{background:#991B1B;}
@keyframes tIn{from{transform:translateY(16px);opacity:0}to{transform:translateY(0);opacity:1}}

/* EMPTY */
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
</style>
</head>
<body>

<?php
$sidebar_path = dirname(__DIR__) . "/includes/sidebar.php";
if (file_exists($sidebar_path)) require_once $sidebar_path;
?>

<div class="bento-main">

  <!-- HEADER -->
  <div class="page-hd">
    <div>
      <div class="page-title">👥 <span>Xodimlar</span> Boshqaruvi</div>
      <div style="font-size:13px;color:var(--muted);margin-top:3px;">Ruhsatlar, maosh va xodim ma'lumotlarini boshqaring</div>
    </div>
    <button class="btn-add" onclick="openAddModal()">
      <i class="fas fa-user-plus"></i> Yangi Xodim
    </button>
  </div>

  <!-- STATS -->
  <div class="stats-row">
    <div class="scard">
      <div class="scard-ico" style="background:#EEF2FF;">👥</div>
      <div>
        <div class="scard-val"><?= (int)($st2['cnt']??0) ?></div>
        <div class="scard-lbl">Jami xodim</div>
      </div>
    </div>
    <div class="scard">
      <div class="scard-ico" style="background:#ECFDF5;">✅</div>
      <div>
        <div class="scard-val" style="color:#059669;"><?= (int)($st2['active_cnt']??0) ?></div>
        <div class="scard-lbl">Faol</div>
      </div>
    </div>
    <div class="scard">
      <div class="scard-ico" style="background:#F3F0FF;">💰</div>
      <div>
        <div class="scard-val"><?= number_format((float)($st2['total_salary']??0)/1000000,1) ?>M</div>
        <div class="scard-lbl">Oy maoshi (jami)</div>
      </div>
    </div>
    <div class="scard">
      <div class="scard-ico" style="background:#FFF7ED;">📤</div>
      <div>
        <div class="scard-val" style="color:#EA580C;"><?= number_format((float)($month_paid['total']??0)/1000000,1) ?>M</div>
        <div class="scard-lbl"><?= date('F') ?> to'langan</div>
      </div>
    </div>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" class="search-inp" id="searchInp" placeholder="Ism yoki login bo'yicha qidirish..." oninput="filterCards()">
    </div>
    <select class="filter-sel" id="roleFilter" onchange="filterCards()">
      <option value="">Barcha rollar</option>
      <option value="superadmin">👑 Boshqaruvchi</option>
      <option value="admin">🛡️ Admin</option>
      <option value="menejer">📋 Menejer</option>
      <option value="sotuvchi">🧾 Sotuvchi</option>
    </select>
    <select class="filter-sel" id="statusFilter" onchange="filterCards()">
      <option value="">Barcha holat</option>
      <option value="active">✅ Faol</option>
      <option value="inactive">⛔ Nofaol</option>
    </select>
  </div>

  <!-- CARDS -->
  <div class="cards-grid" id="cardsGrid">
    <?php foreach($users as $u):
      if ($u['role'] === 'mijoz') continue;
      [$ico,$clr,$bg,$border,$roleLabel] = roleBadge($u['role']);
      $initials = mb_strtoupper(mb_substr($u['name'],0,1));
      $isActive = ($u['status']??'active') === 'active';
      $salary   = (float)($u['salary']??0);
      $isSelf   = ($u['id'] == $_SESSION['user_id']);
      $isSuper  = $u['role'] === 'superadmin';

      // Ruhsatlar
      $perms = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user_permissions WHERE user_id={$u['id']} LIMIT 1"));

      // Joriy oy maoshi
      $mPaid = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) as total FROM salary_payments WHERE user_id={$u['id']} AND DATE_FORMAT(paid_at,'%Y-%m')='{$thisMonth}'"));
      $paidThisMonth = (float)($mPaid['total']??0);

      // Ruhsatlar soni
      $permCount = 0;
      if ($perms) {
          foreach(['perm_sell','perm_discount','perm_reports','perm_add_prod','perm_edit_prod','perm_del_prod','perm_finance','perm_orders'] as $pk)
              if (!empty($perms[$pk])) $permCount++;
      }
    ?>
    <div class="emp-card <?= $isActive?'':'inactive' ?>"
         data-name="<?= htmlspecialchars(mb_strtolower($u['name'])) ?>"
         data-user="<?= htmlspecialchars(mb_strtolower($u['username'])) ?>"
         data-role="<?= $u['role'] ?>"
         data-status="<?= $u['status']??'active' ?>"
         style="border-top-color:<?= $clr ?>">

      <div class="status-dot <?= $isActive?'active':'inactive' ?>"></div>

      <div class="emp-top">
        <div class="emp-avatar" style="background:<?= $clr ?>">
          <?php if(!empty($u['avatar'])): ?>
            <img src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="emp-info">
          <div class="emp-name"><?= htmlspecialchars($u['name']) ?></div>
          <div class="emp-user">@<?= htmlspecialchars($u['username']) ?></div>
          <span class="role-badge" style="background:<?= $bg ?>;color:<?= $clr ?>;border:1px solid <?= $border ?>">
            <?= $ico ?> <?= $roleLabel ?>
          </span>
        </div>
      </div>

      <!-- Stats -->
      <div class="emp-stats">
        <div class="emp-stat">
          <div class="emp-stat-val"><?= $salary>0?number_format($salary/1000000,1).'M':'—' ?></div>
          <div class="emp-stat-lbl">💰 Oylik maosh</div>
        </div>
        <div class="emp-stat">
          <div class="emp-stat-val" style="color:<?= $paidThisMonth>0?'#059669':'#94A3B8' ?>">
            <?= $paidThisMonth>0?number_format($paidThisMonth/1000000,1).'M':'To\'lanmagan' ?>
          </div>
          <div class="emp-stat-lbl">📤 Bu oy</div>
        </div>
      </div>

      <!-- Permission pills -->
      <div class="perm-pills">
        <?php
        $permMap = ['perm_sell'=>'🛒 Sotish','perm_discount'=>'💸 Chegirma','perm_orders'=>'📋 Buyurtma','perm_reports'=>'📊 Hisobot','perm_add_prod'=>'➕ Qo\'shish','perm_edit_prod'=>'✏️ Tahrirlash','perm_finance'=>'💼 Moliya'];
        if ($perms) {
            foreach($permMap as $pk=>$plabel) {
                if (!empty($perms[$pk])) echo "<span class='perm-pill perm-on'>$plabel</span>";
            }
        } else {
            echo "<span style='font-size:11px;color:#94A3B8;'>Ruhsatlar belgilanmagan</span>";
        }
        ?>
      </div>

      <!-- Actions -->
      <div class="emp-actions">
        <button class="act-btn" onclick="openEditModal(<?= $u['id'] ?>)" title="Tahrirlash">
          <i class="fas fa-edit"></i> Tahrir
        </button>
        <button class="act-btn" onclick="openPermModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'],ENT_QUOTES) ?>')" title="Ruhsatlar">
          <i class="fas fa-shield-alt"></i> Ruhsat
        </button>
        <button class="act-btn success" onclick="openSalaryModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'],ENT_QUOTES) ?>')" title="Maosh">
          <i class="fas fa-money-bill-wave"></i>
        </button>
        <?php if(!$isSelf && !$isSuper): ?>
        <button class="act-btn danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'],ENT_QUOTES) ?>')" title="O'chirish">
          <i class="fas fa-trash-alt"></i>
        </button>
        <?php endif; ?>
      </div>

    </div>
    <?php endforeach; ?>
  </div>

</div><!-- /bento-main -->

<!-- ═══════════════════════════════════════════
     MODAL 1: XODIM QO'SHISH
═══════════════════════════════════════════ -->
<div class="modal-bg" id="addModal" onclick="if(event.target===this)closeModal('addModal')">
  <div class="modal-box" style="max-width:520px;">
    <div class="modal-head">
      <h4>👤 Yangi Xodim Qo'shish</h4>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="addAlert"></div>
      <div class="finput-row">
        <div class="fgroup">
          <label class="flabel">To'liq ism *</label>
          <input type="text" class="finput" id="add_name" placeholder="Sardor Komilov">
        </div>
        <div class="fgroup">
          <label class="flabel">Telefon</label>
          <input type="tel" class="finput" id="add_phone" placeholder="+998 90 123 45 67">
        </div>
      </div>
      <div class="fgroup">
        <label class="flabel">Manzil</label>
        <input type="text" class="finput" id="add_address" placeholder="Toshkent, Chilonzor">
      </div>
      <div class="finput-row">
        <div class="fgroup">
          <label class="flabel">Login *</label>
          <input type="text" class="finput" id="add_username" placeholder="sardor_kassa" autocomplete="off">
        </div>
        <div class="fgroup">
          <label class="flabel">Parol * (min 4)</label>
          <input type="password" class="finput" id="add_password" placeholder="••••••••" autocomplete="new-password">
        </div>
      </div>
      <div class="finput-row">
        <div class="fgroup">
          <label class="flabel">Vazifa (Rol) *</label>
          <select class="finput" id="add_role" style="cursor:pointer;">
            <option value="sotuvchi">🧾 Sotuvchi</option>
            <option value="menejer">📋 Menejer</option>
            <option value="admin">🛡️ Admin</option>
          </select>
        </div>
        <div class="fgroup">
          <label class="flabel">Oylik maosh (UZS)</label>
          <input type="number" class="finput" id="add_salary" placeholder="3000000" min="0">
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-sec" onclick="closeModal('addModal')">Bekor</button>
      <button class="btn-primary" style="flex:1;" onclick="addUser()">
        <i class="fas fa-user-plus mr-1"></i> Qo'shish
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL 2: TAHRIRLASH (info + parol + faol/nofaol)
═══════════════════════════════════════════ -->
<div class="modal-bg" id="editModal" onclick="if(event.target===this)closeModal('editModal')">
  <div class="modal-box" style="max-width:520px;">
    <div class="modal-head">
      <h4 id="editModalTitle">✏️ Xodimni Tahrirlash</h4>
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('edit','info',this)">👤 Ma'lumotlar</button>
        <button class="tab-btn" onclick="switchTab('edit','pass',this)">🔒 Parol</button>
        <button class="tab-btn" onclick="switchTab('edit','status',this)">⚙️ Holat</button>
      </div>
      <input type="hidden" id="edit_uid">

      <!-- Info tab -->
      <div class="tab-pane active" id="edit-tab-info">
        <div id="editAlert"></div>
        <div class="finput-row">
          <div class="fgroup">
            <label class="flabel">To'liq ism</label>
            <input type="text" class="finput" id="edit_name">
          </div>
          <div class="fgroup">
            <label class="flabel">Telefon</label>
            <input type="tel" class="finput" id="edit_phone">
          </div>
        </div>
        <div class="fgroup">
          <label class="flabel">Manzil</label>
          <input type="text" class="finput" id="edit_address">
        </div>
        <div class="fgroup">
          <label class="flabel">Vazifa (Rol)</label>
          <select class="finput" id="edit_role" style="cursor:pointer;">
            <option value="sotuvchi">🧾 Sotuvchi</option>
            <option value="menejer">📋 Menejer</option>
            <option value="admin">🛡️ Admin</option>
          </select>
        </div>
        <button class="btn-primary" style="width:100%;" onclick="saveInfo()">💾 Saqlash</button>
      </div>

      <!-- Password tab -->
      <div class="tab-pane" id="edit-tab-pass">
        <div class="fgroup">
          <label class="flabel">Yangi parol (min 4 belgi)</label>
          <input type="password" class="finput" id="edit_newpass" placeholder="Yangi parol" autocomplete="new-password">
        </div>
        <div class="fgroup">
          <label class="flabel">Parolni tasdiqlang</label>
          <input type="password" class="finput" id="edit_confpass" placeholder="Qayta kiriting">
        </div>
        <div id="passAlert" style="margin-bottom:10px;"></div>
        <button class="btn-primary" style="width:100%;" onclick="savePassword()">🔒 Parolni saqlash</button>
      </div>

      <!-- Status tab -->
      <div class="tab-pane" id="edit-tab-status">
        <div style="text-align:center;padding:20px 0;">
          <div style="font-size:52px;margin-bottom:12px;" id="statusEmoji">✅</div>
          <div style="font-size:16px;font-weight:800;color:#0F172A;margin-bottom:6px;" id="statusTitle">Xodim faol</div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:24px;" id="statusDesc">Xodim tizimga kira oladi</div>
          <button class="btn-danger" id="toggleStatusBtn" style="width:100%;height:48px;font-size:15px;" onclick="toggleStatus()">
            ⛔ Bloklash
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL 3: RUHSATLAR
═══════════════════════════════════════════ -->
<div class="modal-bg" id="permModal" onclick="if(event.target===this)closeModal('permModal')">
  <div class="modal-box" style="max-width:500px;">
    <div class="modal-head">
      <h4>🛡️ Ruhsatlar — <span id="permUserName" style="color:var(--primary)"></span></h4>
      <button class="modal-close" onclick="closeModal('permModal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="perm_uid">
      <div style="background:#EEF2FF;border-radius:12px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#4338CA;font-weight:600;">
        💡 Har bir ruhsatni alohida yoqing yoki o'chiring. O'zgarishlar darhol saqlanadi.
      </div>
      <div class="toggle-list" id="permList">
        <!-- JS bilan to'ldiriladi -->
      </div>
      <div id="discountExtra" style="display:none;">
        <div class="discount-row">
          <label><i class="fas fa-percent mr-1"></i> Maksimal chegirma foizi:</label>
          <input type="number" class="discount-inp" id="maxDiscountInp" min="0" max="100" value="0"> %
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-sec" onclick="closeModal('permModal')">Yopish</button>
      <button class="btn-primary" style="flex:1;" onclick="savePerms()">
        <i class="fas fa-save mr-1"></i> Ruhsatlarni Saqlash
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL 4: MAOSH
═══════════════════════════════════════════ -->
<div class="modal-bg" id="salaryModal" onclick="if(event.target===this)closeModal('salaryModal')">
  <div class="modal-box" style="max-width:480px;">
    <div class="modal-head">
      <h4>💰 Maosh — <span id="salaryUserName" style="color:var(--primary)"></span></h4>
      <button class="modal-close" onclick="closeModal('salaryModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('sal','pay',this)">💳 To'lov</button>
        <button class="tab-btn" onclick="switchTab('sal','history',this)">📋 Tarix</button>
        <button class="tab-btn" onclick="switchTab('sal','edit',this)">✏️ Belgilash</button>
      </div>
      <input type="hidden" id="sal_uid">

      <!-- Pay tab -->
      <div class="tab-pane active" id="sal-tab-pay">
        <div class="salary-current">
          <div class="label">Oylik maosh (belgilangan)</div>
          <div class="amount" id="sal_monthly">—</div>
          <div class="label" style="margin-top:4px;">UZS / oy</div>
        </div>
        <div class="month-status" id="monthStatus">
          <div>
            <div style="font-size:13px;font-weight:700;color:#065F46;">📅 <?= date('F Y') ?></div>
            <div style="font-size:12px;color:#059669;margin-top:2px;" id="monthPaidInfo">Yuklanmoqda...</div>
          </div>
          <div id="monthBadge"></div>
        </div>
        <div class="fgroup">
          <label class="flabel">To'lov summasi (UZS)</label>
          <input type="number" class="finput" id="pay_amount" placeholder="3 000 000" min="0">
        </div>
        <div class="fgroup">
          <label class="flabel">Izoh (ixtiyoriy)</label>
          <input type="text" class="finput" id="pay_note" placeholder="Iyun oyi maoshi...">
        </div>
        <div id="payAlert" style="margin-bottom:10px;"></div>
        <button class="btn-success" style="width:100%;height:48px;font-size:15px;" onclick="paySalary()">
          💳 Maosh To'lash
        </button>
      </div>

      <!-- History tab -->
      <div class="tab-pane" id="sal-tab-history">
        <div class="pay-history" id="payHistory">
          <div style="text-align:center;padding:30px;color:var(--muted);">⏳ Yuklanmoqda...</div>
        </div>
      </div>

      <!-- Edit monthly tab -->
      <div class="tab-pane" id="sal-tab-edit">
        <div style="background:#F3F0FF;border-radius:12px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#7C3AED;font-weight:600;">
          💡 Bu yerda xodimning oylik maosh miqdorini belgilaysiz. Bu faqat mos yozuv uchun — to'lov qo'lda amalga oshiriladi.
        </div>
        <div class="fgroup">
          <label class="flabel">Yangi oylik maosh (UZS)</label>
          <input type="number" class="finput" id="sal_new_amount" placeholder="3 500 000" min="0">
        </div>
        <button class="btn-primary" style="width:100%;height:48px;" onclick="updateSalary()">
          💾 Maoshni Belgilash
        </button>
      </div>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast-box" id="toastBox"></div>

<script>
const API = '/admin/Xodimlar/xodim_api.php';

// ── Qidirish + filter ──────────────────────────────────────────
function filterCards() {
  const q      = document.getElementById('searchInp').value.toLowerCase().trim();
  const role   = document.getElementById('roleFilter').value;
  const status = document.getElementById('statusFilter').value;
  let found = 0;
  document.querySelectorAll('.emp-card').forEach(card => {
    const nm = (card.dataset.name||'') + (card.dataset.user||'');
    const matchQ  = !q      || nm.includes(q);
    const matchR  = !role   || card.dataset.role   === role;
    const matchS  = !status || card.dataset.status === status;
    const show = matchQ && matchR && matchS;
    card.style.display = show ? '' : 'none';
    if (show) found++;
  });
}

// ── Modalni ochish/yopish ──────────────────────────────────────
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id)  { document.getElementById(id).classList.add('open'); }

// ── Tab almashtirish ───────────────────────────────────────────
function switchTab(prefix, name, btn) {
  const scope = prefix+'-tab-';
  document.querySelectorAll('[id^="'+scope+'"]').forEach(p=>p.classList.remove('active'));
  btn.closest('.modal-box').querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById(scope+name).classList.add('active');
  btn.classList.add('active');
}

// ══ ADD USER ══════════════════════════════════════════════════
function openAddModal() {
  ['add_name','add_phone','add_address','add_username','add_password','add_salary'].forEach(id=>{
    const el=document.getElementById(id); if(el) el.value='';
  });
  document.getElementById('add_role').value='sotuvchi';
  document.getElementById('addAlert').innerHTML='';
  openModal('addModal');
  setTimeout(()=>document.getElementById('add_name').focus(),100);
}

async function addUser() {
  const name    = document.getElementById('add_name').value.trim();
  const username= document.getElementById('add_username').value.trim();
  const password= document.getElementById('add_password').value.trim();
  if (!name||!username||password.length<4) {
    showAlert('addAlert','Ism, login va parol (min 4 belgi) talab qilinadi','err'); return;
  }
  const fd = new FormData();
  fd.append('action','add_user');
  fd.append('name',name);
  fd.append('username',username);
  fd.append('password',password);
  fd.append('phone',  document.getElementById('add_phone').value.trim());
  fd.append('address',document.getElementById('add_address').value.trim());
  fd.append('role',   document.getElementById('add_role').value);
  fd.append('salary', document.getElementById('add_salary').value||0);
  const d = await post(fd);
  if (d.status==='ok') {
    closeModal('addModal'); toast('Xodim muvaffaqiyatli qo\'shildi! ✅','ok'); reload();
  } else showAlert('addAlert',d.message||'Xato','err');
}

// ══ EDIT USER ═════════════════════════════════════════════════
function openEditModal(uid) {
  const card = document.querySelector(`.emp-card[data-name]`);
  // Find the card by matching action button
  const btn = event.target.closest('.act-btn');
  const c   = btn ? btn.closest('.emp-card') : null;

  document.getElementById('edit_uid').value = uid;
  document.getElementById('editAlert').innerHTML = '';
  document.getElementById('passAlert').innerHTML = '';
  document.getElementById('edit_newpass').value = '';
  document.getElementById('edit_confpass').value = '';

  // Fill from page data
  fetch(API+'?action=list').then(r=>r.json()).then(d=>{
    const u = (d.users||[]).find(x=>x.id==uid);
    if (!u) return;
    document.getElementById('editModalTitle').innerHTML = '✏️ '+escH(u.name)+' — Tahrirlash';
    document.getElementById('edit_name').value    = u.name||'';
    document.getElementById('edit_phone').value   = u.phone||'';
    document.getElementById('edit_address').value = u.address||'';
    document.getElementById('edit_role').value    = u.role||'sotuvchi';
    // Status tab
    const isActive = (u.status||'active')==='active';
    document.getElementById('statusEmoji').textContent  = isActive ? '✅' : '⛔';
    document.getElementById('statusTitle').textContent  = isActive ? 'Xodim faol' : 'Xodim bloklangan';
    document.getElementById('statusDesc').textContent   = isActive ? 'Tizimga kira oladi' : 'Tizimga kira olmaydi';
    document.getElementById('toggleStatusBtn').textContent = isActive ? '⛔ Bloklash' : '✅ Faollashtirish';
    document.getElementById('toggleStatusBtn').className = isActive ? 'btn-danger' : 'btn-success';
    document.getElementById('toggleStatusBtn').style.width='100%';
    document.getElementById('toggleStatusBtn').style.height='48px';
    document.getElementById('toggleStatusBtn').style.fontSize='15px';
  });

  // Reset to first tab
  document.getElementById('editModal').querySelectorAll('.tab-btn').forEach((b,i)=>{b.classList.toggle('active',i===0);});
  document.getElementById('editModal').querySelectorAll('.tab-pane').forEach((p,i)=>{p.classList.toggle('active',i===0);});

  openModal('editModal');
}

async function saveInfo() {
  const uid  = document.getElementById('edit_uid').value;
  const name = document.getElementById('edit_name').value.trim();
  if (!name) { showAlert('editAlert','Ism bo\'sh bo\'lmasin','err'); return; }
  const fd = new FormData();
  fd.append('action','update_info');
  fd.append('user_id',uid);
  fd.append('name',name);
  fd.append('phone',  document.getElementById('edit_phone').value.trim());
  fd.append('address',document.getElementById('edit_address').value.trim());
  fd.append('role',   document.getElementById('edit_role').value);
  const d = await post(fd);
  if(d.status==='ok') { showAlert('editAlert','Ma\'lumotlar saqlandi ✅','ok'); reload(); }
  else showAlert('editAlert',d.message||'Xato','err');
}

async function savePassword() {
  const uid  = document.getElementById('edit_uid').value;
  const p1   = document.getElementById('edit_newpass').value;
  const p2   = document.getElementById('edit_confpass').value;
  if(p1.length<4){ showAlert('passAlert','Parol kamida 4 belgi','err'); return; }
  if(p1!==p2)    { showAlert('passAlert','Parollar mos kelmadi','err'); return; }
  const fd = new FormData();
  fd.append('action','change_password');
  fd.append('user_id',uid);
  fd.append('password',p1);
  const d = await post(fd);
  if(d.status==='ok'){ showAlert('passAlert','Parol o\'zgartirildi ✅','ok'); document.getElementById('edit_newpass').value=''; document.getElementById('edit_confpass').value=''; }
  else showAlert('passAlert',d.message||'Xato','err');
}

async function toggleStatus() {
  const uid = document.getElementById('edit_uid').value;
  const fd  = new FormData(); fd.append('action','toggle_status'); fd.append('user_id',uid);
  const d   = await post(fd);
  if(d.status==='ok') {
    const isActive = d.new_status==='active';
    document.getElementById('statusEmoji').textContent = isActive?'✅':'⛔';
    document.getElementById('statusTitle').textContent = isActive?'Xodim faol':'Xodim bloklangan';
    document.getElementById('statusDesc').textContent  = isActive?'Tizimga kira oladi':'Tizimga kira olmaydi';
    document.getElementById('toggleStatusBtn').textContent = isActive?'⛔ Bloklash':'✅ Faollashtirish';
    document.getElementById('toggleStatusBtn').className = (isActive?'btn-danger':'btn-success');
    document.getElementById('toggleStatusBtn').style.cssText='width:100%;height:48px;font-size:15px;';
    toast(isActive?'Xodim faollashtirildi ✅':'Xodim bloklandi ⛔', isActive?'ok':'err');
    reload();
  }
}

// ══ PERMISSIONS ═══════════════════════════════════════════════
const PERMS_CONFIG = [
  {key:'perm_sell',     ico:'🛒', name:'Sotish (Kassa)',          desc:'POS terminaldan foydalana oladi'},
  {key:'perm_discount', ico:'💸', name:'Chegirma berish',          desc:'Sotuvda chegirma qo\'llash huquqi'},
  {key:'perm_orders',   ico:'📋', name:'Buyurtmalarni ko\'rish',   desc:'Menejer buyurtmalar ro\'yxatini ko\'radi'},
  {key:'perm_reports',  ico:'📊', name:'Hisobotlarni ko\'rish',    desc:'Savdo statistikasi va tahlillarga kirish'},
  {key:'perm_add_prod', ico:'➕', name:'Mahsulot qo\'shish',       desc:'Ombor ga yangi mahsulot qo\'sha oladi'},
  {key:'perm_edit_prod',ico:'✏️', name:'Mahsulot tahrirlash',      desc:'Narx, miqdor va ma\'lumotlarni o\'zgartiradi'},
  {key:'perm_del_prod', ico:'🗑️', name:'Mahsulot o\'chirish',     desc:'Ombor dan mahsulotni o\'chira oladi'},
  {key:'perm_finance',  ico:'💼', name:'Moliyaviy tahlil',         desc:'Daromad, xarajat va foydani ko\'radi'},
];

async function openPermModal(uid, name) {
  document.getElementById('perm_uid').value = uid;
  document.getElementById('permUserName').textContent = name;
  openModal('permModal');

  const d = await get('get_permissions', uid);
  if(d.status!=='ok') return;
  const p = d.permissions;
  const list = document.getElementById('permList');
  list.innerHTML = PERMS_CONFIG.map(cfg => {
    const checked = p[cfg.key]==1;
    return `<div class="toggle-item ${checked?'active':''}" id="ti_${cfg.key}">
      <div class="toggle-left">
        <div class="toggle-ico" style="background:${checked?'#ECFDF5':'#F8FAFC'}">${cfg.ico}</div>
        <div>
          <div class="toggle-name">${cfg.name}</div>
          <div class="toggle-desc">${cfg.desc}</div>
        </div>
      </div>
      <label class="switch">
        <input type="checkbox" id="p_${cfg.key}" ${checked?'checked':''} onchange="onPermChange('${cfg.key}',this)">
        <span class="slider"></span>
      </label>
    </div>`;
  }).join('');

  document.getElementById('maxDiscountInp').value = p.max_discount||0;
  document.getElementById('discountExtra').style.display = p.perm_discount==1?'block':'none';
}

function onPermChange(key, cb) {
  const item = document.getElementById('ti_'+key);
  const ico  = item.querySelector('.toggle-ico');
  if(cb.checked) {
    item.classList.add('active');
    ico.style.background='#ECFDF5';
  } else {
    item.classList.remove('active');
    ico.style.background='#F8FAFC';
  }
  if(key==='perm_discount') {
    document.getElementById('discountExtra').style.display = cb.checked?'block':'none';
  }
}

async function savePerms() {
  const uid = document.getElementById('perm_uid').value;
  const fd  = new FormData();
  fd.append('action','save_permissions');
  fd.append('user_id',uid);
  PERMS_CONFIG.forEach(cfg => {
    const cb = document.getElementById('p_'+cfg.key);
    fd.append(cfg.key, cb&&cb.checked?1:0);
  });
  fd.append('max_discount', document.getElementById('maxDiscountInp').value||0);
  const d = await post(fd);
  if(d.status==='ok') { toast('Ruhsatlar saqlandi ✅','ok'); closeModal('permModal'); reload(); }
  else toast(d.message||'Xato','err');
}

// ══ SALARY ════════════════════════════════════════════════════
async function openSalaryModal(uid, name) {
  document.getElementById('sal_uid').value = uid;
  document.getElementById('salaryUserName').textContent = name;
  document.getElementById('pay_amount').value='';
  document.getElementById('pay_note').value='';
  document.getElementById('payAlert').innerHTML='';
  // Reset to first tab
  document.getElementById('salaryModal').querySelectorAll('.tab-btn').forEach((b,i)=>b.classList.toggle('active',i===0));
  document.getElementById('salaryModal').querySelectorAll('.tab-pane').forEach((p,i)=>p.classList.toggle('active',i===0));
  openModal('salaryModal');
  loadSalaryData(uid);
}

async function loadSalaryData(uid) {
  const d = await get('get_salary', uid);
  if(d.status!=='ok') return;
  const monthly = (float2(d.user.salary)||0);
  document.getElementById('sal_monthly').textContent = FMT(monthly);
  document.getElementById('sal_new_amount').value = monthly||'';

  // Month status
  const paid = float2(d.month_paid)||0;
  document.getElementById('monthPaidInfo').textContent = paid>0 ? 'To\'langan: '+FMT(paid)+' UZS' : 'Hali to\'lanmagan';
  const badge = document.getElementById('monthBadge');
  badge.innerHTML = paid>0
    ? '<span style="background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;">✅ To\'langan</span>'
    : '<span style="background:#FEF2F2;color:#EF4444;border:1px solid #FECACA;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;">⏳ Kutilmoqda</span>';

  // Suggest amount
  if(!document.getElementById('pay_amount').value && monthly>0)
    document.getElementById('pay_amount').value = monthly;

  // History
  const hist = document.getElementById('payHistory');
  if(!d.payments || !d.payments.length) {
    hist.innerHTML='<div style="text-align:center;padding:30px;color:#94A3B8;font-size:13px;">📭 Maosh tarixi yo\'q</div>';
  } else {
    hist.innerHTML = d.payments.map(p=>`
      <div class="pay-row">
        <div>
          <div class="pay-row-date">📅 ${p.paid_at ? p.paid_at.substring(0,16) : '—'}</div>
          ${p.note ? '<div class="pay-row-note">'+escH(p.note)+'</div>' : ''}
        </div>
        <div class="pay-row-amt">+ ${FMT(p.amount)} UZS</div>
      </div>`).join('');
  }
}

async function paySalary() {
  const uid    = document.getElementById('sal_uid').value;
  const amount = parseFloat(document.getElementById('pay_amount').value)||0;
  const note   = document.getElementById('pay_note').value.trim();
  if(amount<=0){ showAlert('payAlert','Summa kiriting','err'); return; }
  const fd = new FormData();
  fd.append('action','pay_salary');
  fd.append('user_id',uid);
  fd.append('amount',amount);
  fd.append('note',note);
  const d = await post(fd);
  if(d.status==='ok') {
    toast('Maosh to\'landi ✅','ok');
    loadSalaryData(uid); reload();
    document.getElementById('pay_amount').value='';
    document.getElementById('pay_note').value='';
  } else showAlert('payAlert',d.message||'Xato','err');
}

async function updateSalary() {
  const uid    = document.getElementById('sal_uid').value;
  const amount = parseFloat(document.getElementById('sal_new_amount').value)||0;
  const fd = new FormData();
  fd.append('action','update_salary');
  fd.append('user_id',uid);
  fd.append('salary',amount);
  const d = await post(fd);
  if(d.status==='ok') {
    toast('Oylik maosh yangilandi ✅','ok');
    document.getElementById('sal_monthly').textContent = FMT(amount);
    reload();
  }
}

// ══ DELETE ════════════════════════════════════════════════════
async function deleteUser(uid, name) {
  if(!confirm(`"${name}" ni o'chirasizmi? Bu amalni qaytarib bo'lmaydi!`)) return;
  const fd = new FormData(); fd.append('action','delete_user'); fd.append('user_id',uid);
  const d  = await post(fd);
  if(d.status==='ok') { toast('Xodim o\'chirildi','ok'); reload(); }
  else toast(d.message||'Xato','err');
}

// ══ HELPERS ═══════════════════════════════════════════════════
async function post(fd)      { const r=await fetch(API,{method:'POST',body:fd}); return r.json(); }
async function get(a,id='')  { const r=await fetch(`${API}?action=${a}${id?'&id='+id:''}`); return r.json(); }
const FMT = n => Number(n).toLocaleString('uz-UZ');
const float2 = v => parseFloat(v)||0;
function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showAlert(id, msg, type) {
  const el = document.getElementById(id);
  if(!el) return;
  el.innerHTML = `<div style="padding:10px 14px;border-radius:10px;font-size:13px;font-weight:700;margin-bottom:10px;
    background:${type==='ok'?'#ECFDF5':'#FEF2F2'};color:${type==='ok'?'#065F46':'#B91C1C'};
    border:1px solid ${type==='ok'?'#A7F3D0':'#FECACA'};">
    ${type==='ok'?'✅':'❌'} ${escH(msg)}</div>`;
  if(type==='ok') setTimeout(()=>{if(el) el.innerHTML='';},3000);
}

function toast(msg, type='ok') {
  const box = document.getElementById('toastBox');
  const el  = document.createElement('div');
  el.className = 'xtoast '+type;
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(()=>el.remove(), 3500);
}

function reload() {
  // Sahifani yangilash (cards ni server dan qayta olish)
  setTimeout(()=>location.reload(), 400);
}
</script>
</body>
</html>
