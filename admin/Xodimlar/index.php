<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. BAZAGA VA MIDDLEWARE GA ULANISH (Absolute Path - 100% Aniq Yo'l)
// __DIR__ bu 'admin/Xodimlar' degani. dirname(__DIR__, 2) esa ikkita orqaga (asosiy papkaga) chiqadi.
$root_path = dirname(__DIR__, 2); 
require_once $root_path . "/middleware/admin_check.php";
require_once $root_path . "/config/dokon_db.php";

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
            <p style='color: #64748B; font-size: 15px; font-weight: 500; margin-bottom: 30px; line-height: 1.5;'>Kechirasiz, sizda bu sahifaga kirish huquqi yo'q. Faqat <b style='color:#0F172A;'>Super Admin</b> xodimlarni boshqara oladi.</p>
            <button onclick='window.history.back()' style='padding: 14px 30px; background: #4F46E5; color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);'>
                <i class='fas fa-arrow-left mr-2'></i> Orqaga qaytish
            </button>
        </div>
    </div>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    ");
}
$is_super = true;

// DO'KON NOMINI OLISH
$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "ZIFRA SMART";

$status = "";

// migration: address ustuni
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL");

// 2. XODIM QO'SHISH LOGIKASI
if (isset($_POST['add_user'])) {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']     ?? ''));
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']    ?? ''));
    $address  = mysqli_real_escape_string($conn, trim($_POST['address']  ?? ''));
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $role     = mysqli_real_escape_string($conn, $_POST['role'] ?? 'sotuvchi');

    // Login band emasligini tekshirish
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
    if (mysqli_num_rows($check) > 0) {
        $status = "error_exist";
    } else {
        // TiDB Cloud: AUTO_INCREMENT yo'q — ID ni qo'lda hisoblaymiz
        $row   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(id),0)+1 AS nid FROM users"));
        $newId = (int)$row['nid'];

        $sql = "INSERT INTO users (id, name, username, password, phone, address, role)
                VALUES ($newId, '$name', '$username', '$password', '$phone', '$address', '$role')";
        if (mysqli_query($conn, $sql)) {
            $status = "success";
        } else {
            $status = "error_db";
            $db_err = mysqli_error($conn);
        }
    }
}

// 3. PAROLNI O'ZGARTIRISH (Superadmin istalgan xodim parolini o'zgartira oladi)
if (isset($_POST['change_password'])) {
    $uid = (int)$_POST['user_id'];
    $new_pass = trim($_POST['new_password']);
    if ($uid > 0 && strlen($new_pass) >= 4) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        if (mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE id=$uid")) {
            $status = "pass_changed";
        } else {
            $status = "error";
        }
    } else {
        $status = "pass_short";
    }
}

// 4. XODIMNI O'CHIRISH
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id=$id AND role NOT IN ('superadmin','admin','menejer')");
        header("Location: index.php?status=deleted");
        exit;
    }
}

if(isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $status = "deleted";
}

