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
    padding-left: 90px;
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
        <div class="col-3 mb-3 fade-up d2">
            <div class="stat-card">
                <div class="stat-icon ic-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Bugungi Tushum</div>
                    <div class="stat-val">
                        <?= number_format($tushum, 0, '.', ' ') ?>
                        <span class="stat-unit">UZS</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-3 mb-3 fade-up d3">
            <div class="stat-card">
                <div class="stat-icon ic-orange">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Xaridlar Soni</div>
                    <div class="stat-val">
                        <?= $chek_soni ?>
                        <span class="stat-unit">ta chek</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-3 mb-3 fade-up d4">
            <div class="stat-card">
                <div class="stat-icon ic-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Ombordagi Tovarlar</div>
                    <div class="stat-val">
                        <?= $prod_count ?>
                        <span class="stat-unit">xil</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-3 mb-3 fade-up d5">
            <div class="stat-card">
                <div class="stat-icon ic-red">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Tugayotgan Tovarlar</div>
                    <div class="stat-val" style="color:#EE5D50;">
                        <?= $low_stock ?>
                        <span class="stat-unit">ta</span>
                    </div>
                </div>
            </div>
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
</script>

</body>
</html>