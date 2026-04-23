<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once "../../config/dokon_db.php";

$query = "";
if (isset($_POST["query"])) {
    $query = mysqli_real_escape_string($conn, $_POST["query"]);
}

$sql = "SELECT * FROM products ";
if ($query != "") { $sql .= " WHERE name LIKE '%$query%' OR barcode LIKE '%$query%' "; }
$sql .= " ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
$output = '';

if (!$result) {
    echo '<tr><td colspan="6" class="text-center text-danger"><b>Bazada xato:</b> ' . mysqli_error($conn) . '</td></tr>';
    exit;
}

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        $stock_class = ($row['quantity'] <= 5) ? 'text-danger font-weight-bold' : 'text-dark font-weight-bold';
        $expiry = !empty($row['expiry_date']) ? date('d.m.Y', strtotime($row['expiry_date'])) : '<span style="color:#CBD5E1; font-size:12px;">Muddatsiz</span>';
        $unit = $row['unit'] ?? 'dona';
        $barcode = !empty($row['barcode']) ? $row['barcode'] : '---';
        $price = !empty($row['price']) ? number_format($row['price'], 0, '.', ' ') : '0';

        $output .= '
        <tr style="transition: 0.2s;">
            <td style="color: #64748B; font-family: monospace;">'.$barcode.'</td>
            <td style="color: #0F172A; font-weight: 600; font-size: 15px;">'.htmlspecialchars($row['name']).'</td>
            <td style="color: #10B981; font-weight: 700;">'.$price.' <span style="font-size:12px; font-weight:500;">UZS</span></td>
            <td class="'.$stock_class.'">'.$row['quantity'].' <span style="font-weight:500; font-size:12px;">'.$unit.'</span></td>
            <td>'.$expiry.'</td>
            <td class="text-right">
                <a href="tahrirlash.php?id='.$row['id'].'" title="Tahrirlash" 
                   style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:10px; background:#EEF2FF; color:#4F46E5; text-decoration:none; margin-right:8px; transition:0.2s;">
                   <i class="fas fa-pen" style="font-size:14px;"></i>
                </a>
                
                <a href="ochirish.php?id='.$row['id'].'" title="O\'chirish" onclick="return confirm(\'Rostdan ham o\\\'chirasizmi?\')"
                   style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:10px; background:#FEF2F2; color:#EF4444; text-decoration:none; transition:0.2s;">
                   <i class="fas fa-trash-alt" style="font-size:14px;"></i>
                </a>
            </td>
        </tr>';
    }
} else {
    $output = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-box-open mb-3" style="font-size: 40px; opacity:0.3;"></i><br>Hozircha mahsulot yo\'q yoki topilmadi.</td></tr>';
}
echo $output;
?>