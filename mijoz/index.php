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

// Avatar olish
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar MEDIUMTEXT DEFAULT NULL");
$uid      = (int)($_SESSION['user_id'] ?? 0);
$userRow  = $uid ? mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid LIMIT 1")) : null;
$userAvatar = $userRow['avatar'] ?? '';

// ── Migration: ustunlar yo'q bo'lsa qo'shish
@mysqli_query($conn,"ALTER TABLE products ADD COLUMN IF NOT EXISTS min_qty INT NOT NULL DEFAULT 1");
@mysqli_query($conn,"ALTER TABLE products ADD COLUMN IF NOT EXISTS unit VARCHAR(20) DEFAULT 'dona'");
@mysqli_query($conn,"ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL");

$cats_res   = mysqli_query($conn,"SELECT id, name FROM categories ORDER BY name ASC");
$categories = [];
if ($cats_res) while ($c = mysqli_fetch_assoc($cats_res)) $categories[] = $c;

// ── Smart emoji by keyword ──
function catEmoji($name) {
    $n = mb_strtolower($name);
    if(str_contains($n,'gazli')||str_contains($n,'ichimlik')||str_contains($n,'suv')) return '🥤';
    if(str_contains($n,'sut')||str_contains($n,'qatiq')||str_contains($n,'kefir')||str_contains($n,'pishloq')||str_contains($n,'saryog')) return '🥛';
    if(str_contains($n,'go\'sht')||str_contains($n,'gosht')||str_contains($n,'kolbasa')||str_contains($n,'tuxum')) return '🥩';
    if(str_contains($n,'sabzavot')||str_contains($n,'meva')||str_contains($n,'poliz')) return '🥦';
    if(str_contains($n,'non')||str_contains($n,'un mahsulot')||str_contains($n,'lavash')||str_contains($n,'makaron')) return '🍞';
    if(str_contains($n,'qandolat')||str_contains($n,'shirinlik')||str_contains($n,'konfet')||str_contains($n,'shokolad')) return '🍭';
    if(str_contains($n,'gigiy')||str_contains($n,'tozalik')||str_contains($n,'kir yuvish')||str_contains($n,'parfum')||str_contains($n,'shampun')) return '🧴';
    if(str_contains($n,'don')||str_contains($n,'yorma')||str_contains($n,'guruch')||str_contains($n,'grechka')) return '🌾';
    if(str_contains($n,'moy')||str_contains($n,'sous')||str_contains($n,'ketchup')||str_contains($n,'majonez')) return '🫙';
    if(str_contains($n,'muzqaymoq')||str_contains($n,'muz q')) return '🍦';
    if(str_contains($n,'dukkak')||str_contains($n,'loviya')||str_contains($n,'no\'xat')) return '🫘';
    if(str_contains($n,'yog\'')) return '🧈';
    if(str_contains($n,'un')) return '🌾';
    return '🛒';
}
// ── Smart color by keyword ──
function catColor($name) {
    $n = mb_strtolower($name);
    if(str_contains($n,'gazli')||str_contains($n,'ichimlik')) return '#4f46e5';
    if(str_contains($n,'sut'))  return '#06b6d4';
    if(str_contains($n,'gosht')||str_contains($n,'go\'sht')) return '#ef4444';
    if(str_contains($n,'sabzavot')||str_contains($n,'meva')) return '#10b981';
    if(str_contains($n,'non')||str_contains($n,'un')) return '#f59e0b';
    if(str_contains($n,'qandolat')||str_contains($n,'shirinlik')) return '#ec4899';
    if(str_contains($n,'gigiy')||str_contains($n,'tozalik')||str_contains($n,'kir')) return '#8b5cf6';
    if(str_contains($n,'don')||str_contains($n,'yorma')) return '#84cc16';
    if(str_contains($n,'moy')||str_contains($n,'sous')) return '#f97316';
    if(str_contains($n,'muzqaymoq')) return '#06b6d4';
    // fallback: sequential
    static $i=0; $cols=['#4f46e5','#10b981','#f59e0b','#8b5cf6','#06b6d4','#ec4899','#f97316']; return $cols[($i++)%count($cols)];
}
// ── Short display name (filter bar) ──
function shortName($name, $max=13) {
    // faqat birinchi so'zlar, max $max belgi
    $words = explode(' ', $name);
    $out = $words[0];
    for($i=1;$i<count($words);$i++){
        $t = $out.' '.$words[$i];
        if(mb_strlen($t) > $max) break;
        $out = $t;
    }
    return $out;
}

$catColorMap = []; $catEmojiMap = [];
foreach($categories as $_c){
    $catColorMap[$_c['name']] = catColor($_c['name']);
    $catEmojiMap[$_c['name']] = catEmoji($_c['name']);
}

// Migrations
@mysqli_query($conn,"ALTER TABLE products ADD COLUMN IF NOT EXISTS image MEDIUMTEXT DEFAULT NULL");
@mysqli_query($conn,"ALTER TABLE products ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL");

