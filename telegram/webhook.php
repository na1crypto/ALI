<?php
/**
 * @AI_Menejer_bot — Telegram Webhook Handler
 * URL: https://ali-eleven.up.railway.app/telegram/webhook.php
 */
error_reporting(0);
ini_set('display_errors', 0);

define('BOT_TOKEN', '8694101598:AAE54kDSYsl4NaTxNA2bw73-4sN9Jq4xVmE');
define('TG_API',    'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('GEMINI_KEY','AIzaSyCqXMjQjdDCzIQf5qcaH25HqbqhC61czZg');
define('GEMINI_URL','https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.GEMINI_KEY);

require_once __DIR__.'/../config/dokon_db.php';

// Migrations
@mysqli_query($conn, "ALTER TABLE settings ADD COLUMN IF NOT EXISTS telegram_admin_chat VARCHAR(100) DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE settings ADD COLUMN IF NOT EXISTS telegram_allowed_ids TEXT DEFAULT NULL");

// Telegram update
$raw    = file_get_contents('php://input');
$update = json_decode($raw, true);

// Callback query (inline button) yoki oddiy xabar
$msg      = $update['message']            ?? $update['edited_message'] ?? null;
$callback = $update['callback_query']     ?? null;

if ($callback) {
    // Inline button bosildi
    $chat_id  = $callback['message']['chat']['id'];
    $data     = $callback['data'] ?? '';
    tg_answer_callback($callback['id']);
    route_command($conn, $chat_id, '/'.$data);
    exit;
}

if (!$msg) exit;

$chat_id   = $msg['chat']['id'];
$chat_type = $msg['chat']['type'] ?? 'private';
$text      = trim($msg['text'] ?? '');
$from_id   = $msg['from']['id']         ?? 0;
$from_name = $msg['from']['first_name'] ?? 'Admin';

if (!$text) exit;

// ── /start: admin sifatida ro'yxatdan o'tish ──
if (strpos($text, '/start') === 0) {
    // Birinchi admin bo'lsa — saqlash; aks holda ruxsat so'rash
    $st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT store_name, telegram_admin_chat FROM settings WHERE id=1"));
    $store = $st['store_name'] ?? "Do'kon";

    if (empty($st['telegram_admin_chat'])) {
        // Birinchi ulanish — avtomatik admin
        mysqli_query($conn, "UPDATE settings SET telegram_admin_chat='$chat_id' WHERE id=1");
        send($chat_id,
            "👋 Salom *".esc($from_name)."*\\!\n\n".
            "✅ *Admin sifatida ro'yxatga olindingiz\\!*\n\n".
            "🏪 Do'kon: *".esc($store)."*\n\n".
            "📋 Buyruqlar uchun /yordam yozing",
            main_keyboard()
        );
    } else {
        send($chat_id,
            "👋 Salom *".esc($from_name)."*\\!\n🏪 *".esc($store)."* boshqaruv boti\n\n📋 /yordam",
            main_keyboard()
        );
    }
    exit;
}

// Barcha buyruqlarni route qilish
route_command($conn, $chat_id, $text, $from_name);

// ═══════════════════════════════════════════════════
// ROUTING
// ═══════════════════════════════════════════════════
function route_command($conn, $chat_id, $text, $from_name='') {
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $month     = date('Y-m');

    $cmd = strtolower(explode(' ', $text)[0]);

    switch($cmd) {

        // ── BUGUN ──
        case '/bugun':
        case 'bugun':
        case '📊':
            $q = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(SUM(total_price),0) as jami, COUNT(*) as chek,
                        COALESCE(SUM(CASE WHEN payment_method='naqd'  THEN total_price ELSE 0 END),0) as naqd,
                        COALESCE(SUM(CASE WHEN payment_method='karta' THEN total_price ELSE 0 END),0) as karta,
                        COALESCE(SUM(CASE WHEN payment_method='optom' THEN total_price ELSE 0 END),0) as optom
                 FROM sales WHERE DATE(sale_date)='$today'"
            ));
            $qy = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(SUM(total_price),0) as j FROM sales WHERE DATE(sale_date)='$yesterday'"
            ));
            $diff     = floatval($q['jami']) - floatval($qy['j']);
            $diff_ico = $diff >= 0 ? '📈' : '📉';
            $diff_str = ($diff >= 0 ? '\\+' : '\\-') . fmt(abs($diff));

            $msg  = "📊 *Bugungi savdo — ".date('d\\.m\\.Y')."*\n\n";
            $msg .= "💰 Jami: *".fmt($q['jami'])." UZS*\n";
            $msg .= "🧾 Cheklar: *".(int)$q['chek']." ta*\n\n";
            $msg .= "💵 Naqd: ".fmt($q['naqd'])." UZS\n";
            $msg .= "💳 Karta: ".fmt($q['karta'])." UZS\n";
            if ($q['optom'] > 0)
                $msg .= "🏪 Optom: ".fmt($q['optom'])." UZS\n";
            $msg .= "\n$diff_ico Kechadan farq: *$diff_str UZS*";
            send($chat_id, $msg);
            break;

        // ── OMBOR ──
        case '/ombor':
        case '📦':
            $q = mysqli_query($conn,
                "SELECT name, quantity, unit FROM products WHERE quantity <= 5 ORDER BY quantity ASC LIMIT 15"
            );
            if (!$q || mysqli_num_rows($q) == 0) {
                send($chat_id, "✅ Barcha tovarlar zaxirada yetarli\\!");
                break;
            }
            $msg = "⚠️ *Tugayotgan tovarlar:*\n\n";
            while ($r = mysqli_fetch_assoc($q)) {
                $ico  = $r['quantity'] <= 0 ? '❌' : ($r['quantity'] <= 2 ? '🔴' : '🟡');
                $msg .= "$ico ".esc($r['name'])." — *".(int)$r['quantity']." ".esc($r['unit'])."*\n";
            }
            send($chat_id, $msg);
            break;

        // ── TOP ──
        case '/top':
        case '🏆':
            $q = mysqli_query($conn,
                "SELECT p.name, SUM(si.quantity) as soni, SUM(si.price) as summa
                 FROM sale_items si
                 JOIN products p ON si.product_id=p.id
                 JOIN sales s ON si.sale_id=s.id
                 WHERE DATE_FORMAT(s.sale_date,'%Y-%m')='$month'
                 GROUP BY si.product_id ORDER BY soni DESC LIMIT 5"
            );
            $medals = ['🥇','🥈','🥉','4️⃣','5️⃣'];
            $i = 0;
            $msg = "🏆 *Bu oyning top mahsulotlari:*\n\n";
            if ($q) while ($r = mysqli_fetch_assoc($q)) {
                $msg .= $medals[$i]." ".esc($r['name'])."\n";
                $msg .= "   📦 ".(int)$r['soni']." ta \\| 💰 ".fmt($r['summa'])." UZS\n";
                $i++;
            }
            if ($i == 0) $msg = "📭 Bu oy hali savdo yo'q\\.";
            send($chat_id, $msg);
            break;

        // ── HAFTA ──
        case '/hafta':
        case '📅':
            $msg = "📅 *Oxirgi 7 kun savdosi:*\n\n";
            $max_val = 0;
            $days_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $r = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COALESCE(SUM(total_price),0) as s, COUNT(*) as c FROM sales WHERE DATE(sale_date)='$d'"
                ));
                $days_data[] = ['d'=>$d,'s'=>(float)$r['s'],'c'=>(int)$r['c']];
                if ((float)$r['s'] > $max_val) $max_val = (float)$r['s'];
            }
            foreach ($days_data as $day) {
                $label = date('d\\.m', strtotime($day['d']));
                $bars  = $max_val > 0 ? round($day['s'] / $max_val * 8) : 0;
                $bar   = str_repeat('█', $bars) . str_repeat('░', 8 - $bars);
                $msg  .= "`$label` $bar ".fmt($day['s'])."\n";
            }
            send($chat_id, $msg);
            break;

        // ── BUYURTMALAR ──
        case '/buyurtmalar':
        case '/yangi':
        case '📋':
            $q = mysqli_query($conn,
                "SELECT s.id, s.total_price, s.note, s.sale_type, u.name as uname
                 FROM sales s LEFT JOIN users u ON s.user_id=u.id
                 WHERE s.status='yangi' AND s.source='mijoz'
                 ORDER BY s.id DESC LIMIT 5"
            );
            if (!$q || mysqli_num_rows($q) == 0) {
                send($chat_id, "✅ Yangi buyurtmalar yo'q\\.");
                break;
            }
            $msg = "📋 *Yangi buyurtmalar:*\n\n";
            while ($r = mysqli_fetch_assoc($q)) {
                $ico  = $r['sale_type'] === 'yetkazish' ? '🚚' : '🏪';
                $msg .= "$ico Buyurtma *\\#".(int)$r['id']."*\n";
                $msg .= "👤 ".esc($r['uname'] ?? 'Noma\'lum')."\n";
                $msg .= "💰 ".fmt($r['total_price'])." UZS\n";
                $msg .= "📝 ".esc($r['note'] ?? '—')."\n\n";
            }
            send($chat_id, $msg);
            break;

        // ── YORDAM ──
        case '/yordam':
        case '/help':
        case '❓':
            $msg  = "📋 *Buyruqlar ro'yxati:*\n\n";
            $msg .= "📊 /bugun — Bugungi tushum\n";
            $msg .= "📦 /ombor — Tugayotgan tovarlar\n";
            $msg .= "🏆 /top — Top 5 tovar \\(bu oy\\)\n";
            $msg .= "📅 /hafta — 7 kunlik savdo\n";
            $msg .= "📋 /buyurtmalar — Yangi buyurtmalar\n\n";
            $msg .= "💬 Yoki erkin savol yozing — AI javob beradi\\!";
            send($chat_id, $msg, main_keyboard());
            break;

        // ── AI JAVOB (boshqa matnlar) ──
        default:
            $qt   = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(SUM(total_price),0) as j, COUNT(*) as c FROM sales WHERE DATE(sale_date)='$today'"
            ));
            $qlow = mysqli_query($conn, "SELECT name FROM products WHERE quantity<=5 LIMIT 5");
            $low  = [];
            if ($qlow) while($r=mysqli_fetch_assoc($qlow)) $low[]=esc($r['name']);
            $qtop = mysqli_query($conn,
                "SELECT p.name FROM sale_items si JOIN products p ON si.product_id=p.id
                 GROUP BY si.product_id ORDER BY SUM(si.quantity) DESC LIMIT 3"
            );
            $tops = [];
            if ($qtop) while($r=mysqli_fetch_assoc($qtop)) $tops[]=esc($r['name']);
            $st  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT store_name FROM settings WHERE id=1"));
            $store = $st['store_name'] ?? "Do'kon";

            $context = "$store: bugun ".fmt($qt['j'])." UZS, ".(int)$qt['c']." chek. ".
                       "Tugayotgan: ".implode(', ',$low).". Top: ".implode(', ',$tops).".";

            $ai = gemini_ask($text, $context, $store);
            send($chat_id, "🤖 ".esc_md($ai));
            break;
    }
}

