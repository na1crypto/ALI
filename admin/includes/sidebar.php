<?php
$site_name = 'SMART POS'; 
if (isset($conn)) {
    $st_query = mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1");
    if ($st_query && mysqli_num_rows($st_query) > 0) {
        $st_data = mysqli_fetch_assoc($st_query);
        $site_name = $st_data['store_name'];
    }
}

$active    = $active_menu ?? 'dashboard';
$user_role = $_SESSION['role'] ?? 'manager'; 

$script_path = $_SERVER['SCRIPT_NAME'];
if (strpos($script_path, '/admin/') !== false) {
    $base_arr     = explode('/admin/', $script_path);
    $project_base = $base_arr[0]; 
} else {
    $project_base = ""; 
}
$admin_base = $project_base . "/admin";
?>

<style>
/* ================================================
   SIDEBAR
================================================ */
.app-sidebar {
    position: fixed;
    top: 0; left: 0;
    width: 90px;
    height: 100vh;
    background: #FFFFFF;
    border-right: 1px solid #E2E8F0;
    padding: 28px 14px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    white-space: nowrap;
    box-shadow: 2px 0 12px rgba(17,28,68,0.04);
    transition: width 0.4s cubic-bezier(0.4,0,0.2,1),
                padding 0.4s cubic-bezier(0.4,0,0.2,1),
                box-shadow 0.4s ease;
}
.app-sidebar:hover {
    width: 264px;
    padding: 28px 20px;
    box-shadow: 10px 0 36px rgba(17,28,68,0.08);
}

/* BRAND */
.app-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none !important;
    margin-bottom: 36px;
    padding: 4px;
    flex-shrink: 0;
    overflow: hidden;
}
.brand-icon {
    width: 44px; height: 44px;
    background: #4318FF;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    flex-shrink: 0;
    transition: border-radius 0.3s;
}
.app-sidebar:hover .brand-icon { border-radius: 12px; }

.brand-text {
    font-size: 18px; font-weight: 800;
    color: #2B3674;
    font-family: 'Segoe UI', sans-serif;
    letter-spacing: -0.3px;
    opacity: 0;
    transform: translateX(-10px);
    transition: opacity 0.3s ease 0.1s, transform 0.3s ease 0.1s;
    white-space: nowrap;
}
.app-sidebar:hover .brand-text {
    opacity: 1;
    transform: translateX(0);
}

/* NAV */
.app-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    overflow: hidden;
}

/* ITEM */
.app-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 13px 10px;
    border-radius: 14px;
    color: #A3AED0;
    text-decoration: none !important;
    font-weight: 600;
    font-size: 14px;
    font-family: 'Segoe UI', sans-serif;
    transition: background 0.2s, color 0.2s;
    cursor: pointer;
    flex-shrink: 0;
    overflow: hidden;
    white-space: nowrap;
    border: 1px solid transparent;
}
.item-icon {
    width: 44px; height: 44px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    border-radius: 12px;
    transition: background 0.2s;
}
.item-label {
    opacity: 0;
    transform: translateX(-10px);
    transition: opacity 0.3s ease 0.1s, transform 0.3s ease 0.1s;
    white-space: nowrap;
}
.app-sidebar:hover .item-label {
    opacity: 1;
    transform: translateX(0);
}

