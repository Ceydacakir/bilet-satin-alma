<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Admin kontrolü
requireRole('admin');

// Kupon silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    $coupon_id = $_POST['coupon_id'];
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    if ($stmt->execute([$coupon_id])) {
        setSuccessMessage('Kupon silindi.');
    } else {
        setErrorMessage('Kupon silinemedi.');
    }
    header('Location: coupons.php');
    exit();
}

// Kuponları al
$stmt = $pdo->query("\n    SELECT c.*, bc.name AS company_name,\n           (SELECT COUNT(*) FROM user_coupons uc WHERE uc.coupon_id = c.id) AS usage_count\n    FROM coupons c\n    LEFT JOIN bus_companies bc ON c.company_id = bc.id\n    ORDER BY c.created_at DESC\n");
$coupons = $stmt->fetchAll();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="text-white"><i class="fas fa-tags me-2"></i>Kupon Yönetimi</h2>
            <p class="text-muted">Tüm firmalar için kuponları yönetin</p>
        </div>
        <a href="coupon_add.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Yeni Kupon</a>
    </div>
    <?php displayMessages(); ?>
    <div class="card bg-dark border-secondary">
        <div class="card-body">
            <?php if (empty($coupons)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5 class="text-white">Kupon Yok</h5>
                    <p class="text-muted">Yeni kupon oluşturabilirsiniz.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Kod</th>
                                <th>İndirim</th>
                                <th>Firma</th>
                                <th>Kullanım</th>
                                <th>Son Tarih</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $c): ?>
                                <tr>
                                    <td><code class="text-primary"><?php echo h($c['code']); ?></code></td>
                                    <td><span class="badge bg-success">%<?php echo (float)$c['discount']; ?></span></td>
                                    <td><?php echo $c['company_name'] ? h($c['company_name']) : '<span class="text-info">Genel</span>'; ?></td>
                                    <td><?php echo (int)$c['usage_count']; ?> / <?php echo (int)$c['usage_limit']; ?></td>
                                    <td><?php echo $c['expire_date'] ? h(date('d.m.Y', strtotime($c['expire_date']))) : '<span class="text-muted">Sınırsız</span>'; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="coupon_add.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-primary" title="Düzenle"><i class="fas fa-edit"></i></a>
                                            <form method="post" style="display:inline">
                                                <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" name="delete_coupon" class="btn btn-outline-danger" title="Sil"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



