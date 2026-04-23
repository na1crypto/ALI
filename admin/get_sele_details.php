<?php
session_start();
require_once "../config/dokon_db.php"; 

// Agar tizimga kirmagan bo'lsa qaytarish
if (!isset($_SESSION['role'])) {
    echo "<div class='text-center text-danger py-4'>Ruxsat etilmagan!</div>";
    exit;
}

if (isset($_POST['sale_id'])) {
    $sale_id = intval($_POST['sale_id']);

    // Asosiy savdo ma'lumotlarini tortamiz
    $sale_res = mysqli_query($conn, "SELECT s.*, u.name as seller_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = $sale_id");
    
    if (!$sale_res || mysqli_num_rows($sale_res) == 0) {
        echo "<div class='text-center text-danger py-4'><i class='fas fa-exclamation-triangle mb-2 fa-2x'></i><br>Chek topilmadi!</div>";
        exit;
    }
    $sale = mysqli_fetch_assoc($sale_res);

    // Chek ichidagi tovarlar ro'yxatini tortamiz
    $items_res = mysqli_query($conn, "SELECT si.*, p.name FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = $sale_id");

    $is_optom = (isset($sale['sale_type']) && $sale['sale_type'] == 'optom');
    $badge_class = $is_optom ? 'badge-danger' : 'badge-primary';
    $sale_type_text = $is_optom ? 'OPTOM' : 'DONA (Oddiy)';
    
    $payment_icon = (isset($sale['payment_method']) && $sale['payment_method'] == 'naqd') ? '<i class="fas fa-money-bill-wave text-success"></i> Naqd' : '<i class="fas fa-credit-card text-info"></i> Karta';

    // Modal uchun HTML darcha tayyorlaymiz
    echo "<div class='receipt-details-wrapper'>";
    
    // Chek tepasi (Sarlavha qismi)
    echo "<div class='d-flex justify-content-between align-items-start border-bottom pb-3 mb-3'>";
    echo "  <div>";
    echo "      <h5 class='font-weight-bold mb-1 text-dark'>Chek #".str_pad($sale['id'], 6, '0', STR_PAD_LEFT)." <span class='badge $badge_class ml-2' style='font-size: 11px;'>$sale_type_text</span></h5>";
    echo "      <div class='text-muted' style='font-size: 13px;'><i class='far fa-calendar-alt mr-1'></i> ".date('d.m.Y H:i', strtotime($sale['sale_date']))."</div>";
    echo "  </div>";
    echo "  <div class='text-right'>";
    echo "      <div class='font-weight-bold text-dark' style='font-size: 14px;'><i class='fas fa-user-tie text-muted mr-1'></i> ".htmlspecialchars($sale['seller_name'] ?? 'Tizim')."</div>";
    echo "      <div style='font-size: 13px;' class='mt-1'>To'lov: <b>$payment_icon</b></div>";
    echo "  </div>";
    echo "</div>";

    // Tovarlar ro'yxati (Jadval)
    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm table-hover' style='font-size: 14px;'>";
    echo "  <thead class='bg-light text-muted'>";
    echo "      <tr>";
    echo "          <th style='border-top: none;'>#</th>";
    echo "          <th style='border-top: none;'>Mahsulot nomi</th>";
    echo "          <th style='border-top: none;' class='text-center'>Miqdori</th>";
    echo "          <th style='border-top: none;' class='text-right'>Narxi</th>";
    echo "          <th style='border-top: none;' class='text-right'>Jami</th>";
    echo "      </tr>";
    echo "  </thead>";
    echo "  <tbody>";
    
    $n = 1;
    $calc_total = 0;
    if ($items_res && mysqli_num_rows($items_res) > 0) {
        while ($item = mysqli_fetch_assoc($items_res)) {
            $summa = $item['quantity'] * $item['price'];
            $calc_total += $summa;
            echo "<tr>";
            echo "  <td class='text-muted'>$n</td>";
            echo "  <td class='font-weight-bold text-dark'>".htmlspecialchars($item['name'] ?? 'Noma\'lum mahsulot')."</td>";
            echo "  <td class='text-center'>".floatval($item['quantity'])."</td>";
            echo "  <td class='text-right'>".number_format($item['price'], 0, '.', ' ')."</td>";
            echo "  <td class='text-right font-weight-bold text-primary'>".number_format($summa, 0, '.', ' ')."</td>";
            echo "</tr>";
            $n++;
        }
    } else {
        echo "<tr><td colspan='5' class='text-center text-muted py-3'>Ushbu chekda mahsulotlar ro'yxati topilmadi.</td></tr>";
    }
    
    echo "  </tbody>";
    echo "</table>";
    echo "</div>";

    // Hisob-kitob qismi (Chegirma va Yakuniy summa)
    echo "<div class='mt-3 p-3 bg-light rounded' style='border: 1px dashed #cbd5e1;'>";
    
    if (isset($sale['discount']) && $sale['discount'] > 0) {
        echo "<div class='d-flex justify-content-between mb-2 text-muted' style='font-size: 14px;'>";
        echo "  <span>Asosiy summa:</span>";
        echo "  <span class='font-weight-bold'>".number_format($calc_total, 0, '.', ' ')." UZS</span>";
        echo "</div>";
        echo "<div class='d-flex justify-content-between mb-2 text-danger' style='font-size: 14px;'>";
        echo "  <span>Chegirma:</span>";
        echo "  <span class='font-weight-bold'>- ".number_format($sale['discount'], 0, '.', ' ')." UZS</span>";
        echo "</div>";
    }

    $final_total = isset($sale['total_price']) ? $sale['total_price'] : 0;

    echo "  <div class='d-flex justify-content-between align-items-center mt-2 pt-2' style='border-top: 1px solid #e2e8f0;'>";
    echo "      <span class='font-weight-bold text-uppercase text-muted' style='font-size: 13px; letter-spacing: 1px;'>Yakuniy to'lov:</span>";
    echo "      <span class='font-weight-bold text-dark' style='font-size: 22px;'>".number_format($final_total, 0, '.', ' ')." <span style='font-size: 14px; color: #64748b;'>UZS</span></span>";
    echo "  </div>";
    echo "</div>";

    echo "</div>";
    exit;
}
?>