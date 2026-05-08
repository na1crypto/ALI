<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mijoz') {
    echo json_encode(['status'=>'error','message'=>'Ruxsat yo\'q']);
    exit;
}

require_once "../config/dokon_db.php";
$action = $_GET['action'] ?? '';

// ── Bir martalik migration (ustunlar yo'q bo'lsa qo'shadi) ──
@mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS image MEDIUMTEXT DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS min_qty INT NOT NULL DEFAULT 1");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'tolangan'");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS note TEXT DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL");

// ── Mahsulotlarni qidirish / barcha mahsulotlar ──
if ($action === 'products') {
    $q = mysqli_real_escape_string($conn, $_POST['q'] ?? '');
    $where = $q ? "AND (p.name LIKE '%$q%' OR p.barcode='$q')" : "";
    $sql = "SELECT p.id, p.name, p.price, p.optom_price, p.quantity, p.barcode,
                   IFNULL(p.min_qty, 1) AS min_qty, IFNULL(p.unit,'dona') AS unit,
                   IFNULL(p.image,'') AS image,
                   IFNULL(k.name,'') AS kategoriya
            FROM products p
            LEFT JOIN categories k ON p.category_id = k.id
            WHERE p.quantity > 0 $where
            ORDER BY p.name ASC
            LIMIT 60";
    $res = mysqli_query($conn, $sql);
    $list = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) {
        $min = max(1, (int)$r['min_qty']);
        $list[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'price'       => (float)$r['price'],
            'optom_price' => (float)$r['optom_price'],
            'quantity'    => (int)$r['quantity'],
            'barcode'     => $r['barcode'],
            'min_qty'     => $min,
            'unit'        => $r['unit'],
            'image'       => $r['image'],
            'kategoriya'  => $r['kategoriya'],
        ];
    }
    echo json_encode($list);
    exit;
}