// Mahsulotlar (optom narxda) — min_qty fallback
$prods_res = mysqli_query($conn,
    "SELECT p.id, p.name, p.price, p.optom_price, p.quantity,
            IFNULL(p.unit,'dona') AS unit,
            IFNULL(p.min_qty,1)  AS min_qty,
            p.category_id,
            IFNULL(c.name,'')    AS kategoriya,
            p.image,
            p.notes
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
  --primary:#6366f1;--primary-d:#4f46e5;
  --green:#10b981;--red:#ef4444;
  --dark:#f1f5f9;--bg:#0a0f1e;
  --glass:rgba(255,255,255,.07);--glass2:rgba(255,255,255,.12);
  --border:rgba(255,255,255,.11);--muted:#94a3b8;
}
/* BG gradient blobs */
body::before{content:'';position:fixed;width:500px;height:500px;background:radial-gradient(circle,rgba(99,102,241,.22),transparent 70%);top:-160px;left:-160px;pointer-events:none;z-index:0;}
body::after{content:'';position:fixed;width:420px;height:420px;background:radial-gradient(circle,rgba(16,185,129,.16),transparent 70%);bottom:-120px;right:-120px;pointer-events:none;z-index:0;}
body{font-family:'Inter',sans-serif;background:var(--bg);overflow:hidden;user-select:none;position:relative;}

/* HEADER */
.navbar{
  background:rgba(10,15,30,.88);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-bottom:1px solid var(--border);
  padding:0 14px;height:56px;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;z-index:10;
}
.nav-left{display:flex;align-items:center;gap:10px;}
.logo{font-size:18px;font-weight:900;color:#fff;letter-spacing:-.5px;}
/* Profile avatar button */
.profile-av-btn{
  display:flex;align-items:center;gap:7px;
  background:var(--glass);border:1px solid var(--border);
  border-radius:10px;padding:5px 10px 5px 5px;
  text-decoration:none;transition:.15s;cursor:pointer;
}
.profile-av-btn:hover{background:var(--glass2);}
.profile-av{
  width:28px;height:28px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:900;color:#fff;
  overflow:hidden;flex-shrink:0;
}
.profile-av img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.profile-av-name{font-size:12px;font-weight:700;color:var(--muted);}
.logout-btn{
  background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.22);
  border-radius:8px;width:32px;height:32px;color:#fca5a5;font-size:16px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  text-decoration:none;
}
.logout-btn:hover{background:rgba(239,68,68,.22);}
.cart-top-btn{
  position:relative;
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  color:#fff;border:none;
  border-radius:10px;width:40px;height:40px;font-size:18px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 14px rgba(99,102,241,.4);
}
.cart-badge{
  position:absolute;top:-5px;right:-5px;
  background:var(--red);color:#fff;font-size:10px;font-weight:900;
  min-width:18px;height:18px;border-radius:999px;
  display:flex;align-items:center;justify-content:center;
  border:2px solid rgba(10,15,30,.8);padding:0 2px;
}
.nav-right{display:flex;align-items:center;gap:8px;}

/* MAIN LAYOUT */
.layout{display:flex;flex-direction:column;height:calc(100vh - 56px);overflow:hidden;position:relative;z-index:1;}

/* TOP CATEGORIES BAR */
.cats-bar{
  background:rgba(10,15,30,.75);
  backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  border-bottom:1px solid var(--border);
  padding:8px 10px;display:flex;gap:6px;
  overflow-x:auto;scrollbar-width:none;flex-shrink:0;
}
.cats-bar::-webkit-scrollbar{display:none;}
.scat{
  padding:6px 14px;border-radius:20px;cursor:pointer;
  border:1px solid var(--border);
  background:var(--glass);transition:.15s;white-space:nowrap;flex-shrink:0;
  font-size:12px;font-weight:700;color:var(--muted);
}
.scat.active{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:#fff;border-color:transparent;box-shadow:0 4px 12px rgba(99,102,241,.4);}
.scat:active{transform:scale(.95);}

/* MAIN AREA */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;}

