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

        // تحديد نوع الإدخال: إيميل أو رقم جوال
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            // تسجيل دخول بالبريد
            $sql       = "SELECT * FROM users WHERE email = ?";
            $loginData = $login;
        } else {
            // تسجيل دخول برقم الجوال مع المقدمة
            $phone = preg_replace('/\s+/', '', $login); // حذف المسافات
            if (substr($phone, 0, 2) === '00') {
                $phone = '+' . substr($phone, 2);
            }
            $sql       = "SELECT * FROM users WHERE phone = ?";
            $loginData = $phone;
        }

        // mysqli
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $loginData);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = 'مشكلة في الاتصال بقاعدة البيانات.';
            $user  = null;
        }

        // ملاحظة: حاليًا كلمات المرور نص عادي
        if ($user && $user['password'] === $password) {
            // لو فعّلت التشفير لاحقًا استخدم:
            // if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role']; // 'user' أو 'admin'

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
    <title>تسجيل الدخول - متجر الطازج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at 10% 20%, #16a34a 0, transparent 50%),
                radial-gradient(circle at 90% 80%, #15803d 0, transparent 55%),
                radial-gradient(circle at 50% 50%, #020617 0, #020617 60%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            direction: rtl;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .auth-wrapper {
            max-width: 430px;
            width: 100%;
        }
        .auth-card {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 18px 45px rgba(0,0,0,0.55);
            background-color: #ffffff;
        }
        .auth-header {
            background: linear-gradient(135deg, #0f172a, #15803d);
            color: #fff;
            padding: 20px 24px 60px;
            position: relative;
            text-align: center;
        }
        .auth-logo {
            position: absolute;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .auth-logo img {
            width: 100%;
            height: auto;
            object-fit: contain;

        }
        .auth-title {
            margin-top: 40px;
            font-size: 22px;
            font-weight: 700;
        }
        .auth-subtitle {
            font-size: 13px;
            opacity: 0.9;
        }
        .auth-body {
            padding: 22px 24px 20px;
        }
        .auth-footer {
            padding: 0 24px 20px;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 16px 0;
        }
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }
        .divider span {
            padding: 0 10px;
            color: #9ca3af;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-logo">
                <!-- تقدر تشيل الصورة لو مش حاب -->
                <img src="assets/img/Altazaj.png" alt="Altazaj" >
            </div>
            <div class="auth-title mt-5">تسجيل الدخول</div>
            <div class="auth-subtitle">متجر الطازج للدواجن واللحوم</div>
        </div>

        <div class="auth-body">
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 mb-3">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label small mb-1">
                        البريد الإلكتروني أو رقم الجوال (مع المقدمة)
                    </label>
                    <input type="text"
                           name="login"
                           class="form-control"
                           placeholder="example@mail.com أو +97259xxxxxxx"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label small mb-1">كلمة المرور</label>
                    <input type="password"
                           name="password"
                           class="form-control"
                           required>
                </div>

                <button type="submit" class="btn btn-success w-100 py-2 mt-1">
                    دخول
                </button>
            </form>
        </div>

        <div class="auth-footer">
            <div class="divider"><span>خيارات أخرى</span></div>

            <div class="d-flex flex-column gap-2">
                <button type="button"
                        class="btn btn-outline-success w-100"
                        onclick="window.location.href='register.php'">
                    إنشاء حساب جديد
                </button>
                <button type="button"
                        class="btn btn-outline-secondary w-100"
                        onclick="window.location.href='forgot_password.php'">
                    نسيت كلمة المرور؟
                </button>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
