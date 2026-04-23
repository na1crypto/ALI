<?php
// Agar sessiya boshlanmagan bo'lsa, boshlaymiz
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Agar foydalanuvchi tizimga kirmagan bo'lsa (user_id yo'q bo'lsa)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// 2. Agar foydalanuvchi kirgan bo'lsa, lekin roli admin yoki superadmin bo'lmasa
// (Masalan, sotuvchi admin panelga kirmoqchi bo'lsa)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../sotuvchi/dashboard.php");
    exit();
}

// Agar hammasi to'g'ri bo'lsa, pastdagi kodlar ishlayveradi (hech qanday redirect bo'lmaydi)
?>