// ── Savatga qo'shish ──
if ($action === 'add_cart') {
    $pid = (int)($_POST['id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id=$pid AND quantity>0"));
    if (!$row) { echo json_encode(['status'=>'error','message'=>'Mahsulot topilmadi']); exit; }

    $min_qty = max(1, (int)($row['min_qty'] ?? 1));

    // Minimal cheklov tekshiruvi
    if ($qty < $min_qty) {
        echo json_encode([
            'status'  => 'error',
            'message' => "Minimal buyurtma: $min_qty " . ($row['unit'] ?: 'dona')
        ]);
        exit;
    }
    // min_qty ga bo'linishi kerak
    $qty = (int)(round($qty / $min_qty) * $min_qty);
    if ($qty < $min_qty) $qty = $min_qty;

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $cart = &$_SESSION['cart'];
    $price = (float)$row['optom_price'] ?: (float)$row['price'];

    if (isset($cart[$pid])) {
        $new_qty = $cart[$pid]['qty'] + $qty;
        // Stokdan oshmasin, lekin min_qty ga karrali bo'lsin
        $max_allowed = (int)floor((int)$row['quantity'] / $min_qty) * $min_qty;
        $cart[$pid]['qty'] = min($new_qty, $max_allowed ?: (int)$row['quantity']);
    } else {
        $cart[$pid] = [
            'id'      => $pid,
            'name'    => $row['name'],
            'price'   => $price,
            'stock'   => (int)$row['quantity'],
            'min_qty' => $min_qty,
            'unit'    => $row['unit'] ?: 'dona',
            'image'   => $row['image'] ?? '',
            'qty'     => $qty,
        ];
    }
    $cart_total = 0;
    foreach ($cart as $_ci) $cart_total += ($_ci['price']??0) * ($_ci['qty']??0);
    echo json_encode([
        'status'     => 'ok',
        'cart_count' => array_sum(array_column($cart,'qty')),
        'cart_total' => $cart_total,
    ]);
    exit;
}

// ── Savatdan o'chirish ──
if ($action === 'remove_cart') {
    $pid = (int)($_POST['id'] ?? 0);
    if (isset($_SESSION['cart'][$pid])) unset($_SESSION['cart'][$pid]);
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Miqdorni o'zgartirish ──
if ($action === 'update_qty') {
    $pid = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    if ($qty <= 0) {
        unset($_SESSION['cart'][$pid]);
    } elseif (isset($_SESSION['cart'][$pid])) {
        $min = $_SESSION['cart'][$pid]['min_qty'] ?? 1;
        // min_qty ga karrali qilib yumalash
        $qty = (int)(round($qty / $min) * $min);
        if ($qty < $min) $qty = $min;
        $_SESSION['cart'][$pid]['qty'] = min($qty, $_SESSION['cart'][$pid]['stock']);
    }
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Savatni ko'rish ──
if ($action === 'get_cart') {
    $cart = $_SESSION['cart'] ?? [];
    $total = 0;
    foreach ($cart as $item) $total += $item['price'] * $item['qty'];
    echo json_encode(['items'=>array_values($cart),'total'=>$total]);
    exit;
}

// ── Buyurtma berish ──
if ($action === 'checkout') {
    $cart          = $_SESSION['cart'] ?? [];
    $yetkazish     = (int)($_POST['yetkazish'] ?? 0);
    $manzil        = mysqli_real_escape_string($conn, trim($_POST['manzil'] ?? ''));
    $phone         = mysqli_real_escape_string($conn, trim($_POST['phone']  ?? ''));
    $izoh          = mysqli_real_escape_string($conn, trim($_POST['izoh']   ?? ''));
    $pay_method_in = trim($_POST['payment_method'] ?? 'online');
    $allowed_pays  = ['naqd','karta','online'];
    $payment_method_val = in_array($pay_method_in, $allowed_pays) ? $pay_method_in : 'online';

    if (empty($cart)) {
        echo json_encode(['status'=>'error','message'=>'Savat bo\'sh!']); exit;
    }
    if (!$phone) {
        echo json_encode(['status'=>'error','message'=>'Telefon raqamingizni kiriting!']); exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $user_id = (int)$_SESSION['user_id'];
        $total = 0;
        foreach ($cart as $item) $total += $item['price'] * $item['qty'];
        $final = $total;

        // Telefon raqamini users jadvalida yangilash
        mysqli_query($conn, "UPDATE users SET phone='$phone' WHERE id=$user_id");

        $note_parts = ["Tel: $phone"];
        if ($yetkazish) $note_parts[] = "Yetkazish: $manzil";
        else            $note_parts[] = "O'zi oladi";
        if ($izoh)      $note_parts[] = $izoh;
        $note_esc  = mysqli_real_escape_string($conn, implode(' | ', $note_parts));
        $sale_type = $yetkazish ? 'yetkazish' : 'oddiy';

        // TiDB: id uchun MAX(id)+1
        $nid_r   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(id),0)+1 AS nid FROM sales"));
        $new_sid = (int)$nid_r['nid'];

        $sql_sale = "INSERT INTO sales (id, user_id, customer_id, total_price, payment_method, sale_type, note, status, source)
                     VALUES ($new_sid, $user_id, NULL, $final, '$payment_method_val', '$sale_type', '$note_esc', 'yangi', 'mijoz')";
        if (!mysqli_query($conn, $sql_sale)) {
            throw new Exception("Buyurtma yozilmadi: " . mysqli_error($conn));
        }
        $sale_id = $new_sid;

        // product_name ustunini qo'shish (bir martalik migration)
        @mysqli_query($conn, "ALTER TABLE sale_items ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) DEFAULT NULL");

        // sale_items uchun ham MAX(id)+1
        $si_nid_r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(id),0)+1 AS nid FROM sale_items"));
        $si_id    = (int)$si_nid_r['nid'];

        foreach ($cart as $item) {
            $p       = (int)$item['id'];
            $q       = (int)$item['qty'];
            $pr      = (float)$item['price'];
            $ti      = $pr * $q;
            $pname_e = mysqli_real_escape_string($conn, $item['name'] ?? 'Mahsulot');
            $sql_item = "INSERT INTO sale_items (id, sale_id, product_id, product_name, quantity, unit_price, price)
                         VALUES ($si_id, $sale_id, $p, '$pname_e', $q, $pr, $ti)";
            if (!mysqli_query($conn, $sql_item)) {
                throw new Exception("Mahsulot yozilmadi: " . mysqli_error($conn));
            }
            $si_id++;
            mysqli_query($conn, "UPDATE products SET quantity=quantity-$q WHERE id=$p");
        }

        mysqli_commit($conn);

        // ── Telegram admin bildirishnomasi ──
        $st_tg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT telegram_admin_chat, store_name FROM settings WHERE id=1"));
        $tg_chat = $st_tg['telegram_admin_chat'] ?? '';
        if ($tg_chat) {
            $store_tg  = $st_tg['store_name'] ?? "Do'kon";
            $ico       = $yetkazish ? '🚚 Yetkazish' : '🏪 O\'zi oladi';
            $pay_ico   = $payment_method_val === 'naqd' ? '💵 Naqd' : ($payment_method_val === 'karta' ? '💳 Karta' : '🌐 Online');
            $items_str = '';
            foreach ($cart as $ci) {
                $items_str .= "• " . $ci['name'] . " × " . $ci['qty'] . " = " . number_format($ci['price']*$ci['qty'],0,'.',' ') . " UZS\n";
            }
            $tg_msg =
                "🛒 *Yangi buyurtma \\#$sale_id*\n\n" .
                "$ico | $pay_ico\n" .
                "📱 Tel: " . str_replace(['-','(',')','+','.',' '],['\\-','\\(','\\)','\\+','\\.','\\'],$phone) . "\n" .
                ($yetkazish && $manzil ? "📍 Manzil: " . mb_substr($manzil,0,50) . "\n" : "") .
                ($izoh ? "📝 Izoh: " . mb_substr($izoh,0,80) . "\n" : "") .
                "\n$items_str\n" .
                "💰 *Jami: " . number_format($final,0,'.',' ') . " UZS*";

            // Escape for MarkdownV2
            $tg_msg = preg_replace_callback('/(?<!\\\\)([_\[\]()~`>#+=|{}.!])/', function($m){ return '\\'.$m[1]; }, $tg_msg);

            $bot_token = '8694101598:AAE54kDSYsl4NaTxNA2bw73-4sN9Jq4xVmE';
            $tg_data = ['chat_id'=>$tg_chat,'text'=>$tg_msg,'parse_mode'=>'MarkdownV2'];
            $tg_ch = curl_init("https://api.telegram.org/bot$bot_token/sendMessage");
            curl_setopt_array($tg_ch,[
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>json_encode($tg_data),
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>5,
            ]);
            curl_exec($tg_ch);
            curl_close($tg_ch);
        }

        $_SESSION['cart'] = [];
        echo json_encode(['status'=>'ok','sale_id'=>$sale_id,'total'=>$final]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

// ── Mening buyurtmalarim (mijoz uchun status ko'rish) ──
if ($action === 'my_orders') {
    $uid = (int)$_SESSION['user_id'];
    $res = mysqli_query($conn,
        "SELECT s.id, s.sale_date, s.total_price, s.status, s.sale_type, s.note
         FROM sales s
         WHERE s.user_id=$uid AND s.source='mijoz'
         ORDER BY s.id DESC LIMIT 10"
    );
    $orders = [];
    if ($res) while ($row = mysqli_fetch_assoc($res)) {
        $st = $row['status'];
        if ($st === 'yangi')          { $label = '⏳ Kutilmoqda';            $color = '#FFB547'; }
        elseif ($st === 'qabul_qilindi') { $label = '📦 Tayyorlanmoqda';    $color = '#05CD99'; }
        elseif ($st === 'rad_etildi')    { $label = '❌ Bekor qilindi';      $color = '#EE5D50'; }
        elseif ($st === 'tolangan')      { $label = '✅ Yakunlandi';         $color = '#4318FF'; }
        else                             { $label = $st;                      $color = '#A3AED0'; }
        $orders[] = [
            'id'          => (int)$row['id'],
            'sale_date'   => $row['sale_date'],
            'total_price' => (float)$row['total_price'],
            'status'      => $st,
            'status_label'=> $label,
            'status_color'=> $color,
            'sale_type'   => $row['sale_type'],
            'note'        => $row['note'],
        ];
    }
    echo json_encode(['status'=>'ok','orders'=>$orders]);
    exit;
}

// ── Savatni to'liq sinxronlash (client → server session)
if ($action === 'sync_cart') {
    $data = json_decode($_POST['cart'] ?? '{}', true);
    if (!is_array($data)) { echo json_encode(['status'=>'ok']); exit; }
    $newCart = [];
    foreach ($data as $pid => $item) {
        $pid = (int)$pid;
        if ($pid <= 0) continue;
        $row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM products WHERE id=$pid AND quantity>0"));
        if (!$row) continue;
        $qty   = max(1,(int)($item['qty']??1));
        $minQ  = max(1,(int)($row['min_qty']??1));
        $qty   = (int)(round($qty/$minQ)*$minQ);
        $price = (float)$row['optom_price'] ?: (float)$row['price'];
        $newCart[$pid] = [
            'id'      => $pid,
            'name'    => $row['name'],
            'price'   => $price,
            'stock'   => (int)$row['quantity'],
            'min_qty' => $minQ,
            'unit'    => $row['unit'] ?: 'dona',
            'qty'     => min($qty,(int)$row['quantity']),
        ];
    }
    $_SESSION['cart'] = $newCart;
    echo json_encode(['status'=>'ok']);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Noma\'lum so\'rov']);
