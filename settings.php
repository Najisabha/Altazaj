<?php
session_start();
require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: admin/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = "";

/* جلب بيانات المستخدم */
$stmt = $conn->prepare("
    SELECT first_name, last_name, email, phone, birth_date
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* حفظ التعديلات */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    $_SESSION['user_name'] = $first . " " . $last;
    $message = "تم تحديث بياناتك بنجاح ✅";
}

/* جلب كوبونات المستخدم */
$sql = "
    SELECT 
        c.code,
        c.discount_value,
        c.discount_type,
        c.end_date,
        uc.status
    FROM user_coupons uc
    INNER JOIN coupons c ON uc.coupon_id = c.id
    WHERE uc.user_id = ?
";
$coupons = $conn->prepare($sql);
$coupons->bind_param("i", $user_id);
$coupons->execute();
$result    = $coupons->get_result();
$myCoupons = $result->fetch_all(MYSQLI_ASSOC);
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
<div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<form method="post">
<div class="row g-3">

<div class="col-md-6">
<label>الاسم الأول</label>
<input type="text" name="first_name" class="form-control" value="<?= $user['first_name'] ?>" required>
</div>

<div class="col-md-6">
<label>الاسم الثاني</label>
<input type="text" name="last_name" class="form-control" value="<?= $user['last_name'] ?>" required>
</div>

<div class="col-md-6">
<label>البريد الإلكتروني (ثابت)</label>
<input type="email" class="form-control" value="<?= $user['email'] ?>" disabled>
</div>

<div class="col-md-6">
<label>رقم الجوال</label>
<input type="text" name="phone" class="form-control" value="<?= $user['phone'] ?>" required>
</div>

<div class="col-md-6">
<label>تاريخ الميلاد</label>
<input type="date" name="birth_date" class="form-control" value="<?= $user['birth_date'] ?>">
</div>

<div class="col-12">
<button class="btn btn-success">حفظ التعديلات</button>
<a href="index.php" class="btn btn-secondary">العودة</a>
</div>

</div>
</form>

<hr>

<h5>🎁 الكوبونات الخاصة بك</h5>

<?php if (!$myCoupons): ?>
<p class="text-muted">لا يوجد كوبونات حتى الآن</p>
<?php else: ?>
<table class="table table-bordered">
<tr>
<th>الكود</th><th>قيمة الخصم</th><th>النوع</th><th>الحالة</th><th>الانتهاء</th>
</tr>
<?php foreach ($myCoupons as $c): ?>
<tr>
<td><?= $c['code'] ?></td>
<td><?= $c['discount_value'] ?></td>
<td><?= $c['discount_type'] ?></td>
<td><?= $c['status'] ?></td>
<td><?= $c['end_date'] ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

</div>
</div>
</div>

</body>
</html>
