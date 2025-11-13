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

// تأكد أن السلة موجودة
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// أضف الكمية
if (!isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id] = 0;
}

$_SESSION['cart'][$product_id] += $qty;

header("Location: cart.php");
exit;
