<?php include 'header.php'; ?>

<?php
// إضافة كوبون جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $code = trim($_POST['code']);
    $discount_type = $_POST['discount_type'];
    $discount_value = isset($_POST['discount_value']) ? (float)$_POST['discount_value'] : 0;
    $free_delivery = isset($_POST['free_delivery']) ? 1 : 0;
    $min_amount = isset($_POST['min_amount']) ? (float)$_POST['min_amount'] : 0;
    $max_discount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : NULL;
    $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : NULL;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($code !== '' && ($discount_value > 0 || $free_delivery)) {
        $stmt = $conn->prepare("
            INSERT INTO coupons (code, discount_type, discount_value, free_delivery, min_amount, max_discount, usage_limit, start_date, end_date, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssdiddissi", $code, $discount_type, $discount_value, $free_delivery, $min_amount, $max_discount, $usage_limit, $start_date, $end_date, $is_active);
        
        if ($stmt->execute()) {
            header("Location: coupons.php?success=1");
            exit;
        } else {
            $error_msg = "خطأ: " . $stmt->error;
        }
        $stmt->close();
    }
}

// حذف كوبون
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM coupons WHERE id = $id");
    header("Location: coupons.php");
    exit;
}

// تغيير حالة الكوبون
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: coupons.php");
    exit;
}

