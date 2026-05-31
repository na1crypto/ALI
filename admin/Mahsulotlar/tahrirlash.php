<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../../middleware/admin_check.php";
require_once "../../config/dokon_db.php";

$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "SMART POS";

// 1. Mahsulot ma'lumotlarini olish
if(isset($_GET['id'])){
    $id = (int)$_GET['id'];
    $res = mysqli_query($conn, "SELECT * FROM products WHERE id = $id");
    $product = mysqli_fetch_assoc($res);
    if(!$product) { header("Location: index.php"); exit(); }
} else { header("Location: index.php"); exit(); }

$cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");

// 2. Formadan kelganda yangilash (Update)
if (isset($_POST['yangilash'])) {
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $category_id = (int)$_POST['category_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $purchase_price = (float)$_POST['purchase_price'];
    $optom_price = (float)$_POST['optom_price']; 
    $price = (float)$_POST['price'];
    $quantity = (float)$_POST['quantity'];
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $min_qty = max(1, (int)($_POST['min_qty'] ?? 1));
    $expiry_date = !empty($_POST['expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['expiry_date']) . "'" : "NULL";

    // Bir martalik migration
    @mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS min_qty INT NOT NULL DEFAULT 1");
    @mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS image MEDIUMTEXT DEFAULT NULL");

    // Rasm qayta ishlash (kutubxonadan yoki fayldan)
    $image_set = '';
    if (!empty($_POST['selected_image']) && str_starts_with(trim($_POST['selected_image']), 'data:image')) {
        // Kutubxonadan tanlangan rasm
        $b64 = mysqli_real_escape_string($conn, trim($_POST['selected_image']));
        $image_set = ", image='$b64'";
    } elseif (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === 0) {
        // To'g'ridan yuklangan fayl
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        if (in_array($mime, $allowed) && $_FILES['image']['size'] < 2097152) {
            $imgData = file_get_contents($_FILES['image']['tmp_name']);
            $b64 = 'data:' . $mime . ';base64,' . base64_encode($imgData);
            $image_set = ", image='" . mysqli_real_escape_string($conn, $b64) . "'";
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        $image_set = ", image=NULL";
    }

    $sql = "UPDATE products SET
            barcode='$barcode', category_id='$category_id', name='$name',
            purchase_price='$purchase_price', optom_price='$optom_price',
            price='$price', quantity='$quantity', unit='$unit', min_qty='$min_qty'
            $image_set, expiry_date=$expiry_date
            WHERE id=$id";
    
    if(mysqli_query($conn, $sql)){
        echo "<script>alert('O\'zgarishlar saqlandi!'); window.location.href='index.php';</script>";
        exit;
    } else {
        echo "<script>alert('Xato: " . addslashes(mysqli_error($conn)) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <title>Tahrirlash | <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #F8FAFC; color: #0F172A; }
        .bento-card { background: #FFFFFF; border-radius: 24px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid #E2E8F0; }
        .bento-label { font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block; }
        .bento-input { width: 100%; padding: 14px 18px; border-radius: 14px; border: 2px solid #E2E8F0; background: #F8FAFC; font-size: 15px; font-weight: 500; margin-bottom: 20px; transition: 0.2s; }
        .bento-input:focus { outline: none; border-color: #4F46E5; background: #FFFFFF; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        .btn-bento { background: #4F46E5; color: white; border: none; padding: 14px 30px; border-radius: 14px; font-weight: 600; transition: 0.2s; }
        .btn-bento:hover { background: #4338CA; transform: translateY(-2px); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 40px 0;">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="bento-card">
                    <h4 class="font-weight-bold mb-4 text-dark border-bottom pb-3">
                        <i class="fas fa-pen text-primary mr-2"></i> Tahrirlash: <span style="color: #4F46E5;"><?= htmlspecialchars($product['name']) ?></span>
                    </h4>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="bento-label">Shtrix-kod</label>
                                <input type="text" name="barcode" class="bento-input" value="<?= htmlspecialchars($product['barcode']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="bento-label">Kategoriya</label>
                                <select name="category_id" class="bento-input" required>
                                    <?php while($c = mysqli_fetch_assoc($cats)): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($c['id'] == $product['category_id']) ? 'selected' : '' ?>><?= $c['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="bento-label">Mahsulot Nomi</label>
                                <input type="text" name="name" class="bento-input" value="<?= htmlspecialchars($product['name']) ?>" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="bento-label">Kelish Narxi</label>
                                <input type="number" name="purchase_price" class="bento-input" value="<?= $product['purchase_price'] ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="bento-label text-danger">Ulgurji (Optom)</label>
                                <input type="number" name="optom_price" class="bento-input border-danger" value="<?= $product['optom_price'] ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="bento-label text-success">Sotuv (Dona)</label>
                                <input type="number" name="price" class="bento-input border-success" value="<?= $product['price'] ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="bento-label">Omborga Soni</label>
                                <input type="number" step="0.001" name="quantity" class="bento-input" value="<?= $product['quantity'] ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="bento-label">O'lchov Birligi</label>
                                <select name="unit" class="bento-input">
                                    <option value="dona" <?= $product['unit']=='dona'?'selected':'' ?>>Dona</option>
                                    <option value="kg" <?= $product['unit']=='kg'?'selected':'' ?>>Kg</option>
                                    <option value="litr" <?= $product['unit']=='litr'?'selected':'' ?>>Litr</option>
                                    <option value="blok" <?= $product['unit']=='blok'?'selected':'' ?>>Blok</option>
                                    <option value="qop" <?= $product['unit']=='qop'?'selected':'' ?>>Qop</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="bento-label">Min. buyurtma <small class="text-muted">(dona/blok)</small></label>
                                <input type="number" name="min_qty" class="bento-input" min="1"
                                       value="<?= (int)($product['min_qty'] ?? 1) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="bento-label">Yaroqlilik muddati</label>
                                <input type="date" name="expiry_date" class="bento-input" value="<?= $product['expiry_date'] ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="bento-label">📷 Mahsulot rasmi</label>

                                <!-- Tanlangan rasm preview -->
                                <div id="imgPreviewWrap" style="margin-bottom:14px;<?= empty($product['image'])?'display:none':'' ?>">
                                    <div style="display:flex;align-items:center;gap:12px;background:#F8FAFC;border:2px solid #E2E8F0;border-radius:14px;padding:12px;">
                                        <img id="cur_img" src="<?= $product['image'] ?? '' ?>"
                                             style="height:90px;width:90px;border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid #E2E8F0;">
                                        <div>
                                            <div style="font-size:12px;font-weight:700;color:#4F46E5;margin-bottom:6px;">✅ Rasm tanlangan</div>
                                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#ef4444;cursor:pointer;font-weight:600;">
                                                <input type="checkbox" name="remove_image" value="1"
                                                       onchange="if(this.checked){document.getElementById('cur_img').style.opacity='.3';document.getElementById('imgPreviewWrap').style.borderColor='#fca5a5';}else{document.getElementById('cur_img').style.opacity='1';document.getElementById('imgPreviewWrap').style.borderColor='';}">
                                                Rasmni o'chirish
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hidden: kutubxonadan tanlangan rasm -->
                                <input type="hidden" name="selected_image" id="selectedImageData" value="">

                                <!-- 2 ta variant -->
                                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <!-- 1. Kutubxonadan tanlash -->
                                    <button type="button" onclick="openMediaPicker()"
                                            style="background:linear-gradient(135deg,#4F46E5,#4338CA);color:#fff;border:none;
                                                   padding:11px 20px;border-radius:12px;font-size:13px;font-weight:700;
                                                   cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(79,70,229,.3);">
                                        🖼️ Kutubxonadan tanlash
                                    </button>
                                    <!-- 2. To'g'ridan yuklash -->
                                    <label style="background:#F8FAFC;border:2px dashed #CBD5E1;color:#475569;
                                                  padding:11px 20px;border-radius:12px;font-size:13px;font-weight:700;
                                                  cursor:pointer;display:flex;align-items:center;gap:8px;transition:.15s;"
                                           onmouseover="this.style.borderColor='#4F46E5'" onmouseout="this.style.borderColor='#CBD5E1'">
                                        📁 Fayldan yuklash
                                        <input type="file" name="image" accept="image/*" style="display:none"
                                               onchange="previewImgEdit(this)">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                            <a href="index.php" class="btn btn-light px-4 font-weight-bold" style="border-radius: 12px;">Bekor qilish</a>
                            <button type="submit" name="yangilash" class="btn-bento shadow"><i class="fas fa-check mr-2"></i> Saqlash</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<!-- ══════ MEDIA PICKER MODAL ══════ -->
<style>
.mp-bg{position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:9990;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:20px;}
.mp-bg.open{display:flex;}
.mp-box{background:#fff;border-radius:24px;width:100%;max-width:820px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.3);overflow:hidden;}
.mp-head{padding:18px 22px 14px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.mp-head h5{font-size:16px;font-weight:800;color:#0F172A;margin:0;}
.mp-close{background:#F1F5F9;border:none;border-radius:8px;width:32px;height:32px;font-size:16px;cursor:pointer;color:#475569;}
.mp-search{padding:12px 16px;border-bottom:1px solid #E2E8F0;flex-shrink:0;}
.mp-search input{width:100%;height:38px;background:#F8FAFC;border:1.5px solid #E2E8F0;border-radius:10px;padding:0 12px 0 36px;font-size:13px;font-weight:500;}
.mp-search input:focus{outline:none;border-color:#4F46E5;}
.mp-search-wrap{position:relative;}
.mp-search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:13px;}
.mp-grid{flex:1;overflow-y:auto;padding:14px 16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;}
.mp-card{border:2.5px solid #E2E8F0;border-radius:12px;overflow:hidden;cursor:pointer;transition:.15s;position:relative;}
.mp-card:hover{border-color:#4F46E5;box-shadow:0 4px 14px rgba(79,70,229,.18);transform:scale(1.02);}
.mp-card.sel{border-color:#4F46E5;box-shadow:0 0 0 3px rgba(79,70,229,.2);}
.mp-card img{width:100%;height:100px;object-fit:cover;background:#F1F5F9;display:block;}
.mp-card-ph{width:100%;height:100px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;color:#CBD5E1;font-size:28px;}
.mp-card-name{padding:6px 8px;font-size:10px;font-weight:700;color:#475569;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;background:#fff;}
.mp-sel-badge{position:absolute;top:6px;right:6px;background:#4F46E5;color:#fff;width:22px;height:22px;border-radius:50%;display:none;align-items:center;justify-content:center;font-size:13px;font-weight:900;}
.mp-card.sel .mp-sel-badge{display:flex;}
.mp-foot{padding:14px 18px;border-top:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;background:#F8FAFC;}
.mp-foot-info{font-size:13px;color:#64748B;font-weight:600;}
.btn-mp-ok{background:#4F46E5;color:#fff;border:none;padding:10px 24px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s;}
.btn-mp-ok:hover{background:#4338CA;}
.btn-mp-ok:disabled{opacity:.5;pointer-events:none;}
.mp-empty{text-align:center;padding:40px;color:#94A3B8;grid-column:1/-1;}
.mp-loading{text-align:center;padding:40px;color:#94A3B8;grid-column:1/-1;}
</style>

<div class="mp-bg" id="mpBg" onclick="if(event.target===this)closePicker()">
  <div class="mp-box">
    <div class="mp-head">
      <h5>🖼️ Rasm kutubxonasidan tanlang</h5>
      <button class="mp-close" onclick="closePicker()">✕</button>
    </div>
    <div class="mp-search">
      <div class="mp-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="mpSearch" placeholder="Rasm nomini qidiring..." oninput="mpFilter()">
      </div>
    </div>
    <div class="mp-grid" id="mpGrid">
      <div class="mp-loading"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Yuklanmoqda...</div>
    </div>
    <div class="mp-foot">
      <div class="mp-foot-info" id="mpFootInfo">Rasm tanlang</div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-light" style="border-radius:10px;font-size:13px;" onclick="closePicker()">Bekor</button>
        <button class="btn-mp-ok" id="btnMpOk" disabled onclick="confirmPicker()">✓ Tanlash</button>
      </div>
    </div>
  </div>
</div>

<script>
let mpImages   = [];
let mpSelected = null; // {id, data, name}

function previewImgEdit(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            showSelectedImg(e.target.result, input.files[0].name);
            document.getElementById('selectedImageData').value = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function showSelectedImg(src, name) {
    const wrap = document.getElementById('imgPreviewWrap');
    const img  = document.getElementById('cur_img');
    img.src   = src;
    img.style.opacity = '1';
    wrap.style.display = '';
    // Uncheck remove
    const chk = wrap.querySelector('input[type=checkbox]');
    if (chk) chk.checked = false;
}

// ── Picker ochish
async function openMediaPicker() {
    document.getElementById('mpBg').classList.add('open');
    mpSelected = null;
    document.getElementById('btnMpOk').disabled = true;
    document.getElementById('mpFootInfo').textContent = 'Rasm tanlang';
    document.getElementById('mpSearch').value = '';
    await mpLoad();
}

async function mpLoad() {
    const grid = document.getElementById('mpGrid');
    grid.innerHTML = '<div class="mp-loading"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Yuklanmoqda...</div>';
    try {
        const res = await fetch('/admin/media_api.php?action=list');
        const d   = await res.json();
        mpImages  = d.images || [];
        mpRender(mpImages);
        // Lazy load thumbnails
        mpLazyLoad(mpImages);
    } catch(e) {
        grid.innerHTML = '<div class="mp-empty">❌ Yuklashda xato</div>';
    }
}

function mpRender(images) {
    const grid = document.getElementById('mpGrid');
    if (!images.length) {
        grid.innerHTML = `<div class="mp-empty">
            <div style="font-size:48px;margin-bottom:12px">🖼️</div>
            <p style="font-size:14px;font-weight:700;color:#334155;margin-bottom:6px">Kutubxona bo'sh</p>
            <a href="/admin/media_library.php" target="_blank"
               style="background:#4F46E5;color:#fff;padding:8px 18px;border-radius:10px;text-decoration:none;font-size:12px;font-weight:700;">
               + Rasm yuklash
            </a>
        </div>`;
        return;
    }
    grid.innerHTML = images.map(img => `
        <div class="mp-card" id="mpc_${img.id}" onclick="mpSelect(${img.id},'${escH(img.name)}')">
            <div class="mp-card-ph" id="mph_${img.id}">🖼️</div>
            <img id="mpi_${img.id}" src="" alt="${escH(img.name)}" style="display:none;width:100%;height:100px;object-fit:cover;">
            <div class="mp-sel-badge">✓</div>
            <div class="mp-card-name">${escH(img.name)}</div>
        </div>
    `).join('');
}

async function mpLazyLoad(images) {
    for (const img of images) {
        try {
            const res = await fetch(`/admin/media_api.php?action=get&id=${img.id}`);
            const d   = await res.json();
            if (d.status==='ok' && d.image) {
                const ph = document.getElementById('mph_'+img.id);
                const im = document.getElementById('mpi_'+img.id);
                if (im) {
                    im.src = d.image.data;
                    im.onload = () => { if(ph) ph.style.display='none'; im.style.display='block'; };
                }
                // Tanlangan bo'lsa data ni ham saqlash
                if (mpSelected && mpSelected.id === img.id) mpSelected.data = d.image.data;
            }
        } catch(e) {}
    }
}

function mpSelect(id, name) {
    document.querySelectorAll('.mp-card').forEach(c => c.classList.remove('sel'));
    document.getElementById('mpc_'+id).classList.add('sel');
    mpSelected = {id, name, data: null};
    document.getElementById('btnMpOk').disabled  = false;
    document.getElementById('mpFootInfo').textContent = `✅ Tanlandi: ${name}`;
    // data ni olish
    fetch(`/admin/media_api.php?action=get&id=${id}`)
        .then(r=>r.json()).then(d=>{ if(d.status==='ok') mpSelected.data = d.image.data; });
}

function mpFilter() {
    const q = document.getElementById('mpSearch').value.toLowerCase().trim();
    const filtered = q ? mpImages.filter(i=>i.name.toLowerCase().includes(q)) : mpImages;
    mpRender(filtered);
    mpLazyLoad(filtered);
}

function confirmPicker() {
    if (!mpSelected || !mpSelected.data) {
        if (mpSelected) {
            setTimeout(confirmPicker, 300); // data kelishini kutish
        }
        return;
    }
    document.getElementById('selectedImageData').value = mpSelected.data;
    showSelectedImg(mpSelected.data, mpSelected.name);
    closePicker();
}

function closePicker() {
    document.getElementById('mpBg').classList.remove('open');
}

function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>