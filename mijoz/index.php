<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mijoz') {
    header("Location: /auth/login.php"); exit;
}
require_once "../config/dokon_db.php";

$st        = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM settings WHERE id=1"));
$site      = $st['store_name'] ?? 'ELEVEN';
$ism       = $_SESSION['name'] ?? 'Mijoz';
$cart      = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cart,'qty'));
$cartTotal = 0;
foreach ($cart as $ci) $cartTotal += ($ci['price'] ?? 0) * ($ci['qty'] ?? 0);

// ── Migration: ustunlar yo'q bo'lsa qo'shish
@mysqli_query($conn,"ALTER TABLE products ADD COLUMN IF NOT EXISTS min_qty INT NOT NULL DEFAULT 1");
@mysqli_query($conn,"ALTER TABLE products ADD COLUMN IF NOT EXISTS unit VARCHAR(20) DEFAULT 'dona'");
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL");

$cats_res   = mysqli_query($conn,"SELECT id, name FROM categories ORDER BY name ASC");
$categories = [];
if ($cats_res) while ($c = mysqli_fetch_assoc($cats_res)) $categories[] = $c;

// Mahsulotlar (optom narxda) — min_qty fallback
$prods_res = mysqli_query($conn,
    "SELECT p.id, p.name, p.price, p.optom_price, p.quantity,
            IFNULL(p.unit,'dona') AS unit,
            IFNULL(p.min_qty,1)  AS min_qty,
            p.category_id,
            IFNULL(c.name,'')    AS kategoriya
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.quantity > 0
     ORDER BY p.name ASC");
$products = [];
if ($prods_res) while ($p = mysqli_fetch_assoc($prods_res)) $products[] = $p;
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title><?= htmlspecialchars($site) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
:root{
  --primary:#4f46e5;--primary-d:#3730a3;
  --green:#10b981;--red:#ef4444;
  --dark:#0f172a;--bg:#f1f5f9;
  --border:#e2e8f0;--muted:#64748b;
}
body{font-family:'Inter',sans-serif;background:var(--bg);overflow:hidden;user-select:none;}

/* HEADER */
.navbar{
  background:#fff;border-bottom:1px solid var(--border);
  padding:0 14px;height:54px;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;z-index:10;
}
.nav-left{display:flex;align-items:center;gap:10px;}
.logo{font-size:18px;font-weight:900;color:var(--primary);}
.user-badge{
  background:#f8fafc;border:1px solid var(--border);
  border-radius:999px;padding:5px 12px 5px 8px;
  font-size:12px;font-weight:700;color:var(--muted);
  display:flex;align-items:center;gap:6px;
}
.user-av{
  width:24px;height:24px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  color:#fff;font-size:11px;font-weight:900;
  display:flex;align-items:center;justify-content:center;
}
.logout-btn{
  background:#fee2e2;border:none;border-radius:8px;
  width:32px;height:32px;color:var(--red);font-size:16px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
}
.cart-top-btn{
  position:relative;
  background:var(--primary);color:#fff;border:none;
  border-radius:10px;width:40px;height:40px;font-size:18px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  box-shadow:0 3px 8px rgba(79,70,229,.3);
}
.cart-badge{
  position:absolute;top:-5px;right:-5px;
  background:var(--red);color:#fff;font-size:10px;font-weight:900;
  min-width:18px;height:18px;border-radius:999px;
  display:flex;align-items:center;justify-content:center;
  border:2px solid #fff;padding:0 2px;
}

/* MAIN LAYOUT */
.layout{display:flex;flex-direction:column;height:calc(100vh - 54px);overflow:hidden;}

/* TOP CATEGORIES BAR */
.cats-bar{
  background:#fff;border-bottom:1px solid var(--border);
  padding:8px 10px;display:flex;gap:6px;
  overflow-x:auto;scrollbar-width:none;flex-shrink:0;
}
.cats-bar::-webkit-scrollbar{display:none;}
.scat{
  display:flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:20px;cursor:pointer;border:none;
  background:#f1f5f9;transition:.15s;white-space:nowrap;flex-shrink:0;
  font-size:12px;font-weight:700;color:var(--muted);
}
.scat-ic{font-size:16px;}
.scat.active{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:#fff;box-shadow:0 3px 8px rgba(79,70,229,.3);}
.scat:active{transform:scale(.95);}

/* MAIN AREA */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;}

/* SEARCH */
.search-wrap{padding:8px 10px;background:#fff;border-bottom:1px solid var(--border);flex-shrink:0;}
.search-box{
  width:100%;height:40px;padding:0 12px 0 38px;
  background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;
  font-size:14px;font-weight:500;transition:.2s;
}
.search-box:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(79,70,229,.1);}
.search-box::placeholder{color:#94a3b8;}
.search-ic{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:15px;}
.search-rel{position:relative;}

/* BANNER */
.banner{
  margin:8px 10px 0;
  background:linear-gradient(135deg,#ede9fe,#dbeafe);
  border:1px solid #c7d2fe;border-radius:10px;
  padding:8px 12px;font-size:11px;font-weight:700;color:#4338ca;
  display:flex;align-items:center;gap:8px;flex-shrink:0;
}

/* PRODUCTS GRID */
.grid{
  flex:1;overflow-y:auto;overflow-x:hidden;
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
  gap:8px;padding:8px 10px 20px;
  align-content:start;
}
.grid::-webkit-scrollbar{width:3px;}
.grid::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px;}

/* PRODUCT CARD — sotuvchi dasturidagi kabi */
.pcard{
  background:#fff;border-radius:12px;
  border:2px solid var(--border);
  padding:10px 10px 8px;
  cursor:pointer;position:relative;
  transition:.15s;
  display:flex;flex-direction:column;
  justify-content:space-between;
  min-height:120px;
  box-shadow:0 2px 4px rgba(0,0,0,.03);
}
.pcard:active{transform:scale(.95);background:#f8fafc;}
.pcard.added{border-color:var(--green);}
.stock-badge{
  position:absolute;top:7px;right:7px;
  font-size:10px;font-weight:700;
  background:#f1f5f9;color:var(--muted);
  padding:2px 6px;border-radius:6px;
}
.stock-badge.low{background:#fff7ed;color:#f59e0b;}
.pname{
  font-weight:700;font-size:12px;color:var(--dark);
  line-height:1.3;margin-top:12px;
  overflow:hidden;display:-webkit-box;
  -webkit-line-clamp:2;-webkit-box-orient:vertical;
}
.pprice{font-size:14px;font-weight:900;color:var(--primary);margin-top:6px;}
.punit{font-size:10px;color:var(--muted);font-weight:600;}
.pminq{font-size:10px;color:#f59e0b;font-weight:700;margin-top:2px;}

/* FLOATING CART BAR — o'ng pastda */
.cart-bar{
  position:fixed;bottom:16px;right:16px;
  z-index:100;transition:opacity .25s;width:280px;
}
.cart-bar-btn{
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  border-radius:16px;padding:13px 16px;
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 8px 24px rgba(79,70,229,.45);
  cursor:pointer;transition:.15s;border:none;width:100%;
}
.cart-bar-btn:active{transform:scale(.97);}
.cart-bar-btn.empty{background:linear-gradient(135deg,#94a3b8,#64748b);box-shadow:0 3px 8px rgba(0,0,0,.15);}
.cb-left{display:flex;align-items:center;gap:8px;}
.cb-cnt{background:rgba(255,255,255,.2);border-radius:7px;padding:3px 8px;font-size:12px;font-weight:800;color:#fff;}
.cb-lbl{font-size:13px;font-weight:700;color:rgba(255,255,255,.9);}
.cb-total{font-size:14px;font-weight:900;color:#fff;}
.cb-arrow{font-size:18px;color:rgba(255,255,255,.7);margin-left:4px;}

/* OVERLAY */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;opacity:0;pointer-events:none;transition:.3s;}
.overlay.open{opacity:1;pointer-events:all;}

/* CART DRAWER */
.drawer{
  position:fixed;top:0;right:0;bottom:0;
  width:min(360px,100vw);
  background:#fff;z-index:201;
  transform:translateX(100%);transition:.3s cubic-bezier(.4,0,.2,1);
  display:flex;flex-direction:column;
  box-shadow:-4px 0 20px rgba(0,0,0,.1);
}
.drawer.open{transform:translateX(0);}
.drawer-head{
  padding:14px 16px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;
}
.drawer-head h3{font-size:17px;font-weight:800;color:var(--dark);}
.close-btn{background:#f1f5f9;border:none;border-radius:8px;width:32px;height:32px;font-size:16px;cursor:pointer;color:var(--muted);}
.drawer-items{flex:1;overflow-y:auto;padding:10px 14px;}
.drawer-items::-webkit-scrollbar{width:3px;}
.drawer-items::-webkit-scrollbar-thumb{background:#e2e8f0;}

/* CART ITEM */
.citem{
  background:#fff;border:1px solid var(--border);border-radius:10px;
  border-left:4px solid var(--primary);
  padding:10px;margin-bottom:8px;
}
.citem-top{display:flex;justify-content:space-between;margin-bottom:8px;}
.citem-name{font-weight:700;font-size:13px;color:var(--dark);line-height:1.2;flex:1;padding-right:8px;}
.citem-del{background:none;border:none;color:#94a3b8;font-size:15px;cursor:pointer;padding:0;}
.citem-del:active{color:var(--red);}
.citem-bot{display:flex;align-items:center;justify-content:space-between;}
.qty-wrap{display:flex;align-items:center;background:#f8fafc;border:1px solid var(--border);border-radius:8px;overflow:hidden;}
.qty-btn{width:30px;height:30px;background:none;border:none;font-size:17px;font-weight:700;color:var(--dark);cursor:pointer;}
.qty-btn:active{background:#e2e8f0;}
.qty-val{width:30px;text-align:center;font-size:13px;font-weight:800;border:none;border-left:1px solid var(--border);border-right:1px solid var(--border);height:30px;background:#f8fafc;pointer-events:none;}
.citem-sum{font-size:14px;font-weight:900;color:var(--primary);}

.empty-cart{text-align:center;padding:50px 20px;color:var(--muted);}
.empty-ic{font-size:48px;margin-bottom:12px;}

/* CART FOOTER */
.cart-footer{
  padding:12px 14px 20px;border-top:1px solid var(--border);
  background:#fff;flex-shrink:0;
}
.cfline{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);font-weight:600;margin-bottom:4px;}
.cftotal{
  display:flex;justify-content:space-between;align-items:baseline;
  margin:8px 0 10px;padding-top:8px;border-top:2px solid var(--dark);
}
.cftotal-lbl{font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;}
.cftotal-val{font-size:24px;font-weight:900;color:var(--dark);letter-spacing:-1px;}

/* Phone + Manzil inputs */
.contact-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;}
.contact-inp{
  width:100%;padding:13px 14px;border:2px solid var(--border);
  border-radius:12px;font-size:15px;font-weight:600;
  background:#f8fafc;color:var(--dark);
}
.contact-inp:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(79,70,229,.1);}
.contact-inp::placeholder{color:#94a3b8;font-weight:500;font-size:13px;}

/* Delivery */
.deliv-box{
  background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;
  padding:10px 12px;margin-bottom:10px;display:none;
}
.deliv-box.show{display:block;}
.deliv-row{display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px;}
.deliv-row input{width:16px;height:16px;accent-color:var(--green);}
.deliv-row span{font-size:13px;font-weight:700;color:#065f46;flex:1;}
.deliv-badge{background:#dcfce7;color:#15803d;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;}
#manzilInp{
  width:100%;padding:9px 12px;border:1px solid #bbf7d0;
  border-radius:8px;font-size:13px;background:#fff;
  display:none;margin-top:4px;
}
#manzilInp:focus{outline:none;border-color:var(--green);}

/* CHECKOUT BTN */
.checkout-btn{
  width:100%;height:52px;border-radius:12px;
  background:linear-gradient(135deg,var(--green),#059669);
  color:#fff;border:none;font-size:15px;font-weight:800;
  cursor:pointer;box-shadow:0 6px 16px rgba(16,185,129,.35);
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:.15s;
}
.checkout-btn:active{transform:scale(.97);}
.checkout-btn:disabled{opacity:.6;pointer-events:none;}

/* ORDERS MODAL */
.omodal{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;display:none;align-items:flex-end;justify-content:center;}
.omodal.show{display:flex;}
.obox{background:#fff;border-radius:20px 20px 0 0;padding:20px 18px 30px;width:100%;max-width:480px;max-height:80vh;overflow-y:auto;animation:slideup .3s ease;}
@keyframes slideup{from{transform:translateY(100%)}to{transform:translateY(0)}}
.obox-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
.obox-hd h3{font-size:17px;font-weight:800;color:var(--dark);}
.oitem{border:1.5px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:10px;}
.oitem-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.oitem-id{font-size:13px;font-weight:800;color:var(--primary);}
.oitem-date{font-size:11px;color:var(--muted);}
.oitem-status{font-size:12px;font-weight:800;padding:4px 10px;border-radius:20px;background:#f1f5f9;}
.oitem-note{font-size:11px;color:var(--muted);margin-top:4px;line-height:1.4;}
.oitem-total{font-size:15px;font-weight:900;color:var(--dark);margin-top:6px;}

/* SUCCESS MODAL */
.smodal{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;display:none;align-items:center;justify-content:center;padding:20px;}
.smodal.show{display:flex;}
.sbox{background:#fff;border-radius:20px;padding:32px 24px;text-align:center;max-width:320px;width:100%;animation:pop .3s ease;}
@keyframes pop{from{transform:scale(.8);opacity:0}to{transform:scale(1);opacity:1}}
.sbox h2{font-size:20px;font-weight:800;margin:12px 0 8px;}
.sbox p{color:var(--muted);font-size:13px;line-height:1.5;margin-bottom:20px;}
.ok-btn{width:100%;padding:12px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;margin-bottom:8px;}
.ord-btn{width:100%;padding:10px;background:#f1f5f9;color:var(--dark);border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;}

/* SKELETON */
.skel{background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200%;animation:sk 1.2s infinite;border-radius:8px;}
@keyframes sk{0%{background-position:200%}100%{background-position:-200%}}
</style>
</head>
<body>

<!-- HEADER -->
<div class="navbar">
  <div class="nav-left">
    <button class="cart-top-btn" onclick="openDrawer()" id="cartTopBtn">
      🛒<span class="cart-badge" id="cartBadge" style="<?= $cartCount?'':'display:none' ?>"><?= $cartCount ?></span>
    </button>
    <span class="logo"><?= htmlspecialchars($site) ?></span>
  </div>
  <div style="display:flex;align-items:center;gap:8px;">
    <button onclick="openOrders()" style="background:#EEF2FF;border:none;border-radius:9px;padding:6px 11px;font-size:12px;font-weight:800;color:#4318FF;cursor:pointer;">📋 Buyurtmalarim</button>
    <div class="user-badge">
      <div class="user-av"><?= mb_strtoupper(mb_substr($ism,0,1)) ?></div>
      <span><?= htmlspecialchars($ism) ?></span>
    </div>
    <a href="/auth/logout.php" class="logout-btn" title="Chiqish">⏻</a>
  </div>
</div>

<!-- MAIN LAYOUT -->
<div class="layout">

  <!-- TOP CATEGORIES BAR -->
  <div class="cats-bar">
    <?php
    $icons = ['🏠','🥤','🍫','🧴','🧹','📦','🍕','🥛','🧃','🍬','🧊','🍞','🌿','🐟','🥩','🍳','🧺','💊','🏮','🎁'];
    $ii = 0;
    ?>
    <button class="scat active" onclick="filterCat('',this)">
      <span class="scat-ic">🏠</span>Barcha
    </button>
    <?php foreach($categories as $c): $ico=$icons[$ii%count($icons)];$ii++; ?>
    <button class="scat" onclick="filterCat(<?= json_encode($c['name']) ?>,this)">
      <span class="scat-ic"><?= $ico ?></span><?= htmlspecialchars($c['name']) ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- MAIN: qidiruv + grid -->
  <div class="main">
    <div class="search-wrap">
      <div class="search-rel">
        <span class="search-ic">🔍</span>
        <input type="text" class="search-box" id="searchInp" placeholder="Mahsulot qidirish..." autocomplete="off">
      </div>
    </div>

    <div class="banner">
      🚚 <span>1 500 000 UZS dan yuqori buyurtmada <b>BEPUL yetkazish!</b></span>
    </div>

    <!-- MAHSULOTLAR -->
    <div class="grid" id="grid">
      <?php foreach($products as $p):
        $dispPrice = $p['optom_price'] > 0 ? $p['optom_price'] : $p['price'];
        $minQ = max(1,(int)$p['min_qty']);
        $unit = $p['unit'] ?: 'dona';
        $lowStock = $p['quantity'] <= 5;
      ?>
      <div class="pcard"
           data-id="<?= $p['id'] ?>"
           data-name="<?= htmlspecialchars(mb_strtolower($p['name'])) ?>"
           data-cat="<?= htmlspecialchars($p['kategoriya']) ?>"
           data-price="<?= $dispPrice ?>"
           data-minq="<?= $minQ ?>"
           data-unit="<?= htmlspecialchars($unit) ?>"
           data-stock="<?= (int)$p['quantity'] ?>"
           onclick="addToCart(this)">
        <div class="stock-badge <?= $lowStock?'low':'' ?>"><?= (int)$p['quantity'] ?></div>
        <div class="pname"><?= htmlspecialchars($p['name']) ?></div>
        <div>
          <div class="pprice"><?= number_format($dispPrice,0,'.',' ') ?> UZS</div>
          <?php if($minQ>1): ?><div class="pminq">📦 Min: <?= $minQ ?> <?= htmlspecialchars($unit) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- FLOATING CART BAR -->
<?php
$cbEmpty = $cartCount == 0;
$cbLabel = $cartCount ? $cartCount.' ta mahsulot' : 'Savat bo\'sh';
$cbSum   = $cartCount ? number_format($cartTotal,0,'.',' ').' UZS' : '';
?>
<div class="cart-bar" id="cartBar">
  <button class="cart-bar-btn <?= $cbEmpty?'empty':'' ?>" onclick="openDrawer()">
    <div class="cb-left">
      <div class="cb-cnt" id="cbCnt">🛒 <?= $cartCount ?></div>
      <div class="cb-lbl" id="cbLbl"><?= htmlspecialchars($cbLabel) ?></div>
    </div>
    <div style="display:flex;align-items:center;">
      <div class="cb-total" id="cbSum"><?= htmlspecialchars($cbSum) ?></div>
      <div class="cb-arrow">›</div>
    </div>
  </button>
</div>

<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="closeDrawer()"></div>

<!-- CART DRAWER -->
<div class="drawer" id="drawer">
  <div class="drawer-head">
    <h3>🛒 Savat</h3>
    <button class="close-btn" onclick="closeDrawer()">✕</button>
  </div>
  <div class="drawer-items" id="drawerItems">
    <div class="empty-cart"><div class="empty-ic">🛒</div><p>Savat bo'sh</p></div>
  </div>
  <div class="cart-footer" id="cartFooter" style="display:none;">
    <div class="cfline"><span>Mahsulotlar:</span><span id="cfSub">—</span></div>
    <div class="cfline"><span>Yetkazish:</span><span id="cfDeliv" style="color:var(--green);font-weight:800;">—</span></div>
    <div class="cftotal">
      <span class="cftotal-lbl">JAMI</span>
      <span class="cftotal-val" id="cfTotal">0</span>
    </div>
    <!-- Telefon + Manzil (yonma-yon, katta) -->
    <div class="contact-row">
      <input type="tel" class="contact-inp" id="phoneInp" placeholder="📞 +998 XX XXX XX XX" maxlength="20">
      <input type="text" class="contact-inp" id="addressInp" placeholder="📍 Manzil (ko'cha, uy)">
    </div>
    <!-- Yetkazish (faqat 1.5M dan yuqori) -->
    <div class="deliv-box" id="delivBox">
      <div class="deliv-row" onclick="toggleDeliv()">
        <input type="checkbox" id="delivChk">
        <span>🚚 Yetkazib berish (BEPUL)</span>
        <span class="deliv-badge">✓</span>
      </div>
    </div>
    <button class="checkout-btn" id="checkoutBtn" onclick="checkout()">
      ✅ Buyurtma berish
    </button>
  </div>
</div>

<!-- SUCCESS -->
<div class="smodal" id="smodal">
  <div class="sbox">
    <div style="font-size:52px;">🎉</div>
    <h2>Buyurtma qabul!</h2>
    <p id="smsg">Tez orada siz bilan bog'lanamiz.</p>
    <button class="ok-btn" onclick="document.getElementById('smodal').classList.remove('show')">Tushunarli</button>
    <button class="ord-btn" onclick="document.getElementById('smodal').classList.remove('show');openOrders()">📋 Buyurtmamni ko'rish</button>
  </div>
</div>

<!-- BUYURTMALARIM MODAL -->
<div class="omodal" id="omodal" onclick="if(event.target===this)closeOrders()">
  <div class="obox">
    <div class="obox-hd">
      <h3>📋 Mening Buyurtmalarim</h3>
      <button onclick="closeOrders()" style="background:#f1f5f9;border:none;border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:15px;color:#64748b">✕</button>
    </div>
    <div id="ordersBody">
      <div style="text-align:center;padding:30px;color:#94a3b8">⏳ Yuklanmoqda...</div>
    </div>
  </div>
</div>

<script>
const FMT = n => Number(n).toLocaleString('uz-UZ');
let cart = {};   // {id: {id,name,price,minQ,unit,stock,qty}}
let activeCat = '';

// ── Cart: server sessiondan o'qish
<?php if($cartCount): ?>
<?php foreach($_SESSION['cart']??[] as $pid=>$item): ?>
cart[<?= $pid ?>] = {
  id:<?= $pid ?>,
  name:<?= json_encode($item['name']) ?>,
  price:<?= (float)$item['price'] ?>,
  minQ:<?= max(1,(int)($item['min_qty']??1)) ?>,
  unit:<?= json_encode($item['unit']??'dona') ?>,
  stock:<?= (int)($item['stock']??99) ?>,
  qty:<?= (int)$item['qty'] ?>
};
<?php endforeach; ?>
<?php endif; ?>

// ── Kategoriya filtri
function filterCat(catName, btn) {
  activeCat = catName;
  document.querySelectorAll('.scat').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  doFilter();
}

function doFilter() {
  const q = document.getElementById('searchInp').value.toLowerCase().trim();
  document.querySelectorAll('.pcard').forEach(card => {
    const nm  = card.dataset.name || '';
    const cat = card.dataset.cat  || '';
    const matchQ   = !q   || nm.includes(q);
    const matchCat = !activeCat || cat === activeCat;
    card.style.display = (matchQ && matchCat) ? '' : 'none';
  });
}

document.getElementById('searchInp').addEventListener('input', ()=>doFilter());

// ── Savatga qo'shish
function addToCart(card) {
  const id    = parseInt(card.dataset.id);
  const price = parseFloat(card.dataset.price);
  const minQ  = parseInt(card.dataset.minq) || 1;
  const unit  = card.dataset.unit || 'dona';
  const stock = parseInt(card.dataset.stock) || 0;
  const name  = card.querySelector('.pname').textContent.trim();

  if (cart[id]) {
    const next = cart[id].qty + minQ;
    if (next > stock) { flashCard(card,'red'); return; }
    cart[id].qty = next;
  } else {
    cart[id] = {id,name,price,minQ,unit,stock,qty:minQ};
  }
  flashCard(card,'green');
  syncCart();
}

function flashCard(card, color) {
  card.style.borderColor = color === 'green' ? 'var(--green)' : 'var(--red)';
  setTimeout(()=>card.style.borderColor='', 700);
}

// ── Savatni server bilan sinxronlash
async function syncCart() {
  updateUI();
  // Background server sync
  const fd = new FormData();
  fd.append('cart', JSON.stringify(cart));
  fetch('/mijoz/api.php?action=sync_cart', {method:'POST',body:fd}).catch(()=>{});
}

// ── UI yangilash
function updateUI() {
  const items = Object.values(cart);
  const totalQty   = items.reduce((s,i)=>s+i.qty,0);
  const totalPrice = items.reduce((s,i)=>s+i.price*i.qty,0);

  // Header badge
  const badge = document.getElementById('cartBadge');
  badge.textContent = totalQty;
  badge.style.display = totalQty ? 'flex' : 'none';

  // Floating bar
  const barBtn = document.querySelector('.cart-bar-btn');
  document.getElementById('cbCnt').textContent = '🛒 ' + totalQty;
  if (totalQty > 0) {
    barBtn.classList.remove('empty');
    document.getElementById('cbLbl').textContent  = totalQty + ' ta mahsulot';
    document.getElementById('cbSum').textContent  = FMT(totalPrice) + ' UZS';
  } else {
    barBtn.classList.add('empty');
    document.getElementById('cbLbl').textContent = "Savat bo'sh";
    document.getElementById('cbSum').textContent = '';
  }
}

// ── Drawer
function openDrawer() {
  document.getElementById('drawer').classList.add('open');
  document.getElementById('overlay').classList.add('open');
  document.getElementById('cartBar').style.opacity = '0';
  document.getElementById('cartBar').style.pointerEvents = 'none';
  document.body.style.overflow = 'hidden';
  renderDrawer();
}
function closeDrawer() {
  document.getElementById('drawer').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
  document.getElementById('cartBar').style.opacity = '1';
  document.getElementById('cartBar').style.pointerEvents = '';
  document.body.style.overflow = '';
}

// ── Drawerni render qilish
function renderDrawer() {
  const items = Object.values(cart);
  const body  = document.getElementById('drawerItems');
  const foot  = document.getElementById('cartFooter');
  if (!items.length) {
    body.innerHTML = '<div class="empty-cart"><div class="empty-ic">🛒</div><p>Savat bo\'sh</p></div>';
    foot.style.display = 'none';
    return;
  }
  body.innerHTML = items.map(item=>{
    const sum = item.price * item.qty;
    return `<div class="citem">
      <div class="citem-top">
        <div class="citem-name">${escH(item.name)}<br>
          <small style="color:var(--muted);font-weight:600;">${FMT(item.price)} UZS / ${item.unit}</small>
        </div>
        <button class="citem-del" onclick="delItem(${item.id})">✕</button>
      </div>
      <div class="citem-bot">
        <div class="qty-wrap">
          <button class="qty-btn" onclick="changeQty(${item.id},-1)">−</button>
          <input class="qty-val" value="${item.qty}" readonly>
          <button class="qty-btn" onclick="changeQty(${item.id},+1)">+</button>
        </div>
        <div class="citem-sum">${FMT(sum)} UZS</div>
      </div>
    </div>`;
  }).join('');

  foot.style.display = 'block';
  const total = items.reduce((s,i)=>s+i.price*i.qty,0);
  document.getElementById('cfSub').textContent   = FMT(total)+' UZS';
  document.getElementById('cfTotal').textContent = FMT(total)+' UZS';

  const deliv = document.getElementById('delivBox');
  if (total >= 1500000) {
    deliv.classList.add('show');
    document.getElementById('cfDeliv').textContent = '🎁 BEPUL';
  } else {
    deliv.classList.remove('show');
    document.getElementById('delivChk').checked = false;
    document.getElementById('cfDeliv').textContent = '—';
  }
}

function changeQty(id, delta) {
  if (!cart[id]) return;
  const minQ = cart[id].minQ || 1;
  const next = cart[id].qty + delta * minQ;
  if (next <= 0) { delete cart[id]; }
  else if (next > cart[id].stock) return;
  else cart[id].qty = next;
  syncCart();
  renderDrawer();
}
function delItem(id) {
  delete cart[id];
  syncCart();
  renderDrawer();
}

function toggleDeliv() {
  const chk = document.getElementById('delivChk');
  chk.checked = !chk.checked;
  // Manzil endi yuqorida contact-row da
  if (chk.checked) {
    document.getElementById('addressInp').focus();
    document.getElementById('addressInp').placeholder = '📍 Yetkazish manzilini yozing...';
  } else {
    document.getElementById('addressInp').placeholder = '📍 Manzil (ko\'cha, uy)';
  }
}

async function checkout() {
  const btn      = document.getElementById('checkoutBtn');
  const phone    = document.getElementById('phoneInp').value.trim();
  const address  = document.getElementById('addressInp').value.trim();
  const yetkazish= document.getElementById('delivChk').checked ? 1 : 0;

  if (!phone) {
    document.getElementById('phoneInp').focus();
    document.getElementById('phoneInp').style.borderColor='var(--red)';
    setTimeout(()=>document.getElementById('phoneInp').style.borderColor='var(--border)',2000);
    return;
  }

  btn.disabled = true; btn.textContent = '⏳ Yuborilmoqda...';
  const fd = new FormData();
  fd.append('phone',     phone);
  fd.append('manzil',   address);
  fd.append('yetkazish', yetkazish);

  const res = await fetch('/mijoz/api.php?action=checkout',{method:'POST',body:fd});
  const d   = await res.json();
  if (d.status === 'ok') {
    cart = {}; syncCart(); closeDrawer();
    const msg = yetkazish
      ? `Buyurtma №${d.sale_id} qabul qilindi. Jami: ${FMT(d.total)} UZS.\nYetkazib beramiz! ⏳ Kutilmoqda...`
      : `Buyurtma №${d.sale_id} qabul qilindi. Jami: ${FMT(d.total)} UZS.\nO'zingiz olasiz. ⏳ Kutilmoqda...`;
    document.getElementById('smsg').textContent = msg;
    document.getElementById('smodal').classList.add('show');
  } else {
    alert(d.message || 'Xatolik!');
  }
  btn.disabled=false; btn.innerHTML='✅ Buyurtma berish';
}

// ── BUYURTMALARIM ──
function openOrders() {
  document.getElementById('omodal').classList.add('show');
  loadOrders();
}
function closeOrders() {
  document.getElementById('omodal').classList.remove('show');
}

async function loadOrders() {
  document.getElementById('ordersBody').innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8">⏳ Yuklanmoqda...</div>';
  const res = await fetch('/mijoz/api.php?action=my_orders');
  const d   = await res.json();
  if (!d.orders || !d.orders.length) {
    document.getElementById('ordersBody').innerHTML =
      '<div style="text-align:center;padding:40px;color:#94a3b8;font-size:13px">📭 Hali buyurtma yo\'q</div>';
    return;
  }
  document.getElementById('ordersBody').innerHTML = d.orders.map(o => {
    const dt = new Date(o.sale_date);
    const dateStr = dt.toLocaleDateString('uz-UZ',{day:'2-digit',month:'2-digit',year:'numeric'})
                  + ' ' + dt.toLocaleTimeString('uz-UZ',{hour:'2-digit',minute:'2-digit'});
    const typeIco = o.sale_type === 'yetkazish' ? '🚚' : '🏪';
    return `<div class="oitem">
      <div class="oitem-top">
        <span class="oitem-id">#${String(o.id).padStart(4,'0')} ${typeIco}</span>
        <span class="oitem-status" style="color:${o.status_color}">${o.status_label}</span>
      </div>
      <div class="oitem-date">${dateStr}</div>
      ${o.note ? `<div class="oitem-note">${escH(o.note)}</div>` : ''}
      <div class="oitem-total">${FMT(o.total_price)} UZS</div>
    </div>`;
  }).join('');
}

function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>
