<?php include 'header.php'; ?>

<?php
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['whatsapp_number'])) {
        $whatsapp = trim($_POST['whatsapp_number']);
        $stmt = $conn->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES ('whatsapp_number', ?) 
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        $stmt->bind_param("s", $whatsapp);
        $stmt->execute();
        $stmt->close();
        $message = "تم تحديث رقم الواتساب بنجاح.";
    }
    
    if (isset($_POST['delivery_fee'])) {
        $delivery_fee = (float)$_POST['delivery_fee'];
        $stmt = $conn->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES ('delivery_fee', ?) 
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        $stmt->bind_param("d", $delivery_fee);
        $stmt->execute();
        $stmt->close();
        $message = "تم تحديث الإعدادات بنجاح.";
    }
}

$current_whatsapp = get_setting('whatsapp_number');
$current_delivery_fee = get_setting('delivery_fee');
if ($current_delivery_fee === null) $current_delivery_fee = 0;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">الإعدادات العامة</h2>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">إعداد رقم الواتساب</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">رقم الواتساب (بدون + أو 00)</label>
                        <input type="text" name="whatsapp_number" class="form-control"
                               value="<?php echo htmlspecialchars($current_whatsapp); ?>" required
                               placeholder="مثال: 9725XXXXXXXX">
                        <small class="text-muted">
                            سيتم إرسال الطلبات إلى هذا الرقم باستخدام رابط <code>wa.me</code>.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-success">
                        حفظ الإعدادات
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">إعداد سعر التوصيل</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">سعر التوصيل (شيكل)</label>
                        <input type="number" step="0.01" name="delivery_fee" class="form-control"
                               value="<?php echo $current_delivery_fee; ?>" required
                               placeholder="مثال: 10.00" min="0">
                        <small class="text-muted">
                            سيتم إضافة هذا المبلغ إلى جميع الطلبات (ما لم يكن هناك كوبون توصيل مجاني).
                        </small>
                    </div>

                    <button type="submit" class="btn btn-success">
                        حفظ الإعدادات
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
