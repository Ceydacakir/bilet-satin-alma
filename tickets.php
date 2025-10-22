<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - tüm kullanıcılar biletlerim sayfasına erişebilir
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

// Kullanıcının biletlerini al - rol bazlı sorgu
if ($_SESSION['role'] === 'user') {
    // Normal kullanıcı - sadece kendi biletleri
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
               bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers,
               u.full_name as passenger_name, u.email as passenger_email
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_companies bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
} elseif ($_SESSION['role'] === 'company') {
    // Firma admin - kendi firmasının tüm biletleri
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
               bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers,
               u.full_name as passenger_name, u.email as passenger_email
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_companies bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        JOIN users u ON t.user_id = u.id
        WHERE tr.company_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['company_id']]);
} else {
    // Admin - tüm biletler
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
               bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers,
               u.full_name as passenger_name, u.email as passenger_email
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_companies bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        JOIN users u ON t.user_id = u.id
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
}
$tickets = $stmt->fetchAll();
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
                <h2 class="text-white">
                    <i class="fas fa-rocket me-2"></i>Bilet Yönetimi
                </h2>
                <p class="text-muted">
                    <?php if ($_SESSION['role'] === 'user'): ?>
                        Tüm biletlerinizi buradan görüntüleyebilir ve yönetebilirsiniz.
                    <?php elseif ($_SESSION['role'] === 'company'): ?>
                        Firmamızın tüm biletlerini buradan görüntüleyebilir ve yönetebilirsiniz.
                    <?php else: ?>
                        Sistemdeki tüm biletleri buradan görüntüleyebilir ve yönetebilirsiniz.
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">
                            <i class="fas fa-rocket me-1"></i>Mevcut Bakiye
                        </h6>
                        <h4 class="text-primary mb-0"><?php echo formatPrice($_SESSION['balance']); ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- Biletler -->
        <?php if (empty($tickets)): ?>
            <div class="card bg-dark border-secondary">
                <div class="card-body text-center py-5">
                    <i class="fas fa-rocket fa-3x text-muted mb-3"></i>
                    <h5 class="text-white">
                        <?php if ($_SESSION['role'] === 'user'): ?>
                            Henüz Biletiniz Yok
                        <?php elseif ($_SESSION['role'] === 'company'): ?>
                            Henüz Bilet Satışı Yok
                        <?php else: ?>
                            Henüz Bilet Kaydı Yok
                        <?php endif; ?>
                    </h5>
                    <p class="text-muted">
                        <?php if ($_SESSION['role'] === 'user'): ?>
                            İlk biletinizi almak için sefer arayabilirsiniz.
                        <?php elseif ($_SESSION['role'] === 'company'): ?>
                            Müşterileriniz bilet aldığında burada görünecektir.
                        <?php else: ?>
                            Sistem kullanılmaya başlandığında biletler burada görünecektir.
                        <?php endif; ?>
                    </p>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Sefer Ara
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-dark border-secondary h-100">
                            <div class="card-body">
                                <!-- Bilet Başlığı - Geliştirilmiş -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="text-white mb-1">
                                            <i class="fas fa-rocket me-2"></i><?php echo h($ticket['company_name']); ?>
                                        </h5>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-chair me-1"></i>Koltuk: <?php echo h($ticket['seat_numbers']); ?>
                                        </p>
                                        <?php if ($_SESSION['role'] !== 'user'): ?>
                                            <p class="text-muted mb-0">
                                                <i class="fas fa-user me-1"></i>Yolcu: <?php echo h($ticket['passenger_name']); ?>
                                            </p>
                                        <?php endif; ?>
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

                                <!-- Geliştirilmiş Bilgiler -->
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="p-3 bg-secondary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-chair me-2"></i>Koltuk
                                            </h6>
                                            <h4 class="text-primary mb-0"><?php echo h($ticket['seat_numbers']); ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 bg-secondary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-rocket me-2"></i>Firma
                                            </h6>
                                            <h5 class="text-success mb-0"><?php echo h($ticket['company_name']); ?></h5>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Güzergah ve Tarih Bilgileri -->
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="p-3 bg-secondary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-route me-2"></i>Güzergah
                                            </h6>
                                            <h6 class="text-info mb-0"><?php echo h($ticket['departure_city']); ?> → <?php echo h($ticket['destination_city']); ?></h6>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 bg-secondary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-clock me-2"></i>Kalkış
                                            </h6>
                                            <h6 class="text-warning mb-0"><?php echo formatDate($ticket['departure_time'], 'd.m.Y H:i'); ?></h6>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Fiyat Bilgisi -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="p-3 bg-primary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-lira-sign me-2"></i>Toplam Fiyat
                                            </h6>
                                            <h4 class="text-white mb-0"><?php echo formatPrice($ticket['total_price']); ?></h4>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aksiyon Butonları - Geliştirilmiş -->
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($ticket['status'] == 'active'): ?>
                                        <?php if (canCancelTicket($ticket['departure_time'])): ?>
                                            <button type="button" 
                                                    class="btn btn-danger btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#cancelModal"
                                                    data-ticket='<?php echo htmlspecialchars(json_encode([
                                                        'id' => $ticket['id'],
                                                        'departure' => $ticket['departure_city'],
                                                        'destination' => $ticket['destination_city'],
                                                        'date' => formatDate($ticket['departure_time'])
                                                    ]), ENT_QUOTES); ?>'>
                                                <i class="fas fa-times me-1"></i>İptal Et
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>İptal Edilemez
                                            </span>
                                            <small class="d-block text-muted">Kalkışa 1 saatten az kaldı</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger">İptal Edildi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-white">Bilet İptal Onayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Bu bileti iptal etmek istediğinizden emin misiniz?
                    </div>
                    <div class="card bg-dark-surface border border-secondary">
                        <div class="card-body">
                            <p class="mb-2"><strong>Güzergah:</strong> <span id="modalRoute"></span></p>
                            <p class="mb-2"><strong>Koltuk No:</strong> <span id="modalSeat"></span></p>
                            <p class="mb-0"><strong>Tarih:</strong> <span id="modalDate"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <form action="ticket_cancel.php" method="POST">
                        <input type="hidden" name="ticket_id" id="modalTicketId">
                        <button type="submit" class="btn btn-danger">İptal Et</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
    function setTicketData(button) {
        const modal = document.getElementById('cancelModal');
        document.getElementById('modalRoute').textContent = 
            `${button.getAttribute('data-departure')} - ${button.getAttribute('data-destination')}`;
        document.getElementById('modalSeat').textContent = 
            button.getAttribute('data-seat') || 'Koltuk bilgisi bulunamadı';
        document.getElementById('modalDate').textContent = 
            button.getAttribute('data-date');
        document.getElementById('modalTicketId').value = 
            button.getAttribute('data-id');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const cancelModal = document.getElementById('cancelModal');
        if (cancelModal) {
            cancelModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const ticketData = JSON.parse(button.getAttribute('data-ticket'));
                
                document.getElementById('modalRoute').textContent = `${ticketData.departure} - ${ticketData.destination}`;
                document.getElementById('modalSeat').textContent = ticketData.seat;
                document.getElementById('modalDate').textContent = ticketData.date;
                document.getElementById('modalTicketId').value = ticketData.id;
            });
        }
    });
    </script>
</body>
</html>

