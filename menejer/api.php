<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/menejer_check.php";
require_once "../config/dokon_db.php";

// Migration
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'tolangan'");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS note TEXT DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Yangi buyurtmalar ro'yxati ──
if ($action === 'orders') {
    $filter = $_GET['filter'] ?? 'yangi';
    $where = "s.source = 'mijoz'";
    if ($filter === 'yangi')    $where .= " AND s.status = 'yangi'";
    elseif ($filter === 'qabul') $where .= " AND s.status = 'qabul_qilindi'";
    elseif ($filter === 'rad')   $where .= " AND s.status = 'rad_etildi'";
    elseif ($filter === 'barchasi') { /* source=mijoz yetarli */ }

    $sql = "SELECT s.id, s.sale_date, s.total_price, s.status, s.note, s.sale_type,
                   u.name AS mijoz_nomi, u.username AS mijoz_login
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE $where
            ORDER BY s.id DESC
            LIMIT 50";
    $res = mysqli_query($conn, $sql);
    $orders = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            // Buyurtma mahsulotlarini olish
            $items_res = mysqli_query($conn,
                "SELECT si.quantity, si.unit_price, si.price AS total,
                        p.name AS prod_name, IFNULL(p.unit,'dona') AS unit
                 FROM sale_items si
                 LEFT JOIN products p ON si.product_id = p.id
                 WHERE si.sale_id = " . (int)$row['id']
            );
            $items = [];
            if ($items_res) while ($i = mysqli_fetch_assoc($items_res)) $items[] = $i;

            $orders[] = [
                'id'          => (int)$row['id'],
                'sale_date'   => $row['sale_date'],
                'total_price' => (float)$row['total_price'],
                'status'      => $row['status'],
                'note'        => $row['note'],
                'sale_type'   => $row['sale_type'],
                'mijoz_nomi'  => $row['mijoz_nomi'] ?? 'Noma\'lum',
                'mijoz_login' => $row['mijoz_login'] ?? '',
                'items'       => $items,
            ];
        }
    }
    echo json_encode(['status' => 'ok', 'orders' => $orders]);
    exit;
}

// ── Buyurtmani qabul qilish ──
if ($action === 'accept') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['status'=>'error','message'=>'ID yo\'q']); exit; }
    mysqli_query($conn, "UPDATE sales SET status='qabul_qilindi' WHERE id=$id AND source='mijoz'");
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── Buyurtmani rad etish ──
if ($action === 'reject') {
    $id   = (int)($_POST['id'] ?? 0);
    $sabab = mysqli_real_escape_string($conn, trim($_POST['sabab'] ?? 'Rad etildi'));
    if (!$id) { echo json_encode(['status'=>'error','message'=>'ID yo\'q']); exit; }

    // Mahsulot miqdorini qaytarish
    $items = mysqli_query($conn, "SELECT product_id, quantity FROM sale_items WHERE sale_id=$id");
    if ($items) {
        while ($it = mysqli_fetch_assoc($items)) {
            mysqli_query($conn, "UPDATE products SET quantity=quantity+" . (int)$it['quantity'] .
                " WHERE id=" . (int)$it['product_id']);
        }
    }
    mysqli_query($conn, "UPDATE sales SET status='rad_etildi', note=CONCAT(IFNULL(note,''),' [RAD: $sabab]')
                         WHERE id=$id AND source='mijoz'");
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── AI Statistika ──
if ($action === 'stats') {
    $today = date('Y-m-d');
    $week  = date('Y-m-d', strtotime('-6 days'));

    // Bugungi mijoz buyurtmalari
    $today_r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS soni, COALESCE(SUM(total_price),0) AS jami
         FROM sales WHERE source='mijoz' AND DATE(sale_date)='$today'"
    ));

    // Yangi (kutilayotgan) buyurtmalar
    $yangi_r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS soni FROM sales WHERE source='mijoz' AND status='yangi'"
    ));

    // Haftalik mijoz buyurtmalari (kunlik)
    $week_q = mysqli_query($conn,
        "SELECT DATE(sale_date) AS kun, COUNT(*) AS soni, COALESCE(SUM(total_price),0) AS jami
         FROM sales WHERE source='mijoz' AND DATE(sale_date) >= '$week'
         GROUP BY DATE(sale_date) ORDER BY kun ASC"
    );
    $haftalik = [];
    if ($week_q) while ($w = mysqli_fetch_assoc($week_q)) $haftalik[] = $w;

    // Top 5 mahsulot (mijoz buyurtmalaridan)
    $top_q = mysqli_query($conn,
        "SELECT p.name, SUM(si.quantity) AS jami_qty, SUM(si.price) AS jami_summa
         FROM sale_items si
         JOIN sales s ON si.sale_id = s.id
         JOIN products p ON si.product_id = p.id
         WHERE s.source = 'mijoz'
         GROUP BY si.product_id
         ORDER BY jami_qty DESC
         LIMIT 5"
    );
    $top_prods = [];
    if ($top_q) while ($t = mysqli_fetch_assoc($top_q)) $top_prods[] = $t;

    // Yetkazish vs olib ketish nisbati
    $delivery_r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            SUM(CASE WHEN sale_type='yetkazish' THEN 1 ELSE 0 END) AS yetkazish,
            SUM(CASE WHEN sale_type='oddiy' THEN 1 ELSE 0 END) AS olib_ketish
         FROM sales WHERE source='mijoz'"
    ));

    // O'rtacha chek
    $avg_r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(AVG(total_price),0) AS ortacha FROM sales WHERE source='mijoz'"
    ));

    // Jami qabul/rad statistikasi
    $holat_r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            SUM(CASE WHEN status='qabul_qilindi' THEN 1 ELSE 0 END) AS qabul,
            SUM(CASE WHEN status='rad_etildi'    THEN 1 ELSE 0 END) AS rad,
            SUM(CASE WHEN status='yangi'         THEN 1 ELSE 0 END) AS yangi
         FROM sales WHERE source='mijoz'"
    ));

    echo json_encode([
        'status'       => 'ok',
        'bugun_soni'   => (int)($today_r['soni'] ?? 0),
        'bugun_jami'   => (float)($today_r['jami'] ?? 0),
        'yangi_soni'   => (int)($yangi_r['soni'] ?? 0),
        'haftalik'     => $haftalik,
        'top_prods'    => $top_prods,
        'yetkazish'    => (int)($delivery_r['yetkazish'] ?? 0),
        'olib_ketish'  => (int)($delivery_r['olib_ketish'] ?? 0),
        'ortacha_chek' => (float)($avg_r['ortacha'] ?? 0),
        'qabul'        => (int)($holat_r['qabul'] ?? 0),
        'rad'          => (int)($holat_r['rad'] ?? 0),
        'kutilayotgan' => (int)($holat_r['yangi'] ?? 0),
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Noma\'lum so\'rov']);
