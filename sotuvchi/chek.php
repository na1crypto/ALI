<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
require_once "../config/dokon_db.php";

if (!isset($_GET['id'])) die("Chek ID yo'q!");
$sale_id = (int)$_GET['id'];

$st   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
$sale = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM sales WHERE id=$sale_id"));
if (!$sale) die("Chek topilmadi!");

$items = mysqli_query($conn, "
    SELECT si.quantity, si.unit_price, p.name
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = $sale_id
");

$store_name = $st['store_name'] ?? 'ELEVEN';
$phone      = $st['phone']      ?? '';
$address    = $st['address']    ?? '';
$footer     = $st['receipt_footer'] ?? 'XARIDINGIZ UCHUN RAHMAT!';
$kassir     = $_SESSION['name'] ?? 'Kassir';
$pay_method = strtoupper($sale['payment_method'] ?? 'NAQD');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title>Chek #<?= $sale_id ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: #555;
    display: flex;
    justify-content: center;
    padding: 10px;
  }
  .chek {
    background: #fff;
    width: 280px;
    padding: 10px 12px;
  }
  .center { text-align: center; }
  .bold   { font-weight: bold; }
  .line   { border-top: 1px dashed #000; margin: 6px 0; }
  .row    { display: flex; justify-content: space-between; margin: 2px 0; }
  .store  { font-size: 16px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
  .total  { font-size: 14px; font-weight: bold; }
  @media print {
    body { background: #fff; padding: 0; }
    .chek { width: 100%; box-shadow: none; }
  }
</style>
</head>
<body onload="window.print(); window.onafterprint = function(){ window.close(); }">
<div class="chek">

  <div class="center">
    <div class="store"><?= htmlspecialchars($store_name) ?></div>
    <?php if($phone): ?><div><?= htmlspecialchars($phone) ?></div><?php endif; ?>
    <?php if($address): ?><div><?= htmlspecialchars($address) ?></div><?php endif; ?>
  </div>

  <div class="line"></div>

  <div class="row">
    <span>Chek:</span><span class="bold">#<?= $sale_id ?></span>
  </div>
  <div class="row">
    <span>Sana:</span><span><?= date('d.m.Y H:i', strtotime($sale['sale_date'])) ?></span>
  </div>
  <div class="row">
    <span>Kassir:</span><span><?= htmlspecialchars($kassir) ?></span>
  </div>

  <div class="line"></div>

  <?php $i=1; while($item = mysqli_fetch_assoc($items)):
    $price = (float)($item['unit_price'] ?? 0);
    $qty   = (int)$item['quantity'];
    $sum   = $price * $qty;
  ?>
  <div style="margin-bottom:5px;">
    <div class="bold"><?= $i++ ?>. <?= htmlspecialchars($item['name']) ?></div>
    <div class="row">
      <span><?= $qty ?> x <?= number_format($price,0,'.',' ') ?></span>
      <span class="bold"><?= number_format($sum,0,'.',' ') ?> uzs</span>
    </div>
  </div>
  <?php endwhile; ?>

  <div class="line"></div>

  <div class="row total">
    <span>JAMI:</span>
    <span><?= number_format($sale['total_price'],0,'.',' ') ?> UZS</span>
  </div>
  <div class="row">
    <span>To'lov:</span><span><?= $pay_method ?></span>
  </div>

  <div class="line"></div>

  <div class="center" style="margin-top:6px;">
    <div class="bold"><?= htmlspecialchars($footer) ?></div>
  </div>

</div>
</body>
</html>
