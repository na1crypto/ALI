<?php
session_start();

// Agar tizimga kirmagan bo'lsa qaytarish
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php"); 
    exit;
}

require_once "../config/dokon_db.php";

$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "ZIFRA SMART";
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yordam markazi | <?= htmlspecialchars($site_name) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; }
        
        /* O'rta qismni markazlash */
        .main-content { 
            padding: 40px 20px; 
            min-height: 100vh;
            display: flex; 
            justify-content: center; 
            align-items: flex-start;
        }

        /* Oq quti dizayni */
        .support-card {
            background: #FFFFFF;
            width: 100%;
            max-width: 650px;
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            border: 1px solid #E2E8F0;
            text-align: center;
        }

        .support-header {
            font-size: 28px;
            font-weight: 800;
            color: #0F172A;
            margin-bottom: 40px;
            letter-spacing: -0.5px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 20px;
        }

        /* Havolalar va tugmalar */
        .help-link {
            display: inline-block;
            font-size: 16px;
            font-weight: 600;
            color: #2563EB;
            text-decoration: none;
            transition: 0.2s;
        }
        .help-link:hover { color: #1D4ED8; text-decoration: underline; }

        .btn-action {
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary-custom {
            background: #2563EB;
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-primary-custom:hover { background: #1D4ED8; transform: translateY(-2px); color: white; text-decoration: none;}

        .btn-outline-custom {
            background: #FFFFFF;
            color: #2563EB;
            border: 2px solid #2563EB;
        }
        .btn-outline-custom:hover { background: #EFF6FF; transform: translateY(-2px); }

        .divider {
            height: 1px;
            background: #E2E8F0;
            margin: 40px 0;
        }

        /* Dastur haqida qismi */
        .app-info {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .app-info .dev-name {
            font-weight: 800;
            color: #4F46E5;
        }
        .status-badge {
            display: inline-block;
            background: #ECFDF5;
            color: #059669;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <?php 
        $active_menu = 'support'; 
        // include "includes/sidebar.php"; // O'zingizni papkaga qarab yoqib qo'yasiz
    ?>

    <div class="main-content">
        <div class="support-card">
            
            <div class="support-header">
                Yordam markazi
            </div>

            <div>
                <div class="section-title">O'quv materiallari</div>
                <a href="#" class="help-link"><i class="fas fa-book-reader mr-2"></i> Tizimdan foydalanish bo'yicha qo'llanma</a>
            </div>

            <div class="divider"></div>

            <div>
                <div class="section-title">24/7 Qo'llab-quvvatlash xizmati</div>
                
                <a href="https://t.me/SizningTelegramUsername" target="_blank" class="btn-action btn-primary-custom">
                    <i class="fab fa-telegram-plane" style="font-size: 20px;"></i> Dasturchi bilan chat
                </a>
                
                <button class="btn-action btn-outline-custom" onclick="alert('Xatolik jurnali serverga muvaffaqiyatli yuborildi!')">
                    <i class="fas fa-bug"></i> Xatolik haqida xabar berish
                </button>
            </div>

            <div class="divider"></div>

            <div>
                <div class="section-title">Ilova haqida</div>
                
                <div class="app-info">
                    <div>Versiya: <b>1.0.0 (Smart POS)</b></div>
                    <div>Muallif: <span class="dev-name">Nasibjonov Muhammadali</span></div>
                    <div class="status-badge"><i class="fas fa-check-circle mr-1"></i> Sizda so'nggi versiya o'rnatilgan</div>
                </div>

                <button class="btn-action btn-primary-custom" onclick="alert('Dastur versiyasi eng so\'nggi holatda. Yangilanishlar yo\'q.')">
                    <i class="fas fa-sync-alt"></i> Yangilanishlarni tekshirish
                </button>
            </div>

        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</body>
</html>