<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php"); 
    exit;
}

require_once "../middleware/admin_check.php";
require_once "../config/dokon_db.php";

// 🔴 1. BAZANI AVTOMATIK YANGILASH VA TOZALASH (6 OYLIK LIMIT)
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM sales LIKE 'sale_type'");
if ($check_col && mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE sales ADD COLUMN sale_type VARCHAR(20) DEFAULT 'oddiy'");
}

// 🚀 6 OYDAN ESKIRGAN SAVDOLARNI O'CHIRISH (Dastur yengil ishlashi uchun)
$olti_oy_oldin = date('Y-m-d', strtotime('-6 months'));
mysqli_query($conn, "DELETE FROM sales WHERE sale_date < '$olti_oy_oldin'");
// Savdosi o'chib ketgan "yetim" tovarlarni ham tozalaymiz
mysqli_query($conn, "DELETE FROM sale_items WHERE sale_id NOT IN (SELECT id FROM sales)");

$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin');

// 🔴 2. BAZADAN SOZLAMALARNI OLISH (Chek va Dastur nomi uchun)
$st_query = mysqli_query($conn, "SELECT * FROM settings WHERE id=1");
$st = ($st_query && mysqli_num_rows($st_query) > 0) ? mysqli_fetch_assoc($st_query) : null;
$site_name = ($st && isset($st['store_name'])) ? $st['store_name'] : "SMART POS";
$receipt_header = $st['receipt_header'] ?? "Xush kelibsiz!";
$receipt_footer = $st['receipt_footer'] ?? "Xaridingiz uchun tashakkur!";

// =========================================================================
// 🚀 3. AJAX: CHEKNI CHIZISH UCHUN ORQA FON SO'ROVI
// =========================================================================
if (isset($_GET['ajax_receipt_id'])) {
    $receipt_id = (int)$_GET['ajax_receipt_id'];
    
    $sale_q = mysqli_query($conn, "SELECT s.*, u.name as cashier FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = $receipt_id");
    if (!$sale_q || mysqli_num_rows($sale_q) == 0) { die("<div class='text-danger text-center'>Chek topilmadi!</div>"); }
    $sale_data = mysqli_fetch_assoc($sale_q);
    
    $items_html = "";
    $items_q = mysqli_query($conn, "SELECT * FROM sale_items WHERE sale_id = $receipt_id");
    if ($items_q && mysqli_num_rows($items_q) > 0) {
        while ($item = mysqli_fetch_assoc($items_q)) {
            $p_name = $item['product_name'] ?? 'Mahsulot';
            $p_qty = floatval($item['quantity'] ?? 1);
            $p_price = floatval($item['price'] ?? 0);
            $p_total = floatval($item['total_price'] ?? ($p_qty * $p_price));
            $items_html .= "<tr><td style='padding: 4px 0;'>{$p_name}</td><td style='text-align: center;'>{$p_qty}</td><td style='text-align: right;'>".number_format($p_total, 0, '.', ' ')."</td></tr>";
        }
    } else {
        $items_html = "<tr><td colspan='3' style='text-align:center;'>Tovar topilmadi.</td></tr>";
    }

    echo "
    <div id='printReceiptArea' style='font-family: \"Courier New\", monospace; color: #000; width: 100%; max-width: 320px; margin: 0 auto; background: #fff; padding: 15px;'>
        <div style='text-align: center; font-weight: bold; font-size: 20px; margin-bottom: 5px;'>".htmlspecialchars($site_name)."</div>
        <div style='text-align: center; font-size: 13px; margin-bottom: 15px; white-space: pre-line;'>".htmlspecialchars($receipt_header)."</div>
        <div style='border-bottom: 1px dashed #000; margin-bottom: 10px;'></div>
        <table style='width: 100%; font-size: 13px; margin-bottom: 10px;'>
            <tr><td><b>Chek №:</b></td><td style='text-align:right;'>".str_pad($sale_data['id'], 6, '0', STR_PAD_LEFT)."</td></tr>
            <tr><td><b>Sana:</b></td><td style='text-align:right;'>".date('d.m.Y H:i', strtotime($sale_data['sale_date']))."</td></tr>
            <tr><td><b>Kassir:</b></td><td style='text-align:right;'>".htmlspecialchars($sale_data['cashier'] ?? 'Tizim')."</td></tr>
            <tr><td><b>To'lov:</b></td><td style='text-align:right;'>".ucfirst($sale_data['payment_method'] ?? 'Naqd')."</td></tr>
        </table>
        <div style='border-bottom: 1px dashed #000; margin-bottom: 10px;'></div>
        <table style='width: 100%; font-size: 13px; margin-bottom: 10px;'>
            <tr style='font-weight: bold; border-bottom: 1px solid #000;'><td style='padding-bottom:5px;'>Nomi</td><td style='text-align:center;'>Soni</td><td style='text-align:right;'>Summa</td></tr>
            {$items_html}
        </table>
        <div style='border-bottom: 1px dashed #000; margin-bottom: 10px;'></div>
        <div style='display: flex; justify-content: space-between; font-size: 16px; font-weight: bold; margin-bottom: 15px;'><span>JAMI:</span><span>".number_format($sale_data['total_price'], 0, '.', ' ')." UZS</span></div>
        <div style='text-align: center; font-size: 13px; white-space: pre-line;'>".htmlspecialchars($receipt_footer)."</div>
    </div>";
    exit;
}
// =========================================================================

