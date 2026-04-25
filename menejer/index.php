<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/menejer_check.php";
require_once "../config/dokon_db.php";

@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'tolandan'");
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS note TEXT DEFAULT NULL");
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL");
@mysqli_query($conn,"CREATE TABLE IF NOT EXISTS stock_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    cost_price DECIMAL(10,2) DEFAULT 0,
    supplier VARCHAR(100) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$today = date('Y-m-d');

// ── STATISTIKA — alohida so'rovlar ──
$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM sales WHERE source='mijoz' AND status='yangi'"));
$yangi_cnt = (int)($tmp ? $tmp['c'] : 0);

$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c, COALESCE(SUM(total_price),0) AS s FROM sales WHERE source='mijoz' AND DATE(sale_date)='$today'"));
$bugun_cnt   = (int)($tmp ? $tmp['c'] : 0);
$bugun_summa = (float)($tmp ? $tmp['s'] : 0);

$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM products WHERE quantity<=5 AND quantity>0"));
$low_cnt = (int)($tmp ? $tmp['c'] : 0);

$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM products WHERE quantity=0"));
$zero_cnt = (int)($tmp ? $tmp['c'] : 0);

// ── YANGI BUYURTMALAR (oxirgi 5) ──
$orders_q = mysqli_query($conn,"
    SELECT s.id, s.sale_date, s.total_price, s.note, s.sale_type,
           u.name AS mijoz_nomi
    FROM sales s
    LEFT JOIN users u ON s.user_id=u.id
    WHERE s.source='mijoz' AND s.status='yangi'
    ORDER BY s.id DESC LIMIT 5
");

// ── TUGAYOTGAN TOVARLAR ──
$low_q = mysqli_query($conn,"
    SELECT name, quantity, IFNULL(unit,'dona') AS unit
    FROM products WHERE quantity<=5 AND quantity>0
    ORDER BY quantity ASC LIMIT 6
");

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard | Menejer</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',-apple-system,sans-serif;background:#F4F7FE;color:#1B2559;min-height:100vh}
.navbar{height:62px;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 28px;border-bottom:1px solid #E9EDF7;position:sticky;top:0;z-index:90;box-shadow:0 2px 8px rgba(17,28,68,.04)}
.nav-title{font-size:18px;font-weight:800;color:#111C44}
.nav-sub{font-size:12px;color:#A3AED0;margin-top:1px}
.user-pill{background:#F4F7FE;padding:5px 14px 5px 5px;border-radius:30px;display:flex;align-items:center;gap:9px;font-weight:700;color:#111C44;font-size:13px}
.u-av{width:30px;height:30px;border-radius:50%;background:#4318FF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.main{padding:22px 28px;max-width:1200px}

/* STATS — 4 ta kichkina karta */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.scard{background:#fff;border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 10px rgba(17,28,68,.05)}
.s-ico{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.ic-blue{background:#EEF2FF}
.ic-green{background:#E8F9F1}
.ic-orange{background:#FFF4E5}
.ic-red{background:#FEECEF}
.s-val{font-size:22px;font-weight:800;color:#111C44;line-height:1.1}
.s-lbl{font-size:11px;color:#A3AED0;font-weight:600;margin-top:2px}

/* ASOSIY GRID */
.grid2{display:grid;grid-template-columns:1.7fr 1fr;gap:16px}
.card{background:#fff;border-radius:14px;padding:18px 20px;box-shadow:0 2px 10px rgba(17,28,68,.05)}
.card-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.card-title{font-size:14px;font-weight:800;color:#111C44}
.card-sub{font-size:11px;color:#A3AED0;margin-top:2px}

/* TABLE */
.mtable{width:100%;border-collapse:collapse}
.mtable th{font-size:11px;font-weight:700;color:#A3AED0;text-transform:uppercase;letter-spacing:.4px;padding:0 10px 8px;text-align:left}
.mtable td{padding:9px 10px;font-size:13px;font-weight:600;color:#111C44;border-bottom:1px solid #F4F7FE}
.mtable tr:last-child td{border:none}
.bid{color:#4318FF;font-weight:800}

/* BADGE */
.bs{padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700}
.bs-y{background:#FFF4E5;color:#FFB547}
.bs-p{background:#E8F9F1;color:#05CD99}

/* LOW STOCK */
.ls-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F4F7FE}
.ls-row:last-child{border:none}
.ls-name{font-size:13px;font-weight:700;color:#111C44}
.ls-qty{font-size:12px;font-weight:800;padding:3px 10px;border-radius:20px}
.lq-warn{background:#FFF4E5;color:#FFB547}
.lq-err{background:#FEECEF;color:#EE5D50}

/* BUGUNGI SAVDO BANNER */
.banner{background:linear-gradient(135deg,#4318FF,#7551FF);border-radius:14px;padding:18px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.b-label{font-size:12px;font-weight:600;opacity:.8;margin-bottom:4px}
.b-val{font-size:26px;font-weight:900}
.b-meta{font-size:12px;opacity:.7;margin-top:3px}

.btn-sm{display:inline-flex;align-items:center;gap:5px;background:#4318FF;color:#fff;border:none;padding:7px 14px;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:.15s}
.btn-sm:hover{background:#3311cc;color:#fff}
.empty{text-align:center;padding:24px;color:#A3AED0;font-size:13px}
@media(max-width:860px){.stats{grid-template-columns:repeat(2,1fr)}.grid2{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="navbar">
    <div>
        <div class="nav-title">📊 Boshqaruv Paneli</div>
        <div class="nav-sub"><?= date('d.m.Y') ?> · Menejer</div>
    </div>
    <div class="user-pill">
        <div class="u-av"><?= strtoupper(substr($_SESSION['name']??'M',0,1)) ?></div>
        <?= htmlspecialchars(explode(' ',$_SESSION['name']??'Menejer')[0]) ?>
    </div>
</div>

<div class="main">

    <!-- 4 ta stat -->
    <div class="stats">
        <div class="scard">
            <div class="s-ico ic-blue">📋</div>
            <div>
                <div class="s-val" style="color:#4318FF"><?= $yangi_cnt ?></div>
                <div class="s-lbl">Yangi buyurtma</div>
            </div>
        </div>
        <div class="scard">
            <div class="s-ico ic-green">📦</div>
            <div>
                <div class="s-val" style="color:#05CD99"><?= $bugun_cnt ?></div>
                <div class="s-lbl">Bugungi buyurtma</div>
            </div>
        </div>
        <div class="scard">
            <div class="s-ico ic-orange">⚠️</div>
            <div>
                <div class="s-val" style="color:#FFB547"><?= $low_cnt ?></div>
                <div class="s-lbl">Tugayotgan tovar</div>
            </div>
        </div>
        <div class="scard">
            <div class="s-ico ic-red">🚫</div>
            <div>
                <div class="s-val" style="color:#EE5D50"><?= $zero_cnt ?></div>
                <div class="s-lbl">Tugagan tovar</div>
            </div>
        </div>
    </div>

    <!-- Bugungi savdo banner -->
    <div class="banner">
        <div>
            <div class="b-label">Bugungi mijoz savdosi</div>
            <div class="b-val"><?= number_format($bugun_summa,0,'.',' ') ?> <span style="font-size:16px;font-weight:600">UZS</span></div>
            <div class="b-meta"><?= $bugun_cnt ?> ta buyurtma · <?= date('d F Y') ?></div>
        </div>
        <div style="font-size:48px;opacity:.4">💰</div>
    </div>

    <!-- Asosiy ikki ustun -->
    <div class="grid2">

        <!-- YANGI BUYURTMALAR -->
        <div class="card">
            <div class="card-hd">
                <div>
                    <div class="card-title">🛒 Yangi Buyurtmalar</div>
                    <div class="card-sub">Mijozlardan kelgan, tasdiqlash kutilmoqda</div>
                </div>
                <a href="/menejer/buyurtmalar.php" class="btn-sm">Barchasi →</a>
            </div>

            <?php if($orders_q && mysqli_num_rows($orders_q)>0): ?>
            <table class="mtable">
                <thead>
                    <tr><th>#</th><th>Mijoz</th><th>Summa</th><th>Turi</th><th>Vaqt</th></tr>
                </thead>
                <tbody>
                <?php while($o=mysqli_fetch_assoc($orders_q)): ?>
                <tr>
                    <td class="bid">#<?= str_pad($o['id'],4,'0',STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($o['mijoz_nomi']??'—') ?></td>
                    <td style="font-weight:800"><?= number_format($o['total_price'],0,'.',' ') ?> <small style="color:#A3AED0">UZS</small></td>
                    <td><span class="bs <?= $o['sale_type']==='yetkazish'?'bs-y':'bs-p' ?>"><?= $o['sale_type']==='yetkazish'?'🚚 Yetkazish':'🏪 Olish' ?></span></td>
                    <td style="font-size:11px;color:#A3AED0"><?= date('H:i',strtotime($o['sale_date'])) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">✅ Hozircha yangi buyurtma yo'q</div>
            <?php endif; ?>
        </div>

        <!-- TUGAYOTGAN TOVARLAR -->
        <div class="card">
            <div class="card-hd">
                <div>
                    <div class="card-title">⚠️ Tugayotgan Tovarlar</div>
                    <div class="card-sub">Miqdori 5 ta yoki kamroq</div>
                </div>
                <a href="/menejer/kelgan.php" class="btn-sm">📥 Qabul</a>
            </div>

            <?php if($low_q && mysqli_num_rows($low_q)>0): ?>
            <?php while($lp=mysqli_fetch_assoc($low_q)): ?>
            <div class="ls-row">
                <div class="ls-name"><?= htmlspecialchars($lp['name']) ?></div>
                <span class="ls-qty <?= $lp['quantity']<=2?'lq-err':'lq-warn' ?>">
                    <?= (float)$lp['quantity'] ?> <?= htmlspecialchars($lp['unit']) ?>
                </span>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
            <div class="empty">✅ Barcha tovarlar yetarli</div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
// Har 30 sekundda sahifani yangilashtir
setTimeout(function(){ location.reload(); }, 30000);
</script>
</body>
</html>
