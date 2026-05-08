<?php
/**
 * Telegram Webhook o'rnatish
 * Bir marta tashrif buyuring: https://ali-eleven.up.railway.app/telegram/set_webhook.php
 */

define('BOT_TOKEN', '8694101598:AAE54kDSYsl4NaTxNA2bw73-4sN9Jq4xVmE');
define('WEBHOOK_URL', 'https://ali-eleven.up.railway.app/telegram/webhook.php');

$action = $_GET['action'] ?? 'set';

if ($action === 'delete') {
    $url = 'https://api.telegram.org/bot'.BOT_TOKEN.'/deleteWebhook';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    echo '<h2>Webhook o\'chirildi</h2>';
    echo '<pre>'.json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).'</pre>';
    echo '<a href="set_webhook.php">Qayta o\'rnatish</a>';
    exit;
}

if ($action === 'info') {
    $url = 'https://api.telegram.org/bot'.BOT_TOKEN.'/getWebhookInfo';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    echo '<h2>Webhook holati</h2>';
    echo '<pre>'.json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).'</pre>';
    echo '<a href="set_webhook.php">O\'rnatish</a> | <a href="set_webhook.php?action=delete">O\'chirish</a>';
    exit;
}

// Webhook o'rnatish
$url = 'https://api.telegram.org/bot'.BOT_TOKEN.'/setWebhook';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['url' => WEBHOOK_URL]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

$ok = $res['ok'] ?? false;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Webhook Setup</title>
<style>
  body { font-family:Arial,sans-serif; max-width:600px; margin:40px auto; padding:20px; }
  .ok  { background:#d1fae5; border:1px solid #10b981; padding:16px; border-radius:8px; }
  .err { background:#fee2e2; border:1px solid #ef4444; padding:16px; border-radius:8px; }
  pre  { background:#f3f4f6; padding:12px; border-radius:6px; overflow:auto; }
  a    { color:#3b82f6; margin-right:12px; }
</style>
</head>
<body>
<h2>🤖 @AI_Menejer_bot Webhook</h2>
<?php if ($ok): ?>
<div class="ok">
  ✅ <strong>Webhook muvaffaqiyatli o'rnatildi!</strong><br><br>
  Bot URL: <code><?= WEBHOOK_URL ?></code><br><br>
  Endi Telegram'da <a href="https://t.me/AI_Menejer_bot" target="_blank">@AI_Menejer_bot</a> ga /start yuboring.
</div>
<?php else: ?>
<div class="err">
  ❌ <strong>Xatolik yuz berdi!</strong>
  <pre><?= json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?></pre>
</div>
<?php endif; ?>

<br>
<pre><?= json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?></pre>

<p>
  <a href="set_webhook.php?action=info">📋 Webhook holati</a>
  <a href="set_webhook.php?action=delete">🗑 O'chirish</a>
  <a href="set_webhook.php">🔄 Qayta o'rnatish</a>
</p>
</body>
</html>
