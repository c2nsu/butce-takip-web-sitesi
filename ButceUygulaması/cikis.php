<?php
// Oturumu başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Tüm oturum değişkenlerini temizle
$_SESSION = array();

// Oturumu sonlandır
session_destroy();

// Ana sayfaya (ilksayfa.html) yönlendir
header("Location: ilksayfa.html");
exit;
?>