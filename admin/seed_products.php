<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/admin_check.php";
require_once "../config/dokon_db.php";

// Bir martalik migration
@mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS manufacture_date DATE DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS expiry_date DATE DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS min_qty INT NOT NULL DEFAULT 1");
@mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS image MEDIUMTEXT DEFAULT NULL");

$added = 0; $skipped = 0; $errors = [];

// ═══ KATEGORIYALAR ═══
$cats_data = [
    "Ichimliklar","Un mahsulotlari","Sut mahsulotlari",
    "Go'sht mahsulotlari","Sabzavot va meva","Qandolat",
    "Gigiena va tozalik","Don va yorma","Moy va souslar","Muzqaymoq"
];

$catMap = [];
foreach ($cats_data as $cname) {
    $cn = mysqli_real_escape_string($conn, $cname);
    $r  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM categories WHERE name='$cn' LIMIT 1"));
    if ($r) {
        $catMap[$cname] = (int)$r['id'];
    } else {
        if (!mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$cn')")) {
            $nid_r = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(MAX(id),0)+1 AS x FROM categories"));
            $nid   = (int)$nid_r['x'];
            mysqli_query($conn, "INSERT INTO categories (id, name) VALUES ($nid, '$cn')");
            $catMap[$cname] = $nid;
        } else {
            $lid = mysqli_insert_id($conn);
            if ($lid > 0) {
                $catMap[$cname] = $lid;
            } else {
                $r2 = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id FROM categories WHERE name='$cn' LIMIT 1"));
                $catMap[$cname] = $r2 ? (int)$r2['id'] : 1;
            }
        }
    }
}

// ═══ MAHSULOT QO'SHISH FUNKSIYASI ═══
function addP($conn, &$added, &$skipped, &$errors, $catMap, $catName, $name, $pp, $op, $sp, $qty, $unit='dona', $minq=1, $shelf=null) {
    $n   = mysqli_real_escape_string($conn, $name);
    $chk = mysqli_query($conn, "SELECT id FROM products WHERE name='$n' LIMIT 1");
    if ($chk && mysqli_num_rows($chk) > 0) { $skipped++; return; }

    $cat_id = $catMap[$catName] ?? 1;
    $mfdate = date('Y-m-d');
    $exdate = $shelf ? "'" . date('Y-m-d', strtotime("+{$shelf} days")) . "'" : "NULL";

    $sql = "INSERT INTO products (category_id, name, purchase_price, optom_price, price, quantity, unit, min_qty, manufacture_date, expiry_date)
            VALUES ($cat_id, '$n', $pp, $op, $sp, $qty, '$unit', $minq, '$mfdate', $exdate)";
    if (mysqli_query($conn, $sql)) { $added++; return; }

    $nid = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(MAX(id),0)+1 AS x FROM products"))['x'];
    $sql2= "INSERT INTO products (id, category_id, name, purchase_price, optom_price, price, quantity, unit, min_qty, manufacture_date, expiry_date)
            VALUES ($nid, $cat_id, '$n', $pp, $op, $sp, $qty, '$unit', $minq, '$mfdate', $exdate)";
    if (mysqli_query($conn, $sql2)) { $added++; }
    else { $errors[] = "$name: ".mysqli_error($conn); $skipped++; }
}

// ═══ MAHSULOTLAR ═══

// 🥤 Ichimliklar
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Coca-Cola 1L",       5500, 6500, 8000,  50,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Coca-Cola 2L",       9000,10500,13000,  40,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Pepsi 1L",           5000, 6000, 7500,  45,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Fanta Apelsin 1L",   5500, 6500, 8000,  40,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Sprite 1L",          5500, 6500, 8000,  35,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","RedBull 0.25L",     11000,13000,16000,  20,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Aqua 0.5L",          1500, 1800, 2500,  80,'dona',1,730);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Aqua 1.5L",          3000, 3500, 5000,  60,'dona',1,730);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Lipton Ice Tea 1L",  6000, 7000, 9000,  30,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Ichimliklar","Choy Lipton 100p",  15000,18000,22000,  20,'dona',1,730);

