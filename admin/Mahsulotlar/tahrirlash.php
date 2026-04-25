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

    // Avval min_qty ustunini qo'shish (bir martalik migration)
    @mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS min_qty INT NOT NULL DEFAULT 1");

    $sql = "UPDATE products SET
            barcode='$barcode', category_id='$category_id', name='$name',
            purchase_price='$purchase_price', optom_price='$optom_price',
            price='$price', quantity='$quantity', unit='$unit', min_qty='$min_qty', expiry_date=$expiry_date
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

                    <form method="POST">
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
</body>
</html>