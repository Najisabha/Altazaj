<?php
session_start();
require 'db.php';
require 'functions.php';

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

// جلب تفاصيل المنتجات من قاعدة البيانات
$ids = implode(',', array_keys($cart));
$sql = "SELECT * FROM products WHERE id IN ($ids)";
$result = $conn->query($sql);

$products = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $qty = $cart[$row['id']];
    $subtotal = $qty * $row['price'];
    $row['qty'] = $qty;
    $row['subtotal'] = $subtotal;
    $total += $subtotal;
    $products[] = $row;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customer_name    = trim($_POST['name']);
    $customer_phone   = trim($_POST['phone']);
    $customer_address = trim($_POST['address']);
    $note             = trim($_POST['note']);

    if ($customer_name === '' || $customer_phone === '' || $customer_address === '') {
        $error = "الرجاء تعبئة جميع الحقول المطلوبة (الاسم، الهاتف، العنوان).";
    } else {
        // كود الطلب
        $order_code = "ALT-" . date("ymdHis");

        $conn->begin_transaction();

        try {
            // حفظ الطلب
            $stmt = $conn->prepare("
                INSERT INTO orders (order_code, customer_name, customer_phone, customer_address, note, total_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, 'جديد')
            ");
            $stmt->bind_param("sssssd", $order_code, $customer_name, $customer_phone, $customer_address, $note, $total);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            $stmt->close();

            // حفظ عناصر الطلب
            $stmt_item = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($products as $p) {
                $pid      = $p['id'];
                $qty      = $p['qty'];
                $price    = $p['price'];
                $subtotal = $p['subtotal'];

                $stmt_item->bind_param("iiidd", $order_id, $pid, $qty, $price, $subtotal);
                $stmt_item->execute();
            }
            $stmt_item->close();

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            die("حدث خطأ أثناء حفظ الطلب: " . $e->getMessage());
        }

        // تجهيز رسالة واتساب
        $message  = "طلب جديد من موقع الطازج:%0A";
        $message .= "رقم الطلب: " . urlencode($order_code) . "%0A";
        $message .= "الاسم: " . urlencode($customer_name) . "%0A";
        $message .= "الجوال: " . urlencode($customer_phone) . "%0A";
        $message .= "العنوان: " . urlencode($customer_address) . "%0A";
        if ($note !== '') {
            $message .= "ملاحظات: " . urlencode($note) . "%0A";
        }
        $message .= "%0Aالطلبات:%0A";

        foreach ($products as $p) {
            $line = "- " . $p['name'] .
                    " | الكمية: " . $p['qty'] .
                    " | السعر: " . $p['price'] .
                    " | المجموع: " . $p['subtotal'] . " شيكل";
            $message .= urlencode($line) . "%0A";
        }

        $message .= "%0Aالإجمالي الكلي: " . $total . " شيكل";

        // رقم الواتساب من الإعدادات
        $whatsapp_number = get_setting('whatsapp_number');
        if (!$whatsapp_number) {
            $whatsapp_number = "9725XXXXXXXX"; // احتياطي لو مش موجود في settings
        }

        $url = "https://wa.me/" . $whatsapp_number . "?text=" . $message;

        // تفريغ السلة
        unset($_SESSION['cart']);

        // تحويل المستخدم لواتساب
        header("Location: " . $url);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إتمام الطلب - الطازج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .order-summary-card {
            max-height: 400px;
            overflow-y: auto;
        }
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
                    <a href="cart.php" class="nav-link">العودة إلى السلة</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <div class="row g-4">
        <div class="col-12">
            <h2 class="h4 mb-3">إتمام الطلب</h2>
        </div>

        <!-- ملخص الطلب -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 order-summary-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">ملخص الطلب</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($products as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <small class="text-muted">
                                        الكمية: <?php echo $p['qty']; ?> | السعر: <?php echo $p['price']; ?> شيكل
                                    </small>
                                </div>
                                <span><?php echo $p['subtotal']; ?> شيكل</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="d-flex justify-content-between">
                        <strong>الإجمالي الكلي:</strong>
                        <strong class="text-success"><?php echo $total; ?> شيكل</strong>
                    </div>

                    <p class="small text-muted mt-3 mb-0">
                        بعد إتمام الطلب سيتم فتح تطبيق / ويب واتساب مع رسالة جاهزة تحتوي تفاصيل الطلب لإرسالها للمتجر.
                    </p>
                </div>
            </div>
        </div>

        <!-- نموذج بيانات العميل -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">بيانات التوصيل</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل</label>
                            <input type="text" name="name" class="form-control" required placeholder="اكتب اسمك الكامل">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رقم الجوال</label>
                            <input type="text" name="phone" class="form-control" required placeholder="مثال: 059xxxxxxx">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">العنوان بالتفصيل</label>
                            <textarea name="address" class="form-control" rows="3" required
                                      placeholder="المدينة، الحي، أقرب معلم، رقم المنزل أو البناية"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات إضافية (اختياري)</label>
                            <textarea name="note" class="form-control" rows="2"
                                      placeholder="مثال: موعد التوصيل المفضل، ملاحظات على تقطيع اللحوم..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            تأكيد الطلب وإرساله عبر واتساب 📲
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
