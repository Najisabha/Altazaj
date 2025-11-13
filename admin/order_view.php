<?php include 'header.php'; ?>

<?php
if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit;
}
$order_id = (int)$_GET['id'];

// تحديث الحالة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim($_POST['status']);
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
    $stmt->execute();
    $stmt->close();
}

// جلب الطلب
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();

if (!$order) {
    echo "<div class='alert alert-danger'>الطلب غير موجود.</div>";
    include 'footer.php';
    exit;
}

// جلب عناصر الطلب
$items = $conn->query("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = $order_id
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">تفاصيل الطلب</h2>
    <a href="orders.php" class="btn btn-sm btn-outline-secondary">عودة للطلبات</a>
</div>

<div class="row g-3">
    <!-- بيانات العميل والطلب -->
    <div class="col-md-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0">بيانات الطلب</h5>
            </div>
            <div class="card-body">
                <p><strong>رقم الطلب:</strong> <?php echo htmlspecialchars($order['order_code']); ?></p>
                <p><strong>اسم العميل:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p><strong>رقم الهاتف:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                <p><strong>العنوان:</strong><br><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></p>
                <p><strong>ملاحظات:</strong><br><?php echo $order['note'] ? nl2br(htmlspecialchars($order['note'])) : '<span class="text-muted">لا توجد</span>'; ?></p>
                <p><strong>الإجمالي:</strong> <?php echo $order['total_amount']; ?> شيكل</p>
                <p><strong>تاريخ الطلب:</strong> <?php echo $order['created_at']; ?></p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">حالة الطلب</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-2 align-items-center">
                    <div class="col-8">
                        <select name="status" class="form-select">
                            <option value="جديد"         <?php if($order['status']=='جديد') echo 'selected'; ?>>جديد</option>
                            <option value="قيد التجهيز" <?php if($order['status']=='قيد التجهيز') echo 'selected'; ?>>قيد التجهيز</option>
                            <option value="مكتمل"       <?php if($order['status']=='مكتمل') echo 'selected'; ?>>مكتمل</option>
                            <option value="ملغي"        <?php if($order['status']=='ملغي') echo 'selected'; ?>>ملغي</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary w-100">
                            تحديث الحالة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- المنتجات داخل الطلب -->
    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">المنتجات في الطلب</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>المنتج</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>الإجمالي الفرعي</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($items->num_rows > 0): ?>
                            <?php while($it = $items->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($it['name']); ?></td>
                                    <td><?php echo $it['quantity']; ?></td>
                                    <td><?php echo $it['unit_price']; ?> شيكل</td>
                                    <td><?php echo $it['subtotal']; ?> شيكل</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    لا توجد عناصر في هذا الطلب.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0">
                    * تأكد من مطابقة الكميات والأسعار قبل تجهيز الطلب للعميل.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
