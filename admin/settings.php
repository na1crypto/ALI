<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 🔴 CHIQISH (LOGOUT)
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php"); 
    exit;
}

// 1. BAZAGA ULANISH
require_once "../middleware/admin_check.php";
require_once "../config/dokon_db.php";

// =========================================================
// 🔒 XAVFSIZLIK: FAQAT SUPER ADMIN KIRA OLADI!
// =========================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    die("
    <div style='display: flex; align-items: center; justify-content: center; height: 100vh; background-color: #F8FAFC; font-family: \"Inter\", sans-serif;'>
        <div style='background: white; padding: 50px; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 500px; border: 1px solid #E2E8F0;'>
            <div style='width: 80px; height: 80px; background: #FEF2F2; color: #EF4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 20px;'>
                <i class='fas fa-shield-alt'></i>
            </div>
            <h1 style='color:#0F172A; font-size:28px; font-weight: 800; margin-bottom: 10px; letter-spacing: -0.5px;'>Kirish Taqiqlangan!</h1>
            <p style='color: #64748B; font-size: 15px; font-weight: 500; margin-bottom: 30px; line-height: 1.5;'>Kechirasiz, sizda bu sahifaga kirish huquqi yo'q. Faqat <b style='color:#0F172A;'>Super Admin</b> do'kon sozlamalarini o'zgartira oladi.</p>
            <button onclick='window.history.back()' style='padding: 14px 30px; background: #4F46E5; color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);'>
                <i class='fas fa-arrow-left mr-2'></i> Orqaga qaytish
            </button>
        </div>
    </div>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    ");
}
$is_super = true;

// 2. BAZADAGI XATOLARNI AVTOMATIK TUZATISH (Self-Healing)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, store_name VARCHAR(255) DEFAULT 'ZIFRA SMART')");
mysqli_query($conn, "ALTER TABLE settings ADD COLUMN IF NOT EXISTS receipt_header TEXT AFTER store_name");
mysqli_query($conn, "ALTER TABLE settings ADD COLUMN IF NOT EXISTS receipt_footer TEXT AFTER receipt_header");

$check_st = mysqli_query($conn, "SELECT id FROM settings WHERE id=1");
if (mysqli_num_rows($check_st) == 0) {
    mysqli_query($conn, "INSERT INTO settings (id, store_name) VALUES (1, 'ZIFRA SMART')");
}

$status = "";
if (isset($_POST['update_settings'])) {
    $name   = mysqli_real_escape_string($conn, trim($_POST['store_name']));
    $head   = mysqli_real_escape_string($conn, trim($_POST['receipt_header']));
    $foot   = mysqli_real_escape_string($conn, trim($_POST['receipt_footer']));

    $update = mysqli_query($conn, "UPDATE settings SET store_name='$name', receipt_header='$head', receipt_footer='$foot' WHERE id=1");
    $status = $update ? "success" : "error";
}

