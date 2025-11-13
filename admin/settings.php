<?php include 'header.php'; ?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

$current_whatsapp = get_setting('whatsapp_number');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">الإعدادات العامة</h2>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">إعداد رقم الواتساب</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success py-2">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

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
</div>

<?php include 'footer.php'; ?>
