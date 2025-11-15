<?php include 'header.php'; ?>

<?php
// التأكد من وجود رقم المنتج في الرابط
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$id = (int)$_GET['id'];

// جلب بيانات المنتج من قاعدة البيانات
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "<div class='alert alert-danger mt-3'>المنتج غير موجود.</div>";
    include 'footer.php';
    exit;
}

// جلب التصنيفات لاستخدامها في القائمة المنسدلة
$cats = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name']);
    $desc   = trim($_POST['description']);
    $price  = (float) $_POST['price'];
    $unit   = trim($_POST['unit']);
    $cat_id = (int) $_POST['category_id'];
    $stock_quantity = isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? (int)$_POST['stock_quantity'] : -1;
    if ($stock_quantity < 0) $stock_quantity = -1; // -1 يعني غير محدود

    // حقول التحكم
    $is_weight_based = isset($_POST['is_weight_based']) ? 1 : 0;
    $is_trending     = isset($_POST['is_trending']) ? 1 : 0;
    $is_offer        = isset($_POST['is_offer']) ? 1 : 0;
    $is_active       = isset($_POST['is_active']) ? 1 : 0;

    // الصورة
    $image_name = $product['image'];

    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_name = time() . "_" . rand(1000,9999) . "." . $ext;
        if (@move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $new_name)) {
            // ممكن لو حابب تحذف القديمة:
            // if ($image_name && file_exists("../uploads/" . $image_name)) {
            //     @unlink("../uploads/" . $image_name);
            // }
            $image_name = $new_name;
        }
    }

    if ($name !== '' && $price > 0) {
        $stmt = $conn->prepare("
        UPDATE products 
        SET category_id = ?, 
            name        = ?, 
            description = ?, 
            price       = ?, 
            stock_quantity = ?,
            unit        = ?, 
            image       = ?, 
            is_weight_based = ?, 
            is_trending = ?, 
            is_offer    = ?, 
            is_active   = ?
        WHERE id = ?
        ");

        // الأنواع: i = int, s = string, d = double
        // cat_id (i)
        // name (s)
        // desc (s)
        // price (d)
        // stock_quantity (i)
        // unit (s)
        // image_name (s)
        // is_weight_based (i)
        // is_trending (i)
        // is_offer (i)
        // is_active (i)
        // id (i)
        $stmt->bind_param(
            "issdissiiiii",
            $cat_id,
            $name,
            $desc,
            $price,
            $stock_quantity,
            $unit,
            $image_name,
            $is_weight_based,
            $is_trending,
            $is_offer,
            $is_active,
            $id
        );

        $stmt->execute();
        $stmt->close();

        // إعادة تحميل بيانات المنتج بعد التحديث
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $success_msg = "تم تحديث بيانات المنتج بنجاح.";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">تعديل المنتج</h2>
    <a href="products.php" class="btn btn-sm btn-outline-secondary">عودة لقائمة المنتجات</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">بيانات المنتج</h5>
            </div>
            <div class="card-body">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success py-2">
                        <?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- الاسم -->
                    <div class="mb-3">
                        <label class="form-label">اسم المنتج</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>

                    <!-- الوصف -->
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" rows="3"><?php
                            echo htmlspecialchars($product['description']);
                        ?></textarea>
                    </div>

                    <!-- السعر / الوحدة / التصنيف -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">السعر (بالشيكل)</label>
                            <input type="number" step="0.01" name="price" class="form-control" required
                                   value="<?php echo $product['price']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الكمية المتاحة (المخزون)</label>
                            <input type="number" name="stock_quantity" class="form-control" 
                                   value="<?php 
                                   $stock_val = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : -1;
                                   echo $stock_val >= 0 ? $stock_val : '';
                                   ?>" 
                                   min="-1" placeholder="فارغ أو -1 = غير محدود">
                            <small class="text-muted">فارغ أو -1 = غير محدود، 0 = نفذت الكمية</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الوحدة</label>
                            <input type="text" name="unit" class="form-control"
                                   value="<?php echo htmlspecialchars($product['unit']); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">التصنيف</label>
                            <select name="category_id" class="form-select">
                                <?php
                                // نرجع نجيب التصنيفات من جديد لأن $cats استهلكناه في while
                                $cats2 = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
                                while($c = $cats2->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        <?php if ($product['category_id'] == $c['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <!-- الصورة -->
                    <div class="mb-3">
                        <label class="form-label">صورة المنتج</label>
                        <input type="file" name="image" class="form-control mb-2">
                        <?php if (!empty($product['image'])): ?>
                            <div class="mt-1">
                                <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>"
                                     width="140" height="90" style="object-fit:cover;border-radius:6px;">
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">لا توجد صورة حالياً.</span>
                        <?php endif; ?>
                    </div>
                    <!-- يباع بالوزن -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_weight_based" class="form-check-input" id="weightCheck"
                            <?php if (!empty($product['is_weight_based'])) echo 'checked'; ?>>
                        <label class="form-check-label" for="weightCheck">
                            هذا المنتج يُباع <strong>بالوزن (السعر لكل 1 كغم)</strong>
                        </label>
                    </div>
                    <!-- ترند -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_trending" class="form-check-input" id="trendCheck"
                            <?php if (!empty($product['is_trending'])) echo 'checked'; ?>>
                        <label class="form-check-label" for="trendCheck">
                            جعل هذا المنتج <strong>ترند / مميز 🔥</strong>
                        </label>
                    </div>

                    <!-- عرض -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_offer" class="form-check-input" id="offerCheck"
                            <?php if (!empty($product['is_offer'])) echo 'checked'; ?>>
                        <label class="form-check-label" for="offerCheck">
                            إضافة هذا المنتج إلى <strong>قسم العروض 🔖</strong>
                        </label>
                    </div>

                    <!-- تفعيل -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck"
                            <?php if (!empty($product['is_active'])) echo 'checked'; ?>>
                        <label class="form-check-label" for="activeCheck">
                            المنتج <strong>مفعل</strong> (يظهر في المتجر)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        حفظ التعديلات
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
