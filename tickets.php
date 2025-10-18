<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - tüm roller erişebilir ama farklı içerik gösterilir
requireLogin();

// Bilet iptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    
    // Bilet bilgilerini al
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_time 
        FROM tickets t 
        JOIN trips tr ON t.trip_id = tr.id 
        WHERE t.id = ? AND t.user_id = ? AND t.status = 'active'
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $ticket = $stmt->fetch();
    
    if ($ticket) {
        if (canCancelTicket($ticket['departure_time'])) {
            try {
                $pdo->beginTransaction();
                
                // Bilet durumunu güncelle
                $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$ticket_id]);
                
                // Rezerve koltukları sil
                $stmt = $pdo->prepare("DELETE FROM booked_seats WHERE ticket_id = ?");
                $stmt->execute([$ticket_id]);
                
                // Bakiyeyi geri yükle
                updateUserBalance($pdo, $_SESSION['user_id'], $ticket['total_price']);
                $_SESSION['balance'] += $ticket['total_price'];
                
                $pdo->commit();
                
                setSuccessMessage('Bilet başarıyla iptal edildi. Ücret bakiyenize iade edildi.');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                setErrorMessage('Bilet iptal edilirken bir hata oluştu.');
            }
        } else {
            setErrorMessage('Kalkış saatinden 1 saatten az kaldığı için bilet iptal edilemez.');
        }
    } else {
        setErrorMessage('Bilet bulunamadı veya iptal edilemez.');
    }
}

// Kullanıcının rolüne göre farklı veriler al
$tickets = [];
$grouped_tickets = [];
$created_trips = [];

