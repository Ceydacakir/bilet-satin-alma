<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];

// İstatistikleri al
$stats = [];

// Toplam sefer sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE company_id = ?");
$stmt->execute([$company_id]);
$stats['total_trips'] = $stmt->fetchColumn();

// Aktif sefer sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE company_id = ? AND departure_time > datetime('now')");
$stmt->execute([$company_id]);
$stats['active_trips'] = $stmt->fetchColumn();

// Toplam satılan bilet sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tickets t 
    JOIN trips tr ON t.trip_id = tr.id 
    WHERE tr.company_id = ? AND t.status = 'active'
");
$stmt->execute([$company_id]);
$stats['sold_tickets'] = $stmt->fetchColumn();

// Toplam gelir
$stmt = $pdo->prepare("
    SELECT SUM(t.total_price) FROM tickets t 
    JOIN trips tr ON t.trip_id = tr.id 
    WHERE tr.company_id = ? AND t.status = 'active'
");
$stmt->execute([$company_id]);
$stats['total_revenue'] = $stmt->fetchColumn() ?: 0;

// Son seferler
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id = t.id AND tk.status = 'active') as sold_tickets
    FROM trips t 
    WHERE t.company_id = ? 
    ORDER BY t.created_date DESC 
    LIMIT 5
");
$stmt->execute([$company_id]);
$recent_trips = $stmt->fetchAll();

// Firma bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM bus_companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Paneli - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Background Elements -->
    <div class="rockets"></div>
    <div class="space-particles"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-rocket me-2 rocket-icon"></i>HopBilet
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
                        <a class="nav-link active" href="dashboard.php">Firma Paneli</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="trips.php">Seferler</a>
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
                            <li><a class="dropdown-item" href="../account.php">Hesabım</a></li>
                            <li><a class="dropdown-item" href="../tickets.php">Bilet Yönetimi</a></li>
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
            <div class="col-12">
                <h2 class="text-white">
                    <i class="fas fa-rocket me-2 rocket-icon"></i>Firma Paneli
                </h2>
                <p class="text-muted"><?php echo h($company['name']); ?> - Yönetim Paneli 🚀</p>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-route fa-2x text-primary mb-3"></i>
                        <h4 class="text-white"><?php echo $stats['total_trips']; ?></h4>
                        <p class="text-muted mb-0">Toplam Sefer</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x text-success mb-3"></i>
                        <h4 class="text-white"><?php echo $stats['active_trips']; ?></h4>
                        <p class="text-muted mb-0">Aktif Sefer</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-ticket-alt fa-2x text-warning mb-3"></i>
                        <h4 class="text-white"><?php echo $stats['sold_tickets']; ?></h4>
                        <p class="text-muted mb-0">Satılan Bilet</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-lira-sign fa-2x text-info mb-3"></i>
                        <h4 class="text-white"><?php echo formatPrice($stats['total_revenue']); ?></h4>
                        <p class="text-muted mb-0">Toplam Gelir</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Son Seferler -->
            <div class="col-lg-8">
                <div class="card bg-dark border-secondary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-list me-2"></i>Son Seferler
                        </h5>
                        <a href="trips.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Yeni Sefer
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_trips)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-route fa-3x text-muted mb-3"></i>
                                <h6 class="text-white">Henüz Sefer Yok</h6>
                                <p class="text-muted">İlk seferinizi oluşturmak için yukarıdaki butona tıklayın.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Güzergah</th>
                                            <th>Kalkış</th>
                                            <th>Fiyat</th>
                                            <th>Doluluk</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_trips as $trip): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo h($trip['departure_city']); ?></strong> - 
                                                    <?php echo h($trip['destination_city']); ?>
                                                </td>
                                                <td><?php echo formatDate($trip['departure_time'], 'd.m.Y H:i'); ?></td>
                                                <td><?php echo formatPrice($trip['price']); ?></td>
                                                <td>
                                                    <?php 
                                                    $occupancy = ($trip['sold_tickets'] / $trip['capacity']) * 100;
                                                    $occupancy_class = $occupancy > 80 ? 'text-success' : ($occupancy > 50 ? 'text-warning' : 'text-danger');
                                                    ?>
                                                    <span class="<?php echo $occupancy_class; ?>">
                                                        <?php echo $trip['sold_tickets']; ?>/<?php echo $trip['capacity']; ?>
                                                        (<?php echo number_format($occupancy, 1); ?>%)
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $now = new DateTime();
                                                    $departure = new DateTime($trip['departure_time']);
                                                    $status = $departure > $now ? 'Aktif' : 'Tamamlandı';
                                                    $status_class = $departure > $now ? 'text-success' : 'text-muted';
                                                    ?>
                                                    <span class="<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="trip_edit.php?id=<?php echo $trip['id']; ?>" 
                                                           class="btn btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-outline-danger" 
                                                                onclick="confirmDelete(<?php echo $trip['id']; ?>)">
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

            <!-- Hızlı Erişim -->
            <div class="col-lg-4">
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-bolt me-2"></i>Hızlı Erişim
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="trip_add.php" class="btn btn-primary">
                                <i class="fas fa-rocket me-2"></i>Yeni Sefer Ekle
                            </a>
                            <a href="trips.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i>Tüm Seferler
                            </a>
                            <a href="../tickets.php" class="btn btn-outline-success">
                                <i class="fas fa-ticket-alt me-2"></i>Bilet Yönetimi
                            </a>
                            <a href="coupon_add.php" class="btn btn-outline-warning">
                                <i class="fas fa-tag me-2"></i>Kupon Oluştur
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Firma Bilgileri -->
                <div class="card bg-dark border-secondary">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-info-circle me-2"></i>Firma Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Firma Adı</small>
                            <p class="text-white mb-0"><?php echo h($company['name']); ?></p>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Oluşturulma Tarihi</small>
                            <p class="text-white mb-0"><?php echo formatDate($company['created_at'], 'd.m.Y'); ?></p>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted">Firma ID</small>
                            <p class="text-white mb-0">#<?php echo $company['id']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Sefer Sil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white">Bu seferi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" action="trip_delete.php" style="display: inline;">
                        <input type="hidden" name="trip_id" id="deleteTripId">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        function confirmDelete(tripId) {
            document.getElementById('deleteTripId').value = tripId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>

