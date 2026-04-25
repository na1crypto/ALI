<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/menejer_check.php";
require_once "../config/dokon_db.php";

@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'tolangan'");
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");
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

// ── STATISTIKA ──
$st_q = mysqli_query($conn,"
    SELECT
        (SELECT COUNT(*) FROM sales WHERE source='mijoz' AND status='yangi') AS yangi_cnt,
        (SELECT COUNT(*) FROM sales WHERE source='mijoz' AND DATE(sale_date)='$today') AS bugun_cnt,
        (SELECT COALESCE(SUM(total_price),0) FROM sales WHERE source='mijoz' AND DATE(sale_date)='$today') AS bugun_summa,
        (SELECT COUNT(*) FROM products WHERE quantity>0) AS prod_cnt,
        (SELECT COUNT(*) FROM products WHERE quantity<=5 AND quantity>0) AS low_cnt,
        (SELECT COUNT(*) FROM products WHERE quantity=0) AS zero_cnt,
        (SELECT COUNT(*) FROM users WHERE role='mijoz') AS mijoz_cnt
");
$st = mysqli_fetch_assoc($st_q);

// ── OXIRGI YANGI BUYURTMALAR (5 ta) ──
$orders_q = mysqli_query($conn,"
    SELECT s.id, s.sale_date, s.total_price, s.note, s.sale_type,
           u.name AS mijoz_nomi
    FROM sales s
    LEFT JOIN users u ON s.user_id=u.id
    WHERE s.source='mijoz' AND s.status='yangi'
    ORDER BY s.id DESC LIMIT 5
");

// ── TUGAYOTGAN MAHSULOTLAR ──
$low_q = mysqli_query($conn,"
    SELECT id, name, quantity, IFNULL(unit,'dona') AS unit
    FROM products WHERE quantity<=5 AND quantity>0
    ORDER BY quantity ASC LIMIT 8
");

// ── OXIRGI KELGAN TOVARLAR ──
$entry_q = mysqli_query($conn,"
    SELECT e.created_at, e.quantity, e.supplier, p.name AS prod_name, p.unit
    FROM stock_entries e
    LEFT JOIN products p ON e.product_id=p.id
    ORDER BY e.id DESC LIMIT 5
");

$site_q = mysqli_fetch_assoc(mysqli_query($conn,"SELECT store_name FROM settings WHERE id=1"));
$site_name = $site_q['store_name'] ?? "SMART POS";
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
.navbar{
    height:62px;background:#fff;display:flex;align-items:center;
    justify-content:space-between;padding:0 28px;
    border-bottom:1px solid #E9EDF7;position:sticky;top:0;z-index:90;
    box-shadow:0 2px 8px rgba(17,28,68,.04);
}
.nav-title{font-size:19px;font-weight:800;color:#111C44}
.nav-sub{font-size:12px;color:#A3AED0;margin-top:1px}
.user-pill{background:#F4F7FE;padding:5px 14px 5px 5px;border-radius:30px;display:flex;align-items:center;gap:9px;font-weight:700;color:#111C44;font-size:13px}
.u-av{width:30px;height:30px;border-radius:50%;background:#4318FF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.main{padding:24px 28px}
/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.scard{background:#fff;border-radius:16px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 4px 16px rgba(17,28,68,.04);transition:.2s}
.scard:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(17,28,68,.08)}
.s-ico{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ic-blue{background:#EEF2FF;color:#4318FF}
.ic-green{background:#E8F9F1;color:#05CD99}
.ic-orange{background:#FFF4E5;color:#FFB547}
.ic-red{background:#FEECEF;color:#EE5D50}
.s-val{font-size:22px;font-weight:800;color:#111C44;line-height:1}
.s-lbl{font-size:12px;color:#A3AED0;font-weight:600;margin-top:3px}
/* GRID */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.grid3{display:grid;grid-template-columns:1.6fr 1fr;gap:16px}
.card{background:#fff;border-radius:16px;padding:20px;box-shadow:0 4px 16px rgba(17,28,68,.04)}
.card-title{font-size:14px;font-weight:800;color:#111C44;margin-bottom:4px}
.card-sub{font-size:11px;color:#A3AED0;margin-bottom:16px}
/* TABLE */
.mtable{width:100%;border-collapse:collapse}
.mtable th{font-size:11px;font-weight:700;color:#A3AED0;text-transform:uppercase;letter-spacing:.5px;padding:0 8px 10px;text-align:left}
.mtable td{padding:10px 8px;font-size:13px;font-weight:600;color:#111C44;border-bottom:1px solid #F4F7FE}
.mtable tr:last-child td{border:none}
.bid{color:#4318FF;font-weight:800}
.bdate{font-size:11px;color:#A3AED0}
/* STATUS */
.bs{padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700}
.bs-yangi{background:#EEF2FF;color:#4318FF}
.bs-yetkazish{background:#FFF4E5;color:#FFB547}
.bs-pickup{background:#E8F9F1;color:#05CD99}
/* LOW STOCK */
.ls-item{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F4F7FE}
.ls-item:last-child{border:none}
.ls-name{font-size:13px;font-weight:700;color:#111C44}
.ls-qty{font-size:12px;font-weight:800;padding:3px 10px;border-radius:20px}
.lq-warn{background:#FFF4E5;color:#FFB547}
.lq-danger{background:#FEECEF;color:#EE5D50}
/* ENTRY */
.entry-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F4F7FE}
.entry-item:last-child{border:none}
.e-ico{width:32px;height:32px;border-radius:9px;background:#E8F9F1;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#05CD99}
.e-name{font-size:13px;font-weight:700;color:#111C44}
.e-meta{font-size:11px;color:#A3AED0}
.e-qty{font-size:13px;font-weight:800;color:#05CD99;margin-left:auto;flex-shrink:0}
/* BTN */
.btn-primary{display:inline-flex;align-items:center;gap:6px;background:#4318FF;color:#fff;border:none;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;transition:.2s}
.btn-primary:hover{background:#3311cc;transform:translateY(-1px);color:#fff}
.empty{text-align:center;padding:28px;color:#A3AED0;font-size:13px}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}.grid2,.grid3{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="navbar">
    <div>
        <div class="nav-title">Boshqaruv Paneli</div>
        <div class="nav-sub"><?= date('d F Y') ?> · Menejer</div>
    </div>
    <div class="user-pill">
        <div class="u-av"><?= strtoupper(substr($_SESSION['name']??'M',0,1)) ?></div>
        <?= htmlspecialchars(explode(' ',$_SESSION['name']??'Menejer')[0]) ?>
    </div>
</div>

<div class="main">

    <!-- STATS -->
    <div class="stats">
        <div class="scard">
            <div class="s-ico ic-blue">📋</div>
            <div>
                <div class="s-val"><?= $st['yangi_cnt'] ?></div>
                <div class="s-lbl">Yangi buyurtma</div>
            </div>
        </div>
        <div class="scard">
            <div class="s-ico ic-green">📦</div>
            <div>
                <div class="s-val"><?= $st['bugun_cnt'] ?></div>
                <div class="s-lbl">Bugungi buyurtma</div>
            </div>
        </div>
        <div class="scard">
            <div class="s-ico ic-orange">🏪</div>
            <div>
                <div class="s-val"><?= $st['prod_cnt'] ?></div>
                <div class="s-lbl">Ombordagi tovar turi</div>
            </div>
        </div>
        <div class="scard">
            <div class="s-ico ic-red">⚠️</div>
            <div>
                <div class="s-val" style="color:#EE5D50"><?= $st['low_cnt'] ?></div>
                <div class="s-lbl">Tugayotgan tovar</div>
            </div>
        </div>
    </div>

    <div class="grid3">

        <!-- YANGI BUYURTMALAR -->
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
                <div>
                    <div class="card-title">Yangi Buyurtmalar</div>
                    <div class="card-sub">Mijozlardan kelgan, kutilayotgan</div>
                </div>
                <a href="/menejer/buyurtmalar.php" class="btn-primary">Barchasini ko'rish →</a>
            </div>
            <?php if(mysqli_num_rows($orders_q)>0): ?>
            <table class="mtable">
                <thead>
                    <tr><th>#</th><th>Mijoz</th><th>Summa</th><th>Turi</th><th>Vaqt</th></tr>
                </thead>
                <tbody>
                <?php while($o=mysqli_fetch_assoc($orders_q)): ?>
                <tr>
                    <td class="bid">#<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($o['mijoz_nomi']??'—') ?></td>
                    <td><?= number_format($o['total_price'],0,'.',' ') ?> <span style="font-size:11px;color:#A3AED0">UZS</span></td>
                    <td><span class="bs <?= $o['sale_type']==='yetkazish'?'bs-yetkazish':'bs-pickup' ?>"><?= $o['sale_type']==='yetkazish'?'🚚 Yetkazish':'🏪 Olish' ?></span></td>
                    <td class="bdate"><?= date('H:i',strtotime($o['sale_date'])) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">✅ Yangi buyurtmalar yo'q</div>
            <?php endif; ?>
        </div>

        <!-- O'NG USTUN -->
        <div style="display:flex;flex-direction:column;gap:16px">

            <!-- TUGAYOTGAN TOVARLAR -->
            <div class="card">
                <div class="card-title">⚠️ Tugayotgan Tovarlar</div>
                <div class="card-sub">Miqdori 5 ta yoki kamroq</div>
                <?php if(mysqli_num_rows($low_q)>0): ?>
                <?php while($lp=mysqli_fetch_assoc($low_q)): ?>
                <div class="ls-item">
                    <div class="ls-name"><?= htmlspecialchars($lp['name']) ?></div>
                    <span class="ls-qty <?= $lp['quantity']<=2?'lq-danger':'lq-warn' ?>">
                        <?= $lp['quantity'] ?> <?= htmlspecialchars($lp['unit']) ?>
                    </span>
                </div>
                <?php endwhile; ?>
                <div style="margin-top:12px">
                    <a href="/menejer/kelgan.php" class="btn-primary" style="width:100%;justify-content:center">📥 Tovar qabul qilish</a>
                </div>
                <?php else: ?>
                <div class="empty">✅ Barcha tovarlar yetarli</div>
                <?php endif; ?>
            </div>

            <!-- OXIRGI KELGAN -->
            <div class="card">
                <div class="card-title">📥 Oxirgi Qabul Qilingan</div>
                <div class="card-sub">So'nggi kiritilgan tovarlar</div>
                <?php if($entry_q && mysqli_num_rows($entry_q)>0): ?>
                <?php while($e=mysqli_fetch_assoc($entry_q)): ?>
                <div class="entry-item">
                    <div class="e-ico">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M5 19h14"/></svg>
                    </div>
                    <div>
                        <div class="e-name"><?= htmlspecialchars($e['prod_name']??'—') ?></div>
                        <div class="e-meta"><?= htmlspecialchars($e['supplier']??'') ?> · <?= date('d.m H:i',strtotime($e['created_at'])) ?></div>
                    </div>
                    <div class="e-qty">+<?= (float)$e['quantity'] ?></div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="empty">Hali kiritilmagan</div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- QISQACHA STATISTIKA QATORI -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:16px">
        <div class="card" style="display:flex;align-items:center;gap:12px">
            <div style="font-size:28px">🛒</div>
            <div><div style="font-size:20px;font-weight:800;color:#4318FF"><?= number_format($st['bugun_summa'],0,'.',' ') ?></div><div style="font-size:12px;color:#A3AED0;font-weight:600">Bugungi savdo (UZS)</div></div>
        </div>
        <div class="card" style="display:flex;align-items:center;gap:12px">
            <div style="font-size:28px">👤</div>
            <div><div style="font-size:20px;font-weight:800;color:#05CD99"><?= $st['mijoz_cnt'] ?></div><div style="font-size:12px;color:#A3AED0;font-weight:600">Ro'yxatdagi mijozlar</div></div>
        </div>
        <div class="card" style="display:flex;align-items:center;gap:12px">
            <div style="font-size:28px">🚫</div>
            <div><div style="font-size:20px;font-weight:800;color:#EE5D50"><?= $st['zero_cnt'] ?></div><div style="font-size:12px;color:#A3AED0;font-weight:600">Tugagan tovarlar</div></div>
        </div>
    </div>

</div>
</body>
</html>
