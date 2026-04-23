<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Xatolarni ekranga sochmaslik uchun
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 🔥 AQLLI ULANISH: Baza faylini qayerda bo'lsa ham topadi
$db_paths = [
    "../config/dokon_db.php",
    "../../config/dokon_db.php",
    "../../../config/dokon_db.php"
];
$db_found = false;
foreach($db_paths as $path) {
    if(file_exists($path)) {
        require_once $path;
        $db_found = true;
        break;
    }
}

if(!$db_found) {
    die("XATO: Baza ulanishi (dokon_db.php) topilmadi.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $user_msg = mysqli_real_escape_string($conn, $_POST['message']);
    
    // ==========================================
    // 1. DO'KON NOMINI BAZADAN OLISH (NASTROYKA)
    // ==========================================
    $store_name = "SMART POS"; // Zaxira nom
    $st_query = mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1");
    if($st_query && mysqli_num_rows($st_query) > 0) {
        $st_data = mysqli_fetch_assoc($st_query);
        $store_name = $st_data['store_name'] ? $st_data['store_name'] : $store_name;
    }
    
    // 2. MA'LUMOTLARNI YIG'ISH (Bugun, Kecha, Top tovarlar)
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime("-1 days"));

    $q_today = mysqli_query($conn, "SELECT 
        SUM(total_price) as jami,
        SUM(CASE WHEN payment_method='naqd' THEN total_price ELSE 0 END) as naqd,
        SUM(CASE WHEN payment_method='karta' THEN total_price ELSE 0 END) as karta
        FROM sales WHERE DATE(sale_date) = '$today'");
    $res_t = mysqli_fetch_assoc($q_today);
    $bugun_jami = number_format(($res_t['jami'] ?? 0), 0, '.', ' ');
    
    $q_yest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_price) as jami FROM sales WHERE DATE(sale_date) = '$yesterday'"));
    $kecha_jami = number_format(($q_yest['jami'] ?? 0), 0, '.', ' ');

    $q_top = mysqli_query($conn, "SELECT p.name, SUM(si.quantity) as soni 
        FROM sale_items si JOIN products p ON si.product_id = p.id 
        GROUP BY si.product_id ORDER BY soni DESC LIMIT 5");
    
    $top_tovarlar = "";
    if($q_top) {
        while($t = mysqli_fetch_assoc($q_top)) { $top_tovarlar .= $t['name'] . ", "; }
    }

    // ==========================================
    // 3. GEMINI API VA DINAMIK PROMPT
    // ==========================================
    $apiKey = "AIzaSyDo8RHyMGKimbVj_bF5B_L_zM-3t7DRbyA"; 
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

    // 🔥 MANA SHU YERDA ZIFRA/ZARIFA OLIB TASHLANDI VA BAZAGA BOG'LANDI
    $prompt = "Siz '{$store_name}' do'konining aqlli moliyaviy tahlilchisisiz. 
               Sizning ismingiz: '{$store_name} AI'. 
               Mijozlarga xushmuomala bo'ling.
               
               Do'kon Statistikasi:
               - Bugungi tushum: $bugun_jami so'm (Naqd: ".number_format($res_t['naqd'] ?? 0).", Karta: ".number_format($res_t['karta'] ?? 0).")
               - Kechagi tushum: $kecha_jami so'm
               - Hozirgi eng yaxshi sotilayotgan tovarlar: $top_tovarlar
               
               Foydalanuvchi savoli: '$user_msg'. 
               Javobingizni tahliliy, qisqa, tushunarli va faqat o'zbek tilida (lotin alifbosida), markdown yordamida chiroyli qilib yozing.";

    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // SSL xatolarini chetlab o'tish
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "API bilan aloqa uzildi. Sababi: " . $err;
    } else {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            echo $result['candidates'][0]['content']['parts'][0]['text'];
        } elseif (isset($result['error']['message'])) {
            echo "API Xatosi: " . $result['error']['message'];
        } else {
            echo "Kechirasiz, tahlil qilishda noma'lum xatolik bo'ldi.";
        }
    }
} else {
    echo "Faqat POST so'rovlar qabul qilinadi!";
}
?>