$today = date('Y-m-d');

// ANALITIKA VA FILTRLAR...
$stat_sql = "SELECT COUNT(id) as cheklar_soni, SUM(total_price) as jami_savdo, AVG(total_price) as ortacha_chek FROM sales WHERE DATE(sale_date) = '$today'";
$stat_res = mysqli_fetch_assoc(mysqli_query($conn, $stat_sql));
$jami_savdo = $stat_res['jami_savdo'] ?? 0;
$cheklar_soni = $stat_res['cheklar_soni'] ?? 0;
$ortacha_chek = $stat_res['ortacha_chek'] ?? 0;

$peak_res = mysqli_query($conn, "SELECT HOUR(sale_date) as soat, COUNT(id) as soni FROM sales WHERE DATE(sale_date) = '$today' GROUP BY HOUR(sale_date) ORDER BY soni DESC LIMIT 1");
$peak_hour = ($peak_res && mysqli_num_rows($peak_res) > 0) ? mysqli_fetch_assoc($peak_res)['soat'] : '--';
$peak_text = ($peak_hour !== '--') ? str_pad($peak_hour, 2, '0', STR_PAD_LEFT).":00 - ".str_pad($peak_hour+1, 2, '0', STR_PAD_LEFT).":00" : "Hali savdo yo'q";

$where = "1=1";
if(!empty($_GET['sana_dan'])) $where .= " AND DATE(s.sale_date) >= '".mysqli_real_escape_string($conn, $_GET['sana_dan'])."'"; 
if(!empty($_GET['sana_gacha'])) $where .= " AND DATE(s.sale_date) <= '".mysqli_real_escape_string($conn, $_GET['sana_gacha'])."'"; 
if(!empty($_GET['tolov_turi'])) $where .= " AND s.payment_method = '".mysqli_real_escape_string($conn, $_GET['tolov_turi'])."'"; 
if(!empty($_GET['sale_type'])) $where .= " AND s.sale_type = '".mysqli_real_escape_string($conn, $_GET['sale_type'])."'"; 

$reyting_res = mysqli_query($conn, "SELECT u.name, COUNT(s.id) as savdo_soni, SUM(s.total_price) as summa FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE $where GROUP BY s.user_id ORDER BY summa DESC LIMIT 5");
$top_products_res = mysqli_query($conn, "SELECT p.name, SUM(si.quantity) as jami_miqdor FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE $where GROUP BY p.id ORDER BY jami_miqdor DESC LIMIT 5");
$history_res = mysqli_query($conn, "SELECT s.*, u.name as seller_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE $where ORDER BY s.sale_date DESC LIMIT 50");

