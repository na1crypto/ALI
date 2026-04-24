<?php
// Barcha PHP xatolarni yashirish va faqat JSON formatida qaytarish (Dastur qotmasligi uchun)
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

session_start();

// =================================================================
// 0. XAVFSIZLIK: Faqat tizimga kirgan kassirlar ishlata oladi!
// =================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sotuvchi') {
    echo json_encode(['status' => 'error', 'message' => 'Ruxsat etilmagan foydalanuvchi! Dasturga qayta kiring.']);
    exit;
}

// Bazaga ulanish
require_once "../config/dokon_db.php"; 

$action = $_GET['action'] ?? '';

// =================================================================
// 1-FUNKSIYA: MAHSULOTLARNI QIDIRISH (Shtrix-kod va Nom orqali)
// =================================================================
if ($action == 'search_product') {
    $q = mysqli_real_escape_string($conn, $_POST['query'] ?? '');
    
    if (empty($q)) {
        echo json_encode([]);
        exit;
    }

    // Shtrix-koddan ham, nomidan ham izlaydi va Ulgurji (optom) narxini qo'shib olib keladi
    $query = "SELECT id, name, price, optom_price, quantity, barcode 
              FROM products 
              WHERE (name LIKE '%$q%' OR barcode = '$q') 
              AND quantity > 0 
              ORDER BY name ASC 
              LIMIT 20";
    
    $res = mysqli_query($conn, $query);
    $products = [];
    
    if($res) {
        while($row = mysqli_fetch_assoc($res)) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => htmlspecialchars($row['name']),
                'price' => (float)$row['price'],
                'optom_price' => isset($row['optom_price']) ? (float)$row['optom_price'] : 0,
                'quantity' => (int)$row['quantity'],
                'barcode' => $row['barcode']
            ];
        }
    }
    echo json_encode($products);
    exit;
}

// =================================================================
// 2-FUNKSIYA: SAVDONI YAKUNLASH VA OMBORDAN AYIRISH
// =================================================================
if ($action == 'complete_sale') {
    $items_json = $_POST['items'] ?? '[]';
    $items = json_decode($items_json, true);
    $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : 'naqd';
    $discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0;
    
    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Savat bo\'sh yoki xato mahsulot!']);
        exit;
    }

    // TRANZAKSIYANI BOSHLASH (Biri xato qilsa, hammasi bekor bo'ladi - Xavfsizlik!)
    mysqli_begin_transaction($conn);

    try {
        $total_price = 0;
        
        // Jami summani Backendni o'zida qaytadan hisoblaymiz (Front-end'dagi firibgarlikni oldini olish uchun)
        foreach($items as $i) { 
            $price = (float)$i['price'];
            $count = (int)$i['count'];
            $total_price += ($price * $count); 
        }

        $final_price = $total_price - $discount;
        if ($final_price < 0) { $final_price = 0; }
        
        // Qaysi Kassir sotayotganini aniqlaymiz
        $user_id = 1; // Ehtiyot sharti
        if(isset($_SESSION['user_id'])) { $user_id = $_SESSION['user_id']; }
        elseif(isset($_SESSION['id'])) { $user_id = $_SESSION['id']; }

        // --- 1-QADAM: `sales` (Chek) jadvaliga yozish ---
        $next_sale_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(id),0)+1 AS nid FROM sales"))['nid'];
        $sale_query = "INSERT INTO sales (id, user_id, total_price, payment_method) VALUES ('$next_sale_id', '$user_id', '$final_price', '$payment_method')";
        if (!mysqli_query($conn, $sale_query)) {
            throw new Exception("Sales jadvaliga yozib bo'lmadi: " . mysqli_error($conn));
        }
        
        // Chek raqami (ID)
        $sale_id = $next_sale_id;

        // --- 2-QADAM: `sale_items` (Ichidagi tovarlar) jadvaliga yozish ---
        foreach($items as $item) {
            $p_id = (int)$item['id'];
            $qty = (int)$item['count'];
            $price = (float)$item['price'];

            $next_item_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(id),0)+1 AS nid FROM sale_items"))['nid'];
            $total_item_price = $price * $qty;
            $item_query = "INSERT INTO sale_items (id, sale_id, product_id, price, quantity, unit_price) VALUES ('$next_item_id', '$sale_id', '$p_id', '$total_item_price', '$qty', '$price')";
            if (!mysqli_query($conn, $item_query)) {
                throw new Exception("Sale_items ga yozishda muammo: " . mysqli_error($conn));
            }

            // --- 3-QADAM: OMBORDAN TOVARNI KEMAYTIRISH ---
            $update_stock = "UPDATE products SET quantity = quantity - $qty WHERE id = $p_id";
            if(!mysqli_query($conn, $update_stock)) {
                throw new Exception("Ombordagi qoldiqni yangilab bo'lmadi!");
            }
        }

        // --- 4-QADAM: HAMMASI JOYIDA BO'LSA TIZIMGA SAQLASH! ---
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'sale_id' => $sale_id]);

    } catch (Exception $e) {
        // Agar biror bosqichda xato bo'lsa, qilingan barcha ishlarni orqaga qaytaramiz!
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Boshqa tushunarsiz so'rov kelib qolsa
echo json_encode(['status' => 'error', 'message' => 'Noma\'lum so\'rov (Invalid API Action)']);
exit;
?>