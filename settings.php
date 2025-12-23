<?php
session_start();
require __DIR__ . '/db.php';

// منع المستخدم غير المسجل
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// تحويل الأدمن للوحة التحكم
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: admin/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = "";
$coupon_message = "";

/*=========================================
    جلب بيانات المستخدم
=========================================*/
$stmt = $conn->prepare("
    SELECT first_name, last_name, email, phone, birth_date
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/*=========================================
    حفظ التعديلات
=========================================*/
if (isset($_POST['save_info'])) {

    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $birth = $_POST['birth_date'] ?? null;

    $stmt = $conn->prepare("
        UPDATE users
        SET first_name = ?, last_name = ?, phone = ?, birth_date = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $first, $last, $phone, $birth, $user_id);
    $stmt->execute();

    $_SESSION['user_name'] = "$first $last";
    $message = "تم تحديث بياناتك بنجاح ✅";
}

/*=========================================================
    فحص وجود جدول user_coupons
=========================================================*/
function userCouponsTableExists($conn)
{
    $q = $conn->query("SHOW TABLES LIKE 'user_coupons'");
    return ($q && $q->num_rows > 0);
}

/*=========================================================
    إضافة كوبون
=========================================================*/
if (isset($_POST['add_coupon_code']) && trim($_POST['add_coupon_code']) !== '') {

    if (!userCouponsTableExists($conn)) {
        $coupon_message = "نظام الكوبونات غير متاح حالياً.";
    } else {
        $code = trim($_POST['add_coupon_code']);

        $stmt = $conn->prepare("SELECT id, end_date, usage_limit, used_count FROM coupons WHERE code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows === 0) {
            $coupon_message = "الكوبون غير موجود ❌";
        } else {
            $coupon = $res->fetch_assoc();
            $coupon_id = (int)$coupon['id'];

            // تحقق انتهاء الصلاحية
            $today = date("Y-m-d");
            if (!empty($coupon['end_date']) && $coupon['end_date'] < $today) {
                $coupon_message = "انتهت صلاحية هذا الكوبون ❌";
            } else {
                // تحقق عدم إضافة الكوبون سابقاً
                $chk = $conn->prepare("SELECT id FROM user_coupons WHERE user_id = ? AND coupon_id = ? LIMIT 1");
                $chk->bind_param("ii", $user_id, $coupon_id);
                $chk->execute();
                $cc = $chk->get_result();

                if ($cc->num_rows > 0) {
                    $coupon_message = "لقد استخدمت هذا الكوبون مسبقاً ❗";
                } else {
                    $ins = $conn->prepare("
                        INSERT INTO user_coupons (user_id, coupon_id, status, created_at)
                        VALUES (?, ?, 'available', NOW())
                    ");
                    $ins->bind_param("ii", $user_id, $coupon_id);

                    if ($ins->execute()) {
                        $coupon_message = "تم إضافة الكوبون بنجاح 🎉";
                    } else {
                        $coupon_message = "حدث خطأ، حاول مرة أخرى ❌";
                    }
                }
            }
        }
    }
}

/*=========================================================
    استخدام كوبون (ملاحظة: هذه النسخة تميّز الكوبون كـ used فور التطبيق.
    إن أردت التأخير حتى إتمام الطلب أخبرني لأعدلها)
=========================================================*/
if (isset($_POST['use_coupon_id'])) {

    $ucid = (int)$_POST['use_coupon_id'];

    if (!userCouponsTableExists($conn)) {
        $coupon_message = "نظام الكوبونات غير متاح حالياً.";
    } else {

        $stmt = $conn->prepare("
            SELECT uc.id, uc.coupon_id, uc.status, c.end_date, c.min_amount, c.discount_type, c.discount_value
            FROM user_coupons uc
            JOIN coupons c ON uc.coupon_id = c.id
            WHERE uc.id = ? AND uc.user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $ucid, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows === 0) {
            $coupon_message = "هذا الكوبون غير موجود ❌";
        } else {
            $row = $res->fetch_assoc();

            if ($row['status'] !== "available") {
                $coupon_message = "هذا الكوبون غير متاح للاستخدام ❗";
            } else {
                $today = date("Y-m-d");
                if (!empty($row['end_date']) && $row['end_date'] < $today) {
                    $coupon_message = "انتهت صلاحية الكوبون ❌";
                } else {
                    // تحقق من السلة والحد الأدنى
                    $cart = $_SESSION['cart'] ?? [];
                    if (empty($cart)) {
                        $coupon_message = "سلة الشراء فارغة. أضف منتجات ثم حاول استخدام الكوبون.";
                    } else {
                        $total = 0.0;
                        foreach ($cart as $item) {
                            $price = isset($item['price']) ? (float)$item['price'] : 0;
                            $qty   = isset($item['qty']) ? (int)$item['qty'] : 1;
                            $total += $price * $qty;
                        }

                        $min = (float)($row['min_amount'] ?? 0);
                        if ($total < $min) {
                            $coupon_message = "إجمالي السلة أقل من الحد الأدنى المطلوب: {$min}";
                        } else {
                            // علم الكوبون كمستخدم وحدث used_count
                            $upd = $conn->prepare("UPDATE user_coupons SET status = 'used', used_at = NOW() WHERE id = ? LIMIT 1");
                            $upd->bind_param("i", $ucid);
                            if ($upd->execute()) {
                                $inc = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ? LIMIT 1");
                                $cid = (int)$row['coupon_id'];
                                $inc->bind_param("i", $cid);
                                $inc->execute();
                                $inc->close();

                                // خزّن الكوبون في الجلسة ليُطبّق في السلة/الدفع
                                $_SESSION['applied_coupon'] = [
                                    'uc_id' => $row['id'],
                                    'coupon_id' => $cid,
                                    'code' => $row['code'] ?? '',
                                    'discount_type' => $row['discount_type'] ?? 'fixed',
                                    'discount_value' => $row['discount_value'] ?? 0,
                                    'min_amount' => $min,
                                ];

                                $coupon_message = "تم تطبيق الكوبون على سلتك 🎉";
                                // تحويل المستخدم إلى index.php كما طلبت
                                $_SESSION['coupon_flash'] = $coupon_message;
                                header('Location: index.php');
                                exit;
                            } else {
                                $coupon_message = "فشل تفعيل الكوبون، حاول لاحقاً.";
                            }
                        }
                    }
                }
            }
        }
    }
}

/*=========================================================
    جلب كوبونات المستخدم لعرضها
=========================================================*/
$myCoupons = [];
if (userCouponsTableExists($conn)) {
    $q = $conn->prepare("
        SELECT uc.id AS uc_id, c.id AS coupon_real_id, c.code, c.discount_value, c.discount_type, c.end_date, uc.status, c.min_amount
        FROM user_coupons uc
        JOIN coupons c ON uc.coupon_id = c.id
        WHERE uc.user_id = ?
        ORDER BY uc.created_at DESC
    ");
    $q->bind_param("i", $user_id);
    $q->execute();
    $myCoupons = $q->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إعدادات الحساب</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="card shadow">
<div class="card-header bg-success text-white">إعدادات الحساب</div>
<div class="card-body">

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="save_info" value="1">

<div class="row g-3">

<div class="col-md-6">
<label>الاسم الأول</label>
<input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
</div>

<div class="col-md-6">
<label>الاسم الثاني</label>
<input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
</div>

<div class="col-md-6">
<label>البريد الإلكتروني (ثابت)</label>
<input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
</div>

<div class="col-md-6">
<label>رقم الجوال</label>
<input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
</div>

<div class="col-md-6">
<label>تاريخ الميلاد</label>
<input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($user['birth_date']) ?>">
</div>

<div class="col-12">
<button class="btn btn-success">حفظ التعديلات</button>
<a href="index.php" class="btn btn-secondary">العودة</a>
</div>
</div>
</form>

<hr>

<h5>🎁 الكوبونات الخاصة بك</h5>

<?php if ($coupon_message): ?>
    <div class="alert alert-info"><?= htmlspecialchars($coupon_message) ?></div>
<?php endif; ?>

<!-- إضافة كوبون -->
<form method="post" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="add_coupon_code" class="form-control" placeholder="أدخل كود كوبون">
    </div>
    <div class="col-md-3">
        <button class="btn btn-success">إضافة كوبون</button>
    </div>
</form>

<?php if (empty($myCoupons)): ?>
<p class="text-muted">لا يوجد كوبونات حالياً</p>
<?php else: ?>

<table class="table table-bordered align-middle">
<thead class="table-light">
<tr>
<th>الكود</th>
<th>قيمة الخصم</th>
<th>النوع</th>
<th>الحالة</th>
<th>الانتهاء</th>
<th>الحد الأدنى</th>
<th>إجراءات</th>
</tr>
</thead>

<tbody>
<?php foreach ($myCoupons as $c): ?>
<tr>
<td><?= htmlspecialchars($c['code']) ?></td>
<td><?= htmlspecialchars($c['discount_value']) ?></td>
<td><?= htmlspecialchars($c['discount_type']) ?></td>

<td>
<?php
if ($c['status'] === 'available') echo '<span class="badge bg-warning text-dark">متاح</span>';
elseif ($c['status'] === 'used') echo '<span class="badge bg-secondary">مستخدم</span>';
else echo '<span class="badge bg-danger">منتهي</span>';
?>
</td>

<td><?= htmlspecialchars($c['end_date'] ?: '—') ?></td>
<td><?= htmlspecialchars($c['min_amount'] ?: '—') ?></td>

<td>
<?php if ($c['status'] === 'available'): ?>
    <form method="post" action="index.php" style="display:inline-block;">
        <input type="hidden" name="use_coupon_id" value="<?= (int)$c['uc_id'] ?>">
        <button type="submit" class="btn btn-primary btn-sm">استخدام</button>
    </form>
<?php else: ?>
    <button class="btn btn-secondary btn-sm" disabled>—</button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php endif; ?>

</div>
</div>
</div>

</body>
</html>
