<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mijoz') {
    header("Location: /auth/login.php"); exit;
}
require_once "../config/dokon_db.php";

$st       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM settings WHERE id=1"));
$site     = $st['store_name'] ?? 'ELEVEN';
$ism      = $_SESSION['name'] ?? 'Mijoz';
$cart     = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cart,'qty'));
$cartTotal = array_sum(array_map(fn($i)=>$i['price']*$i['qty'], $cart));

// Kategoriyalar
$cats_res = mysqli_query($conn,"SELECT id, name FROM categories ORDER BY name ASC");
$categories = [];
while($c = mysqli_fetch_assoc($cats_res)) $categories[] = $c;
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title><?= htmlspecialchars($site) ?> — Do'kon</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
:root{
  --primary:#6366f1;--primary-d:#4f46e5;
  --green:#10b981;--red:#ef4444;
  --bg:#f1f5f9;--card:#fff;
  --text:#0f172a;--muted:#64748b;
  --border:#e2e8f0;--radius:14px;
}
body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

/* ── HEADER ── */
.header{
  background:#fff; border-bottom:1px solid var(--border);
  padding:0 16px; height:60px;
  display:flex; align-items:center; justify-content:space-between;
  position:sticky; top:0; z-index:100;
  box-shadow:0 1px 8px rgba(0,0,0,.06);
}
.logo-txt{font-size:20px;font-weight:900;background:linear-gradient(135deg,var(--primary),var(--green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.header-right{display:flex;align-items:center;gap:10px;}
.user-chip{
  background:#f1f5f9; border-radius:999px;
  padding:6px 12px 6px 8px;
  font-size:13px; font-weight:600; color:var(--muted);
  display:flex; align-items:center; gap:7px;
}
.user-chip .av{
  width:26px;height:26px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  color:#fff;font-size:12px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
}
.cart-btn{
  position:relative;background:var(--primary);color:#fff;border:none;
  border-radius:12px;width:44px;height:44px;font-size:20px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  box-shadow:0 3px 10px rgba(99,102,241,.35);transition:.2s;
}
.cart-btn:active{transform:scale(.93);}
.cart-badge{
  position:absolute;top:-6px;right:-6px;
  background:var(--red);color:#fff;font-size:11px;font-weight:800;
  min-width:20px;height:20px;border-radius:999px;
  display:flex;align-items:center;justify-content:center;
  border:2px solid #fff; padding:0 3px;
}

/* ── SEARCH ── */
.search-bar{padding:10px 12px 8px;background:#fff;border-bottom:1px solid var(--border);}
.search-wrap{position:relative;}
.search-wrap input{
  width:100%;padding:10px 16px 10px 40px;
  background:#f8fafc;border:1px solid var(--border);border-radius:10px;
  font-size:14px;color:var(--text);transition:.2s;
}
.search-wrap input:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
.search-wrap input::placeholder{color:#94a3b8;}
.search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:16px;color:#94a3b8;}

/* ── INFO BANNER ── */
.banner{
  background:linear-gradient(135deg,#ede9fe,#dbeafe);
  border-radius:10px; padding:10px 12px;
  display:flex; align-items:center; gap:8px;
  font-size:12px; color:#4338ca; font-weight:600;
  border:1px solid #c7d2fe;
}

/* ── BODY LAYOUT ── */
.main-layout{
  display:flex;
  height:calc(100vh - 60px);  /* header = 60px */
  overflow:hidden;
}

/* ── CHAP SIDEBAR — Kategoriyalar ── */
.cat-sidebar{
  width:76px;flex-shrink:0;
  background:#fff;border-right:1px solid var(--border);
  overflow-y:auto;scrollbar-width:none;
  display:flex;flex-direction:column;gap:2px;
  padding:8px 6px;
}
.cat-sidebar::-webkit-scrollbar{display:none;}
.cat-tab{
  display:flex;flex-direction:column;align-items:center;gap:3px;
  padding:10px 4px;border-radius:12px;cursor:pointer;border:none;
  background:transparent;color:var(--muted);transition:.18s;
  font-size:10px;font-weight:700;text-align:center;line-height:1.2;
  word-break:break-word;
}
.cat-tab .ct-icon{
  font-size:22px;width:40px;height:40px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  background:#f1f5f9;transition:.18s;
}
.cat-tab.active{color:var(--primary);}
.cat-tab.active .ct-icon{
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  color:#fff;box-shadow:0 3px 8px rgba(99,102,241,.35);
}
.cat-tab:active{transform:scale(.93);}

/* ── O'NG MAIN AREA ── */
.main-area{
  flex:1;overflow-y:auto;
  display:flex;flex-direction:column;
}
.main-area::-webkit-scrollbar{width:3px;}
.main-area::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px;}

/* ── GRID ── */
.products-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
  gap:10px; padding:10px 10px 130px;
}
@media(max-width:360px){.products-grid{grid-template-columns:1fr 1fr;}}

/* ── FLOATING CART BAR ── */
.cart-bar{
  position:fixed;bottom:0;left:76px;right:0;   /* sidebar width = 76px */
  padding:10px 12px 16px;
  background:linear-gradient(to top, rgba(241,245,249,1) 75%, rgba(241,245,249,0));
  z-index:150;
  transition:opacity .3s;
}
.cart-bar-inner{
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  border-radius:16px;
  padding:12px 16px;
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 6px 20px rgba(99,102,241,.4);
  cursor:pointer;
  transition:.2s;
}
.cart-bar-inner:active{transform:scale(.98);}
.cb-left{display:flex;align-items:center;gap:8px;}
.cb-badge{
  background:rgba(255,255,255,.22);
  border-radius:8px;padding:3px 9px;
  font-size:13px;font-weight:800;color:#fff;
}
.cb-text{font-size:13px;font-weight:700;color:rgba(255,255,255,.9);}
.cb-total{font-size:16px;font-weight:900;color:#fff;}
.cb-arrow{font-size:20px;color:rgba(255,255,255,.8);}

/* empty cart bar */
.cart-bar.empty .cart-bar-inner{
  background:linear-gradient(135deg,#94a3b8,#64748b);
  box-shadow:0 3px 10px rgba(0,0,0,.1);
}

/* ── CARD ── */
.p-card{
  background:var(--card);border-radius:var(--radius);
  border:1px solid var(--border);
  display:flex;flex-direction:column;
  transition:.18s;position:relative;overflow:hidden;
  box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.p-card:active{transform:scale(.97);}
.p-img-wrap{
  width:100%;overflow:hidden;
  background:linear-gradient(135deg,#f1f5f9,#e2e8f0);
  position:relative;flex-shrink:0;
  padding-top:100%; /* square via padding trick */
}
.p-img-wrap > *{
  position:absolute;top:0;left:0;width:100%;height:100%;
}
.p-img-wrap img{object-fit:cover;display:block;}
.p-img-placeholder{
  width:100%;height:100%;
  display:flex;align-items:center;justify-content:center;
  font-size:42px;font-weight:900;color:#fff;
  background:linear-gradient(135deg,#6366f1,#4f46e5);
}
.p-body{padding:10px 10px 12px;display:flex;flex-direction:column;gap:5px;flex:1;}
.p-name{font-size:13px;font-weight:700;color:var(--text);line-height:1.3;}
.p-kat{font-size:10px;color:var(--muted);background:#f1f5f9;padding:2px 7px;border-radius:999px;width:fit-content;}
.p-minqty{font-size:10px;color:#f59e0b;font-weight:700;background:#fffbeb;padding:2px 6px;border-radius:6px;width:fit-content;}
.p-price-block{margin-top:auto;padding-top:4px;}
.p-optom{font-size:15px;font-weight:800;color:var(--primary);}
.p-retail{font-size:11px;color:var(--muted);text-decoration:line-through;}
.p-stock{font-size:10px;color:var(--green);font-weight:600;}
.p-stock.low{color:#f59e0b;}
.add-btn{
  background:var(--primary);color:#fff;border:none;
  border-radius:0 0 var(--radius) var(--radius);
  padding:10px;font-size:13px;font-weight:700;
  cursor:pointer;width:100%;transition:.18s;
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.add-btn:active{background:var(--primary-d);}
.add-btn.added{background:var(--green);}

/* ── CART DRAWER ── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;opacity:0;pointer-events:none;transition:.3s;}
.overlay.open{opacity:1;pointer-events:all;}
.drawer{
  position:fixed;bottom:0;left:0;right:0;
  background:#fff;border-radius:24px 24px 0 0;
  z-index:201;transform:translateY(100%);transition:.35s cubic-bezier(.4,0,.2,1);
  max-height:90vh;display:flex;flex-direction:column;
}
.drawer.open{transform:translateY(0);}
.drawer-handle{width:40px;height:4px;background:#e2e8f0;border-radius:99px;margin:12px auto 4px;}
.drawer-head{padding:12px 20px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.drawer-head h3{font-size:18px;font-weight:800;}
.close-btn{background:#f1f5f9;border:none;border-radius:10px;width:34px;height:34px;font-size:18px;cursor:pointer;color:var(--muted);}
.drawer-body{overflow-y:auto;flex:1;padding:16px 20px;}
.cart-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 0;border-bottom:1px dashed #e2e8f0;
}
.cart-item:last-child{border-bottom:none;}
.ci-thumb{
  width:48px;height:48px;border-radius:10px;overflow:hidden;flex-shrink:0;
  background:linear-gradient(135deg,#ede9fe,#dbeafe);
  display:flex;align-items:center;justify-content:center;font-size:20px;
}
.ci-thumb img{width:100%;height:100%;object-fit:cover;}
.ci-info{flex:1;min-width:0;}
.ci-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ci-unit-price{font-size:11px;color:var(--muted);}
.ci-line-total{font-size:13px;font-weight:800;color:var(--primary);white-space:nowrap;}
.qty-ctrl{display:flex;align-items:center;gap:4px;flex-shrink:0;}
.qty-btn{
  width:28px;height:28px;border:1.5px solid var(--border);
  border-radius:7px;background:#f8fafc;
  font-size:16px;cursor:pointer;font-weight:700;
  display:flex;align-items:center;justify-content:center;color:var(--text);
  transition:.15s;
}
.qty-btn:active{background:#e2e8f0;}
.qty-num{font-size:14px;font-weight:800;min-width:22px;text-align:center;}
.del-btn{background:#fee2e2;border:none;border-radius:7px;width:28px;height:28px;cursor:pointer;font-size:13px;color:var(--red);flex-shrink:0;}
.empty-cart{text-align:center;padding:40px 20px;color:var(--muted);}
.empty-cart .ic{font-size:48px;margin-bottom:12px;}

/* ── CART RECEIPT HEADER ── */
.cart-summary-head{
  background:linear-gradient(135deg,#f8faff,#f0f4ff);
  border-radius:12px;padding:12px 14px;margin-bottom:12px;
  border:1px solid #e0e7ff;
}
.cart-summary-head .cs-row{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:3px;}
.cart-summary-head .cs-row b{color:var(--text);}
.cart-count-badge{
  background:var(--primary);color:#fff;border-radius:999px;
  font-size:11px;font-weight:800;padding:2px 8px;
}

/* ── CHECKOUT PANEL ── */
.checkout-panel{padding:14px 20px 24px;border-top:2px dashed #e2e8f0;}
.receipt-line{display:flex;justify-content:space-between;font-size:13px;color:var(--muted);margin-bottom:4px;}
.receipt-line.total{
  border-top:2px solid var(--text);margin-top:8px;padding-top:8px;
  font-size:18px;font-weight:900;color:var(--text);
}
.total-label{font-size:14px;color:var(--muted);font-weight:600;}
.total-sum{font-size:22px;font-weight:900;color:var(--text);}

.delivery-box{
  background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;
  padding:12px 14px;margin-bottom:12px;display:none;
}
.delivery-box.show{display:block;}
.delivery-toggle{display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:8px;}
.delivery-toggle input[type=checkbox]{width:18px;height:18px;accent-color:var(--green);cursor:pointer;}
.delivery-toggle span{font-size:14px;font-weight:700;color:#065f46;}
.delivery-badge{background:#dcfce7;color:#15803d;font-size:11px;font-weight:800;padding:2px 8px;border-radius:999px;margin-left:auto;}
#manzilWrap{margin-top:8px;display:none;}
#manzilInp{
  width:100%;padding:10px 14px;border:1px solid #bbf7d0;border-radius:10px;
  font-size:14px;background:#fff;color:var(--text);
}
#manzilInp:focus{outline:none;border-color:var(--green);}

.checkout-btn{
  width:100%;padding:15px;
  background:linear-gradient(135deg,var(--green),#059669);
  border:none;border-radius:14px;color:#fff;
  font-size:16px;font-weight:800;cursor:pointer;
  box-shadow:0 4px 14px rgba(16,185,129,.35);transition:.2s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.checkout-btn:active{transform:scale(.97);}
.checkout-btn:disabled{opacity:.6;pointer-events:none;}

/* ── SUCCESS ── */
.success-modal{
  position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;
  display:none;align-items:center;justify-content:center;padding:20px;
}
.success-modal.show{display:flex;}
.success-box{
  background:#fff;border-radius:24px;padding:36px 28px;
  text-align:center;max-width:340px;width:100%;
  animation:popIn .35s cubic-bezier(.4,0,.2,1);
}
@keyframes popIn{from{transform:scale(.8);opacity:0}to{transform:scale(1);opacity:1}}
.success-ic{font-size:60px;margin-bottom:16px;}
.success-box h2{font-size:22px;font-weight:800;margin-bottom:8px;}
.success-box p{color:var(--muted);font-size:14px;line-height:1.5;margin-bottom:24px;}
.ok-btn{
  width:100%;padding:14px;background:var(--primary);color:#fff;
  border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;
}

.logout-link{font-size:12px;color:var(--muted);text-decoration:none;font-weight:600;}
.logout-link:hover{color:var(--red);}

/* skeleton */
.skel{background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200%;animation:sk 1.2s infinite;border-radius:8px;}
@keyframes sk{0%{background-position:200%}100%{background-position:-200%}}
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
  <div style="display:flex;align-items:center;gap:10px;">
    <button class="cart-btn" onclick="openDrawer()" id="cartBtn">
      🛒
      <span class="cart-badge" id="cartBadge" style="<?= $cartCount?'':'display:none' ?>"><?= $cartCount ?></span>
    </button>
    <span class="logo-txt"><?= htmlspecialchars($site) ?></span>
  </div>
  <div class="header-right">
    <div class="user-chip">
      <div class="av"><?= mb_strtoupper(mb_substr($ism,0,1)) ?></div>
      <span><?= htmlspecialchars($ism) ?></span>
    </div>
    <a href="/auth/logout.php" class="logout-link" title="Chiqish">🚪</a>
  </div>
</div>

<!-- SEARCH (header ostida) -->
<div class="search-bar" style="padding:10px 12px 8px;">
  <div class="search-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInp" placeholder="Qidirish..." autocomplete="off" inputmode="search">
  </div>
</div>

<!-- MAIN LAYOUT: sidebar + content -->
<div class="main-layout">

  <!-- CHAP SIDEBAR — Kategoriyalar -->
  <div class="cat-sidebar" id="catSidebar">
    <?php
    $catIcons = ['🏠','🥤','🍫','🧴','🧹','📦','🍕','🥛','🧃','🍬','🧊','🍞','🌿','🐟','🥩','🍳','🧺','💊','🏮','🎁'];
    $iconIdx = 0;
    ?>
    <button class="cat-tab active" onclick="filterCat('',this)">
      <div class="ct-icon">🏠</div>
      Barchasi
    </button>
    <?php foreach($categories as $c):
      $ico = $catIcons[$iconIdx % count($catIcons)]; $iconIdx++;
    ?>
    <button class="cat-tab" onclick="filterCat(<?= json_encode($c['name']) ?>,this)">
      <div class="ct-icon"><?= $ico ?></div>
      <?= htmlspecialchars(mb_substr($c['name'],0,8)) ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- O'NG TARAF — Mahsulotlar -->
  <div class="main-area" id="mainArea">

    <!-- BANNER -->
    <div class="banner" style="margin:8px 10px;">
      <span style="font-size:18px;">🚚</span>
      <span>1.5M dan yuqorida <strong>BEPUL yetkazish!</strong></span>
    </div>

    <!-- MAHSULOTLAR GRID -->
    <div class="products-grid" id="grid">
      <?php for($i=0;$i<6;$i++): ?>
      <div class="p-card">
        <div style="width:100%;padding-top:100%;position:relative;">
          <div class="skel" style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:0;"></div>
        </div>
        <div class="p-body" style="gap:6px;">
          <div class="skel" style="height:12px;width:85%;border-radius:5px;"></div>
          <div class="skel" style="height:10px;width:55%;border-radius:5px;"></div>
          <div class="skel" style="height:15px;width:65%;border-radius:5px;margin-top:4px;"></div>
        </div>
        <div class="skel" style="height:36px;border-radius:0 0 14px 14px;"></div>
      </div>
      <?php endfor; ?>
    </div>

  </div><!-- /main-area -->
</div><!-- /main-layout -->

<!-- FLOATING CART BAR -->
<?php
  $cbClass = $cartCount ? '' : ' empty';
  $cbBadge = $cartCount ?: '0';
  $cbTotal = $cartCount ? number_format($cartTotal, 0, '.', ' ') . ' UZS' : 'Savat bo\'sh';
  $cbText  = $cartCount ? ($cartCount . ' ta mahsulot') : 'Hech narsa qo\'shilmagan';
?>
<div class="cart-bar<?= $cbClass ?>" id="cartBar" onclick="openDrawer()">
  <div class="cart-bar-inner">
    <div class="cb-left">
      <div class="cb-badge" id="cbBadge">🛒 <?= $cbBadge ?></div>
      <div class="cb-text" id="cbText"><?= htmlspecialchars($cbText) ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <div class="cb-total" id="cbTotal"><?= htmlspecialchars($cbTotal) ?></div>
      <div class="cb-arrow">›</div>
    </div>
  </div>
</div>

<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="closeDrawer()"></div>

<!-- CART DRAWER -->
<div class="drawer" id="drawer">
  <div class="drawer-handle"></div>
  <div class="drawer-head">
    <h3>🛒 Savat</h3>
    <button class="close-btn" onclick="closeDrawer()">✕</button>
  </div>
  <div class="drawer-body" id="drawerBody">
    <div class="empty-cart"><div class="ic">🛒</div><p>Savat bo'sh</p></div>
  </div>
  <div class="checkout-panel" id="checkoutPanel" style="display:none;">
    <div class="receipt-line"><span>Mahsulotlar narxi</span><span id="subtotalSum">—</span></div>
    <div class="receipt-line"><span>Yetkazish</span><span id="deliveryCost">Bepul</span></div>
    <div class="receipt-line total"><span>JAMI</span><span id="totalSum">0 UZS</span></div>

    <!-- YETKAZISH (1.5M+ bo'lsa ko'rinadi) -->
    <div class="delivery-box" id="deliveryBox" style="margin-top:10px;">
      <div class="delivery-toggle" onclick="toggleDelivery()">
        <input type="checkbox" id="deliveryCheck">
        <span>🚚 Yetkazish xizmati</span>
        <span class="delivery-badge">BEPUL</span>
      </div>
      <div id="manzilWrap">
        <input type="text" id="manzilInp" placeholder="Manzilingizni yozing...">
      </div>
    </div>

    <button class="checkout-btn" id="checkoutBtn" onclick="checkout()">
      ✅ Buyurtma berish
    </button>
  </div>
</div>

<!-- SUCCESS MODAL -->
<div class="success-modal" id="successModal">
  <div class="success-box">
    <div class="success-ic">🎉</div>
    <h2>Buyurtma qabul qilindi!</h2>
    <p id="successMsg">Tez orada siz bilan bog'lanamiz.</p>
    <button class="ok-btn" onclick="document.getElementById('successModal').classList.remove('show')">Tushunarli</button>
  </div>
</div>

<script>
const FMT = n => Number(n).toLocaleString('uz-UZ') + ' UZS';
let allProducts = [];
let debTimer;
let activeCat = 0; // 0 = barchasi

// ── Mahsulotlarni yuklash ──
async function loadProducts() {
  const fd = new FormData();
  const res = await fetch('/mijoz/api.php?action=products', {method:'POST',body:fd});
  const data = await res.json();
  allProducts = data;
  applyFilter();
}

// ── Kategoriya filtri ──
let activeCatName = ''; // '' = barchasi

function filterCat(catName, btn) {
  activeCatName = catName;
  document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  applyFilter();
}

function applyFilter() {
  const q = document.getElementById('searchInp').value.trim().toLowerCase();
  let filtered = allProducts;
  if (activeCatName) filtered = filtered.filter(p => p.kategoriya === activeCatName);
  if (q) filtered = filtered.filter(p =>
    p.name.toLowerCase().includes(q) || p.barcode === q
  );
  renderGrid(filtered);
}

// Mahsulot nomidan rang generatsiya
const GRADIENTS = [
  'linear-gradient(135deg,#6366f1,#4f46e5)',
  'linear-gradient(135deg,#10b981,#059669)',
  'linear-gradient(135deg,#f59e0b,#d97706)',
  'linear-gradient(135deg,#ef4444,#dc2626)',
  'linear-gradient(135deg,#8b5cf6,#7c3aed)',
  'linear-gradient(135deg,#06b6d4,#0891b2)',
  'linear-gradient(135deg,#f97316,#ea580c)',
  'linear-gradient(135deg,#ec4899,#db2777)',
];
function getGrad(id) { return GRADIENTS[id % GRADIENTS.length]; }
function firstLetter(name) { return (name||'?')[0].toUpperCase(); }

function renderGrid(products) {
  const grid = document.getElementById('grid');
  if (!products.length) {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#94a3b8;"><div style="font-size:48px">😕</div><p style="font-weight:600;margin-top:12px;">Mahsulot topilmadi</p></div>';
    return;
  }
  grid.innerHTML = products.map(p => {
    const showOptom = p.optom_price > 0;
    const displayPrice = showOptom ? p.optom_price : p.price;
    const stockClass   = p.quantity <= 5 ? 'low' : '';
    const minQ = p.min_qty || 1;
    const unit = p.unit || 'dona';
    const grad = getGrad(p.id);

    const imgHtml = p.image
      ? `<img src="${p.image}" alt="${escHtml(p.name)}" loading="lazy">`
      : `<div class="p-img-placeholder" style="background:${grad}">${firstLetter(p.name)}</div>`;

    return `
    <div class="p-card" id="card_${p.id}">
      <div class="p-img-wrap">${imgHtml}</div>
      <div class="p-body">
        <div class="p-name">${escHtml(p.name)}</div>
        ${p.kategoriya ? `<div class="p-kat">${escHtml(p.kategoriya)}</div>` : ''}
        ${minQ > 1 ? `<div class="p-minqty">📦 Min: ${minQ} ${unit}</div>` : ''}
        <div class="p-price-block">
          <div class="p-optom">${FMT(displayPrice)}</div>
          ${showOptom && p.price !== p.optom_price ? `<div class="p-retail">${FMT(p.price)}</div>` : ''}
        </div>
        <div class="p-stock ${stockClass}">✦ ${p.quantity} ${unit} bor</div>
      </div>
      <button class="add-btn" id="btn_${p.id}" onclick="addCart(${p.id},${displayPrice},${minQ},'${escHtml(unit)}')">
        ＋ Savatga
      </button>
    </div>`;
  }).join('');
}

function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Savatga qo'shish ──
async function addCart(id, price, minQty, unit) {
  minQty = minQty || 1;
  unit   = unit   || 'dona';
  const btn = document.getElementById('btn_'+id);
  btn.disabled = true;
  const fd = new FormData();
  fd.append('id', id); fd.append('qty', minQty);
  const res = await fetch('/mijoz/api.php?action=add_cart',{method:'POST',body:fd});
  const d = await res.json();
  if (d.status === 'ok') {
    btn.className = 'add-btn added';
    btn.textContent = '✓ Qo\'shildi';
    updateBadge(d.cart_count, d.cart_total);
    setTimeout(() => {
      btn.className = 'add-btn';
      btn.innerHTML = '＋ Savatga';
      btn.disabled = false;
    }, 1200);
    if (document.getElementById('drawer').classList.contains('open')) loadCart();
  } else {
    // Minimal cheklov xatosi
    showToast(d.message || 'Xatolik', 'err');
    btn.disabled = false;
  }
}

function updateBadge(n, total) {
  // Header badge
  const b = document.getElementById('cartBadge');
  b.textContent = n;
  b.style.display = n > 0 ? 'flex' : 'none';

  // Floating cart bar
  const bar = document.getElementById('cartBar');
  document.getElementById('cbBadge').textContent = '🛒 ' + (n || '0');
  if (n > 0) {
    bar.classList.remove('empty');
    document.getElementById('cbText').textContent = n + ' ta mahsulot';
    if (total !== undefined) {
      document.getElementById('cbTotal').textContent = FMT(total);
    }
  } else {
    bar.classList.add('empty');
    document.getElementById('cbText').textContent = 'Hech narsa qo\'shilmagan';
    document.getElementById('cbTotal').textContent = 'Savat bo\'sh';
  }
}

// ── Drawer ──
function openDrawer() {
  document.getElementById('drawer').classList.add('open');
  document.getElementById('overlay').classList.add('open');
  document.getElementById('cartBar').style.opacity = '0';
  document.getElementById('cartBar').style.pointerEvents = 'none';
  document.body.style.overflow = 'hidden';
  loadCart();
}
function closeDrawer() {
  document.getElementById('drawer').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
  document.getElementById('cartBar').style.opacity = '1';
  document.getElementById('cartBar').style.pointerEvents = '';
  document.body.style.overflow = '';
}

// ── Savatni yuklash ──
async function loadCart() {
  const res = await fetch('/mijoz/api.php?action=get_cart');
  const d = await res.json();
  const body = document.getElementById('drawerBody');
  const panel = document.getElementById('checkoutPanel');

  if (!d.items || !d.items.length) {
    body.innerHTML = '<div class="empty-cart"><div class="ic">🛒</div><p>Savat bo\'sh</p></div>';
    panel.style.display = 'none';
    updateBadge(0, 0);
    return;
  }

  // Savat sarlavhasi: necha xil mahsulot, jami dona
  const totalItems = d.items.reduce((s,i)=>s+i.qty,0);
  const uniqueItems = d.items.length;
  const summaryHead = `
    <div class="cart-summary-head">
      <div class="cs-row"><span>Mahsulotlar</span><span><b class="cart-count-badge">${uniqueItems} xil</b></span></div>
      <div class="cs-row"><span>Jami dona</span><b>${totalItems} ta</b></div>
    </div>`;

  body.innerHTML = summaryHead + d.items.map(item => {
    const minQ = item.min_qty || 1;
    const unit = item.unit   || 'dona';
    const prevQty = Math.max(minQ, item.qty - minQ);
    const nextQty = item.qty + minQ;
    const grad = getGrad(item.id);
    const thumbHtml = item.image
      ? `<img src="${item.image}" alt="">`
      : `<span style="font-size:18px;font-weight:900;color:#fff;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:${grad};border-radius:10px;">${firstLetter(item.name)}</span>`;
    return `
    <div class="cart-item" id="ci_${item.id}">
      <div class="ci-thumb">${thumbHtml}</div>
      <div class="ci-info">
        <div class="ci-name">${escHtml(item.name)}</div>
        <div class="ci-unit-price">${FMT(item.price)} / ${unit}</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
        <div class="ci-line-total">${FMT(item.price*item.qty)}</div>
        <div class="qty-ctrl">
          <button class="qty-btn" onclick="changeQty(${item.id},${prevQty},${minQ})"${item.qty<=minQ?' style="opacity:.35"':''}>−</button>
          <span class="qty-num">${item.qty}</span>
          <button class="qty-btn" onclick="changeQty(${item.id},${nextQty},${minQ},${item.stock||99})">＋</button>
          <button class="del-btn" onclick="removeItem(${item.id})">🗑</button>
        </div>
      </div>
    </div>`;
  }).join('');

  panel.style.display = 'block';
  const total = d.total;
  document.getElementById('subtotalSum').textContent = FMT(total);
  document.getElementById('totalSum').textContent    = FMT(total);
  document.getElementById('deliveryCost').textContent = total >= 1500000 ? '🎁 BEPUL' : '—';

  // Floating bar yangilash
  const totalQty = d.items.reduce((s,i)=>s+i.qty,0);
  updateBadge(totalQty, total);

  // Yetkazish
  const delivBox = document.getElementById('deliveryBox');
  if (total >= 1500000) {
    delivBox.classList.add('show');
  } else {
    delivBox.classList.remove('show');
    document.getElementById('deliveryCheck').checked = false;
    document.getElementById('manzilWrap').style.display = 'none';
  }
}

function toggleDelivery() {
  const chk = document.getElementById('deliveryCheck');
  chk.checked = !chk.checked;
  document.getElementById('manzilWrap').style.display = chk.checked ? 'block' : 'none';
}

async function changeQty(id, qty, minQ, max) {
  minQ = minQ || 1;
  if (qty < minQ) { removeItem(id); return; }
  if (qty > (max||999)) return;
  // min_qty ga karrali qilib yumalash
  qty = Math.round(qty / minQ) * minQ;
  if (qty < minQ) qty = minQ;
  const fd = new FormData();
  fd.append('id', id); fd.append('qty', qty);
  await fetch('/mijoz/api.php?action=update_qty',{method:'POST',body:fd});
  loadCart();
}

async function removeItem(id) {
  const fd = new FormData();
  fd.append('id', id);
  await fetch('/mijoz/api.php?action=remove_cart',{method:'POST',body:fd});
  loadCart();
}

async function checkout() {
  const btn = document.getElementById('checkoutBtn');
  btn.disabled = true;
  btn.textContent = '⏳ Yuborilmoqda...';

  const yetkazish = document.getElementById('deliveryCheck').checked ? 1 : 0;
  const manzil    = document.getElementById('manzilInp').value.trim();

  if (yetkazish && !manzil) {
    alert('Iltimos, manzilingizni kiriting!');
    btn.disabled = false;
    btn.innerHTML = '✅ Buyurtma berish';
    return;
  }

  const fd = new FormData();
  fd.append('yetkazish', yetkazish);
  fd.append('manzil', manzil);

  const res = await fetch('/mijoz/api.php?action=checkout',{method:'POST',body:fd});
  const d = await res.json();

  if (d.status === 'ok') {
    closeDrawer();
    const msg = yetkazish
      ? `Buyurtma №${d.sale_id}. Jami: ${FMT(d.total)}. Manzil: ${manzil}. Yetkazib beramiz!`
      : `Buyurtma №${d.sale_id}. Jami: ${FMT(d.total)}. O'zingiz olib ketasiz.`;
    document.getElementById('successMsg').textContent = msg;
    document.getElementById('successModal').classList.add('show');
    updateBadge(0, 0);
  } else {
    alert(d.message || 'Xatolik!');
  }
  btn.disabled = false;
  btn.innerHTML = '✅ Buyurtma berish';
}

// ── Toast xabar ──
function showToast(msg, type) {
  let t = document.getElementById('toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'toast';
    t.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;z-index:9999;transition:.3s;max-width:90vw;text-align:center;';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.background = type === 'err' ? '#ef4444' : '#10b981';
  t.style.color = '#fff';
  t.style.opacity = '1';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.opacity = '0'; }, 2500);
}

// ── Qidiruv ──
document.getElementById('searchInp').addEventListener('input', function() {
  clearTimeout(debTimer);
  debTimer = setTimeout(() => applyFilter(), 250);
});

// ── Boshlang'ich yuklash ──
loadProducts();
</script>
</body>
</html>
