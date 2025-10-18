<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

// Firma silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company'])) {
    $company_id = $_POST['company_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Önce seferleri sil
        $stmt = $pdo->prepare("SELECT id FROM trips WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $trips = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($trips as $trip_id) {
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
        setSuccessMessage('Firma ve tüm verileri başarıyla silindi.');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setErrorMessage('Firma silinirken bir hata oluştu.');
    }
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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Yönetimi - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Admin Paneli</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="companies.php">Firmalar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="coupons.php">Kuponlar</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php">Hesabım</a></li>
                            <li><a class="dropdown-item" href="../tickets.php">Biletlerim</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
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
                                                   class="btn btn-outline-info" title="Admin Yönetimi">
                                                    <i class="fas fa-user-shield"></i>
                                                </a>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $company['id']; ?>, '<?php echo h($company['name']); ?>')"
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

