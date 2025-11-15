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
    $row['stock_quantity'] = isset($row['stock_quantity']) ? (int)$row['stock_quantity'] : -1;
    $total += $subtotal;
    $products[] = $row;
}

$error = "";
$coupon_code = isset($_POST['coupon_code']) ? trim(strtoupper($_POST['coupon_code'])) : '';
$discount_amount = 0;
$coupon_info = null;

// جلب سعر التوصيل من الإعدادات
$delivery_fee = (float)get_setting('delivery_fee');
if ($delivery_fee === null) $delivery_fee = 0;

// التحقق من الكوبون إذا تم إدخاله (عند الضغط على تطبيق أو إرسال النموذج)
if (!empty($coupon_code) && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    $today = date('Y-m-d');
    $coupon_stmt = $conn->prepare("
        SELECT * FROM coupons 
        WHERE code = ? 
        AND is_active = 1
        AND (start_date IS NULL OR start_date <= ?)
        AND (end_date IS NULL OR end_date >= ?)
        AND (usage_limit IS NULL OR used_count < usage_limit)
    ");
    $coupon_stmt->bind_param("sss", $coupon_code, $today, $today);
    $coupon_stmt->execute();
    $coupon_result = $coupon_stmt->get_result();
    $coupon_info = $coupon_result->fetch_assoc();
    $coupon_stmt->close();
    
    if ($coupon_info) {
        // التحقق من الحد الأدنى للطلب
        if ($coupon_info['min_amount'] > 0 && $total < $coupon_info['min_amount']) {
            $error = "الحد الأدنى لاستخدام هذا الكوبون هو " . $coupon_info['min_amount'] . " شيكل.";
            $coupon_info = null;
            $coupon_code = '';
        } else {
            // حساب الخصم على المنتجات
            if ($coupon_info['discount_value'] > 0) {
                if ($coupon_info['discount_type'] == 'percentage') {
                    $discount_amount = ($total * $coupon_info['discount_value']) / 100;
                    // تطبيق الحد الأقصى للخصم إذا كان موجوداً
                    if ($coupon_info['max_discount'] && $discount_amount > $coupon_info['max_discount']) {
                        $discount_amount = $coupon_info['max_discount'];
                    }
                } else {
                    // خصم ثابت
                    $discount_amount = $coupon_info['discount_value'];
                    // لا يمكن أن يكون الخصم أكبر من الإجمالي
                    if ($discount_amount > $total) {
                        $discount_amount = $total;
                    }
                }
            }
        }
    } else {
        $error = "كود الكوبون غير صحيح أو منتهي الصلاحية.";
        $coupon_code = '';
    }
}

// حساب سعر التوصيل (مجاني إذا كان الكوبون يوفر توصيل مجاني)
$final_delivery_fee = $delivery_fee;
if ($coupon_info && !empty($coupon_info['free_delivery'])) {
    $final_delivery_fee = 0;
}

// حساب الإجمالي النهائي: المنتجات - الخصم + التوصيل
$final_total = $total - $discount_amount + $final_delivery_fee;
if ($final_total < 0) $final_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $customer_name    = trim($_POST['name']);
    $customer_phone   = trim($_POST['phone']);
    $customer_address = trim($_POST['address']);
    $note             = trim($_POST['note']);

    if ($customer_name === '' || $customer_phone === '' || $customer_address === '') {
        $error = "الرجاء تعبئة جميع الحقول المطلوبة (الاسم، الهاتف، العنوان).";
    } else {
        // التحقق من الكمية المتاحة قبل إتمام الطلب
        foreach ($products as $p) {
            $stock = isset($p['stock_quantity']) ? (int)$p['stock_quantity'] : -1;
            // -1 = غير محدود، 0 = نفذت، > 0 = كمية محددة
            if ($stock == 0) {
                $error = "عذراً، نفذت كمية " . htmlspecialchars($p['name']) . ".";
                break;
            }
            if ($stock > 0 && $p['qty'] > $stock) {
                $error = "الكمية المطلوبة من " . htmlspecialchars($p['name']) . " (" . $p['qty'] . ") تتجاوز الكمية المتاحة (" . $stock . ").";
                break;
            }
        }

        if ($error === '') {
            // كود الطلب
            $order_code = "ALT-" . date("ymdHis");

            $conn->begin_transaction();

            try {
                // حفظ الطلب مع الكوبون وسعر التوصيل
                $stmt = $conn->prepare("
                    INSERT INTO orders (order_code, customer_name, customer_phone, customer_address, note, coupon_code, discount_amount, total_amount, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'جديد')
                ");
                $coupon_code_for_db = !empty($coupon_code) && $coupon_info ? $coupon_code : NULL;
                // نضيف سعر التوصيل للخصم المحفوظ (للتتبع)
                $total_discount = $discount_amount + ($delivery_fee - $final_delivery_fee);
                $stmt->bind_param("ssssssdd", $order_code, $customer_name, $customer_phone, $customer_address, $note, $coupon_code_for_db, $total_discount, $final_total);
                $stmt->execute();
                $order_id = $stmt->insert_id;
                $stmt->close();

                // حفظ عناصر الطلب وتحديث المخزون
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
            
            // لا نخصم الكمية هنا - سيتم الخصم فقط عند تغيير الحالة إلى "مكتمل" من لوحة الإدارة
            // الكمية يتم التحقق منها فقط عند إنشاء الطلب
            
            // تحديث عدد استخدامات الكوبون
            if ($coupon_info) {
                $update_coupon_stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
                $update_coupon_stmt->bind_param("i", $coupon_info['id']);
                $update_coupon_stmt->execute();
                $update_coupon_stmt->close();
            }

                $conn->commit();

            } catch (Exception $e) {
                $conn->rollback();
                $error = "حدث خطأ أثناء حفظ الطلب: " . $e->getMessage();
            }

            if ($error === '') {
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

                if ($discount_amount > 0) {
                    $message .= "%0Aالخصم: -" . $discount_amount . " شيكل";
                    if ($coupon_code) {
                        $message .= " (كوبون: " . urlencode($coupon_code) . ")";
                    }
                }
                if ($final_delivery_fee > 0) {
                    $message .= "%0Aسعر التوصيل: " . $final_delivery_fee . " شيكل";
                } elseif ($coupon_info && !empty($coupon_info['free_delivery'])) {
                    $message .= "%0Aالتوصيل: مجاني (كوبون: " . urlencode($coupon_code) . ")";
                } elseif ($delivery_fee > 0) {
                    $message .= "%0Aسعر التوصيل: " . $delivery_fee . " شيكل";
                }
                $message .= "%0Aالإجمالي الكلي: " . $final_total . " شيكل";

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

                    <?php if ($discount_amount > 0 || $final_delivery_fee > 0 || ($coupon_info && !empty($coupon_info['free_delivery']))): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>الإجمالي الفرعي:</span>
                            <span><?php echo number_format($total, 2); ?> شيكل</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($discount_amount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>الخصم <?php if ($coupon_code): ?>(<?php echo htmlspecialchars($coupon_code); ?>)<?php endif; ?>:</span>
                            <span>-<?php echo number_format($discount_amount, 2); ?> شيكل</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($final_delivery_fee > 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>سعر التوصيل:</span>
                            <span><?php echo number_format($final_delivery_fee, 2); ?> شيكل</span>
                        </div>
                    <?php elseif ($coupon_info && !empty($coupon_info['free_delivery'])): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>التوصيل (<?php echo htmlspecialchars($coupon_code); ?>):</span>
                            <span>مجاني</span>
                        </div>
                    <?php elseif ($delivery_fee > 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>سعر التوصيل:</span>
                            <span><?php echo number_format($delivery_fee, 2); ?> شيكل</span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between border-top pt-2">
                        <strong>الإجمالي الكلي:</strong>
                        <strong class="text-success"><?php echo number_format($final_total, 2); ?> شيكل</strong>
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
                        <div class="alert alert-danger alert-dismissible fade show py-2">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                            <label class="form-label">كود الكوبون (اختياري)</label>
                            <div class="input-group">
                                <input type="text" name="coupon_code" class="form-control" 
                                       placeholder="أدخل كود الكوبون" 
                                       value="<?php echo htmlspecialchars($coupon_code); ?>"
                                       style="text-transform:uppercase;">
                                <button type="submit" name="apply_coupon" class="btn btn-outline-primary" formnovalidate>
                                    تطبيق
                                </button>
                            </div>
                            <?php if ($coupon_info): ?>
                                <small class="text-success d-block mt-1">
                                    ✓ تم تطبيق الكوبون!
                                    <?php if ($discount_amount > 0): ?>
                                        خصم <?php echo number_format($discount_amount, 2); ?> شيكل
                                    <?php endif; ?>
                                    <?php if (!empty($coupon_info['free_delivery'])): ?>
                                        <?php if ($discount_amount > 0): ?> + <?php endif; ?>
                                        توصيل مجاني
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
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
