<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

$root = dirname(__DIR__, 2);
require_once $root . "/middleware/admin_check.php";
require_once $root . "/config/dokon_db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['status'=>'error','message'=>'Ruxsat yo\'q']); exit;
}

// ── Jadvallar yaratish ──────────────────────────────────────────
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS user_permissions (
    user_id       INT PRIMARY KEY,
    perm_sell     TINYINT DEFAULT 1,
    perm_discount TINYINT DEFAULT 0,
    max_discount  INT     DEFAULT 0,
    perm_reports  TINYINT DEFAULT 0,
    perm_add_prod TINYINT DEFAULT 0,
    perm_edit_prod TINYINT DEFAULT 0,
    perm_del_prod TINYINT DEFAULT 0,
    perm_finance  TINYINT DEFAULT 0,
    perm_orders   TINYINT DEFAULT 1,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS salary_payments (
    id       BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT    NOT NULL,
    amount   DECIMAL(14,2) NOT NULL,
    note     VARCHAR(255)  DEFAULT '',
    paid_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    paid_by  INT           DEFAULT NULL
)");
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS salary DECIMAL(14,2) DEFAULT 0");
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Ruhsatlarni olish ──────────────────────────────────────────
if ($action === 'get_permissions') {
    $uid = (int)($_GET['id'] ?? 0);
    $p   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM user_permissions WHERE user_id=$uid LIMIT 1"));
    if (!$p) {
        // Default ruhsatlar (rol bo'yicha)
        $u    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT role FROM users WHERE id=$uid LIMIT 1"));
        $role = $u['role'] ?? 'sotuvchi';
        $p    = [
            'user_id'      => $uid,
            'perm_sell'    => in_array($role,['sotuvchi','admin','superadmin']) ? 1 : 0,
            'perm_discount'=> 0, 'max_discount'  => 0,
            'perm_reports' => in_array($role,['menejer','admin','superadmin']) ? 1 : 0,
            'perm_add_prod'=> in_array($role,['admin','superadmin']) ? 1 : 0,
            'perm_edit_prod'=> in_array($role,['admin','superadmin']) ? 1 : 0,
            'perm_del_prod'=> $role==='superadmin' ? 1 : 0,
            'perm_finance' => in_array($role,['admin','superadmin']) ? 1 : 0,
            'perm_orders'  => in_array($role,['menejer','admin','superadmin']) ? 1 : 0,
        ];
    }
    echo json_encode(['status'=>'ok','permissions'=>$p]);
    exit;
}

// ── Ruhsatlarni saqlash ────────────────────────────────────────
if ($action === 'save_permissions') {
    $uid  = (int)($_POST['user_id'] ?? 0);
    $data = [
        'perm_sell'    => (int)($_POST['perm_sell']    ?? 0),
        'perm_discount'=> (int)($_POST['perm_discount']?? 0),
        'max_discount' => min(100,(int)($_POST['max_discount']??0)),
        'perm_reports' => (int)($_POST['perm_reports'] ?? 0),
        'perm_add_prod'=> (int)($_POST['perm_add_prod']?? 0),
        'perm_edit_prod'=>(int)($_POST['perm_edit_prod']??0),
        'perm_del_prod'=> (int)($_POST['perm_del_prod']?? 0),
        'perm_finance' => (int)($_POST['perm_finance'] ?? 0),
        'perm_orders'  => (int)($_POST['perm_orders']  ?? 0),
    ];
    $exist = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM user_permissions WHERE user_id=$uid LIMIT 1"));
    if ($exist) {
        $sets = [];
        foreach($data as $k=>$v) $sets[] = "$k=$v";
        mysqli_query($conn,"UPDATE user_permissions SET ".implode(',',$sets)." WHERE user_id=$uid");
    } else {
        $cols = 'user_id,'.implode(',',array_keys($data));
        $vals = "$uid,".implode(',',array_values($data));
        mysqli_query($conn,"INSERT INTO user_permissions ($cols) VALUES ($vals)");
    }
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Maosh ma'lumotlari ─────────────────────────────────────────
if ($action === 'get_salary') {
    $uid = (int)($_GET['id'] ?? 0);
    $u   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id,name,salary FROM users WHERE id=$uid LIMIT 1"));
    // So'nggi 6 oylik to'lovlar
    $res = mysqli_query($conn,"SELECT * FROM salary_payments WHERE user_id=$uid ORDER BY paid_at DESC LIMIT 12");
    $pays = [];
    if ($res) while($r = mysqli_fetch_assoc($res)) $pays[] = $r;
    // Joriy oyda to'langan?
    $thisMonth = date('Y-m');
    $paid = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(amount) as total FROM salary_payments WHERE user_id=$uid AND DATE_FORMAT(paid_at,'%Y-%m')='$thisMonth'"));
    echo json_encode(['status'=>'ok','user'=>$u,'payments'=>$pays,'month_paid'=>(float)($paid['total']??0)]);
    exit;
}

// ── Maosh to'lash ──────────────────────────────────────────────
if ($action === 'pay_salary') {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $note   = mysqli_real_escape_string($conn, trim($_POST['note'] ?? ''));
    $paidBy = (int)$_SESSION['user_id'];
    if ($amount <= 0) { echo json_encode(['status'=>'error','message'=>'Summa noto\'g\'ri']); exit; }
    $nid_r = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(MAX(id),0)+1 AS nid FROM salary_payments"));
    $nid   = (int)$nid_r['nid'];
    mysqli_query($conn,"INSERT INTO salary_payments (id,user_id,amount,note,paid_by) VALUES ($nid,$uid,$amount,'$note',$paidBy)");
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Oylik maoshni o'zgartirish ─────────────────────────────────
if ($action === 'update_salary') {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $salary = (float)($_POST['salary'] ?? 0);
    mysqli_query($conn,"UPDATE users SET salary=$salary WHERE id=$uid");
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Asosiy ma'lumotlarni yangilash ─────────────────────────────
if ($action === 'update_info') {
    $uid     = (int)($_POST['user_id'] ?? 0);
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']    ?? ''));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']   ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $role    = mysqli_real_escape_string($conn, trim($_POST['role']    ?? 'sotuvchi'));
    $allowed = ['sotuvchi','menejer','admin'];
    if (!in_array($role,$allowed)) $role='sotuvchi';
    mysqli_query($conn,"UPDATE users SET name='$name',phone='$phone',address='$address',role='$role' WHERE id=$uid AND role NOT IN('superadmin')");
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Status toggle (faol/nofaol) ────────────────────────────────
if ($action === 'toggle_status') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $cur = mysqli_fetch_assoc(mysqli_query($conn,"SELECT status FROM users WHERE id=$uid LIMIT 1"));
    $new = ($cur['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
    mysqli_query($conn,"UPDATE users SET status='$new' WHERE id=$uid AND role NOT IN('superadmin')");
    echo json_encode(['status'=>'ok','new_status'=>$new]);
    exit;
}

// ── Parolni o'zgartirish ───────────────────────────────────────
if ($action === 'change_password') {
    $uid  = (int)($_POST['user_id'] ?? 0);
    $pass = trim($_POST['password'] ?? '');
    if (strlen($pass) < 4) { echo json_encode(['status'=>'error','message'=>'Parol kamida 4 belgi']); exit; }
    $hash = mysqli_real_escape_string($conn, password_hash($pass, PASSWORD_DEFAULT));
    mysqli_query($conn,"UPDATE users SET password='$hash' WHERE id=$uid");
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Xodim qo'shish ─────────────────────────────────────────────
if ($action === 'add_user') {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']     ?? ''));
    $username= mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']    ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']  ?? ''));
    $pass    = trim($_POST['password'] ?? '');
    $role    = mysqli_real_escape_string($conn, $_POST['role'] ?? 'sotuvchi');
    $salary  = (float)($_POST['salary'] ?? 0);

    if (!$name || !$username || strlen($pass)<4) {
        echo json_encode(['status'=>'error','message'=>'Ism, login va parol (min 4) talab qilinadi']); exit;
    }
    $chk = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id FROM users WHERE username='$username' LIMIT 1"));
    if ($chk) { echo json_encode(['status'=>'error','message'=>'Bu login band!']); exit; }

    $hash  = mysqli_real_escape_string($conn, password_hash($pass, PASSWORD_DEFAULT));
    $nid_r = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(MAX(id),0)+1 AS nid FROM users"));
    $nid   = (int)$nid_r['nid'];
    mysqli_query($conn,"INSERT INTO users (id,name,username,password,phone,address,role,salary,status)
                        VALUES ($nid,'$name','$username','$hash','$phone','$address','$role',$salary,'active')");
    echo json_encode(['status'=>'ok','id'=>$nid,'name'=>$name]);
    exit;
}

// ── Xodimni o'chirish ──────────────────────────────────────────
if ($action === 'delete_user') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid == (int)$_SESSION['user_id']) { echo json_encode(['status'=>'error','message'=>'O\'zingizni o\'chira olmaysiz']); exit; }
    $chk = mysqli_fetch_assoc(mysqli_query($conn,"SELECT role FROM users WHERE id=$uid LIMIT 1"));
    if (($chk['role'] ?? '') === 'superadmin') { echo json_encode(['status'=>'error','message'=>'Superadminni o\'chirish mumkin emas']); exit; }
    mysqli_query($conn,"DELETE FROM users WHERE id=$uid");
    mysqli_query($conn,"DELETE FROM user_permissions WHERE user_id=$uid");
    echo json_encode(['status'=>'ok']);
    exit;
}

// ── Xodimlar ro'yxati ──────────────────────────────────────────
if ($action === 'list') {
    $res   = mysqli_query($conn,"SELECT id,name,username,phone,address,role,salary,status,avatar FROM users ORDER BY id DESC");
    $users = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) {
        // avatar ni qisqartirish (faqat mavjudligini ko'rsatish)
        $r['has_avatar'] = !empty($r['avatar']);
        unset($r['avatar']);
        $users[] = $r;
    }
    // Statistika
    $stats = [];
    $sr = mysqli_query($conn,"SELECT COUNT(*) as cnt, COALESCE(SUM(salary),0) as total_salary,
                               SUM(status='active') as active_cnt FROM users WHERE role != 'mijoz'");
    if ($sr) $stats = mysqli_fetch_assoc($sr);
    echo json_encode(['status'=>'ok','users'=>$users,'stats'=>$stats]);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Noma\'lum so\'rov']);
