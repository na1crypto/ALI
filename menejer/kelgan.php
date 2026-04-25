<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/menejer_check.php";
require_once "../config/dokon_db.php";

@mysqli_query($conn,"CREATE TABLE IF NOT EXISTS stock_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    cost_price DECIMAL(10,2) DEFAULT 0,
    supplier VARCHAR(100) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$msg = '';
$msg_type = '';

// ── Tovar qabul qilish ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['qabul'])) {
    $pid         = (int)($_POST['product_id'] ?? 0);
    $qty         = (float)($_POST['qty'] ?? 0);
    $cost        = (float)($_POST['cost_price'] ?? 0);
    $optom       = (float)($_POST['optom_price'] ?? 0);
    $sotuv       = (float)($_POST['price'] ?? 0);
    $expiry      = trim($_POST['expiry_date'] ?? '');
    $supplier    = mysqli_real_escape_string($conn, trim($_POST['supplier'] ?? ''));
    $note        = mysqli_real_escape_string($conn, trim($_POST['note'] ?? ''));
    $uid         = (int)$_SESSION['user_id'];

    if ($pid <= 0 || $qty <= 0) {
        $msg = 'Mahsulot va miqdorni to\'liq kiriting!';
        $msg_type = 'err';
    } else {
        $prod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name,unit,purchase_price FROM products WHERE id=$pid"));
        if (!$prod) {
            $msg = 'Mahsulot topilmadi!'; $msg_type = 'err';
        } else {
            // 1. stock_entries ga yoz
            mysqli_query($conn, "INSERT INTO stock_entries (product_id, quantity, cost_price, supplier, note, created_by)
                VALUES ($pid, $qty, $cost, '$supplier', '$note', $uid)");

            // 2. Ombor miqdorini yangilashtir
            mysqli_query($conn, "UPDATE products SET quantity=quantity+$qty WHERE id=$pid");

            // 3. Narxlarni yangilashtir (kiritilgan bo'lsa)
            $updates = [];
            if ($cost  > 0) $updates[] = "purchase_price=$cost";
            if ($optom > 0) $updates[] = "optom_price=$optom";
            if ($sotuv > 0) $updates[] = "price=$sotuv";
            if ($expiry)    $updates[] = "expiry_date='" . mysqli_real_escape_string($conn,$expiry) . "'";
            if ($updates)   mysqli_query($conn, "UPDATE products SET ".implode(',',$updates)." WHERE id=$pid");

            $msg = htmlspecialchars($prod['name']) . " — " . $qty . " " . ($prod['unit']?:'dona') . " qabul qilindi!";
            $msg_type = 'ok';
        }
    }
}

// Mahsulotlar ro'yxati
$prods = mysqli_query($conn, "
    SELECT p.id, p.name, p.quantity, IFNULL(p.unit,'dona') AS unit,
           IFNULL(p.purchase_price,0) AS purchase_price,
           IFNULL(p.optom_price,0) AS optom_price,
           IFNULL(p.price,0) AS price,
           IFNULL(p.expiry_date,'') AS expiry_date,
           IFNULL(c.name,'—') AS kategoriya
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.name ASC
");
$prods_arr = [];
if ($prods) while ($r = mysqli_fetch_assoc($prods)) $prods_arr[] = $r;

// Oxirgi 30 ta qabul
$entries = mysqli_query($conn, "
    SELECT e.id, e.created_at, e.quantity, e.cost_price, e.supplier, e.note,
           p.name AS prod_name, IFNULL(p.unit,'dona') AS unit,
           u.name AS who
    FROM stock_entries e
    LEFT JOIN products p ON e.product_id=p.id
    LEFT JOIN users u ON e.created_by=u.id
    ORDER BY e.id DESC LIMIT 30
");

$site_q = mysqli_fetch_assoc(mysqli_query($conn,"SELECT store_name FROM settings WHERE id=1"));
$site_name = $site_q['store_name'] ?? "SMART POS";
$active_page = 'kelgan';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kelgan Tovarlar | Menejer</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',-apple-system,sans-serif;background:#F4F7FE;color:#1B2559;min-height:100vh}
.navbar{height:62px;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 28px;border-bottom:1px solid #E9EDF7;position:sticky;top:0;z-index:90;box-shadow:0 2px 8px rgba(17,28,68,.04)}
.nav-title{font-size:19px;font-weight:800;color:#111C44}
.user-pill{background:#F4F7FE;padding:5px 14px 5px 5px;border-radius:30px;display:flex;align-items:center;gap:9px;font-weight:700;color:#111C44;font-size:13px}
.u-av{width:30px;height:30px;border-radius:50%;background:#4318FF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.main{padding:24px 28px;display:grid;grid-template-columns:400px 1fr;gap:18px;align-items:start}
/* FORM CARD */
.form-card{background:#fff;border-radius:16px;padding:22px;box-shadow:0 4px 16px rgba(17,28,68,.04)}
.fc-title{font-size:15px;font-weight:800;color:#111C44;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.lbl{display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.inp{width:100%;padding:12px 14px;border-radius:11px;border:1.5px solid #E9EDF7;background:#F8FAFF;font-size:14px;font-weight:500;color:#111C44;transition:.2s;margin-bottom:14px}
.inp:focus{outline:none;border-color:#4318FF;background:#fff;box-shadow:0 0 0 3px rgba(67,24,255,.1)}
.inp::placeholder{color:#A3AED0}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn-sub{width:100%;padding:13px;background:#4318FF;color:#fff;border:none;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;margin-top:4px}
.btn-sub:hover{background:#3311cc;transform:translateY(-2px)}
.alert{padding:12px 16px;border-radius:11px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.a-ok{background:#E8F9F1;border:1px solid #A7F3D0;color:#065F46}
.a-err{background:#FEECEF;border:1px solid #FECACA;color:#B91C1C}
/* PRODUCT SEARCH */
.ps-wrap{position:relative;margin-bottom:14px}
.ps-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid #E9EDF7;border-radius:11px;z-index:100;max-height:220px;overflow-y:auto;box-shadow:0 8px 24px rgba(17,28,68,.1);display:none}
.ps-item{padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid #F4F7FE;transition:.15s}
.ps-item:hover{background:#F0EEFF;color:#4318FF}
.ps-item:last-child{border:none}
.ps-item .ps-qty{font-size:11px;color:#A3AED0;margin-top:1px}
.sel-prod{background:#EEF2FF;border-color:#4318FF;color:#4318FF;font-weight:700}
/* TABLE */
.table-card{background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(17,28,68,.04);overflow:hidden}
.tc-header{padding:16px 20px;border-bottom:1px solid #E9EDF7}
.tc-title{font-size:14px;font-weight:800;color:#111C44}
.tc-sub{font-size:11px;color:#A3AED0;margin-top:2px}
.mtable{width:100%;border-collapse:collapse}
.mtable th{font-size:11px;font-weight:700;color:#A3AED0;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;text-align:left;background:#fff;border-bottom:1px solid #E9EDF7}
.mtable td{padding:11px 14px;font-size:13px;font-weight:600;color:#111C44;border-bottom:1px solid #F4F7FE;vertical-align:middle}
.mtable tr:last-child td{border:none}
.mtable tbody tr:hover td{background:#F8FAFF}
.qty-badge{background:#E8F9F1;color:#05CD99;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.empty{text-align:center;padding:36px;color:#A3AED0;font-size:13px}
@media(max-width:960px){.main{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="navbar">
    <div class="nav-title">📥 Kelgan Tovarlar</div>
    <div class="user-pill">
        <div class="u-av"><?= strtoupper(substr($_SESSION['name']??'M',0,1)) ?></div>
        <?= htmlspecialchars(explode(' ',$_SESSION['name']??'Menejer')[0]) ?>
    </div>
</div>

<div class="main">

    <!-- FORM -->
    <div class="form-card">
        <div class="fc-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4318FF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M5 19h14"/></svg>
            Yangi Tovar Qabul Qilish
        </div>

        <?php if($msg): ?>
        <div class="alert <?= $msg_type==='ok'?'a-ok':'a-err' ?>">
            <?= $msg_type==='ok' ? '✅' : '⚠️' ?> <?= $msg ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <label class="lbl">Mahsulot *</label>
            <div class="ps-wrap">
                <input type="text" id="prodSearch" class="inp" placeholder="Mahsulot nomini kiriting..."
                       autocomplete="off" style="margin-bottom:0">
                <input type="hidden" name="product_id" id="productId">
                <div class="ps-results" id="psResults"></div>
            </div>
            <div id="selectedProd" style="display:none;background:#EEF2FF;border-radius:10px;padding:9px 13px;margin-bottom:14px;font-size:13px;color:#4318FF;font-weight:700"></div>
            <div id="kategorijaRow" style="display:none;background:#F8FAFF;border-radius:10px;padding:8px 13px;margin-bottom:14px;font-size:12px;color:#64748b;font-weight:600">
                📂 Kategoriya: <span id="kategorijaVal" style="color:#111C44;font-weight:700"></span>
            </div>

            <div class="row2">
                <div>
                    <label class="lbl">Miqdori *</label>
                    <input type="number" name="qty" class="inp" placeholder="0" min="0.001" step="0.001" required>
                </div>
                <div>
                    <label class="lbl">Kelish narxi (UZS)</label>
                    <input type="number" name="cost_price" class="inp" placeholder="0" min="0" step="1" id="costInput">
                </div>
            </div>

            <div class="row2">
                <div>
                    <label class="lbl">Ulgurja narxi (UZS)</label>
                    <input type="number" name="optom_price" class="inp" placeholder="0" min="0" step="1" id="optomInput">
                </div>
                <div>
                    <label class="lbl">Sotilish narxi (UZS)</label>
                    <input type="number" name="price" class="inp" placeholder="0" min="0" step="1" id="sotuvInput">
                </div>
            </div>

            <label class="lbl">Yaroqlilik muddati <span style="color:#A3AED0;font-weight:500;text-transform:none">(ixtiyoriy)</span></label>
            <input type="date" name="expiry_date" class="inp" id="expiryInput">

            <label class="lbl">Yetkazuvchi / Ta'minotchi</label>
            <input type="text" name="supplier" class="inp" placeholder="Kompaniya yoki shaxs ismi">

            <label class="lbl">Izoh</label>
            <textarea name="note" class="inp" rows="2" placeholder="Qo'shimcha ma'lumot..." style="resize:vertical"></textarea>

            <button type="submit" name="qabul" class="btn-sub">
                ✓ Omborga Kiritish
            </button>
        </form>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="tc-header">
            <div class="tc-title">Qabul Tarixi</div>
            <div class="tc-sub">Omborga kiritilgan tovarlar ro'yxati</div>
        </div>
        <?php if($entries && mysqli_num_rows($entries)>0): ?>
        <div style="overflow-x:auto">
        <table class="mtable">
            <thead>
                <tr>
                    <th>Mahsulot</th>
                    <th>Miqdor</th>
                    <th>Kelish narxi</th>
                    <th>Ta'minotchi</th>
                    <th>Kim kiritdi</th>
                    <th>Sana</th>
                </tr>
            </thead>
            <tbody>
            <?php while($e=mysqli_fetch_assoc($entries)): ?>
            <tr>
                <td style="font-weight:700;color:#111C44"><?= htmlspecialchars($e['prod_name']??'—') ?></td>
                <td><span class="qty-badge">+<?= (float)$e['quantity'] ?> <?= htmlspecialchars($e['unit']) ?></span></td>
                <td><?= $e['cost_price']>0 ? number_format($e['cost_price'],0,'.',' ').' UZS' : '<span style="color:#CBD5E1">—</span>' ?></td>
                <td><?= htmlspecialchars($e['supplier'] ?: '—') ?></td>
                <td style="color:#64748b"><?= htmlspecialchars($e['who']??'—') ?></td>
                <td style="color:#A3AED0;font-size:12px"><?= date('d.m.y H:i',strtotime($e['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty">📦 Hali hech narsa qabul qilinmagan</div>
        <?php endif; ?>
    </div>
</div>

<script>
var products = <?= json_encode($prods_arr) ?>;
var selId = 0;

var srchEl    = document.getElementById('prodSearch');
var resEl     = document.getElementById('psResults');
var pidEl     = document.getElementById('productId');
var selEl     = document.getElementById('selectedProd');
var katRow    = document.getElementById('kategorijaRow');
var katVal    = document.getElementById('kategorijaVal');
var costEl    = document.getElementById('costInput');
var optomEl   = document.getElementById('optomInput');
var sotuvEl   = document.getElementById('sotuvInput');
var expiryEl  = document.getElementById('expiryInput');

srchEl.addEventListener('input', function(){
    var q = this.value.toLowerCase().trim();
    pidEl.value = '';
    selEl.style.display = 'none';
    katRow.style.display = 'none';
    selId = 0;
    if (!q) { resEl.style.display='none'; return; }
    var filtered = products.filter(function(p){ return p.name.toLowerCase().includes(q); }).slice(0,10);
    if (!filtered.length) { resEl.style.display='none'; return; }
    resEl.innerHTML = filtered.map(function(p){
        return '<div class="ps-item" onclick="selectProd('+p.id+','+JSON.stringify(p.name)+','+p.quantity+','+JSON.stringify(p.unit)+','+p.purchase_price+','+p.optom_price+','+p.price+','+JSON.stringify(p.expiry_date)+','+JSON.stringify(p.kategoriya)+')">'
        + '<strong>' + esc(p.name) + '</strong>'
        + '<div class="ps-qty">Omborda: ' + p.quantity + ' ' + esc(p.unit) + ' &nbsp;|&nbsp; Kat: ' + esc(p.kategoriya) + '</div>'
        + '</div>';
    }).join('');
    resEl.style.display = 'block';
});

function selectProd(id, name, qty, unit, cost, optom, sotuv, expiry, kat) {
    selId = id;
    pidEl.value = id;
    srchEl.value = name;
    resEl.style.display = 'none';
    selEl.textContent = '✓ ' + name + ' (Omborda: ' + qty + ' ' + unit + ')';
    selEl.style.display = 'block';
    katVal.textContent = kat;
    katRow.style.display = 'block';
    if (cost  > 0) costEl.value  = cost;
    if (optom > 0) optomEl.value = optom;
    if (sotuv > 0) sotuvEl.value = sotuv;
    if (expiry)    expiryEl.value = expiry;
}

function esc(s) { var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

document.addEventListener('click', function(e){
    if (!resEl.contains(e.target) && e.target !== srchEl) resEl.style.display='none';
});
</script>
</body>
</html>
