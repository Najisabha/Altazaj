<?php include 'header.php'; ?>

<?php
// إضافة تصنيف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $parent_id = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
        if ($parent_id) {
            $stmt->bind_param("si", $name, $parent_id);
        } else {
            $null = null;
            $stmt->bind_param("ss", $name, $null);
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
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">اسم التصنيف</label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: لحوم طازجة">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">التصنيف الأب (اختياري)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">بدون أب (تصنيف رئيسي)</option>
                            <?php while($c = $root_cats->fetch_assoc()): ?>
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
                                <th>الاسم</th>
                                <th>الأب</th>
                                <th class="text-center">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($cats->num_rows > 0): ?>
                            <?php while($c = $cats->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $c['id']; ?></td>
                                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                                    <td><?php echo $c['parent_id'] ? $c['parent_id'] : '-'; ?></td>
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
                                <td colspan="4" class="text-center text-muted">
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
