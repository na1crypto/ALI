<?php
session_start();
require_once "../middleware/admin_check.php";
require_once "../config/dokon_db.php";

// =========================================================
// 🔒 XAVFSIZLIK: Faqat Superadmin kira oladi
// =========================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    die("<div style='text-align:center; padding: 50px; font-family: Arial, sans-serif; background: #F8FAFC; height: 100vh;'>
            <h1 style='color:#EF4444; font-size:60px; margin-bottom: 10px;'>🛑 XATOLIK!</h1>
            <h2 style='color:#0F172A;'>Kechirasiz, sizda bu sahifaga kirish huquqi yo'q.</h2>
            <p style='color: #64748B; font-size: 18px;'>Faqat Super Admin xodimlarni boshqara oladi.</p>
            <button onclick='window.history.back()' style='margin-top: 20px; padding: 14px 30px; background: #4F46E5; color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: bold; cursor: pointer;'>Orqaga qaytish</button>
         </div>");
}

// 1. Yangi xodim qo'shish
if (isset($_POST['add_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    
    // Parolni xavfsiz heshlash (Tavsiya etiladi) yoki aslidagidek saqlash
    // Agar oldingi tizimda hesh bo'lmasa, pastdagi heshni olib tashlab aslidagidek qoldiring:
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT); // Yoki shunchaki: mysqli_real_escape_string($conn, $_POST['password']);
    
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    $sql = "INSERT INTO users (name, username, password, role, status) VALUES ('$name', '$user', '$pass', '$role', 'active')";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Yangi xodim muvaffaqiyatli qo\'shildi!'); window.location.href='index.php';</script>";
        exit;
    }
}

