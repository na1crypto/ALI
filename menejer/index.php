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

// ── STATISTIKA ──
$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM sales WHERE source='mijoz' AND status='yangi'"));
$yangi_cnt = (int)($tmp ? $tmp['c'] : 0);

$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c, COALESCE(SUM(total_price),0) AS s FROM sales WHERE source='mijoz' AND DATE(sale_date)='$today'"));
$bugun_cnt   = (int)($tmp ? $tmp['c'] : 0);
$bugun_summa = (float)($tmp ? $tmp['s'] : 0);

$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM products WHERE quantity<=5 AND quantity>0"));
$low_cnt = (int)($tmp ? $tmp['c'] : 0);

$tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM products WHERE quantity=0"));
$zero_cnt = (int)($tmp ? $tmp['c'] : 0);

// ── 7 KUNLIK GRAFIK ──
$haftalik = [];
$max_sum  = 1;
$days_uz  = ['Yak','Du','Se','Chor','Pay','Jum','Shan'];
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $dow = (int)date('w', strtotime($d)); // 0=yak
    $r   = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(total_price),0) AS s, COUNT(*) AS c
         FROM sales WHERE source='mijoz' AND DATE(sale_date)='$d'"
    ));
    $s = (float)($r['s'] ?? 0);
    if ($s > $max_sum) $max_sum = $s;
    $haftalik[] = [
        'date'  => $d,
        'label' => $days_uz[$dow],
        'sum'   => $s,
        'cnt'   => (int)($r['c'] ?? 0),
        'today' => ($d === $today),
    ];
}

