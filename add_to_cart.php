<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$product_id = (int)$_POST['product_id'];
$qty        = isset($_POST['qty']) ? floatval($_POST['qty']) : 1;

if ($qty <= 0) {
    $qty = 1;
}

// جلب بيانات المنتج للتحقق من الكمية المتاحة
$stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['error'] = "المنتج غير موجود.";
    header("Location: index.php");
    exit;
}

$stock_quantity = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : -1;

// تأكد أن السلة موجودة
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// التحقق من نفاد الكمية
if ($stock_quantity == 0) {
    $_SESSION['error'] = "عذراً، نفذت كمية هذا المنتج.";
    header("Location: index.php");
    exit;
}

// حساب الكمية الحالية في السلة
$current_cart_qty = isset($_SESSION['cart'][$product_id]) ? floatval($_SESSION['cart'][$product_id]) : 0;
$new_total_qty = $current_cart_qty + $qty;

// التحقق من الكمية المتاحة (إذا كان stock_quantity > 0 يعني نتحقق من الكمية)
// -1 يعني غير محدود، 0 يعني نفذت، > 0 يعني كمية محددة
if ($stock_quantity > 0) {
    if ($new_total_qty > $stock_quantity) {
        $_SESSION['error'] = "الكمية المطلوبة (" . $new_total_qty . ") تتجاوز الكمية المتاحة (" . $stock_quantity . ").";
        header("Location: cart.php");
        exit;
    }
}

// أضف الكمية
$_SESSION['cart'][$product_id] = $new_total_qty;

header("Location: cart.php");
exit;
