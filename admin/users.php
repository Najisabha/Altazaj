<?php
require '../db.php';
include 'header.php';

// معالجة قبول / رفض الهوية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['id_action'])) {
    $user_id = (int) $_POST['user_id'];
    $action  = $_POST['id_action'];

    $newStatus = null;
    if ($action === 'approve') {
        $newStatus = 'approved';
    } elseif ($action === 'reject') {
        $newStatus = 'rejected';
    }

    if ($newStatus && $user_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET id_status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // منع إعادة إرسال الفورم عند إعادة تحميل الصفحة
    header("Location: users.php");
    exit;
}

// جلب المستخدمين (MYSQLi)
$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY id DESC");

if ($result) {
    if (method_exists($result, 'fetch_all')) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}
?>

<div class="container-fluid px-4">
    <h3 class="mt-4 mb-4">إدارة المستخدمين</h3>

    <div class="card shadow-sm">
        <div class="card-body">

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>البريد</th>
                            <th>الجوال</th>
                            <th>الدور</th>
                            <th>صورة الهوية</th>
                            <th>حالة الهوية</th>
                            <th>إجراءات</th>
                            <th>تاريخ التسجيل</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['phone']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'success' ?>">
                                        <?= $u['role'] ?>
                                    </span>
                                </td>

                                <!-- صورة الهوية -->
                                <td>
                                    <?php if (!empty($u['national_image'])): ?>
                                        <a href="../uploads/ids/<?= htmlspecialchars($u['national_image']) ?>" target="_blank">
                                            <img src="../uploads/ids/<?= htmlspecialchars($u['national_image']) ?>"
                                                 class="img-thumbnail"
                                                 style="width:60px; height:60px; object-fit:cover;">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">لم يرفع هوية</span>
                                    <?php endif; ?>
                                </td>

                                <!-- حالة الهوية -->
                                <td>
                                    <?php if (empty($u['national_image'])): ?>
                                        <span class="badge bg-secondary">لا يوجد هوية</span>
                                    <?php else: ?>
                                        <?php
                                        $status = $u['id_status'] ?? 'pending';
                                        if ($status === 'approved') {
                                            echo '<span class="badge bg-success">موثّق</span>';
                                        } elseif ($status === 'rejected') {
                                            echo '<span class="badge bg-danger">مرفوضة</span>';
                                        } else {
                                            echo '<span class="badge bg-warning text-dark">قيد المراجعة</span>';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </td>

                                <!-- أزرار قبول / رفض -->
                                <td>
                                    <?php if (!empty($u['national_image'])): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="id_action" value="approve"
                                                    class="btn btn-success btn-sm">
                                                قبول
                                            </button>
                                        </form>

                                        <form method="post" class="d-inline ms-1">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="id_action" value="reject"
                                                    class="btn btn-danger btn-sm">
                                                رفض
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= $u['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-muted">لا يوجد مستخدمون بعد</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
