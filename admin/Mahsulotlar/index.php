<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../../middleware/admin_check.php";
require_once "../../config/dokon_db.php";

$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin');
$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "ZIFRA SMART";

// ========================================================
// 📥 MAHSULOT VA YANGI KATEGORIYA SAQLASH LOGIKASI
// ========================================================
if (isset($_POST['saqlash'])) {
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $purchase_price = (float)$_POST['purchase_price'];
    $optom_price = (float)$_POST['optom_price']; 
    $price = (float)$_POST['price'];
    $quantity = (float)$_POST['quantity'];
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $min_qty = max(1, (int)($_POST['min_qty'] ?? 1));
    $expiry_date = !empty($_POST['expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['expiry_date']) . "'" : "NULL";

    // Kategoriya mantiqi (Yangi qo'shildimi yoki eskisi tanlandimi?)
    $category_id_post = $_POST['category_id'];
    
    if ($category_id_post === 'new' && !empty($_POST['new_category_name'])) {
        $new_cat_name = mysqli_real_escape_string($conn, trim($_POST['new_category_name']));
        
        // Bazada bunday kategoriya oldin bor-yo'qligini tekshiramiz (Dublikat bo'lmasligi uchun)
        $check_cat = mysqli_query($conn, "SELECT id FROM categories WHERE name = '$new_cat_name'");
        if (mysqli_num_rows($check_cat) > 0) {
            $category_id = mysqli_fetch_assoc($check_cat)['id']; // Bor bo'lsa o'shani oladi
        } else {
            mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$new_cat_name')");
            $category_id = mysqli_insert_id($conn); // Yangi ochilganning ID sini oladi
        }
    } else {
        $category_id = (int)$category_id_post;
    }

    // Avval min_qty ustunini qo'shish (bir martalik migration)
    @mysqli_query($conn, "ALTER TABLE products ADD COLUMN IF NOT EXISTS min_qty INT NOT NULL DEFAULT 1 COMMENT 'Minimal buyurtma miqdori'");

    $sql = "INSERT INTO products (barcode, category_id, name, purchase_price, optom_price, price, quantity, unit, min_qty, expiry_date)
            VALUES ('$barcode', '$category_id', '$name', '$purchase_price', '$optom_price', '$price', '$quantity', '$unit', '$min_qty', $expiry_date)";

    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Mahsulot muvaffaqiyatli saqlandi!'); window.location.href='index.php';</script>";
        exit;
    } else {
        echo "<script>alert('DIQQAT XATOLIK! Sababi: " . addslashes(mysqli_error($conn)) . "');</script>";
    }
}

// ========================================================
// 📊 STATISTIKALAR VA KATEGORIYALAR
// ========================================================
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as jami FROM products"))['jami'] ?? 0;
$out_of_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as tugadi FROM products WHERE quantity <= 0"))['tugadi'] ?? 0;

$today = date('Y-m-d');
$next_10_days = date('Y-m-d', strtotime('+10 days'));
$expiring_soon = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as muddati FROM products WHERE expiry_date BETWEEN '$today' AND '$next_10_days'"))['muddati'] ?? 0;

// Kategoriyalarni massivga yig'amiz (Filtr va Modal uchun alohida ishlatish oson bo'ladi)
$cats_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
$categories = [];
while($c = mysqli_fetch_assoc($cats_query)) {
    $categories[] = $c;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Ombor Boshqaruvi | <?= htmlspecialchars($site_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; overflow-x: hidden; }
        
        /* ASOSIY QISM */
        .bento-main { padding: 28px 32px; min-height: 100vh; margin-left: 90px; }
        .bento-header { margin-bottom: 30px; }
        .header-title { font-size: 28px; font-weight: 800; color: #0F172A; margin: 0; letter-spacing: -0.5px; }

        /* QUTILAR */
        .bento-card { background: #FFFFFF; border-radius: 20px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); border: 1px solid #E2E8F0; display: flex; align-items: center; transition: 0.3s; height: 100%; }
        .bento-card:hover { border-color: #CBD5E1; box-shadow: 0 6px 12px rgba(0,0,0,0.04); }
        .icon-box { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 20px; }
        .stat-title { font-size: 13px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 4px; }
        .stat-value { font-size: 26px; font-weight: 800; color: #0F172A; line-height: 1.2; }

        /* QIDIRUV VA FILTR PANEL */
        .toolbar-wrapper { display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; padding: 15px; border-radius: 16px; border: 1px solid #E2E8F0; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); flex-wrap: wrap; gap: 15px; }
        
        .search-group { display: flex; align-items: center; gap: 15px; flex: 1; }
        
        /* Qidiruv inputi */
        .search-bar { position: relative; max-width: 400px; flex: 1; }
        .search-input { width: 100%; padding: 12px 20px 12px 45px; border-radius: 12px; border: 1px solid #E2E8F0; background: #F8FAFC; font-size: 14px; font-weight: 500; color: #0F172A; transition: 0.2s; }
        .search-input:focus { outline: none; border-color: #4F46E5; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; }

        /* Kategoriya Filtri */
        .filter-select { padding: 12px 16px; border-radius: 12px; border: 1px solid #E2E8F0; background: #F8FAFC; font-size: 14px; font-weight: 600; color: #334155; outline: none; cursor: pointer; min-width: 220px; }
        .filter-select:focus { border-color: #4F46E5; background: #FFFFFF; }
        
        .btn-bento { background: #0F172A; color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 6px rgba(15, 23, 42, 0.2); cursor: pointer; }
        .btn-bento:hover { background: #1E293B; transform: translateY(-2px); }

        /* JADVAL */
        .table-container { background: #FFFFFF; border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; }
        .bento-table { width: 100%; border-collapse: collapse; }
        .bento-table th { padding: 16px 20px; font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; border-bottom: 1px solid #E2E8F0; background: #F8FAFC; white-space: nowrap; }
        .bento-table td { padding: 16px 20px; font-size: 14px; font-weight: 500; color: #334155; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        .bento-table tbody tr:hover td { background: #F8FAFC; }

        /* MODAL (QALQIB CHIQUVCHI OYNA) */
        .modal-content { border-radius: 20px; border: none; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); }
        .modal-header { background: #F8FAFC; border-bottom: 1px solid #E2E8F0; padding: 20px 24px; border-radius: 20px 20px 0 0; }
        .modal-title { font-weight: 800; color: #0F172A; font-size: 18px; }
        .modal-body { padding: 24px; }
        .modal-footer { border-top: 1px solid #E2E8F0; padding: 20px 24px; background: #F8FAFC; border-radius: 0 0 20px 20px; }
        
        .bento-label { font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; display: block; }
        .bento-input-modal { width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid #E2E8F0; background: #FFFFFF; font-size: 14px; font-weight: 500; color: #0F172A; transition: 0.2s; margin-bottom: 16px; }
        .bento-input-modal:focus { border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); outline: none; }
        
        .btn-submit { background: #4F46E5; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; transition: 0.2s; }
        .btn-submit:hover { background: #4338CA; }

        @media (max-width: 768px) {
            .bento-main { padding: 20px; }
        }
    </style>
</head>
<body>

    <?php 
        $active_menu = 'ombor'; 
        include "../includes/sidebar.php"; 
    ?>

    <div class="bento-main">
        
        <div class="bento-header">
            <h1 class="header-title">Ombor Boshqaruvi 📦</h1>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="bento-card">
                    <div class="icon-box" style="background: #EEF2FF; color: #4F46E5;"><i class="fas fa-boxes"></i></div>
                    <div>
                        <div class="stat-title">Jami Tovar (Xil)</div>
                        <div class="stat-value"><?= $total_products ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="bento-card" style="border-color: #FECACA;">
                    <div class="icon-box" style="background: #FEF2F2; color: #EF4444;"><i class="fas fa-hourglass-end"></i></div>
                    <div>
                        <div class="stat-title text-danger">Muddati O'tayotganlar</div>
                        <div class="stat-value text-danger"><?= $expiring_soon ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="bento-card" style="border-color: #FDE68A;">
                    <div class="icon-box" style="background: #FFFBEB; color: #F59E0B;"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <div class="stat-title text-warning">Tugagan Mahsulotlar</div>
                        <div class="stat-value" style="color: #D97706;"><?= $out_of_stock ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="toolbar-wrapper">
            <div class="search-group">
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="live_search" class="search-input" placeholder="Shtrix-kod yoki nomini qidiring...">
                </div>
                
                <select id="category_filter" class="filter-select">
                    <option value="">Barcha Kategoriyalar</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <button type="button" class="btn-bento" data-toggle="modal" data-target="#addProductModal">
                    <i class="fas fa-plus mr-1"></i> Tovar Qo'shish
                </button>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="bento-table">
                    <thead>
                        <tr>
                            <th>Shtrix-kod</th>
                            <th>Nomi va Kategoriyasi</th>
                            <th>Narx (Sotuv)</th>
                            <th>Zaxira Soni</th>
                            <th>Muddati</th>
                            <th class="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody id="search_results">
                        </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-box-open text-primary mr-2"></i> Yangi Tovar Kiritish</h5>
                    <button type="button" class="close text-muted" data-dismiss="modal" style="border:none; background:none; font-size:24px;">&times;</button>
                </div>
                
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="bento-label">Shtrix-kod</label>
                                <input type="text" name="barcode" class="bento-input-modal" placeholder="Skanerlang..." required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="bento-label">Kategoriya</label>
                                <select name="category_id" id="catSelect" class="bento-input-modal" onchange="checkNewCategory(this)" required>
                                    <option value="" disabled selected>-- Tanlang --</option>
                                    <option value="new" style="font-weight: 800; color: #4F46E5;">➕ Yangi Kategoriya Ochish</option>
                                    <?php foreach($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="new_category_name" id="newCatInput" class="bento-input-modal mt-2" placeholder="Yangi kategoriya nomini yozing..." style="display: none; border-color: #4F46E5; background: #EEF2FF;">
                            </div>
                            
                            <div class="col-md-12">
                                <label class="bento-label">Mahsulot Nomi</label>
                                <input type="text" name="name" class="bento-input-modal" placeholder="Masalan: Fanta 1L" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="bento-label">Kelish Narxi</label>
                                <input type="number" name="purchase_price" class="bento-input-modal" placeholder="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="bento-label">Ulgurji (Optom)</label>
                                <input type="number" name="optom_price" class="bento-input-modal" placeholder="0">
                            </div>
                            <div class="col-md-4">
                                <label class="bento-label" style="color: #10B981;">Sotuv (Dona)</label>
                                <input type="number" name="price" class="bento-input-modal" style="border-color: #10B981;" placeholder="0" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="bento-label">Qabul Soni</label>
                                <input type="number" step="0.001" name="quantity" class="bento-input-modal" value="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="bento-label">O'lchovi</label>
                                <select name="unit" class="bento-input-modal">
                                    <option value="dona">Dona</option>
                                    <option value="kg">Kg</option>
                                    <option value="litr">Litr</option>
                                    <option value="blok">Blok</option>
                                    <option value="qop">Qop</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="bento-label">Min. buyurtma <span class="text-muted font-weight-normal">(dona/blok)</span></label>
                                <input type="number" name="min_qty" class="bento-input-modal" value="1" min="1" placeholder="1">
                            </div>
                            <div class="col-md-4">
                                <label class="bento-label">Yaroqlilik <span class="text-muted font-weight-normal">(Ixtiyoriy)</span></label>
                                <input type="date" name="expiry_date" class="bento-input-modal">
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Bekor qilish</button>
                        <button type="submit" name="saqlash" class="btn-submit"><i class="fas fa-save mr-2"></i> Bazaga Yozish</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. Kategoriyada "Yangi qo'shish" ni tanlaganda Inputni chiqarish
    function checkNewCategory(selectObj) {
        var newCatInput = document.getElementById("newCatInput");
        if (selectObj.value === "new") {
            newCatInput.style.display = "block";
            newCatInput.setAttribute("required", "required"); // Yozishni majburiy qilish
            newCatInput.focus();
        } else {
            newCatInput.style.display = "none";
            newCatInput.removeAttribute("required");
        }
    }

    // 2. QIDIRUV VA FILTR MANTIQI (AJAX)
    $(document).ready(function() {
        // Ma'lumotni yuklash funksiyasi (Qidiruv + Kategoriya IDsini jo'natadi)
        function load_data() {
            var query_text = $('#live_search').val();
            var category_id = $('#category_filter').val(); // Tanlangan kategoriyani olamiz
            
            $.ajax({
                url: "fetch_products.php",
                method: "POST",
                data: {
                    query: query_text,
                    category_filter: category_id
                },
                success: function(data) { 
                    $('#search_results').html(data); 
                }
            });
        }
        
        load_data(); // Boshida ishga tushadi
        
        // Qidiruv maydoniga yozganda ishlaydi
        $('#live_search').keyup(function() { 
            load_data(); 
        });

        // Kategoriya ro'yxati o'zgarganda ishlaydi (Jonli Filtr)
        $('#category_filter').change(function() {
            load_data();
        });

        // Modal ochilganda avtomat Shtrix-kodga tayyor turadi
        $('#addProductModal').on('shown.bs.modal', function () {
            $('input[name="barcode"]').trigger('focus');
        });
    });
</script>

</body>
</html>