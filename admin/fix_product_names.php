<?php
// Bir martalik script: eski sale_items larning product_name ustunini to'ldirish
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/admin_check.php";
require_once "../config/dokon_db.php";

// 1. product_name ustunini qo'shish (agar yo'q bo'lsa)
@mysqli_query($conn, "ALTER TABLE sale_items ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) DEFAULT NULL");

// 2. product_name bo'sh bo'lgan va product_id ga mos mahsulot mavjud bo'lgan yozuvlarni yangilash
$updated = 0;
$upd = mysqli_query($conn, "
    UPDATE sale_items si
    JOIN products p ON si.product_id = p.id
    SET si.product_name = p.name
    WHERE (si.product_name IS NULL OR si.product_name = '')
      AND si.product_id > 0
");
if ($upd) $updated = mysqli_affected_rows($conn);

// 3. Hali ham bo'sh qolganlarni ko'rish (product o'chirilgan bo'lsa)
$still_empty = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM sale_items WHERE product_name IS NULL OR product_name=''"
))['c'] ?? 0;

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>body{font-family:Arial,sans-serif;max-width:500px;margin:50px auto;padding:20px;}
.ok{background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:16px;margin:10px 0;}
.warn{background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:16px;margin:10px 0;}
.btn{display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;}
</style></head><body>";
echo "<h2>🛠️ Mahsulot Nomlarini Tuzatish</h2>";
echo "<div class='ok'>✅ <b>$updated ta</b> yozuv yangilandi (product_name to'ldirildi)</div>";
if ($still_empty > 0) {
    echo "<div class='warn'>⚠️ <b>$still_empty ta</b> yozuv yangilanmadi — mos mahsulot o'chirilgan bo'lishi mumkin. Ular chekda 'Noma'lum mahsulot' deb ko'rinadi.</div>";
} else {
    echo "<div class='ok'>🎉 Barcha yozuvlar to'liq!</div>";
}
echo "<br><a href='sales_history.php' class='btn'>← Savdo tarixiga qaytish</a>";
echo "</body></html>";
?>
