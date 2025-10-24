<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Admin kontrolü
requireRole('admin');

// Firma silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company'])) {
    $company_id = $_POST['company_id'];
    
    try {
        $pdo->beginTransaction();

        // Firmanın tüm seferlerini çek
        $stmt = $pdo->prepare("SELECT id FROM trips WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $trips = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $total_refund_amount = 0;
        $total_cancelled_tickets = 0;

        foreach ($trips as $trip_id) {

            // Sefer tamamlanmışsa iade yapılmayacak
            $trip_completed = isTripCompleted($pdo, $trip_id);

            // Sadece tamamlanmamış seferlerdeki aktif biletleri al
            $stmt = $pdo->prepare("
                SELECT id, user_id, total_price 
                FROM tickets 
                WHERE trip_id = ? AND status = 'active'
            ");
            $stmt->execute([$trip_id]);
            $active_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$trip_completed) {
                foreach ($active_tickets as $ticket) {
                    $user_id = $ticket['user_id'];
                    $refund_amount = (float)$ticket['total_price'];
                    updateUserBalance($pdo, $user_id, $refund_amount);
                    $total_refund_amount += $refund_amount;
                    $total_cancelled_tickets++;
                }

                if (!empty($active_tickets)) {
                    $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE trip_id = ? AND status = 'active'");
                    $stmt->execute([$trip_id]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE tickets SET status = 'expired' WHERE trip_id = ? AND status = 'active'");
                $stmt->execute([$trip_id]);
            }

            // Rezerve koltukları sil
            $stmt = $pdo->prepare("
                DELETE FROM booked_seats WHERE ticket_id IN (
                    SELECT id FROM tickets WHERE trip_id = ?
                )
            ");
            $stmt->execute([$trip_id]);

            // Biletleri sil
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
        }

        // Seferleri sil
        $stmt = $pdo->prepare("DELETE FROM trips WHERE company_id = ?");
        $stmt->execute([$company_id]);
        
        // Kuponları sil
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE company_id = ?");
        $stmt->execute([$company_id]);
        
        // Firma admin kullanıcılarını sil
        $stmt = $pdo->prepare("DELETE FROM users WHERE company_id = ?");
        $stmt->execute([$company_id]);
        
        // Firmayı sil
        $stmt = $pdo->prepare("DELETE FROM bus_companies WHERE id = ?");
        $stmt->execute([$company_id]);

        $pdo->commit();

        $message = "Firma ve tüm verileri başarıyla silindi. "
                 . "Toplam {$total_cancelled_tickets} bilet iptal edilip "
                 . number_format($total_refund_amount, 2) . " TL iade edildi.";
        setSuccessMessage($message);

    } catch (Exception $e) {
        $pdo->rollBack();
        setErrorMessage('Firma silinirken bir hata oluştu: ' . $e->getMessage());
    }

    header('Location: companies.php');
    exit();
}



// Firmaları al
$stmt = $pdo->query("
    SELECT bc.*, 
           COUNT(DISTINCT t.id) as trip_count,
           COUNT(DISTINCT u.id) as user_count,
           COUNT(DISTINCT c.id) as coupon_count
    FROM bus_companies bc
    LEFT JOIN trips t ON bc.id = t.company_id
    LEFT JOIN users u ON bc.id = u.company_id
    LEFT JOIN coupons c ON bc.id = c.company_id
    GROUP BY bc.id
    ORDER BY bc.created_at DESC
");
$companies = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <!-- Başlık -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="text-white">
                <i class="fas fa-building me-2"></i>Firma Yönetimi
            </h2>
            <p class="text-muted">Sistemdeki tüm otobüs firmalarını yönetin</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="company_add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Yeni Firma Ekle
            </a>
        </div>
    </div>
    <!-- Mesajlar -->
    <?php displayMessages(); ?>
    <!-- Firmalar Tablosu -->
    <div class="card bg-dark border-secondary">
        <div class="card-body">
            <?php if (empty($companies)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h5 class="text-white">Henüz Firma Yok</h5>
                    <p class="text-muted">İlk firmayı oluşturmak için yukarıdaki butona tıklayın.</p>
                    <a href="company_add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>İlk Firmayı Oluşturun
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Firma Adı</th>
                                <th>Sefer Sayısı</th>
                                <th>Kullanıcı Sayısı</th>
                                <th>Kupon Sayısı</th>
                                <th>Oluşturulma Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-bus text-primary me-2"></i>
                                            <strong><?php echo h($company['name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $company['trip_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo $company['user_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $company['coupon_count']; ?></span>
                                    </td>
                                    <td><?php echo formatDate($company['created_at'], 'd.m.Y'); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="company_edit.php?id=<?php echo $company['id']; ?>" 
                                               class="btn btn-outline-primary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="company_users.php?id=<?php echo $company['id']; ?>" 
                                               class="btn btn-outline-info" title="Kullanıcılar">
                                                <i class="fas fa-users"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="confirmDelete('<?php echo $company['id']; ?>', '<?php echo h($company['name']); ?>')"
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
                <h5 class="modal-title text-white">Firma Sil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Dikkat!</strong> Bu işlem geri alınamaz.
                </div>
                <p class="text-white">Aşağıdaki firmayı silmek istediğinizden emin misiniz?</p>
                <p class="text-warning" id="companyName"></p>
                <p class="text-muted">Bu işlem ile birlikte:</p>
                <ul class="text-muted">
                    <li>Firmanın tüm seferleri silinecek</li>
                    <li>Firmanın tüm kuponları silinecek</li>
                    <li>Firma admin kullanıcıları silinecek</li>
                    <li>Bu firmaya ait tüm biletler iptal edilecek</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="company_id" id="deleteCompanyId">
                    <input type="hidden" name="delete_company" value="1">
                    <button type="submit" class="btn btn-danger">Evet, Sil</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    function confirmDelete(companyId, companyName) {
        document.getElementById('deleteCompanyId').value = companyId;
        document.getElementById('companyName').textContent = companyName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>
</body>
</html>

