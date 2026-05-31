<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../middleware/admin_check.php";
require_once __DIR__ . "/../config/dokon_db.php";

$st        = mysqli_fetch_assoc(mysqli_query($conn,"SELECT store_name FROM settings WHERE id=1"));
$site_name = $st['store_name'] ?? 'SMART POS';
$active_menu = 'media';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rasm Kutubxonasi | <?= htmlspecialchars($site_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
<style>
body { font-family:'Inter',sans-serif; background:#F8FAFC; color:#0F172A; }

.page-header {
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:24px;flex-wrap:wrap;gap:12px;
}
.page-title { font-size:24px;font-weight:800;color:#0F172A;letter-spacing:-.5px; }
.page-title span { color:#4318FF; }

/* STATS */
.stat-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px; }
.stat-card {
  background:#fff;border:1px solid #E2E8F0;border-radius:16px;
  padding:18px 20px;display:flex;align-items:center;gap:14px;
}
.stat-ico {
  width:46px;height:46px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;
}
.stat-val { font-size:22px;font-weight:900;color:#0F172A;line-height:1; }
.stat-lbl { font-size:12px;font-weight:600;color:#94A3B8;margin-top:2px; }

/* UPLOAD ZONE */
.upload-zone {
  background:#fff;border:2.5px dashed #CBD5E1;border-radius:20px;
  padding:36px 24px;text-align:center;cursor:pointer;
  transition:.2s;margin-bottom:24px;position:relative;
}
.upload-zone:hover,.upload-zone.drag { border-color:#4318FF;background:#F0EEFF; }
.upload-zone.drag { transform:scale(1.01); }
.uz-ico { font-size:42px;margin-bottom:10px;line-height:1; }
.uz-title { font-size:16px;font-weight:700;color:#0F172A;margin-bottom:4px; }
.uz-sub   { font-size:13px;color:#94A3B8;margin-bottom:14px; }
.btn-upload {
  background:#4318FF;color:#fff;border:none;padding:10px 24px;
  border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;
  display:inline-flex;align-items:center;gap:8px;transition:.15s;
}
.btn-upload:hover { background:#3912E8; }
#fileInput { display:none; }

/* PROGRESS */
.upload-progress { display:none;margin-top:14px; }
.progress-bar-outer {
  background:#E2E8F0;border-radius:999px;height:8px;overflow:hidden;margin-bottom:6px;
}
.progress-bar-inner {
  height:100%;background:linear-gradient(90deg,#4318FF,#818CF8);
  border-radius:999px;transition:width .3s;width:0%;
}
.progress-text { font-size:12px;font-weight:600;color:#64748B;text-align:center; }

/* FILTER/SEARCH */
.toolbar {
  display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;
}
.search-inp {
  flex:1;min-width:180px;height:40px;
  background:#fff;border:1.5px solid #E2E8F0;border-radius:10px;
  padding:0 14px 0 38px;font-size:14px;font-weight:500;
}
.search-inp:focus { outline:none;border-color:#4318FF; }
.search-wrap { position:relative;flex:1;min-width:180px; }
.search-wrap i { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8; }

/* IMAGE GRID */
.img-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
  gap:14px;
}
.img-card {
  background:#fff;border:1.5px solid #E2E8F0;border-radius:14px;
  overflow:hidden;transition:.18s;position:relative;
}
.img-card:hover { border-color:#4318FF;box-shadow:0 6px 20px rgba(67,24,255,.12);transform:translateY(-2px); }
.img-thumb {
  width:100%;height:130px;object-fit:cover;background:#F1F5F9;
  display:flex;align-items:center;justify-content:center;overflow:hidden;
  cursor:pointer;
}
.img-thumb img { width:100%;height:100%;object-fit:cover;transition:.3s; }
.img-thumb:hover img { transform:scale(1.06); }
.img-thumb-placeholder { font-size:36px;color:#CBD5E1; }
.img-info { padding:8px 10px 10px; }
.img-name { font-size:11px;font-weight:700;color:#334155;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;margin-bottom:2px; }
.img-size { font-size:10px;color:#94A3B8;font-weight:600; }
.img-actions { display:flex;gap:6px;margin-top:8px; }
.btn-copy {
  flex:1;height:30px;border:1.5px solid #E2E8F0;border-radius:8px;
  background:#F8FAFC;font-size:11px;font-weight:700;color:#475569;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:4px;transition:.15s;
}
.btn-copy:hover { border-color:#4318FF;color:#4318FF;background:#F0EEFF; }
.btn-del {
  width:30px;height:30px;border:none;border-radius:8px;
  background:#FEE2E2;color:#EF4444;font-size:13px;cursor:pointer;transition:.15s;
  display:flex;align-items:center;justify-content:center;
}
.btn-del:hover { background:#EF4444;color:#fff; }

/* CHECK overlay */
.img-check {
  position:absolute;top:8px;right:8px;
  width:22px;height:22px;border-radius:50%;
  background:#4318FF;color:#fff;font-size:12px;
  display:none;align-items:center;justify-content:center;
  box-shadow:0 2px 8px rgba(67,24,255,.4);
}

/* EMPTY */
.empty-box { text-align:center;padding:60px 20px;color:#94A3B8; }
.empty-box .ei { font-size:56px;margin-bottom:14px; }
.empty-box p { font-size:15px;font-weight:700;color:#334155;margin-bottom:6px; }

/* TOAST */
.toast-box {
  position:fixed;bottom:24px;right:24px;z-index:9999;
  display:flex;flex-direction:column;gap:8px;pointer-events:none;
}
.toast {
  background:#0F172A;color:#fff;padding:12px 18px;border-radius:12px;
  font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.25);
  animation:toastIn .3s ease;display:flex;align-items:center;gap:8px;
  pointer-events:all;
}
.toast.success { background:#065F46; }
.toast.error   { background:#991B1B; }
@keyframes toastIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}

/* VIEW modal */
.view-modal-bg {
  position:fixed;inset:0;background:rgba(0,0,0,.7);
  z-index:9990;display:none;align-items:center;justify-content:center;
  backdrop-filter:blur(4px);
}
.view-modal-bg.show{display:flex;}
.view-modal {
  background:#fff;border-radius:20px;max-width:500px;width:90%;
  overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.3);
}
.view-modal img{width:100%;max-height:360px;object-fit:contain;background:#F1F5F9;}
.view-modal-foot{padding:16px 20px;display:flex;justify-content:space-between;align-items:center;}
.view-name{font-size:14px;font-weight:700;color:#0F172A;}
.view-size{font-size:12px;color:#94A3B8;}
.btn-close-view{
  background:#F1F5F9;border:none;border-radius:10px;
  padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;
}
</style>
</head>
<body>

<?php require_once __DIR__ . "/includes/sidebar.php"; ?>

<div class="main-content" style="padding:24px 28px;min-height:100vh;">

  <!-- HEADER -->
  <div class="page-header">
    <div>
      <div class="page-title">🖼️ Rasm <span>Kutubxonasi</span></div>
      <div style="font-size:13px;color:#94A3B8;margin-top:2px;">Bir marta yuklang — har doim foydalaning</div>
    </div>
    <a href="Mahsulotlar/index.php" class="btn btn-light" style="border-radius:12px;font-weight:600;font-size:13px;">
      ← Mahsulotlarga
    </a>
  </div>

  <!-- STATS -->
  <div class="stat-grid" id="statsRow">
    <div class="stat-card">
      <div class="stat-ico" style="background:#EEF2FF;">🖼️</div>
      <div><div class="stat-val" id="statCount">—</div><div class="stat-lbl">Jami rasm</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-ico" style="background:#F0FDF4;">💾</div>
      <div><div class="stat-val" id="statSize">—</div><div class="stat-lbl">Hajmi (KB)</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-ico" style="background:#FFF7ED;">⚡</div>
      <div><div class="stat-val">150</div><div class="stat-lbl">Max KB / rasm</div></div>
    </div>
  </div>

  <!-- UPLOAD ZONE -->
  <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()"
       ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFiles(event)">
    <div class="uz-ico">📤</div>
    <div class="uz-title">Rasmlarni shu yerga tashlang yoki tanlang</div>
    <div class="uz-sub">JPG, PNG, WEBP • Avtomatik siqiladi • Bir vaqtda ko'p yuklash</div>
    <button class="btn-upload" onclick="event.stopPropagation();" type="button"
            onmousedown="document.getElementById('fileInput').click()">
      <i class="fas fa-cloud-upload-alt"></i> Rasm tanlash
    </button>
    <input type="file" id="fileInput" multiple accept="image/*" onchange="handleFiles(this.files)">
    <div class="upload-progress" id="uploadProg">
      <div class="progress-bar-outer"><div class="progress-bar-inner" id="progBar"></div></div>
      <div class="progress-text" id="progText">Yuklanmoqda...</div>
    </div>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" class="search-inp" id="searchInp" placeholder="Rasm nomini qidiring..." oninput="filterGrid()">
    </div>
    <button class="btn btn-light" style="border-radius:10px;font-size:13px;font-weight:600;" onclick="loadImages()">
      <i class="fas fa-sync-alt mr-1"></i> Yangilash
    </button>
  </div>

  <!-- IMAGE GRID -->
  <div id="imgGrid" class="img-grid"></div>

</div>

<!-- VIEW MODAL -->
<div class="view-modal-bg" id="viewModal" onclick="if(event.target===this)closeView()">
  <div class="view-modal">
    <img id="viewImg" src="" alt="">
    <div class="view-modal-foot">
      <div><div class="view-name" id="viewName"></div><div class="view-size" id="viewSz"></div></div>
      <button class="btn-close-view" onclick="closeView()">✕ Yopish</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast-box" id="toastBox"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let allImages = [];

// ── Yuklanganda
document.addEventListener('DOMContentLoaded', () => { loadStats(); loadImages(); });

// ── Statistika
async function loadStats() {
  const d = await fetchJson('/admin/media_api.php?action=stats');
  if (d.status === 'ok') {
    document.getElementById('statCount').textContent = d.count;
    document.getElementById('statSize').textContent  = (d.total_kb).toLocaleString();
  }
}

// ── Rasmlarni yuklash
async function loadImages() {
  document.getElementById('imgGrid').innerHTML = '<div style="text-align:center;padding:40px;color:#94A3B8;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
  const d = await fetchJson('/admin/media_api.php?action=list');
  if (d.status !== 'ok') { toast('Xatolik: '+d.message,'error'); return; }
  allImages = d.images;
  renderGrid(allImages);
}

// ── Grid render — thumbnailsiz (lazy load)
function renderGrid(images) {
  const grid = document.getElementById('imgGrid');
  if (!images.length) {
    grid.innerHTML = `<div class="empty-box"><div class="ei">🖼️</div><p>Rasm kutubxonasi bo'sh</p><span>Yuqoridagi maydonga rasm tashlang</span></div>`;
    return;
  }
  grid.innerHTML = images.map(img => `
    <div class="img-card" data-id="${img.id}" data-name="${escH(img.name)}">
      <div class="img-thumb" onclick="viewImg(${img.id},'${escH(img.name)}')">
        <div class="img-thumb-placeholder" id="ph_${img.id}"><i class="fas fa-image"></i></div>
        <img id="th_${img.id}" src="" alt="${escH(img.name)}"
             style="display:none;width:100%;height:100%;object-fit:cover;"
             onload="this.previousElementSibling.style.display='none';this.style.display='block';">
      </div>
      <div class="img-check" id="chk_${img.id}">✓</div>
      <div class="img-info">
        <div class="img-name" title="${escH(img.name)}">${escH(img.name)}</div>
        <div class="img-size">${img.size_kb} KB</div>
        <div class="img-actions">
          <button class="btn-copy" onclick="copyImgData(${img.id})">
            <i class="fas fa-copy"></i> Nusxalash
          </button>
          <button class="btn-del" onclick="deleteImg(${img.id})" title="O'chirish">
            <i class="fas fa-trash-alt"></i>
          </button>
        </div>
      </div>
    </div>
  `).join('');

  // Lazy load thumbnails
  lazyLoadThumbs(images);
}

// ── Thumbnail lazy load
async function lazyLoadThumbs(images) {
  for (const img of images) {
    try {
      const d = await fetchJson(`/admin/media_api.php?action=get&id=${img.id}`);
      if (d.status === 'ok' && d.image) {
        const el = document.getElementById('th_' + img.id);
        if (el) el.src = d.image.data;
      }
    } catch(e) {}
  }
}

// ── Qidirish
function filterGrid() {
  const q = document.getElementById('searchInp').value.toLowerCase().trim();
  const filtered = q ? allImages.filter(i => i.name.toLowerCase().includes(q)) : allImages;
  renderGrid(filtered);
}

// ── Fayllarni qayta ishlash
async function handleFiles(files) {
  if (!files.length) return;
  const prog = document.getElementById('uploadProg');
  const bar  = document.getElementById('progBar');
  const txt  = document.getElementById('progText');
  prog.style.display = 'block'; bar.style.width = '0%';

  let success = 0, fail = 0;
  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    bar.style.width = Math.round((i / files.length) * 80) + '%';
    txt.textContent = `Yuklanmoqda ${i+1}/${files.length}: ${file.name}`;

    try {
      const b64 = await compressImage(file, 400, 0.85);
      const fd  = new FormData();
      fd.append('action', 'upload');
      fd.append('data',   b64);
      fd.append('name',   file.name.replace(/\.[^.]+$/,''));
      const res = await fetch('/admin/media_api.php', {method:'POST',body:fd});
      const d   = await res.json();
      if (d.status === 'ok') success++;
      else { fail++; toast(`${file.name}: ${d.message}`, 'error'); }
    } catch(e) { fail++; }
  }

  bar.style.width = '100%';
  txt.textContent = `✅ ${success} ta yuklandi${fail ? ' | ❌ '+fail+' ta xato' : ''}`;
  setTimeout(() => { prog.style.display='none'; }, 2500);

  toast(`${success} ta rasm muvaffaqiyatli yuklandi! 🎉`);
  loadStats(); loadImages();
  document.getElementById('fileInput').value = '';
}

// ── Rasm siqish (canvas)
function compressImage(file, maxPx=400, quality=0.85) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = e => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        let w = img.width, h = img.height;
        if (w > h) { if (w > maxPx) { h = Math.round(h*maxPx/w); w = maxPx; } }
        else       { if (h > maxPx) { w = Math.round(w*maxPx/h); h = maxPx; } }
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        resolve(canvas.toDataURL('image/jpeg', quality));
      };
      img.onerror = reject;
      img.src = e.target.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

// ── Rasmni ko'rish
async function viewImg(id, name) {
  const d = await fetchJson(`/admin/media_api.php?action=get&id=${id}`);
  if (d.status !== 'ok') return;
  document.getElementById('viewImg').src = d.image.data;
  document.getElementById('viewName').textContent = name;
  document.getElementById('viewSz').textContent   = d.image.size_kb + ' KB';
  document.getElementById('viewModal').classList.add('show');
}
function closeView() { document.getElementById('viewModal').classList.remove('show'); }

// ── Nusxalash (clipboard)
async function copyImgData(id) {
  const d = await fetchJson(`/admin/media_api.php?action=get&id=${id}`);
  if (d.status !== 'ok') return;
  try {
    await navigator.clipboard.writeText(d.image.data);
    toast('URL nusxalandi! ✅');
  } catch(e) {
    // fallback
    const ta = document.createElement('textarea');
    ta.value = d.image.data; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
    toast('URL nusxalandi! ✅');
  }
}

// ── O'chirish
async function deleteImg(id) {
  if (!confirm('Bu rasmni o\'chirasizmi?')) return;
  const fd = new FormData(); fd.append('id', id);
  const res = await fetch('/admin/media_api.php?action=delete', {method:'POST',body:fd});
  const d   = await res.json();
  if (d.status === 'ok') { toast('Rasm o\'chirildi', 'success'); loadStats(); loadImages(); }
  else toast('Xatolik: '+d.message, 'error');
}

// ── Drag & Drop
function dragOver(e)  { e.preventDefault(); document.getElementById('uploadZone').classList.add('drag'); }
function dragLeave(e) { document.getElementById('uploadZone').classList.remove('drag'); }
function dropFiles(e) { e.preventDefault(); dragLeave(); handleFiles(e.dataTransfer.files); }

// ── Helpers
async function fetchJson(url) {
  const res = await fetch(url);
  return await res.json();
}
function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function toast(msg, type='success') {
  const box = document.getElementById('toastBox');
  const el  = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}
</script>
</body>
</html>
