<?php
session_start();
require 'db.php';

// جلب التصنيفات الرئيسية (أب فقط) - مع إعادة تعيين المؤشر
$cats_result_all = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1");
$cats_result = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1");

// لمعرفة هل يوجد بحث أو فلترة تصنيف
$has_filter = !empty($_GET['q']) || !empty($_GET['cat']);
$show_categories = !$has_filter; // عرض التصنيفات فقط عندما لا يوجد فلترة

// أقسام خاصة تظهر فقط عندما لا يكون هناك بحث / فلترة
$offers = $trending = $bestsellers = null;

if (!$has_filter) {
    // قسم العروض
    $sql_offers = "
        SELECT p.*, c.name AS category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.is_offer = 1
        ORDER BY p.created_at DESC
        LIMIT 6
    ";
    $offers = $conn->query($sql_offers);

    // قسم الترند
    $sql_trend = "
        SELECT p.*, c.name AS category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.is_trending = 1
        ORDER BY p.created_at DESC
        LIMIT 6
    ";
    $trending = $conn->query($sql_trend);

    // قسم الأكثر مبيعاً من جدول order_items (يومي فقط - طلبات اليوم المكتملة)
    $today = date('Y-m-d');
    $sql_best = "
        SELECT p.*, c.name AS category_name, 
               COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN orders o ON o.id = oi.order_id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
          AND o.status = 'مكتمل'
          AND DATE(o.created_at) = ?
        GROUP BY p.id
        HAVING total_sold > 0
        ORDER BY total_sold DESC, p.created_at DESC
        LIMIT 6
    ";
    $stmt_best = $conn->prepare($sql_best);
    $stmt_best->bind_param("s", $today);
    $stmt_best->execute();
    $bestsellers = $stmt_best->get_result();
}

// إعداد استعلام المنتجات الأساسية
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1";
$params = [];
$types  = "";

// فلترة بالبحث
$current_search = '';
if (!empty($_GET['q'])) {
    $q = "%".$_GET['q']."%";
    $sql .= " AND p.name LIKE ?";
    $params[] = $q;
    $types   .= "s";
    $current_search = $_GET['q'];
}

// فلترة بالتصنيف
$current_cat = '';
$current_cat_name = '';
if (!empty($_GET['cat'])) {
    $cat_id = (int) $_GET['cat'];
    // جلب اسم التصنيف
    $cat_stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $cat_stmt->bind_param("i", $cat_id);
    $cat_stmt->execute();
    $cat_result = $cat_stmt->get_result();
    if ($cat_row = $cat_result->fetch_assoc()) {
        $current_cat_name = $cat_row['name'];
    }
    $cat_stmt->close();
    
    // فلترة المنتجات: إما في التصنيف المحدد أو في تصنيفات فرعية له
    // نستخدم استعلام فرعي للتصنيفات الفرعية
    $sql .= " AND (p.category_id = ? OR p.category_id IN (SELECT id FROM categories WHERE parent_id = ?))";
    $params[] = $cat_id;
    $params[] = $cat_id;
    $types   .= "ii";
    $current_cat = $cat_id;
}

