<?php
session_start();
require __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');   // إيميل أو جوال
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'الرجاء إدخال البريد الإلكتروني أو رقم الجوال وكلمة المرور.';
    } else {

        // تحديد هل المُدخل إيميل أم رقم جوال
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            // تسجيل دخول بالبريد
            $sql    = "SELECT * FROM users WHERE email = ?";
            $params = [$login];
        } else {
            // تسجيل دخول برقم الجوال مع المقدمة
            $phone = preg_replace('/\s+/', '', $login); // حذف المسافات

            // لو كتب 00 بدل + نحولها
            if (substr($phone, 0, 2) === '00') {
                $phone = '+' . substr($phone, 2);
            }

            $sql    = "SELECT * FROM users WHERE phone = ?";
            $params = [$phone];
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // لو استخدمت password_hash لاحقًا استبدل الشرط بتعليق السطرين
        if ($user && $user['password'] === $password) {
        // if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role']  = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: admin/index.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'بيانات الدخول غير صحيحة.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - الطازج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at bottom right, #198754 0, #0f172a 40%, #000 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            max-width: 420px;
            width: 100%;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            background-color: #ffffff;
        }
        .auth-header {
            background: linear-gradient(135deg, #111827, #198754);
            color: #fff;
            padding: 18px 24px;
        }
        .auth-header h3 {
            margin: 0;
            font-weight: 700;
        }
        .brand-badge {
            font-size: 13px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-header d-flex justify-content-between align-items-center">
        <div>
            <div class="brand-badge">متجر الطازج للدواجن واللحوم</div>
            <h3>تسجيل الدخول</h3>
        </div>
        <img src="assets/img/Altazaj.png" alt="Logo" style="width:50px;height:50px;border-radius:50%;background:#fff;">
    </div>

    <div class="p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">البريد الإلكتروني أو رقم الجوال (مع المقدمة)</label>
                <input type="text" name="login" class="form-control" required
                       placeholder="example@mail.com أو +97259xxxxxxx">
            </div>

            <div class="mb-3">
                <label class="form-label">كلمة المرور</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success w-100 py-2 mb-2">
                دخول
            </button>

            <div class="d-flex justify-content-between small">
                <a href="register.php">إنشاء حساب جديد</a>
                <a href="forgot_password.php">نسيت كلمة المرور؟</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
