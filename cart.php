<?php
session_start();
require 'db.php';

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$products = [];
$total = 0;

if (!empty($cart)) {
    $ids = implode(',', array_keys($cart));
    $sql = "SELECT * FROM products WHERE id IN ($ids)";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $qty = $cart[$row['id']];
        $subtotal = $qty * $row['price'];
        $row['qty'] = $qty;
        $row['subtotal'] = $subtotal;
        $total += $subtotal;
        $products[] = $row;
    }
}

$cart_count = array_sum($cart);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سلة المشتريات - الطازج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            الطازج <span class="text-success">للدواجن واللحوم</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">العودة للمتجر</a>
                </li>
                <li class="nav-item ms-2">
                    <span class="btn btn-success position-relative">
                        السلة 🛒
                        <?php if ($cart_count > 0): ?>
                            <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <div class="row">
        <div class="col-12">
            <h2 class="h4 mb-3">سلة المشتريات</h2>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-warning">
            السلة فارغة حاليًا. يمكنك تصفح المنتجات من <a href="index.php" class="alert-link">الصفحة الرئيسية</a>.
        </div>
    <?php else: ?>
        <form method="POST" action="update_cart.php">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>المنتج</th>
                                    <th>السعر (شيكل)</th>
                                    <th>الكمية</th>
                                    <th>الإجمالي الفرعي</th>
                                    <th class="text-center">حذف</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['name']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($p['unit']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo $p['price']; ?></td>
                                    <td style="max-width: 90px;">
                                        <input type="number"
                                               name="qty[<?php echo $p['id']; ?>]"
                                               value="<?php echo $p['qty']; ?>"
                                               min="1"
                                               class="form-control form-control-sm text-center">
                                    </td>
                                    <td><?php echo $p['subtotal']; ?> شيكل</td>
                                    <td class="text-center">
                                        <input type="checkbox"
                                               name="remove[]"
                                               value="<?php echo $p['id']; ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">الإجمالي الكلي</th>
                                    <th colspan="2"><?php echo $total; ?> شيكل</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <div>
                            <button type="submit" class="btn btn-outline-secondary">
                                تحديث السلة
                            </button>
                        </div>
                        <div>
                            <a href="checkout.php" class="btn btn-success">
                                إتمام الطلب ✅
                            </a>
                        </div>
                    </div>

                    <p class="small text-muted mt-3 mb-0">
                        يمكنك تعديل الكميات مباشرة من الجدول، وأيضًا تحديد المنتجات التي تريد حذفها.
                    </p>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
