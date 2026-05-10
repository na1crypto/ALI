<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','superadmin','manager'])) {
    header('Location: ../auth/login.php'); exit;
}
require_once '../config/dokon_db.php';

$sel_year  = (int)($_GET['yil']  ?? date('Y'));
$sel_month = (int)($_GET['oy']   ?? 0); // 0 = hammasi
$years     = [];
$yr = mysqli_query($conn, "SELECT DISTINCT YEAR(sale_date) AS y FROM sales ORDER BY y DESC");
while ($r = mysqli_fetch_assoc($yr)) $years[] = $r['y'];
if (empty($years)) $years = [date('Y')];

// ── OYLIK MA'LUMOTLAR (tanlangan yil) ──
$months_data = [];
for ($m = 1; $m <= 12; $m++) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(total_price),0) AS jami,
                COUNT(*) AS chek,
                COALESCE(SUM(CASE WHEN payment_method='naqd'  THEN total_price ELSE 0 END),0) AS naqd,
                COALESCE(SUM(CASE WHEN payment_method='karta' THEN total_price ELSE 0 END),0) AS karta,
                COALESCE(SUM(CASE WHEN payment_method='optom' THEN total_price ELSE 0 END),0) AS optom
         FROM sales
         WHERE YEAR(sale_date)=$sel_year AND MONTH(sale_date)=$m"
    ));
    $months_data[$m] = $r;
}

// ── YIL JAMI ──
$year_total = array_sum(array_column($months_data, 'jami'));
$year_chek  = array_sum(array_column($months_data, 'chek'));
$year_naqd  = array_sum(array_column($months_data, 'naqd'));
$year_karta = array_sum(array_column($months_data, 'karta'));
$year_optom = array_sum(array_column($months_data, 'optom'));

// ── O'TGAN YIL JAMI ──
$prev_yr = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_price),0) AS j FROM sales WHERE YEAR(sale_date)=".($sel_year-1)
));
$prev_year_total = (float)$prev_yr['j'];

// ── BU OY ──
$cur_m = (int)date('m');
$cur_month = $months_data[$cur_m];

// ── O'TGAN OY ──
$prev_m = $cur_m == 1 ? 12 : $cur_m - 1;
$prev_my = $cur_m == 1 ? $sel_year - 1 : $sel_year;
$prev_month = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_price),0) AS j FROM sales WHERE YEAR(sale_date)=$prev_my AND MONTH(sale_date)=$prev_m"
));

// ── TOP MAHSULOTLAR (yil boyicha) ──
$top_q = mysqli_query($conn,
    "SELECT p.name, SUM(si.quantity) AS soni, SUM(si.price) AS summa
     FROM sale_items si
     JOIN products p ON si.product_id=p.id
     JOIN sales s ON si.sale_id=s.id
     WHERE YEAR(s.sale_date)=$sel_year
     GROUP BY si.product_id ORDER BY summa DESC LIMIT 8"
);
$tops = [];
if ($top_q) while ($r = mysqli_fetch_assoc($top_q)) $tops[] = $r;

$oy_nomi = ['','Yanvar','Fevral','Mart','Aprel','May','Iyun','Iyul','Avgust','Sentabr','Oktabr','Noyabr','Dekabr'];
function fm($n){ return number_format((float)$n, 0, '.', ' '); }
function pct($a,$b){ if(!$b) return 0; return round(($a-$b)/$b*100); }

$active_menu = 'tahlil';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Moliyaviy Tahlil</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php include "includes/sidebar.php"; ?>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:#f8f9fc;color:#1a1d23;}

/* PAGE */
.page{padding:28px 32px 40px;max-width:1200px;}

