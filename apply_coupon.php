<?php
// apply_coupon.php
session_start();
require __DIR__ . '/db.php';

// تأكد طلب POST ومن وجود use_coupon_id و user
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['use_coupon_id']) || empty($_SESSION['user_id'])) {
    header('Location: settings.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$ucid    = (int) $_POST['use_coupon_id'];

// أولاً: تأكد وجود الجدول user_coupons لتفادي الأخطاء
$chk = $conn->query("SHOW TABLES LIKE 'user_coupons'");
if (!$chk || $chk->num_rows === 0) {
    $_SESSION['coupon_flash'] = 'نظام الكوبونات مؤقتاً غير متاح.';
    header('Location: settings.php');
    exit;
}

// جلب تفاصيل الربط والكوبون
$stmt = $conn->prepare("
    SELECT uc.id AS uc_id, uc.status, c.id AS coupon_id, c.code, c.discount_type, c.discount_value, c.min_amount, c.end_date
    FROM user_coupons uc
    JOIN coupons c ON uc.coupon_id = c.id
    WHERE uc.id = ? AND uc.user_id = ? LIMIT 1
");
$stmt->bind_param("ii", $ucid, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    $_SESSION['coupon_flash'] = 'هذا الكوبون غير موجود أو لا يخص حسابك.';
    header('Location: settings.php');
    exit;
}

$row = $res->fetch_assoc();

// حالة الربط
if ($row['status'] !== 'available') {
    $_SESSION['coupon_flash'] = 'هذا الكوبون غير متاح للاستخدام.';
    header('Location: settings.php');
    exit;
}

// تحقق صلاحية تاريخ انتهاء الكوبون (لو موجود)
$today = date('Y-m-d');
if (!empty($row['end_date']) && $row['end_date'] < $today) {
    $_SESSION['coupon_flash'] = 'انتهت صلاحية الكوبون.';
    header('Location: settings.php');
    exit;
}

// جلب مجموع السلة من الجلسة — عدّل هذا الجزء حسب كيف تخزن السلة في مشروعك.
// افتراض شائع: $_SESSION['cart'] = [ ['product_id'=>1,'qty'=>2,'price'=>10], ... ]
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    $_SESSION['coupon_flash'] = 'سلة الشراء فارغة. أضف منتجات ثم حاول استخدام الكوبون.';
    header('Location: index.php'); // راجع المستخدم للصفحة الرئيسية
    exit;
}

// حساب إجمالي السلة
$total = 0.0;
foreach ($cart as $item) {
    $price = isset($item['price']) ? (float)$item['price'] : 0;
    $qty   = isset($item['qty']) ? (int)$item['qty'] : 1;
    $total += $price * $qty;
}

// تحقق من الحد الأدنى للكوبون (min_amount). افتراض أن الحقل اسمه min_amount في جدول coupons.
$min = (float) ($row['min_amount'] ?? 0);
if ($total < $min) {
    $_SESSION['coupon_flash'] = "إجمالي السلة ({$total}) أقل من الحد الأدنى المطلوب: {$min}. أضف منتجات أخرى.";
    header('Location: settings.php');
    exit;
}

// إن اجتازت جميع الفحوص: علم الكوبون كمستخدم (used) وحدّث used_count
$upd = $conn->prepare("UPDATE user_coupons SET status = 'used', used_at = NOW() WHERE id = ? LIMIT 1");
$upd->bind_param("i", $ucid);
$ok = $upd->execute();

if ($ok) {
    // زيادة عداد الاستخدام في coupons (لو موجود الحقل used_count)
    $inc = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ? LIMIT 1");
    $cid = (int)$row['coupon_id'];
    $inc->bind_param("i", $cid);
    $inc->execute();
    $inc->close();

    // ضع الكوبون في الجلسة لتطبيقه عند عرض السلة / اتمام الطلب
    $_SESSION['applied_coupon'] = [
        'uc_id' => $row['uc_id'],
        'coupon_id' => $cid,
        'code' => $row['code'],
        'discount_type' => $row['discount_type'], // 'percentage' أو 'fixed' حسب جدولك
        'discount_value' => $row['discount_value'],
        'min_amount' => $min,
    ];

    $_SESSION['coupon_flash'] = 'تم تطبيق الكوبون على سلتك. تابع الشراء.';
    // وأخيراً نعيد التوجيه إلى index.php حسب طلبك
    header('Location: index.php');
    exit;
} else {
    $_SESSION['coupon_flash'] = 'فشل تفعيل الكوبون، حاول لاحقاً.';
    header('Location: settings.php');
    exit;
}
