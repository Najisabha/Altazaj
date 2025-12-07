
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تغيير كلمة المرور</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white text-center">
                    <h5 class="mb-0">تغيير كلمة المرور</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2">
                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success py-2">
                            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="mb-3">
                            <label class="form-label">كلمة المرور الحالية</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            حفظ كلمة المرور الجديدة
                        </button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <a href="index.php" class="small">الرجوع للوحة التحكم</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php
session_start();
require '../db.php';

// لو ما في جلسة دخول رجّعه على صفحة تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";

// توليد توكن CSRF إذا غير موجود
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // التحقق من CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "جلسة غير صالحة، يرجى تحديث الصفحة والمحاولة مرة أخرى.";
    } else {

        $current = trim($_POST['current_password']);
        $new     = trim($_POST['new_password']);
        $confirm = trim($_POST['confirm_password']);

        if ($current === '' || $new === '' || $confirm === '') {
            $error = "يرجى تعبئة جميع الحقول.";
        } elseif ($new !== $confirm) {
            $error = "كلمة المرور الجديدة وتأكيدها غير متطابقتين.";
        } elseif (strlen($new) < 8) {
            $error = "يجب أن تكون كلمة المرور الجديدة 8 أحرف على الأقل.";
        } else {

            // جلب كلمة المرور الحالية من قاعدة البيانات
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($hash);
            
            if ($stmt->fetch()) {
                // التحقق من أن كلمة المرور الحالية صحيحة
                if (password_verify($current, $hash)) {
                    $stmt->close();

                    // هاش جديد
                    $newHash = password_hash($new, PASSWORD_DEFAULT);

                    // تحديث كلمة المرور
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $newHash, $_SESSION['user_id']);

                    if ($stmt->execute()) {
                        $success = "تم تغيير كلمة المرور بنجاح.";
                    } else {
                        $error = "حدث خطأ أثناء تحديث كلمة المرور.";
                    }

                    $stmt->close();
                } else {
                    $error = "كلمة المرور الحالية غير صحيحة.";
                }
            } else {
                $error = "حدث خطأ أثناء جلب البيانات.";
            }
        }
    }
}
?>