// ── YANGI BUYURTMALAR ──
$orders_q = mysqli_query($conn,"
    SELECT s.id, s.sale_date, s.total_price, s.sale_type, u.name AS mijoz_nomi
    FROM sales s LEFT JOIN users u ON s.user_id=u.id
    WHERE s.source='mijoz' AND s.status='yangi'
    ORDER BY s.id DESC LIMIT 5
");

// ── TUGAYOTGAN TOVARLAR ──
$low_q = mysqli_query($conn,"
    SELECT name, quantity, IFNULL(unit,'dona') AS unit
    FROM products WHERE quantity<=5 AND quantity>0
    ORDER BY quantity ASC LIMIT 5
");

// ── SO'NGGI FAOLIYAT (kelgan + buyurtmalar) ──
$activity = [];
$act_q = mysqli_query($conn,"
    SELECT 'buyurtma' AS tip, s.id, s.total_price AS miqdor,
           u.name AS kim, s.sale_date AS vaqt, s.sale_type AS tur
    FROM sales s LEFT JOIN users u ON s.user_id=u.id
    WHERE s.source='mijoz'
    ORDER BY s.id DESC LIMIT 4
");
if ($act_q) while ($a = mysqli_fetch_assoc($act_q)) $activity[] = $a;

$ent_q = mysqli_query($conn,"
    SELECT 'kelgan' AS tip, e.id, e.quantity AS miqdor,
           p.name AS kim, e.created_at AS vaqt, IFNULL(p.unit,'dona') AS tur
    FROM stock_entries e LEFT JOIN products p ON e.product_id=p.id
    ORDER BY e.id DESC LIMIT 4
");
if ($ent_q) while ($a = mysqli_fetch_assoc($ent_q)) $activity[] = $a;

// Vaqtga qarab saralash
usort($activity, function($a,$b){ return strcmp($b['vaqt'],$a['vaqt']); });
$activity = array_slice($activity, 0, 6);

$active_page = 'dashboard';
$ism = explode(' ', $_SESSION['name'] ?? 'Menejer')[0];
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
.nav-left{display:flex;flex-direction:column}
.nav-title{font-size:17px;font-weight:800;color:#111C44}
.nav-sub{font-size:11px;color:#A3AED0;margin-top:1px}
.nav-right{display:flex;align-items:center;gap:12px}
.live-clock{font-size:20px;font-weight:900;color:#4318FF;letter-spacing:1px;font-variant-numeric:tabular-nums}
.user-pill{background:#F4F7FE;padding:5px 14px 5px 5px;border-radius:30px;display:flex;align-items:center;gap:9px;font-weight:700;color:#111C44;font-size:13px}
.u-av{width:30px;height:30px;border-radius:50%;background:#4318FF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.main{padding:20px 28px;max-width:1300px}

/* GREETING */
.greet{background:linear-gradient(135deg,#4318FF 0%,#7551FF 60%,#9F7AEA 100%);border-radius:18px;padding:20px 26px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 28px rgba(67,24,255,.25)}
.greet-left h2{font-size:22px;font-weight:900;color:#fff;margin-bottom:4px}
.greet-left p{font-size:13px;color:rgba(255,255,255,.75);font-weight:500}
.greet-emoji{font-size:52px;opacity:.85}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
.scard{background:#fff;border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 10px rgba(17,28,68,.05);transition:.2s}
.scard:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(17,28,68,.1)}
.s-ico{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ic-blue{background:#EEF2FF} .ic-green{background:#E8F9F1} .ic-orange{background:#FFF4E5} .ic-red{background:#FEECEF}
.s-val{font-size:24px;font-weight:900;color:#111C44;line-height:1.1}
.s-lbl{font-size:11px;color:#A3AED0;font-weight:600;margin-top:2px}

/* TEZKOR HAVOLALAR */
.quick{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.qcard{border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;cursor:pointer;text-decoration:none;transition:.2s;border:2px solid transparent}
.qcard:hover{transform:translateY(-3px);filter:brightness(1.05)}
.qc-1{background:linear-gradient(135deg,#4318FF,#7551FF);color:#fff}
.qc-2{background:linear-gradient(135deg,#05CD99,#00A878);color:#fff}
.qc-3{background:linear-gradient(135deg,#FFB547,#FF8C00);color:#fff}
.qc-ico{font-size:28px;flex-shrink:0}
.qc-title{font-size:15px;font-weight:800;color:#fff}
.qc-sub{font-size:12px;color:rgba(255,255,255,.75);margin-top:2px}
.qc-arrow{margin-left:auto;font-size:20px;color:rgba(255,255,255,.5)}

/* ASOSIY GRID */
.g3{display:grid;grid-template-columns:1fr 1.1fr 0.9fr;gap:14px}
.card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 2px 10px rgba(17,28,68,.05)}
.card-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.card-title{font-size:14px;font-weight:800;color:#111C44}
.card-sub{font-size:11px;color:#A3AED0;margin-top:1px}
.btn-sm{display:inline-flex;align-items:center;gap:5px;background:#4318FF;color:#fff;border:none;padding:6px 13px;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:.15s}
.btn-sm:hover{background:#3311cc;color:#fff}

/* TABLE */
.mtable{width:100%;border-collapse:collapse}
.mtable th{font-size:11px;font-weight:700;color:#A3AED0;text-transform:uppercase;letter-spacing:.4px;padding:0 8px 8px;text-align:left}
.mtable td{padding:8px;font-size:13px;font-weight:600;color:#111C44;border-bottom:1px solid #F4F7FE}
.mtable tr:last-child td{border:none}
.bid{color:#4318FF;font-weight:800}
.bs{padding:3px 8px;border-radius:20px;font-size:11px;font-weight:700}
.bs-y{background:#FFF4E5;color:#FFB547} .bs-p{background:#E8F9F1;color:#05CD99}

/* LOW STOCK */
.ls-row{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #F4F7FE}
.ls-row:last-child{border:none}
.ls-name{font-size:13px;font-weight:700;color:#111C44}
.ls-qty{font-size:12px;font-weight:800;padding:3px 10px;border-radius:20px}
.lq-warn{background:#FFF4E5;color:#FFB547} .lq-err{background:#FEECEF;color:#EE5D50}

/* 7 KUNLIK GRAFIK */
.chart-wrap{display:flex;align-items:flex-end;gap:6px;height:90px;padding:0 4px}
.bar-col{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1}
.bar{width:100%;border-radius:6px 6px 0 0;transition:.8s;min-height:4px;cursor:pointer;position:relative}
.bar-today{box-shadow:0 0 0 2px #fff,0 0 0 4px #4318FF}
.bar-lbl{font-size:10px;font-weight:700;color:#A3AED0;text-align:center}
.bar-today-lbl{color:#4318FF}
.chart-cnt{font-size:10px;color:#A3AED0;margin-top:1px}

/* SO'NGGI FAOLIYAT */
.act-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F4F7FE}
.act-item:last-child{border:none}
.act-ico{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.act-buy{background:#EEF2FF} .act-kel{background:#E8F9F1}
.act-name{font-size:13px;font-weight:700;color:#111C44;line-height:1.2}
.act-meta{font-size:11px;color:#A3AED0;margin-top:1px}
.act-val{margin-left:auto;font-size:12px;font-weight:800;flex-shrink:0}

.empty{text-align:center;padding:22px;color:#A3AED0;font-size:13px}
@media(max-width:1000px){.stats{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:1fr}.quick{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="navbar">
    <div class="nav-left">
        <div class="nav-title">📊 Boshqaruv Paneli</div>
        <div class="nav-sub"><?= date('d.m.Y') ?> · Menejer</div>
    </div>
    <div class="nav-right">
        <div class="live-clock" id="clock">--:--:--</div>
        <div class="user-pill">
            <div class="u-av"><?= strtoupper(substr($_SESSION['name']??'M',0,1)) ?></div>
            <?= htmlspecialchars($ism) ?>
        </div>
    </div>
</div>

<div class="main">

    <!-- GREETING BANNER -->
    <div class="greet">
        <div class="greet-left">
            <h2 id="greetMsg">Xayrli kun, <?= htmlspecialchars($ism) ?>! 👋</h2>
            <p id="greetSub">Bugun <?= date('d F Y') ?> · Barcha buyurtmalar nazorat ostida</p>
        </div>
        <div class="greet-emoji" id="greetEmo">☀️</div>
    </div>

    <!-- 4 STAT -->
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

    <!-- TEZKOR HAVOLALAR -->
    <div class="quick">
        <a href="/menejer/buyurtmalar.php" class="qcard qc-1">
            <div class="qc-ico">🛒</div>
            <div>
                <div class="qc-title">Buyurtmalar</div>
                <div class="qc-sub"><?= $yangi_cnt ?> ta kutilmoqda</div>
            </div>
            <div class="qc-arrow">›</div>
        </a>
        <a href="/menejer/kelgan.php" class="qcard qc-2">
            <div class="qc-ico">📥</div>
            <div>
                <div class="qc-title">Tovar Qabul Qilish</div>
                <div class="qc-sub">Omborga kiritish</div>
            </div>
            <div class="qc-arrow">›</div>
        </a>
        <a href="/menejer/mahsulotlar.php" class="qcard qc-3">
            <div class="qc-ico">📦</div>
            <div>
                <div class="qc-title">Ombor</div>
                <div class="qc-sub"><?= $low_cnt ?> ta tugayapti</div>
            </div>
            <div class="qc-arrow">›</div>
        </a>
    </div>

    <!-- 3 USTUN -->
    <div class="g3">

        <!-- 7 KUNLIK GRAFIK + YANGI BUYURTMALAR -->
        <div style="display:flex;flex-direction:column;gap:14px">

            <!-- GRAFIK -->
            <div class="card">
                <div class="card-hd">
                    <div>
                        <div class="card-title">📈 7 Kunlik Savdo</div>
                        <div class="card-sub">Mijoz buyurtmalari</div>
                    </div>
                    <div style="font-size:13px;font-weight:800;color:#4318FF"><?= number_format($bugun_summa,0,'.',' ') ?> UZS bugun</div>
                </div>
                <div class="chart-wrap">
                <?php foreach($haftalik as $h):
                    $pct = $max_sum > 0 ? max(4, round(($h['sum']/$max_sum)*86)) : 4;
                    $clr = $h['today'] ? '#4318FF' : '#C7D2FE';
                ?>
                <div class="bar-col">
                    <div class="bar <?= $h['today']?'bar-today':'' ?>"
                         style="height:<?= $pct ?>px;background:<?= $clr ?>"
                         title="<?= $h['date'] ?>: <?= number_format($h['sum'],0,'.',' ') ?> UZS (<?= $h['cnt'] ?> ta)">
                    </div>
                    <div class="bar-lbl <?= $h['today']?'bar-today-lbl':'' ?>"><?= $h['label'] ?></div>
                    <?php if($h['cnt']>0): ?><div class="chart-cnt"><?= $h['cnt'] ?>ta</div><?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
                <!-- Legend -->
                <div style="display:flex;gap:12px;margin-top:10px">
                    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#A3AED0">
                        <div style="width:10px;height:10px;border-radius:3px;background:#4318FF"></div> Bugun
                    </div>
                    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#A3AED0">
                        <div style="width:10px;height:10px;border-radius:3px;background:#C7D2FE"></div> Boshqa kunlar
                    </div>
                </div>
            </div>

            <!-- YANGI BUYURTMALAR -->
            <div class="card">
                <div class="card-hd">
                    <div>
                        <div class="card-title">🛒 Yangi Buyurtmalar</div>
                        <div class="card-sub">Tasdiqlash kutilmoqda</div>
                    </div>
                    <a href="/menejer/buyurtmalar.php" class="btn-sm">Barchasi →</a>
                </div>
                <?php if($orders_q && mysqli_num_rows($orders_q)>0): ?>
                <table class="mtable">
                    <thead><tr><th>#</th><th>Mijoz</th><th>Summa</th><th>Tur</th></tr></thead>
                    <tbody>
                    <?php while($o=mysqli_fetch_assoc($orders_q)): ?>
                    <tr>
                        <td class="bid">#<?= str_pad($o['id'],4,'0',STR_PAD_LEFT) ?></td>
                        <td><?= htmlspecialchars($o['mijoz_nomi']??'—') ?></td>
                        <td style="font-weight:800"><?= number_format($o['total_price'],0,'.',' ') ?></td>
                        <td><span class="bs <?= $o['sale_type']==='yetkazish'?'bs-y':'bs-p' ?>"><?= $o['sale_type']==='yetkazish'?'🚚':'🏪' ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">✅ Yangi buyurtma yo'q</div>
                <?php endif; ?>
            </div>
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
                <div>
                    <div class="ls-name"><?= htmlspecialchars($lp['name']) ?></div>
                </div>
                <span class="ls-qty <?= $lp['quantity']<=2?'lq-err':'lq-warn' ?>">
                    <?= (float)$lp['quantity'] ?> <?= htmlspecialchars($lp['unit']) ?>
                </span>
            </div>
            <?php endwhile; ?>
            <!-- Progress bar yoqim uchun -->
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F4F7FE">
                <div style="font-size:11px;font-weight:700;color:#A3AED0;margin-bottom:8px;text-transform:uppercase;">Ombor holati</div>
                <div style="background:#F4F7FE;border-radius:8px;height:8px;overflow:hidden">
                    <?php $pct_low = min(100, round(($low_cnt/max(1,$low_cnt+$zero_cnt+1))*100)); ?>
                    <div style="width:<?= $pct_low ?>%;height:100%;background:linear-gradient(90deg,#FFB547,#FF6B00);border-radius:8px;transition:1.2s"></div>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:5px;font-size:11px;color:#A3AED0">
                    <span><?= $low_cnt ?> ta tugayapti</span>
                    <span><?= $zero_cnt ?> ta tugagan</span>
                </div>
            </div>
            <?php else: ?>
            <div class="empty">✅ Barcha tovarlar yetarli</div>
            <?php endif; ?>
        </div>

        <!-- SO'NGGI FAOLIYAT -->
        <div class="card">
            <div class="card-hd">
                <div>
                    <div class="card-title">⚡ So'nggi Faoliyat</div>
                    <div class="card-sub">Buyurtmalar va kirimlar</div>
                </div>
            </div>
            <?php if($activity): foreach($activity as $a):
                $is_buy = $a['tip'] === 'buyurtma';
                $t = strtotime($a['vaqt']);
                $diff = time() - $t;
                if ($diff < 60)           $ago = $diff . 's oldin';
                elseif ($diff < 3600)     $ago = round($diff/60) . 'min oldin';
                elseif ($diff < 86400)    $ago = round($diff/3600) . 'soat oldin';
                else                      $ago = date('d.m', $t);
            ?>
            <div class="act-item">
                <div class="act-ico <?= $is_buy?'act-buy':'act-kel' ?>">
                    <?= $is_buy ? '🛒' : '📥' ?>
                </div>
                <div style="min-width:0">
                    <div class="act-name"><?= htmlspecialchars(mb_substr($a['kim']??'—',0,18)) ?></div>
                    <div class="act-meta"><?= $is_buy?'Buyurtma':'Kirim' ?> · <?= $ago ?></div>
                </div>
                <div class="act-val" style="color:<?= $is_buy?'#4318FF':'#05CD99' ?>">
                    <?= $is_buy ? number_format($a['miqdor'],0,'.',' ').' UZS' : '+'.(float)$a['miqdor'].' '.$a['tur'] ?>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="empty">Hali faoliyat yo'q</div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
// ── JONLI SOAT ──
function tick() {
    const now = new Date();
    const hh  = String(now.getHours()).padStart(2,'0');
    const mm  = String(now.getMinutes()).padStart(2,'0');
    const ss  = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('clock').textContent = hh+':'+mm+':'+ss;
}
tick(); setInterval(tick, 1000);

// ── SALOMLASHISH ──
(function(){
    const h = new Date().getHours();
    let msg, emo;
    if (h>=5  && h<12) { msg='Xayrli tong'; emo='🌅'; }
    else if (h>=12 && h<17) { msg='Xayrli kun'; emo='☀️'; }
    else if (h>=17 && h<21) { msg='Xayrli kech'; emo='🌆'; }
    else                    { msg='Xayrli tun'; emo='🌙'; }
    document.getElementById('greetMsg').textContent = msg+', <?= htmlspecialchars($ism) ?>! 👋';
    document.getElementById('greetEmo').textContent = emo;
})();

// ── 30 sekundda refresh ──
setTimeout(()=>location.reload(), 30000);
</script>
</body>
</html>
