<?php
include __DIR__ . "/../config/dikon_db.php";
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /diplom_ishi/auth/login.php");
    exit;
}
