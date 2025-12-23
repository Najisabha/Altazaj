<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function get_setting($key) {
    global $conn;
    $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $stmt->bind_result($value);
    if ($stmt->fetch()) {
        $stmt->close();
        return $value;
    }
    $stmt->close();
    return null;
}
function set_setting($key, $value) {
    global $conn;
    $stmt = $conn->prepare("REPLACE INTO settings (`key`, `value`) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    $stmt->close();
}
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
function format_price($amount) {
    return number_format($amount, 2) . " ر.س";
}
function give_coupon_to_user(PDO $conn, int $user_id, int $coupon_id) {
    // لا تعطي المستخدم نفس الكوبون أكثر من مرة
    $check = $conn->prepare("SELECT id FROM user_coupons WHERE user_id = ? AND coupon_id = ?");
    $check->execute([$user_id, $coupon_id]);
    if ($check->fetch()) {
        return; // موجود مسبقًا
    }

    $stmt = $conn->prepare("
        INSERT INTO user_coupons (user_id, coupon_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$user_id, $coupon_id]);
}

?>