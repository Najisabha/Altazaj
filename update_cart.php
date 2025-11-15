<?php
session_start();
require 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // تحديث الكميات
    if (isset($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $value) {
            $new_qty = floatval($value);
            $product_id = (int)$id;

            if ($new_qty <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                // التحقق من الكمية المتاحة
                $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                $stmt->close();

                if ($product) {
                    $stock_quantity = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : -1;
                    
                    // -1 = غير محدود، 0 = نفذت، > 0 = كمية محددة
                    if ($stock_quantity == 0) {
                        $_SESSION['error'] = "عذراً، نفذت كمية هذا المنتج.";
                        header("Location: cart.php");
                        exit;
                    }
                    
                    // إذا كان stock_quantity > 0 نتحقق من الكمية
                    if ($stock_quantity > 0 && $new_qty > $stock_quantity) {
                        $_SESSION['error'] = "الكمية المطلوبة (" . $new_qty . ") تتجاوز الكمية المتاحة (" . $stock_quantity . ") للمنتج.";
                        header("Location: cart.php");
                        exit;
                    }
                }

                $_SESSION['cart'][$product_id] = $new_qty;
            }
        }
    }

    // إزالة منتجات
    if (isset($_POST['remove'])) {
        foreach ($_POST['remove'] as $id) {
            unset($_SESSION['cart'][(int)$id]);
        }
    }
}

header("Location: cart.php");
exit;
