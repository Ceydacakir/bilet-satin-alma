<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];

// Sefer silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trip'])) {
    $trip_id = $_POST['trip_id'];
    
    // Seferin bu firmaya ait olduğunu kontrol et
    $stmt = $pdo->prepare("SELECT id FROM trips WHERE id = ? AND company_id = ?");
    $stmt->execute([$trip_id, $company_id]);
    
    if ($stmt->fetch()) {
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
        
        // Seferi sil
        $stmt = $pdo->prepare("DELETE FROM trips WHERE id = ?");
        $stmt->execute([$trip_id]);
        
        setSuccessMessage('Sefer başarıyla silindi.');
    } else {
        setErrorMessage('Sefer bulunamadı veya silinemez.');
    }
}

// Filtreleme parametreleri
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Seferleri al
$where_conditions = ["t.company_id = ?"];
$params = [$company_id];

if ($status === 'active') {
    $where_conditions[] = "t.departure_time > datetime('now')";
} elseif ($status === 'completed') {
    $where_conditions[] = "t.departure_time <= datetime('now')";
}

if (!empty($search)) {
    $where_conditions[] = "(t.departure_city LIKE ? OR t.destination_city LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id = t.id AND tk.status = 'active') as sold_tickets
    FROM trips t 
    WHERE $where_clause
    ORDER BY t.departure_time DESC
");
$stmt->execute($params);
$trips = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Yönetimi - HopBilet</title>
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
                        <a class="nav-link" href="dashboard.php">Firma Paneli</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="trips.php">Seferler</a>
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
        <!-- Başlık ve Filtreler -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2 class="text-white">
                    <i class="fas fa-route me-2"></i>Sefer Yönetimi
                </h2>
                <p class="text-muted">Firmanıza ait seferleri yönetin</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="trip_add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Yeni Sefer Ekle
                </a>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- Filtreler -->
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Durum</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Tümü</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Tamamlanan</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Arama</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Şehir adı ile arayın..." value="<?php echo h($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-search me-1"></i>Filtrele
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Seferler Tablosu -->
        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <?php if (empty($trips)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-route fa-3x text-muted mb-3"></i>
                        <h5 class="text-white">Sefer Bulunamadı</h5>
                        <p class="text-muted">Arama kriterlerinize uygun sefer bulunmamaktadır.</p>
                        <a href="trip_add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>İlk Seferinizi Oluşturun
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Güzergah</th>
                                    <th>Kalkış Tarihi</th>
                                    <th>Kalkış Saati</th>
                                    <th>Varış Saati</th>
                                    <th>Fiyat</th>
                                    <th>Doluluk</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips as $trip): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($trip['departure_city']); ?></strong> - 
                                            <?php echo h($trip['destination_city']); ?>
                                        </td>
                                        <td><?php echo formatDate($trip['departure_time'], 'd.m.Y'); ?></td>
                                        <td><?php echo formatDate($trip['departure_time'], 'H:i'); ?></td>
                                        <td><?php echo formatDate($trip['arrival_time'], 'H:i'); ?></td>
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
                                            if ($departure > $now) {
                                                echo '<span class="badge bg-success">Aktif</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">Tamamlandı</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="trip_edit.php?id=<?php echo $trip['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $trip['id']; ?>, '<?php echo h($trip['departure_city'] . ' - ' . $trip['destination_city']); ?>')"
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
                    <h5 class="modal-title text-white">Sefer Sil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white">Aşağıdaki seferi silmek istediğinizden emin misiniz?</p>
                    <p class="text-warning" id="tripName"></p>
                    <p class="text-muted">Bu işlem geri alınamaz ve tüm biletler iptal edilir.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="trip_id" id="deleteTripId">
                        <input type="hidden" name="delete_trip" value="1">
                        <button type="submit" class="btn btn-danger">Evet, Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        function confirmDelete(tripId, tripName) {
            document.getElementById('deleteTripId').value = tripId;
            document.getElementById('tripName').textContent = tripName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>