$coupons = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">إدارة الكوبونات والخصومات</h2>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        تم إضافة الكوبون بنجاح!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- فورم إضافة كوبون جديد -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                إضافة كوبون / خصم جديد
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">كود الكوبون</label>
                        <input type="text" name="code" class="form-control" required 
                               placeholder="مثال: SUMMER2024" style="text-transform:uppercase;">
                        <small class="text-muted">يجب أن يكون فريداً</small>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="free_delivery" class="form-check-input" id="freeDeliveryCheck">
                        <label class="form-check-label" for="freeDeliveryCheck">
                            <strong>توصيل مجاني فقط</strong> (بدون خصم على المنتجات)
                        </label>
                    </div>

                    <div id="discountFields">
                        <div class="mb-3">
                            <label class="form-label">نوع الخصم</label>
                            <select name="discount_type" class="form-select" id="discountType">
                                <option value="percentage">نسبة مئوية (%)</option>
                                <option value="fixed">مبلغ ثابت (شيكل)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" id="discountLabel">قيمة الخصم (%)</label>
                            <input type="number" step="0.01" name="discount_value" class="form-control" 
                                   placeholder="مثال: 10" min="0.01" id="discountValue">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الحد الأدنى للطلب (شيكل)</label>
                        <input type="number" step="0.01" name="min_amount" class="form-control" 
                               value="0" min="0" placeholder="0 = بدون حد أدنى">
                    </div>

                    <div class="mb-3" id="maxDiscountDiv" style="display:none;">
                        <label class="form-label">الحد الأقصى للخصم (شيكل)</label>
                        <input type="number" step="0.01" name="max_discount" class="form-control" 
                               min="0" placeholder="فارغ = بدون حد">
                        <small class="text-muted">لنسبة مئوية فقط</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">حد الاستخدام</label>
                        <input type="number" name="usage_limit" class="form-control" 
                               min="1" placeholder="فارغ = غير محدود">
                        <small class="text-muted">عدد المرات المسموح استخدام الكوبون</small>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">تاريخ البدء</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">تاريخ الانتهاء</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck" checked>
                        <label class="form-check-label" for="activeCheck">
                            الكوبون <strong>مفعل</strong>
                        </label>
                    </div>

                    <button type="submit" name="add" class="btn btn-primary w-100">
                        حفظ الكوبون
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- جدول الكوبونات -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">قائمة الكوبونات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>الكود</th>
                                <th>نوع الخصم</th>
                                <th>القيمة</th>
                                <th>الحد الأدنى</th>
                                <th>الاستخدام</th>
                                <th>الحالة</th>
                                <th>الصلاحية</th>
                                <th class="text-center">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($coupons->num_rows > 0): ?>
                            <?php while($c = $coupons->fetch_assoc()): 
                                $today = date('Y-m-d');
                                $is_valid = true;
                                $validity_msg = '';
                                
                                if ($c['start_date'] && $c['start_date'] > $today) {
                                    $is_valid = false;
                                    $validity_msg = 'لم يبدأ بعد';
                                } elseif ($c['end_date'] && $c['end_date'] < $today) {
                                    $is_valid = false;
                                    $validity_msg = 'منتهي الصلاحية';
                                } elseif ($c['usage_limit'] && $c['used_count'] >= $c['usage_limit']) {
                                    $is_valid = false;
                                    $validity_msg = 'تم استنفاد الاستخدام';
                                } else {
                                    $validity_msg = 'صالح';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?php echo htmlspecialchars($c['code']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($c['discount_type'] == 'percentage'): ?>
                                            <span class="badge bg-info">نسبة مئوية</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">مبلغ ثابت</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($c['free_delivery'])) {
                                            echo '<span class="badge bg-success">توصيل مجاني</span>';
                                            if ($c['discount_value'] > 0) {
                                                echo '<br>';
                                                if ($c['discount_type'] == 'percentage') {
                                                    echo $c['discount_value'] . '%';
                                                } else {
                                                    echo $c['discount_value'] . ' شيكل';
                                                }
                                            }
                                        } else {
                                            if ($c['discount_type'] == 'percentage') {
                                                echo $c['discount_value'] . '%';
                                                if ($c['max_discount']) {
                                                    echo '<br><small class="text-muted">حد أقصى: ' . $c['max_discount'] . ' شيكل</small>';
                                                }
                                            } else {
                                                echo $c['discount_value'] . ' شيكل';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $c['min_amount'] > 0 ? $c['min_amount'] . ' شيكل' : '<span class="text-muted">لا يوجد</span>'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($c['usage_limit']) {
                                            echo $c['used_count'] . ' / ' . $c['usage_limit'];
                                        } else {
                                            echo $c['used_count'] . ' / <span class="text-muted">∞</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($c['is_active'] && $is_valid): ?>
                                            <span class="badge bg-success">مفعل</span>
                                        <?php elseif (!$c['is_active']): ?>
                                            <span class="badge bg-secondary">معطل</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?php echo $validity_msg; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($c['start_date']): ?>
                                                من: <?php echo $c['start_date']; ?><br>
                                            <?php endif; ?>
                                            <?php if ($c['end_date']): ?>
                                                إلى: <?php echo $c['end_date']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">بدون انتهاء</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="?toggle=1&id=<?php echo $c['id']; ?>" 
                                               class="btn btn-outline-<?php echo $c['is_active'] ? 'warning' : 'success'; ?>"
                                               onclick="return confirm('<?php echo $c['is_active'] ? 'تعطيل' : 'تفعيل'; ?> الكوبون؟');">
                                                <?php echo $c['is_active'] ? 'تعطيل' : 'تفعيل'; ?>
                                            </a>
                                            <a href="?delete=<?php echo $c['id']; ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('هل أنت متأكد من حذف هذا الكوبون؟');">
                                                حذف
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    لا توجد كوبونات بعد.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('freeDeliveryCheck').addEventListener('change', function() {
    const discountFields = document.getElementById('discountFields');
    const discountValue = document.getElementById('discountValue');
    
    if (this.checked) {
        discountFields.style.display = 'none';
        discountValue.removeAttribute('required');
    } else {
        discountFields.style.display = 'block';
        discountValue.setAttribute('required', 'required');
    }
});

document.getElementById('discountType').addEventListener('change', function() {
    const type = this.value;
    const label = document.getElementById('discountLabel');
    const maxDiv = document.getElementById('maxDiscountDiv');
    
    if (type === 'percentage') {
        label.textContent = 'قيمة الخصم (%)';
        maxDiv.style.display = 'block';
    } else {
        label.textContent = 'قيمة الخصم (شيكل)';
        maxDiv.style.display = 'none';
    }
});
</script>

<?php include 'footer.php'; ?>

