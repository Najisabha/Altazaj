<?php include 'header.php'; ?>

<?php
// إضافة تصنيف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $parent_id = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;

    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = time() . "_" . rand(1000,9999) . "." . $ext;
        @move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $image_name);
    }

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (name, image, parent_id) VALUES (?, ?, ?)");
        if ($parent_id) {
            $stmt->bind_param("ssi", $name, $image_name, $parent_id);
        } else {
            $null = null;
            $stmt->bind_param("sss", $name, $image_name, $null);
        }
        $stmt->execute();
        $stmt->close();
        header("Location: categories.php");
        exit;
    }
}

// حذف تصنيف
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM categories WHERE id = $id");
    header("Location: categories.php");
    exit;
}

$cats      = $conn->query("SELECT * FROM categories ORDER BY parent_id, name");
$root_cats = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">إدارة التصنيفات</h2>
</div>

<div class="row g-3">
    <!-- فورم إضافة تصنيف -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white">
                إضافة تصنيف جديد
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">اسم التصنيف</label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: لحوم طازجة">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">صورة التصنيف (اختياري)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">يفضل صورة 400×300 تقريباً</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">التصنيف الأب (اختياري)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">بدون أب (تصنيف رئيسي)</option>
                            <?php 
                            // إعادة جلب التصنيفات الرئيسية
                            $root_cats2 = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
                            while($c = $root_cats2->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" name="add" class="btn btn-success w-100">
                        حفظ التصنيف
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- جدول التصنيفات -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">جميع التصنيفات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الصورة</th>
                                <th>الاسم</th>
                                <th>الأب</th>
                                <th class="text-center">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($cats->num_rows > 0): ?>
                            <?php 
                            // إعادة جلب التصنيفات مع معلومات الأب
                            $cats_with_parent = $conn->query("
                                SELECT c.*, p.name AS parent_name 
                                FROM categories c 
                                LEFT JOIN categories p ON c.parent_id = p.id 
                                ORDER BY c.parent_id, c.name
                            ");
                            while($c = $cats_with_parent->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $c['id']; ?></td>
                                    <td>
                                        <?php if (!empty($c['image'])): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($c['image']); ?>" 
                                                 width="60" height="40" style="object-fit:cover;border-radius:4px;">
                                        <?php else: ?>
                                            <span class="text-muted small">لا يوجد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                                        <?php if (!$c['parent_id']): ?>
                                            <span class="badge bg-primary ms-2">رئيسي</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $c['parent_name'] ? htmlspecialchars($c['parent_name']) : '<span class="text-muted">-</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?delete=<?php echo $c['id']; ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('هل أنت متأكد من حذف هذا التصنيف؟');">
                                            حذف
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    لا توجد تصنيفات بعد.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0">
                    * يمكنك استخدام التصنيفات الرئيسية مثل: لحوم، دواجن، مجمدات، ثم إنشاء تصنيفات فرعية تحتها.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