.app-item:hover {
    background: #F4F7FE;
    color: #4318FF;
}
.app-item:hover svg { stroke: #4318FF; }

.app-item.active {
    background: #4318FF;
    color: #FFFFFF;
    box-shadow: 0 8px 20px -4px rgba(67,24,255,0.4);
}
.app-item.active svg { stroke: #FFFFFF; }
.app-item.active .item-icon { background: rgba(255,255,255,0.15); }

/* DIVIDER */
.sb-divider {
    border: none;
    border-top: 1px solid #E2E8F0;
    margin: 6px 0;
    flex-shrink: 0;
}

/* LOGOUT */
.app-logout {
    margin-top: auto;
    color: #E02424 !important;
}
.app-logout:hover {
    background: #FDF2F2 !important;
    color: #C81E1E !important;
    border-color: #FEE2E2 !important;
}
.app-logout svg { stroke: #E02424; }
.app-logout:hover svg { stroke: #C81E1E; }

/* ROL BADGE */
.role-badge {
    font-size: 9px;
    padding: 2px 7px;
    border-radius: 6px;
    font-weight: 700;
    text-transform: uppercase;
    margin-left: auto;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity 0.2s ease 0.15s;
}
.app-sidebar:hover .role-badge { opacity: 1; }
.badge-superadmin { background: #E8F9F1; color: #05CD99; }
.badge-admin      { background: #E9EDF7; color: #4318FF; }
.badge-manager    { background: #FFF9E6; color: #FFA800; }

/* ================================================
   DEFAULT BODY OFFSET
================================================ */
body {
    padding-left: 90px;
    transition: padding-left 0.4s cubic-bezier(0.4,0,0.2,1);
}
.bento-main, .main-content, .page-wrapper, .page-content {
    margin-left: 0 !important;
    transition: margin-left 0.4s cubic-bezier(0.4,0,0.2,1);
}

/* ================================================
   LOGOUT MODAL
================================================ */
.logout-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(17,28,68,0.45);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}
.logout-overlay.show { display: flex; }

.logout-modal {
    background: #fff;
    border-radius: 22px;
    padding: 42px 38px;
    text-align: center;
    max-width: 320px;
    width: 90%;
    box-shadow: 0 20px 50px rgba(17,28,68,0.18);
    animation: mPop 0.28s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes mPop {
    from { transform: scale(0.85); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
}
.lm-icon {
    width: 66px; height: 66px;
    border-radius: 50%;
    background: #FEECEF;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px;
    color: #E02424;
}
.lm-title {
    font-size: 18px; font-weight: 700;
    color: #111C44; margin-bottom: 8px;
    font-family: 'Segoe UI', sans-serif;
}
.lm-text {
    font-size: 13px; color: #A3AED0;
    margin-bottom: 26px;
    font-family: 'Segoe UI', sans-serif;
}
.lm-btns { display: flex; gap: 10px; }
.lm-cancel {
    flex: 1; padding: 12px 0; border-radius: 12px;
    border: 1px solid #E2E8F0; background: #F4F7FE;
    color: #2B3674; font-weight: 600; cursor: pointer;
    font-size: 14px; font-family: 'Segoe UI', sans-serif;
    transition: background 0.2s;
}
.lm-cancel:hover { background: #E2E8F0; }
.lm-confirm {
    flex: 1; padding: 12px 0; border-radius: 12px;
    border: none; background: #E02424;
    color: #fff; font-weight: 600; cursor: pointer;
    font-size: 14px; font-family: 'Segoe UI', sans-serif;
    transition: background 0.2s;
}
.lm-confirm:hover { background: #C81E1E; }
</style>

<!-- SIDEBAR -->
<div class="app-sidebar" id="appSidebar">

    <!-- BRAND -->
    <a href="<?= $admin_base ?>/dashboard.php" class="app-brand">
        <div class="brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
            </svg>
        </div>
        <span class="brand-text"><?= htmlspecialchars($site_name) ?></span>
    </a>

    <div class="app-nav">

        <!-- Boshqaruv -->
        <a href="<?= $admin_base ?>/dashboard.php"
           class="app-item <?= $active=='dashboard'?'active':'' ?>">
            <div class="item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            </div>
            <span class="item-label">Boshqaruv</span>
        </a>

<!-- AI Tahlilchi -->
<a href="<?= $admin_base ?>/AI/index.php"
   class="app-item <?= $active=='ai'?'active':'' ?>">
    <div class="item-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
    </div>
    <span class="item-label">AI Tahlilchi</span>
</a>
        <!-- Ombor -->
        <a href="<?= $admin_base ?>/Mahsulotlar/index.php"
           class="app-item <?= $active=='ombor'?'active':'' ?>">
            <div class="item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
            </div>
            <span class="item-label">Ombor</span>
        </a>

        <!-- Savdo Tarixi -->
        <a href="<?= $admin_base ?>/sales_history.php"
           class="app-item <?= $active=='savdo'?'active':'' ?>">
            <div class="item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
            <span class="item-label">Savdo Tarixi</span>
        </a>

        <?php if($user_role === 'superadmin'): ?>
            <hr class="sb-divider">

            <!-- Xodimlar -->
            <a href="<?= $admin_base ?>/Xodimlar/index.php"
               class="app-item <?= $active=='xodimlar'?'active':'' ?>">
                <div class="item-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <span class="item-label">Xodimlar</span>
            </a>

            <!-- Sozlamalar -->
            <a href="<?= $admin_base ?>/settings.php"
               class="app-item <?= $active=='sozlamalar'?'active':'' ?>">
                <div class="item-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                </div>
                <span class="item-label">Sozlamalar</span>
            </a>
        <?php endif; ?>

        <!-- LOGOUT -->
        <div class="app-item app-logout"
             onclick="document.getElementById('logoutOverlay').classList.add('show')">
            <div class="item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </div>
            <span class="item-label">Chiqish</span>
            <span class="role-badge badge-<?= $user_role ?>"><?= $user_role ?></span>
        </div>

    </div>
</div>

<script>
(function(){
    var sb = document.getElementById('appSidebar');
    if(!sb) return;
    var t = 'transition:margin-left 0.4s cubic-bezier(0.4,0,0.2,1),padding-left 0.4s cubic-bezier(0.4,0,0.2,1)';
    function pushContent(open){
        document.body.style.paddingLeft = open ? '270px' : '90px';
    }
    sb.addEventListener('mouseenter', function(){ pushContent(true); });
    sb.addEventListener('mouseleave', function(){ pushContent(false); });
})();
</script>

<!-- LOGOUT MODAL -->
<div class="logout-overlay" id="logoutOverlay"
     onclick="if(event.target===this)this.classList.remove('show')">
    <div class="logout-modal">
        <div class="lm-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none"
                 stroke="#E02424" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </div>
        <div class="lm-title">Chiqishni tasdiqlang</div>
        <div class="lm-text">Haqiqatan ham tizimdan chiqmoqchimisiz?</div>
        <div class="lm-btns">
            <button class="lm-cancel"
                    onclick="document.getElementById('logoutOverlay').classList.remove('show')">
                Bekor qilish
            </button>
            <button class="lm-confirm"
                    onclick="window.location.href='<?= $project_base ?>/auth/logout.php'">
                Ha, chiqish
            </button>
        </div>
    </div>
</div>