// ═══════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════

function send($chat_id, $text, $keyboard=null) {
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'MarkdownV2',
    ];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);

    $ch = curl_init(TG_API.'sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 8,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function tg_answer_callback($callback_id) {
    $ch = curl_init(TG_API.'answerCallbackQuery');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['callback_query_id'=>$callback_id]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function gemini_ask($question, $context, $store) {
    $prompt = "Siz '$store' do'konining AI yordamchisisiz. Telegram orqali admin bilan gaplashyapsiz. ".
              "Qisqa, aniq javob bering (2-3 jumla). Faqat o'zbek tilida. ".
              "Do'kon ma'lumotlari: $context. Savol: $question";

    $ch = curl_init(GEMINI_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]]]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 12,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $r = json_decode($res, true);
    return $r['candidates'][0]['content']['parts'][0]['text']
        ?? "Kechirasiz, hozir javob bera olmadim. /bugun /ombor /top buyruqlaridan foydalaning.";
}

function main_keyboard() {
    return [
        'keyboard' => [
            [['text'=>'📊 Bugun'], ['text'=>'📦 Ombor']],
            [['text'=>'🏆 Top'],   ['text'=>'📅 Hafta']],
            [['text'=>'📋 Buyurtmalar'], ['text'=>'❓ Yordam']],
        ],
        'resize_keyboard'   => true,
        'one_time_keyboard' => false,
    ];
}

// MarkdownV2 uchun escape
function esc($t) {
    return str_replace(
        ['_','*','[',']','(',')','~','`','>','#','+','-','=','|','{','}','.','!'],
        ['\_','\*','\[','\]','\(','\)','\\~','\`','\>','\#','\+','\-','\=','\|','\{','\}','\.','\\!'],
        (string)$t
    );
}
function esc_md($t) { return esc($t); }
function fmt($n) { return number_format((float)$n, 0, '.', ' '); }
