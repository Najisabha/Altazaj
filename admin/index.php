<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">لوحة التحكم</h2>
    <span class="text-muted small">إدارة متجر الطازج للدواجن واللحوم</span>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="text-muted">إجمالي المنتجات</h6>
                <h3 class="fw-bold text-success"><?php echo $products_count; ?></h3>
                <p class="small text-muted mb-0">عدد المنتجات المتاحة في المتجر</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="text-muted">إجمالي الطلبات</h6>
                <h3 class="fw-bold text-primary"><?php echo $orders_count; ?></h3>
                <p class="small text-muted mb-0">كل الطلبات المسجلة في النظام</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="text-muted">طلبات جديدة</h6>
                <h3 class="fw-bold text-warning"><?php echo $pending_count; ?></h3>
                <p class="small text-muted mb-0">طلبات في حالة "جديد" تحتاج المعالجة</p>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">نصائح سريعة لإدارة المتجر</h5>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>تأكد من إضافة جميع أصناف اللحوم، الدواجن، والمجمّدات مع الأسعار المحدثة.</li>
            <li>راجع صفحة الطلبات بشكل دوري لمعالجة الطلبات الجديدة بسرعة.</li>
            <li>تأكد من صحة رقم الواتساب في صفحة الإعدادات لاستقبال الطلبات بدون مشاكل.</li>
        </ul>
    </div>
</div>
<?php include 'header.php'; ?>

<?php
$orders_count   = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
$products_count = $conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$pending_count  = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'جديد'")->fetch_assoc()['c'];
?>

<?php include 'footer.php'; ?>
