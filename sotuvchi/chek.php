<?php
// CHEK QOG'OZIGA HECH QANDAY PHP XATOLAR CHIQMASLIGI UCHUN:
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once "../config/dokon_db.php"; 

if (!isset($_GET['id'])) { die("Chek raqami (ID) ko'rsatilmadi!"); }
$sale_id = (int)$_GET['id'];

// Do'kon sozlamalari va Savdo ma'lumotlarini bazadan olish
$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
$sale = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM sales WHERE id=$sale_id"));

if (!$sale) { die("Bunday chek bazada topilmadi!"); }

// Shu chekga tegishli mahsulotlarni tortib olish
$items = mysqli_query($conn, "
    SELECT si.*, p.name 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id=$sale_id
");

// Xatolik bermasligi uchun sozlamalarni xavfsiz o'zgaruvchilarga olamiz
$store_name = !empty($st['store_name']) ? $st['store_name'] : 'SMART POS';
$r_header = !empty($st['receipt_header']) ? $st['receipt_header'] : '';
$r_footer = !empty($st['receipt_footer']) ? $st['receipt_footer'] : 'XARIDINGIZ UCHUN RAHMAT!';
$payment_method = !empty($sale['payment_method']) ? $sale['payment_method'] : 'NAQD';
$kassir = !empty($_SESSION['name']) ? $_SESSION['name'] : 'Kassir';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Chek #<?= $sale_id ?></title>
    <style>
        body { background: #525659; display: flex; justify-content: center; padding: 20px; font-family: 'Courier New', Courier, monospace; margin: 0; }
        .receipt-container { background: #fff; width: 320px; padding: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.3); color: #000; font-size: 13px; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .flex-between { display: flex; justify-content: space-between; }
        .divider { border-bottom: 1px dashed #000; margin: 8px 0; }
        .item-row { margin-bottom: 10px; line-height: 1.2; }
        .item-details { color: #333; font-size: 11px; margin-top: 2px; }
        
        @media print {
            body { background: #fff; padding: 0; display: block; }
            .receipt-container { box-shadow: none; width: 100%; max-width: 100%; padding: 0; margin: 0; }
        }
    </style>
</head>
<body onload="window.print(); window.onafterprint = function(){ window.close(); }">
    
    <div class="receipt-container">
        <div class="text-center">
            <h2 style="margin: 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 900;">
                <?= htmlspecialchars($store_name) ?>
            </h2>
            <?php if($r_header): ?>
                <div style="font-size: 12px; margin-top: 5px;"><?= htmlspecialchars($r_header) ?></div>
            <?php endif; ?>
            <div class="divider"></div>
            <div style="font-size: 13px; font-weight: bold;">Chek raqami: #<?= $sale_id ?></div>
            <div style="font-size: 12px;">Sana: <?= date('d.m.Y H:i', strtotime($sale['sale_date'])) ?></div>
            <div class="divider"></div>
        </div>

        <div id="items">
            <?php 
            $i = 1; 
            $totalQQS = 0;
            while($item = mysqli_fetch_assoc($items)): 
                // Matematik hisoblar
                $price = isset($item['unit_price']) ? $item['unit_price'] : (isset($item['price']) ? $item['price'] : 0);
                $sum = $item['quantity'] * $price;
                $qqs = $sum * 0.12; 
                $totalQQS += $qqs;
                
                $sku = "47800" . rand(100000, 999999) . $item['product_id'];
                $mxik = "01701001006000000";
            ?>
            <div class="item-row">
                <div class="font-bold"><?= $i++ ?>) <?= htmlspecialchars($item['name']) ?></div>
                <div class="flex-between" style="margin-top: 3px;">
                    <span><?= $item['quantity'] ?> * <?= number_format($price, 0, '.', ' ') ?></span>
                    <span class="font-bold"><?= number_format($sum, 0, '.', ' ') ?></span>
                </div>
                <div class="flex-between item-details" style="padding-left: 10px;">
                    <span>sh.j QQS 12%</span>
                    <span><?= number_format($qqs, 2, '.', ' ') ?></span>
                </div>
                <div class="item-details" style="padding-left: 10px;">Sh.K/SKU: <?= $sku ?></div>
                <div class="item-details" style="padding-left: 10px;">MXIK: <?= $mxik ?></div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="divider"></div>
        
        <div class="flex-between font-bold" style="font-size: 16px;">
            <span>JAMI TO'LOV:</span>
            <span><?= number_format($sale['total_price'], 0, '.', ' ') ?> UZS</span>
        </div>
        <div class="flex-between" style="font-size: 12px; margin-top: 4px;">
            <span>SHU JUMLADAN QQS:</span>
            <span><?= number_format($totalQQS, 2, '.', ' ') ?> UZS</span>
        </div>
        
        <div class="divider"></div>
        
        <div class="flex-between font-bold" style="font-size: 13px;">
            <span>TO'LOV TURI:</span>
            <span style="text-transform: uppercase;"><?= htmlspecialchars($payment_method) ?></span>
        </div>
        
        <div class="divider"></div>
        
        <div class="text-center" style="font-size: 12px; margin-top: 15px;">
            <p style="margin:0;">Sotuvchi: <?= htmlspecialchars($kassir) ?></p>
            <b style="text-transform: uppercase; display:block; margin-top: 10px;"><?= htmlspecialchars($r_footer) ?></b>
        </div>
    </div>
</body>
</html> 