// XODIMLAR RO'YXATINI OLISH
$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xodimlar | <?= htmlspecialchars($site_name) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; overflow-x: hidden; }
        .bento-main { margin-left: 90px; padding: 28px 32px; min-height: 100vh; transition: margin-left 0.4s cubic-bezier(0.4,0,0.2,1); }
        .bento-header { margin-bottom: 30px; }
        .header-title { font-size: 28px; font-weight: 800; color: #0F172A; margin: 0; letter-spacing: -0.5px; }
        .bento-card { background: #FFFFFF; border-radius: 20px; padding: 24px 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #E2E8F0; margin-bottom: 24px; }
        .bento-label { font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .bento-input { width: 100%; padding: 14px 18px; border-radius: 12px; border: 1px solid #E2E8F0; background: #F8FAFC; font-size: 14px; font-weight: 500; color: #0F172A; transition: 0.2s ease; margin-bottom: 20px; }
        .bento-input:focus { outline: none; border-color: #4F46E5; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .btn-submit { background: #4F46E5; color: white; border: none; padding: 14px 24px; border-radius: 12px; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-submit:hover { background: #4338CA; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(79, 70, 229, 0.3); }
        .bento-table { width: 100%; border-collapse: collapse; }
        .bento-table th { padding: 16px; font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; border-bottom: 1px solid #E2E8F0; background: #FFFFFF; white-space: nowrap; }
        .bento-table td { padding: 16px; background: #FFFFFF; font-size: 14px; font-weight: 500; color: #334155; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        .bento-table tbody tr:hover td { background: #F8FAFC; }
        .badge-role { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .role-superadmin { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .role-admin      { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .role-sotuvchi   { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .role-menejer    { background: #FFF7ED; color: #EA580C; border: 1px solid #FED7AA; }
        .user-avatar-sm { width: 36px; height: 36px; border-radius: 10px; background: #F1F5F9; color: #475569; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; margin-right: 12px; }
        .bento-alert { padding: 16px 20px; border-radius: 14px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 14px; margin-bottom: 24px; animation: slideDown 0.4s ease-out; }
        .alert-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .alert-danger { background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 992px) { .bento-main { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>

    <?php 
        $active_menu = 'xodimlar'; 
        $base_folder = '../'; 
        // __DIR__ hozir admin/Xodimlar papkasi. dirname(__DIR__) esa admin papkasi degani.
        $sidebar_path = dirname(__DIR__) . "/includes/sidebar.php";
        if(file_exists($sidebar_path)) {
            require_once $sidebar_path; 
        } else {
            echo "<div style='padding:20px; background:red; color:white; z-index:9999; position:fixed;'>DIQQAT: sidebar.php shu manzilda topilmadi: " . $sidebar_path . "</div>";
        }
    ?>

    <div class="bento-main">
        
        <div class="bento-header">
            <h1 class="header-title">Xodimlar Boshqaruvi 👥</h1>
            <p class="text-muted mt-2 mb-0" style="font-weight: 500; font-size: 14px;">Yangi kassir va menejerlarni qo'shish yoki o'chirish.</p>
        </div>

        <?php if($status == "success"): ?>
            <div class="bento-alert alert-success"><i class="fas fa-check-circle" style="font-size: 20px;"></i> Yangi xodim muvaffaqiyatli qo'shildi!</div>
        <?php elseif($status == "deleted"): ?>
            <div class="bento-alert alert-success"><i class="fas fa-trash-alt" style="font-size: 20px;"></i> Xodim tizimdan o'chirildi.</div>
        <?php elseif($status == "error_exist"): ?>
            <div class="bento-alert alert-danger"><i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i> Bu Login band! Boshqa login o'ylab toping.</div>
        <?php elseif($status == "error_db"): ?>
            <div class="bento-alert alert-danger"><i class="fas fa-times-circle" style="font-size: 20px;"></i> DB xatosi: <?= htmlspecialchars($db_err ?? '') ?></div>
        <?php elseif($status == "error"): ?>
            <div class="bento-alert alert-danger"><i class="fas fa-times-circle" style="font-size: 20px;"></i> Xatolik yuz berdi. Qaytadan urinib ko'ring.</div>
        <?php elseif($status == "pass_changed"): ?>
            <div class="bento-alert alert-success"><i class="fas fa-key" style="font-size: 20px;"></i> Parol muvaffaqiyatli o'zgartirildi!</div>
        <?php elseif($status == "pass_short"): ?>
            <div class="bento-alert alert-danger"><i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i> Parol kamida 4 ta belgidan iborat bo'lishi kerak!</div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div class="bento-card">
                    <h5 style="font-weight: 800; color: #0F172A; margin-bottom: 24px;"><i class="fas fa-user-plus text-primary mr-2"></i> Yangi Xodim</h5>
                    
                    <form method="POST">
                        <label class="bento-label">To'liq Ismi *</label>
                        <input type="text" name="name" class="bento-input" placeholder="Sardor Komilov" required
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

                        <label class="bento-label">Telefon raqami</label>
                        <input type="tel" name="phone" class="bento-input" placeholder="+998 90 123 45 67"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

                        <label class="bento-label">Manzil (yashash joyi)</label>
                        <input type="text" name="address" class="bento-input" placeholder="Toshkent, Chilonzor 5"
                               value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">

                        <label class="bento-label">Login *</label>
                        <input type="text" name="username" class="bento-input" placeholder="sardor_123" required
                               autocomplete="off" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">

                        <label class="bento-label">Parol *</label>
                        <input type="password" name="password" class="bento-input" placeholder="••••••••" required autocomplete="new-password">

                        <label class="bento-label">Vazifasi (Roli) *</label>
                        <select name="role" class="bento-input" style="cursor: pointer;" required>
                            <option value="sotuvchi">🧾 Sotuvchi (Kassa)</option>
                            <option value="menejer">📋 Menejer (Buyurtmalar)</option>
                            <option value="admin">🛡️ Admin (Ombor/Boshqaruv)</option>
                        </select>

                        <button type="submit" name="add_user" class="btn-submit mt-2">
                            <i class="fas fa-user-plus"></i> Xodimni Qo'shish
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="bento-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 24px 30px; border-bottom: 1px solid #E2E8F0; background: #FFFFFF;">
                        <h5 style="font-weight: 800; color: #0F172A; margin: 0;"><i class="fas fa-users text-primary mr-2"></i> Mavjud Xodimlar</h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="bento-table">
                            <thead>
                                <tr>
                                    <th>Xodim</th>
                                    <th>Telefon / Manzil</th>
                                    <th>Lavozim</th>
                                    <th>Holat</th>
                                    <th class="text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = mysqli_fetch_assoc($users)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-sm">
                                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:700;color:#0F172A;font-size:14px;"><?= htmlspecialchars($u['name']) ?></div>
                                                <div style="font-size:11px;color:#64748B;font-family:monospace;font-weight:600;">@<?= htmlspecialchars($u['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(!empty($u['phone'])): ?>
                                            <div style="font-size:13px;font-weight:600;color:#0F172A;">
                                                <i class="fas fa-phone-alt" style="color:#4F46E5;font-size:11px;"></i>
                                                <?= htmlspecialchars($u['phone']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(!empty($u['address'])): ?>
                                            <div style="font-size:11px;color:#64748B;margin-top:2px;">
                                                <i class="fas fa-map-marker-alt" style="font-size:10px;"></i>
                                                <?= htmlspecialchars($u['address']) ?>
                                            </div>
                                        <?php elseif(empty($u['phone'])): ?>
                                            <span style="font-size:12px;color:#CBD5E1;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($u['role'] == 'superadmin'): ?>
                                            <span class="badge-role role-superadmin"><i class="fas fa-crown"></i> Boshqaruvchi</span>
                                        <?php elseif($u['role'] == 'admin'): ?>
                                            <span class="badge-role role-admin"><i class="fas fa-shield-alt"></i> Admin</span>
                                        <?php elseif($u['role'] == 'menejer'): ?>
                                            <span class="badge-role role-menejer"><i class="fas fa-clipboard-list"></i> Menejer</span>
                                        <?php elseif($u['role'] == 'mijoz'): ?>
                                            <span class="badge-role" style="background:#F3F0FF;color:#7C3AED;border:1px solid #DDD6FE;"><i class="fas fa-user"></i> Mijoz</span>
                                        <?php else: ?>
                                            <span class="badge-role role-sotuvchi"><i class="fas fa-cash-register"></i> Sotuvchi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $st_val = $u['status'] ?? 'active'; ?>
                                        <?php if($st_val === 'active'): ?>
                                            <span style="font-size:12px;font-weight:700;color:#059669;"><i class="fas fa-circle" style="font-size:8px;"></i> Faol</span>
                                        <?php else: ?>
                                            <span style="font-size:12px;font-weight:700;color:#94A3B8;"><i class="fas fa-circle" style="font-size:8px;"></i> Nofaol</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <div class="d-flex justify-content-end align-items-center" style="gap:6px;">
                                            <button onclick="openPassModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"
                                                style="background:#EFF6FF;color:#2563EB;border:none;border-radius:8px;padding:7px 11px;font-weight:600;font-size:12px;cursor:pointer;">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if(!in_array($u['role'], ['superadmin','admin','menejer'])): ?>
                                                <a href="?delete=<?= $u['id'] ?>" onclick="return confirm('Rostdan ham o\'chirasizmi?')"
                                                   style="background:#FEF2F2;color:#EF4444;border-radius:8px;padding:7px 11px;font-weight:600;font-size:12px;text-decoration:none;">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size:11px;color:#CBD5E1;padding:7px 8px;"><i class="fas fa-lock"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

<!-- ============ PAROLNI O'ZGARTIRISH MODALI ============ -->
<div id="passModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:20px; padding:36px 32px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.15); position:relative;">
        <button onclick="closePassModal()" style="position:absolute; top:16px; right:16px; background:#F1F5F9; border:none; border-radius:8px; width:32px; height:32px; font-size:16px; cursor:pointer; color:#64748B;">&times;</button>

        <div style="text-align:center; margin-bottom:24px;">
            <div style="width:60px; height:60px; background:#EFF6FF; border-radius:16px; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:26px; color:#2563EB;">
                <i class="fas fa-key"></i>
            </div>
            <h5 style="font-weight:800; color:#0F172A; margin:0; font-size:18px;">Parolni O'zgartirish</h5>
            <p id="passModalName" style="color:#64748B; font-size:14px; margin-top:6px; font-weight:500;"></p>
        </div>

        <form method="POST" onsubmit="return validatePassForm()">
            <input type="hidden" name="user_id" id="passUserId">

            <label style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Yangi Parol</label>
            <div style="position:relative; margin-bottom:16px;">
                <input type="password" name="new_password" id="newPassInput" placeholder="Yangi parolni kiriting..."
                    style="width:100%; padding:13px 46px 13px 16px; border:1px solid #E2E8F0; border-radius:12px; font-size:14px; font-weight:500; color:#0F172A; background:#F8FAFC; box-sizing:border-box; transition:0.2s;"
                    onfocus="this.style.borderColor='#2563EB'; this.style.background='#fff'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'"
                    onblur="this.style.borderColor='#E2E8F0'; this.style.background='#F8FAFC'; this.style.boxShadow='none'">
                <button type="button" onclick="togglePass('newPassInput', 'eyeIcon1')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#94A3B8; font-size:16px;">
                    <i id="eyeIcon1" class="fas fa-eye"></i>
                </button>
            </div>

            <label style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Parolni Tasdiqlang</label>
            <div style="position:relative; margin-bottom:24px;">
                <input type="password" id="confirmPassInput" placeholder="Parolni qayta kiriting..."
                    style="width:100%; padding:13px 46px 13px 16px; border:1px solid #E2E8F0; border-radius:12px; font-size:14px; font-weight:500; color:#0F172A; background:#F8FAFC; box-sizing:border-box; transition:0.2s;"
                    onfocus="this.style.borderColor='#2563EB'; this.style.background='#fff'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'"
                    onblur="this.style.borderColor='#E2E8F0'; this.style.background='#F8FAFC'; this.style.boxShadow='none'">
                <button type="button" onclick="togglePass('confirmPassInput', 'eyeIcon2')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#94A3B8; font-size:16px;">
                    <i id="eyeIcon2" class="fas fa-eye"></i>
                </button>
            </div>

            <div id="passError" style="display:none; background:#FEF2F2; border:1px solid #FECACA; color:#B91C1C; border-radius:10px; padding:10px 14px; font-size:13px; font-weight:600; margin-bottom:16px;"></div>

            <button type="submit" name="change_password"
                style="width:100%; background:linear-gradient(135deg,#2563EB,#1D4ED8); color:white; border:none; border-radius:12px; padding:14px; font-weight:700; font-size:15px; cursor:pointer; box-shadow:0 4px 12px rgba(37,99,235,0.3); transition:0.2s;"
                onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <i class="fas fa-save mr-2"></i> Parolni Saqlash
            </button>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPassModal(userId, userName) {
    document.getElementById('passUserId').value = userId;
    document.getElementById('passModalName').textContent = userName + ' uchun yangi parol';
    document.getElementById('newPassInput').value = '';
    document.getElementById('confirmPassInput').value = '';
    document.getElementById('passError').style.display = 'none';
    const modal = document.getElementById('passModal');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('newPassInput').focus(), 100);
}
function closePassModal() {
    document.getElementById('passModal').style.display = 'none';
}
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
function validatePassForm() {
    const p1 = document.getElementById('newPassInput').value;
    const p2 = document.getElementById('confirmPassInput').value;
    const errDiv = document.getElementById('passError');
    if (p1.length < 4) {
        errDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Parol kamida 4 ta belgidan iborat bo\'lishi kerak!';
        errDiv.style.display = 'block';
        return false;
    }
    if (p1 !== p2) {
        errDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Parollar bir-biriga mos kelmaydi!';
        errDiv.style.display = 'block';
        return false;
    }
    errDiv.style.display = 'none';
    return true;
}
// Modal tashqarisiga bosish — yopiladi
document.getElementById('passModal').addEventListener('click', function(e) {
    if (e.target === this) closePassModal();
});
</script>
</body>
</html>