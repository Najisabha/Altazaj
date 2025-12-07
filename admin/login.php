<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

$error = "";

// توليد توكن CSRF إذا غير موجود
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // التحقق من توكن CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "جلسة غير صالحة، يرجى تحديث الصفحة والمحاولة مرة أخرى.";
    } else {

        // تنظيف وفحص المدخلات
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $pass  = trim($_POST['password']);

        if (!$email || $pass === '') {
            $error = "يرجى إدخال بريد إلكتروني صحيح وكلمة مرور.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($id, $name, $hash);
                    $stmt->fetch();

                    if (password_verify($pass, $hash)) {
                        session_regenerate_id(true);

                        $_SESSION['user_id']   = $id;
                        $_SESSION['user_name'] = $name;

                        header("Location: index.php");
                        exit;
                    } else {
                        $error = "البريد أو كلمة المرور غير صحيحة.";
                    }
                } else {
                    $error = "البريد أو كلمة المرور غير صحيحة.";
                }

                $stmt->close();
            } else {
                $error = "حدث خطأ غير متوقع، يرجى المحاولة لاحقًا.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f5132 0%, #198754 40%, #ffffff 100%);
            min-height: 100vh;
        }
        .login-card {
            max-width: 420px;
            margin: auto;
            margin-top: 7%;
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center">
    <div class="card shadow-lg login-card">
        <div class="card-header text-center bg-success text-white">
            <h4 class="mb-0">لوحة تحكم متجر الطازج</h4>
        </div>
        <div class="card-body">
            <p class="text-center text-muted mb-4">
                يرجى تسجيل الدخول لإدارة المتجر والطلبات
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" required
                           placeholder="admin@altazaj.local">
                </div>

                <div class="mb-3">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-success w-100">
                    دخول إلى لوحة التحكم
                </button>
            </form>
        </div>
        <div class="card-footer text-center small text-muted">
            الطازج للدواجن واللحوم &copy; <?php echo date('Y'); ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
