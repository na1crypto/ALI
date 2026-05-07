<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

require_once "../middleware/admin_check.php";
require_once "../config/dokon_db.php";

// AJAX: maoshni yangilash
if (isset($_POST['action']) && $_POST['action'] === 'update_salary') {
    header('Content-Type: application/json');
    $uid    = (int)($_POST['user_id'] ?? 0);
    $maosh  = max(0, (float)($_POST['salary'] ?? 0));
    if ($uid > 0) {
        mysqli_query($conn, "UPDATE users SET salary=$maosh WHERE id=$uid");
        echo json_encode(['ok' => true, 'total' => mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(salary),0) AS s FROM users WHERE salary>0"))['s']]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

$st_query = mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1");
$st = ($st_query && mysqli_num_rows($st_query) > 0) ? mysqli_fetch_assoc($st_query) : null;
$site_name = $st['store_name'] ?? "SMART POS";

$today         = date('Y-m-d');
$current_month = date('Y-m');

// ==========================================
// BARCHA STATISTIKA - 1 TA QUERY
// ==========================================
$stats_q = mysqli_query($conn, "
    SELECT
        (SELECT COALESCE(SUM(total_price),0) FROM sales WHERE DATE(sale_date)='$today') as tushum,
        (SELECT COUNT(id) FROM sales WHERE DATE(sale_date)='$today') as chek_soni,
        (SELECT COUNT(id) FROM products) as prod_count,
        (SELECT COUNT(id) FROM products WHERE quantity <= 5) as low_stock,
        (SELECT COALESCE(SUM(total_price),0) FROM sales
         WHERE DATE_FORMAT(sale_date,'%Y-%m')='$current_month') as oylik_savdo
");
$stats       = mysqli_fetch_assoc($stats_q);
$tushum      = $stats['tushum']      ?? 0;
$chek_soni   = $stats['chek_soni']   ?? 0;
$prod_count  = $stats['prod_count']  ?? 0;
$low_stock   = $stats['low_stock']   ?? 0;
$oylik_savdo = $stats['oylik_savdo'] ?? 0;

// ==========================================
// HAFTALIK SAVDO - 1 TA QUERY
// ==========================================
$week_q = mysqli_query($conn, "
    SELECT DATE(sale_date) as kun, COALESCE(SUM(total_price),0) as s
    FROM sales
    WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(sale_date)
");
$week_data = [];
if ($week_q) while ($r = mysqli_fetch_assoc($week_q)) $week_data[$r['kun']] = $r['s'];

$days = []; $data_points = [];
for ($i = 6; $i >= 0; $i--) {
    $d           = date('Y-m-d', strtotime("-$i days"));
    $days[]      = date('d M', strtotime($d));
    $data_points[] = isset($week_data[$d]) ? (float)$week_data[$d] : 0;
}

// ==========================================
// OYLIK MAQSAD
// ==========================================
$oylik_maqsad    = 50000000;
$bajarilgan_foiz = ($oylik_maqsad > 0)
    ? min(round((floatval($oylik_savdo) / $oylik_maqsad) * 100, 1), 100)
    : 0;

// ==========================================
// MOLIYAVIY TAHLIL — FOYDA HISOBLASH
// ==========================================
// Migration: salary ustuni
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS salary DECIMAL(15,2) DEFAULT 0");
@mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(15,2) DEFAULT 0");

// Oylik tushum (already: $oylik_savdo)

// Tannarx (sotilgan mahsulotlar xarid narxi asosida)
$tmp = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(si.quantity * p.purchase_price),0) AS tannarx
     FROM sale_items si
     JOIN sales s ON si.sale_id=s.id
     JOIN products p ON si.product_id=p.id
     WHERE DATE_FORMAT(s.sale_date,'%Y-%m')='$current_month'"
));
$oylik_tannarx = (float)($tmp['tannarx'] ?? 0);

// Xodimlar umumiy oylik maoshi
$tmp2 = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(salary),0) AS maosh FROM users WHERE salary > 0"
));
$jami_maosh = (float)($tmp2['maosh'] ?? 0);

// Foyda hisoblash
$brutto_foyda = (float)$oylik_savdo - $oylik_tannarx;
$sof_foyda    = $brutto_foyda - $jami_maosh;

// Foizlar (vizual uchun)
$max_v = max((float)$oylik_savdo, 1);
$tannarx_foiz = min(round($oylik_tannarx / $max_v * 100), 100);
$maosh_foiz   = min(round($jami_maosh   / $max_v * 100), 100);
$foyda_foiz   = max(0, min(round($brutto_foyda / $max_v * 100), 100));

// Xodimlar ro'yxati (maosh boshqaruvi uchun)
$xodimlar_q = mysqli_query($conn,
    "SELECT id, name, username, role, COALESCE(salary,0) AS salary
     FROM users WHERE role IN ('sotuvchi','menejer','admin','superadmin')
     ORDER BY name ASC"
);
$xodimlar = [];
if ($xodimlar_q) while ($x = mysqli_fetch_assoc($xodimlar_q)) $xodimlar[] = $x;

