<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];

// Kupon silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    $coupon_id = $_POST['coupon_id'];
    
    // Kuponun bu firmaya ait olduğunu kontrol et
    $stmt = $pdo->prepare("SELECT id FROM coupons WHERE id = ? AND company_id = ?");
    $stmt->execute([$coupon_id, $company_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->execute([$coupon_id]);
        
        setSuccessMessage('Kupon başarıyla silindi.');
    } else {
        setErrorMessage('Kupon bulunamadı veya silinemez.');
    }
}

// Kuponları al
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM user_coupons uc WHERE uc.coupon_id = c.id) as usage_count
    FROM coupons c 
    WHERE c.company_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$company_id]);
$coupons = $stmt->fetchAll();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <!-- Başlık -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="text-white">
                <i class="fas fa-tags me-2"></i>Kupon Yönetimi
            </h2>
            <p class="text-muted">Firmanıza özel indirim kuponları oluşturun ve yönetin</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="coupon_add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Yeni Kupon Oluştur
            </a>
        </div>
    </div>
    <!-- Mesajlar -->
    <?php displayMessages(); ?>
    <!-- Kuponlar Tablosu -->
    <div class="card bg-dark border-secondary">
        <div class="card-body">
            <?php if (empty($coupons)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5 class="text-white">Henüz Kupon Yok</h5>
                    <p class="text-muted">Müşterileriniz için indirim kuponları oluşturun.</p>
                    <a href="coupon_add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>İlk Kuponunuzu Oluşturun
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Kupon Kodu</th>
                                <th>İndirim Oranı</th>
                                <th>Kullanım Limiti</th>
                                <th>Kullanılan</th>
                                <th>Son Kullanma</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $coupon): ?>
                                <tr>
                                    <td>
                                        <code class="text-primary"><?php echo h($coupon['code']); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">%<?php echo $coupon['discount']; ?></span>
                                    </td>
                                    <td><?php echo $coupon['usage_limit']; ?></td>
                                    <td>
                                        <span class="<?php echo $coupon['usage_count'] >= $coupon['usage_limit'] ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo $coupon['usage_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($coupon['expire_date']) {
                                            $expire_date = new DateTime($coupon['expire_date']);
                                            $now = new DateTime();
                                            $is_expired = $expire_date < $now;
                                            $expire_class = $is_expired ? 'text-danger' : 'text-muted';
                                            echo '<span class="' . $expire_class . '">' . formatDate($coupon['expire_date'], 'd.m.Y') . '</span>';
                                        } else {
                                            echo '<span class="text-muted">Sınırsız</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $is_expired = $coupon['expire_date'] && new DateTime($coupon['expire_date']) < new DateTime();
                                        $is_limit_reached = $coupon['usage_count'] >= $coupon['usage_limit'];
                                        
                                        if ($is_expired) {
                                            echo '<span class="badge bg-danger">Süresi Dolmuş</span>';
                                        } elseif ($is_limit_reached) {
                                            echo '<span class="badge bg-warning">Limit Dolmuş</span>';
                                        } else {
                                            echo '<span class="badge bg-success">Aktif</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="coupon_edit.php?id=<?php echo $coupon['id']; ?>" 
                                               class="btn btn-outline-primary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="confirmDelete('<?php echo $coupon['id']; ?>', '<?php echo h($coupon['code']); ?>')"
                                                    title="Sil">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
<!-- Silme Onay Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header">
                <h5 class="modal-title text-white">Kupon Sil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-white">Aşağıdaki kuponu silmek istediğinizden emin misiniz?</p>
                <p class="text-warning" id="couponCode"></p>
                <p class="text-muted">Bu işlem geri alınamaz.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="coupon_id" id="deleteCouponId">
                    <input type="hidden" name="delete_coupon" value="1">
                    <button type="submit" class="btn btn-danger">Evet, Sil</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    function confirmDelete(couponId, couponCode) {
        document.getElementById('deleteCouponId').value = couponId;
        document.getElementById('couponCode').textContent = couponCode;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>
</body>
</html>

