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
    $cart_total = array_sum(array_map(fn($i)=>$i['price']*$i['qty'], $cart));
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
    $cart      = $_SESSION['cart'] ?? [];
    $yetkazish = (int)($_POST['yetkazish'] ?? 0);
    $manzil    = mysqli_real_escape_string($conn, trim($_POST['manzil'] ?? ''));
    $izoh      = mysqli_real_escape_string($conn, trim($_POST['izoh'] ?? ''));

    if (empty($cart)) {
        echo json_encode(['status'=>'error','message'=>'Savat bo\'sh!']); exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $user_id = (int)$_SESSION['user_id'];
        $total = 0;
        foreach ($cart as $item) $total += $item['price'] * $item['qty'];
        $final = $total;

        $note_text = $yetkazish
            ? "Yetkazish: $manzil" . ($izoh ? ". $izoh" : "")
            : "Olib ketadi" . ($izoh ? ". $izoh" : "");
        $note_esc  = mysqli_real_escape_string($conn, $note_text);
        $sale_type = $yetkazish ? 'yetkazish' : 'oddiy';

        $sql_sale = "INSERT INTO sales (id, user_id, customer_id, total_price, payment_method, sale_type, note)
                     VALUES (NULL, $user_id, NULL, $final, 'online', '$sale_type', '$note_esc')";
        if (!mysqli_query($conn, $sql_sale)) {
            $sql_sale = "INSERT INTO sales (id, user_id, total_price, payment_method, note)
                         VALUES (NULL, $user_id, $final, 'naqd', '$note_esc')";
            if (!mysqli_query($conn, $sql_sale)) {
                throw new Exception("Buyurtma yozilmadi: " . mysqli_error($conn));
            }
        }
        $sale_id = mysqli_insert_id($conn);

        foreach ($cart as $item) {
            $p  = (int)$item['id'];
            $q  = (int)$item['qty'];
            $pr = (float)$item['price'];
            $total_item = $pr * $q;
            $sql_item = "INSERT INTO sale_items (id, sale_id, product_id, quantity, unit_price, price)
                         VALUES (NULL, $sale_id, $p, $q, $pr, $total_item)";
            if (!mysqli_query($conn, $sql_item)) {
                throw new Exception("Mahsulot yozilmadi: " . mysqli_error($conn));
            }
            mysqli_query($conn, "UPDATE products SET quantity=quantity-$q WHERE id=$p");
        }

        mysqli_commit($conn);
        $_SESSION['cart'] = [];
        echo json_encode(['status'=>'ok','sale_id'=>$sale_id,'total'=>$final]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
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
