<?php
session_start();
require __DIR__ . '/db.php';

$error   = '';
$success = '';

// نضمن تعريف المتغيرات لتفادي الأخطاء في أول تحميل للصفحة
$first_name  = $first_name  ?? '';
$last_name   = $last_name   ?? '';
$email       = $email       ?? '';
$phone       = $phone       ?? '';
$birth_date  = $birth_date  ?? '';
$national_image = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['full_phone'] ?? ''); // الرقم كامل مع المقدمة
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
        // هل البريد أو الجوال موجود؟
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            $error = 'البريد الإلكتروني أو رقم الجوال مستخدم من قبل.';
        } else {
            // رفع صورة الهوية إن وجدت
            $national_image = null;

            if (!empty($_FILES['national_image']['name'])) {
                $allowed = ['jpg','jpeg','png'];
                $ext = strtolower(pathinfo($_FILES['national_image']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    $error = 'صيغة صورة الهوية غير مسموحة. الرجاء رفع ملف بصيغة JPG أو JPEG أو PNG.';
                } else {
                    $newName = 'id_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/ids/';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $uploadPath = $uploadDir . $newName;

                    if (move_uploaded_file($_FILES['national_image']['tmp_name'], $uploadPath)) {
                        $national_image = $newName;
                    } else {
                        $error = 'حدث خطأ أثناء رفع صورة الهوية. حاول مرة أخرى.';
                    }
                }
            }

            // لو ما في أخطاء لحد هون نكمل الإدخال
            if ($error === '') {
                // يمكنك لاحقاً استبدالها بـ password_hash وتعديل كود تسجيل الدخول
                $hashed = $password;

                $stmt = $conn->prepare("
                    INSERT INTO users (first_name, last_name, email, phone, national_image, birth_date, password, role, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'user', NOW())
                ");
                $stmt->execute([
                    $first_name,
                    $last_name,
                    $email,
                    $phone,
                    $national_image,                       // null لو ما رفع صورة
                    $birth_date  !== '' ? $birth_date : null,
                    $hashed
                ]);

                $success = 'تم إنشاء الحساب بنجاح، يمكنك تسجيل الدخول الآن.';
                // تفريغ الحقول بعد نجاح التسجيل
                $first_name = $last_name = $email = $phone = $birth_date = '';
                $national_image = null;
            }
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
        <img src="assets/img/Altazaj.png" alt="Logo" height="50px" width="auto">
    </div>

    <div class="p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" id="registerForm" enctype="multipart/form-data" novalidate>
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
                    <label class="form-label">صورة الهوية (اختياري)</label>
                    <div class="border rounded-3 p-3 text-center bg-light">
                        <img id="idPreview"
                             src="https://cdn-icons-png.flaticon.com/512/942/942748.png"
                             class="img-fluid mb-2"
                             style="max-height:120px; opacity:.7;">
                        <input type="file" name="national_image" class="form-control"
                               accept="image/*"
                               onchange="previewID(this)">
                        <small class="text-muted d-block mt-1">PNG - JPG - JPEG فقط</small>
                    </div>
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

    // تعبئة الرقم الكامل مع المقدمة في الحقل المخفي قبل الإرسال
    document.getElementById("registerForm").addEventListener("submit", function (event) {
        if (iti.isValidNumber()) {
            fullPhoneField.value = iti.getNumber(); // مثال: +97259xxxxxxx
        } else {
            alert("الرجاء إدخال رقم جوال صحيح.");
            event.preventDefault();
        }
    });

    // معاينة صورة الهوية
    function previewID(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById("idPreview").src = e.target.result;
                document.getElementById("idPreview").style.opacity = "1";
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</body>
</html>