<?php
// ابدأ الجلسة (لو لم تبدأ)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تفريغ بيانات الجلسة
$_SESSION = [];

// حذف كوكي الجلسة من المتصفح
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// تدمير الجلسة على السيرفر
session_destroy();

// للتأكد أن الصفحة التالية التي يفتحها المستخدم ليست النسخة المخزنة بالمتصفح
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// أعد التوجيه لصفحة الدخول أو الرئيسية
header("Location: ../index.php");
exit;
