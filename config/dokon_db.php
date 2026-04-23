<?php
$host = "gateway01.eu-central-1.prod.aws.tidbcloud.com";
$port = 4000;
$user = "sJwLacNCvu5iX2E.root";
$pass = "srplSgvlq1HW3I8e";
$db   = "dokon_db";

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);

if (!$conn) {
    die("Bazaga ulanishda xato: " . mysqli_connect_error());
}
?>
