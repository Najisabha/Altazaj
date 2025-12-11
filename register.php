<?php
session_start();
require __DIR__ . '/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['full_phone'] ?? ''); // الرقم كامل مع المقدمة
    $national_id = trim($_POST['national_id'] ?? '');
    $birth_date  = trim($_POST['birth_date'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    // تحققات بسيطة
    if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $password === '' || $confirm === '') {
        $error = 'الرجاء تعبئة جميع الحقول الإلزامية.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'صيغة البريد الإلكتروني غير صحيحة.';
    } elseif ($password !== $confirm) {
        $error = 'كلمتا المرور غير متطابقتين.';
    } else {
        // هل البريد موجود؟
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            $error = 'البريد الإلكتروني أو رقم الجوال مستخدم من قبل.';
        } else {
            // يمكنك لاحقاً استبدالها بـ password_hash
            $hashed = $password;

            $stmt = $conn->prepare("
                INSERT INTO users (first_name, last_name, email, phone, national_id, birth_date, password, role, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'user', NOW())
            ");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $phone,
                $national_id !== '' ? $national_id : null,
                $birth_date  !== '' ? $birth_date  : null,
                $hashed
            ]);

            $success = 'تم إنشاء الحساب بنجاح، يمكنك تسجيل الدخول الآن.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء حساب - الطازج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- مكتبة رقم الجوال مع مقدمة الدولة -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@21.2.4/build/css/intlTelInput.css">

    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top left, #198754 0, #0f172a 40%, #000 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            max-width: 520px;
            width: 100%;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            background-color: #ffffff;
        }
        .auth-header {
            background: linear-gradient(135deg, #198754, #16a34a);
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
            <h3>إنشاء حساب جديد</h3>
        </div>
        <img src="assets/img/Altazaj.png" alt="Logo">
    </div>

    <div class="p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" id="registerForm" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">الاسم الأول *</label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?= htmlspecialchars($first_name ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">الاسم الثاني *</label>
                    <input type="text" name="last_name" class="form-control" required
                           value="<?= htmlspecialchars($last_name ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">البريد الإلكتروني *</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($email ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">رقم الجوال (واتساب) *</label>
                    <input type="tel" id="phone" class="form-control" required>
                    <!-- هنا نخزن الرقم كامل مع المقدمة -->
                    <input type="hidden" name="full_phone" id="full_phone" value="<?= htmlspecialchars($phone ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">رقم الهوية (اختياري)</label>
                    <input type="text" name="national_id" class="form-control"
                           value="<?= htmlspecialchars($national_id ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">تاريخ الميلاد (اختياري)</label>
                    <input type="date" name="birth_date" class="form-control"
                           value="<?= htmlspecialchars($birth_date ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">كلمة المرور *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">تأكيد كلمة المرور *</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-success w-100 py-2">
                        إنشاء الحساب
                    </button>
                </div>

                <div class="col-12 text-center">
                    <hr>
                    <span class="text-muted small">لديك حساب بالفعل؟</span>
                    <a href="login.php" class="small">تسجيل الدخول</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@21.2.4/build/js/intlTelInput.min.js"></script>
<script>
    const phoneInputField = document.querySelector("#phone");
    const fullPhoneField  = document.querySelector("#full_phone");

    const iti = window.intlTelInput(phoneInputField, {
        // الدولة الافتراضية حسب IP
        initialCountry: "auto",
        geoIpLookup: function(callback) {
            fetch("https://ipapi.co/json")
                .then(function(res) { return res.json(); })
                .then(function(data) { callback(data.country_code); })
                .catch(function() { callback("PS"); }); // فلسطين افتراضياً لو فشل
        },
        preferredCountries: ["ps","sa","ae","eg","jo"],
        separateDialCode: true,
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@21.2.4/build/js/utils.js"
    });

    // عند الإرسال نخزن الرقم الكامل مع المقدمة في الحقل المخفي
    document.getElementById("registerForm").addEventListener("submit", function () {
        if (iti.isValidNumber()) {
            fullPhoneField.value = iti.getNumber(); // مثال: +97259xxxxxxx
        } else {
            alert("الرجاء إدخال رقم جوال صحيح.");
            event.preventDefault();
        }
    });
</script>
</body>
</html>
