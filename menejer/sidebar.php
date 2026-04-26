<?php
// Menejer shared sidebar
if (!isset($conn)) require_once "../config/dokon_db.php";
$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site_name_sb = $st['store_name'] ?? "SMART POS";
$active_page  = $active_page ?? 'dashboard';

// Yangi buyurtmalar soni (badge uchun)
$yangi_q = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM sales WHERE source='mijoz' AND status='yangi'"
));
$yangi_cnt = (int)($yangi_q['c'] ?? 0);
?>
<style>
.msb{
    position:fixed;top:0;left:0;width:88px;height:100vh;
    background:#fff;border-right:1px solid #E9EDF7;
    padding:22px 12px;z-index:9999;
    display:flex;flex-direction:column;
    box-shadow:2px 0 12px rgba(17,28,68,.04);
    transition:width .35s cubic-bezier(.4,0,.2,1),padding .35s cubic-bezier(.4,0,.2,1);
    overflow:hidden;white-space:nowrap;
}
.msb:hover{width:256px;padding:22px 18px;box-shadow:10px 0 36px rgba(17,28,68,.08)}
.msb-brand{display:flex;align-items:center;gap:12px;text-decoration:none;margin-bottom:30px;padding:4px}
.msb-logo{width:42px;height:42px;background:#4318FF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.msb-name{font-size:17px;font-weight:800;color:#2B3674;opacity:0;transform:translateX(-8px);transition:opacity .3s ease .1s,transform .3s ease .1s;white-space:nowrap}
.msb:hover .msb-name{opacity:1;transform:translateX(0)}
.msb-nav{display:flex;flex-direction:column;gap:3px;flex:1}
.msb-item{
    display:flex;align-items:center;gap:12px;
    padding:12px 9px;border-radius:12px;
    color:#A3AED0;text-decoration:none;
    font-size:13px;font-weight:600;
    transition:background .15s,color .15s;
    overflow:hidden;white-space:nowrap;
    position:relative;
}
.msb-ico{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;flex-shrink:0;transition:background .15s}
.msb-lbl{opacity:0;transform:translateX(-8px);transition:opacity .3s ease .1s,transform .3s ease .1s;white-space:nowrap}
.msb:hover .msb-lbl{opacity:1;transform:translateX(0)}
.msb-item:hover{background:#F4F7FE;color:#4318FF}
.msb-item:hover svg{stroke:#4318FF}
.msb-item.active{background:#4318FF;color:#fff;box-shadow:0 6px 18px rgba(67,24,255,.35)}
.msb-item.active svg{stroke:#fff}
.msb-item.active .msb-ico{background:rgba(255,255,255,.15)}
.msb-badge{
    position:absolute;right:8px;top:50%;transform:translateY(-50%);
    background:#EE5D50;color:#fff;font-size:10px;font-weight:800;
    border-radius:10px;padding:2px 6px;
    opacity:0;transition:opacity .2s ease .15s;
}
.msb:hover .msb-badge{opacity:1}
.msb-divider{border:none;border-top:1px solid #E9EDF7;margin:6px 0;flex-shrink:0}
.msb-logout{margin-top:auto;color:#E02424!important}
.msb-logout:hover{background:#FDF2F2!important;color:#C81E1E!important}
.msb-logout svg{stroke:#E02424}
.msb-logout:hover svg{stroke:#C81E1E}
body{padding-left:88px;transition:padding-left .35s cubic-bezier(.4,0,.2,1)}

/* 📱 Mobile */
@media(max-width:768px){
  .msb{ display:none !important; }
  body{ padding-left:0 !important; padding-bottom:64px !important; }
  .mob-mnav{ display:flex !important; }
}
.mob-mnav{
  display:none;
  position:fixed;bottom:0;left:0;right:0;height:60px;
  background:#fff;border-top:1.5px solid #E9EDF7;
  z-index:9999;justify-content:space-around;align-items:center;
  box-shadow:0 -4px 16px rgba(17,28,68,.08);
  padding-bottom:env(safe-area-inset-bottom,0);
}
.mob-mnav-item{
  display:flex;flex-direction:column;align-items:center;
  gap:3px;flex:1;padding:6px 4px;
  color:#A3AED0;text-decoration:none !important;
  font-size:9px;font-weight:700;letter-spacing:.3px;
  transition:color .2s;border:none;background:none;cursor:pointer;
  position:relative;
}
.mob-mnav-item svg{width:22px;height:22px;stroke:currentColor;}
.mob-mnav-item.active{color:#4318FF;}
.mob-mnav-item.logout{color:#E02424;}
.mob-mnav-badge{
  position:absolute;top:4px;right:calc(50% - 18px);
  background:#EE5D50;color:#fff;font-size:9px;font-weight:800;
  border-radius:8px;padding:1px 5px;
  min-width:16px;text-align:center;
}
</style>

<div class="msb" id="mSidebar">
    <a href="/menejer/index.php" class="msb-brand">
        <div class="msb-logo">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        </div>
        <span class="msb-name"><?= htmlspecialchars($site_name_sb) ?></span>
    </a>

    <div class="msb-nav">
        <!-- Dashboard -->
        <a href="/menejer/index.php" class="msb-item <?= $active_page==='dashboard'?'active':'' ?>">
            <div class="msb-ico">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            </div>
            <span class="msb-lbl">Dashboard</span>
        </a>

        <!-- Buyurtmalar -->
        <a href="/menejer/buyurtmalar.php" class="msb-item <?= $active_page==='buyurtmalar'?'active':'' ?>">
            <div class="msb-ico">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </div>
            <span class="msb-lbl">Buyurtmalar</span>
            <?php if($yangi_cnt > 0): ?>
                <span class="msb-badge"><?= $yangi_cnt ?></span>
            <?php endif; ?>
        </a>

        <!-- Kelgan tovarlar -->
        <a href="/menejer/kelgan.php" class="msb-item <?= $active_page==='kelgan'?'active':'' ?>">
            <div class="msb-ico">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
            </div>
            <span class="msb-lbl">Kelgan Tovarlar</span>
        </a>

        <!-- Mahsulotlar -->
        <a href="/menejer/mahsulotlar.php" class="msb-item <?= $active_page==='mahsulotlar'?'active':'' ?>">
            <div class="msb-ico">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
            </div>
            <span class="msb-lbl">Ombor</span>
        </a>

        <hr class="msb-divider">

        <!-- Logout -->
        <a href="/auth/logout.php" class="msb-item msb-logout"
           onclick="return confirm('Tizimdan chiqishni tasdiqlaysizmi?')">
            <div class="msb-ico">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </div>
            <span class="msb-lbl">Chiqish</span>
        </a>
    </div>
</div>

<script>
(function(){
    var sb = document.getElementById('mSidebar');
    if(!sb) return;
    sb.addEventListener('mouseenter', function(){ if(window.innerWidth>768) document.body.style.paddingLeft='262px'; });
    sb.addEventListener('mouseleave', function(){ if(window.innerWidth>768) document.body.style.paddingLeft='88px'; });
})();
</script>

<!-- 📱 Menejer Mobile Bottom Nav -->
<nav class="mob-mnav">
  <a href="/menejer/index.php" class="mob-mnav-item <?= $active_page==='dashboard'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
      <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
    </svg>
    Bosh
  </a>
  <a href="/menejer/buyurtmalar.php" class="mob-mnav-item <?= $active_page==='buyurtmalar'?'active':'' ?>">
    <?php if($yangi_cnt>0): ?><span class="mob-mnav-badge"><?= $yangi_cnt ?></span><?php endif; ?>
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
      <line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
    </svg>
    Buyurtma
  </a>
  <a href="/menejer/kelgan.php" class="mob-mnav-item <?= $active_page==='kelgan'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
    </svg>
    Kelgan
  </a>
  <a href="/menejer/mahsulotlar.php" class="mob-mnav-item <?= $active_page==='mahsulotlar'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
      <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
    </svg>
    Ombor
  </a>
  <a href="/auth/logout.php" class="mob-mnav-item logout"
     onclick="return confirm('Tizimdan chiqasizmi?')">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="#E02424">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
      <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
    Chiqish
  </a>
</nav>
