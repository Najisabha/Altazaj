<?php include 'header.php'; ?>

<?php
// جلب التصنيفات لاستخدامها في الفورم
$cats = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// إضافة منتج جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name   = trim($_POST['name']);
    $desc   = trim($_POST['description']);
    $price  = (float) $_POST['price'];
    $unit   = trim($_POST['unit']);
    $cat_id = (int) $_POST['category_id'];

    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = time() . "_" . rand(1000,9999) . "." . $ext;
        @move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $image_name);
    }

    if ($name !== '' && $price > 0) {
        $stmt = $conn->prepare("INSERT INTO products (category_id, name, description, price, unit, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdss", $cat_id, $name, $desc, $price, $unit, $image_name);
        $stmt->execute();
        $stmt->close();
        header("Location: products.php");
        exit;
    }
}

// حذف منتج
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE id = $id");
    header("Location: products.php");
    exit;
}

// تغيير حالة الترند
if (isset($_GET['trend']) && isset($_GET['tval'])) {
    $id  = (int)$_GET['trend'];
    $val = (int)$_GET['tval']; // 0 أو 1
    $stmt = $conn->prepare("UPDATE products SET is_trending = ? WHERE id = ?");
    $stmt->bind_param("ii", $val, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: products.php");
    exit;
}

// تغيير حالة العرض
if (isset($_GET['offer']) && isset($_GET['oval'])) {
    $id  = (int)$_GET['offer'];
    $val = (int)$_GET['oval']; // 0 أو 1
    $stmt = $conn->prepare("UPDATE products SET is_offer = ? WHERE id = ?");
    $stmt->bind_param("ii", $val, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: products.php");
    exit;
}

$products = $conn->query("
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">إدارة المنتجات</h2>
</div>

<div class="row g-3">
    <!-- فورم إضافة منتج جديد -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white">
                إضافة منتج جديد
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">اسم المنتج</label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: لحم عجل بلدي">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الوصف (اختياري)</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="وصف مختصر للمنتج"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">السعر (بالشيكل)</label>
                        <input type="number" step="0.01" name="price" class="form-control" required placeholder="مثال: 55.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الوحدة</label>
                        <input type="text" name="unit" class="form-control" value="كغم">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">التصنيف</label>
                        <select name="category_id" class="form-select">
                            <?php while($c = $cats->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">صورة المنتج (اختياري)</label>
                        <input type="file" name="image" class="form-control">
                        <small class="text-muted">يفضل صورة أفقية 400×200 تقريباً.</small>
                    </div>

                    <button type="submit" name="add" class="btn btn-success w-100">
                        حفظ المنتج
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- جدول المنتجات -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">قائمة المنتجات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الصورة</th>
                                <th>الاسم</th>
                                <th>التصنيف</th>
                                <th>السعر</th>
                                <th>ترند</th>
                                <th>عرض</th>
                                <th class="text-center">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($products->num_rows > 0): ?>
                            <?php while($p = $products->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $p['id']; ?></td>
                                    <td>
                                        <?php if ($p['image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($p['image']); ?>" width="60" height="40" style="object-fit:cover;">
                                        <?php else: ?>
                                            <span class="text-muted small">لا يوجد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($p['name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($p['unit']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                                    <td><?php echo $p['price']; ?> شيكل</td>
                                    <td>
                                        <?php if ($p['is_trending']): ?>
                                            <span class="badge bg-warning text-dark">ترند 🔥</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">عادي</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['is_offer']): ?>
                                            <span class="badge bg-danger">عرض 🔖</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">لا يوجد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- زر تعديل -->
                                            <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary">
                                                تعديل
                                            </a>

                                            <!-- زر ترند / إلغاء ترند -->
                                            <?php if ($p['is_trending']): ?>
                                                <a href="?trend=<?php echo $p['id']; ?>&tval=0"
                                                   class="btn btn-outline-warning"
                                                   onclick="return confirm('إلغاء جعل المنتج ترند؟');">
                                                    إلغاء ترند
                                                </a>
                                            <?php else: ?>
                                                <a href="?trend=<?php echo $p['id']; ?>&tval=1"
                                                   class="btn btn-outline-warning"
                                                   onclick="return confirm('جعل هذا المنتج ترند؟');">
                                                    ترند 🔥
                                                </a>
                                            <?php endif; ?>

                                            <!-- زر عرض / إلغاء عرض -->
                                            <?php if ($p['is_offer']): ?>
                                                <a href="?offer=<?php echo $p['id']; ?>&oval=0"
                                                   class="btn btn-outline-danger"
                                                   onclick="return confirm('إلغاء وضع المنتج في العروض؟');">
                                                    إلغاء عرض
                                                </a>
                                            <?php else: ?>
                                                <a href="?offer=<?php echo $p['id']; ?>&oval=1"
                                                   class="btn btn-outline-danger"
                                                   onclick="return confirm('إضافة المنتج لقسم العروض؟');">
                                                    عرض 🔖
                                                </a>
                                            <?php endif; ?>

                                            <!-- زر حذف -->
                                            <a href="?delete=<?php echo $p['id']; ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('هل أنت متأكد من حذف هذا المنتج؟');">
                                                حذف
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    لا توجد منتجات بعد.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0">
                    * يمكنك جعل المنتج ترند 🔥 أو إضافته لقسم العروض 🔖 بسهولة من هنا أو من صفحة التعديل.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