// ترتيب: أولاً الترند ثم الأحدث
$sql .= " ORDER BY p.is_trending DESC, p.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>متجر الطازج للدواجن واللحوم</title>
    <link rel="icon" href="./assets/img/Altazaj.png" >
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .brand-highlight {
            color: #198754;
            font-weight: 700;
        }
        .category-pill a {
            text-decoration: none;
        }
        .product-card img {
            max-height: 200px;
            object-fit: cover;
        }
        .category-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .category-card:hover {
            transform: translateY(-5px);
        }
        .category-card img {
            border-radius: 8px 8px 0 0;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            الطازج <span class="text-success">للدواجن واللحوم</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <!-- بحث -->
            <form class="d-flex ms-auto my-2 my-lg-0" method="GET" action="index.php">
                <input class="form-control me-2" type="search" name="q"
                       placeholder="ابحث عن منتج..."
                       value="<?php echo htmlspecialchars($current_search); ?>">
                <button class="btn btn-outline-light" type="submit">بحث</button>
            </form>

            <!-- السلة -->
            <ul class="navbar-nav me-3 mb-2 mb-lg-0">
                <li class="nav-item ms-3">
                    <a class="btn btn-success position-relative" href="cart.php">
                        السلة 🛒
                        <?php if ($cart_count > 0): ?>
                            <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- المحتوى الرئيسي -->
<div class="container">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div class="row">
        <!-- التصنيفات الجانبية -->
        <aside class="col-lg-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white fw-bold">
                    التصنيفات
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <div class="category-pill">
                            <a href="index.php" class="badge rounded-pill 
                                <?php echo $current_cat === '' ? 'bg-success' : 'bg-secondary'; ?>">
                                الكل
                            </a>
                        </div>
                        <?php while($cat = $cats_result->fetch_assoc()): ?>
                            <div class="category-pill">
                                <a href="index.php?cat=<?php echo $cat['id']; ?>"
                                   class="badge rounded-pill
                                       <?php echo ($current_cat == $cat['id']) ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <?php if ($current_search !== ''): ?>
                <div class="alert alert-info mt-3">
                    نتائج البحث عن:
                    <strong><?php echo htmlspecialchars($current_search); ?></strong>
                </div>
            <?php endif; ?>
            
            <?php if ($current_cat_name !== ''): ?>
                <div class="alert alert-success mt-3">
                    <strong>التصنيف:</strong> <?php echo htmlspecialchars($current_cat_name); ?>
                    <a href="index.php" class="btn btn-sm btn-outline-light ms-2">عرض الكل</a>
                </div>
            <?php endif; ?>
        </aside>

        <!-- قائمة المنتجات + الأقسام الخاصة -->
        <main class="col-lg-9">

            <?php if ($show_categories): ?>
                <!-- عرض التصنيفات الرئيسية -->
                <section class="mb-5">
                    <h2 class="mb-4">
                        <span class="brand-highlight">التصنيفات الرئيسية</span>
                    </h2>
                    <div class="row g-4">
                        <?php 
                        // إعادة جلب التصنيفات الرئيسية
                        $cats_for_display = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY name");
                        while($cat = $cats_for_display->fetch_assoc()): 
                            // حساب عدد المنتجات في هذا التصنيف (بما في ذلك التصنيفات الفرعية)
                            $products_count = $conn->query("
                                SELECT COUNT(*) as count 
                                FROM products p
                                WHERE (p.category_id = {$cat['id']} OR p.category_id IN (SELECT id FROM categories WHERE parent_id = {$cat['id']}))
                                AND p.is_active = 1
                            ")->fetch_assoc()['count'];
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <a href="index.php?cat=<?php echo $cat['id']; ?>" 
                                   class="text-decoration-none">
                                    <div class="card h-100 shadow-sm border-0 category-card">
                                        <?php if (!empty($cat['image'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($cat['image']); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($cat['name']); ?>"
                                                 style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                                 style="height: 200px;">
                                                <span class="text-muted fs-1">📁</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body text-center">
                                            <h5 class="card-title mb-2 text-dark">
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </h5>
                                            <p class="text-muted small mb-0">
                                                <?php echo $products_count; ?> منتج
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </section>
                <hr class="my-5">
            <?php endif; ?>

            <?php if (!$has_filter): ?>

                <!-- قسم العروض الخاصة -->
                <?php if ($offers && $offers->num_rows > 0): ?>
                    <section class="mb-4">
                        <h4 class="mb-3 text-danger">عروض خاصة 🔖</h4>
                        <div class="row g-3">
                            <?php while($row = $offers->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 shadow-sm product-card border-danger">
                                        <?php if (!empty($row['image'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>"
                                                 class="card-img-top"
                                                 alt="<?php echo htmlspecialchars($row['name']); ?>">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/400x200?text=No+Image"
                                                 class="card-img-top"
                                                 alt="No image">
                                        <?php endif; ?>

                                        <div class="card-body d-flex flex-column">
                                            <span class="badge bg-danger mb-1">عرض خاص 🔖</span>
                                            <h6 class="card-title text-truncate">
                                                <?php echo htmlspecialchars($row['name']); ?>
                                            </h6>
                                            <?php if (!empty($row['category_name'])): ?>
                                                <span class="badge bg-secondary mb-2">
                                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <p class="mb-2 fw-bold text-danger">
                                                <?php echo $row['price']; ?> شيكل
                                                <span class="text-muted">/ <?php echo htmlspecialchars($row['unit']); ?></span>
                                            </p>
                                            <?php 
                                            $stock = isset($row['stock_quantity']) ? (int)$row['stock_quantity'] : -1;
                                            if ($stock > 0): 
                                            ?>
                                                <small class="text-info mb-2 d-block">المتاح: <?php echo $stock; ?></small>
                                            <?php elseif ($stock == 0): ?>
                                                <small class="text-danger mb-2 d-block">نفذت الكمية</small>
                                            <?php endif; ?>

                                            <form action="add_to_cart.php" method="POST" class="mt-auto d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                                <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                    <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="0.25"
                                                           step="0.25"
                                                           max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:90px;"
                                                           <?php if ($stock == 0) echo 'disabled'; ?>>
                                                <?php else: ?>
                                                    <label class="me-2 mb-0 small">الكمية:</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="1"
                                                           step="1"
                                                           max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:80px;"
                                                           <?php if ($stock == 0) echo 'disabled'; ?>>
                                                <?php endif; ?>

                                                <button type="submit" class="btn btn-sm btn-danger" <?php if ($stock == 0) echo 'disabled'; ?>>
                                                    <?php echo ($stock == 0) ? 'نفذت الكمية' : 'أضف للسلة 🛒'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <hr>
                    </section>
                <?php endif; ?>

                <!-- قسم الترند -->
                <?php if ($trending && $trending->num_rows > 0): ?>
                    <section class="mb-4">
                        <h4 class="mb-3 text-warning">المنتجات الترند 🔥</h4>
                        <div class="row g-3">
                            <?php while($row = $trending->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 shadow-sm product-card">
                                        <?php if (!empty($row['image'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>"
                                                 class="card-img-top"
                                                 alt="<?php echo htmlspecialchars($row['name']); ?>">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/400x200?text=No+Image"
                                                 class="card-img-top"
                                                 alt="No image">
                                        <?php endif; ?>

                                        <div class="card-body d-flex flex-column">
                                            <span class="badge bg-warning text-dark mb-1">ترند 🔥</span>
                                            <h6 class="card-title text-truncate">
                                                <?php echo htmlspecialchars($row['name']); ?>
                                            </h6>
                                            <?php if (!empty($row['category_name'])): ?>
                                                <span class="badge bg-secondary mb-2">
                                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <p class="mb-2 fw-bold text-success">
                                                <?php echo $row['price']; ?> شيكل
                                                <span class="text-muted">/ <?php echo htmlspecialchars($row['unit']); ?></span>
                                            </p>
                                            <?php 
                                            $stock = isset($row['stock_quantity']) ? (int)$row['stock_quantity'] : -1;
                                            if ($stock > 0): 
                                            ?>
                                                <small class="text-info mb-2 d-block">المتاح: <?php echo $stock; ?></small>
                                            <?php elseif ($stock == 0): ?>
                                                <small class="text-danger mb-2 d-block">نفذت الكمية</small>
                                            <?php endif; ?>

                                            <form action="add_to_cart.php" method="POST" class="mt-auto d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                                <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                    <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="0.25"
                                                           step="0.25"
                                                           max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:90px;"
                                                           <?php if ($stock == 0) echo 'disabled'; ?>>
                                                <?php else: ?>
                                                    <label class="me-2 mb-0 small">الكمية:</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="1"
                                                           step="1"
                                                           max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:80px;"
                                                           <?php if ($stock == 0) echo 'disabled'; ?>>
                                                <?php endif; ?>

                                                <button type="submit" class="btn btn-sm btn-success" <?php if ($stock == 0) echo 'disabled'; ?>>
                                                    <?php echo ($stock == 0) ? 'نفذت الكمية' : 'أضف للسلة 🛒'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <hr>
                    </section>
                <?php endif; ?>

                <!-- قسم الأكثر مبيعاً -->
                <?php if ($bestsellers && $bestsellers->num_rows > 0): ?>
                    <section class="mb-4">
                        <h4 class="mb-3 text-primary">الأكثر مبيعاً 🏆</h4>
                        <div class="row g-3">
                            <?php while($row = $bestsellers->fetch_assoc()): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 shadow-sm product-card">
                                        <?php if (!empty($row['image'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>"
                                                 class="card-img-top"
                                                 alt="<?php echo htmlspecialchars($row['name']); ?>">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/400x200?text=No+Image"
                                                 class="card-img-top"
                                                 alt="No image">
                                        <?php endif; ?>

                                        <div class="card-body d-flex flex-column">
                                            <span class="badge bg-primary mb-1">
                                                مبيعات: <?php echo $row['total_sold']; ?>
                                            </span>
                                            <h6 class="card-title text-truncate">
                                                <?php echo htmlspecialchars($row['name']); ?>
                                            </h6>
                                            <?php if (!empty($row['category_name'])): ?>
                                                <span class="badge bg-secondary mb-2">
                                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <p class="mb-2 fw-bold text-success">
                                                <?php echo $row['price']; ?> شيكل
                                                <span class="text-muted">/ <?php echo htmlspecialchars($row['unit']); ?></span>
                                            </p>
                                            <?php 
                                            $stock = isset($row['stock_quantity']) ? (int)$row['stock_quantity'] : -1;
                                            if ($stock > 0): 
                                            ?>
                                                <small class="text-info mb-2 d-block">المتاح: <?php echo $stock; ?></small>
                                            <?php elseif ($stock == 0): ?>
                                                <small class="text-danger mb-2 d-block">نفذت الكمية</small>
                                            <?php endif; ?>

                                            <form action="add_to_cart.php" method="POST" class="mt-auto d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                                <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                    <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="0.25"
                                                           step="0.25"
                                                           max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:90px;"
                                                           <?php if ($stock == 0) echo 'disabled'; ?>>
                                                <?php else: ?>
                                                    <label class="me-2 mb-0 small">الكمية:</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="1"
                                                           step="1"
                                                           max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:80px;"
                                                           <?php if ($stock == 0) echo 'disabled'; ?>>
                                                <?php endif; ?>

                                                <button type="submit" class="btn btn-sm btn-primary" <?php if ($stock == 0) echo 'disabled'; ?>>
                                                    <?php echo ($stock == 0) ? 'نفذت الكمية' : 'أضف للسلة 🛒'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <hr>
                    </section>
                <?php endif; ?>

            <?php endif; ?>

            <!-- قائمة المنتجات العادية -->
            <?php if ($has_filter): ?>
                <h2 class="mb-3">
                    <span class="brand-highlight">المنتجات المتاحة</span>
                </h2>
            <?php else: ?>
                <h2 class="mb-3">
                    <span class="brand-highlight">منتجات مميزة</span>
                </h2>
            <?php endif; ?>

            <?php if ($result->num_rows > 0): ?>
                <div class="row g-3">
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm product-card">
                                <?php if (!empty($row['image'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>"
                                         class="card-img-top"
                                         alt="<?php echo htmlspecialchars($row['name']); ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/400x200?text=No+Image"
                                         class="card-img-top"
                                         alt="No image">
                                <?php endif; ?>

                                <div class="card-body d-flex flex-column">
                                    <?php if (!empty($row['is_trending']) && $row['is_trending'] == 1): ?>
                                        <span class="badge bg-warning text-dark mb-1">ترند 🔥</span>
                                    <?php endif; ?>

                                    <?php if (!empty($row['is_offer']) && $row['is_offer'] == 1): ?>
                                        <span class="badge bg-danger mb-1">عرض 🔖</span>
                                    <?php endif; ?>

                                    <h5 class="card-title text-truncate">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                    </h5>

                                    <?php if (!empty($row['category_name'])): ?>
                                        <span class="badge bg-secondary mb-2">
                                            <?php echo htmlspecialchars($row['category_name']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($row['description'])): ?>
                                        <p class="card-text small text-muted">
                                            <?php echo nl2br(htmlspecialchars(mb_strimwidth($row['description'], 0, 120, '...'))); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="mt-auto">
                                        <p class="mb-2 fw-bold text-success">
                                            <?php echo $row['price']; ?> شيكل
                                            <span class="text-muted">/ <?php echo htmlspecialchars($row['unit']); ?></span>
                                        </p>
                                        <?php 
                                        $stock = isset($row['stock_quantity']) ? (int)$row['stock_quantity'] : 0;
                                        if ($stock > 0): 
                                        ?>
                                            <small class="text-info mb-2 d-block">المتاح: <?php echo $stock; ?></small>
                                        <?php elseif ($stock == 0): ?>
                                            <small class="text-danger mb-2 d-block">نفذت الكمية</small>
                                        <?php endif; ?>

                                        <form action="add_to_cart.php" method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                            <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                <input type="number"
                                                       name="qty"
                                                       value="1"
                                                       min="0.25"
                                                       step="0.25"
                                                       max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                       class="form-control form-control-sm me-2"
                                                       style="width:90px;"
                                                       <?php if ($stock == 0) echo 'disabled'; ?>>
                                            <?php else: ?>
                                                <label class="me-2 mb-0 small">الكمية:</label>
                                                <input type="number"
                                                       name="qty"
                                                       value="1"
                                                       min="1"
                                                       step="1"
                                                       max="<?php echo ($stock > 0) ? $stock : ''; ?>"
                                                       class="form-control form-control-sm me-2"
                                                       style="width:80px;"
                                                       <?php if ($stock == 0) echo 'disabled'; ?>>
                                            <?php endif; ?>

                                            <button type="submit" class="btn btn-sm btn-success" <?php if ($stock == 0) echo 'disabled'; ?>>
                                                <?php echo ($stock == 0) ? 'نفذت الكمية' : 'أضف للسلة 🛒'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-3">
                    لا توجد منتجات مطابقة للبحث / التصنيف الحالي.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
