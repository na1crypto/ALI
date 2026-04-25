<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../middleware/menejer_check.php";
require_once "../config/dokon_db.php";
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'tolangan'");
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS source VARCHAR(20) DEFAULT 'pos'");
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS note TEXT DEFAULT NULL");
@mysqli_query($conn,"ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL");

$site_q = mysqli_fetch_assoc(mysqli_query($conn,"SELECT store_name FROM settings WHERE id=1"));
$site_name = $site_q['store_name'] ?? "SMART POS";
$active_page = 'buyurtmalar';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Buyurtmalar | Menejer</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',-apple-system,sans-serif;background:#F4F7FE;color:#1B2559;min-height:100vh}
.navbar{height:62px;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 28px;border-bottom:1px solid #E9EDF7;position:sticky;top:0;z-index:90;box-shadow:0 2px 8px rgba(17,28,68,.04)}
.nav-title{font-size:19px;font-weight:800;color:#111C44}
.user-pill{background:#F4F7FE;padding:5px 14px 5px 5px;border-radius:30px;display:flex;align-items:center;gap:9px;font-weight:700;color:#111C44;font-size:13px}
.u-av{width:30px;height:30px;border-radius:50%;background:#4318FF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.main{padding:24px 28px;display:flex;gap:16px;height:calc(100vh - 62px)}
/* LEFT */
.orders-wrap{flex:1;min-width:0;display:flex;flex-direction:column;background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(17,28,68,.04);overflow:hidden}
.ow-header{padding:16px 20px;border-bottom:1px solid #E9EDF7;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.ow-title{font-size:14px;font-weight:800;color:#111C44}
.ow-sub{font-size:11px;color:#A3AED0;margin-top:2px}
.filters{display:flex;gap:6px}
.ftab{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;border:1.5px solid #E9EDF7;background:#fff;color:#A3AED0;cursor:pointer;transition:.15s}
.ftab:hover,.ftab.active{background:#4318FF;color:#fff;border-color:#4318FF}
.cnt{display:inline-block;background:rgba(255,255,255,.25);border-radius:10px;padding:0 5px;margin-left:3px;font-size:11px}
.ftab:not(.active) .cnt{background:#F4F7FE;color:#4318FF}
.orders-list{flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:10px}
/* ORDER CARD */
.ocard{background:#fff;border-radius:14px;padding:16px;border:1.5px solid #E9EDF7;transition:.18s;animation:sl .3s ease}
@keyframes sl{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.ocard:hover{box-shadow:0 5px 18px rgba(17,28,68,.07)}
.ocard.yangi{border-left:4px solid #4318FF}
.ocard.qabul_qilindi{border-left:4px solid #05CD99}
.ocard.rad_etildi{border-left:4px solid #EE5D50;opacity:.65}
.oc-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
.oc-id{font-size:13px;font-weight:800;color:#4318FF}
.oc-time{font-size:11px;color:#A3AED0;margin-top:1px}
.ostatus{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.s-yangi{background:#EEF2FF;color:#4318FF}.s-qabul_qilindi{background:#E8F9F1;color:#05CD99}.s-rad_etildi{background:#FEECEF;color:#EE5D50}
.oc-client{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.cl-av{width:26px;height:26px;border-radius:50%;background:#F0EEFF;color:#4318FF;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.cl-name{font-size:13px;font-weight:700;color:#111C44}
.cl-type{font-size:11px;padding:2px 7px;border-radius:6px;margin-left:5px;font-weight:600}
.t-d{background:#FFF4E5;color:#FFB547}.t-p{background:#E8F9F1;color:#05CD99}
.oc-items{background:#F8FAFF;border-radius:9px;padding:9px 11px;margin-bottom:10px}
.oi{display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:12px;border-bottom:1px solid #EEF0F8}
.oi:last-child{border:none}
.oi-n{color:#1B2559;font-weight:600}.oi-q{color:#A3AED0;font-size:11px}.oi-p{font-weight:700;color:#111C44}
.oc-note{font-size:12px;color:#64748b;background:#FFF9E6;padding:6px 10px;border-radius:8px;margin-bottom:10px;border-left:3px solid #FFB547}
.oc-total{display:flex;justify-content:space-between;align-items:center;font-size:14px;font-weight:800;color:#111C44;padding-top:8px;border-top:1px solid #E9EDF7;margin-bottom:10px}
.oc-tot-val{color:#4318FF;font-size:15px}
.oc-actions{display:flex;gap:7px}
.btn-ok{flex:1;padding:9px;border-radius:9px;border:none;background:#4318FF;color:#fff;font-size:13px;font-weight:700;cursor:pointer;transition:.15s}
.btn-ok:hover{background:#3311cc}
.btn-no{padding:9px 14px;border-radius:9px;border:1.5px solid #EE5D50;background:#fff;color:#EE5D50;font-size:13px;font-weight:700;cursor:pointer;transition:.15s}
.btn-no:hover{background:#FEECEF}
.btn-done{flex:1;padding:9px;border-radius:9px;border:none;background:#E8F9F1;color:#05CD99;font-size:13px;font-weight:700;cursor:default}
.empty{text-align:center;padding:48px 20px;color:#A3AED0}
/* RIGHT: AI STATS */
.stats-side{width:320px;flex-shrink:0;background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(17,28,68,.04);display:flex;flex-direction:column;overflow:hidden}
.ss-header{padding:14px 18px;border-bottom:1px solid #E9EDF7;display:flex;align-items:center;gap:8px;flex-shrink:0}
.ss-dot{width:8px;height:8px;border-radius:50%;background:#05CD99;box-shadow:0 0 6px #05CD99;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.ss-title{font-size:14px;font-weight:800;color:#111C44}
.ss-body{padding:14px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:12px}
.mini2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.msc{background:#F8FAFF;border-radius:11px;padding:11px;text-align:center}
.msc-v{font-size:18px;font-weight:800;color:#111C44;line-height:1}
.msc-l{font-size:11px;color:#A3AED0;font-weight:600;margin-top:3px}
.msc.blue .msc-v{color:#4318FF}.msc.green .msc-v{color:#05CD99}.msc.orange .msc-v{color:#FFB547}
.sec-t{font-size:11px;font-weight:700;color:#A3AED0;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
.tp-row{display:flex;align-items:center;gap:9px;padding:7px 9px;border-radius:9px;background:#F8FAFF;margin-bottom:5px}
.tp-n{font-size:11px;font-weight:800;color:#fff;width:20px;height:20px;border-radius:6px;background:#4318FF;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.tp-n.n2{background:#7C3AED}.tp-n.n3{background:#A78BFA}.tp-n.n4,.tp-n.n5{background:#E9EDF7;color:#A3AED0}
.tp-name{flex:1;font-size:12px;font-weight:700;color:#1B2559;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tp-qty{font-size:11px;color:#A3AED0;font-weight:600;flex-shrink:0}
.ai-box{background:linear-gradient(135deg,#111C44,#4318FF);border-radius:13px;padding:14px;color:#fff}
.ai-t{font-size:13px;font-weight:700;margin-bottom:7px}
.ai-p{font-size:12px;color:rgba(255,255,255,.8);line-height:1.6}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="navbar">
    <div class="nav-title">Buyurtmalar Boshqaruvi</div>
    <div class="user-pill">
        <div class="u-av"><?= strtoupper(substr($_SESSION['name']??'M',0,1)) ?></div>
        <?= htmlspecialchars(explode(' ',$_SESSION['name']??'Menejer')[0]) ?>
    </div>
</div>

<div class="main">

    <!-- ORDERS -->
    <div class="orders-wrap">
        <div class="ow-header">
            <div><div class="ow-title">Kiruvchi Buyurtmalar</div><div class="ow-sub" id="lastUpd">Yuklanmoqda...</div></div>
            <div class="filters">
                <button class="ftab active" onclick="setF('yangi',this)">Yangi <span class="cnt" id="c-yangi">0</span></button>
                <button class="ftab" onclick="setF('qabul',this)">Qabul <span class="cnt" id="c-qabul">0</span></button>
                <button class="ftab" onclick="setF('rad',this)">Rad <span class="cnt" id="c-rad">0</span></button>
                <button class="ftab" onclick="setF('barchasi',this)">Barchasi</button>
            </div>
        </div>
        <div class="orders-list" id="ordersList"><div class="empty">Yuklanmoqda...</div></div>
    </div>

    <!-- STATS -->
    <div class="stats-side">
        <div class="ss-header"><div class="ss-dot"></div><div class="ss-title">AI Tahlil</div></div>
        <div class="ss-body" id="statsBody"><div style="color:#A3AED0;text-align:center;padding:30px;font-size:13px">Yuklanmoqda...</div></div>
    </div>
</div>

<!-- REJECT MODAL -->
<div id="rejModal" style="display:none;position:fixed;inset:0;background:rgba(17,28,68,.45);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:18px;padding:28px;width:320px;box-shadow:0 20px 50px rgba(17,28,68,.18)">
        <div style="font-size:15px;font-weight:700;color:#111C44;margin-bottom:14px">Rad etish sababi</div>
        <input id="rejSabab" type="text" placeholder="Sabab (ixtiyoriy)..."
            style="width:100%;padding:11px 13px;border-radius:10px;border:1.5px solid #E9EDF7;font-size:13px;outline:none;margin-bottom:14px">
        <div style="display:flex;gap:8px">
            <button onclick="closeRej()" style="flex:1;padding:10px;border-radius:10px;border:1px solid #E9EDF7;background:#F4F7FE;font-weight:600;font-size:13px;cursor:pointer">Bekor</button>
            <button onclick="confirmRej()" style="flex:1;padding:10px;border-radius:10px;border:none;background:#EE5D50;color:#fff;font-weight:700;font-size:13px;cursor:pointer">Rad etish</button>
        </div>
    </div>
</div>

<script>
var cf='yangi', rejId=0, cnts={yangi:0,qabul:0,rad:0};
function fmt(n){return Number(n).toLocaleString('uz-UZ')+' UZS';}
function fmtD(s){var d=new Date(s);return d.toLocaleDateString('uz-UZ',{day:'2-digit',month:'2-digit'})+' '+d.toLocaleTimeString('uz-UZ',{hour:'2-digit',minute:'2-digit'});}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function setF(f,btn){cf=f;document.querySelectorAll('.ftab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');loadOrders();}
function loadOrders(){
    fetch('/menejer/api.php?action=orders&filter='+cf).then(r=>r.json()).then(d=>{
        if(d.status!=='ok')return;
        renderOrders(d.orders);
        document.getElementById('lastUpd').textContent='Yangilandi: '+new Date().toLocaleTimeString('uz-UZ');
    });
}
function renderOrders(list){
    var el=document.getElementById('ordersList');
    if(cf==='barchasi'){cnts={yangi:0,qabul:0,rad:0};list.forEach(o=>{if(o.status==='yangi')cnts.yangi++;else if(o.status==='qabul_qilindi')cnts.qabul++;else if(o.status==='rad_etildi')cnts.rad++;});}
    else{cnts[cf==='qabul'?'qabul':cf==='rad'?'rad':'yangi']=list.length;}
    document.getElementById('c-yangi').textContent=cnts.yangi;
    document.getElementById('c-qabul').textContent=cnts.qabul;
    document.getElementById('c-rad').textContent=cnts.rad;
    if(!list.length){el.innerHTML='<div class="empty">📭 Buyurtmalar yo\'q</div>';return;}
    var h='';
    list.forEach(o=>{
        var sLbl=o.status==='yangi'?'Yangi':o.status==='qabul_qilindi'?'Qabul':o.status==='rad_etildi'?'Rad':o.status;
        var items='';
        (o.items||[]).forEach(i=>{items+='<div class="oi"><span class="oi-n">'+esc(i.prod_name||'—')+'</span><span class="oi-q">'+i.quantity+' '+esc(i.unit)+'</span><span class="oi-p">'+fmt(i.total)+'</span></div>';});
        var note=o.note?'<div class="oc-note">📝 '+esc(o.note)+'</div>':'';
        var actions=o.status==='yangi'
            ?'<div class="oc-actions"><button class="btn-ok" onclick="accept('+o.id+')">✓ Qabul qilish</button><button class="btn-no" onclick="openRej('+o.id+')">✕ Rad etish</button></div>'
            :o.status==='qabul_qilindi'?'<div class="oc-actions"><div class="btn-done">✓ Qabul qilindi</div></div>':'';
        h+='<div class="ocard '+esc(o.status)+'" id="o-'+o.id+'">'
            +'<div class="oc-top"><div><div class="oc-id">#'+String(o.id).padStart(5,'0')+'</div><div class="oc-time">'+fmtD(o.sale_date)+'</div></div>'
            +'<span class="ostatus s-'+esc(o.status)+'">'+sLbl+'</span></div>'
            +'<div class="oc-client"><div class="cl-av">'+esc((o.mijoz_nomi||'M').charAt(0).toUpperCase())+'</div>'
            +'<span class="cl-name">'+esc(o.mijoz_nomi)+'</span>'
            +'<span class="cl-type '+(o.sale_type==='yetkazish'?'t-d':'t-p')+'">'+(o.sale_type==='yetkazish'?'🚚 Yetkazish':'🏪 Olib ketish')+'</span></div>'
            +(items?'<div class="oc-items">'+items+'</div>':'')
            +note
            +'<div class="oc-total"><span>Jami:</span><span class="oc-tot-val">'+fmt(o.total_price)+'</span></div>'
            +actions+'</div>';
    });
    el.innerHTML=h;
}
function accept(id){var c=document.getElementById('o-'+id);if(c)c.style.opacity='.5';var fd=new FormData();fd.append('id',id);fetch('/menejer/api.php?action=accept',{method:'POST',body:fd}).then(r=>r.json()).then(()=>{loadOrders();loadStats();});}
function openRej(id){rejId=id;document.getElementById('rejSabab').value='';document.getElementById('rejModal').style.display='flex';}
function closeRej(){document.getElementById('rejModal').style.display='none';rejId=0;}
function confirmRej(){if(!rejId)return;var fd=new FormData();fd.append('id',rejId);fd.append('sabab',document.getElementById('rejSabab').value);closeRej();fetch('/menejer/api.php?action=reject',{method:'POST',body:fd}).then(r=>r.json()).then(()=>{loadOrders();loadStats();});}
function loadStats(){
    fetch('/menejer/api.php?action=stats').then(r=>r.json()).then(d=>{
        if(d.status!=='ok')return;
        var h='<div class="mini2">'
            +'<div class="msc blue"><div class="msc-v">'+d.yangi_soni+'</div><div class="msc-l">Yangi</div></div>'
            +'<div class="msc green"><div class="msc-v">'+d.bugun_soni+'</div><div class="msc-l">Bugungi</div></div>'
            +'<div class="msc orange"><div class="msc-v">'+fmtS(d.bugun_jami)+'</div><div class="msc-l">Daromad</div></div>'
            +'<div class="msc"><div class="msc-v">'+fmtS(d.ortacha_chek)+'</div><div class="msc-l">O\'rtacha</div></div>'
            +'</div>';
        if(d.top_prods&&d.top_prods.length){
            h+='<div><div class="sec-t">Top mahsulotlar</div>';
            var ns=['','n2','n3','n4','n5'];
            d.top_prods.forEach((p,i)=>{h+='<div class="tp-row"><div class="tp-n '+ns[i]+'">'+(i+1)+'</div><div class="tp-name">'+esc(p.name)+'</div><div class="tp-qty">'+Math.round(p.jami_qty)+' dona</div></div>';});
            h+='</div>';
        }
        var ins=[];
        if(d.yangi_soni>0)ins.push('Hozir <strong>'+d.yangi_soni+' ta yangi buyurtma</strong> kutilmoqda!');
        if(d.top_prods&&d.top_prods[0])ins.push('Eng mashhur: <strong>'+esc(d.top_prods[0].name)+'</strong>.');
        if(d.yetkazish>d.olib_ketish)ins.push('Mijozlarning '+Math.round(d.yetkazish/(d.yetkazish+d.olib_ketish)*100)+'% yetkazishni tanlaydi.');
        if(!ins.length)ins.push('Yangi buyurtmalar yo\'q. Saytni reklama qiling!');
        h+='<div class="ai-box"><div class="ai-t">🤖 AI Tavsiya</div><div class="ai-p">'+ins.join(' ')+'</div></div>';
        document.getElementById('statsBody').innerHTML=h;
    });
}
function fmtS(n){n=+n;if(n>=1000000)return(n/1e6).toFixed(1)+'M';if(n>=1000)return(n/1000).toFixed(0)+'K';return Math.round(n);}
loadOrders();loadStats();
setInterval(()=>{loadOrders();},20000);
setInterval(()=>{loadStats();},60000);
</script>
</body>
</html>