// ==========================================
// TO'LOV USULI BO'YICHA BUGUN
// ==========================================
$pay_today_q = mysqli_query($conn, "
    SELECT COALESCE(payment_method,'naqd') as pm, COALESCE(SUM(total_price),0) as summa
    FROM sales WHERE DATE(sale_date)='$today'
    GROUP BY pm
");
$pay_today = ['naqd'=>0,'karta'=>0,'online'=>0,'optom'=>0];
if($pay_today_q) while($r=mysqli_fetch_assoc($pay_today_q)) {
    $k = $r['pm'] ?: 'naqd';
    if(isset($pay_today[$k])) $pay_today[$k]=(float)$r['summa'];
    else $pay_today['naqd']+=(float)$r['summa'];
}

// ==========================================
// TO'LOV USULI BO'YICHA OY
// ==========================================
$pay_month_q = mysqli_query($conn, "
    SELECT COALESCE(payment_method,'naqd') as pm, COALESCE(SUM(total_price),0) as summa
    FROM sales WHERE DATE_FORMAT(sale_date,'%Y-%m')='$current_month'
    GROUP BY pm
");
$pay_month = ['naqd'=>0,'karta'=>0,'online'=>0,'optom'=>0];
if($pay_month_q) while($r=mysqli_fetch_assoc($pay_month_q)) {
    $k = $r['pm'] ?: 'naqd';
    if(isset($pay_month[$k])) $pay_month[$k]=(float)$r['summa'];
    else $pay_month['naqd']+=(float)$r['summa'];
}

// ==========================================
// OYLIK KUNLIK DINAMIKA (modal uchun)
// ==========================================
$monthly_q = mysqli_query($conn, "
    SELECT DATE(sale_date) as kun, COALESCE(SUM(total_price),0) as s
    FROM sales
    WHERE DATE_FORMAT(sale_date,'%Y-%m')='$current_month'
    GROUP BY DATE(sale_date)
    ORDER BY kun ASC
");
$monthly_raw = [];
if($monthly_q) while($r=mysqli_fetch_assoc($monthly_q)) $monthly_raw[$r['kun']]=(float)$r['s'];
$m_days=[]; $m_points=[];
$days_in_month=(int)date('t');
for($i=1;$i<=$days_in_month;$i++){
    $d=date('Y-m').'-'.str_pad($i,2,'0',STR_PAD_LEFT);
    $m_days[]=(string)$i;
    $m_points[]=isset($monthly_raw[$d])?(float)$monthly_raw[$d]:0;
}

// ==========================================
// OXIRGI BUYURTMALAR
// ==========================================
$recent_orders = mysqli_query($conn, "
    SELECT s.id, s.sale_date, s.total_price, u.name as cashier
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.id DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Dashboard | <?= htmlspecialchars($site_name) ?></title>
<style>
/* ================================================
   RESET & BASE
================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #F4F7FE;
    color: #1B2559;
    /* padding-left sidebar.php tomonidan boshqariladi */
    overflow-x: hidden;
    min-height: 100vh;
    transition: padding-left 0.4s cubic-bezier(0.4,0,0.2,1);
}

/* ================================================
   GRID (Bootstrap o'rniga)
================================================ */
.row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
.col { padding: 0 10px; flex: 1; }
.col-3 { width: 25%; padding: 0 10px; }
.col-4 { width: 33.333%; padding: 0 10px; }
.col-8 { width: 66.666%; padding: 0 10px; }
.col-12 { width: 100%; padding: 0 10px; }
.mb-4 { margin-bottom: 24px; }
.mb-3 { margin-bottom: 16px; }

@media (max-width: 900px) {
    .col-3, .col-4, .col-8 { width: 100%; }
    body { padding-left: 0; }
}
/* 📱 MOBILE — 768px bilan sidebar bilan mos */
@media (max-width: 768px) {
    body { padding-left: 0 !important; }
    .top-navbar { padding: 0 14px; height: 54px; }
    .navbar-title { font-size: 17px; }
    .navbar-sub { display: none; }
    .user-pill span { display: none; }
    .user-pill { padding: 4px 8px 4px 4px; }
    .main-content { padding: 12px 10px 80px !important; }
    .row { margin: 0 -5px; }
    .col, .col-3, .col-4, .col-8, .col-12 { padding: 0 5px; }
    /* Stat kartalar: 2 ustun */
    .col-3 { width: 50% !important; }
    .col-4, .col-8, .col-12 { width: 100% !important; }
    .mb-4 { margin-bottom: 10px; }
    .mb-3 { margin-bottom: 8px; }
    .stat-card { padding: 12px; gap: 10px; border-radius: 14px; }
    .stat-icon { width: 38px; height: 38px; font-size: 17px; border-radius: 10px; }
    .stat-val { font-size: 15px; }
    .stat-label { font-size: 10px; }
    .bento { padding: 14px; border-radius: 14px; }
    .chart-wrap { height: 180px; }
    .modern-table th { font-size: 10px; padding: 0 6px 10px; }
    .modern-table td { padding: 9px 6px; font-size: 12px; }
    .cashier-av { width: 22px; height: 22px; font-size: 9px; }
    .target-box { padding: 18px; }
    .tb-amount { font-size: 20px; }
    .ai-card { min-height: 260px; }
    .chat-box { min-height: 130px; }
    /* Foyda panel */
    .foyda-grid { grid-template-columns: 1fr !important; }
    .foyda-schema { padding: 14px !important; }
    .xodim-row { padding: 10px 0 !important; }
}

/* ================================================
   ANIMATIONS
================================================ */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
.fade-up { animation: fadeUp 0.45s ease forwards; opacity: 0; }
.d1 { animation-delay: 0.05s; }
.d2 { animation-delay: 0.10s; }
.d3 { animation-delay: 0.15s; }
.d4 { animation-delay: 0.20s; }
.d5 { animation-delay: 0.25s; }
.d6 { animation-delay: 0.30s; }
.d7 { animation-delay: 0.35s; }

/* ================================================
   TOP NAVBAR
================================================ */
.top-navbar {
    height: 70px;
    background: rgba(244,247,254,0.94);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 36px;
    position: sticky;
    top: 0;
    z-index: 90;
    border-bottom: 1px solid #E9EDF7;
}
.navbar-title { font-size: 21px; font-weight: 700; color: #111C44; letter-spacing: -0.3px; }
.navbar-sub   { font-size: 13px; color: #A3AED0; font-weight: 500; margin-top: 2px; }

.user-pill {
    background: #fff;
    padding: 6px 14px 6px 6px;
    border-radius: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    font-weight: 700;
    color: #111C44;
    font-size: 14px;
}
.user-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: #4318FF; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
}

/* ================================================
   MAIN CONTENT
================================================ */
.main-content { padding: 28px 36px; }

/* ================================================
   STAT KARTALAR
================================================ */
.stat-card {
    background: #fff;
    border-radius: 18px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(17,28,68,0.04);
    transition: transform 0.25s, box-shadow 0.25s;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 28px rgba(17,28,68,0.09);
}
.stat-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.ic-blue   { background: #F0EEFF; color: #4318FF; }
.ic-orange { background: #FFF4E5; color: #FFB547; }
.ic-green  { background: #E8F9F1; color: #05CD99; }
.ic-red    { background: #FEECEF; color: #EE5D50; }

.stat-label { font-size: 13px; color: #A3AED0; font-weight: 500; margin-bottom: 4px; }
.stat-val   { font-size: 22px; font-weight: 700; color: #111C44; line-height: 1.2; }
.stat-unit  { font-size: 13px; color: #A3AED0; font-weight: 500; }

/* ================================================
   BENTO CARD (umumiy)
================================================ */
.bento {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(17,28,68,0.04);
    height: 100%;
}
.bento-title {
    font-size: 16px; font-weight: 700;
    color: #111C44; margin-bottom: 4px;
}
.bento-sub {
    font-size: 12px; color: #A3AED0;
    margin-bottom: 20px;
}

/* ================================================
   GRAFIK CANVAS
================================================ */
.chart-wrap { position: relative; height: 300px; }
.chart-wrap canvas { width: 100% !important; height: 100% !important; display: block; }

/* ================================================
   TARGET BOX
================================================ */
.target-box {
    background: linear-gradient(135deg, #111C44 0%, #4318FF 100%);
    border-radius: 18px;
    padding: 28px;
    color: #fff;
    position: relative;
    overflow: hidden;
    height: 100%;
    box-shadow: 0 12px 28px rgba(67,24,255,0.15);
    transition: transform 0.25s;
}
.target-box:hover { transform: translateY(-4px); }

.tb-circle1 {
    position: absolute; right: -30px; top: -30px;
    width: 140px; height: 140px; border-radius: 50%;
    background: rgba(255,255,255,0.07);
}
.tb-circle2 {
    position: absolute; left: -20px; bottom: -40px;
    width: 110px; height: 110px; border-radius: 50%;
    background: rgba(255,255,255,0.05);
}
.tb-label  { font-size: 13px; color: rgba(255,255,255,0.65); margin-bottom: 4px; }
.tb-amount { font-size: 26px; font-weight: 800; line-height: 1.2; }
.tb-unit   { font-size: 13px; font-weight: 500; }
.tb-maqsad { font-size: 12px; color: rgba(255,255,255,0.55); margin-top: 4px; }

.progress-track {
    height: 10px; border-radius: 10px;
    background: rgba(255,255,255,0.15);
    margin-top: 22px; overflow: hidden;
}
.progress-fill {
    height: 100%; border-radius: 10px;
    background: #05CD99;
    width: <?= $bajarilgan_foiz ?>%;
    box-shadow: 0 0 8px rgba(5,205,153,0.5);
}
.tb-foiz-row {
    display: flex; justify-content: space-between;
    font-size: 12px; font-weight: 600; margin-top: 8px;
    color: rgba(255,255,255,0.6);
}
.tb-foiz-row span:last-child { color: #05CD99; }

/* ================================================
   JADVAL
================================================ */
.modern-table { width: 100%; border-collapse: collapse; }
.modern-table th {
    font-size: 11px; font-weight: 700; color: #A3AED0;
    text-transform: uppercase; letter-spacing: 0.6px;
    padding: 0 10px 14px; text-align: left; border: none;
}
.modern-table td {
    padding: 13px 10px;
    font-size: 13px; font-weight: 600; color: #111C44;
    border-bottom: 1px solid #F4F7FE; vertical-align: middle;
}
.modern-table tr:last-child td { border-bottom: none; }
.chek-id  { color: #4318FF; }
.td-time  { font-size: 11px; color: #A3AED0; font-weight: 500; }
.td-date  { font-size: 13px; color: #111C44; font-weight: 600; }

.cashier-av {
    width: 28px; height: 28px; border-radius: 50%;
    background: #F0EEFF; color: #4318FF;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; margin-right: 8px; flex-shrink: 0;
}
.cashier-cell { display: flex; align-items: center; }

.badge-green {
    background: #E8F9F1; color: #05CD99;
    padding: 4px 12px; border-radius: 8px;
    font-size: 12px; font-weight: 700;
}

/* ================================================
   AI CHAT
================================================ */
.ai-card {
    background: #fff;
    border-radius: 18px;
    padding: 22px;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px rgba(17,28,68,0.04);
}
.ai-header {
    display: flex; align-items: center;
    gap: 12px; margin-bottom: 14px;
}
.ai-icon {
    width: 38px; height: 38px; border-radius: 11px;
    background: #F0EEFF;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #4318FF; flex-shrink: 0;
}
.ai-name   { font-size: 15px; font-weight: 700; color: #111C44; }
.ai-status { font-size: 11px; color: #05CD99; font-weight: 600; }

.chat-box {
    flex: 1; background: #F4F7FE; border-radius: 14px;
    padding: 16px; overflow-y: auto;
    display: flex; flex-direction: column; gap: 10px;
    min-height: 200px; margin-bottom: 12px;
    scroll-behavior: smooth;
}
.ai-msg {
    align-self: flex-start; background: #fff; color: #111C44;
    padding: 10px 14px; border-radius: 0 14px 14px 14px;
    font-size: 13px; font-weight: 500; max-width: 92%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04); line-height: 1.5;
}
.user-msg {
    align-self: flex-end; background: #4318FF; color: #fff;
    padding: 10px 14px; border-radius: 14px 0 14px 14px;
    font-size: 13px; font-weight: 500; max-width: 92%;
}
.ai-msg p { margin: 0 0 6px; }
.ai-msg p:last-child { margin: 0; }

.chat-input-wrap {
    display: flex; align-items: center; gap: 8px;
    background: #F4F7FE; border-radius: 12px;
    border: 1.5px solid #E9EDF7;
    padding: 4px 8px 4px 4px; transition: .2s;
}
.chat-input-wrap:focus-within { border-color: #4318FF; background: #fff; }
.chat-input {
    flex: 1; background: transparent; border: none; outline: none;
    padding: 9px 12px; color: #111C44; font-size: 13px;
    font-family: inherit;
}
.chat-btn {
    width: 38px; height: 38px; border-radius: 10px;
    background: #4318FF; color: #fff; border: none;
    cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
}
.chat-btn:hover { background: #111C44; }

/* TYPING */
.typing { display: flex; gap: 4px; padding: 2px 0; }
.dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #A3AED0;
    animation: bounce 1.2s infinite ease-in-out;
}
.dot:nth-child(2) { animation-delay: 0.2s; }
.dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-5px); background: #4318FF; }
}

/* ================================================
   SVG IKONKALAR (Font Awesome o'rniga)
================================================ */
.ico { display: inline-block; vertical-align: middle; }

/* ================================================
   MOLIYAVIY TAHLIL PANELI
================================================ */
.foyda-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 20px;
}
/* Schema (chap tomon) */
.foyda-schema {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(17,28,68,.04);
}
.fs-title { font-size: 16px; font-weight: 700; color: #111C44; margin-bottom: 4px; }
.fs-sub   { font-size: 12px; color: #A3AED0; margin-bottom: 22px; }

/* Har bir satr */
.foyda-row {
    margin-bottom: 14px;
}
.fr-top {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 5px;
}
.fr-label { font-size: 13px; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 6px; }
.fr-dot   { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.fr-val   { font-size: 14px; font-weight: 800; color: #111C44; }
.fr-bar   { height: 8px; border-radius: 6px; background: #F1F5F9; overflow: hidden; }
.fr-fill  { height: 100%; border-radius: 6px; transition: width .8s ease; }

/* Sof foyda katta blok */
.sof-foyda-box {
    margin-top: 18px;
    border-radius: 14px;
    padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between;
}
.sfb-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; opacity: .8; }
.sfb-val   { font-size: 24px; font-weight: 900; letter-spacing: -1px; }
.sfb-note  { font-size: 11px; opacity: .7; margin-top: 2px; }

/* Xodimlar maoshi (o'ng tomon) */
.xodim-panel {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(17,28,68,.04);
    display: flex; flex-direction: column;
}
.xp-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 16px;
}
.xp-title { font-size: 15px; font-weight: 700; color: #111C44; }
.xp-total {
    font-size: 13px; font-weight: 800;
    color: #4318FF;
    background: #F0EEFF; border-radius: 8px; padding: 4px 10px;
}
.xodim-list { flex: 1; overflow-y: auto; max-height: 280px; }
.xodim-list::-webkit-scrollbar { width: 3px; }
.xodim-list::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 2px; }
.xodim-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 0; border-bottom: 1px solid #F4F7FE;
}
.xodim-row:last-child { border-bottom: none; }
.xod-av {
    width: 34px; height: 34px; border-radius: 50%;
    background: #F0EEFF; color: #4318FF;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 800; flex-shrink: 0;
}
.xod-info { flex: 1; min-width: 0; }
.xod-name { font-size: 13px; font-weight: 700; color: #111C44; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.xod-role { font-size: 11px; color: #A3AED0; font-weight: 500; }
.xod-salary {
    display: flex; align-items: center; gap: 4px;
}
.xod-sal-inp {
    width: 90px; text-align: right;
    border: 1.5px solid #E2E8F0; border-radius: 8px;
    padding: 5px 8px; font-size: 12px; font-weight: 700;
    color: #111C44; background: #F8FAFC;
    transition: .2s;
}
.xod-sal-inp:focus { outline: none; border-color: #4318FF; background: #fff; }
.xod-sal-save {
    width: 28px; height: 28px; border-radius: 7px;
    background: #4318FF; color: #fff; border: none;
    cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center;
    transition: .15s; flex-shrink: 0;
}
.xod-sal-save:hover { background: #3730a3; }
.xod-sal-save:active { transform: scale(.92); }

/* ================================================
   STAT CARD LINK
================================================ */
a.stat-card-link { text-decoration: none; display: block; height: 100%; }
a.stat-card-link .stat-card { cursor: pointer; }

.stat-card.clickable { cursor: pointer; }
.stat-card.clickable:active { transform: scale(0.97); }

/* ================================================
   ANALYTICS MODAL
================================================ */
.an-overlay {
    display: none;
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(17,28,68,0.55);
    backdrop-filter: blur(6px);
    align-items: center; justify-content: center;
    padding: 20px;
}
.an-overlay.open { display: flex; }

.an-modal {
    background: #fff;
    border-radius: 24px;
    width: 100%; max-width: 860px;
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: 0 24px 60px rgba(17,28,68,0.18);
    animation: modalIn 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.88) translateY(30px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.an-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 24px 28px 20px;
    border-bottom: 1px solid #F1F5F9;
    position: sticky; top: 0; background: #fff; z-index: 2;
    border-radius: 24px 24px 0 0;
}
.an-title { font-size: 19px; font-weight: 800; color: #111C44; }
.an-sub   { font-size: 12px; color: #A3AED0; margin-top: 2px; }
.an-close {
    width: 36px; height: 36px; border-radius: 10px;
    background: #F4F7FE; border: none; cursor: pointer;
    font-size: 18px; display: flex; align-items: center; justify-content: center;
    color: #64748b; transition: .2s; flex-shrink: 0;
}
.an-close:hover { background: #FEECEF; color: #EE5D50; }

.an-body { padding: 24px 28px; }

/* KPI row */
.an-kpi-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 28px;
}
@media(max-width:600px){ .an-kpi-row { grid-template-columns: 1fr 1fr; } }
.an-kpi {
    border-radius: 16px;
    padding: 16px 18px;
    display: flex; flex-direction: column; gap: 4px;
}
.an-kpi-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; opacity: .75; }
.an-kpi-val { font-size: 20px; font-weight: 900; line-height: 1.1; }
.an-kpi-sub { font-size: 11px; opacity: .65; }

/* Charts area */
.an-charts {
    display: grid;
    grid-template-columns: 1.7fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
@media(max-width:640px){ .an-charts { grid-template-columns: 1fr; } }

.an-chart-box {
    background: #F8FAFF;
    border-radius: 16px;
    padding: 18px;
}
.an-chart-title {
    font-size: 13px; font-weight: 700; color: #111C44;
    margin-bottom: 14px;
}
.an-canvas-wrap { position: relative; height: 220px; }
.an-canvas-wrap canvas { width: 100% !important; height: 100% !important; }

/* Donut legend */
.donut-legend {
    margin-top: 16px;
    display: flex; flex-direction: column; gap: 8px;
}
.dl-row {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 12px; font-weight: 600;
}
.dl-dot {
    width: 10px; height: 10px; border-radius: 50%;
    display: inline-block; margin-right: 7px; flex-shrink: 0;
}
.dl-name { display: flex; align-items: center; color: #475569; flex: 1; }
.dl-val  { color: #111C44; font-weight: 800; font-size: 13px; }

/* Today breakdown */
.an-today-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
@media(max-width:500px){ .an-today-grid { grid-template-columns: 1fr; } }
.an-today-card {
    border-radius: 14px;
    padding: 14px 16px;
    text-align: center;
}
.atc-ico  { font-size: 24px; margin-bottom: 6px; }
.atc-lbl  { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; opacity: .7; }
.atc-val  { font-size: 16px; font-weight: 900; margin-top: 4px; }
.atc-sub  { font-size: 10px; opacity: .6; margin-top: 2px; }

.an-section-title {
    font-size: 13px; font-weight: 700; color: #A3AED0;
    text-transform: uppercase; letter-spacing: .6px;
    margin-bottom: 12px;
}
</style>
</head>
<body>

<?php $active_menu = 'dashboard'; include "includes/sidebar.php"; ?>

<!-- NAVBAR -->
<div class="top-navbar fade-up d1">
    <div>
        <div class="navbar-title">Boshqaruv Paneli</div>
        <div class="navbar-sub"><?= date('d F, Y') ?> &bull; O&lsquo;zbekiston</div>
    </div>
    <div class="user-pill">
        <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
        </div>
        <?= htmlspecialchars(explode(' ', $_SESSION['name'] ?? 'Administrator')[0]) ?>
    </div>
</div>

<div class="main-content">

    <!-- STAT KARTALAR -->
    <div class="row mb-4">
        <!-- Bugungi Tushum — Modal ochadi -->
        <div class="col-3 mb-3 fade-up d2">
            <div class="stat-card clickable" onclick="openAnalyticsModal()" title="Batafsil tahlilni ko'rish">
                <div class="stat-icon ic-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Bugungi Tushum <span style="font-size:10px;color:#4318FF;">📊</span></div>
                    <div class="stat-val">
                        <?= number_format($tushum, 0, '.', ' ') ?>
                        <span class="stat-unit">UZS</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Xaridlar Soni — savdo tarixiga o'tadi -->
        <div class="col-3 mb-3 fade-up d3">
            <a href="sales_history.php" class="stat-card-link" title="Savdo tarixini ko'rish">
            <div class="stat-card">
                <div class="stat-icon ic-orange">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Xaridlar Soni <span style="font-size:10px;color:#FFB547;">→</span></div>
                    <div class="stat-val">
                        <?= $chek_soni ?>
                        <span class="stat-unit">ta chek</span>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <!-- Ombordagi Tovarlar — mahsulotlarga o'tadi -->
        <div class="col-3 mb-3 fade-up d4">
            <a href="Mahsulotlar/index.php" class="stat-card-link" title="Omborga o'tish">
            <div class="stat-card">
                <div class="stat-icon ic-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Ombordagi Tovarlar <span style="font-size:10px;color:#05CD99;">→</span></div>
                    <div class="stat-val">
                        <?= $prod_count ?>
                        <span class="stat-unit">xil</span>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <!-- Tugayotgan Tovarlar — mahsulotlar filtriga o'tadi -->
        <div class="col-3 mb-3 fade-up d5">
            <a href="Mahsulotlar/index.php?filter=low_stock" class="stat-card-link" title="Tugayotgan tovarlarni ko'rish">
            <div class="stat-card">
                <div class="stat-icon ic-red">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Tugayotgan Tovarlar <span style="font-size:10px;color:#EE5D50;">→</span></div>
                    <div class="stat-val" style="color:#EE5D50;">
                        <?= $low_stock ?>
                        <span class="stat-unit">ta</span>
                    </div>
                </div>
            </div>
            </a>
        </div>
    </div>

    <!-- GRAFIK + TARGET -->
    <div class="row mb-4">
        <div class="col-8 mb-3 fade-up d5">
            <div class="bento">
                <div class="bento-title">Haftalik Savdo Dinamikasi</div>
                <div class="bento-sub">Oxirgi 7 kundagi tushumlar tahlili</div>
                <div class="chart-wrap">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-4 mb-3 fade-up d6">
            <div class="target-box">
                <div class="tb-circle1"></div>
                <div class="tb-circle2"></div>
                <div style="position:relative;z-index:2;">
                    <div style="font-size:15px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFB547" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                        Oylik Savdo Rejasi
                    </div>
                    <div class="tb-label">Joriy Oylik Tushum:</div>
                    <div class="tb-amount">
                        <?= number_format($oylik_savdo, 0, '.', ' ') ?>
                        <span class="tb-unit">UZS</span>
                    </div>
                    <div class="tb-maqsad">Maqsad: <?= number_format($oylik_maqsad, 0, '.', ' ') ?> UZS</div>
                    <div class="progress-track">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="tb-foiz-row">
                        <span>0%</span>
                        <span><?= $bajarilgan_foiz ?>% Bajarildi</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ MOLIYAVIY TAHLIL ══ -->
    <div class="foyda-grid mb-4 fade-up d6">

        <!-- CHAP: Foyda sxemasi -->
        <div class="foyda-schema">
            <div class="fs-title">💰 Moliyaviy Tahlil</div>
            <div class="fs-sub"><?= date('F Y') ?> · Joriy oy natijalari</div>

            <!-- Tushum -->
            <div class="foyda-row">
                <div class="fr-top">
                    <span class="fr-label"><span class="fr-dot" style="background:#4318FF"></span>Umumiy Tushum</span>
                    <span class="fr-val"><?= number_format($oylik_savdo,0,'.',' ') ?> UZS</span>
                </div>
                <div class="fr-bar"><div class="fr-fill" style="width:100%;background:linear-gradient(90deg,#4318FF,#7551FF)"></div></div>
            </div>

            <!-- Tannarx -->
            <div class="foyda-row">
                <div class="fr-top">
                    <span class="fr-label"><span class="fr-dot" style="background:#EE5D50"></span>Tannarx (Xarid narxi)</span>
                    <span class="fr-val" style="color:#EE5D50"><?= number_format($oylik_tannarx,0,'.',' ') ?> UZS</span>
                </div>
                <div class="fr-bar"><div class="fr-fill" style="width:<?= $tannarx_foiz ?>%;background:linear-gradient(90deg,#EE5D50,#FF8B7B)"></div></div>
            </div>

            <!-- Brutto foyda -->
            <div class="foyda-row">
                <div class="fr-top">
                    <span class="fr-label"><span class="fr-dot" style="background:#05CD99"></span>Brutto Foyda</span>
                    <span class="fr-val" style="color:#05CD99"><?= number_format($brutto_foyda,0,'.',' ') ?> UZS</span>
                </div>
                <div class="fr-bar"><div class="fr-fill" style="width:<?= $foyda_foiz ?>%;background:linear-gradient(90deg,#05CD99,#00E5B3)"></div></div>
            </div>

            <!-- Maosh -->
            <div class="foyda-row">
                <div class="fr-top">
                    <span class="fr-label"><span class="fr-dot" style="background:#FFB547"></span>Xodimlar Maoshi</span>
                    <span class="fr-val" style="color:#FFB547"><?= number_format($jami_maosh,0,'.',' ') ?> UZS</span>
                </div>
                <div class="fr-bar"><div class="fr-fill" style="width:<?= $maosh_foiz ?>%;background:linear-gradient(90deg,#FFB547,#FFD07A)"></div></div>
            </div>

            <!-- Sof foyda -->
            <?php
            $sf_pozitiv = $sof_foyda >= 0;
            $sf_bg  = $sf_pozitiv ? 'linear-gradient(135deg,#05CD99,#00B386)' : 'linear-gradient(135deg,#EE5D50,#C84040)';
            ?>
            <div class="sof-foyda-box" style="background:<?= $sf_bg ?>">
                <div>
                    <div class="sfb-label" style="color:#fff"><?= $sf_pozitiv ? '✅ Sof Foyda' : '⚠️ Zarar' ?></div>
                    <div class="sfb-val" style="color:#fff"><?= number_format(abs($sof_foyda),0,'.',' ') ?> UZS</div>
                    <div class="sfb-note" style="color:rgba(255,255,255,.8)">Tushum − Tannarx − Maosh</div>
                </div>
                <div style="font-size:38px"><?= $sf_pozitiv ? '📈' : '📉' ?></div>
            </div>

            <?php if($oylik_tannarx == 0): ?>
            <div style="margin-top:12px;padding:10px 14px;background:#FFF4E5;border-radius:10px;font-size:12px;color:#92400e;font-weight:600;">
                ⚠️ Tannarx hisoblanmadi — mahsulot xarid narxini kiriting
                (<a href="/admin/Mahsulotlar/index.php" style="color:#4318FF">Omborga o'ting</a>)
            </div>
            <?php endif; ?>
        </div>

        <!-- O'NG: Xodimlar maoshi -->
        <div class="xodim-panel">
            <div class="xp-head">
                <div class="xp-title">👥 Xodimlar Maoshi</div>
                <div class="xp-total"><?= number_format($jami_maosh,0,'.',' ') ?> UZS</div>
            </div>
            <div class="xodim-list">
                <?php foreach($xodimlar as $xod):
                    $rol_colors = ['superadmin'=>'#EE5D50','admin'=>'#4318FF','menejer'=>'#FFB547','sotuvchi'=>'#05CD99'];
                    $rc = $rol_colors[$xod['role']] ?? '#A3AED0';
                ?>
                <div class="xodim-row">
                    <div class="xod-av" style="background:<?= $rc ?>22;color:<?= $rc ?>">
                        <?= mb_strtoupper(mb_substr($xod['name'],0,1)) ?>
                    </div>
                    <div class="xod-info">
                        <div class="xod-name"><?= htmlspecialchars($xod['name']) ?></div>
                        <div class="xod-role"><?= $xod['role'] ?></div>
                    </div>
                    <div class="xod-salary">
                        <input type="number" class="xod-sal-inp"
                               id="sal_<?= $xod['id'] ?>"
                               value="<?= (int)$xod['salary'] ?>"
                               placeholder="0"
                               min="0" step="50000">
                        <button class="xod-sal-save" onclick="saveSalary(<?= $xod['id'] ?>)" title="Saqlash">✓</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($xodimlar)): ?>
                <div style="text-align:center;padding:30px;color:#A3AED0;font-size:13px;">Xodimlar topilmadi</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- JADVAL + AI CHAT -->
    <div class="row fade-up d7">
        <div class="col-8 mb-4">
            <div class="bento">
                <div class="bento-title" style="margin-bottom:18px;">Oxirgi Buyurtmalar</div>
                <div style="overflow-x:auto;">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Sana</th>
                                <th>Chek ID</th>
                                <th>Xodim</th>
                                <th>Summa</th>
                                <th style="text-align:right;">Holat</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($recent_orders && mysqli_num_rows($recent_orders) > 0):
                            while ($row = mysqli_fetch_assoc($recent_orders)): ?>
                            <tr>
                                <td>
                                    <div class="td-time"><?= date('H:i', strtotime($row['sale_date'])) ?></div>
                                    <div class="td-date"><?= date('d M, Y', strtotime($row['sale_date'])) ?></div>
                                </td>
                                <td class="chek-id">#<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="cashier-cell">
                                        <div class="cashier-av">
                                            <?= strtoupper(substr($row['cashier'] ?? 'T', 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($row['cashier'] ?? 'Tizim') ?>
                                    </div>
                                </td>
                                <td><?= number_format($row['total_price'], 0, '.', ' ') ?> UZS</td>
                                <td style="text-align:right;">
                                    <span class="badge-green">To'langan</span>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:30px;color:#A3AED0;">
                                    Hozircha savdolar yo'q.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-4 mb-4">
            <div class="ai-card">
                <div class="ai-header">
                    <div class="ai-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="ai-name"><?= htmlspecialchars($site_name) ?> AI</div>
                        <div class="ai-status">&#9679; Online</div>
                    </div>
                </div>

                <div class="chat-box" id="chatBox">
                    <div class="ai-msg">
                        Salom! Men AI yordamchiman. Savdo yoki ombor haqida savolingiz bormi?
                    </div>
                </div>

                <div class="chat-input-wrap">
                    <input type="text" id="chatInput" class="chat-input"
                           placeholder="Xabar yozing..." autocomplete="off">
                    <button class="chat-btn" id="chatBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /main-content -->

<!-- ═══════════════════════════════════════════════
     ANALYTICS MODAL — Bugungi Tushum tahlili
════════════════════════════════════════════════ -->
<div class="an-overlay" id="analyticsOverlay" onclick="closeAnalyticsModal(event)">
<div class="an-modal" id="analyticsModal">

    <!-- Header -->
    <div class="an-header">
        <div>
            <div class="an-title">📊 Savdo Tahlili</div>
            <div class="an-sub"><?= date('d F, Y') ?> · <?= htmlspecialchars(date('F Y')) ?></div>
        </div>
        <button class="an-close" onclick="closeAnalyticsModal(null, true)">✕</button>
    </div>

    <div class="an-body">

        <!-- KPI row: oylik ko'rsatkichlar -->
        <div class="an-section-title">📅 <?= date('F Y') ?> — Oylik Ko'rsatkichlar</div>
        <div class="an-kpi-row">
            <div class="an-kpi" style="background:#F0EEFF;color:#4318FF;">
                <div class="an-kpi-lbl" style="color:#4318FF;">Umumiy Tushum</div>
                <div class="an-kpi-val"><?= number_format($oylik_savdo,0,'.',' ') ?></div>
                <div class="an-kpi-sub">UZS</div>
            </div>
            <div class="an-kpi" style="background:#E8F9F1;color:#05CD99;">
                <div class="an-kpi-lbl" style="color:#05CD99;">💵 Naqd</div>
                <div class="an-kpi-val"><?= number_format($pay_month['naqd'],0,'.',' ') ?></div>
                <div class="an-kpi-sub">UZS</div>
            </div>
            <div class="an-kpi" style="background:#FFF4E5;color:#FFB547;">
                <div class="an-kpi-lbl" style="color:#FFB547;">💳 Karta</div>
                <div class="an-kpi-val"><?= number_format($pay_month['karta'],0,'.',' ') ?></div>
                <div class="an-kpi-sub">UZS</div>
            </div>
            <div class="an-kpi" style="background:#FEECEF;color:#EE5D50;">
                <div class="an-kpi-lbl" style="color:#EE5D50;">🏪 Optom</div>
                <div class="an-kpi-val"><?= number_format($pay_month['optom'],0,'.',' ') ?></div>
                <div class="an-kpi-sub">UZS</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="an-charts">
            <!-- Oylik dinamika grafigi -->
            <div class="an-chart-box">
                <div class="an-chart-title">📈 Oylik Savdo Dinamikasi (kunlar bo'yicha)</div>
                <div class="an-canvas-wrap">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Donut chart: to'lov usullari -->
            <div class="an-chart-box">
                <div class="an-chart-title">🥧 To'lov Usullari (oy)</div>
                <div class="an-canvas-wrap" style="height:160px;">
                    <canvas id="donutChart"></canvas>
                </div>
                <div class="donut-legend" id="donutLegend"></div>
            </div>
        </div>

        <!-- Bugun bo'yicha breakdown -->
        <div class="an-section-title">🌅 Bugun — <?= date('d F, Y') ?></div>
        <div class="an-today-grid">
            <div class="an-today-card" style="background:#F0EEFF;">
                <div class="atc-ico">💵</div>
                <div class="atc-lbl" style="color:#4318FF;">Naqd</div>
                <div class="atc-val" style="color:#4318FF;"><?= number_format($pay_today['naqd'],0,'.',' ') ?></div>
                <div class="atc-sub" style="color:#4318FF;">UZS</div>
            </div>
            <div class="an-today-card" style="background:#FFF4E5;">
                <div class="atc-ico">💳</div>
                <div class="atc-lbl" style="color:#FFB547;">Karta</div>
                <div class="atc-val" style="color:#FFB547;"><?= number_format($pay_today['karta'],0,'.',' ') ?></div>
                <div class="atc-sub" style="color:#FFB547;">UZS</div>
            </div>
            <div class="an-today-card" style="background:#FEECEF;">
                <div class="atc-ico">🏪</div>
                <div class="atc-lbl" style="color:#EE5D50;">Optom</div>
                <div class="atc-val" style="color:#EE5D50;"><?= number_format($pay_today['optom'],0,'.',' ') ?></div>
                <div class="atc-sub" style="color:#EE5D50;">UZS</div>
            </div>
        </div>

    </div>
</div>
</div><!-- /analyticsOverlay -->

<script>
/* ================================================
   GRAFIK - pure JS Canvas (Chart.js o'rniga)
================================================ */
(function() {
    var canvas = document.getElementById('weeklyChart');
    if (!canvas) return;

    var ctx    = canvas.getContext('2d');
    var labels = <?= json_encode($days) ?>;
    var data   = <?= json_encode($data_points) ?>;

    function draw() {
        var W = canvas.offsetWidth || canvas.parentElement.offsetWidth;
        var H = canvas.parentElement.offsetHeight || 300;
        canvas.width  = W;
        canvas.height = H;

        var pad = { top: 30, right: 20, bottom: 50, left: 70 };
        var maxV  = Math.max.apply(null, data) || 1;
        var n     = data.length;
        var bw = Math.floor((W - pad.left - pad.right) / n * 0.65); /* 0.55 dan 0.65 ga */
        var gap   = (W - pad.left - pad.right) / n;

        // Grid chiziqlar
        ctx.strokeStyle = '#E9EDF7';
        ctx.lineWidth   = 1;
        ctx.setLineDash([4, 4]);
        for (var g = 0; g <= 4; g++) {
            var gy = pad.top + (H - pad.top - pad.bottom) / 4 * g;
            ctx.beginPath();
            ctx.moveTo(pad.left, gy);
            ctx.lineTo(W - pad.right, gy);
            ctx.stroke();
        }
        ctx.setLineDash([]);

        // Y labels
        ctx.fillStyle  = '#A3AED0';
        ctx.font       = '11px Segoe UI';
        ctx.textAlign  = 'right';
        for (var g = 0; g <= 4; g++) {
            var val = Math.round(maxV / 4 * (4 - g));
            var gy  = pad.top + (H - pad.top - pad.bottom) / 4 * g;
            var lbl = val >= 1000000
                ? (val/1000000).toFixed(1) + 'M'
                : val >= 1000 ? (val/1000).toFixed(0) + 'K' : val;
            ctx.fillText(lbl, pad.left - 8, gy + 4);
        }

        // Barlar + X labels
        ctx.textAlign = 'center';
        for (var i = 0; i < n; i++) {
            var x   = pad.left + gap * i + gap / 2 - bw / 2;
            var bh  = (data[i] / maxV) * (H - pad.top - pad.bottom);
            var by  = H - pad.bottom - bh;

            // Gradient
            var gr = ctx.createLinearGradient(0, by, 0, H - pad.bottom);
            gr.addColorStop(0, '#4318FF');
            gr.addColorStop(1, '#6AD2FF');

            // Bar (yumaloq burchak)
            var r = Math.min(8, bw / 2);
            ctx.beginPath();
            ctx.moveTo(x + r, by);
            ctx.lineTo(x + bw - r, by);
            ctx.quadraticCurveTo(x + bw, by, x + bw, by + r);
            ctx.lineTo(x + bw, H - pad.bottom);
            ctx.lineTo(x, H - pad.bottom);
            ctx.lineTo(x, by + r);
            ctx.quadraticCurveTo(x, by, x + r, by);
            ctx.closePath();
            ctx.fillStyle = gr;
            ctx.fill();

            // X label
            ctx.fillStyle = '#A3AED0';
            ctx.font      = '11px Segoe UI';
            ctx.fillText(labels[i], x + bw / 2, H - pad.bottom + 18);
        }
    }

    draw();
    window.addEventListener('resize', draw);
})();

/* ================================================
   AI CHAT - pure JS (jQuery o'rniga)
================================================ */
(function() {
    var box   = document.getElementById('chatBox');
    var input = document.getElementById('chatInput');
    var btn   = document.getElementById('chatBtn');

    function scrollBottom() { box.scrollTop = box.scrollHeight; }

    function addMsg(text, cls) {
        var d = document.createElement('div');
        d.className = cls;
        d.innerHTML = text;
        box.appendChild(d);
        scrollBottom();
        return d;
    }

    function sendMsg() {
        var msg = input.value.trim();
        if (!msg) return;
        input.value = '';

        addMsg(escHtml(msg), 'user-msg');

        var typing = addMsg(
            '<div class="typing"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>',
            'ai-msg'
        );

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'AI/ai_engine.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                box.removeChild(typing);
                if (xhr.status === 200) {
                    addMsg(simpleMarkdown(xhr.responseText), 'ai-msg');
                } else {
                    addMsg('<span style="color:#EE5D50;">Tarmoq xatosi.</span>', 'ai-msg');
                }
            }
        };
        xhr.send('message=' + encodeURIComponent(msg));
    }

    // Oddiy markdown (marked.js o'rniga)
    function simpleMarkdown(t) {
        return t
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
            .replace(/\*(.+?)\*/g,'<em>$1</em>')
            .replace(/\n/g,'<br>');
    }

    function escHtml(t) {
        var d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    btn.addEventListener('click', sendMsg);
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMsg();
    });
})();

/* ================================================
   ANALYTICS MODAL
================================================ */
var _monthlyChartDrawn = false;
var _donutChartDrawn   = false;

function openAnalyticsModal() {
    var overlay = document.getElementById('analyticsOverlay');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    if (!_monthlyChartDrawn) { drawMonthlyChart(); _monthlyChartDrawn = true; }
    if (!_donutChartDrawn)   { drawDonutChart();   _donutChartDrawn   = true; }
}
function closeAnalyticsModal(e, force) {
    if (force || (e && e.target === document.getElementById('analyticsOverlay'))) {
        document.getElementById('analyticsOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeAnalyticsModal(null,true); });

/* ── Oylik grafik ── */
function drawMonthlyChart() {
    var canvas = document.getElementById('monthlyChart');
    if (!canvas) return;
    var ctx    = canvas.getContext('2d');
    var labels = <?= json_encode($m_days) ?>;
    var data   = <?= json_encode($m_points) ?>;

    function draw() {
        var W = canvas.parentElement.offsetWidth  || 420;
        var H = canvas.parentElement.offsetHeight || 220;
        canvas.width  = W; canvas.height = H;
        var pad = { top: 24, right: 16, bottom: 36, left: 58 };
        var maxV = Math.max.apply(null, data) || 1;
        var n    = data.length;
        var gap  = (W - pad.left - pad.right) / n;
        var bw   = Math.max(2, Math.floor(gap * 0.55));

        // grid
        ctx.strokeStyle='#E2E8F0'; ctx.lineWidth=1; ctx.setLineDash([3,3]);
        for(var g=0;g<=4;g++){
            var gy=pad.top+(H-pad.top-pad.bottom)/4*g;
            ctx.beginPath(); ctx.moveTo(pad.left,gy); ctx.lineTo(W-pad.right,gy); ctx.stroke();
            var val=Math.round(maxV/4*(4-g));
            ctx.fillStyle='#A3AED0'; ctx.font='10px Segoe UI'; ctx.textAlign='right';
            ctx.fillText(val>=1000000?(val/1000000).toFixed(1)+'M':val>=1000?(val/1000).toFixed(0)+'K':val, pad.left-4, gy+4);
        }
        ctx.setLineDash([]);

        // line path
        ctx.beginPath();
        var pts=[];
        for(var i=0;i<n;i++){
            var x=pad.left+gap*i+gap/2;
            var y=H-pad.bottom-(data[i]/maxV)*(H-pad.top-pad.bottom);
            pts.push({x:x,y:y});
        }
        // gradient fill
        if(pts.length>1){
            var grad=ctx.createLinearGradient(0,pad.top,0,H-pad.bottom);
            grad.addColorStop(0,'rgba(67,24,255,0.18)');
            grad.addColorStop(1,'rgba(67,24,255,0.01)');
            ctx.beginPath();
            ctx.moveTo(pts[0].x, H-pad.bottom);
            ctx.lineTo(pts[0].x, pts[0].y);
            for(var i=1;i<pts.length;i++){
                var cx=(pts[i-1].x+pts[i].x)/2;
                ctx.bezierCurveTo(cx,pts[i-1].y,cx,pts[i].y,pts[i].x,pts[i].y);
            }
            ctx.lineTo(pts[pts.length-1].x,H-pad.bottom);
            ctx.closePath();
            ctx.fillStyle=grad; ctx.fill();
            // line
            ctx.beginPath();
            ctx.moveTo(pts[0].x,pts[0].y);
            for(var i=1;i<pts.length;i++){
                var cx=(pts[i-1].x+pts[i].x)/2;
                ctx.bezierCurveTo(cx,pts[i-1].y,cx,pts[i].y,pts[i].x,pts[i].y);
            }
            ctx.strokeStyle='#4318FF'; ctx.lineWidth=2.5; ctx.stroke();
        }

        // dots + x-labels (every 5th day)
        ctx.textAlign='center'; ctx.fillStyle='#A3AED0'; ctx.font='10px Segoe UI';
        for(var i=0;i<pts.length;i++){
            if(data[i]>0){
                ctx.beginPath(); ctx.arc(pts[i].x,pts[i].y,3.5,0,Math.PI*2);
                ctx.fillStyle='#4318FF'; ctx.fill();
            }
            if(i===0||i===pts.length-1||(i+1)%5===0){
                ctx.fillStyle='#A3AED0';
                ctx.fillText(labels[i], pts[i].x, H-pad.bottom+15);
            }
        }
    }
    draw();
}

/* ── Donut chart ── */
function drawDonutChart() {
    var canvas = document.getElementById('donutChart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    var payData = [
        { label:'💵 Naqd',  val:<?= $pay_month['naqd'] ?>,  color:'#4318FF' },
        { label:'💳 Karta', val:<?= $pay_month['karta'] ?>, color:'#FFB547' },
        { label:'🏪 Optom', val:<?= $pay_month['optom'] ?>, color:'#EE5D50' },
        { label:'🌐 Online',val:<?= $pay_month['online'] ?>,color:'#05CD99' }
    ].filter(function(d){ return d.val > 0; });

    var total = payData.reduce(function(s,d){ return s+d.val; }, 0);
    if(!total) payData=[{label:'Ma\'lumot yo\'q', val:1, color:'#E2E8F0'}];

    // legend
    var legend = document.getElementById('donutLegend');
    if(legend){
        legend.innerHTML = payData.map(function(d){
            var pct = total>1 ? Math.round(d.val/total*100) : 0;
            var fmt = d.val>=1000000?(d.val/1000000).toFixed(1)+'M':d.val>=1000?(d.val/1000).toFixed(0)+'K':d.val;
            return '<div class="dl-row"><span class="dl-name"><span class="dl-dot" style="background:'+d.color+'"></span>'+d.label+' ('+pct+'%)</span><span class="dl-val">'+fmt+'</span></div>';
        }).join('');
    }

    function draw(){
        var W=canvas.parentElement.offsetWidth||200;
        var H=canvas.parentElement.offsetHeight||160;
        canvas.width=W; canvas.height=H;
        var cx=W/2, cy=H/2, outerR=Math.min(W,H)/2-8, innerR=outerR*0.55;
        var start=-Math.PI/2;
        payData.forEach(function(d){
            var slice=d.val/total*Math.PI*2;
            ctx.beginPath();
            ctx.moveTo(cx,cy);
            ctx.arc(cx,cy,outerR,start,start+slice);
            ctx.closePath();
            ctx.fillStyle=d.color;
            ctx.fill();
            start+=slice;
        });
        // hole
        ctx.beginPath();
        ctx.arc(cx,cy,innerR,0,Math.PI*2);
        ctx.fillStyle='#F8FAFF';
        ctx.fill();
        // center text
        ctx.textAlign='center';
        ctx.fillStyle='#111C44'; ctx.font='bold 13px Segoe UI';
        ctx.fillText('Jami', cx, cy-4);
        var tot_fmt=total>=1000000?(total/1000000).toFixed(1)+'M':total>=1000?(total/1000).toFixed(0)+'K':total;
        ctx.font='bold 11px Segoe UI'; ctx.fillStyle='#4318FF';
        ctx.fillText(tot_fmt, cx, cy+12);
    }
    draw();
}

// ── Xodim maoshini saqlash ──
async function saveSalary(userId) {
    const inp = document.getElementById('sal_' + userId);
    const btn = inp.nextElementSibling;
    const salary = parseFloat(inp.value) || 0;

    btn.textContent = '⏳';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'update_salary');
    fd.append('user_id', userId);
    fd.append('salary', salary);

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const d = await res.json();
        if (d.ok) {
            btn.textContent = '✅';
            btn.style.background = '#05CD99';
            // Jami maoshni yangilash
            const totalEl = document.querySelector('.xp-total');
            if (totalEl) totalEl.textContent = Number(d.total).toLocaleString('uz-UZ') + ' UZS';
            setTimeout(() => {
                btn.textContent = '✓';
                btn.style.background = '';
                btn.disabled = false;
            }, 1500);
        } else {
            btn.textContent = '✕'; btn.disabled = false;
        }
    } catch(e) {
        btn.textContent = '✕'; btn.disabled = false;
    }
}
</script>

</body>
</html>