/* HEADER */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;}
.ph-title{font-size:22px;font-weight:800;color:#1a1d23;}
.ph-sub{font-size:13px;color:#8a94a6;margin-top:2px;}
.year-sel{display:flex;gap:6px;flex-wrap:wrap;}
.year-btn{padding:7px 16px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;
  font-size:13px;font-weight:600;color:#6b7280;cursor:pointer;text-decoration:none;transition:.15s;}
.year-btn:hover{border-color:#4318FF;color:#4318FF;}
.year-btn.active{background:#4318FF;color:#fff;border-color:#4318FF;}

/* SUMMARY CARDS */
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px;}
@media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr);}}
@media(max-width:500px){.cards{grid-template-columns:1fr;}}
.card{background:#fff;border-radius:14px;padding:20px;border:1.5px solid #f0f2f7;}
.card-label{font-size:12px;font-weight:600;color:#8a94a6;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px;}
.card-val{font-size:20px;font-weight:800;color:#1a1d23;line-height:1.2;}
.card-val span{font-size:12px;font-weight:600;color:#8a94a6;}
.card-badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;margin-top:6px;}
.badge-up{background:#e8f9f1;color:#05cd99;}
.badge-dn{background:#fef2f2;color:#ef4444;}
.badge-eq{background:#f4f7fe;color:#8a94a6;}

/* CHART */
.chart-box{background:#fff;border-radius:14px;padding:20px 24px;border:1.5px solid #f0f2f7;margin-bottom:28px;}
.chart-title{font-size:14px;font-weight:700;color:#1a1d23;margin-bottom:16px;}
.chart-wrap{overflow-x:auto;}
canvas{display:block;}

/* TABLE */
.tbl-box{background:#fff;border-radius:14px;padding:20px 24px;border:1.5px solid #f0f2f7;margin-bottom:28px;}
.tbl-title{font-size:14px;font-weight:700;color:#1a1d23;margin-bottom:14px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:#8a94a6;
  text-transform:uppercase;letter-spacing:.4px;border-bottom:1.5px solid #f0f2f7;}
tbody tr{border-bottom:1px solid #f8f9fc;transition:.1s;}
tbody tr:hover{background:#f8f9fc;}
tbody tr:last-child{border-bottom:none;}
td{padding:10px 12px;color:#374151;}
td.num{text-align:right;font-weight:600;font-variant-numeric:tabular-nums;}
td.oy{font-weight:700;color:#1a1d23;}
.row-cur td{background:#faf8ff !important;}

/* TOP TABLE */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:780px){.two-col{grid-template-columns:1fr;}}
.top-bar{height:6px;border-radius:3px;background:#4318FF;opacity:.7;min-width:4px;}
</style>
</head>
<body>
<div class="page">

  <!-- HEADER -->
  <div class="ph">
    <div>
      <div class="ph-title">Moliyaviy Tahlil</div>
      <div class="ph-sub">Oylik va yillik savdo hisoboti</div>
    </div>
    <div class="year-sel">
      <?php foreach($years as $y): ?>
        <a href="?yil=<?=$y?>" class="year-btn <?=$y==$sel_year?'active':''?>"><?=$y?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- SUMMARY CARDS -->
  <?php
    $diff_month = pct($cur_month['jami'], $prev_month['j']);
    $diff_year  = pct($year_total, $prev_year_total);
    $avg_month  = $year_chek > 0 ? $year_total / max(1, array_sum(array_map(fn($r)=>$r['chek']>0?1:0, $months_data))) : 0;
  ?>
  <div class="cards">
    <div class="card">
      <div class="card-label">Bu oy (<?=$oy_nomi[$cur_m]?>)</div>
      <div class="card-val"><?=fm($cur_month['jami'])?> <span>UZS</span></div>
      <?php $cls=$diff_month>0?'badge-up':($diff_month<0?'badge-dn':'badge-eq'); ?>
      <div class="card-badge <?=$cls?>"><?=$diff_month>=0?'+':''?><?=$diff_month?>% o'tgan oydan</div>
    </div>
    <div class="card">
      <div class="card-label"><?=$sel_year?> yil jami</div>
      <div class="card-val"><?=fm($year_total)?> <span>UZS</span></div>
      <?php $cls2=$diff_year>0?'badge-up':($diff_year<0?'badge-dn':'badge-eq'); ?>
      <div class="card-badge <?=$cls2?>"><?=$diff_year>=0?'+':''?><?=$diff_year?>% o'tgan yildan</div>
    </div>
    <div class="card">
      <div class="card-label">Jami cheklar (<?=$sel_year?>)</div>
      <div class="card-val"><?=fm($year_chek)?> <span>ta</span></div>
      <div class="card-badge badge-eq">O'rtacha: <?=$year_chek>0?fm($year_total/$year_chek).' UZS':'—'?></div>
    </div>
    <div class="card">
      <div class="card-label">To'lov turlari</div>
      <div class="card-val" style="font-size:14px;line-height:1.7;">
        Naqd: <b><?=fm($year_naqd)?></b><br>
        Karta: <b><?=fm($year_karta)?></b><br>
        <?php if($year_optom>0): ?>Optom: <b><?=fm($year_optom)?></b><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CHART -->
  <div class="chart-box">
    <div class="chart-title"><?=$sel_year?> yil oylik savdo dinamikasi</div>
    <div class="chart-wrap">
      <canvas id="barChart" height="180"></canvas>
    </div>
  </div>

  <!-- MONTHLY TABLE + TOP PRODUCTS -->
  <div class="two-col">

    <!-- MONTHLY TABLE -->
    <div class="tbl-box" style="overflow-x:auto;">
      <div class="tbl-title">Oylik taqsimot — <?=$sel_year?></div>
      <table>
        <thead>
          <tr>
            <th>Oy</th>
            <th style="text-align:right">Tushum</th>
            <th style="text-align:right">Chek</th>
            <th style="text-align:right">Naqd</th>
            <th style="text-align:right">Karta</th>
          </tr>
        </thead>
        <tbody>
        <?php for($m=1;$m<=12;$m++):
          $row = $months_data[$m];
          $isCur = ($m==$cur_m && $sel_year==date('Y'));
        ?>
          <tr class="<?=$isCur?'row-cur':''?>">
            <td class="oy"><?=$oy_nomi[$m]?><?=$isCur?' <span style="font-size:10px;color:#4318FF;">●</span>':''?></td>
            <td class="num"><?=$row['jami']>0?fm($row['jami']):'—'?></td>
            <td class="num"><?=$row['chek']>0?$row['chek']:'—'?></td>
            <td class="num" style="font-size:12px;"><?=$row['naqd']>0?fm($row['naqd']):'—'?></td>
            <td class="num" style="font-size:12px;"><?=$row['karta']>0?fm($row['karta']):'—'?></td>
          </tr>
        <?php endfor; ?>
          <tr style="border-top:2px solid #e2e8f0;font-weight:700;">
            <td>Jami</td>
            <td class="num"><?=fm($year_total)?></td>
            <td class="num"><?=$year_chek?></td>
            <td class="num" style="font-size:12px;"><?=fm($year_naqd)?></td>
            <td class="num" style="font-size:12px;"><?=fm($year_karta)?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- TOP PRODUCTS -->
    <div class="tbl-box">
      <div class="tbl-title">Top mahsulotlar — <?=$sel_year?></div>
      <?php if(empty($tops)): ?>
        <p style="color:#8a94a6;font-size:13px;">Ma'lumot yo'q</p>
      <?php else:
        $max_s = max(array_column($tops,'summa')) ?: 1;
      ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Mahsulot</th>
            <th style="text-align:right">Soni</th>
            <th style="text-align:right">Summa</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($tops as $i=>$t): ?>
          <tr>
            <td style="color:#8a94a6;font-weight:700;"><?=$i+1?></td>
            <td>
              <?=htmlspecialchars($t['name'])?>
              <div class="top-bar" style="width:<?=round($t['summa']/$max_s*100)?>%;margin-top:4px;"></div>
            </td>
            <td class="num"><?=fm($t['soni'])?></td>
            <td class="num"><?=fm($t['summa'])?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
// Bar chart
(function(){
  const canvas = document.getElementById('barChart');
  const labels = <?= json_encode(array_values($oy_nomi)) ?>.slice(1);
  const values = <?= json_encode(array_values(array_map(fn($r)=>(float)$r['jami'], $months_data))) ?>;
  const curM   = <?= $cur_m - 1 ?>; // 0-based

  const W = canvas.parentElement.offsetWidth || 700;
  canvas.width  = W;
  const H = 180;
  const padL=44, padR=16, padT=10, padB=36;
  const ctx = canvas.getContext('2d');

  const max = Math.max(...values, 1);
  const cols = 12;
  const barW = Math.floor((W - padL - padR) / cols * 0.55);
  const gap  = (W - padL - padR) / cols;

  // Grid lines
  ctx.strokeStyle = '#f0f2f7';
  ctx.lineWidth   = 1;
  for(let i=0;i<=4;i++){
    const y = padT + (H - padT - padB) * (1 - i/4);
    ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(W-padR, y); ctx.stroke();
    ctx.fillStyle='#aab'; ctx.font='10px Inter';
    ctx.textAlign='right';
    ctx.fillText(fmtK(max*i/4), padL-4, y+4);
  }

  // Bars
  values.forEach((v,i)=>{
    const x = padL + gap*i + gap/2 - barW/2;
    const barH = (H - padT - padB) * (v / max);
    const y = H - padB - barH;
    ctx.fillStyle = i===curM ? '#4318FF' : '#d1d5ef';
    ctx.beginPath();
    ctx.roundRect(x, y, barW, barH, [4,4,0,0]);
    ctx.fill();
    // Label
    ctx.fillStyle='#8a94a6'; ctx.font='10px Inter'; ctx.textAlign='center';
    ctx.fillText(labels[i].slice(0,3), x+barW/2, H-padB+14);
    // Value on top
    if(v>0){
      ctx.fillStyle= i===curM?'#4318FF':'#8a94a6';
      ctx.font= i===curM?'bold 10px Inter':'10px Inter';
      ctx.fillText(fmtK(v), x+barW/2, y-4);
    }
  });

  function fmtK(n){
    if(n>=1000000) return (n/1000000).toFixed(1)+'M';
    if(n>=1000)    return Math.round(n/1000)+'K';
    return Math.round(n);
  }
})();
</script>
</body>
</html>
