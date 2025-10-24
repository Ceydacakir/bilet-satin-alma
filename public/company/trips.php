<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];

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

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
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
                                         $occupancy = getTripOccupancy($pdo, $trip['id']);
                                         $occupancy_class = $occupancy['percentage'] > 80 ? 'text-danger' : ($occupancy['percentage'] > 50 ? 'text-warning' : 'text-success'); ?>
                                        <span class="<?php echo $occupancy_class; ?>">
                                            <?php echo $occupancy['occupied']; ?>/<?php echo $occupancy['capacity']; ?>
                                            (<?php echo $occupancy['percentage']; ?>%)
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
                                                    onclick="confirmDelete('<?php echo $trip['id']; ?>', '<?php echo h($trip['departure_city'] . ' - ' . $trip['destination_city']); ?>')"
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
    function confirmDelete(tripId, tripName) {
        document.getElementById('deleteTripId').value = tripId;
        document.getElementById('tripName').textContent = tripName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>
</body>
</html>

