<?php
session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // تحديث الكميات
    if (isset($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $value) {
            $new_qty = floatval($value);

            if ($new_qty <= 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                $_SESSION['cart'][$id] = $new_qty;
            }
        }
    }

    // إزالة منتجات
    if (isset($_POST['remove'])) {
        foreach ($_POST['remove'] as $id) {
            unset($_SESSION['cart'][$id]);
        }
    }
}

header("Location: cart.php");
exit;
