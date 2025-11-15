<?php include 'header.php'; ?>

<?php
if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit;
}
$order_id = (int)$_GET['id'];

// تحديث الحالة
$success_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = trim($_POST['status']);
    
    // جلب الحالة القديمة
    $old_order_stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $old_order_stmt->bind_param("i", $order_id);
    $old_order_stmt->execute();
    $old_order = $old_order_stmt->get_result()->fetch_assoc();
    $old_order_stmt->close();
    
    $old_status = $old_order['status'];
    
    // تحديث الحالة
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();
    $stmt->close();
    
    // جلب عناصر الطلب (سنحتاجها في جميع الحالات)
    $items_stmt = $conn->prepare("
        SELECT product_id, quantity 
        FROM order_items 
        WHERE order_id = ?
    ");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    $items_stmt->close();
    
    // منطق تغيير الحالة:
    // 1. ملغي: إرجاع الكمية (إلغاء العملية) - فقط إذا كان الطلب كان مكتملاً
    // 2. مكتمل: خصم الكمية - فقط إذا لم يكن مكتملاً من قبل
    // 3. جديد: لا شيء، فقط إشعار
    
    if ($new_status == 'ملغي') {
        // إذا كان الطلب مكتملاً من قبل، نرجع الكمية
        if ($old_status == 'مكتمل') {
            $update_stmt = $conn->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE id = ? AND stock_quantity >= 0
            ");
            
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = floatval($item['quantity']);
                $update_stmt->bind_param("di", $qty, $pid);
                $update_stmt->execute();
            }
            
            $update_stmt->close();
            $success_msg = "تم إلغاء الطلب وإرجاع الكمية للمنتجات بنجاح.";
        } else {
            $success_msg = "تم إلغاء الطلب بنجاح.";
        }
    }
    elseif ($new_status == 'مكتمل' && $old_status != 'مكتمل') {
        // خصم الكمية من المنتجات (فقط إذا لم يكن مكتملاً من قبل)
        $update_stmt = $conn->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ? AND stock_quantity > 0
        ");
        
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            $qty = floatval($item['quantity']);
            $update_stmt->bind_param("di", $qty, $pid);
            $update_stmt->execute();
        }
        
        $update_stmt->close();
        $success_msg = "تم تحديث حالة الطلب إلى مكتمل وخصم الكمية من المخزون.";
    }
    elseif ($new_status == 'جديد') {
        // إذا كان الطلب مكتملاً من قبل، نرجع الكمية
        if ($old_status == 'مكتمل') {
            $update_stmt = $conn->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE id = ? AND stock_quantity >= 0
            ");
            
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = floatval($item['quantity']);
                $update_stmt->bind_param("di", $qty, $pid);
                $update_stmt->execute();
            }
            
            $update_stmt->close();
            $success_msg = "تم تحديث حالة الطلب إلى جديد وإرجاع الكمية. (لم يتم خصم الكمية بعد)";
        } else {
            $success_msg = "تم تحديث حالة الطلب إلى جديد. (لم يتم خصم الكمية بعد)";
        }
    }
    else {
        // أي حالة أخرى (مثل قيد التجهيز)
        // إذا كان الطلب مكتملاً من قبل، نرجع الكمية
        if ($old_status == 'مكتمل' && $new_status != 'مكتمل') {
            $update_stmt = $conn->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE id = ? AND stock_quantity >= 0
            ");
            
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = floatval($item['quantity']);
                $update_stmt->bind_param("di", $qty, $pid);
                $update_stmt->execute();
            }
            
            $update_stmt->close();
            $success_msg = "تم تحديث حالة الطلب وإرجاع الكمية.";
        } else {
            $success_msg = "تم تحديث حالة الطلب بنجاح.";
        }
    }
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
$items_stmt = $conn->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">تفاصيل الطلب</h2>
    <a href="orders.php" class="btn btn-sm btn-outline-secondary">عودة للطلبات</a>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

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
                <?php 
                // حساب سعر التوصيل من الإجمالي
                $delivery_fee_setting = (float)get_setting('delivery_fee');
                if ($delivery_fee_setting === null) $delivery_fee_setting = 0;
                
                // نحسب سعر التوصيل من الفرق بين الإجمالي والمنتجات والخصم
                $items_total = 0;
                $items_temp = $conn->query("SELECT SUM(subtotal) as total FROM order_items WHERE order_id = $order_id");
                if ($items_temp && $row = $items_temp->fetch_assoc()) {
                    $items_total = (float)$row['total'];
                }
                
                // الإجمالي = المنتجات - الخصم + التوصيل
                // التوصيل = الإجمالي - المنتجات + الخصم
                $discount = (float)$order['discount_amount'];
                $calculated_delivery = $order['total_amount'] - $items_total + $discount;
                if ($calculated_delivery < 0) $calculated_delivery = 0;
                
                // التحقق من كوبون التوصيل المجاني
                $has_free_delivery = false;
                if (!empty($order['coupon_code'])) {
                    $coupon_check = $conn->prepare("SELECT free_delivery FROM coupons WHERE code = ?");
                    $coupon_check->bind_param("s", $order['coupon_code']);
                    $coupon_check->execute();
                    $coupon_result = $coupon_check->get_result();
                    if ($coupon_row = $coupon_result->fetch_assoc()) {
                        $has_free_delivery = !empty($coupon_row['free_delivery']);
                    }
                    $coupon_check->close();
                }
                ?>
                
                <?php if ($has_free_delivery): ?>
                    <p><strong>التوصيل:</strong> 
                        <span class="text-success">مجاني</span>
                        <small class="text-muted">(كوبون: <?php echo htmlspecialchars($order['coupon_code']); ?>)</small>
                    </p>
                <?php elseif ($calculated_delivery > 0): ?>
                    <p><strong>سعر التوصيل:</strong> 
                        <?php echo number_format($calculated_delivery, 2); ?> شيكل
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($order['coupon_code'])): ?>
                    <p><strong>الكوبون المستخدم:</strong> 
                        <span class="badge bg-info"><?php echo htmlspecialchars($order['coupon_code']); ?></span>
                    </p>
                    <?php if ($order['discount_amount'] > 0): ?>
                        <p><strong>الخصم:</strong> 
                            <span class="text-danger">-<?php echo number_format($order['discount_amount'], 2); ?> شيكل</span>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <p><strong>الإجمالي:</strong> <?php echo number_format($order['total_amount'], 2); ?> شيكل</p>
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
