<?php include 'header.php'; ?>

<?php
$orders = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">الطلبات</h2>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">جميع الطلبات</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>رقم الطلب</th>
                        <th>الاسم</th>
                        <th>الهاتف</th>
                        <th>الإجمالي (شيكل)</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th class="text-center">تفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($orders->num_rows > 0): ?>
                    <?php while($o = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $o['id']; ?></td>
                            <td><?php echo htmlspecialchars($o['order_code']); ?></td>
                            <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($o['customer_phone']); ?></td>
                            <td><?php echo $o['total_amount']; ?></td>
                            <td>
                                <?php
                                    $badge_class = 'secondary';
                                    if ($o['status'] == 'جديد') $badge_class = 'warning';
                                    elseif ($o['status'] == 'قيد التجهيز') $badge_class = 'info';
                                    elseif ($o['status'] == 'مكتمل') $badge_class = 'success';
                                    elseif ($o['status'] == 'ملغي') $badge_class = 'danger';
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($o['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $o['created_at']; ?></td>
                            <td class="text-center">
                                <a href="order_view.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    عرض
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            لا توجد طلبات بعد.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
