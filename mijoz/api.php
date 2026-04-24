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

// ── Mahsulotlarni qidirish / barcha mahsulotlar ──
if ($action === 'products') {
    $q = mysqli_real_escape_string($conn, $_POST['q'] ?? '');
    $where = $q ? "AND (p.name LIKE '%$q%' OR p.barcode='$q')" : "";
    $sql = "SELECT p.id, p.name, p.price, p.optom_price, p.quantity, p.barcode,
                   IFNULL(k.name,'') AS kategoriya
            FROM products p
            LEFT JOIN kategoriyalar k ON p.kategoriya_id = k.id
            WHERE p.quantity > 0 $where
            ORDER BY p.name ASC
            LIMIT 60";
    $res = mysqli_query($conn, $sql);
    $list = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) {
        $list[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'price'       => (float)$r['price'],
            'optom_price' => (float)$r['optom_price'],
            'quantity'    => (int)$r['quantity'],
            'barcode'     => $r['barcode'],
            'kategoriya'  => $r['kategoriya'],
        ];
    }
    echo json_encode($list);
    exit;
}

// ── Savatga qo'shish ──
if ($action === 'add_cart') {
    $pid   = (int)($_POST['id'] ?? 0);
    $qty   = max(1, (int)($_POST['qty'] ?? 1));
    $row   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id=$pid AND quantity>0"));
    if (!$row) { echo json_encode(['status'=>'error','message'=>'Mahsulot topilmadi']); exit; }

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $cart = &$_SESSION['cart'];
    if (isset($cart[$pid])) {
        $cart[$pid]['qty'] = min($cart[$pid]['qty'] + $qty, (int)$row['quantity']);
    } else {
        $cart[$pid] = [
            'id'    => $pid,
            'name'  => $row['name'],
            'price' => (float)$row['optom_price'] ?: (float)$row['price'],
            'stock' => (int)$row['quantity'],
            'qty'   => $qty,
        ];
    }
    echo json_encode(['status'=>'ok','cart_count'=>array_sum(array_column($cart,'qty'))]);
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
    $cart     = $_SESSION['cart'] ?? [];
    $yetkazish = (int)($_POST['yetkazish'] ?? 0);
    $manzil   = mysqli_real_escape_string($conn, trim($_POST['manzil'] ?? ''));
    $izoh     = mysqli_real_escape_string($conn, trim($_POST['izoh'] ?? ''));

    if (empty($cart)) {
        echo json_encode(['status'=>'error','message'=>'Savat bo\'sh!']); exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $user_id = (int)$_SESSION['user_id'];
        $total = 0;
        foreach ($cart as $item) $total += $item['price'] * $item['qty'];

        $delivery_fee = ($yetkazish && $total >= 1500000) ? 0 : 0; // Yetkazish bepul ≥1.5M
        $final = $total + $delivery_fee;

        // Buyurtma yozish
        $payment = 'naqd';
        $next = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(MAX(id),0)+1 AS n FROM sales"))['n'];

        // note ustuni borligini aniqlash
        $cols = mysqli_query($conn,"SHOW COLUMNS FROM sales LIKE 'note'");
        if (mysqli_num_rows($cols) > 0) {
            $note = $yetkazish ? "Yetkazish: $manzil. $izoh" : "O'zi oladi. $izoh";
            $note = mysqli_real_escape_string($conn, $note);
            mysqli_query($conn,"INSERT INTO sales (id,user_id,total_price,payment_method,note)
                                 VALUES ($next,$user_id,$final,'$payment','$note')");
        } else {
            mysqli_query($conn,"INSERT INTO sales (id,user_id,total_price,payment_method)
                                 VALUES ($next,$user_id,$final,'$payment')");
        }

        foreach ($cart as $item) {
            $p  = (int)$item['id'];
            $q  = (int)$item['qty'];
            $pr = (float)$item['price'];
            $ni = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(MAX(id),0)+1 AS n FROM sale_items"))['n'];
            mysqli_query($conn,"INSERT INTO sale_items (id,sale_id,product_id,quantity,unit_price,price)
                                VALUES ($ni,$next,$p,$q,$pr,".($pr*$q).")");
            mysqli_query($conn,"UPDATE products SET quantity=quantity-$q WHERE id=$p");
        }

        mysqli_commit($conn);
        $_SESSION['cart'] = [];
        echo json_encode(['status'=>'ok','sale_id'=>$next,'total'=>$final]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

echo json_encode(['status'=>'error','message'=>'Noma\'lum so\'rov']);