/* SEARCH */
.search-wrap{
  padding:8px 10px;
  background:rgba(10,15,30,.75);
  backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  border-bottom:1px solid var(--border);
  flex-shrink:0;display:flex;align-items:center;gap:8px;
}
.search-rel{position:relative;flex:1;}
.search-box{
  width:100%;height:40px;padding:0 12px 0 38px;
  background:var(--glass);border:1.5px solid var(--border);border-radius:10px;
  font-size:14px;font-weight:500;color:#fff;transition:.2s;
}
.search-box:focus{outline:none;border-color:var(--primary);background:rgba(99,102,241,.12);box-shadow:0 0 0 3px rgba(99,102,241,.18);}
.search-box::placeholder{color:#334155;}
.search-ic{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#475569;font-size:15px;}

/* PRICE MODE TOGGLE */
.price-tog{display:flex;border:1.5px solid var(--border);border-radius:10px;overflow:hidden;flex-shrink:0;background:var(--glass);}
.ptog{
  padding:0 12px;height:40px;border:none;background:transparent;
  font-size:12px;font-weight:700;color:#475569;cursor:pointer;
  transition:.18s;line-height:1;white-space:nowrap;
}
.ptog:first-child{border-right:1.5px solid var(--border);}
.ptog.active{background:var(--primary);color:#fff;}
.ptog.active.optom-active{background:#10b981;}

/* PRODUCTS GRID */
.grid{
  flex:1;overflow-y:auto;overflow-x:hidden;
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(155px,1fr));
  grid-auto-rows:262px;
  gap:10px;padding:10px 10px 80px;
}
.grid::-webkit-scrollbar{width:3px;}
.grid::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px;}

/* PRODUCT CARD — glassmorphism */
.pcard{
  background:rgba(255,255,255,.07);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border-radius:16px;
  border:1px solid rgba(255,255,255,.1);
  cursor:pointer;position:relative;
  transition:.2s cubic-bezier(.4,0,.2,1);
  display:flex;flex-direction:column;
  overflow:hidden;height:100%;
}
.pcard:hover{
  background:rgba(255,255,255,.11);
  border-color:rgba(99,102,241,.45);
  transform:translateY(-3px);
  box-shadow:0 12px 32px rgba(0,0,0,.4),0 0 0 1px rgba(99,102,241,.25);
}
.pcard:active{transform:scale(.96);}
.pcard.in-cart{
  border-color:rgba(99,102,241,.6);
  box-shadow:0 4px 20px rgba(99,102,241,.3),0 0 0 2px rgba(99,102,241,.18);
}

/* ── IMAGE AREA ── */
.pimg{
  width:100%;height:128px;flex-shrink:0;
  background:rgba(255,255,255,.05);
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;position:relative;
}
.pimg img{
  width:100%;height:100%;
  object-fit:cover;        /* rasm to'liq va teng to'ldiriladi */
  object-position:center;
  transition:.3s;
  display:block;
}
.pcard:hover .pimg img{ transform:scale(1.06); }
.pimg-ico{
  font-size:46px;line-height:1;
  user-select:none;
  opacity:.85;
}
.pimg-cart-overlay{ display:none; }

/* Stock badge */
.stk-lbl{
  position:absolute;top:7px;right:7px;
  font-size:9px;font-weight:800;
  background:rgba(10,15,30,.82);backdrop-filter:blur(6px);
  color:var(--muted);
  padding:2px 7px;border-radius:10px;
  border:1px solid var(--border);
  z-index:2;white-space:nowrap;
}
.stk-lbl.low{background:rgba(245,158,11,.2);color:#fbbf24;border-color:rgba(245,158,11,.3);}

/* ── BODY — flex column, narx har doim pastda ── */
.pcard-body{
  padding:8px 10px 10px;
  display:flex;flex-direction:column;
  flex:1;                  /* qolgan bo'shliqni to'ldiradi */
}

/* Category tag */
.cat-tag{
  display:inline-flex;align-items:center;gap:3px;
  background:color-mix(in srgb,var(--clr,var(--primary)) 11%,transparent);
  color:var(--clr,var(--primary));
  font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:20px;
  margin-bottom:4px;
  max-width:100%;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  flex-shrink:0;
  height:20px;             /* doimiy balandlik */
  line-height:16px;
}

/* Name */
.pname{
  font-weight:700;font-size:13px;color:rgba(255,255,255,.95);
  line-height:1.35;
  overflow:hidden;display:-webkit-box;
  -webkit-line-clamp:2;-webkit-box-orient:vertical;
  height:35px;             /* 2 qator × 13px × 1.35 ≈ 35px (fixed) */
  flex-shrink:0;
  margin-bottom:3px;
}

/* Notes — 1 qator yoki bo'sh joy (balandlik doimiy) */
.pnotes{
  font-size:10px;font-weight:600;color:#94a3b8;
  overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
  height:16px;flex-shrink:0;  /* izoh bo'lmasa bo'sh qoladi */
  margin-bottom:4px;
}

/* Bottom row — har doim body ning pastida */
.pcard-bot{
  display:flex;align-items:center;justify-content:space-between;
  gap:4px;
  margin-top:auto;         /* har doim pastga */
}
.pprice-wrap{ flex:1;min-width:0; }
.pprice{font-size:14px;font-weight:900;color:#fff;line-height:1.1;}
.puzs{font-size:9px;font-weight:700;color:rgba(148,163,184,.8);}
.pminq{font-size:9px;color:#fbbf24;font-weight:700;margin-top:1px;}

/* Add button */
.padd-ico{
  width:32px;height:32px;border-radius:10px;
  background:var(--clr,var(--primary));
  color:#fff;font-size:21px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:.15s;line-height:1;
  box-shadow:0 3px 8px color-mix(in srgb,var(--clr,var(--primary)) 40%,transparent);
}
.pcard.in-cart .padd-ico{background:var(--green);}

/* FLOATING CART BAR */
.cart-bar{
  position:fixed;bottom:16px;right:16px;
  z-index:100;transition:opacity .25s;width:280px;
}
.cart-bar-btn{
  background:linear-gradient(135deg,var(--primary),var(--primary-d));
  border-radius:16px;padding:13px 16px;
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 8px 28px rgba(99,102,241,.5);
  cursor:pointer;transition:.15s;border:none;width:100%;position:relative;
}
.cart-bar-btn:active{transform:scale(.97);}
.cart-bar-btn.empty{background:rgba(255,255,255,.08);border:1px solid var(--border);box-shadow:0 3px 10px rgba(0,0,0,.3);}
.cb-left{display:flex;align-items:center;gap:8px;}
.cb-cnt{background:rgba(255,255,255,.2);border-radius:7px;padding:3px 8px;font-size:12px;font-weight:800;color:#fff;}
.cb-lbl{font-size:13px;font-weight:700;color:rgba(255,255,255,.9);}
.cb-total{font-size:14px;font-weight:900;color:#fff;}
.cb-arrow{font-size:18px;color:rgba(255,255,255,.7);margin-left:4px;}
/* Mobil FAB elementlari — desktop da yashirin */
.fab-ico{display:none;font-size:26px;line-height:1;}
.fab-num{
  display:none;
  position:absolute;top:-4px;right:-4px;
  background:var(--red);color:#fff;
  font-size:11px;font-weight:900;
  min-width:20px;height:20px;border-radius:999px;
  align-items:center;justify-content:center;
  border:2.5px solid #fff;padding:0 3px;
}
.fab-num:empty{display:none!important;}

/* OVERLAY */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);z-index:200;opacity:0;pointer-events:none;transition:.3s;}
.overlay.open{opacity:1;pointer-events:all;}

/* CART DRAWER — glassmorphism */
.drawer{
  position:fixed;top:0;right:0;bottom:0;
  width:min(360px,100vw);
  background:rgba(10,15,30,.92);
  backdrop-filter:blur(30px);-webkit-backdrop-filter:blur(30px);
  border-left:1px solid var(--border);
  z-index:201;
  transform:translateX(100%);transition:.3s cubic-bezier(.4,0,.2,1);
  display:flex;flex-direction:column;
  box-shadow:-8px 0 40px rgba(0,0,0,.55);
}
.drawer.open{transform:translateX(0);}
.drawer-head{
  padding:16px;
  border-bottom:1px solid var(--border);
  background:rgba(99,102,241,.08);
  display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;
}
.drawer-head h3{font-size:17px;font-weight:800;color:#fff;}
.close-btn{
  background:var(--glass);border:1px solid var(--border);
  border-radius:8px;width:32px;height:32px;font-size:16px;
  cursor:pointer;color:var(--muted);
}
.close-btn:hover{background:var(--glass2);color:#fff;}
.drawer-items{flex:1;overflow-y:auto;padding:10px 14px;}
.drawer-items::-webkit-scrollbar{width:3px;}
.drawer-items::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);}

/* CART ITEM */
.citem{
  background:rgba(255,255,255,.06);
  border:1px solid var(--border);border-radius:12px;
  border-left:3px solid var(--primary);
  padding:12px;margin-bottom:8px;
}
.citem-top{display:flex;justify-content:space-between;margin-bottom:8px;}
.citem-name{font-weight:700;font-size:13px;color:#fff;line-height:1.2;flex:1;padding-right:8px;}
.citem-del{background:none;border:none;color:rgba(148,163,184,.6);font-size:15px;cursor:pointer;padding:0;}
.citem-del:active{color:var(--red);}
.citem-bot{display:flex;align-items:center;justify-content:space-between;}
.qty-wrap{display:flex;align-items:center;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;overflow:hidden;}
.qty-btn{width:30px;height:30px;background:none;border:none;font-size:17px;font-weight:700;color:#fff;cursor:pointer;}
.qty-btn:active{background:rgba(255,255,255,.1);}
.qty-val{width:30px;text-align:center;font-size:13px;font-weight:800;border:none;border-left:1px solid var(--border);border-right:1px solid var(--border);height:30px;background:transparent;pointer-events:none;color:#fff;}
.citem-sum{font-size:14px;font-weight:900;color:#a5b4fc;}

.empty-cart{text-align:center;padding:50px 20px;color:var(--muted);}
.empty-ic{font-size:48px;margin-bottom:12px;}

/* CART FOOTER */
.cart-footer{
  padding:14px 16px 22px;
  border-top:1px solid var(--border);
  background:rgba(99,102,241,.05);
  flex-shrink:0;
}
.cfline{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);font-weight:600;margin-bottom:5px;}
.cftotal{
  display:flex;justify-content:space-between;align-items:baseline;
  margin:10px 0 12px;padding-top:10px;border-top:1px solid var(--border);
}
.cftotal-lbl{font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;}
.cftotal-val{font-size:26px;font-weight:900;color:#fff;letter-spacing:-1px;}

/* Contact inputs */
.contact-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;}
.contact-inp{
  width:100%;padding:12px 14px;
  background:var(--glass);
  border:1.5px solid var(--border);
  border-radius:12px;font-size:14px;font-weight:600;color:#fff;
}
.contact-inp:focus{outline:none;border-color:var(--primary);background:rgba(99,102,241,.12);box-shadow:0 0 0 3px rgba(99,102,241,.18);}
.contact-inp::placeholder{color:#334155;font-weight:500;font-size:13px;}

/* Payment */
.pay-pick{display:flex;gap:8px;margin-bottom:10px;}
.pay-opt{
  flex:1;padding:10px 8px;border-radius:12px;
  border:1.5px solid var(--border);
  background:var(--glass);cursor:pointer;
  text-align:center;font-size:13px;font-weight:700;color:var(--muted);transition:.15s;
}
.pay-opt.sel{border-color:var(--primary);background:rgba(99,102,241,.15);color:#a5b4fc;}
.pay-opt span{display:block;font-size:18px;margin-bottom:2px;}

/* Delivery */
.deliv-box{
  background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.22);
  border-radius:10px;padding:10px 12px;margin-bottom:10px;display:none;
}
.deliv-box.show{display:block;}
.deliv-row{display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px;}
.deliv-row input{width:16px;height:16px;accent-color:var(--green);}
.deliv-row span{font-size:13px;font-weight:700;color:#6ee7b7;flex:1;}
.deliv-badge{background:rgba(16,185,129,.2);color:#6ee7b7;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;}
#manzilInp{
  width:100%;padding:9px 12px;border:1px solid rgba(16,185,129,.3);
  background:rgba(16,185,129,.07);
  border-radius:8px;font-size:13px;color:#fff;display:none;margin-top:4px;
}
#manzilInp:focus{outline:none;border-color:var(--green);}

/* CHECKOUT BTN */
.checkout-btn{
  width:100%;height:52px;border-radius:12px;
  background:linear-gradient(135deg,var(--green),#059669);
  color:#fff;border:none;font-size:15px;font-weight:800;
  cursor:pointer;box-shadow:0 6px 20px rgba(16,185,129,.4);
  display:flex;align-items:center;justify-content:center;gap:8px;transition:.15s;
}
.checkout-btn:active{transform:scale(.97);}
.checkout-btn:disabled{opacity:.6;pointer-events:none;}

/* ORDERS MODAL */
.omodal{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(8px);z-index:300;display:none;align-items:flex-end;justify-content:center;}
.omodal.show{display:flex;}
.obox{
  background:rgba(10,15,30,.96);
  backdrop-filter:blur(30px);-webkit-backdrop-filter:blur(30px);
  border:1px solid var(--border);
  border-radius:20px 20px 0 0;padding:20px 18px 30px;
  width:100%;max-width:480px;max-height:80vh;overflow-y:auto;
  animation:slideup .3s ease;
}
@keyframes slideup{from{transform:translateY(100%)}to{transform:translateY(0)}}
.obox-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
.obox-hd h3{font-size:17px;font-weight:800;color:#fff;}
.oitem{border:1px solid var(--border);background:var(--glass);border-radius:12px;padding:12px 14px;margin-bottom:10px;}
.oitem-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.oitem-id{font-size:13px;font-weight:800;color:var(--primary);}
.oitem-date{font-size:11px;color:var(--muted);}
.oitem-status{font-size:12px;font-weight:800;padding:4px 10px;border-radius:20px;}
.oitem-note{font-size:11px;color:var(--muted);margin-top:4px;line-height:1.4;}
.oitem-total{font-size:15px;font-weight:900;color:#fff;margin-top:6px;}

/* SUCCESS MODAL */
.smodal{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(8px);z-index:300;display:none;align-items:center;justify-content:center;padding:20px;}
.smodal.show{display:flex;}
.sbox{
  background:rgba(10,15,30,.96);
  backdrop-filter:blur(30px);-webkit-backdrop-filter:blur(30px);
  border:1px solid var(--border);
  border-radius:24px;padding:32px 24px;text-align:center;max-width:320px;width:100%;
  animation:pop .3s ease;
}
@keyframes pop{from{transform:scale(.8);opacity:0}to{transform:scale(1);opacity:1}}
.sbox h2{font-size:20px;font-weight:800;margin:12px 0 8px;color:#fff;}
.sbox p{color:var(--muted);font-size:13px;line-height:1.5;margin-bottom:20px;}
.ok-btn{width:100%;padding:12px;background:linear-gradient(135deg,var(--primary),var(--primary-d));color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;margin-bottom:8px;}
.ord-btn{width:100%;padding:10px;background:var(--glass);border:1px solid var(--border);color:var(--muted);border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;}

/* SKELETON */
.skel{background:linear-gradient(90deg,rgba(255,255,255,.06) 25%,rgba(255,255,255,.1) 50%,rgba(255,255,255,.06) 75%);background-size:200%;animation:sk 1.2s infinite;border-radius:8px;}
@keyframes sk{0%{background-position:200%}100%{background-position:-200%}}

/* ══════════════════════════════════════════
   📱 MOBILE FIRST — telefon uchun (≤640px)
   ══════════════════════════════════════════ */
@media(max-width:640px){

  /* NAVBAR */
  .navbar{ height:52px; padding:0 10px; }
  .logo{ font-size:16px; }
  .profile-av-name{ display:none; }
  .profile-av-btn{ padding:5px; border-radius:50%; }

  /* "Buyurtmalarim" matni qisqaroq */
  button[onclick="openOrders()"]{ padding:5px 8px; font-size:11px; }

  /* KATEGORIYALAR */
  .cats-bar{ padding:6px 8px; gap:5px; }
  .scat{ padding:8px 12px; font-size:12px; border-radius:16px; }

  /* MAIN LAYOUT */
  .layout{ height:calc(100vh - 52px); }

  /* QIDIRUV */
  .search-wrap{ padding:6px 8px; }
  .search-box{ height:38px; font-size:14px; }

    /* TOVAR GRID: 2 ustun, fixed height */
  .grid{
    grid-template-columns: repeat(2, 1fr);
    grid-auto-rows: 230px;
    gap:8px;
    padding:8px 8px 80px;
  }

  /* TOVAR KARTOCHKA — mobilda ham bir xil balandlik */
  .pcard{ border-radius:14px; }
  .pimg{ height:108px; }
  .pcard-body{ padding:6px 8px 8px; }
  .pname{ font-size:12px; height:32px; }
  .pprice{ font-size:13px; }
  .padd-ico{ width:28px; height:28px; font-size:19px; border-radius:8px; }
  .cat-tag{ font-size:9px; height:18px; line-height:14px; }
  .pnotes{ font-size:9px; height:14px; }

  /* ── SAVAT FAB: o'ng tomonda yuvarlangan tugma ── */
  .cart-bar{
    right:16px; bottom:28px;
    left:auto; width:auto;
    padding:0; background:transparent;
  }
  .cart-bar-btn{
    width:62px; height:62px;
    border-radius:50%;
    padding:0;
    justify-content:center; align-items:center;
    box-shadow:0 6px 22px rgba(79,70,229,.55);
  }
  .cart-bar-btn.empty{
    box-shadow:0 4px 14px rgba(0,0,0,.22);
  }
  /* Desktop elementlarini yashir */
  .cb-left{ display:none!important; }
  .cb-total{ display:none!important; }
  .cb-arrow{ display:none!important; }
  /* Mobil FAB ikonkasini ko'rsat */
  .fab-ico{ display:flex; }
  .fab-num{ display:flex; }

  /* DRAWER: to'liq ekran */
  .drawer{ width:100%; border-radius:0; }
  .drawer-head{ padding:16px; }
  .drawer-head h3{ font-size:18px; }
  .drawer-items{ padding:10px 12px; }

  /* SAVAT ICHIDAGI TOVAR */
  .citem{ padding:12px; border-radius:12px; }
  .citem-name{ font-size:14px; }
  .qty-btn{ width:36px; height:36px; font-size:20px; }
  .qty-val{ width:36px; height:36px; font-size:14px; }
  .citem-sum{ font-size:15px; }

  /* SAVAT FOOTER */
  .cart-footer{ padding:12px 14px env(safe-area-inset-bottom, 20px); }
  .cftotal-val{ font-size:26px; }

  /* TELEFON + MANZIL: ustma-ust (stacked) */
  .contact-row{ grid-template-columns:1fr; gap:8px; }
  .contact-inp{ font-size:16px; padding:14px; }

  /* MODALS: to'liq ekran pastdan chiqadi */
  .smodal{ align-items:flex-end; padding:0; }
  .sbox{ border-radius:24px 24px 0 0; max-width:100%; padding:28px 20px env(safe-area-inset-bottom,24px); }

  /* ORDERS MODAL */
  .obox{ max-height:90vh; border-radius:24px 24px 0 0; padding:20px 16px env(safe-area-inset-bottom,24px); }

  /* CHECKOUT BTN: kattaroq */
  .checkout-btn{ height:56px; font-size:16px; border-radius:14px; }
}

/* ══════════════════════════════════════════
   📟 TABLET (641px – 1024px)
   ══════════════════════════════════════════ */
@media(min-width:641px) and (max-width:1024px){
  .grid{ grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); grid-auto-rows:262px; gap:10px; padding:10px 12px 24px; }
  .pimg{ height:128px; }
  .cart-bar{ width:320px; bottom:20px; right:20px; }
  .drawer{ width:420px; }
  .contact-row{ grid-template-columns:1fr 1fr; }
}
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
    <button onclick="openOrders()" style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.25);border-radius:9px;padding:6px 11px;font-size:12px;font-weight:800;color:#a5b4fc;cursor:pointer;">📋 Buyurtmalarim</button>
    <a href="/mijoz/profil.php" class="profile-av-btn" title="Profil">
      <div class="profile-av">
        <?php if(!empty($userAvatar)): ?>
          <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar">
        <?php else: ?>
          <?= mb_strtoupper(mb_substr($ism,0,1)) ?>
        <?php endif; ?>
      </div>
      <span class="profile-av-name"><?= htmlspecialchars($ism) ?></span>
    </a>
    <a href="/auth/logout.php" class="logout-btn" title="Chiqish"
       onclick="return confirm('Tizimdan chiqasizmi?')">⏻</a>
  </div>
</div>

<!-- MAIN LAYOUT -->
<div class="layout">

  <!-- TOP CATEGORIES BAR -->
  <div class="cats-bar">
    <button class="scat active" onclick="filterCat(0,this)">🛍️ Barcha</button>
    <?php foreach($categories as $c): ?>
    <button class="scat" onclick="filterCat(<?= (int)$c['id'] ?>,this)"
            title="<?= htmlspecialchars($c['name']) ?>">
      <?= $catEmojiMap[$c['name']] ?? '📦' ?> <?= htmlspecialchars(shortName($c['name'], 12)) ?>
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
      <div class="price-tog">
        <button class="ptog" id="btn-chakana" onclick="setPriceMode('chakana')">💰 Chakana</button>
        <button class="ptog" id="btn-optom"   onclick="setPriceMode('optom')">📦 Optom</button>
      </div>
    </div>


    <!-- MAHSULOTLAR -->
    <div class="grid" id="grid">
      <?php foreach($products as $p):
        $retailPrice = (float)$p['price'];
        $optomPrice  = (float)$p['optom_price'] > 0 ? (float)$p['optom_price'] : $retailPrice;
        $minQ  = max(1,(int)$p['min_qty']);
        $unit  = $p['unit'] ?: 'dona';
        $qty   = (int)$p['quantity'];
        $clr   = $catColorMap[$p['kategoriya']] ?? '#4f46e5';
        $lowStock = ($qty > 0 && $qty <= 5);
        $hasImg   = !empty($p['image']);
        $notes    = htmlspecialchars($p['notes'] ?? '');
        $hasOptom = (float)$p['optom_price'] > 0 && (float)$p['optom_price'] != $retailPrice;
      ?>
      <div class="pcard"
           data-id="<?= $p['id'] ?>"
           data-name="<?= htmlspecialchars(mb_strtolower($p['name'])) ?>"
           data-cat="<?= (int)$p['category_id'] ?>"
           data-price="<?= $retailPrice ?>"
           data-retail="<?= $retailPrice ?>"
           data-optom="<?= $optomPrice ?>"
           data-minq="<?= $minQ ?>"
           data-unit="<?= htmlspecialchars($unit) ?>"
           data-stock="<?= $qty ?>"
           onclick="addToCart(this)"
           style="--clr:<?= $clr ?>">

        <!-- IMAGE AREA -->
        <div class="pimg">
          <?php if($hasImg): ?>
            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
          <?php endif; ?>
          <div class="pimg-cart-overlay">✓</div>
        </div>

        <!-- Zaxira belgisi -->
        <span class="stk-lbl <?= $lowStock?'low':'' ?>"><?= $lowStock ? '⚠️ '.$qty.' '.$unit : $qty.' '.$unit ?></span>

        <!-- BODY -->
        <div class="pcard-body">
          <?php if($p['kategoriya']): ?>
          <div class="cat-tag"><?= $catEmojiMap[$p['kategoriya']] ?? '' ?> <?= htmlspecialchars(shortName($p['kategoriya'], 14)) ?></div>
          <?php endif; ?>
          <div class="pname"><?= htmlspecialchars($p['name']) ?></div>
          <div class="pnotes"><?= $notes ? '📦 '.$notes : '' ?></div>
          <div class="pcard-bot">
            <div class="pprice-wrap">
              <div class="pprice" data-retail-txt="<?= number_format($retailPrice,0,'.',' ') ?>" data-optom-txt="<?= number_format($optomPrice,0,'.',' ') ?>">
                <?= number_format($retailPrice,0,'.',' ') ?> <span class="puzs">UZS</span>
              </div>
              <?php if($hasOptom): ?>
              <div class="poptom-lbl" style="display:none;font-size:9px;color:#10b981;font-weight:700;">📦 Optom narx</div>
              <?php endif; ?>
              <?php if($minQ>1): ?><div class="pminq">min <?= $minQ ?> <?= $unit ?></div><?php endif; ?>
            </div>
            <div class="padd-ico">+</div>
          </div>
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
    <!-- Mobil FAB uchun -->
    <span class="fab-ico">🛒</span>
    <span class="fab-num" id="fabNum"><?= $cartCount ?: '' ?></span>
    <!-- Desktop uchun -->
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
    <!-- To'lov usuli -->
    <div class="pay-pick">
      <div class="pay-opt sel" id="payNaqd" onclick="pickPay('naqd')">
        <span>💵</span>Naqd pul
      </div>
      <div class="pay-opt" id="payKarta" onclick="pickPay('karta')">
        <span>💳</span>Karta
      </div>
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
let activeCat = 0;
let priceMode = localStorage.getItem('priceMode') || 'chakana';

// ── Narx rejimi (Chakana / Optom)
function setPriceMode(mode, force=false) {
  const hasCart = Object.keys(cart).length > 0;
  if (!force && hasCart && mode !== priceMode) {
    if (!confirm('Narx rejimi o\'zgartirilsa savat tozalanadi. Davom etasizmi?')) return;
    cart = {};
  }
  priceMode = mode;
  localStorage.setItem('priceMode', mode);
  // Toggle ko'rinishi
  document.getElementById('btn-chakana').classList.toggle('active', mode==='chakana');
  document.getElementById('btn-optom').classList.toggle('active', mode==='optom');
  document.getElementById('btn-optom').classList.toggle('optom-active', mode==='optom');
  // Barcha kartalar narxini yangilash
  document.querySelectorAll('.pcard').forEach(card => {
    const retail = parseFloat(card.dataset.retail) || 0;
    const optom  = parseFloat(card.dataset.optom)  || retail;
    const price  = mode === 'optom' ? optom : retail;
    card.dataset.price = price;
    const pEl = card.querySelector('.pprice');
    if (pEl) {
      const txt = mode==='optom' ? pEl.dataset.optomTxt : pEl.dataset.retailTxt;
      pEl.innerHTML = txt + ' <span class="puzs">UZS</span>';
    }
    const optomLbl = card.querySelector('.poptom-lbl');
    if (optomLbl) optomLbl.style.display = mode==='optom' ? 'block' : 'none';
  });
  if (hasCart && !force) { syncCart(); }
}

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

// ── Sahifa yuklanganida narx rejimini tiklash
setPriceMode(priceMode, true);

// ── Kategoriya filtri (ID bilan — sotuvchi kabi)
function filterCat(catId, btn) {
  activeCat = parseInt(catId) || 0;
  document.querySelectorAll('.scat').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  doFilter();
}

function doFilter() {
  const q = document.getElementById('searchInp').value.toLowerCase().trim();
  document.querySelectorAll('.pcard').forEach(card => {
    const nm     = card.dataset.name || '';
    const catId  = parseInt(card.dataset.cat) || 0;
    const matchQ   = !q         || nm.includes(q);
    const matchCat = !activeCat || catId === activeCat;
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

  if (stock <= 0) return;

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
  if(color==='green'){
    card.classList.add('in-cart');
    const ico = card.querySelector('.padd-ico');
    if(ico){ ico.textContent='✓'; setTimeout(()=>{ if(cart[parseInt(card.dataset.id)]) ico.textContent='✓'; else ico.textContent='+'; },600); }
  } else {
    card.style.borderColor='var(--red)';
    const ico = card.querySelector('.padd-ico');
    if(ico){ ico.textContent='!'; setTimeout(()=>{ ico.textContent='+'; card.style.borderColor=''; },700); }
  }
}

// Savat yangilanganda kartochkalarni belgilash
function markCartCards(){
  document.querySelectorAll('.pcard').forEach(c=>{
    const id = parseInt(c.dataset.id);
    const ico = c.querySelector('.padd-ico');
    if(cart[id]){
      c.classList.add('in-cart');
      if(ico) ico.textContent='✓';
    } else {
      c.classList.remove('in-cart');
      if(ico) ico.textContent='+';
    }
  });
}

// ── Savatni server bilan sinxronlash
async function syncCart() {
  updateUI();
  markCartCards();
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
  // Mobil FAB badge
  const fabNum = document.getElementById('fabNum');
  if(fabNum) fabNum.textContent = totalQty > 0 ? totalQty : '';
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

let selectedPayMethod = 'naqd'; // default

function pickPay(method) {
  selectedPayMethod = method;
  document.getElementById('payNaqd').classList.toggle('sel', method === 'naqd');
  document.getElementById('payKarta').classList.toggle('sel', method === 'karta');
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
  fd.append('phone',          phone);
  fd.append('manzil',         address);
  fd.append('yetkazish',      yetkazish);
  fd.append('payment_method', selectedPayMethod);
  fd.append('price_mode',     priceMode);

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

// Sahifa ochilganda savatdagilarni belgilash
markCartCards();
</script>
</body>
</html>
