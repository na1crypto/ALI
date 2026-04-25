<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/menejer_check.php";
require_once "../config/dokon_db.php";

// Migration
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'tolangan'");
@mysqli_query($conn, "ALTER TABLE sales ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");

$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? "SMART POS";
$user_name = $_SESSION['name'] ?? 'Menejer';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menejer Panel | <?= htmlspecialchars($site_name) ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif;background:#F4F7FE;color:#1B2559;min-height:100vh}

/* ─── NAVBAR ─── */
.navbar{
    height:64px;background:#fff;display:flex;align-items:center;
    justify-content:space-between;padding:0 28px;
    border-bottom:1px solid #E9EDF7;
    position:sticky;top:0;z-index:100;
    box-shadow:0 2px 10px rgba(17,28,68,.04);
}
.nav-brand{display:flex;align-items:center;gap:12px}
.nav-logo{
    width:40px;height:40px;background:#4318FF;border-radius:12px;
    display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;
    flex-shrink:0;
}
.nav-title{font-size:18px;font-weight:800;color:#111C44;letter-spacing:-.3px}
.nav-right{display:flex;align-items:center;gap:16px}
.nav-badge{
    background:#FEECEF;color:#EE5D50;padding:4px 12px;border-radius:20px;
    font-size:12px;font-weight:700;cursor:default;
}
.nav-badge.green{background:#E8F9F1;color:#05CD99}
.user-pill{
    background:#F4F7FE;padding:6px 14px 6px 6px;border-radius:30px;
    display:flex;align-items:center;gap:10px;font-weight:700;color:#111C44;font-size:13px;
}
.user-av{
    width:32px;height:32px;border-radius:50%;background:#4318FF;color:#fff;
    display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;
}
.logout-btn{
    background:none;border:1px solid #E9EDF7;border-radius:10px;
    padding:7px 14px;color:#A3AED0;font-size:13px;font-weight:600;
    cursor:pointer;transition:.2s;
}
.logout-btn:hover{background:#FEECEF;color:#EE5D50;border-color:#FEE2E2}

/* ─── LAYOUT ─── */
.page{display:flex;gap:0;height:calc(100vh - 64px)}

/* ─── LEFT: ORDERS ─── */
.orders-panel{
    flex:1;min-width:0;display:flex;flex-direction:column;
    border-right:1px solid #E9EDF7;
}
.panel-header{
    padding:16px 24px;background:#fff;border-bottom:1px solid #E9EDF7;
    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.panel-title{font-size:15px;font-weight:700;color:#111C44}
.panel-sub{font-size:12px;color:#A3AED0;margin-top:2px}

/* Filter tabs */
.filter-tabs{display:flex;gap:6px}
.ftab{
    padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;
    border:1.5px solid #E9EDF7;background:#fff;color:#A3AED0;
    cursor:pointer;transition:.15s;
}
.ftab:hover{border-color:#4318FF;color:#4318FF}
.ftab.active{background:#4318FF;color:#fff;border-color:#4318FF}
.ftab .cnt{
    display:inline-block;background:rgba(255,255,255,.25);
    border-radius:10px;padding:0 6px;margin-left:4px;font-size:11px;
}
.ftab:not(.active) .cnt{background:#F4F7FE;color:#4318FF}

/* Orders list */
.orders-list{flex:1;overflow-y:auto;padding:16px 24px;display:flex;flex-direction:column;gap:12px}

/* Order card */
.order-card{
    background:#fff;border-radius:16px;padding:18px;
    border:1.5px solid #E9EDF7;transition:.2s;
    animation:slideIn .3s ease;
}
.order-card:hover{box-shadow:0 6px 20px rgba(17,28,68,.07)}
.order-card.yangi{border-left:4px solid #4318FF}
.order-card.qabul_qilindi{border-left:4px solid #05CD99}
.order-card.rad_etildi{border-left:4px solid #EE5D50;opacity:.7}

@keyframes slideIn{
    from{opacity:0;transform:translateY(-8px)}
    to{opacity:1;transform:translateY(0)}
}

.oc-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.oc-id{font-size:13px;font-weight:800;color:#4318FF}
.oc-time{font-size:11px;color:#A3AED0;margin-top:2px}
.oc-status{
    padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;
}
.s-yangi{background:#EEF2FF;color:#4318FF}
.s-qabul_qilindi{background:#E8F9F1;color:#05CD99}
.s-rad_etildi{background:#FEECEF;color:#EE5D50}

.oc-client{
    display:flex;align-items:center;gap:8px;margin-bottom:10px;
}
.cl-av{
    width:28px;height:28px;border-radius:50%;background:#F0EEFF;color:#4318FF;
    display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;
    flex-shrink:0;
}
.cl-name{font-size:13px;font-weight:700;color:#111C44}
.cl-type{
    font-size:11px;padding:2px 8px;border-radius:6px;margin-left:6px;font-weight:600;
}
.t-delivery{background:#FFF4E5;color:#FFB547}
.t-pickup{background:#E8F9F1;color:#05CD99}

/* Items list */
.oc-items{background:#F8FAFF;border-radius:10px;padding:10px 12px;margin-bottom:12px}
.oc-item{
    display:flex;justify-content:space-between;align-items:center;
    padding:4px 0;font-size:12px;border-bottom:1px solid #EEF0F8;
}
.oc-item:last-child{border:none}
.oi-name{color:#1B2559;font-weight:600}
.oi-qty{color:#A3AED0;font-size:11px}
.oi-price{font-weight:700;color:#111C44}

/* Note */
.oc-note{
    font-size:12px;color:#64748b;background:#FFF9E6;
    padding:6px 10px;border-radius:8px;margin-bottom:12px;
    border-left:3px solid #FFB547;
}

/* Total */
.oc-total{
    display:flex;justify-content:space-between;align-items:center;
    font-size:14px;font-weight:800;color:#111C44;
    padding-top:10px;border-top:1px solid #E9EDF7;margin-bottom:12px;
}
.oc-total-val{color:#4318FF;font-size:16px}

/* Action buttons */
.oc-actions{display:flex;gap:8px}
.btn-accept{
    flex:1;padding:10px;border-radius:10px;border:none;
    background:#4318FF;color:#fff;font-size:13px;font-weight:700;
    cursor:pointer;transition:.15s;
}
.btn-accept:hover{background:#3311cc;transform:translateY(-1px)}
.btn-reject{
    padding:10px 16px;border-radius:10px;border:1.5px solid #EE5D50;
    background:#fff;color:#EE5D50;font-size:13px;font-weight:700;
    cursor:pointer;transition:.15s;
}
.btn-reject:hover{background:#FEECEF}
.btn-done{
    flex:1;padding:10px;border-radius:10px;border:none;
    background:#E8F9F1;color:#05CD99;font-size:13px;font-weight:700;
    cursor:default;
}

/* Empty state */
.empty-state{
    text-align:center;padding:60px 20px;color:#A3AED0;
}
.empty-ico{font-size:48px;margin-bottom:12px}
.empty-txt{font-size:14px;font-weight:600}

/* Refresh indicator */
.refresh-bar{
    padding:8px 24px;background:#F0EEFF;
    font-size:12px;color:#4318FF;font-weight:600;
    display:flex;align-items:center;gap:6px;flex-shrink:0;
}
.spin{animation:spin 1.2s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}

/* ─── RIGHT: AI STATS ─── */
.stats-panel{
    width:360px;flex-shrink:0;display:flex;flex-direction:column;
    background:#fff;overflow-y:auto;
}
.sp-header{
    padding:16px 20px;border-bottom:1px solid #E9EDF7;flex-shrink:0;
    background:#fff;position:sticky;top:0;z-index:10;
}
.sp-title{font-size:15px;font-weight:700;color:#111C44;display:flex;align-items:center;gap:8px}
.ai-dot{
    width:8px;height:8px;border-radius:50%;background:#05CD99;
    box-shadow:0 0 6px #05CD99;animation:pulse 2s infinite;
}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

.sp-body{padding:16px;display:flex;flex-direction:column;gap:12px}

/* Mini stat cards */
.mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.msc{
    background:#F8FAFF;border-radius:12px;padding:12px;
    text-align:center;
}
.msc-val{font-size:20px;font-weight:800;color:#111C44;line-height:1}
.msc-lbl{font-size:11px;color:#A3AED0;margin-top:4px;font-weight:600}
.msc.blue .msc-val{color:#4318FF}
.msc.green .msc-val{color:#05CD99}
.msc.orange .msc-val{color:#FFB547}
.msc.red .msc-val{color:#EE5D50}

/* Section titles */
.sec-title{
    font-size:12px;font-weight:700;color:#A3AED0;
    text-transform:uppercase;letter-spacing:.6px;
    margin-bottom:8px;
}

/* Top products */
.top-prod{
    display:flex;align-items:center;gap:10px;
    padding:8px 10px;border-radius:10px;background:#F8FAFF;margin-bottom:6px;
}
.tp-rank{
    width:24px;height:24px;border-radius:8px;background:#4318FF;color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-size:11px;font-weight:800;flex-shrink:0;
}
.tp-rank.r2{background:#7C3AED}
.tp-rank.r3{background:#A78BFA}
.tp-rank.r4,.tp-rank.r5{background:#E9EDF7;color:#A3AED0}
.tp-name{flex:1;font-size:12px;font-weight:700;color:#1B2559;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tp-qty{font-size:11px;color:#A3AED0;font-weight:600;flex-shrink:0}

/* Bar chart */
.bar-chart{padding:4px 0}
.bc-row{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.bc-label{font-size:11px;color:#A3AED0;font-weight:600;width:36px;text-align:right;flex-shrink:0}
.bc-track{flex:1;height:8px;background:#F4F7FE;border-radius:10px;overflow:hidden}
.bc-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#4318FF,#6AD2FF);transition:width .6s ease}
.bc-val{font-size:11px;font-weight:700;color:#111C44;flex-shrink:0;width:32px}

/* Delivery pie */
.pie-row{display:flex;align-items:center;gap:12px;padding:8px 10px;background:#F8FAFF;border-radius:10px;margin-bottom:6px}
.pie-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.pie-lbl{flex:1;font-size:12px;font-weight:700;color:#1B2559}
.pie-cnt{font-size:12px;font-weight:800;color:#111C44}

/* AI insight card */
.ai-insight{
    background:linear-gradient(135deg,#111C44 0%,#4318FF 100%);
    border-radius:14px;padding:16px;color:#fff;
    font-size:12px;line-height:1.6;
}
.ai-insight-title{font-size:13px;font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.ai-insight p{color:rgba(255,255,255,.8);margin:0}

/* Loading skeleton */
.skeleton{
    background:linear-gradient(90deg,#F4F7FE 25%,#E9EDF7 50%,#F4F7FE 75%);
    background-size:200% 100%;animation:shimmer 1.5s infinite;
    border-radius:8px;
}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="nav-brand">
        <div class="nav-logo">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        </div>
        <div>
            <div class="nav-title">Menejer Panel</div>
        </div>
    </div>
    <div class="nav-right">
        <span class="nav-badge" id="navYangi">⏳ Yangi: 0</span>
        <span class="nav-badge green" id="navBugun">📦 Bugun: 0</span>
        <div class="user-pill">
            <div class="user-av"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>
        </div>
        <button class="logout-btn" onclick="window.location.href='/auth/logout.php'">Chiqish</button>
    </div>
</div>

<!-- MAIN PAGE -->
<div class="page">

    <!-- ─── LEFT: ORDERS ─── -->
    <div class="orders-panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">Kiruvchi Buyurtmalar</div>
                <div class="panel-sub" id="lastUpdate">Yuklanmoqda...</div>
            </div>
            <div class="filter-tabs">
                <button class="ftab active" data-f="yangi" onclick="setFilter('yangi',this)">
                    Yangi <span class="cnt" id="cnt-yangi">0</span>
                </button>
                <button class="ftab" data-f="qabul" onclick="setFilter('qabul',this)">
                    Qabul <span class="cnt" id="cnt-qabul">0</span>
                </button>
                <button class="ftab" data-f="rad" onclick="setFilter('rad',this)">
                    Rad <span class="cnt" id="cnt-rad">0</span>
                </button>
                <button class="ftab" data-f="barchasi" onclick="setFilter('barchasi',this)">
                    Barchasi
                </button>
            </div>
        </div>

        <div class="refresh-bar" id="refreshBar" style="display:none">
            <span class="spin">↻</span> Yangilanmoqda...
        </div>

        <div class="orders-list" id="ordersList">
            <div class="empty-state">
                <div class="skeleton" style="width:60px;height:60px;border-radius:50%;margin:0 auto 12px"></div>
                <div class="skeleton" style="width:120px;height:14px;margin:0 auto"></div>
            </div>
        </div>
    </div>

    <!-- ─── RIGHT: AI STATS ─── -->
    <div class="stats-panel">
        <div class="sp-header">
            <div class="sp-title">
                <div class="ai-dot"></div>
                AI Tahlil & Statistika
            </div>
        </div>
        <div class="sp-body" id="statsBody">
            <!-- JS to'ldiradi -->
            <div class="skeleton" style="height:80px;border-radius:12px"></div>
            <div class="skeleton" style="height:120px;border-radius:12px"></div>
            <div class="skeleton" style="height:160px;border-radius:12px"></div>
        </div>
    </div>

</div>

<!-- REJECT MODAL -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(17,28,68,.45);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:32px;width:340px;box-shadow:0 20px 50px rgba(17,28,68,.18)">
        <div style="font-size:16px;font-weight:700;color:#111C44;margin-bottom:6px">Buyurtmani rad etish</div>
        <div style="font-size:13px;color:#A3AED0;margin-bottom:16px">Sabab ko'rsating (ixtiyoriy)</div>
        <input id="rejectSabab" type="text" placeholder="Rad etish sababi..."
            style="width:100%;padding:12px 14px;border-radius:12px;border:1.5px solid #E9EDF7;font-size:13px;outline:none;margin-bottom:16px">
        <div style="display:flex;gap:8px">
            <button onclick="closeReject()" style="flex:1;padding:11px;border-radius:11px;border:1px solid #E9EDF7;background:#F4F7FE;font-weight:600;font-size:13px;cursor:pointer">Bekor</button>
            <button onclick="confirmReject()" style="flex:1;padding:11px;border-radius:11px;border:none;background:#EE5D50;color:#fff;font-weight:700;font-size:13px;cursor:pointer">Rad etish</button>
        </div>
    </div>
</div>

<script>
var currentFilter = 'yangi';
var rejectId      = 0;
var counts        = {yangi:0, qabul:0, rad:0};

// ─── FORMAT ───
function fmt(n){ return Number(n).toLocaleString('uz-UZ') + ' UZS'; }
function fmtDate(s){
    var d = new Date(s);
    return d.toLocaleDateString('uz-UZ',{day:'2-digit',month:'2-digit'}) + ' ' +
           d.toLocaleTimeString('uz-UZ',{hour:'2-digit',minute:'2-digit'});
}
function esc(s){ var d=document.createElement('div');d.textContent=s;return d.innerHTML; }

// ─── FILTER ───
function setFilter(f, btn){
    currentFilter = f;
    document.querySelectorAll('.ftab').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    loadOrders();
}

// ─── LOAD ORDERS ───
function loadOrders(silent){
    if(!silent){
        document.getElementById('refreshBar').style.display = 'flex';
    }
    fetch('/menejer/api.php?action=orders&filter=' + currentFilter)
        .then(r=>r.json())
        .then(data=>{
            document.getElementById('refreshBar').style.display = 'none';
            if(data.status !== 'ok') return;
            renderOrders(data.orders);
            document.getElementById('lastUpdate').textContent =
                'Oxirgi yangilanish: ' + new Date().toLocaleTimeString('uz-UZ');
        })
        .catch(()=>{ document.getElementById('refreshBar').style.display='none'; });
}

function renderOrders(orders){
    var list = document.getElementById('ordersList');

    // Count totals per status from current response if filter=barchasi
    if(currentFilter === 'barchasi'){
        counts = {yangi:0,qabul:0,rad:0};
        orders.forEach(o=>{
            if(o.status==='yangi') counts.yangi++;
            else if(o.status==='qabul_qilindi') counts.qabul++;
            else if(o.status==='rad_etildi') counts.rad++;
        });
        updateCounts();
    } else {
        counts[currentFilter === 'qabul' ? 'qabul' : currentFilter === 'rad' ? 'rad' : 'yangi'] = orders.length;
        updateCounts();
    }

    // Navbar update
    document.getElementById('navYangi').textContent = '⏳ Yangi: ' + counts.yangi;
    document.getElementById('navBugun').textContent = '📦 Bugun: ' + (counts.yangi + counts.qabul);

    if(!orders.length){
        list.innerHTML = '<div class="empty-state"><div class="empty-ico">📭</div><div class="empty-txt">Buyurtmalar yo\'q</div></div>';
        return;
    }

    var html = '';
    orders.forEach(function(o){
        var statusLbl = o.status === 'yangi' ? 'Yangi'
            : o.status === 'qabul_qilindi' ? 'Qabul qilindi'
            : o.status === 'rad_etildi' ? 'Rad etildi' : o.status;

        var typeCls  = o.sale_type === 'yetkazish' ? 't-delivery' : 't-pickup';
        var typeTxt  = o.sale_type === 'yetkazish' ? '🚚 Yetkazish' : '🏪 Olib ketish';

        var itemsHtml = '';
        if(o.items && o.items.length){
            o.items.forEach(function(it){
                itemsHtml += '<div class="oc-item">' +
                    '<span class="oi-name">' + esc(it.prod_name || '—') + '</span>' +
                    '<span class="oi-qty">' + it.quantity + ' ' + esc(it.unit) + '</span>' +
                    '<span class="oi-price">' + fmt(it.total) + '</span>' +
                '</div>';
            });
        }

        var noteHtml = '';
        if(o.note){
            noteHtml = '<div class="oc-note">📝 ' + esc(o.note) + '</div>';
        }

        var actionHtml = '';
        if(o.status === 'yangi'){
            actionHtml = '<div class="oc-actions">' +
                '<button class="btn-accept" onclick="acceptOrder(' + o.id + ')">✓ Qabul qilish</button>' +
                '<button class="btn-reject" onclick="openReject(' + o.id + ')">✕ Rad etish</button>' +
            '</div>';
        } else if(o.status === 'qabul_qilindi'){
            actionHtml = '<div class="oc-actions"><div class="btn-done">✓ Qabul qilindi</div></div>';
        }

        html += '<div class="order-card ' + esc(o.status) + '" id="order-' + o.id + '">' +
            '<div class="oc-top">' +
                '<div><div class="oc-id">#' + String(o.id).padStart(6,'0') + '</div><div class="oc-time">' + fmtDate(o.sale_date) + '</div></div>' +
                '<span class="oc-status s-' + esc(o.status) + '">' + statusLbl + '</span>' +
            '</div>' +
            '<div class="oc-client">' +
                '<div class="cl-av">' + esc((o.mijoz_nomi || 'M').charAt(0).toUpperCase()) + '</div>' +
                '<span class="cl-name">' + esc(o.mijoz_nomi) + '</span>' +
                '<span class="cl-type ' + typeCls + '">' + typeTxt + '</span>' +
            '</div>' +
            (itemsHtml ? '<div class="oc-items">' + itemsHtml + '</div>' : '') +
            noteHtml +
            '<div class="oc-total"><span>Jami summa:</span><span class="oc-total-val">' + fmt(o.total_price) + '</span></div>' +
            actionHtml +
        '</div>';
    });
    list.innerHTML = html;
}

function updateCounts(){
    document.getElementById('cnt-yangi').textContent = counts.yangi;
    document.getElementById('cnt-qabul').textContent = counts.qabul;
    document.getElementById('cnt-rad').textContent   = counts.rad;
}

// ─── ACCEPT ───
function acceptOrder(id){
    var card = document.getElementById('order-' + id);
    if(card) card.style.opacity = '.5';
    var fd = new FormData();
    fd.append('id', id);
    fetch('/menejer/api.php?action=accept', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(()=>{ loadOrders(); loadStats(); });
}

// ─── REJECT ───
function openReject(id){
    rejectId = id;
    document.getElementById('rejectSabab').value = '';
    var m = document.getElementById('rejectModal');
    m.style.display = 'flex';
}
function closeReject(){
    document.getElementById('rejectModal').style.display = 'none';
    rejectId = 0;
}
function confirmReject(){
    if(!rejectId) return;
    var sabab = document.getElementById('rejectSabab').value;
    var fd = new FormData();
    fd.append('id', rejectId);
    fd.append('sabab', sabab);
    closeReject();
    fetch('/menejer/api.php?action=reject', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(()=>{ loadOrders(); loadStats(); });
}

// ─── STATS ───
function loadStats(){
    fetch('/menejer/api.php?action=stats')
        .then(r=>r.json())
        .then(renderStats);
}

function renderStats(d){
    if(d.status !== 'ok') return;
    var html = '';

    // Mini cards
    html += '<div class="mini-stats">' +
        '<div class="msc blue"><div class="msc-val">' + d.yangi_soni + '</div><div class="msc-lbl">Yangi buyurtma</div></div>' +
        '<div class="msc green"><div class="msc-val">' + d.bugun_soni + '</div><div class="msc-lbl">Bugungi</div></div>' +
        '<div class="msc orange"><div class="msc-val">' + fmtShort(d.bugun_jami) + '</div><div class="msc-lbl">Bugungi daromad</div></div>' +
        '<div class="msc"><div class="msc-val">' + fmtShort(d.ortacha_chek) + '</div><div class="msc-lbl">O\'rtacha chek</div></div>' +
    '</div>';

    // Haftalik grafik
    if(d.haftalik && d.haftalik.length){
        var maxJami = Math.max.apply(null, d.haftalik.map(h=>+h.jami)) || 1;
        html += '<div><div class="sec-title">Haftalik buyurtmalar</div><div class="bar-chart">';
        d.haftalik.forEach(function(h){
            var pct = Math.round((+h.jami / maxJami) * 100);
            var lbl = h.kun ? h.kun.slice(5) : ''; // MM-DD
            html += '<div class="bc-row">' +
                '<div class="bc-label">' + esc(lbl) + '</div>' +
                '<div class="bc-track"><div class="bc-fill" style="width:' + pct + '%"></div></div>' +
                '<div class="bc-val">' + h.soni + '</div>' +
            '</div>';
        });
        html += '</div></div>';
    }

    // Top mahsulotlar
    if(d.top_prods && d.top_prods.length){
        html += '<div><div class="sec-title">Top mahsulotlar</div>';
        var rankCls = ['','r2','r3','r4','r5'];
        d.top_prods.forEach(function(p, i){
            html += '<div class="top-prod">' +
                '<div class="tp-rank ' + (rankCls[i]||'r5') + '">' + (i+1) + '</div>' +
                '<div class="tp-name">' + esc(p.name) + '</div>' +
                '<div class="tp-qty">' + Math.round(p.jami_qty) + ' dona</div>' +
            '</div>';
        });
        html += '</div>';
    }

    // Yetkazish nisbati
    var yTotal = (d.yetkazish || 0) + (d.olib_ketish || 0);
    if(yTotal > 0){
        html += '<div><div class="sec-title">Yetkazish holati</div>' +
            '<div class="pie-row"><div class="pie-dot" style="background:#4318FF"></div>' +
                '<div class="pie-lbl">🚚 Yetkazish</div>' +
                '<div class="pie-cnt">' + d.yetkazish + ' ta</div></div>' +
            '<div class="pie-row"><div class="pie-dot" style="background:#05CD99"></div>' +
                '<div class="pie-lbl">🏪 Olib ketish</div>' +
                '<div class="pie-cnt">' + d.olib_ketish + ' ta</div></div>' +
        '</div>';
    }

    // Holat statistikasi
    html += '<div><div class="sec-title">Buyurtmalar holati</div>' +
        '<div class="pie-row"><div class="pie-dot" style="background:#4318FF"></div>' +
            '<div class="pie-lbl">⏳ Kutilayotgan</div>' +
            '<div class="pie-cnt">' + (d.kutilayotgan||0) + ' ta</div></div>' +
        '<div class="pie-row"><div class="pie-dot" style="background:#05CD99"></div>' +
            '<div class="pie-lbl">✓ Qabul qilindi</div>' +
            '<div class="pie-cnt">' + (d.qabul||0) + ' ta</div></div>' +
        '<div class="pie-row"><div class="pie-dot" style="background:#EE5D50"></div>' +
            '<div class="pie-lbl">✕ Rad etildi</div>' +
            '<div class="pie-cnt">' + (d.rad||0) + ' ta</div></div>' +
    '</div>';

    // AI tavsiya
    var insight = generateInsight(d);
    html += '<div class="ai-insight">' +
        '<div class="ai-insight-title">🤖 AI Tavsiya</div>' +
        '<p>' + insight + '</p>' +
    '</div>';

    document.getElementById('statsBody').innerHTML = html;
}

function fmtShort(n){
    n = +n;
    if(n >= 1000000) return (n/1000000).toFixed(1) + 'M';
    if(n >= 1000)    return (n/1000).toFixed(0) + 'K';
    return Math.round(n).toString();
}

function generateInsight(d){
    var msgs = [];
    if(d.yangi_soni > 0)
        msgs.push('Hozir <strong>' + d.yangi_soni + ' ta yangi buyurtma</strong> kutilmoqda — tezroq qabul qiling!');
    if(d.bugun_soni > 0 && d.ortacha_chek > 0)
        msgs.push('Bugungi o\'rtacha chek: <strong>' + fmtShort(d.ortacha_chek) + ' UZS</strong>.');
    if(d.top_prods && d.top_prods[0])
        msgs.push('Eng ko\'p buyurtma qilingan mahsulot: <strong>' + esc(d.top_prods[0].name) + '</strong>.');
    if(d.yetkazish > d.olib_ketish)
        msgs.push('Mijozlarning <strong>' + Math.round(d.yetkazish/(d.yetkazish+d.olib_ketish)*100) + '%</strong> i yetkazib berishni tanlaydi — yetkazish xizmatini kuchaytiring.');
    if(d.rad > 0 && d.qabul > 0){
        var radPct = Math.round(d.rad / (d.rad+d.qabul) * 100);
        if(radPct > 20)
            msgs.push('Rad etish darajasi yuqori (' + radPct + '%) — sabablarini tekshiring.');
    }
    if(!msgs.length) msgs.push('Hozircha mijoz buyurtmalari yo\'q. Saytni reklama qiling!');
    return msgs.join(' ');
}

// ─── AUTO REFRESH ───
loadOrders();
loadStats();

// Har 20 soniyada yangilanish
setInterval(function(){
    loadOrders(true);
}, 20000);

// Har 60 soniyada statistika yangilanish
setInterval(function(){
    loadStats();
}, 60000);
</script>
</body>
</html>
