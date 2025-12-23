<?php
session_start();
require '../db.php';
require '../functions.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم - متجر الطازج</title>
    <link rel="icon" href="../assets/img/Altazaj.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;              /* ✅ يلغي الشريط الأبيض فوق وتحت */
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
        }
        .sidebar a {
            color: #adb5bd;
            text-decoration: none;
        }
        .sidebar a.active,
        .sidebar a:hover {
            color: #ffffff;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin-bottom: 0.25rem;
        }
        .brand-logo {
            font-weight: 700;
            color: #ffffff;
        }
        .brand-logo span {
            color: #20c997;
        }
    </style>
</head>
<body>

<!-- Navbar أعلى الصفحة -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand brand-logo">
            الطازج <span>أدمن</span>
        </span>

        <div class="d-flex align-items-center">
            <span class="text-light me-3 small">
                مرحباً، <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-light">
                تسجيل الخروج
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 sidebar d-flex flex-column p-3">
            <h6 class="text-secondary text-uppercase small mb-3">القائمة الرئيسية</h6>
            <nav class="nav nav-pills flex-column">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"
                   href="index.php">
                    🏠 الرئيسية
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>"
                   href="categories.php">
                    🧾 التصنيفات
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>"
                   href="products.php">
                    🛒 المنتجات
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>"
                   href="orders.php">
                    📦 الطلبات
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'coupons.php' ? 'active' : ''; ?>"
                   href="coupons.php">
                    🎫 الكوبونات والخصومات
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>"
                   href="settings.php">
                    ⚙️ الإعدادات
                </a>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>"
                   href="users.php">
                    👥 إدارة المستخدمين
                </a>
            </nav>

            <div class="mt-auto text-secondary small">
                <hr class="border-secondary">
                <div>الطازج &copy; <?php echo date('Y'); ?></div>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="col-md-9 col-lg-10 p-4">
