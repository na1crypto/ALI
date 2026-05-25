<?php
// Xatolarni yashirish
mysqli_report(MYSQLI_REPORT_OFF);

// Environment variable dan olish (Render/Railway), yo'q bo'lsa default
$host = getenv('DB_HOST') ?: "gateway01.eu-central-1.prod.aws.tidbcloud.com";
$port = (int)(getenv('DB_PORT') ?: 4000);
$user = getenv('DB_USER') ?: "sJwLacNCvu5iX2E.root";
$pass = getenv('DB_PASS') ?: "srplSgvlq1HW3I8e";
$db   = getenv('DB_NAME') ?: "dokon_db";

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
$connected = @mysqli_real_connect(
    $conn, $host, $user, $pass, $db, $port,
    NULL,
    MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
);

if (!$connected) {
    http_response_code(503);
    die(json_encode([
        'error' => 'DB ulanishda xato',
        'msg'   => mysqli_connect_error()
    ]));
}

mysqli_set_charset($conn, 'utf8mb4');
?>
