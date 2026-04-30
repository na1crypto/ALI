<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once "../../config/dokon_db.php";

$output = '';

// ==========================================
// 1. QIDIRUV VA FILTR MANTIQI
// ==========================================
$where = "1=1"; // Boshlang'ich (Hamma narsani ko'rsatish)

// Agar qidiruv so'zi yozilgan bo'lsa
if (isset($_POST["query"]) && !empty(trim($_POST["query"]))) {
    $search = mysqli_real_escape_string($conn, trim($_POST["query"]));
    $where .= " AND (p.name LIKE '%$search%' OR p.barcode LIKE '%$search%')";
}

// Agar ma'lum bir kategoriya tanlangan bo'lsa (Barchasi bo'lmasa)
if (isset($_POST["category_filter"]) && !empty($_POST["category_filter"])) {
    $cat_id = (int)$_POST["category_filter"];
    $where .= " AND p.category_id = $cat_id";
}

// ==========================================
// 2. BAZADAN MA'LUMOTLARNI TORTISH (JOIN BILAN)
// ==========================================
// Mahsulotlar jadvaliga (products) Kategoriyalar jadvalini (categories) ulaymiz (JOIN). 
// Maqsad: Kategoriya ID si emas, uning Nomi kerak bizga!
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $where 
        ORDER BY p.id DESC";

$result = mysqli_query($conn, $sql);

// ==========================================
// 3. JADVALNI YASASH VA HTML QAYTARISH
// ==========================================
if (!$result) {
    echo '<tr><td colspan="6" class="text-center text-danger py-4"><b>Bazada xato yuz berdi:</b> ' . mysqli_error($conn) . '</td></tr>';
    exit;
}

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        // Zaxira ko'rsatkichi (5 tadan kam qolsa qizil bo'ladi)
        $stock_class = ($row['quantity'] <= 5) ? 'color: #EF4444; font-weight: 800;' : 'color: #0F172A; font-weight: 700;';
        
        // Muddati — rang bilan (tugayotgan bo'lsa sariq/qizil)
        if (!empty($row['expiry_date'])) {
            $exp_ts    = strtotime($row['expiry_date']);
            $days_left = (int)(($exp_ts - time()) / 86400);
            $exp_str   = date('d.m.Y', $exp_ts);
            if ($days_left < 0)       $expiry = '<span style="color:#ef4444;font-weight:800;">❌ '.$exp_str.' (tugagan)</span>';
            elseif ($days_left <= 10) $expiry = '<span style="color:#f59e0b;font-weight:800;">⚠️ '.$exp_str.' ('.$days_left.' kun)</span>';
            else                      $expiry = '<span style="color:#475569;font-weight:600;">'.$exp_str.'</span>';
        } else {
            $expiry = '<span style="color:#94A3B8;font-size:12px;font-weight:500;">—</span>';
        }
        
        // Bo'sh kelishi mumkin bo'lgan qiymatlar
        $unit = $row['unit'] ?? 'dona';
        $barcode = !empty($row['barcode']) ? htmlspecialchars($row['barcode']) : '<span class="text-muted">Yo\'q</span>';
        $price = !empty($row['price']) ? number_format($row['price'], 0, '.', ' ') : '0';
        $cat_name = !empty($row['category_name']) ? htmlspecialchars($row['category_name']) : 'Boshqa';

        // HTML qator
        $output .= '
        <tr style="transition: 0.2s;">
            <td style="color: #4F46E5; font-family: monospace; font-weight: 600;">#'.$barcode.'</td>
            
            <td>
                <div style="color: #0F172A; font-weight: 700; font-size: 15px;">'.htmlspecialchars($row['name']).'</div>
                <div style="color: #64748B; font-size: 12px; font-weight: 500;"><i class="fas fa-tag mr-1" style="font-size:10px;"></i> '.$cat_name.'</div>
            </td>
            
            <td style="color: #10B981; font-weight: 700; font-size: 15px;">
                '.$price.' <span style="font-size:11px; font-weight:600; color:#64748B;">UZS</span>
            </td>
            
            <td style="'.$stock_class.' font-size: 16px;">
                '.$row['quantity'].' <span style="font-weight:600; font-size:12px; color:#64748B;">'.$unit.'</span>
            </td>
            
            <td style="color: #475569; font-weight: 500; font-size: 14px;">'.$expiry.'</td>
            
            <td class="text-right">
                <a href="tahrirlash.php?id='.$row['id'].'" title="Tahrirlash" 
                   style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:10px; background:#EEF2FF; color:#4F46E5; text-decoration:none; margin-right:8px; transition:0.2s;">
                   <i class="fas fa-pen" style="font-size:14px;"></i>
                </a>
                
                <a href="ochirish.php?id='.$row['id'].'" title="O\'chirish" onclick="return confirm(\'Rostdan ham bu mahsulotni o\\\'chirasizmi?\')"
                   style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:10px; background:#FEF2F2; color:#EF4444; text-decoration:none; transition:0.2s;">
                   <i class="fas fa-trash-alt" style="font-size:14px;"></i>
                </a>
            </td>
        </tr>';
    }
} else {
    $output = '
    <tr>
        <td colspan="6" class="text-center py-5 text-muted">
            <div style="width: 60px; height: 60px; background: #F1F5F9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-box-open" style="font-size: 24px; color: #94A3B8;"></i>
            </div>
            <h6 style="color: #475569; font-weight: 600;">Mahsulot topilmadi</h6>
            <div style="font-size: 13px;">Qidiruv so\'zini yoki kategoriyani o\'zgartirib ko\'ring.</div>
        </td>
    </tr>';
}

echo $output;
?>