// 2. Xodimni o'chirish
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    // O'zini o'zi yoki 'admin' loginli foydalanuvchini o'chira olmaydi
    mysqli_query($conn, "DELETE FROM users WHERE id = $id AND username != 'admin' AND id != {$_SESSION['user_id']}");
    header("Location: index.php");
    exit;
}

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "ZIFRA SMART";
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <title>Xodimlarni boshqarish | <?= htmlspecialchars($site_name) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <style>
        /* ======================================================== */
        /* 🔥 BENTO GRID & MODERN SAAS STYLES */
        /* ======================================================== */
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; overflow-x: hidden; }
        
        /* Modern Sidebar */
        .bento-sidebar { position: fixed; top: 0; left: 0; width: 280px; height: 100vh; background: #FFFFFF; border-right: 1px solid #E2E8F0; padding: 32px 24px; z-index: 100; display: flex; flex-direction: column; }
        .bento-brand { font-size: 24px; font-weight: 800; color: #0F172A; text-decoration: none; display: flex; align-items: center; gap: 12px; margin-bottom: 48px; letter-spacing: -0.5px; }
        .bento-brand span { color: #4F46E5; }
        
        .nav-item-modern { display: flex; align-items: center; padding: 14px 18px; border-radius: 16px; color: #64748B; text-decoration: none; font-weight: 600; font-size: 15px; margin-bottom: 8px; transition: all 0.2s ease; }
        .nav-item-modern:hover { background: #F1F5F9; color: #0F172A; text-decoration: none; }
        .nav-item-modern.active { background: #4F46E5; color: #FFFFFF; box-shadow: 0 8px 16px -4px rgba(79, 70, 229, 0.3); }
        .nav-item-modern.active i { color: #FFFFFF; }
        .nav-item-modern i { width: 24px; font-size: 18px; color: #94A3B8; margin-right: 12px; transition: 0.2s; }
        .nav-item-modern.text-danger:hover { background: #FEF2F2; color: #DC2626; }
        .nav-item-modern.text-danger:hover i { color: #DC2626; }

        /* User Profile in Sidebar */
        .sidebar-user { margin-top: auto; padding-top: 24px; border-top: 1px solid #E2E8F0; display: flex; align-items: center; gap: 12px; }
        .user-avatar-small { width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; }
        
        /* Main Content */
        .bento-main { margin-left: 280px; padding: 40px; min-height: 100vh; }
        
        /* Header */
        .bento-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header-title { font-size: 28px; font-weight: 800; color: #0F172A; margin: 0; letter-spacing: -0.5px; }

        /* Bento Card & Table */
        .bento-card { background: #FFFFFF; border-radius: 28px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -2px rgba(0,0,0,0.02); border: 1px solid #E2E8F0; }
        
        .bento-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
        .bento-table th { padding: 0 16px 12px; font-size: 13px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; border: none; }
        .bento-table td { padding: 16px; background: #F8FAFC; font-size: 15px; font-weight: 500; color: #0F172A; border-top: 1px solid #F1F5F9; border-bottom: 1px solid #F1F5F9; transition: 0.2s; }
        .bento-table tr:hover td { background: #F1F5F9; }
        .bento-table td:first-child { border-left: 1px solid #F1F5F9; border-radius: 16px 0 0 16px; }
        .bento-table td:last-child { border-right: 1px solid #F1F5F9; border-radius: 0 16px 16px 0; }

        /* Avatars and Badges */
        .user-avatar { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; margin-right: 15px; }
        
        .bento-badge { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 10px; font-weight: 600; font-size: 13px; letter-spacing: 0.3px; }
        .bg-soft-primary { background: #EEF2FF; color: #4F46E5; }
        .bg-soft-success { background: #ECFDF5; color: #10B981; }
        .bg-soft-danger { background: #FEF2F2; color: #EF4444; }
        .bg-soft-secondary { background: #F1F5F9; color: #64748B; }

        /* Buttons */
        .btn-bento { background: #4F46E5; color: white; border: none; padding: 12px 24px; border-radius: 14px; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-bento:hover { background: #4338CA; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4); color: white; text-decoration: none; }
        
        .btn-action-delete { width: 36px; height: 36px; border-radius: 10px; background: #FEF2F2; color: #EF4444; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; cursor: pointer; }
        .btn-action-delete:hover { background: #FCA5A5; color: white; transform: scale(1.05); }

        /* Modal Styles */
        .modal-content { border-radius: 28px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-header { border-bottom: 1px solid #E2E8F0; padding: 24px 32px; border-radius: 28px 28px 0 0; }
        .modal-body { padding: 32px; }
        .modal-footer { border-top: 1px solid #E2E8F0; padding: 24px 32px; border-radius: 0 0 28px 28px; background: #F8FAFC; }
        
        .bento-label { font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block; }
        .bento-input { width: 100%; padding: 14px 18px; border-radius: 14px; border: 2px solid #E2E8F0; background: #FFFFFF; font-size: 15px; font-weight: 500; color: #0F172A; transition: all 0.2s ease; margin-bottom: 20px; }
        .bento-input:focus { outline: none; border-color: #4F46E5; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
    </style>
</head>
<body>

    <div class="bento-sidebar">
        <a href="#" class="bento-brand">
            <i class="fas fa-layer-group" style="font-size: 28px; color: #4F46E5;"></i>
            <?= htmlspecialchars($site_name) ?><span>.</span>
        </a>

        <nav>
            <a href="../dashboard.php" class="nav-item-modern">
                <i class="fas fa-chart-pie"></i> Boshqaruv
            </a>
            <a href="../Mahsulotlar/index.php" class="nav-item-modern">
                <i class="fas fa-box-open"></i> Ombor
            </a>
            <a href="../sales_history.php" class="nav-item-modern">
                <i class="fas fa-receipt"></i> Savdo Tarixi
            </a>
            <a href="index.php" class="nav-item-modern active">
                <i class="fas fa-users"></i> Xodimlar
            </a>
            <a href="../settings.php" class="nav-item-modern">
                <i class="fas fa-sliders-h"></i> Sozlamalar
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="user-avatar-small">
                <?= strtoupper(substr($_SESSION['name'] ?? 'S', 0, 1)) ?>
            </div>
            <div style="flex: 1; overflow: hidden;">
                <div style="font-weight: 700; color: #0F172A; font-size: 15px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?= $_SESSION['name'] ?? 'Superadmin' ?></div>
                <div style="font-weight: 500; color: #64748B; font-size: 13px;">Boshqaruvchi</div>
            </div>
            <a href="../auth/logout.php" onclick="return confirm('Tizimdan chiqasizmi?')" class="nav-item-modern text-danger" style="padding: 10px; margin: 0;">
                <i class="fas fa-power-off" style="margin: 0; color: #94A3B8;"></i>
            </a>
        </div>
    </div>

    <div class="bento-main">
        
        <div class="bento-header">
            <div>
                <h1 class="header-title">Xodimlar Nazorati 👥</h1>
                <p class="text-muted mt-2 mb-0 font-weight-500">Tizimga kirish huquqiga ega barcha foydalanuvchilar ro'yxati.</p>
            </div>
            <button class="btn-bento" data-toggle="modal" data-target="#userModal">
                <i class="fas fa-user-plus"></i> Yangi xodim
            </button>
        </div>

        <div class="bento-card" style="padding: 10px 32px 32px 32px;">
            <div class="table-responsive" style="overflow-x: auto;">
                <table class="bento-table">
                    <thead>
                        <tr>
                            <th>F.I.SH / Ismi</th>
                            <th>Login</th>
                            <th>Vazifasi (Rol)</th>
                            <th>Holati</th>
                            <th>Oxirgi kirish</th>
                            <th class="text-right">Amal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($users)): 
                            // Avatar ranglari va Rol badjlari
                            $role_class = 'bg-soft-success';
                            $role_name = 'Sotuvchi (Kassir)';
                            $avatar_bg = '#ECFDF5'; $avatar_color = '#10B981';

                            if($row['role'] == 'superadmin') {
                                $role_class = 'bg-soft-danger';
                                $role_name = 'Super Admin';
                                $avatar_bg = '#FEF2F2'; $avatar_color = '#EF4444';
                            } elseif($row['role'] == 'admin') {
                                $role_class = 'bg-soft-primary';
                                $role_name = 'Admin (Menejer)';
                                $avatar_bg = '#EEF2FF'; $avatar_color = '#4F46E5';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar" style="background: <?= $avatar_bg ?>; color: <?= $avatar_color ?>;">
                                        <?= strtoupper(substr($row['name'], 0, 1)) ?>
                                    </div>
                                    <span class="font-weight-bold" style="font-size: 16px; color: #0F172A;">
                                        <?= htmlspecialchars($row['name']) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span style="font-family: monospace; background: #FFFFFF; padding: 4px 8px; border-radius: 6px; border: 1px solid #E2E8F0; color: #475569;">
                                    @<?= htmlspecialchars($row['username']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="bento-badge <?= $role_class ?>">
                                    <?= $role_name ?>
                                </span>
                            </td>
                            <td>
                                <?php if($row['status'] == 'active'): ?>
                                    <span class="bento-badge bg-soft-success"><i class="fas fa-circle mr-2" style="font-size: 8px;"></i> Faol</span>
                                <?php else: ?>
                                    <span class="bento-badge bg-soft-secondary"><i class="fas fa-circle mr-2" style="font-size: 8px;"></i> Faol emas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="color: #64748B; font-size: 14px; font-weight: 500;">
                                    <?= !empty($row['last_login']) ? date('d M, Y • H:i', strtotime($row['last_login'])) : 'Hali kirmagan' ?>
                                </div>
                            </td>
                            <td class="text-right">
                                <?php if($row['username'] != 'admin' && $row['id'] != $_SESSION['user_id']): ?>
                                    <a href="?del=<?= $row['id'] ?>" class="btn-action-delete" title="O'chirish" onclick="return confirm('Ushbu xodimni butunlay o\'chirib tashlamoqchimisiz?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #94A3B8; font-size: 20px;" title="Bu foydalanuvchini o'chirib bo'lmaydi">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            
            <div class="modal-header border-0 pb-0">
                <h4 class="modal-title font-weight-bold" style="color: #0F172A; letter-spacing: -0.5px;">Yangi Xodim</h4>
                <button type="button" class="close" data-dismiss="modal" style="outline: none;">&times;</button>
            </div>
            
            <div class="modal-body">
                <label class="bento-label">To'liq ism-sharifi</label>
                <input type="text" name="name" class="bento-input" placeholder="Masalan: Sardorbek Aliyev" required>
                
                <label class="bento-label">Kirish logini (Username)</label>
                <input type="text" name="username" class="bento-input" placeholder="sardor_01" required>
                
                <label class="bento-label">Maxfiy parol</label>
                <input type="password" name="password" class="bento-input" placeholder="Kamida 6 ta belgi" required>
                
                <label class="bento-label">Tizimdagi Vazifasi (Roli)</label>
                <select name="role" class="bento-input" style="cursor: pointer;" required>
                    <option value="sotuvchi">🛍️ Sotuvchi (Faqat kassa oynasi)</option>
                    <option value="admin">📦 Admin (Ombor va mahsulotlar)</option>
                    <option value="superadmin">👑 Super Admin (To'liq nazorat)</option>
                </select>
            </div>
            
            <div class="modal-footer border-0 pt-0" style="background: transparent;">
                <button type="button" class="btn btn-light px-4" data-dismiss="modal" style="border-radius: 12px; font-weight: 600;">Bekor qilish</button>
                <button type="submit" name="add_user" class="btn-bento m-0 px-4">Saqlash</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>