if ($_SESSION['role'] === 'user') {
    // Kullanıcının biletlerini al - gruplandırılmış
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
               bc.name as company_name, GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number) as seat_numbers,
               tr.id as trip_id, tr.price as trip_price, tr.capacity as trip_capacity
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_companies bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        WHERE t.user_id = ?
        GROUP BY t.id
        ORDER BY tr.departure_city, tr.destination_city, tr.departure_time DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll();

    // Biletleri güzergaha göre grupla
    foreach ($tickets as $ticket) {
        $route_key = $ticket['departure_city'] . '-' . $ticket['destination_city'];
        if (!isset($grouped_tickets[$route_key])) {
            $grouped_tickets[$route_key] = [
                'route' => $ticket['departure_city'] . ' - ' . $ticket['destination_city'],
                'tickets' => []
            ];
        }
        $grouped_tickets[$route_key]['tickets'][] = $ticket;
    }
} elseif ($_SESSION['role'] === 'company') {
    // Firma kullanıcısının oluşturduğu seferleri al
    $stmt = $pdo->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id = t.id AND tk.status = 'active') as sold_tickets,
               (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id = t.id) as total_tickets
        FROM trips t 
        WHERE t.company_id = ?
        ORDER BY t.departure_time DESC
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $created_trips = $stmt->fetchAll();

    // Seferleri güzergaha göre grupla
    foreach ($created_trips as $trip) {
        $route_key = $trip['departure_city'] . '-' . $trip['destination_city'];
        if (!isset($grouped_tickets[$route_key])) {
            $grouped_tickets[$route_key] = [
                'route' => $trip['departure_city'] . ' - ' . $trip['destination_city'],
                'trips' => []
            ];
        }
        $grouped_tickets[$route_key]['trips'][] = $trip;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletlerim - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .seat-map {
            max-width: 100%;
            overflow-x: auto;
        }
        
        .seat-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }
        
        .seat-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .seat {
            width: 35px;
            height: 35px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }
        
        .seat.available {
            background-color: #28a745;
            color: white;
        }
        
        .seat.selected {
            background-color: #007bff;
            color: white;
            border-color: #0056b3;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        
        .seat.occupied {
            background-color: #dc3545;
            color: white;
            cursor: not-allowed;
        }
        
        .seat.driver {
            background-color: #6c757d;
            color: white;
            cursor: not-allowed;
        }
        
        .seat-corridor {
            width: 20px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .seat-legend {
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
        }
        
        .route-group {
            margin-bottom: 2rem;
        }
        
        .route-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 8px 8px 0 0;
        }
    </style>
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">Sefer Ara</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Hesabım</a></li>
                            <li><a class="dropdown-item active" href="tickets.php">Biletlerim</a></li>
                            <?php if ($_SESSION['role'] == 'company'): ?>
                                <li><a class="dropdown-item" href="company/dashboard.php">Firma Paneli</a></li>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/dashboard.php">Admin Paneli</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Başlık ve Bakiye -->
        <div class="row mb-4">
            <div class="col-md-8">
                <?php if ($_SESSION['role'] === 'user'): ?>
                    <h2 class="text-white">
                        <i class="fas fa-ticket-alt me-2"></i>Biletlerim
                    </h2>
                    <p class="text-muted">Tüm biletlerinizi buradan görüntüleyebilir ve yönetebilirsiniz.</p>
                <?php elseif ($_SESSION['role'] === 'company'): ?>
                    <h2 class="text-white">
                        <i class="fas fa-route me-2"></i>Oluşturduğum Seferler
                    </h2>
                    <p class="text-muted">Firmanıza ait seferleri buradan görüntüleyebilir ve yönetebilirsiniz.</p>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <?php if ($_SESSION['role'] === 'user'): ?>
                            <h6 class="text-muted mb-1">Mevcut Bakiye</h6>
                            <h4 class="text-primary mb-0"><?php echo formatPrice($_SESSION['balance']); ?></h4>
                        <?php elseif ($_SESSION['role'] === 'company'): ?>
                            <h6 class="text-muted mb-1">Toplam Sefer</h6>
                            <h4 class="text-primary mb-0"><?php echo count($created_trips); ?></h4>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- İçerik -->
        <?php if ($_SESSION['role'] === 'user'): ?>
            <!-- Kullanıcı Biletleri -->
            <?php if (empty($tickets)): ?>
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-white">Henüz Biletiniz Yok</h5>
                        <p class="text-muted">İlk biletinizi almak için sefer arayabilirsiniz.</p>
                        <a href="search.php" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Sefer Ara
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Güzergah Grupları -->
                <?php foreach ($grouped_tickets as $route_key => $route_data): ?>
                    <div class="card bg-dark border-secondary mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-route me-2"></i><?php echo h($route_data['route']); ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($route_data['tickets']); ?> Bilet</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($route_data['tickets'] as $ticket): ?>
                                    <div class="col-lg-6 mb-3">
                                        <div class="card bg-secondary border-light h-100">
                                            <div class="card-body">
                                                <!-- Bilet Başlığı -->
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="text-white mb-1">
                                                            <i class="fas fa-bus me-2"></i><?php echo h($ticket['company_name']); ?>
                                                        </h6>
                                                        <p class="text-muted mb-0 small">
                                                            <i class="fas fa-calendar me-1"></i><?php echo formatDate($ticket['departure_time'], 'd.m.Y H:i'); ?>
                                                        </p>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        switch ($ticket['status']) {
                                                            case 'active':
                                                                $status_class = 'bg-success';
                                                                $status_text = 'Aktif';
                                                                break;
                                                            case 'cancelled':
                                                                $status_class = 'bg-danger';
                                                                $status_text = 'İptal';
                                                                break;
                                                            case 'expired':
                                                                $status_class = 'bg-secondary';
                                                                $status_text = 'Süresi Dolmuş';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    </div>
                                                </div>

                                                <!-- Bilet Bilgileri -->
                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <div class="p-2 bg-dark rounded text-center">
                                                            <h6 class="text-white mb-1 small">
                                                                <i class="fas fa-chair me-1"></i>Koltuk
                                                            </h6>
                                                            <h5 class="text-primary mb-0"><?php echo h($ticket['seat_numbers']); ?></h5>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="p-2 bg-dark rounded text-center">
                                                            <h6 class="text-white mb-1 small">
                                                                <i class="fas fa-lira-sign me-1"></i>Fiyat
                                                            </h6>
                                                            <h5 class="text-success mb-0"><?php echo formatPrice($ticket['total_price']); ?></h5>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Yolculuk Bilgileri -->
                                                <div class="row mb-3">
                                                    <div class="col-6 text-center">
                                                        <small class="text-muted">Kalkış</small>
                                                        <div class="text-white"><?php echo formatDate($ticket['departure_time'], 'H:i'); ?></div>
                                                        <small class="text-muted"><?php echo h($ticket['departure_city']); ?></small>
                                                    </div>
                                                    <div class="col-6 text-center">
                                                        <small class="text-muted">Varış</small>
                                                        <div class="text-white"><?php echo formatDate($ticket['arrival_time'], 'H:i'); ?></div>
                                                        <small class="text-muted"><?php echo h($ticket['destination_city']); ?></small>
                                                    </div>
                                                </div>

                                                <!-- Aksiyon Butonları -->
                                                <div class="d-flex gap-2">
                                                    <?php if ($ticket['status'] == 'active'): ?>
                                                        <?php if (canCancelTicket($ticket['departure_time'])): ?>
                                                            <button class="btn btn-outline-danger btn-sm" 
                                                                    onclick="confirmCancel(<?php echo $ticket['id']; ?>)">
                                                                <i class="fas fa-times me-1"></i>İptal Et
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary btn-sm" disabled 
                                                                    title="Kalkış saatinden 1 saatten az kaldığı için iptal edilemez">
                                                                <i class="fas fa-times me-1"></i>İptal Edilemez
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button class="btn btn-outline-primary btn-sm" 
                                                                onclick="downloadTicket(<?php echo $ticket['id']; ?>)">
                                                            <i class="fas fa-download me-1"></i>PDF İndir
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn btn-outline-info btn-sm" 
                                                            onclick="viewTicketDetails(<?php echo $ticket['id']; ?>, <?php echo $ticket['trip_id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>Detaylar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($_SESSION['role'] === 'company'): ?>
            <!-- Firma Seferleri -->
            <?php if (empty($created_trips)): ?>
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-route fa-3x text-muted mb-3"></i>
                        <h5 class="text-white">Henüz Sefer Oluşturmadınız</h5>
                        <p class="text-muted">İlk seferinizi oluşturmak için aşağıdaki butona tıklayın.</p>
                        <a href="company/trip_add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Yeni Sefer Ekle
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Güzergah Grupları -->
                <?php foreach ($grouped_tickets as $route_key => $route_data): ?>
                    <div class="card bg-dark border-secondary mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-route me-2"></i><?php echo h($route_data['route']); ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($route_data['trips']); ?> Sefer</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($route_data['trips'] as $trip): ?>
                                    <div class="col-lg-6 mb-3">
                                        <div class="card bg-secondary border-light h-100">
                                            <div class="card-body">
                                                <!-- Sefer Başlığı -->
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="text-white mb-1">
                                                            <i class="fas fa-bus me-2"></i>Sefer #<?php echo $trip['id']; ?>
                                                        </h6>
                                                        <p class="text-muted mb-0 small">
                                                            <i class="fas fa-calendar me-1"></i><?php echo formatDate($trip['departure_time'], 'd.m.Y H:i'); ?>
                                                        </p>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php
                                                        $now = new DateTime();
                                                        $departure = new DateTime($trip['departure_time']);
                                                        if ($departure > $now) {
                                                            echo '<span class="badge bg-success">Aktif</span>';
                                                        } else {
                                                            echo '<span class="badge bg-secondary">Tamamlandı</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <!-- Sefer Bilgileri -->
                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <div class="p-2 bg-dark rounded text-center">
                                                            <h6 class="text-white mb-1 small">
                                                                <i class="fas fa-lira-sign me-1"></i>Fiyat
                                                            </h6>
                                                            <h5 class="text-primary mb-0"><?php echo formatPrice($trip['price']); ?></h5>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="p-2 bg-dark rounded text-center">
                                                            <h6 class="text-white mb-1 small">
                                                                <i class="fas fa-users me-1"></i>Doluluk
                                                            </h6>
                                                            <h5 class="text-success mb-0"><?php echo $trip['sold_tickets']; ?>/<?php echo $trip['capacity']; ?></h5>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Yolculuk Bilgileri -->
                                                <div class="row mb-3">
                                                    <div class="col-6 text-center">
                                                        <small class="text-muted">Kalkış</small>
                                                        <div class="text-white"><?php echo formatDate($trip['departure_time'], 'H:i'); ?></div>
                                                        <small class="text-muted"><?php echo h($trip['departure_city']); ?></small>
                                                    </div>
                                                    <div class="col-6 text-center">
                                                        <small class="text-muted">Varış</small>
                                                        <div class="text-white"><?php echo formatDate($trip['arrival_time'], 'H:i'); ?></div>
                                                        <small class="text-muted"><?php echo h($trip['destination_city']); ?></small>
                                                    </div>
                                                </div>

                                                <!-- Doluluk Oranı -->
                                                <div class="mb-3">
                                                    <small class="text-muted">Doluluk Oranı</small>
                                                    <div class="progress mt-1">
                                                        <?php 
                                                        $occupancy = ($trip['sold_tickets'] / $trip['capacity']) * 100;
                                                        $progress_class = $occupancy > 80 ? 'bg-success' : ($occupancy > 50 ? 'bg-warning' : 'bg-danger');
                                                        ?>
                                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                                             style="width: <?php echo $occupancy; ?>%">
                                                            <?php echo number_format($occupancy, 1); ?>%
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Aksiyon Butonları -->
                                                <div class="d-flex gap-2">
                                                    <a href="company/trip_edit.php?id=<?php echo $trip['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-edit me-1"></i>Düzenle
                                                    </a>
                                                    
                                                    <button class="btn btn-outline-info btn-sm" 
                                                            onclick="viewTripDetails(<?php echo $trip['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>Detaylar
                                                    </button>
                                                    
                                                    <button class="btn btn-outline-danger btn-sm" 
                                                            onclick="confirmDeleteTrip(<?php echo $trip['id']; ?>, '<?php echo h($trip['departure_city'] . ' - ' . $trip['destination_city']); ?>')">
                                                        <i class="fas fa-trash me-1"></i>Sil
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- İptal Onay Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Bilet İptal Et</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white">Bu bilet iptal edilecek ve ücret bakiyenize iade edilecektir. Emin misiniz?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hayır</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="ticket_id" id="cancelTicketId">
                        <input type="hidden" name="cancel_ticket" value="1">
                        <button type="submit" class="btn btn-danger">Evet, İptal Et</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bilet Detayları Modal -->
    <div class="modal fade" id="ticketDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-ticket-alt me-2"></i>Bilet Detayları
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="ticketDetailsContent">
                        <!-- İçerik JavaScript ile doldurulacak -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sefer Detayları Modal -->
    <div class="modal fade" id="tripDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-route me-2"></i>Sefer Detayları
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="tripDetailsContent">
                        <!-- İçerik JavaScript ile doldurulacak -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        function confirmCancel(ticketId) {
            document.getElementById('cancelTicketId').value = ticketId;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        function downloadTicket(ticketId) {
            // PDF indirme işlemi burada yapılacak
            window.open('download_ticket.php?id=' + ticketId, '_blank');
        }

        function viewTicketDetails(ticketId, tripId) {
            // AJAX ile bilet detaylarını al
            fetch(`get_ticket_details.php?ticket_id=${ticketId}&trip_id=${tripId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTicketDetails(data.ticket, data.trip, data.booked_seats);
                        new bootstrap.Modal(document.getElementById('ticketDetailsModal')).show();
                    } else {
                        alert('Bilet detayları alınamadı: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bilet detayları alınırken bir hata oluştu.');
                });
        }

        function displayTicketDetails(ticket, trip, bookedSeats) {
            const content = document.getElementById('ticketDetailsContent');
            
            // Bilet bilgileri
            const ticketInfo = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-secondary border-light">
                            <div class="card-body">
                                <h6 class="text-white mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Bilet Bilgileri
                                </h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Bilet No:</small>
                                        <div class="text-white">#${ticket.id}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Durum:</small>
                                        <div class="text-white">
                                            <span class="badge ${ticket.status === 'active' ? 'bg-success' : 'bg-danger'}">
                                                ${ticket.status === 'active' ? 'Aktif' : 'İptal'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Toplam Fiyat:</small>
                                        <div class="text-primary fw-bold">${formatPrice(ticket.total_price)}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Satın Alma Tarihi:</small>
                                        <div class="text-white">${formatDate(ticket.created_at)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-secondary border-light">
                            <div class="card-body">
                                <h6 class="text-white mb-3">
                                    <i class="fas fa-route me-2"></i>Sefer Bilgileri
                                </h6>
                                <div class="text-center mb-3">
                                    <div class="text-white fw-bold">${trip.departure_city} → ${trip.destination_city}</div>
                                    <small class="text-muted">${trip.company_name}</small>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-center">
                                        <small class="text-muted">Kalkış</small>
                                        <div class="text-white">${formatDate(trip.departure_time, 'H:i')}</div>
                                        <small class="text-muted">${formatDate(trip.departure_time, 'd.m.Y')}</small>
                                    </div>
                                    <div class="col-6 text-center">
                                        <small class="text-muted">Varış</small>
                                        <div class="text-white">${formatDate(trip.arrival_time, 'H:i')}</div>
                                        <small class="text-muted">${formatDate(trip.arrival_time, 'd.m.Y')}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Koltuk haritası
            const seatMap = `
                <div class="card bg-secondary border-light">
                    <div class="card-body">
                        <h6 class="text-white mb-3">
                            <i class="fas fa-chair me-2"></i>Koltuk Durumu
                        </h6>
                        <div class="seat-map">
                            <div class="seat-grid mb-3">
                                ${generateSeatMap(trip.capacity, bookedSeats, ticket.seat_numbers)}
                            </div>
                            <div class="seat-legend">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="seat available me-2"></div>
                                            <span class="text-muted">Müsait</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="seat selected me-2"></div>
                                            <span class="text-muted">Sizin Koltuklarınız</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="seat occupied me-2"></div>
                                            <span class="text-muted">Dolu</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="seat driver me-2"></div>
                                            <span class="text-muted">Şoför</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            content.innerHTML = ticketInfo + seatMap;
        }

        function generateSeatMap(capacity, bookedSeats, userSeats) {
            const userSeatsArray = userSeats ? userSeats.split(',').map(s => parseInt(s.trim())) : [];
            let html = '';
            
            // 2+2 düzeninde koltuk haritası
            const rows = Math.ceil(capacity / 4);
            
            for (let row = 1; row <= rows; row++) {
                html += '<div class="seat-row mb-2">';
                
                // Sol taraf (2 koltuk)
                for (let col = 1; col <= 2; col++) {
                    const seatNum = (row - 1) * 4 + col;
                    if (seatNum <= capacity) {
                        const seatClass = getSeatClass(seatNum, bookedSeats, userSeatsArray);
                        html += `<div class="seat ${seatClass}" data-seat="${seatNum}">${seatNum}</div>`;
                    }
                }
                
                // Koridor
                html += '<div class="seat-corridor"></div>';
                
                // Sağ taraf (2 koltuk)
                for (let col = 3; col <= 4; col++) {
                    const seatNum = (row - 1) * 4 + col;
                    if (seatNum <= capacity) {
                        const seatClass = getSeatClass(seatNum, bookedSeats, userSeatsArray);
                        html += `<div class="seat ${seatClass}" data-seat="${seatNum}">${seatNum}</div>`;
                    }
                }
                
                html += '</div>';
            }
            
            return html;
        }

        function getSeatClass(seatNum, bookedSeats, userSeats) {
            if (userSeats.includes(seatNum)) {
                return 'selected';
            } else if (bookedSeats.includes(seatNum)) {
                return 'occupied';
            } else {
                return 'available';
            }
        }

        function formatDate(dateString, format = 'd.m.Y H:i') {
            const date = new Date(dateString);
            if (format === 'd.m.Y H:i') {
                return date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'});
            } else if (format === 'H:i') {
                return date.toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'});
            } else if (format === 'd.m.Y') {
                return date.toLocaleDateString('tr-TR');
            }
            return dateString;
        }

        // Firma kullanıcıları için sefer detayları
        function viewTripDetails(tripId) {
            // AJAX ile sefer detaylarını al
            fetch(`get_trip_details.php?trip_id=${tripId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTripDetails(data.trip, data.booked_seats);
                        new bootstrap.Modal(document.getElementById('tripDetailsModal')).show();
                    } else {
                        alert('Sefer detayları alınamadı: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Sefer detayları alınırken bir hata oluştu.');
                });
        }

        function displayTripDetails(trip, bookedSeats) {
            const content = document.getElementById('tripDetailsContent');
            
            const tripInfo = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-secondary border-light">
                            <div class="card-body">
                                <h6 class="text-white mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Sefer Bilgileri
                                </h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Sefer No:</small>
                                        <div class="text-white">#${trip.id}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Fiyat:</small>
                                        <div class="text-primary fw-bold">${formatPrice(trip.price)}</div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Kapasite:</small>
                                        <div class="text-white">${trip.capacity} koltuk</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Dolu Koltuk:</small>
                                        <div class="text-white">${bookedSeats.length} koltuk</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-secondary border-light">
                            <div class="card-body">
                                <h6 class="text-white mb-3">
                                    <i class="fas fa-route me-2"></i>Güzergah
                                </h6>
                                <div class="text-center mb-3">
                                    <div class="text-white fw-bold">${trip.departure_city} → ${trip.destination_city}</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 text-center">
                                        <small class="text-muted">Kalkış</small>
                                        <div class="text-white">${formatDate(trip.departure_time, 'H:i')}</div>
                                        <small class="text-muted">${formatDate(trip.departure_time, 'd.m.Y')}</small>
                                    </div>
                                    <div class="col-6 text-center">
                                        <small class="text-muted">Varış</small>
                                        <div class="text-white">${formatDate(trip.arrival_time, 'H:i')}</div>
                                        <small class="text-muted">${formatDate(trip.arrival_time, 'd.m.Y')}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Koltuk haritası
            const seatMap = `
                <div class="card bg-secondary border-light">
                    <div class="card-body">
                        <h6 class="text-white mb-3">
                            <i class="fas fa-chair me-2"></i>Koltuk Durumu
                        </h6>
                        <div class="seat-map">
                            <div class="seat-grid mb-3">
                                ${generateSeatMap(trip.capacity, bookedSeats, [])}
                            </div>
                            <div class="seat-legend">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="seat available me-2"></div>
                                            <span class="text-muted">Müsait</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="seat occupied me-2"></div>
                                            <span class="text-muted">Dolu</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="seat driver me-2"></div>
                                            <span class="text-muted">Şoför</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            content.innerHTML = tripInfo + seatMap;
        }

        function confirmDeleteTrip(tripId, tripName) {
            if (confirm(`"${tripName}" seferini silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve tüm biletler iptal edilir.`)) {
                // Form oluştur ve gönder
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'company/trip_delete.php';
                
                const tripIdInput = document.createElement('input');
                tripIdInput.type = 'hidden';
                tripIdInput.name = 'trip_id';
                tripIdInput.value = tripId;
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_trip';
                deleteInput.value = '1';
                
                form.appendChild(tripIdInput);
                form.appendChild(deleteInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

