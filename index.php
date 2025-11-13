<?php
session_start();
require 'db.php';

// جلب التصنيفات الرئيسية (أب فقط)
$cats_result = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1");

// لمعرفة هل يوجد بحث أو فلترة تصنيف
$has_filter = !empty($_GET['q']) || !empty($_GET['cat']);

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

    // قسم الأكثر مبيعاً من جدول order_items
    $sql_best = "
        SELECT p.*, c.name AS category_name, 
               COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING total_sold > 0
        ORDER BY total_sold DESC, p.created_at DESC
        LIMIT 6
    ";
    $bestsellers = $conn->query($sql_best);
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
if (!empty($_GET['cat'])) {
    $cat_id = (int) $_GET['cat'];
    $sql .= " AND p.category_id = ?";
    $params[] = $cat_id;
    $types   .= "i";
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
        </aside>

        <!-- قائمة المنتجات + الأقسام الخاصة -->
        <main class="col-lg-9">

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

                                            <form action="add_to_cart.php" method="POST" class="mt-auto d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                                <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                    <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="0.25"
                                                           step="0.25"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:90px;">
                                                <?php else: ?>
                                                    <label class="me-2 mb-0 small">الكمية:</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="1"
                                                           step="1"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:80px;">
                                                <?php endif; ?>

                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    أضف للسلة 🛒
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
                                            <p class="mb-2 fw-bold text_success text-success">
                                                <?php echo $row['price']; ?> شيكل
                                                <span class="text-muted">/ <?php echo htmlspecialchars($row['unit']); ?></span>
                                            </p>

                                            <form action="add_to_cart.php" method="POST" class="mt-auto d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                                <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                    <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="0.25"
                                                           step="0.25"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:90px;">
                                                <?php else: ?>
                                                    <label class="me-2 mb-0 small">الكمية:</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="1"
                                                           step="1"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:80px;">
                                                <?php endif; ?>

                                                <button type="submit" class="btn btn-sm btn-success">
                                                    أضف للسلة 🛒
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

                                            <form action="add_to_cart.php" method="POST" class="mt-auto d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                                <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                    <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="0.25"
                                                           step="0.25"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:90px;">
                                                <?php else: ?>
                                                    <label class="me-2 mb-0 small">الكمية:</label>
                                                    <input type="number"
                                                           name="qty"
                                                           value="1"
                                                           min="1"
                                                           step="1"
                                                           class="form-control form-control-sm me-2"
                                                           style="width:80px;">
                                                <?php endif; ?>

                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    أضف للسلة 🛒
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
            <h2 class="mb-3">
                <span class="brand-highlight">المنتجات المتاحة</span>
            </h2>

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

                                        <form action="add_to_cart.php" method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">

                                            <?php if (!empty($row['is_weight_based']) && $row['is_weight_based'] == 1): ?>
                                                <label class="me-2 mb-0 small">الوزن (كغم):</label>
                                                <input type="number"
                                                       name="qty"
                                                       value="1"
                                                       min="0.25"
                                                       step="0.25"
                                                       class="form-control form-control-sm me-2"
                                                       style="width:90px;">
                                            <?php else: ?>
                                                <label class="me-2 mb-0 small">الكمية:</label>
                                                <input type="number"
                                                       name="qty"
                                                       value="1"
                                                       min="1"
                                                       step="1"
                                                       class="form-control form-control-sm me-2"
                                                       style="width:80px;">
                                            <?php endif; ?>

                                            <button type="submit" class="btn btn-sm btn-success">
                                                أضف للسلة 🛒
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
