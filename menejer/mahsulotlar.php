<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/menejer_check.php";
require_once "../config/dokon_db.php";

$search = trim($_GET['q'] ?? '');
$cat_id = (int)($_GET['cat'] ?? 0);

$where = "WHERE 1=1";
if ($search) $where .= " AND (p.name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' OR p.barcode LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
if ($cat_id) $where .= " AND p.category_id=$cat_id";

$prods = mysqli_query($conn, "
    SELECT p.id, p.name, p.barcode, p.price, p.optom_price, p.purchase_price,
           p.quantity, IFNULL(p.unit,'dona') AS unit, IFNULL(p.min_qty,1) AS min_qty,
           IFNULL(c.name,'—') AS kategoriya
    FROM products p
    LEFT JOIN categories c ON p.category_id=c.id
    $where
    ORDER BY p.name ASC
    LIMIT 200
");

$cats = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");

// Ombor statistika
$st_q = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN quantity=0 THEN 1 ELSE 0 END) AS zero_cnt,
           SUM(CASE WHEN quantity>0 AND quantity<=5 THEN 1 ELSE 0 END) AS low_cnt,
           SUM(CASE WHEN quantity>5 THEN 1 ELSE 0 END) AS ok_cnt
    FROM products
"));

$site_q = mysqli_fetch_assoc(mysqli_query($conn,"SELECT store_name FROM settings WHERE id=1"));
$site_name = $site_q['store_name'] ?? "SMART POS";
$active_page = 'mahsulotlar';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ombor | Menejer</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',-apple-system,sans-serif;background:#F4F7FE;color:#1B2559;min-height:100vh}
.navbar{height:62px;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 28px;border-bottom:1px solid #E9EDF7;position:sticky;top:0;z-index:90;box-shadow:0 2px 8px rgba(17,28,68,.04)}
.nav-title{font-size:19px;font-weight:800;color:#111C44}
.user-pill{background:#F4F7FE;padding:5px 14px 5px 5px;border-radius:30px;display:flex;align-items:center;gap:9px;font-weight:700;color:#111C44;font-size:13px}
.u-av{width:30px;height:30px;border-radius:50%;background:#4318FF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.main{padding:20px 28px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
.sc{background:#fff;border-radius:13px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 3px 12px rgba(17,28,68,.04)}
.sc-ico{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.ic-b{background:#EEF2FF;color:#4318FF}.ic-g{background:#E8F9F1;color:#05CD99}.ic-o{background:#FFF4E5;color:#FFB547}.ic-r{background:#FEECEF;color:#EE5D50}
.sc-v{font-size:20px;font-weight:800;color:#111C44;line-height:1}
.sc-l{font-size:11px;color:#A3AED0;font-weight:600;margin-top:2px}
.toolbar{display:flex;gap:10px;margin-bottom:16px;align-items:center;flex-wrap:wrap}
.search-inp{flex:1;min-width:200px;padding:11px 14px;border-radius:11px;border:1.5px solid #E9EDF7;background:#fff;font-size:14px;color:#111C44;outline:none;transition:.2s}
.search-inp:focus{border-color:#4318FF;box-shadow:0 0 0 3px rgba(67,24,255,.1)}
.cat-sel{padding:11px 14px;border-radius:11px;border:1.5px solid #E9EDF7;background:#fff;font-size:13px;color:#111C44;outline:none;cursor:pointer}
.btn-kelgan{display:inline-flex;align-items:center;gap:6px;background:#4318FF;color:#fff;border:none;padding:11px 18px;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;transition:.2s;white-space:nowrap}
.btn-kelgan:hover{background:#3311cc;color:#fff}
.card{background:#fff;border-radius:14px;box-shadow:0 3px 12px rgba(17,28,68,.04);overflow:hidden}
.mtable{width:100%;border-collapse:collapse}
.mtable th{font-size:11px;font-weight:700;color:#A3AED0;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;border-bottom:1px solid #E9EDF7;background:#fff}
.mtable td{padding:11px 14px;font-size:13px;font-weight:600;color:#111C44;border-bottom:1px solid #F4F7FE;vertical-align:middle}
.mtable tr:last-child td{border:none}
.mtable tbody tr:hover td{background:#F8FAFF}
.qbadge{padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.q-ok{background:#E8F9F1;color:#05CD99}
.q-warn{background:#FFF4E5;color:#FFB547}
.q-danger{background:#FEECEF;color:#EE5D50}
.q-zero{background:#F1F5F9;color:#94A3B8}
.empty{text-align:center;padding:48px;color:#A3AED0}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="navbar">
    <div class="nav-title">🏪 Ombor Holati</div>
    <div class="user-pill">
        <div class="u-av"><?= strtoupper(substr($_SESSION['name']??'M',0,1)) ?></div>
        <?= htmlspecialchars(explode(' ',$_SESSION['name']??'Menejer')[0]) ?>
    </div>
</div>

<div class="main">

    <!-- STATS -->
    <div class="stats">
        <div class="sc"><div class="sc-ico ic-b">📦</div><div><div class="sc-v"><?= $st_q['total'] ?></div><div class="sc-l">Jami tovar turi</div></div></div>
        <div class="sc"><div class="sc-ico ic-g">✅</div><div><div class="sc-v"><?= $st_q['ok_cnt'] ?></div><div class="sc-l">Yetarli (>5)</div></div></div>
        <div class="sc"><div class="sc-ico ic-o">⚠️</div><div><div class="sc-v"><?= $st_q['low_cnt'] ?></div><div class="sc-l">Kam (1-5)</div></div></div>
        <div class="sc"><div class="sc-ico ic-r">🚫</div><div><div class="sc-v" style="color:#EE5D50"><?= $st_q['zero_cnt'] ?></div><div class="sc-l">Tugagan (0)</div></div></div>
    </div>

    <!-- TOOLBAR -->
    <form method="GET" class="toolbar">
        <input type="text" name="q" class="search-inp" placeholder="🔍 Mahsulot nomi yoki barkod..."
               value="<?= htmlspecialchars($search) ?>">
        <select name="cat" class="cat-sel" onchange="this.form.submit()">
            <option value="0">Barcha kategoriyalar</option>
            <?php $cats && mysqli_data_seek($cats,0); while($c=mysqli_fetch_assoc($cats)): ?>
            <option value="<?= $c['id'] ?>" <?= $cat_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" style="padding:11px 16px;border-radius:11px;background:#F4F7FE;border:1.5px solid #E9EDF7;font-size:13px;font-weight:700;cursor:pointer;color:#111C44">Qidirish</button>
        <a href="/menejer/kelgan.php" class="btn-kelgan">📥 Tovar Qabul Qilish</a>
    </form>

    <!-- TABLE -->
    <div class="card">
        <?php if($prods && mysqli_num_rows($prods)>0): ?>
        <div style="overflow-x:auto">
        <table class="mtable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Mahsulot nomi</th>
                    <th>Kategoriya</th>
                    <th>Barkod</th>
                    <th>Kelish narxi</th>
                    <th>Sotuv narxi</th>
                    <th>Optom narxi</th>
                    <th>Ombor</th>
                    <th>Min. buyurtma</th>
                </tr>
            </thead>
            <tbody>
            <?php $i=1; while($p=mysqli_fetch_assoc($prods)): ?>
            <tr>
                <td style="color:#A3AED0;font-size:12px"><?= $i++ ?></td>
                <td style="font-weight:700;color:#111C44;max-width:200px"><?= htmlspecialchars($p['name']) ?></td>
                <td style="color:#64748b"><?= htmlspecialchars($p['kategoriya']) ?></td>
                <td style="font-family:monospace;font-size:12px;color:#64748b"><?= htmlspecialchars($p['barcode']??'—') ?></td>
                <td><?= number_format($p['purchase_price'],0,'.',' ') ?> <span style="font-size:11px;color:#A3AED0">so'm</span></td>
                <td style="color:#4318FF;font-weight:700"><?= number_format($p['price'],0,'.',' ') ?> <span style="font-size:11px;color:#A3AED0">so'm</span></td>
                <td style="color:#05CD99;font-weight:700"><?= number_format($p['optom_price'],0,'.',' ') ?> <span style="font-size:11px;color:#A3AED0">so'm</span></td>
                <td>
                    <?php $q=(int)$p['quantity']; ?>
                    <span class="qbadge <?= $q==0?'q-zero':($q<=2?'q-danger':($q<=5?'q-warn':'q-ok')) ?>">
                        <?= $q ?> <?= htmlspecialchars($p['unit']) ?>
                    </span>
                </td>
                <td style="color:#64748b"><?= $p['min_qty'] ?> <?= htmlspecialchars($p['unit']) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty">📭 Mahsulot topilmadi</div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