// 🌾 Un mahsulotlari
addP($conn,$added,$skipped,$errors,$catMap,"Un mahsulotlari","Non (Oq non)",    2500, 3000, 4000, 100,'dona',1,3);
addP($conn,$added,$skipped,$errors,$catMap,"Un mahsulotlari","Yoglik non",      3500, 4000, 5500,  80,'dona',1,5);
addP($conn,$added,$skipped,$errors,$catMap,"Un mahsulotlari","Lavash",          4000, 4800, 6000,  60,'dona',1,7);
addP($conn,$added,$skipped,$errors,$catMap,"Un mahsulotlari","Un premium 2kg", 10000,11500,14000,  30,'dona',1,180);
addP($conn,$added,$skipped,$errors,$catMap,"Un mahsulotlari","Makaron 400g",    7000, 8000,10000,  40,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Un mahsulotlari","Suxari 300g",     6000, 7000, 9000,  25,'dona',1,180);

// 🥛 Sut mahsulotlari
addP($conn,$added,$skipped,$errors,$catMap,"Sut mahsulotlari","Sut 1L (paster.)", 7000, 8000,10000, 40,'dona',1,10);
addP($conn,$added,$skipped,$errors,$catMap,"Sut mahsulotlari","Kefir 1L",          6500, 7500, 9500, 35,'dona',1,14);
addP($conn,$added,$skipped,$errors,$catMap,"Sut mahsulotlari","Qatiq 500g",         5000, 6000, 7500, 30,'dona',1,7);
addP($conn,$added,$skipped,$errors,$catMap,"Sut mahsulotlari","Saryog 200g",       12000,14000,18000, 25,'dona',1,30);
addP($conn,$added,$skipped,$errors,$catMap,"Sut mahsulotlari","Pishloq 1kg",       55000,65000,80000, 10,'kg',  1,60);
addP($conn,$added,$skipped,$errors,$catMap,"Sut mahsulotlari","Qaymoq 400g",        8000, 9500,12000, 20,'dona',1,14);

// 🥩 Go'sht
addP($conn,$added,$skipped,$errors,$catMap,"Go'sht mahsulotlari","Mol go'shti 1kg", 60000,70000,85000, 15,'kg',  1,5);
addP($conn,$added,$skipped,$errors,$catMap,"Go'sht mahsulotlari","Tovuq 1kg",        25000,30000,38000, 20,'kg',  1,7);
addP($conn,$added,$skipped,$errors,$catMap,"Go'sht mahsulotlari","Kolbasa 500g",     20000,23000,29000, 15,'dona',1,30);
addP($conn,$added,$skipped,$errors,$catMap,"Go'sht mahsulotlari","Tuxum 10ta",       15000,17000,22000, 30,'dona',1,30);

// 🥦 Sabzavot va meva
addP($conn,$added,$skipped,$errors,$catMap,"Sabzavot va meva","Kartoshka 1kg",  4000, 4500, 6000, 50,'kg',1,60);
addP($conn,$added,$skipped,$errors,$catMap,"Sabzavot va meva","Piyoz 1kg",      3000, 3500, 5000, 40,'kg',1,60);
addP($conn,$added,$skipped,$errors,$catMap,"Sabzavot va meva","Sabzi 1kg",      4000, 4800, 6500, 35,'kg',1,30);
addP($conn,$added,$skipped,$errors,$catMap,"Sabzavot va meva","Pomidor 1kg",    6000, 7000, 9000, 30,'kg',1,14);
addP($conn,$added,$skipped,$errors,$catMap,"Sabzavot va meva","Banan 1kg",     10000,12000,15000, 20,'kg',1,14);
addP($conn,$added,$skipped,$errors,$catMap,"Sabzavot va meva","Olma 1kg",       7000, 8500,11000, 25,'kg',1,30);

// 🍭 Qandolat
addP($conn,$added,$skipped,$errors,$catMap,"Qandolat","Shakar 1kg",            8000, 9000,11000, 50,'kg',  1,730);
addP($conn,$added,$skipped,$errors,$catMap,"Qandolat","Shokolad Milka 90g",    9000,10500,13000, 30,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Qandolat","Pechene Barakli 500g",  8000, 9500,12000, 25,'dona',1,180);
addP($conn,$added,$skipped,$errors,$catMap,"Qandolat","Konfet 1kg",           22000,26000,32000, 15,'kg',  1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Qandolat","Wafer Kremli 160g",     5500, 6500, 8500, 30,'dona',1,180);

// 🧴 Gigiena
addP($conn,$added,$skipped,$errors,$catMap,"Gigiena va tozalik","Tish pastasi Blend-a-med", 8000, 9500,12000, 25,'dona',1,730);
addP($conn,$added,$skipped,$errors,$catMap,"Gigiena va tozalik","Shampun H&S 400ml",       22000,26000,32000, 15,'dona',1,730);
addP($conn,$added,$skipped,$errors,$catMap,"Gigiena va tozalik","Sovun Safeguard 90g",      5000, 6000, 7500, 40,'dona',1,730);
addP($conn,$added,$skipped,$errors,$catMap,"Gigiena va tozalik","Dezodorant Rexona",        12000,14000,18000, 20,'dona',1,730);
addP($conn,$added,$skipped,$errors,$catMap,"Gigiena va tozalik","Tualet qog'ozi 4ta",       9000,10500,13000, 30,'dona',1,730);

// 🌾 Don va yorma
addP($conn,$added,$skipped,$errors,$catMap,"Don va yorma","Guruch 1kg",   8000, 9500,12000, 40,'kg',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Don va yorma","Loviya 1kg",   7000, 8500,11000, 25,'kg',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Don va yorma","No'xat 1kg",   8500, 9800,13000, 20,'kg',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Don va yorma","Grechka 1kg", 10000,12000,15000, 20,'kg',1,365);

// 🫙 Moy va souslar
addP($conn,$added,$skipped,$errors,$catMap,"Moy va souslar","O'simlik moyi 1L",   14000,16000,20000, 30,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Moy va souslar","Ketchup Heinz 340g", 12000,14000,18000, 20,'dona',1,365);
addP($conn,$added,$skipped,$errors,$catMap,"Moy va souslar","Majonez Ryaba 400g",  9000,10500,13000, 25,'dona',1,180);
addP($conn,$added,$skipped,$errors,$catMap,"Moy va souslar","Tuz 1kg",             2000, 2500, 3500, 50,'dona',1,null);

// 🍦 Muzqaymoq
addP($conn,$added,$skipped,$errors,$catMap,"Muzqaymoq","Inshoot 90g",         5000, 6000, 7500, 20,'dona',1,180);
addP($conn,$added,$skipped,$errors,$catMap,"Muzqaymoq","Plombir 100g",        4500, 5500, 7000, 20,'dona',1,180);
addP($conn,$added,$skipped,$errors,$catMap,"Muzqaymoq","Magnum Almond 86g",  12000,14000,18000, 15,'dona',1,180);

?>
<!DOCTYPE html>
<html lang="uz">
<head><meta charset="UTF-8"><title>Seed Natijasi</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:#fff;border-radius:20px;padding:36px 32px;max-width:520px;width:100%;box-shadow:0 4px 20px rgba(0,0,0,.08);}
h2{font-size:22px;font-weight:800;color:#0f172a;margin-bottom:20px;}
.stat{display:flex;gap:12px;margin-bottom:24px;}
.st-box{flex:1;border-radius:14px;padding:16px;text-align:center;}
.st-num{font-size:36px;font-weight:900;line-height:1;}
.st-lbl{font-size:13px;font-weight:600;margin-top:4px;opacity:.7;}
.errors{background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px;margin-bottom:20px;font-size:13px;color:#dc2626;}
.errors ul{margin:8px 0 0 16px;}
.btn{display:inline-block;padding:12px 24px;background:#4f46e5;color:#fff;border-radius:12px;font-weight:700;text-decoration:none;font-size:15px;transition:.2s;}
.btn:hover{background:#4338ca;}
.btn-g{background:#10b981;margin-left:8px;}
.btn-g:hover{background:#059669;}
</style>
</head>
<body>
<div class="card">
    <h2>🌱 Seed Natijasi</h2>
    <div class="stat">
        <div class="st-box" style="background:#ecfdf5;color:#059669;">
            <div class="st-num"><?= $added ?></div>
            <div class="st-lbl">✅ Qo'shildi</div>
        </div>
        <div class="st-box" style="background:#f8fafc;color:#64748b;">
            <div class="st-num"><?= $skipped ?></div>
            <div class="st-lbl">⏭️ Bor edi</div>
        </div>
        <div class="st-box" style="background:#fef2f2;color:#dc2626;">
            <div class="st-num"><?= count($errors) ?></div>
            <div class="st-lbl">❌ Xato</div>
        </div>
    </div>
    <?php if($errors): ?>
    <div class="errors"><b>Xatolar:</b><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <a href="Mahsulotlar/index.php" class="btn">📦 Omborga o'tish</a>
    <a href="dashboard.php" class="btn btn-g">🏠 Bosh sahifa</a>
</div>
</body>
</html>