// ── Dastavka tarixi ──
$dastavka_res = mysqli_query($conn,"
    SELECT s.id, s.sale_date, s.total_price, s.status, s.note, s.sale_type,
           u.name AS mijoz_nomi, u.username AS mijoz_login
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.source='mijoz'
    ORDER BY s.id DESC LIMIT 100
");
// Dastavka statistika
$dav_tmp = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c, COALESCE(SUM(total_price),0) AS s FROM sales WHERE source='mijoz'"));
$dav_cnt   = (int)($dav_tmp['c'] ?? 0);
$dav_summa = (float)($dav_tmp['s'] ?? 0);
$dav_yangi = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM sales WHERE source='mijoz' AND status='yangi'"));
$dav_yangi = (int)($dav_yangi['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savdo Tarixi | <?= htmlspecialchars($site_name) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; }
        .main-content { margin-left: 90px; padding: 28px 32px; transition: margin-left 0.4s cubic-bezier(0.4,0,0.2,1); }

        .filter-section { background: #FFFFFF; border-radius: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; margin-bottom: 24px; display: flex; flex-wrap: wrap; align-items: center; gap: 15px; }
        .filter-group { display: flex; align-items: center; gap: 12px; }  
        .filter-input { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px 16px; font-size: 14px; font-weight: 500; color: #334155; outline: none; transition: 0.2s; }
        .filter-input:focus { border-color: #4F46E5; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .btn-search { background: #0F172A; color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 600; font-size: 14px; transition: 0.2s; }
        .btn-search:hover { background: #1E293B; }

        .report-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px; transition: 0.2s; height: 100%;}
        .report-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .report-title { font-size: 13px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .report-value { font-size: 24px; font-weight: 700; color: #0F172A; line-height: 1.2; }

        .data-table-wrapper { background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .data-table-header { padding: 20px 24px; border-bottom: 1px solid #E2E8F0; background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; }
        .table { margin-bottom: 0; width: 100%; border-collapse: collapse; }
        .table th { background: #FFFFFF; padding: 16px 24px; font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; border-bottom: 1px solid #E2E8F0; border-top: none; }
        .table td { padding: 16px 24px; font-size: 14px; font-weight: 500; color: #334155; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        .table tbody tr:hover { background: #F8FAFC; }
        
        .status-badge { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; gap: 6px; }
        .badge-naqd { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .badge-karta { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .badge-optom { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .badge-oddiy { background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0; }

        .side-panel { background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);}
        .side-list-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px dashed #E2E8F0; }
        
        .progress-custom { height: 8px; border-radius: 10px; background: #F1F5F9; margin-top: 6px; overflow: hidden; }
        .progress-custom .bar { height: 100%; border-radius: 10px; background: linear-gradient(90deg, #4F46E5, #818CF8); }

        /* Chek Modali va Tugmasi dizayni */
        .btn-view-receipt { background: #EEF2FF; color: #4F46E5; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; transition: 0.2s; cursor: pointer; }
        .btn-view-receipt:hover { background: #4F46E5; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); }
        .modal-content { border-radius: 20px; border: none; }
        .btn-print { background: #0F172A; color: white; border: none; padding: 12px; border-radius: 10px; width: 100%; font-weight: 600; transition: 0.2s; }
        .btn-print:hover { background: #4F46E5; }
        
        @media print {
            body * { visibility: hidden; }
            #printReceiptArea, #printReceiptArea * { visibility: visible; }
            #printReceiptArea { position: absolute; left: 0; top: 0; width: 80mm; margin: 0; padding: 0; }
            .modal-content { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

    <?php $active_menu = 'savdo'; include "includes/sidebar.php"; ?>

    <div class="main-content">
        
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h1 style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 0;">Savdo Tarixi va Hisobotlar</h1>
                <p class="text-muted mt-1 mb-0" style="font-size: 14px;">Barcha tranzaksiyalar ro'yxati.</p>
            </div>
        </div>

        <!-- TABLAR -->
        <div style="display:flex;gap:8px;margin-bottom:20px;">
            <button onclick="showTab('savdo')" id="tab-savdo"
                style="padding:10px 22px;border-radius:10px;border:none;font-weight:700;font-size:14px;cursor:pointer;background:#0F172A;color:#fff;">
                🧾 Savdo Tarixi
            </button>
            <button onclick="showTab('dastavka')" id="tab-dastavka"
                style="padding:10px 22px;border-radius:10px;border:none;font-weight:700;font-size:14px;cursor:pointer;background:#E2E8F0;color:#475569;">
                🚚 Dastavka Tarixi
                <?php if($dav_yangi>0): ?>
                <span style="background:#EF4444;color:#fff;border-radius:999px;padding:2px 7px;font-size:11px;margin-left:4px;"><?= $dav_yangi ?></span>
                <?php endif; ?>
            </button>
        </div>

<!-- ═══ SAVDO TARIXI ═══ -->
<div id="panel-savdo">
        <form method="GET" class="filter-section">
            <div class="filter-group">
                <div class="text-muted font-weight-bold mr-2" style="font-size: 14px;">Davr:</div>
                <input type="date" name="sana_dan" class="filter-input" value="<?= htmlspecialchars($_GET['sana_dan'] ?? '') ?>">
                <span class="text-muted">-</span>
                <input type="date" name="sana_gacha" class="filter-input" value="<?= htmlspecialchars($_GET['sana_gacha'] ?? '') ?>">
            </div>
            <div class="filter-group">
                <div class="text-muted font-weight-bold mr-2" style="font-size: 14px;">To'lov:</div>
                <select name="tolov_turi" class="filter-input">
                    <option value="">Barchasi</option>
                    <option value="naqd" <?= (isset($_GET['tolov_turi']) && $_GET['tolov_turi'] == 'naqd') ? 'selected' : '' ?>>💵 Naqd</option>
                    <option value="karta" <?= (isset($_GET['tolov_turi']) && $_GET['tolov_turi'] == 'karta') ? 'selected' : '' ?>>💳 Karta</option>
                </select>
            </div>
            <div class="filter-group">
                <div class="text-muted font-weight-bold mr-2" style="font-size: 14px;">Turi:</div>
                <select name="sale_type" class="filter-input">
                    <option value="">Barchasi</option>
                    <option value="oddiy" <?= (isset($_GET['sale_type']) && $_GET['sale_type'] == 'oddiy') ? 'selected' : '' ?>>🛍️ Dona</option>
                    <option value="optom" <?= (isset($_GET['sale_type']) && $_GET['sale_type'] == 'optom') ? 'selected' : '' ?>>📦 Optom</option>
                </select>
            </div>
            <button type="submit" class="btn-search ml-auto"><i class="fas fa-filter mr-2"></i> Filtrni qo'llash</button>
        </form>

        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="report-card">
                    <div class="report-icon" style="background: #EFF6FF; color: #2563EB;"><i class="fas fa-coins"></i></div>
                    <div><div class="report-title">Bugungi Summa</div><div class="report-value"><?= number_format($jami_savdo, 0, '.', ' ') ?> <span style="font-size:14px; color:#64748B;">UZS</span></div></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="report-card">
                    <div class="report-icon" style="background: #F8FAFC; color: #475569;"><i class="fas fa-receipt"></i></div>
                    <div><div class="report-title">Bugungi Cheklar</div><div class="report-value"><?= $cheklar_soni ?> <span style="font-size:14px; color:#64748B;">ta</span></div></div>
                </div> 
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="report-card">
                    <div class="report-icon" style="background: #FFF7ED; color: #EA580C;"><i class="fas fa-chart-pie"></i></div>
                    <div><div class="report-title">O'rtacha Chek</div><div class="report-value"><?= number_format($ortacha_chek, 0, '.', ' ') ?> <span style="font-size:14px; color:#64748B;">UZS</span></div></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="report-card">
                    <div class="report-icon" style="background: #FEF2F2; color: #DC2626;"><i class="fas fa-clock"></i></div>
                    <div><div class="report-title">Qizg'in Vaqt</div><div class="report-value" style="font-size: 20px;"><?= $peak_text ?></div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="data-table-wrapper">
                    <div class="data-table-header">
                        <h5><i class="fas fa-list mr-2 text-primary"></i> Tranzaksiyalar Ro'yxati</h5>
                        <span style="font-size: 13px; color: #64748B; font-weight: 600;">Jami <?= $history_res ? mysqli_num_rows($history_res) : 0 ?> ta topildi</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Chek ID / Turi</th>
                                    <th>Sana va Vaqt</th>
                                    <th>Mas'ul Xodim</th>
                                    <th>To'lov Turi</th>
                                    <th class="text-right">Summa</th>
                                    <th class="text-center">Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($history_res && mysqli_num_rows($history_res) > 0): while($row = mysqli_fetch_assoc($history_res)): ?>
                                <tr>
                                    <td>
                                        <a href="#" style="color: #4F46E5; text-decoration: none; font-weight: 700; display:block; margin-bottom: 4px;">#<?= str_pad($row['id'], 6, '0', STR_PAD_LEFT) ?></a>
                                        <?php if(isset($row['sale_type']) && $row['sale_type'] == 'optom'): ?>
                                            <span class="status-badge badge-optom"><i class="fas fa-box"></i> Optom</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-oddiy"><i class="fas fa-shopping-bag"></i> Dona</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #0F172A;"><?= date('d.m.Y', strtotime($row['sale_date'])) ?></div>
                                        <div style="font-size: 12px; color: #64748B;"><?= date('H:i:s', strtotime($row['sale_date'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 28px; height: 28px; border-radius: 50%; background: #F1F5F9; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; color: #475569;">
                                                <?= strtoupper(substr($row['seller_name'] ?? 'S', 0, 1)) ?>
                                            </div>
                                            <?= htmlspecialchars($row['seller_name'] ?? 'Tizim') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(isset($row['payment_method']) && $row['payment_method'] == 'naqd'): ?>
                                            <span class="status-badge badge-naqd"><i class="fas fa-money-bill-wave"></i> Naqd</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-karta"><i class="fas fa-credit-card"></i> Karta</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right" style="font-weight: 700; color: #0F172A; font-size: 15px;">
                                        <?= number_format($row['total_price'], 0, '.', ' ') ?> <span style="font-size: 12px; color: #64748B;">UZS</span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-view-receipt" onclick="openReceipt(<?= $row['id'] ?>)">
                                            <i class="fas fa-receipt mr-1"></i> Chek
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted font-weight-bold">Bunday ma'lumot topilmadi.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="side-panel" style="border-top: 4px solid #4F46E5;">
                    <h6 style="font-weight: 700; color: #0F172A; margin-bottom: 20px;"><i class="fas fa-fire text-danger mr-2"></i> Eng ko'p sotilganlar</h6>
                    <?php 
                    if($top_products_res && mysqli_num_rows($top_products_res) > 0): 
                        $top_items = []; $max_qty = 0;
                        while($r = mysqli_fetch_assoc($top_products_res)) { $top_items[] = $r; if($r['jami_miqdor'] > $max_qty) $max_qty = $r['jami_miqdor']; }
                        foreach($top_items as $item): $percent = ($max_qty > 0) ? round(($item['jami_miqdor'] / $max_qty) * 100) : 0;
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-end">
                                <span style="font-size: 14px; font-weight: 600; color: #334155;"><?= htmlspecialchars($item['name']) ?></span>
                                <span style="font-size: 14px; font-weight: 800; color: #4F46E5;"><?= floatval($item['jami_miqdor']) ?> ta</span>
                            </div>
                            <div class="progress-custom"><div class="bar" style="width: <?= $percent ?>%;"></div></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="text-center py-4 text-muted"><i class="fas fa-box-open mb-2" style="font-size: 28px; opacity: 0.5;"></i><br>Topilmadi</div>
                    <?php endif; ?>
                </div>

                <div class="side-panel">
                    <h6 style="font-weight: 700; color: #0F172A; margin-bottom: 20px;"><i class="fas fa-star text-warning mr-2"></i> Faol kassirlar</h6>
                    <?php $rank = 1; if($reyting_res && mysqli_num_rows($reyting_res) > 0): while($s = mysqli_fetch_assoc($reyting_res)): ?>
                        <div class="side-list-item">
                            <div style="width: 24px; font-weight: 800; color: #94A3B8; font-size: 14px;"><?= $rank ?>.</div>
                            <div class="flex-grow-1 ml-2">
                                <div style="font-weight: 600; color: #0F172A; font-size: 14px;"><?= htmlspecialchars($s['name'] ?? 'Noma\'lum') ?></div>
                                <div style="color: #64748B; font-size: 12px;"><?= $s['savdo_soni'] ?> ta xizmat</div>
                            </div>
                            <div class="text-right" style="font-weight: 700; color: #10B981; font-size: 14px;"><?= number_format($s['summa'], 0, '.', ' ') ?></div>
                        </div>
                    <?php $rank++; endwhile; else: ?>
                        <div class="text-center text-muted py-3" style="font-size: 13px;">Ma'lumot yo'q</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /panel-savdo -->

<!-- ═══ DASTAVKA TARIXI ═══ -->
<div id="panel-dastavka" style="display:none;">
    <!-- 3 stat karta -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="report-card">
                <div class="report-icon" style="background:#FFF7ED;color:#EA580C;">🚚</div>
                <div><div class="report-title">Jami buyurtmalar</div><div class="report-value"><?= $dav_cnt ?> <span style="font-size:14px;color:#64748B;">ta</span></div></div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="report-card">
                <div class="report-icon" style="background:#EFF6FF;color:#2563EB;">⏳</div>
                <div><div class="report-title">Kutilayotgan</div><div class="report-value" style="color:#EF4444;"><?= $dav_yangi ?> <span style="font-size:14px;color:#64748B;">ta</span></div></div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="report-card">
                <div class="report-icon" style="background:#ECFDF5;color:#059669;">💰</div>
                <div><div class="report-title">Jami summa</div><div class="report-value"><?= number_format($dav_summa,0,'.',' ') ?> <span style="font-size:14px;color:#64748B;">UZS</span></div></div>
            </div>
        </div>
    </div>

    <div class="data-table-wrapper">
        <div class="data-table-header">
            <h5>🚚 Online Buyurtmalar (Dastavka Tarixi)</h5>
            <span style="font-size:13px;color:#64748B;font-weight:600;"><?= $dastavka_res ? mysqli_num_rows($dastavka_res) : 0 ?> ta topildi</span>
        </div>
        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#ID / Turi</th>
                    <th>Mijoz</th>
                    <th>Telefon / Manzil</th>
                    <th>Holat</th>
                    <th>Summa</th>
                    <th>Sana</th>
                </tr>
            </thead>
            <tbody>
            <?php if($dastavka_res && mysqli_num_rows($dastavka_res)>0): while($dv=mysqli_fetch_assoc($dastavka_res)):
                $st = $dv['status'];
                if($st==='yangi')            { $stlbl='⏳ Kutilmoqda';        $stcl='#FFB547'; $stbg='#FFF4E5'; }
                elseif($st==='qabul_qilindi'){ $stlbl='📦 Tayyorlanmoqda';   $stcl='#059669'; $stbg='#ECFDF5'; }
                elseif($st==='rad_etildi')   { $stlbl='❌ Bekor qilindi';     $stcl='#DC2626'; $stbg='#FEF2F2'; }
                else                         { $stlbl='✅ Yakunlandi';        $stcl='#4F46E5'; $stbg='#EEF2FF'; }
                // Notedan telefon/manzil ajratish
                $note = $dv['note'] ?? '';
                $tel = $manz = '';
                if(preg_match('/Tel:\s*([^\|]+)/u',$note,$m)) $tel  = trim($m[1]);
                if(preg_match('/(Yetkazish|O\'zi oladi):\s*([^\|]+)/u',$note,$m)) $manz = trim($m[2] ?? $m[1]);
            ?>
            <tr>
                <td>
                    <span style="color:#4F46E5;font-weight:800;">#<?= str_pad($dv['id'],4,'0',STR_PAD_LEFT) ?></span><br>
                    <small style="color:#94A3B8;"><?= $dv['sale_type']==='yetkazish'?'🚚 Yetkazish':'🏪 O\'zi oladi' ?></small>
                </td>
                <td>
                    <div style="font-weight:700;color:#0F172A;"><?= htmlspecialchars($dv['mijoz_nomi']??'—') ?></div>
                    <div style="font-size:12px;color:#94A3B8;">@<?= htmlspecialchars($dv['mijoz_login']??'') ?></div>
                </td>
                <td>
                    <?php if($tel): ?><div style="font-weight:700;color:#0F172A;">📞 <?= htmlspecialchars($tel) ?></div><?php endif; ?>
                    <?php if($manz): ?><div style="font-size:12px;color:#64748B;">📍 <?= htmlspecialchars($manz) ?></div><?php endif; ?>
                    <?php if(!$tel && !$manz): ?><span style="color:#CBD5E1">—</span><?php endif; ?>
                </td>
                <td>
                    <span style="background:<?= $stbg ?>;color:<?= $stcl ?>;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:800;"><?= $stlbl ?></span>
                </td>
                <td style="font-weight:800;color:#0F172A;"><?= number_format($dv['total_price'],0,'.',' ') ?> <small style="color:#94A3B8;">UZS</small></td>
                <td style="font-size:12px;color:#64748B;"><?= date('d.m.y H:i',strtotime($dv['sale_date'])) ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="6" class="text-center py-5 text-muted">Hali dastavka buyurtmasi yo'q</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div><!-- /panel-dastavka -->

    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" style="font-weight: 700; color: #0F172A;"><i class="fas fa-print mr-2 text-primary"></i> Elektron Chek</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body bg-light mt-3" id="receiptContainer" style="display: flex; justify-content: center; padding: 20px;">
                    </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn-print" onclick="window.print()"><i class="fas fa-print mr-2"></i> Chop etish</button>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function showTab(tab) {
        document.getElementById('panel-savdo').style.display    = tab==='savdo'    ? 'block':'none';
        document.getElementById('panel-dastavka').style.display = tab==='dastavka' ? 'block':'none';
        document.getElementById('tab-savdo').style.background    = tab==='savdo'    ? '#0F172A':'#E2E8F0';
        document.getElementById('tab-savdo').style.color         = tab==='savdo'    ? '#fff':'#475569';
        document.getElementById('tab-dastavka').style.background = tab==='dastavka' ? '#0F172A':'#E2E8F0';
        document.getElementById('tab-dastavka').style.color      = tab==='dastavka' ? '#fff':'#475569';
    }
    // URL da tab=dastavka bo'lsa avtomatik ochish
    if(window.location.search.includes('tab=dastavka')) showTab('dastavka');

    // Chekni chaqiruvchi funksiya
    function openReceipt(saleId) {
        $('#receiptContainer').html('<div class="text-center text-muted my-4"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Kuting...</div>');
        $('#receiptModal').modal('show');

        $.ajax({
            url: window.location.pathname, 
            type: 'GET',
            data: { ajax_receipt_id: saleId },
            success: function(res) { $('#receiptContainer').html(res); },
            error: function() { $('#receiptContainer').html('<div class="text-danger text-center">Xatolik!</div>'); }
        });
    }
</script>
</body>
</html>