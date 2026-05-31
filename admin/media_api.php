<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../middleware/admin_check.php";
require_once __DIR__ . "/../config/dokon_db.php";

// Jadval yaratish (bir marta)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS media_library (
    id     BIGINT AUTO_INCREMENT PRIMARY KEY,
    name   VARCHAR(255)  DEFAULT 'rasm',
    data   MEDIUMTEXT    NOT NULL,
    size_kb INT          DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── СПИСОК ──────────────────────────────────────
if ($action === 'list') {
    $res  = mysqli_query($conn, "SELECT id, name, size_kb, created_at,
                                        SUBSTRING(data,1,300) AS thumb_prefix
                                 FROM media_library ORDER BY id DESC");
    $list = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) {
        // to'liq data ni alohida endpoint orqali olamiz
        $list[] = [
            'id'         => (int)$r['id'],
            'name'       => $r['name'],
            'size_kb'    => (int)$r['size_kb'],
            'created_at' => $r['created_at'],
        ];
    }
    echo json_encode(['status'=>'ok','images'=>$list]);
    exit;
}

// ── BITTA RASMNI OLISH ───────────────────────────
if ($action === 'get') {
    $id  = (int)($_GET['id'] ?? 0);
    $row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id,name,data,size_kb FROM media_library WHERE id=$id LIMIT 1"));
    if (!$row) { echo json_encode(['status'=>'error','message'=>'Topilmadi']); exit; }
    echo json_encode(['status'=>'ok','image'=>$row]);
    exit;
}

// ── YUKLASH ──────────────────────────────────────
if ($action === 'upload') {
    $data = trim($_POST['data'] ?? '');
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? 'rasm'));

    if (!$data || !str_starts_with($data, 'data:image')) {
        echo json_encode(['status'=>'error','message'=>'Rasm ma\'lumoti topilmadi']); exit;
    }
    // Hajm tekshiruv: max 150KB base64 (~112KB asl)
    $sizeKb = (int)round(strlen($data) * 3/4 / 1024);
    if (strlen($data) > 200000) {
        echo json_encode(['status'=>'error','message'=>"Rasm juda katta ({$sizeKb}KB). Max 150KB."]); exit;
    }

    // Bir xil rasm ikki marta yuklanmasin
    $prefix = mysqli_real_escape_string($conn, substr($data, 0, 100));
    $exist  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id FROM media_library WHERE SUBSTRING(data,1,100)='$prefix' LIMIT 1"));
    if ($exist) {
        echo json_encode(['status'=>'ok','id'=>(int)$exist['id'],'message'=>'Allaqachon mavjud']);
        exit;
    }

    $dataEsc = mysqli_real_escape_string($conn, $data);
    $nid_r   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(MAX(id),0)+1 AS nid FROM media_library"));
    $nid     = (int)$nid_r['nid'];

    mysqli_query($conn,"INSERT INTO media_library (id,name,data,size_kb) VALUES ($nid,'$name','$dataEsc',$sizeKb)");
    echo json_encode(['status'=>'ok','id'=>$nid,'size_kb'=>$sizeKb]);
    exit;
}

// ── O'CHIRISH ──────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['status'=>'error','message'=>'ID kerak']); exit; }
    mysqli_query($conn, "DELETE FROM media_library WHERE id=$id");
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── STATISTIKA ─────────────────────────────────
if ($action === 'stats') {
    $r = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as cnt, COALESCE(SUM(size_kb),0) as total_kb FROM media_library"));
    echo json_encode(['status'=>'ok','count'=>(int)$r['cnt'],'total_kb'=>(int)$r['total_kb']]);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Noma\'lum so\'rov']);
