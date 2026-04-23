<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Bazaga ulanish
require_once "../../config/dokon_db.php";

// Tizimga kirmagan bo'lsa, haydash
if (!isset($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$status = "";
$message = "";

// 2. SOTUV MANTIG'I (Xavfsiz Tranzaksiya bilan)
if (isset($_POST['sotuv_tugmasi'])) {
    $p_id = (int)$_POST['p_id'];
    $miqdor = (float)$_POST['miqdor'];
    
    // Hozirgi kirib turgan xodim (Kassir) ID sini olish
    $u_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? 1); 

    // Mahsulot narxi va sonini tekshiramiz
    $query = mysqli_query($conn, "SELECT name, price, quantity FROM products WHERE id = '$p_id'");
    $res = mysqli_fetch_assoc($query);

    if ($res && $res['quantity'] >= $miqdor) {
        
        // TRANZAKSIYA BOSHLASH (Biri xato qilsa hammasi orqaga qaytadi)
        mysqli_begin_transaction($conn);
        
        try {
            $jami = $res['price'] * $miqdor;

            // 1. Salesga yozish
            mysqli_query($conn, "INSERT INTO sales (user_id, total_price) VALUES ('$u_id', '$jami')");
            $s_id = mysqli_insert_id($conn);

            // 2. Sale_itemsga yozish
            mysqli_query($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price) 
                                 VALUES ('$s_id', '$p_id', '$miqdor', '{$res['price']}')");

            // 3. Ombordan ayirish
            mysqli_query($conn, "UPDATE products SET quantity = quantity - '$miqdor' WHERE id = '$p_id'");

            // HAMMASI MUVAFFAQIYATLI!
            mysqli_commit($conn);
            $status = "success";
            $message = "<b>{$res['name']}</b> ({$miqdor} ta) muvaffaqiyatli sotildi!";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $status = "error";
            $message = "Xatolik yuz berdi, savdo bekor qilindi.";
        }
    } else {
        $status = "warning";
        $message = "Omborda yetarli mahsulot yo'q!";
    }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kassa (POS) | Smart Terminal</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <style>
        /* ======================================================== */
        /* 🔥 BENTO GRID & MODERN SAAS STYLES */
        /* ======================================================== */
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .bento-card { background: #FFFFFF; border-radius: 28px; padding: 40px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.05), 0 10px 15px -3px rgba(0,0,0,0.02); border: 1px solid #E2E8F0; width: 100%; max-width: 500px; }
        
        .bento-header { text-align: center; margin-bottom: 30px; }
        .bento-header-icon { width: 64px; height: 64px; background: #EEF2FF; color: #4F46E5; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 16px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.15); }
        .bento-title { font-size: 24px; font-weight: 800; color: #0F172A; letter-spacing: -0.5px; margin: 0; }
        
        .bento-label { font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block; }
        .bento-select, .bento-input { width: 100%; padding: 16px 20px; border-radius: 16px; border: 2px solid #E2E8F0; background: #F8FAFC; font-size: 15px; font-weight: 500; color: #0F172A; transition: all 0.2s ease; margin-bottom: 24px; appearance: none; }
        .bento-select:focus, .bento-input:focus { outline: none; border-color: #4F46E5; background: #FFFFFF; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        .select-wrapper { position: relative; }
        .select-wrapper::after { content: '\f107'; font-family: 'Font Awesome 5 Free'; font-weight: 900; position: absolute; right: 20px; top: 40%; transform: translateY(-50%); color: #64748B; pointer-events: none; }

        .btn-bento { width: 100%; background: #4F46E5; color: white; border: none; padding: 18px; border-radius: 16px; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-bento:hover { background: #4338CA; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4); }

        /* Alerts */
        .bento-alert { padding: 16px 20px; border-radius: 16px; display: flex; align-items: center; gap: 12px; font-weight: 600; margin-bottom: 24px; font-size: 14px; }
        .alert-success-modern { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .alert-warning-modern { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .alert-error-modern { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        <div class="bento-card">
            
            <div class="bento-header">
                <div class="bento-header-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2 class="bento-title">Sotuv Bo'limi (POS)</h2>
                <p class="text-muted mt-2 mb-0" style="font-size: 14px;">Tezkor va qulay xizmat</p>
            </div>

            <?php if($status == "success"): ?>
                <div class="bento-alert alert-success-modern">
                    <i class="fas fa-check-circle" style="font-size: 20px;"></i> 
                    <?= $message ?>
                </div>
            <?php elseif($status == "warning"): ?>
                <div class="bento-alert alert-warning-modern">
                    <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i> 
                    <?= $message ?>
                </div>
            <?php elseif($status == "error"): ?>
                <div class="bento-alert alert-error-modern">
                    <i class="fas fa-times-circle" style="font-size: 20px;"></i> 
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                
                <div class="select-wrapper">
                    <label class="bento-label">Mahsulotni tanlang</label>
                    <select name="p_id" class="bento-select" required>
                        <option value="" disabled selected>-- Ro'yxatdan tanlang --</option>
                        <?php
                        $prods = mysqli_query($conn, "SELECT id, name, quantity, price FROM products WHERE quantity > 0 ORDER BY name ASC");
                        while($p = mysqli_fetch_assoc($prods)) {
                            $narx = number_format($p['price'], 0, '.', ' ');
                            echo "<option value='{$p['id']}'>{$p['name']} — {$narx} UZS (Qoldiq: {$p['quantity']} ta)</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="bento-label">Miqdori (Soni)</label>
                    <input type="number" step="0.001" name="miqdor" class="bento-input" value="1" min="0.001" required>
                </div>

                <button type="submit" name="sotuv_tugmasi" class="btn-bento mt-2">
                    <i class="fas fa-check"></i> Tasdiqlash va Sotish
                </button>
                
            </form>

            <div class="text-center mt-4 pt-4" style="border-top: 1px solid #E2E8F0;">
                <a href="../../dashboard.php" style="color: #64748B; text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.2s;">
                    <i class="fas fa-arrow-left mr-1"></i> Asosiy menuga qaytish
                </a>
            </div>

        </div>
    </div>

</body>
</html>