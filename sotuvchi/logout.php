<?php
session_start();
session_unset();
session_destroy();

// JavaScript orqali loyihaning eng asosiy sahifasiga qaytaramiz
echo "<script>window.location.href = '../';</script>";
exit;
?>