$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "SMART POS";
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sozlamalar | <?= htmlspecialchars($site_name) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; }
        
        .bento-main { margin-left: 90px; padding: 28px 32px; min-height: 100vh; transition: margin-left 0.4s cubic-bezier(0.4,0,0.2,1); }
        .bento-header { margin-bottom: 40px; }
        .header-title { font-size: 28px; font-weight: 800; color: #0F172A; margin: 0; letter-spacing: -0.5px; }

        .settings-card { background: #FFFFFF; border-radius: 24px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); border: 1px solid #E2E8F0; height: 100%; }
        .bento-label { font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .bento-input { width: 100%; padding: 16px 20px; border-radius: 14px; border: 1px solid #E2E8F0; background: #F8FAFC; font-size: 15px; font-weight: 600; color: #0F172A; transition: 0.2s ease; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.01); }
        .bento-input:focus { outline: none; border-color: #4F46E5; background: #FFFFFF; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        textarea.bento-input { resize: vertical; min-height: 120px; line-height: 1.6; }

        .btn-submit { background: #4F46E5; color: white; border: none; padding: 14px 32px; border-radius: 12px; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background: #4338CA; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(79, 70, 229, 0.3); }
        
        .bento-alert { background: #ECFDF5; border: 1px solid #A7F3D0; padding: 16px 24px; border-radius: 16px; display: flex; align-items: center; gap: 15px; color: #065F46; font-weight: 600; font-size: 15px; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.1); animation: slideDown 0.4s ease-out; }
        .bento-alert i { font-size: 24px; color: #10B981; }

        /* SUPPORT CARD STYLES */
        .support-card { background: linear-gradient(145deg, #266fe4, #214db3); border-radius: 24px; padding: 40px 30px; color: white; height: 100%; position: relative; overflow: hidden; box-shadow: 0 15px 30px rgba(15, 23, 42, 0.15); text-align: center; }
        .support-circle { position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .support-icon-wrap { width: 80px; height: 80px; background: rgba(79, 70, 229, 0.2); border: 2px solid #4F46E5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #818CF8; margin: 0 auto 20px; }
        
        .btn-support { display: flex; align-items: center; justify-content: center; width: 100%; padding: 14px; border-radius: 12px; font-weight: 600; font-size: 15px; text-decoration: none; transition: 0.2s; margin-bottom: 12px; gap: 10px; }
        .btn-tg { background: #0088cc; color: white; }
        .btn-tg:hover { background: #0077B5; color: white; transform: translateY(-2px); text-decoration: none; }
        .btn-phone { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
        .btn-phone:hover { background: rgba(255,255,255,0.2); color: white; transform: translateY(-2px); text-decoration: none; }

        .sys-info { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 12px; font-size: 13px; color: #94A3B8; text-align: left; margin-top: 20px; }
        .sys-info div { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .sys-info div:last-child { margin-bottom: 0; }
        .sys-info span { color: white; font-weight: 600; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            .bento-main { margin-left: 0; padding: 20px; }
            .settings-card, .support-card { padding: 24px; }
        }
    </style>
</head>
<body>

    <?php 
        $active_menu = 'sozlamalar'; 
        include "includes/sidebar.php"; 
    ?>

    <div class="bento-main">
        
        <div class="bento-header">
            <h1 class="header-title">Tizim Sozlamalari ⚙️</h1>
            <p class="text-muted mt-2 mb-0" style="font-weight: 500; font-size: 14px;">Do'kon ma'lumotlari va chek ko'rinishini boshqarish.</p>
        </div>

        <?php if($status == "success"): ?>
            <div class="bento-alert">
                <i class="fas fa-check-circle"></i> 
                <div>
                    <div style="font-size: 15px;">Muvaffaqiyatli saqlandi!</div>
                    <div style="font-size: 13px; font-weight: 500; color: #047857;">Tizim ma'lumotlari va chek dizayni yangilandi.</div>
                </div>
            </div>
        <?php elseif($status == "error"): ?>
            <div class="bento-alert" style="background: #FEF2F2; border-color: #FECACA; color: #B91C1C;">
                <i class="fas fa-exclamation-circle" style="color: #EF4444;"></i>
                <div style="font-size: 15px;">Saqlashda xatolik yuz berdi! Qaytadan urinib ko'ring.</div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="settings-card">
                    <form action="" method="POST">
                        
                        <div class="mb-4 pb-4" style="border-bottom: 1px solid #F1F5F9;">
                            <h5 style="font-weight: 800; color: #0F172A; margin-bottom: 20px;"><i class="fas fa-store text-primary mr-2"></i> Asosiy Ma'lumotlar</h5>
                            
                            <label class="bento-label">Do'kon yoki Kompaniya Nomi</label>
                            <input type="text" name="store_name" class="bento-input mb-0" value="<?= htmlspecialchars($st['store_name'] ?? '') ?>" required placeholder="Masalan: ZIFRA SMART, MyMarket...">
                            <small style="color: #94A3B8; font-weight: 500; display: block; margin-top: 8px;">Bu nom dastur menyularida va asosiy oyna tepasida ko'rinadi.</small>
                        </div>

                        <div>
                            <h5 style="font-weight: 800; color: #0F172A; margin-bottom: 20px;"><i class="fas fa-print text-primary mr-2"></i> Chek (Printer) Sozlamalari</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="bento-label">Chekning Tepasi (Header)</label>
                                    <textarea name="receipt_header" class="bento-input" placeholder="Xush kelibsiz!&#10;Manzil: Chilonzor, Toshkent&#10;Tel: +998 90 123-45-67"><?= htmlspecialchars($st['receipt_header'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="bento-label">Chekning Pasti (Footer)</label>
                                    <textarea name="receipt_footer" class="bento-input" placeholder="Xaridingiz uchun tashakkur!&#10;Kassa apparati tizimi: Zifra POS"><?= htmlspecialchars($st['receipt_footer'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-2 pt-4" style="border-top: 1px solid #E2E8F0;">
                            <button type="submit" name="update_settings" class="btn-submit">
                                <i class="fas fa-save"></i> O'zgarishlarni Saqlash
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="support-card">
                    <div class="support-circle"></div>
                    
                    <div class="support-icon-wrap">
                        <i class="fas fa-headset"></i>
                    </div>
                    
                    <h4 style="font-weight: 800; margin-bottom: 10px;">Texnik Yordam</h4>
                    <p style="color: #94A3B8; font-size: 14px; font-weight: 500; margin-bottom: 30px;">
                        Tizimda nosozlik bormi yoki takliflaringiz bormi? To'g'ridan-to'g'ri dasturchi bilan bog'laning.
                    </p>

                    <a href="https://t.me/xd_9009" target="_blank" class="btn-support btn-tg">
                        <i class="fab fa-telegram-plane" style="font-size: 18px;"></i> Telegram orqali yozish
                    </a>

                    <a href="tel:+998991234567" class="btn-support btn-phone">
                        <i class="fas fa-phone-alt"></i> +998 91 288 33 38 
                    </a>

                    <div class="sys-info">
                        <div>Dasturchi: <span>M. Nasibjonov</span></div>
                        <div>Tizim versiyasi: <span>v1.0.0 (Pro)</span></div>
                        <div>Litsenziya: <span>Faol <i class="fas fa-check-circle text-success ml-1"></i></span></div>
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(49, 38, 255, 0.2);">
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>